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


namespace helPHP\libs;

/**
 * @class HTML
 * 
 * it's the base class for all HTML tags and elements.
 * It allows to create HTML tags and elements, to add attributes, children,etc.
 * it's main purpose is to generate HTML code in a structured way, and manipulate it easily as an object,
 * and permit to avoid direct html writing inside PHP for a better reading of the code,
 * but also to push all the display at the end of the data processing.
 * 
 * It can be used to create complex HTML structures, such as forms, tables, lists, etc.
 * And it's the fundation of the H class that is creating more complex html object.
 * 
 * all constants are there to bridge to html tags, if html evolve or if you want to connect to another lib display
 * 
 * this class use magic methods to help manipulate html object.
 * @see __get()
 * @see __set()
 * @see __unset()
 * 
 * @package helPHP\libs
 * */
class Html
{
    const HTML = 'html';
    const HEAD = 'head';
    const TITLE = 'title';
    const META = 'meta';
    const SCRIPT = 'script';
    const STYLE = 'style';
    const LINK = 'link';
    const PARAM = 'param';

    const BODY = 'body';

    const DIV = 'div';
    const SPAN = 'span';
    const IMG = 'img';
    const A = 'a';

    const FORM = 'form';
    const INPUT = 'input'; // https://developer.mozilla.org/fr/docs/Web/HTML/Element/input
    const BUTTON = 'button';
    const TEXTAREA = 'textarea';
    const LABEL = 'label';
    const LEGEND = 'legend';
    const FIELDSET = 'fieldset';
    const DATALIST = 'datalist'; // https://developer.mozilla.org/fr/docs/Web/HTML/Element/datalist

    const SELECT = 'select';
    const OPTION = 'option';
    const OPTGROUP = 'optgroup';

    const VIDEO = 'video';
    const AUDIO = 'audio';
    const CANVAS = 'canvas';
    const SOURCE = 'source';

    const BR = 'br';
    const HR = 'hr';

    const UL = 'ul';
    const LI = 'li';

    const TABLE = 'table';
    const TR = 'tr';
    const TD = 'td';
    const TH = 'th';
    const THEAD = 'thead';
    const TBODY = 'tbody';
    const TFOOT = 'tfoot';

    const SVG = 'svg';
    const USE = 'use';
    const IMAGE = 'IMAGE';

    const DETAILS = 'details';
    const SUMMARY = 'summary';

    const _X_NORMAL = '&#10005;';
    const _X_BOLD = '&#10006;';

    const _CHECKMARK_NORMAL = '&#10003;';
    const _CHECKMARK_BOLD = '&#10004;';

   //https://www.toptal.com/designers/htmlarrows/symbols/
    const _LEFT_ARROW = '&larr;';
    const _RIGHT_ARROW = '&rarr;';
    const _UP_ARROW = '&uarr;';
    const _DOWN_ARROW = '&darr;';

    const _RIGHT_ARROW_HEAD = '&#10148;';

    const _LEFT_DASHED_ARROW = '&#8672;';
    const _RIGHT_DASHED_ARROW = '&#8674;';
    const _UP_DASHED_ARROW = '&#8673;';
    const _DOWN_DASHED_ARROW = '&#8675;';

    const _STAR_FULL = '&#9733;'; // &starf;
    const _STAR_EMPTY = '&#9734;'; // &star;

    // constants used by the quick edit function
    const QUICK_EDIT_ID         = 'qe_i';
    const QUICK_EDIT_DATA       = 'qe_d';
    const QUICK_EDIT_FIELD      = 'qe_f';
    const QUICK_EDIT_TYPE       = 'qe_t';
    const QUICK_EDIT_TYPE_INDEX = 'qe_ti';

    const simple_tags = [
            Html::IMG,
            Html::IMAGE,
            Html::BR,
            Html::HR,
            Html::INPUT,
            Html::LINK,
            Html::META,
            Html::PARAM,
            Html::USE
        ];

    //------------------------------------------------------------------------------------
    
    /**
     * the unique ID identify one html element, and as it is unique, can be used by css of js selectors.
     *
     * @var int
     */
    protected static $unique_id = 0;
    protected $tag_name = '';
    /**
     * All html tag got attributes, and this array store them to make them more easy to manipulate.
     * 
     * @var array
     */
    protected $attributes = array();
    protected $data = array();
    public $children = array();
    public $items_after = array();
    protected $key_name = '';
    public $debug = false;
    public $extra = [];
    protected $simple = false; // true if the tag must be closed by a closing one
    public $label = '';
    public $label_for = false; // to indicate the for of the label generated by label_tag()
    public $current_value = null;
    public $current_value_label = null;
    //------------------------------------------------------------------------------------------------
    // global functions
    //
    
