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
 * @class Language
 * 
 * The language class is a really important componant as it permit to make multi-language UI easily and 
 * to create real different user context. 
 * When you have to manage users, you have to think about how to adapt your display to usual aspects of life for the user :
 * Does he read from left to right ? top to bottom ?
 * Does a white background have the same meaning in all culture ? 
 * How to develop one app for everyone and adapt its display not only to the screen size (responsive design) but also to its culture.
 * 
 * First how to get some clue about user attemps ? Geolocalisation is not important, the user can be a traveler or using a proxy.
 * The only clue is the language ! The navigator is in general set in the language of the user or in english for common professional
 * purpose (like computing / dev ;) ). 
 * So first HelPHP detect the navigator language and if it's available in the app will autoswitch to it.
 * If not, it will go the default language.  
 * 
 * As you'll be able to edit your content depending the language, you'll be able to adapt your display.
 * 
 * Some tools are available working around this class :
 * - the language module to manage them, and in its public part the showflags method that will display flags to enforce a language.
 *   Note that in any resquest if you add language=iso (iso code like en fr de ...) it will switch to the corresponding language.
 * - the translate block in HelPHP_module create automaticaly input forms that are editable in multi language and saved in the DB.
 * - the save_ and load_translation in HelPḦP_module will automate their saving loading
 * - the get_tl method etc... you'll find in this class will help manage every aspect
 * - the uitranslate module will help you translate your module UI and will create all the translations files (tl_xxx) next to your module files. 
 * - a connection to libretranslate in uitranslate and the translateblock will automate the translation in 36 languages :
 *   "fr","en", "de", "es", "ar", "fi", "ga", "hi", "hu", "id", "it", "ja", "ko", "pt", "pt", "sv", "uk", "bg", "bn", "ca", "cs", "da", "el", "az", "eo", "et", "fa", "he"
 * - in your instance, in the tests folder, you find a translate.php test page
 * - The indexation module will help you to create multilingual sitemap index, and it's automated from the document editor
 * 
 * Init.php will call create_instance method that will init the $LANG global to offer access to all method and states of this instancied class.   
 *  
 * @package helPHP\libs
 * 
 */
class Language {
    /**
     * USeful var to make run a piece of in a different context so in another language
     * practical case : when you preview a public page in its default language in the backoffice set with your language
     * so it's used by default to make the difference between front and back UI.
     *
     * @var string
     */
    public static $context = '';

    /**
     * language_identifier used to identify request to switch the current language
     *
     * @var string
     */
    const language_identifier = 'language';

    const session_language_identifier = 'language';

    const id_attribute = 'id_lang_data';

    /**
     * tl_short translation field type constants for short inputs
     *
     * @var string
     */
    const tl_short = 'short';
     /**
     * tl_short translation field type constants for long inputs
     *
     * @var string
     */
    const tl_long = 'long';

    // name of the language management module (for database access)
    const lang_module_name = 'languages';

    // prefixes corresponding to the types of translation fields
    const short_translation_prefix = 'shrt_trnsl';
    const long_translation_prefix = 'lng_trnsl';

    // array associating types and prefixes
    const translation_prefix = [
        Language::tl_short    => Language::short_translation_prefix,
        Language::tl_long     => Language::long_translation_prefix
    ];

    public $current_language = '';
    public $current_id_data = '';

    public function __construct($new_context = '') {
        Language::$context = $new_context;

        $identifier = Language::session_language_identifier;

        // retrieves the language stored in session
        $this->current_language = (isset($_SESSION[$identifier.Language::$context]))?$_SESSION[$identifier.Language::$context]:'';

        // attribute the specified language in the posted data
        if (isset($_REQUEST[$identifier]) && $this->validate_language($_REQUEST[$identifier])) {
            $this->current_language = $_REQUEST[$identifier];
        }

        // if the current language is not valid, attempt to retrieve the browser language
        if (!$this->validate_language($this->current_language) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $tmp = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $this->current_language = strtolower(substr(chop($tmp[0]), 0, 2));
        }

        // validation of the language so that it corresponds to an authorized language
        $this->current_language = $this->set_language_iso($this->current_language);

        // storing the value of the current language in a session variable
        $_SESSION[$identifier.Language::$context]=$this->current_language;
    }
    
    /**
     * Call the construct to create the global instance $LANG
     *
     * @return global $LANG
     * 
     */
    public static function create_instance($context='') {
        global $LANG;
        if (!$LANG) {
            $LANG = new Language($context);
        }
        return $LANG;
    }
    
    /**
     * Create a different context to use another language in sz
     *
     * @param string $new_context
     * 
     * @return void
     * 
     */
    public function set_context($new_context = '') {
        if (Language::$context != $new_context) {
            Language::$context = $new_context;
            $this->update_session();
        }
    }

    /**
     * Update_session if some vars of the instance should be copied in the session
     *
     * @return void
     * 
     */
    public function update_session() {
        $identifier =  Language::session_language_identifier;

        // retrieves the language stored in session
        $this->current_language = $_SESSION[$identifier.Language::$context];

        // validation of the language so that it corresponds to an authorized language
        $this->current_language = $this->set_language_iso($this->current_language);

        // storing the value of the current language in a session variable
        $_SESSION[$identifier.Language::$context]=$this->current_language;
    }

