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

$home_folder = __DIR__.'/';

if (is_file('../../config/main.php')) {
    include_once('../../config/main.php');
} else if (is_file('../config/main.php')){
    include_once('../config/main.php');
} else if (is_file('./config/main.php')){
    include_once('./config/main.php');
}

global $CONFIG;
$CONFIG = new \Config();

if (is_file('../../config/db.php')) {
    include_once('../../config/db.php');
} else if (is_file('../config/db.php')){
    include_once('../config/db.php');
} else if (is_file('./config/db.php')){
    include_once('./config/db.php');
}

$instance_path = __DIR__.'/';

// values to get from Config
$config_values = [
    'string'=>[
        'HELPHP_FOLDER'=>'/home/helPHP/',

        'SITE_NAME'=>'helPHP',
        'DOMAIN'=>'helPHP.com',
        'SITE_FOLDER'=>'',
        'ADMIN_FOLDER'=>'admin',

        'LIBTRANSLATE_URL'=>'http://libretranslate:5000/',
        'LIBTRANSLATE_APIKEY'=>'e43158fd-ff6d-487f-85bc-31732234187a',

        'REDIS_HOST'=>'redis',
        'REDIS_PORT'=>'6379',
    ],

    'boolean'=>[
        'REDIS'=>true,
        'API_MODE'=>true,
        'CLUSTER_MODE'=>false,
    ]
];

$data = [];
foreach($config_values as $type => $list){
    foreach($list as $name => $default_value){
        if ($type == 'string') {
            $data[$name] = isset($_POST[$name]) ? $_POST[$name] : false;
            if ($data[$name] === false) $data[$name] = (null != Config::{$name}) ? Config::{$name} : $default_value;

            // some special cases
            if ($name == 'HELPHP_FOLDER') if ($data[$name] != '') $data[$name] = '/'.trim($data[$name], '/').'/';
        }

        if ($type == 'boolean') {
            $data[$name] = (isset($_POST[$name]) && intval($_POST[$name]) == 1) ? true : false;
            if (!isset($_POST['action']) && $data[$name] === false) {
                $data[$name] = (null != Config::{$name}) ? Config::{$name} : $default_value;
            }
        }
    }
}

$config_db_values = [
    'string'=>[
        'DB_HOST'=>'mariamysql',
        'DB_BASE'=>'helPHP',
        'DB_USER'=>'helPHP',
        'DB_PASS'=>'',
        'DB_TABLE_PREFIX'=>'hlp',

        'DB_SLAVE_HOST'=>'',
        'DB_SLAVE_USER'=>'',
        'DB_SLAVE_BASE'=>'',
        'DB_SLAVE_PASS'=>'',

        'DB_CENTRAL_HOST'=>'',
        'DB_CENTRAL_USER'=>'',
        'DB_CENTRAL_BASE'=>'',
        'DB_CENTRAL_PASS'=>'',

        'DB_JOBS_HOST'=>'',
        'DB_JOBS_USER'=>'',
        'DB_JOBS_BASE'=>'',
        'DB_JOBS_PASS'=>'',

    ],
    
    'boolean'=>[
        'MASTER_SLAVE_MODE'=>false,
        'DB_CENTRAL'=>false,
        'DB_JOBS'=>true,
    ]
];
foreach($config_db_values as $type => $list){
    foreach($list as $name => $default_value){
        if ($type == 'string') {
            $data[$name] = isset($_POST[$name]) ? $_POST[$name] : false;
            if ($data[$name] === false) $data[$name] = (null != Config_db::{$name}) ? Config_db::{$name} : $default_value;

            // some special cases
            // if ($name == 'HELPHP_FOLDER') if ($data[$name] != '') $data[$name] = '/'.trim($data[$name], '/').'/';
        }

        if ($type == 'boolean') {
            $data[$name] = (isset($_POST[$name]) && intval($_POST[$name]) == 1) ? true : false;
            if (!isset($_POST['action']) && $data[$name] === false) {
                $data[$name] = (null != Config_db::{$name}) ? Config_db::{$name} : $default_value;
            }
        }
    }
}

