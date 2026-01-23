<?php
/**
 * COPYRIGHT M666 moi@myke666.fr 40600 BISCARROSSE FRANCE 2009-2024 - STEUZ steineremile@duck.com
 * ALL RIGHTS RESERVED
 * TOUS DROITS RESERVES
 * THIS CODE CAN'T BE DUPLICATED OR MODIFY WITHOUT M666 moi@myke666.fr AGREEMENT
 * CE CODE NE PEUT PAS ETRE DUPLIQUE OU MODIFIE SANS L'ACCORD D'M666 moi@myke666.fr
 */


/**
 * To call from CLI :
 *      php convert-sp2-3-to-helphp-module.php /path/to/my/module/ new_module_name
 */

use helPHP\libs\Filesystem;
use helPHP\libs\Utils;
global $helphp_folder;
$helphp_folder = dirname(__DIR__).'/';

$CLI = isset($argc); // true, called from CLI

if ($CLI) { // call from CLI
    if (!isset($argv[1])){
        echo 'Need an module path'.PHP_EOL;
        exit;
    }
    if (!isset($argv[2])) {
        echo 'Need a new module name'.PHP_EOL;
        exit;
    }

    $module_path = $argv[1];
    $module_name = $argv[2];

    convert_module($module_path, $module_name);
}

