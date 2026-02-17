<?php

if(!isset($argv)){
    die('home path needed');
}
$target = $argv[1];
$target = rtrim($target, '/');
if (!is_dir($target)) {
    die('bad path');
} else {
    make_constants_js($target);
}

use helPHP\libs\HelPHP_module;
use helPHP\libs\Ajax;
use helPHP\libs\Filesystem;
use helPHP\libs\Html;
use helPHP\libs\Media;
use helPHP\libs\Utils;
/**
 * The constants utils is used to make/update a js file with all needed constants
 * like some translations, basic context infos etc, anything that is necessary to make
 * run js parts of modules and helPHP js libs
 * 
 * can be called from cli with instance path as argument: 
 * php helphp/utils/constants.php instance_home_path
 *
 * @param mixed $target the home path of the instance to get config
 * 
 * @return String|files echoing results and js/constants*.js files in the instance.
 * 
 * @package helPHP\utils
 */
function make_constants_js($target) {
    // echo $target.'/config/main.php'.PHP_EOL.PHP_EOL;
    include_once($target.'/config/main.php');
    include_once(Config::HELPHP_FOLDER.'/autoload.php');

    $posted_varname = HelPHP_module::posted_varname;
    $posted_varname_container = HelPHP_module::posted_container_name;

    $output = 'var H_constants = {};'.PHP_EOL;
    //languages constants...
    $output.= 'H_constants.languages ='.PHP_EOL;
    global $LANG;
    $langdata = $LANG->get_languages_data();
    $lng = array();
    foreach ($langdata as $lng_data) {
        $lng[$lng_data['id_data']] = $lng_data;
    }
    $output.= json_encode($lng).';'.PHP_EOL; 
    //various config vars
    $output.= 'H_constants.current_lang_id = '.$LANG->current_id_data.';'.PHP_EOL;
    $output.= 'H_constants.current_lang_iso = "'.$LANG->current_language.'";'.PHP_EOL;
    $sids = (Config::DEVMODE == true)?'true':'false';
    $output.= 'H_constants.sids = '.$sids.';'.PHP_EOL;
    $output.= 'H_constants.base_url = "'.Config::BASE_URL.'";'.PHP_EOL;
    $output.= 'H_constants.site_folder = "'.Config::SITE_FOLDER.'";'.PHP_EOL;
    $output.= 'H_constants.include_js_animate = '.Config::INCLUDE_JS_ANIMATE.';'.PHP_EOL;
    $output.= 'H_constants.reload_noparams = "'.HelPHP_module::reload_no_params.'";'.PHP_EOL;
    $output.= 'H_constants.posted_varname = "'.$posted_varname.'";'.PHP_EOL;
    $output.= 'H_constants.posted_varname_container = "'.$posted_varname_container.'";'.PHP_EOL;

    // //storage status (must be called again after an upload or on connection)
    Ajax::update_storage_status();

    // max size for ONE file that can be upload without chunk
    $output.= 'H_constants.file_upload_size = 1048576; // 1 Mo'.PHP_EOL;
    // when uploading a big file, we need to divise the file into little chunk.
    // this variable indicate the size of those chunk (1 Mo)
    $output.= 'H_constants.slice_upload_size = 1048576;'.PHP_EOL;

    // variable used in conjunction with the PHP Ajax to identify json variables
    $output.= 'H_constants.json_list_identifier = "'.Ajax::json_list_identifier.'";'.PHP_EOL;
    $output.= 'H_constants.quick_edit_field = "'.Html::QUICK_EDIT_FIELD.'";'.PHP_EOL;
    $output.= 'H_constants.quick_edit_data = "'.Html::QUICK_EDIT_DATA.'";'.PHP_EOL;
    $output.= 'H_constants.quick_edit_id = "'.Html::QUICK_EDIT_ID.'";'.PHP_EOL;
    $output.= 'H_constants.quick_edit_type = "'.Html::QUICK_EDIT_TYPE.'";'.PHP_EOL;
    $output.= 'H_constants.quick_edit_type_index = "'.Html::QUICK_EDIT_TYPE_INDEX.'";'.PHP_EOL;

    //texts from tl...

    if (Config::USERNAME_VALID_STRING !== null) {
        $output.= 'H_constants.username_valid_string = \''.Config::USERNAME_VALID_STRING.'\';'.PHP_EOL;
    }

    $output.= 'H_constants.get_text = function(key , replace_array){
        if (H_constants.texts[key]===undefined){
            return \'{\'+key+\'}\';
        }else{
            return H_strings.complete_string(H_constants.texts[key] , replace_array);
        }
    };'.PHP_EOL;

    $output.= 'H_constants.image_ext = '.json_encode(Media::image_ext).';'.PHP_EOL;
    $output.= 'H_constants.video_ext = '.json_encode(Media::video_ext).';'.PHP_EOL;

    // now we're writing constants for the theme
    global $DB;
    $q = 'SELECT pub.options as public, adm.options as `admin` FROM '.$DB->table('csseditor_theme').' pub LEFT JOIN '.$DB->table('csseditor_theme').' adm ON (adm.id='.$CONFIG::THEME_ID_ADMIN.') WHERE pub.id='.$CONFIG::THEME_ID;
    $theme_opts = $DB->query_line($q);
    $output.= 'H_constants.theme_public = '.(($theme_opts['public'] != '' && $theme_opts['public'] != NULL) ? htmlspecialchars_decode($theme_opts['public']) : '{}').';'.PHP_EOL;
    $output.= 'H_constants.theme_admin = '.(($theme_opts['admin'] != '' && $theme_opts['admin'] != NULL) ? htmlspecialchars_decode($theme_opts['admin']) : '{}').';'.PHP_EOL;

    //writing the file
    $constantsFile = fopen(Config::HOME_FOLDER.'js/constants.js', 'w');
    fwrite($constantsFile, $output);
    fclose($constantsFile);
    // echo 'main constants done'.PHP_EOL;
    // now we're writing constants text depending the languages available in this instance

    $err = 0;
    foreach ($langdata as $lng_data) {
        $texts = [];
        if (is_file(Config::HELPHP_FOLDER.'libs/tl/tl_constants-'.$lng_data['iso'].'.php')){
            include_once(Config::HELPHP_FOLDER.'libs/tl/tl_constants-'.$lng_data['iso'].'.php');
            if (isset($tl_data) && isset($tl_data[$lng_data['iso']])) {
                $texts = array_merge($texts, $tl_data[$lng_data['iso']]);   
            }
        }
        if (is_file(Config::HELPHP_FOLDER.'libs/tl/tl_common-'.$lng_data['iso'].'.php')){
            include_once(Config::HELPHP_FOLDER.'libs/tl/tl_common-'.$lng_data['iso'].'.php');
            if (isset($tl_data) && isset($tl_data[$lng_data['iso']])) {
                $texts = array_merge($texts, $tl_data[$lng_data['iso']]);
            }
        }

        $output='H_constants.texts = {};'.PHP_EOL;

        foreach ($texts as $key=>$value) {
            if (str_contains($key,'-')) $output.= 'H_constants.texts["'.$key.'"] = \''.addslashes($value).'\';'.PHP_EOL;
            else $output.= 'H_constants.texts.'.$key.' = \''.addslashes($value).'\';'.PHP_EOL;
        }

        $res = Filesystem::save_content(Config::HOME_FOLDER.'js/constants_texts-'.$lng_data['iso'].'.js', $output,true);
        if (!$res){
            $err++;
        }
        // echo 'write in file '.Config::HOME_FOLDER.'js/constants_texts-'.$lng_data['iso'].'.js ';
        // echo $res !== false ? 'success' : 'error';
        // echo PHP_EOL;
        // $constantsFile = fopen(Config::HOME_FOLDER.'js/constants_texts-'.$lng_data['iso'].'.js', 'w');
        // fwrite($constantsFile, $output);
        // fclose($constantsFile);
    }

    if ($err === 0){
        echo 'done';
    } else {
        echo 'err';
    }
}