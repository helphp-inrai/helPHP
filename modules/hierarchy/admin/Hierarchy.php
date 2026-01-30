<?php
/*
 * COPYRIGHT M666 moi@myke666.fr 40600 BISCARROSSE FRANCE 2009-2023, steuz steineremile@duck.com
 * ALL RIGHTS RESERVED
 * TOUS DROITS RESERVES
 * THIS CODE CAN'T BE DUPLICATED OR MODIFY WITHOUT M666 moi@myke666.fr AGREEMENT
 * CE CODE NE PEUT PAS ETRE DUPLIQUE OU MODIFIE SANS L'ACCORD D'M666 moi@myke666.fr
 */
namespace helPHP\modules\hierarchy\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\User;
use helPHP\libs\Media;
use helPHP\modules\group\admin\Group;
use helPHP\modules\media\admin\Media as Media_ui;

class Hierarchy extends HelPHP_module {
    const module_name = 'hierarchy';

    private $other_module = false;
    private $first_level = 0;

    protected $process = false;
    protected $media = false;

    protected $list_items_id = [];

    // To display the admin menu
    private $ACTION_DISPLAY_MENU = self::module_name.'_display_menu';

    // Root structure
    private $ACTION_NEW_ROOT = self::module_name.'_new_root';
    private $ACTION_EDIT_ROOT = self::module_name.'_edit_root';
    private $ACTION_SAVE_ROOT = self::module_name.'_save_root';
    private $ACTION_DELETE_ROOT = self::module_name.'_delete_root';
    private $ACTION_DELETE_STRUCTURE = self::module_name.'_delete_structure';

    // Item (link)
    private $ACTION_EDIT_ITEM = self::module_name.'_edit_item';
    private $ACTION_SAVE_ITEM = self::module_name.'_save_item';
    private $ACTION_DUPLICATE_ITEM = self::module_name.'_duplicate_item';

