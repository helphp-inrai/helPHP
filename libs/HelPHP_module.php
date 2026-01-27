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
 * @class HelPHP_module
 * 
 * Base class for all modules in helPHP. 
 * 
 * Provides essential methods and properties for module management,
 * including access control, data handling, output structuring, translation, form generation, and integration
 * with the system's core features. 
 * 
 * Serves as an intermediate layer between basic libraries and modules,
 * combining and extending their functionalities.
 * 
 * All HelPHP modules are extentions of this class, permitting also to share all common objects initialized by autoload and init.php
 * 
 * All new module should execute $this->prepare_module to indicate if it's an admin or public module and setting around its module name
 * and then execute parent::__construct($domContainer) ($domContainer is set by prepare_module to define its default display container).
 * Take a look on different module to see how they are constructed, but to help you a training is available on our site 
 * "how to create a new module" : URL MISSING ! 
 *  
 * @package helPHP\libs
 */
class HelPHP_module
{
    public static $disable_output = false;

    //----------------------------------------------------------------------------------------------
    // essential variables for all modules
    //----------------------------------------------------------------------------------------------
    const module_name = 'helphpmodule';

    const hierarchy_suffix = '_parentage';
    const indentation_key = '_level_';

    protected $module_name = self::module_name; //important to avoid conflict between module, must be unique

    const posted_varname = 'posted_from_container';
    protected $posted_varname = 'posted_from_container';
    const posted_container_name = 'posted_from_container_name';
    protected $posted_container_name = 'posted_from_container_name';

    protected $admin = false; //is set by prepare_module

    protected $input_action_identifier = 'action';

    protected $root_module = false;
    //----------------------------------------------------------------------------------------------
    // variables specific to this module
    //----------------------------------------------------------------------------------------------

    public $tl = array();

    protected $dom_container = 'helphpmodule'; //basename for default display container  
    protected $dom_id; // unique identifier, default to current timestamp to identify different instances of same module
    protected $dom_target; // will be the concatenation of dom_container and dom_id
    protected $current_id = 0;
    // protected $in_a_tab = false; // if module is load in a tab will be the number of the tab

    protected $css = '';
    /**
     * save the javascript instance name
     * 
     * filled by function process_data
     * ex: $this->inst_js = 'h.modules.csseditor_a["'.$this->dom_target.'"].';
     * all scripts for modules are instances saved in a array named from the module name +_a if it's the admin module.
     * and the identifier is dom_id (it's unique) and this array is accessible in the js global object h.modules.
     */
    protected $inst_js = '';

    // this variable indicates whether the received data has been sent
    // from a form displayed in the module container and therefore a new container is unnecessary
    protected $posted_from_container = false;

    private $stored_table_data = array(); // get extracttable_data data for manips according to table structure

    public $lang = '';

    public $module_path = '';

    // content of the html return
    public $display ;
    
    protected $display_root = null;

    protected $display_obj_tree = null; // representation of output as an object

    protected $construct_time;

    protected $core_insert = false; // determines whether the output will be integrated into the core module or not.

    protected $user_allowed = true;
    protected $user_can_edit = true;

    protected $init_script = '';

    protected $indentation_key = HelPHP_module::indentation_key;
    
    // list  error messages displayed as a popup
    public $error_list = [];

    // list informative messages displayed in popup
    public $message_list = [];

    // if true, the page will be reloaded when the popup is closed
    // if a url is specified, it will be used as the reload address
    public $reload_after_message = false;

    public $options;
    protected $settings_data;

    const reload_page = true;
    protected $reload_root;

    // this constant is used in the JS constants and is used to force a reload of the page by indicating that all url parameters must be removed
    // see in H_dom.reload_page() and H_ajax_sender.process_json()
    const reload_no_params = 'reloadnoparams';

    // if this value is greater than 0,
    // a full page reload script will be automatically inserted at the end of the output of this module
    // with specified delay, in ms
    public $force_reload_delay = 0;

    // the output of the module will be a json string
    public $json_output = null;

    public $debug = false;

      
    //----------------------------------------------------------------------------------------------
    // Init methods in the construct, __get, and load options
    //----------------------------------------------------------------------------------------------

    /**
     *
     * Initializes the module, sets up display container, checks user access, loads translations and options,
     * and prepares settings if available.
     *
     * @param string|null $dom_container Optional. The DOM container name.
     * @param string|false $module_path Optional. Path to the module.
     */
    public function __construct($dom_container = null,$module_path=false)
    {
        global $CONFIG;
        $this->reload_root = $CONFIG::BASE_URL;

        $this->construct_time = microtime(true); // for time measurement with get_elapsed_time

        // create a HTML_Class/HTML_GROUP_Class object who will receive the output
        $this->display = H::group($this->module_name.'_display');

        $this->css = $this->module_name.'_' . ($this->admin ? 'admin_' : '');

        // update of div's name who will receive the result to be displayed
        if ($dom_container !== null) {
            $this->dom_container = $dom_container;
        }
        
        $this->check_module_access(); // check access rights
        
        if (!$this->user_allowed) {
            global $USER;
            Utils::error_log($this->module_name.($this->admin?' admin':'').' not allowed for user "'.$USER->login.'"');
            $this->display->add_child('<span style="color:red; font-weight:bold;">'.$this->module_name.($this->admin?' admin':'').' -> access forbidden !</span>');
            $this->add_error('no_access');
            $this->reload_after_message = HelPHP_module::reload_no_params;
            return $this->display;  // STOP HERE !
        }else{
        
            global $LANG; // instance of the Language class, initialized in init.php
            // $LANG->set_context($this->admin?'admin':'');
            $this->lang = $LANG->current_language;
            // recover translation files :
            $this->load_translation_files($module_path);

            $this->load_options();
            
            if (isset($CONFIG::MODULES_LIST['settings']) && ((isset($this->settings) && $this->settings) || (isset($this->settings_private) && $this->settings_private))){
                $this->load_settings_data();
            }
                
        }
        
        
        //~ Utils::error_log('User can edit in '.$this->module_name.' : '.$this->user_can_edit);
    }

    /**
     * Magic getter for dynamic property access (table names, field names, JS instance, etc.).
     *
     * @param string $property The property name.
     * @return mixed The computed property value or null.
     */
    public function __get($property)
    {
        // kept for compatibility  and can still be usefull. . .
        $pos = strpos($property, '_');

        if ($pos !== false) {
            $type = substr($property, 0, $pos);
            $name = substr($property, $pos+1, strlen($property)-1);

            switch ($type) {
                // generates the name of the table as it appears in the bdd: “tablePrefix_module_name”
                // here $name equals to tableName
                case 'bddt':
                case 'bddtable':
                    return $this->build_table_name($name);
                break;
                // generates the input name, link to a field in bdd
                // the input name will be  "moduleName_tableName-fieldName"
                // here $table equals to tableName and $field to fieldName
                case 'ifld':
                case 'inpfield':
                case 'inputfield':
                    $pos = strpos($name, '_');
                    if ($pos !== false) {
                        $table = substr($name, 0, $pos);
                        $field = substr($name, $pos+1, strlen($name)-1);
                        return $this->build_field_name($table, $field);
                    } else {
                        Utils::error_log('fieldname generation error on'.$this->module_name.'->'.$property);
                    }
                break;
                case 'dti':
                case 'domtagid':
                    return $this->css.$name.$this->dom_id;
                break;
            }
        } else {
            switch ($property) {
                case 'js':
                    return 'SPD_'.$this->module_name;
                break;

                // case 'css':
                //     return $this->module_name.'_' . ($this->admin ? 'admin_':'');
                // break;
            }
        }

        return null;
    }
    
    /**
     * check if user has right to access depending group restriction
     * 
     * Item from a module can be access restricted depending group. If item is not restricted will return true. If item 
     * is restricted, will check if user belongs to group with access.
     * 
     * @param string $field_identifier Identify the field like "my_table-id"
     * @param integer $id_item ID of the field
     * 
     * @return boolean User right to access
     */
    public function group_has_access($field_identifier, $id_item) {
        global $DB, $USER;
        
        // retrieve list of group that can access 
        $q = 'SELECT id_group_data FROM '.$DB->table('group_content').' WHERE field_identifier=? AND id_item=?';
        $groups = $DB->prepared_query_list($q,'si',[$field_identifier, $id_item]);

        if (!$groups) return true; // no entry mean no restriction

        $user_groups = $USER->allowed_groups();
        $intersect = array_intersect($groups, $user_groups);
        
        // intersect check if there is same id in both array, mean that user is part of a group that can access
        if ($intersect) return true;

        return false;
    }

     /**
     * Prepares the SQL query part for group-based access restrictions.
     *
     * @param string $field_identifier Field identifier.
     * @param int $id Item ID.
     * @return string SQL query part to add in the query to limit depending group
     */
    public function prepare_group_query_part($field_identifier, $id){
        global $USER;
        // test group
        $groups = $USER->allowed_groups();
        $str_groups = implode(',', $groups);
        if ($str_groups != '') {
            $q_groups = ' AND ('.$this->bddt_data.'.id_group_data = 0 OR '.$this->bddt_data.'.id_group_data IN ('.$str_groups.') ) ';
        } else {
            $q_groups = ' AND '.$this->bddt_data.'.id_group_data = 0 ';
        }

        return $q_groups;
    }
     /**
     * Returns the SQL query part to limit results to the current user.
     *
     * @param string|false $field Optional. Field name to check.
     * @return string SQL query part.
     */
    public function user_query_part($field = false){
        global $USER;
        if ($USER->admin){
            return '';
        }
        if (!$field) {
            return ' AND '.$this->bddt_data.'.id_users_data = '.$USER->id;
        } else {
            return ' AND '.$field.' = '.$USER->id;
        }
    }
    /**
     * Prepares the module with its name and admin status.
     *
     * @param string $newName New module name.
     * @param bool $isadmin Whether the module is in admin mode.
     * @return void
     */
    public function prepare_module($newName, $isadmin){
        // rename
        $this->module_name = $newName;
        $this->input_action_identifier = $newName.'_action';
        // name of div container dom where to display the html return
        $this->admin = $isadmin;
        $this->dom_container = ($isadmin)? 'module_container '.$newName.'_admin_container' : 'module_container '.$newName.'_container' ;
    }
    
