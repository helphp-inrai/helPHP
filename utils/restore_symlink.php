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
 *      php restore_symlink.php /path/to/my/instance/
 */

use helPHP\libs\DB;

$CLI = isset($argc); // true, called from CLI

if ($CLI){ // call from CLI
    if (!isset($argv[1])){
        echo 'Need an instance path'.PHP_EOL;
        exit;
    }

    $instance_path = $argv[1];
    if (!is_file($instance_path.'/config/main.php')){
        echo 'Config file not found at '.$instance_path.'/config/main.php'.PHP_EOL;
        exit;
    }

    $res = restore_symlink($instance_path);
}


/**
 * Restore all the symlinks for an instance, start with the external JS one and then process every module installed
 * 
 * To call from CLI :
 *      php restore_symlink.php /path/to/my/instance/
 *
 * @param mixed $instance_path
 * 
 * @return string error or success message
 * @package helPHP\utils
 */
function restore_symlink($instance_path) {
    
    $instance_path = rtrim(trim($instance_path), '/');
    include_once($instance_path.'/config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    global $CONFIG;

    if (!file_exists($CONFIG::HOME_FOLDER.'js/externals')) mkdir($CONFIG::HOME_FOLDER.'js/externals', 0775, true);
    // tinymce
    if (!file_exists($CONFIG::HOME_FOLDER.'js/externals/tinymce')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/tinymce '.$CONFIG::HOME_FOLDER.'js/externals/tinymce');
    // alwan colorpicker
    if (!file_exists($CONFIG::HOME_FOLDER.'js/externals/alwan')) mkdir($CONFIG::HOME_FOLDER.'js/externals/alwan', 0775, true);
    if (!file_exists($CONFIG::HOME_FOLDER.'js/externals/alwan/alwan.min.js')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/alwan/alwan.min.js '.$CONFIG::HOME_FOLDER.'js/externals/alwan/alwan.min.js');
    if (!file_exists($CONFIG::HOME_FOLDER.'js/externals/alwan/alwan.min.css')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/alwan/alwan.min.css '.$CONFIG::HOME_FOLDER.'js/externals/alwan/alwan.min.css');
    // ace
    if (!file_exists($CONFIG::HOME_FOLDER.'js/externals/ace')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/ace '.$CONFIG::HOME_FOLDER.'js/externals/ace');

    foreach(array_keys($CONFIG::MODULES_LIST) as $module_name){
        $modules_folder = $CONFIG::HELPHP_FOLDER.'modules/';
        if (!is_file($modules_folder.$module_name.'/install.php')) continue;
        
        include($modules_folder.$module_name.'/install.php');
        if (isset($symlinks) && sizeof($symlinks) > 0){
            foreach ($symlinks as $symlink) {
                $symlink[1] = str_replace("admin/", $CONFIG::ADMIN_FOLDER, $symlink[1]);
                if (file_exists($modules_folder.$symlink[0])) {
                    if (file_exists($instance_path.'/'.$symlink[1])) unlink($instance_path.'/'.$symlink[1]);
                    shell_exec('ln -sf '.$modules_folder.$symlink[0].' '.$instance_path.'/'.$symlink[1]);
                }
            }
        }
    }
}