    // update order in hierarchy
    private $ACTION_UPDATE_STRUCTURE = self::module_name.'_update_structure';

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);
        
        global $CONFIG;
        if (isset($CONFIG::MODULES_LIST['media'])) {
            // include('media/admin/media_admin_class.php');
            $this->media = true;
        }
    }

    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_structure_id] = $post['id'];
        }

        $master_output = H::group('hierarchy_display');
        switch ($post[$this->input_action_identifier]) {

            // display a menu in the admin
            case $this->ACTION_DISPLAY_MENU:
                $master_output->add_child($this->show_menu($post));
            break;

            // -------------------------------------------------------------------------------------------
            // root actions
            case $this->ACTION_NEW_ROOT:
                $post[$this->ifld_structure_id]=0;
                $this->reset_fields($post, 'hierarchy_structure');
                $master_output->add_child( $this->select_root($post) );
                $master_output->add_child( $this->data_edit_root($post) );
            break;
            case $this->ACTION_EDIT_ROOT:
                $this->prepare_fields($post, 'hierarchy_structure');
                $master_output->add_child( $this->select_root($post) );
                $master_output->add_child( $this->data_edit_root($post) );
            break;
            case $this->ACTION_SAVE_ROOT:
                $this->check_posted_data($post,'hierarchy_structure');
                $this->data_save_root($post);
                $master_output->add_child( $this->select_root($post) );
                $master_output->add_child( $this->data_edit_root($post) );
            break;
            case $this->ACTION_DELETE_STRUCTURE:
            case $this->ACTION_DELETE_ROOT:
                $this->check_posted_data($post,'hierarchy_structure');
                $this->data_delete_root($post);
                if (!isset($post['fromjs'])){
                    $post[$this->ifld_structure_id]=0;
                    $this->reset_fields($post, 'hierarchy_structure');
                    $master_output->add_child( $this->select_root($post) );
                }
            break;

            // -------------------------------------------------------------------------------------------
            // Item actions, displayed in a modal
            case $this->ACTION_EDIT_ITEM:
                if (isset($post[$this->ifld_item_id]) && $post[$this->ifld_item_id] > 0){
                    $module = $post[$this->ifld_item_module];
                    $this->prepare_fields($post, 'hierarchy_item');
                    $post[$this->ifld_item_module] = $module;
                } else {
                    $this->check_posted_data($post, 'hierarchy_item', ['module']);
                    $this->reset_fields($post, 'hierarchy_item', ['id','params','target','image']);
                }
                if (isset($post[$this->ifld_structure_id]) && $post[$this->ifld_structure_id] > 0){
                    $this->prepare_fields($post, 'hierarchy_structure');
                } else {
                    $this->check_posted_data($post,'hierarchy_structure',['id_parent']);
                    $this->reset_fields($post, 'hierarchy_structure', ['id','active']);
                }

                $this->get_other_module();
                $master_output->add_child( $this->data_edit_item($post) );
            break;
            case $this->ACTION_SAVE_ITEM:
                $this->check_posted_data($post,'hierarchy_item');
                $this->check_posted_data($post,'hierarchy_structure');
                $this->get_other_module();
                $master_output->add_child( $this->data_save_item($post) );
            break;
            case $this->ACTION_DUPLICATE_ITEM:
                $this->check_posted_data($post,'hierarchy_item');
                $this->check_posted_data($post,'hierarchy_structure');
                $master_output->add_child( $this->data_duplicate_item($post) );
                $master_output->add_child( $this->data_edit_item($post) );
            break;

            case $this->ACTION_UPDATE_STRUCTURE:
                $master_output->add_child( $this->data_update_structure($post) );
            break;

            default:
                $master_output->add_child( $this->select_root($post) );
            break;


        }
        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    //----------------------------------------------------------------------------------------------
    // ----------------------------------------------------------------------------------------------

    /**
     * Parse modules options from config
     */
    public function get_other_module() {
        global $CONFIG;
        $this->other_module = array();
        foreach($CONFIG::MODULES_LIST as $module_name => $module_data){
            if ($module_data['hierarchy']){
                array_push($this->other_module, ['index'=>$module_name, 'name'=>$module_name, 'param'=>$module_data['public_param']]);
            }
        }
    }

    // ----------------------------------------------------------------------------------------------
    // ----------------------------------------------------------------------------------------------

    // Root functions
    public function select_root(&$post) {
        global $DB,$CONFIG;

        // retrieves root items
        $q = 'SELECT * FROM '.$this->bddt_structure.' WHERE id_parent=0';
        $list = $DB->query_list($q);

        // when not in devmode remove the admin hierarchy
        if ($list && !$CONFIG::DEVMODE) {
            foreach ($list as $key=>$line) {
                if (strstr($line['name'], 'admin')) {
                    unset($list[$key]);
                }
            }
        }

        $output = H::group('select_root');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
            if (isset($post[$this->ifld_structure_id]) && $post[$this->ifld_structure_id] != 0) {
                $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_structure_id])));
            }

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));
                
                if (isset($post[$this->posted_container_name])) $form->add_child(H::input_hidden(['name'=>$this->posted_container_name,'value'=>$post[$this->posted_container_name],'data-alwaysposted'=>1]));
                $button_new=H::submit_button_single(array('class'=>$this->css.'button_new button_new','name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW_ROOT, 'title'=>$this->get_tl('tlc_new')), $this->get_tl('tlc_new'));

                $selected_id = isset($post[$this->ifld_structure_id]) ? $post[$this->ifld_structure_id] : 0;
                $opts_data = array('first_empty'=>true , 'value_key'=>'id' , 'label_key'=>'name' , 'options'=>$list);
                $select = H::select(array('id'=>$this->css.'select_root'.$this->dom_id, 'name'=>$this->ifld_structure_id, 'label'=>$this->get_tl('tlc_select')), $opts_data, $selected_id, $this->input_action_identifier, $this->ACTION_EDIT_ROOT);

            $form->add_child([$button_new,$select->label_tag(),$select]);

        $output->add_child([$title,$form]);

        return $output;
    }

    public function data_edit_root(&$post) {

        $output = H::group('edit_root');

        // disable edition of the root element for the admin menu
        $preserved_root = ['menu_admin'];
        if (!in_array($post[$this->ifld_structure_name], $preserved_root)) {
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_edit form_edit']);
                
                if (isset($post[$this->posted_container_name])) $form->add_child(H::input_hidden(['name'=>$this->posted_container_name,'value'=>$post[$this->posted_container_name],'data-alwaysposted'=>1]));
                //nous sauvons l'id de l'objet
                $form->add_child(H::input_hidden(['name'=>$this->ifld_structure_id, 'value'=>$post[$this->ifld_structure_id], 'data-alwaysposted'=>1]));

                $name = H::input_text(['name'=>$this->ifld_structure_name,'id'=>$this->ifld_data_name, 'label'=>$this->get_tl('root_name'), 'value'=>$post[$this->ifld_structure_name], 'class'=>'inp_short_text']);

                $checked = ($post[$this->ifld_structure_create_result] == 1) ? true : false;
                $create_result = H::input_checkbox(['name'=>$this->ifld_structure_create_result, 'label'=>$this->get_tl('result'), 'value'=>1, 'class'=>'inp_check', 'checked'=>$checked]);

            $form->add_child([$name->label_tag(),$name,$create_result->label_tag(),$create_result]);

                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(array('name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_admin_btn_save' , 'value'=>$this->ACTION_SAVE_ROOT, 'class'=>$this->css.'btn_save button_save', 'title'=>$this->get_tl('tlc_save') ) , $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_structure_id] > 0) {
                    $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier , 'id'=>$this->module_name.'_admin_btn_del', 'value'=>$this->ACTION_DELETE_ROOT, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')), $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
                // $btn_delete = H::submit_button(array('class'=>$this->module_name.'_admin_btn_del', 'name'=>$this->input_action_identifier , 'id'=>$this->module_name.'_admin_btn_save', 'value'=>$this->ACTION_DELETE_ROOT, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')),  'X');
                // $btn_save = H::submit_button(array('class'=>$this->module_name.'_admin_btn_save','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_admin_btn_save' , 'value'=>$this->ACTION_SAVE_ROOT, 'class'=>$this->css.'btn_save', 'title'=>$this->get_tl('tlc_save') ) , $this->get_tl('tlc_save'));

            $form->add_child($block_btns);

            $output->add_child($form);
        }

        if ($post[$this->ifld_structure_id] > 0){
            $sub_container = H::DIV(['class'=>$this->css.'sub_container']);

                $info = H::DIV(['class'=>$this->css.'info_tree'], $this->get_tl('info_tree'));

                $modal_param = ['dom_id'=>$this->dom_id,$this->input_action_identifier=>$this->ACTION_EDIT_ITEM,$this->ifld_structure_id_parent=>$post[$this->ifld_structure_id]];
                $btn_add_link = H::BUTTON(['onclick'=>'H_ui.open_popup_modal(event,"'.self::module_name.'",'.json_encode($modal_param).');','class'=>$this->css.'btn_add_item button_new', 'title'=>$this->get_tl('add_structure_element')], $this->get_tl('add_structure_element'));

                $hierarchie = $this->hierarchy_recurse_tree($post[$this->ifld_structure_id]);
                $tree = H::UL(['class'=>$this->css.'tree_parent', 'id'=>$this->dti_tree_parent, 'data-id'=>$post[$this->ifld_structure_id]]);
                // $tree = H::UL(['class'=>$this->css.'tree_parent', 'id'=>$this->css.'tree_parent'.$this->dom_id, 'data-id'=>$post[$this->ifld_structure_id]]);
                $tree->add_child( $this->display_tree($hierarchie) );

            $sub_container->add_child([$info,$btn_add_link,$tree]);

            $output->add_child($sub_container);
                
                $js = 'helphp_timeout(\'Hierarchy_a.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($this->list_items_id)).');\');';
                $script = H::script($js);
            
            $output->add_child($script);
        }

        return $output;
    }
    public function data_save_root(&$post) {
        global $DB;
        
        $post[$this->ifld_structure_name] = str_replace(' ', '_', $post[$this->ifld_structure_name]);
        
        // if an other root exist with the same name, can't save it
        $q = 'SELECT id FROM '.$this->bddt_structure.' WHERE name=?';
        $exist = $DB->prepared_query_value($q,'s',[$post[$this->ifld_structure_name]]);
        if ($exist && $exist != $post[$this->ifld_structure_id]){
            $this->add_error('root_exist');
            return;
        }

        if (isset($post[$this->ifld_structure_id]) && $post[$this->ifld_structure_id] > 0) {
            $q = 'UPDATE '.$this->bddt_structure.' SET name=?, create_result=? WHERE id=?';
            $DB->prepared_query($q, 'sii', [$post[$this->ifld_structure_name], $post[$this->ifld_structure_create_result], $post[$this->ifld_structure_id]]);
        } else {
            $q = 'INSERT INTO '.$this->bddt_structure.' SET name=?, create_result=?';
            $DB->prepared_query($q, 'si', [$post[$this->ifld_structure_name], $post[$this->ifld_structure_create_result]]);
            $post[$this->ifld_structure_id] = $DB->last_insert_id();
        }
    }
    public function data_delete_root(&$post, $id=0) {
        global $DB;

        $id_to_del = ($id > 0) ? $id : $post[$this->ifld_structure_id];
        if (isset($post[$this->ifld_structure_id]) && $post[$this->ifld_structure_id] > 0) {
            // retrieve all the child and call the delete function on them before deleting himself
            $q = 'SELECT DISTINCT id FROM '.$this->bddt_structure.' WHERE id_parent=?';
            $childs = $DB->prepared_query_list($q, 'i', [ $id_to_del ]);
            if ($childs) {
                foreach ($childs as $i) {
                    $this->data_delete_root($post,$i);
                }
            }

            // delete the item and the association linked to this structure
            $q = 'SELECT id_item FROM '.$this->bddt_modular_association.' WHERE id_structure=?';
            $id_item = $DB->prepared_query_value($q, 'i', [ $id_to_del ]);
            if ($id_item){
                Language::delete_short_translation_value($this->build_field_name('item', 'name'), $id_item);
                $q = 'DELETE FROM '.$this->bddt_item.' WHERE id='.$id_item;
                $DB->query($q);
            }
            $q = 'DELETE FROM '.$this->bddt_modular_association.' WHERE id_structure=?';
            $DB->prepared_query($q, 'i', [ $id_to_del ]);

            $q = 'DELETE FROM '.$this->bddt_structure.' WHERE id=?';
            $DB->prepared_query_value($q, 'i', [ $id_to_del ]);
        }
    }

    // Item functions
    public function data_edit_item(&$post) {
        global $CONFIG,$DB;

        $output = H::group('form_item');

            $title = H::DIV(['class'=>$this->css.'editor_title_item module_title'], $this->get_tl('title_item'));

        $output->add_child( $title );

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'popup_modal_content', 'class'=>$this->css.'form_item_editor form_edit', 'dom_id'=>$this->dom_id]);

            // hidden fields
            $id_parent = H::input_hidden(['name'=>$this->ifld_structure_id_parent, 'value'=>$post[$this->ifld_structure_id_parent], 'data-alwaysposted'=>1]);
            $id_structure = H::input_hidden(['name'=>$this->ifld_structure_id, 'value'=>$post[$this->ifld_structure_id], 'data-alwaysposted'=>1]);
            $id_item = H::input_hidden(['name'=>$this->ifld_item_id, 'value'=>$post[$this->ifld_item_id], 'data-alwaysposted'=>1]);
        
        $form->add_child([$id_parent,$id_structure,$id_item]);

            // select what the link will redirect to, a module, a custom link, a page, a product...
            $list = [['index'=>'module','name'=>'module'],['index'=>'custom' , 'name'=>'other']];
            if ($this->other_module) {
                $list = array_merge($list, $this->other_module);
            }
            $options_data = array('value_key'=>'index' , 'label_key'=>'name', 'options'=>$list, 'exclude_attributes'=>['table','fields']);
            $selected = ($post[$this->ifld_item_module] != '') ? $post[$this->ifld_item_module] : '';
            $select = H::select(array('name'=>$this->ifld_item_module, 'label'=>$this->get_tl('select_module'), 'class'=>$this->css.'select', 'data-alwaysposted'=>1), $options_data, $selected, $this->input_action_identifier, $this->ACTION_EDIT_ITEM);
        
        $form->add_child([$select->label_tag(), $select]);
        
        $params = ($post[$this->ifld_item_params] != '') ? json_decode(html_entity_decode($post[$this->ifld_item_params]), true) : [];

        // depending of the first choice, load sub part of the form
        switch($post[$this->ifld_item_module]){
            case 'custom':
                $form->add_child($this->form_item_custom($post, $params));
            break;
            case 'module':
            case '':
                $form->add_child($this->form_item_module($post, $params));
            break;
            default:
                $form->add_child($this->form_item_other_module($post, $params));
            break;
        }

            $post[$this->ifld_structure_active] = ($post[$this->ifld_item_id] == 0) ? 1 : $post[$this->ifld_structure_active];
            $active = H::input_checkbox(['name'=>$this->ifld_structure_active , 'value'=>1, 'label'=>$this->get_tl('display_item'), 'checked'=>$post[$this->ifld_structure_active]]);
        
        $form->add_child([$active->label_tag(),$active]);

            // checkbox to add an image
            $checked = ($post[$this->ifld_item_id] > 0 && Media::has_media($this->ifld_item_image, $post[$this->ifld_item_id])) ? 1 : 0;
            // or an icon
            $checked = ($checked || (isset($params['icon']) && $params['icon'])) ? 1 : 0;
            $check_img = H::input_checkbox(['label'=>$this->get_tl('check_img'), 'name'=>'with_images', 'checked'=>$checked, 'onchange'=>$this->inst_js.'.toggle_image(event);']);
            // field to choose the image
            $div_img = H::DIV(['class'=>$this->css.'container_imgs']);
            if (!$checked) $div_img->add_class('hidden');
            if ($this->media) {
                $this->process = [];
                $this->process['process'][0]=['type'=>'image_resize', 'max_width'=>200, 'max_height'=>200];
                $this->process['process'][1]=['type'=>'image_to_file', 'quality'=>60];

                $module_media = Media_ui::display('uploader', ['accept'=>'image/*', 'on_delete'=>$this->inst_js.'.on_delete_media'], $this->ifld_item_image, $post[$this->ifld_item_id], $this->process);
                
                $div_img->add_child($module_media);

                $post['item_icon'] = (isset($params['icon']) && $params['icon']) ? $params['icon'] : false;
                $icon = H::input_text(['name'=>'item_icon', 'value'=>$post['item_icon'], 'class'=>$this->css.'item_icon', 'label'=>$this->get_tl('item_icon')]);
                $div_img->add_child( [ $icon->label_tag(), $icon] );
            }
        
        $form->add_child([$check_img->label_tag(), $check_img, $div_img]);

            // target
            if ($post[$this->ifld_item_target] == 'lemain' && $post[$this->ifld_item_id] == 0) {
                $temp = ['id_parent'=>$post[$this->ifld_structure_id_parent]];
                while ($temp['id_parent'] != 0) {
                    $q = 'SELECT id_parent, create_result, name FROM '.$this->bddt_structure.' WHERE id='.$temp['id_parent'];
                    $temp = $DB->query_line($q);
                }
                if ($temp['create_result']) {
                    $post[$this->ifld_item_target] = $temp['name'].'_result';
                }
            }
            $inp_targ = H::input_text(['name'=>$this->ifld_item_target, 'label'=>$this->get_tl('inp_target'), 'value'=>$post[$this->ifld_item_target]]);

        $form->add_child([$inp_targ->label_tag(), $inp_targ]);

            // type
            $opt = array(0=>['name'=>$this->get_tl('open_ong'), 'value'=>1], 1=>['name'=>$this->get_tl('open_fen'), 'value'=>2]);
            $options_data = array('first_empty'=>true, 'value_key'=>'value', 'label_key'=>'name', 'options'=>$opt);
            $selected = isset($params['open_type']) ? $params['open_type'] : 0;
            $select = H::select(['name'=>'open_type', 'label'=>$this->get_tl('selec_ouv'), 'class'=>$this->css.'select'], $options_data, $selected);
            
        $form->add_child([$select->label_tag(), $select]);
        
        // $btns = H::DIV(['class'=>$this->css.''])
            $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_ITEM, 'class'=>$this->css.'item_btn_save button_save'], $this->get_tl('tlc_save'));
        
        $form->add_child($btn_save);

        $output->add_child($form);

        if ($post[$this->ifld_item_id] > 0){
            $output->add_child( Group::widget([], $this->ifld_item_id, $post[$this->ifld_item_id]) );
        }

        return $output;
    }
    public function form_item_custom(&$post, $params) {
        global $LANG;

        $url = isset($params['url']) ? $params['url'] : '';
        $lien = H::input_text(['name'=>'url', 'label'=>$this->get_tl('lien'), 'value'=>$url]);

        $LANG->load_translation_data($post, self::module_name, 'item', $post[$this->ifld_item_id]);
        $name = $this->translate_block($post, [$this->ifld_item_name.'['.$post[$this->ifld_item_id].']'], 's');

        return [$lien->label_tag(), $lien, $name];
    }
    public function form_item_module($post, $params) {
        global $CONFIG;

        $lst = $CONFIG::MODULES_LIST;
        $opts = [];
        foreach($lst as $module_name => $uselessData){
            $label = $this->get_translated_text_from_other_module($module_name, true, 'module_name');
            if (str_contains($label, '{')) $label = $this->get_translated_text_from_other_module($module_name, false, 'module_name');
            if (!str_contains($label, '{')){
                array_push($opts,['value'=>$module_name,'label'=>$label]);
            }
            //  else {
            //     Utils::error_log('hierachy cant add '.$module_name.' to the list, missing his module_name translation');
            // }
        }
        usort($opts, fn($a, $b) => $a['label'] <=> $b['label']);

        $options_data = array('first_empty'=>true, 'value_key'=>'value', 'label_key'=>'label', 'options'=>$opts);
        $selected = isset($params['module']) ? $params['module'] : 0;
        $select = H::select(array('name'=>'module', 'label'=>$this->get_tl('select_params'), 'class'=>'hierarchie_admin_select', 'data-alwaysposted'=>1), $options_data, $selected);
        
        $val = isset($params['params']) ? $params['params'] : '';
        $parameters = H::input_text(['name'=>'params', 'value'=>$val, 'label'=>$this->get_tl('module_params'), 'class'=>'hierarchie_admin_input']);

        global $LANG;
        $data = [];
        $LANG->load_translation_data($data, self::module_name, 'item', $post[$this->ifld_item_id]);
        $name = $this->translate_block($data, [$this->ifld_item_name.'['.$post[$this->ifld_item_id].']'], 's');

        return [$select->label_tag(), $select, $parameters->label_tag(), $parameters, $name];
    }
    public function form_item_other_module($post, $params) {
        global $DB, $LANG;

        $module = false;
        foreach ($this->other_module as $elem) {
            if ($elem['index'] == $post[$this->ifld_item_module]) {
                $module = $elem;
            }
        }
        
        if ($module) {
            $query_field = 'id as id';
            $query_field.= ($module['param'] != 'id') ? ', '.$module['param'] : '';
            $q = 'SELECT '.$query_field.' FROM '.$this->build_module_table_name($module['name'], 'data');
            $list = $DB->query_list($q);
            
            foreach ($list as $key => $line) {
                if (!is_array($line)){
                    $id = $line;
                    $line = [];
                    $line['id'] = $id;
                }
                $line['name'] = Language::get_name($module['name'].'_data', $line['id']);
                // $line['name'] = Language::load_short_translation_value($this->build_module_field_name($module['name'], 'data', 'name'), $line['id']);
                // if ($line['name'] == '') $line['name'] = Language::load_short_translation_value($this->build_module_field_name($module['name'], 'data', 'title'), $line['id']);
                $line['params'] = $line[$module['param']];
                $list[$key] = $line;
            }
        }

        $options_data = array('first_empty'=>true , 'value_key'=>'params' , 'label_key'=>'name' , 'indentation'=>$this->indentation_key, 'options'=>$list , 'exclude_attributes'=>['id','sort_order'] );
        $selected = isset($params['params']) ? $params['params'] : 0;
        $select = H::select(array('name'=>'params', 'label'=>$this->get_tl('select_params'), 'class'=>'hierarchie_admin_select', 'data-alwaysposted'=>1), $options_data, $selected);

        return [$select->label_tag(), $select];
    }
    public function data_save_item($post) {
        global $DB;
    
        $params = [];
        if ($post[$this->ifld_item_module] == 'module'){
            $params['module'] = $post['module'];
            $params['params'] = $post['params'];
        } else if ($post[$this->ifld_item_module] == 'custom'){
            $params['url'] = $post['url'];
        } else {
            $params['params'] = $post['params'];
        }
        $params['open_type'] = $post['open_type'];
        if ($post['item_icon'] != '') $params['icon'] = $post['item_icon'];

        $types = 'sss';
        $vars = [$post[$this->ifld_item_module] , $post[$this->ifld_item_target] , json_encode($params)];
        if ($post[$this->ifld_item_id] == 0) {
            $q = 'INSERT INTO '.$this->bddt_item.' SET module=? , target=? , params=?';
        } else {
            $q = 'UPDATE '.$this->bddt_item.' SET module=? , target=? , params=? WHERE id=?';
            $types .= 'i';
            array_push($vars, $post[$this->ifld_item_id]);
        }
        $DB->prepared_query($q, $types, $vars);


        if ($post[$this->ifld_item_id] == 0) {
            $post[$this->ifld_item_id] = $DB->last_insert_id;

            if ($post[$this->ifld_item_id]) {
                // creation of the structure associated with the item and parentage
                $q = 'SELECT DISTINCT MAX(`sort_order`)+1 FROM '.$this->bddt_structure.' WHERE id_parent=?';
                $order = $DB->prepared_query_value($q, 'i', [ $post[$this->ifld_structure_id_parent] ]);
                if (!$order) {
                    $order = 0;
                }

                $q = 'INSERT INTO '.$this->bddt_structure.' SET id_parent=? , active=? , `sort_order`=?';
                $DB->prepared_query($q, 'iii', [ $post[$this->ifld_structure_id_parent] , $post[$this->ifld_structure_active] , $order ]);
                $post[$this->ifld_structure_id] = $DB->last_insert_id;

                // association of the structure with the module element
                $q = 'INSERT INTO '.$this->bddt_modular_association.' SET id_structure=? , id_item=?';
                $DB->prepared_query($q, 'ii', [ $post[$this->ifld_structure_id] , $post[$this->ifld_item_id]]);
            } else {
                echo 'ERROR !'.$q;
                return null;
            }
        } else {
            $q = 'UPDATE '.$this->bddt_structure.' SET active=? WHERE id=?';
            $DB->prepared_query($q, 'ii', [ ($post[$this->ifld_structure_active]>0 ? 1 : 0) ,$post[$this->ifld_structure_id] ]);
        }

        // if ($post[$this->ifld_item_module] == 'custom'){
        if (isset($post[Language::short_translation_prefix])) Language::save_translation_data($post, $post[$this->ifld_item_id]);
        else Language::delete_short_translation_value($this->ifld_item_name, $post[$this->ifld_item_id]);

        if (isset($post['with_images']) && $this->media) {
            global $MEDIA;
            $MEDIA->process_media($post, $post[$this->ifld_item_id]);
        }

        $item_data = [];
        $item_data['id'] = $post[$this->ifld_structure_id];
        $item_data['active'] = $post[$this->ifld_structure_active];
        $item_data['image'] = Media::has_media($this->ifld_item_image, $post[$this->ifld_item_id]);
        if (isset($params['icon'])) $item_data['icon'] = $params['icon'];
        $item_data['name'] = $this->get_item_name($post[$this->ifld_item_id], $post[$this->ifld_item_module], $params);
        $item_data['id_item'] = $post[$this->ifld_item_id];
        $item_data['module_item'] = $post[$this->ifld_item_module];
        $item_data['childs'] = $this->hierarchy_recurse_tree($post[$this->ifld_structure_id]);
        $item = $this->display_item($item_data);

        $script = H::script($this->inst_js.'.update_item('.$post[$this->ifld_structure_id].', "'.addslashes($item->full_html()).'");');
        return $script;
    }
    public function data_duplicate_item(&$post){
        global $DB;

        $q = 'INSERT INTO '.$this->bddt_item.' (module, params, target) SELECT cp.module, cp.params, cp.target FROM '.$this->bddt_item.' cp';
        $q.=' WHERE cp.id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_item_id]]);
        $copied_id_item = $post[$this->ifld_item_id];
        $post[$this->ifld_item_id] = $DB->last_insert_id();
        
        $q = $q = 'INSERT INTO '.$this->bddt_structure.' (id_parent, sort_order, order_tree, active, active_tree, name, create_result)';
        $q.=' SELECT cp.id_parent, cp.sort_order, cp.order_tree, cp.active, cp.active_tree, CONCAT(cp.name, " copy"), cp.create_result FROM '.$this->bddt_structure.' cp';
        $q.=' WHERE cp.id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_structure_id]]);
        $copied_id_structure = $post[$this->ifld_structure_id];
        $post[$this->ifld_structure_id] = $DB->last_insert_id();

        $q = 'INSERT INTO '.$this->bddt_modular_association.' SET id_item='.$post[$this->ifld_item_id].', id_structure='.$post[$this->ifld_structure_id];
        $DB->query($q);

        $q = 'INSERT INTO '.$DB->table('languages_short').' (id_data, id_item, field_identifier, value) SELECT id_data, ?, field_identifier, CONCAT(value, " copy")';
        $q.=' FROM '.$DB->table('languages_short').' WHERE id_item=? AND field_identifier LIKE "hierarchy_item-name"';
        $res = $DB->prepared_query($q, 'ii', [$post[$this->ifld_item_id], $copied_id_item]);

        $this->prepare_fields($post, 'hierarchy_item');
        $this->prepare_fields($post, 'hierarchy_structure');

        $item_data = [];
        $item_data['id'] = $post[$this->ifld_structure_id];
        $item_data['active'] = $post[$this->ifld_structure_active];
        $item_data['image'] = Media::has_media($this->ifld_item_image, $post[$this->ifld_item_id]);
        $params = ($post[$this->ifld_item_params] != '') ? json_decode(html_entity_decode($post[$this->ifld_item_params]), true) : [];
        if (isset($params['icon'])) $item_data['icon'] = $params['icon'];
        $item_data['name'] = $this->get_item_name($post[$this->ifld_item_id], $post[$this->ifld_item_module], $params);
        $item_data['id_item'] = $post[$this->ifld_item_id];
        $item_data['module_item'] = $post[$this->ifld_item_module];
        $item_data['childs'] = $this->hierarchy_recurse_tree($post[$this->ifld_structure_id]);
        $item = $this->display_item($item_data);
        
        // add display
        $js = 'let after = document.getElementById("hierarchy_item'.$this->dom_id.'_'.$copied_id_structure.'");H_dom.insert_after("'.addslashes($item->full_html()).'", after);';
        $js.= $this->inst_js.'.add_event_on_items(["hierarchy_item'.$this->dom_id.'_'.$post[$this->ifld_structure_id].'"]);';
        return H::script($js, ['autoremove'=>1]);
    }
    public function hierarchy_recurse_tree($id_parent) {
        global $DB;

        $list = [];

        if (!$id_parent) {
            return $list;
        }

        $q = 'SELECT id, id_parent, `sort_order`, order_tree ,active,active_tree FROM '.$this->bddt_structure.' WHERE id_parent=? ORDER BY `sort_order`';
        $list = $DB->prepared_query_list($q, 'i', [$id_parent]);
        // Utils::error_log($list);
        if (is_array($list)) {
            foreach ($list as $key => $line) {
                $q = 'SELECT item.* FROM '.$this->bddt_item.' item, '.$this->bddt_modular_association.' asso where ';
                $q.=' asso.id_structure=? AND asso.id_item=item.id';
                $item_data = $DB->prepared_query_line($q, 'i', [$line['id']]);
                // Utils::error_log($item_data);
                if ($this->media && Media::has_media($this->ifld_item_image, $item_data['id'])) {
                    $line['image'] = true;
                }
                $line['id_item'] = $item_data['id'];
                $line['module_item'] = $item_data['module'];
                $params = json_decode($item_data['params'], true);
                $line['name'] = $this->get_item_name($item_data['id'], $item_data['module'], $params);
                if (isset($params['icon']) && $params['icon']) $line['icon'] = $params['icon'];
                $line['childs'] = $this->hierarchy_recurse_tree($line['id']);
                $list[$key] = $line;
            }
        }

        return $list;
    }
    public function get_item_name($id,$type,$params) {
        $name = '';
        switch ($type){
            case 'custom':
                global $LANG;
                $name = $LANG->load_short_translation_value($this->ifld_item_name, $id);
            break;
            case 'module':
                global $LANG;
                $name = $LANG->load_short_translation_value($this->ifld_item_name, $id);
                if (!$name) {
                    $module_name = $params['module'];
                    $name = $this->get_translated_text_from_other_module($module_name, true, 'module_name');
                    if (str_contains($name, '{')) $name = $this->get_translated_text_from_other_module($module_name, false, 'module_name');
                }
            break;
            default:
                $name = Language::get_name($type.'_data', $params['params']);
            break;
        }
        return $name;
    }
    public function display_tree($list) {

        $output = [];

        foreach($list as $key => $line) {
            $li = $this->display_item($line);
            array_push($this->list_items_id, $li->id);
            array_push($output, $li);
        }

        return $output;
    }
    public function display_item($data) {
        $params = [];
        $params['id'] = 'hierarchy_item'.$this->dom_id.'_'.$data['id'];
        $params['class'] = $this->css.'tree_elem '.($data['active'] ? '' : 'tree_elem_disabled');
        $params['data-id'] = $data['id'];
        $li = H::LI($params);
            $div = H::DIV();
                $name = H::SPAN(['class'=>$this->css.'tree_elem_name'], $data['name']);
                $image = H::DIV(['class'=>$this->css.'tree_elem_image', 'id'=>self::module_name.'_item_image_'.$data['id_item'].$this->dom_id]);
                if (isset($data['image']) && $data['image']){
                    global $MEDIA;
                    $image->add_child( $MEDIA->get_html($this->ifld_item_image, $data['id_item']) );
                }
                if (isset($data['icon']) && $data['icon'] != ''){
                    $image->add_after( H::icon($data['icon'], ['class'=>$this->css.'tree_elem_icon']) );
                }
                $modal_param = [
                    'dom_id' => $this->dom_id,
                    $this->input_action_identifier => $this->ACTION_EDIT_ITEM,
                    $this->ifld_item_id => $data['id_item'],
                    $this->ifld_structure_id => $data['id'],
                    $this->ifld_item_module => $data['module_item']
                ];
                $edit = H::button_icon('edit-2', ['onclick'=>'H_ui.open_popup_modal(event,"'.self::module_name.'",'.json_encode($modal_param).');','class'=>$this->css.'tree_elem_edit button_edit', 'title'=>$this->get_tl('tlc_edit')]);
                $delete = H::button_icon('trash-2', ['class'=>$this->css.'btn_del item button_delete', 'id'=>$params['id'].'_btn_del', 'data-id'=>$data['id'], 'title'=>$this->get_tl('tlc_delete')]);
                $modal_param[$this->input_action_identifier] = $this->ACTION_DUPLICATE_ITEM;
                $duplicate = H::button_icon('copy', ['onclick'=>'H_ui.open_popup_modal(event,"'.self::module_name.'",'.json_encode($modal_param).');','class'=>$this->css.'tree_elem_duplicate button_duplicate', 'title'=>$this->get_tl('tlc_duplicate')]);
            $div->add_child([$name,$image,$edit,$delete,$duplicate]);
        $li->add_child($div);
            $sub_tree = H::UL(['class'=>$this->css.'subtree_parent', 'id'=>$params['id'].'_subtree', 'data-id'=>$data['id']]);
            if ($data['childs']) $sub_tree->add_child( $this->display_tree($data['childs']) );
        $li->add_child($sub_tree);
        return $li;
    }

    public function data_update_structure($post) {
        global $DB;

        if (isset($post['items']) && is_array($post['items'])) {
            $first = false;
            foreach ($post['items'] as $item) {
                // check existence of parent before adding it
                if (!$first){
                    $first = true;
                    $q = 'SELECT id FROM '.$this->bddt_structure.' WHERE id=?';
                    $exist = $DB->prepared_query_value($q,'i',[$item['id_parent']]);
                    if (!$exist){
                        $this->add_error('parent_does_not_exist');
                        return;
                    }
                }
                $q = 'UPDATE '.$this->bddt_structure.' SET id_parent=? , `sort_order`=? WHERE id=?';
                $DB->prepared_query($q, 'iii', [ $item['id_parent'] , $item['order'] , $item['id'] ]);
            }
        }
    }
    //add an item from its modulename, method made for utils/install_module.php
    public function add_item($module_name = '', $admin = false, $params = '') {
        if ($module_name == ''){
            Utils::error_log('Can\'t add item, no module name provided');
            return;
        }

        global $DB;
        $menu_name = $admin ? 'menu_admin' : 'menu_public';
        // get the parent structure data (menu public or menu admin)
        $q = 'SELECT id, create_result FROM '.$this->bddt_structure.' WHERE name="'.$menu_name.'"';
        $parent_data = $DB->query_line($q);
        if (!$parent_data){
            Utils::error_log('Can\'t find parent structure, query:');
            Utils::error_log($q);
            return;
        }

        // item
        $target = ($parent_data['create_result'] == 1) ? $menu_name.'_result' : 'lemain';
        $types = 'sss';
        $params = ['module'=>$module_name,'params'=>$params,'open_type'=>''];
        $vars = ['module', $target, json_encode($params)];

        // before adding item check if it already exist (with same parameter)
        $q = 'SELECT id FROM '.$this->bddt_item.' WHERE module=? AND target=? AND params=?';
        $exist = $DB->prepared_query_line($q, $types, $vars);
        if ($exist) return;

        $q = 'INSERT INTO '.$this->bddt_item.' SET module=? , target=? , params=?';
        $DB->prepared_query($q, $types, $vars);
        $id_item = $DB->last_insert_id;
        // structure
        $q = 'SELECT DISTINCT MAX(`sort_order`)+1 FROM '.$this->bddt_structure.' WHERE id_parent='.$parent_data['id'];
        $order = $DB->query_value($q);
        if (!$order) {
            $order = 0;
        }
        $q = 'INSERT INTO '.$this->bddt_structure.' SET id_parent='.$parent_data['id'].' , active=1 , `sort_order`='.$order;
        $DB->prepared_query($q);
        $id_structure = $DB->last_insert_id;

        // association of the structure with the module element
        $q = 'INSERT INTO '.$this->bddt_modular_association.' SET id_structure='.$id_structure.' , id_item='.$id_item;
        $DB->prepared_query($q);
    }

    //delete an item from its modulename, method made for utils/uninstall module.php
    public function delete_item($module_name='',$admin=false) {
        if ($module_name == ''){
            Utils::error_log('Can\'t delete item, no module name provided');
            return;
        }

        global $DB;
        $menu_name = $admin ? 'menu_admin' : 'menu_public';
        // get the parent structure data (menu public or menu admin)
        $q = 'SELECT id, create_result FROM '.$this->bddt_structure.' WHERE name="'.$menu_name.'"';
        $parent_data = $DB->query_line($q);
        if (!$parent_data){
            Utils::error_log('Can\'t find parent structure, query:');
            Utils::error_log($q);
            return;
        }
        // item
        $target = ($parent_data['create_result'] == 1) ? $menu_name.'_result' : 'lemain';
        $types = 'sss';
        $params = ['module'=>$module_name,'params'=>'','open_type'=>''];
        $vars = ['module', $target, json_encode($params)];
        //get items
        $q = 'SELECT id FROM '.$this->bddt_item.' where module=? and target=? and params=?';
        $items=$DB->prepared_query_list($q, $types, $vars);
        //get id_structure
        foreach($items as $key=>$id_item){
            $q = 'SELECT id_structure from '.$this->bddt_modular_association.' where id_item='.$id_item;
            $structure_assoc=$DB->prepared_query_list($q);
            $ids = implode(",", $structure_assoc);
            $q = 'DELETE from '.$this->bddt_structure.' where id in('.$ids.')';
            $DB->query($q);
            $q = 'DELETE from '.$this->bddt_modular_association.' where id_item='.$id_item ;
            $DB->query($q);
        }
        $q = 'DELETE from '.$this->bddt_item.' where module=? and target=? and params=?';
        $DB->prepared_query($q, $types, $vars);
    }


    // ----------------------------------------------------------------------------------------------
    // ----------------------------------------------------------------------------------------------
    // display of a menu in the admin
    public function show_menu($post) {
        global $DB;

        if (isset($post['id']) && $post['id'] > 0) {
            $q = 'SELECT * FROM '.$this->bddt_structure.' WHERE id=?';
            $root = $DB->prepared_query_line($q, 'i', [$post['id']]);

            $this->css = str_replace(' ', '_', $root['name']);

            $this->dom_container .= '_'.$this->css;

            $hierarchie = $this->make_list($post['id']);

            $this->first_level = 0;

            $output = H::group('hierarchie');

            $output->add_child($this->show_list($hierarchie));

            if ($root['create_result']) {
                $output->add_child(H::DIV(['id'=>$this->css.'_result', 'class'=>$this->css.'_result']));
            }

            return $output;
        }
    }

    public function make_list($id) {
        global $DB, $USER;

        $q = 'SELECT * FROM '.$this->bddt_structure.' WHERE id_parent=? AND active=1 ORDER BY `sort_order`';
        $liste = $DB->prepared_query_list($q, 'i', [$id]);

        if (is_array($liste)) {
            $result = [];

            $groups = $USER->allowed_groups();
            $str_groups = implode(',', $groups);
            $table_item = $this->bddt_item;
            $table_asso = $this->bddt_modular_association;
            $table_group = $DB->table('group_content');
            $field_identifier = $this->build_field_name('item', 'id');

            $q_groups = '';
            if (in_array(0, $groups)) {
                if ($str_groups !='') {
                    $q_groups= ' AND ('.$table_group.'.id_group_data IS NULL OR '.$table_group.'.id_group_data IN ('.$str_groups.') )';
                } else {
                    $q_groups= ' AND '.$table_group.'.id_group_data IS NULL';
                }
            } else {
                if ($str_groups != '') {
                    $q_groups= ' AND '.$table_group.'.id_group_data IN ('.$str_groups.')';
                } else {
                    $q_groups= '';
                }
            }
            
            foreach ($liste as $index => $line) {
                $q = 'SELECT DISTINCT '.$table_item.'.module, '.$table_item.'.params, '.$table_item.'.target, '.$table_item.'.id';
                $q.= ' FROM '.$table_item.' ';
                $q.= ' LEFT JOIN '.$table_asso.' ON '.$table_item.'.id='.$table_asso.'.id_item ';
                $q.= ' LEFT JOIN '.$table_group.' ON ('.$table_group.'.id_item = '.$table_item.'.id AND '.$table_group.'.field_identifier="'.$field_identifier.'")';
                $q.= ' WHERE '.$table_asso.'.id_structure=? '.$q_groups;
                $line['item'] = $DB->prepared_query_line($q, 'i', [$line['id']]);
                if (!$line['item']) continue;
                
                if ($this->media && $line['item'] && Media::has_media($this->ifld_item_image, $line['item']['id'])) {
                    $line['item']['image'] = true;
                }
                
                $visible = true;
                if (is_array($line['item'])) {
                    $line['childs'] = $this->make_list($line['id']);

                    $params = json_decode($line['item']['params'], true);

                    $line['name'] = $this->get_item_name($line['item']['id'],$line['item']['module'],$params);
                    
                    //to force display for a super user
                    // if ($USER->user_data['login'] == 'admin' && !$visible) {
                    //     $visible = true;
                    // }
                    if ($visible) {
                        array_push($result, $line);
                    }
                }
            }
        }

        return $result;
    }

    public function make_hash($data) {
        global $LANG;

        $res = '';

        $params = json_decode($data['params'], true);

        if ($data['module'] == 'custom'){
            return $params['url'];
        } else if ($data['module'] == 'module'){
            $module_name = $params['module'];
            $res.= $module_name;
            if ($params['params'] != ''){
                $res.= '|'.str_replace('&', '|', $params['params']);
            }
        } else {
            $res.= $data['module'].'='.$params['params'];
        }

        if ($data['target'] != 'lemain') {
            // language
            $res .= '-'.$LANG->current_language;
            // target
            $res .= '-'.$data['target'];
        }

        return $res;
    }

    public function show_list($liste) {
        global $USER;

        $this->first_level++;

        if ($this->first_level == 1) {
            $ul = H::UL(array('class'=>$this->css, 'id'=>$this->css));
        } else {
            $ul = H::UL();
        }

        if (strstr($this->css, 'compte')) {
            if ($USER->connection_state == User::state_not_logged) {
                $this->display->add_child(H::script('H_history.change_hash(event, "#connection=true");'));
            }
        }

        foreach ($liste as $index=>$line) {
            if (isset($line['dataTree']) && $line['dataTree']) {
                // element of a data tree (parentage)

                $hash = $this->make_hash($line);
                
                $class_a = Utils::filter_string($line['name']);
                if ($hash != '') {
                    
                    $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");', $line['name']));
                } else {
                }
                
                $li = H::LI(null, $a);

                $item = $line;
            } else {
                // normal element

                $item = $line['item'];

                $class_a = Utils::filter_string($line['name']);

                if ($line['name'] == '') {
                    continue;
                }
                
                $name = H::DIV(['class'=>$this->css.'_text_link'], $line['name']);

                $li = H::LI();
                $hash = $this->make_hash($item);
                if (isset($item['image']) && $item['image']) {
                    global $MEDIA;
                    $img = H::DIV(['class'=>$this->css.'_block_img', 'title'=>$line['name']]);
                    $media = $MEDIA->get_html($this->ifld_item_image, $item['id']);
                    $media->add_class($this->css.'_image');
                    $img->add_child($media);
                }
                $params = json_decode($item['params'], true);
                if (isset($params['icon']) && $params['icon'] != ''){
                    $icon = H::icon($params['icon']);
                }

                if (strstr($this->css, 'mon_compte') && $hash == 'disconnect') {
                    $li->add_child(H::A(array('href'=>'#', 'onclick'=>'event.preventDefault(); Connection.Disconnect();'), $name));
                } elseif (isset($line['entete']) && $line['entete'] == 1) {
                    $a = H::A(array('href'=>'?'.$hash, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");', 'class'=>$class_a.' categorie_en_avant'));
                    if (isset($img)) {
                        $a->add_child( $img );
                    }
                    if (isset($icon)) {
                        $a->add_child( $icon );
                    }
                    $a->add_child( $name );
                    $li->add_child($a);
                } else {
                    
                    $type = $params['open_type'];
                    if ($type == 1) {
                        // $url = $params['url'];
                        // $hash = strstr($url, '=');
                        $a = H::A(array('href'=>$hash, 'class'=>$class_a, 'target'=>'_blank'));
                    } elseif ($type == 2) {
                        // $url = $params['url'];
                        // $hash = strstr($url, '=');
                        $a = H::A(array('class'=>$class_a, 'onclick'=>'H_ui.popup("'.$hash.'", "'.$line['name'].'");'));
                    } elseif ($hash != '') {
                        $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");'));
                    } else {
                        $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a, 'onclick'=>'if(event.preventDefault) event.preventDefault(); else event.returnValue = false;'));
                    }
                    if (isset($img)) {
                        $a->add_child($img);
                    }
                    if (isset($icon)) {
                        $a->add_child($icon);
                    }
                    $a->add_child( $name );
                    $li->add_child($a);
                }

                if (count($line['childs']) > 0) {
                    $li->add_child($this->show_list($line['childs']));
                }
            }

            if (explode(',', $item['module'])[0] == 'categorie') {
                $li->set_attribute('class', $this->css.'_categorie');
            }
            $ul->add_child($li);
            if (isset($img)) {
                unset($img);
            }
            if (isset($icon)) {
                unset($icon);
            }
        }

        return $ul;
    }
}