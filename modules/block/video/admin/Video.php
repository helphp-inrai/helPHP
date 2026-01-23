<?php

namespace helPHP\modules\block\video\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\modules\media\admin\Media as Media_UI;
use helPHP\libs\Datetime;

class Video extends HelPHP_module {

    const module_name = 'block';

    const block_name = 'video';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container, $CONFIG::HELPHP_FOLDER.'modules/block/video/admin/Video.php');
    }

    private $ACTION_NEW_video = self::module_name.'_new';
    private $ACTION_SAVE_video = self::module_name.'_save';
    private $ACTION_EDIT_video = self::module_name.'_edit';
    private $ACTION_DELETE_video = self::module_name.'_delete';
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $CONFIG;

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_video_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'no_edit '.$this->css;
        }

        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW_video:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_video_id]);
                    $this->reset_fields($post, 'block_video');
                    $master_output->add_child( $this->edit_video($post) );
                }
            break;
            case $this->ACTION_EDIT_video:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, 'block_video');
    
                    $master_output->add_child( $this->edit_video($post) );
                }
            break;
            case $this->ACTION_SAVE_video:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_video');
                    $this->save_video($post);
    
                    $master_output->add_child( $this->edit_video($post) );
                }
            break;
            
            case $this->ACTION_DELETE_video:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_video');
                    $this->delete_video($post);
    
                    $master_output->add_child( H::SPAN(['class'=>$this->css.'block_deleted'], $this->get_tl('tlc_deleted')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, 'block_video');
                $master_output->add_child( $this->edit_video($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function edit_video($post) {
        global $DB, $CONFIG;
        
        $output = H::div(['class'=>'block_container_a video_a','data-block_type'=>'video','data-block_id'=>$post[$this->ifld_video_id],'id'=>'block_admin_video_'.$post[$this->ifld_video_id].$this->dom_id ]);
            



            $form = H::form(['action'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.'block/index.php', 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);

            $form->add_child( H::input_hidden(['name'=>'block_name', 'value'=>self::block_name, 'data-alwaysposted'=>1]) );

            if (isset($post['caller'])) $form->add_child( H::input_hidden(['name'=>'caller', 'value'=>$post['caller'], 'data-alwaysposted'=>1]) );
            if (isset($post['caller_params'])){
                foreach($post['caller_params'] as $name => $value){
                    $form->add_child( H::input_hidden(['name'=>'caller_params['.$name.']', 'value'=>$value, 'data-alwaysposted'=>1]) );
                }
            }

            $form->add_child(H::input_hidden(['name'=>$this->ifld_video_id, 'value'=>$post[$this->ifld_video_id], 'data-alwaysposted'=>1]));

                $checked=($post[$this->ifld_video_autoplay]==1)?true:false;
                $autoplay = H::input_checkbox(['name'=>$this->ifld_video_autoplay,'id'=>$this->ifld_video_autoplay, 'label'=>$this->get_tl('autoplay'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$checked]);
                $checked=($post[$this->ifld_video_control]==1)?true:false;
                $control = H::input_checkbox(['name'=>$this->ifld_video_control,'id'=>$this->ifld_video_control, 'label'=>$this->get_tl('control'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$checked]);
                $checked=($post[$this->ifld_video_vloop]==1)?true:false;
                $vloop = H::input_checkbox(['name'=>$this->ifld_video_vloop,'id'=>$this->ifld_video_vloop, 'label'=>$this->get_tl('vloop'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$checked]);
                $checked=($post[$this->ifld_video_nodonwload]==1)?true:false;
                $nodonwload = H::input_checkbox(['name'=>$this->ifld_video_nodonwload,'id'=>$this->ifld_video_nodonwload, 'label'=>$this->get_tl('nodonwload'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$checked]);
                $label_video = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('video'));
                $params = ['accept'=>'video/*'];
                $video = Media_UI::display('uploader', $params, $this->ifld_video_video, $post[$this->ifld_video_id]);
                $youtubesrc = H::input_text(['name'=>$this->ifld_video_youtubesrc,'id'=>$this->ifld_video_youtubesrc, 'label'=>$this->get_tl('youtubesrc'), 'value'=>$post[$this->ifld_video_youtubesrc], 'class'=>'inp_short_text']);
                $form->add_child([$autoplay->label_tag(),$autoplay,$control->label_tag(),$control,$vloop->label_tag(),$vloop,$nodonwload->label_tag(),$nodonwload,$label_video,$video,$youtubesrc->label_tag(),$youtubesrc]);

                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_video, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_video_id] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_video, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('tlc_ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);

        $output->add_child($form);
        

        return $output;
    }
    
    public function save_video(&$post) {
        global $DB;

        if($post[$this->ifld_video_id] == 0 || !isset($post[$this->ifld_video_id])){
            // create
            $post[$this->ifld_video_creadate] = date("Y-m-d H:i:s"); 
            $q = 'INSERT INTO '.$DB->table('block_video').' SET autoplay=?,control=?,vloop=?,nodonwload=?,youtubesrc=?,creadate=?';
            $success = $DB->prepared_query($q,'iiiiss',[$post[$this->ifld_video_autoplay],$post[$this->ifld_video_control],$post[$this->ifld_video_vloop],$post[$this->ifld_video_nodonwload],$post[$this->ifld_video_youtubesrc],$post[$this->ifld_video_creadate]]);
            $post[$this->ifld_video_id] = $DB->last_insert_id();
            
        }else{
            $post[$this->ifld_video_creadate] = date("Y-m-d H:i:s"); 
            $q = 'UPDATE '.$DB->table('block_video').' SET autoplay=?,control=?,vloop=?,nodonwload=?,youtubesrc=?,creadate=? where id=?';
            $success = $DB->prepared_query($q,'iiiissi',[$post[$this->ifld_video_autoplay],$post[$this->ifld_video_control],$post[$this->ifld_video_vloop],$post[$this->ifld_video_nodonwload],$post[$this->ifld_video_youtubesrc],$post[$this->ifld_video_creadate],$post[$this->ifld_video_id]]);
        }
        if (isset($post['need_id'])) {
            $_SESSION[$post['need_id']] = $post[$this->ifld_video_id];
        }

                global $MEDIA;
                $res = $MEDIA->process_media($post, $post[$this->ifld_video_id]);
                if (!$res) $this->add_error('media_error');


    }

    public function delete_video(&$post) {
        global $DB;

        $q = 'DELETE FROM '.$DB->table('block_video').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_video_id]]);

                global $MEDIA;
                                $MEDIA->delete_media('block_video_video', $post[$this->ifld_video_id]);


    }
}
$json_sql='"{\"name\":\"video\",\"json\":\"[{\"type\":\"boolean\",\"name\":\"autoplay\",\"index\":\"\",\"sort_order\":\"1\"},{\"type\":\"boolean\",\"name\":\"control\",\"index\":\"\",\"sort_order\":\"2\"},{\"type\":\"boolean\",\"name\":\"vloop\",\"index\":\"\",\"sort_order\":\"3\"},{\"type\":\"boolean\",\"name\":\"nodonwload\",\"index\":\"\",\"sort_order\":\"4\"},{\"type\":\"video\",\"name\":\"video\",\"index\":\"\",\"sort_order\":\"5\"},{\"type\":\"short_text\",\"name\":\"youtubesrc\",\"limit\":\"255\",\"index\":\"\",\"sort_order\":\"7\"}]\"}';