    /**
     * create the unique_id for html tag
     *
     * @return String
     * 
     */
    public static function get_unique_id()
    {
        return 'DOM_'.time().\random_int(100,10000000);
    }
    
    /**
     * check if the tag is a simple one like <br> with no closing tag 
     *
     * @param Mixed $tag_name
     * 
     * @return Bool true false
     * 
     */
    public static function is_simple($tag_name)
    {
        return in_array($tag_name, Html::simple_tags);
    }

    
    /**
     * Create a new Html instance for a tag
     *
     * @param String $tag_name
     * @param Null $attributes
     * @param Null $content
     * 
     * @return Instance object
     * 
     */
    public static function tag($tag_name = '', $attributes = null, $content = null)
    {
        return new Html($tag_name, $attributes, $content);
    }

    /**
     * Create an opening tag with its attributes
     *
     * @param Mixed $tag_name
     * @param Null $attributes
     * 
     * @return String 
     * 
     */
    public static function open_tag($tag_name, $attributes = null)
    {
        $tag_name = strtolower($tag_name);

        $tag = new Html($tag_name, $attributes);
        return $tag->open_html_tag();
    }
    
    /**
     * Create a closing tag
     *
     * @param Mixed $tag_name
     * 
     * @return String
     * 
     */
    public static function close_tag($tag_name)
    {
        return '</'.strtolower($tag_name).'>';
    }


    /**
     * Will return the current HTML content as string.
     * Echo force the call to magic method __toString that will convert everything with full_html()
     *
     * @param Mixed $htmlContent
     * 
     * @return String html 
     * 
     */
    public static function output($htmlContent)
    {
        echo $htmlContent;
    }

    //------------------------------------------------------------------------------------------------
    // instance functions
    //
    
    public function __construct($tag_name  = '', $attributes = null, $content = null)
    {
        $tag_name = strtolower($tag_name);
        // the label attribute is used to define the possible label displayed next to an input
        // it must not be kept in the list of attributes
        // EXCEPT for an element of the type OPTGROUP which one finds only in the SELECT
        if (isset($attributes['label']) && $tag_name != Html::OPTGROUP) {
            $this->label = $attributes['label'];
            if (!isset($attributes['id'])) {
                $attributes['id'] = Html::get_unique_id();
            }
            unset($attributes['label']);

            if (isset($attributes['label_for'])){
                $this->label_for = $attributes['label_for'];
                unset($attributes['label_for']);
            }
        }
        if ($tag_name == Html::IMG && !isset($attributes['alt']) && isset($attributes['src'])) {
            $imgName = explode('/', $attributes['src']);
            $imgName = $imgName[(count($imgName) - 1)];
            $imgName = explode('.', $imgName)[0];
            $attributes['alt'] = $imgName;
        }
        
        if ($tag_name == 'button' && !isset($attributes['type'])){
            $attributes['type'] = 'button';
        }

        $this->tag_name = $tag_name;

        if (is_array($attributes)) {
            foreach ($attributes as $key=>$val) {
                if (substr($key, 0, 5)=='data-') {
                    $key = substr($key, 5, strlen($key)-5);
                    $this->data[$key] = $val;
                } else {
                    $this->attributes[$key] = $val;
                }
            }
        }

        if ($content !== null) {
            $this->add_child($content);
        }

        $this->simple = Html::is_simple($tag_name);
    }