    /**
     * Switch the current language depending iso
     *
     * @param mixed $iso
     * 
     * @return string current language iso
     * 
     */
    public function set_language_iso($iso) {
        global $DB;

        $this->current_language = $this->get_available_language($iso);

        $table_data = $DB->table(Language::lang_module_name.'_data');

        $data = $DB->query_line('SELECT * FROM `'.$table_data.'` WHERE iso="'.$this->current_language.'"');

        if ($data) {
            $this->current_id_data = $data['id'];
        } else {
            Utils::error_log('mauvaise identification de la langue');
        }

        return $this->current_language;
    }

    /**
     * It send calls to libretranslate (can be a local docker container).
     * URL and api_key of libretranslate are set in the main config.
     *
     * @param string $text original to translate
     * @param string $format json 
     * @param string $iso_original 
     * @param array $iso_targets=[] of iso to translate to.
     * 
     * @return array key pairs iso=>translation
     * 
     */
    public static function translate($text,$format,$iso_original,$iso_targets=[]) {
         // translation with libretranslate local docker container
        global $CONFIG;
        $translated=[];
        foreach($iso_targets as $target){
            $data = ['q'=>stripslashes($text),'source'=>$iso_original,'target'=>$target,'format'=>$format,'api_key'=>$CONFIG::LIBTRANSLATE_APIKEY];
            //no header ! it's json !
            $header = [];
            // $header = ['Content-Type'=>'application/x-www-form-urlencoded'];
            $apiRest = new Rest($CONFIG::LIBTRANSLATE_URL,'POST','translate',$data,$header,false,true,false,false,true);
            $translated[$target] = $apiRest->client_request();
        }
        return $translated;
    }

    /**
     * checks that the language specified in parameter is authorized / available 
     *
     * @param string $lang
     * 
     * @return bool true false
     * 
     */
    public function validate_language($lang) {
        global $CONFIG;
        return in_array(strtolower($lang), $CONFIG::AVAILABLE_LANGUAGES);
    }

    /**
     * doing nearly same job as validate_language() but will return an iso, the default one if the iso tested do not exist.
     *
     * @param string $lang
     * 
     * @return string iso same as $lang if available or the default one .
     * 
     */
    public function get_available_language($lang = '') {
        global $CONFIG;
        if ($lang == '') {
            $lang = $CONFIG::DEFAULT_LANGUAGE;
        }

        $index = array_search(strtolower($lang), $CONFIG::AVAILABLE_LANGUAGES);
        if ($index === false) {
            return $CONFIG::AVAILABLE_LANGUAGES[0];
        } else {
            return $CONFIG::AVAILABLE_LANGUAGES[$index];
        }
    }

    // retrieves basic information of the authorized languages for the site
    // for each language, we will have:
    //
    // id_data = id in languages_data
    // iso = language code on 2 letters (except in Chinese where it can go up to 7)
    // label = name of the language in full, in English
    // own = name of the language, in its native script
    //
    /**
     * retrieves basic information of the authorized languages
     *
     * @return array for each language we'll have: 
     *               id_data = id in languages_data
     *               iso = language code on 2 letters (except in Chinese where it can go up to 7)
     *               label = name of the language in English
     *               own = name of the language, in its native 
     */
    public function get_languages_data() {
        global $DB;

        $table_allowed = $DB->table(Language::lang_module_name.'_allowed');
        $table_data = $DB->table(Language::lang_module_name.'_data');

        $q = 'SELECT DISTINCT a.id_data, a.id, d.iso, d.en AS label, d.own_language AS own, d.libretranslate';
        $q.= ' FROM '.$table_allowed.' a';
        $q.= ' LEFT JOIN '.$table_data.' d ON a.id_data = d.id';
        $q.= ' ORDER BY a.id';

        $result = $DB->prepared_query_list($q);

        return $result;
    }

    /**
     * create a translation input name to display in a form
     *
     * @param string $name identification name of the field, in the format "module_table-field"
     * @param int $id id of the entry in the table
     * @param int $lang_id id of the language, taken from the languages_data table
     * @param string $type type of field, to be taken from the different types defined as constants in the language class (short/long)
     * 
     * @return [type]
     * 
     */
    public static function create_translation_name($name, $id, $lang_id, $type) {
        if (!isset(Language::translation_prefix[$type])) {
            Utils::error_log('WARNING : '.$type.' is not a valid type for a translation field (Language.php)');
            $type = Language::short_translation_prefix;
        }

        return Language::translation_prefix[$type].'['.$name.']['.$id.']['.$lang_id.']';
    }

