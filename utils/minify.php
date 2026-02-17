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

use helPHP\libs\DB;
use helPHP\utils\Packer;
use helPHP\libs\Utils;
use helPHP\modules\csseditor\admin\Csseditor;

if (!isset($argv[1])){
    error_log('no argument passed when calling minify.php');
    exit('missing_argument');
}

$target = '/'.trim($argv[1],'/').'/';

if (!is_dir($target)){
    error_log('path : '.$target.' not found');
    exit('path_not_found');
}

if (!is_file($target.'config/main.php')){
    error_log('path : '.$target.'config/main.php not found');
    exit('main_file_not_found');
}else{
    minify($target);
}

/**
 * Minify an instance : Take all js files to create only one js.gz file
 * the same for the CSS theme. 
 * 
 * At the end you'll get only two small files instead of 30/40~ and with a size reduced by ten.
 * of course the impact on speed is huge and as the number or http queries is reduced the server ressource are increased by 20 ~ !
 * 
 * After minifying, you just need to swith DEVMODE to false in your main config file to see the difference.
 * 
 * it's common practices to keep one version of the instance as a dev version and the second as production.
 * 
 * in that case you just need to move css/gz/* and jsgz/* files from dev version to prod + database 
 *
 * @param string $target the home path of the instance to get config
 * 
 * @return String|files echoing results of minification.
 * 
 * @package helPHP\utils
 */
function minify($target){
    global $DB, $CONFIG;

    $copyright='/**
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
';

    $uw_user = $uw_name = "www-data";

    // list all files to minify
    $js_to_min = array();
    $js_to_min_admin = array();
    $css_to_min = array();
    $css_to_min_admin = array();

    include_once($target.'config/main.php');
    include_once(Config::HELPHP_FOLDER.'autoload.php');

    if (!Utils::write_in_config('minification_time', 's', time())){
        exit('error_write_in_config');
    }
        
    DB::create_instance();
    $CONFIG = new \Config();

    $js_statics = ['generics.js', 'dom.js', 'events.js', 'ajax.js', 'validator.js', 'animate.js', 'history.js', 'ui.js', 'module.js', 'init.js'];
    $js_folder = $CONFIG::HELPHP_FOLDER.'js/';
    foreach($js_statics as $file){
        if ($CONFIG::INCLUDE_JS_ANIMATE==false && $file=="animate.js"){
            continue;
        }
        array_push($js_to_min, $js_folder.$file);
        array_push($js_to_min_admin, $js_folder.$file);
    }

    // add the js from module
    foreach($CONFIG::MODULES_LIST as $moduleName => $module){
        $admin_path = $CONFIG::HOME_FOLDER.'admin/'.$moduleName.'/'.$moduleName.'.js';
        if (!file_exists($admin_path)) $admin_path = $CONFIG::HELPHP_FOLDER.'modules/'.$moduleName.'/admin/'.$moduleName.'.js';
        if (file_exists($admin_path)){
            array_push($js_to_min_admin, $admin_path);
        }

        $public_path = $CONFIG::HOME_FOLDER.'public/'.$moduleName.'/'.$moduleName.'.js';
        if (!file_exists($public_path)) $public_path = $CONFIG::HELPHP_FOLDER.'modules/'.$moduleName.'/public/'.$moduleName.'.js';
        if (file_exists($public_path)){
            array_push($js_to_min, $public_path);
        }
    }

    // parse each public js file to make one big string
    $js_str = '';
    foreach ($js_to_min as $filepath) {
        $js_str.= file_get_contents($filepath)."\n";
    }
    $packer = new Packer($js_str, '10');
    $packed_js = $copyright.$packer->pack();
    
    // $packed_js=$js_str;
    // write into a file
    writeUTF8File($target.'jsgz/all.js', $packed_js);
    unlink($target.'jsgz/all.js.gz');
    // gzip the file and adjust it right
    exec("gzip ".$target."/jsgz/all.js");
    chmod($target."jsgz/all.js.gz", 0755);
    chown($target."jsgz/all.js.gz", $uw_user);
    chgrp($target."jsgz/all.js.gz", $uw_name);

    // parse each admin js file to make one big string
    $js_adm_str = '';
    foreach ($js_to_min_admin as $filepath) {
        $js_adm_str.= file_get_contents($filepath)."\n";
    }
    $packer2 = new Packer($js_adm_str, '10');
    $packed_js = $copyright.$packer2->pack();
    
    // $packed_js=$js_adm_str;
    // write into a file
    writeUTF8File($target.'jsgz/alladm.js', $packed_js);
    unlink($target.'jsgz/alladm.js.gz');
    // gzip the file and adjust it right
    exec("gzip ".$target."jsgz/alladm.js");
    chmod($target."jsgz/alladm.js.gz", 0755);
    chown($target."jsgz/alladm.js.gz", $uw_user);
    chgrp($target."jsgz/alladm.js.gz", $uw_name);

    // doing the same for the css, first the public
    $css_str = Csseditor::get_css($CONFIG::THEME_ID);
    if (str_contains($css_str, 'url(../')){
        $css_str = str_replace('url(../', 'url(../../', $css_str);
    }
    // $css_str = Utils::get_css_as_str($CONFIG::THEME_ID);
    writeUTF8File($target."css/gz/all.css", $css_str);
    unlink($target."/css/gz/all.css.gz");
    exec("gzip ".$target."/css/gz/all.css");
    chmod($target."css/gz/all.css.gz", 0755);
    chown($target."css/gz/all.css.gz", $uw_user);
    chgrp($target."css/gz/all.css.gz", $uw_name);
    // unlink($target."css/gz/all.css");

    // now the admin
    // $css_str = Utils::get_css_as_str($CONFIG::THEME_ID_ADMIN);
    $css_str = Csseditor::get_css($CONFIG::THEME_ID_ADMIN);
    if (str_contains($css_str, 'url(../')){
        $css_str = str_replace('url(../', 'url(../../', $css_str);
    }
    writeUTF8File($target."css/gz/alladm.css", $css_str);
    unlink($target."css/gz/alladm.css.gz");
    exec("gzip ".$target."css/gz/alladm.css");
    chmod($target."css/gz/alladm.css.gz", 0755);
    chown($target."css/gz/alladm.css.gz", $uw_user);
    chgrp($target."css/gz/alladm.css.gz", $uw_name);
    // unlink($target."css/gz/alladm.css");

    echo 'done';
}
function writeUTF8File($filename, $content)
{
    $f=fopen($filename, "w");
    # Now UTF-8 - Add byte order mark
    fwrite($f, pack("CCC", 0xef, 0xbb, 0xbf));
        
    fwrite($f, $content);
    fclose($f);
}