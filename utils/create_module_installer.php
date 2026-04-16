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


global $helphp_folder;
$helphp_folder = dirname(__DIR__).'/';

$CLI = isset($argc); // true, called from CLI

if ($CLI){ // call from CLI
    if (!isset($argv[1])){
        echo 'Need a module name.'.PHP_EOL;
        exit;
    }
    if (!isset($argv[2])) {
        echo 'Need the list of sql object'.PHP_EOL;
        exit;
    }

    $module_name = $argv[1];
    $object_list = $argv[2];
    $sql_only = (isset($argv[3]) && $argv[3]) ? true : false;
    $public = (!isset($argv[4]) || $argv[4]) ? true : false;

    make_install($module_name, $object_list, $sql_only, $public);
}

/**
 * create everything needed for installing a module
 * 
 * Will create the file install.php with sql query and list of file needed inside.
 * Will also create both index.php for admin and public
 * 
 * 
 * To call from CLI
 *      php create_module_installer.php module_name sql_object1,sql_object2,... true|false
 * if the module has no db
 *      php create_module_installer.php module_name no_db
 * 
 * @param string    $module_name            name of the module
 * @param string    $object_list            sql object of the module name separated by a ',' 
 * @param bool      $sql_only               Optional. True, only create the sql part, ignore file
 * 
 * @return string error or success message
 * @package helPHP\utils
 */
function make_install($module_name, $object_list, $sql_only = false, $public = true){
    global $helphp_folder, $CLI;

    $target_path = $helphp_folder.'modules/'.$module_name.'/';

    if ($object_list != '' && $object_list != 'no_db') {
        $objects = explode(',', $object_list);
        $db_json = array();
        foreach ($objects as $object){
            // $object_path = $helphp_folder.'generated/sql_objects/'.$object.'.php';
            $object_path = $helphp_folder.'generated/sql_objects/'.$object.'.json';
            if (is_file($object_path)){
                $object_json = json_decode(file_get_contents($object_path), true);
                if (isset($object_json['tables'])){
                    if (!isset($db_json['tables'])) $db_json['tables'] = [];
                    $db_json['tables'] = array_merge($db_json['tables'], $object_json['tables']);
                }
                // $json_file = json_decode(file_get_contents($object_path), true);
                // if (isset($json_file['tables'])){
                //     if (!isset($db_json['tables'])) $db_json['tables'] = [];
                // $db_json['tables'] = array_merge($db_json['tables'], $json_file['tables']);
            // }
            } else {
                if ($CLI){
                    echo $object_path.' not found'.PHP_EOL;
                    exit;
                } else return $object_path.' not found';
            }
        }

        if (isset($db_json)){
            file_put_contents($target_path.'db.json', json_encode($db_json));
            if ($CLI) echo 'done db.json'.PHP_EOL;
        } else if ($CLI) echo 'no db'.PHP_EOL;
    }

    if ($sql_only) {
        if ($CLI) exit;
        else return;
    }

    
    if ($CLI) echo 'Generating install.php'.PHP_EOL;

    $content = '<?php'.PHP_EOL.PHP_EOL.'$module_name = \''.$module_name.'\';'.PHP_EOL.PHP_EOL;

    generate_index($module_name, $target_path, $public);

    $result = recurse_ls($target_path, $module_name.'/');
    
    $str_file = '$files = array('.PHP_EOL;
    $inc = 0;
    $to_exclude = [
        $module_name."/install.php", // previous install.php
        $module_name."/db.json", // previous install.php
        $module_name."/public/".ucfirst($module_name).".php", // class public
        $module_name."/admin/".ucfirst($module_name).".php", // class admin
        $module_name."/public/".$module_name.".js", // js file
        $module_name."/admin/".$module_name.".js", // js file
        $module_name."/public/".$module_name.".css", // css file
        $module_name."/admin/".$module_name.".css", // css file
    ];
    foreach($result['files'] as $file){
        // tl files can end with many languages iso so special test with start_with
        if ( !in_array($file, $to_exclude) && !str_starts_with($file, $module_name."/public/tl_".$module_name) && !str_starts_with($file, $module_name."/admin/tl_".$module_name)){
            $file_dest = str_replace($module_name."/public/", "public/".$module_name.'/', $file);
            $file_dest = str_replace($module_name."/admin/", "admin/".$module_name.'/', $file_dest);
            $str_file .= '    '.$inc.'=>[\''.$file.'\', \''.$file_dest.'\', 0755],'.PHP_EOL;
            $inc++;
        }
    }

    $str_folder = '$folders = array('.PHP_EOL;
    $inc=0;
    foreach($result['folders'] as $folder){
        $folder_dest = str_replace($module_name."/public", "public/".$module_name, $folder);
        $folder_dest = str_replace($module_name."/admin", "admin/".$module_name, $folder_dest);
        $str_folder .= '    '.$inc.'=>[\''.$folder_dest.'\', 0755],'.PHP_EOL;
        $inc++;
    }
    $content .= $str_file.');'.PHP_EOL.PHP_EOL;
    $content .= $str_folder.');'.PHP_EOL.PHP_EOL;

    $conf_part="\$config_part = array(
    '".$module_name."'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);";
    
    $content .= $conf_part.PHP_EOL;

    file_put_contents($target_path.'install.php', $content);

    if ($CLI) echo 'done install.php'.PHP_EOL;

    return 'done';
}

