<?php

namespace helPHP\modules\block\news\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\Datetime;

class News extends HelPHP_module {

    const module_name = 'block';

    const block_name = 'news';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container, $CONFIG::HELPHP_FOLDER.'modules/block/news/admin/News.php');
    }

    private $ACTION_NEW_news = self::module_name.'_new';
    private $ACTION_SAVE_news = self::module_name.'_save';
    private $ACTION_EDIT_news = self::module_name.'_edit';
    private $ACTION_DELETE_news = self::module_name.'_delete';
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $CONFIG;

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_news_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'no_edit '.$this->css;
        }

        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW_news:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_news_id]);
                    $this->reset_fields($post, 'block_news');
                    $master_output->add_child( $this->edit_news($post) );
                }
            break;
            case $this->ACTION_EDIT_news:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, 'block_news');
    
                    $master_output->add_child( $this->edit_news($post) );
                }
            break;
            case $this->ACTION_SAVE_news:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_news');
                    $this->save_news($post);
    
                    $master_output->add_child( $this->edit_news($post) );
                }
            break;
            
            case $this->ACTION_DELETE_news:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, 'block_news');
                    $this->delete_news($post);
    
                    $master_output->add_child( H::SPAN(['class'=>$this->css.'block_deleted'], $this->get_tl('tlc_deleted')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, 'block_news');
                $master_output->add_child( $this->edit_news($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function edit_news($post) {
        global $DB, $CONFIG;
        
        $output = H::div(['class'=>'block_container_a news_a','data-block_type'=>'news','data-block_id'=>$post[$this->ifld_news_id],'id'=>'block_admin_news_'.$post[$this->ifld_news_id].$this->dom_id ]);
            



            $form = H::form(['action'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.'block/index.php', 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);

            $form->add_child( H::input_hidden(['name'=>'block_name', 'value'=>self::block_name, 'data-alwaysposted'=>1]) );

            if (isset($post['caller'])) $form->add_child( H::input_hidden(['name'=>'caller', 'value'=>$post['caller'], 'data-alwaysposted'=>1]) );
            if (isset($post['caller_params'])){
                foreach($post['caller_params'] as $name => $value){
                    $form->add_child( H::input_hidden(['name'=>'caller_params['.$name.']', 'value'=>$value, 'data-alwaysposted'=>1]) );
                }
            }

            $form->add_child(H::input_hidden(['name'=>$this->ifld_news_id, 'value'=>$post[$this->ifld_news_id], 'data-alwaysposted'=>1]));

                $form->add_child([]);


                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_news, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_news_id] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_news, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('tlc_ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);
        $output->add_child($form);
        
        

        return $output;
    }
    
    public function save_news(&$post) {
        global $DB;

        if($post[$this->ifld_news_id] == 0 || !isset($post[$this->ifld_news_id])){
            // create
            $post[$this->ifld_news_creadate] = date("Y-m-d H:i:s"); 
            $q = 'INSERT INTO '.$DB->table('block_news').' SET creadate=?';
            $success = $DB->prepared_query($q,'s',[$post[$this->ifld_news_creadate]]);
            $post[$this->ifld_news_id] = $DB->last_insert_id();
            
        }else{
            $post[$this->ifld_news_creadate] = date("Y-m-d H:i:s"); 
            $q = 'UPDATE '.$DB->table('block_news').' SET creadate=? where id=?';
            $success = $DB->prepared_query($q,'si',[$post[$this->ifld_news_creadate],$post[$this->ifld_news_id]]);
        }
        if (isset($post['need_id'])) {
            $_SESSION[$post['need_id']] = $post[$this->ifld_news_id];
        }



    }

    public function delete_news(&$post) {
        global $DB;

        $q = 'DELETE FROM '.$DB->table('block_news').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_news_id]]);



    }
}
$json_sql='"{\"name\":\"news\",\"json\":\"[]\"}';