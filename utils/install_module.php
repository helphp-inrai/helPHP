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

/**
 * To call from CLI :
 *      php install_module.php /path/to/my/instance/ module_name
 */

use helPHP\libs\DB;
use helPHP\libs\Utils;

// global $helphp_folder;
// $helphp_folder = dirname(__DIR__).'/';

$CLI = isset($argc); // true, called from CLI

if ($CLI){ // call from CLI
    if (!isset($argv[1])){
        echo 'Need an instance path'.PHP_EOL;
        exit;
    }
    if (!isset($argv[2])) {
        echo 'Need a module name'.PHP_EOL;
        exit;
    }

    $instance_path = $argv[1];
    $module_name = $argv[2];
    $ignore_file_and_folder = (isset($argv[3]) && $argv[3]) ? true : false;

    if (!is_file($instance_path.'/config/main.php')){
        echo 'config file not found at '.$instance_path.'/config/main.php';
        exit;
    }

    $res = install_module($instance_path, $module_name, $ignore_file_and_folder);
    if ($res['err']){
        echo 'An error occured :'.PHP_EOL;
        echo $res['err'].PHP_EOL;
        echo json_encode($res['params']).PHP_EOL;
    } else {
        echo 'Installation success'.PHP_EOL;
    }
}


/**
 * Install a module in the instance and add a menu in admin hierarchy
 * 
 * To call from CLI :
 *      php install_module.php /path/to/my/instance/ module_name
 *
 * @param mixed $instance_path
 * @param mixed $module_name
 * @param bool $ignore_file_and_folder
 * 
 * @return string error or success message
 * @package helPHP\utils
 */
function install_module($instance_path, $module_name, $ignore_file_and_folder = false) {
    
    $instance_path = rtrim(trim($instance_path), '/');
    include_once($instance_path.'/config/main.php');
    include_once($instance_path.'/config/db.php');
    include_once(Config::HELPHP_FOLDER.'libs/DB.php');

    global $CONFIG;
    $CONFIG = new \Config();

    global $CONFIG_DB;
    $CONFIG_DB = new \Config_db();
    DB::create_instance();
    global $DB;

    $modules_folder = $CONFIG::HELPHP_FOLDER.'modules/';
    
    $uw_user = $CONFIG::APACHE_USER;
    $uw_name = "www-data";
    
    //install module
    //including installer
    if (!is_file($modules_folder.$module_name.'/install.php')) {
        return ['err'=>'no_install_file_found', 'params'=>[$modules_folder.$module_name.'/install.php']];
    }

    // echo 'installing module '.$module_name.PHP_EOL;
    // if ($CLI) echo 'installing module '.$module_name.PHP_EOL;

    $files = '';
    $folders = '';

    include($modules_folder.$module_name.'/install.php');

    //creating folders
    if (!$ignore_file_and_folder) {
        if (is_array($folders)) {
            if (sizeof($folders)>0) {
                foreach ($folders as $fold) {
                    //replace admin folder by config
                    $fold[0] = str_replace("admin/", $CONFIG::ADMIN_FOLDER, $fold[0]);
                    if (!file_exists($instance_path.'/'.$fold[0])) super_mkdir($instance_path.'/'.$fold[0], $fold[1], $uw_user, $uw_name);
                }
            }
        }

        //copying files
        if (is_array($files)) {
            if (sizeof($files)>0) {
                foreach ($files as $file) {
                    $file[1] = str_replace("admin/", $CONFIG::ADMIN_FOLDER, $file[1]);
                    if (file_exists($modules_folder.$file[0])) super_copy($modules_folder.$file[0], $instance_path.'/'.$file[1], $uw_user, $uw_name);
                }
            }
        }
    }

    //creating tables and inserting data
    if (is_file($modules_folder.$module_name.'/db.json')) {
        $json = json_decode(file_get_contents($modules_folder.$module_name.'/db.json'), true);
        if (!$json) return ['err'=>'db_json_invalid', 'params'=>[$modules_folder.$module_name.'/db.json']];
        else $DB->sql_from_json($json);
    }

    //modifying config.php.
    if (is_array($config_part)) {
        $config = file_get_contents($instance_path.'/config/main.php');
        $configBeginning = explode('//>>>modules>>>', $config);
        $configBeginning = $configBeginning[0];
        $module_list = $CONFIG::MODULES_LIST;
        $module_list = array_merge($module_list, $config_part);
        $configEnding = explode('//<<<modules<<<', $config);
        $configEnding = $configEnding[1];

        // detect if there is a temporary module in module_list in config by searching for //>>>temporarymodule>>>
        // give the module name to the function beautify_module_list if any
        $temporary_module = false;
        if (strpos($config, '//>>>temporarymodule>>>') > 0){
            if (!preg_match('/\/\/>>>temporarymodule>>>\n +\/\/<<<temporarymodule<<</', $config)){ // regex return true if temporarymodule part is empty
                preg_match('/\/\/>>>temporarymodule>>>\n +\'(.+)\'/', $config, $temporary_module);
                $temporary_module = $temporary_module[1];
                // Utils::error_log($temporary_module);
            }
        }
        include_once('beautify_module_list.php');
        $newConfig = $configBeginning.'//>>>modules>>>'.PHP_EOL.'    const MODULES_LIST = '.beautify_module_list($module_list, $temporary_module).'; '.PHP_EOL.'    //<<<modules<<<'.$configEnding;
        file_put_contents($instance_path.'/config/main.php', $newConfig);
    }

    return true;
}
function super_copy($source, $target, $user, $groupe) {
    copy($source, $target);
    chown($target, $user);
}
function super_mkdir($target, $right, $user, $groupe) {
    mkdir($target, $right, true);
    chown($target, $user);
}