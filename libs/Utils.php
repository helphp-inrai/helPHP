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
namespace helPHP\libs;

//to link the classic error_handler to our own
global $CONFIG;
if ($CONFIG::DEVMODE) {
    set_error_handler('helPHP\libs\Utils::error_handler');
}

/**
 * @class Utils 
 * 
 * Provides utility functions for system operations, debugging, string and array manipulation,
 * file and process management, configuration updates, and more.
 * Includes helpers for launching and tracking system processes, logging, filtering strings,
 * computing discounts and VAT, merging arrays, checking URLs, generating random strings,
 * handling CSS and configuration, and retrieving IP addresses.
 * 
 * In fact every little utils that haven't their places in other libs, but they are all usefull.
 * 
 * they are grouped in different sections (search for them to jump on it):
 * -SYSTEM SECTION
 * -NETWORK SECTION
 * -CONFIG SECTION
 * -MIME SECTION
 * -DEBUG SECTION
 * -STRING MANIPULATION SECTION
 * -NUMBERS MANIPULATION SECTION
 * -ARRAY MANIPULATION SECTION
 * 
 * One is really used often : Utils::error_log(); that replace the classic error_log to 
 * send a more readable log in $CONFIG::LOG_FILE file. 
 * It combine also the classic errors except error 500 (still going to apache error log in that case).
 * So do not hesitate to use it during your debug session.
 * 
 *
 * @package helPHP\libs
 */
class Utils
{
    public function __construct() {
    }
    
//---------------------------------SYSTEM SECTION-----------------------------
    /**
     * Launches a system process and pushes its output to a session variable, which can be followed by a key.
     * Used a lot by Filesystem class
     * 
     * @param string $cmd The command to execute.
     * @param string $key The key to indentify the process output in session.
     * @return void
     */
    public static function system_process($cmd, $key) {
        $descriptorspec = array(
           0 => array("pipe", "r"),
           1 => array("pipe", "w"),
           2 => array("pipe", "w")
        );
        $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
        if ($key == '') {
            $key = time().'_'.floor(rand()*10000);
        }
        if (is_resource($process)) {
            while ($s = fgets($pipes[1], 256)) {
                $_SESSION['processes'][$key]=nl2br(addslashes(trim($s)));
                session_force_update();
                usleep(10);
                set_time_limit(5);
            }
        }
        $_SESSION['processes'][$key]='ok!';
        session_force_update();
        fclose($pipes[1]);
        proc_close($process);
    }

    /**
     * Launches a system process and returns its output as a string (without session tracking).
     *
     * @param string $cmd The command to execute.
     * @return string The output of the command.
     */
    public static function system_process_no_session($cmd)
    {
        //same thing that system process but only return a text variable
        $descriptorspec = array(
            0 => array('pipe', 'r'),   // stdin is a pipe that the child will read from
            1 => array('pipe', 'w'),   // stdout is a pipe that the child will write to
            2 => array('pipe', 'w')    // stderr is a pipe that the child will write to
        );
        $toreturn='';
        $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());

