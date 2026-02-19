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
 * @class Tinymce
 * 
 * Provides integration and configuration utilities for the TinyMCE editor.
 * Includes methods to get the required JavaScript and CSS, generate initialization options,
 * produce initialization JavaScript, and handle file uploads from the editor.
 * 
 * Note that depending you main config file, you must set TINYMCE_UPLOAD to true to allow image upload in it.
 * The default path then will be an "images" folder in your public/module_name/ folder depending the module executed.
 *
 * if you want to change the upload url to make your own treatment, when you init H::input_textarea you can change its attribute like this :
 * attributes['tinymce'] = array('images_upload_url'=>'your_path');   
 *
 * @package helPHP\libs
 */

class Tinymce
{
    public function __construct($dom_container = null)
    {
    }
     /**
     * Generates and returns the TinyMCE initialization options array.
     *
     * Sets up plugins, toolbar, language, and other editor options.
     *
     * @param array $options Initial options to customize or extend.
     * @return array The complete TinyMCE options array.
     */
    public static function get_options_init($options)
    {
        global $LANG,$CONFIG;

        if($CONFIG::TINYMCE_UPLOAD === false && isset($options['images_upload_url']) ){
            unset($options['images_upload_url']);
        }

        $options['plugins'] = 'advlist lists link autolink charmap code table preview searchreplace directionality emoticons insertdatetime fullscreen searchreplace visualblocks quickbars wordcount charmap pagebreak nonbreaking visualchars'; // imagetools
        $options['menubar'] ='edit view insert format tools';
        $options['toolbar'] = ' undo redo searchreplace styles fontsize | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat code fullscreen animation ';
        $options['a11y_advanced_options'] = true;
        $options['skin'] = 'oxide-dark';
        $options['default_link_target'] = '_blank';
        $options['contextmenu'] = 'link | inserttable | cell row column deletetable | selectall copy cut paste';
        $options['relative_urls'] = true;
        $options['promotion'] = false;
        $options['document_base_url'] = $CONFIG::BASE_URL;
        $options['forced_root_block'] = 'div';
        $options['quickbars_insert_toolbar'] = '';
        $options['font_size_formats'] = 'var(--font-size-small) var(--font-size) var(--font-size-large) 0.8vmax 0.9vmax 1vmax 1.1vmax 1.2vmax 1.3vmax 1.4vmax 1.5vmax 1.6vmax 1.7vmax 1.8vmax 1.9vmax 2vmax 2.1vmax 2.2vmax 2.3vmax 2.4vmax 2.5vmax 3vmax 3.5vmax 4vmax';
        $options['font_family_formats'] = 'Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Indie Flower=indie flower,cursive;Impact=impact,chicago;Lato=lato;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;Wingdings=wingdings,zapf dingbats';
        $options['extended_valid_elements'] = 'details[onclick|class|id|style],div[onclick|class|id|style|onchange],img[class|id|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|onclick|onmousedown|onmouseup|style]';
        $options['extended_valid_elements'] .= ',video[class|id|style|width|height|controls|preload|data-setup|autoplay|muted|loop],source[src|type],iframe[src|width|height|allowfullscreen],canvas[class|id|style|width|height|onload],a[class|href|onclick|id|target],button[class|onclick]';
        $options['video_template_callback'] = 'function(data) {return \'<iframe width="\' + data.width + \'" height="\' + data.height + \'" src="\' + data.source1 + \'" allowfullscreen="allowfullscreen" </iframe>\';}';
        $options['convert_urls'] = false;
        $options['paste_merge_formats']= true;

        if ($CONFIG::TINYMCE_UPLOAD === true) {
            $options['plugins'] .= ' image';
            $options['contextmenu'] = 'image '.$options['contextmenu'];
        }

        switch($LANG->current_language) {
            case 'fr':
                $options['language'] = 'fr_FR';
                break;
            case 'bg':
                $options['language'] = 'bg_BG';
                break;
            case 'ca':
                $options['language'] = 'ca';
                break;
            case 'cs':
                $options['language'] = 'cs';
                break;
            case 'cy':
                $options['language'] = 'cy';
                break;
            case 'da':
                $options['language'] = 'da';
                break;
            case 'de':
                $options['language'] = 'de';
                break;
            case 'dv':
                $options['language'] = 'dv';
                break;
            case 'el':
                $options['language'] = 'el';
                break;
            case 'eo':
                $options['language'] = 'eo';
                break;
            case 'es':
                $options['language'] = 'es';
                break;
            case 'es':
                $options['language'] = 'es';
                break;
            case 'et':
                $options['language'] = 'et';
                break;
            case 'fa':
                $options['language'] = 'fa';
                break;
            case 'fi':
                $options['language'] = 'fi';
                break;
            case 'ga':
                $options['language'] = 'ga';
                break;
            case 'gl':
                $options['language'] = 'gl';
                break;
            case 'he':
                $options['language'] = 'he_IL';
                break;
            case 'hi':
                $options['language'] = 'hi';
                break;
            case 'hr':
                $options['language'] = 'hr';
                break;
            case 'hu':
                $options['language'] = 'hu_HU';
                break;
            case 'hu':
                $options['language'] = 'hu_HU';
                break;
            case 'id':
                $options['language'] = 'id';
                break;
            case 'is':
                $options['language'] = 'is_IS';
                break;
            case 'it':
                $options['language'] = 'it';
                break;
            case 'ja':
                $options['language'] = 'ja';
                break;
            case 'ka':
                $options['language'] = 'ka_GE';
                break;
            case 'kab':
                $options['language'] = 'kab';
                break;
            case 'kk':
                $options['language'] = 'kk';
                break;
            case 'ko':
                $options['language'] = 'ko_KR';
                break;
            case 'ku':
                $options['language'] = 'ku';
                break;
            case 'lt':
                $options['language'] = 'lt';
                break;
            case 'lv':
                $options['language'] = 'lv';
                break;
            case 'ne':
                $options['language'] = 'ne';
                break;
            case 'nl':
                $options['language'] = 'nl';
                break;
            case 'oc':
                $options['language'] = 'oc';
                break;
            case 'pl':
                $options['language'] = 'pl';
                break;
            case 'pt':
                $options['language'] = 'pt_BR';
                break;
            case 'ro':
                $options['language'] = 'ro';
                break;
            case 'ru':
                $options['language'] = 'ru';
                break;
            case 'sk':
                $options['language'] = 'sk';
                break;
            case 'sl':
                $options['language'] = 'sl_SI';
                break;
            case 'sr':
                $options['language'] = 'sr';
                break;
            case 'sv':
                $options['language'] = 'sv_SE';
                break;
            case 'ta':
                $options['language'] = 'ta';
                break;
            case 'tg':
                $options['language'] = 'tg';
                break;
            case 'th':
                $options['language'] = 'th_TH';
                break;
            case 'tr':
                $options['language'] = 'tr';
                break;
            case 'ug':
                $options['language'] = 'ug';
                break;
            case 'uk':
                $options['language'] = 'uk';
                break;
            case 'uz':
                $options['language'] = 'uz';
                break;
            case 'vi':
                $options['language'] = 'vi';
                break;
            case 'zh':
                $options['language'] = 'zh-Hans';
                break;
        }
        return $options;
    }
     
