<?php

namespace helPHP\modules\block\shadertoytext\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\Datetime;

class Shadertoytext extends HelPHP_module {

    const module_name = 'block';
    
    const block_name = 'shadertoytext';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container,$CONFIG::HELPHP_FOLDER.'modules/block/shadertoytext/admin/Shadertoytext.php');
    }

    private $ACTION_NEW_shadertoytext = self::module_name.'_new';
    private $ACTION_SAVE_shadertoytext = self::module_name.'_save';
    private $ACTION_EDIT_shadertoytext = self::module_name.'_edit';
    private $ACTION_DELETE_shadertoytext = self::module_name.'_delete';

    
    public function process_data(&$post, $to_return=false){
        global $CONFIG;
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        // $this->inst_js = 'helPHP_blk_shadertoytext["'.$this->dom_id.'"]';

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_shadertoytext_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'no_edit '.$this->css;
        }

        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            //les diverses action sur l'objet maitre donc sur la table data !
            case $this->ACTION_NEW_shadertoytext:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_shadertoytext_id]);
                    $this->reset_fields($post, 'block_shadertoytext');
                    $master_output->add_child( $this->edit_shadertoytext($post) );
                }
            break;
            case $this->ACTION_EDIT_shadertoytext:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, 'block_shadertoytext');
                    //si il y a des champs multilingue faire appel à load_translation_data ici.
                    Language::load_translation_data($post, self::module_name, 'shadertoytext', $post[$this->ifld_shadertoytext_id]);
                    $master_output->add_child( $this->edit_shadertoytext($post) );
                }
            break;
            case $this->ACTION_SAVE_shadertoytext:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_shadertoytext');
                    $this->save_shadertoytext($post);
                    Language::save_translation_data($post, $post[$this->ifld_shadertoytext_id]);
                    $master_output->add_child( $this->edit_shadertoytext($post) );
                }
            break;
            
            case $this->ACTION_DELETE_shadertoytext:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_shadertoytext');
                    $this->delete_shadertoytext($post);
                    Language::delete_translation_data($post, self::module_name, 'shadertoytext', $post[$this->ifld_shadertoytext_id]);
                    $master_output->add_child( H::SPAN(['class'=>$this->css.'block_deleted'], $this->get_tl('tlc_deleted')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, 'block_shadertoytext');
                 Language::load_translation_data($post, self::module_name, 'shadertoytext', $post[$this->ifld_shadertoytext_id]);
                $master_output->add_child( $this->edit_shadertoytext($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    //le formulaire d'édition classique :
    public function edit_shadertoytext($post) {
        global $DB, $CONFIG;
        
        $output = H::div(['class'=>'block_container_a shadertoytext_a','data-block_type'=>'shadertoytext','data-block_id'=>$post[$this->ifld_shadertoytext_id],'id'=>'block_admin_shadertoytext_'.$post[$this->ifld_shadertoytext_id].$this->dom_id ]);
            



            $form = H::form(['action'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.'block/index.php', 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);

            $form->add_child( H::input_hidden(['name'=>'block_name', 'value'=>self::block_name, 'data-alwaysposted'=>1]) );

            if (isset($post['caller'])) $form->add_child( H::input_hidden(['name'=>'caller', 'value'=>$post['caller'], 'data-alwaysposted'=>1]) );
            if (isset($post['caller_params'])){
                foreach($post['caller_params'] as $name => $value){
                    $form->add_child( H::input_hidden(['name'=>'caller_params['.$name.']', 'value'=>$value, 'data-alwaysposted'=>1]) );
                }
            }

            $form->add_child(H::input_hidden(['name'=>$this->ifld_shadertoytext_id, 'value'=>$post[$this->ifld_shadertoytext_id], 'data-alwaysposted'=>1]));
                $code = H::input_textarea(['name'=>$this->ifld_shadertoytext_code,'id'=>$this->ifld_shadertoytext_code, 'label'=>$this->get_tl('code'), 'value'=>$post[$this->ifld_shadertoytext_code], 'class'=>'inp_textarea']);
                $multiblock=$this->translate_block($post, [$this->ifld_shadertoytext_content], 'l');
                $form->add_child([$code->label_tag(),$code,$multiblock]);

                
                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_shadertoytext, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_shadertoytext_id] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_shadertoytext, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('tlc_ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);

        $output->add_child($form);
        

        return $output;
    }
    
    public function save_shadertoytext(&$post) {
        global $DB;

        if($post[$this->ifld_shadertoytext_id] == 0 || !isset($post[$this->ifld_shadertoytext_id])){
            // create
            $post[$this->ifld_shadertoytext_creadate] = date("Y-m-d H:i:s"); 
            $q = 'INSERT INTO '.$DB->table('block_shadertoytext').' SET shadertoyid=?,code=?,creadate=?';
            $success = $DB->prepared_query($q,'sss',[$post[$this->ifld_shadertoytext_shadertoyid],$post[$this->ifld_shadertoytext_code],$post[$this->ifld_shadertoytext_creadate]]);
            $post[$this->ifld_shadertoytext_id] = $DB->last_insert_id();
            
        }else{
            // mise à jour
            $post[$this->ifld_shadertoytext_creadate] = date("Y-m-d H:i:s"); 
            $q = 'UPDATE '.$DB->table('block_shadertoytext').' SET shadertoyid=?,code=?,creadate=? where id=?';
            //attention au i du type de l'id qui est pré-inséré
            $success = $DB->prepared_query($q,'sssi',[$post[$this->ifld_shadertoytext_shadertoyid],$post[$this->ifld_shadertoytext_code],$post[$this->ifld_shadertoytext_creadate],$post[$this->ifld_shadertoytext_id]]);
        }
        if (isset($post['need_id'])) {
            $_SESSION[$post['need_id']] = $post[$this->ifld_shadertoytext_id];
        }



    }

    public function delete_shadertoytext(&$post) {
        global $DB;

        $q = 'DELETE FROM '.$DB->table('block_shadertoytext').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_shadertoytext_id]]);



    }
}
$json_sql='"{\"name\":\"shadertoytext\",\"json\":\"[{\"type\":\"short_text\",\"name\":\"shadertoyid\",\"limit\":\"\",\"index\":\"\",\"sort_order\":\"1\"},{\"type\":\"long_text\",\"name\":\"code\",\"index\":\"\",\"sort_order\":\"2\"},{\"type\":\"long_multilangue\",\"name\":\"contenu\",\"index\":\"\",\"sort_order\":\"4\"}]\"}';