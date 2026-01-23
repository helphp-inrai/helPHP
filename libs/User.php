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

namespace helPHP\libs;
/**
 * @class User 
 * 
 * Handles user management, authentication, and session state for the application.
 * Provides methods for account creation, activation, login, logout, group and module access control,
 * error/message handling, and connection/session management.
 * 
 * This class manage the $USER global object and most of its methods can be used by the User module.
 *
 * @package helPHP\libs
 */
class User
{
    const action_identifier = 'user_action';

    const connect = 'connect';
    const disconnect = 'disconnect';
    const create = 'create';
    const activate = 'activate';

    const session_try_identifier = 'connection_try';
    const session_connection_data = 'connection_data';
    const session_connection_state = 'connection_state';
    const session_user_id = 'id';
    const session_user_login = 'login';

    const state_ban = -1;
    const state_not_logged = 0;
    const state_logged = 1;

    /**
     * @var array $error_list List of error messages displayed in popups.
     */
    public $error_list = [];

    /**
     * @var array $message_list List of informative messages displayed in popups.
     */
    public $message_list = [];

    /**
     * @var bool $reload_after_message If true, the page will be reloaded when the popup is closed.
     */
    public $reload_after_message = false;

    /**
     * @var string $login The user's login name.
     */
    public $login = '';

    /**
     * @var mixed $user_data User data array or object. will be used to store details.
     */
    public $user_data = 0;

    /**
     * @var int $id The user's unique ID.
     */
    public $id = 0;

    /**
     * @var bool $admin Whether the user has admin privileges.
     */
    public $admin = false;

    /**
     * @var int $connection_state The user's connection state (banned, not logged, logged).
     */
    public $connection_state;

    /**
     * @var bool|null $account_created Whether the account was successfully created.
     */
    public $account_created = null;

    /**
     * @var string|null $connection_hash The hash identifying the user's session.
     */
    public $connection_hash = null;

    /**
     * @var int $nb_attempt Number of connection attempts.
     */
    public $nb_attempt = 0;

    /**
     * @var mixed $connection_data Connection data array or object.
     */
    private $connection_data = null;

    /**
     * @var int $id_activated ID of the activated user.
     */
    public $id_activated = 0;

    /**
     * @var int $force_reload_delay Delay before forcing reload after message.
     */
    public $force_reload_delay = 0;
    
    public function __construct() {
        $this->check_connection();
    }
    /**
     * Creates the global instance $USER of the User class.
     *
     * @return void
     */
    public static function create_instance()
    {
        global $USER;

        if (!$USER) {
            $USER = new User();
        }
    }
    /**
     * Creates a new user account.
     *
     * Validates login, email, password, and VAT ID, checks for duplicates,
     * and inserts the new user into the database.
     * it will record the account in $DB_central which can be $DB or another one depending your DB config 
     *
     * @param array $data User registration data.
     * @return bool True if account was created, false otherwise.
     */
    public function create_account($data)
    {
        global $DB, $CRYPT, $LANG, $CONFIG;

        $this->account_created = false;

        $login = isset($data['login']) ? trim($data['login']) : trim($data['users_data-login']);
        $email = isset($data['email']) ? trim($data['email']) : trim($data['users_data-email']);
        $password = isset($data['password']) ? trim($data['password']) : trim($data['users_data-password']);

        if ($login == '' || $password == '' || $login == false || $password == false){
            array_push($this->error_list, 'LOGIN OR PASSWORD EMPTY');
            Utils::error_log('LOGIN OR PASSWORD EMPTY');
        }
        
        $data['users_data-vat_id'] = isset($data['users_data-vat_id']) ? $data['users_data-vat_id'] : '';
        $vat_id = isset($data['vat_id']) ? trim($data['vat_id']) : trim($data['users_data-vat_id']);

        // check that the login contains only authorized characters
        if ($CONFIG::USERNAME_VALID_STRING !== null) {
            if (!Utils::str_contains_only($login, $CONFIG::USERNAME_VALID_STRING)) {
                array_push($this->error_list, ['key'=>'invalid_username' , 'replace'=>$login]);
            }
        }

        // check the validity of the mail
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            array_push($this->error_list, 'invalid_email');
        }