/**
 * to get the list of file and folder
 * 
 * modified version of filesystem.php to also be used by CLI call
 */
function recurse_ls($path, $base_path='') {
    if ($path){
        $result = array();
        $result['files']= array();
        $result['folders']= array();
        $result['links']= array();
        $listing = scandir($path);
        foreach ($listing as $key => $value) {
            // skipping .. and .
            if (in_array($value, array(".",".."))) continue;
            if (is_dir($path . DIRECTORY_SEPARATOR . $value)) {
                $result['folders'][]= $base_path.$value;
                //recursive
                if ($base_path!='') {
                    $nbase=$base_path . $value. DIRECTORY_SEPARATOR;
                } else {
                    $nbase=$value. DIRECTORY_SEPARATOR;
                }
                $subdir= recurse_ls($path . DIRECTORY_SEPARATOR . $value, $nbase);
                $result['folders']=array_merge($result['folders'], $subdir['folders']);
                $result['files']=array_merge($result['files'], $subdir['files']);
            }else if (is_file($path . DIRECTORY_SEPARATOR . $value) && !is_link($path . DIRECTORY_SEPARATOR . $value)) {
                $result['files'][]= $base_path.$value;
            }else if (is_link($path . DIRECTORY_SEPARATOR . $value)){
                $result['links'][]= $base_path.$value;
            } 
        }
        return $result;
    } else {
        error_log('recurse_ls PATH ERROR : '.$path);
        return false;
    }
}

function generate_index($module_name, $path, $public){
    global $CLI;

    if ($CLI) echo 'generating indexes'.PHP_EOL;

    // generating index.php
    $index_content = '<?php'.PHP_EOL.PHP_EOL;
    $index_content.= 'include_once(dirname(dirname(__DIR__)).\'/config/main.php\');'.PHP_EOL;
    $index_content.= 'include_once(Config::HELPHP_FOLDER.\'autoload.php\');'.PHP_EOL.PHP_EOL;
    $index_content.= '$module = new helPHP\modules\\'.$module_name.'\admin\\'.ucfirst($module_name).'();'.PHP_EOL;
    $index_content.= '$module->process_data($_POST);'.PHP_EOL;
    $index_content.= '$module->publish_output();'.PHP_EOL;

    file_put_contents($path.'admin/index.php', $index_content);
    if ($CLI) echo 'index admin OK'.PHP_EOL;
    if ($public) {
        file_put_contents($path.'public/index.php', str_replace('admin', 'public', $index_content));
        if ($CLI) echo 'index public OK'.PHP_EOL;
    }

    if ($CLI) echo 'done indexes'.PHP_EOL;
}

function beautify_db_writing($str){
    // replace 2 space indent by 4 space indent then replace 4 space indent by 8
    $parsed = preg_replace('/^ {2}(\S)/m', '    $1', $str);
    $parsed = preg_replace('/^ {4}/m', '        ', $parsed);
    // the line that end the CREATE TABLE query is the only one that don't get any tab in front
    // detect it and add 8 tab indent
    $parsed = preg_replace('/\n\) /', PHP_EOL.'    ) ', $parsed);
    // the lines with the indexes of the array need only 4 tab
    // it's more simple to catch every line that starts with a index declaration than doing the inverse.
    $parsed = preg_replace('/ {8}(\d+ =>)/', '    $1', $parsed);

    return $parsed;
}