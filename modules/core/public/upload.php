<?php

include_once(dirname(dirname(__DIR__)).'/config/main.php');
include_once(Config::HELPHP_FOLDER.'autoload.php');

use helPHP\libs\Ajax;

global $USER,$CONFIG;
if ($USER->connection_state != $USER::state_logged){
    return 'not_connected';
}

$folder = $CONFIG::HOME_FOLDER.'temp/';
if (isset($_POST['cancel']) && $_POST['cancel'] == 1){
    // Upload of chunk are stopped.
    // Need to remove all chunk from the server
    if (isset($_POST['name']) && $_POST['name'] != ''){
        global $FS;
        
        $name = $FS->get_file_name($_POST['name']);
        $name.= $_POST['uniqueid'];
        
        $destinationDir = $folder.$name.'/';
        if (is_dir($destinationDir)){
            $res = $FS->recurse_ls($destinationDir);
            if ($res){
                foreach($res['files'] as $fileName){
                    unlink($destinationDir.$fileName);
                }
                rmdir($destinationDir);
            }
        } else if (is_file($folder.$name)){
            unlink($folder.$name);
        }
        
        if (isset($_POST['time'])){
            $time = $_POST['time'];
            if (isset($_SESSION['upload_locks']) && isset($_SESSION['upload_locks'][$time])){
                foreach($_SESSION['upload_locks'][$time] as $path){
                    // UTILS_Class::error_log('delete lock cancel upload');
                    $FS->delete_lock($FS->root_fs.$path, $time, false, false);
                }
                unset($_SESSION['upload_locks'][$time]);
            }
        }
        
        echo 'ok';
    }
} else if (isset($_POST['merge']) && $_POST['merge'] == 1){
    
    global $FS;
    
    $name = $FS->get_file_name($_POST['name']);
    
    $last = isset($_POST['last']) && $_POST['last'];
    $res = Ajax::merge_chunks($name, $_POST['index'], $_POST['uniqueid'], $last);

    if (isset($_POST['time'])){
        $time = $_POST['time'];
        if (isset($_SESSION['upload_locks']) && isset($_SESSION['upload_locks'][$time])){
            foreach($_SESSION['upload_locks'][$time] as $path){
                // UTILS_Class::error_log('delete lock merge upload');
                // $realPath = $FS->root_fs.$path
                $FS->delete_lock($path, $time, false, false);
            }
            unset($_SESSION['upload_locks'][$time]);
        }
    }

    if ($res == 'ok') return $res;
    else return json_encode($res);
    
} else {
    global $FS;
    
    $name = $FS->get_file_name($_POST['filename']);
    
    if (!isset($_POST['full'])){
        $name.= $_POST['uniqueid'];
        $index = $_POST['chunk_index'];
        
        $destinationDir = $folder.'chunk_'.$name;
    } else {
        $destinationDir = $CONFIG::HOME_FOLDER.'temp/';
        $index = null;
    }

    // update lock
    if (isset($_POST['time'])){
        $time = $_POST['time'];
        if (isset($_SESSION['upload_locks']) && isset($_SESSION['upload_locks'][$time])){
            foreach($_SESSION['upload_locks'][$time] as $path){
                // UTILS_Class::error_log('update lock upload');
                $FS->update_lock($path);
            }
        }
    }

    $res = Ajax::process_files($destinationDir,null,$index);
    if (!$res){
        echo 'no';
        return;
    }
    $chron->step_chrono('end process file');
    if (count($res) > 0){
        echo 'ok';

        // update storage status at the end of an upload if not a chunk and if it's the last uploaded element, in case of multiple upload we 
        // don't want to rewrite the file on each file but only one time at the end
        if (isset($_POST['full']) && $_POST['full'] && isset($_POST['last']) && $_POST['last']){
            Ajax::update_storage_status();
        }
    } else {
        echo 'no';
    }
}