    /**
     * Retrieve any attribute of the tag via: attr_ {attribute name}
     * ex: $ tag-> attr_style to get the css style
     * the 'id', 'name' and 'class' attributes can be retrieved directly without using the attr_ prefix
     * For non-standard attributes defined by the user, you must enter data_ instead of attr_
     * once converted to html, these attributes will be named "data- {attribute name}" to become
     * accessible for JS conterpart thru dataset property.
     * 
     * ex : <div id="stuff" style="color:red;" data-custom="stuff">
     * for div equivalent objet as $div :
     * $custom=$div.data_custom; <- will trig a __get
     *
     * @param String $property
     * 
     * @return String or null
     * 
     */
    public function __get($property)
    {
        if ($this->debug) {
            Utils::error_log('get prop : '.$property);
        }

        if (strtolower($property) == 'tagname') {
            return $this->tag_name;
        }

        $result = null;

        switch ($property) {
            case 'id':
            case 'name':
            case 'class':
                if (isset($this->attributes[$property])) {
                    $result = $this->attributes[$property];
                }
            break;

            default:
                switch (substr($property, 0, 5)) {
                    case 'attr_':
                        $attr_name = substr($property, 5, strlen($property)-5);

                        if (isset($this->attributes[$attr_name])) {
                            $result = $this->attributes[$attr_name];
                        }
                    break;

                    case 'data_':
                        $attr_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->data[$attr_name])) {
                            $result = $this->data[$attr_name];
                        }
                    break;

                    // retrieving a child element
                    case 'chld_':
                        $child_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->children[$child_name])) {
                            $result = $this->children[$child_name];
                        } else {
                            Utils::error_log('Child "'.$child_name.'" not found.');
                            Utils::error_log(array_keys($this->children));
                            Utils::error_log($this->children);
                        }
                    break;

                    // retrieving an associated element after the current element
                    case 'next_':
                        $child_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->items_after[$child_name])) {
                            $result = $this->items_after[$child_name];
                        } else {
                            Utils::error_log('Next item "'.$child_name.'" not found.');
                            Utils::error_log(array_keys($this->items_after));
                            Utils::error_log($this->items_after);
                        }
                    break;
                }
            break;
        }

        return $result;
    }
    
    /**
     * Magic method to set attributes of the HTML element.
     *
     * @param String $property The name of the property to set. Can be 'id', 'name', 'class', or prefixed with 'attr_', 'data_', 'chld_', or 'next_'.
     * @param Mixed $value The value to set for the property.
     *
     * @return Void
     */
    public function __set($property, $value)
    {
        switch ($property) {
            case 'id':
            case 'name':
            case 'class':
                $this->attributes[$property] = $value;
            break;

            default:
                switch (substr($property, 0, 5)) {
                    case 'attr_':
                        $attr_name = substr($property, 5, strlen($property)-5);
                        $this->attributes[$attr_name] = $value;
                    break;

                    case 'data_':
                        $attr_name = substr($property, 5, strlen($property)-5);
                        $this->data[$attr_name] = $value;
                    break;

                    case 'chld_':
                        $child_name = substr($property, 5, strlen($property)-5);
                        if (is_numeric($child_name)) {
                            $child_name = intval($child_name);
                        }
                        if (is_array($value)) {
                            Utils::error_log('WARNING : cannot set an array as value for chld_'.$child_name);
                            $value = array_shift($value);
                        }
                        $this->add_child([$child_name=>$value]);
                    break;

                    case 'next_':
                        $child_name = substr($property, 5, strlen($property)-5);
                        if (is_numeric($child_name)) {
                            $child_name = intval($child_name);
                        }
                        if (is_array($value)) {
                            Utils::error_log('WARNING : cannot set an array as value for next_'.$child_name);
                            $value = array_shift($value);
                        }
                        $this->add_after([$child_name=>$value]);
                    break;
                }
            break;
        }
    }
    
    /**
     * Magic method to unset an attribute of the HTML element.
     *
     * @param String $property The name of the property to unset. Can be 'id', 'name', 'class', or prefixed with 'attr_', 'data_', 'chld_', or 'next_'.
     *
     * @return Void
     */
    public function __unset($property)
    {
        switch ($property) {
            case 'id':
            case 'name':
            case 'class':
                if (isset($this->attributes[$property])) {
                    unset($this->attributes[$property]);
                }
            break;

            default:
                switch (substr($property, 0, 5)) {
                    case 'attr_':
                        $attr_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->attributes[$attr_name])) {
                            unset($this->attributes[$attr_name]);
                        }
                    break;

                    case 'data_':
                        $attr_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->data[$attr_name])) {
                            unset($this->data[$attr_name]);
                        }
                    break;

                    case 'chld_':
                        $child_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->children[$child_name])) {
                            unset($this->children[$child_name]);
                        }
                    break;

                    case 'next_':
                        $item_name = substr($property, 5, strlen($property)-5);
                        if (isset($this->items_after[$item_name])) {
                            unset($this->items_after[$item_name]);
                        }
                    break;
                }
            break;
        }
    }
    
    /**
     * Copies attributes, data, and extra properties from the current HTML element to another.
     *
     * @param Html $destination_tag The target HTML element to copy attributes to.
     * @return Void
     */
    public function copy_attributes_to($destination_tag)
    {
        foreach ($this->attributes as $key => $value) {
            $destination_tag->set_attribute($key, $value);
        }

        foreach ($this->data as $key => $value) {
            $destination_tag->data[$key] = $value;
        }

        foreach ($this->extra as $key => $value) {
            $destination_tag->extra[$key] = $value;
        }
    }
    
    /**
     * Adds a CSS class to the HTML element's class attribute.
     *
     * @param String $value The CSS class to add.
     * @return Void
     */
    public function add_class($value)
    {
        if (!isset($this->attributes['class'])) {
            $this->attributes['class'] = $value;
        } else {
            $this->attributes['class'] .= ' ' . $value;
        }
    }
    
    /**
     * Sets an HTML attribute for the element.
     *
     * @param String $name The name of the attribute.
     * @param Mixed $value The value of the attribute.
     * @return Void
     */
    public function set_attribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    
    /**
     * Delete an HTML attribute for the element.
     * can be useful for boolean attributes, like "controls" for video
     *
     * @param String $name The name of the attribute.
     * @return Void
     */
    public function del_attribute($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * Sets an extra property for the element.
     *
     * @param String $name The name of the extra property.
     * @param Mixed $value The value of the extra property.
     * @return Void
     */
    public function set_extra($name, $value)
    {
        $this->extra[$name] = $value;
    }

    public function add_debug($item)
    {
        $this->add_child(var_export($item));
    }
    
    /**
     * Adds a child element or a list of child elements to the current HTML element.
     *
     * @param Mixed $child_list The child element(s) to add. Can be a single Html object, a string, or an array of Html objects or strings.
     * @param String $key_name Optional key name for the child element if it's a single element and needs a specific key.
     *
     * @return Int|Bool Returns the index of the last added child if successful, or false if the element is simple or the child list is invalid.
     *               If a key is provided the child is added by the given key and the function return the index of the added element.
     */
    public function add_child($child_list, $key_name = '')
    {
        if ($this->simple) {
            return false;
        }
        if ($child_list === null) {
            return false;
        }

        if (!is_array($child_list)) {
            if ($key_name) {
                $child_list = [$key_name=>$child_list];
            } else {
                $child_list = [$child_list];
            }
        } else if ($key_name) {
            $child_list = [$key_name=>$child_list];
        }

        foreach ($child_list as $key=>$child) {
            if (is_object($child) && $child->key_name) {
                $key = $child->key_name;
            }
            
            if (is_string($key)) {
                if (is_object($child)) {
                    $child->key_name = $key;
                }
                $this->children[$key] = $child;
            } else {
                array_push($this->children, $child);
            }
        }

        return sizeof($this->children)-1;
    }
    
    /**
     * Recursively searches for a child element within the current HTML element's children.
     *
     * @param String $key_name The key name of the child element to find.
     * @param Int $maxRecursion The maximum recursion depth for the search. Default is 0 (only direct children are searched).
     * @param Html|null $parent The parent element to start the search from. Defaults to the current element.
     * @param Int $level The current recursion level.
     *
     * @return Html|null Returns the found child element as an Html object, or null if not found.
     *               If the key name is found in the direct children, it returns that child.
     */
    public function find_child($key_name, $maxRecursion = 0, $parent = null, $level = 0)
    {
        if (!$parent) {
            $parent = $this;
        }

        if (isset($parent->children[$key_name])) {
            return $parent->children[$key_name];
        }

        if ($maxRecursion > $level) {
            foreach ($parent->children as $child) {
                if (is_object($child)) {
                    $found = $parent->find_child($key_name, $maxRecursion, $child, $level+1);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }
    
    /**
     * All is in the name
     *
     * @return Array Returns an array containing the child elements of the current HTML element.
     */
    public function get_children()
    {
        return $this->children;
    }

    /**
     * Retrieves the items that should be rendered after the current HTML element.
     *
     * @return Array Returns an array containing the item to be rendered after.
     */
    public function get_items_after()
    {
        return $this->items_after;
    }
    
    /**
     * Adds an item or a list of items to be rendered after the current HTML element.
     *
     * @param Mixed $itemList The item(s) to add. Can be a single Html object, a string, or an array of Html objects or strings.
     * @param String $key_name Optional key name for the item if it's a single item and needs a specific key.
     *
     * @return Int|Bool Returns the index of the last added item if successful, or false if the item list is invalid.
     */
    public function add_after($itemList, $key_name = '')
    {
        if ($itemList === null) {
            return false;
        }

        if (!is_array($itemList)) {
            if ($key_name) {
                $itemList = [$key_name=>$itemList];
            } else {
                $itemList = [$itemList];
            }
        }

        foreach ($itemList as $key=>$child) {
            if (is_object($child) && $child->key_name) {
                $key = $child->key_name;
            }
            if (is_string($key)) {
                if (isset($this->items_after[$key])) {
                    Utils::error_log('WARNING : add_after overwrite itemAfter at key='.$key);
                }
                if (is_object($child)) {
                    $child->key_name = $key;
                }
                $this->items_after[$key] = $child;
            } else {
                array_push($this->items_after, $child);
            }
        }

        return sizeof($this->items_after)-1;
    }
     /**
     * Reset key_name
     */
    public function reset_key($newValue = '')
    {
        $this->key_name = $newValue;
    }

    /**
     * Create an Html::Label tag from $label and $attributes or from $this->label and $this->attributes of the current object.
     * 
     * @param String $label
     * @param Array $attributes
     * 
     * @return Html object Html::label
     * 
     */
    public function label_tag($label = null, $attributes = null)
    {
        if (is_null($attributes)) {
            $attributes = [];
        }
        
        if (is_null($label) && $this->label) {
            $label = $this->label;
        }
        elseif (is_null($label)) {
            $label='';
        }

        if (!$this->id) {
            $this->id = Html::get_unique_id();
            $attributes['id'] = $this->id;
        }else{
            $attributes['id'] = $this->id.'_label';
            $attributes['for'] = $this->label_for ? $this->label_for : $this->id;
        }

        // fixed class depending type of input
        if (isset($this->data['type'])) $attributes['class'] = isset($attributes['class']) ? 'label_'.$this->data['type'].' '.$attributes['class'] : 'label_'.$this->data['type'];

        return new Html(Html::LABEL, $attributes, $label);
    }
    /**
     * Generates the opening HTML tag for the current element.
     *
     * This includes the tag name and all its attributes (standard and data-*).
     * It also handles special cases like the DOCTYPE for the <html> tag and
     * event parsing for JavaScript integration.
     * For quick edit fields/forms, it encrypt the field infos to avoid XSS or SQL injection.
     *
     * @return String The opening HTML tag as a string.
     */
    public function open_html_tag()
    {
        $str = '';

        if ($this->tag_name == Html::HTML) {
            $str .= '<!DOCTYPE html>';
        }

        $str .= '<';
        $str .= $this->tag_name;
        
        // detect some event to redirect them into event.js
        $this->parse_event();

        // integration of standard attributes
        foreach ($this->attributes as $key=>$val) {
            $key = strtolower($key);
            if ($key == '_') {
                $str .= ' '.$val;
            } else {
                $val = ($val == null) ? '' : $val;
                $str .= ' '.$key.'="'.str_replace('"', '&#34;', $val).'"';
            }
        }

        if ($this->tag_name == Html::FORM) {
            if (isset($this->extra['quick_edit_fields'])) {
                global $CRYPT;

                $data = $CRYPT->encrypt($this->extra['quick_edit_fields']);
                $this->data[Html::QUICK_EDIT_DATA]= $data;
            }
        }
        // integration of custom attributes
        foreach ($this->data as $key=>$val) {

            // if it is an object or an array, convert to json string
            if (!is_string($val)) {
                $val = json_encode($val, JSON_HEX_QUOT | JSON_NUMERIC_CHECK);
            }
            $str .= ' data-'.strtolower($key).'="'.str_replace('"', '&#34;', $val).'"';
        }
        $str .='>';

        return $str;
    }

    /**
     * Generates the HTML content of the element's children.
     *
     * This method iterates through the children of the current HTML element.
     * If a child is an Html object, it recursively calls its `full_html` method.
     * Otherwise, it treats the child as a string.
     *
     * @param Int $l The current indentation level (for pretty printing).
     * @return String The HTML content of the children.
     */
    public function content_html($l=0)
    {
        $str = '';

        foreach ($this->children as $child) {
            if (is_object($child) && method_exists($child, 'full_html')) {
                $str .= $child->full_html($l+1);
            } else {
                if (is_array($child)) {
                    echo '<hr>Error !';
                    print_r($child);
                    echo '<hr>';

                }

                $str .= $child;
            }
        }

        return $str;
    }

    /**
     * Generates the HTML for items that should be rendered after the current element.
     *
     * This method iterates through the items in the `items_after` array.
     * If an item is an Html object, it recursively calls its `full_html` method.
     * Otherwise, it treats the item as a string.
     *
     * @param Int $l The current indentation level (for pretty printing).
     * @return String The HTML content of the items to be rendered after.
     */
    public function after_html($l=0)
    {
        $str = '';

        foreach ($this->items_after as $item) {
            //~ Utils::error_log($item);
            if (is_object($item) && method_exists($item, 'full_html')) {
                $str .= $item->full_html($l+1);
            } else {
                if (is_array($item)) {
                    echo '<hr>Error !';
                    print_r($item);
                    echo '<hr>';

                    Utils::error_log('Html array instead of string :');
                    Utils::error_log($item);
                }

                $str .= $item;
            }
        }

        return $str;
    }
    /**
     * Generates the closing HTML tag for the current element.
     *
     * @return String The closing HTML tag.
     */
    public function close_html()
    {
        return '</'.$this->tag_name.'>';
    }

    /**
     * Generates the complete HTML for the current object/element, including its opening tag, content, and closing tag.
     *
     * This method orchestrates the generation of the full HTML representation of the element.
     * It handles both standard HTML tags and tag-less groups of children.
     * For standard tags, it calls `open_html_tag`, `content_html`, and `close_html`.
     * For simple tags (like <br>), it omits the content and closing tag.
     * It also appends any HTML content specified to be rendered after the element.
     *
     * @param Int $l The current indentation level for pretty-printing the HTML.
     * @return String The complete HTML string for the element.
     */
    public function full_html($l=0)
    {
        $str = '';

        if ($this->tag_name != '') {
            $str = $this->open_html_tag();

            if (!$this->simple) {
                $str .= $this->content_html($l);
                $str .= $this->close_html();
            }
            $str .= $this->after_html($l);
        } else {
            $str .= $this->content_html();
        }
        
        return $str;
    }
    
    /**
     * Magic method called when a string operation is done with the current object.
     * it will act as a shortcut to full_html().
     *
     * @return String A string representation of the current HTML element.
     */
    public function __toString()
    {
        return $this->full_html();
    }
    /**
     * exepose the current object structure as an object tree.
     * Helpfull to debug very complexe object tree.
     * @param String $key_name the key to use to identify the current object in the tree
     * @param String $parent the parent object to use as a reference
     * @param Int $l the level of the current object in the tree and to force usage of $key_name and $parent
     * @param Bool $is_next to start with next item or first child.
     * 
     * @return Html structure of the objet as an Html tree.
     */
    public function debug_structure($key_name='', $parent = '', $l=0, $is_next = false)
    {
        $r = "\n";

        $str = '';

        $tab_str = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        $tab = str_repeat($tab_str, $l);
        $tab_sub = str_repeat($tab_str, $l+1);

        $id = $this->id!=''?' id="'.$this->id.'"':'';
        $name = $this->name!=''?' name="'.$this->name.'"':'';

        $me = '';

        $sub_type = 'chld';
        if ($is_next) {
            $sub_type = 'next';
        }

        if ($this->tag_name == '') {
            $str .= $tab.'(group key:'.$this->key_name.')';

            if ($l>0) {
                $me = $parent.'->'.$sub_type.'_'.$this->key_name;
            }
        } else {
            $str .= $tab.'['.$this->tag_name.' key:'.$key_name.' | '.$id.$name.'"]';

            if ($l>0) {
                $me = $parent.'->'.$sub_type.'_'.$key_name;
            }
        }

        $str .=  '&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:bold; font-size:10px;">'.$me.'</span>'.$r;

        foreach ($this->children as $key=>$child) {
            if (is_object($child)) {
                $str .= $child->debug_structure($key, $me, $l+1);
            } else {
                $str .= $tab_sub.'[string key="'.$key.'"] <i>'.$child.'</i>';
                $string_me = $me.'->chld_'.$key;
                $str .=  '&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:bold; font-size:10px;">'.$string_me.'</span>'.$r;
            }
        }

        if (!$this->simple) {
            if ($this->tag_name == '') {
                $str .= $tab.'(/group key="'.$this->key_name.'")'.$r.$r;
            } else {
                $str .= $tab.'[/'.$this->tag_name.' key:'.$key_name.' | '.$id.$name.'"]'.$r;
            }
        }

        if (sizeof($this->items_after)>0) {
            $str .= $tab.'&nbsp;>(next '.$this->tag_name.' key:'.$key_name.') '.$r;

            foreach ($this->items_after as $key=>$child) {
                if (is_object($child)) {
                    $str .= $child->debug_structure($key, $me, $l+1, true);
                } else {
                    $str .= $tab_sub.'[string key="'.$key.'"] <i>'.$child.'</i>';
                    $string_me = $me.'->next_'.$key;
                    $str .=  '&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:bold; font-size:10px;">'.$string_me.'</span>'.$r;
                }
            }
            $str .= $tab.'&nbsp;>(/next)'.$r;
        }

        return $str;
    }
    
    /**
    * Retrieve event that need to pass by event.js
    * it automaticaly removed onclick or onchange and over attributes related to events, to pass them thru the event.js class.
    * This will avoid conflict and permit multiple similar events on the same tag
    * Except for A tag because they need to stop the original event to not trig the page reload
    * This function is called automaticaly 
    */
    public function parse_event(){
        global $event_lst;
        
        $js = '';
        if (isset($this->attributes['onclick']) && $this->tag_name != Html::A){
            $this->attributes['onclick'] = str_ends_with($this->attributes['onclick'],';') ? $this->attributes['onclick'] : $this->attributes['onclick'].';';
            $js.= 'h.e.add_event_click(domElem,(event)=>{'.$this->attributes['onclick'].'});';
            unset($this->attributes['onclick']);
        }
        if (isset($this->attributes['ondblclick']) && isset($this->attributes['no_doubleclick'])){
            Utils::error_log('PROBLEM when parsing event, you can\'t have an element with both attribute onDblClick and no_doubleclick.');
            unset($this->attributes['ondblclick']);
            unset($this->attributes['no_doubleclick']);
        }
        if (isset($this->attributes['ondblclick'])){
            $this->attributes['ondblclick'] = str_ends_with($this->attributes['ondblclick'],';') ? $this->attributes['ondblclick'] : $this->attributes['ondblclick'].';';
            $js.= 'h.e.add_event_dbl_click(domElem,(event)=>{'.$this->attributes['ondblclick'].'});';
            unset($this->attributes['ondblclick']);
        }
        if (isset($this->attributes['no_doubleclick']) && $this->attributes['no_doubleclick']){
            $js.= 'h.e.disable_double_click.push(domElem.id);';
            unset($this->attributes['no_doubleclick']);
        };
        if (isset($this->attributes['onchange'])){
            $this->attributes['onchange'] = str_ends_with($this->attributes['onchange'],';') ? $this->attributes['onchange'] : $this->attributes['onchange'].';';
            $js.= 'h.e.add_event(domElem,"change",(event)=>{'.$this->attributes['onchange'].'});';
            unset($this->attributes['onchange']);
        }
        if (isset($this->attributes['onkeydown'])){
            $this->attributes['onkeydown'] = str_ends_with($this->attributes['onkeydown'],';') ? $this->attributes['onkeydown'] : $this->attributes['onkeydown'].';';
            $js.= 'h.e.add_event_key(domElem,(event)=>{'.$this->attributes['onkeydown'].'});';
            // $js.= 'h.e.add_event(domElem,"on",(event)=>{'.$this->attributes['onkeydown'].'});';
            unset($this->attributes['onkeydown']);
        }
        if ($js != ''){
            if (!isset($this->attributes['id'])){
                $this->attributes['id'] = Html::get_unique_id();
            }
            if (!isset($event_lst[$this->attributes['id']])){
                $event_lst[$this->attributes['id']] = '(()=>{let domElem = document.getElementById("'.$this->attributes['id'].'");'.$js.'})();';
            } else {
                Utils::error_log('ERROR when trying to add an event. ID already in use by an other element : '.$this->attributes['id']);
            }
        }
    }
}