        if (is_resource($process)) {
            while ($s = fgets($pipes[1], 256)) {
                $toreturn.=$s;
            }
        }
        fclose($pipes[1]);
        proc_close($process);
        return $toreturn;
    }

    /**
     * Launches one or more system processes with no tracking
     *
     * @param array|string $cmds The command(s) to execute.
     * @param string $key The key to reference the process in Redis.
     * @param string|false $type Optional. Type for progress tracking.
     * @param array|false $arg Optional. Argument for the command type.
     * @param array|false $to_unlocked Optional. Paths to lock/unlock during the process.
     * @return void
     */
    public static function system_process_no_redis($cmds, $key, $type = false, $arg = false, $to_unlocked = false) {
        global $CONFIG;
        if (!is_array($cmds)){
            $cmds = [$cmds];
        }

        foreach($cmds as $i => $cmd){
            $cmd_to_exec = 'php '.$CONFIG::HELPHP_FOLDER.'utils/process_launcher.php "'.$cmd.'" -k"'.$key.'¤'.$i.'"';
            $cmd_to_exec.= ' -i"'.$CONFIG::HOME_FOLDER.'"';
            if ($type) $cmd_to_exec.=' -t'.$type;
            if ($arg !== false) {
                if ($type == 'copy') $cmd_to_exec.=' -a'.$arg[$i];
                else $cmd_to_exec.=' -a'.$arg;
            }
            if ($to_unlocked) {
                if (is_array($to_unlocked[$i])){
                    $t = implode('¤¤¤¤', $to_unlocked[$i]);
                } else if (is_array($to_unlocked)) {
                    $t = implode('¤¤¤¤',$to_unlocked);
                }
                $cmd_to_exec.=' -l"'.$t.'"';
            }
            $cmd_to_exec.=' -r0';
            
            exec($cmd_to_exec);
        }
    }

    /**
     * Launches one or more system processes and tracks their output/progress in Redis.
     *
     * @param array|string $cmds The command(s) to execute.
     * @param string $key The key to reference the process in Redis.
     * @param string|false $type Optional. Type for progress tracking.
     * @param array|false $arg Optional. Argument for the command type.
     * @param array|false $to_unlocked Optional. Paths to lock/unlock during the process.
     * @return void
     */
    public static function system_process_to_redis($cmds, $key, $type = false, $arg = false, $to_unlocked = false) {
        global $redisproc, $CONFIG;
        if ($redisproc == null ) {
            $redisproc = new \Redis();
            $redisproc->connect($CONFIG::REDIS_HOST, $CONFIG::REDIS_PORT);
        }
        if (!is_array($cmds)){
            $cmds = [$cmds];
        }
        $redisproc->set($CONFIG::SITE_NAME.'-processes-nbr_cmd-'.$key,count($cmds));
        foreach($cmds as $i => $cmd){
            $cmd_to_exec = 'php '.$CONFIG::HELPHP_FOLDER.'utils/process_launcher.php "'.$cmd.'" -k"'.$key.'¤'.$i.'"';
            $cmd_to_exec.= ' -i"'.$CONFIG::HOME_FOLDER.'"';
            if ($type) $cmd_to_exec.=' -t'.$type;
            if ($arg !== false) {
                if ($type == 'copy') $cmd_to_exec.=' -a'.$arg[$i];
                else $cmd_to_exec.=' -a'.$arg;
            }
            if ($to_unlocked) {
                if (is_array($to_unlocked[$i])){
                    $t = implode('¤¤¤¤', $to_unlocked[$i]);
                } else if (is_array($to_unlocked)) {
                    $t = implode('¤¤¤¤',$to_unlocked);
                }
                $cmd_to_exec.=' -l"'.$t.'"';
            }
            $cmd_to_exec.=' > /dev/null & echo $!';
            
            $redisproc->set($CONFIG::SITE_NAME.'-processes-'.$key.'¤'.$i,'waiting');
            $pid = exec($cmd_to_exec);
            $redisproc->set($CONFIG::SITE_NAME.'-processes-pid-'.$key.'¤'.$i,$pid);
        }
    }
        
    /**
     * Follows the progress of a system process tracked in session.
     *
     * @param string|false $key The key referencing the process.
     * @return string|false The process output or 'ok!' when finished.
     */
    public static function follow_system_process($key = false) {
        
        if ((isset($_SESSION['processes'][$key]) && $_SESSION['processes'][$key] == 'ok!') || isset($_REQUEST['process_clear'])) {
            unset($_SESSION[get_class()][$key]);
            return 'ok!';
        }
        if (isset($_SESSION['processes'][$key])) {
            return $_SESSION['processes'][$key];
        }
    }

    /**
     * Follows the progress of a system process tracked in Redis.
     *
     * @param string|false $key The key referencing the process.
     * @param bool $update_history Whether to update history when finished.
     * @return mixed Progress percentage, 'ok!' when finished, or false.
     */
    public static function follow_system_process_redis($key = false, $update_history = false) {
        global $redisproc,$CONFIG;
        if ($redisproc == null ) {
            $redisproc = new \Redis();
            $redisproc->connect($CONFIG::REDIS_HOST, $CONFIG::REDIS_PORT);
        }
        
        $nbr_cmd = $redisproc->get($CONFIG::SITE_NAME.'-processes-nbr_cmd-'.$key);
        if (!$nbr_cmd){
            return 'ok!';
        }
        
        if (isset($_REQUEST['process_clear'])){
            for ($i=0; $i < $nbr_cmd; $i++) {
                $pid=$redisproc->get($CONFIG::SITE_NAME.'-processes-pid-'.$key.'¤'.$i);
                //proc_terminate
                posix_kill($pid, SIGTERM);
                $redisproc->del($CONFIG::SITE_NAME.'-processes-'.$key.'¤'.$i);
                $redisproc->del($CONFIG::SITE_NAME.'-processes-pid-'.$key.'¤'.$i);
            }
            return 'ok!';
        }

        // will compute the progression for each command in this variable
        $total = 0;
        for ($i=0; $i < $nbr_cmd; $i++) {
            
            $state = $redisproc->get($CONFIG::SITE_NAME.'-processes-'.$key.'¤'.$i);
            //already dead
            if ($state == null || $state == 'ok!'){
                $total += 100 / $nbr_cmd;
            } else {
                $state = floatval($state) > 0 ? $state : 0;
                $total += $state / $nbr_cmd;
            }
        }
        
        $total = round($total * 100) / 100;
        if ($total >= 100){
            for ($i=0; $i < $nbr_cmd; $i++) {
                $redisproc->del($CONFIG::SITE_NAME.'-processes-'.$key.'¤'.$i);
                $redisproc->del($CONFIG::SITE_NAME.'-processes-pid-'.$key.'¤'.$i);
            }
            $redisproc->del($CONFIG::SITE_NAME.'-processes-nbr_cmd-'.$key);
            if ($update_history) Filesystem::update_history($key);
            
            return 'ok!';
        }
        return $total;
        
    }

    // ----------------------------- NETWORK SECTION -----------------------------
    
    /**
     * Retrieves the client IPv4 address.
     *
     * @return string The IP address or 'NoIp' if not found.
     */
    public static function get_ip() {
        $the_ip = '';

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers = $_SERVER;
        }
        
        if (array_key_exists('X-Real-IP', $headers) && filter_var($headers['X-Real-IP'], FILTER_VALIDATE_IP)) {
            $the_ip = $headers['X-Real-IP'];
        }elseif (array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP)) {
            $the_ip = $headers['X-Forwarded-For'];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
            $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        }
        
        if ($the_ip == '') {
            $the_ip = 'NoIp';
        }

        return $the_ip;
    }

    /**
     * Retrieves the client IPv6 address.
     *
     * @return string The IPv6 address or 'NoIp' if not found.
     */
    public static function get_ip_v6() {
        $the_ip = '';

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers = $_SERVER;
        }
        
        //NOT: in db try_logs connection in ipv6 ip varchar must be >= 50

        if (array_key_exists('X-Real-IP', $headers) && filter_var($headers['X-Real-IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $the_ip = $headers['X-Real-IP'];
        }elseif (array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $the_ip = $headers['X-Forwarded-For'];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }
        
        if ($the_ip == '') {
            $the_ip = 'NoIp';
        }

        return $the_ip;
    }
    /**
     * Retrieves the host IP address from the routing table.
     *
     * @return string The host IP address.
     */
    public static function get_host_ip() {
        $hi = "";
        // Get the host IP from the routing table,
        //usefull to call some service runnning on the host outer than the docker or virtual network
        $table = file("/proc/net/route");
        foreach ($table as $row) {
            // Split the fields out of the routing table
            $fields = preg_split("/[\t ]+/", trim($row));
            
            // Skip this route if it's not the default gateway
            if ($fields[1] != "00000000") continue;

            // Convert the hex gateway IP to dotted-notation
            $hi_hex = $fields[2];
            $hi_rev = long2ip(hexdec("0x$hi_hex"));
            $hi = implode(".", array_reverse(explode(".", $hi_rev)));
            break;
        }
        return $hi;
    }
    /**
     * Checks if a URL is reachable (HTTP status < 400).
     *
     * @param string $url The URL to check.
     * @return bool True if reachable, false otherwise.
     */
    public static function check_url($url) {
        set_error_handler('helPHP\libs\Utils::error_handler_to_trash');
        $headers = @get_headers($url, 1);
        if (is_array($headers)) {
            $httpcode = substr($headers[0], 9);
            return ( $httpcode < 400);
        }else{
            return false;
        }
    }

// ----------------------------- CONFIG SECTION -----------------------------
    /**
     * Regenerates the constants.js file from PHP constants.
     *
     * @return bool True on success, false on error.
     */
    public static function make_constant() {
        global $CONFIG;
        $res = exec('php '.$CONFIG::HELPHP_FOLDER.'utils/constants.php '.$CONFIG::HOME_FOLDER);
        if ($res != 'done'){
            Utils::error_log('ERROR while making constants.js');
            return false;
        }
        return true;
    }

    /**
     * Updates the main config file with new variable values.
     *
     * @param string|array $variable_name Variable name(s) to update.
     * @param string|array $type Type(s) of variable ('s' for string, etc.).
     * @param mixed $value Value(s) to set.
     * @param string $path Path of the config file to modify.
     * @return bool True on success, false on error.
     */
    public static function write_in_config($variable_name, $type, $value, $path = false) {
        global $CONFIG,$FS;

        $variable_name = is_array($variable_name) ? $variable_name : [$variable_name];
        $value = is_array($value) ? $value : [$value];
        $type = is_array($type) ? $type : [$type];
        foreach($value as $key => $val){
            if ($type[$key] == 's'){
                $value[$key] = "'".$val."'";
            } else if ($type[$key] == 'b'){
                if ($val === 'true' || $val === 'false') continue;
                $value[$key] = (!$val) ? 'false' : 'true';
            }
        }

        if (count($variable_name) != count($value) || count($variable_name) != count($type)) {
            Utils::error_log('Error on write_in_config, not the same amount of each parameters');
            return false;
        }

        $config_file = ($path === false) ? $CONFIG::HOME_FOLDER.'config/main.php' : $path;
        $content = file_get_contents($config_file);

        $lines = explode(PHP_EOL , $content);
        
        $output = '';
        $found = [];
        foreach($lines as $l){
            foreach($variable_name as $key => $name){
                $parsed_name = trim(preg_replace('/const (.*) =.*/', '$1', $l));
                if($parsed_name == strtoupper($name)){
                    $l = '    const '.strtoupper($name).' = '.$value[$key].';';
                    array_push($found, $name);
                }
            }
            
            if (str_contains($l , '//>>>modules>>>')){
                $write = false;
                foreach($variable_name as $key => $name){
                    if (!in_array($name, $found)){
                        $write = true;
                        $l = '    const '.strtoupper($name).' = '.$value.';'.PHP_EOL;
                    }
                }
                
                if ($write) $l.= PHP_EOL.'    //>>>modules>>>';
            }

            $output .= $l.PHP_EOL;
        }
        $output = substr($output, 0, -1);
        if (!is_object($FS)){
            include_once('Filesystem.php');
        }
        Filesystem::save_content($config_file , $output);
        
        return true;
    }
// ----------------------------- MIME SECTION -----------------------------
    public static function get_mime_type($ext) {
        $mime = '';
        switch ($ext) {
            case 'x3d': $mime='application/vnd.hzn-3d-crossword'; break;
            case '3gp': $mime='video/3gpp'; break;
            case '3g2': $mime='video/3gpp2'; break;
            case 'mseq': $mime='application/vnd.mseq'; break;
            case 'pwn': $mime='application/vnd.3m.post-it-notes'; break;
            case 'plb': $mime='application/vnd.3gpp.pic-bw-large'; break;
            case 'psb': $mime='application/vnd.3gpp.pic-bw-small'; break;
            case 'pvb': $mime='application/vnd.3gpp.pic-bw-var'; break;
            case 'tcap': $mime='application/vnd.3gpp2.tcap'; break;
            case '7z': $mime='application/x-7z-compressed'; break;
            case 'abw': $mime='application/x-abiword'; break;
            case 'ace': $mime='application/x-ace-compressed'; break;
            case 'acc': $mime='application/vnd.americandynamics.acc'; break;
            case 'acu': $mime='application/vnd.acucobol'; break;
            case 'atc': $mime='application/vnd.acucorp'; break;
            case 'adp': $mime='audio/adpcm'; break;
            case 'aab': $mime='application/x-authorware-bin'; break;
            case 'aam': $mime='application/x-authorware-map'; break;
            case 'aas': $mime='application/x-authorware-seg'; break;
            case 'air': $mime='application/vnd.adobe.air-application-installer-package+zip'; break;
            case 'swf': $mime='application/x-shockwave-flash'; break;
            case 'fxp': $mime='application/vnd.adobe.fxp'; break;
            case 'pdf': $mime='application/pdf'; break;
            case 'ppd': $mime='application/vnd.cups-ppd'; break;
            case 'dir': $mime='application/x-director'; break;
            case 'xdp': $mime='application/vnd.adobe.xdp+xml'; break;
            case 'xfdf': $mime='application/vnd.adobe.xfdf'; break;
            case 'aac': $mime='audio/x-aac'; break;
            case 'ahead': $mime='application/vnd.ahead.space'; break;
            case 'azf': $mime='application/vnd.airzip.filesecure.azf'; break;
            case 'azs': $mime='application/vnd.airzip.filesecure.azs'; break;
            case 'azw': $mime='application/vnd.amazon.ebook'; break;
            case 'ami': $mime='application/vnd.amiga.ami'; break;
            case 'N/A': $mime='application/andrew-inset'; break;
            case 'apk': $mime='application/vnd.android.package-archive'; break;
            case 'cii': $mime='application/vnd.anser-web-certificate-issue-initiation'; break;
            case 'fti': $mime='application/vnd.anser-web-funds-transfer-initiation'; break;
            case 'atx': $mime='application/vnd.antix.game-component'; break;
            case 'mpkg': $mime='application/vnd.apple.installer+xml'; break;
            case 'aw': $mime='application/applixware'; break;
            case 'les': $mime='application/vnd.hhe.lesson-player'; break;
            case 'swi': $mime='application/vnd.aristanetworks.swi'; break;
            case 's': $mime='text/x-asm'; break;
            case 'atomcat': $mime='application/atomcat+xml'; break;
            case 'atomsvc': $mime='application/atomsvc+xml'; break;
            case 'atom, xml': $mime='application/atom+xml'; break;
            case 'ac': $mime='application/pkix-attr-cert'; break;
            case 'aif': $mime='audio/x-aiff'; break;
            case 'avi': $mime='video/x-msvideo'; break;
            case 'aep': $mime='application/vnd.audiograph'; break;
            case 'dxf': $mime='image/vnd.dxf'; break;
            case 'dwf': $mime='model/vnd.dwf'; break;
            case 'par': $mime='text/plain-bas'; break;
            case 'bcpio': $mime='application/x-bcpio'; break;
            case 'bin': $mime='application/octet-stream'; break;
            case 'dat': $mime='application/octet-stream'; break;
            case 'bmp': $mime='image/bmp'; break;
            case 'torrent': $mime='application/x-bittorrent'; break;
            case 'cod': $mime='application/vnd.rim.cod'; break;
            case 'mpm': $mime='application/vnd.blueice.multipass'; break;
            case 'bmi': $mime='application/vnd.bmi'; break;
            case 'sh': $mime='application/x-sh'; break;
            case 'btif': $mime='image/prs.btif'; break;
            case 'rep': $mime='application/vnd.businessobjects'; break;
            case 'bz': $mime='application/x-bzip'; break;
            case 'bz2': $mime='application/x-bzip2'; break;
            case 'csh': $mime='application/x-csh'; break;
            case 'c': $mime='text/x-c'; break;
            case 'cdxml': $mime='application/vnd.chemdraw+xml'; break;
            case 'css': $mime='text/css'; break;
            case 'cdx': $mime='chemical/x-cdx'; break;
            case 'cml': $mime='chemical/x-cml'; break;
            case 'csml': $mime='chemical/x-csml'; break;
            case 'cdbcmsg': $mime='application/vnd.contact.cmsg'; break;
            case 'cla': $mime='application/vnd.claymore'; break;
            case 'c4g': $mime='application/vnd.clonk.c4group'; break;
            case 'sub': $mime='image/vnd.dvb.subtitle'; break;
            case 'cdmia': $mime='application/cdmi-capability'; break;
            case 'cdmic': $mime='application/cdmi-container'; break;
            case 'cdmid': $mime='application/cdmi-domain'; break;
            case 'cdmio': $mime='application/cdmi-object'; break;
            case 'cdmiq': $mime='application/cdmi-queue'; break;
            case 'c11amc': $mime='application/vnd.cluetrust.cartomobile-config'; break;
            case 'c11amz': $mime='application/vnd.cluetrust.cartomobile-config-pkg'; break;
            case 'ras': $mime='image/x-cmu-raster'; break;
            case 'dae': $mime='model/vnd.collada+xml'; break;
            case 'csv': $mime='text/csv'; break;
            case 'cpt': $mime='application/mac-compactpro'; break;
            case 'wmlc': $mime='application/vnd.wap.wmlc'; break;
            case 'cgm': $mime='image/cgm'; break;
            case 'ice': $mime='x-conference/x-cooltalk'; break;
            case 'cmx': $mime='image/x-cmx'; break;
            case 'xar': $mime='application/vnd.xara'; break;
            case 'cmc': $mime='application/vnd.cosmocaller'; break;
            case 'cpio': $mime='application/x-cpio'; break;
            case 'clkx': $mime='application/vnd.crick.clicker'; break;
            case 'clkk': $mime='application/vnd.crick.clicker.keyboard'; break;
            case 'clkp': $mime='application/vnd.crick.clicker.palette'; break;
            case 'clkt': $mime='application/vnd.crick.clicker.template'; break;
            case 'clkw': $mime='application/vnd.crick.clicker.wordbank'; break;
            case 'wbs': $mime='application/vnd.criticaltools.wbs+xml'; break;
            case 'cryptonote': $mime='application/vnd.rig.cryptonote'; break;
            case 'cif': $mime='chemical/x-cif'; break;
            case 'cmdf': $mime='chemical/x-cmdf'; break;
            case 'cu': $mime='application/cu-seeme'; break;
            case 'cww': $mime='application/prs.cww'; break;
            case 'curl': $mime='text/vnd.curl'; break;
            case 'dcurl': $mime='text/vnd.curl.dcurl'; break;
            case 'mcurl': $mime='text/vnd.curl.mcurl'; break;
            case 'scurl': $mime='text/vnd.curl.scurl'; break;
            case 'car': $mime='application/vnd.curl.car'; break;
            case 'pcurl': $mime='application/vnd.curl.pcurl'; break;
            case 'cmp': $mime='application/vnd.yellowriver-custom-menu'; break;
            case 'dssc': $mime='application/dssc+der'; break;
            case 'xdssc': $mime='application/dssc+xml'; break;
            case 'deb': $mime='application/x-debian-package'; break;
            case 'uva': $mime='audio/vnd.dece.audio'; break;
            case 'uvi': $mime='image/vnd.dece.graphic'; break;
            case 'uvh': $mime='video/vnd.dece.hd'; break;
            case 'uvm': $mime='video/vnd.dece.mobile'; break;
            case 'uvu': $mime='video/vnd.uvvu.mp4'; break;
            case 'uvp': $mime='video/vnd.dece.pd'; break;
            case 'uvs': $mime='video/vnd.dece.sd'; break;
            case 'uvv': $mime='video/vnd.dece.video'; break;
            case 'dvi': $mime='application/x-dvi'; break;
            case 'seed': $mime='application/vnd.fdsn.seed'; break;
            case 'dtb': $mime='application/x-dtbook+xml'; break;
            case 'res': $mime='application/x-dtbresource+xml'; break;
            case 'ait': $mime='application/vnd.dvb.ait'; break;
            case 'svc': $mime='application/vnd.dvb.service'; break;
            case 'eol': $mime='audio/vnd.digital-winds'; break;
            case 'djvu': $mime='image/vnd.djvu'; break;
            case 'dtd': $mime='application/xml-dtd'; break;
            case 'mlp': $mime='application/vnd.dolby.mlp'; break;
            case 'wad': $mime='application/x-doom'; break;
            case 'dpg': $mime='application/vnd.dpgraph'; break;
            case 'dra': $mime='audio/vnd.dra'; break;
            case 'dfac': $mime='application/vnd.dreamfactory'; break;
            case 'dts': $mime='audio/vnd.dts'; break;
            case 'dtshd': $mime='audio/vnd.dts.hd'; break;
            case 'dwg': $mime='image/vnd.dwg'; break;
            case 'geo': $mime='application/vnd.dynageo'; break;
            case 'es': $mime='application/ecmascript'; break;
            case 'mag': $mime='application/vnd.ecowin.chart'; break;
            case 'mmr': $mime='image/vnd.fujixerox.edmics-mmr'; break;
            case 'rlc': $mime='image/vnd.fujixerox.edmics-rlc'; break;
            case 'exi': $mime='application/exi'; break;
            case 'mgz': $mime='application/vnd.proteus.magazine'; break;
            case 'epub': $mime='application/epub+zip'; break;
            case 'eml': $mime='message/rfc822'; break;
            case 'nml': $mime='application/vnd.enliven'; break;
            case 'xpr': $mime='application/vnd.is-xpr'; break;
            case 'xif': $mime='image/vnd.xiff'; break;
            case 'xfdl': $mime='application/vnd.xfdl'; break;
            case 'emma': $mime='application/emma+xml'; break;
            case 'ez2': $mime='application/vnd.ezpix-album'; break;
            case 'ez3': $mime='application/vnd.ezpix-package'; break;
            case 'fst': $mime='image/vnd.fst'; break;
            case 'fvt': $mime='video/vnd.fvt'; break;
            case 'fbs': $mime='image/vnd.fastbidsheet'; break;
            case 'fe_launch': $mime='application/vnd.denovo.fcselayout-link'; break;
            case 'f4v': $mime='video/x-f4v'; break;
            case 'flv': $mime='video/x-flv'; break;
            case 'fpx': $mime='image/vnd.fpx'; break;
            case 'npx': $mime='image/vnd.net-fpx'; break;
            case 'flx': $mime='text/vnd.fmi.flexstor'; break;
            case 'fli': $mime='video/x-fli'; break;
            case 'ftc': $mime='application/vnd.fluxtime.clip'; break;
            case 'fdf': $mime='application/vnd.fdf'; break;
            case 'f': $mime='text/x-fortran'; break;
            case 'mif': $mime='application/vnd.mif'; break;
            case 'fm': $mime='application/vnd.framemaker'; break;
            case 'fh': $mime='image/x-freehand'; break;
            case 'fsc': $mime='application/vnd.fsc.weblaunch'; break;
            case 'fnc': $mime='application/vnd.frogans.fnc'; break;
            case 'ltf': $mime='application/vnd.frogans.ltf'; break;
            case 'ddd': $mime='application/vnd.fujixerox.ddd'; break;
            case 'xdw': $mime='application/vnd.fujixerox.docuworks'; break;
            case 'xbd': $mime='application/vnd.fujixerox.docuworks.binder'; break;
            case 'oas': $mime='application/vnd.fujitsu.oasys'; break;
            case 'oa2': $mime='application/vnd.fujitsu.oasys2'; break;
            case 'oa3': $mime='application/vnd.fujitsu.oasys3'; break;
            case 'fg5': $mime='application/vnd.fujitsu.oasysgp'; break;
            case 'bh2': $mime='application/vnd.fujitsu.oasysprs'; break;
            case 'spl': $mime='application/x-futuresplash'; break;
            case 'fzs': $mime='application/vnd.fuzzysheet'; break;
            case 'g3': $mime='image/g3fax'; break;
            case 'gmx': $mime='application/vnd.gmx'; break;
            case 'gtw': $mime='model/vnd.gtw'; break;
            case 'txd': $mime='application/vnd.genomatix.tuxedo'; break;
            case 'ggb': $mime='application/vnd.geogebra.file'; break;
            case 'ggt': $mime='application/vnd.geogebra.tool'; break;
            case 'gdl': $mime='model/vnd.gdl'; break;
            case 'gex': $mime='application/vnd.geometry-explorer'; break;
            case 'gxt': $mime='application/vnd.geonext'; break;
            case 'g2w': $mime='application/vnd.geoplan'; break;
            case 'g3w': $mime='application/vnd.geospace'; break;
            case 'gsf': $mime='application/x-font-ghostscript'; break;
            case 'bdf': $mime='application/x-font-bdf'; break;
            case 'gtar': $mime='application/x-gtar'; break;
            case 'texinfo': $mime='application/x-texinfo'; break;
            case 'gnumeric': $mime='application/x-gnumeric'; break;
            case 'kml': $mime='application/vnd.google-earth.kml+xml'; break;
            case 'kmz': $mime='application/vnd.google-earth.kmz'; break;
            case 'gqf': $mime='application/vnd.grafeq'; break;
            case 'gif': $mime='image/gif'; break;
            case 'gv': $mime='text/vnd.graphviz'; break;
            case 'gac': $mime='application/vnd.groove-account'; break;
            case 'ghf': $mime='application/vnd.groove-help'; break;
            case 'gim': $mime='application/vnd.groove-identity-message'; break;
            case 'grv': $mime='application/vnd.groove-injector'; break;
            case 'gtm': $mime='application/vnd.groove-tool-message'; break;
            case 'tpl': $mime='application/vnd.groove-tool-template'; break;
            case 'vcg': $mime='application/vnd.groove-vcard'; break;
            case 'h261': $mime='video/h261'; break;
            case 'h263': $mime='video/h263'; break;
            case 'h264': $mime='video/h264'; break;
            case 'hpid': $mime='application/vnd.hp-hpid'; break;
            case 'hps': $mime='application/vnd.hp-hps'; break;
            case 'hdf': $mime='application/x-hdf'; break;
            case 'rip': $mime='audio/vnd.rip'; break;
            case 'hbci': $mime='application/vnd.hbci'; break;
            case 'jlt': $mime='application/vnd.hp-jlyt'; break;
            case 'pcl': $mime='application/vnd.hp-pcl'; break;
            case 'hpgl': $mime='application/vnd.hp-hpgl'; break;
            case 'hvs': $mime='application/vnd.yamaha.hv-script'; break;
            case 'hvd': $mime='application/vnd.yamaha.hv-dic'; break;
            case 'hvp': $mime='application/vnd.yamaha.hv-voice'; break;
            case 'sfd-hdstx': $mime='application/vnd.hydrostatix.sof-data'; break;
            case 'stk': $mime='application/hyperstudio'; break;
            case 'hal': $mime='application/vnd.hal+xml'; break;
            case 'html': $mime='text/html'; break;
            case 'irm': $mime='application/vnd.ibm.rights-management'; break;
            case 'sc': $mime='application/vnd.ibm.secure-container'; break;
            case 'ics': $mime='text/calendar'; break;
            case 'icc': $mime='application/vnd.iccprofile'; break;
            case 'ico': $mime='image/x-icon'; break;
            case 'igl': $mime='application/vnd.igloader'; break;
            case 'ief': $mime='image/ief'; break;
            case 'ivp': $mime='application/vnd.immervision-ivp'; break;
            case 'ivu': $mime='application/vnd.immervision-ivu'; break;
            case 'rif': $mime='application/reginfo+xml'; break;
            case '3dml': $mime='text/vnd.in3d.3dml'; break;
            case 'spot': $mime='text/vnd.in3d.spot'; break;
            case 'igs': $mime='model/iges'; break;
            case 'i2g': $mime='application/vnd.intergeo'; break;
            case 'cdy': $mime='application/vnd.cinderella'; break;
            case 'xpw': $mime='application/vnd.intercon.formnet'; break;
            case 'fcs': $mime='application/vnd.isac.fcs'; break;
            case 'ipfix': $mime='application/ipfix'; break;
            case 'cer': $mime='application/pkix-cert'; break;
            case 'pki': $mime='application/pkixcmp'; break;
            case 'crl': $mime='application/pkix-crl'; break;
            case 'pkipath': $mime='application/pkix-pkipath'; break;
            case 'igm': $mime='application/vnd.insors.igm'; break;
            case 'rcprofile': $mime='application/vnd.ipunplugged.rcprofile'; break;
            case 'irp': $mime='application/vnd.irepository.package+xml'; break;
            case 'jad': $mime='text/vnd.sun.j2me.app-descriptor'; break;
            case 'jar': $mime='application/java-archive'; break;
            case 'class': $mime='application/java-vm'; break;
            case 'jnlp': $mime='application/x-java-jnlp-file'; break;
            case 'ser': $mime='application/java-serialized-object'; break;
            case 'java': $mime='text/x-java-source,java'; break;
            case 'js': $mime='application/javascript'; break;
            case 'json': $mime='application/json'; break;
            case 'joda': $mime='application/vnd.joost.joda-archive'; break;
            case 'jpm': $mime='video/jpm'; break;
            case 'jpeg': $mime='image/jpeg'; break;
            case 'jpg': $mime='image/jpeg'; break;
            case 'jpgv': $mime='video/jpeg'; break;
            case 'ktz': $mime='application/vnd.kahootz'; break;
            case 'mmd': $mime='application/vnd.chipnuts.karaoke-mmd'; break;
            case 'karbon': $mime='application/vnd.kde.karbon'; break;
            case 'chrt': $mime='application/vnd.kde.kchart'; break;
            case 'kfo': $mime='application/vnd.kde.kformula'; break;
            case 'flw': $mime='application/vnd.kde.kivio'; break;
            case 'kon': $mime='application/vnd.kde.kontour'; break;
            case 'kpr': $mime='application/vnd.kde.kpresenter'; break;
            case 'ksp': $mime='application/vnd.kde.kspread'; break;
            case 'kwd': $mime='application/vnd.kde.kword'; break;
            case 'htke': $mime='application/vnd.kenameaapp'; break;
            case 'kia': $mime='application/vnd.kidspiration'; break;
            case 'kne': $mime='application/vnd.kinar'; break;
            case 'sse': $mime='application/vnd.kodak-descriptor'; break;
            case 'lasxml': $mime='application/vnd.las.las+xml'; break;
            case 'latex': $mime='application/x-latex'; break;
            case 'lbd': $mime='application/vnd.llamagraphics.life-balance.desktop'; break;
            case 'lbe': $mime='application/vnd.llamagraphics.life-balance.exchange+xml'; break;
            case 'jam': $mime='application/vnd.jam'; break;
            case '123': $mime='application/vnd.lotus-1-2-3'; break;
            case 'apr': $mime='application/vnd.lotus-approach'; break;
            case 'pre': $mime='application/vnd.lotus-freelance'; break;
            case 'nsf': $mime='application/vnd.lotus-notes'; break;
            case 'org': $mime='application/vnd.lotus-organizer'; break;
            case 'scm': $mime='application/vnd.lotus-screencam'; break;
            case 'lwp': $mime='application/vnd.lotus-wordpro'; break;
            case 'lvp': $mime='audio/vnd.lucent.voice'; break;
            case 'm3u': $mime='audio/x-mpegurl'; break;
            case 'm4v': $mime='video/x-m4v'; break;
            case 'm4a': $mime='audio/m4a'; break;
            case 'hqx': $mime='application/mac-binhex40'; break;
            case 'portpkg': $mime='application/vnd.macports.portpkg'; break;
            case 'mgp': $mime='application/vnd.osgeo.mapguide.package'; break;
            case 'mrc': $mime='application/marc'; break;
            case 'mrcx': $mime='application/marcxml+xml'; break;
            case 'mxf': $mime='application/mxf'; break;
            case 'nbp': $mime='application/vnd.wolfram.player'; break;
            case 'ma': $mime='application/mathematica'; break;
            case 'mathml': $mime='application/mathml+xml'; break;
            case 'mbox': $mime='application/mbox'; break;
            case 'mc1': $mime='application/vnd.medcalcdata'; break;
            case 'mscml': $mime='application/mediaservercontrol+xml'; break;
            case 'cdkey': $mime='application/vnd.mediastation.cdkey'; break;
            case 'mwf': $mime='application/vnd.mfer'; break;
            case 'mfm': $mime='application/vnd.mfmp'; break;
            case 'msh': $mime='model/mesh'; break;
            case 'mads': $mime='application/mads+xml'; break;
            case 'mets': $mime='application/mets+xml'; break;
            case 'mods': $mime='application/mods+xml'; break;
            case 'meta4': $mime='application/metalink4+xml'; break;
            case 'potm': $mime='application/vnd.ms-powerpoint.template.macroenabled.12'; break;
            case 'docm': $mime='application/vnd.ms-word.document.macroenabled.12'; break;
            case 'dotm': $mime='application/vnd.ms-word.template.macroenabled.12'; break;
            case 'mcd': $mime='application/vnd.mcd'; break;
            case 'flo': $mime='application/vnd.micrografx.flo'; break;
            case 'igx': $mime='application/vnd.micrografx.igx'; break;
            case 'es3': $mime='application/vnd.eszigno3+xml'; break;
            case 'mdb': $mime='application/x-msaccess'; break;
            case 'asf': $mime='video/x-ms-asf'; break;
            case 'exe': $mime='application/x-msdownload'; break;
            case 'cil': $mime='application/vnd.ms-artgalry'; break;
            case 'cab': $mime='application/vnd.ms-cab-compressed'; break;
            case 'ims': $mime='application/vnd.ms-ims'; break;
            case 'application': $mime='application/x-ms-application'; break;
            case 'clp': $mime='application/x-msclip'; break;
            case 'mdi': $mime='image/vnd.ms-modi'; break;
            case 'eot': $mime='application/vnd.ms-fontobject'; break;
            case 'xls': $mime='application/vnd.ms-excel'; break;
            case 'xlam': $mime='application/vnd.ms-excel.addin.macroenabled.12'; break;
            case 'xlsb': $mime='application/vnd.ms-excel.sheet.binary.macroenabled.12'; break;
            case 'xltm': $mime='application/vnd.ms-excel.template.macroenabled.12'; break;
            case 'xlsm': $mime='application/vnd.ms-excel.sheet.macroenabled.12'; break;
            case 'chm': $mime='application/vnd.ms-htmlhelp'; break;
            case 'crd': $mime='application/x-mscardfile'; break;
            case 'lrm': $mime='application/vnd.ms-lrm'; break;
            case 'mvb': $mime='application/x-msmediaview'; break;
            case 'mny': $mime='application/x-msmoney'; break;
            case 'pptx': $mime='application/vnd.openxmlformats-officedocument.presentationml.presentation'; break;
            case 'sldx': $mime='application/vnd.openxmlformats-officedocument.presentationml.slide'; break;
            case 'ppsx': $mime='application/vnd.openxmlformats-officedocument.presentationml.slideshow'; break;
            case 'potx': $mime='application/vnd.openxmlformats-officedocument.presentationml.template'; break;
            case 'xlsx': $mime='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
            case 'xltx': $mime='application/vnd.openxmlformats-officedocument.spreadsheetml.template'; break;
            case 'docx': $mime='application/vnd.openxmlformats-officedocument.wordprocessingml.document'; break;
            case 'dotx': $mime='application/vnd.openxmlformats-officedocument.wordprocessingml.template'; break;
            case 'obd': $mime='application/x-msbinder'; break;
            case 'thmx': $mime='application/vnd.ms-officetheme'; break;
            case 'onetoc': $mime='application/onenote'; break;
            case 'pya': $mime='audio/vnd.ms-playready.media.pya'; break;
            case 'pyv': $mime='video/vnd.ms-playready.media.pyv'; break;
            case 'ppt': $mime='application/vnd.ms-powerpoint'; break;
            case 'ppam': $mime='application/vnd.ms-powerpoint.addin.macroenabled.12'; break;
            case 'sldm': $mime='application/vnd.ms-powerpoint.slide.macroenabled.12'; break;
            case 'pptm': $mime='application/vnd.ms-powerpoint.presentation.macroenabled.12'; break;
            case 'ppsm': $mime='application/vnd.ms-powerpoint.slideshow.macroenabled.12'; break;
            case 'mpp': $mime='application/vnd.ms-project'; break;
            case 'pub': $mime='application/x-mspublisher'; break;
            case 'scd': $mime='application/x-msschedule'; break;
            case 'xap': $mime='application/x-silverlight-app'; break;
            case 'stl': $mime='application/vnd.ms-pki.stl'; break;
            case 'cat': $mime='application/vnd.ms-pki.seccat'; break;
            case 'vsd': $mime='application/vnd.visio'; break;
            case 'wm': $mime='video/x-ms-wm'; break;
            case 'wma': $mime='audio/x-ms-wma'; break;
            case 'wax': $mime='audio/x-ms-wax'; break;
            case 'wmx': $mime='video/x-ms-wmx'; break;
            case 'wmd': $mime='application/x-ms-wmd'; break;
            case 'wpl': $mime='application/vnd.ms-wpl'; break;
            case 'wmz': $mime='application/x-ms-wmz'; break;
            case 'wmv': $mime='video/x-ms-wmv'; break;
            case 'wvx': $mime='video/x-ms-wvx'; break;
            case 'wmf': $mime='application/x-msmetafile'; break;
            case 'trm': $mime='application/x-msterminal'; break;
            case 'doc': $mime='application/msword'; break;
            case 'wri': $mime='application/x-mswrite'; break;
            case 'wps': $mime='application/vnd.ms-works'; break;
            case 'xbap': $mime='application/x-ms-xbap'; break;
            case 'xps': $mime='application/vnd.ms-xpsdocument'; break;
            case 'mid': $mime='audio/midi'; break;
            case 'mpy': $mime='application/vnd.ibm.minipay'; break;
            case 'afp': $mime='application/vnd.ibm.modcap'; break;
            case 'rms': $mime='application/vnd.jcp.javame.midlet-rms'; break;
            case 'tmo': $mime='application/vnd.tmobile-livetv'; break;
            case 'prc': $mime='application/x-mobipocket-ebook'; break;
            case 'mbk': $mime='application/vnd.mobius.mbk'; break;
            case 'dis': $mime='application/vnd.mobius.dis'; break;
            case 'plc': $mime='application/vnd.mobius.plc'; break;
            case 'mqy': $mime='application/vnd.mobius.mqy'; break;
            case 'msl': $mime='application/vnd.mobius.msl'; break;
            case 'txf': $mime='application/vnd.mobius.txf'; break;
            case 'daf': $mime='application/vnd.mobius.daf'; break;
            case 'fly': $mime='text/vnd.fly'; break;
            case 'mpc': $mime='application/vnd.mophun.certificate'; break;
            case 'mpn': $mime='application/vnd.mophun.application'; break;
            case 'mj2': $mime='video/mj2'; break;
            case 'mpga': $mime='audio/mpeg'; break;
            case 'mxu': $mime='video/vnd.mpegurl'; break;
            case 'mpeg': $mime='video/mpeg'; break;
            case 'm21': $mime='application/mp21'; break;
            case 'mp4a': $mime='audio/mp4'; break;
            case 'mp4': $mime='video/mp4'; break;
            case 'm3u8': $mime='application/x-mpegURL'; break;
            case 'ts': $mime='video/MP2T'; break;
            case 'mus': $mime='application/vnd.musician'; break;
            case 'msty': $mime='application/vnd.muvee.style'; break;
            case 'mxml': $mime='application/xv+xml'; break;
            case 'ngdat': $mime='application/vnd.nokia.n-gage.data'; break;
            case 'n-gage': $mime='application/vnd.nokia.n-gage.symbian.install'; break;
            case 'ncx': $mime='application/x-dtbncx+xml'; break;
            case 'nc': $mime='application/x-netcdf'; break;
            case 'nlu': $mime='application/vnd.neurolanguage.nlu'; break;
            case 'dna': $mime='application/vnd.dna'; break;
            case 'nnd': $mime='application/vnd.noblenet-directory'; break;
            case 'nns': $mime='application/vnd.noblenet-sealer'; break;
            case 'nnw': $mime='application/vnd.noblenet-web'; break;
            case 'rpst': $mime='application/vnd.nokia.radio-preset'; break;
            case 'rpss': $mime='application/vnd.nokia.radio-presets'; break;
            case 'n3': $mime='text/n3'; break;
            case 'edm': $mime='application/vnd.novadigm.edm'; break;
            case 'edx': $mime='application/vnd.novadigm.edx'; break;
            case 'ext': $mime='application/vnd.novadigm.ext'; break;
            case 'gph': $mime='application/vnd.flographit'; break;
            case 'ecelp4800': $mime='audio/vnd.nuera.ecelp4800'; break;
            case 'ecelp7470': $mime='audio/vnd.nuera.ecelp7470'; break;
            case 'ecelp9600': $mime='audio/vnd.nuera.ecelp9600'; break;
            case 'oda': $mime='application/oda'; break;
            case 'ogx': $mime='application/ogg'; break;
            case 'oga': $mime='audio/ogg'; break;
            case 'ogg': $mime='audio/ogg'; break;
            case 'ogv': $mime='video/ogg'; break;
            case 'dd2': $mime='application/vnd.oma.dd2+xml'; break;
            case 'oth': $mime='application/vnd.oasis.opendocument.text-web'; break;
            case 'opf': $mime='application/oebps-package+xml'; break;
            case 'qbo': $mime='application/vnd.intu.qbo'; break;
            case 'oxt': $mime='application/vnd.openofficeorg.extension'; break;
            case 'osf': $mime='application/vnd.yamaha.openscoreformat'; break;
            case 'weba': $mime='audio/webm'; break;
            case 'webm': $mime='video/webm'; break;
            case 'odc': $mime='application/vnd.oasis.opendocument.chart'; break;
            case 'otc': $mime='application/vnd.oasis.opendocument.chart-template'; break;
            case 'odb': $mime='application/vnd.oasis.opendocument.database'; break;
            case 'odf': $mime='application/vnd.oasis.opendocument.formula'; break;
            case 'odft': $mime='application/vnd.oasis.opendocument.formula-template'; break;
            case 'odg': $mime='application/vnd.oasis.opendocument.graphics'; break;
            case 'otg': $mime='application/vnd.oasis.opendocument.graphics-template'; break;
            case 'odi': $mime='application/vnd.oasis.opendocument.image'; break;
            case 'oti': $mime='application/vnd.oasis.opendocument.image-template'; break;
            case 'odp': $mime='application/vnd.oasis.opendocument.presentation'; break;
            case 'otp': $mime='application/vnd.oasis.opendocument.presentation-template'; break;
            case 'ods': $mime='application/vnd.oasis.opendocument.spreadsheet'; break;
            case 'ots': $mime='application/vnd.oasis.opendocument.spreadsheet-template'; break;
            case 'odt': $mime='application/vnd.oasis.opendocument.text'; break;
            case 'odm': $mime='application/vnd.oasis.opendocument.text-master'; break;
            case 'ott': $mime='application/vnd.oasis.opendocument.text-template'; break;
            case 'php': $mime='text/plain'; break;
            case 'sql': $mime='text/x-sql'; break;
            case 'ktx': $mime='image/ktx'; break;
            case 'sxc': $mime='application/vnd.sun.xml.calc'; break;
            case 'stc': $mime='application/vnd.sun.xml.calc.template'; break;
            case 'sxd': $mime='application/vnd.sun.xml.draw'; break;
            case 'std': $mime='application/vnd.sun.xml.draw.template'; break;
            case 'sxi': $mime='application/vnd.sun.xml.impress'; break;
            case 'sti': $mime='application/vnd.sun.xml.impress.template'; break;
            case 'sxm': $mime='application/vnd.sun.xml.math'; break;
            case 'sxw': $mime='application/vnd.sun.xml.writer'; break;
            case 'sxg': $mime='application/vnd.sun.xml.writer.global'; break;
            case 'stw': $mime='application/vnd.sun.xml.writer.template'; break;
            case 'otf': $mime='application/x-font-otf'; break;
            case 'osfpvg': $mime='application/vnd.yamaha.openscoreformat.osfpvg+xml'; break;
            case 'dp': $mime='application/vnd.osgi.dp'; break;
            case 'pdb': $mime='application/vnd.palm'; break;
            case 'p': $mime='text/x-pascal'; break;
            case 'paw': $mime='application/vnd.pawaafile'; break;
            case 'pclxl': $mime='application/vnd.hp-pclxl'; break;
            case 'efif': $mime='application/vnd.picsel'; break;
            case 'pcx': $mime='image/x-pcx'; break;
            case 'psd': $mime='image/vnd.adobe.photoshop'; break;
            case 'prf': $mime='application/pics-rules'; break;
            case 'pic': $mime='image/x-pict'; break;
            case 'chat': $mime='application/x-chat'; break;
            case 'p10': $mime='application/pkcs10'; break;
            case 'p12': $mime='application/x-pkcs12'; break;
            case 'p7m': $mime='application/pkcs7-mime'; break;
            case 'p7s': $mime='application/pkcs7-signature'; break;
            case 'p7r': $mime='application/x-pkcs7-certreqresp'; break;
            case 'p7b': $mime='application/x-pkcs7-certificates'; break;
            case 'p8': $mime='application/pkcs8'; break;
            case 'plf': $mime='application/vnd.pocketlearn'; break;
            case 'pnm': $mime='image/x-portable-anymap'; break;
            case 'pbm': $mime='image/x-portable-bitmap'; break;
            case 'pcf': $mime='application/x-font-pcf'; break;
            case 'pfr': $mime='application/font-tdpfr'; break;
            case 'pgn': $mime='application/x-chess-pgn'; break;
            case 'pgm': $mime='image/x-portable-graymap'; break;
            case 'png': $mime='image/png'; break;
            case 'ppm': $mime='image/x-portable-pixmap'; break;
            case 'pskcxml': $mime='application/pskc+xml'; break;
            case 'pml': $mime='application/vnd.ctc-posml'; break;
            case 'ai': $mime='application/postscript'; break;
            case 'pfa': $mime='application/x-font-type1'; break;
            case 'pbd': $mime='application/vnd.powerbuilder6'; break;
            case '': $mime='application/pgp-encrypted'; break;
            case 'pgp': $mime='application/pgp-signature'; break;
            case 'box': $mime='application/vnd.previewsystems.box'; break;
            case 'ptid': $mime='application/vnd.pvi.ptid1'; break;
            case 'pls': $mime='application/pls+xml'; break;
            case 'str': $mime='application/vnd.pg.format'; break;
            case 'ei6': $mime='application/vnd.pg.osasli'; break;
            case 'dsc': $mime='text/prs.lines.tag'; break;
            case 'psf': $mime='application/x-font-linux-psf'; break;
            case 'qps': $mime='application/vnd.publishare-delta-tree'; break;
            case 'wg': $mime='application/vnd.pmi.widget'; break;
            case 'qxd': $mime='application/vnd.quark.quarkxpress'; break;
            case 'esf': $mime='application/vnd.epson.esf'; break;
            case 'msf': $mime='application/vnd.epson.msf'; break;
            case 'ssf': $mime='application/vnd.epson.ssf'; break;
            case 'qam': $mime='application/vnd.epson.quickanime'; break;
            case 'qfx': $mime='application/vnd.intu.qfx'; break;
            case 'qt': $mime='video/quicktime'; break;
            case 'rar': $mime='application/x-rar-compressed'; break;
            case 'ram': $mime='audio/x-pn-realaudio'; break;
            case 'rmp': $mime='audio/x-pn-realaudio-plugin'; break;
            case 'rsd': $mime='application/rsd+xml'; break;
            case 'rm': $mime='application/vnd.rn-realmedia'; break;
            case 'bed': $mime='application/vnd.realvnc.bed'; break;
            case 'mxl': $mime='application/vnd.recordare.musicxml'; break;
            case 'musicxml': $mime='application/vnd.recordare.musicxml+xml'; break;
            case 'rnc': $mime='application/relax-ng-compact-syntax'; break;
            case 'rdz': $mime='application/vnd.data-vision.rdz'; break;
            case 'rdf': $mime='application/rdf+xml'; break;
            case 'rp9': $mime='application/vnd.cloanto.rp9'; break;
            case 'jisp': $mime='application/vnd.jisp'; break;
            case 'rtf': $mime='application/rtf'; break;
            case 'rtx': $mime='text/richtext'; break;
            case 'link66': $mime='application/vnd.route66.link66+xml'; break;
            case 'rss, xml': $mime='application/rss+xml'; break;
            case 'shf': $mime='application/shf+xml'; break;
            case 'st': $mime='application/vnd.sailingtracker.track'; break;
            case 'svg': $mime='image/svg+xml'; break;
            case 'svgz': $mime='image/svg+xml'; break;
            case 'sus': $mime='application/vnd.sus-calendar'; break;
            case 'sru': $mime='application/sru+xml'; break;
            case 'setpay': $mime='application/set-payment-initiation'; break;
            case 'setreg': $mime='application/set-registration-initiation'; break;
            case 'sema': $mime='application/vnd.sema'; break;
            case 'semd': $mime='application/vnd.semd'; break;
            case 'semf': $mime='application/vnd.semf'; break;
            case 'see': $mime='application/vnd.seemail'; break;
            case 'snf': $mime='application/x-font-snf'; break;
            case 'spq': $mime='application/scvp-vp-request'; break;
            case 'spp': $mime='application/scvp-vp-response'; break;
            case 'scq': $mime='application/scvp-cv-request'; break;
            case 'scs': $mime='application/scvp-cv-response'; break;
            case 'sdp': $mime='application/sdp'; break;
            case 'etx': $mime='text/x-setext'; break;
            case 'movie': $mime='video/x-sgi-movie'; break;
            case 'ifm': $mime='application/vnd.shana.informed.formdata'; break;
            case 'itp': $mime='application/vnd.shana.informed.formtemplate'; break;
            case 'iif': $mime='application/vnd.shana.informed.interchange'; break;
            case 'ipk': $mime='application/vnd.shana.informed.package'; break;
            case 'tfi': $mime='application/thraud+xml'; break;
            case 'shar': $mime='application/x-shar'; break;
            case 'rgb': $mime='image/x-rgb'; break;
            case 'slt': $mime='application/vnd.epson.salt'; break;
            case 'aso': $mime='application/vnd.accpac.simply.aso'; break;
            case 'imp': $mime='application/vnd.accpac.simply.imp'; break;
            case 'twd': $mime='application/vnd.simtech-mindmapper'; break;
            case 'csp': $mime='application/vnd.commonspace'; break;
            case 'saf': $mime='application/vnd.yamaha.smaf-audio'; break;
            case 'mmf': $mime='application/vnd.smaf'; break;
            case 'spf': $mime='application/vnd.yamaha.smaf-phrase'; break;
            case 'teacher': $mime='application/vnd.smart.teacher'; break;
            case 'svd': $mime='application/vnd.svd'; break;
            case 'rq': $mime='application/sparql-query'; break;
            case 'srx': $mime='application/sparql-results+xml'; break;
            case 'gram': $mime='application/srgs'; break;
            case 'grxml': $mime='application/srgs+xml'; break;
            case 'ssml': $mime='application/ssml+xml'; break;
            case 'skp': $mime='application/vnd.koan'; break;
            case 'sgml': $mime='text/sgml'; break;
            case 'sdc': $mime='application/vnd.stardivision.calc'; break;
            case 'sda': $mime='application/vnd.stardivision.draw'; break;
            case 'sdd': $mime='application/vnd.stardivision.impress'; break;
            case 'smf': $mime='application/vnd.stardivision.math'; break;
            case 'sdw': $mime='application/vnd.stardivision.writer'; break;
            case 'sgl': $mime='application/vnd.stardivision.writer-global'; break;
            case 'sm': $mime='application/vnd.stepmania.stepchart'; break;
            case 'sit': $mime='application/x-stuffit'; break;
            case 'sitx': $mime='application/x-stuffitx'; break;
            case 'sdkm': $mime='application/vnd.solent.sdkm+xml'; break;
            case 'xo': $mime='application/vnd.olpc-sugar'; break;
            case 'au': $mime='audio/basic'; break;
            case 'wqd': $mime='application/vnd.wqd'; break;
            case 'sis': $mime='application/vnd.symbian.install'; break;
            case 'smi': $mime='application/smil+xml'; break;
            case 'xsm': $mime='application/vnd.syncml+xml'; break;
            case 'bdm': $mime='application/vnd.syncml.dm+wbxml'; break;
            case 'xdm': $mime='application/vnd.syncml.dm+xml'; break;
            case 'sv4cpio': $mime='application/x-sv4cpio'; break;
            case 'sv4crc': $mime='application/x-sv4crc'; break;
            case 'sbml': $mime='application/sbml+xml'; break;
            case 'tsv': $mime='text/tab-separated-values'; break;
            case 'tiff': $mime='image/tiff'; break;
            case 'tao': $mime='application/vnd.tao.intent-module-archive'; break;
            case 'tar': $mime='application/x-tar'; break;
            case 'tcl': $mime='application/x-tcl'; break;
            case 'tex': $mime='application/x-tex'; break;
            case 'tfm': $mime='application/x-tex-tfm'; break;
            case 'tei': $mime='application/tei+xml'; break;
            case 'txt': $mime='text/plain'; break;
            case 'dxp': $mime='application/vnd.spotfire.dxp'; break;
            case 'sfs': $mime='application/vnd.spotfire.sfs'; break;
            case 'tsd': $mime='application/timestamped-data'; break;
            case 'tpt': $mime='application/vnd.trid.tpt'; break;
            case 'mxs': $mime='application/vnd.triscape.mxs'; break;
            case 't': $mime='text/troff'; break;
            case 'tra': $mime='application/vnd.trueapp'; break;
            case 'ttf': $mime='application/x-font-ttf'; break;
            case 'ttl': $mime='text/turtle'; break;
            case 'umj': $mime='application/vnd.umajin'; break;
            case 'uoml': $mime='application/vnd.uoml+xml'; break;
            case 'unityweb': $mime='application/vnd.unity'; break;
            case 'ufd': $mime='application/vnd.ufdl'; break;
            case 'uri': $mime='text/uri-list'; break;
            case 'utz': $mime='application/vnd.uiq.theme'; break;
            case 'ustar': $mime='application/x-ustar'; break;
            case 'uu': $mime='text/x-uuencode'; break;
            case 'vcs': $mime='text/x-vcalendar'; break;
            case 'vcf': $mime='text/x-vcard'; break;
            case 'vcd': $mime='application/x-cdlink'; break;
            case 'vsf': $mime='application/vnd.vsf'; break;
            case 'wrl': $mime='model/vrml'; break;
            case 'vcx': $mime='application/vnd.vcx'; break;
            case 'mts': $mime='model/vnd.mts'; break;
            case 'vtu': $mime='model/vnd.vtu'; break;
            case 'vis': $mime='application/vnd.visionary'; break;
            case 'viv': $mime='video/vnd.vivo'; break;
            case 'ccxml': $mime='application/ccxml+xml,'; break;
            case 'vxml': $mime='application/voicexml+xml'; break;
            case 'src': $mime='application/x-wais-source'; break;
            case 'wbxml': $mime='application/vnd.wap.wbxml'; break;
            case 'wbmp': $mime='image/vnd.wap.wbmp'; break;
            case 'wav': $mime='audio/x-wav'; break;
            case 'davmount': $mime='application/davmount+xml'; break;
            case 'woff': $mime='application/x-font-woff'; break;
            case 'wspolicy': $mime='application/wspolicy+xml'; break;
            case 'webp': $mime='image/webp'; break;
            case 'wtb': $mime='application/vnd.webturbo'; break;
            case 'wgt': $mime='application/widget'; break;
            case 'hlp': $mime='application/winhlp'; break;
            case 'wml': $mime='text/vnd.wap.wml'; break;
            case 'wmls': $mime='text/vnd.wap.wmlscript'; break;
            case 'wmlsc': $mime='application/vnd.wap.wmlscriptc'; break;
            case 'wpd': $mime='application/vnd.wordperfect'; break;
            case 'stf': $mime='application/vnd.wt.stf'; break;
            case 'wsdl': $mime='application/wsdl+xml'; break;
            case 'xbm': $mime='image/x-xbitmap'; break;
            case 'xpm': $mime='image/x-xpixmap'; break;
            case 'xwd': $mime='image/x-xwindowdump'; break;
            case 'der': $mime='application/x-x509-ca-cert'; break;
            case 'fig': $mime='application/x-xfig'; break;
            case 'xhtml': $mime='application/xhtml+xml'; break;
            case 'xml': $mime='application/xml'; break;
            case 'xdf': $mime='application/xcap-diff+xml'; break;
            case 'xenc': $mime='application/xenc+xml'; break;
            case 'xer': $mime='application/patch-ops-error+xml'; break;
            case 'rl': $mime='application/resource-lists+xml'; break;
            case 'rs': $mime='application/rls-services+xml'; break;
            case 'rld': $mime='application/resource-lists-diff+xml'; break;
            case 'xslt': $mime='application/xslt+xml'; break;
            case 'xop': $mime='application/xop+xml'; break;
            case 'xpi': $mime='application/x-xpinstall'; break;
            case 'xspf': $mime='application/xspf+xml'; break;
            case 'xul': $mime='application/vnd.mozilla.xul+xml'; break;
            case 'xyz': $mime='chemical/x-xyz'; break;
            case 'yang': $mime='application/yang'; break;
            case 'yin': $mime='application/yin+xml'; break;
            case 'zir': $mime='application/vnd.zul'; break;
            case 'zip': $mime='application/zip'; break;
            case 'zmm': $mime='application/vnd.handheld-entertainment+xml'; break;
            case 'zaz': $mime='application/vnd.zzazz.deck+xml'; break;
            case 'exr': $mime='image/x-exr; version="2"'; break;
            case 'mp3': $mime='audio/mpeg'; break;
            case 'flac': $mime='audio/flac'; break;
            case 'md': case 'markdn': case 'markdown': case 'mdown': $mime='text/markdown'; break;
            default:
                Utils::error_log('ext not found ! '.$ext);
        }
        return $mime;
    }

    public static function get_ext_from_mime($mime) {
        $ext = '';
        switch ($mime){
            case 'application/vnd.hzn-3d-crossword': $ext='x3d'; break;
            case 'video/3gpp': $ext='3gp'; break;
            case 'video/3gpp2': $ext='3g2'; break;
            case 'application/vnd.mseq': $ext='mseq'; break;
            case 'application/vnd.3m.post-it-notes': $ext='pwn'; break;
            case 'application/vnd.3gpp.pic-bw-large': $ext='plb'; break;
            case 'application/vnd.3gpp.pic-bw-small': $ext='psb'; break;
            case 'application/vnd.3gpp.pic-bw-var': $ext='pvb'; break;
            case 'application/vnd.3gpp2.tcap': $ext='tcap'; break;
            case 'application/x-7z-compressed': $ext='7z'; break;
            case 'application/x-abiword': $ext='abw'; break;
            case 'application/x-ace-compressed': $ext='ace'; break;
            case 'application/vnd.americandynamics.acc': $ext='acc'; break;
            case 'application/vnd.acucobol': $ext='acu'; break;
            case 'application/vnd.acucorp': $ext='atc'; break;
            case 'audio/adpcm': $ext='adp'; break;
            case 'application/x-authorware-bin': $ext='aab'; break;
            case 'application/x-authorware-map': $ext='aam'; break;
            case 'application/x-authorware-seg': $ext='aas'; break;
            case 'application/vnd.adobe.air-application-installer-package+zip': $ext='air'; break;
            case 'application/x-shockwave-flash': $ext='swf'; break;
            case 'application/vnd.adobe.fxp': $ext='fxp'; break;
            case 'application/pdf': $ext='pdf'; break;
            case 'application/vnd.cups-ppd': $ext='ppd'; break;
            case 'application/x-director': $ext='dir'; break;
            case 'application/vnd.adobe.xdp+xml': $ext='xdp'; break;
            case 'application/vnd.adobe.xfdf': $ext='xfdf'; break;
            case 'audio/x-aac': $ext='aac'; break;
            case 'application/vnd.ahead.space': $ext='ahead'; break;
            case 'application/vnd.airzip.filesecure.azf': $ext='azf'; break;
            case 'application/vnd.airzip.filesecure.azs': $ext='azs'; break;
            case 'application/vnd.amazon.ebook': $ext='azw'; break;
            case 'application/vnd.amiga.ami': $ext='ami'; break;
            case 'application/andrew-inset': $ext='N/A'; break;
            case 'application/vnd.android.package-archive': $ext='apk'; break;
            case 'application/vnd.anser-web-certificate-issue-initiation': $ext='cii'; break;
            case 'application/vnd.anser-web-funds-transfer-initiation': $ext='fti'; break;
            case 'application/vnd.antix.game-component': $ext='atx'; break;
            case 'application/vnd.apple.installer+xml': $ext='mpkg'; break;
            case 'application/applixware': $ext='aw'; break;
            case 'application/vnd.hhe.lesson-player': $ext='les'; break;
            case 'application/vnd.aristanetworks.swi': $ext='swi'; break;
            case 'text/x-asm': $ext='s'; break;
            case 'application/atomcat+xml': $ext='atomcat'; break;
            case 'application/atomsvc+xml': $ext='atomsvc'; break;
            case 'application/atom+xml': $ext='atom, xml'; break;
            case 'application/pkix-attr-cert': $ext='ac'; break;
            case 'audio/x-aiff': $ext='aif'; break;
            case 'video/x-msvideo': $ext='avi'; break;
            case 'application/vnd.audiograph': $ext='aep'; break;
            case 'image/vnd.dxf': $ext='dxf'; break;
            case 'model/vnd.dwf': $ext='dwf'; break;
            case 'text/plain-bas': $ext='par'; break;
            case 'application/x-bcpio': $ext='bcpio'; break;
            case 'application/octet-stream': $ext='bin'; break;
            case 'application/octet-stream': $ext='dat'; break;
            case 'image/bmp': $ext='bmp'; break;
            case 'application/x-bittorrent': $ext='torrent'; break;
            case 'application/vnd.rim.cod': $ext='cod'; break;
            case 'application/vnd.blueice.multipass': $ext='mpm'; break;
            case 'application/vnd.bmi': $ext='bmi'; break;
            case 'application/x-sh': $ext='sh'; break;
            case 'image/prs.btif': $ext='btif'; break;
            case 'application/vnd.businessobjects': $ext='rep'; break;
            case 'application/x-bzip': $ext='bz'; break;
            case 'application/x-bzip2': $ext='bz2'; break;
            case 'application/x-csh': $ext='csh'; break;
            case 'text/x-c': $ext='c'; break;
            case 'application/vnd.chemdraw+xml': $ext='cdxml'; break;
            case 'text/css': $ext='css'; break;
            case 'chemical/x-cdx': $ext='cdx'; break;
            case 'chemical/x-cml': $ext='cml'; break;
            case 'chemical/x-csml': $ext='csml'; break;
            case 'application/vnd.contact.cmsg': $ext='cdbcmsg'; break;
            case 'application/vnd.claymore': $ext='cla'; break;
            case 'application/vnd.clonk.c4group': $ext='c4g'; break;
            case 'image/vnd.dvb.subtitle': $ext='sub'; break;
            case 'application/cdmi-capability': $ext='cdmia'; break;
            case 'application/cdmi-container': $ext='cdmic'; break;
            case 'application/cdmi-domain': $ext='cdmid'; break;
            case 'application/cdmi-object': $ext='cdmio'; break;
            case 'application/cdmi-queue': $ext='cdmiq'; break;
            case 'application/vnd.cluetrust.cartomobile-config': $ext='c11amc'; break;
            case 'application/vnd.cluetrust.cartomobile-config-pkg': $ext='c11amz'; break;
            case 'image/x-cmu-raster': $ext='ras'; break;
            case 'model/vnd.collada+xml': $ext='dae'; break;
            case 'text/csv': $ext='csv'; break;
            case 'application/mac-compactpro': $ext='cpt'; break;
            case 'application/vnd.wap.wmlc': $ext='wmlc'; break;
            case 'image/cgm': $ext='cgm'; break;
            case 'x-conference/x-cooltalk': $ext='ice'; break;
            case 'image/x-cmx': $ext='cmx'; break;
            case 'application/vnd.xara': $ext='xar'; break;
            case 'application/vnd.cosmocaller': $ext='cmc'; break;
            case 'application/x-cpio': $ext='cpio'; break;
            case 'application/vnd.crick.clicker': $ext='clkx'; break;
            case 'application/vnd.crick.clicker.keyboard': $ext='clkk'; break;
            case 'application/vnd.crick.clicker.palette': $ext='clkp'; break;
            case 'application/vnd.crick.clicker.template': $ext='clkt'; break;
            case 'application/vnd.crick.clicker.wordbank': $ext='clkw'; break;
            case 'application/vnd.criticaltools.wbs+xml': $ext='wbs'; break;
            case 'application/vnd.rig.cryptonote': $ext='cryptonote'; break;
            case 'chemical/x-cif': $ext='cif'; break;
            case 'chemical/x-cmdf': $ext='cmdf'; break;
            case 'application/cu-seeme': $ext='cu'; break;
            case 'application/prs.cww': $ext='cww'; break;
            case 'text/vnd.curl': $ext='curl'; break;
            case 'text/vnd.curl.dcurl': $ext='dcurl'; break;
            case 'text/vnd.curl.mcurl': $ext='mcurl'; break;
            case 'text/vnd.curl.scurl': $ext='scurl'; break;
            case 'application/vnd.curl.car': $ext='car'; break;
            case 'application/vnd.curl.pcurl': $ext='pcurl'; break;
            case 'application/vnd.yellowriver-custom-menu': $ext='cmp'; break;
            case 'application/dssc+der': $ext='dssc'; break;
            case 'application/dssc+xml': $ext='xdssc'; break;
            case 'application/x-debian-package': $ext='deb'; break;
            case 'audio/vnd.dece.audio': $ext='uva'; break;
            case 'image/vnd.dece.graphic': $ext='uvi'; break;
            case 'video/vnd.dece.hd': $ext='uvh'; break;
            case 'video/vnd.dece.mobile': $ext='uvm'; break;
            case 'video/vnd.uvvu.mp4': $ext='uvu'; break;
            case 'video/vnd.dece.pd': $ext='uvp'; break;
            case 'video/vnd.dece.sd': $ext='uvs'; break;
            case 'video/vnd.dece.video': $ext='uvv'; break;
            case 'application/x-dvi': $ext='dvi'; break;
            case 'application/vnd.fdsn.seed': $ext='seed'; break;
            case 'application/x-dtbook+xml': $ext='dtb'; break;
            case 'application/x-dtbresource+xml': $ext='res'; break;
            case 'application/vnd.dvb.ait': $ext='ait'; break;
            case 'application/vnd.dvb.service': $ext='svc'; break;
            case 'audio/vnd.digital-winds': $ext='eol'; break;
            case 'image/vnd.djvu': $ext='djvu'; break;
            case 'application/xml-dtd': $ext='dtd'; break;
            case 'application/vnd.dolby.mlp': $ext='mlp'; break;
            case 'application/x-doom': $ext='wad'; break;
            case 'application/vnd.dpgraph': $ext='dpg'; break;
            case 'audio/vnd.dra': $ext='dra'; break;
            case 'application/vnd.dreamfactory': $ext='dfac'; break;
            case 'audio/vnd.dts': $ext='dts'; break;
            case 'audio/vnd.dts.hd': $ext='dtshd'; break;
            case 'image/vnd.dwg': $ext='dwg'; break;
            case 'application/vnd.dynageo': $ext='geo'; break;
            case 'application/ecmascript': $ext='es'; break;
            case 'application/vnd.ecowin.chart': $ext='mag'; break;
            case 'image/vnd.fujixerox.edmics-mmr': $ext='mmr'; break;
            case 'image/vnd.fujixerox.edmics-rlc': $ext='rlc'; break;
            case 'application/exi': $ext='exi'; break;
            case 'application/vnd.proteus.magazine': $ext='mgz'; break;
            case 'application/epub+zip': $ext='epub'; break;
            case 'message/rfc822': $ext='eml'; break;
            case 'application/vnd.enliven': $ext='nml'; break;
            case 'application/vnd.is-xpr': $ext='xpr'; break;
            case 'image/vnd.xiff': $ext='xif'; break;
            case 'application/vnd.xfdl': $ext='xfdl'; break;
            case 'application/emma+xml': $ext='emma'; break;
            case 'application/vnd.ezpix-album': $ext='ez2'; break;
            case 'application/vnd.ezpix-package': $ext='ez3'; break;
            case 'image/vnd.fst': $ext='fst'; break;
            case 'video/vnd.fvt': $ext='fvt'; break;
            case 'image/vnd.fastbidsheet': $ext='fbs'; break;
            case 'application/vnd.denovo.fcselayout-link': $ext='fe_launch'; break;
            case 'video/x-f4v': $ext='f4v'; break;
            case 'video/x-flv': $ext='flv'; break;
            case 'image/vnd.fpx': $ext='fpx'; break;
            case 'image/vnd.net-fpx': $ext='npx'; break;
            case 'text/vnd.fmi.flexstor': $ext='flx'; break;
            case 'video/x-fli': $ext='fli'; break;
            case 'application/vnd.fluxtime.clip': $ext='ftc'; break;
            case 'application/vnd.fdf': $ext='fdf'; break;
            case 'text/x-fortran': $ext='f'; break;
            case 'application/vnd.mif': $ext='mif'; break;
            case 'application/vnd.framemaker': $ext='fm'; break;
            case 'image/x-freehand': $ext='fh'; break;
            case 'application/vnd.fsc.weblaunch': $ext='fsc'; break;
            case 'application/vnd.frogans.fnc': $ext='fnc'; break;
            case 'application/vnd.frogans.ltf': $ext='ltf'; break;
            case 'application/vnd.fujixerox.ddd': $ext='ddd'; break;
            case 'application/vnd.fujixerox.docuworks': $ext='xdw'; break;
            case 'application/vnd.fujixerox.docuworks.binder': $ext='xbd'; break;
            case 'application/vnd.fujitsu.oasys': $ext='oas'; break;
            case 'application/vnd.fujitsu.oasys2': $ext='oa2'; break;
            case 'application/vnd.fujitsu.oasys3': $ext='oa3'; break;
            case 'application/vnd.fujitsu.oasysgp': $ext='fg5'; break;
            case 'application/vnd.fujitsu.oasysprs': $ext='bh2'; break;
            case 'application/x-futuresplash': $ext='spl'; break;
            case 'application/vnd.fuzzysheet': $ext='fzs'; break;
            case 'image/g3fax': $ext='g3'; break;
            case 'application/vnd.gmx': $ext='gmx'; break;
            case 'model/vnd.gtw': $ext='gtw'; break;
            case 'application/vnd.genomatix.tuxedo': $ext='txd'; break;
            case 'application/vnd.geogebra.file': $ext='ggb'; break;
            case 'application/vnd.geogebra.tool': $ext='ggt'; break;
            case 'model/vnd.gdl': $ext='gdl'; break;
            case 'application/vnd.geometry-explorer': $ext='gex'; break;
            case 'application/vnd.geonext': $ext='gxt'; break;
            case 'application/vnd.geoplan': $ext='g2w'; break;
            case 'application/vnd.geospace': $ext='g3w'; break;
            case 'application/x-font-ghostscript': $ext='gsf'; break;
            case 'application/x-font-bdf': $ext='bdf'; break;
            case 'application/x-gtar': $ext='gtar'; break;
            case 'application/x-texinfo': $ext='texinfo'; break;
            case 'application/x-gnumeric': $ext='gnumeric'; break;
            case 'application/vnd.google-earth.kml+xml': $ext='kml'; break;
            case 'application/vnd.google-earth.kmz': $ext='kmz'; break;
            case 'application/vnd.grafeq': $ext='gqf'; break;
            case 'image/gif': $ext='gif'; break;
            case 'text/vnd.graphviz': $ext='gv'; break;
            case 'application/vnd.groove-account': $ext='gac'; break;
            case 'application/vnd.groove-help': $ext='ghf'; break;
            case 'application/vnd.groove-identity-message': $ext='gim'; break;
            case 'application/vnd.groove-injector': $ext='grv'; break;
            case 'application/vnd.groove-tool-message': $ext='gtm'; break;
            case 'application/vnd.groove-tool-template': $ext='tpl'; break;
            case 'application/vnd.groove-vcard': $ext='vcg'; break;
            case 'video/h261': $ext='h261'; break;
            case 'video/h263': $ext='h263'; break;
            case 'video/h264': $ext='h264'; break;
            case 'application/vnd.hp-hpid': $ext='hpid'; break;
            case 'application/vnd.hp-hps': $ext='hps'; break;
            case 'application/x-hdf': $ext='hdf'; break;
            case 'audio/vnd.rip': $ext='rip'; break;
            case 'application/vnd.hbci': $ext='hbci'; break;
            case 'application/vnd.hp-jlyt': $ext='jlt'; break;
            case 'application/vnd.hp-pcl': $ext='pcl'; break;
            case 'application/vnd.hp-hpgl': $ext='hpgl'; break;
            case 'application/vnd.yamaha.hv-script': $ext='hvs'; break;
            case 'application/vnd.yamaha.hv-dic': $ext='hvd'; break;
            case 'application/vnd.yamaha.hv-voice': $ext='hvp'; break;
            case 'application/vnd.hydrostatix.sof-data': $ext='sfd-hdstx'; break;
            case 'application/hyperstudio': $ext='stk'; break;
            case 'application/vnd.hal+xml': $ext='hal'; break;
            case 'text/html': $ext='html'; break;
            case 'application/vnd.ibm.rights-management': $ext='irm'; break;
            case 'application/vnd.ibm.secure-container': $ext='sc'; break;
            case 'text/calendar': $ext='ics'; break;
            case 'application/vnd.iccprofile': $ext='icc'; break;
            case 'image/x-icon': $ext='ico'; break;
            case 'application/vnd.igloader': $ext='igl'; break;
            case 'image/ief': $ext='ief'; break;
            case 'application/vnd.immervision-ivp': $ext='ivp'; break;
            case 'application/vnd.immervision-ivu': $ext='ivu'; break;
            case 'application/reginfo+xml': $ext='rif'; break;
            case 'text/vnd.in3d.3dml': $ext='3dml'; break;
            case 'text/vnd.in3d.spot': $ext='spot'; break;
            case 'model/iges': $ext='igs'; break;
            case 'application/vnd.intergeo': $ext='i2g'; break;
            case 'application/vnd.cinderella': $ext='cdy'; break;
            case 'application/vnd.intercon.formnet': $ext='xpw'; break;
            case 'application/vnd.isac.fcs': $ext='fcs'; break;
            case 'application/ipfix': $ext='ipfix'; break;
            case 'application/pkix-cert': $ext='cer'; break;
            case 'application/pkixcmp': $ext='pki'; break;
            case 'application/pkix-crl': $ext='crl'; break;
            case 'application/pkix-pkipath': $ext='pkipath'; break;
            case 'application/vnd.insors.igm': $ext='igm'; break;
            case 'application/vnd.ipunplugged.rcprofile': $ext='rcprofile'; break;
            case 'application/vnd.irepository.package+xml': $ext='irp'; break;
            case 'text/vnd.sun.j2me.app-descriptor': $ext='jad'; break;
            case 'application/java-archive': $ext='jar'; break;
            case 'application/java-vm': $ext='class'; break;
            case 'application/x-java-jnlp-file': $ext='jnlp'; break;
            case 'application/java-serialized-object': $ext='ser'; break;
            case 'text/x-java-source,java': $ext='java'; break;
            case 'application/javascript': $ext='js'; break;
            case 'application/json': $ext='json'; break;
            case 'application/vnd.joost.joda-archive': $ext='joda'; break;
            case 'video/jpm': $ext='jpm'; break;
            case 'image/jpeg': $ext='jpeg'; break;
            case 'image/jpeg': $ext='jpg'; break;
            case 'video/jpeg': $ext='jpgv'; break;
            case 'application/vnd.kahootz': $ext='ktz'; break;
            case 'application/vnd.chipnuts.karaoke-mmd': $ext='mmd'; break;
            case 'application/vnd.kde.karbon': $ext='karbon'; break;
            case 'application/vnd.kde.kchart': $ext='chrt'; break;
            case 'application/vnd.kde.kformula': $ext='kfo'; break;
            case 'application/vnd.kde.kivio': $ext='flw'; break;
            case 'application/vnd.kde.kontour': $ext='kon'; break;
            case 'application/vnd.kde.kpresenter': $ext='kpr'; break;
            case 'application/vnd.kde.kspread': $ext='ksp'; break;
            case 'application/vnd.kde.kword': $ext='kwd'; break;
            case 'application/vnd.kenameaapp': $ext='htke'; break;
            case 'application/vnd.kidspiration': $ext='kia'; break;
            case 'application/vnd.kinar': $ext='kne'; break;
            case 'application/vnd.kodak-descriptor': $ext='sse'; break;
            case 'application/vnd.las.las+xml': $ext='lasxml'; break;
            case 'application/x-latex': $ext='latex'; break;
            case 'application/vnd.llamagraphics.life-balance.desktop': $ext='lbd'; break;
            case 'application/vnd.llamagraphics.life-balance.exchange+xml': $ext='lbe'; break;
            case 'application/vnd.jam': $ext='jam'; break;
            case 'application/vnd.lotus-1-2-3': $ext='123'; break;
            case 'application/vnd.lotus-approach': $ext='apr'; break;
            case 'application/vnd.lotus-freelance': $ext='pre'; break;
            case 'application/vnd.lotus-notes': $ext='nsf'; break;
            case 'application/vnd.lotus-organizer': $ext='org'; break;
            case 'application/vnd.lotus-screencam': $ext='scm'; break;
            case 'application/vnd.lotus-wordpro': $ext='lwp'; break;
            case 'audio/vnd.lucent.voice': $ext='lvp'; break;
            case 'audio/x-mpegurl': $ext='m3u'; break;
            case 'video/x-m4v': $ext='m4v'; break;
            case 'audio/m4a': $ext='m4a'; break;
            case 'application/mac-binhex40': $ext='hqx'; break;
            case 'application/vnd.macports.portpkg': $ext='portpkg'; break;
            case 'application/vnd.osgeo.mapguide.package': $ext='mgp'; break;
            case 'application/marc': $ext='mrc'; break;
            case 'application/marcxml+xml': $ext='mrcx'; break;
            case 'application/mxf': $ext='mxf'; break;
            case 'application/vnd.wolfram.player': $ext='nbp'; break;
            case 'application/mathematica': $ext='ma'; break;
            case 'application/mathml+xml': $ext='mathml'; break;
            case 'application/mbox': $ext='mbox'; break;
            case 'application/vnd.medcalcdata': $ext='mc1'; break;
            case 'application/mediaservercontrol+xml': $ext='mscml'; break;
            case 'application/vnd.mediastation.cdkey': $ext='cdkey'; break;
            case 'application/vnd.mfer': $ext='mwf'; break;
            case 'application/vnd.mfmp': $ext='mfm'; break;
            case 'model/mesh': $ext='msh'; break;
            case 'application/mads+xml': $ext='mads'; break;
            case 'application/mets+xml': $ext='mets'; break;
            case 'application/mods+xml': $ext='mods'; break;
            case 'application/metalink4+xml': $ext='meta4'; break;
            case 'application/vnd.ms-powerpoint.template.macroenabled.12': $ext='potm'; break;
            case 'application/vnd.ms-word.document.macroenabled.12': $ext='docm'; break;
            case 'application/vnd.ms-word.template.macroenabled.12': $ext='dotm'; break;
            case 'application/vnd.mcd': $ext='mcd'; break;
            case 'application/vnd.micrografx.flo': $ext='flo'; break;
            case 'application/vnd.micrografx.igx': $ext='igx'; break;
            case 'application/vnd.eszigno3+xml': $ext='es3'; break;
            case 'application/x-msaccess': $ext='mdb'; break;
            case 'video/x-ms-asf': $ext='asf'; break;
            case 'application/x-msdownload': $ext='exe'; break;
            case 'application/vnd.ms-artgalry': $ext='cil'; break;
            case 'application/vnd.ms-cab-compressed': $ext='cab'; break;
            case 'application/vnd.ms-ims': $ext='ims'; break;
            case 'application/x-ms-application': $ext='application'; break;
            case 'application/x-msclip': $ext='clp'; break;
            case 'image/vnd.ms-modi': $ext='mdi'; break;
            case 'application/vnd.ms-fontobject': $ext='eot'; break;
            case 'application/vnd.ms-excel': $ext='xls'; break;
            case 'application/vnd.ms-excel.addin.macroenabled.12': $ext='xlam'; break;
            case 'application/vnd.ms-excel.sheet.binary.macroenabled.12': $ext='xlsb'; break;
            case 'application/vnd.ms-excel.template.macroenabled.12': $ext='xltm'; break;
            case 'application/vnd.ms-excel.sheet.macroenabled.12': $ext='xlsm'; break;
            case 'application/vnd.ms-htmlhelp': $ext='chm'; break;
            case 'application/x-mscardfile': $ext='crd'; break;
            case 'application/vnd.ms-lrm': $ext='lrm'; break;
            case 'application/x-msmediaview': $ext='mvb'; break;
            case 'application/x-msmoney': $ext='mny'; break;
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation': $ext='pptx'; break;
            case 'application/vnd.openxmlformats-officedocument.presentationml.slide': $ext='sldx'; break;
            case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow': $ext='ppsx'; break;
            case 'application/vnd.openxmlformats-officedocument.presentationml.template': $ext='potx'; break;
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': $ext='xlsx'; break;
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.template': $ext='xltx'; break;
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': $ext='docx'; break;
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template': $ext='dotx'; break;
            case 'application/x-msbinder': $ext='obd'; break;
            case 'application/vnd.ms-officetheme': $ext='thmx'; break;
            case 'application/onenote': $ext='onetoc'; break;
            case 'audio/vnd.ms-playready.media.pya': $ext='pya'; break;
            case 'video/vnd.ms-playready.media.pyv': $ext='pyv'; break;
            case 'application/vnd.ms-powerpoint': $ext='ppt'; break;
            case 'application/vnd.ms-powerpoint.addin.macroenabled.12': $ext='ppam'; break;
            case 'application/vnd.ms-powerpoint.slide.macroenabled.12': $ext='sldm'; break;
            case 'application/vnd.ms-powerpoint.presentation.macroenabled.12': $ext='pptm'; break;
            case 'application/vnd.ms-powerpoint.slideshow.macroenabled.12': $ext='ppsm'; break;
            case 'application/vnd.ms-project': $ext='mpp'; break;
            case 'application/x-mspublisher': $ext='pub'; break;
            case 'application/x-msschedule': $ext='scd'; break;
            case 'application/x-silverlight-app': $ext='xap'; break;
            case 'application/vnd.ms-pki.stl': $ext='stl'; break;
            case 'application/vnd.ms-pki.seccat': $ext='cat'; break;
            case 'application/vnd.visio': $ext='vsd'; break;
            case 'video/x-ms-wm': $ext='wm'; break;
            case 'audio/x-ms-wma': $ext='wma'; break;
            case 'audio/x-ms-wax': $ext='wax'; break;
            case 'video/x-ms-wmx': $ext='wmx'; break;
            case 'application/x-ms-wmd': $ext='wmd'; break;
            case 'application/vnd.ms-wpl': $ext='wpl'; break;
            case 'application/x-ms-wmz': $ext='wmz'; break;
            case 'video/x-ms-wmv': $ext='wmv'; break;
            case 'video/x-ms-wvx': $ext='wvx'; break;
            case 'application/x-msmetafile': $ext='wmf'; break;
            case 'application/x-msterminal': $ext='trm'; break;
            case 'application/msword': $ext='doc'; break;
            case 'application/x-mswrite': $ext='wri'; break;
            case 'application/vnd.ms-works': $ext='wps'; break;
            case 'application/x-ms-xbap': $ext='xbap'; break;
            case 'application/vnd.ms-xpsdocument': $ext='xps'; break;
            case 'audio/midi': $ext='mid'; break;
            case 'application/vnd.ibm.minipay': $ext='mpy'; break;
            case 'application/vnd.ibm.modcap': $ext='afp'; break;
            case 'application/vnd.jcp.javame.midlet-rms': $ext='rms'; break;
            case 'application/vnd.tmobile-livetv': $ext='tmo'; break;
            case 'application/x-mobipocket-ebook': $ext='prc'; break;
            case 'application/vnd.mobius.mbk': $ext='mbk'; break;
            case 'application/vnd.mobius.dis': $ext='dis'; break;
            case 'application/vnd.mobius.plc': $ext='plc'; break;
            case 'application/vnd.mobius.mqy': $ext='mqy'; break;
            case 'application/vnd.mobius.msl': $ext='msl'; break;
            case 'application/vnd.mobius.txf': $ext='txf'; break;
            case 'application/vnd.mobius.daf': $ext='daf'; break;
            case 'text/vnd.fly': $ext='fly'; break;
            case 'application/vnd.mophun.certificate': $ext='mpc'; break;
            case 'application/vnd.mophun.application': $ext='mpn'; break;
            case 'video/mj2': $ext='mj2'; break;
            case 'audio/mpeg': $ext='mpga'; break;
            case 'video/vnd.mpegurl': $ext='mxu'; break;
            case 'video/mpeg': $ext='mpeg'; break;
            case 'application/mp21': $ext='m21'; break;
            case 'audio/mp4': $ext='mp4a'; break;
            case 'video/mp4': $ext='mp4'; break;
            case 'application/x-mpegURL': $ext='m3u8'; break;
            case 'video/MP2T': $ext='ts'; break;
            case 'application/vnd.musician': $ext='mus'; break;
            case 'application/vnd.muvee.style': $ext='msty'; break;
            case 'application/xv+xml': $ext='mxml'; break;
            case 'application/vnd.nokia.n-gage.data': $ext='ngdat'; break;
            case 'application/vnd.nokia.n-gage.symbian.install': $ext='n-gage'; break;
            case 'application/x-dtbncx+xml': $ext='ncx'; break;
            case 'application/x-netcdf': $ext='nc'; break;
            case 'application/vnd.neurolanguage.nlu': $ext='nlu'; break;
            case 'application/vnd.dna': $ext='dna'; break;
            case 'application/vnd.noblenet-directory': $ext='nnd'; break;
            case 'application/vnd.noblenet-sealer': $ext='nns'; break;
            case 'application/vnd.noblenet-web': $ext='nnw'; break;
            case 'application/vnd.nokia.radio-preset': $ext='rpst'; break;
            case 'application/vnd.nokia.radio-presets': $ext='rpss'; break;
            case 'text/n3': $ext='n3'; break;
            case 'application/vnd.novadigm.edm': $ext='edm'; break;
            case 'application/vnd.novadigm.edx': $ext='edx'; break;
            case 'application/vnd.novadigm.ext': $ext='ext'; break;
            case 'application/vnd.flographit': $ext='gph'; break;
            case 'audio/vnd.nuera.ecelp4800': $ext='ecelp4800'; break;
            case 'audio/vnd.nuera.ecelp7470': $ext='ecelp7470'; break;
            case 'audio/vnd.nuera.ecelp9600': $ext='ecelp9600'; break;
            case 'application/oda': $ext='oda'; break;
            case 'application/ogg': $ext='ogx'; break;
            case 'audio/ogg': $ext='oga'; break;
            case 'audio/ogg': $ext='ogg'; break;
            case 'video/ogg': $ext='ogv'; break;
            case 'application/vnd.oma.dd2+xml': $ext='dd2'; break;
            case 'application/vnd.oasis.opendocument.text-web': $ext='oth'; break;
            case 'application/oebps-package+xml': $ext='opf'; break;
            case 'application/vnd.intu.qbo': $ext='qbo'; break;
            case 'application/vnd.openofficeorg.extension': $ext='oxt'; break;
            case 'application/vnd.yamaha.openscoreformat': $ext='osf'; break;
            case 'audio/webm': $ext='weba'; break;
            case 'video/webm': $ext='webm'; break;
            case 'application/vnd.oasis.opendocument.chart': $ext='odc'; break;
            case 'application/vnd.oasis.opendocument.chart-template': $ext='otc'; break;
            case 'application/vnd.oasis.opendocument.database': $ext='odb'; break;
            case 'application/vnd.oasis.opendocument.formula': $ext='odf'; break;
            case 'application/vnd.oasis.opendocument.formula-template': $ext='odft'; break;
            case 'application/vnd.oasis.opendocument.graphics': $ext='odg'; break;
            case 'application/vnd.oasis.opendocument.graphics-template': $ext='otg'; break;
            case 'application/vnd.oasis.opendocument.image': $ext='odi'; break;
            case 'application/vnd.oasis.opendocument.image-template': $ext='oti'; break;
            case 'application/vnd.oasis.opendocument.presentation': $ext='odp'; break;
            case 'application/vnd.oasis.opendocument.presentation-template': $ext='otp'; break;
            case 'application/vnd.oasis.opendocument.spreadsheet': $ext='ods'; break;
            case 'application/vnd.oasis.opendocument.spreadsheet-template': $ext='ots'; break;
            case 'application/vnd.oasis.opendocument.text': $ext='odt'; break;
            case 'application/vnd.oasis.opendocument.text-master': $ext='odm'; break;
            case 'application/vnd.oasis.opendocument.text-template': $ext='ott'; break;
            case 'image/ktx': $ext='ktx'; break;
            case 'application/vnd.sun.xml.calc': $ext='sxc'; break;
            case 'application/vnd.sun.xml.calc.template': $ext='stc'; break;
            case 'application/vnd.sun.xml.draw': $ext='sxd'; break;
            case 'application/vnd.sun.xml.draw.template': $ext='std'; break;
            case 'application/vnd.sun.xml.impress': $ext='sxi'; break;
            case 'application/vnd.sun.xml.impress.template': $ext='sti'; break;
            case 'application/vnd.sun.xml.math': $ext='sxm'; break;
            case 'application/vnd.sun.xml.writer': $ext='sxw'; break;
            case 'application/vnd.sun.xml.writer.global': $ext='sxg'; break;
            case 'application/vnd.sun.xml.writer.template': $ext='stw'; break;
            case 'application/x-font-otf': $ext='otf'; break;
            case 'application/vnd.yamaha.openscoreformat.osfpvg+xml': $ext='osfpvg'; break;
            case 'application/vnd.osgi.dp': $ext='dp'; break;
            case 'application/vnd.palm': $ext='pdb'; break;
            case 'text/x-pascal': $ext='p'; break;
            case 'application/vnd.pawaafile': $ext='paw'; break;
            case 'application/vnd.hp-pclxl': $ext='pclxl'; break;
            case 'application/vnd.picsel': $ext='efif'; break;
            case 'image/x-pcx': $ext='pcx'; break;
            case 'image/vnd.adobe.photoshop': $ext='psd'; break;
            case 'application/pics-rules': $ext='prf'; break;
            case 'image/x-pict': $ext='pic'; break;
            case 'application/x-chat': $ext='chat'; break;
            case 'application/pkcs10': $ext='p10'; break;
            case 'application/x-pkcs12': $ext='p12'; break;
            case 'application/pkcs7-mime': $ext='p7m'; break;
            case 'application/pkcs7-signature': $ext='p7s'; break;
            case 'application/x-pkcs7-certreqresp': $ext='p7r'; break;
            case 'application/x-pkcs7-certificates': $ext='p7b'; break;
            case 'application/pkcs8': $ext='p8'; break;
            case 'application/vnd.pocketlearn': $ext='plf'; break;
            case 'image/x-portable-anymap': $ext='pnm'; break;
            case 'image/x-portable-bitmap': $ext='pbm'; break;
            case 'application/x-font-pcf': $ext='pcf'; break;
            case 'application/font-tdpfr': $ext='pfr'; break;
            case 'application/x-chess-pgn': $ext='pgn'; break;
            case 'image/x-portable-graymap': $ext='pgm'; break;
            case 'image/png': $ext='png'; break;
            case 'image/x-portable-pixmap': $ext='ppm'; break;
            case 'application/pskc+xml': $ext='pskcxml'; break;
            case 'application/vnd.ctc-posml': $ext='pml'; break;
            case 'application/postscript': $ext='ai'; break;
            case 'application/x-font-type1': $ext='pfa'; break;
            case 'application/vnd.powerbuilder6': $ext='pbd'; break;
            case 'application/pgp-signature': $ext='pgp'; break;
            case 'application/vnd.previewsystems.box': $ext='box'; break;
            case 'application/vnd.pvi.ptid1': $ext='ptid'; break;
            case 'application/pls+xml': $ext='pls'; break;
            case 'application/vnd.pg.format': $ext='str'; break;
            case 'application/vnd.pg.osasli': $ext='ei6'; break;
            case 'text/prs.lines.tag': $ext='dsc'; break;
            case 'application/x-font-linux-psf': $ext='psf'; break;
            case 'application/vnd.publishare-delta-tree': $ext='qps'; break;
            case 'application/vnd.pmi.widget': $ext='wg'; break;
            case 'application/vnd.quark.quarkxpress': $ext='qxd'; break;
            case 'application/vnd.epson.esf': $ext='esf'; break;
            case 'application/vnd.epson.msf': $ext='msf'; break;
            case 'application/vnd.epson.ssf': $ext='ssf'; break;
            case 'application/vnd.epson.quickanime': $ext='qam'; break;
            case 'application/vnd.intu.qfx': $ext='qfx'; break;
            case 'video/quicktime': $ext='qt'; break;
            case 'application/x-rar-compressed': $ext='rar'; break;
            case 'audio/x-pn-realaudio': $ext='ram'; break;
            case 'audio/x-pn-realaudio-plugin': $ext='rmp'; break;
            case 'application/rsd+xml': $ext='rsd'; break;
            case 'application/vnd.rn-realmedia': $ext='rm'; break;
            case 'application/vnd.realvnc.bed': $ext='bed'; break;
            case 'application/vnd.recordare.musicxml': $ext='mxl'; break;
            case 'application/vnd.recordare.musicxml+xml': $ext='musicxml'; break;
            case 'application/relax-ng-compact-syntax': $ext='rnc'; break;
            case 'application/vnd.data-vision.rdz': $ext='rdz'; break;
            case 'application/rdf+xml': $ext='rdf'; break;
            case 'application/vnd.cloanto.rp9': $ext='rp9'; break;
            case 'application/vnd.jisp': $ext='jisp'; break;
            case 'application/rtf': $ext='rtf'; break;
            case 'text/richtext': $ext='rtx'; break;
            case 'application/vnd.route66.link66+xml': $ext='link66'; break;
            case 'application/rss+xml': $ext='rss, xml'; break;
            case 'application/shf+xml': $ext='shf'; break;
            case 'application/vnd.sailingtracker.track': $ext='st'; break;
            case 'image/svg+xml': $ext='svg'; break;
            case 'image/svg+xml': $ext='svgz'; break;
            case 'application/vnd.sus-calendar': $ext='sus'; break;
            case 'application/sru+xml': $ext='sru'; break;
            case 'application/set-payment-initiation': $ext='setpay'; break;
            case 'application/set-registration-initiation': $ext='setreg'; break;
            case 'application/vnd.sema': $ext='sema'; break;
            case 'application/vnd.semd': $ext='semd'; break;
            case 'application/vnd.semf': $ext='semf'; break;
            case 'application/vnd.seemail': $ext='see'; break;
            case 'application/x-font-snf': $ext='snf'; break;
            case 'application/scvp-vp-request': $ext='spq'; break;
            case 'application/scvp-vp-response': $ext='spp'; break;
            case 'application/scvp-cv-request': $ext='scq'; break;
            case 'application/scvp-cv-response': $ext='scs'; break;
            case 'application/sdp': $ext='sdp'; break;
            case 'text/x-setext': $ext='etx'; break;
            case 'video/x-sgi-movie': $ext='movie'; break;
            case 'application/vnd.shana.informed.formdata': $ext='ifm'; break;
            case 'application/vnd.shana.informed.formtemplate': $ext='itp'; break;
            case 'application/vnd.shana.informed.interchange': $ext='iif'; break;
            case 'application/vnd.shana.informed.package': $ext='ipk'; break;
            case 'application/thraud+xml': $ext='tfi'; break;
            case 'application/x-shar': $ext='shar'; break;
            case 'image/x-rgb': $ext='rgb'; break;
            case 'application/vnd.epson.salt': $ext='slt'; break;
            case 'application/vnd.accpac.simply.aso': $ext='aso'; break;
            case 'application/vnd.accpac.simply.imp': $ext='imp'; break;
            case 'application/vnd.simtech-mindmapper': $ext='twd'; break;
            case 'application/vnd.commonspace': $ext='csp'; break;
            case 'application/vnd.yamaha.smaf-audio': $ext='saf'; break;
            case 'application/vnd.smaf': $ext='mmf'; break;
            case 'application/vnd.yamaha.smaf-phrase': $ext='spf'; break;
            case 'application/vnd.smart.teacher': $ext='teacher'; break;
            case 'application/vnd.svd': $ext='svd'; break;
            case 'application/sparql-query': $ext='rq'; break;
            case 'application/sparql-results+xml': $ext='srx'; break;
            case 'application/srgs': $ext='gram'; break;
            case 'application/srgs+xml': $ext='grxml'; break;
            case 'application/ssml+xml': $ext='ssml'; break;
            case 'application/vnd.koan': $ext='skp'; break;
            case 'text/sgml': $ext='sgml'; break;
            case 'application/vnd.stardivision.calc': $ext='sdc'; break;
            case 'application/vnd.stardivision.draw': $ext='sda'; break;
            case 'application/vnd.stardivision.impress': $ext='sdd'; break;
            case 'application/vnd.stardivision.math': $ext='smf'; break;
            case 'application/vnd.stardivision.writer': $ext='sdw'; break;
            case 'application/vnd.stardivision.writer-global': $ext='sgl'; break;
            case 'application/vnd.stepmania.stepchart': $ext='sm'; break;
            case 'application/x-stuffit': $ext='sit'; break;
            case 'application/x-stuffitx': $ext='sitx'; break;
            case 'application/vnd.solent.sdkm+xml': $ext='sdkm'; break;
            case 'application/vnd.olpc-sugar': $ext='xo'; break;
            case 'audio/basic': $ext='au'; break;
            case 'application/vnd.wqd': $ext='wqd'; break;
            case 'application/vnd.symbian.install': $ext='sis'; break;
            case 'application/smil+xml': $ext='smi'; break;
            case 'application/vnd.syncml+xml': $ext='xsm'; break;
            case 'application/vnd.syncml.dm+wbxml': $ext='bdm'; break;
            case 'application/vnd.syncml.dm+xml': $ext='xdm'; break;
            case 'application/x-sv4cpio': $ext='sv4cpio'; break;
            case 'application/x-sv4crc': $ext='sv4crc'; break;
            case 'application/sbml+xml': $ext='sbml'; break;
            case 'text/tab-separated-values': $ext='tsv'; break;
            case 'image/tiff': $ext='tiff'; break;
            case 'application/vnd.tao.intent-module-archive': $ext='tao'; break;
            case 'application/x-tar': $ext='tar'; break;
            case 'application/x-tcl': $ext='tcl'; break;
            case 'application/x-tex': $ext='tex'; break;
            case 'application/x-tex-tfm': $ext='tfm'; break;
            case 'application/tei+xml': $ext='tei'; break;
            case 'text/plain': $ext='txt'; break;
            case 'application/vnd.spotfire.dxp': $ext='dxp'; break;
            case 'application/vnd.spotfire.sfs': $ext='sfs'; break;
            case 'application/timestamped-data': $ext='tsd'; break;
            case 'application/vnd.trid.tpt': $ext='tpt'; break;
            case 'application/vnd.triscape.mxs': $ext='mxs'; break;
            case 'text/troff': $ext='t'; break;
            case 'application/vnd.trueapp': $ext='tra'; break;
            case 'application/x-font-ttf': $ext='ttf'; break;
            case 'text/turtle': $ext='ttl'; break;
            case 'application/vnd.umajin': $ext='umj'; break;
            case 'application/vnd.uoml+xml': $ext='uoml'; break;
            case 'application/vnd.unity': $ext='unityweb'; break;
            case 'application/vnd.ufdl': $ext='ufd'; break;
            case 'text/uri-list': $ext='uri'; break;
            case 'application/vnd.uiq.theme': $ext='utz'; break;
            case 'application/x-ustar': $ext='ustar'; break;
            case 'text/x-uuencode': $ext='uu'; break;
            case 'text/x-vcalendar': $ext='vcs'; break;
            case 'text/x-vcard': $ext='vcf'; break;
            case 'application/x-cdlink': $ext='vcd'; break;
            case 'application/vnd.vsf': $ext='vsf'; break;
            case 'model/vrml': $ext='wrl'; break;
            case 'application/vnd.vcx': $ext='vcx'; break;
            case 'model/vnd.mts': $ext='mts'; break;
            case 'model/vnd.vtu': $ext='vtu'; break;
            case 'application/vnd.visionary': $ext='vis'; break;
            case 'video/vnd.vivo': $ext='viv'; break;
            case 'application/ccxml+xml,': $ext='ccxml'; break;
            case 'application/voicexml+xml': $ext='vxml'; break;
            case 'application/x-wais-source': $ext='src'; break;
            case 'application/vnd.wap.wbxml': $ext='wbxml'; break;
            case 'image/vnd.wap.wbmp': $ext='wbmp'; break;
            case 'audio/x-wav': $ext='wav'; break;
            case 'application/davmount+xml': $ext='davmount'; break;
            case 'application/x-font-woff': $ext='woff'; break;
            case 'application/wspolicy+xml': $ext='wspolicy'; break;
            case 'image/webp': $ext='webp'; break;
            case 'application/vnd.webturbo': $ext='wtb'; break;
            case 'application/widget': $ext='wgt'; break;
            case 'application/winhlp': $ext='hlp'; break;
            case 'text/vnd.wap.wml': $ext='wml'; break;
            case 'text/vnd.wap.wmlscript': $ext='wmls'; break;
            case 'application/vnd.wap.wmlscriptc': $ext='wmlsc'; break;
            case 'application/vnd.wordperfect': $ext='wpd'; break;
            case 'application/vnd.wt.stf': $ext='stf'; break;
            case 'application/wsdl+xml': $ext='wsdl'; break;
            case 'image/x-xbitmap': $ext='xbm'; break;
            case 'image/x-xpixmap': $ext='xpm'; break;
            case 'image/x-xwindowdump': $ext='xwd'; break;
            case 'application/x-x509-ca-cert': $ext='der'; break;
            case 'application/x-xfig': $ext='fig'; break;
            case 'application/xhtml+xml': $ext='xhtml'; break;
            case 'application/xml': $ext='xml'; break;
            case 'application/xcap-diff+xml': $ext='xdf'; break;
            case 'application/xenc+xml': $ext='xenc'; break;
            case 'application/patch-ops-error+xml': $ext='xer'; break;
            case 'application/resource-lists+xml': $ext='rl'; break;
            case 'application/rls-services+xml': $ext='rs'; break;
            case 'application/resource-lists-diff+xml': $ext='rld'; break;
            case 'application/xslt+xml': $ext='xslt'; break;
            case 'application/xop+xml': $ext='xop'; break;
            case 'application/x-xpinstall': $ext='xpi'; break;
            case 'application/xspf+xml': $ext='xspf'; break;
            case 'application/vnd.mozilla.xul+xml': $ext='xul'; break;
            case 'chemical/x-xyz': $ext='xyz'; break;
            case 'application/yang': $ext='yang'; break;
            case 'application/yin+xml': $ext='yin'; break;
            case 'application/vnd.zul': $ext='zir'; break;
            case 'application/zip': $ext='zip'; break;
            case 'application/vnd.handheld-entertainment+xml': $ext='zmm'; break;
            case 'application/vnd.zzazz.deck+xml': $ext='zaz'; break;
            case 'image/x-exr; version="2"': $ext='exr'; break;
            case 'audio/mpeg': $ext='mp3'; break;
            case 'audio/flac': $ext='flac'; break;
            case 'text/markdown': $ext='md'; break;
            // case 'application/gzip': $ext='gzip'; break;
            default:
                Utils::error_log('mime type not found ! '.$mime);
        }
        return $ext;
    }

//---------------------------------DEBUG SECTION-----------------------------
    /**
     * Custom error handler that logs errors to helPHP.log.
     *
     * @param int $errno Error number.
     * @param string $errstr Error message.
     * @param string $errfile File where error occurred.
     * @param int $errline Line number of error.
     * @return void
     */
    public static function error_handler($errno, $errstr, $errfile, $errline) {
        global $CONFIG;
        // If don't have LOG_FILE in your config, rename LOG_FOLDER to LOG_FILE, don't forget to add .'helPHP.log' at the end of your LOG_FILE
        if (!is_file($CONFIG::LOG_FILE)){
            $folder = Filesystem::get_file_path($CONFIG::LOG_FILE);
            if (!is_dir($folder)) mkdir($folder);
            touch($CONFIG::LOG_FILE);
        }

        $today = date("F j H:i:s");
        $logAuth = fopen($CONFIG::LOG_FILE, 'a+');
        if ($logAuth !== false) {
            fputs($logAuth, $today . ' | ERROR :' . $errfile . ' line : ' . $errline . ' : ' . $errstr . "\n");
            fclose($logAuth);
        }
    }
    /**
     * Logs a PHP backtrace to helPHP.log.
     *
     * @param bool $show_args Whether to show function arguments in the log.
     * @return void (it's writing in helphp.log file)
     */
    public static function log_backtrace($show_args = false) {
        global $CONFIG;

        $lst = array_reverse(debug_backtrace());

        $logAuth = fopen($CONFIG::LOG_FILE, 'a+');
        if ($logAuth !== false) {
            fputs($logAuth, '****************  BACKTRACE START  *****************************'."\n");
            fputs($logAuth, date("F j H:i:s")."\n");
            foreach ($lst as $k=>$line) {
                if (isset($line['file']) && isset($line['line'])) {
                    fputs($logAuth, ($k+1).'> '.$line['file'].' | line:'.$line['line'].' -> function '.$line['function'].'( '.($show_args?implode(' , ', $line['args']):'').' )'."\n");
                } else {
                    fputs($logAuth, ($k+1).'!> '.print_r($line, true));
                }
            }
            fputs($logAuth, '****************   BACKTRACE END   *****************************'."\n");
            fclose($logAuth);
        } else {
            trigger_error('BACKTRACE ERRROR');
        }
    }
    /**
     * Logs arbitrary data to helPHP.log, with optional short format.
     *
     * @param mixed $data Data to log.
     * @param bool $short_form If true, logs in short format.
     * @return void
     */
    public static function error_log($data, $short_form = false) {
        global $CONFIG;
        $lst = debug_backtrace();
        $l = $lst[0];
        $f = isset($lst[1]['function']) ? $lst[1]['function'] : '';

        $today = date("F j H:i:s");
        $logAuth = fopen($CONFIG::LOG_FILE, 'a+');
        if ($logAuth !== false) {
            if ($short_form) {
                fputs($logAuth, $today . ' | LOG   : '.print_r($data, true)."\n");
            } else {
                fputs($logAuth, $today . ' | LOG   :' . $l['file'] . ' | line : ' . $l['line'] .' -> function '.$f.'() : '. "\n".print_r($data, true)."\n");
            }
            fclose($logAuth);
        }
    }
    /**
     * Dummy error handler for suppressed errors.
     *
     * $errno $errstr $errfile $errline ar there juste for compatibility 
     * they are unsused
     * @return bool Always returns false.
     */
    public static function error_handler_to_trash($errno, $errstr, $errfile, $errline)
    {
        //we don't want to catch error on @
        return false;
    }

//---------------------------------STRING MANIPULATION SECTION-----------------------------
    /**
     * Filters a string to only allow characters from a given set.
     *
     * @param string $t The input string.
     * @param string $limit Allowed characters.
     * @return string The filtered string.
     */
    public static function filter_string($t, $limit = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_") {
        $final="";
        $t = ($t == null) ? '' : $t;
        for ($i=0;$i<strlen($t);$i++) {
            $pos=strpos($limit, substr($t, $i, 1));
            if ($pos === false) {
                //
            } else {
                $final .=substr($t, $i, 1);
            }
        }
        return $final;
    }

    /**
     * Gets a substring from HTML, preserving tags and word boundaries.
     *
     * @param string $str The HTML string.
     * @param int $start Start position.
     * @param int $len Length of substring.
     * @return string The substring with HTML tags preserved.
     */
    public static function substr_from_html($str, $start, $len) {

        $str_clean = substr(strip_tags($str),$start,$len);
        $pos = strrpos($str_clean, " ");
        if($pos === false) {
            $str_clean = substr(strip_tags($str),$start,$len);  
            }else
            $str_clean = substr(strip_tags($str),$start,$pos);

        if(preg_match_all('/\<[^>]+>/is',$str,$matches,PREG_OFFSET_CAPTURE)){

            for($i=0;$i<count($matches[0]);$i++){

                if($matches[0][$i][1] < $len){

                    $str_clean = substr($str_clean,0,$matches[0][$i][1]) . $matches[0][$i][0] . substr($str_clean,$matches[0][$i][1]);

                }else if(preg_match('/\<[^>]+>$/is',$matches[0][$i][0])){

                    $str_clean = substr($str_clean,0,$matches[0][$i][1]) . $matches[0][$i][0] . substr($str_clean,$matches[0][$i][1]);

                    break;

                }

            }

            return $str_clean;

        }else{
            $string = substr($str,$start,$len);
             $pos = strrpos($string, " ");
            if($pos === false) {
                return substr($str,$start,$len);
            }
                return substr($str,$start,$pos);

        }

    }

    /**
     * Checks if a string contains only characters from a given set.
     *
     * @param string $string The string to check.
     * @param string $gama Allowed characters.
     * @return bool True if only allowed characters, false otherwise.
     */
    public static function str_contains_only($string, $gama) {
        $chars = str_split($string);
        $gama = str_split($gama);
        foreach ($chars as $char) {
            if (in_array($char, $gama)==false) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Removes accents from a string.
     *
     * @param string $text The input string.
     * @return string The string without accents.
     */
    public static function remove_accents($text) {
        // minuscules
        $search  = array('à','á','â','ã','ä','å', 'æ','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','œ','ù','ü','ú','ÿ');
        $replace = array('a','a','a','a','a','a','ae','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','oe','u','u','u','y');
        $text    = str_replace($search, $replace, $text);
        // majuscules
        $search  = array('À','Á','Â','Ã','Ä','Å', 'Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ñ','Ò','Ó','Ô','Õ','Ö','Œ','Ù','Ü','Ú','ß');
        $replace = array('A','A','A','A','A','A','AE','C','E','E','E','E','I','I','I','I','N','O','O','O','O','O','OE','U','U','U','Y');
        $text    = str_replace($search, $replace, $text);
        return $text;
    }
    /**
     * Fixes double slaches in the classic EOL.
     *
     * @param string $str The input string.
     * @return string The string with fixed EOL.
     */
    public static function fix_EOL($str) {
        return stripslashes(str_replace('\\r\\n', "\r\n", $str));
    }

    /**
     * Checks if a string is present at the start of any string in an array.
     *
     * @param string $str The string to search for.
     * @param array $array The array to search in.
     * @param bool $details If true, returns matching entries; else returns true/false.
     * @return mixed Array of matches or boolean.
     */
    public static function string_in_array_str($str, $array, $details = true) {
        $results  = preg_grep ('/^'.$str.' (\w+)/i', $array);
        //$details conditionning to return an array containing keys and string of the array containing the string, or just a true:false state
        $present = (array_sum($results)>0)?true:false;
        return ($details)?$results:$present;
    }

    /**
     * Checks if any string from an array is present in a given string.
     *
     * @param string $str The string to search in.
     * @param array $array The array of needles.
     * @param bool $startwith If true, checks for start of string.
     * @return bool True if found, false otherwise.
     */
    public static function array_str_in_string($str, $array, $startwith) {
        //it's the contrary function of string in array, it's looking if one str from the array is in the string.
        foreach($array as $key => $needle){
            if ($startwith){
                if (str_starts_with($str,$needle)) {
                    return true;
                }
             }else{
                if (str_contains($str,$needle)) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Generates a random string of given length and character set.
     *
     * @param int $length Length of the string.
     * @param string|false $valid_characters Allowed characters.
     * @return string Random string.
     */
    public static function random_string($length = 5, $valid_characters = false) {
        $character_set = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($valid_characters) $character_set = $valid_characters;
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($character_set) - 1);
            $str.= $character_set[$index];
        }
        return $str;
    }
    /**
     * Returns a CSS theme in DB as a string to export it.
     *
     * @param int $id_theme Theme ID.
     * @return string CSS string.
     */
    public static function get_css_as_str($id_theme = 0){
        global $DB;

        $db_theme = $DB->table('csseditor_theme');

        $q = 'SELECT id FROM '.$db_theme.' WHERE id=?';
        $id_theme = $DB->prepared_query_value($q,'i',[$id_theme]);

        if (!$id_theme) {
            Utils::error_log('wrong id theme given '.$id_theme);
            return;
        }

        $css = array();

        $q = 'SELECT var.id_media, med.value as media, med.order, var.name, var.properties FROM '.$DB->table('csseditor_variables').' var LEFT JOIN '.$DB->table('csseditor_media').' med ON (var.id_media=med.id) WHERE var.id_theme='.$id_theme.' ORDER BY med.order';
        $variables = $DB->query_list($q);
        foreach($variables as $key => $variable){
            if (!isset($css[$variable['media']])) {
                if ($variable['id_media'] == 1) $css[$variable['media']] = ':root {';
                else $css[$variable['media']] = $variable['media'].' { :root {';
            }
            $css[$variable['media']].= $variable['name'].':'.$variable['properties'].';';
        }
        // close :root
        foreach($css as $key => $str){
            $css[$key].='}';
        }

        // add font-face
        $q = 'SELECT name,file FROM '.$DB->table('csseditor_fonts').' font, '.$DB->table('csseditor_theme_fonts').' asso WHERE asso.id_fonts=font.id AND asso.id_theme='.$id_theme;
        $fonts = $DB->query_list($q);
        if ($fonts){
            foreach($fonts as $key => $font){
                $str = '@font-face {font-family: "'.$font['name'].'"; src: url(fonts/'.$font['file'].');}';
                $css[''] .= $str;
            }
        }

        // all the rules
        $q = 'SELECT rul.id_media, med.value as media, med.order, rul.selector, rul.properties FROM '.$DB->table('csseditor_rules').' rul LEFT JOIN '.$DB->table('csseditor_media').' med ON (med.id=rul.id_media) WHERE rul.id_theme='.$id_theme.' ORDER BY med.order';
        $list = $DB->query_list($q);
        foreach ($list as $key => $line) {
            if (!isset($css[$line['media']])) {
                $css[$line['media']] = $line['media'].'{';
            }
            $css[$line['media']] .= $line['selector'].' {'.$line['properties'].'}';
        }

        // concatene everything to form the final file and add it to the dom
        $str_css = '';
        foreach ($css as $media => $val) {
            if ($media == '') {
                $str_css .= $val;
            } else {
                $str_css .= $val.'}';
            }
        }
        $str_css = preg_replace('/(url\()(.*\))/', "$1../../$2", $str_css);

        return $str_css;
    }

//---------------------------------NUMBERS MANIPULATION SECTION-----------------------------
    /**
     * Computes the discounted price.
     *
     * @param float $prix Original price.
     * @param float $remise Discount percentage.
     * @return float Discounted price.
     */
    public static function compute_discount($prix, $remise) {
        $prix = (floatval($prix) * (1-(floatval($remise)/100))) * 100;
        return (round($prix)) / 100;
    }
    /**
     * Computes the price including VAT.
     *
     * @param float $prix Original price.
     * @param float $tva VAT percentage.
     * @return float Price including VAT.
     */
    public static function compute_vat($prix, $tva) {
        $prix = (floatval($prix) * (1+(floatval($tva)/100))) * 100;
        return (round($prix)) / 100;
    }
    /**
     * Removes VAT from a price.
     *
     * @param float $prix Price including VAT.
     * @param float $tva VAT percentage.
     * @return float Price without VAT.
     */
    public static function remove_vat($prix, $tva) {
        $prix = (floatval($prix) / (1+(floatval($tva)/100))) * 1000;
        return (round($prix)) / 1000;
    }
//---------------------------------ARRAY MANIPULATION SECTION-----------------------------

    /**
     * merge two associative array by a common value key
     * 
     * The two associative array should have a common label key that will be used to merge them
     * if I put the valueKey "id", my two array will be merged by the id key in their values.
     * Preserve the orginal index
     * in case of identical key, the second one is kept
     * 
     * Exemple:
     * $a = [['id'=>'1','value'=>'jesuis1'],['id'=>'2','value'=>'jesuis2']];
     * $b = [['id'=>'1','value'=>'jesuispas1'],['id'=>'3','value'=>'jesuis3']];
     * $c = array_merge_by_value($a, $b, 'id'); [['id'=>'1','value'=>'jesuispas1'],['id'=>'2','value'=>'jesuis2'],['id'=>'3','value'=>'jesuis3']]
     * $c = array_merge_by_value($b, $a, 'id'); [['id'=>'1','value'=>'jesuis1'],['id'=>'2','value'=>'jesuis2'],['id'=>'3','value'=>'jesuis3']]
     * @param array $a First array.
     * @param array $b Second array.
     * @param string $valueKey Key to merge by.
     * @return array Merged array.
     */
    public static function array_merge_by_value($a, $b, $valueKey) {
        $tmp_key = '___key____';

        $tmp_a = [];

        foreach ($a as $key=>$data) {
            $data[$tmp_key] = $key;
            $tmp_a[$data[$valueKey]] = $data;
        }

        foreach ($b as $data) {
            $val = $data[$valueKey];
            if (isset($tmp_a[$val])) {
                $a[$tmp_a[$val][$tmp_key]] = $data;
            } else {
                array_push($a, $data);
            }
        }

        return $a;
    }

    /**
     * Converts an array to CSV and writes to a file or outputs as attachment.
     *
     * @param array $arraydata Data to write.
     * @param string $filename Filename or output stream.
     * @param bool $attachment If true, sends as attachment.
     * @param bool $headers If true, includes headers.
     * @return void
     */
    public static function array_to_csv($arraydata, $filename, $attachment = false, $headers = true) {
       
        if($attachment) {
            // send response headers to the browser
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment;filename='.$filename);
            $fp = fopen('php://output', 'w');
        } else {
            $fp = fopen($filename, 'w');
        }
        if($headers) {
           fputcsv($fp, array_keys($headers));
        }
        foreach ($arraydata as $data) {
           fputcsv($fp, $data);
        }
        fclose($fp);
    }

}