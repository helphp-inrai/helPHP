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

//**************************************************************************************************************

namespace helPHP\libs;

use Config;

/**
* @class H
* 
* That's our UI componant class...
* It generate Html5 componants/widgets and common elements depending main config and properties given
* As it extends Html class, it use also its magic methods and offer an intuitive usage.
*
* @package helPHP\libs 
*/
class H extends Html {

    /**
     * used by H::icons, it define the main svg sheet used.
     * the original sheet comes from https://feathericons.com/
     *
     * @var string
     */
    const svg_sprite = Config::SVG_SPRITE_ICONS_FILE; // the name of the file containing the SVG sprite icons

    public static $_debug = false;
    
    /**
     * $H is a global used everywhere in HelPHP to call this class methods
     * and create_instance will create it... 
     *
     * @param Bool $forceNewInstance
     * 
     * @return Object global $H
     */

    public static function create_instance($forceNewInstance = false) {
        global $H;
        
        if ($H != null && $forceNewInstance == false) {
            return $H;
        }
        $H = new H();
        return $H;
    }
    
    /**
     * Constructor for the H class.
     *
     * This method initializes a new instance of the H class with the specified tag name, attributes, and data.
     * will simply execute the construct of Html class.
     *
     * @param string $tag_name The name of the HTML tag to use for this element (e.g., 'div', 'p', etc.).
     * @param array|null $attributes An array of key-value pairs representing the attributes for this element.
     * @param mixed|null $data Additional data to be used when creating this element. in general its content/innerhtml
     * 
     * @see Html\construct
     */
    public function __construct($tag_name = '', $attributes = null, $data = null) {
        parent::__construct($tag_name, $attributes, $data);
    }

    /**
     * will execute its parent from Html class
     *
     * @param mixed $property
     * 
     * @return Mixed
     * 
     * @see Html\__get
     */
    public function __get($property) {
        return parent::__get($property);
    }

    /**
     * will execute its parent from Html class
     *
     * @param mixed $property
     * 
     * @return Mixed
     * 
     * @see Html\__set
     */
    public function __set($property, $value) {
        return parent::__set($property, $value);
    }

    /**
     * will execute its parent from Html class
     *
     * @param mixed $property
     * 
     * @return Mixed
     * 
     * @see Html\__unset
     */
    public function __unset($property) {
        return parent::__unset($property);
    }
    
    /** 
    * Magic method to handle the call to a static function that does not exist
    * and will create corresponding html/object element
    * @param String $name the function/html element name
    * @param Array $args [0] : the html attributes as a key pairs array, [1] : the content of the tag
    *
    * @return Object Html element
    * 
    */
    public static function __callStatic($name, $args) {
        $params = isset($args[0]) ? $args[0] : null;
        $content = isset($args[1]) ? $args[1] : null;
        return new Html(strtoupper($name), $params, $content);
    }

