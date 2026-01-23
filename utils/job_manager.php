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
use helPHP\libs\DB;
use helPHP\libs\Utils;


if(!isset($argv)){
    die('Arguments needed');
}else{
    job_manager($argv);
}
/**
 * here is our buddy the job manager, made to be run as a command an working with "jobs" database as a job pile.
 * 
 * arguments :
 *  -k , to identify the job
 *  -C , callback when command is done
 *  -c , the command to execute
 *  -f , path to the instance
 *  -a , action for the job manager (new, progress or cancel)
 * 
 * 
 * exemples : 
 * - add a job :
 * $job_manager_call='php /home/helphp/utils/job_manager.php -a"new" -f"'.$from.'" -c"'.$cmd.'" -C"'.$callback.'" -k"'.$key.'"';
 * 
 * - get progress :
 * $job_manager_call='php /home/helphp/utils/job_manager.php -a"progress" -f"'.$from.'" -k"'.$key.'"';
 * - cancel :
 * $job_manager_call='php /home/helphp/utils/job_manager.php -a"cancel" -f"'.$from.'" -k"'.$key.'"';
 * 
 * must be followed by : exec($job_manager_call);
 * 
 * @package helPHP\utils
 */
function job_manager($argv){
    $nb_jobs = 3; //nb of simultaneous jobs...

    // parsing args : 
    // parse arguments 
    foreach($argv as $index => $arg){

        if ($index == 0){
            
            $cmd = $arg;
            
        } else if ($index > 0){

            $opt = substr($arg,0,2);
            $val = substr($arg,2);
            
            switch($opt){
                case '-k':
                    $keyid = $val;
                break;
                case '-C':
                    $callback = $val;
                break;
                case '-c':
                    $command = $val;
                break;
                case '-f':
                    $fromwho = $val;
                break;
                case '-a':
                    $action = $val;
                break;
                case '-s': // for status when updating a job
                    $status = $val;
                break;
            }
        }
    }

    if (!is_file($fromwho.'config/main.php')){
        die('config file not found in '.$fromwho.'config/main.php');
    }
    include_once($fromwho.'config/main.php');
    include_once($CONFIG::HELPHP_FOLDER.'libs/autoload.php');

    if (isset($action)){
        global $CONFIG_DB;
        $job_db = new DB(['host'=>$CONFIG_DB::DB_JOBS_HOST, 'user'=>$CONFIG_DB::DB_JOBS_USER,'dbname'=>$CONFIG_DB::DB_JOBS_BASE,'password'=>$CONFIG_DB::DB_JOBS_PASS]);
        $job_db->connect();
        
        switch($action){
            case 'new':
                new_job($fromwho, $command, $callback, $keyid);
            break;
            case 'cancel':
                cancel_job($fromwho, $keyid);
            break;
            case 'progress':
                get_job_progress($fromwho, $keyid);
            break;
            case 'loop':
                job_loop();
            break;
            case 'set_status':
                set_status_job($fromwho, $keyid, $status);
            break;
            default:
                echo 'action not found'.PHP_EOL;
            break;
        }
        
        $job_db->close();
    }
}

/**
 * to add a new job in "jobs" database pile
 *
 * @param mixed $from origine identifier
 * @param mixed $command to exec
 * @param mixed $callback ta call back after exec
 * @param mixed $key the key identifier that permit to follow th process.
 * 
 * @return void
 * 
 * @package helPHP\utils
 * 
 */
function new_job($from, $command, $callback, $key) {
    global $job_db, $redisproc;
    if ($redisproc == null ) {
        $redisproc = new Redis();
        $redisproc->connect('redis', 6379);
    }

    if (isset($from, $command, $callback, $key)) {
        // $key is normaly for redis, but we store/check it also in the db 
        $q = 'SELECT DISTINCT COUNT(*) FROM jobs WHERE fromwho="'.$from.'" AND command="'.addslashes($command).'" AND callback="'.$callback.'" AND keyid="'.$key.'" AND status=0';
        $exists = $job_db->query_value($q);
        if($exists){
            Utils::error_log('error adding new job, already exist');
        }else{
            $q = 'INSERT INTO jobs SET fromwho="'.$from.'", command="'.addslashes($command).'", callback="'.$callback.'", keyid="'.$key.'", status=0';
            $job_db->query($q);
            job_loop();
        }
    }else{
        Utils::error_log('missing param');
        Utils::error_log(json_encode(['fromwho'=>$from,'command'=>$command,'callback'=>$callback,'keyid'=>$key]));
    }
}

/**
 * Cancel a job and move if from db to history
 *
 * @param mixed $from origine identifier
 * @param mixed $key indentifier of the job to cancel.
 * 
 * @return void
 * 
 * @package helPHP\utils
 * 
 */
