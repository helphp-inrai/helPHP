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
namespace helPHP\modules\group\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
class Group extends HelPHP_module{

    const module_name = 'group';

    protected $ACTION_NEW = self::module_name.'_new';
    protected $ACTION_SAVE = self::module_name.'_save';
    protected $ACTION_EDIT = self::module_name.'_edit';
    protected $ACTION_DELETE = self::module_name.'_delete';

    protected $ACTION_NEW_group_modules = self::module_name.'_group_modules_new';
    protected $ACTION_SAVE_group_modules = self::module_name.'_group_modules_save';
    protected $ACTION_EDIT_group_modules = self::module_name.'_group_modules_edit';
    protected $ACTION_DELETE_group_modules = self::module_name.'_group_modules_delete';
    
    protected $ACTION_ADD_CONTENT = self::module_name.'_add_content';
    protected $ACTION_SAVE_CONTENT = self::module_name.'_save_content';
    protected $ACTION_DEL_CONTENT = self::module_name.'_delete_content';

    protected $root_module = true;

    public $base_grp = ['users'=>1]; // indesctrutible group, every user are in it. each is formed by group_name=>id_group

    protected $params = [];

    protected $field_identifier = false;
    protected $field_id = false;
    protected $widget_id = false;

    function __construct($dom_container = null, $field_identifier = false, $field_id=false, $params=false) {
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container);

        global $DB_CENTRAL;
        foreach($this->base_grp as $name => $id){
            $exist = $DB_CENTRAL->query_value('SELECT name FROM '.$DB_CENTRAL->table('group_data').' WHERE id='.$id);
            if (!$exist){
                $DB_CENTRAL->query('INSERT INTO '.$DB_CENTRAL->table('group_data').' SET name="'.$name.'", id='.$id.', active=1');
                // add every users in it, to ignore duplication, start by deleting every one from it
                $DB_CENTRAL->query('DELETE FROM '.$DB_CENTRAL->table('group_users').' WHERE id_group_data='.$id);
                $q = 'INSERT INTO '.$DB_CENTRAL->table('group_users').' (id_group_data, id_users_data) SELECT DISTINCT '.$id.',id FROM '.$DB_CENTRAL->table('users_data');
                $DB_CENTRAL->query($q);
            }
        }

        if ($params) {
            $this->params = array_replace($this->params, $params);
        }
        
        if ($field_identifier !== false) {
            $this->field_identifier = $field_identifier;
            $this->widget_id = $field_identifier;
        }

        if ($field_id !== false) {
            $this->field_id = $field_id;
            $this->widget_id .= '¤'.$field_id;
        }

        if ($this->widget_id !== false){
            if (!isset($_SESSION['widget_group'])) $_SESSION['widget_group'] = [];
            if (!isset($_SESSION['widget_group'][$this->widget_id])) $_SESSION['widget_group'][$this->widget_id] = [];

            $_SESSION['widget_group'][$this->widget_id]['params'] = json_encode($this->params);
            $_SESSION['widget_group'][$this->widget_id]['time'] = time();
        }