        // check the minimum password length

        $min_size = $CONFIG::USERPASSWORD_MINIMUM_LENGTH == null ? 6 : $CONFIG::USERPASSWORD_MINIMUM_LENGTH;

        if (strlen($password) < $min_size) {
            array_push($this->error_list, 'invalid_password');
        }

        // check that the login and email are not already used
        $test_account = $this->account_exists($login, $email);

        if (is_array($test_account)) {
            $test_account['login'] = strtolower($test_account['login']);
            $test_account['email'] = strtolower($test_account['email']);

            if ($test_account['login'] == strtolower($login)) {
                array_push($this->error_list, ['key'=>'login_used' , 'replace'=>$login]);
            }
            if ($test_account['email'] == strtolower($email)) {
                array_push($this->error_list, ['key'=>'email_used' , 'replace'=>$email]);
            }
        }

        // account creation if there is no error
        if (sizeof($this->error_list) == 0) {

            global $DB_CENTRAL;
            
            $q = 'INSERT INTO '.$DB_CENTRAL->table('users_data').' SET ';
            $q.= ' login=? ';
            $q.= ', email=? ';
            $q.= ', password_hash=? ';
            $q.= ', language=? ';
            $hash = $CRYPT->create_password_hash($password);
            $values=array($login , $email , $hash , $LANG->current_language);
            $params='ssss';
            if ($CONFIG::FIRST_USE==true) {
                $q.= ', active=?, admin=?, lastname=? ,firstname=?';
                $params='ssssiiss';
                array_push($values, 1, 1, trim($data['users_data-lastname']), trim($data['users_data-firstname']), $data['auto']);
            }
            $test = $DB_CENTRAL->prepared_query($q, $params, $values);

            if ($test) {
                if ($CONFIG::FIRST_USE==false) {
                    array_push($this->message_list, 'account_created');
                } else {
                    array_push($this->message_list, 'first_account_created');
                }
                $this->account_created = true;
                $this->id = $DB_CENTRAL->last_insert_id();
            } else {
                array_push($this->error_list, 'database_error');
            }
            

            // for the special process like forum account creation / revision account
            if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
                $temp = $_POST;
                $_POST = [];
                $_POST['mode'] = 'create';
                $_POST['login'] = $login;
                $_POST['password'] = $password;
                $_POST['email'] = $email;
                $_POST['id'] = $this->id;
                $_POST['firstname'] = isset($data['users_data-firstname']) ? trim($data['users_data-firstname']) : '';
                $_POST['lastname'] = isset($data['users_data-lastname']) ? trim($data['users_data-lastname']) : '';
                $_POST['entity'] = isset($data['users_data-entity']) ? trim($data['users_data-entity']) : '';
                $_POST['newsletter'] = (isset($temp['newsletter']) && $temp['newsletter']) ? $temp['newsletter'] : false;
                include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
                $_POST = $temp;
            }
        }

        return $this->account_created;
    }
    /**
     * Checks the current user connection/session and updates user state.
     * and will ban the navigatorby its user-agent after 5 tries.
     *
     * @param string|false $connection_hash Optional. 
     * @return void
     */
    public function check_connection($connection_hash = false)
    {
        global $DB,$CONFIG,$DB_CENTRAL;
        
        $md5_ua=md5($this->get_user_agent());

        if ($connection_hash !== false) {
            $this->connection_hash = $connection_hash;
        } else {
            $this->connection_hash = (isset($_SESSION[User::session_connection_data])) ? $_SESSION[User::session_connection_data] : null;
        }

        if ($this->connection_hash !== null) {
            $ip = Utils::get_ip();
            $user_agent = $this->get_user_agent();
            
            $q = 'SELECT DISTINCT *, unix_timestamp(con.starttime) AS time ,con.id AS id_connection FROM '.$DB_CENTRAL->table('users_connections').' con, '.$DB_CENTRAL->table('users_data').' dat WHERE ';
            $q.= 'con.hash=? AND con.id_user=dat.id AND con.ip=? AND con.useragent_hash=?';
                
            $user_connection_data = $DB_CENTRAL->prepared_query_line($q, 'sss', [$this->connection_hash, $ip, md5($user_agent)]);
            if (is_array($user_connection_data)) {
                $user_data = $DB_CENTRAL->prepared_query_line('SELECT DISTINCT * FROM '.$DB_CENTRAL->table('users_data').' WHERE id=?', 's', array($user_connection_data['id_user']));
                $this->user_data = $user_data;
                $this->id = $user_data['id'];
                $this->login = $user_data['login'];
                $this->admin = $user_data['admin'] > 0;
                $this->connection_state = User::state_logged;
                $this->connection_data = $user_connection_data;
                $DB_CENTRAL->query('UPDATE '.$DB_CENTRAL->table('users_connections').' SET lasttime="'.Datetime::mysql_date().'" WHERE id='.$user_connection_data['id_connection']);
            } else {
                Utils::error_log('auto connect Error : no data in database -> reset');
                $this->reset();
            }
        } else {
            $this->login = '';
            $this->id = 0;
            $this->user_data = null;
            $this->admin = false;
            $this->connection_state = User::state_not_logged;
            $IP = Utils::get_ip();
            // check if the ban has been removed by an admin
            $unbanned = $DB->prepared_query_value('SELECT COUNT(*) FROM '.$DB->table('connection_try_logs').' WHERE ip = ? and user_agent_hash = ? and locked = 2', 'ss', array($IP, $md5_ua));
            if ($unbanned > 0){
                // put the connection try to 0
                $DB->prepared_query('DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('connection_try_logs').' WHERE ip = ? and user_agent_hash = ?', 'ss', array($IP, $md5_ua));
                $_SESSION[User::session_try_identifier] = 0;
            }
            $this->nb_attempt = $DB->prepared_query_value('SELECT COUNT(*) FROM '.$DB->table('connection_try_logs').' WHERE ip = ? and user_agent_hash = ?', 'ss', array($IP, $md5_ua));
            // get the session number 
            $try_count = (isset($_SESSION[User::session_try_identifier])) ? $_SESSION[User::session_try_identifier] : 0;
            if ($IP == 'NoIp' || $this->nb_attempt >= $CONFIG::MAX_USER_CONNECTION_ATTEMPTS || $try_count >= $CONFIG::MAX_USER_CONNECTION_ATTEMPTS) {
                $this->connection_state = User::state_ban;
            }
        }
    }
    /**
     * Returns the list of group IDs the user is allowed to access.
     *
     * @param int $id Optional. User ID to check (defaults to current user).
     * @return array List of group IDs.
     */
    public function allowed_groups($id = 0)
    {
        global $DB_CENTRAL;
        if ($id == 0) {
            $id = intval($this->id);
        }

        if ($this->admin) {
            // admin user, access to all groups
            $groups = $DB_CENTRAL->query_list('SELECT DISTINCT id FROM '.$DB_CENTRAL->table('group_data'));
            if (!is_array($groups)) {
                $groups = [];
            }
            array_push($groups, 0);
        } else {
            $groups = $DB_CENTRAL->query_list('SELECT DISTINCT id_group_data FROM '.$DB_CENTRAL->table('group_users').' WHERE id_users_data='.$id);

            if (!is_array($groups)) {
                $groups = [];
            }

        }
        return $groups;
    }
    
    /**
     * Returns the list of admin modules accessible for the user's group(s).
     *
     * @param int $id Optional. User ID to check (defaults to current user).
     * @return array List of admin module names.
     */
    public function allowed_admin_modules($id = 0)
    {
        global $DB,$CONFIG;
        $modules = [];

        if ($this->admin == false) {
            $groups = $this->allowed_groups($id);
            //~ Utils::error_log($groups);
            if (count($groups) > 0) {
                $modules = $DB->query_list('SELECT DISTINCT module FROM '.$DB->table('group_modules').' WHERE admin>0 AND id_group_data IN('.implode(',', $groups).') ORDER BY module');
                if (count($modules) > 0){
                    $basics_modules = ['core', 'burger', 'languages', 'connection', 'hierarchy', 'media', 'tabs', 'preview'];
                    $modules = array_merge($modules, $basics_modules);
                }
            }
        } else {
            $modules = array_keys($CONFIG::MODULES_LIST);
        }
        return $modules;
    }
    /**
     * Returns the list of registered modules accessible for the user's group(s).
     *
     * @param int $id Optional. User ID to check (defaults to current user).
     * @return array List of registered module names.
     */
    public function allowed_registered_modules($id = 0)
    {
        global $DB;

        $groups = $this->allowed_groups($id);

        $modules = [];
        if (count($groups)>0) {
            $modules = $DB->query_list('SELECT DISTINCT module FROM '.$DB->table('group_modules').' WHERE registered>0 AND id_group_data IN('.implode(',', $groups).') ORDER BY module');
            if (!is_array($modules)) {
                $modules = [];
            }
        }
        return $modules;
    }
    
    /**
     * Returns the list of public modules accessible to any user.
     *
     * @return array List of public module names.
     */
    public function allowed_public_modules()
    {
        global $DB,$CONFIG;

        $assigned_modules = $DB->query_list('SELECT DISTINCT module FROM '.$DB->table('group_modules').' WHERE registered=1 ORDER BY module');

        if (!$assigned_modules) {
            $assigned_modules = [];
        }

        $public_modules = [];
        foreach ($CONFIG::MODULES_LIST as $moduleName => $module_data) {
            if (!in_array($moduleName, $assigned_modules)) {
                array_push($public_modules, $moduleName);
            }
        }
        if($CONFIG::DEVMODE){
            array_push($public_modules, 'test');
        }
        sort($public_modules);
        return $public_modules;
    }
    
    /**
     * Returns the list of modules with restricted editing for the user's group(s).
     *
     * @param int $id_user_data Optional. User ID to check (defaults to current user).
     * @return array List of restricted module names.
     */
    public function restricted_edit_modules($id_user_data = 0)
    {
        global $DB;
        $modules = [];
        if ($this->admin == false) {
            $groups = $this->allowed_groups($id_user_data);
            if (count($groups)>0) {
                $modules = $DB->query_list('SELECT DISTINCT module FROM '.$DB->table('group_modules').' WHERE no_edit>0 AND id_group_data IN('.implode(',', $groups).') ORDER BY module');
                if (!is_array($modules)) {
                    $modules = [] ;
                }
            }
        }
        return $modules;
    }
    /**
     * Generates an activation code for the user account.
     *
     * @param int $id User ID.
     * @return string|true|null Activation code, true if already active, or null on error.
     */
    public function generate_activation_code($id)
    {
        global $DB_CENTRAL;
        $data = $DB_CENTRAL->prepared_query_line('SELECT DISTINCT login,email,active FROM '.$DB_CENTRAL->table('users_data').' WHERE id=?', 'i', [$id]);
        if (is_array($data)) {
            if ($data['active'] == 1) {
                return true;
            } else {
                $code = $id.md5($data['login'].$data['email']);
                $DB_CENTRAL->prepared_query('UPDATE '.$DB_CENTRAL->table('users_data').' SET activation_code=? WHERE id=?', 'si', [$code,$id]);

                return $code;
            }
        } else {
            Utils::error_log('Erreur de création du code d\'activation, failed request :');
            Utils::error_log('SELECT DISTINCT login,email,active FROM '.$DB_CENTRAL->table('users_data').' WHERE id='.$id);
        }

        return null;
    }
    /**
     * Handles user connection actions (create, activate, connect, disconnect) based on provided data.
     * 
     * @param array|null $connection_data Connection data (from POST/REQUEST).
     * @return int The user's connection state.
     */
    public function check_connection_data($connection_data = null)
    {
        global $DB,$CRYPT,$CONFIG,$DB_CENTRAL;
        $ua=$this->get_user_agent();
        $md5_ua=md5($ua);

        if (!is_array($connection_data)) {
            if (isset($_REQUEST['users']) && str_contains($_REQUEST['users'],'|')){
                $_REQUEST['users'] = str_replace('|','&',$_REQUEST['users']);
                if (strpos($_REQUEST['users'], '=') !== false) {
                    parse_str($_REQUEST['users'], $_REQUEST);
                }
                $_REQUEST['users'] = '';
            }
            $connection_data = $_REQUEST;
        }

        // action retrieved from posted data
        $action = isset($connection_data[User::action_identifier]) ? $connection_data[User::action_identifier] : '';

        if (!$action) {
            if (isset($connection_data[User::activate])) {
                $action = User::activate;
            }
        }

        switch ($action) {

            case User::create:
                
                if ($this->create_account($connection_data)) {
                    if ($CONFIG::FIRST_USE == true) {
                        $this->reload_after_message = $CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER;
                    } else {
                        $this->reload_after_message = $CONFIG::BASE_URL;
                    }
                }

            break;

            case User::activate:

                $data = $DB_CENTRAL->prepared_query_line('SELECT DISTINCT id, active, email, activation_code, login FROM '.$DB_CENTRAL->table('users_data').' WHERE login=?', 's', [$connection_data['login']]);

                if (is_array($data) && !$data['active'] && $data['activation_code'] == $connection_data[User::activate]) {
                    $DB_CENTRAL->prepared_query('UPDATE '.$DB_CENTRAL->table('users_data').' SET activation_code="" , active=1 WHERE id=?', 'i', [$data['id']]);
                    
                    if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
                        $t = $_POST;
                        $_POST = [];
                        $_POST['mode'] = 'activate';
                        $_POST['login'] = $data['login'];
                        $_POST['email'] = $data['email'];
                        $_POST['id'] = $data['id'];
                        include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
                        $_POST = $t;
                    } else {
                        Utils::error_log('error '.$CONFIG::HOME_FOLDER.'public/users/user_extend.php not found');
                    }

                    array_push($this->message_list, 'activation_success');
                    $this->id_activated = $data['id'];
                    
                } elseif (!is_array($data) || !$data['active']) {
                    array_push($this->error_list, 'invalid_activation_key');
                } else {
                    array_push($this->error_list, 'account_already_active');
                }

                $this->reload_after_message = $CONFIG::BASE_URL;

            break;


            case User::connect:
                if ($this->connection_state == User::state_logged) {
                    array_push($this->error_list, 'connection_active');
                    // $this->reload_after_message = $CONFIG::BASE_URL;
                    break;
                }
                
                $connection_data['password'] = trim($connection_data['password']);

                if ($connection_data['login'] == '' || $connection_data['password'] == '') {
                    array_push($this->error_list, 'bad_credentials');
                }

                $user_data = $DB_CENTRAL->prepared_query_line('SELECT DISTINCT * FROM '.$DB_CENTRAL->table('users_data').' WHERE login=?', 's', array($connection_data['login']));
                if (is_array($user_data)) {
                    if (!$user_data['active'] && !isset($connection_data['autocon'])) {
                        array_push($this->error_list, 'account_not_activated');
                    }
                } else {
                    array_push($this->error_list, 'bad_credentials');
                }

                // get the maximum number of connection attempts from the configuration class
                $max_connection_count = $CONFIG::MAX_USER_CONNECTION_ATTEMPTS;
                
                $IP = Utils::get_ip();
                $date_time = date('Y-m-d H:i:s', time());
                $date_banlimit = date('Y-m-d H:i:s', time() - ($CONFIG::CONNECTION_TRY_BAN_HOURS*60*60));
                
                //removal of old connection attempts
                $DB->query('DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('connection_try_logs').' WHERE time < "'.$date_banlimit.'"');
                $this->nb_attempt = $DB->prepared_query_value('SELECT COUNT(*) FROM '.$DB->table('connection_try_logs').' WHERE ip = ? and user_agent_hash = ?', 'ss', array($IP, $md5_ua));
                if ($this->nb_attempt < $max_connection_count) {
                    //add a record for the next test ...
                    $DB->prepared_query('INSERT INTO '.$DB->table('connection_try_logs').' SET ip = ? , user_agent = ?, user_agent_hash = ?, login = ?', 'ssss', array($IP, $ua, $md5_ua,$connection_data['login']));
                    $nbcontest = true;
                    $this->nb_attempt++;
                }else{
                    $nbcontest = false;
                }
                
                
                if ($CONFIG::API_MODE && !isset($_SESSION[User::session_try_identifier])){
                    $try_count = 0;
                }else{
                    // retrieves the number of connection attempts stored in the session
                    $try_count = (isset($_SESSION[User::session_try_identifier])) ? $_SESSION[User::session_try_identifier] : 0;
                    $nbcontest = ($try_count < $max_connection_count && $this->nb_attempt < $max_connection_count);
                }

                if (!$nbcontest) {
                    array_push($this->error_list, 'account_banned');

                    $this->connection_state = User::state_ban;

                    $_SESSION[User::session_connection_state] = $this->connection_state;

                    $DB->prepared_query('UPDATE '.$DB->table('connection_try_logs').' SET locked=1 WHERE ip = ? and user_agent_hash = ?', 'ss', array($IP, $md5_ua));
                }

                if (count($this->error_list) == 0){
                    if (is_array($user_data) && $CRYPT->verify_password_hash($connection_data['password'], $user_data['password_hash'])) {
                        $DB->prepared_query('DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('connection_try_logs').' WHERE ip = ? and user_agent_hash = ?', 'ss', array($IP, $md5_ua));

                        // connection monoposte check
                        if ($CONFIG::MONOPOSTE){
                            $q = 'DELETE LOW_PRIORITY IGNORE FROM '.$DB_CENTRAL->table('users_connections').' WHERE id_user=?';
                            $DB->prepared_query($q,'i',[$user_data['id']]);
                        }

                        $this->nb_attempt = 0;

                        // updating class data
                        $this->id = $user_data['id'];
                        $this->user_data = $user_data;
                        $this->login = $user_data['login'];

                        $this->connection_state = User::state_logged;

                        // creation of the connection identification hash
                        $connection_hash = $CRYPT->create_password_hash(md5($user_data['login'].$this->get_user_agent().$IP));

                        $DB_CENTRAL->prepared_query('INSERT INTO '.$DB_CENTRAL->table('users_connections').' SET id_user=? , hash=? , useragent=? , ip=?, useragent_hash=?', 'issss', array($this->id , $connection_hash , $this->get_user_agent() , $IP, $md5_ua ));

                        $this->connection_hash=$connection_hash;
                        $_SESSION[User::session_connection_data] = $connection_hash;
                        $_SESSION[User::session_connection_state] = $this->connection_state;
                        
                    } else {
                        array_push($this->error_list, 'bad_credentials');
                    }
                }
                $_SESSION[User::session_try_identifier] = $this->nb_attempt;
                // $this->reload_after_message = true;

            break;

            case User::disconnect:

                if ($this->connection_state == User::state_logged) {
                    $this->reset();
                } else {
                    $this->reset();
                    array_push($this->error_list, 'no_connection');
                }

                $this->reload_after_message = true;

            break;
        }

        return $this->connection_state;
    }
    /**
     * Returns the current error list.
     *
     * @return array Error messages.
     */
    public function get_error_list()
    {
        return $this->error_list;
    }
    /**
     * Returns the current message list.
     *
     * @return array Informative messages.
     */
    public function get_message_list()
    {
        return $this->message_list;
    }

    /**
     * Resets the user session and connection state.
     *
     * @param bool $bloquer If true, adds a "blocked" error message.
     * @return void
     */
    public function reset($bloquer = false)
    {
        global $DB,$CONFIG;

        if ($CONFIG::API_MODE){
            if(isset($data['connection_hash'])){
                $this->connection_hash=$data['connection_hash'];
            }else{
                $connection_hash = (isset($_SESSION[User::session_connection_data]))?$_SESSION[User::session_connection_data]:null;
            }
        }else{
            $connection_hash = (isset($_SESSION[User::session_connection_data]))?$_SESSION[User::session_connection_data]:null;
        }
        
        if ($connection_hash!==null){
            $DB->prepared_query('DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('users_connections').' WHERE hash=?', 's', array($connection_hash));
        }
        // delete connections or the last access was over a week ago
        $DB->query('DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('users_connections').' WHERE DATEDIFF(lasttime,now()) < -7');

        if (session_status() == 2) session_destroy();

        $this->login = '';
        $this->id = 0;
        $this->admin = false;

        $this->connection_data = null;
        $this->user_data = [];
        
        if ($bloquer){
            array_push($this->error_list, 'bloquer');
        }
    }
    /**
     * Checks if a login  already exists.
     *
     * @param string $login to check.
     * @return bool true , false
     */
    public function login_exists($login)
    {
        global $DB_CENTRAL;

        $exists = $DB_CENTRAL->prepared_query_value('SELECT DISTINCT COUNT(*) FROM '.$DB_CENTRAL->table('users_data').' WHERE login=?', 's', array($login));
        return $exists > 0;
    }
    /**
     * Checks if a email  already exists.
     *
     * @param string $email to check.
     * @return bool true,false
     */
    public function email_exists($email)
    {
        global $DB_CENTRAL;
        $exists = $DB_CENTRAL->prepared_query_value('SELECT DISTINCT COUNT(*) FROM '.$DB_CENTRAL->table('users_data').' WHERE email=?', 's', array($email));
        return $exists > 0;
    }
    /**
     * Checks if an account exists for the given login and email.
     *
     * @param string $login 
     * @param string $email 
     * @return array Array with 'login' and 'email' keys if found.
     */
    public function account_exists($login, $email)
    {
        global $DB_CENTRAL;
        $data = [];
        $data['login'] = $DB_CENTRAL->prepared_query_value('SELECT DISTINCT login FROM '.$DB_CENTRAL->table('users_data').' WHERE login=?', 's', array($login));
        $data['email'] = $DB_CENTRAL->prepared_query_value('SELECT DISTINCT email FROM '.$DB_CENTRAL->table('users_data').' WHERE email=?', 's', array($email));
        return $data;
    }
    /**
     * Returns the current user agent string.
     *
     * @return string User agent.
     */
    public function get_user_agent()
    {
        if (!isset($_SERVER["HTTP_USER_AGENT"])) {
            if (!isset($_SERVER["UNIQUE_ID"])) {
                if (!isset($_SERVER["HOSTNAME"])) {
                    return str_replace('"','',php_uname("n"));
                }else{   
                    return str_replace('"','',$_SERVER["HOSTNAME"]);
                }
            }else{
                return str_replace('"','',$_SERVER["UNIQUE_ID"]);
            }
        } else {
            return str_replace('"','',$_SERVER["HTTP_USER_AGENT"]);
        }
    }
    /**
     * Returns the duration (in seconds) of the current connection.
     *
     * @return int Connection duration in seconds.
     */
    public function get_connection_duration()
    {
        if (is_array($this->connection_data)) {
            return (time() - $this->connection_data['time']);
        } else {
            return 0;
        }
    }
}