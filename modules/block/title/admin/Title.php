<?php

namespace helPHP\modules\block\title\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\Datetime;

class Title extends HelPHP_module {

    const module_name = 'block';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container,$CONFIG::HELPHP_FOLDER.'modules/block/title/admin/Title.php');
    }

    private $ACTION_NEW_title = self::module_name.'_new';
    private $ACTION_SAVE_title = self::module_name.'_save';
    private $ACTION_EDIT_title = self::module_name.'_edit';
    private $ACTION_DELETE_title = self::module_name.'_delete';

    
    public function process_data(&$post, $to_return=false){
        global $CONFIG;
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        // $this->inst_js = 'helPHP_blk_title["'.$this->dom_id.'"]';

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_title_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'noedit '.$this->css;
        }

        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            //les diverses action sur l'objet maitre donc sur la table data !
            case $this->ACTION_NEW_title:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_title_id]);
                    $this->reset_fields($post, 'block_title');
                    $master_output->add_child( $this->edit_title($post) );
                }
            break;
            case $this->ACTION_EDIT_title:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, 'block_title');
                    //si il y a des champs multilingue faire appel à load_translation_data ici.
    
                    $master_output->add_child( $this->edit_title($post) );
                }
            break;
            case $this->ACTION_SAVE_title:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_title');
                    $this->save_title($post);
                    //si il y a des champs multilingue faire appel à save_translation_data ici.
    
                    $master_output->add_child($this->reload_block($post));
                    if ($post['block_id']=='block_new'.$post['dom_id']){
                        $post['block_id']='block_'.$post['block_name'].'_'.$post[$this->ifld_title_id];
                        $post['block_db_id']=$post[$this->ifld_title_id];
                    }
                    $master_output->add_child( $this->edit_title($post) );
                }
            break;
            
            case $this->ACTION_DELETE_title:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_title');
                    $this->delete_title($post);
                    //si il y a des champs multilingue faire appel à delete_translation_data ici.
    
                   $master_output->add_child( H::SPAN(['class'=>$this->css.'block_deleted'], $this->get_tl('tlc_deleted')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, 'block_title');
                $master_output->add_child( $this->edit_title($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function reload_block(&$post){
        if(isset($post['document_data_id']) && $post['document_data_id'] > 0){
            $js = H::script('h.modules.document_a["'.$this->dom_id.'"].load_block("'.$post['block_name'].'",'.$post[$this->ifld_title_id].',"'.$post['block_id'].'","'.$post['document_data_id'].'","'.$post['dom_id'].'");', ['autoremove'=>true]);
            return $js;
        }
    }
    //le formulaire d'édition classique :
    public function edit_title($post) {
        global $DB, $CONFIG;
        if(isset($post['no_subdiv'])){
            $output = H::group('edit_module');
        }else{
            // $block_order=isset($post['data-order'])?$post['data-order']:0;
            $output = H::div(['class'=>'block_admin_container block_1 title','data-blocktype'=>'title','data-blockid'=>$post[$this->ifld_title_id],'id'=>'block_admin_title_'.$post[$this->ifld_title_id] ]);
        }
            $form = H::form(['action'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.'document/index.php', 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit']);
            if (isset($post['document_data_id']) && $post['document_data_id'] > 0) {
                $target=H::input_hidden(['name'=>'target', 'value'=>$post['target']]);
                $block_name=H::input_hidden(['name'=>'block_name', 'value'=>$post['block_name']]);
                $block_id=H::input_hidden(['name'=>'block_id', 'value'=>$post['block_id']]);
                $dom_id=H::input_hidden(['name'=>'dom_id', 'value'=>$post['dom_id']]);
                $order=H::input_hidden(['name'=>'order', 'value'=>$post['last_order']]);
                $document_data_id=H::input_hidden(['name'=>'document_data_id', 'value'=>$post['document_data_id']]);
                $form->add_child([$target, $block_name, $block_id, $document_data_id, $dom_id,$order]);
                if (isset($post['block_db_id']) && $post['block_db_id'] > 0) {
                    $block_db_id=H::input_hidden(['name'=>'block_db_id', 'value'=>$post['block_db_id']]);
                    $form->add_child($block_db_id);
                }
            }
            // if (isset($post[$this->POSTED_CONTAINER_NAME])) $form->add_child(H::input_hidden(['name'=>$this->POSTED_CONTAINER_NAME,'value'=>$post[$this->POSTED_CONTAINER_NAME],'data-alwaysposted'=>1]));
            //nous sauvons l'id de l'objet
            if (isset($post[$this->ifld_title_id])){
                $form->add_child(H::input_hidden(['name'=>$this->ifld_title_id, 'value'=>$post[$this->ifld_title_id], 'data-alwaysposted'=>1]));
            }
                $title = H::input_text(['name'=>$this->ifld_title_title,'id'=>$this->ifld_title_title, 'label'=>$this->get_tl('title'), 'value'=>$post[$this->ifld_title_title], 'class'=>'inp_short_text']);
                $form->add_child([$title->label_tag(),$title]);

                
                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_title, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_title_id] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_title, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('tlc_ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);

        $output->add_child($form);
        

        return $output;
    }
    
    public function save_title(&$post) {
        global $DB;

        if($post[$this->ifld_title_id] == 0 || !isset($post[$this->ifld_title_id])){
            // create
            $post[$this->ifld_title_creadate] = date("Y-m-d H:i:s"); 
            $q = 'INSERT INTO '.$DB->table('block_title').' SET title=?,creadate=?';
            $success = $DB->prepared_query($q,'ss',[$post[$this->ifld_title_title],$post[$this->ifld_title_creadate]]);
            $post[$this->ifld_title_id] = $DB->last_insert_id();
            
        }else{
            // mise à jour
            $post[$this->ifld_title_creadate] = date("Y-m-d H:i:s"); 
            $q = 'UPDATE '.$DB->table('block_title').' SET title=?,creadate=? where id=?';
            //attention au i du type de l'id qui est pré-inséré
            $success = $DB->prepared_query($q,'ssi',[$post[$this->ifld_title_title],$post[$this->ifld_title_creadate],$post[$this->ifld_title_id]]);
        }
        if (isset($post['need_id'])) {
            $_SESSION[$post['need_id']] = $post[$this->ifld_title_id];
        }



    }

    public function delete_title(&$post) {
        global $DB;

        $q = 'DELETE FROM '.$DB->table('block_title').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_title_id]]);



    }
}
$json_sql='"{\"name\":\"title\",\"json\":\"[{\"type\":\"short_text\",\"name\":\"title\",\"limit\":\"\",\"index\":\"\",\"sort_order\":\"1\"}]\"}';