<?php
/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */


if(!isset($argv)){
    die('Arguments needed');
}else{
    process_launcher($argv);
}
use helPHP\libs\Utils;
/**
 * launch process in background and store their progress in redis,
 * it's a subprocess script for helPHP\libs\utils\system_process_with_tracking
 * 
 * must be launch from cli with arguments
 * 
 * can be used also to "force cancel" a job when type of process is "job",
 * it's a special case when we want to create easily some "cancel" button for job in progress
 * 
 * @param array $argv the arguments of the cli command : 
 *      argument Options: 
 *          -k , the key to identify the process in redis
 *          -t , the type of the process
 *          -a , params depending the type
 *          -l , list of files that need to be locked during the process
 *          -i , path to the instance
 * 
 * @see helPHP\libs\utils\system_process_with_tracking
 * 
 * @package helPHP\utils
 */
function process_launcher($argv){
    $cmd = $key = $type = $type_param = $to_lock = false;
    $timestamp_lock = false;
    $redis = true;

    // parse arguments 
    foreach($argv as $index => $arg){

        if ($index == 1){
            
            $cmd = $arg;
            
        } else if ($index > 1){

            $opt = substr($arg,0,2);
            $val = substr($arg,2);
            
            switch($opt){
                case '-k':
                    $key = $val;
                break;
                case '-t':
                    $type = $val;
                break;
                case '-a':
                    $type_param = $val;
                break;
                case '-l':
                    if (str_contains($val,'¤¤¤¤')){
                        $to_lock = explode('¤¤¤¤',$val);
                    } else {
                        $to_lock = [$val];
                    }
                break;
                case '-i':
                    $basePath = $val;
                break;
                case '-r':
                    $redis = intval($val) == 0 ? false : true;
                break;
            }
        }
    }

    if (!is_file($basePath.'config/main.php')){
        die('config file not found in '.$basePath.'config/main.php');
    }
    include_once($basePath.'config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    global $redisproc, $CONFIG;

    if ($redisproc == null && $redis) {
        $redisproc = new Redis();
        $redisproc->connect('redis', 6379);
    }

    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );

    $prev_percent = 0;
    $total_percent = 0;

    $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
    if ($key == '') {
        $key = time().'_'.floor(rand()*10000);
    }

    if ($redis) $redisproc->set($CONFIG::SITE_NAME.'-processes-'.$key, 'start');

    if (is_resource($process)) {
        
        // LOCK THE FILE
        if ($to_lock){
            global $FS;
            $timestamp_lock = time();
            foreach($to_lock as $pathToLock){
                $FS->add_lock($pathToLock, $timestamp_lock);
            }
        }
        
        $read_output = $read_error = false;
        $buffer_len  = $prev_buffer_len = 0;
        $ms          = 10;
        $res         = '';
        $read_output = true;
        $error       = '';
        $read_error  = true;
        $extra_cmd = [];
        
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        
        while ($read_error != false or $read_output != false)
        {
            if ($read_output != false)
            {
                if(feof($pipes[1]))
                {
                    fclose($pipes[1]);
                    $read_output = false;
                }
                else
                {
                    $str = fgets($pipes[1], 256);
                    $len = strlen($str);
                    if ($len) {
                        $res .= $str;
                        $buffer_len += $len;
                        if ($type != '') {
                            $percent = false;
                            switch($type){
                                // case 'rsync':
                                //     $t = preg_split('/\s+/', $str);
                                //     foreach($t as $tt){
                                //         if (str_contains($tt, '%')){
                                //             $percent = substr($tt,0,-1);
                                //         }
                                //     }
                                // break;
                                case 'copy':
                                    $str = trim($str);
                                    $current = intval($str);
                                    if ($current) {
                                        if ($prev_percent == 0) $prev_percent = $current;
                                        if ($prev_percent > $current){
                                            $total_percent += 100;
                                        }
                                        $prev_percent = $current;
                                        $percent = ($total_percent + $current) / $type_param;
                                    }
                                break;
                                case 'pack':
                                    $str = trim($str);
                                    if ($str != '' && str_contains($str, '%')){
                                        $percent = explode('+', $str)[0];
                                        $pos = strpos($percent, '%');
                                        $percent = intval(substr($percent, $pos - 2, $pos));
                                    }
                                break;
                                case 'job':
                                    // \helPHP\libs\Utils::error_log($str);
                                break;
                                default:
                                    $nbFile = $type_param;
                                    $nbLine = explode("\n",trim($res,"\n"));
                                    $nbLine = count($nbLine);
                                    $percent = (100*$nbLine)/$nbFile;
                                break;
                            }

                            if ($percent !== false){
                                $percent = round(floatval($percent) * 100) / 100;
                                if ($redis) $redisproc->set($CONFIG::SITE_NAME.'-processes-'.$key, $percent);
                            }
                            
                            // UPDATE THE LOCK
                            if ($to_lock){
                                $current_timestamp = time();
                                $diff = $current_timestamp - $timestamp_lock;
                                if ($diff > (($CONFIG::TOKEN_MINUTE*60)/2)){
                                    foreach($to_lock as $pathToLock){
                                        $FS->update_lock($pathToLock);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            if ($read_error != false) {
                if(feof($pipes[2])) {
                    fclose($pipes[2]);
                    $read_error = false;
                } else {
                    $str = fgets($pipes[2], 256);
                    $len = strlen($str);
                    if ($len) {
                        if (str_starts_with($str, 'mv:')) {
                            $t = explode("'", $str);
                            $source = $t[1];
                            $dest = $t[3];
                            array_push($extra_cmd, 'rm -rf \''.$dest.'\' && mv \''.$source.'\' \''.$dest.'\'');
                        }
                        $error .= $str;
                        $buffer_len += $len;
                    }
                }
            }
            
            if ($buffer_len > $prev_buffer_len) {
                $prev_buffer_len = $buffer_len;
                $ms = 10;
            } else {
                usleep($ms * 1000); // sleep for $ms milliseconds
                set_time_limit(5);
                if ($ms < 160) {
                    $ms = $ms * 2;
                }
            }
        }
    }

    if ($extra_cmd){
        $cmd = implode(' && ', $extra_cmd);
        sleep(1);
        $res_extra = shell_exec($cmd);
    }

    // UNLOCK THE FILE
    if ($to_lock){
        foreach($to_lock as $pathToLock){
            $FS->delete_lock($pathToLock, $timestamp_lock);
        }
    }

    // when packing, create a temp that we delete at the end of the process
    if ($type == 'pack'){
        $cmd = 'rm "'.$type_param.'"';
        exec($cmd);
    }

    clearstatcache();

    if ($redis) {
        $redisproc->set($CONFIG::SITE_NAME.'-processes-'.$key,'ok!');
        $redisproc->expire($CONFIG::SITE_NAME.'-processes-'.$key,120); //this key will die in two minutes
        $redisproc->expire($CONFIG::SITE_NAME.'-processes-pid-'.$key,120);
        $real_key = substr($key,0,strrpos($key,'¤'));
        $redisproc->expire($CONFIG::SITE_NAME.'-processes-nbr_cmd-'.$real_key,120);
    }

    proc_close($process);

    if ($type == 'job'){
        $shu_pos = strpos($real_key, '¤');
        $target = substr($real_key, 0, $shu_pos);
        $key_id = substr($real_key, $shu_pos + 2); // add 2 to not select the ¤ char (¤ is 2 char length)
        $cmd = 'php '.$CONFIG::HELPHP_FOLDER.'utils/job_manager.php -a"set_status" -t"'.$target.'" -k"'.$key_id.'" -s"2"';
        shell_exec($cmd);
    }
}