    /** 
     * Major method that will create all the head elements of a new html output.
     * it will manage the embeding of the javascripts and css files depending if we are in dev or production mode.
     * But also the indexations and socials tags for indexable documents.
     *  @param String $title The title of the page.
     *  @param String $keywords : all the keywords for meta keywords 
     *  @param Array $meta_data : meta tags as key pairs (name => value).
     *  @param Bool $public indicate if this head is for the front or backoffice / admin side
     *  @param String|Bool $canonical is used for sitemap indexation to indicate the original document in case of double URL
     *  @param Int $theme ID of the theme to load. 
     * 
     * @return Object The complete head of the HTML document.
     *  
     */
    public static function new_document($title, $keywords, $description, $meta_data=false, $public=false, $canonical=false, $theme=false) {
        global $LANG,$DB,$CONFIG;

        $pre=$CONFIG::BASE_URL;
        $refresher = '';
        if ($CONFIG::DEVMODE) {
            $refresher = '?a='.time();
        } else {
            // to force the reloading of the compacted files only when it changed, add the last modified time to the url
            $lastmod=filemtime($CONFIG::HOME_FOLDER.'jsgz/all.js.gz');
            $refresher = '?a='.$lastmod;
        }
        $lalang=$LANG->current_language;

        //header
        $document = H::HTML(array('lang' => $lalang));
        $head=H::HEAD();
        $head->add_child(H::TITLE(null, $title));
        $head->add_child(H::META(array('charset'=>'utf-8')));
        $head->add_child(H::META(array('http-equiv'=>'Content-Type' , 'content'=>'text/html; charset=utf-8')));
        $head->add_child(H::META(array('name'=>'keywords' , 'content'=>$keywords, 'lang' => $lalang)));
        $head->add_child(H::META(array('name'=>'description' , 'content'=>$description, 'lang' => $lalang)));
        if ($canonical) {
            $head->add_child(Html::open_tag(Html::LINK, array('href'=>$canonical , 'rel'=>'canonical')));
        }
        $head->add_child(H::META(array('name'=>'robots' , 'content'=>'index, follow')));
        $head->add_child(H::META(array('name'=>'revisit-after' , 'content'=>'7 days')));
        $head->add_child(H::META(array('name'=>'document-type' , 'content'=>'Public')));

        //iOS
        $head->add_child(H::META(array('name'=>'mobile-web-app-capable' , 'content'=>'yes')));
        //ZOOM CONTROL !
        $head->add_child(H::META(array('name'=>'viewport' , 'content'=>'width=device-width, initial-scale=1.0, maximum-scale=2.0, user-scalable=yes')));
        //favicon
        $head->add_child(H::LINK(array('href'=>$pre.'images/favicon.png' , 'rel'=>'icon' , 'type'=>'image/png')));
        //will give the constants for js 
        $head->add_child(H::SCRIPT('', array('src'=>$pre.'js/constants.js'.$refresher , 'language'=>'Javascript')));
        //the texts for JS dependind the current language
        $head->add_child(H::SCRIPT('', array('src'=>$pre.'js/constants_texts-'.$LANG->current_language.'.js'.$refresher , 'language'=>'Javascript')));
        // load the storage status for js
        $head->add_child(H::SCRIPT('', array('src'=>$pre.'js/storages.js'.$refresher , 'language'=>'Javascript')));
        
        if ($CONFIG::DEVMODE) {
            $head->add_child(H::SCRIPT('',array('src'=>$pre.'js/externals/tinymce/tinymce.min.js', 'language'=>'Javascript')));
            $head->add_child(H::SCRIPT('',array('src'=>$pre.'js/externals/alwan/alwan.min.js', 'language'=>'Javascript')));
            $head->add_child(H::LINK(array('rel'=>'stylesheet' , 'type'=>'text/css', 'href'=>$pre.'js/externals/alwan/alwan.min.css')));
            $head->add_child(H::load_js($public, $pre, $refresher));

            $id_theme = $theme ? $theme : 0;
            if ($id_theme == 0){
                $id_theme = $public ? $CONFIG::THEME_ID : $CONFIG::THEME_ID_ADMIN;
            }

            if ($id_theme > 0) {
                $str_css = \helPHP\modules\csseditor\admin\Csseditor::get_css($id_theme);
                $head->add_child(H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css', 'id'=>'insertedFromDB'), $str_css), 'css');

            } else {
                foreach ($CONFIG::MODULES_LIST as $moduleName => $module) {
                    $path = $public ? $pre.'public/' : $pre.$CONFIG::ADMIN_FOLDER;
                    $path.= $moduleName.'/'.$moduleName.'.css';
                    if (is_file($path)) {
                        $head->add_child(H::LINK(array('rel'=>'stylesheet' , 'type'=>'text/css', 'href'=>$path)));
                    }
                }
            }
            
        } else {
            if ($public) {
                $adm='';
            } else {
                $adm='adm';
                 $head->add_child(H::SCRIPT('',array('src'=>$pre.'js/externals/tinymce/tinymce.min.js.gz', 'language'=>'Javascript')));
            }
            $head->add_child(H::SCRIPT('',array('src'=>$CONFIG::BASE_URL.'jsgz/all'.$adm.'.js.gz'.$refresher, 'language'=>'Javascript')));
            $head->add_child(H::LINK(array('href'=>$CONFIG::BASE_URL.'css/gz/all'.$adm.'.css.gz'.$refresher, 'rel'=>'stylesheet' , 'type'=>'text/css')), 'css');
        }

        //add optional meta (especially for social networks) which must be arrays
        if (isset($meta_data) && is_array($meta_data)) {
            foreach ($meta_data as $meta) {
                $head->add_child(H::META($meta));
            }
        }

        // add js variable to indicate if admin side
        $head->add_child(H::script('H_constants.is_admin = '.(($public) ? 'false' : 'true').';',['autoremove'=>true]));

        //end of header
        $document->add_child($head, 'head');

        //add basic body
        $body = H::tag(H::BODY, array('id'=>'dabody' , 'class'=>'mon_body'));
        $document->add_child($body, 'body');

        return $document;
    }

    /**
    * Generate an image object/HTML tag based on provided attributes.
    *
    * @param  Mixed  $attributes The attributes for the image tag. Can be passed as associative array, or 'src' value.
    *
    * @return Object The generated image object/HTML tag.
    */
    
    public static function image($attributes) {
        if (!is_array($attributes)) {
            $src = $attributes;
            $attributes = array();
            $attributes['src'] = $src;
        }
        if (!isset($attributes['name'])) {
            $attributes['name'] = time();
        }
        if (!isset($attributes['class'])) {
            $attributes['class'] =  $attributes['name'];
        }
        if (!isset($attributes['alt'])) {
            $attributes['alt'] = $attributes['src'];
        }
        $image = H::tag(H::IMG,$attributes);
        return $image;
    }
    /**
     * Generates an icon SVG element based on the provided name and attributes.
     * The SVG spreadsheet is a collection of def/symboles. 
     * To easily pick one you'll find a very useful admin module named "Icons" that will present to you all the icons in the speadsheet
     * and will permit to search and pick one by clicking on it (it will copy an H:icon('picked icon name') in your clipboard ) and you'll
     * just need to paste the icon at the right place in your code.
     * 
     * Note : it's creating an SVG, so if you want to add an event on it, you should embed it in a div and put the event on it.
     *
     * @param String $name The name of the icon to be used in the #id attribute of the <use> tag.
     * @param Array $attributes Optional attributes for the icon, including class and style. Defaults to an empty array.
     *
     * @return Object H::SVG The generated SVG Object element containing the icon.
     * 
     */
    public static function icon($name, $attributes = []) {
        global $CONFIG;

        $attributes['class'] = 'hlp_icon ' . (isset($attributes['class']) ? $attributes['class'] : '');
        $attributes['style'] = 'pointer-events: none;';

        $svg = H::tag(H::SVG, $attributes);
            $use = H::tag(H::USE, ['href'=>$CONFIG::BASE_URL.'./images/icons/'.H::svg_sprite.'#'.$name]);
        $svg->add_child($use);

        return $svg;
    }

    /**
     * display a button with and icon inside
     * 
     * @param $name icon name to display
     * @return Object H::BUTTON with the generated SVG Object element containing the icon.
     * 
     * @see icon 
     * you can also use the admin module Icon to pick the icon needed and just add "button_" like this : H:button_icon('picked icon name')
     * to get it quickly.
     */
    public static function button_icon($name, $attributes = []) {

        $attributes['class'] = 'hlp_button_icon ' . (isset($attributes['class']) ? $attributes['class'] : '');
        $button = H::BUTTON($attributes);

        $button->add_child( H::icon($name) );

        return $button;
    }
    
    /**
     * same as button_icon() but with a text label that can be place on the left or right side of the button
     *
     * @param Mixed $name of the icpn
     * @param String $side 'left' or 'right' default
     * @param String $label
     * @param Array $attributes
     * 
     * @return Object H::BUTTON with the generated SVG Object element containing the icon and a SPAN with the text label
     *
     * @see icon 
     * you can also use the admin module Icon to pick the icon needed and just call the function like this : H:button_icon_with_text('picked icon name','left','text label')
     * to get it quickly.
     */
    public static function button_icon_with_text($name, $side = 'right', $label = '', $attributes = []) {

        $attributes['class'] = 'hlp_button_icon_with_text' . (isset($attributes['class']) ? ' '.$attributes['class'] : '');
        $button = H::BUTTON($attributes);

        if ($side == 'right') {
            $button->add_child( [H::SPAN(null, $label), H::icon($name, ['class'=>'hlp_icon_'.$side])] );
        } else {
            $button->add_child( [H::icon($name, ['class'=>'hlp_icon_'.$side]), H::SPAN(null, $label)] );
        }

        return $button;
    }

    /**
     * Create a informative button
     * 
     * When clicked display a modal aside with content given to the method, used to display information to the user
     * 
     * @param Array $attributes
     * @param String|Object $content, either string or HTML object
     * @param String $type Optional, determine the type of string given (text, link)
     * 
     * @return Objet Html div container
     */
    public static function button_info($attributes, $content, $type = 'text') {
        $container = H::DIV(['class'=>'hlp_button_info_container']);
            
            $class = isset($attributes['class']) ? ' '.$attributes['class'] : '';
            $id_content = 'hlp_btn_info_content¤'.H::get_unique_id();

            $attributes['class'] = 'hlp_button_info_button'.$class;
            $attributes['id'] = isset($attributes['id']) ? $attributes['id'] : H::get_unique_id();
            $attributes['onclick'] = 'H_ui.tooltip(event, "'.$id_content.'");';
            $button = H::DIV($attributes, H::icon('help-circle'));

            $container_attribute = ['class'=>'hlp_button_info_content hidden'.$class, 'id'=>$id_content];
            if (is_string($content)){
                if ($type == 'link') {
                    $content = H::A(['href'=>$content, 'target'=>'_blank'], $content);
                }
            }
            $container_content = H::DIV($container_attribute, $content);

        $container->add_child( [$button, $container_content] );

        return $container;
    }

    /**
     * The Form ! One of the more important tag ! In this class the form created come with multiple automatic features :
     * Fields validation (depending each input)
     * the choice of the div targeted to display the result after a submit (dom_target attributes) or its immediate parent (dom_target='.parent')
     * some custom javascript function to call on submit etc.
     * classic form ex : 
     * $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>'my_form']);
     * the value coming with $this are coming from helPHP_module in this example...
     * 
     * @param Array|Null $attributes list of html attributes
     * @param Array|String|Null $content content of the form
     * @param String $custom_submit_function javascript function to call when submitting the form
     */
    public static function form($attributes = null, $content = '', $custom_submit_function = '') {
        if (!is_array($attributes)) {
            $attributes = array();
        }
        if (!isset($attributes['action'])) {
            $attributes['action'] = trim($_SERVER['PHP_SELF']);
        }
        if (!isset($attributes['method'])) {
            $attributes['method'] = 'POST';
        }
        if (!isset($attributes['enctype']) && $attributes['method']=='POST') {
            $attributes['enctype'] = 'multipart/form-data';
        }

        $dom_id = false;
        if (isset($attributes['dom_id'])) {
            $dom_id = $attributes['dom_id'];
            unset($attributes['dom_id']);
        }
        if (!$dom_id && isset($attributes['dom_target']) && str_contains($attributes['dom_target'], '¤')){
            $dom_id = '¤'.explode('¤', $attributes['dom_target'])[1];
        }
        if (isset($attributes['dom_target'])) {
            $attributes['data-dom_target'] = trim($attributes['dom_target']);
            unset($attributes['dom_target']);
        }
        if ($custom_submit_function != '') {
            $attributes['data-submit_function'] = trim($custom_submit_function);
        }

        if (!isset($attributes['id'])) {
            $attributes['id'] = 'form_' . H::get_unique_id();
        }

        $form = H::tag(H::FORM, $attributes);
        if ($content) {
            $form->add_child($content);
        }
        
        if (!isset($attributes['posted_from_container']) || (isset($attributes['posted_from_container']) && $attributes['posted_from_container'])){
            $form->add_child(H::input_hidden(array('name' => HelPHP_module::posted_varname , 'value'=>1 , 'data-alwaysposted'=>'1')));
        }
        if ($dom_id !== false){
            $form->add_child(H::input_hidden(array('name' => 'dom_id' , 'value'=>$dom_id , 'data-alwaysposted'=>'1')));
        }
        //in case of api usage, we need to keep the id on each submit. must be used for ajax request too.
        if (isset($_POST['oid'])){
            $form->add_child(H::input_hidden(array('name' => 'oid' , 'value'=>$_POST['oid'] , 'data-alwaysposted'=>'1')));
        }

        $form->add_after(H::script('if(h.v){h.v.parse_fields(\''.$attributes['id'].'\');}', ['defer'=>true , 'autoremove'=>true]));

        return $form;
    }
    /**
     * Creates a fieldset object/HTML element with the given legend.
     *      
     * @param Array|Null $attributes Additional attributes to apply to the fieldset tag.
     * @param String $legend The text content of the legend tag. Defaults to an empty string.
     *
     * @return H\Fieldset The created Fieldset object with the added legend child.
     */
    public static function fieldset($attributes = null, $legend = '') {
        if (!is_array($attributes)) {
            $attributes = array();
        }
        $fieldset = H::tag(H::FIELDSET, $attributes);
            $legend = H::tag(H::LEGEND, null, $legend);
        $fieldset->add_child( $legend );

        return $fieldset;
    }

    /**
     * TODO : Create a form that will be shown step by step
     * 
     */

    /**
     * Generates an input field of the specified type.
     * validation process is automaticaly made depending the type
     * This function is a global shortcut to all input_xxx ones you can find bellow.
     * 
     * @param string $type The type of input field to generate. One of:
     *                             - 'login'
     *                             - 'password'
     *                             - 'email'
     *                             - 'integer'
     *                             - 'float'
     *                             - 'date'
     *                             - 'textarea'
     *                             - 'hidden'
     *                             - 'file'
     *                             - 'bool' / 'checkbox'
     *                             - 'radio'
     *                             - 'varchar'
     *                             - 'char'
     *                             - 'text'
     *
     * @param Array $attributes  Optional. Additional attributes to pass to the input field.
     *
     * @return Object / Html input 
     */ 

    public static function input_field($type, $attributes = null) {
        switch ($type) {
            case 'login':
                return H::input_login($attributes);
            break;

            case 'password':
                return H::input_password($attributes);
            break;

            case 'email':
                return H::input_email($attributes);
            break;

            case 'integer':
                return H::input_integer($attributes);
            break;

            case 'float':
                return H::input_float($attributes);
            break;

            case 'date':
                return H::input_date($attributes);
            break;

            case 'textarea':
                return H::input_textarea($attributes);
            break;

            case 'hidden':
                return H::input_hidden($attributes);
            break;

            case 'file':
                return H::input_file($attributes);
            break;

            case 'bool':
            case 'checkbox':
                return H::input_checkbox($attributes);
            break;

            case 'radio':
                return H::input_radio($attributes);
            break;

            case 'varchar':
            case 'char':
            case 'text':
                return H::input_text($attributes);
            break;

            default:
                Utils::error_log('H::input_field -> type not referenced :'.$type);
                return H::input_text($attributes);
            break;
        }
    }
    /**
     * Generates an HTML input field for the login type of data.
     *
     * @param Array|Null $attributes of the input element. If null, default attributes will be used.
     * @return Object The generated HTML input login field.
     */
    public static function input_login($attributes = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'text';
        $attributes['data-type'] = 'login';
        if (!isset($attributes['data-required'])) {
            $attributes['data-required'] = 1;
        }
        if (!isset($attributes['id'])) {
            $attributes['id'] = 'login_'.H::get_unique_id();
        }
        if (!isset($attributes['name'])) {
            $attributes['name'] = 'login';
        }
        if (!isset($attributes['placeholder'])) {
            $attributes['placeholder'] = 'Login';
        }
        if (!isset($attributes['data-sizemin'])) {
            $attributes['data-sizemin'] = 3;
        }
        if (!isset($attributes['data-sizemax'])) {
            $attributes['data-sizemax'] = 20;
        }

        return H::_input($attributes);
    }

     /**
     * Generates an HTML input field for the password type of data.
     *
     * @param Array|Null $attributes of the input element. If null, default attributes will be used.
     * @return Object The generated HTML input password field.
     */
    public static function input_password($attributes = null) {
        global $CONFIG;
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'password';
        $attributes['data-type'] = 'password';
        if (!isset($attributes['data-required'])) {
            $attributes['data-required'] = 1;
        }
        if (!isset($attributes['name'])) {
            $attributes['name'] = 'password';
        }
        if (!isset($attributes['id'])) {
            $attributes['id'] = 'password_'.H::get_unique_id();
        }
        if (!isset($attributes['placeholder'])) {
            $attributes['placeholder'] = 'Password';
        }
        if (!isset($attributes['data-sizemin'])) {
            $attributes['data-sizemin'] = $CONFIG::USERPASSWORD_MINIMUM_LENGTH == null ? 6 : $CONFIG::USERPASSWORD_MINIMUM_LENGTH;
        }
        if (!isset($attributes['data-sizemax'])) {
            $attributes['data-sizemax'] = 20;
        }

        return H::_input($attributes);
    }

    /**
     * Generates an HTML input field for the email type of data.
     *
     * @param Array|Null $attributes of the input element. If null, default attributes will be used.
     * @return Object The generated HTML input email field.
     */
    public static function input_email($attributes = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'email';
        $attributes['data-type'] = 'email';
        
        if (!isset($attributes['name'])) {
            $attributes['name'] = 'email';
        }
        if (!isset($attributes['id'])) {
            $attributes['id'] = 'email_'.H::get_unique_id();
        }
        if (!isset($attributes['placeholder'])) {
            $attributes['placeholder'] = 'Email';
        }

        return H::_input($attributes);
    }

    /**
     * The input confirmation is a classic field used in general to confirm the content of a previous one,
     * like a password or email you need to confirm.
     * 
     * @param Object $original_input the original field that needed to be confimed
     * @param string $label
     * 
     * @return Object html input confirm with similar aspect of the orginal input
     * 
     */
    public static function input_confirmation($original_input, $label = '[confirm]') {
        $confirm = H::_input(['type'=>$original_input->tag_name]);
        $original_input->copy_attributes_to($confirm);

        $confirm->attr_name = 'confirm_'.$confirm->attr_name;
        $confirm->attr_id = 'confirm_'.$confirm->attr_id;
        $confirm->data_check_field = $original_input->attr_id;

        unset($confirm->data_required);
        unset($confirm->attr_value);

        $confirm->label = $label;
        $original_input->data_confirm_field = $confirm->attr_id;

        return $confirm;
    }

    /**
     * Another input field dedicated to integers.
     * You can set a minimal and maximal value for an accepted value in a range.
     * accept only integers (of course )
     *
     * @param array|null $attributes
     * @param int|null $min
     * @param int|null $max
     * 
     * @return object html input
     * 
     */
    public static function input_integer($attributes = null, $min = null, $max = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'number';
        $attributes['data-type'] = 'int';
        if ($min !== null) {
            $attributes['min'] = strval($min);
        }
        if ($max !== null) {
            $attributes['max'] = strval($max);
        }
        if (!isset($attributes['placeholder'])) {
            $attributes['placeholder'] = '0';
        }

        return H::_input($attributes);
    }

    /**
     * You liked the input integer ? here is the same for float...
     * With min max limit for accepted range as well.
     *
     * @param array|null $attributes
     * @param int|null $min
     * @param int|null $max
     * 
     * @return object html input
     * 
     */
    public static function input_float($attributes = null, $min = null, $max = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'number';
        $attributes['data-type'] = 'float';
        if ($min !== null) {
            $attributes['min'] = strval($min);
        }
        if ($max !== null) {
            $attributes['max'] = strval($max);
        }
        $attributes['step'] = isset($attributes['step']) ? $attributes['step'] : 0.01;
        if (!isset($attributes['placeholder'])) {
            $attributes['placeholder'] = '0.0';
        }

        return H::_input($attributes);
    }

    /**
     * A date selector! Perfect for booking...
     * You can set a minimal and maximal value for an accepted value in a range.
     * 
     * @param array|null $attributes
     * @param int|null $min
     * @param int|null $max
     * 
     * @return object html input
     * 
     * @see input_datetime() if hours matters 
     */
    public static function input_date($attributes = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'date';
        $attributes['data-type'] = 'date';

        return H::_input($attributes);
    }

     /**
     * A datetime selector! 
     * Note that some navigator still got some issue with this kind of input, and
     * sometimes it's better to use an input_date and a separated input_time to cover all needs and possible issue.
     * 
     * @param array|null $attributes
     * 
     * @return object html input
     * 
     */
    public static function input_datetime($attributes = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'datetime-local';
        $attributes['data-type'] = 'datetime-local';

        return H::_input($attributes);
    }
    
     /**
     * A time selector! 
     * 
     * @param array|null $attributes
     * 
     * @return object html input
     * 
     */
    public static function input_time($attributes = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'time';
        $attributes['data-type'] = 'time';

        return H::_input($attributes);
    }

    /**
     * this is the base function for input_short_translation and input_long_translation that
     * can be found in HelPHP_module.php. themselves are used in translate_block.
     * 
     * @param array $attributes
     * @param int $id
     * @param bool $short if true return an input text, false a textarea
     * 
     * @return array|object of multiple input text or textarea depending the number of languages ans short type true/false
     * 
     * @see HelPHP_module\input_short_translation
     * @see HelPHP_module\input_long_translation
     * @see HelPHP_module\translate_block
     * 
     */
    public static function _input_translation($attributes, $id = 0, $short = true) {
        global $LANG;
        $languages = $LANG->get_languages_data();

        $result = H::group();

        $name = $attributes['name'];
        $values = isset($attributes['value'])?$attributes['value']:'';

        foreach ($languages as $index => $data) {

            if (is_array($values)) {
                $attributes['value'] = isset($values[$data['id_data']])?$values[$data['id_data']]:'';
                if ($attributes['value'] == '') {
                    $attributes['value'] = isset($values[$data['iso']])?$values[$data['iso']]:$attributes['value'];
                }
            }

            if ($short) {
                $attributes['name'] = Language::create_translation_name($name, $id, $data['id_data'], Language::tl_short);
                $input = H::input_text($attributes);
            } else {
                $attributes['name'] = Language::create_translation_name($name, $id, $data['id_data'], Language::tl_long);
                $input = H::input_textarea($attributes);
            }

            // $result->add_child($input, $data['iso'], true);
            $result->add_child([$input->label_tag(), $input], $data['iso'], true);

            // remove the internal indexation key for this item
            $input->reset_key();
        }

        return $result;
    }

    /**
     * A simple input text
     *
     * @param array $attributes
     * 
     * @return Object html input
     * 
     */
    public static function input_text($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }
        if (!isset($attributes['value'])) {
            $attributes['value'] = '';
        }

        $attributes['type'] = 'text';
        $attributes['data-type'] = 'text';

        return H::_input($attributes);
    }
     /**
     * A textarea !
     * But not so simple, because if $attributes['tinymce'] is set, the textarea will be transformed in a tinyMCE instance.
     *
     * @param array $attributes
     * 
     * @return Object html input
     * 
     */

