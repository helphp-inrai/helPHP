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

namespace helPHP\modules\media\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Media as MEDIA_Class;
use helPHP\libs\Utils;
use helPHP\libs\Language;

class Media extends HelPHP_module
{
    const module_name = 'media';

    protected $ACTION_EDIT = self::module_name.'_edit';
    protected $ACTION_SAVE = self::module_name.'_save';
    protected $ACTION_DELETE = self::module_name.'_delete';
    protected $ACTION_LIST = self::module_name.'_list';
    protected $ACTION_BIG_VIEW = self::module_name.'_big_view';
    protected $ACTION_PROCESS = self::module_name.'_process';
    protected $ACTION_UPLOADER = self::module_name.'_display_uploader';
    protected $ACTION_UPLOAD = self::module_name.'_upload';
    protected $ACTION_SAVE_NAME = self::module_name.'_save_name';
    protected $ACTION_DELETE_LANG = self::module_name.'_delete_languages';
    
    protected $ACTION_PROCESS_VIDEO_PROGRESS = self::module_name.'_progress_video_process';
    
    public $mode = false;
    
    public $params = [
        // about the input
        'accept'=>'',                       // type of file accepted, see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file#unique_file_type_specifiers
        'multiple'=>false,                  // accept multiple file

        // about extra widget
        'delete'=>true,                     // display delete button
        'edit'=>true,                       // display edit button
        'options'=>true,
        'big_view'=>false,                  // display big view button
        'list'=>false,                      // display list choice 
        'no_resize'=>false,                 // deactivate resize option in edit
        'display_current'=>true,
        
        // callbacks
        // 'on_save'=>false,                // fonction js appelé après le save de l'image
        'on_delete'=>false,                 // callback on media delete
        'on_process_end'=>false,            // callback on end of processing (for video and others)
        
        // about final process on file
        'original'=>false,                  // keep original or not
        // 'instances'=>false,              // pour faire une image unique si instances multiples
        // 'link_edition'=>false,           // édition lié, c-a-d que la modification se répercute sur toutes les tailles de l'image

        'lang' => false,                    // is multilanguage mode activate
        'lang_iso' => false                 // false when default media, otherwise iso of the language,
    ];
    
    //id du media
    private $media_id = false;
    private $field_identifier, $field_id;
    //process
    private $process = false;
    private $path;
    
