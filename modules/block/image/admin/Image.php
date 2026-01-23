<?php

namespace helPHP\modules\block\image\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\modules\media\admin\Media as Media_UI;
use helPHP\libs\Datetime;

class Image extends HelPHP_module {

    const module_name = 'block';

    const block_name = 'image';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container, $CONFIG::HELPHP_FOLDER.'modules/block/image/admin/Image.php');
    }

    private $ACTION_NEW_image = self::module_name.'_new';
    private $ACTION_SAVE_image = self::module_name.'_save';
    private $ACTION_EDIT_image = self::module_name.'_edit';
    private $ACTION_DELETE_image = self::module_name.'_delete';
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $CONFIG;

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_image_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'no_edit '.$this->css;
        }

        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW_image:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_image_id]);
                    $this->reset_fields($post, 'block_image');
                    $master_output->add_child( $this->edit_image($post) );
                }
            break;
            case $this->ACTION_EDIT_image:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, 'block_image');
    
                    $master_output->add_child( $this->edit_image($post) );
                }
            break;
            case $this->ACTION_SAVE_image:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_image');
                    $this->save_image($post);
    
                    $master_output->add_child( $this->edit_image($post) );
                }
            break;
            
            case $this->ACTION_DELETE_image:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_image');
                    $this->delete_image($post);
    
                    $master_output->add_child( H::SPAN(['class'=>$this->css.'block_deleted'], $this->get_tl('tlc_deleted')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, 'block_image');
                $master_output->add_child( $this->edit_image($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function edit_image($post) {
        global $DB, $CONFIG;
        
        $output = H::div(['class'=>'block_container_a image_a','data-block_type'=>'image','data-block_id'=>$post[$this->ifld_image_id],'id'=>'block_admin_image_'.$post[$this->ifld_image_id].$this->dom_id ]);
            



            $form = H::form(['action'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.'block/index.php', 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);

            $form->add_child( H::input_hidden(['name'=>'block_name', 'value'=>self::block_name, 'data-alwaysposted'=>1]) );

            if (isset($post['caller'])) $form->add_child( H::input_hidden(['name'=>'caller', 'value'=>$post['caller'], 'data-alwaysposted'=>1]) );
            if (isset($post['caller_params'])){
                foreach($post['caller_params'] as $name => $value){
                    $form->add_child( H::input_hidden(['name'=>'caller_params['.$name.']', 'value'=>$value, 'data-alwaysposted'=>1]) );
                }
            }

            $form->add_child(H::input_hidden(['name'=>$this->ifld_image_id, 'value'=>$post[$this->ifld_image_id], 'data-alwaysposted'=>1]));

                $label_image = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('image'));
                $params = ['accept'=>'image/*', 'list'=>true];
                $process['process'] = [['type'=>'image_to_file', 'quality'=>80]];
                $image = Media_UI::display('uploader', $params, $this->ifld_image_image, $post[$this->ifld_image_id], $process);
                $form->add_child([$label_image,$image]);


                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_image, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_image_id] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_image, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('tlc_ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);
        $output->add_child($form);
        
        

        return $output;
    }
    
    public function save_image(&$post) {
        global $DB;

        if($post[$this->ifld_image_id] == 0 || !isset($post[$this->ifld_image_id])){
            // create
            $post[$this->ifld_image_creadate] = date("Y-m-d H:i:s"); 
            $q = 'INSERT INTO '.$DB->table('block_image').' SET creadate=?';
            $success = $DB->prepared_query($q,'s',[$post[$this->ifld_image_creadate]]);
            $post[$this->ifld_image_id] = $DB->last_insert_id();
            
        }else{
            $post[$this->ifld_image_creadate] = date("Y-m-d H:i:s"); 
            $q = 'UPDATE '.$DB->table('block_image').' SET creadate=? where id=?';
            $success = $DB->prepared_query($q,'si',[$post[$this->ifld_image_creadate],$post[$this->ifld_image_id]]);
        }
        if (isset($post['need_id'])) {
            $_SESSION[$post['need_id']] = $post[$this->ifld_image_id];
        }

                                global $MEDIA;
                                $res = $MEDIA->process_media($post, $post[$this->ifld_image_id]);
                                if (!$res) $this->add_error('media_error');


    }

    public function delete_image(&$post) {
        global $DB;

        $q = 'DELETE FROM '.$DB->table('block_image').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_image_id]]);

                                global $MEDIA;
                                $MEDIA->delete_media($this->ifld_image_image, $post[$this->ifld_image_id]);


    }
}
$json_sql='"{\"name\":\"image\",\"json\":\"[{\"type\":\"image\",\"name\":\"image\",\"index\":\"\",\"sort_order\":\"2\"}]\"}';