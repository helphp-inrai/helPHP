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
 *  -k , key to identify the job
 *  -C , callback when command is done
 *  -c , the command to execute
 *  -t , path to the instance
 *  -a , action for the job manager (new, progress or cancel)
 * 
 * 
 * exemples : 
 * - add a job :
 * $job_manager_call='php /home/helphp/utils/job_manager.php -a"new" -t"'.$target.'" -c"'.$cmd.'" -C"'.$callback.'" -k"'.$key.'"';
 * 
 * - get progress :
 * $job_manager_call='php /home/helphp/utils/job_manager.php -a"progress" -t"'.$target.'" -k"'.$key.'"';
 * - cancel :
 * $job_manager_call='php /home/helphp/utils/job_manager.php -a"cancel" -t"'.$target.'" -k"'.$key.'"';
 * 
 * must be followed by : exec($job_manager_call);
 * 
 * @package helPHP\utils
 * php /home/helPHP/utils/job_manager.php -a"loop" -t"/home/default/" -k"block_video-video¤6"
 */
function job_manager($argv){
    global $nb_jobs, $job_db;
    $nb_jobs = 3; //nb of simultaneous jobs...

    $callback = '';

    // parse arguments 
    foreach($argv as $index => $arg){

        if ($index == 0){
            
            $cmd = $arg;
            
        } else if ($index > 0){

            $opt = substr($arg,0,2);
            $val = substr($arg,2);

            switch($opt){
                case '-k':
                    $key_id = $val;
                break;
                case '-C':
                    $callback = $val;
                break;
                case '-c':
                    $command = $val;
                break;
                case '-t':
                    $target = $val;
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

    // echo 'action - '.$action.PHP_EOL; 
    // exit;

    if (!is_file($target.'config/main.php')){
        die('config file not found in '.$target.'config/main.php');
    }
    include_once($target.'config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    if (isset($action)){

        // connect to jobs db if exist
        global $CONFIG_DB;
        if ($CONFIG_DB::DB_JOBS){
            $job_db = new DB(['host'=>$CONFIG_DB::DB_JOBS_HOST, 'user'=>$CONFIG_DB::DB_JOBS_USER, 'dbname'=>$CONFIG_DB::DB_JOBS_BASE, 'password'=>$CONFIG_DB::DB_JOBS_PASS]);
            $job_db->connect();
        } else {
            global $DB;
            $job_db = $DB;
        }

        // check if the table jobs exist and create it otherwise
        if (!$job_db->table_exists($job_db->table('jobs'))){
            $json_db = '{
                "tables": [{
                    "name": "jobs",
                    "fields": [
                        {"name": "id", "type": "int", "limit":11, "null":false, "primary":true},
                        {"name": "status", "type": "int", "limit": 11, "null": false, "default": "0"},
                        {"name": "recorded", "type": "datetime", "null": false, "default": "CURRENT_TIMESTAMP"},
                        {"name": "started", "type": "datetime", "null": true},
                        {"name": "ended", "type": "datetime", "null": true},
                        {"name": "target", "type": "varchar", "limit": 255, "null": false, "default": ""},
                        {"name": "command", "type": "text", "null": true},
                        {"name": "return", "type": "text", "null": true},
                        {"name": "callback", "type": "text", "null":true},
                        {"name": "key_id", "type": "varchar", "limit": 255, "null": false, "default": ""}
                    ]
                }]
            }';
            $job_db->sql_from_json($json_db);
        }

        switch($action){
            case 'new':
                new_job($target, $command, $callback, $key_id);
            break;
            case 'cancel':
                cancel_job($target, $key_id);
            break;
            case 'progress':
                get_job_progress($target, $key_id);
            break;
            case 'loop':
                job_loop();
            break;
            case 'set_status':
                set_status_job($target, $key_id, $status);
            break;
            default:
                echo 'action not found'.PHP_EOL;
            break;
        }
    }
}

/**
 * to add a new job in "jobs" database pile
 *
 * @param mixed $target origine identifier
 * @param mixed $command to exec
 * @param mixed $callback ta call back after exec
 * @param mixed $key the key identifier that permit to follow th process.
 * 
 * @return void
 * 
 * @package helPHP\utils
 * 
 */
function new_job($target, $command, $callback, $key) {
    global $job_db;

    if (!isset($target, $command, $callback, $key)) {
        Utils::error_log('Error adding new job, missing parameters');
        Utils::error_log([
            'target' => $target,
            'command' => $command,
            'callback' => $callback,
            'key_id' => $key
        ]);
        return;
    }

    // $key is normally for redis, but we store/check it also in the db
    $q = 'SELECT DISTINCT COUNT(*) FROM '.$job_db->table('jobs').' WHERE target=? AND command=? AND callback=? AND key_id=? AND status=0';
    $exists = $job_db->prepared_query_value($q, 'ssss', [$target, addslashes($command), $callback, $key]);
    if($exists){
        Utils::error_log('Error adding new job, the exact same job already exist.');
        Utils::error_log([
            'target' => $target,
            'command' => $command,
            'callback' => $callback,
            'key_id' => $key
        ]);
        return;
    }
    
    $q = 'INSERT INTO '.$job_db->table('jobs').' SET target=?, command=?, callback=?, key_id=?, status=0';
    $job_db->prepared_query($q, 'ssss', [$target, addslashes($command), $callback, $key]);
    job_loop();
}

/**
 * Cancel a job and move if from db to history
 *
 * @param mixed $target origine identifier
 * @param mixed $key indentifier of the job to cancel.
 * 
 * @return void
 * 
 * @package helPHP\utils
 * 
 */
function cancel_job($target, $key){
    global $nb_jobs, $redisproc, $job_db, $CONFIG;
    for($i = 0; $i <= $nb_jobs; $i++){
        //checking if there is a job in progress or to clean
        $job_key_state = $redisproc->get($CONFIG::SITE_NAME.'-jobcue-'.$i);
        if ($job_key_state == $target.'¤'.$key){
            $_REQUEST['process_clear'] = true;
            $state = Utils::follow_system_process_redis($job_key_state);
            if ($state == 'ok!'){
                //no callback
                //cleaning 
                $q = 'DELETE FROM '.$job_db->table('jobs').' WHERE key_id="'.$job_key_state.'"';
                $job_done = $job_db->query($q);
                $redisproc->del($CONFIG::SITE_NAME.'-jobcue-'.$i);
            }
        }
    }
}

/**
 * Change the status of a job in the pile.
 *
 * @param mixed $target origine identifier
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
function set_status_job($target, $key, $status){
    global $job_db;
    if (intval($status) == 1) {
        $q = 'UPDATE '.$job_db->table('jobs').' SET `status`='.$status.', started=CURRENT_TIMESTAMP WHERE target="'.$target.'" AND key_id="'.$key.'"';
        $job_db->query($q);
    } else if (intval($status) == 2) {
        $q = 'UPDATE '.$job_db->table('jobs').' SET `status`='.$status.', ended=CURRENT_TIMESTAMP WHERE target="'.$target.'" AND key_id="'.$key.'"';
        $job_db->query($q);
        $q = 'SELECT callback FROM '.$job_db->table('jobs').' WHERE target="'.$target.'" AND key_id="'.$key.'"';
        $callback = $job_db->query_value($q);
        if ($callback) {
            Utils::system_process_no_session($callback);
        }
    }
    
}

/**
 * Get the progression of a job
 *
 * @param mixed $target origine identifier
 * @param mixed $key indentifier of the job 
 * 
 * @return string messages / echo from the process if it got status = 1 (1 in progress)
 * - status 0 : it will return "wait"
 * - status 2 : "ok!" because finished
 * - status 3 : "err" an error happenned.
 * 
 * @package helPHP\utils
 */
function get_job_progress($target, $key){
    global $job_db, $redisproc, $nb_jobs, $CONFIG;
    if ($redisproc == null ) {
        $redisproc = new Redis();
        $redisproc->connect('redis', 6379);
    }

    $in_progress = false;
    for($i = 0; $i < $nb_jobs; $i++){
        //checking if there is a job in progress or to clean
        $job_key_state = $redisproc->get($CONFIG::SITE_NAME.'-jobcue-'.$i);
        if ($job_key_state == $target.'¤'.$key){
            $in_progress = true;
            $state = Utils::follow_system_process_redis($job_key_state);
            echo $state;
        }
    }
    
    if (!$in_progress){
        $q = 'SELECT id, `status` FROM '.$job_db->table('jobs').' WHERE target="'.$target.'" AND key_id="'.$key.'"';
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
 * The job loop will run until there is no more job to do, so it must be started at start of the server
 * and it will go on infine loop waiting for job to execute.
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
            // There can be multiple ¤ in the key, we need to separate from the first one that makes the separation between instance path and key_id
            $shu_pos = strpos($job_key_state, '¤');
            $target = substr($job_key_state, 0, $shu_pos);
            $key_id = substr($job_key_state, $shu_pos + 2); // add 2 to not select the ¤ char (¤ is 2 char length)
            
            $q = 'SELECT DISTINCT * FROM '.$job_db->table('jobs').' WHERE target="'.$target.'" AND key_id="'.$key_id.'" AND status=2';
            $job_done = $job_db->query_line($q);
            if ($state == 'ok!' && $job_key_state !== false && $job_done){
                // it's already dead but the callback is not sent
                // if ($job_done){
                //     Utils::system_process_no_session($job_done['callback']);
                // }

                //cleaning 
                // $q = 'DELETE FROM '.$job_db->table('jobs').' WHERE target="'.$target.'" AND key_id="'.$key_id.'"';
                $q = 'DELETE FROM '.$job_db->table('jobs').' WHERE id='.$job_done['id'];
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
            // queue is free looking for a new job to do.
            $q = 'SELECT DISTINCT * FROM '.$job_db->table('jobs').' WHERE status=0 ORDER BY recorded limit 0,1';
            $last_job = $job_db->query_line($q);
            if (is_array($last_job)){
                Utils::system_process_with_tracking(stripslashes($last_job['command']), $last_job['target'].'¤'.$last_job['key_id'], "job");
                //we record the job cue key
                $redisproc->set($CONFIG::SITE_NAME.'-jobcue-'.$i,$last_job['target'].'¤'.$last_job['key_id']);
                // set it's status to 1 (in progress)
                set_status_job($last_job['target'], $last_job['key_id'], 1);
                // $q = 'UPDATE '.$job_db->table('jobs').' SET status=1 WHERE id='.$last_job['id'];
                // $job_db->query($q);
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