<?php

// $siteroot = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['SCRIPT_FILENAME'])[0];
// include_once($siteroot.'/config/main.php');
include_once('../config/main.php');
include_once(Config::HELPHP_FOLDER.'autoload.php');

if (!is_file(Config::HOME_FOLDER.'originals/index.html')){
    include_once($CONFIG::HOME_FOLDER.'installscript.php');
}

include_once(__DIR__ . '/core/index.php');