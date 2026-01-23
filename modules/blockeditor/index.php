<?php
/* version: 1.0 */

// index for instance/admin/block

if (is_file('../../config/main.php')) {
    include_once('../../config/main.php');
} else if (is_file('../config/main.php')){
    include_once('../config/main.php');
}
include_once(Config::HELPHP_FOLDER.'autoload.php');

$module = new helPHP\modules\block\Bridge();
$module->process_data($_POST);
$module->publish_output();
