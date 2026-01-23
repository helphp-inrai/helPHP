#!/usr/bin/php
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

if (!isset($argv[1])){
    error_log('no argument passed when calling check_replica.php');
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
    check_replication($target);
}

/**
 * The check replica utils is checking is the master slave replication is still operationnal.
 * the user used for connection must have  "SUPER" or "SLAVE MONITOR" privilege
 * can be called from cli with instance path as argument: 
 * php helphp/utils/check_replica.php instance_home_path
 *
 * @param mixed $target the home path of the instance to get db config
 * 
 * @return String echoing results
 * 
 * @package helPHP\utils
 */
function check_replication($target){

    include_once($target.'config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    $errors = '';

    try {
        $db = new DB(['host'=>$CONFIG_DB::DB_SLAVE_HOST,'user'=>$CONFIG_DB::DB_SLAVE_USER,'password'=>$CONFIG_DB::DB_SLAVE_PASS]);
        $db->connect();
        $q = 'SHOW SLAVE STATUS';
        $row = $db->query_line($q);

        if(isset($row['Slave_IO_Running']) && $row['Slave_IO_Running'] == 'No') {
            $errors.= 'io_stopped';
            $log = "Slave IO not running".PHP_EOL;
            $log.= "Error number: {$row['Last_IO_Errno']}".PHP_EOL;
            $log.= "Error message: {$row['Last_IO_Error']}";
            Utils::error_log($log);
        }

        if(isset($row['Slave_SQL_Running']) && $row['Slave_SQL_Running'] == 'No') {
            $errors.= 'sql_stopped';
            $log = "Slave SQL not running".PHP_EOL;
            $log.= "Error number: {$row['Last_SQL_Errno']}".PHP_EOL;
            $log.= "Error message: {$row['Last_SQL_Error']}".PHP_EOL;
            Utils::error_log($log);
        }

        // $db->close();
    } catch(Exception $e) {
        $errors.= 'connect_slave';
        // $errors.= 'Could not connect to slave or missing right - except : '.$e->getMessage();
    }

    if($errors) {
        echo $errors;
        //If necessary we can send mail here when this script is used in a cron check
    } else {
        echo 'done';
    }
}