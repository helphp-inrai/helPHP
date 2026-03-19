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

    $admin_user = $argv[2];
    $admin_pass = $argv[3];

    $db_root_user = $argv[4];
    $db_root_pass = $argv[5];

     for($i = 4; $i < count($argv); $i++){
        $arg = $argv[$i];
        $t = explode('=', $argv[$i]);
        if ($t[0] == '--central_user') $root_username_central = $t[1];
        if ($t[0] == '--central_pass') $root_password_central = $t[1];
        if ($t[0] == '--slave_user') $root_username_slave = $t[1];
        if ($t[0] == '--slave_pass') $root_password_slave = $t[1];
    }

    install_instance($target, $admin_user, $admin_pass, $db_root_user, $db_root_pass, $root_username_central, $root_password_central, $root_username_slave, $root_password_slave);
}

function install_instance($home_folder, $admin_user, $admin_pass, $db_root_user, $db_root_pass, $db_root_user_central, $db_root_password_central, $db_root_user_slave, $db_root_password_slave){

    include_once($home_folder.'config/main.php');
    global $CONFIG;
    $CONFIG = new \Config();

    include_once($home_folder.'config/db.php');
    global $CONFIG_DB;
    $CONFIG_DB = new \Config_db();

    set_time_limit(100);

    // create public and admin folder
    $old_umask = umask(2);
    if (!file_exists($home_folder.'public')) mkdir($home_folder.'public', 0775, true);
    if (!file_exists($home_folder.$CONFIG::ADMIN_FOLDER)) mkdir($home_folder.$CONFIG::ADMIN_FOLDER, 0775, true);

    // make all directories and symbol link needed by the instance to work properly
    // js folders
    if (!file_exists($home_folder.'js/tmp')) mkdir($home_folder.'js/tmp', 0775, true);
    if (!file_exists($home_folder.'js/externals')) mkdir($home_folder.'js/externals', 0775, true);
    // tinymce
    if (!file_exists($home_folder.'js/externals/tinymce')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/tinymce '.$home_folder.'js/externals/tinymce');
    // alwan colorpicker
    if (!file_exists($home_folder.'js/externals/alwan')) mkdir($home_folder.'js/externals/alwan', 0775, true);
    if (!file_exists($home_folder.'js/externals/alwan/alwan.min.js')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/alwan/alwan.min.js '.$home_folder.'js/externals/alwan/alwan.min.js');
    if (!file_exists($home_folder.'js/externals/alwan/alwan.min.css')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/alwan/alwan.min.css '.$home_folder.'js/externals/alwan/alwan.min.css');
    // ace
    if (!file_exists($home_folder.'js/externals/ace')) shell_exec('ln -sf '.$CONFIG::HELPHP_FOLDER.'js/externals/ace '.$home_folder.'js/externals/ace');
    // tmp js

    if (!file_exists($home_folder.'/temp')) {
        mkdir($home_folder.'temp', 0775, true);
    }
    
    // log
    // if (!file_exists($CONFIG::LOG_FILE)) {
    //     mkdir($CONFIG::LOG_FOLDER, 0775, true);
    // }
    // if (!file_exists($CONFIG::LOG_FOLDER.'helPHP.log')) {
    //     shell_exec('touch '.$CONFIG::LOG_FOLDER.'helPHP.log');
    // }
    // chmod($CONFIG::LOG_FOLDER.'helPHP.log', 00775);

    // css
    if (!file_exists($home_folder.'css')) {
        mkdir($home_folder.'css', 0775, true);
    }
    if (!file_exists($home_folder.'css/gz')) {
        mkdir($home_folder.'css/gz', 0775, true);
    }
    if (!file_exists($home_folder.'css/theme')) {
        mkdir($home_folder.'css/theme', 0775, true);
    }
    
    umask($old_umask);
    file_put_contents($home_folder.'errors.txt','folders done'.PHP_EOL, FILE_APPEND);
    $cmd = 'php '.$CONFIG::HELPHP_FOLDER.'utils/install_db_and_modules.php '.$home_folder.' '.$db_root_user.' '.$db_root_pass;
    if ($CONFIG_DB::DB_CENTRAL) $cmd.= ' --central_user='.$db_root_user_central.' --central_pass='.$db_root_password_central;
    if ($CONFIG_DB::MASTER_SLAVE_MODE) $cmd.= ' --slave_user='.$db_root_user_slave.' --slave_pass='.$db_root_password_slave;
    $res = shell_exec($cmd);
     

    include_once($CONFIG::HELPHP_FOLDER.'/autoload.php');

    \helPHP\libs\Ajax::update_storage_status();

    // create admin usercreate_account
    global $USER;
    $USER->create_account([
        'login'     => $admin_user,
        'password'  => $admin_pass,
        'email'     => 'fakeemail@helPHP.com',
    ]);

    global $DB, $DB_CENTRAL, $FS;
    $DB_CENTRAL->query('UPDATE '.$DB_CENTRAL->table('users_data').' SET active=1, admin=1 WHERE id=1');

    // detect presence of admin theme file and install them
    $path_files = $home_folder.'css/theme/';
    $folders = $FS->recurse_ls($path_files)['folders'];
    if ($folders) {
        global $FS;
        $csseditor = new \helPHP\modules\csseditor\admin\Csseditor();
        foreach($folders as $folder){
            if (!str_contains($folder, '/')) continue;
            $t = explode('/', $folder);
            $name = $t[1];
            $admin = $t[0] == 'admin' ? 1 : 0;
            $csseditor->import([
                'path'=>$path_files.$folder,
                'admin'=>$admin,
                'name'=>$name
            ]);
        }
    }

    foreach($CONFIG::MODULES_LIST as $name => $module){
        set_time_limit(100);
        
        // installing css public and admin
        $css_paths = [$CONFIG::HELPHP_FOLDER.'modules/'.$name.'/admin/'.$name.'.css', $CONFIG::HELPHP_FOLDER.'modules/'.$name.'/public/'.$name.'.css'];
        foreach($css_paths as $path){
            if (!file_exists($path)) continue;
            $admin = str_contains($path, 'admin') ? 1 : 0;
            $md5 = \md5_file($path);
            $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE path=?';
            $source = $DB->prepared_query_line($q, 's', [$path]);
            if ($source) {
                if ($source['md5'] != $md5) \helPHP\modules\csseditor\admin\Csseditor::compare_css_source($source);
            } else {
                $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="module", path=?, md5=?, admin='.$admin;
                $DB->prepared_query($q, 'ss', [$path, $md5]);
                $id_source = $DB->last_insert_id();
                \helPHP\modules\csseditor\admin\Csseditor::import_css_source($path, $id_source, true);
            }
        }

        // load the install.php file of the module to get data
        if (!is_file($CONFIG::HELPHP_FOLDER.'modules/'.$name.'/install.php')) continue;
        include($CONFIG::HELPHP_FOLDER.'modules/'.$name.'/install.php');
    }

    //check multiple lang case from  config :
    if (sizeof($CONFIG::AVAILABLE_LANGUAGES) > 1){
        foreach ($CONFIG::AVAILABLE_LANGUAGES as $key=>$isolang){
            $q = 'SELECT DISTINCT allo.id_data from '.$DB->table('languages_allowed').' allo,'.$DB->table('languages_data').' dat WHERE allo.id_data=dat.id and dat.iso=?';
            $existing = $DB->prepared_query_value($q, 's', array($isolang));
            if ($existing =="" || $existing==false){
                $idiso= $DB->query_value('SELECT DISTINCT dat.id from '.$DB->table('languages_data').' dat WHERE dat.iso="'.$isolang.'"');
                $q = 'INSERT INTO '.$DB->table('languages_allowed').' set id_data=?';
                $existing = $DB->prepared_query_value($q, 'i', array($idiso));
            }
        }
    }

    // make constants
    shell_exec('php '.$CONFIG::HELPHP_FOLDER.'utils/constants.php '.$home_folder);

    // add extra data to the DB
    if (file_exists($CONFIG::HOME_FOLDER.'install.json')) {
        $DB->sql_from_json(file_get_contents($CONFIG::HOME_FOLDER.'install.json'));
    }

    // do extra stuff in the instance depending the need of the instance
    if (file_exists($CONFIG::HOME_FOLDER.'install_extra.php')) {
        include_once($CONFIG::HOME_FOLDER.'install_extra.php');
    }

    // add a file to indicate to the end of installation to the script waiting for.
    if (!file_exists($home_folder.'originals/index.html')) {
        if (!file_exists($home_folder.'originals')){
            mkdir($home_folder.'originals');
        }
        file_put_contents($home_folder.'originals/installed.html', '');
    }
}