    public static function input_textarea($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'textarea';
        $attributes['data-type'] = 'text';

        return H::_input($attributes);
    }

    /**
     * The hidden input ! 
     * you don't see it, but still the best solution to keep some temporary values between two post!
     *
     * @param array $attributes
     * 
     * @return Object html input
     * 
     */
    public static function input_hidden($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'hidden';
        $attributes['data-type'] = 'hidden';

        return H::_input($attributes);
    }
    /**
     * The input file can support multiple upload. 
     * Those are chunked uploads automaticaly managed by our Ajax JS and PHP class
     * Nothing special to do, you'll reveice your file(s) in $_FILE as usual, but the Ajax\process_file() method will help you move/process them.
     * But if you want to upload image or video you should take a look on the Media module that offer a lot more.
     *
     * @param array $attributes
     * 
     * @return Object html input
     * 
     */
    public static function input_file($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'file';
        $attributes['data-type'] = 'file';
        if (isset($attributes['onchange'])) {
            $attributes['onchange'] = 'H_ajax.check_file_data(event.target.form);'.$attributes['onchange'];
        } else {
            $attributes['onchange'] = 'H_ajax.check_file_data(event.target.form);';
        }
        if (!isset($attributes['name'])) {
            $attributes['name'] = 'file_upload';
        }

        if (isset($attributes['multiple']) && $attributes['multiple']) {
            $attributes['multiple'] = 1;
            if (!str_ends_with($attributes['name'],'[]')) $attributes['name'] .= '[]';
        } else {
            unset($attributes['multiple']);
        }

        return H::_input($attributes);
    }
    
    /**
     * The input range take care of all possibilities of the html5 range tag.
     * With step and limits it can also be used to represent a playing track.
     * 
     * @param array $attributes
     * @param float $min minimal limitation
     * @param float $max maximal limitation
     * @param float $step the precision of the step will define also the precision of the range
     * @param string $value=null 
     * @param string $orient=null (vertical or horizontal for firefox) you can also add "writing-mode" or "direction" in $attributes array. 
     * 
     * @return Object html input range
     * 
     */
    public static function input_range($attributes, $min = null, $max = null, $step = null, $value=null, $orient=null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }
        
        $attributes['type'] = 'range';
        $attributes['data-type'] = 'range';
        