    /**
     * retrieves the posted translation data corresponding to the specified field
     *
     * @param array $post
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id id of the entry in the database
     * 
     * @return string value
     * 
     */
    public static function extract_translation(&$post, $field_identifier, $id) {
        $value = null;
        foreach (Language::translation_prefix as $type=>$prefix) {
            if (isset($post[$prefix])) {
                $tmp = &$post[$prefix];
                if (isset($tmp[$field_identifier])) {
                    if (isset($tmp[$field_identifier][$id])) {
                        $value = $tmp[$field_identifier][$id];

                        foreach ($value as $id_data=>$text) {
                            $value[$id_data] = stripslashes(str_replace('\\r\\n', "\r\n", $text));
                        }
                    }
                }
            }
        }
        return $value;
    }

    /**
     * filling $post with translation data from the database
     * it's in general used for admin side because it's loading all language data for editing.
     *
     * @param array $post current $post
     * @param string $module module name
     * @param string $table table name
     * @param int|array|string $filter selection filter, can be an id, a array list of ids or a string containing a mysql subquery
     * 
     * @return void it's filling $post
     * 
     */
    public static function load_translation_data(&$post, $module, $table, $filter = null) {
        global $DB;

        foreach (Language::translation_prefix as $type=>$prefix) {
            if (!isset($post[$prefix])) {
                $post[$prefix] = [];
            }

            $tbl = $DB->table_prefix.'_languages_'.$type;

            $field_identifier = HelPHP_module::build_module_field_name($module, $table, '%');

            $q = 'SELECT DISTINCT * FROM '.$tbl;
            $q .= ' WHERE field_identifier LIKE "'.$field_identifier.'" ';

            if (is_int($filter)) {
                $q.=' AND id_item = '.intval($filter);
            } elseif (is_array($filter)) {
                $q.=' AND id_item IN ('.implode(',', $filter).')';
            } elseif (is_string($filter)) {
                $q.=' AND id_item IN ('.$filter.')';
            }

            $q .=' ORDER BY id_item , field_identifier , id_data';

            $data_list = $DB->query_list($q);

            if (is_array($data_list)) {
                foreach ($data_list as $data) {
                    if (!isset($post[$prefix][$data['field_identifier']])) {
                        $post[$prefix][$data['field_identifier']] = [];
                    }

                    if (!isset($post[$prefix][$data['field_identifier']][$data['id_item']])) {
                        $post[$prefix][$data['field_identifier']][$data['id_item']] = [];
                    }

                    $post[$prefix][$data['field_identifier']][$data['id_item']][$data['id_data']] = $data['value'];
                }
            }
        }
    }
    /**
     * filling $post with translation data from the database, working like load_translation_data but
     * it's in general used for public side because it's loading only current language data for display.
     *
     * @param array $post current $post
     * @param string $module module name
     * @param string $table table name
     * @param int|array|string $filter selection filter, can be an id, a array list of ids or a string containing a mysql subquery
     * 
     * @return void it's filling $post
     * 
     */
    public static function load_public_translation_data(&$post, $module, $table, $filter = null) {
        global $DB,$LANG;

        foreach (Language::translation_prefix as $type=>$prefix) {
            if (!isset($post[$prefix])) {
                $post[$prefix] = [];
            }

            $tbl = $DB->table_prefix.'_languages_'.$type;

            $field_identifier = HelPHP_module::build_module_field_name($module, $table, '%');

            $q = 'SELECT DISTINCT * FROM '.$tbl;
            $q .= ' WHERE field_identifier LIKE "'.$field_identifier.'" and id_data = '.intval($LANG->current_id_data).' ';

            if (is_int($filter)) {
                $q.=' AND id_item = '.intval($filter);
            } elseif (is_array($filter)) {
                $q.=' AND id_item IN ('.implode(',', $filter).')';
            } elseif (is_string($filter)) {
                $q.=' AND id_item IN ('.$filter.')';
            }

            $q .=' ORDER BY id_item , field_identifier , id_data';

            $data_list = $DB->query_list($q);

            if (is_array($data_list)) {
                foreach ($data_list as $data) {
                    if (!isset($post[$data['field_identifier']])) {
                        $post[$data['field_identifier']] = $data['value'];
                    }
                }
            }
        }
    }

    /**
     * Save a long translation value
     *
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id in the db
     * @param string $value content to save
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return bool true if ok
     * 
     */
    public static function save_long_translation_value($field_identifier, $id, $value, $id_lang_data = 0) {
        global $DB,$LANG;

        $_sgbd = $DB;
        $tbl = $DB->table_prefix.'_languages_'.Language::tl_long;


        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }

        $q = 'SELECT DISTINCT COUNT(*) FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        $q.=' AND field_identifier=?';
        $q.=' AND id_data = '.intval($id_lang_data);
        $exists = $_sgbd->prepared_query_value($q, 's', [$field_identifier]);

        if (!$exists) {
            $q = 'INSERT INTO '.$tbl;
            $q.= ' SET value=? ';
            $q.=' , id_item='.intval($id);
            $q.=' , field_identifier=?';
            $q.=' , id_data='.intval($id_lang_data);
        } else {
            $q = 'UPDATE '.$tbl;
            $q.= ' SET value=? ';
            $q.=' WHERE id_item='.intval($id);
            $q.=' AND field_identifier=?';
            $q.=' AND id_data='.intval($id_lang_data);
        }
        $r = $_sgbd->prepared_query($q, 'ss', [$value , $field_identifier]);