function convert_module($module_path, $module_name){
    global $helphp_folder;
    $mpath = $helphp_folder.'modules/'.strtolower($module_name);
    //first we create the destionation folder :
    $res = shell_exec('mkdir -p "'.$mpath.'"'); 

    //we'll use some functions from other utils :
    include_once($helphp_folder.'/utils/create_module_installer.php');
    //current name of the module :
    $old_module_name = explode('/',rtrim($module_path,'/'));
    $old_module_name = array_pop($old_module_name);

    //parsing files 
    $tree = recurse_ls(rtrim($module_path,'/'));
    //creating all subfolders
    
    foreach($tree['folders'] as $folder) {
        $stats = stat($module_path.'/'.$folder);
        shell_exec('mkdir -p "'.$mpath.'/'.$folder.'"');
        chown($mpath.'/'.$folder, $stats[4]);
        chgrp($mpath.'/'.$folder, $stats[5]);
    }
    //moving other files than those who should be recreated thru the main upgrader function.

    $to_filter = [
        'install.php', // previous install.php to ignore
        // 'public/'.$old_module_name.'_public_class.php', // class public
        // 'admin/'.$old_module_name.'_admin_class.php', // class admin
        'public/index.php', //to ignore
        'admin/index.php', //to ignore
    ];
    foreach($tree['files'] as $file){
        echo $file.PHP_EOL;
        $exppath = explode('/',rtrim($file,'/'));
        $filename = array_pop($exppath);
        $ext =array_pop(explode('.',$filename));
        if ( !in_array($file, $to_filter) && $ext !='php' ) {
            $stats = stat($module_path.'/'.$file);
            $nfile = str_replace(strtolower($old_module_name),strtolower($module_name),$file);
            $nfile = str_replace(ucfirst($old_module_name),ucfirst($module_name),$file);
            shell_exec('cp "'.$module_path.'/'.$file.'" "'.$mpath.'/'.$nfile.'"');
            chown($mpath.'/'.$nfile, $stats[4]);
            chgrp($mpath.'/'.$nfile, $stats[5]);
        }else{
            switch($file){
                case 'public/'.$old_module_name.'_public_class.php':
                    $npath = $mpath.'/public/'.ucfirst($module_name).'.php';
                break;
                case 'admin/'.$old_module_name.'_admin_class.php':
                    $npath = $mpath.'/admin/'.ucfirst($module_name).'.php';
                break;
                default:
                    $nfile = str_replace(strtolower($old_module_name),strtolower($module_name),$file);
                    $npath = $mpath.'/'.$nfile;
                break;
            }
            $base_folder = isset($exppath[0]) ? $exppath[0] : '';
            upgrade($base_folder,$old_module_name,$module_name,$module_path.'/'.$file,$npath);

        }
    }

    // generate_index($module_name, $mpath.'/', $public);
    //creation of new install file and index :
    $public_inst = (isset($result['files']['/public/index.php'])) ? true : false;
    if (in_array('install.php', $tree['files'])) {
        include_once($module_path.'/install.php');
        make_install($module_name, $bdd_tables, false, $public_inst);
        // make_install($module_name, $bdd_tables, false, $public_inst);
    } else {
        make_install($module_name, 'no_db', false, $public_inst);
    }
}
function upgrade($public_admin, $old_module_name, $module_name, $old_file, $new_file){
    $is_admin = ($public_admin=='admin') ? 'true' : 'false';
    $content = file_get_contents($old_file);
    $stats = stat($old_file);
    // copyright
    $pos = strpos($content,"include_once('spade_module.php');");
    $pos = $pos === false ? strpos($content, "include_once('s3_module.php');") : $pos;
    // echo ($pos === NULL).PHP_EOL;
    $header = "<?php
/*
 * COPYRIGHT (c) 2024-".date("Y")." INRAI / Mickaël Bourgeoisat / Emile Steiner
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
";
    if ($pos !== NULL && $pos > 3 ) {
        $content = $header.substr($content,$pos-1,null);
    }
    //search and replace tab 
    $search_and_replace = array();
    $search_and_replace["include_once('spade_module.php');"] = "namespace helPHP\modules\\".strtolower($module_name)."\\".$public_admin.";

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\User;";
    $search_and_replace["include_once('s3_module.php');"] = "namespace helPHP\modules\\".strtolower($module_name)."\\".$public_admin.";

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\User;";
    $search_and_replace["class ".$old_module_name] = "class ".ucfirst($module_name);
    $search_and_replace["class ".ucfirst($old_module_name)] = "class ".ucfirst($module_name);
    $search_and_replace[$old_module_name] = strtolower($module_name); 
    $search_and_replace[ucfirst($old_module_name)] = strtolower($module_name); 
    $search_and_replace[strtoupper($old_module_name)] = strtoupper($module_name); 
    $search_and_replace["
    protected \$ADMIN = true;"] = "";
    $search_and_replace["
    protected \$ADMIN = false;"] = "";
    $search_and_replace["
        parent::__construct(\$domContainer);"] = "
        \$this->prepare_module(self::module_name, ".$is_admin.");
        parent::__construct(\$domContainer);";
    $search_and_replace["protected \$MODULE_NAME = self::MODULE_NAME;
"] = "";
    $search_and_replace["protected \$SCRIPT_PATH = __DIR__;
"] = "";
    $search_and_replace["protected \$INPUT_ACTION_IDENTIFIER = self::MODULE_NAME.'_action';
"] = "";
    $search_and_replace["protected \$INPUT_ACTION_IDENTIFIER = 'action';
"] = "";
    $search_and_replace["private \$css = self::MODULE_NAME.'_admin_';
"] = "";
    $search_and_replace["protected \$domContainer = self::MODULE_NAME.'_admin_container';
"] = "";
    $search_and_replace["protected \$domContainer = self::MODULE_NAME.'_public_container';
"] = "";
    $search_and_replace["private \$css_class;
"] = "";
    $search_and_replace["INPUT_ACTION_IDENTIFIER"] = "input_action_identifier";
    $search_and_replace["H_ajax"] = "Ajax";
    $search_and_replace["ALERT_Class"] = "Alert";
    $search_and_replace["CRYPT_Class"] = "Crypt";
    $search_and_replace["DATETIME_class"] = "Datetime";
    $search_and_replace["FILESYSTEM_Class"] = "Filesystem";
    $search_and_replace["HTML_Class"] = "Html";
    $search_and_replace["LANGUAGE_Class"] = "Language";
    $search_and_replace["MAIL_Class"] = "Mail";
    $search_and_replace["MEDIA_Class"] = "Media";
    $search_and_replace["REST_class"] = "Rest";
    $search_and_replace["SECURITY_Class"] = "Security";
    $search_and_replace["SESSIONS_Class"] = "Sessions";
    $search_and_replace["SGBD_Class"] = "DB";
    $search_and_replace["\$SGBD"] = "\$DB";
    $search_and_replace["preparedQueryList"] = "prepared_query_list";
    $search_and_replace["preparedQueryLine"] = "prepared_query_line";
    $search_and_replace["preparedQueryValue"] = "prepared_query_value";
    $search_and_replace["lastInsertId"] = "last_insert_id";
    $search_and_replace["queryList"] = "query_list";
    $search_and_replace["queryLine"] = "query_line";
    $search_and_replace["queryValue"] = "query_value";
    $search_and_replace["USER_Class"] = "User";
    $search_and_replace["UTILS_Class"] = "Utils";
    $search_and_replace["_Admin_Class"] = "";
    $search_and_replace["_admin_class"] = "";
    $search_and_replace["_Public_Class"] = "";
    $search_and_replace["_public_class"] = "";
    $search_and_replace["Spade_Module"] = "HelPHP_module";
    $search_and_replace["MODULE_NAME"] = "module_name";
    $search_and_replace["ApplyBddData"] = "apply_bdd_data";
    $search_and_replace["ProcessData"] = "process_data";
    $search_and_replace["PublishOutput"] = "publish_output";
    $search_and_replace["new HTMLGROUP_Class"] = "H::group";
    $search_and_replace["GetTranslatedText"] = "get_tl";
    $search_and_replace["labelTag"] = "label_tag";
    $search_and_replace["AddChild"] = "add_child";
    $search_and_replace["addChild"] = "add_child";
    $search_and_replace["setAttribute"] = "set_attribute";
    $search_and_replace["GetIndexRelativePath"] = "get_index_relative_path";
    $search_and_replace["InitFormFields"] = "init_form_fields";
    $search_and_replace["ResetFields"] = "reset_fields";
    $search_and_replace["=>\$this->domContainer"] = "=>\$this->dom_target";
    $search_and_replace["BuildModuleTableName"] = "build_module_table_name";
    $search_and_replace["BuildModuleFieldName"] = "build_module_field_name";
    $search_and_replace["BuildTableName"] = "build_table_name";
    $search_and_replace["addAfter"] = "add_after";
    $search_and_replace["calc_TVA"] = "compute_vat";
    $search_and_replace["calc_remise"] = "compute_discount";
    $search_and_replace["calc_HT"] = "remove_vat";
    $search_and_replace["session_force_update();"] = "";
    $search_and_replace["CheckPostedData"] = "check_posted_data";
    $search_and_replace["GetParentData"] = "get_parent_data";
    $search_and_replace["DeleteAssociations"] = "delete_associations";
    $search_and_replace["DeleteParenting"] = "delete_parenting";
    $search_and_replace["DataTreeForSelect"] = "data_tree_for_select";
    $search_and_replace["GetParentId"] = "get_parent_id";
    $search_and_replace["GetAssociationTables"] = "get_association_tables";
    $search_and_replace["HasDataTree"] = "has_data_tree";
    $search_and_replace["\$this->css_class"] = "\$this->css";
    $search_and_replace["\$this->domContainer"] = "\$this->dom_container";
    // lines not needed anymore
    //replacing :
    foreach($search_and_replace as $old => $new){
        $content = str_replace($old, $new, $content);
    }

    $patterns_replacements = [];

    //cleaning the H::tag
    $patterns_replacements['/H::tag\(H::(\w+)/'] = 'H::${1}(';
    //get session value
    $patterns_replacements['/\$this->get_session_value\((\.+)\)/'] = '$_SESSION[${1}]';
    //set session value
    $patterns_replacements['/\$this->set_session_value\((.+),(.+)\)/'] = '$_SESSION[${1}]=${2}';
    //get static session value
    $patterns_replacements['/\$this->get_static_session_value\((\.+)\)/'] = '$_SESSION[${1}]';
    //set static session value
    $patterns_replacements['/\$this->set_static_session_value\((\.+),(\.+)\)/'] = '$_SESSION[${1}]=${2}';
    // rewrite function () 
    // {
    // to function() {
    $patterns_replacements['/(function .+)\n +{/'] = '$1 {';
    // same rewrite for class declaration
    $patterns_replacements['/(class .+HelPHP_module)\n{/'] = '$1 {';
    // remove closing php balise and remove useless line
    $patterns_replacements['/\?>\n*/'] = '';
    // transform Config to $CONFIG
    $patterns_replacements['/( *)(.*)Config/'] = '$1global \$CONFIG;'.PHP_EOL.'$1$2\$CONFIG';
    // specific to tl file, add module_name in first position of every languages
    if (preg_match('/\/tl_.*.php/', $new_file)){
        $patterns_replacements['/(=>array\(\n)/'] = "\$1        'module_name'=>'$module_name',\n";
    }

    foreach($patterns_replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    
    

    Filesystem::save_content($new_file,$content);
    chown($new_file, $stats[4]);
    chgrp($new_file, $stats[5]);
    echo $new_file.PHP_EOL;
}
function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}