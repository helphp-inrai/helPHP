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


use helPHP\libs\Utils;

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

    uninstall_module($instance_path, $module_name, $ignore_file_and_folder);
}

/**
 * Uninstall a module in the instance and
 * 
 * To call from CLI :
 *      php uninstall_module.php /path/to/my/instance/ module_name
 *
 * @param mixed $instance_path
 * @param mixed $module_name
 * @param bool $ignore_file_and_folder
 * 
 * @return string error or success message
 * 
 * @package helPHP\utils
 */
function uninstall_module($instance_path, $module_name, $ignore_file_and_folder=false, $CLI = false) {
    

    $instance_path = rtrim(trim($instance_path), '/');
    include_once($instance_path.'/config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');
    global $CONFIG, $DB, $CLI;
    $modules_folder = Config::HELPHP_FOLDER.'modules/';

    
    //uninstall module

    if ($CLI) echo 'uninstalling module '.$module_name.PHP_EOL;

    // deleting sql from json
    $sql_file = $modules_folder.$module_name.'/db.json';
    if (is_file($sql_file)){
        $json = json_decode(file_get_contents($sql_file), true);
        if ($json){
            
            // delete table listed in json
            if (isset($json['tables'])) {
                foreach($json['tables'] as $key => $table){
                    $q = 'DROP TABLE IF EXISTS `'.$DB->table($table['name']).'`';
                    $DB->query($q);
                }
            }
            
            // delete entry listed in json
            if (isset($json['entries'])){
                // will store all the primary field detected when inserting to not do the same query multiple if there
                // is more than one deletion to a table : table_name => field
                $primary_list = [];

                foreach($json['entries'] as $key => $entry) {

                    // $q = 'SHOW TABLES LIKE `'.$DB->table($entry['table']).'`';
                    // Utils::error_log($q);
                    // $table_exist = $DB->query_value($q);
                    // // Utils::error_log('table exist = '.$table_exist);
                    // if ($table_exist == '') continue;
                    if (!$DB->table_exists($DB->table($entry['table']))) return;

                    // get primary field for the table
                    if (!\key_exists($entry['table'], $primary_list)){
                        $q = 'SHOW INDEX FROM `'.$DB->table($entry['table']).'` WHERE Key_name="PRIMARY"';
                        $primary = $DB->query_line($q);
                        if ($primary){
                            $primary_list[$entry['table']] = $primary['Column_name'];
                        }
                    }
                    
                    // wether to check existence of the line on id or with all the fields
                    $id = false;

                    // each field is added to a string like `name=value` use it next to insert or select the row in db
                    $sql = '';
                    foreach($entry['fields'] as $ind => $field) {
                        if (key_exists($entry['table'], $primary_list) && $primary_list[$entry['table']] == $field['name']) {
                            // the inserted row has the unique id inside
                            $id = $field;
                        }
                        
                        $sql.= $field['name'].' = ';
                        if ($field['type'] == 's') $sql.= '"'.$field['value'].'"';
                        else $sql.= $field['value'];
                        $sql.= ', ';
                    }
                    $sql = substr($sql, 0, -2);

                    // check a row exist with the same id or with exactly the same value in the table
                    if ($id !== false) {
                        $q = 'DELETE FROM `'.$DB->table($entry['table']).'` WHERE '.$id['name'].' = '.$id['value'];
                        $DB->query($q);
                    } else {
                        $q = 'DELETE FROM `'.$DB->table($entry['table']).'` WHERE '.str_replace(', ', ' AND ', $sql);
                        $DB->query($q);
                    }
                }
            }
        }
    }

    if (!$ignore_file_and_folder && $module_name!=''){
        //if we don't keep files...
        if (is_dir($modules_folder.$module_name)){
            $res = shell_exec('rm -r "'.$modules_folder.$module_name.'"');
        }
        if (is_dir($CONFIG::HOME_FOLDER.'public/'.$module_name)){
            $res = shell_exec('rm -r "'.$CONFIG::HOME_FOLDER.'public/'.$module_name.'"');
        }
        if (is_dir($CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER.$module_name)){
            $res = shell_exec('rm -r "'.$CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER.$module_name.'"');
        }
    }
    
    //modifying config.php.
    $config = file_get_contents($instance_path.'/config/main.php');
    $module_list = $CONFIG::MODULES_LIST;
    if (isset($module_list[$module_name])) {
        
        $config_beginning = explode('//>>>modules>>>', $config);
        $config_beginning = $config_beginning[0];
        
        //deleting module config
        unset($module_list[$module_name]);
        $config_ending = explode('//<<<modules<<<', $config);
        $config_ending = $config_ending[1];

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
        $newConfig = $config_beginning.'//>>>modules>>>'.PHP_EOL.'    const MODULES_LIST = '.beautify_module_list($module_list, $temporary_module).'; '.PHP_EOL.'    //<<<modules<<<'.$config_ending;
        file_put_contents($instance_path.'/config/main.php', $newConfig);
    }

    // deleting module in hierarchy admin
    $hierarchy = new \helPHP\modules\hierarchy\admin\Hierarchy();
    $hierarchy->delete_item($module_name, true);

    // deleting css public and admin
    $css_paths = [$modules_folder.$module_name.'/admin/'.$module_name.'.css', $modules_folder.$module_name.'/public/'.$module_name.'.css'];
    foreach($css_paths as $path){
        \helPHP\modules\csseditor\admin\Csseditor::delete_css_source('module', $path);
    }
    
    return true;
}