    /**
     * Generates the JavaScript code to initialize TinyMCE on a given DOM element.
     *
     * @param string $domID   The DOM element ID to attach TinyMCE to.
     * @param array|null $options Optional. Additional TinyMCE options.
     * @return string JavaScript code for TinyMCE initialization.
     */
    public static function get_init_javascript($domID, $options = null)
    {
        global $LANG;

        $str = '';
        $ed = 'ed_'.str_replace('-','_',$domID);
        $options = Tinymce::get_options_init($options);

        if (isset($options['images_upload_url'])) {
            $str .= 'var '.$ed.'_data = {action:"tinymceupload",url:"'.$options['images_upload_url'].'"};';
        }
        $str .= 'var '.$ed.' = new tinymce.Editor("'.$domID.'" , {license_key: "gpl",';
        
        if (is_array($options)) {
            if (isset($options['images_upload_url'])) {
                $parameters = array('images_upload_handler:H_ajax.tinymce_image_upload_handler.bind('.$ed.'_data)');
            } else {
                $parameters = [];
            }
            foreach ($options as $key=>$value) {
                if (is_array($value)) {
                    $t = $key.': [';
                    foreach ($value as $ind => $val) {
                        if ($ind > 0) {
                            $t.= ', ';
                        }
                        $t.= '"'.$val.'"';
                    }
                    $t.= ']';
                    array_push($parameters, $t);
                } elseif (strpos($value, 'function(') === false) {
                    array_push($parameters, $key.': '.json_encode($value));
                } else {
                    array_push($parameters, $key.': '.$value);
                }
            }
            $str .= implode(',', $parameters);
        }
        $str .= '}, tinymce.EditorManager);';

        // initialization and call function of the validator when the text is updated
        $str .= ' setTimeout(function(){ '.$ed.'.render(); '.$ed.'.on("KeyUp", function (e) { if('.$ed.'.targetElm){'.$ed.'.targetElm.value='.$ed.'.getContent(); setTimeout(function(){'.$ed.'.targetElm._validator.check_field('.$ed.'.targetElm);},5); }} ); } , 100); ';
        $str .= ' document.getElementById("'.$domID.'")._tinymce = '.$ed.';';
        return $str;
    }
    