// error_log(json_encode($data));

// db root
$db_root_user = isset($_POST['db_root_login']) ? $_POST['db_root_login'] : '';
$db_root_pass = isset($_POST['db_root_password']) ? $_POST['db_root_password'] : '';

// db root central
$db_root_user_central = isset($_POST['db_root_login_central']) ? $_POST['db_root_login_central'] : '';
$db_root_password_central = isset($_POST['db_root_password_central']) ? $_POST['db_root_password_central'] : '';

// db root slave
$db_root_user_slave = isset($_POST['db_root_login_slave']) ? $_POST['db_root_login_slave'] : '';
$db_root_password_slave = isset($_POST['db_root_password_slave']) ? $_POST['db_root_password_slave'] : '';

// instance admin
$admin_user = isset($_POST['admin_user']) ? $_POST['admin_user'] : '';
$admin_pass = isset($_POST['admin_pass']) ? $_POST['admin_pass'] : '';

if (isset($_POST['action']) && $_POST['action'] == 'install') {
    
    $err = false;
    // verify if the root login and password are given
    $missing_root = false;
    if ($db_root_user == '' || $db_root_pass == '') {
        // display a message for missing information
        $missing_root = true;
        $err = true;
    }

    // verify the main db 
    $missing_db = false;
    if ($data['DB_HOST'] == '' || $data['DB_BASE'] == '' || $data['DB_TABLE_PREFIX'] == '' || $data['DB_USER'] == '' || $data['DB_PASS'] == '') {
        // display a message for missing information
        $missing_db = true;
        $err = true;
    }

    // $helphp_folder = $_POST['helphp_folder'] != '' ? $_POST['helphp_folder'] : false;
    // $helphp_folder_filled = ($helphp_folder !== false);
    $helphp_folder_found = false;
    if ($data['HELPHP_FOLDER'] != '' && file_exists($data['HELPHP_FOLDER']) && file_exists($data['HELPHP_FOLDER'].'libs/Utils.php')) {
        $helphp_folder_found = true;
    } else {
        $err = true;
    }

    // verify if the admin login and password are given
    $missing_admin = false;
    if ($admin_user == '' || $admin_pass == '') {
        // display a message for missing information
        $missing_admin = true;
        $err = true;
    }
    // verify password size
    $min_size = Config::USERPASSWORD_MINIMUM_LENGTH == null ? 6 : Config::USERPASSWORD_MINIMUM_LENGTH;
    $short_password = false;
    if (strlen($admin_pass) <= $min_size) {
        $short_password = true;
        $err = true;
    }

    // verify root user and pass for central if activate
    if ($data['DB_CENTRAL']) {
        $missing_root_central = false;
        if ($db_root_user_central == '' || $db_root_password_central == '') {
            // display a message for missing information
            $missing_root_central = true;
            $err = true;
        }
    }

    // verify root user and pass for slave if activate
    if ($data['MASTER_SLAVE_MODE']) {
        $missing_root_slave = false;
        if ($db_root_user_slave == '' || $db_root_password_slave == '') {
            // display a message for missing information
            $missing_root_slave = true;
            $err = true;
        }
    }

    if ($err === false) {
        // load utils
        include_once($data['HELPHP_FOLDER'].'libs/Utils.php');

        // write into main.php
        $types = [];
        $names = [];
        $values = [];
        foreach($config_values as $type => $list){
            foreach($list as $name => $default_value) {
                $t = $type == 'string' ? 's' : 'b';
                array_push($types, $t);
                array_push($names, $name);
                array_push($values, $data[$name]);
            }
        }
        \helPHP\libs\Utils::write_in_config($names, $types, $values);

        // write into db.php
        $types = [];
        $names = [];
        $values = [];
        foreach($config_db_values as $type => $list){
            foreach($list as $name => $default_value) {
                $t = $type == 'string' ? 's' : 'b';
                array_push($types, $t);
                array_push($names, $name);
                array_push($values, $data[$name]);
            }
        }
        \helPHP\libs\Utils::write_in_config($names, $types, $values, $instance_path.'config/db.php');

         // create public and admin folder
        exec('mkdir -p '.Config::HOME_FOLDER.'public && chmod 2774 '.Config::HOME_FOLDER.'public');
        exec('mkdir -p '.Config::HOME_FOLDER.'admin && chmod 2774 '.Config::HOME_FOLDER.'admin');

        // include install db and launch it
        $cmd = 'php '.$data['HELPHP_FOLDER'].'utils/install_db_and_modules.php '.$instance_path.' '.$db_root_user.' '.$db_root_pass;
        if ($data['DB_CENTRAL']) $cmd.= ' --central_user='.$db_root_user_central.' --central_pass='.$db_root_password_central;
        if ($data['MASTER_SLAVE_MODE']) $cmd.= ' --slave_user='.$db_root_user_slave.' --slave_pass='.$db_root_password_slave;
        error_log($cmd);
        $res = shell_exec($cmd);

        // make all directories and symbol link needed by the instance to work properly
        // js folders
        if (!file_exists(Config::HOME_FOLDER.'js')) shell_exec('mkdir -p '.Config::HOME_FOLDER.'js && chmod 2774 '.Config::HOME_FOLDER.'js');
        if (!file_exists(Config::HOME_FOLDER.'js/externals')) shell_exec('mkdir -p '.Config::HOME_FOLDER.'js/externals && chmod 2774 '.Config::HOME_FOLDER.'js/externals');
        // tinymce
        if (!file_exists(Config::HOME_FOLDER.'js/externals/tinymce')) shell_exec('ln -sf '.$data['HELPHP_FOLDER'].'js/externals/tinymce '.Config::HOME_FOLDER.'js/externals/tinymce');
        // alwan colorpicker
        if (!file_exists(Config::HOME_FOLDER.'js/externals/alwan')) shell_exec('mkdir -p '.Config::HOME_FOLDER.'js/externals/alwan && chmod 2774 '.Config::HOME_FOLDER.'js/externals/alwan');
        if (!file_exists(Config::HOME_FOLDER.'js/externals/alwan/alwan.min.js')) shell_exec('ln -sf '.$data['HELPHP_FOLDER'].'js/externals/alwan/alwan.min.js '.Config::HOME_FOLDER.'js/externals/alwan/alwan.min.js');
        if (!file_exists(Config::HOME_FOLDER.'js/externals/alwan/alwan.min.css')) shell_exec('ln -sf '.$data['HELPHP_FOLDER'].'js/externals/alwan/alwan.min.css '.Config::HOME_FOLDER.'js/externals/alwan/alwan.min.css');
        // ace
        if (!file_exists(Config::HOME_FOLDER.'js/externals/ace')) shell_exec('ln -sf '.$data['HELPHP_FOLDER'].'js/externals/ace '.Config::HOME_FOLDER.'js/externals/ace');
        // tmp js
        if (!file_exists(Config::HOME_FOLDER.'js/tmp')) {
            shell_exec('mkdir -p '.Config::HOME_FOLDER.'js/tmp && chmod 2774 '.Config::HOME_FOLDER.'js/tmp');
        }

        if (!file_exists(Config::HOME_FOLDER.'originals')) {
            // git do not copy empty folder, need to recreate them, it is index.html that is checked to know if the installcript has already been executed
            shell_exec('mkdir -p '.Config::HOME_FOLDER.'originals && chmod 2774 '.Config::HOME_FOLDER.'originals');
            shell_exec('touch '.Config::HOME_FOLDER.'originals/index.html');
        }
        
        // log
        if (!file_exists(Config::HOME_FOLDER.'log')) {
            shell_exec('mkdir -p '.Config::HOME_FOLDER.'log && chmod 2774 '.Config::HOME_FOLDER.'log');
        }

        // css
        if (!file_exists(Config::HOME_FOLDER.'css')) shell_exec('mkdir -p '.Config::HOME_FOLDER.'css && chmod 2774 '.Config::HOME_FOLDER.'css');
        if (!file_exists(Config::HOME_FOLDER.'css/gz')) shell_exec('mkdir -p '.Config::HOME_FOLDER.'css/gz');
        if (!file_exists(Config::HOME_FOLDER.'css/theme')) shell_exec('mkdir -p '.Config::HOME_FOLDER.'css/theme');
        
        // make constants
        shell_exec('php '.$data['HELPHP_FOLDER'].'utils/constants.php '.Config::HOME_FOLDER);

        include_once($data['HELPHP_FOLDER'].'/autoload.php');

        \helPHP\libs\Utils::error_log('php '.$data['HELPHP_FOLDER'].'utils/constants.php '.Config::HOME_FOLDER);
        // will create the file with storage status for the javascript
        // include_once($data['HELPHP_FOLDER'].'/libs/Ajax.php');
        \helPHP\libs\Ajax::update_storage_status();

        // create admin usercreate_account
        global $USER;
        $USER->create_account([
            'login'     => $admin_user,
            'password'  => $admin_pass,
            'email'     => 'fakeemail@helPHP.com',
        ]);

        global $DB_CENTRAL;
        $DB_CENTRAL->query('UPDATE '.$DB_CENTRAL->table('users_data').' SET active=1, admin=1 WHERE id=1');

        // detect presence of a theme file and install it
        $path_files = Config::HOME_FOLDER.'css/theme/';
        $files = $FS->recurse_ls($path_files, '', false, true)['files'];
        if ($files) {
            global $FS;
            $csseditor = new \helPHP\modules\csseditor\admin\Csseditor();
            foreach($files as $filename){
                $noext = $FS->get_file_name_noext($filename);
                $t = explode('¤', $noext);
                $name = $t[0];
                $type = $t[1] == 'admin' ? 1 : 0;
                $csseditor->import(['file'=>$filename, 'admin'=>$type, 'name'=>$name]);
            }
        }

        foreach(Config::MODULES_LIST as $name => $module){
            set_time_limit(20);
            
            // installing css public and admin
            $css_paths = [Config::HELPHP_FOLDER.'modules/'.$name.'/admin/'.$name.'.css', Config::HELPHP_FOLDER.'modules/'.$name.'/public/'.$name.'.css'];
            foreach($css_paths as $path){
                if (!file_exists($path)) continue;
                $admin = str_contains($path, 'admin') ? 1 : 0;
                $md5 = \md5_file($path);
                $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="module", path=?, md5=?, admin='.$admin;
                $DB->prepared_query($q, 'ss', [$path, $md5]);
                $id_source = $DB->last_insert_id();
                \helPHP\modules\csseditor\admin\Csseditor::import_css_source($path, $id_source, true);
            }
        }

        // echo 'install success';

    }

}