    /**
     * Returns the time elapsed (in seconds) since the class was instantiated.
     * it doesn't use Datetime class chrono function, 
     *
     * @return float Elapsed time in seconds.
     */
    public function get_elapsed_time()
    {
        return microtime(true) - $this->construct_time;
    }

    /**
     * Checks module access rights for the current user and sets user_allowed and user_can_edit.
     *
     * @return void
     */
    public function check_module_access()
    {
        global $USER,$CONFIG;

        if ($CONFIG::MODULES_LIST !== null) {
            if (isset($_SERVER['argc']) && $_SERVER['argc'] > 0){
                $this->user_allowed = $CONFIG::MODULES_LIST;
                return;
            }
            if ($this->admin && $_SERVER['HTTP_HOST'] == $CONFIG::DOMAIN && strpos(trim($_SERVER['REQUEST_URI'], '/'), trim($CONFIG::SITE_NAME.'/'.$CONFIG::ADMIN_FOLDER, '/'))==0) {
                // we are in the admin side
                if ($USER->connection_state == User::state_logged) {
                    // if the visitor is connected
                    $allowed_admin_modules = $USER->allowed_admin_modules();
                    
                    if (count($allowed_admin_modules) > 0) {
                        $this->user_allowed = in_array($this->module_name, $allowed_admin_modules);
                        
                    } else {
                        $this->user_allowed = $this->root_module;
                    }
                } else {
                    // if the visitor is not connected
                    $this->user_allowed = $this->root_module;
                }
                
            } else {
                // we are in the public side
                $allowed_public_modules = $USER->allowed_public_modules();

                // public module
                if ($USER->connection_state == User::state_logged) {
                    if (in_array($this->module_name, $allowed_public_modules)) {
                        $this->user_allowed = true;
                    } else {
                        // if the visitor is connected
                        $allowed_registered_modules = $USER->allowed_registered_modules();

                        if (count($allowed_registered_modules) > 0) {
                            $this->user_allowed = in_array($this->module_name, $allowed_registered_modules);
                        } else {
                            $this->user_allowed = $this->root_module;
                        }
                    }
                } else {
                    // if the visitor is not connected
                    $this->user_allowed = in_array($this->module_name, $allowed_public_modules);
                }
            }
            
            // in both case we need to check if the user has the right to edit
            $restricted_edit_modules = $USER->restricted_edit_modules();
            if (count($restricted_edit_modules) > 0) {
                $this->user_can_edit = in_array($this->module_name, $restricted_edit_modules) ? false : true;
            }
            
        } else {
            Utils::error_log('no module list defined '.$_SERVER['REQUEST_URI']);
        }
    }
    