    public function __construct($dom_container = null, $field_identifier = false, $field_id=false, $process=false, $params=false, &$post=[]) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);

        global $CONFIG;
        $path = $CONFIG::HOME_FOLDER.'medias/';

        if ($params) {
            $this->params = array_replace($this->params, $params);
        }
        
        if ($field_identifier !== false) {
            $this->field_identifier = $field_identifier;
            $this->media_id = $field_identifier;
        }

        if ($field_id !== false) {
            $this->field_id = $field_id;
            $this->media_id .= '¤'.$field_id;
        }

        if ($this->media_id !== false){
            if (!isset($_SESSION['media_list'])) $_SESSION['media_list'] = [];
            if (!isset($_SESSION['media_list'][$this->media_id])) $_SESSION['media_list'][$this->media_id] = [];

            if ($process !== false){
                $this->process = $process;
                $this->process['media_id'] = $this->media_id;
                $this->process['original'] = $this->params['original'] ? 1 : 0;
                $_SESSION['media_list'][$this->media_id]['process'] = json_encode($this->process);
                // Utils::error_log('saved process');
                // Utils::error_log($this->process);
            } else {
                unset($_SESSION['media_list'][$this->media_id]['process']);
            }

            $_SESSION['media_list'][$this->media_id]['params'] = json_encode($this->params);
            $_SESSION['media_list'][$this->media_id]['time'] = time();
        }

        // detect if language is activated by the presence of a media for a lang.
        // test on lang_iso to only do the query once when loading the default uploader
        if ($this->params['lang_iso'] == false) {
            global $DB;
            $q = 'SELECT id FROM '.$DB->table('media_use').' WHERE field_identifier LIKE ? AND field_identifier NOT LIKE ?';
            $lang_exist = $DB->prepared_query_list($q, 'ss', [$this->field_identifier.'___¤'.$this->field_id, $this->media_id]);
            if ($lang_exist) $this->params['lang'] = true;
        }

        $this->clean_session();
    }

    public static function display($mode, $params = [], $field_identifier = false, $field_id = false, $process = false) {
        $post = [];
        $media = new Media(null, $field_identifier, $field_id, $process, $params, $post);
        $media->get_dom_id();
        $media->inst_js = 'h.modules.'.$media->module_name.'_a["'.$media->dom_id.'"]';
        if ($mode == 'uploader') {
            return $media->process_display($post, 'uploader');
            // $post[$media->input_action_identifier] = $media->ACTION_UPLOADER;
        } else if ($mode == 'manager') {
            // return $media->process_display($post, 'uploader');
            // $post[$media->input_action_identifier] = $media->ACTION_MANAGER;
        }
        // return $media->process_data($post, true);
    }

    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // utilisateur non autorisé sur ce module
            return null;
        }

        // when detecting a shared media that will be modified by an action. Need to ask user for what he want to do.
        // In that case we store the previous post (with the action in it) and we retrieve it from session after user make his choice.
        if (isset($post['post_in_session']) && $post['post_in_session']){
            $post = array_merge($post, $_SESSION['media_post_waiting']);
            unset($_SESSION['media_post_waiting']);
        }
        
        if (isset($post['media_id']) && $post['media_id']){
            $this->media_id = $post['media_id'];
            $t = explode('¤', $post['media_id']);
            $this->field_identifier = $t[0];
            $this->field_id = $t[1];
        }

        if ($this->media_id !== false) {
            $this->process = isset($_SESSION['media_list'][$this->media_id]['process']) ? json_decode($_SESSION['media_list'][$this->media_id]['process'], true) : false;
            
            if (!isset($_SESSION['media_list'][$this->media_id]['params'])){
                Utils::error_log($this->media_id);
                Utils::error_log($_SESSION['media_list']);
            }
            $this->params = isset($_SESSION['media_list'][$this->media_id]['params']) ? json_decode($_SESSION['media_list'][$this->media_id]['params'], true) : false;

            // update time
            if (isset($_SESSION['media_list'][$this->media_id]['time'])){
                $_SESSION['media_list'][$this->media_id]['time'] = time();
            }
        }
        
        $master_output = H::group('media_display');
        switch ($post[$this->input_action_identifier]) {

            case $this->ACTION_UPLOAD:
                $master_output->add_child( $this->process_upload($post) );
            break;
            case $this->ACTION_BIG_VIEW:
                $master_output->add_child( $this->toggle_big_view($post) );
            break;

            case $this->ACTION_EDIT:
                $master_output->add_child( $this->process_display($post, 'editor') );
            break;
            case $this->ACTION_SAVE_NAME:
                Language::save_translation_data($post);
                $master_output->add_child( $this->process_display($post, 'editor') );
            break;
            
            case $this->ACTION_LIST:
                $master_output->add_child( $this->process_display($post, 'list') );
            break;
            
            case $this->ACTION_PROCESS:
                $master_output->add_child( $this->apply_process($post) );
            break;
            
            case $this->ACTION_DELETE:
                $master_output->add_child( $this->delete_media($post) );
            break;

            case $this->ACTION_DELETE_LANG:
                $master_output->add_child( $this->delete_languages($post) );
            break;

            case $this->ACTION_PROCESS_VIDEO_PROGRESS:
                $master_output->add_child( $this->process_video_progress($post) );
            break;
            
            case $this->ACTION_UPLOADER:
            default:
                $master_output->add_child( $this->process_display($post, 'uploader') );
            break;
        }

        if ($to_return) {
            return $master_output;
        } else {
            $this->display->add_child( $master_output );
        }
    }

    //----------------------------------------------------------------------------------------------
    protected $fields_data = array('id','field_identifier','field_id');

    //form/data cleaning...
    public function process_display(&$post, $UI)
    {
        global $DB;

        switch ($UI) {
            case 'uploader':
                return $this->display_uploader($post);
            break;
            case 'list':
                return $this->display_list($post);
            break;
            
            case 'editor':
                return $this->display_edit($post);
            break;
            
            // case 'manager':
            //     return $this->MediaManager($post);
            // break;
        }
    }

    //MEDIA UPLOAD WIDGET
    public function display_uploader($post) {
        global $FS, $CONFIG, $LANG, $DB;
        
        $settings = [
            'media_id'=>$this->media_id,
            'params'=>$this->params
        ];
        $js = 'helphp_timeout(\'Media_a.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($settings)).');';

        $output = H::group('uploader'.$this->params['lang_iso']);

        if ($this->params['lang_iso'] === false && count($CONFIG::AVAILABLE_LANGUAGES) > 1) {

            $output = H::DIV(['class'=>$this->css.'container_uploader', 'id'=>self::module_name.'_widget'.$this->dom_id]);

            // add language toggler only if it's the default widget
            $div_lang = H::DIV(['class'=>$this->css.'lang']);
                $activate_lang = H::button_icon('globe', ['class'=>$this->css.'btn_activate_lang', 'id'=>self::module_name.'_btn_activate_lang'.$this->dom_id, 'data-confirm'=>$this->get_tl('delete_languages_media'), 'title'=>$this->get_tl('activate_lang')]);
                $lang_list = H::DIV(['class'=>$this->css.'lang_list', 'id'=>self::module_name.'_lang_list'.$this->dom_id]);
                    $default = H::DIV(['class'=>$this->css.'lang_item default', 'data-iso'=>'', 'title'=>$this->get_tl('default')], $this->get_tl('default'));
                $lang_list->add_child( $default );
                if (!$this->params['lang']) $lang_list->add_class('hidden');
                foreach ($LANG->get_languages_data() as $key => $lang) {
                    $block_lang = H::DIV(['class'=>$this->css.'lang_item', 'data-iso'=>$lang['iso'], 'title'=>$lang['label']], $lang['own'].' ('.$lang['iso'].')');
                    if ($this->params['lang'] && $LANG->current_language == $lang['iso']) $block_lang->add_class('selected');
                    $lang_list->add_child( $block_lang );
                }
            $div_lang->add_child( [$activate_lang, $lang_list] );
            $output->add_child($div_lang);
        }

        $container = H::DIV(['class'=>$this->css.'uploader', 'id'=>self::module_name.'_uploader'.$this->dom_id, 'data-iso'=>($this->params['lang_iso'] ? $this->params['lang_iso'] : '')]);
        if (isset($this->params['label'])) $container->label = $this->params['label'];
        if ($this->params['lang'] === true && $this->params['lang_iso'] != $LANG->current_language) $container->add_class('hidden');

            if (!$this->params['multiple']) {
                // the next code is a copy of the MEDIA::get_media method without the language iso part
                $q = 'SELECT d.id as id_media, u.id as id_use, d.type, d.path, d.big_view, d.width, d.height, u.use_key, u.process_key, u.field_identifier as media_id FROM '.$DB->table('media_data').' d INNER JOIN '.$DB->table('media_use').' u ON u.field_identifier=? AND u.id_media=d.id';
                $current_media = $DB->prepared_query_line($q, 's', [$this->media_id]);
                if ($current_media) {
                    $q = 'SELECT count(*) FROM '.$DB->table('media_use').' WHERE id_media='.$current_media['id_media'];
                    $current_media['nbinstances'] = $DB->query_value($q);

                    global $LANG;
                    $current_media['name'] = Language::load_short_translation_value($current_media['media_id'], $current_media['id_media'], $LANG->current_id_data);

                    $current_media = [$current_media];
                }
            } else {
                // the next code is a copy of the MEDIA::get_media_list method without the language iso part
                $q = 'SELECT GROUP_CONCAT(id_media SEPARATOR ",") as id_medias, use_key FROM '.$DB->table('media_use').' WHERE field_identifier=? GROUP BY use_key';
                $tmp = $DB->prepared_query_list($q, 's', [$this->media_id]);
                $current_media = [];
                foreach($tmp as $line){
                    $q = 'SELECT d.id as id_media, u.id as id_use, d.type, d.path, d.big_view, d.width, '.$line['use_key'].' as use_key, u.field_identifier as media_id FROM '.$DB->table('media_data').' d INNER JOIN '.$DB->table('media_use').' u';
                    $q.=' ON u.id_media=d.id AND d.id IN ('.$line['id_medias'].') ORDER BY d.width ASC LIMIT 1';
                    $media = $DB->query_line($q);
                    if (!$media) continue;
                    
                    $q = 'SELECT count(*) FROM '.$DB->table('media_use').' WHERE id_media='.$media['id_media'];
                    $media['nbinstances']= $DB->query_value($q);

                    global $LANG;
                    $media['name'] = Language::load_short_translation_value($media['media_id'], $media['id_media'], $LANG->current_id_data);

                    array_push($current_media, $media);
                }
                $container->add_class('multiple');
            }

            $div_drop = H::DIV(['class'=>$this->css.'droper', 'id'=>self::module_name.'_droper'.$this->dom_id]);

                $icon = H::icon('upload-cloud', ['class'=>$this->css.'droper_icon']);
                $txt = H::SPAN(['class'=>$this->css.'droper_txt', 'id'=>self::module_name.'_droper_txt'.$this->dom_id], $this->get_tl('media_droper'));
                if ($this->params['list']) {
                    $btn_list = H::SPAN(['class'=>$this->css.'toggle_list', 'id'=>self::module_name.'_toggle_list'.$this->dom_id], $this->get_tl('media_list'));
                    $txt->add_after($btn_list);
                }
                $selected = H::DIV(['class'=>$this->css.'droper_selected', 'id'=>self::module_name.'_droper_selected'.$this->dom_id]);


                $progress = H::DIV(['class'=>$this->css.'progress_bar']);
                    $state = H::DIV(['id'=>self::module_name.'_progress_state'.$this->dom_id, 'class'=>$this->css.'progress_state']);
                    $label = H::SPAN(['id'=>self::module_name.'_progress_label'.$this->dom_id, 'class'=>$this->css.'progress_label']);
                $progress->add_child([$state,$label]);

            $div_drop->add_child( [$icon, $txt, $selected, $progress] );

        $container->add_child($div_drop);

        if ($this->params['options']){
            $div_options = H::DIV(['class'=>$this->css.'options']);
                $ttl = H::DIV(['class'=>$this->css.'options_ttl', 'data-target_id'=>self::module_name.'_options_subcontainer'.$this->dom_id], $this->get_tl('options'));
                $subdiv_options = H::DIV(['class'=>$this->css.'options_subcontainer hidden', 'id'=>self::module_name.'_options_subcontainer'.$this->dom_id]);
                    $upload_options = H::fieldset(['class'=>$this->css.'options_fieldset upload'], $this->get_tl('upload'));
                        // forcer le jpg
                        $inp_force_jpg = H::input_checkbox(['name'=>'media_force_jpg'.$this->dom_id, 'value'=>1, 'label'=>$this->get_tl('lab_force_jpg'), 'title'=>$this->get_tl('inf_force_jpg')]);
                    $upload_options->add_child([$inp_force_jpg->label_tag(),$inp_force_jpg]);
                $subdiv_options->add_child([$upload_options]);
                
                    $images_options = H::fieldset(['class'=>$this->css.'options_fieldset upload'], $this->get_tl('images'));
                        if ($this->params['big_view']) {
                            $checked = ($current_media) ? $current_media[0]['big_view'] : 1;
                            $inp_big_view = H::input_checkbox(['name'=>'media_big_view'.$this->dom_id, 'value'=>1, 'label'=>$this->get_tl('lab_big_view'), 'title'=>$this->get_tl('inf_big_view'), 'checked'=>$checked, 'id'=>self::module_name.'_big_view'.$this->dom_id]);
                            $images_options->add_child([$inp_big_view->label_tag(),$inp_big_view]);
                        }
                    if (count($images_options->get_children()) > 1) $subdiv_options->add_child([$images_options]); // > 1 because of the legend
            $div_options->add_child([$ttl,$subdiv_options]);
            // $js.= 'H_ui.toggle_accordion("'.$this->css.'options_ttl'.'","hidden");';
            
            $container->add_child($div_options);
        }
        
            $base_name = 'media_data['.$this->media_id.']';
            $file_name = $base_name.'[files]';
            $inp_file = H::input_file(['id'=>self::module_name.'_input'.$this->dom_id, 'class'=>$this->css.'input_file hidden', 'name'=>$file_name, 'accept'=>$this->params['accept'], 'multiple'=>$this->params['multiple'], 'data-dom_id'=>$this->dom_id]);

            // dom_id of the media,this field is detected by ajax.js to tell him to display the upload progress in the media uploader
            $hidden_identifier = H::input_hidden(['name'=>'media_dom_id'.$this->dom_id, 'value'=>$this->dom_id]);
            $hidden_modified = H::input_hidden(['name'=>'media_modified'.$this->dom_id, 'value'=>0, 'id'=>self::module_name.'_modified'.$this->dom_id]);

        $container->add_child([$inp_file,$hidden_identifier,$hidden_modified]);

        if ($this->params['list']) {
            $list_name = $base_name.'[list]';
            $hidden_list = H::input_hidden(['name'=>$list_name, 'id'=>self::module_name.'_input_list'.$this->dom_id,]);
            $container->add_child($hidden_list);
        }
        
            $current_lst = H::DIV(['class'=>$this->css.'current_lst', 'id'=>self::module_name.'_current_lst'.$this->dom_id]);
            if ($current_media && $this->params['display_current']){
                foreach($current_media as $media){
                    $elem = H::DIV(['class'=>$this->css.'current_elem', 'id'=>self::module_name.'_current_elem'.$media['use_key'].$this->dom_id, 'data-use_key'=>$media['use_key'], 'data-nb_instances'=>$media['nbinstances']]);

                    if (in_array(strtolower($FS->get_file_ext($media['path'])), MEDIA_Class::image_ext)){
                        // image
                        if ($this->params['edit']) {
                            $btn_edit = H::button_icon('edit-2', ['class'=>$this->css.'toggle_edit button_edit', 'id'=>self::module_name.'_toggle_edit'.$media['use_key'].$this->dom_id, 'title'=>$this->get_tl('tlc_edit')]);
                            $elem->add_child($btn_edit);
                        }
                        
                        $path = $CONFIG::BASE_URL.'public/media/media.php?f='.$this->media_id.'&i='.$media['use_key'].'&t='.time();
                        $img = H::IMG(['id'=>self::module_name.'_current_img'.$media['use_key'].$this->dom_id, 'class'=>$this->css.'current_img', 'src'=>$path, 'alt'=>$media['name']]);
                        if ($media['width'] > $media['height']) $img->add_class('landscape');
                        else $img->add_class('portrait');
                        $elem->add_child($img);
                        if ($this->params['big_view']) {
                            $img->set_attribute('data-big_view', $media['big_view']);
                            $btn_big_view = H::DIV(['class'=>$this->css.'btn_big_view off', 'id'=>self::module_name.'_big_view'.$this->dom_id, 'title'=>$this->get_tl('big_view')]);
                            if ($media['big_view']) {
                                $btn_big_view->set_attribute('class', $this->css.'btn_big_view on');
                            }
                            $elem->add_child($btn_big_view);
                        }

                    } else if (in_array(strtolower($FS->get_file_ext($media['path'])), MEDIA_Class::video_ext)){
                        // check if the video has been processed
                        
                        $state = shell_exec('php '.$CONFIG::HELPHP_FOLDER.'utils/job_manager.php -a"progress" -k"'.$this->media_id.'" -t"'.$CONFIG::HOME_FOLDER.'" 2>&1');
                        if ($state == 'ok!'){
                            // video
                            $img = H::IMG(['class'=>$this->css.'current_img', 'id'=>self::module_name.'_current_img'.$this->dom_id]);
                            $video_src = 'public/media/media.php?';
                            $video_src .= 'f='.$this->media_id;
                            $video_src .= '&i='.$media['use_key'];
                            $js.= 'Media_a.get_video_image("'.$video_src.'", 2, "'.self::module_name.'_current_img'.$this->dom_id.'");';
                            $elem->add_child( $img );
                        } else {
                            $process = H::DIV(['class'=>$this->css.'progress', 'id'=>self::module_name.'_video_progress'.$this->dom_id]);
                            if ($state == 'wait') {
                                $process->add_child( H::SPAN(['class'=>$this->css.'video_progress_state'], $this->get_tl('process_waiting')) );
                            } else {
                                $process->add_child( H::SPAN(['class'=>$this->css.'video_progress_state'], $this->get_tl('process_working')) );
                            }
                            $js.= 'setTimeout(()=>{'.$this->inst_js.'.get_video_progress('.$media['use_key'].');}, 3000);';
                            $elem->add_child( $process );
                        }
                    } else {
                        // others
                        $ext = $FS->get_file_ext($media['path']);
                        $name = H::SPAN(['class'=>$this->css.'current_elem'], $media['name'].'.'.$ext);
                        $elem->add_child($name);
                    }

                    if ($this->params['delete']) {
                        $btn_delete = H::button_icon('trash-2', ['class'=>$this->css.'btn_del button_delete', 'id'=>self::module_name.'_delete'.$media['use_key'].$this->dom_id, 'data-confirm'=>$this->get_tl('del_img'), 'title'=>$this->get_tl('tlc_delete')]);
                        $elem->add_child($btn_delete);
                    }

                    $current_lst->add_child($elem);
                }
            }
        
        $container->add_child( $current_lst );
            
        if ($this->params['multiple']){
            $multiple_name = $base_name.'[multiple]';
            $container->add_child( H::input_hidden(['name'=>$multiple_name, 'value'=>1]) );
        } else {
            // $shared_name = 'media_is_shared'.$this->dom_id;
            $container->add_child( H::input_hidden(['name'=>'media_is_shared'.$this->dom_id, 'value'=>0, 'id'=>self::module_name.'_input_shared'.$this->dom_id]) );
        }

        $output->add_child($container, 'uploader_container-'.$this->params['lang_iso']);

        if ($this->params['lang_iso'] === false && count($CONFIG::AVAILABLE_LANGUAGES) > 1) {
            foreach ($LANG->get_languages_data() as $key => $lang) {
                $params = $this->params;
                $params['lang_iso'] = $lang['iso'];
                $t = [];
                $t['dom_id'] = $this->dom_id.'-'.$lang['iso'];
                $sub_med = new Media(null, $this->field_identifier.'-'.$lang['iso'], $this->field_id, $this->process, $params, $t);
                $sub_med->get_dom_id($t);
                $output->add_child( $sub_med->process_display($t, 'uploader') );
                if ($this->params['lang'] === true && $LANG->current_language == $lang['iso']) {
                    $chld = $output->find_child('uploader_container-'.$lang['iso'], 5);
                    if ($chld) $chld->add_class('selected');
                } else {
                    $chld = $output->find_child('uploader_container-'.$lang['iso'], 5);
                    if ($chld) $chld->add_class('hidden');
                }
            }
        }

        $js .= '\');';
        $script = H::script($js, ['autoremove'=>true]);
        $output->add_child($script);

        return $output;
    }

    public function process_video_progress($post) {
        global $CONFIG;
        $state = shell_exec('php '.$CONFIG::HELPHP_FOLDER.'utils/job_manager.php -a"progress" -k"'.$this->media_id.'" -t"'.$CONFIG::HOME_FOLDER.'" 2>&1');
        $html = H::group('process_video_display');
        if ($state == 'ok!'){
            // video
            $img = H::IMG(['class'=>$this->css.'current_img', 'id'=>self::module_name.'_current_img'.$this->dom_id]);
            $video_src = 'public/media/media.php?';
            $video_src .= 'f='.$this->media_id;
            $video_src .= '&i='.$post['use_key'];
            $js = 'Media_a.get_video_image("'.$video_src.'", 2, "'.self::module_name.'_current_img'.$this->dom_id.'");';
            if ($this->params['on_process_end']) $js.= $this->params['on_process_end'];
            $script = H::script($js, ['autoremove'=>true]);
            $html->add_child( [$img, $script] );
        } else {
            $process = H::DIV(['class'=>$this->css.'progress', 'id'=>self::module_name.'_video_progress'.$this->dom_id]);
            if ($state == 'wait') {
                $process->add_child( H::SPAN(['class'=>$this->css.'video_progress_state'], $this->get_tl('process_waiting')) );
            } else {
                $process->add_child( H::SPAN(['class'=>$this->css.'video_progress_state'], $this->get_tl('process_working')) );
            }

            $script = H::script('setTimeout(()=>{'.$this->inst_js.'.get_video_progress('.$post['use_key'].');}, 3000);', ['autoremove'=>true]);
            $html->add_child( [$process, $script] );
        }

        return $html;
    }
    
    //MEDIA UPLOAD
    public function process_upload(&$post) {
        global $MEDIA;

        $res = $MEDIA->process_media($post);
        if (!$res) $this->add_error('media_error');
        
        return $this->display_uploader($post);
    }
    
    //MEDIA LIST
    public function display_list($post){
        global $DB, $FS, $CONFIG, $LANG;

        $post['search_txt'] = isset($post['search_txt']) ? $post['search_txt'] : '';
        $post['start_index'] = isset($post['start_index']) ? $post['start_index'] : 0;
        $post['page_limit'] = isset($post['page_limit']) ? $post['page_limit'] : 10;
        $post['sort_list'] = isset($post['sort_list']) ? $post['sort_list'] : '';

        // get all the medias for each field identifier as a list of id
        // $q = 'SELECT SQL_CALC_FOUND_ROWS ';

        $q = 'SELECT SQL_CALC_FOUND_ROWS GROUP_CONCAT(u.id_media SEPARATOR ",") as id_medias, u.field_identifier as media_id, u.use_key,';
        $q.=' l.value as name, GROUP_CONCAT(d.path SEPARATOR "¤¤¤¤") as paths, GROUP_CONCAT(d.type SEPARATOR ",") as types, GROUP_CONCAT(d.original SEPARATOR ",") as originals, GROUP_CONCAT(d.width, "x", d.height SEPARATOR "¤") as sizes';
        $q.=' FROM '.$this->build_table_name('use').' u LEFT JOIN '.$this->build_module_table_name('languages', 'short').' l ON (u.id_media=l.id_item AND l.field_identifier="media_data-filename"';
        $q.=' AND l.id_data='.$LANG->current_id_data.') LEFT JOIN '.$this->build_table_name('data').' d ON (d.id=u.id_media) WHERE u.share=0';
        $accepts = explode(',', $this->params['accept']);
        if ($accepts) {
            $q.= ' AND (';
            foreach($accepts as $key => $accept){
                if ($key > 0) $q.= ' OR ';
                switch ($accept) {
                    case 'image/*':
                        $q.= 'd.type=1';
                    break;
                    case 'video/*':
                        $q.= 'd.type=2';
                    break;
                    case 'audio/*':
                        $q.= 'd.type=3';
                    break;
                    default:
                        if (str_starts_with($accept, '.')) $q.= 'd.path LIKE "%'.$accept.'"'; // A valid case-insensitive filename extension
                        else $q.= 'd.path LIKE "%.'.Utils::get_ext_from_mime($accept).'"'; // A valid MIME type string
                }
            }
            $q.= ')';
        }
        if ($post['search_txt'] != '') {
            $q.= ' AND l.value LIKE ?';

        }
        $q.=' GROUP BY u.field_identifier, u.use_key, l.value';
        switch ($post['sort_list']) {
            case 'R':
                $q .= ' ORDER BY u.id ASC';
            break;
            case 'r':
            default:
                $q .= ' ORDER BY u.id DESC';
            break;
        }
        $q .= ' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
        if ($post['search_txt'] != '') {
            $temp_data = $DB->prepared_query_list($q, 's', ['%'.$post['search_txt'].'%']);
        } else {
            $temp_data = $DB->query_list($q);
        }
        
        // Utils::error_log($temp_data);
        $pages = $DB->last_pages_data();

        $data = [];
        if ($temp_data){
            foreach($temp_data as $key => $line){
                $medias = [];
                $medias_id = explode(',', $line['id_medias']);
                $originals = explode(',', $line['originals']);
                $types = explode(',', $line['types']);
                $paths = explode('¤¤¤¤', $line['paths']);
                $sizes = explode('¤', $line['sizes']);
                foreach($medias_id as $key => $id){
                    $size = explode('x', $sizes[$key]);
                    $medias[] = [
                        'id'=>$id,
                        'type'=>$types[$key],
                        'original'=>$originals[$key],
                        'original'=>$originals[$key],
                        'path'=>$paths[$key],
                        'width'=>$size[0],
                        'height'=>$size[1]
                    ];
                }

                $smaller = $medias[0];
                $path = $CONFIG::BASE_URL.'public/media/media.php?f='.$line['media_id'].'&i='.$line['use_key'].'&t='.time();
                $ext = strtolower($FS->get_file_ext($smaller['path']));
                array_push($data, [
                        'media_id' => $line['media_id'],
                        'use_key' => $line['use_key'],
                        'medias' => $medias,
                        'path' => $path,
                        'name' => $line['name'],
                        'image' => (in_array($ext, MEDIA_Class::image_ext)) ? true : false,
                        'video' => (in_array($ext, MEDIA_Class::video_ext)) ? true : false,
                        // 'audio' => (in_array($ext, MEDIA_Class::audio_ext)) ? true : false,
                        'ext' => $ext
                    ]
                );
            }
        }

        $js = '';

        $output = H::group('media_list');
            
            $title = H::DIV(['class'=>$this->css.'title module_title media_list'], $this->get_tl('ttl_list'));
            
        $output->add_child($title);

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'popup_modal_content', 'class'=>$this->css.'form_list', 'dom_id'=>$this->dom_id]);
            
                $hidden_media_id = H::input_hidden(['name'=>'media_id', 'value'=>$this->media_id, 'data-alwaysposted'=>1]);

            $form->add_child( [$hidden_media_id] );
                
                $div_filter = H::DIV(['class'=>$this->css.'list_filters']);

                    $info = H::DIV(['class'=>$this->css.'info filter'], $this->get_tl('info_list_filter'));
                    
                    $inp_txt = H::input_text(['name'=>'search_txt', 'value'=>$post['search_txt'], 'class'=>$this->css.'input_search', 'placeholder'=>$this->get_tl('search_txt'), 'data-alwaysposted'=>1]);
                    
                    $opts[] = ['lab'=>$this->get_tl('recent'), 'val'=>'r'];
                    $opts[] = ['lab'=>$this->get_tl('vieux'), 'val'=>'R'];
                    $opts[] = ['lab'=>$this->get_tl('name_desc'), 'val'=>'n'];
                    $opts[] = ['lab'=>$this->get_tl('name_asc'), 'val'=>'N'];
                    $opts_data = ['label_key'=>'lab', 'value_key'=>'val', 'options'=>$opts];
                    $select = H::select(['name'=>'sort_list', 'class'=>$this->css.'select_tri', 'label'=>$this->get_tl('sel_tri'), 'data-alwaysposted'=>1], $opts_data, $post['sort_list']);
                    
                    $btn_search = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_LIST, 'class'=>$this->css.'btn_search'], 'ok');

                $div_filter->add_child([$info, $inp_txt, $select->label_tag(), $select, $btn_search]);

            $form->add_child($div_filter);

                $info = H::DIV(['class'=>$this->css.'info'], $this->get_tl('info_list'));

            $form->add_child( $info );

                $div_medias = H::DIV(['class'=>$this->css.'list_medias', 'id'=>self::module_name.'_list_medias'.$this->dom_id]);
                if ($data) {
                    foreach ($data as $key => $line) {
                        $div = H::DIV(['class'=>$this->css.'list_media', 'data-media_id'=>$line['media_id'], 'data-use_key'=>$line['use_key'], 'data-name'=>$line['name']]);
                        $name = H::DIV(['class'=>$this->css.'list_media_name', 'title'=>$line['name'].'.'.$line['ext']], $line['name'].'.'.$line['ext']);
                        if ($line['image']){
                            // image
                            $div->add_class('image');
                            $img = H::IMG(['src'=>$line['path'], 'class'=>$this->css.'list_media_img', 'alt'=>$line['name']]);
                            $img->add_class(($line['medias'][0]['width'] > $line['medias'][0]['height']) ? 'landscape' : 'portrait');
                            $div->add_child($img);
                        } else if ($line['video']){
                            $div->add_class('video');
                            $id_img = self::module_name.'list_media_img_'.$key.$this->dom_id;
                            $img = H::IMG(['class'=>$this->css.'list_media_img', 'id'=>$id_img, 'alt'=>$line['name']]);
                            $js.='Media_a.get_video_image("'.$line['path'].'", 2, "'.$id_img.'");';
                            $div->add_child($img);
                        }

                        $target_id = self::module_name.'list_media_detail_'.$key.$this->dom_id;
                        $details = H::detail(['class'=>$this->css.'list_media_detail', 'id'=>$target_id], $this->get_tl('detail'));

                        if ($line['image']){
                            foreach ($line['medias'] as $index => $media) {
                                $format = H::DIV(['class'=>$this->css.'format']);
                                    $label = $media['original'] == 1 ? $this->get_tl('original') : $this->get_tl('format', $index+1);
                                    $title = H::SPAN(['class'=>$this->css.'format_label'], $label);
                                $format->add_child($title);
                                if (strtolower($FS->get_file_ext($media['path'])) == 'svg') {
                                    $format->add_child( H::SPAN(['class'=>$this->css.'format_svg'], 'svg') );
                                } else {
                                    $width = H::SPAN(['class'=>$this->css.'format_width'], $media['width'].'px');
                                    $sep = H::SPAN(['class'=>$this->css.'format_separator'], '/');
                                    $height = H::SPAN(['class'=>$this->css.'format_height'], $media['height'].'px');
                                    $format->add_child([$width, $sep, $height]);
                                }
                                $details->add_child($format);
                            }
                        } else if ($line['video']) {
                            $format = H::DIV(['class'=>$this->css.'format']);
                                    $title = H::SPAN(['class'=>$this->css.'format_label'], $this->get_tl('format', '1'));
                                    $width = H::SPAN(['class'=>$this->css.'format_width'], $line['medias'][0]['width'].'px');
                                    $slash = H::SPAN(['class'=>$this->css.'format_separator'], '/');
                                    $height = H::SPAN(['class'=>$this->css.'format_height'], $line['medias'][0]['height'].'px');
                                $format->add_child([$title, $width, $slash, $height]);
                            $details->add_child($format);
                        }
                        $div->add_child([$name,$details]);
                        $div_medias->add_child($div);
                    }
                    // $js.= 'H_ui.toggle_accordion("'.$this->css.'list_media_label_detail'.'","hidden");';
                } else {
                    $nothing = H::DIV(['class'=>$this->css.'list_media_empty'], $this->get_tl('empty_media_list'));
                    $div_medias->add_child($nothing);
                }

            $form->add_child($div_medias);
                    
                $div_page = H::DIV(['class'=>$this->css.'list_pages']);
                if ($pages['page_count'] > 1) {
                    $index = $pages['page_index'];
                    if ($index > 0) {
                        $start_index = ($index-1)*$post['page_limit'];
                        $btn_previous = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_LIST, 'data-parameters'=>['start_index'=>$start_index]], '<<');
                        $div_page->add_child($btn_previous);
                    }

                    $opts = [];
                    for ($i=0 ; $i<$pages['page_count']; $i++) {
                        array_push($opts, ['label'=>$i+1, 'value'=>$i*$post['page_limit']]);
                    }
                    $options_data = array('label_key'=>'label', 'value_key'=>'value', 'options'=>$opts);
                    $selected = $index*$post['page_limit'];
                    $select = H::select(['name'=>'start_index'], $options_data, $selected, $this->input_action_identifier, $this->ACTION_LIST);
                    $div_page->add_child($select);

                    if ($index < ($pages['page_count']-1)) {
                        $start_index = ($index+1)*$post['page_limit'];
                        $btn_next = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_LIST, 'data-parameters'=>['start_index'=>$start_index]], '>>');
                        $div_page->add_child($btn_next);
                    }
                }
                
            $form->add_child($div_page);

            if ($this->params['multiple']){
                $btn_select = H::BUTTON(['id'=>self::module_name.'_list_btn_send_select'.$this->dom_id,'class'=>$this->css.'btn_send_select'], $this->get_tl('send_select'));
                $form->add_child($btn_select);
            }

            $js.= $this->inst_js.'.init_list();';
            $script = H::script($js, ['autoremove'=>1]);
            $form->add_child($script);

        $output->add_child( $form );

        return $output;
    }

    public function display_edit($post){
        global $CONFIG, $LANG;

        $medias = MEDIA_Class::get_info($post['media_id'], $post['use_key']);

        $output = H::group('edit_media');

            $ttl = H::DIV(['class'=>$this->css.'ttl_edit_media module_title'], $this->get_tl('ttl_edit_media'));

        $output->add_child( $ttl );

        if (!$medias){
            $not_found = H::SPAN(['class'=>$this->css.'edit_not_found'], $this->get_tl('media_not_found'));
            $output->add_child( $not_found );
            return $output;
        }

        $selected_id = (isset($post[$this->ifld_data_id]) && $post[$this->ifld_data_id]) ? $post[$this->ifld_data_id] : $medias[0]['id'];
        $media = false;
        foreach($medias as $key => $line){
            if ($line['id'] == $selected_id) $media = $line;
        }
        
        // $container_edit = H::DIV(['class'=>$this->css.'edit']);

        $form_select = H::form(['action'=>$this->get_index_relative_path(), 'class'=>$this->css.'form_media_select form_select', 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);

            $opts_data = ['first_empty'=>false,'label_key'=>'name','value_key'=>'id', 'options'=>$medias];
            $select = H::select(['name'=>$this->ifld_data_id, 'class'=>$this->css.'select_media', 'label'=>$this->get_tl('sel_edit_media')], $opts_data, $selected_id);

        $form_select->add_child([$select->label_tag(), $select]);

        $output->add_child($form_select);

            $preview = H::DIV(['class'=>$this->css.'edit_preview']);
                $label = H::DIV(['class'=>$this->css.'edit_preview_label'], $this->get_tl(('edit_preview')));
                // affiche un apercu
                $container_image = H::DIV(['class'=>$this->css.'edit_preview_image']);
                    $path = $CONFIG::BASE_URL.'public/media/media.php?f='.$this->media_id.'&i='.$post['use_key'].'&p='.$media['process_key'].'t='.time();
                    $img = H::IMG(['src'=>$path]);
                $container_image->add_child($img);
            $preview->add_child( [$label, $container_image] );

        $output->add_child($preview);
            
            $actions = H::DIV(['class'=>$this->css.'edit_actions']);
        
                // will be use by each block title to display the collapse indicator, 
                $form_target = self::module_name.'_edit_result_js';
                
                // to link details
                $name_details = 'media_edit_details';
                
                // add in every form
                $hiddens = [];
                array_push($hiddens, H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$selected_id]));
                array_push($hiddens, H::input_hidden(['name'=>'media_id', 'value'=>$this->media_id]));
                array_push($hiddens, H::input_hidden(['name'=>'use_key', 'value'=>$post['use_key']]));
                array_push($hiddens, H::input_hidden(['name'=>'process_key', 'value'=>$media['process_key']]));
        
                // NAME
                $details_name = H::detail(['class'=>$this->css.'details_media_edit name', 'name'=>$name_details, 'open'=>1], $this->get_tl('name'));
                    $form_name = H::form(['action'=>$this->get_index_relative_path(), 'class'=>$this->css.'form_media_name', 'dom_target'=>$form_target, 'dom_id'=>$this->dom_id]);
                    $form_name->add_child($hiddens);
                        $LANG->load_translation_data($post, self::module_name, 'data', $media['id']);
                        $name = $this->translate_block($post, [$this->ifld_data_filename.'['.$media['id'].']'], 's');
                        $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_NAME, 'class'=>$this->css.'btn_save name button_save'], $this->get_tl('tlc_save'));
                    $form_name->add_child([$name, $btn_save]);
                $details_name->add_child($form_name);

            $actions->add_child($details_name);

                // RESIZE
                if (!$this->params['no_resize']) {
                    $details_resize = H::detail(['class'=>$this->css.'details_media_edit resize', 'name'=>$name_details], $this->get_tl('resize'));
                        
                        $form_resize = H::form(['action'=>$this->get_index_relative_path(), 'class'=>$this->css.'form_media_resize form_edit', 'dom_target'=>$form_target, 'dom_id'=>$this->dom_id]);
                        $form_resize->add_child($hiddens);
                        $form_resize->add_child( H::input_hidden(['name'=>'process', 'value'=>'image_resize']) );

                            $width = H::input_integer([
                                'id'=>self::module_name.'_input_width'.$this->dom_id,
                                'name'=>'resize_width',
                                'label'=>$this->get_tl('lab_width'),
                                'class'=>$this->css.'resize_width_inp',
                                'value'=>$media['width'],
                                'data-default'=>$media['width']
                            ], 0, $media['width']);
                        
                        $form_resize->add_child([$width->label_tag(), $width]);

                            $height = H::input_integer([
                                'id'=>self::module_name.'_input_height'.$this->dom_id,
                                'name'=>'resize_height', 'label'=>$this->get_tl('lab_height'),
                                'class'=>$this->css.'resize_height_inp',
                                'value'=>$media['height'],
                                'data-default'=>$media['height']
                            ], 0, $media['height']);
                        
                        $form_resize->add_child([$height->label_tag(), $height]);

                            $proportion = H::input_checkbox(['id'=>self::module_name.'_input_proportion'.$this->dom_id, 'name'=>'resize_proportion', 'value'=>1, 'label'=>$this->get_tl('lab_proportion'), 'class'=>$this->css.'resize_proportion_inp', 'checked'=>true]);
                            $btn_apply = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_PROCESS, 'class'=>$this->css.'btn_apply button_action'], $this->get_tl('tlc_apply'));

                        $form_resize->add_child([$proportion->label_tag(), $proportion, $btn_apply]);

                    $details_resize->add_child([$form_resize]);

                    $actions->add_child($details_resize);
                    
                    $actions->add_child( H::script($this->inst_js.'.init_edit_resize();', ['autoremove'=>1]) );
                }

                // ROTATE
                $details_rotate = H::detail(['class'=>$this->css.'details_media_edit rotate', 'name'=>$name_details], $this->get_tl('rotate'));
                    $form_rotate = H::form(['action'=>$this->get_index_relative_path(), 'class'=>$this->css.'form_media_rotate form_edit', 'dom_target'=>$form_target, 'dom_id'=>$this->dom_id]);
                    $form_rotate->add_child($hiddens);
                    $form_rotate->add_child( H::input_hidden(['name'=>'process', 'value'=>'image_rotate']) );
                        $angle = H::input_integer(['id'=>self::module_name.'_input_angle'.$this->dom_id, 'name'=>'rotate_angle', 'label'=>$this->get_tl('lab_angle'), 'class'=>$this->css.'rotate_angle_inp']);
                        $angle_preview = H::DIV(['class'=>$this->css.'angle', 'id'=>self::module_name.'_rotate_angle'.$this->dom_id]);
                        $crop = H::input_checkbox(['name'=>'crop', 'value'=>1, 'label'=>$this->get_tl('keep_size'), 'checked'=>true, 'class'=>$this->css.'resize_crop_inp']);
                        $btn_apply = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_PROCESS, 'class'=>$this->css.'btn_apply button_action'], $this->get_tl('tlc_apply'));
                    $form_rotate->add_child([$angle->label_tag(), $angle, $angle_preview, $crop->label_tag(), $crop, $btn_apply]);
                $details_rotate->add_child( $form_rotate );
            
            $actions->add_child($details_rotate);
                
        $output->add_child($actions);

            $container_return_js = H::DIV(['class'=>'hidden','id'=>$form_target]);

        $output->add_child($container_return_js);

        return $output;

    }

    public function apply_process(&$post) {
        global $MEDIA,$DB;

        if (!isset($post['process']) || !isset($post[$this->ifld_data_id]) || !$post[$this->ifld_data_id]) {
            Utils::error_log($post);
            Utils::error_log('missing data in post');
            return false;
        }

        // first detect if it's a shared media, in this case ask for what to do with it (apply to all or only this one)
        if (!isset($post['media_replace_all'])){
            $q = 'SELECT COUNT(id) FROM '.$this->bddt_use.' WHERE id_media=?';
            $nb_instances = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);
            if ($nb_instances > 1){
                $_SESSION['media_post_waiting'] = $post;
                $script = H::script('Media_a.ask_for_shared();');
                return $script;
            }
        }
        
        if (isset($post['media_replace_all']) && !$post['media_replace_all']){
            // make the current media a unique version
            $post[$this->ifld_data_id] = $MEDIA->unshare_media($this->media_id,$post['use_key'], $post['process_key']);
        }

        $q = 'SELECT path, width, height FROM '.$this->bddt_data.' WHERE id=?';
        $data = $DB->prepared_query_line($q,'i',[$post[$this->ifld_data_id]]);
        $full_path = $MEDIA->base_path.$data['path'];

        $img = $MEDIA->file_to_image($full_path);
        if ($img) {
            switch ($post['process']) {
                case 'image_resize':
                    if (isset($post['resize_width']) && isset($post['resize_height'])) {
                        if ($post['resize_width'] > 0 && $post['resize_height'] <= 0) {
                            $post['resize_height'] = $post['resize_width'];
                        }
                        if ($post['resize_height'] > 0 && $post['resize_width'] <= 0) {
                            $post['resize_width'] = $post['resize_height'];
                        }
                        if (isset($post['resize_proportion'])) {
                            $img = $MEDIA->image_resize($img, $post['resize_width'], $post['resize_height']);
                        } else {
                            $img = $MEDIA->image_noprop_resize($img, $post['resize_width'], $post['resize_height']);
                        }
                    }
                break;
                
                case 'image_rotate':
                    if (isset($post['rotate_angle']) && $post['rotate_angle'] > 0) {
                        if (isset($post['crop'])) {
                            $img = $MEDIA->image_rotate($img, $post['rotate_angle'], true);
                        } else {
                            $img = $MEDIA->image_rotate($img, $post['rotate_angle']);
                        }
                    }
                break;
            }

            $width = imagesx($img);
            $height = imagesy($img);
            
            $img = $MEDIA->image_to_file($img, $full_path);
            if ($img) {
                $q = 'UPDATE '.$this->bddt_data.' SET width=?, height=? WHERE id=?';
                $DB->prepared_query($q, 'iii', [$width, $height, $post[$this->ifld_data_id]]);
            }
        }

        return H::script($this->inst_js.'.display_edit({}, '.$post['use_key'].');'.$this->inst_js.'.refresh_media('.$post['use_key'].');');
    }
    
    public function delete_media(&$post) {
        global $MEDIA;
        
        $field_identifier = explode('¤', $this->media_id)[0];
        $field_id = explode('¤', $this->media_id)[1];

        if ($MEDIA->delete_media_strict($field_identifier, $field_id, $post['media_use_key'])){
            return 'done';
        }

        return '';
    }
    
    public function toggle_big_view($post) {
        global $DB;

        if (isset($post['state'])){
            $state = ($post['state']) ? 1 : 0;
            $q = 'UPDATE '.$this->bddt_data.' d, '.$this->bddt_use.' u SET d.big_view=? WHERE d.id=u.id_media AND u.field_identifier=?';
            $DB->prepared_query($q, 'is', [$state, $this->media_id]);
            return 'done';
        }
    }
    
    public function clean_session() {
        global $CONFIG;
        $list = $_SESSION['media_list'];
        $currentTime = time();
        if ($list) {
            $session_time = $CONFIG::SESSION_HOURS * 60 * 60;
            foreach ($list as $key => $line) {
                if (isset($line['time'])){
                    $difT = intval($currentTime) - intval($line['time']);
                    if ($this->media_id != $key && $difT > $session_time) {
                        unset($_SESSION['media_list'][$key]);
                    }
                }
            }
        }
    }

    public function delete_languages($post){
        global $LANG, $MEDIA;

        foreach ($LANG->get_languages_data() as $key => $lang) {
            $MEDIA->delete_media_strict($this->field_identifier.'-'.$lang['iso'], $this->field_id);
        }
    }
}