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
 *      php install_blocks.php /path/to/my/instance/ module_name
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

    $instance_path = $argv[1];

    if (!is_file($instance_path.'/config/main.php')){
        echo 'config file not found at '.$instance_path.'/config/main.php';
        exit;
    }

    $res = install_blocks($instance_path);
    if (isset($res['err'])) {
        echo 'An error occured :'.PHP_EOL;
        echo $res['err'].PHP_EOL;
        echo json_encode($res['params']).PHP_EOL;
    } else {
        echo 'Installation success'.PHP_EOL;
    }
}


/**
 * Install all blocks found in modules/block folder from helPHP framework
 * 
 * To call from CLI :
 *      php install_blocks.php /path/to/my/instance/
 *
 * @param mixed $instance_path
 * 
 * @return string error or success message
 * @package helPHP\utils
 */
function install_blocks($instance_path) {
    $instance_path = rtrim(trim($instance_path), '/');
    include_once($instance_path.'/config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    global $DB, $CONFIG, $FS;

    $path = $CONFIG::HELPHP_FOLDER.'modules/block/';

    $folders = $FS->shell_ls($path)['folders'];

    foreach($folders as $folder){
        $block_path = $path.$folder['name'].'/';

        $ucfirst = ucfirst($folder['name']);
        if (!file_exists($block_path.$ucfirst.'.json')) continue;

        // insert in db
        try{
            @$DB->sql_from_json(file_get_contents($block_path.$ucfirst.'.json'));
        } catch (Exception $e){
            Utils::error_log('ERROR sql from json ');
            Utils::error_log($e);
            // skip block
            continue;
        }
        

        $q = 'SELECT id FROM '.$DB->table('block_data').' WHERE name=?';
        $id_block = $DB->prepared_query_value($q, 's', [$folder['name']]);

        $css_file_public = $block_path.'public/'.$ucfirst.'.css';
        if (\file_exists($css_file_public)) {
            $md5 = \md5_file($css_file_public);
            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=0';
            $DB->prepared_query($q, 'ss', [$css_file_public, $md5]);
            $id_source = $DB->last_insert_id();
            \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_public, $id_source);
        }

        $css_file_admin = $block_path.'admin/'.$ucfirst.'.css';
        if (\file_exists($css_file_admin)) {
            $md5 = \md5_file($css_file_admin);
            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=1';
            $DB->prepared_query($q, 'ss', [$css_file_admin, $md5]);
            $id_source = $DB->last_insert_id();
            \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_admin, $id_source);
        }
    }
}