function cancel_job($from, $key){
    global $nb_jobs, $redisproc, $job_db, $CONFIG;
    for($i = 0; $i <= $nb_jobs; $i++){
        //checking if there is a job in progress or to clean
        $job_key_state = $redisproc->get($CONFIG::SITE_NAME.'-jobcue-'.$i);
        if ($job_key_state == $from.'¤'.$key){
            $_REQUEST['process_clear'] = true;
            $state = Utils::follow_system_process_redis($job_key_state);
            if ($state == 'ok!'){
                //no callback
                //cleaning 
                $q = 'DELETE FROM jobs WHERE keyid="'.$job_key_state.'"';
                $job_done = $job_db->query($q);
                $redisproc->del($CONFIG::SITE_NAME.'-jobcue-'.$i);
            }
        }
    }
}

/**
 * Change the status of a job in the pile.
 *
 * @param mixed $from origine identifier
 * @param mixed $key indentifier of the job 
 * @param mixed $status possible values : 
 * - 0 undone
 * - 1 in progress
 * - 2 done
 * - 3 error
 * 
 * @return void
 * @package helPHP\utils
 * 
 */
function set_status_job($from, $key, $status){
    global $job_db;
    $q = 'UPDATE jobs SET `status`='.$status.' WHERE fromwho="'.$from.'" AND keyid="'.$key.'"';
    $job_db->query($q);
}

/**
 * Get the progression of a job
 *
 * @param mixed $from origine identifier
 * @param mixed $key indentifier of the job 
 * 
 * @return string messages / echo from the process if it got status = 1 (1 in progress)
 * - status 0 : it will return "wait"
 * - status 2 : "ok!" because finished
 * - status 3 : "err" an error happenned.
 * 
 * @package helPHP\utils
 */
function get_job_progress($from, $key){
    global $job_db, $redisproc, $nb_jobs, $CONFIG;
    if ($redisproc == null ) {
        $redisproc = new Redis();
        $redisproc->connect('redis', 6379);
    }

    $in_progress = false;
    for($i = 0; $i < $nb_jobs; $i++){
        //checking if there is a job in progress or to clean
        $job_key_state = $redisproc->get($CONFIG::SITE_NAME.'-jobcue-'.$i);
        if ($job_key_state == $from.'¤'.$key){
            $in_progress = true;
            $state = Utils::follow_system_process_redis($job_key_state);
            echo $state;
        }
    }
    if (!$in_progress){
        $q = 'SELECT id, `status` FROM jobs WHERE fromwho="'.$from.'" AND keyid="'.$key.'"';
        $line = $job_db->query_line($q);
        if ($line){
            if ($line['status'] == 0) echo 'wait';
            if ($line['status'] == 2) echo 'ok!';
            if ($line['status'] == 3) echo 'err';
        } else {
            echo 'ok!';
        }
    }
}

/**
 * The job loop will run until there is no more job to do, so it must be started at start or the server
 * and it will go on infine loop waiting  for job to execute.
 *
 * @return void
 * 
 * @package helPHP\utils
 * 
 */
function job_loop() {
    global $job_db, $redisproc, $nb_jobs, $CONFIG;
    if ($redisproc == null ) {
        $redisproc = new Redis();
        $redisproc->connect('redis', 6379);
    }

    $free_cue = false;
    for($i = 0; $i <= $nb_jobs; $i++){
        // checking if there is a job in progress or to clean
        $job_key_state = $redisproc->get($CONFIG::SITE_NAME.'-jobcue-'.$i);
        if ($job_key_state) {
            $state = Utils::follow_system_process_redis($job_key_state);
            $t = explode('¤',$job_key_state);
            $fromwho = $t[0];
            $keyid = $t[1];
            
            $q = 'SELECT DISTINCT * FROM jobs WHERE fromwho="'.$fromwho.'" AND keyid="'.$keyid.'"';
            $job_done = $job_db->query_line($q);
            if ($state == 'ok!' && $job_key_state !== false && $job_done){
                //it's already dead but the callback is not sent
                if ($job_done){
                    Utils::system_process_no_session($job_done['callback']);
                }

                //cleaning 
                $q = 'DELETE FROM jobs WHERE fromwho="'.$fromwho.'" AND keyid="'.$keyid.'"';
                $job_done = $job_db->query($q);
                $redisproc->del($CONFIG::SITE_NAME.'-jobcue-'.$i);
                $job_key_state = false;
            }else{
                $free_cue = true;
            }
            if (!$job_done){
                $free_cue = true;
                $redisproc->del($CONFIG::SITE_NAME.'-jobcue-'.$i);
            }
        }
        if (!$job_key_state){
            //cluster is free looking for a new job to do.
            $q = 'SELECT DISTINCT * FROM jobs WHERE status=0 ORDER BY recorded limit 0,1';
            $last_job = $job_db->query_line($q);
            if (is_array($last_job)){
                Utils::system_process_to_redis(stripslashes($last_job['command']), $last_job['fromwho'].'¤'.$last_job['keyid'], "job");
                //we record the job cue key
                $redisproc->set($CONFIG::SITE_NAME.'-jobcue-'.$i,$last_job['fromwho'].'¤'.$last_job['keyid']);
                $q = 'UPDATE jobs SET status=1 WHERE id='.$last_job['id'];
                $job_db->query($q);
            }else{
                $free_cue = false;
            }
        }
    }
    if ($free_cue){
        //there is still some free space in the job cue...
        set_time_limit(30);
        job_loop();
    }
}