<?php
/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */

use helPHP\libs\Utils;

if (!isset($argv[1])){
    error_log('no argument passed when calling backup.php');
    exit('missing_argument');
}

$target = '/'.trim($argv[1],'/').'/';

if (!is_dir($target)){
    error_log('path : '.$target.' not found');
    exit('path_not_found');
}

if (!is_file($target.'config/main.php')){
    error_log('path : '.$target.'config/main.php not found');
    exit('main_file_not_found');
}else{
    backup_instance($target);
}


/**
 * The backup instance utils create a backup of your HelPHP instance
 * including the main db indicated in the db config (not the central one if it exist)
 * 
 * can be called from cli with instance path as argument: 
 * php helphp/utils/backup.php instance_home_path
 *
 * @param mixed $target the home path
 * 
 * @return String echoing results
 * 
 * @package helPHP\utils
 */
function backup_instance($target){
    include_once($target.'config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');
    //check if backup folder exist and is writable
    // $tmppath=explode('/',Config::HELPHP_FOLDER);
    // array_pop($tmppath);
    // array_pop($tmppath); //drop last folder name
    // $backuppath = implode('/',$tmppath).'/backup';
    $backuppath = Config::HOME_FOLDER.'backup';
    if (!is_dir($backuppath)){
        if (!mkdir($backuppath, 0777, true)) {
            Utils::error_log('can\'t mkdir'.$backuppath);
            echo 'permission'.PHP_EOL;
            exit;
        }
    }
    if (!is_writable($backuppath)){
        Utils::error_log('can\'t write in '.$backuppath);
        echo 'permission'.PHP_EOL;
        exit;
    }else{
        //ok we can backup !
        //backup db 
        set_time_limit(300);
        $currentdate=date('Y-m-d');
        $cmd = 'mysqldump -h '.$CONFIG_DB::DB_HOST.' -u '.$CONFIG_DB::DB_USER.' -p'.$CONFIG_DB::DB_PASS.' '.$CONFIG_DB::DB_BASE.' > '.$target.'/'.$CONFIG_DB::DB_BASE.'-'.$currentdate.'.sql';
        $res = shell_exec($cmd);
        // echo 'sql db backuped in home folder'.PHP_EOL;
        $cmd = 'cd '.$backuppath.' && tar -czf backup-'.$currentdate.'.tar.gz '.$target.' && echo 1 > /proc/sys/vm/drop_caches';
        $res = shell_exec($cmd);
        // echo 'backup archive created there: '.$backuppath.'/backup-'.$currentdate.'.tar.gz '.PHP_EOL;
        unlink($target.'/'.$CONFIG_DB::DB_BASE.'-'.$currentdate.'.sql');//cleaning sql file to avoid security issue in current instance
        // echo 'sql db backup cleaned from home folder'.PHP_EOL;
    }

    echo 'done';
}
