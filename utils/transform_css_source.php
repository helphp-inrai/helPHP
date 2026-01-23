<?php

use helPHP\libs\Utils;

global $CLI;
$CLI = isset($argc); // true, called from CLI

if ($CLI) { // call from CLI

    if (!isset($argv[1])){
        error_log('no argument passed when calling install_instance.php');
        exit('missing_argument');
    }

    $target = '/'.trim($argv[1],'/').'/';
    if (!is_dir($target)){
        error_log('path : '.$target.' not found');
        exit('path_not_found');
    }

    transform($target);
}

function transform($instance){
    include_once($instance.'config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    global $DB, $FS, $CONFIG;

    $base_path = $CONFIG::HOME_FOLDER.'css/theme/';
    
    // create admin and public folder in css/theme/
    if (!file_exists($base_path.'admin')) $FS->mkdir($base_path.'admin');
    if (!file_exists($base_path.'public')) $FS->mkdir($base_path.'public');

    // get all theme
    $q = 'SELECT sr.*, th.name FROM '.$DB->table('csseditor_source').' sr LEFT JOIN '.$DB->table('csseditor_theme').' th';
    $q.=' ON (th.id_source=sr.id) WHERE sr.type="theme"';
    $themes = $DB->prepared_query_list($q);
    Utils::error_log($themes);
    if (!$themes) return;

    // move theme in corresponding folder and change source path
    foreach($themes as $theme){
        $new_path = $base_path;
        $new_path.= ($theme['admin']) ? 'admin/' : 'public/';
        $new_path.= $theme['name'].'/';
        // create folder for theme
        if (!file_exists($new_path)) $FS->mkdir($new_path);
        // move css file
        $FS->move([['path'=>$theme['path'], 'name'=>'theme.css']], $new_path);
        $q = 'UPDATE '.$DB->table('csseditor_source').' SET path="'.$new_path.'theme.css" WHERE id='.$theme['id'];
        $DB->query($q);
    }
}