    /**
     * Handles image file uploads from TinyMCE.
     *
     * Validates the origin, file extension, and saves the uploaded file to the specified destination folder.
     * Responds with a JSON object containing the file location or an error.
     *
     * @param string $destinationFolder The folder where uploaded images should be saved.
     * @return void Outputs JSON response and exits.
     */
    public static function receive_file($destinationFolder)
    {
        global $CONFIG;
        /*******************************************************
        * Only these origins will be allowed to upload images *
        ******************************************************/
        $accepted_origins = array($CONFIG::BASE_URL , 'http://'.$CONFIG::DOMAIN , 'https://'.$CONFIG::DOMAIN, 'http://www'.$CONFIG::DOMAIN , 'https://www'.$CONFIG::DOMAIN , 'http://localhost', 'http://192.168.1.1');

        /*********************************************
        * Change this line to set the upload folder *
        *********************************************/

        $destinationFolder = trim($destinationFolder, '/') . '/';

        // tester $_SERVER['HTTP_REFERER']

        $imageFolder = $CONFIG::HOME_FOLDER.$destinationFolder;
  
        reset($_FILES);
        $temp = current($_FILES);

        if (!is_dir($imageFolder) && $CONFIG::TINYMCE_UPLOAD == true) {
            if (mkdir($imageFolder, 0774, true)) {
                chown($imageFolder, 'www-data');
                Utils::error_log('Create new folder for tinymce : '.$imageFolder);
            } else {
                Utils::error_log('ERROR create folder for tinymce : '.$imageFolder);
                @unlink($temp['tmp_name']);
                header("HTTP/1.0 403 Destination Denied");
                Utils::error_log('Cannot create destination folder : '.$_SERVER['HTTP_ORIGIN']);
                return;
            }
        }
        
        if (is_uploaded_file($temp['tmp_name'])) {
            if($CONFIG::TINYMCE_UPLOAD === false){
                unlink($temp['tmp_name']);
                header("HTTP/1.0 500 Server Error");
                exit();
            }
            if (isset($_SERVER['HTTP_ORIGIN'])) {

                // same-origin requests won't set an origin. If the origin is set, it must be valid.
                if (in_array(rtrim($_SERVER['HTTP_ORIGIN'], '/').'/', $accepted_origins)) {
                    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
                } else {
                    header("HTTP/1.0 403 Origin Denied");
                    Utils::error_log('Invalid origin for tinymce upload : '.$_SERVER['HTTP_ORIGIN']);
                    return;
                }
            }

            /*
              If your script needs to receive cookies, set images_upload_credentials : true in
              the configuration and enable the following two headers.
            */
            // header('Access-Control-Allow-Credentials: true');
            // header('P3P: CP="There is no P3P policy."');

            // Sanitize input
            if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
                header("HTTP/1.0 500 Invalid file name.");
                Utils::error_log('Invalid tinymce filename : '.$temp['name']);
                return;
            }

            // Verify extension
            $extension = strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION));
            if ($extension == 'dat' && $temp['type'] == 'image/svg+xml') {
                $extension = 'svg';
                $temp['name'] = str_replace('dat', 'svg', $temp['name']);
            }
            if (!in_array($extension, array("gif", "jpg", "jpeg", "png", "svg"))) {
                header("HTTP/1.0 500 Invalid extension.");
                Utils::error_log('Invalid tinymce file extension : '.$extension);
                return;
            }

            // Accept upload if there was no origin, or if it is an accepted origin
            $prefix = '_tmce_';
            if (strpos($temp['name'], $prefix) !== 0) {
                $temp['name'] = $prefix.time().'_'.$temp['name'];
            }

            $filetowrite = $imageFolder . $temp['name'];
            move_uploaded_file($temp['tmp_name'], $filetowrite);

            // Respond to the successful upload with JSON.
            // Use a location key to specify the path to the saved image resource.
            // { location : '/your/uploaded/image/file'}

            $output_path = $CONFIG::BASE_URL . $destinationFolder . $temp['name'];

            echo json_encode(array('location' => $output_path));
            exit;
        } else {
            // Notify editor that the upload failed
            header("HTTP/1.0 500 Server Error");
        }
    }
}
?>