        return $r;
    }
    /**
     * Save a short translation value
     *
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id in the db
     * @param string $value content to save
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return bool true if ok
     * 
     */
    public static function save_short_translation_value($field_identifier, $id, $value, $id_lang_data = 0) {
        global $DB,$LANG;

        $_sgbd = $DB;
        $tbl = $DB->table_prefix.'_languages_'.Language::tl_short;

        $q = 'SELECT DISTINCT COUNT(*) FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        $q.=' AND field_identifier=?';
        $q.=' AND id_data = '.intval($id_lang_data);
        $exists = $_sgbd->prepared_query_value($q, 's', [$field_identifier]);

        if (!$exists) {
            $q = 'INSERT INTO '.$tbl;
            $q.= ' SET value=? ';
            $q.=' , id_item='.intval($id);
            $q.=' , field_identifier=?';
            $q.=' , id_data='.intval($id_lang_data);
        } else {
            $q = 'UPDATE '.$tbl;
            $q.= ' SET value=? ';
            $q.=' WHERE id_item='.intval($id);
            $q.=' AND field_identifier=?';
            $q.=' AND id_data='.intval($id_lang_data);
        }

        return $_sgbd->prepared_query($q, 'ss', [$value , $field_identifier]);
    }
    /**
     * load a list of long translation id and value for a possible use with quick edit
     *
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return array list of result
     * 
     */
    public static function load_long_translation_collection($field_identifier, $id_lang_data = 0) {
        global $DB,$LANG;

        $tbl = $DB->table_prefix.'_languages_'.Language::tl_long;

        $q = 'SELECT DISTINCT id_item , value FROM '.$tbl;
        $q.=' WHERE ';
        $q.=' field_identifier = "'.$field_identifier.'"';
        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }
        $q.=' AND id_data = '.intval($id_lang_data);
        $q.=' ORDER BY id_item ';

        return $DB->query_list($q);
    }
    /**
     * load a list of short translation id and value for a possible use with quick edit
     *
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return array list of result
     * 
     */
    public static function load_short_translation_collection($field_identifier, $id_lang_data = 0) {
        global $DB,$LANG;

        $tbl = $DB->table_prefix.'_languages_'.Language::tl_short;

        $q = 'SELECT DISTINCT id_item AS id , value FROM '.$tbl;
        $q.=' WHERE ';
        $q.=' field_identifier = "'.$field_identifier.'"';
        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }
        $q.=' AND id_data = '.intval($id_lang_data);
        $q.=' ORDER BY id_item ';

        return $DB->query_list($q);
    }

    /**
     * load a long translation value
     *
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id in the db
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return string $value
     * 
     */
    public static function load_long_translation_value($field_identifier, $id, $id_lang_data = 0) {
        global $DB,$LANG;

        $tbl = $DB->table_prefix.'_languages_'.Language::tl_long;

        $q = 'SELECT DISTINCT value FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        $q.=' AND field_identifier = "'.$field_identifier.'"';
        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }
        $q.=' AND id_data = '.intval($id_lang_data);

        $value = $DB->query_value($q);

        return $value;
    }
    /**
     * returns several long values according to a series of fields identifier
     *
     * @param array $fields_identifier_list list ofname of the posted input, in the format "module_table-field"
     * @param int $id in the db
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return array of string values
     * 
     */
    public static function load_long_translation_value_list($fields_identifier_list, $id, $id_lang_data = 0) {
        global $DB,$LANG;

        $tbl = $DB->table_prefix.'_languages_'.Language::tl_long;

        $q = 'SELECT DISTINCT field_identifier,value FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        $q.=' AND field_identifier IN ('.$DB->format_string_list($fields_identifier_list).') ';
        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }
        $q.=' AND id_data = '.intval($id_lang_data);

        return $DB->query_list($q);
    }
    /**
     * load a short translation value
     *
     * @param string $field_identifier name of the posted input, in the format "module_table-field"
     * @param int $id in the db
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return string $value
     * 
     */
    public static function load_short_translation_value($field_identifier, $id, $id_lang_data = 0) {
        global $DB,$LANG;

        $tbl = $DB->table_prefix.'_languages_'.Language::tl_short;

        $q = 'SELECT DISTINCT value FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        $q.=' AND field_identifier = "'.$field_identifier.'"';
        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }
        $q.=' AND id_data = '.intval($id_lang_data);
        
        $value = $DB->query_value($q);

        return $value;
    }
    /**
     * returns several short values according to a series of fields identifier
     *
     * @param array $fields_identifier_list list of name of the posted input, in the format "module_table-field"
     * @param int $id in the db
     * @param int $id_lang_data of the lang coming from languages_data
     * 
     * @return array of string values
     * 
     */
    public static function load_short_translation_value_list($fields_identifier_list, $id, $id_lang_data = 0) {
        global $DB,$LANG;

        $tbl = $DB->table_prefix.'_languages_'.Language::tl_short;

        $q = 'SELECT DISTINCT field_identifier,value FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        $q.=' AND field_identifier IN ('.$DB->format_string_list($fields_identifier_list).') ';
        if (!$id_lang_data) {
            $id_lang_data = $LANG->current_id_data;
        }
        $q.=' AND id_data = '.intval($id_lang_data);

        return $DB->query_list($q);
    }
    /**
     * delete translation for one or more data id
     *
     * @param array $post the current post
     * @param string $module name of the module
     * @param string $table name of the table
     * @param int|array|string $filter selection filter, can be an id, a array list of ids or a string containing a mysql subquery
     * 
     * @return bool 
     * 
     */
    public static function delete_translation_data(&$post, $module, $table, $filter = null) {
        global $DB;
        $_sgbd = $DB;
        foreach (Language::translation_prefix as $type=>$prefix) {
            if (!isset($post[$prefix])) {
                $post[$prefix] = [];
            }

            $tbl = $DB->table_prefix.'_languages_'.$type;

            $field_identifier = HelPHP_module::build_module_field_name($module, $table, '%');

            $q = 'DELETE FROM '.$tbl;
            $q .= ' WHERE field_identifier LIKE "'.$field_identifier.'" ';

            if (is_int($filter)) {
                $q.=' AND id_item = '.intval($filter);
            } elseif (is_array($filter)) {
                $q.=' AND id_item IN ('.implode(',', $filter).')';
            } elseif (is_string($filter)) {
                $q.=' AND id_item IN ('.$filter.')';
            }
            $ret=$_sgbd->query($q);
        }
        return $ret;
        
    }

    /**
     * delete a long translation for one id
     *
     * @param mixed $field_identifier name of the posted input, in the format "module_table-field"
     * @param mixed $id in the db
     * @param int $id_lang_data 
     * 
     * @return bool 
     * 
     */
    public static function delete_long_translation_value($field_identifier, $id, $id_lang_data = 0) {
        global $DB;

        $_sgbd = $DB;
        $tbl = $DB->table_prefix.'_languages_'.Language::tl_long;

        $q = 'DELETE FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        if (strpos($field_identifier, '%') === false) {
            $q.=' AND field_identifier = "'.$field_identifier.'"';
        } else {
            $q.=' AND field_identifier LIKE "'.$field_identifier.'"';
        }

        if ($id_lang_data > 0) {
            $q.=' AND id_data = '.intval($id_lang_data);
        }

        return $_sgbd->query($q);
    }
    /**
     * delete a short translation for one id
     *
     * @param mixed $field_identifier name of the posted input, in the format "module_table-field"
     * @param mixed $id in the db
     * @param int $id_lang_data 
     * 
     * @return bool 
     * 
     */
    public static function delete_short_translation_value($field_identifier, $id, $id_lang_data = 0) {
        global $DB;

        $_sgbd = $DB;
        $tbl = $DB->table_prefix.'_languages_'.Language::tl_short;

        $q = 'DELETE FROM '.$tbl;
        $q.=' WHERE id_item = '.intval($id);
        if (strpos($field_identifier, '%') === false) {
            $q.=' AND field_identifier = "'.$field_identifier.'"';
        } else {
            $q.=' AND field_identifier LIKE "'.$field_identifier.'"';
        }
        if ($id_lang_data > 0) {
            $q.=' AND id_data = '.intval($id_lang_data);
        }

        return $_sgbd->query($q);
    }

    /**
     * formatting of posted data for easier processing
     *
     * @param mixed $post
     * 
     * @return array 
     * 
     */
    public static function prepare_posted_translation_data($post) {
        $result = [];
        foreach (Language::translation_prefix as $type=>$prefix) {
            if (isset($post[$prefix])) {
                if (!isset($result[$type])) {
                    $result[$type] = [];
                }

                if (is_array($post[$prefix])) {
                    foreach ($post[$prefix] as $field_identifier => $id_list) {
                        $name_data = HelPHP_module::explode_field_name($field_identifier);

                        $module = $name_data['module'];
                        $table = $name_data['table'];

                        if (!isset($result[$type][$module])) {
                            $result[$type][$module] = [];
                        }
                        if (!isset($result[$type][$module][$table])) {
                            $result[$type][$module][$table] = [];
                        }

                        foreach ($id_list as $id => $values) {
                            if (!isset($result[$type][$module][$table][$id])) {
                                $result[$type][$module][$table][$id] = [];
                            }
                            $result[$type][$module][$table][$id][$name_data['field']] = $values;
                        }
                    }
                } else {
                    Utils::error_log('error post['.$prefix.'] = '.$post[$prefix]);
                }
            }
        }

        return $result;
    }

    /**
     * save posted translation data in the database
     * IMPORTANT !
     * If a new element must be created (id = 0) and it has already been created previously,
     * you must specify the name of the entry containing this new id in $post via the parameter $created_item_identifier
     * or directly give the value of the id to this parameter
     *
     * @param array $post
     * @param int $created_item_identifier
     * 
     * @return void
     * 
     */
    public static function save_translation_data(&$post, $created_item_identifier = null) {
        global $DB;

        $prepared_data = Language::prepare_posted_translation_data($post);

        $_sgbd = $DB;

        foreach ($prepared_data as $type => $type_data) {

            $tbl = $DB->table_prefix.'_languages_'.$type;

            $new_id = 0;

            foreach ($type_data as $module => $table_list) {
                foreach ($table_list as $table_name => $id_list) {
                    $module_table = HelPHP_module::build_module_table_name($module, $table_name);

                    foreach ($id_list as $id => $field_data) {
                        if ($id == 0) {
                            if ($created_item_identifier != null) {
                                if (is_numeric($created_item_identifier)) {
                                    $id = $created_item_identifier;
                                } elseif (isset($post[$created_item_identifier])) {
                                    $id = $post[$created_item_identifier];
                                } else {
                                    Utils::error_log('Cannot define ID for new item with translation data');
                                }

                                $new_id = $id;
                            } 
                            //deprecated
                            // else {
                            //     // verification that at least one field has been filled
                            //     $ok = false;
                            //     foreach ($field_data as $field_name => $values) {
                            //         foreach ($values as $id_lang_data => $text) {
                            //             if ($text != '') {
                            //                 $ok = true;
                            //                 break;
                            //             }
                            //         }
                            //     }

                            //     // add element if at least one field is filled
                            //     if ($ok) {
                            //         $q = 'INSERT INTO '.$module_table.' SET id=0';
                            //         $DB->query($q);
                            //         $new_id = $DB->last_insert_id();

                            //         if ($new_id > 0) {
                            //             $id = $new_id;
                            //         }
                            //     }
                            // }
                        }

                        if ($id > 0) {
                            foreach ($field_data as $field_name => $values) {
                                $field_identifier = HelPHP_module::build_module_field_name($module, $table_name, $field_name);

                                // retrieves the languages already entered for this field
                                $q = 'SELECT DISTINCT id_data FROM '.$tbl.' WHERE field_identifier=? AND id_item=?';
                                $list = $_sgbd->prepared_query_list($q, 'si', [$field_identifier , $id]);
                                if (!is_array($list)) {
                                    $list = [];
                                }

                                foreach ($values as $id_lang_data => $text) {
                                    $text = stripslashes(str_replace('\\r\\n', "\r\n", $text));
                                    // clean data for long text and tinymce.
                                    // tinymce always return <br> for empty field, when the field contains only <br> and it doesn't exist
                                    // don't save it to don't pollute our db with useless entry
                                    if ($type == Language::tl_long && ($text == '<br >' || $text == '<br>') && !in_array($id_lang_data, $list)){
                                        continue;
                                    }

                                    if (in_array($id_lang_data, $list)) {
                                        $q = 'UPDATE '.$tbl.' SET ';
                                        $q .= ' value=? ';
                                        $q .= ' WHERE';
                                        $q .= '    id_data=? ';
                                        $q .= 'AND id_item=? ';
                                        $q .= 'AND field_identifier=? ';
                                        $updated = $_sgbd->prepared_query($q, 'siis', [$text , $id_lang_data , $id , $field_identifier]);
                                    } elseif ($text != '') {
                                        $q = 'INSERT INTO '.$tbl.' SET ';
                                        $q .= ' value=? ';
                                        $q .= ', id_data=? ';
                                        $q .= ', id_item=? ';
                                        $q .= ', field_identifier=? ';

                                        $added = $_sgbd->prepared_query($q, 'siis', [$text , $id_lang_data , $id , $field_identifier]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($new_id > 0) {
                Language::clean_new_data($post, $new_id);
            }
        }
    }

    /**
     * in the case where we post an element with the id = 0 (=> creation of a new element)
     * we remove its information from the array and place it at the index of the new id created
     * 
     * @param array $post
     * @param int $new_id
     * 
     * @return void
     * 
     */
    public static function clean_new_data(&$post, $new_id) {
        foreach (Language::translation_prefix as $type=>$prefix) {
            if (isset($post[$prefix])) {
                foreach ($post[$prefix] as $field_identifier => $id_list) {
                    if (isset($post[$prefix][$field_identifier][0])) {
                        $post[$prefix][$field_identifier][$new_id] = $post[$prefix][$field_identifier][0];
                        unset($post[$prefix][$field_identifier][0]);
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------------------------------------------------------------
    // ------------------------------------------------------------------------------------------------------------------------
    // TL FILE PART

    protected $tl = [];

    /**
     * load translation data from a 'tl_' module file
     *
     * @param string $module_name
     * @param bool $admin=false
     * @param bool $only_common=false
     * @param string $module_path=false if the module is not in a classic place.
     * 
     * @return result in $tl
     * 
     */
    public function load_translation_files($module_name,$admin=false,$only_common=false,$module_path=false) {
        if (!$only_common){}
        // this variable is used by tl_ file to retrieve the module name
        global $tl_module_name,$CONFIG;
        
        $tl_module_name = $module_name;

        if (!isset($this->tl[$module_name])){
            $this->tl[$module_name] = [];
        }
        
        $tl_file_name='tl_'.$module_name.'.php';

        // we check first if there is a translation file in the instance :

        $path = $CONFIG::HOME_FOLDER;
        $path.= $admin ? $CONFIG::ADMIN_FOLDER : 'public/';
        $path.= $module_name.'/'.$tl_file_name;
        
        if (file_exists($path) && !$module_path) {
            include_once($path); // we include the local one
        } else {
            // no tl in the instance, we have to load the standard one from helPHP_moduleS or the forced path from $module_path.
        
            if ($module_path && file_exists($module_path)) {
                $mpath=explode('/',$module_path);
                $fname=array_pop($mpath);
                $mpath=implode('/',$mpath);
                $fname=explode('.',$fname);
                $fname=$fname[0];
                $fname=strtolower($fname);
                if (file_exists($mpath.'/tl_'.$fname.'-'.$this->current_language.'.php')) include($mpath.'/tl_'.$fname.'-'.$this->current_language.'.php'); // we include the translation file in absolute
            }else{
                //LINE 1061 to 1073 + 1084 should be removed after suppression of old tl files !
                // no tl in the instance nor new tl format ?, we have to load the standard one from helPHP_moduleS.
                $path=__DIR__.'/';
                // since we moved this file from modules folder to libs folder, need to change the path to acces module folder
                $path = str_replace('libs', 'modules', $path);
                $path.= $module_name.'/';
                $path.= $admin ? 'admin/' : 'public/';
                $module_translate_file = $tl_file_name;

                if (file_exists($path.$module_translate_file)) {
                    include($path.$module_translate_file); // we include the translation file in absolute
                // no tl in the instance, we have to load the standard one from helPHP_moduleS or the forced path from $module_path.
                }else{
                    //transition because of uitranslation finished.
                    $tl_file_name = 'tl_'.$module_name.'-'.$this->current_language.'.php';
                    $path=__DIR__.'/';
                    $path = str_replace('libs', 'modules', $path);
                    $path.= $module_name.'/';
                    $path.= $admin ? 'admin/' : 'public/';
                    if (file_exists($path.$tl_file_name)){
                        include($path.$tl_file_name);
                    }
                }
            }
        }
        if (isset($tl_data) && isset($tl_data[$this->current_language])) {
            $this->tl[$module_name] = array_merge($this->tl[$module_name], $tl_data[$this->current_language]);
            unset($tl_data);
        }

        // global file common -> identification keys of common's translation always begin by 'tlc_'
        $this->load_translation_file_common();
    }

    private $file_common_loaded = false;
    
    /**
     * load the common translation file from libs/tl 
     *
     * @return void, filling the global $tl
     * 
     */
    public function load_translation_file_common() {
        if (!$this->file_common_loaded){
            include('tl/tl_common-'.$this->current_language.'.php');
            GLOBAL $TLCOMMON, $tl_module_name;
            if (isset($tl_data) && isset($tl_data[$this->current_language])) {
                $TLCOMMON=$tl_data[$this->current_language];
                $this->file_common_loaded = true;
                $this->tl[$tl_module_name] = array_merge($this->tl[$tl_module_name], $TLCOMMON);
            }
            
        }else{
            GLOBAL $TLCOMMON, $tl_module_name;
            $this->tl[$tl_module_name] = array_merge($this->tl[$tl_module_name], $TLCOMMON);
        }
    }

    /**
     * return the identified text, or the identifier between braces if no text exists for this identifier
     *
     * @param mixed $module_name
     * @param string $key  the key is the id in the tl file, for exemple the word "chicken" and in the tl file [chicken]=>"un poulet" 
     *                     if ($count_for_singular > 1) will search for the $key plural : [chickens] => "des poulets"
     *                     $key.'__none' matches none (so $count_for_singular == 0) to force some expression : [chicken_none] => "pas de poulet"
     *                     $key.'__singular' matches the singular (i. e. $count_for_singular == 1) : [chicken_singular] => "un poulet"
     *                     so depending $count_for_singular and the key "chicken" we will search for a translation that will adapt to the number.
     * @param string|array $replace can contain a word or an array of words : if there is a string it will replace $1 in the tl string
     *                     if it's a key/pair array like this : 1=>"text",2=>"another one" , it will replace $1 by "text" and $2 by "another text" 
     *                     so each word will replace in order the numbered markers preceded by $ present in the text
     * @param int $count_for_singular is used if the phrase needs to be converted to the plural or not based on a quantity
     * @param array $array to search in another array the module tl
     * 
     * @return value
     * 
     */
    public function get_tl($module_name, $key = '', $replace = null, $count_for_singular = null, $array = false) {
        if ($count_for_singular !== null) {
            $count_for_singular = intval($count_for_singular);
            $key2 = $key;

            if ($count_for_singular === 1) {
                $key2 .= '__singular';
            } elseif ($count_for_singular === 0) {
                $key2 .= '__none';
            }

            if (isset($this->tl[$module_name][$key2])) {
                $key = $key2;
            }
        }

        $array = $array ? $array : $this->tl[$module_name];

        if (isset($array[$key])) {
            $out = stripslashes($array[$key]);

            if ($count_for_singular > 0) {
                $out = str_replace('$c', $count_for_singular, $out);
            }

            if (is_array($replace)) {
                foreach ($replace as $k=>$w) {
                    $out = str_replace('$'.($k+1), $w, $out);
                }

                return nl2br($out);
            } elseif ($replace !== null) {
                return nl2br(str_replace('$1', $replace, $out));
            } else {
                return nl2br($out);
            }
        } else {
            return '{'.$key.'}';
        }
    }

    /**
     * returns an array with all identified texts, or the identifier in brackets if no text exists for this identifier
     *
     * @param mixed $module_name
     * @param array $list that contains another array like this: ['key'=>'string identifier' , 'replace'=>[...] ]
     *              replace can contain a word or an array of words
     *              each word will replace in order the numbered markers preceded by $ present in the text=
     * @param null $join if $join is specified, the result will be a string concatenated with this text instead of an array
     * 
     * @return array or string
     *  
     */
    public function get_multiple_tl($module_name, $list, $join = null) {

        $result = [];

        foreach ($list as $data) {
            $replace = null;

            if (is_string($data)) {
                $key = $data;
            } elseif (is_array($data)) {
                if (isset($data['key'])) {
                    $key = $data['key'];
                } else {
                    $key = current($data);
                }
                if (isset($data['replace'])) {
                    $replace = $data['replace'];
                }
            }

            if (isset($this->tl[$module_name][$key])) {
                $out = $this->tl[$module_name][$key];

                if (is_array($replace)) {
                    foreach ($replace as $k=>$w) {
                        $out = str_replace('$'.($k+1), $w, $out);
                    }

                    array_push($result, nl2br($out));
                } elseif ($replace !== null) {
                    $out = str_replace('$1', $replace, $out);
                    array_push($result, nl2br($out));
                } else {
                    array_push($result, nl2br($out));
                }
            } else {
                array_push($result, '{'.$key.'}');
            }
        }

        if ($join !== null) {
            $result = nl2br(implode($join, $result));
        }
        return $result;
    }
    // 
    /**
     * return translated wordings for array of field names
     *
     * @param string $module_name
     * @param string $table
     * @param array $fields list of names
     * 
     * @return array 
     * 
     */
    public function get_translated_table_fields($module_name, $table, $fields) {
        $result = [];
        foreach ($fields as $f) {
            $result[$f] = $this->get_tl(HelPHP_module::build_module_field_name($module_name, $table, $f));
        }
        return $result;
    }
    // 
    /**
     * return the identified text, or the identifier between braces if no text exists for this identifier
     * but from another tl file than the one corresponding to the current module.
     * by specifiyng $other_module name and it's admin or not it will load the othe module tl file before searching in it the wordings like get_tl
     *
     * @param string $other_module name
     * @param bool $admin true false
     * @param string $key  the key is the id in the tl file, for exemple the word "chicken" and in the tl file [chicken]=>"un poulet" 
     *                     if ($count_for_singular > 1) will search for the $key plural : [chickens] => "des poulets"
     *                     $key.'__none' matches none (so $count_for_singular == 0) to force some expression : [chicken_none] => "pas de poulet"
     *                     $key.'__singular' matches the singular (i. e. $count_for_singular == 1) : [chicken_singular] => "un poulet"
     *                     so depending $count_for_singular and the key "chicken" we will search for a translation that will adapt to the number.
     * @param string|array $replace can contain a word or an array of words : if there is a string it will replace $1 in the tl string
     *                     if it's a key/pair array like this : 1=>"text",2=>"another one" , it will replace $1 by "text" and $2 by "another text"
     *                     so each word will replace in order the numbered markers preceded by $ present in the text 
     * @param int $count_for_singular is used if the phrase needs to be converted to the plural or not based on a quantity
     * @param array $array to search in another array the module tl
     * 
     * @return value
     * 
     */
    public function get_translated_text_from_other_module($other_module = '', $admin = false, $key = '', $replace = null, $count_for_singular = null){
        $this->load_translation_files($other_module, $admin, false,false);

        return $this->get_tl($other_module,$key,$replace,$count_for_singular);
    }
    
    /**
     * return the name stored in short table or the internal name if no entry in short.
     * Used to get the name to display for elements like category that may have some multilingual entry.
     *
     * @param  string $module_table the table of the field name. used to form the field_identifier like module_table-name
     * @param  int $id_item
     * @return string name
     */
    public static function get_name($module_table, $id_item){
        global $DB;

        $name = Language::load_short_translation_value($module_table.'-label', $id_item, 0);
        if (!$name) {
            $q = 'SELECT name FROM '.$DB->table($module_table).' WHERE id=?';
            $name = $DB->prepared_query_value($q, 'i', [$id_item]);
        }

        return $name;
    }
}