        $this->clean_session();
    }
    public function clean_session() {
        $list = isset($_SESSION['widget_group']) ? $_SESSION['widget_group'] : false;
        $currentTime = time();
        if ($list) {
            foreach ($list as $key => $line) {
                if (isset($line['time'])){
                    $difT = intval($currentTime) - intval($line['time']);
                    if ($this->widget_id != $key && $difT > 900) { // 15 min
                        unset($_SESSION['widget_group'][$key]);
                    }
                }
            }
        }
    }

    public function process_data(&$post, $toreturn = false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        if (isset($post['widget_id']) && $post['widget_id']){
            $this->widget_id = $post['widget_id'];
            $t = explode('¤', $post['widget_id']);
            $this->field_identifier = $t[0];
            $this->field_id = $t[1];
            $post[$this->ifld_content_field_identifier] = $this->field_identifier;
            $post[$this->ifld_content_id_item] = $this->field_id;
        }

        if ($this->widget_id != false) {
            // if (!isset($_SESSION['widget_group'][$this->widget_id]['params'])){
            //     Utils::error_log($this->widget_id);
            //     Utils::error_log($_SESSION['widget_group']);
            // }
            $this->params = isset($_SESSION['widget_group'][$this->widget_id]['params']) ? json_decode($_SESSION['widget_group'][$this->widget_id]['params'], true) : false;
        }

        $master_output = H::group('group_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW:
                $post[$this->ifld_data_id]=0;
                $this->reset_fields($post, 'group_data');
                $master_output->add_child( $this->select_data($post) );
                $master_output->add_child( $this->edit_data($post) );
            break;
            
            case $this->ACTION_EDIT:
                $this->prepare_fields($post, 'group_data');
                $master_output->add_child( $this->select_data($post) );
                $master_output->add_child( $this->edit_data($post) );
            break;
            
            case $this->ACTION_SAVE:
                $this->check_posted_data($post, 'group_data');
                $this->save_data($post);
                $master_output->add_child( $this->select_data($post) );
                $master_output->add_child( $this->edit_data($post) );
            break;
            
            case $this->ACTION_DELETE:
                $this->check_posted_data($post, 'group_data');
                
                $this->delete_data($post);
                $this->delete_group_modules($post, true);
                $this->delete_content($post, true);
            
                $post[$this->ifld_data_id] = 0;
                $this->reset_fields($post, 'group_data');
                $master_output->add_child( $this->select_data($post) );
                $master_output->add_child( $this->edit_data($post) );
            break;
            
            //les autres appels de méthodes si il y a des sous sections
            

            case $this->ACTION_NEW_group_modules:
                $this->check_posted_data($post, 'group_modules');
                $this->add_group_modules($post);
                $master_output->add_child( $this->edit_group_modules($post) );
            break;
            
            case $this->ACTION_EDIT_group_modules:
                $this->prepare_fields($post, 'group_modules');
                $master_output->add_child( $this->edit_group_modules($post) );
            break;
            
            case $this->ACTION_SAVE_group_modules:
                $this->check_posted_data($post, 'group_modules');
                $this->save_group_modules($post);
                $master_output->add_child( $this->edit_group_modules($post) );
            break;
            
            case $this->ACTION_DELETE_group_modules:
                $this->check_posted_data($post, 'group_modules');
                $this->delete_group_modules($post);
                $master_output->add_child( $this->edit_group_modules($post) );
            break;
            
            case $this->ACTION_ADD_CONTENT:
                $this->check_posted_data($post, 'group_content');
                $master_output->add_child( $this->new_content($post) );
            break;
            case $this->ACTION_SAVE_CONTENT:
                $this->check_posted_data($post, 'group_content');
                $master_output->add_child( $this->save_content($post) );
            break;
            case $this->ACTION_DEL_CONTENT:
                $this->check_posted_data($post, 'group_content');
                $master_output->add_child( $this->delete_content($post) );
            break;

            default:
                $master_output->add_child( $this->select_data($post) );
            break;
        }
        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }
    
    public function select_data ($post) {
        global $DB_CENTRAL;

        $q = 'SELECT id, name FROM '.$DB_CENTRAL->table('group_data');
        $liste = $DB_CENTRAL->prepared_query_list($q);

        $output = H::group('select_group');

        $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
        if (isset($post[$this->ifld_data_id]) && $post[$this->ifld_data_id] != 0) {
            $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_data_id])));
        }
    
        $form = H::form(array('action'=>$this->get_index_relative_path(),'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));
        if (isset($post[$this->posted_container_name])) $form->add_child(H::input_hidden(['name'=>$this->posted_container_name,'value'=>$post[$this->posted_container_name],'data-alwaysposted'=>1]));
            $button_new=H::submit_button_single(array('class'=>$this->css.'button_new button_new','name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW, 'title'=>$this->get_tl('tlc_new')), $this->get_tl('tlc_new'));
            $selected_id = isset($post[$this->ifld_data_id])?$post[$this->ifld_data_id]:0;
            $opts_data = array('first_empty'=>true , 'value_key'=>'id' , 'label_key'=>'name' , 'options'=>$liste);
            $select = H::select(array('name'=>$this->ifld_data_id, 'label'=>$this->get_tl('tlc_select'), 'data-alwaysposted'=>1, 'class'=>$this->css.'select' ) , $opts_data , $selected_id, $this->input_action_identifier, $this->ACTION_EDIT);
        $form->add_child([$button_new,$select->label_tag(),$select]);
        $output->add_child([$title,$form]);

        return $output;
    }
    
    //le formulaire d'édition classique :
    public function edit_data($post){
        global $LANG;

        $output = H::group('edit_module');

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_edit form_edit'));

                //nous sauvons l'id de l'objet
                $hidden_id = H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id], 'data-alwaysposted'=>1, 'id'=>'current_id']);

                // if (!in_array($post[$this->ifld_data_id], $this->base_grp)){
                $name = H::input_text(['name'=>$this->ifld_data_name,'id'=>$this->ifld_data_name, 'label'=>$this->get_tl('name'), 'value'=>$post[$this->ifld_data_name], 'class'=>'inp_short_text']);
                $active = H::input_checkbox(['name'=>$this->ifld_data_active,'id'=>$this->ifld_data_active, 'label'=>$this->get_tl('active'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$post[$this->ifld_data_active]]);
                if (in_array($post[$this->ifld_data_id], $this->base_grp)) {
                    $name->set_attribute('disabled',1);
                    $active->set_attribute('disabled',1);
                }

            $form->add_child([$hidden_id,$name->label_tag(),$name,$active->label_tag(),$active]);

                $rights = H::fieldset(['class'=>$this->css.'fieldset_rights'],$this->get_tl('group_right'));
                    $write = H::input_checkbox(['name'=>$this->ifld_data_write,'id'=>$this->ifld_data_write, 'label'=>$this->get_tl('write'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$post[$this->ifld_data_write]]);
                    $read = H::input_checkbox(['name'=>$this->ifld_data_read,'id'=>$this->ifld_data_read, 'label'=>$this->get_tl('read'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$post[$this->ifld_data_read]]);
                    $delete = H::input_checkbox(['name'=>$this->ifld_data_delete,'id'=>$this->ifld_data_delete, 'label'=>$this->get_tl('delete'), 'value'=>1, 'class'=>' inp_check' , 'checked'=>$post[$this->ifld_data_delete]]);
                $rights->add_child([$write->label_tag(),$write,$read->label_tag(),$read,$delete->label_tag(),$delete]);

            $form->add_child($rights);

                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if (!in_array($post[$this->ifld_data_id], $this->base_grp)) {
                    $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')), $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }

            $form->add_child($block_btns);
                // $btn_delete = H::submit_button(array('class'=>$this->module_name.'_admin_btn_del', 'name'=>$this->input_action_identifier , 'id'=>$this->module_name.'_admin_btn_save', 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')),  'X');
                
        $output->add_child($form);
        //appele l'éditeur d'indexation si le module est indexable

        //l'affichage des parties d'éditeur des sous-section...un div sous container est créé pour chacun pour gérer le placement et les retours ajax dans le même div.

        $group_modules_container = H::div(['class'=>$this->css.'subcontainer module_sub_container multiple', 'id'=>$this->dom_container.'_group_modules'.$this->dom_id]);
            $post[$this->input_action_identifier]=$this->ACTION_EDIT_group_modules;
            $post['group_modules-id_'.self::module_name.'_data'] = $post[$this->ifld_data_id];
        $group_modules_container->add_child( $this->process_data($post,true) );
        $output->add_child($group_modules_container);
    
        return $output;
    }
    
    //sauve les données.
    public function save_data(&$post){
        global $DB_CENTRAL;

        if(!$post[$this->ifld_data_id]){
            // création
            $q = 'INSERT INTO '. $DB_CENTRAL->table('group_data').' SET name=?,active=?,`write`=?,`read`=?,`delete`=?';
            $success = $DB_CENTRAL->prepared_query($q,'siiii',[$post[$this->ifld_data_name],$post[$this->ifld_data_active],$post[$this->ifld_data_write],$post[$this->ifld_data_read],$post[$this->ifld_data_delete]]);
            $post[$this->ifld_data_id] = $DB_CENTRAL->last_insert_id();
        }else{
            // mise à jour
            $q = 'UPDATE '.$DB_CENTRAL->table('group_data').' SET name=?,active=?,`write`=?,`read`=?,`delete`=? where id=?';
            $success = $DB_CENTRAL->prepared_query($q,'siiiii',[$post[$this->ifld_data_name],$post[$this->ifld_data_active],$post[$this->ifld_data_write],$post[$this->ifld_data_read],$post[$this->ifld_data_delete],$post[$this->ifld_data_id]]);
        }
    }
    //supprime les données
    public function delete_data(&$post) {
        global $DB_CENTRAL;
        
        if (in_array($post[$this->ifld_data_id],$this->base_grp)){
            $this->add_error('error_delete_base_grp');
            return;
        }

        $q = 'DELETE FROM '.$DB_CENTRAL->table('group_data').' WHERE id=?';
        $res = $DB_CENTRAL->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
    }
    //les méthodes venues des autres sections

    
    public function edit_group_modules($post){
        global $DB,$CONFIG;
        $output = H::group('edit_module');
        if ($post['group_modules-id_'.self::module_name.'_data'] > 0){
            $q='SELECT * FROM '.$DB->table('group_modules').' WHERE id_'.self::module_name.'_data=? ORDER BY id';
            $asso = $DB->prepared_query_list($q, 'i', [$post['group_modules-id_'.self::module_name.'_data']]);

            $css = 'sub_object multiple group_modules';
            
            $realForm = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_container.'_group_modules'.$this->dom_id, 'class'=>$css.'form_multiple form_multiple'));
                // title sub part
                $ttl = H::DIV(['class'=>$css.' module_title'], $this->get_tl('group_modules_ttl'));
            $realForm->add_child($ttl);
            //nous sauvons l'id de l'objet
            $realForm->add_child(H::input_hidden(['name'=>'group_modules-id_'.self::module_name.'_data', 'value'=>$post['group_modules-id_'.self::module_name.'_data'], 'data-alwaysposted'=>1, 'id'=>'group_modules_currentId']));
            // btn add
                $btns_action = H::DIV(['class'=>$css.' action_buttons']);
                    $btn_add = H::submit_button_single(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW_group_modules, 'class'=>$css.' button_add', 'data-parameters'=>['group_modules-id_'.self::module_name.'_data'=>$post['group_modules-id_'.self::module_name.'_data']]], $this->get_tl('tlc_add'));
                $btns_action->add_child( $btn_add );
            $realForm->add_child( $btns_action );
            
            if ($asso){
                $lst = [];
                $tmodule=$CONFIG::MODULES_LIST;
                ksort($tmodule,SORT_STRING);
                foreach($tmodule as $moduleName => $data){
                    array_push($lst, ['name'=>$moduleName]);
                }
                if (!$lst){ $lst=array(); }
                foreach ($asso as $key=>$line) {
                    $form = H::DIV(['class'=>$css.' form_multiple_line']);
                        $id_group_data = H::input_hidden(['name'=>'group_modules-id_group_data['.$line['id'].']','id'=>'group_modules-id_group_data-'.$line['id'].'', 'label'=>$this->get_tl('id_group_data'), 'value'=>$line['id_group_data'], 'data-alwaysposted'=>'1' ]);
                        
                        $opts_data = ['first_empty'=>['name'=>$this->get_tl('module'), 'value'=>''], 'label_key'=>'name', 'value_key'=>'name', 'options'=>$lst];
                        $selected = $line['module'] > 0 ? $line['module'] : '';
                        $module = H::select(['name'=>'group_modules-module['.$line['id'].']','id'=>'group_modules-module-'.$line['id']], $opts_data, $selected);
                        
                        $checked=($line['registered']==1)?true:false;
                        $registered = H::input_checkbox(['name'=>'group_modules-registered['.$line['id'].']','id'=>'group_modules-registered-'.$line['id'].'', 'label'=>$this->get_tl('registered'), 'value'=>1, 'checked'=>$checked , ]);
                        
                        $checked=($line['admin']==1)?true:false;
                        $admin = H::input_checkbox(['name'=>'group_modules-admin['.$line['id'].']','id'=>'group_modules-admin-'.$line['id'].'', 'label'=>$this->get_tl('admin'), 'value'=>1, 'checked'=>$checked , ]);
                        
                        $checked=($line['no_edit']==1)?true:false;
                        $no_edit = H::input_checkbox(['name'=>'group_modules-no_edit['.$line['id'].']','id'=>'group_modules-no_edit-'.$line['id'].'', 'label'=>$this->get_tl('no_edit'), 'value'=>1 , 'checked'=>$checked]);
                        
                        $btn_del = H::button_icon('trash-2', ['type'=>'submit', 'class'=>$css.' button_delete', 'name'=>$this->input_action_identifier,'value'=>$this->ACTION_DELETE_group_modules,'title'=>$this->get_tl('tlc_delete'),'data-parameters'=>['group_modules-id'=>$line['id'],'group_modules-id_'.self::module_name.'_data'=>$line['id_'.self::module_name.'_data']]]);
                        
                    $form->add_child([$btn_del,$id_group_data,$module,$registered->label_tag(),$registered,$admin->label_tag(),$admin,$no_edit->label_tag(),$no_edit]);
                    $realForm->add_child( $form );
                }
                $block_btn = H::DIV(['class'=>$css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$css.' button_save', 'name' => $this->input_action_identifier , 'value'=>$this->ACTION_SAVE_group_modules], $this->get_tl('tlc_save'));
                $block_btn->add_child( $btn_save );
                $realForm->add_child( $block_btn );
            } else {
                $realForm->add_child( H::DIV(['class'=>$css.' msg_empty'], $this->get_tl('no_group_modules')) );
            }
            $output->add_child($realForm);
        }
        return $output;
    }
    
    public function add_group_modules(&$post)
    {
        global $DB;

        $q = 'INSERT INTO '.$DB->table('group_modules').' SET id_'.self::module_name.'_data=?';
        $DB->prepared_query($q, 'i', [$post['group_modules-id_'.self::module_name.'_data']]);
    }
    
    //sauve les données.
    public function save_group_modules(&$post){
        global $DB;
    
        foreach ($post['group_modules-id_'.self::module_name.'_data'] as $id=>$value) {
            $q = 'UPDATE '.$DB->table('group_modules').' SET id_group_data=?,module=?,registered=?,admin=?,no_edit=? where id=?';
            $success = $DB->prepared_query($q, 'isiiii', array($post['group_modules-id_group_data'][$id],$post['group_modules-module'][$id],(isset($post['group_modules-registered'][$id]))?1:0,(isset($post['group_modules-admin'][$id]))?1:0,(isset($post['group_modules-no_edit'][$id]))?1:0, $id));
        }
        $post['group_modules-id_'.self::module_name.'_data']=$value;
        
            //$q = 'UPDATE '.$DB_CENTRAL->table('group_modules').' SET id_group_data=?,id_modules_data=?,registered=?,admin=?,no_edit=? where id=?';
            //attention au i du type de l'id qui est pré-inséré
            //$success = $DB_CENTRAL->prepared_query($q,'iiiiii',[$post['group_modules-id_group_data'][$id],$post['group_modules-id_modules_data'][$id],(isset($post['group_modules-registered'][$id]))?1:0,(isset($post['group_modules-admin'][$id]))?1:0,(isset($post['group_modules-no_edit'][$id]))?1:0, $post['group_modules-id']]);
    }
    //supprime les données
    public function delete_group_modules(&$post, $from_master = false) {
        global $DB;
        if (!$from_master){
            // delete one element
            $q = 'DELETE FROM '.$DB->table('group_modules').' WHERE id=?';
            $res = $DB->prepared_query($q, 'i', [$post['group_modules-id']]);
        } else {
            // delete all elements
            $q = 'DELETE FROM '.$DB->table('group_modules').' WHERE id_'.self::module_name.'_data=?';
            $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
        }
    }
    
    public static function delete($field_identifier, $field_id){
        if (!$field_identifier){
            Utils::error_log('need parameter field_identifier to work - field_identifier = '.$field_identifier);
            return false;
        }
        if (!$field_id){
            Utils::error_log('need parameter $id_tem to work - field_id = '.$field_id);
            return false;
        }

        global $DB;
        $q = 'DELETE FROM '.$DB->table('group_content').' WHERE field_identifier=? AND id_item=?';
        $DB->prepared_query($q, 'si', [$field_identifier, $field_id]);

        return true;
    }
    public static function widget($params, $field_identifier, $field_id) {
        if (!$field_identifier){
            Utils::error_log('need parameter field_identifier to work - field_identifier = '.$field_identifier);
            return false;
        }
        if (!$field_id){
            Utils::error_log('need parameter $id_tem to work - field_id = '.$field_id);
            return false;
        }

        $post = [];
        $instance = new Group(null, $field_identifier, $field_id, $params);
        return $instance->display_widget($post);
    }
    public function display_widget($post){
        global $DB,$DB_CENTRAL;

        parent::process_data($post); // to create instance js

        if (!$this->field_identifier){
            Utils::error_log('need parameter field_identifier to work - $field_identifier = '.$this->field_identifier);
            return false;
        }
        if (!$this->field_id){
            Utils::error_log('need parameter $id_tem to work - $id_item = '.$this->field_id);
            return false;
        }

        $q = 'SELECT DISTINCT id, name FROM '.$DB_CENTRAL->table('group_data');
        $list = $DB_CENTRAL->query_list($q);
        if (!$list){
            $info = H::DIV(['class'=>$this->css.'info'], $this->get_tl('no_group'));
            return $info;
        }

        $output = H::DIV(['class'=>$this->css.'widget module_widget', 'id'=>self::module_name.'_widget'.$this->dom_id]);

            $title = H::DIV(['class'=>$this->css.'widget_title widget_title'], $this->get_tl('ttl_widget'));

        $output->add_child( $title );

            $actions = H::DIV(['class'=>$this->css.'widget_actions widget_actions']);
                $btn_add = H::BUTTON(['class'=>$this->css.'btn_add content button_new', 'onclick'=>$this->inst_js.'.add(event);'], $this->get_tl('tlc_add'));
            $actions->add_child( $btn_add );

        $output->add_child($actions);
                
            $q = 'SELECT id, id_group_data FROM '.$DB->table('group_content').' WHERE field_identifier = ? AND id_item = ?';
            $selected_groups = $DB->prepared_query_list($q, 'si', [$this->field_identifier, $this->field_id]);
            if ($selected_groups) {
                foreach ($selected_groups as $line) {
                    $output->add_child( $this->widget_line($line, $list) );
                }
            }

        $settings = [
            'widget_id'=>$this->widget_id
        ];
        $js = H::script('Group_a.create_instance("'.$this->dom_id.'", '.json_encode($settings).');', ['autoremove'=>true]);
        $output->add_child( $js );
        
        return $output;
    }
    public function widget_line($line, $grp_list = false) {
        $div = H::DIV(['class'=>$this->css.'line content widget_line']);
            $del = H::button_icon('trash-2', ['class'=>$this->css.'btn_del button_delete content', 'onclick'=>$this->inst_js.'.delete(event, '.$line['id'].');']);
            $opts_data = ['first_empty'=>['name'=>$this->get_tl('select_group_content'), 'id'=>0], 'label_key'=>'name', 'value_key'=>'id', 'options'=>$grp_list];
            $select = H::select(['name'=>$this->ifld_content_id_group_data.'['.$line['id'].']', 'class'=>$this->css.'select content', 'onchange'=>$this->inst_js.'.save(event, '.$line['id'].');'], $opts_data, $line['id_group_data']);
        $div->add_child( [$del,$select] );

        return $div;
    }
    public function new_content($post) {
        global $DB,$DB_CENTRAL;

        if (!isset($post[$this->ifld_content_field_identifier]) || !$post[$this->ifld_content_field_identifier]){
            Utils::error_log('missing field_identifier for group association');
            Utils::error_log($post);
            return false;
        }
        if (!isset($post[$this->ifld_content_id_item]) || !$post[$this->ifld_content_id_item]){
            Utils::error_log('missing id_item for group association');
            Utils::error_log($post);
            return false;
        }
        
        $q = 'INSERT INTO '.$DB->table('group_content').' SET field_identifier=?, id_item=?';
        $DB->prepared_query($q, 'si', [$post[$this->ifld_content_field_identifier], $post[$this->ifld_content_id_item]]);
        $id = $DB->last_insert_id();

        $q = 'SELECT DISTINCT id, name FROM '.$DB_CENTRAL->table('group_data');
        $list = $DB_CENTRAL->query_list($q);
        
        return $this->widget_line(['id'=>$id, 'id_group_data'=>0], $list);

    }
    public function save_content($post) {
        global $DB;

        if (!isset($post[$this->ifld_content_id]) || !$post[$this->ifld_content_id]){
            Utils::error_log('missing content id for saving group association');
            Utils::error_log($post);
            return false;
        }
        
        $q = 'UPDATE '.$DB->table('group_content').' SET id_group_data = ? WHERE id = ?';
        $DB->prepared_query($q, 'ii', [$post[$this->ifld_content_id_group_data], $post[$this->ifld_content_id]]);
        
        return $this->get_tl('content_saved');
    }
    public function delete_content($post, $from_master = false) {
        global $DB;

        if (!$from_master) { // delete one
            
            if (!isset($post[$this->ifld_content_id]) || !$post[$this->ifld_content_id]){
                Utils::error_log('missing content id for deleting group association');
                Utils::error_log($post);
                return false;
            }
    
            $q = 'DELETE FROM '.$DB->table('group_content').' WHERE id = ?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_content_id]]);

        } else { // delete all
            
            $q = 'DELETE FROM '.$DB->table('group_content').' WHERE id_group_data = ?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);


        }
        

        return true;
    }
}