        if ($min !== null) {
            $attributes['min'] = strval($min);
        }
        if ($max !== null) {
            $attributes['max'] = strval($max);
        }
        if ($step !== null) {
            $attributes['step'] = $step;
        }
        if ($value !== null) {
            $attributes['value'] = $value;
        } 
        if ($orient !== null) {
            $attributes['orient'] = $orient;
        }
        return H::_input($attributes);
    }

    /**
     * The classic checkbox
     * set $attributes['checked'] to check it.
     *
     * @param array $attributes
     * @param $post the current post 
     * 
     * @return Object html input checkbox
     * 
     */
    public static function input_checkbox($attributes, $post = null) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'checkbox';
        $attributes['data-type'] = 'checkbox';
        if (!isset($attributes['value'])) {
            $attributes['value'] = '1';
        }

        if (isset($attributes['checked'])) {
            if ($attributes['checked'] === true || intval($attributes['checked']) > 0 || $attributes['checked'] == 'checked') {
                $attributes['checked'] = 'checked';
            } else {
                unset($attributes['checked']);
            }
        } elseif (isset($attributes['name']) && isset($post[$attributes['name']])) {
            $attributes['checked'] = 'checked';
        }
        
        // if you need to use a required on a checkbox, you should use a radio insteed
        if (isset($attributes['data-required'])) unset($attributes['data-required']);

        return H::_input($attributes);
    }
     /**
     * The classic radio 
     * set $attributes['checked'] to indicate which one is checked.
     *
     * @param array $attributes
     * 
     * @return Object html input radio
     * 
     * @see input_multiple_radios() to create all radios in one go
     * 
     */
    public static function input_radio($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'radio';
        $attributes['data-type'] = 'radio';
        if (!isset($attributes['value'])) {
            $attributes['value'] = '1';
        }

        if (isset($attributes['checked'])) {
            if ($attributes['checked'] === true || intval($attributes['checked']) > 0 || $attributes['checked'] == 'checked') {
                $attributes['checked'] = 'checked';
            } else {
                unset($attributes['checked']);
            }
        }

        return H::_input($attributes);
    }
    
    /**
     * create multiple radio 
     * 
     * add a values key to attributes with the list of values.
     * 
     * @param array $attributes Associative array. Some value are required to work properly:
     *                              - values : array of array with label and value inside, each one display a choice
     *                              - name : name's in form
     */
    public static function input_multiple_radios($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        if (!isset($attributes['values'])) {
            Utils::error_log('Missing values when calling H::input_multiple_radios, $attributes :');
            Utils::error_log($attributes);
            return;
        }
        if (!isset($attributes['name'])) {
            Utils::error_log('Missing name when calling H::input_multiple_radios, $attributes :');
            Utils::error_log($attributes);
            return;
        }

        $values = $attributes['values'];
        unset($attributes['values']);
        if (isset($attributes['no_select']) && $attributes['no_select']){
            array_push($values, ['value'=>'', 'label'=>'x']);
            unset($attributes['no_select']);
        }
        
        $name = $attributes['name'];
        unset($attributes['name']);

        $selected = isset($attributes['selected']) ? $attributes['selected'] : '';
        unset($attributes['selected']);
        
        $callback = isset($attributes['callback']) ? $attributes['callback'] : '';
        unset($attributes['callback']);

        $css_class = isset($attributes['class']) ? ' '.$attributes['class'] : '';
        $attributes['class'] = 'hlp_multiradio_block'.$css_class;

        $output = H::DIV($attributes);
        
        foreach ($values as $key => $line) {
            $inp = H::input_radio([
                'name'=>$name,
                'class'=>'hlp_multiradio_inp'.$css_class,
                'checked'=>($selected == $line['value']),
                'value'=>$line['value'], 
                'label'=>$line['label'],
                'onchange'=>'H_ui.change_multi_radio(event);'.$callback
            ]);
            $lab = $inp->label_tag();
            if ($selected == $line['value']){
                $lab->add_class('selected');
            }
            $output->add_child([$lab, $inp]);
        }
        
        return $output;
    }

    /**
     * Create a colorpicker with the external js alwan colorpicker.
     * the value is in class html hexa value (e.g:#FFFFFF)
     *
     * @param mixed $attributes
     * 
     * @return Objet Html div 
     * 
     */
    public static function input_colorpicker($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['id'] = isset($attributes['id']) ? $attributes['id'] : H::get_unique_id();
        $attributes['value'] = isset($attributes['value']) ? $attributes['value'] : '#FFFFFF';

        $class = isset($attributes['class']) ? ' '.$attributes['class'] : '';
        $attributes['class'] = isset($attributes['class']) ? ' '.$attributes['class'] : '';

        
        $output = H::DIV(['class'=>'hlp_colorpicker_block'.$attributes['class'], 'id'=>'hlp_colorpicker-'.$attributes['id']]);
        if (isset($attributes['label'])){
            $output->label = $attributes['label'];
            unset($attributes['label']);
        }

            $inp = H::input_hidden($attributes);
            $color = H::DIV(['class'=>'hlp_colorpicker_input'.$attributes['class'], 'onclick'=>'H_ui.display_color_picker(event, "'.$attributes['id'].'");', 'style'=>'background-color:'.$attributes['value']]);
                $label_color = H::SPAN([], $attributes['value']);
            $color->add_child($label_color);

        $output->add_child([$inp,$color]);

        return $output;
    }

    /**
     * shortcut for _table_row
     * 
     * @see _table_row()
     *
     */
    public static function table_row($data_line, $columns_filter = null, $values_filter = null) {
        return H::_table_row(false, $data_line, $columns_filter, $values_filter);
    }
    /**
     * shortcut for _table_row
     * 
     * @see _table_row()
     *
     */
    public static function table_header_row($data_line, $columns_filter = null, $values_filter = null) {
        return H::_table_row(true, $data_line, $columns_filter, $values_filter);
    }
    /**
     * transforms a data table into a row of cells in a html table
     * $columns_filter : associative array containing the columns to display (= a database row)
     * $values_filter : associative array filtering the values to display in cells
     * ex : if for example a field contains numeric identifiers, they can be replaced by textual values
     *
     * @param mixed $is_header
     * @param mixed $data_line
     * @param null $columns_filter
     * @param null $values_filter
     * 
     * @return Objet Html table
     * 
     */
    private static function _table_row($is_header, $data_line, $columns_filter = null, $values_filter = null) {
        $output = null;

        if (is_array($data_line)) {
            $output = H::tag(H::TR);
            $cell_tag = $is_header ? H::TH : H::TD;

            $use_filter = is_array($values_filter);

            if (is_array($columns_filter)) {
                foreach ($columns_filter as $k => $cell_content) {
                    $cell_content = $data_line[$k];

                    if ($use_filter) {
                        if (isset($values_filter[$k])) {
                            if (isset($values_filter[$k][$cell_content])) {
                                $cell_content = $values_filter[$k][$cell_content];
                            } else {
                                //$cell_content = '';
                            }
                        }
                    }
                    $output->add_child(H::tag($cell_tag, null, $cell_content));
                }
            } else {
                foreach ($data_line as $k => $cell_content) {
                    if ($use_filter) {
                        if (isset($values_filter[$k])) {
                            $cell_content = $values_filter[$k][$cell_content];
                        }
                    }

                    $output->add_child(H::tag($cell_tag, null, $cell_content));
                }
            }
        }
        return $output;
    }

    // $columns_filter contains an array used to specify the fields to display and to give them a display label
    // but 
    // exemple : $columns =  ['id'=>'Identifiant' , 'email'=>'Adresse e-mail' , etc ... ]

    /**
     * Create quickly a datagrid (with a simple table tag) from a simple key=>value array.
     * it can come from DB\prepared_query_list or DB\query_list to display directy the result of a mysql Query.
     * $columns_filter contains an array used to specify fields to display and to give them a display label.
     * it can also change their order :
     * exemple : $columns =  ['id'=>'DB ID' , 'email'=>'mail adress ' , etc ... ]
     *           $liste = 0 => ['email'=>'xxx@yyyyy.com','id'=>125, ...],
     *                    1 => ['email'=>'zzzz@yyyyy.com','id'=>126, ...]
     * @param array $liste
     * @param array $columns_filter
     * @param string $id for the grid
     * 
     * @return Objet Html table
     * 
     */
    public static function simple_data_grid($liste, $columns_filter = null,$id = false) {
        $output = null;

        if (sizeof($liste) > 0) {
            
            $output=($id)? H::TABLE(['border'=>'0' , 'cellpadding'=>'0' , 'cellspacing'=>'0', 'id'=>$id]) : H::TABLE(['border'=>'0' , 'cellpadding'=>'0' , 'cellspacing'=>'0']);

            $keys = array_keys($liste[0]);
            if (in_array('¤params¤', $keys)){
                unset($keys[array_search('¤params¤', $keys)]);
            }

            if (is_array($columns_filter)) {
                $ordered_keys = array_keys($columns_filter);
                $keys = array_intersect($ordered_keys, $keys);
                $label_keys = [];

                foreach ($keys as $k) {
                    if ($k == '') continue;
                    array_push($label_keys, $columns_filter[$k]);
                }

                $head = H::tag(H::THEAD);
                $head->add_child(H::table_header_row($label_keys));
                $output->add_child($head);
            } else {
                // if (is_string($keys[0])) {
                $head = H::tag(H::THEAD);
                $head->add_child(H::table_header_row($keys));
                $output->add_child($head);
                // }
                $keys = null;
            }

            $body = H::tag(H::TBODY);
            foreach ($liste as $line) {  
                if (isset($line['¤params¤'])){
                    $attr = $line['¤params¤'];
                    unset($line['¤params¤']);
                }
                $row = H::table_row($line, $keys);
                if (isset($attr)) $row->attributes = $attr;
                $body->add_child($row);
            }
            $output->add_child($body);
        }
        return $output;
    }
    /**
     * As its name indicate it, it do more than the simple grid, there is an additionnal $value_filter
     * used to replace the value to display by textual values.

     * exemple : the returned data contains a size field with possible values 0,1,2
     * $values_filter = array('size'=> array( 0=>'undefined' , 1=>'small' , 2=>'tall' ) );
     * instead of displaying 0,1,2 in the cells , it will display 'small' or 'tall' etc...    
     * 
     * @param array $liste
     * @param array $columns_filter
     * @param null $values_filter
     * @param string $id for the grid
     * 
     * @return Objet Html table
     * 
     */
    public static function advanced_data_grid($liste, $columns_filter = null, $values_filter = null ,$id = false) {
        $output = null;

        if (sizeof($liste) > 0) {
            $output = H::tag(H::TABLE, ['border'=>1, 'id'=>$id]);

            $keys = array_keys($liste[0]);

            if (is_array($columns_filter)) {
                $ordered_keys = array_keys($columns_filter);
                $keys = array_intersect($ordered_keys, $keys);
                $label_keys = [];

                foreach ($keys as $k) {
                    array_push($label_keys, $columns_filter[$k]);
                }

                $head = H::tag(H::THEAD);
                $head->add_child(H::table_header_row($label_keys));
                $output->add_child($head);
            } else {
                if (is_string($keys[0])) {
                    $head = H::tag(H::THEAD);
                    $head->add_child(H::table_header_row($keys));
                    $output->add_child($head);
                }
            }

            $body = H::tag(H::TBODY);
            foreach ($liste as $line) {
                $body->add_child(H::table_row($line, $keys, $values_filter));
            }
            $output->add_child($body);
        }
        return $output;
    }

    /**
     * the base function for all inputs with common features
     *
     * @param mixed $attributes
     * 
     * @return Objet html input or textarea or TinyMCE
     * 
     */
    private static function _input($attributes) {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        // add fixed class
        if (isset($attributes['data-type'])) $attributes['class'] = isset($attributes['class']) ? 'input_'.$attributes['data-type'].' '.$attributes['class'] : 'input_'.$attributes['data-type'];

        //no capitalize
        $attributes['autocapitalize'] = (isset($attributes['autocapitalize']))?$attributes['autocapitalize']:"off";
        
        if (isset($attributes['label'])) {
            if (!isset($attributes['id'])) {
                $attributes['id'] = H::get_unique_id();
            }
        }

        $type = $attributes['type'];

        //--------------------------------------------------------------------------------------------------
        $tinymce = '';
        if (isset($attributes['tinymce'])) {
            $parameters = $attributes['tinymce'];
            unset($attributes['tinymce']);

            if (!isset($attributes['id'])) {
                $attributes['id'] = 'tinymce_'.H::get_unique_id();
            }
            $tinymce = H::script(Tinymce::get_init_javascript($attributes['id'], $parameters), ['defer'=>false , 'autoremove'=>250]);
        }
        //--------------------------------------------------------------------------------------------------

        if (isset($attributes['data-required'])) {
            if ($attributes['data-required']) {
                $attributes['data-required'] = 1;
            } else {
                unset($attributes['data-required']);
            }
        }

        $input = null;

        switch ($type) {
            case H::TEXTAREA:
            case H::SELECT:

                unset($attributes['type']);

                $content = '';

                if (isset($attributes['value'])) {
                    $content = $attributes['value'];
                    unset($attributes['value']);
                }

                switch ($type) {
                    case H::TEXTAREA:
                        $input = H::tag(H::TEXTAREA, $attributes, $content);
                        if ($tinymce) {
                            $input->add_after($tinymce);
                        }
                    break;

                    case H::SELECT:
                        $input = $content;
                    break;
                }

            break;

            default:
                $input = H::tag(H::INPUT, $attributes);
            break;
        }
        
        // for validator all input come in a div
        // the positionning of error message is way less complicated like that
        // this div is added by validator.js
            return $input;
    }

    // --------------------------------------------------
    // QUICK EDIT

    /**
     * Create a "quick edit" item.
     * a "quick edit" a start seems to be a simple text, but after a double click/tap on it, it transform as a input field that permit
     * to edit the value of the text. After validation, the value is updated in the DB.
     * useful when there is only one thing to edit in a page or with complexe pagination or when you need to enlight the UI.
     * you can have one form object for multiple quick edit item.
     * 
     * exemple : $my_quick_edit = H::quick_edit($form, $line['id'], ['class'=>'myqe'], $this->ifld_data_value, $line['value'], 'float');
     * 
     * @param Objet $form the H::form() that will integrate the field
     * @param String $id integer id of the field in database
     * @param String $field_identifier the name to send, with format module_table-field
     * @param Array $attributes to add the html element (like other elements)
     * @param String $text text to display
     * @param String $type of the field to enable validation
     * @param Int $id_lang_data if translatable field, the id of the language
     * 
     * @return Object Html item that will transform as a typed input 
     * 
     * @see quick_edit_generate_input()
     * 
     */
    public static function quick_edit($form, $id, $field_identifier, $attributes = [], $text = '', $type = 'text', $id_lang_data = null) {
        $attributes['id'] = isset($attributes['id']) ? $attributes['id'] : $field_identifier;
        $attributes['class'] = isset($attributes['class']) ? $attributes['class'] : '';
        $placeholder_mode='off';
        if($text =='' && isset($attributes['placeholder'])){
            $text = $attributes['placeholder'];
            $placeholder_mode='on';
        }
        $element = H::SPAN($attributes, $text);
        
        $element->data['placeholder']= $placeholder_mode;
        $element->data[Language::id_attribute]= $id_lang_data;
        $element->data[Html::QUICK_EDIT_ID]= $id;

        if (isset($form->extra['quick_edit_fields'])) {
            $fields = $form->extra['quick_edit_fields'];
        } else {
            $fields = [];
        }

        $field_data = HelPHP_module::explode_field_name($field_identifier);
        $module = $field_data['module'];
        $table = $field_data['table'];
        $field = $field_data['field'];

        if (!is_array($fields)) {
            $fields = [];
        }
        if (!isset($fields[$module])) {
            $fields[$module] = [];
        }
        if (!isset($fields[$module][$table])) {
            $fields[$module][$table] = [];
        }
        if (!isset($fields[$module][$table][$field])) {
            $fields[$module][$table][$field] = $type;
        }

        $keys = array_keys($fields);
        $m = array_search($module, $keys);

        $keys = array_keys($fields[$module]);
        $t = array_search($table, $keys);

        $keys = array_keys($fields[$module][$table]);
        $f = array_search($field, $keys);

        $form->set_extra('quick_edit_fields', $fields);

        $element->data[Html::QUICK_EDIT_TYPE_INDEX] = $m.','.$t.','.$f;

        // quick edit activation by double clicking on the value
        $element->attr_ondblclick = 'h.libs.quick_edit.qedit(event.target);';

        // adding class
        if (!$element->attr_class) {
            $element->attr_class = 'quick_edit';
        } else {
            $element->attr_class .= ' quick_edit';
        }

        return $element;
    }
    
    /**
     * Quick edit input generated depending the preparation done with quick_edit().
     * this function is automaticaly called by HelPHP_module and create the mini form with current value
     *
     * @param int $id 
     * @param mixed $value 
     * @param mixed $type_index
     * @param mixed $field_data
     * @param null $id_lang_data
     * @param array $attributes
     * 
     * @return Objet Html block
     * 
     */
    public static function quick_edit_generate_input($id, $value, $type_index, $field_data, $id_lang_data = null, $attributes = []) {
        global $CRYPT;

        $data = $CRYPT->decrypt($field_data);

        if (is_array($data)) {
            $uniqueid = 'qv_'.H::get_unique_id();

            $attributes = array_merge($attributes, [
                'id'=>$uniqueid ,
                'value'=>$value,
                'data-'.Html::QUICK_EDIT_FIELD=>$uniqueid,
                'data-'.Html::QUICK_EDIT_ID=>$id,
                'data-'.Html::QUICK_EDIT_TYPE=>$type_index,
                'onkeydown'=>'h.libs.quick_edit.check(event);'
            ]);
            $attributes['ondblclick'] = 'h.libs.quick_edit.qedit((event.target);';

            // adding class
            if (!isset($attributes['class'])) {
                $attributes['class'] = 'quick_edit';
            } else {
                $attributes['class'] .= ' quick_edit';
            }
            if ($id_lang_data != null) {
                $attributes['data-id_lang_data'] = $id_lang_data;
            }

            $type_array = explode(',', $type_index);
            $modules = array_keys($data);
            $m = $data[$modules[$type_array[0]]];

            $tables = array_keys($m);
            $t = $m[$tables[$type_array[1]]];

            $fields = array_keys($t);
            $type = $t[$fields[$type_array[2]]];

            switch ($type) {
                case 'login':
                    $input_value = H::input_login($attributes);
                break;

                case 'password':
                    $input_value = H::input_password($attributes);
                break;

                case 'email':
                    $input_value = H::input_email($attributes);
                break;

                case 'integer':
                case 'int':
                    $input_value = H::input_integer($attributes);
                break;

                case 'float':
                    $input_value = H::input_float($attributes);
                break;

                case 'date':
                    $input_value = H::input_date($attributes);
                break;
                case 'datetime':
                    $input_value = H::input_datetime($attributes);
                break;
                case 'time':
                    $input_value = H::input_time($attributes);
                break;

                case 'tinymce':
                    $attributes['tinymce'] = ['width'=>400];
                    $input_value = H::input_textarea($attributes);
                break;

                case 'textarea':
                    $input_value = H::input_textarea($attributes);
                break;

                case 'file':
                    $input_value = H::input_file($attributes);
                break;

                default:
                    $input_value = H::input_text($attributes);
                break;
            }

            $btn_save = H::BUTTON(['id'=>$uniqueid.'-save', 'name'=>'quick_edit' , 'value'=>'save' , 'data-'.Html::QUICK_EDIT_FIELD=>$uniqueid, 'data-with_token'=>1], 'save');
            $btn_cancel = H::BUTTON(['id'=>$uniqueid.'-cancel', 'name'=>'quick_edit' , 'value'=>'cancel' , 'data-'.Html::QUICK_EDIT_FIELD=>$uniqueid, 'data-with_token'=>1], 'cancel');

            $bloc = H::group('quick_edit');

            $bloc->add_child($btn_cancel);
            $bloc->add_child($input_value);
            $bloc->add_child($btn_save);
            $js = 'let f=document.getElementById(\''.$uniqueid.'\'); h.v.protect_input(f); h.v.check_field(f); f.focus(); f.select();';
            $js.=' let s=document.getElementById(\''.$uniqueid.'-save\');h.e.add_event_click(s,h.a.quick_edit_send);';
            $js.=' let c=document.getElementById(\''.$uniqueid.'-cancel\');h.e.add_event_click(c,h.a.quick_edit_send);';
            $bloc->add_child(H::script($js, ['defer'=>true , 'autoremove'=>200]));

            return $bloc;
        }

        return null;
    }

    /**
     * Doing more than a simple submit button.
     * it use a token system to avoid flooding and limit ddos
     * it will call validator to check the vallue of all input again.
     * if we give an action identifier as name for all submit button, its value can be used to call different process (you'll see that during module construction).
     * Exemple : 
     * $btn_delete = H::submit_button(['class'=>$this->css.'btn_del', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_document_data, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('ask_delete')], $this->get_tl('tlc_del'));
     *
     * @param mixed $attributes
     * @param string $label
     * @param string $custom_submit_function a javascript call back called
     * 
     * @return object Html submit button
     * 
     */
    public static function submit_button($attributes, $label = 'SUBMIT', $custom_submit_function = '') {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['type'] = 'submit';
        $attributes['data-type'] = 'submit';

        if (!isset($attributes['name'])) {
            $attributes['name'] = 'submit_button_'.H::get_unique_id();
        }
        if (!isset($attributes['value'])) {
            $attributes['value'] = 'submit_button_value';
        }

        // add an alternative javascript function to manage the submit of this form
        if ($custom_submit_function != '') {
            $attributes['data-submit_function'] = $custom_submit_function;
        }
        
        if (isset($attributes['with_token']) && $attributes['with_token']){
            $attributes['data-with_token'] = '1';
            unset($attributes['with_token']);
        }

        return H::tag(Html::BUTTON, $attributes, $label);
    }
    
    /**
     * seems to work just like a submit button only this field will be posted
     * (in addition to all those with the 'alwaysposted' flag)
     *
     * @param mixed $attributes
     * @param string $label
     * @param string $custom_submit_function
     * 
     * @return Object Html submit button
     * 
     */
    public static function submit_button_single($attributes, $label = 'SUBMIT', $custom_submit_function = '') {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['data-only'] = 1;

        return H::submit_button($attributes, $label, $custom_submit_function);
    }

    /**
     * Used to add some submit attributes to other input like select
     *
     * @param String $submit_varname
     * @param String $submit_varvalue
     * 
     * @return Array attributes 
     * 
     */
    public static function input_submit_attributes($submit_varname, $submit_varvalue) {
        $attributes = array();

        // this input will post the form at each change
        $attributes['onchange'] = 'H_ajax.submit_on_change(event.target);';

        // it is necessary to specify which action is related to this post
        // actionname =  action name
        // actionvalue = action value
        $attributes['data-actionname'] = $submit_varname;
        $attributes['data-actionvalue'] = $submit_varvalue;

        // only = 1 -> only this field will be posted (in addition to all those with the 'alwaysposted' flag)
        $attributes['data-only'] = 1;

        // this field is always posted (even if the form is posted by another element with the flag only = 1)
        //~ $attributes['data-alwaysposted'] = 1;

        return $attributes;
    }

    /**
     * To get an input select.
     * 
     * the form can be posted automatically when changing the value of this select
     * two other parameters must be specified :
     * $submit_varname : action variable name
     * $submit_varvalue : action variable value
     *
     * @param Array $attributes
     * @param Array $options_data
     * an array containing the list of options to display (usually a result of sql query)
     * value_key : the name of the field containing the value of each option of the select
     * label_key : the name of the field containing the text to display in each option of the select
     *
     * first_empty (optional) : true if it is necessary to display an empty value at the beginning of the list
     * or an array representing the element to display at the start of the list, formatted as the options are: [value_key => "value", label_key => "label"]
     *
     * groups (optional) : an array used to create groups from a field in the options table
     *
     * exemple : 'groups'=>['model'=> [0=>'Thing' , 1=>'Another Thing'] ]);
     * the 'model' field will be used to define the groups, the items with model = 0 will go in the group labeled 'Thing',
     * those with the value 1 will go in the group with the label 'Another Thing'
     *
     * IMPORTANT:
     * two special keys are predefined: 'null' and '?'
     * the group with 'null' will receive all the elements whose specified field contains the value NULL
     * the group with '?' will receive all items that do not have an identified value in another group 
     * 
     * full exemple usage with a list coming from db for: 
     *  // we get the data
     *  $q = 'SELECT id, name FROM '. $DB->table('xxxx_data').' ORDER BY name';
     *  $list = $DB->prepared_query_list($q);
     *  //check if there is already a posted id 
     *  $selected_id = isset($post[$this->build_module_field_name('xxxx','data','id')]) ? $post[$this->build_module_field_name('xxxx','data','id')] : 0;
     *  //setting up all options data
     *  $opts_data = array('first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$list);
     *  //getting the select
     *  $select = H::select(['name'=>$this->build_module_field_name('xxxx','data','id'), 'label'=>$this->get_tl('my_select')], $opts_data, $selected_id, $this->input_action_identifier, $this->ACTION);
     *  //when there will be an onchange event, it will submit the current form.
     *      
     * @param null $default_value (optional) : the value of the option to be selected by default on the first display
     * @param string $submit_varname
     * @param string $submit_varvalue
     * 
     * @return Object Html input select
     * 
     */
    public static function select($attributes, $options_data, $default_value = null, $submit_varname = '', $submit_varvalue = '') {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        if ($submit_varname != '') {
            $attributes = array_merge($attributes, H::input_submit_attributes($submit_varname, $submit_varvalue));
        }

        $select = Html::tag(H::SELECT, $attributes);

        $value_key = isset($options_data['value_key'])?$options_data['value_key']:null;
        $label_key = isset($options_data['label_key'])?$options_data['label_key']:null;

        if (is_string($value_key) && strpos($value_key, ',') > 0) {
            $value_key = explode(',', $value_key);
        } elseif (!is_array($value_key)) {
            $value_key = [$value_key];
        }

        $options = (isset($options_data['options']))?$options_data['options']:false;

        // group management
        if (is_array($options) && isset($options_data['groups'])) {
            $groups = [];

            foreach ($options_data['groups'] as $group_key=>$group_data) {
                foreach ($options as $data) {
                    if (!isset($data[$group_key]) && isset($group_data['null'])) {
                        // insertion in the group of values equal to "null"
                        $gkey = $group_key.'_null';

                        if (!isset($groups[$gkey])) {
                            $groups[$gkey] = ['attributes'=>['label'=>$group_data['null'],'optgroup'=>true] , 'items'=>[]];
                        }
                        array_push($groups[$gkey]['items'], $data);
                    } elseif (isset($data[$group_key])) {
                        // insertion into the group with a specified value
                        $group_val = $data[$group_key];

                        if (isset($group_data[$group_val])) {
                            $gkey = $group_key.'_'.$group_val;

                            if (!isset($groups[$gkey])) {
                                $groups[$gkey] = ['attributes'=>['label'=>$group_data[$group_val],'optgroup'=>true] , 'items'=>[]];
                            }
                            array_push($groups[$gkey]['items'], $data);
                        }
                        if (isset($group_data['?'])) {
                            // insertion in the group "any value"
                            $gkey = $group_key.'_?';

                            if (!isset($groups[$gkey])) {
                                $groups[$gkey] = ['attributes'=>['label'=>$group_data['?'],'optgroup'=>true] , 'items'=>[]];
                            }
                            array_push($groups[$gkey]['items'], $data);
                        }
                    }
                }
            }

            $options = [];
            foreach ($groups as $group_data) {
                array_push($options, $group_data['attributes']);
                $options = array_merge($options, $group_data['items']);
            }
        }

        if (!is_array($options)) {
            $options = [];
        }

        if (isset($options_data['first_empty'])) {
            if (is_array($options_data['first_empty'])) {

                // there are several items to add first
                if (isset($options_data['first_empty'][0]) && is_array($options_data['first_empty'][0])) {
                    $options = array_merge($options_data['first_empty'], $options);
                } else {
                    // there is only one item to add
                    array_unshift($options, $options_data['first_empty']);
                }
            } elseif ($options_data['first_empty']===true) {
                $first = [];
                $first[$options_data['label_key']] = '';

                if (in_array($options_data['label_key'], $value_key)) {
                    $first[$value_key[0]] = '';
                } else {
                    $first[$value_key[0]] = ''; //0
                }

                array_unshift($options, $first);
            }
        }

        $current_value = null;
        $current_label = null;

        foreach ($options as $index=>$data) {
            if (is_array($data)) {
                if (isset($options_data['indentation']) && isset($data[$options_data['indentation']])) {
                    $data[$label_key] = str_repeat('&nbsp;&nbsp;&nbsp;', intval($data[$options_data['indentation']])) . $data[$label_key];
                    unset($data[$options_data['indentation']]);
                }


                if (isset($options_data['exclude_attributes'])) {
                    $exclude_list = $options_data['exclude_attributes'];
                } else {
                    $exclude_list = [];
                }

                if (isset($data['optgroup'])) {
                    $label = '';
                    if (isset($data[$label_key]) && !isset($data['label'])) {
                        $data['label'] = $data[$label_key];
                        unset($data[$label_key]);
                    }
                    unset($data['optgroup']);

                    // for an optgroup, the text is displayed in the label attribute, not in the tag

                    $select->add_child(Html::tag(H::OPTGROUP, $data));
                } else {
                    $option_attributes = $data;
                    unset($option_attributes[$label_key]);

                    foreach ($exclude_list as $e) {
                        if (isset($option_attributes[$e])) {
                            unset($option_attributes[$e]);
                        }
                    }
                    $value = [];

                    foreach ($value_key as $v) {
                        if (isset($data[$v])) {
                            array_push($value, $data[$v]);
                            unset($option_attributes[$v]);
                        }
                    }
                    $option_attributes['value'] = implode(',', $value);

                    if ($default_value == $option_attributes['value']) {
                        $option_attributes['selected'] = 'selected';
                        $current_value = $default_value;
                        $current_label = $data[$label_key];
                    }

                    $label = $data[$label_key] != '' ? stripslashes($data[$label_key]) : '';
                    $select->add_child(Html::tag(H::OPTION, $option_attributes, stripslashes($label)));
                }
            } else {
                $option_attributes = $data;
                $option_attributes['value'] = $index;
                unset($option_attributes[$options_data['value_key']]);
                unset($option_attributes[$label_key]);

                if ($default_value == $index) {
                    $option_attributes['selected'] = 'selected';
                    $current_value = $default_value;
                    $current_label = $default_value;
                }
                $select->add_child(Html::tag(H::OPTION, $option_attributes, $data));
            }
        }

        $attributes['type'] = H::SELECT;
        $attributes['value'] = $select;

        $selector = H::_input($attributes);

        $selector->current_value = $current_value;
        $selector->current_value_label = $current_label;

        return $selector;
    }

    /**
     * The input autocomplete, will search in DB depending the typing or the user to propose a list of available value containing the string. 
     * Other element in the parent form with 'data-autocomp' in parameters will be sent with this input 
     * 
     * It's working by sending ajax requests from H_ui_autocomplete to the default autocomplete function in HelPHP_module.
     * Of course it can be extended in a HelPHP module.
     * 
     * @param array $params with key pairs : 
     *              name -> the name for the posted input
     *              value -> the current id for the hidden field
     *              value_label -> the label for the id, in the text field
     *              label -> is the label to put before the visible field in the form.
     *              class -> css class to add
     * @param null $table_name the name of the table in db
     * @param null $field_name the name of the field in db
     * @param bool $submit_on_change does it send the form when selecting in the list
     * @param bool $callback a js callback
     * 
     * @return Object Html div / autocomplete widget
     * 
     * @see take a look in the admin users module to find some input autocomplete at work with extended exemple.
     */
    public static function input_autocomplete($params = [], $table_name = null, $field_name = null, $submit_on_change = false, $callback = false) {
        // prepare parameters for js
        $settings = [
            'name' => $params['name'],
            'centraldb' => $params['centraldb'],
            'table_name' => $table_name,
            'field_name' => $field_name,
            'confirm' => $params['data-confirm'] ?? false,
            'submit' => $submit_on_change ? true : false,
            'callback' => $callback,
            'new_value' => $params['new_value'] ?? false
        ];

        // to display current value of the autocomplete field
        // value is the id in database, value_label is the text to display
        $value = $params['value'] ?? 0;
        $value_label = $params['value_label'] ?? '';

        $placeholder = $params['placeholder'] ?? '';
        $label = $params['label'] ?? '';
        $dom_id = $params['dom_id'] ?? H::get_unique_id();
        $css = $params['class'] ?? '';
        $id = $params['id'] ?? '';

        $base_id = 'autocomplete_'.$params['name'].$dom_id.'_';

        $div = H::DIV(['id'=>$id, 'class'=>$css.' hlp_autocomplete_container', 'label'=>$label, 'label_for'=>$base_id.'input_search']);
            // display the current value
            $current = H::DIV(['class'=>'hlp_autocomplete_current', 'id'=>$base_id.'current_display'], $value_label);
            $hidden_id = H::input_hidden(['name'=>$params['name'], 'value'=>$value, 'id'=>$base_id.'current_id', 'data-alwaysposted'=>1]);

            // this div is here for placement (css)
            
            $input_search = H::input_text(['class'=>'hlp_autocomplete_input', 'name'=>'autocomplete_search', 'id'=>$base_id.'input_search', 'placeholder'=>$placeholder, 'autocomplete'=>'off']);
            $list_result = H::DIV(['class'=>'hlp_autocomplete_search_list', 'id'=>$base_id.'search_list']);
            // $sub_div->add_child($input_search);

            $js = 'helphp_timeout(\'H_ui_autocomplete.create_instance("'.$dom_id.'", '.addslashes(json_encode($settings)).');\');';
            // $js = 'autocomp.init("'.$params['name'].'", "'.$table_name.'",'.$field_name.','.$submit_on_change.',"'.$params['data-confirm'].'",'.$callback.',"'.$dom_id.'");';
            $script = H::script($js, ['autoremove'=>true]);
            
        $div->add_child([$hidden_id, $current, $input_search->label_tag(), $input_search ,$list_result, $script]);
        return $div;
    }
    
    /*
     * $list_data 
     */

    /**
     * the input_precomplete is different from input_autocomplete, as it do not ask for data in db at each new char, because it already got 
     * the db table inside ansdrequested only one time.
     * It's perfect for a small batch of data like to choose a country or search... 
     * you'll find exemple in the admin category module.
     * 
     * @param Array $params with key pairs : 
     *              name -> the name for the posted input
     *              value -> the current id for the hidden field
     *              value_label -> the label for the id, in the text field
     *              label -> is the label to put before the visible field in the form.
     *              class -> css class to add
     * @param Array $list_data is an array like the opt_data in select, with label_key, value_key, data, (optionnal) first_empty
     * @param Bool $submit_on_change can be false | true, submit form on change
     * @param False|String $callback  a javascript callback to call when change
     * 
     * @return Object Html div / precomplete widget
     */
    public static function input_precomplete($params, $list_data = [], $submit_on_change = false, $callback = false) {

        // value is the id in database, value_label is the text to display
        $value = $params['value'] ?? 0;
        $value_label = $params['value_label'] ?? '';

        $placeholder = $params['placeholder'] ??'';
        $label = $params['label'] ?? '';
        $dom_id = $params['dom_id'] ?? H::get_unique_id();
        $css = $params['class'] ?? '';
        $id = $params['id'] ?? '';
        $params['name']= $params['name'] ?? '';
        $params['new_value']=$params['new_value'] ?? false;

        $required =$params['data-required'] ?? false;

        $base_id = 'precomplete_'.$params['name'].$dom_id.'_';

        $div = H::DIV(['id'=>$id, 'class'=>$css.' hlp_precomplete_container', 'label'=>$label, 'label_for'=>$base_id.'input_search']);
            
            $current = H::DIV(['class'=>'hlp_precomplete_current', 'id'=>$base_id.'current_display'], $value_label);
            $hidden_id = H::input_hidden(['name'=>$params['name'], 'value'=>$value, 'id'=>$base_id.'current_id', 'data-alwaysposted'=>1]);
            // $hidden_val = H::input_hidden(['id'=>$base_id.'_value', 'name'=>$params['name'], 'value'=>'']);
            
            $input_search = H::input_text(['class'=>'hlp_precomplete_input', 'name'=>'precomplete_search', 'id'=>$base_id.'input_search', 'placeholder'=>$placeholder, 'autocomplete'=>'off']);
            if ($required) $input_search->set_attribute('data-required', $required);
            $list_search = H::DIV(['class'=>'hlp_precomplete_search_list hidden', 'id'=>$base_id.'list_search']);
            
            $js_data = [];
            if ($list_data && isset($list_data['data']) && $list_data['data'] && isset($list_data['value_key']) && isset($list_data['label_key'])){

                // add an empry row at first place. key will be the index in the JS array
                if (isset($list_data['first_empty']) && $list_data['first_empty']){
                    $key = count($list_data['data']);
                    $label = \is_string($list_data['first_empty']) ? $list_data['first_empty'] : '&nbsp;'; // insecable space
                    $row = H::DIV(['id'=>$base_id.'row_'.$key, 'class'=>'hlp_precomplete_row', 'data-key'=>$key], $label);
                    $list_search->add_child($row);
                }

                foreach($list_data['data'] as $key => $line){
                    $label = isset($line['_level_']) ? 
                        \str_pad($line[$list_data['label_key']], \strlen($line[$list_data['label_key']]) + ($line['_level_'] * 2), html_entity_decode("&nbsp;"), \STR_PAD_LEFT) : 
                        $line[$list_data['label_key']];
                    $row = H::DIV(['id'=>$base_id.'row_'.$key, 'class'=>'hlp_precomplete_row', 'data-key'=>$key], $label);
                    $list_search->add_child($row);
    
                    array_push($js_data, ['id'=>$line[$list_data['value_key']], 'name'=>$line[$list_data['label_key']]]);
                }

                // add the empry tow at last place to correspond with key given before
                if (isset($list_data['first_empty']) && $list_data['first_empty']) array_push($js_data, ['id'=>0, 'name'=>'first_empty']);

            } else {
                $row = H::DIV(['id'=>$base_id.'_row_empty', 'class'=>'hlp_precomplete_row empty'], isset($list_data['empty']) ? $list_data['empty'] : '');
                $list_search->add_child($row);
            }

            // prepare parameters for js
            $settings = [
                'name' => $params['name'],
                'new_value' => $params['new_value'],
                'data' => $js_data,
                'confirm' => isset($params['data-confirm']) ? $params['data-confirm'] : false,
                'submit' => $submit_on_change,
                'callback' => $callback,
            ];

            $js = 'helphp_timeout(\'H_ui_precomplete.create_instance("'.$dom_id.'", '.addslashes(json_encode($settings)).');\');';
            $script = H::script($js, ['autoremove'=>true]);

        $div->add_child( [$current, $hidden_id, $input_search->label_tag(), $input_search, $list_search, $script] );
        
        return $div;
    }

    /**
     * the purpose of the input order is to permet to ordonnate a list by a numeric value,
     * and to manipulate it thru drag & drop or manual edition
     * in case you want to manipulate lines of data, you can enclose each line with a DIV with the 
     * attribute  'data-order_parent'=>'sort_order['.$post['key'].']' sort order will be the name of the input_order.
     * there can multiple inpu_order series, do it's the name of it that identifie and seperated the various input_order group.
     * exemple: in this exemple we list a DB prepared_query_list and create a div for each line of value and an input order
     * each one, so for each line :
     * 
     * //we create a div for the line 
     * $linediv = H::DIV(['class'=>$this->css.'field', 'data-order_parent'=>'sort_order['.$post['key'].']'],$line['value']);
     * //first we check if an order is already  given...
     * $val_order = (isset($post['sort_order'])) ? $post['sort_order'] : ($post['key']+1);
     * $order = H::input_order(['name'=>'sort_order['.$post['key'].']', 'value'=>$val_order, 'class'=>$this->css.'order']);
     * we add the order input to the parent div of the line...
     * $linediv->add_child($order);
     * 
     * @param array $params, general parameter for the input like.
     *              Need to have name and value to work.
     * @param bool $editable, true if the order can be change with an input integer
     */
    public static function input_order($params, $editable = false, $callback = '') {
        if (!isset($params['name']) || !$params['name']){
            Utils::error_log('Missing name for input_order');
            Utils::error_log($params);
            return false;
        }
        if (!isset($params['value']) || $params['value'] == ''){
            Utils::error_log('Missing value for input_order');
            Utils::error_log($params);
            return false;
        }

        // we get back the basename without [] to identify the input order series in case there are multiple ones...
        $base_name = explode('[', $params['name'])[0];
        $id = \preg_replace('/.+\[(\d+)]/', '$1', $params['name']);

        $always_posted = isset($params['data-alwaysposted']) ? $params['data-alwaysposted'] : false;
        $unique_id = 'ord'.H::get_unique_id();

        $params['class'] = isset($params['class']) ? $params['class'] : '';
        
        $label = isset($params['label']) ? $params['label'] : '';
        $container = H::DIV(['class'=>'hlp_input_order_container '.$params['class'], 'id'=>$unique_id.'-container', 'label'=>$label]);

            $hidden_value = H::input_hidden(['id'=>$unique_id.'-hidden', 'name'=>$params['name'], 'value'=>$params['value'], 'data-alwaysposted'=>$always_posted, 'data-id'=>$id]);
            
            $btn_down = H::button_icon('minus-circle', ['id'=>$unique_id.'-down', 'class'=>'hlp_input_order_down '.$params['class'], 'no_doubleclick'=>true]);
            $display_value = H::SPAN(['id'=>$unique_id.'-display', 'class'=>'hlp_input_order_display '.$params['class']], $params['value']);
            $btn_up = H::button_icon('plus-circle', ['id'=>$unique_id.'-up', 'class'=>'hlp_input_order_up '.$params['class'], 'no_doubleclick'=>true]);
            $drag = H::button_icon('move', ['id'=>$unique_id.'-drag', 'class'=>'hlp_input_order_drag '.$params['class']]);

        $container->add_child( [$hidden_value, $btn_up, $display_value, $btn_down, $drag] );

        if ($editable){
            $btn_cancel = H::button_icon('x', ['id'=>$unique_id.'-cancel', 'class'=>'hlp_input_order_cancel hidden '.$params['class']]);
            $input_edit = H::input_integer(['id'=>$unique_id.'-edit', 'class'=>'hlp_input_order_edit hidden '.$params['class']]);
            $btn_valid = H::button_icon('check', ['id'=>$unique_id.'-valid', 'class'=>'hlp_input_order_valid hidden '.$params['class']]);
            $container->add_child( [$btn_cancel, $input_edit, $btn_valid] );
        }

        $script = H::script('H_ui.init_input_order("'.$base_name.'", "'.$unique_id.'", '.json_encode($editable).', "'.addslashes($callback).'");', ['autoremove'=>true]);
        $container->add_child( $script );

        return $container;
    }

    /**
     * Its name says all !
     *
     * @return Object Html body and html closing tags
     * 
     */
    public static function close_document() {
        $output = Html::close_tag(Html::BODY);
        $output .= Html::close_tag(Html::HTML);

        return $output;
    }

    /**
     * Description for ajax_call
     * This function generates a script that will execute an AJAX call to the specified URL with the provided data.
     * Useful in rare case when you can't initialize de JS module.
     *
     * @param String $url
     * @param Array $data of values to send 
     * @param null|String $target to receive result
     * @param int $delay before launching the script
     * 
     * @return Html script tag to execute the ajax call
     * 
     */
    public static function ajax_call($url, $data, $target = null, $delay = 0) {
        $function_name = 'tmpfunc_'.round(microtime(true)*1000).rand(0, 50);

        $script = '
        function '.$function_name.'(){
            let settings = {};
            settings.url = "'.$url.'";
            settings.method = "POST";
        ';

        if ($target) {
            $script .= 'settings.dom_target = "'.$target.'";';
        }

        $tmp = [];
        foreach ($data as $key=>$value) {
            $str = '"'.$key.'":';
            if (is_string($value)) {
                $str .= '"'.$value.'"';
            } elseif (is_array($value) || is_object($value)) {
                $str .= json_encode($value);
            } else {
                $str .= $value;
            }
            array_push($tmp, $str);
        }

        $script .= '
            settings.data = {'.implode(',', $tmp).'};
            h.a.send(settings,'.$delay.');
        }
        '.$function_name.'();';

        return H::script($script, ['id'=> H::get_unique_id() , 'defer'=>true , 'autoremove'=>true]);
    }

    /**
     * Create a script tag to load a script after the first load of document is done.
     *
     * @param String $url
     * 
     * @return Object html script
     * 
     */
    public static function script_loader($url) {
        $output = H::group('');
        $id = H::get_unique_id();
        $output->add_child(H::script(null, ['src'=>$url , 'id'=>$id , 'defer'=>true]));
        return $output;

    }
        /**
     * Create a link tag to load a css after the first load of document is done, then move it to document head.
     *
     * @param String $url
     * 
     * @return Object html link
     * 
     */
    public static function css_loader($url) {
        $output = H::group('');
        $id = H::get_unique_id();
        $output->add_child(H::LINK(['href'=>$url , 'type'=>'text/css' , 'rel'=>'stylesheet','id'=>$id]));
        $output->add_child(H::script('H_dom.move_to_header("'.$id.'");', ['id'=> H::get_unique_id() , 'autoremove'=>true]));
        return $output;
    }

    // create class instance for a script
    // 
    // if we give it a numerical value it will represent the new delay in milliseconds
    // this allows you to run a punctual piece of javascript, without polluting the page afterwards
    /**
     * Create script tag with some special features...
     * if an "autoremove" attribute is specified the script tag will be automatically removed from the html page
     * after 50 milliseconds (default value) or a specified delay in milliseconds.
     * So if the user inspect the page source to find this js, there will be nothing (useful for security).
     * this allows you to run a punctual piece of javascript, without polluting the page afterwards
     * If async="async": The script is executed asynchronously with the rest of the page (the script will be executed while the page continues the parsing)
     * If async is not present and defer="defer": The script is executed when the page has finished parsing 
     * If neither async or defer is present: The script is fetched and executed immediately, before the browser continues parsing the page
     *
     * @param mixed $content
     * @param null $attributes
     * 
     * @return Object html script
     * 
     */
    public static function script($content, $attributes = null) {

        if (isset($attributes['async'])) {
            if ($attributes['async']) {
                $attributes['async']='async';
            } else {
                unset($attributes['async']);
            }
        }

        if (isset($attributes['defer'])) {
            if ($attributes['defer']) {
                $attributes['defer']='defer';
            } else {
                unset($attributes['defer']);
            }
        }

        if (isset($attributes['autoremove'])) {
            if ($attributes['autoremove'] === false) {
                unset($attributes['autoremove']);
            } else {
                $delay = 50;
                if (is_numeric($attributes['autoremove'])) {
                    $delay = intval($attributes['autoremove'])+1;
                }
                unset($attributes['autoremove']);
                if (!isset($attributes['id'])) {
                    $attributes['id'] = H::get_unique_id();
                }
                if ($content === false) $content = '';
                $content .= 'setTimeout(function(){ let s = document.getElementById("'.$attributes['id'].'"); if(s){ s.remove();} } , '.$delay.');';
            }
        }

        if (!is_array($attributes)) {
            $attributes = array('type'=>'text/javascript');
        } else {
            $attributes['type'] = 'text/javascript';
        }

        $tag = new Html(Html::SCRIPT, $attributes);
        if (!is_null($content)) {
            $tag->add_child($content);
        }

        return $tag;
    }
    
    /**
     * The fantastic multistate button ! with tooglable menu !
     * Will create a div containing a list of buttons with only one active and visible.
     * clicking on the current button will execute its events then display the next one.
     *
     * @param Array $params parameters array for container (id, class, dataset...)
     * @param Array Object $btns the list of element to put inside container
     * @param Int   $index_active the index in btns array of the current active button, the one that is selected by default
     *              if -1, no buttons active by default but the fist one will be used as avatar
     * @param String $side add "side"css class to the element to indicate on which side should toogle the button menu
     * @param mixed $toggle_mode=false
     * 
     * @return Object Html sort of button
     * 
     */
    public static function multi_state_button($params, $btns, $index_active, $side='', $toggle_mode=false) {
        if (!isset($params['id'])){
            $params['id'] = H::get_unique_id();
        }
        
        $css = isset($params['class']) ? $params['class'] : '';
        $params['class'] = isset($params['class']) ? $params['class'].' hlp_multi_state_button_container' : 'hlp_multi_state_button_container';
        if ($side != ''){
            $params['class'].= ' '.trim($side);
        }
        
        $container = H::DIV($params);
            $toggler = H::DIV(['class'=>$css.' hlp_multi_state_button_toggler','id'=>$params['id'].'-toggler']);
        $container->add_child($toggler);
        $sub = H::DIV(['class'=>$css.' hlp_multi_state_button_toggle','id'=>$params['id'].'-toggle']);
        foreach($btns as $key => $btn){
            $btn->add_class('hlp_multi_state_button_item');
            if ($key != $index_active){
                $btn->add_class('state_disable');
            } else {
                $btn->add_class('state_active');
            }
            $sub->add_child($btn);
        }
        $container->add_child($sub);
        $container->add_child(H::script('helphp_timeout(\'H_ui_multi_state.create_instance("'.$params['id'].'", "'.$side.'", '.addslashes(json_encode($toggle_mode)).');\');',['autoremove'=>'autoremove']));
        return $container;
    }

    /**
     * Load_js is used by new_document() only in dev mode.
     * It's used to cp the available js of module in tmp folder on the instance to permit debug .
     *
     * @param bool $public
     * @param string $pre the base usl pre tmp
     * @param string $refresher a timestamp or any changing value to force reload.
     * 
     * @return Objects Html bunch of scripts tag
     * 
     */
    public static function load_js($public = true, $pre = '', $refresher = '') {
        global $CONFIG;
        // copy every common js to tmp folder
        //$baseroot = dirname($_SERVER['DOCUMENT_ROOT']);
        $baseroot = $CONFIG::HELPHP_FOLDER;
        $temp_path = $CONFIG::HOME_FOLDER.'js/tmp';
        $cmd = 'cp '.$baseroot.'js/ajax.js '.$temp_path.'/ajax.js';
        $cmd.= ' && cp '.$baseroot.'js/dom.js '.$temp_path.'/dom.js';
        $cmd.= ' && cp '.$baseroot.'js/events.js '.$temp_path.'/events.js';
        $cmd.= ' && cp '.$baseroot.'js/generics.js '.$temp_path.'/generics.js';
        $cmd.= ' && cp '.$baseroot.'js/history.js '.$temp_path.'/history.js';
        $cmd.= ' && cp '.$baseroot.'js/init.js '.$temp_path.'/init.js';
        $cmd.= ' && cp '.$baseroot.'js/ui.js '.$temp_path.'/ui.js';
        $cmd.= ' && cp '.$baseroot.'js/validator.js '.$temp_path.'/validator.js';
        $cmd.= ' && cp '.$baseroot.'js/module.js '.$temp_path.'/module.js';
        
        // add them to the web page
        $include_path = $pre.'js/tmp/';
        $output = array();
        array_push($output, H::script('',array('src'=>$include_path.'generics.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'dom.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'events.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'ajax.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'validator.js'.$refresher , 'language'=>'Javascript')));
        if ($CONFIG::INCLUDE_JS_ANIMATE){
            $cmd.= ' && cp '.$baseroot.'js/animate.js '.$temp_path.'/animate.js';
            array_push($output, H::script('',array('src'=>$include_path.'animate.js'.$refresher , 'language'=>'Javascript')));
     
        }else{
            //place holder to avoid error when calling H_anim when desactivated
            array_push($output, H::script('class H_anim {constructor(opts){}}'));
        }

        array_push($output, H::script('',array('src'=>$include_path.'history.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'ui.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'module.js'.$refresher , 'language'=>'Javascript')));
        array_push($output, H::script('',array('src'=>$include_path.'init.js'.$refresher , 'language'=>'Javascript')));

        // do the same fot the modules
        foreach($CONFIG::MODULES_LIST as $moduleName => $module) {
            $type = $public ? 'public/' : $CONFIG::ADMIN_FOLDER;
            $js_path = $CONFIG::HOME_FOLDER.$type.$moduleName.'/'.$moduleName.'.js';
            if (!file_exists($js_path)) {
                $type = $public ? 'public/' : 'admin/';
                $js_path = $baseroot.'modules/'.$moduleName.'/'.$type.$moduleName.'.js';
            }
            if (file_exists($js_path)){
                // public and admin js have the same name. prefix them to make the difference
                $cmd.= ' && cp '.$js_path.' '.$temp_path.'/'.str_replace('/','-',$type).$moduleName.'.js';
                array_push($output, H::script('',array('src'=>$include_path.str_replace('/','-',$type).$moduleName.'.js'.$refresher , 'language'=>'Javascript')));
            }
        }
        
        exec($cmd, $outp, $retval);

        //don't forget permissions on /tmp to fix on install !
        return $output;
    }

    /**
     * button preview for admin to call preview module.
     */
    public static function preview_button($module, $id, $label = '', $admin = false, $extra_params = false) {
        if (!$module){
            Utils::error_log('call to preview_button missing module');
            return;
        }
        if (!$id){
            Utils::error_log('call to preview_button missing id');
        }
        $params = [
            'preview_action'=>'preview_module',
            'module'=>$module,
            'id'=>$id,
            'admin'=>$admin
        ];
        if ($extra_params) {
            $params = \array_merge($params, $extra_params);
        }
        $onclick = 'Preview_a.open_preview('.json_encode($params).');';
        $btn = H::BUTTON(['class'=>'btn_preview', 'onclick'=>$onclick, 'title'=>$label], $label);
        return $btn;
    }

    /**
     * group of html object.
     * This little and useful function create a new empty Html instance object.
     * So it can receive childs etc and permit various operation on groups of html objet to merge them etc.
     * Very often used for display_output :
     * $output = new H::group('my_display');
     * $output->add_child([...,...]); 
     * return $output; 
     * 
     * @param mixed $key_name=''
     * 
     * @return Object Html group
     * 
     */
    public static function group($key_name='') {
        $group = new Html();
        $group->tag_name = '';
        $group->key_name = $key_name;

        return $group;
    }

    /**
     * create HTML detail element for accordion effect.
     * @param Array Params : the usual attributes
     * @param String label : the classic label_tag
     */
    public static function detail($params, $label = null) {
        $details = H::DETAILS($params);
        if ($label) {
            $summary = H::SUMMARY(null, $label);
            $details->add_child( $summary );
        }

        return $details;
    }


    /**
     * Create tabs inside a page or modal, useful to avoid to rely on navigator tabs.
     * Take a look in admin blockeditor or document editor to see exemple of usage
     *
     * @param Array $params usual attributes
     * @param Array $labels the tab labels 
     * @param Array $contents the tabl contents
     * 
     * @return Html object tabs
     * 
     */
    public static function tabs($params, $labels, $contents) {

        if (count($labels) != count($contents)){
            Utils::error_log('Not same amount of labels and contents');
            Utils::error_log($labels);
            Utils::error_log($contents);
            return;
        }
        
        $callback = isset($params['callback']) ? $params['callback'] : false;
        $class = 'hlp_tabs';
        $extra_class = isset($params['class']) ? ' '.$params['class'] : '';
        $dom_id = isset($params['dom_id']) ? $params['dom_id'] : '¤'.H::get_unique_id();

        $base_id = isset($params['id']) ? $params['id'] : 'hlp_tabs'.$dom_id;

        $output = H::group('tabs-'.$dom_id);
            $container_labels = H::DIV(['class'=>$class.'_list_label'.$extra_class, 'id'=>$base_id.'_list_label']);
            $container_contents = H::DIV(['class'=>$class.'_list_content'.$extra_class, 'id'=>$base_id.'_list_content']);
            foreach($labels as $key => $label) {
                $dom_lab = H::DIV(['class'=>$class.'_label'.$extra_class,'id'=>$dom_id.'_label_'.$key], $label);
                $container_labels->add_child( $dom_lab );
                
                $dom_content = H::DIV(['class'=>$class.'_content'.$extra_class,'id'=>$dom_id.'_content_'.$key], $contents[$key]);
                $container_contents->add_child( $dom_content );
            }

            $params = ['callback'=>$callback, 'base_id'=>$base_id];
            $script = H::script('helphp_timeout(\'H_ui_tabs.create_instance("'.$dom_id.'", '.addslashes(json_encode($params)).');\');', ['autoremove'=>true]);

        $output->add_child([$container_labels, $container_contents, $script]);

        return $output;
    }
}