    /**
     * Loads options for this module from the main config file.
     * Executed at starting of the module
     *
     * @return void
     */
    public function load_options()
    {
        global $CONFIG;
        if (isset($CONFIG::MODULES_LIST[$this->module_name])) {
            if ($CONFIG::MODULES_LIST[$this->module_name]['options'] != '') {
                $this->options = json_decode($CONFIG::MODULES_LIST[$this->module_name]['options'], true);
            }
        }
    }
     /**
     * Loads options for a given module from the configuration.
     * used to check options from another module than current one.
     *
     * @param string $module Module name.
     * @return array|false Options array or false if not found.
     */
    public static function module_load_options($module)
    {
        global $CONFIG;
        if (isset($CONFIG::MODULES_LIST[$module])) {
            if ($CONFIG::MODULES_LIST[$module]['options'] != '') {
                return json_decode($CONFIG::MODULES_LIST[$module]['options'], true);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
     /**
     * Loads settings data for the current module from settings module DB if it's installed.
     *
     * @return void
     */
    public function load_settings_data()
    {
        global $DB,$USER;
        
        $res = [];
        $result_query = [];
        $full_settings = [];
        if (isset($this->settings_private) && $this->settings_private) $full_settings = array_merge($full_settings, $this->settings_private);
        if (isset($this->settings) && $this->settings) $full_settings = array_merge($full_settings, $this->settings);
        foreach($full_settings as $key => $ensemble){
            $q = 'SELECT sett.value, dat.name FROM '.$DB->table_prefix.'_settings_'.$ensemble.' sett, '.$DB->table_prefix.'_settings_data dat WHERE';
            $q.=' sett.id_users_data='.$USER->id.' AND sett.id_settings_data=dat.id';
            $result_query[$ensemble] = $DB->query_list($q);
        }
        
        foreach($result_query as $ensemble => $lst){
            $res[$ensemble] = [];
            foreach($lst as $key => $line){
                $res[$ensemble][$line['name']] = $line['value'];
            }
        }
        
        $this->settings_data = $res;
    }
    
    //----------------------------------------------------------------------------------------------
    // Methods related to output messages. . .
    //----------------------------------------------------------------------------------------------
    
     /**
     * Adds an error message to be displayed as a popup.
     *
     * @param string $error_message_key Translation key for the error message.
     * @param mixed|null $replace Optional. Replacement values for the message.
     * @return void
     */
    public function add_error($error_message_key, $replace = null)
    {
        $error_message = $this->get_tl($error_message_key, $replace);

        array_push($this->error_list, $error_message);
    }

    /**
     * Adds a standard message to be displayed as a popup.
     *
     * @param string $message_key Translation key for the message.
     * @param mixed|null $replace Optional. Replacement values for the message.
     * @return void
     */
    public function add_message($message_key, $replace = null)
    {

        $message = $this->get_tl($message_key, $replace);

        array_push($this->message_list, $message);
    }

    /**
     * Checks if there are any error message.
     *
     * @return int|bool number of message, false otherwise.
     */
    public function get_error_count()
    {
        return sizeof($this->error_list);
    }
    /**
     * Checks if there are any error or message popups to display.
     *
     * @return bool True if there are messages or errors, false otherwise.
     */
    public function has_any_message()
    {
        return sizeof($this->error_list)>0 || sizeof($this->message_list)>0;
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
     * Synchronizes error and message lists with another module instance.
     *
     * @param HelPHP_module $moduleInstance The module instance to sync with.
     * @return void
     */
    public function sync_messages_with($moduleInstance)
    {
        $msg_error = $this->get_multiple_tl($moduleInstance->get_error_list());
        if (sizeof($msg_error) > 0) {
            $this->error_list = array_unique(array_merge($this->error_list, $msg_error));
        }

        $msg_list = $this->get_multiple_tl($moduleInstance->get_message_list());
        if (sizeof($msg_list) > 0) {
            $this->message_list = array_unique(array_merge($this->message_list, $msg_list));
        }
    }

   

     /**
     * Synchronizes error and message lists with the global user object to check connection etc.
     *
     * @return void
     */
    public function sync_with_user()
    {
        global $USER;
        
        $msg_error = $this->get_multiple_tl($USER->get_error_list());
        if (sizeof($msg_error) > 0) {
            $this->error_list = array_unique(array_merge($this->error_list, $msg_error));
        }

        $msg_list = $this->get_multiple_tl($USER->get_message_list());
        if (sizeof($msg_list) > 0) {
            $this->message_list = array_unique(array_merge($this->message_list, $msg_list));
        }

        if ($this->force_reload_delay == 0) {
            $this->force_reload_delay = $USER->force_reload_delay;
        }

        if ($USER->reload_after_message != '') {
            $this->reload_after_message = $USER->reload_after_message;
        }
    }
    
    //----------------------------------------------------------------------------------------------
    // Methods related to the manipulation of input-output data...
    //----------------------------------------------------------------------------------------------
    
    /**
     * Main method for processing input data and handling module actions.
     * Handles form submissions, translation, autocomplete, TinyMCE uploads, quick edit,
     * prepare javascript init, etc....
     *
     * IMPORTANT: the & before $post makes it a pointer to the original variable which is then updated directly
     * 
     * @param array &$post Reference to the POST data array.
     * @return bool True if processing succeeded, false otherwise.
     */
    public function process_data(&$post)
    {
        if ($this->user_allowed) {
            global $DB,$CONFIG;

            if (!is_array($post)) {
                $post = array();
            }

            if (isset($post['action']) && $post['action'] == 'tinymceupload') {
                $this->receive_tinymce_file();
                exit;
            }
            
            if (isset($post['action']) && $post['action'] == 'translate') {
                set_time_limit(120);
                echo json_encode(Language::translate($post['texte'],$post['format'],$post['iso_original'],$post['iso_targets']),JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (isset($post['action']) && $post['action'] == 'formgettoken') {
                $sessid = session_id();
                $token = floor(microtime(true) * 1000).'C'.$sessid;
                $_SESSION['js_token'] = $token;
                exit($token);
            }

            if (isset($post['action']) && $post['action'] == 'autocomplete') {
                echo $this->autocomplete($post);
                exit;
            }

                        
            if (!isset($post[$this->input_action_identifier])) {
                $post[$this->input_action_identifier] = '';
            }

            if (isset($post[$this->posted_varname])) {
                $this->posted_from_container = true;
                if (isset($post[$this->posted_container_name])){
                    $this->dom_container = $post[$this->posted_container_name];
                }
            }

            $this->get_dom_id($post);
            $this->dom_target = str_replace('module_container ', '', $this->dom_container).$this->dom_id;
            
            $this->inst_js = ($this->admin) ? 'h.modules.'.$this->module_name.'_a["'.$this->dom_id.'"]' : 'h.modules.'.$this->module_name.'["'.$this->dom_id.'"]';
            
            if (isset($post['current_id']) && $post['current_id']) {
                $this->current_id = $post['current_id'];
            } else if (isset($post['id']) && $post['id']) {
                $this->current_id = $post['id'];
            }


            $this->core_insert = isset($post['core_insert']);

            if (isset($post['quick_edit'])) {

                // before sending the quick edit response, check if the response come with a token.
                // if not do nothing
                if (!isset($post['js_token'])) {
                    exit('error');
                }
                if (!isset($_SESSION['js_token']) || $_SESSION['js_token'] != $post['js_token']){
                    exit('error');
                }
                
                unset($_SESSION['js_token']);
                $tokenTime = explode('C', $post['js_token'])[0];
                $currentTime = floor(microtime(true) * 1000);
                $dif = $currentTime - $tokenTime;
                
                if ($dif > 300) {
                    exit('error');
                }
                
                switch ($post['quick_edit']) {
                    case 'save':
                        if (!isset($post['id_lang_data'])) {
                            $post['id_lang_data'] = 0;
                        }

                        $test = $this->quick_edit_update($post['id'], $post['value'], $post['type'], $post['data'], intval($post['id_lang_data']));
                        if ($test !== false) {
                            echo $post['value'];
                        } else {
                            echo 'ERROR!';
                        }


                    break;

                    case 'cancel':
                        echo $post['value'];
                    break;

                    default:
                        if (isset($post['id']) && isset($post['data'])) {
                            if (!isset($post['id_lang_data'])) {
                                $post['id_lang_data'] = 0;
                            }

                            $attributes = [];
                            if (isset($post['min'])){
                                $attributes['min'] = $post['min'];
                            }
                            if (isset($post['max'])){
                                $attributes['max'] = $post['max'];
                            }
                            if (isset($post['step'])){
                                $attributes['step'] = $post['step'];
                            }

                            echo H::quick_edit_generate_input($post['id'], $post['value'], $post['type'], $post['data'], intval($post['id_lang_data']), $attributes);
                        } else {
                            echo 'ERROR!';
                        }
                    break;
                }

                exit;
            }

            return true;
        }else{
            return false;
        }
    }
    /**
     * Generates or retrieves the DOM ID for the module instance.
     *
     * @param array $post Optional. POST data array.
     * @return void set $this->dom_id
     */
    public function get_dom_id($post = []){
        // Utils::error_log($post);
        if (isset($post['dom_id']) && is_array($post['dom_id']) && isset($post['dom_id']['dom_id'])){
            $post['dom_id']=$post['dom_id']['dom_id'];
        }
        $this->dom_id = (isset($post['dom_id']) && $post['dom_id']!='')? $post['dom_id'] : '¤DOM_ID'.time().str_pad(rand(0,1000), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Extracts the structure of a table as an array.
     *
     * $table must contain only the name identifying the purpose of the table
     * without the site prefix or the module name
     *
     * example : for the table "siteprefix_modulename_message"
     * $table should be equal to "message"
     * 
     * @param string $table Table name (without prefix).
     * @return array Table structure data.
     */
    private function extract_table_data($table)
    {
        global $CONFIG_DB;
        if (!isset($this->stored_table_data[$table])) {
            if ($CONFIG_DB::DB_CENTRAL && in_array($table,$CONFIG_DB::DB_CENTRAL_TABLES)){
                global $DB_CENTRAL;
                $TDB=$DB_CENTRAL;
            }else{
                global $DB;
                $TDB=$DB;
            }
            
            $table_name = $TDB->table($table);
            $this->stored_table_data[$table] = $TDB->table_data($table_name);
        }
        return $this->stored_table_data[$table];
    }

    /**
     * Returns the full table name for the current module.
     * shortcut for build_module_table_name() using only table name
     *
     * @param string $table Table name.
     * @return string Full table name.
     */
    public function build_table_name($table)
    {
        return HelPHP_module::build_module_table_name($this->module_name, $table);
    }

     /**
     * Returns the full table name for a specified module.
     *
     * @param string $module Module name.
     * @param string $table Table name.
     * @return string Full table name.
     */
    public static function build_module_table_name($module, $table)
    {
        global $CONFIG_DB;
        if ($CONFIG_DB::DB_CENTRAL && in_array($table, $CONFIG_DB::DB_CENTRAL_TABLES)) {
            global $DB_CENTRAL;
            $TDB = $DB_CENTRAL;
        }else{
            global $DB;
            $TDB = $DB;
        }
        return $TDB->table_prefix.'_'.$module.'_'.$table;
    }

     /** construction of the field name that will be used for the inputs in the forms
     * it is formed by the 'name module'.'_'.'table name'.'-'.'field name'
     * ex : module "message" table "data" field "id" : message_data-id
     * ------------------------------
     * IMPORTANT :
     * the dash (-) is used as a separator, so it should not be found in module, table or field names
     * ------------------------------
     * this function is a shortcut to build_module_field_name (using current module_name)
     * 
     * @param string $table Table name.
     * @param string $field Field name.
     * @return string Field name for forms.
     */

    public function build_field_name($table, $field)
    {
        return HelPHP_module::build_module_field_name($this->module_name, $table, $field);
    }

    /**
     * Returns the field name for form inputs for a specified module.
     *
     * @param string $module Module name.
     * @param string $table Table name.
     * @param string $field Field name.
     * @return string Field name for forms.
     */
    public static function build_module_field_name($module, $table, $field)
    {
        return $module.'_'.$table.'-'.$field;
    }
    
    /**
     * Checks the validity of a token sent by a form with token protection against DDOS
     *
     * @param mixed $post The POST data containing the token to validate in $post['js_token']
     * @param int $delay The timestamp to validate against in microseconds
     * @return bool Returns true if token is valid, false otherwise
     */
    public static function check_token($post,$delay){
        if (isset($post['js_token'])) {
            if (isset($_SESSION['js_token']) && $_SESSION['js_token'] == $post['js_token']){
                unset($_SESSION['js_token']);
                
                $tokenTime = explode('C', $post['js_token'])[0];
                $currentTime = floor(microtime(true) * 1000);
                $dif = $currentTime - $tokenTime;
                
                if ($dif < $delay) {
                    return true;
                }
            }
        }
        return false;
    }

     /**
     * Splits a composed field name into module, table, and field.
     *
     * @param string $composed_field_name The composed field name.
     * @return array Array with keys 'module', 'table', 'field'.
     */
    public static function explode_field_name($composed_field_name)
    {
        $t = explode('_',$composed_field_name,2);
        $module = $t[0];
        $t = explode('-',$t[1],2);
        $table = $t[0];
        $field = $t[1];
        return ['module'=>$module , 'table'=>$table , 'field'=>$field];
    }

     /**
     * Extracts the field name from a composed field name.
     * (a formatted string "module_table-field")
     *
     * @param string $composed_field_name The composed field name.
     * @return string The field name.
     */
    public function extract_field_name($composed_field_name)
    {
        $t = explode('-',$composed_field_name,2);
        return isset($t[1]) ? $t[1] : $composed_field_name;
    }
    
     /**
     * Prepares fields for editing forms by checking posted data and applying database values 
     * if there is an posted ID.
     * shorcut for check_posted_data + apply_bdd_data
     *
     * @param array &$post Reference to the POST data array.
     * @param string $table Table name.
     * @return void $post will be filled.
     */
    public function prepare_fields(&$post,$table) {
        $this->check_posted_data($post , $table);
        if ($post[$table.'-id'] > 0 ) $this->apply_bdd_data($post, $table);
    }
    
    /**
     * Resets all fields in $post according to their type in the database.
     *
     * @param array &$post Reference to the POST data array.
     * @param string $table Table name.
     * @param array|false $fields Optional. List of fields to reset.
     * @return void
     */
    public function reset_fields(&$post, $table, $fields=false)
    {
        $table_data = $this->extract_table_data($table);
        if (!$fields){
            $fields=array_keys($table_data);
        }

        foreach ($fields as $field_name) {

            $post_key = $table.'-'.$field_name;
            if (isset($table_data[$field_name])) {
                switch ($table_data[$field_name]['type']) {
                    case 'varchar':
                    case 'char':
                    case 'text':
                    case 'tinytext':
                    case 'mediumtext':
                    case 'longtext':
                        $post[$post_key] = $table_data[$field_name]['default'];
                    break;

                    case 'int':
                    case 'bigint':
                    case 'tinyint':
                    case 'mediumint':
                    case 'float':
                    case 'decimal':
                        if ($table_data[$field_name]['extra'] == 'auto_increment') {
                            $post[$post_key] = 0;
                        } else {
                            $post[$post_key] = $table_data[$field_name]['default'];
                        }
                    break;

                    case 'timestamp':
                    case 'datetime':
                    case 'date':
                        $post[$post_key] = '';
                    break;

                    default:
                        Utils::error_log('missing case resetData : '.$field_name.' - type:'.$table_data[$field_name]['type']);
                        $post[$post_key] = null;
                    break;
                }
            }
        }
    }
    /**
     * Retrieves data from the database for a table and optional fields/ID.
     *
     * @param string $table Table name.
     * @param string|array $fields Fields to retrieve.
     * @param int|array|null $id Optional. ID(s) to filter.
     * @return array Retrieved data.
     */
    public function get_bdd_data($table, $fields = '*', $id = null)
    {
        global $DB;

        $table_name = $this->build_table_name($table);

        if (is_array($fields)) {
            $fields = implode(',', $fields);
        }

        if ($id === null) {
            $q = 'SELECT DISTINCT '.$fields.' FROM '.$table_name;
            return $DB->prepared_query_list($q);
        } elseif (is_array($id)) {
            $q = 'SELECT DISTINCT '.$fields.' FROM '.$table_name.' WHERE id IN ('.implode(',', $id).')';
            return $DB->prepared_query_line($q);
        } else {
            $q = 'SELECT DISTINCT '.$fields.' FROM '.$table_name.' WHERE id=?';
            return $DB->prepared_query_line($q, 'i', [$id]);
        }
    }

    /** This function modifies $post by incorporating the values from the bdd
     * and prepare them to be displayed in form fields
     * if $fields is false then we take all fields of the table by default. . .
     * this avoids making an useless array of type protected_$fields .
     * @param array &$post Reference to the POST data array.
     * @param string $table Table name.
     * @param array|false $fields Optional. List of fields.
     * @param int|null $id Optional. ID to filter.
     * @return void
     */
    public function apply_bdd_data(&$post, $table, $fields=false, $id = null)
    {
        global $CONFIG_DB;
        if ($CONFIG_DB::DB_CENTRAL && in_array($table,$CONFIG_DB::DB_CENTRAL_TABLES)){
            global $DB_CENTRAL;
            $TDB=$DB_CENTRAL;
        }else{
            global $DB;
            $TDB=$DB;
        }

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        $table_name = $TDB->table($table);
        
        $queryFields=(!$fields)? '*': implode(',', $fields);
        
        if ($id === null) {
            $id = $post[$table.'-id'];
        }

        $q = 'SELECT DISTINCT '.$queryFields.' FROM '.$table_name.' WHERE id=?';
        $bdd_data = $TDB->prepared_query_line($q, 'i', array($id));

        // prepares for posting in a form
        // Text values are corrected in order to be displayed correctly
        // in form fields (management of " and ')
        if (!$bdd_data) {
            $bdd_data = array();
            
            foreach ($post as $field_name => $field_value) {
                if ($field_name != null){
                    $bdd_data[$this->extract_field_name($field_name)] = $field_value;
                }
            }
        }

        if (is_array($bdd_data)) {
            if (is_string($fields)) {
                $fields = explode(',', $fields);
            }

            $table_data = $this->extract_table_data($table);
            if (!$fields){
                $fields = array_keys($table_data);
            }
            foreach ($fields as $field_name) {
                $post_key = $table.'-'.$field_name;
 
                if (isset($table_data[$field_name])) {
                    if (isset($bdd_data[$field_name])) {
                        switch ($table_data[$field_name]['type']) {
                            case 'varchar':
                            case 'char':
                            case 'text':
                            case 'tinytext':
                            case 'mediumtext':
                            case 'longtext':
                                $post[$post_key] = htmlentities($bdd_data[$field_name], ENT_QUOTES);
                            break;

                            case 'bigint':
                            case 'mediumint':
                            case 'int':
                            case 'tinyint':
                                $post[$post_key] = intval($bdd_data[$field_name]);
                            break;

                            case 'float':
                            case 'decimal':
                                $post[$post_key] = floatval($bdd_data[$field_name]);
                            break;

                            case 'timestamp':
                            case 'datetime':
                            case 'date':
                                $post[$post_key] = $bdd_data[$field_name];
                            break;

                            default:
                                Utils::error_log('missing case bddData : '.$field_name.' - type:'.$table_data[$field_name]['type']);
                                $post[$post_key] = $bdd_data[$field_name];
                            break;
                        }
                    } else {
                        $post[$post_key] = '';
                    }
                }
            }
        } else {
            Utils::error_log('error reading bdd data for '.print_r($post, true));
        }
    }
     /**
     * Returns the raw format type for a given field type or name.
     *
     * @param string $nameOrType Field type or name.
     * @return string Raw format type ('int', 'float', 'string').
     */
    public function get_raw_format($nameOrType)
    {
        switch ($nameOrType) {
            case 'bool':
            case 'int':
            case 'bigint':
            case 'tinyint':
            case 'mediumint':
            case 'integer':
            case 'radio':
            case 'checkbox':
                return 'int';
            break;

            case 'float':
            case 'double':
            case 'decimal':
                return 'float';
            break;

            case 'varchar':
            case 'char':
            case 'login':
            case 'email':
            case 'password':
            case 'text':
            case 'tinytext':
            case 'mediumtext':
            case 'longtext':
            case 'select':
                return 'string';
            break;

            default:
                Utils::error_log('get_raw_format -> type not referenced : '.$nameOrType);
            break;
        }

        return 'string';
    }
    
    /**
     * This function checks the validity of fields sent from the form and an alignment is made according to the type of each field.
     * If we don’t send an array “fields” then we will check all the fields of the table, which should be the base...
     * this avoids making an array of type protected_$fields.
     * IMPORTANT: the & before $post makes it a pointer to the original variable which is then updated directly
     *
     * @param array &$post Reference to the POST data array.
     * @param string $table Table name.
     * @param array|false $fields Optional. List of fields.
     * @return void
     */
    public function check_posted_data(&$post, $table, $fields=false)
    { 
        $table_data = $this->extract_table_data($table);
        if (!$fields){
            $fields=array_keys($table_data);
        }
        foreach ($fields as $field_name) {
            $post_key = $table.'-'.$field_name;
            if (isset($table_data[$field_name])) {
                switch ($table_data[$field_name]['type']) {
                    case 'varchar':
                    case 'char':
                        if (isset($post[$post_key])) {
                            if (is_array($post[$post_key])) {
                                foreach ($post[$post_key] as $k=>$val) {
                                    $post[$post_key][$k] = stripslashes($post[$post_key][$k]);
                                }
                            } else {
                                $post[$post_key] = stripslashes($post[$post_key]);
                            }
                        } else {
                            $post[$post_key] = '';
                        }
                    break;

                    case 'text':
                    case 'longtext':
                    case 'tinytext':
                    case 'mediumtext':
                        if (isset($post[$post_key])) {
                            if (is_array($post[$post_key])) {
                                foreach ($post[$post_key] as $k=>$val) {
                                    $post[$post_key][$k] = str_replace(array("\\r\\n","\r","\n","\\r","\\n"), "\n", $post[$post_key][$k]);
                                    $post[$post_key][$k] = stripslashes($post[$post_key][$k]);
                                }
                            } else {
                                $post[$post_key] = str_replace(array("\\r\\n","\r","\n","\\r","\\n"), "\n", $post[$post_key]);
                                $post[$post_key] = stripslashes($post[$post_key]);
                            }
                        } else {
                            $post[$post_key] = '';
                        }
                    break;

                    case 'bool':
                    case 'int':
                    case 'bigint':
                    case 'tinyint':
                    case 'mediumint':
                        if (isset($post[$post_key])) {
                            if (is_array($post[$post_key])) {
                                foreach ($post[$post_key] as $k=>$val) {
                                    $post[$post_key][$k] = intval($post[$post_key][$k]);
                                }
                            } else {
                                $post[$post_key] = intval($post[$post_key]);
                            }
                        } else {
                            $post[$post_key] = '';
                        }
                    break;

                    case 'float':
                    case 'decimal':
                        if (isset($post[$post_key])) {
                            if (is_array($post[$post_key])) {
                                foreach ($post[$post_key] as $k=>$val) {
                                    $post[$post_key][$k] = floatval($post[$post_key][$k]);
                                }
                            } else {
                                $post[$post_key] = floatval($post[$post_key]);
                            }
                        } else {
                            $post[$post_key] = '';
                        }
                    break;

                    case 'timestamp':
                    case 'datetime':
                        if (isset($post[$post_key])) {
                            if (is_array($post[$post_key])) {
                                foreach ($post[$post_key] as $k=>$val) {
                                    $post[$post_key][$k] = stripslashes($post[$post_key][$k]);
                                }
                            } else {
                                $post[$post_key] = stripslashes($post[$post_key]);
                            }
                        } else {
                            $post[$post_key] = time();
                        }
                    break;

                    default:
                        Utils::error_log('missing case postedData : '.$field_name.' - type:'.$table_data[$field_name]['type']);
                        if (isset($post[$post_key])) {
                            if (is_array($post[$post_key])) {
                                foreach ($post[$post_key] as $k=>$val) {
                                    $post[$post_key][$k] = stripslashes($post[$post_key][$k]);
                                }
                            } else {
                                $post[$post_key] = stripslashes($post[$post_key]);
                            }
                        } else {
                            $post[$post_key] = '';
                        }
                    break;
                }
            } else {
                foreach (Language::translation_prefix as $type=>$prefix) {
                    if (isset($post[$prefix])) {
                        $fields = &$post[$prefix];
                        if (isset($fields[$post_key])) {
                            foreach ($fields[$post_key] as $id => $values) {
                                foreach ($values as $id_data => $text) {
                                    $fields[$post_key][$id][$id_data] = Utils::fix_EOL($text);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Generates a name for the group that will contain form fields.
     *
     * @param string $name Optional. Suffix for the group name.
     * @return string Group name.
     */
    public function get_form_fields_name($name = '')
    {
        return $this->module_name.'_form_fields_'.$name;
    }

    /**
     * kept for compatibility. 
     * required fields are now directly into the form function from H class
     * initialise required fields by default in all forms of the module
     *
     * @param string $name Optional. Suffix for the group name.
     * @return mixed Form fields group object.
     */
    public function init_form_fields($name = '')
    {
        $list = H::group($this->get_form_fields_name($name));
        $list->add_child(H::input_hidden(array('name' => $this->posted_varname , 'value'=>1 , 'data-alwaysposted'=>'1')));

        return $list;
    }
    // ------------------- Data Tree and Associations -------------------

    /**
     * Adds a parenting relationship in the hierarchy table.
     *
     * @param string $table Table name.
     * @param int $id_item Item ID.
     * @param int $id_parent Parent ID.
     * @return bool True on success, false otherwise.
     */
    public function add_parenting($table, $id_item, $id_parent = 0){
        global $DB;

        $order = $this->get_order_parenting($table, $id_parent);
        $q = 'INSERT INTO '.$this->build_table_name($table.HelPHP_module::hierarchy_suffix).' SET id_item=?, id_parent=?, sort_order='.$order;
        $res = $DB->prepared_query($q, 'ii', [$id_item, $id_parent]);
        return ($res !== false);
    }
     /**
     * Gets the next sort order value for a parent in the hierarchy table.
     *
     * @param string $table Table name.
     * @param int $id_parent Parent ID.
     * @return int Next sort order value.
     */
    public function get_order_parenting($table, $id_parent = 0) {
        global $DB;

        $q = 'SELECT MAX(sort_order) FROM '.$this->build_table_name($table.HelPHP_module::hierarchy_suffix).' WHERE id_parent=?';
        $order = $DB->prepared_query_value($q, 'i', [$id_parent]);
        $order = ($order) ? $order + 1 : 1;
        return $order;
    }
    /**
     * Deletes all parenting entries for a specified item.
     *
     * @param string $table Table name.
     * @param int $id Item ID.
     * @return void
     */
    public function delete_parenting($table, $id) {
        global $DB;

        if ($id > 0) {
            $table_tree = $this->build_table_name($table.HelPHP_module::hierarchy_suffix);
            if ($DB->table_exists($table_tree)) {
                // if the deleted item has a parent, children of the deleted item are transferred to the this parent.
                $q = 'SELECT id_parent FROM '.$table_tree.' WHERE id_item=?';
                $parent = $DB->prepared_query_value($q, 'i', [$id]);
                if ($parent){
                    $q = 'UPDATE '.$table_tree.' SET id_parent='.$parent.' WHERE id_parent=?';
                    $DB->prepared_query($q, 'i', [$id]);
                }

                $q = 'DELETE FROM '.$table_tree.' WHERE id_item=? OR id_parent=?';
                $res = $DB->prepared_query($q, 'ii', [$id , $id]);
            }
        }
    }

    /**
     * Removes associations related to a specified item.
     *
     * @param int $id Item ID.
     * @param string $otherModule Optional. Other module name.
     * @return void
     */
    public function delete_associations($id, $otherModule = '')
    {
        global $DB;
        $DB->delete_associations($id, $this->module_name, $otherModule);
    }

    /**
     * Returns the list of all association tables related to this module.
     *
     * @param string $otherModule Optional. Other module name.
     * @return array List of association tables.
     */
    public function get_association_tables($otherModule = '')
    {
        global $DB;
        $DB->get_association_tables($this->module_name,$otherModule);
    }
     /**
     * Gets the parent ID for a specified item in the hierarchy table.
     *
     * @param string $table Table name.
     * @param int $id Item ID.
     * @return int|null Parent ID or null if not found.
     */
    public function get_parent_id($table, $id)
    {
        global $DB;

        $id_parent = null;

        $table_data = $this->build_table_name($table);

        $table_tree = $this->build_table_name($table.HelPHP_module::hierarchy_suffix);

        $exists = $DB->table_exists($table_tree);
        if ($exists) {
            $id_parent = $DB->query_value('SELECT DISTINCT id_parent FROM '.$table_tree.' WHERE id_item='.$id);
            if ($id_parent === false) {
                $id_parent = null;
            }
        }

        return $id_parent;
    }
    /**
     * Gets parent data for a specified item in the hierarchy table.
     *
     * @param string $table Table name.
     * @param int $id Item ID.
     * @return array|null Parent data or null if not found.
     */
    public function get_parent_data($table, $id) {
        global $DB;

        $result = null;

        $table_data = $this->build_table_name($table);
        $table_tree = $this->build_table_name($table.HelPHP_module::hierarchy_suffix);

        $exists = $DB->table_exists($table_tree);
        if ($exists) {
            $q = 'SELECT DISTINCT tree.id, tree.id_parent, tree.sort_order, item.name as name FROM '.$table_tree.' tree';
            $q.=' LEFT JOIN '.$table_data.' item ON (item.id = tree.id_parent) WHERE tree.id_item = ?';
            $result = $DB->prepared_query_line($q, 'i', [$id]);
            if ($result === false) {
                $result = null;
            }
        }

        return $result;
    }

    /**
     * Checks if the specified table has a data tree structure.
     * shortcut for module_has_data_tree with current module name
     * 
     * @param string $table Table name.
     * @return bool True if data tree exists, false otherwise.
     */
    public function has_data_tree($table)
    {
        return HelPHP_module::module_has_data_tree($this->module_name, $table);
    }
    /**
     * Checks if a module's table has a data tree structure.
     *
     * @param string $module Module name.
     * @param string $table Table name.
     * @return bool True if data tree exists, false otherwise.
     */
    public static function module_has_data_tree($module, $table)
    {
        global $DB;
        $table_tree = HelPHP_module::build_module_table_name($module, $table.HelPHP_module::hierarchy_suffix);
        return $DB->table_exists($table_tree);
    }

       /**
     * Generates a data tree for the specified table.
     * shortcut of module_data_tree with current module_name
     * 
     * @param string $table Table name.
     * @param string $display_field Field name to display,to identify the element.
     * @param int $id_parent Root parent ID. If this parameter is set to null, only the out of tree structure elements will be returned
     * @param bool $linear false -> the function returns a recursive tree, with the children contained in the key “children”
     *             true -> the function returns a tree in the form of a linear list, the recursion level is given by the key $this->indentation_key
     * @param array|false $ignore list of id to ignore in the request, will ignore corresponding elements and their children
     * @return array Data tree structure.
     */
    public function data_tree($table = 'data', $display_field = 'name', $id_parent = 0, $linear = false, $ignore = false) {
        return HelPHP_module::module_data_tree($this->module_name, $table, $display_field, $id_parent, $linear, $ignore);
    }

    public static function module_data_tree($module, $table = 'data', $display_field = 'name', $id_parent = 0, $linear = false, $ignore = false) {
        global $DB;
        
        $table_data = HelPHP_module::build_module_table_name($module, $table);
        $table_tree = HelPHP_module::build_module_table_name($module, $table.HelPHP_module::hierarchy_suffix);

        if (HelPHP_module::module_has_data_tree($module, $table)) {

            // tree
            if ($id_parent !== NULL) {

                $tree_list = HelPHP_module::recurse_tree($module, $table, $display_field, $id_parent, $linear, $ignore);

            } else {

                $tree_list = [];

                $q =  'SELECT DISTINCT '.$table_data.'.id, sort_order, '.$display_field.' FROM '.$table_data;
                $q .= ' LEFT JOIN '.$table_tree.' ON '.$table_tree.'.id_item='.$table_data.'.id';
                $q .= ' WHERE id_parent IS NULL ';
                $q .= ' ORDER BY sort_order';

                // items list out of tree
                $list1 = $DB->query_list($q);

                // items list of which the parent does not exist
                $q =  'SELECT DISTINCT '.$table_tree.'.id_item AS id, sort_order, '.$display_field.' FROM '.$table_tree.'';
                $q .= ' LEFT JOIN '.$table_data.' ON '.$table_data.'.id = '.$table_tree.'.id_parent';
                $q .= ' WHERE '.$table_data.'.id IS NULL AND id_parent<>0 ORDER BY sort_order, '.$display_field;

                $list2 = $DB->query_list($q);

                if (!is_array($list1)) {
                    $list1 = [];
                }
                if (is_array($list2)) {
                    $list1 = array_merge($list1, $list2);
                }

                $list = $list1;

                usort($list, function($a, $b) use ($display_field) {
                    return strtolower($a[$display_field]) <=> strtolower($b[$display_field]);
                });

                foreach ($list as $index=>$tmp) {
                    $id_parent = $tmp['id'];

                    if ($display_field == 'name'){
                        $tmp['name'] = Language::get_name($module.'_'.$table, $tmp['id']);
                    }

                    $sub = HelPHP_module::recurse_tree($module, $table, $display_field, $id_parent, $linear, $ignore, 1);
                    if (is_array($sub) && sizeof($sub)>0) {
                        if ($linear) {
                            array_push($tree_list, $tmp);
                            foreach ($sub as $s) {
                                array_push($tree_list, $s);
                            }
                        } else {
                            $tmp['children'] = $sub;
                            array_push($tree_list, $tmp);
                        }
                    } else {
                        array_push($tree_list, $tmp);
                    }


                }
            }

            return $tree_list;
        } else {
            $q = 'SELECT DISTINCT '.$table_data.'.id, '.$display_field.' FROM '.$table_data.' ORDER BY '.$display_field;
            $list = $DB->query_list($q);

            return $list;
        }
    }

    /**
     * Generates a data tree for use in a select input, separating out-of-tree items at the end of the list.
     * shortcut to module_data_tree_for_select with current module_name
     * 
     * @param string $table Table name.
     * @param string $display_field Field name to display.
     * @param int $id_parent Root parent ID.
     * @param array|null $options Optional. Additional options.
     * @return array Data tree for select.
     */
    public function data_tree_for_select($table = 'data', $display_field = 'name', $id_parent = 0, $options = null) {
        return  HelPHP_module::module_data_tree_for_select($this->module_name, $table, $display_field, $id_parent, $options);
    }
    /**
     * Generates a data tree for a module's table for use in a select input.
     *
     * @param string $module Module name.
     * @param string $table Table name.
     * @param string $display_field Field name to display.
     * @param int $id_parent Root parent ID.
     * @param array|null $options Optional. Additional options.
     * @return array Data tree for select.
     */
    public static function module_data_tree_for_select($module, $table = 'data', $display_field = 'name', $id_parent = 0, $options = null) {
        global $DB;

        $in_tree = HelPHP_module::module_data_tree($module, $table, $display_field, $id_parent, true);

        $table_tree = HelPHP_module::build_module_table_name($module, $table.HelPHP_module::hierarchy_suffix);
        $exists = $DB->table_exists($table_tree);
        if ($exists) {
            $out_of_tree = HelPHP_module::module_data_tree($module, $table, $display_field, null, true);

            if (sizeof($out_of_tree) > 0 && $id_parent !== null) {
                $tmp = ['optgroup'=>true];
                $tmp[$display_field] = '-----------------------------------';
                array_push($in_tree, $tmp);

                $in_tree = array_merge($in_tree, $out_of_tree);
            }
        }

        foreach ($in_tree as $index=>&$line) {
            if (is_array($options)) {
                if (isset($line['id'])) {
                    if (isset($options['disable']) && in_array($line['id'], $options['disable'])) {
                        $line['disabled']='disabled';
                    }

                    if (isset($options['remove_fields'])) {
                    }
                }
            }
        }

        return $in_tree;
    }
    /**
     * Recursively builds a tree structure for a module's table.
     *
     * @param string $module Module name.
     * @param string $table Table name.
     * @param string $display_field Field name to display.
     * @param int $id_parent Parent ID.
     * @param bool $linear If true, returns a linear list; else recursive tree.
     * @param array|false $ignore List of IDs to ignore.
     * @param int $level Recursion level.
     * @return array Tree structure.
     */
    public static function recurse_tree($module, $table, $display_field, $id_parent = 0, $linear = false, $ignore = false, $level = 0) {
        global $DB,$LANG;

        $table_data = HelPHP_module::build_module_table_name($module, $table);
        $table_tree = HelPHP_module::build_module_table_name($module, $table.HelPHP_module::hierarchy_suffix);

        $q =  'SELECT DISTINCT tree.id_item as id, tree.sort_order, item.'.$display_field;
        $q .= ' FROM '.$table_tree.' tree LEFT JOIN '.$table_data.' item ON (item.id = tree.id_item)';
        $q .= ' WHERE tree.id_parent='.$id_parent;
        if ($ignore) $q.= ' AND tree.id_item NOT IN ('.implode(',', $ignore).')';
        $q .= ' ORDER BY tree.sort_order';
        $result = $DB->query_list($q);
        if (is_array($result)) {
            $output = [];
            foreach ($result as $index=>$child) {
                $result[$index][HelPHP_module::indentation_key] = $level;
                
                if ($display_field == 'name'){
                    $result[$index]['name'] = Language::get_name($module.'_'.$table, $result[$index]['id']);
                }

                array_push($output, $result[$index]);

                $sub = HelPHP_module::recurse_tree($module, $table, $display_field, $child['id'], $linear, $ignore, $level + 1);
                if (is_array($sub) && sizeof($sub) > 0) {
                    if ($linear) {
                        foreach ($sub as $s) {
                            array_push($output, $s);
                        }
                    } else {
                        $result[$index]['children'] = $sub;
                    }
                }
            }
            if ($linear) {
                return $output;
            }
        }
        return $result;
    }
    
    //----------------------------------------------------------------------------------------------
    // Methods related to the structuring of the output and its publication. . .
    //----------------------------------------------------------------------------------------------    
    
    /**
     * Returns the HTML code to be displayed as output for this module.
     *
     * @return string HTML output.
     */
    public function get_output()
    {
        if ($this->posted_from_container || $this->dom_container === '') {
            // the container already exists, no need to create it
            
            if ($this->force_reload_delay > 0) {
                $this->display->add_child(H::script('setTimeout(H_dom.reload_page, '.intval($this->force_reload_delay).');', ['defer'=>true]));
            }
            
            return $this->add_json_output($this->display);
        } else {
            // the container must be created
            $parameters = ['content'=>$this->display];
            $output = $this->get_display_tree('module_container', $parameters);

            if ($this->init_script != '') {
                $output->add_after(H::script($this->init_script, ['defer'=>true , 'autoremove'=>true]));
            }
            
            if ($this->force_reload_delay > 0) {
                $output->add_child(H::script('setTimeout(H_dom.reload_page, '.intval($this->force_reload_delay).');', ['defer'=>true]));
            }

            return $this->add_json_output($output);
        }
    }
    /**
     * Adds JSON output data to the HTML output as a script
     * (after the events init script if there is some).
     *
     * @param mixed $output The HTML output.
     * @return string HTML output with JSON data.
     */
    private function add_json_output($output)
    {
        if (sizeof($this->error_list) > 0) {
            if (!is_array($this->json_output)) {
                $this->json_output = [];
            }
            $this->json_output['errors'] = $this->error_list;
        }

        if (sizeof($this->message_list) > 0) {
            if (!is_array($this->json_output)) {
                $this->json_output = [];
            }
            $this->json_output['messages'] = $this->message_list;
        }

        $output = ''.$output;
        global $event_lst;
        if ($event_lst){
            $js_str = '';
            foreach($event_lst as $key => $js){
                $js_str .= $js;
            }
            $output .= '<script>'.$js_str.'</script>';
            $event_lst = [];
        }

        if (is_array($this->json_output)) {
            if ($this->reload_after_message) {
                $this->json_output['reload_after_message'] = $this->reload_after_message;
            }

            $output .= '<script>setTimeout(function(){H_ajax_sender.process_json('.json_encode($this->json_output).');},200);</script>';
            return $output;
        } else {
            return $output;
        }
    }
    /**
     * Echoes the HTML code to be output for this module.
     *
     * @return void
     */
    public function echo_output()
    {
        if ($this->debug) {
            echo 'echo_output';
        }
        if (!HelPHP_module::$disable_output) {
            $html = ''.$this->get_output();
            echo $html;
        }
    }
    /**
     * Publishes the output, either integrating into core content or echoing directly.
     *
     * @return void
     */
    public function publish_output()
    {
        if ($this->debug) {
            echo 'publish_output coreinsert'.$this->core_insert;
        }
        
        if ($this->core_insert) {
            global $module_html_content;
            if (!isset($module_html_content)) {
                $module_html_content = [];
            }
            if (!isset($module_html_content[$this->module_name])) {
                $module_html_content[$this->module_name] = '';
            }
            $module_html_content[$this->module_name] .= $this->get_output();
        } else {
            $this->echo_output();
        }
    }
    /**
     * Generates the display tree structure for the module container.
     *
     * @param string $name Name of the display tree.
     * @param mixed $data Data for the tree.
     * @return mixed Display tree object.
     */
    public function get_display_tree($name, &$data)
    {
        //create the root of the object that will most often be the div of the container
        if (!isset($this->display_root)){
            $objTree = [];
            $objTree['tag'] = H::DIV;
            $objTree['attributes'] = [];
            $objTree['attributes']['id'] = $this->dom_target;
            $objTree['attributes']['class'] = $this->dom_container;
            $objTree['attributes']['data-current_id'] = $this->current_id;
            $objTree['sub'] = '$content';
        }else{
            $objTree=$this->display_root;
        }
        if ($objTree!==false) {
            return $this->recurse_display_tree($objTree, $data);
        }

        return false;
    }
    /**
     * Recursively builds the display tree structure.
     *
     * @param mixed $obj Tree object or array.
     * @param mixed $data Data for the tree.
     * @return mixed Display tree object.
     */
    private function recurse_display_tree($obj, &$data)
    {
        // $obj is a tag
        if (is_array($obj) && isset($obj['tag'])) {

            $attr = null;
            if (isset($obj['attributes'])) {
                $attr = $obj['attributes'];
            }

            $output = H::tag($obj['tag'], $attr);

            if (isset($obj['sub'])) {
                $output->add_child($this->recurse_display_tree($obj['sub'], $data));
            }
        } else {
            if (is_array($obj)) {
                $output = H::group('sub');
                foreach ($obj as $key=>$value) {
                    if (is_array($value)) {
                        if (isset($value['tag'])) {
                            $value = $this->recurse_display_tree($value, $data);
                        } else {
                            foreach ($value as $v) {
                                $output->add_child($this->recurse_display_tree($v, $data));
                            }
                            $value = null;
                        }
                    } elseif ($value == '_') {
                        $value = H::tag(H::HR);
                    } elseif ($value == ';') {
                        $value = H::tag(H::BR);
                    } elseif (substr($value, 0, 1)=='$') {
                        $k = ltrim($value, '$');
                        if (isset($data[$k])) {
                            $value = $data[$k];
                        }
                    }

                    if ($value !== null) {
                        $output->add_child($value);
                    }
                }
            } else {
                if ($obj == '_') {
                    $obj = H::tag(H::HR);
                } elseif ($obj == ';') {
                    $obj = H::tag(H::BR);
                } elseif (substr($obj, 0, 1)=='$') {
                    $k = ltrim($obj, '$');
                    if (isset($data[$k])) {
                        $obj = $data[$k];
                    }
                }

                $output = $obj;
            }
        }

        return $output;
    }

    /**
     * Integrates another module's HTML output into the current module when no Hcode possible
     * be careful, this function use the HTLM 4 Parser, but have been improved to support most of html5
     * https://www.php.net/manual/en/domdocument.loadhtml.php 
     *
     * @param string $data HTML data containing module references.
     * @return string Modified HTML with integrated modules.
     */
    public function integrate_module($data)
    {
        global $module_html_content, $USER;
        
        if (isset($data) && strstr($data, 'data-module_params')) {

            $Dom = new \DOMDocument('1.0', 'utf-8');

            $data = mb_convert_encoding($data, 'HTML-ENTITIES', "UTF-8");
            $test = $Dom->loadHTML($data, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            if ($test) {
                $div = $Dom->getElementsByTagName('div');

                foreach ($div as $elem) {
                    if ($elem->hasAttribute('data-module') && $elem->hasAttribute('data-module_params')) {

                        $moduleName = $elem->getAttribute('data-module');
                        $moduleParams = json_decode(str_replace("'", '"', $elem->getAttribute('data-module_params')));

                        $_POST = [];
                        $_POST['core_insert'] = true;
                        foreach ($moduleParams as $key => $val) {
                            $_POST[$key] = $val;
                        }
                        $module_html_content[$moduleName] = '';
                        include($CONFIG::HOME_FOLDER.'public/'.$moduleName.'/index.php');

                        if (isset($module_html_content[$moduleName])) {
                            $newElem = new \DOMDocument();
                            @$newElem->loadHTML($module_html_content[$moduleName], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                            $test = $newElem->firstChild;
                            $test = $Dom->importNode($test, true);
                            $elem->appendChild($test);
                        }
                    }
                }

                $newData = $Dom->saveHTML();
                return $newData;
            }
        } else {
            return $data;
        }
    }
    /**
     * Parses Hcode tags in HTML and replaces them with module outputs.
     * The Hcode is simple, it's a string indicating the module and params to execute, then the result
     * is inclued inside the current one.
     * exemple : [module|action=display&id=1] 
     * will call the module and request post with action = display & id=1.
     * the html module code with be parsed / returned to the current one and display at the place was the hcode.
     *
     * @param string $str_html HTML string containing Hcode tags.
     * @param boolean $admin indicates if the loaded module need to be from admin side
     * @return string HTML with parsed Hcode.
     */
    public function parse_hcode($str_html = '', $admin = false)
    {
        global $module_html_content,$CONFIG;
        if ($str_html != '') {
            $old_post = $_POST;
            $start = explode('[', $str_html);
            $output = '';
            foreach ($start as $key => $tt) {
                if ($key != 0) {
                    if (strpos($tt, ']')) {
                        $hcode = explode(']', $tt)[0];
                        $exploded_hcode = explode('|', $hcode);
                        $moduleName = $exploded_hcode[0];
                        if (isset($CONFIG::MODULES_LIST[$moduleName]) && $moduleName != 'core') {
                            $_POST = [];
                            $_POST['core_insert'] = true;
                            
                            $moduleParams = isset($exploded_hcode[1]) ? $exploded_hcode[1] : '';
                            $moduleParams = trim($moduleParams);
                            if ($moduleParams != '') {
                                $moduleParams = html_entity_decode($moduleParams);
                                $moduleParams = explode('&', $moduleParams);
                                foreach ($moduleParams as $ind => $params) {
                                    $params = explode('=', $params);
                                    if (isset($params[1])) {
                                        $_POST[$params[0]] = $params[1];
                                    }
                                }
                            }

                            $path = isset($exploded_hcode[2]) ? $exploded_hcode[2] : $CONFIG::HOME_FOLDER;
                            if ($admin) $path.= $CONFIG::ADMIN_FOLDER;
                            else $path.= 'public/';
                            $path.= $moduleName.'/index.php';
                            
                            $module_html_content[$moduleName] = '';
                            include($path);
                            //~ UTILS_Class::error_log($module_html_content[$moduleName]);
                            $output .= $module_html_content[$moduleName];
                            $output .= explode(']', $tt)[1];
                        } else {
                            $output .= '['.$tt;
                        }
                    } else {
                        $output .= '['.$tt;
                    }
                } else {
                    $output .= $tt;
                }
            }
            $_POST = $old_post;
            return $output;
        }
        return $str_html;
    }

    //----------------------------------------------------------------------------------------------
    //Methods related to translation inputs...
    //----------------------------------------------------------------------------------------------
    /**
     * Generates a translation block with inputs for multiple languages.
     *
     * @param array &$post Reference to the POST data array.
     * @param array $fields List of field names.
     * @param string $types String of types ('s' for short, 'l' for long).
     * @param array|false $attributes Optional. Additional attributes.
     * @return mixed Translation block object.
     */
    public function translate_block(&$post, $fields, $types, $attributes = false)
    {
        global $LANG, $CONFIG;

        // retrieve all the langs
        $languages = $LANG->get_languages_data();

        $output = H::DIV(['class'=>'hlp_translate_block']);

            // create an array containing all the fields
            // the first value in this array is the label of the field
            $inputs = [];
            foreach ($fields as $index => $field_name) {
                $type = substr($types, $index, 1);
                $params = [];
                $params['name'] = $field_name;

                // retrieve base name
                if (strpos($field_name, '[') !== false) {
                    $t = explode('[', $field_name);
                    $id = substr($t[1], 0, -1);
                    $params['name'] = $t[0];
                }
                
                // retrieve table name to later compose field_id 
                $table_name = explode('-', $params['name'])[0];

                // add special attributes
                if ($attributes && isset($attributes[$index])) {
                    foreach ($attributes[$index] as $attr => $val) {
                        $params[$attr] = $val;
                    }
                }

                if ($type=='s') { // short input

                    $params['class'] = $this->css.'_wide_input';
                    if (isset($id)) {
                        $inputs[$field_name] = $this->input_short_translation($params, $id, $post);
                    } else {
                        $inputs[$field_name] = $this->input_short_translation($params, $post[$table_name.'-id'], $post);
                    }

                } else { // long input (tinymce)

                    $params['tinymce'] = array('width'=>'100%', 'height'=>'200px', 'images_upload_url'=>$this->get_index_relative_path());
                    if (isset($params['no_tiny']) && $params['no_tiny']) {
                        unset($params['no_tiny']);
                        unset($params['tinymce']);
                    }
                    if (isset($id)) {
                        $inputs[$field_name] = $this->input_long_translation($params, $id, $post);
                    } else {
                        $inputs[$field_name] = $this->input_long_translation($params, $post[$table_name.'-id'], $post);
                    }

                }
            }

            $lang_count = sizeof($languages);
            $lang_translatable = 0;

            $selected_lang = isset($post['hlp_translate_selected_lang']) ? $post['hlp_translate_selected_lang'] : $LANG->current_language;
            if ($lang_count > 1) {
                $selector = H::DIV(['class'=>'hlp_translate_selectors']);
                $selector->add_child( H::input_hidden(['name'=>'hlp_translate_selected_lang', 'value'=>$selected_lang]) );
                $output->add_child($selector);
                $lang_tab = [];
            }

            foreach ($languages as $i => $data) {

                if ($data['libretranslate'] != '') $lang_translatable++; 

                // the js function 'H_ui.translate_block.change_lang()' is find in ui.js
                if ($lang_count > 1) {
                    $class = ($selected_lang == $data['iso']) ? ' selected' : '';
                    $selector->add_child(H::DIV(['class'=>'hlp_translate_selector '.$class , 'data-iso'=>$data['iso'], 'onclick'=>'H_ui.translate_block.change_lang(event);'], $data['own'].' ('.$data['iso'].')'));
                    array_push($lang_tab, $data['iso']);
                }

                $class = ($selected_lang == $data['iso']) ? ' selected' : ' hidden';
                $bloc = H::DIV(['class'=>'hlp_translate_input'.$class, 'data-iso'=>$data['iso'], 'data-coid'=>$data['id_data'] ]);

                $bloc->add_child( H::DIV(['class'=>'hlp_translate_current'], $data['label'].' ('.$data['iso'].')'));

                // $table = H::TABLE();
                // $table_body = H::TBODY();
                // $table->add_child($table_body);
                // $bloc->add_child($table);

                // $index = 0;
                foreach ($inputs as $field_name => $inputfield) {

                    $child = $inputfield->children[$data['iso']];
                    $bloc->add_child( $child );
                    // $type = substr($types, $index, 1);

                    // if ($type == 'l') {

                        // $bloc->add_child( [$inputfield->children[0], $child] );

                        // $tr = H::TR();

                        // $tr->add_child(H::TD(['class'=>$this->css.'td_description_0'], $inputfield->children[0]));
                        // $tr->add_child(H::TD(['class'=>$this->css.'td_description_'.$data['iso']], $child));
                        // $table_body->add_child($tr);
                    // } else {
                        
                        // $table_body->add_child(H::table_row([$inputfield->children[0] , $child ]));
                    // }
                    // $index++;
                }

                $output->add_child($bloc);
            
            }

            if($CONFIG::LIBTRANSLATE_URL != '' && $lang_translatable > 1){
                $btn_libretranslate = H::BUTTON(['class'=>$this->css.'btn_translate', 'onclick'=>'H_ui.translate_block.translate(event);'], $this->get_tl('tlc_translate'));
                $output->add_child($btn_libretranslate);
            }

        return $output;
        //------------------------------------------------------------------
    }
    
    /**
     * Creates translation inputs for a short text field.
     *the first value of the array is the label of the field

     * @param array $attributes Input attributes.
     * @param int $id Item ID.
     * @param array &$post Reference to the POST data array.
     * 
     * @return mixed Input field object.
     */
    public function input_short_translation($attributes, $id, &$post)
    {
        if (!isset($attributes['value'])) {
            $attributes['value'] = Language::extract_translation($post, $attributes['name'], $id);
        }

        if (!isset($attributes['label'])) $attributes['label'] = $this->get_tl($attributes['name']);

        $fields = H::_input_translation($attributes, $id, true);

        // if (!isset($attributes['label'])) $attributes['label'] = $attributes['name'];
        // if (isset($attributes['label'])) {
            // $label = $attributes['label'];
        // } else {
            // $label = $this->get_tl($attributes['name']);
        // }

        // array_unshift($fields->children, $fields->label_tag());

        return $fields;
    }

    /**
     * Creates translation textarea for a long text field.
     * the first value of the array is the label of the field
     * 
     * @param array $attributes Input attributes.
     * @param int $id Item ID.
     * @param array &$post Reference to the POST data array.
     * @return mixed Textarea field object.
     */
    public function input_long_translation($attributes, $id, &$post)
    {
        if (!isset($attributes['value'])) {
            $attributes['value'] = Language::extract_translation($post, $attributes['name'], $id);
        }
        
        if (isset($attributes['tinymce'])) {
            if (!isset($attributes['tinymce']['css_module'])) {
                $attributes['tinymce']['css_module'] = $this->module_name;
            }
            if (!isset($attributes['tinymce']['css_module_main'])) {
                if ($attributes['tinymce']['css_module'] == 'deco') {
                    $name = $post['deco_data-name'];
                    $attributes['tinymce']['css_module_main'] = '.deco_public_container_'.$name;
                }
            }
        }

        $fields = H::_input_translation($attributes, $id, false);
        if (isset($attributes['label'])) {
            $label = $attributes['label'];
        } else {
            $label = $this->get_tl($attributes['name']);
        }

        array_unshift($fields->children, $label);

        return $fields;
    }

    /**
     * Returns the directory of the current class file.
     *
     * @return string Directory path.
     */
    protected function get_class_dir()
    {
        $reflector = new \ReflectionClass(get_class($this));
        return dirname($reflector->getFileName());
    }
    /**
     * Returns the directory of the parent class file.
     *
     * @return string Directory path.
     */
    protected function get_parent_class_dir()
    {
        $reflector = new \ReflectionClass(get_parent_class($this));
        return dirname($reflector->getFileName());
    }

    /**
     * Loads translation files for the module.
     *
     * @param string|false $module_path Optional. Path to the module.
     * @return void
     */
    public function load_translation_files($module_path)
    {
        global $LANG;
        $LANG->load_translation_files($this->module_name,$this->admin,false,$module_path);
    }

    
    /**
     * Returns the translated text for a given key, with optional replacements and pluralization.
     *  * return the identified text, or the identifier between braces if no text exists for this identifier
     * $replace can contain a word or an array of words
     * each word will replace in order the numbered markers preceded by $ present in the text
     * $count_for_singular  is used if the phrase needs to be converted to the plural or not based on a quantity.
     * in this case :
     * $key is plural (so $count_for_singular > 1)
     * $key.'__none' matches none (so $count_for_singular == 0)
     * $key.'__singular' matches the singular (i. e. $count_for_singular == 1)
     *
     *  variants with the suffixes ’__none' and ’__singular' must therefore exist in the tl_ file
     *
     * @param string $key Translation key.
     * @param mixed|null $replace Optional. Replacement values.
     * @param int|null $count_for_singular Optional. Quantity for pluralization.
     * @param bool $array Optional. Return as array.
     * @return string|array Translated text.
     */
    public function get_tl($key = '', $replace = null, $count_for_singular = null, $array = false) {
        global $LANG;
        return $LANG->get_tl($this->module_name, $key, $replace, $count_for_singular, $array);
    }
    
    /**
     * returns an array with all identified texts, or the identifier in brackets if no text exists for this identifier
     * $list is an array that contains another array like this:
     * ['key'=>'string identifier' , 'replace'=>[...] ]
     * replace can contain a word or an array of words
     * each word will replace in order the numbered markers preceded by $ present in the text=
     * if $join is specified, the result will be a string concatenated with this text instead of an array
     *
     * @param array $list List of translation keys and replacements.
     * @param string|null $join Optional. Join string for concatenation.
     * @return array|string Translated texts.
     */
    public function get_multiple_tl($list, $join = null) {
        global $LANG;
        return $LANG->get_multiple_tl($this->module_name, $list, $join);
    }
    /**
     * Returns translated field names for a table.
     *
     * @param string $table Table name.
     * @param array $fields List of field names.
     * @return array Translated field names.
     */
    public function get_translated_table_fields($table, $fields) {
        global $LANG;
        return $LANG->get_translated_table_fields(self::module_name,$table,$fields);
    }
    /**
     * Gets a translated text from another module.
     *
     * @param string $module Module name.
     * @param bool $admin Whether to use admin translations.
     * @param string $key Translation key.
     * @param mixed|null $replace Optional. Replacement values.
     * @param int|null $count_for_singular Optional. Quantity for pluralization.
     * @return string Translated text.
     */
    public function get_translated_text_from_other_module($module = '', $admin = false, $key = '', $replace = null, $count_for_singular = null){
        global $LANG;
        return $LANG->get_translated_text_from_other_module($module, $admin, $key, $replace, $count_for_singular);
    }

    /**
     * Validates and saves a quick edit update for a field.
     *
     * @param int $id Item ID.
     * @param mixed $value New value.
     * @param string $type_index Type index string.
     * @param string $field_data Encrypted field data.
     * @param int $id_lang_data Optional. Language data ID.
     * @return mixed Result of the update operation.
     */
    public function quick_edit_update($id, $value, $type_index, $field_data, $id_lang_data = 0)
    {
        global $DB,$CRYPT;

        $data = $CRYPT->decrypt($field_data);
        $type_array = explode(',', $type_index);
        $modules = array_keys($data);
        $m = $data[$modules[$type_array[0]]];
        $module_name=$modules[0];
        $tables = array_keys($m);
        $table_name = $tables[$type_array[1]];
        $t = $m[$tables[$type_array[1]]];

        $fields = array_keys($t);
        $field_name = $fields[$type_array[2]];
        $type = $t[$field_name];

        if (intval($id_lang_data) > 0) {
            if ($type == 'textarea') {
                return Language::save_long_translation_value($this->build_field_name($table_name, $field_name), $id, $value, $id_lang_data);
            } else {
                return Language::save_short_translation_value($this->build_field_name($table_name, $field_name), $id, $value, $id_lang_data);
            }
        } else {
            $t = '';

            switch ($type) {
                case 'int':
                case 'integer':
                    $t ='i';
                break;

                case 'float':
                    $t = 'd';
                break;

                default:
                    $t = 's';
                break;
            }

            $table = $this->build_module_table_name($module_name,$table_name);

            $q = 'UPDATE '.$table.' SET `'.$field_name.'`=? WHERE id=?';

            $t .= 'i';

            return $DB->prepared_query($q, $t, [$value , $id]);
        }
    }

    /**
     * Returns the relative path to the module's index page (public or admin).
     *
     * @return string Relative path to index.php.
     */
    public function get_index_relative_path()
    {
        global $CONFIG;
        if ($this->admin) {
            return $CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.$this->module_name.'/index.php';
        } else {
            return $CONFIG::BASE_URL.'public/'.$this->module_name.'/index.php';
        }
    }
    /**
     * Handles TinyMCE file uploads for the module.
     * @see libs/Tinymce.php 
     *
     * @return void
     */
    public function receive_tinymce_file()
    {
        GLOBAL $CONFIG;
        if($CONFIG::TINYMCE_UPLOAD == true) {
            include_once('Tinymce.php');
            Tinymce::receive_file('public/'.$this->module_name.'/images/');
        }
        exit;
    }
    /**
     * Handles autocomplete requests for form fields.
     *
     * @param array &$post Reference to the POST data array.
     * @return mixed Autocomplete result object.
     */
    public function autocomplete(&$post) {
        // Utils::error_log($post);
        if(isset($post['centraldb'])) {
            global $DB_CENTRAL;
            $TDB = $DB_CENTRAL;
        } else {
            global $DB;
            $TDB = $DB;
        }
        $post['value'] = addslashes($post['value']);

        $q_params = [];
        $q_indic = '';
        if ($post['table'] == 'users_data') {
            $q = 'SELECT DISTINCT id, CONCAT(firstname, " ", lastname) as name FROM '.$TDB->table($post['table']).' WHERE `firstname` LIKE ? OR `lastname` LIKE ? OR CONCAT(firstname, " ", lastname) LIKE ?';
            $q_indic.= 'sss';
            array_push($q_params, '%'.$post['value'].'%', '%'.$post['value'].'%', '%'.$post['value'].'%');
        } else {
            $q = 'SELECT DISTINCT id, '.$post['fields'].' as name FROM '.$TDB->table($post['table']).' WHERE `'.$post['fields'].'` LIKE ?';
            $q_indic.= 's';
            array_push($q_params, '%'.$post['value'].'%');

        }
        $q.= ' ORDER BY name LIMIT ?';
        $q_indic.= 'i';
        array_push($q_params, 20);
        $results = $TDB->prepared_query_list($q, $q_indic, $q_params);

        if ($results){
            $output = H::group('autocomplete_result');
            $val = stripslashes($post['value']);
            foreach($results as $key => $line){
                // bold the searched characters
                $label_modified = str_replace($val, '<b>'.$val.'</b>', $line['name']);
                $label_modified = str_replace(ucfirst($val), '<b>'.ucfirst($val).'</b>', $label_modified);
                $output->add_child( H::DIV(['class'=>'hlp_autocomplete_row', 'data-id'=>$line['id'], 'data-text'=>$line['name']], $label_modified) );
            }

            return $output;
        }

        return H::DIV(['data-id'=>0, 'data-text'=>''], $this->get_tl('tlc_no_result_autocomplete'));
    }

}
