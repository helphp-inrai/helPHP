<?php

include_once(dirname(dirname(__DIR__)).'/config/main.php');
include_once(Config::HELPHP_FOLDER.'autoload.php');

$module = new helPHP\modules\search\public\Search();
$module->process_data($_POST);
$module->publish_output();