?>

<html>
    <head>
        <title>Install helPHP instance</title>
        <link href="./public/install/install.css" type="text/css" rel="stylesheet">
        <style>
            body {
                display: grid;
                align-content: center;
                justify-content: center;
                grid-template-columns: 60vw;
                font-family: sans;
            }
            .welcome {
                text-align: center;
                margin: 10px;
            }
            .block {
                background: #eee;
                padding: 10px;
                border-radius: 5px;
                border-top: 2px solid #3a80bd;
            }
            label {
                vertical-align: middle;
                font-weight: bold;
            }
            input[type="text"] {
                vertical-align: middle;
                padding: 6px 8px;
                border-radius: 5px;
                border-style: solid;
                border-width: 1px;
                font-size: 1em;
            }
            .form {
                display: grid;
                grid-gap: 40px;
                margin-top: 40px;
            }

            .block.db_root, .block.admin_credentials {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 5px;
            }
            .info.db_root, .info.admin_credentials {
                grid-column: 1 / 3;
            }
            #db_root_login, #admin_user, #db_root_login_slave, #db_root_login_central {
                grid-row: 4;
            }
            .msg_error {
                color: #bd3a3a;
                grid-column: 1/3;
            }
            .block.db_main, .block.site, .block.helphp_folder {
                display: grid;
                gap: 5px;
            }
            .block.other {
                display: grid;
                grid-template-columns: auto auto;
                justify-content: left;
                gap: 5px;
            }
            input[type="checkbox"] {
                margin: 0;
                vertical-align: middle;
                width: 20px;
                height: 20px;
            }
            .fields {
                flex-direction: column;
                gap: 5px;
            }
            .fields_db_root {
                margin: 20px 0px;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 5px;
            }
            .fields_db_root .info {
                grid-column: 1/3;
            }
            button {
                height: 40px;
                background: #3a80bd;
                border-radius: 5px;
                cursor: pointer;
                border: 2px solid #3a80bd;
                color: #eee;
                font-weight: bold;
                font-size: 1em;
            }
            button:hover {
                background: #eee;
                color: #000;
            }
        </style>
        <script language="javascript">
            function init(){

                let toggles = [
                    'MASTER_SLAVE_MODE',
                    'DB_CENTRAL',
                    'DB_JOBS',
                    'REDIS'
                ];

                toggles.forEach( (name) => {
                    let input = document.getElementById(name);
                    input.addEventListener('change', toggle);

                    toggle({target: input});
                });

            }

            function toggle(evt){
                let target = evt.target;
                let state = target.checked;
                let container = document.getElementById(target.id + '_fields');
                if (container) container.style.display = (state) ? 'flex' : 'none';
            }
        </script>
    </head>
    <body>
        <div class="logo">LOGO</div>
        <div class="welcome">Welcome to the installation of your helPHP instance</div>
        <div class="info">
            To start we need some crucial information. Please enter the required data and enjoy.
        </div>
        <form class="form" action="./" method="POST" enctype="multipart/form-data" >
            
            <!-- helPHP folder -->
            <div class="block helphp_folder">

                <label for="HELPHP_FOLDER">Path to the helPHP folder</label>
                
                <?php if (isset($helphp_folder_found) && !$helphp_folder_found) echo '<div class="msg_error folder_not_found">helPHP libs not found at '.$helphp_folder.'</div>'; ?>
                <?php if (isset($lib_db_not_found) && !$lib_db_not_found) echo '<div class="msg_error db_not_found">Can\'t find the lib DB.php in the folder '.$helphp_folder.'libs/.</div>'; ?>
                
                <input type="text" name="HELPHP_FOLDER" value="<?php echo $data['HELPHP_FOLDER']; ?>" id="HELPHP_FOLDER">

            </div>

            <!-- db root -->
            <div class="block db_root">

                <div class="info db_root">User root to create the database, will not be saved and used only during the installation.</div>

                <div class="msg_error db_root"><?php if (isset($missing_root) && $missing_root) echo 'Missing root username or password';?></div>

                <label for="db_root_login">Username</label>
                <input type="text" name="db_root_login" id="db_root_login" value="<?php echo $db_root_user; ?>">

                <label for="db_root_password">Password</label>
                <input type="text" name="db_root_password" value="<?php echo $db_root_pass; ?>" id="db_root_password">

            </div>

            <div class="block db_main">

                <!-- explain block -->
                <div class="info db_main">Database information to create the user and the base</div>

                <?php 
                    // error messages
                    if (isset($missing_db) && $missing_db) echo '<div class="msg_error db">Please fill every field below</div>';

                    // inputs
                    $field_to_display = ['DB_HOST', 'DB_BASE', 'DB_USER', 'DB_PASS', 'DB_TABLE_PREFIX'];
                    foreach($field_to_display as $name){
                        $t = explode('_', $name);
                        echo '<label for="'.$name.'">'.ucfirst(strtolower(array_pop($t))).'</label>';
                        echo '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$data[$name].'">';
                    }
                ?>

            </div>

            <!-- site information -->
            <div class="block site">
                
                <label for="SITE_NAME">Website name</label>
                <input type="text" name="SITE_NAME" value="<?php echo $data['SITE_NAME']; ?>" id="SITE_NAME">
                
                <label for="DOMAIN">Domain name</label>
                <input type="text" name="DOMAIN" value="<?php echo $data['DOMAIN']; ?>" id="DOMAIN">

                <label for="SITE_FOLDER">Website folder (append it to the url)</label>
                <input type="text" name="SITE_FOLDER" value="<?php echo $data['SITE_FOLDER']; ?>" id="SITE_FOLDER">

                <label for="ADMIN_FOLDER">Admin folder name</label>
                <input type="text" name="ADMIN_FOLDER" value="<?php echo $data['ADMIN_FOLDER']; ?>" id="ADMIN_FOLDER">

            </div>

            <div class="block db_master_slave">

                <!-- explain block -->
                <div class="info db_master_slave">Database information about master slave mode</div>

                <!-- checkbox for activate field -->
                <label for="MASTER_SLAVE_MODE">Activate master/slave mode</label>
                <input type="checkbox" name="MASTER_SLAVE_MODE" id="MASTER_SLAVE_MODE" value=1 <?php echo $data['MASTER_SLAVE_MODE'] ? 'checked' : '' ?>>

                <div class="fields db_master_slave" id="MASTER_SLAVE_MODE_fields">


                    <div class="fields_db_root db_master_slave">

                        <div class="info root_slave">User root to access the slave database, will not be saved and used only during the installation.</div>

                        <div class="msg_error db_slave"><?php if (isset($missing_root_slave) && $missing_root_slave) echo 'Missing root username or password'; ?></div>

                        <label for="db_root_login_slave">Username</label>
                        <input type="text" name="db_root_login_slave" id="db_root_login_slave" value="<?php echo $db_root_user_slave; ?>">

                        <label for="db_root_password_slave">Password</label>
                        <input type="text" name="db_root_password_slave" value="<?php echo $db_root_password_slave; ?>" id="db_root_password_slave">
                    </div>

                    <?php
                        // error messages
                        if (isset($missing_db_slave) && $missing_db_slave) echo '<div class="msg_error db">Please fill every field below</div>';

                        // inputs
                        $field_to_display = ['DB_SLAVE_HOST', 'DB_SLAVE_BASE', 'DB_SLAVE_USER', 'DB_SLAVE_PASS'];
                        foreach($field_to_display as $name){
                            $t = explode('_', $name);
                            echo '<label for="'.$name.'">'.ucfirst(strtolower(array_pop($t))).'</label>';
                            echo '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$data[$name].'">';
                        }
                    ?>

                </div>

            </div>


            <div class="block db_central">

                <!-- explain block -->
                <div class="info db_central">Database information about central mode</div>

                <!-- checkbox for activate field -->
                <label for="DB_CENTRAL">Activate central mode</label>
                <input type="checkbox" name="DB_CENTRAL" id="DB_CENTRAL" value=1 <?php echo $data['DB_CENTRAL'] ? 'checked' : '' ?>>

                <div class="fields db_central" id="DB_CENTRAL_fields">

                    <div class="fields_db_root db_central">
                        <!-- explain block -->
                        <div class="info root_central">User root to access the central database, will not be saved and used only during the installation.</div>

                        <div class="msg_error db_central"><?php if (isset($missing_root_central) && $missing_root_central) echo 'Missing root username or password'; ?></div>

                        <!-- inputs -->
                        <label for="db_root_login_central">Username</label>
                        <input type="text" name="db_root_login_central" id="db_root_login_central" value="<?php echo $db_root_user_central; ?>">

                        <label for="db_root_password_central">Password</label>
                        <input type="text" name="db_root_password_central" value="<?php echo $db_root_password_central; ?>" id="db_root_password_central">
                    </div>

                    <?php
                    
                        // error messages
                        if (isset($missing_db_central) && $missing_db_central) echo '<div class="msg_error db">Please fill every field below</div>';

                        // inputs
                        $field_to_display = ['DB_CENTRAL_HOST', 'DB_CENTRAL_BASE', 'DB_CENTRAL_USER', 'DB_CENTRAL_PASS'];
                        foreach($field_to_display as $name){
                            $t = explode('_', $name);
                            echo '<label for="'.$name.'">'.ucfirst(strtolower(array_pop($t))).'</label>';
                            echo '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$data[$name].'">';
                        }
                    ?>

                </div>
                
            </div>

            <div class="block db_jobs">

                <!-- explain block -->
                <div class="info db_jobs">Database information about jobs base</div>

                <!-- checkbox for activate field -->
                <label for="DB_JOBS">Activate jobs base</label>
                <input type="checkbox" name="DB_JOBS" id="DB_JOBS" value=1 <?php echo $data['DB_JOBS'] ? 'checked' : '' ?>>

                <div class="fields db_jobs" id="DB_JOBS_fields">

                    <?php 
                        // error messages
                        if (isset($missing_db_jobs) && $missing_db_jobs) echo '<div class="msg_error db">Please fill every field below</div>';

                        // inputs
                        $field_to_display = ['DB_JOBS_HOST', 'DB_JOBS_BASE', 'DB_JOBS_USER', 'DB_JOBS_PASS'];
                        foreach($field_to_display as $name){
                            $t = explode('_', $name);
                            echo '<label for="'.$name.'">'.ucfirst(strtolower(array_pop($t))).'</label>';
                            echo '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$data[$name].'">';
                        }
                    ?>

                </div>

                
            </div>

            <div class="block redis">

                <!-- explain block -->
                <div class="info redis">Redis information</div>

                <label for="REDIS">Activate Redis</label>
                <input type="checkbox" name="REDIS" id="REDIS" value=1 <?php echo $data['REDIS'] ? 'checked' : '' ?>>

                <div class="fields redis" id="REDIS_fields">

                    <?php
                        $field_to_display = ['REDIS_HOST', 'REDIS_PORT'];
                        foreach($field_to_display as $name){
                            $t = explode('_', $name);
                            echo '<label for="'.$name.'">'.ucfirst(strtolower(array_pop($t))).'</label>';
                            echo '<input type="text" name="'.$name.'" id="'.$name.'" value="'.$data[$name].'">';
                        }
                    ?>

                </div>

            </div>

            <div class="block other">

                <label for="API_MODE">Activate API</label>
                <input type="checkbox" name="API_MODE" id="API_MODE" value=1 <?php echo $data['API_MODE'] ? 'checked' : '' ?>>

                <label for="CLUSTER_MODE">In a cluster</label>
                <input type="checkbox" name="CLUSTER_MODE" id="CLUSTER_MODE" value=1 <?php echo $data['CLUSTER_MODE'] ? 'checked' : '' ?>>

            </div>

            <div class="block admin_credentials">
                <!-- explain block -->
                <div class="info admin_credentials">Admin credentials to connect to your instance. You will need those credentials to connect after the installation.</div>

                <div class="msg_error admin_credentials">
                    <?php if (isset($missing_admin) && $missing_admin) echo 'Missing admin username or password<br>'; ?>
                    <?php if (isset($short_password) && $short_password) echo 'Password too short, minimum '.$min_size.' characters.'; ?>
                </div>

                <label for="admin_user">Username</label>
                <input type="text" name="admin_user" id="admin_user" value="<?php echo $admin_user; ?>">

                <label for="admin_pass">Password</label>
                <input type="text" name="admin_pass" id="admin_pass" value="<?php echo $admin_pass; ?>">
                
            </div>

            <button type="submit" name="action" value="install">INSTALL</button>

        </form>

        <script language="javascript">
            init();
        </script>
    </body>
</html>