<?php

namespace helPHP\modules\block\imagetext\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\modules\media\admin\Media as Media_UI;
use helPHP\libs\Datetime;

class Imagetext extends HelPHP_module {

    const module_name = 'block';

    const block_name = 'imagetext';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container, $CONFIG::HELPHP_FOLDER.'modules/block/imagetext/admin/Imagetext.php');
    }

    private $ACTION_NEW_imagetext = self::module_name.'_new';
    private $ACTION_SAVE_imagetext = self::module_name.'_save';
    private $ACTION_EDIT_imagetext = self::module_name.'_edit';
    private $ACTION_DELETE_imagetext = self::module_name.'_delete';
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $CONFIG;

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_imagetext_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'no_edit '.$this->css;
        }

        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW_imagetext:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_imagetext_id]);
                    $this->reset_fields($post, 'block_imagetext');
                    $master_output->add_child( $this->edit_imagetext($post) );
                }
            break;
            case $this->ACTION_EDIT_imagetext:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, 'block_imagetext');
                    Language::load_translation_data($post, self::module_name, 'imagetext', $post[$this->ifld_imagetext_id]);
                    $master_output->add_child( $this->edit_imagetext($post) );
                }
            break;
            case $this->ACTION_SAVE_imagetext:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_imagetext');
                    $this->save_imagetext($post);
                    Language::save_translation_data($post, $post[$this->ifld_imagetext_id]);
                    $master_output->add_child( $this->edit_imagetext($post) );
                }
            break;
            
            case $this->ACTION_DELETE_imagetext:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_imagetext');
                    $this->delete_imagetext($post);
                    Language::delete_translation_data($post, self::module_name, 'imagetext', $post[$this->ifld_imagetext_id]);
                    $master_output->add_child( H::SPAN(['class'=>$this->css.'block_deleted'], $this->get_tl('tlc_deleted')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, 'block_imagetext');
                $master_output->add_child( $this->edit_imagetext($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function edit_imagetext($post) {
        global $DB, $CONFIG;
        
        $output = H::div(['class'=>'block_container_a imagetext_a','data-block_type'=>'imagetext','data-block_id'=>$post[$this->ifld_imagetext_id],'id'=>'block_admin_imagetext_'.$post[$this->ifld_imagetext_id].$this->dom_id ]);
            



            $form = H::form(['action'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.'block/index.php', 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);

            $form->add_child( H::input_hidden(['name'=>'block_name', 'value'=>self::block_name, 'data-alwaysposted'=>1]) );

            if (isset($post['caller'])) $form->add_child( H::input_hidden(['name'=>'caller', 'value'=>$post['caller'], 'data-alwaysposted'=>1]) );
            if (isset($post['caller_params'])){
                foreach($post['caller_params'] as $name => $value){
                    $form->add_child( H::input_hidden(['name'=>'caller_params['.$name.']', 'value'=>$value, 'data-alwaysposted'=>1]) );
                }
            }

            $form->add_child(H::input_hidden(['name'=>$this->ifld_imagetext_id, 'value'=>$post[$this->ifld_imagetext_id], 'data-alwaysposted'=>1]));

$values = [['value'=>'floatleft', 'label'=>'floatleft'], ['value'=>'floatright', 'label'=>'floatright']];
                $alignement = H::input_multiple_radios(['name'=>$this->ifld_imagetext_alignement,'id'=>$this->ifld_imagetext_alignement, 'label'=>$this->get_tl('alignement'), 'value'=>$post[$this->ifld_imagetext_alignement], 'class'=>' inp_radio', 'selected'=>$post[$this->ifld_imagetext_alignement], 'values'=>$values]);
                $label_image = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('image'));
                $params = ['accept'=>'image/*', 'list'=>true];
                $process['process'] = [['type'=>'image_to_file', 'quality'=>80]];
                $image = Media_UI::display('uploader', $params, $this->ifld_imagetext_image, $post[$this->ifld_imagetext_id], $process);
                $multiblock=$this->translate_block($post, [$this->ifld_imagetext_contenu], 'l');
                $form->add_child([$multiblock,$alignement,$label_image,$image]);


                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_imagetext, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_imagetext_id] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_imagetext, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('tlc_ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);
        $output->add_child($form);
        
        

        return $output;
    }
    
    public function save_imagetext(&$post) {
        global $DB;

        if($post[$this->ifld_imagetext_id] == 0 || !isset($post[$this->ifld_imagetext_id])){
            // create
            $post[$this->ifld_imagetext_creadate] = date("Y-m-d H:i:s"); 
            $q = 'INSERT INTO '.$DB->table('block_imagetext').' SET alignement=?,creadate=?';
            $success = $DB->prepared_query($q,'ss',[$post[$this->ifld_imagetext_alignement],$post[$this->ifld_imagetext_creadate]]);
            $post[$this->ifld_imagetext_id] = $DB->last_insert_id();
            
        }else{
            $post[$this->ifld_imagetext_creadate] = date("Y-m-d H:i:s"); 
            $q = 'UPDATE '.$DB->table('block_imagetext').' SET alignement=?,creadate=? where id=?';
            $success = $DB->prepared_query($q,'ssi',[$post[$this->ifld_imagetext_alignement],$post[$this->ifld_imagetext_creadate],$post[$this->ifld_imagetext_id]]);
        }
        if (isset($post['need_id'])) {
            $_SESSION[$post['need_id']] = $post[$this->ifld_imagetext_id];
        }

                                global $MEDIA;
                                $res = $MEDIA->process_media($post, $post[$this->ifld_imagetext_id]);
                                if (!$res) $this->add_error('media_error');


    }

    public function delete_imagetext(&$post) {
        global $DB;

        $q = 'DELETE FROM '.$DB->table('block_imagetext').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_imagetext_id]]);

                                global $MEDIA;
                                $MEDIA->delete_media($this->ifld_imagetext_image, $post[$this->ifld_imagetext_id]);


    }
}
$json_sql='"{\"name\":\"imagetext\",\"json\":\"[{\"type\":\"long_multilangue\",\"name\":\"contenu\",\"index\":\"\",\"sort_order\":\"1\"},{\"type\":\"multiple_radios\",\"name\":\"alignement\",\"values\":[\"floatleft\",\"floatright\"],\"index\":\"\",\"sort_order\":\"2\"},{\"type\":\"image\",\"name\":\"image\",\"index\":\"\",\"sort_order\":\"3\"}]\"}';