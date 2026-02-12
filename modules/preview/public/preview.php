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

include_once('../../config/main.php');
include_once(Config::HELPHP_FOLDER.'autoload.php');

use \helPHP\libs\H;
use \helPHP\libs\Utils;
use helPHP\modules\csseditor\admin\Csseditor;

// parse GET data
// $module_name = false;


global $module_html_content,$CONFIG;

// Utils::error_log($_GET);

if (isset($_GET['module'])){
    $moduleName = $_GET['module'];
    unset($_GET['module']);
}

$admin = isset($_GET['admin']);
// $admin = isset($_GET['admin']) && intval($_GET['admin']) > 0;
unset($_GET['admin']);

// $_POST = [];
foreach($_GET as $key => $val){
    $_POST[$key] = $_GET[$key];
}

$theme_admin = $admin;
if (isset($_GET['prevmode'])) {
    if ($_GET['prevmode'] == 'public') $theme_admin = false;
    else $theme_admin = true;
}

if (isset($_GET['language'])) {
    $_SESSION['preview_language'] = $_GET['language'];
}

if (isset($moduleName)) {
    $_POST['core_insert'] = true;

    $output = H::new_document('HelPHP', '', '', false, !$admin);

    // remove css
    $style = $output->find_child('css', 5);
    $style->children = [];

    $displayModule = H::DIV(array('id'=>'preview_module' , 'class'=>'preview_module'));

    $head = $output->find_child('head', 5);
    $id_theme = Csseditor::get_current_theme(true, $theme_admin);
    $style->add_child(Csseditor::get_css($id_theme));

    $module_html_content[$moduleName] = '';
    if ($admin) include($CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER.$moduleName.'/index.php');
    else include($CONFIG::HOME_FOLDER.'public/'.$moduleName.'/index.php');

    $displayModule->add_child($module_html_content[$moduleName]);

    $output->add_child($displayModule);

    echo $output;
} else {
    if ($admin) include($CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER.'index.php');
    else include($CONFIG::HOME_FOLDER.'index.php');
}