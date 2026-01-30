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

namespace helPHP\modules\category\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;

class Category extends HelPHP_module {

    const module_name = 'category';

    private $ACTION_NEW = self::module_name.'_new';
    private $ACTION_SAVE = self::module_name.'_save';
    private $ACTION_EDIT = self::module_name.'_edit';
    private $ACTION_DELETE = self::module_name.'_delete';

    private $ACTION_ADD_CONTENT = self::module_name.'_add_content';
    private $ACTION_DELETE_CONTENT = self::module_name.'_delete_content';

    protected $list = false;

    protected $params = [
        'series'=>'',
        // 'add'=>true,
    ];

    protected $field_identifier = false;
    protected $field_id = false;
    protected $widget_id = false;

    public function __construct($domContainer = null, $field_identifier = false, $field_id = false, $params = false) {
        $this->prepare_module(self::module_name, true);
        parent::__construct($domContainer);

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
            if (!isset($_SESSION['widget_category'])) $_SESSION['widget_category'] = [];
            if (!isset($_SESSION['widget_category'][$this->widget_id])) $_SESSION['widget_category'][$this->widget_id] = [];

            $_SESSION['widget_category'][$this->widget_id]['params'] = json_encode($this->params);
            $_SESSION['widget_category'][$this->widget_id]['time'] = time();
        }

        $this->clean_session();
    }
    public function clean_session() {
        $list = $_SESSION['widget_category'];
        $currentTime = time();
        if ($list) {
            foreach ($list as $key => $line) {
                if (isset($line['time'])){
                    $difT = intval($currentTime) - intval($line['time']);
                    if ($this->widget_id != $key && $difT > 900) { // 15 min
                        unset($_SESSION['widget_category'][$key]);
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
        }

        if ($this->widget_id != false) {
            if (!isset($_SESSION['widget_category'][$this->widget_id]['params'])){
                Utils::error_log($this->widget_id);
                Utils::error_log($_SESSION['widget_category']);
            }
            $this->params = isset($_SESSION['widget_category'][$this->widget_id]['params']) ? json_decode($_SESSION['widget_category'][$this->widget_id]['params'], true) : false;
        }

        $master_output = H::group('category_display');
        switch ($post[$this->input_action_identifier]) {

            case $this->ACTION_EDIT:
                
                $this->prepare_fields($post, 'category_data');
                global $LANG;
                $LANG->load_translation_data($post, 'category', 'data');

                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->form_edit($post) );

            break;

            case $this->ACTION_NEW:

                $post[$this->ifld_data_id] = 0;
                $this->reset_fields($post, 'category_data');

                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->form_edit($post) );

            break;
            case $this->ACTION_SAVE:

                $this->check_posted_data($post, 'category_data');
                $this->save($post);

                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->form_edit($post) );

            break;
            case $this->ACTION_DELETE:

                $this->check_posted_data($post, 'category_data');
                $this->delete_data($post);

                $post[$this->ifld_structure_id] = 0;
                $this->reset_fields($post, 'category_data');

                $master_output->add_child( $this->form_select($post) );

            break;

            case $this->ACTION_ADD_CONTENT:
                $this->check_posted_data($post, 'category_content', ['id_data']);
                $master_output->add_child( $this->add_content($post) );
            break;
            case $this->ACTION_DELETE_CONTENT:
                $this->check_posted_data($post, 'category_content');
                $master_output->add_child( $this->delete_content($post) );
            break;


            default:
                $this->check_posted_data($post, 'category_data');
                $master_output->add_child( $this->form_select($post) );
            break;
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    public function form_select($post) {

        $output = H::group('select_category');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
            if (isset($post[$this->ifld_data_id]) && $post[$this->ifld_data_id] > 0) {
                $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_data_id])));
            }

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));

                $button_new = H::submit_button_single(array('class'=>$this->css.'button_new button_new', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW, 'title'=>$this->get_tl('tlc_new')), $this->get_tl('tlc_new'));

                $list = $this->data_tree_for_select('data', 'name');

                $opts_data = array('first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'indentation'=>$this->indentation_key, 'options'=>$list);
                $select = H::select(['name'=>$this->ifld_data_id, 'label'=>$this->get_tl('tlc_select')], $opts_data, $post[$this->ifld_data_id], $this->input_action_identifier, $this->ACTION_EDIT);

            $form->add_child([$button_new, $select->label_tag(), $select]);

        $output->add_child([$title,$form]);

        return $output;
    }
    public function form_edit($post) {
        global $DB;

        $parents_data = false;
        $ignore_ids = [$post[$this->ifld_data_id]];
        if ($DB->table_exists($this->bddt_data.self::hierarchy_suffix)) {
            // $post['']
            $data = $this->get_parent_data('data', $post[$this->ifld_data_id]);
            // Utils::error_log($parents_data);
            if (is_array($data)) {
                $post['id_parentage'] = $data['id'];
                $post['id_parent'] = $data['id_parent'];
                $post['name_parent'] = $data['name'];
                $post['sort_order'] = $data['sort_order'];
            } else {
                $post['id_parentage'] = 0;
                $post['id_parent'] = 0;
                $post['name_parent'] = '';
                $post['sort_order'] = 1;
            }
        }


        $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_edit form_edit'));

            $hidden_id = H::input_hidden(array('name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id], 'data-alwaysposted'=>1, 'id'=>self::module_name.'_current_id'.$this->dom_id));
            $hidden_parentage = H::input_hidden(['name'=>'id_parentage', 'value'=>$post['id_parentage'], 'data-alwaysposted'=>1]);
            $hidden_previous_parent = H::input_hidden(['name'=>'previous_id_parent', 'value'=>$post['id_parent'], 'data-alwaysposted'=>1]);

        $form->add_child( [$hidden_id, $hidden_parentage, $hidden_previous_parent] );
        
            $internal_name = H::input_text(['name'=>$this->ifld_data_name, 'value'=>$post[$this->ifld_data_name], 'class'=>$this->css.'inp_text name', 'label'=>$this->get_tl('internal_name')]);
            $name = $this->translate_block($post, [$this->ifld_data_name], 's');
        
        $form->add_child( [$internal_name->label_tag(), $internal_name, $name] );

            // $list = $this->data_tree_for_select('data', 'name', 0, ['remove_fields'=>[$post[$this->ifld_data_id]]]);
            $list = $this->data_tree('data', 'name', 0, true, $ignore_ids);
            $opts_data = ['first_empty'=>1, 'value_key'=>'id', 'label_key'=>'name', 'data'=>$list];
            $parent = H::input_precomplete(['name'=>'id_parent', 'dom_id'=>$this->dom_id, 'label'=>$this->get_tl('parent'), 'value'=>$post['id_parent'], 'value_label'=>$post['name_parent']], $opts_data);

        $form->add_child( [$parent->label_tag(), $parent] );
        
        // $form->add_child( [$checkbox->label_tag(), $checkbox] );


            $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                $btn_save = H::submit_button(array('name'=>$this->input_action_identifier , 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save'), $this->get_tl('tlc_save'));
            $block_btns->add_child($btn_save);
            if ($post[$this->ifld_data_id] > 0){
                $btn_del = H::submit_button(array('name'=>$this->input_action_identifier , 'value'=>$this->ACTION_DELETE, 'class'=>$this->css.'btn_del button_delete', 'data-confirm'=>$this->get_tl('confirm_delete')), $this->get_tl('tlc_del'));
                $block_btns->add_child($btn_del);
            }

        $form->add_child([$block_btns]);

        if ($post[$this->ifld_data_id] > 0){
            $widget_group = \helPHP\modules\group\admin\Group::widget([], $this->ifld_data_id, $post[$this->ifld_data_id]);
            $form->add_child([$widget_group]);
        }

        return $form;
    }
    public function save(&$post){
        global $DB;

        $post['id_parent'] = ($post['id_parent']) ? $post['id_parent'] : 0;

        // $series = '';
        // if ($post['id_parent'] > 0) $series = $DB->prepared_query_value('SELECT series FROM '.$this->bddt_data.' WHERE id=?', 'i', [$post['id_parent']]);

        if ($post[$this->ifld_data_id] == 0) {

            // create
            $q = 'INSERT INTO '. $this->bddt_data.' SET name=?, entete=?, csv=?, series=?';
            $success = $DB->prepared_query($q, 'siss', [$post[$this->ifld_data_name], $post[$this->ifld_data_entete], $post[$this->ifld_data_csv], $post[$this->ifld_data_series]]);
            $post[$this->ifld_data_id] = $DB->last_insert_id();

            $this->add_parenting('data', $post[$this->ifld_data_id], $post['id_parent']);

        } else {

            $post[$this->ifld_data_entete] = (isset($post[$this->ifld_data_entete]) && $post[$this->ifld_data_entete]) ? 1 : 0;
            $q = 'UPDATE '. $this->bddt_data.' SET name=?, entete=?, csv=? WHERE id=?';
            $success = $DB->prepared_query($q, 'sisi', array($post[$this->ifld_data_name], $post[$this->ifld_data_entete], $post[$this->ifld_data_csv], $post[$this->ifld_data_id]));

            if ($post['previous_id_parent'] != $post['id_parent']) {
                $q = 'UPDATE '.$this->bddt_data.self::hierarchy_suffix.' SET id_parent=? WHERE id=?';
                $DB->prepared_query($q, 'ii', [$post['id_parent'], $post['id_parentage']]);
            }
        }

        Language::save_translation_data($post, $post[$this->ifld_data_id]);
        
        return $post[$this->ifld_data_id];
    }
    public function delete_data($post){
        global $DB;

        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);

        // Language::delete_long_translation_value($this->ifld_data_bandeau, $post[$this->ifld_data_id]);
        Language::delete_short_translation_value($this->ifld_data_name, $post[$this->ifld_data_id]);

        // $this->delete_associations($post[$this->ifld_data_id]);
        $this->delete_parenting('data', $post[$this->ifld_data_id]);
        
    }

    //---------------------------------------------------------------------------------------------------------------        
    /**
     * Delete link to categories
     *
     * @param  string $field_identifier identify the entry
     * @param  integer $field_id entry's id
     * @return boolean depending success
     */
    public static function delete($field_identifier = false, $field_id = false){
        if (!$field_identifier){
            Utils::error_log('need parameter field_identifier to work - field_identifier = '.$field_identifier);
            return false;
        }
        if (!$field_id){
            Utils::error_log('need parameter $id_tem to work - field_id = '.$field_id);
            return false;
        }

        global $DB;
        $q = 'DELETE FROM '.$DB->table('category_content').' WHERE field_identifier=? AND id_item=?';
        $DB->prepared_query($q, 'si', [$field_identifier, $field_id]);
        
        return true;
    }
        
    /**
     * Display a widget to choose a category for an entry
     *
     * @param  array $params            options, series
     * @param  string $field_identifier identify the entry
     * @param  integer $field_id        entry's id
     * @return string widget's display
     */
    public static function widget($params = [], $field_identifier = false, $field_id = false){
        if (!$field_identifier){
            Utils::error_log('need parameter field_identifier to work - field_identifier = '.$field_identifier);
            return false;
        }
        if (!$field_id){
            Utils::error_log('need parameter $id_tem to work - field_id = '.$field_id);
            return false;
        }

        $post = [];
        $category = new Category(null, $field_identifier, $field_id, $params);
        return $category->display_widget($post, $field_identifier, $field_id);
    }

    public function display_widget($post) {
        global $DB;

        parent::process_data($post); // to create inst_js

        $q = 'SELECT cont.id, data.name, cont.id_data FROM '.$DB->table('category_content').' cont LEFT JOIN '.$DB->table('category_data').' data ON (cont.id_data=data.id)';
        $q.=' WHERE cont.field_identifier=? AND cont.id_item=?';
        $parents = $DB->prepared_query_list($q, 'si', [$this->field_identifier, $this->field_id]);
        $id_parents = \array_column($parents, 'id_data');

        $list = [];
        if ($this->params['series']){
            $id_series = $this->get_series_id($this->params['series']);
            $list = HelPHP_module::module_data_tree('category', 'data', 'name', $id_series, true, $id_parents);
        } else {
            // get all ids of categories with series to ignore them
            $q = 'SELECT dat.id FROM '.$this->bddt_data.' dat LEFT JOIN '.$this->bddt_data.self::hierarchy_suffix.' par ON (par.id_item=dat.id)';
            $q.=' WHERE dat.series<>"" ORDER BY par.sort_order';
            $ids = $DB->query_list($q);
            $id_parents = array_merge($id_parents, $ids);
            $list = HelPHP_module::module_data_tree('category', 'data', 'name', 0, true, $id_parents);
        }

        $output = H::DIV(['class'=>$this->css.'widget module_widget', 'id'=>self::module_name.'_widget'.$this->dom_id]);

            $title = H::DIV(['class'=>$this->css.'widget_title widget_title'], $this->get_tl('ttl_widget'));

        $output->add_child( $title );

            $opts_data = ['value_key'=>'id', 'label_key'=>'name', 'data'=>$list];
            $precomplete = H::input_precomplete(['new_value'=>true, 'class'=>$this->css.'widget_select_add', 'label'=>$this->get_tl('widget_add')], $opts_data, false, $this->inst_js.'.add');

        $output->add_child([$precomplete->label_tag(), $precomplete]);
            
            $parent_list = H::DIV(['class'=>$this->css.'widget_list', 'id'=>self::module_name.'_widget_list'.$this->dom_id]);
            $output->add_child( $parent_list );
            if ($parents) {
                foreach ($parents as $parent) {
                    $parent_list->add_child( $this->widget_line($parent) );
                }
            }
            
            $settings = [
                'widget_id'=>$this->widget_id
            ];
            $js = H::script('helphp_timeout(\'Category_a.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($settings)).');\');', ['autoremove'=>true]);

        $output->add_child( $js );
        return $output;
    }
    public function widget_line($data) {
        $data['name'] = Language::get_name('category_data', $data['id_data']);
        $div = H::DIV(['class'=>$this->css.'line content widget_line', 'id'=>self::module_name.'_widget_line-'.$data['id'].$this->dom_id]);
            $del = H::button_icon('trash-2', ['class'=>$this->css.'btn_del button_delete content', 'onclick'=>$this->inst_js.'.delete('.$data['id'].');']);
            $name = H::SPAN(['class'=>$this->css.'line_name content'], $data['name']);
        $div->add_child( [$del, $name] );
        return $div;
    }
    public function add_content($post) {
        global $DB;

        if (!isset($post[$this->ifld_content_id_data])){
            Utils::error_log('missing id_data for category association');
            Utils::error_log($post);
            return false;
        }
        if ($post[$this->ifld_content_id_data] === 0 && $post['name'] === '') return;

        if ($post[$this->ifld_content_id_data] === 0 && $post['name'] != '') {
            $id_parent = $this->params['series'] ? $this->get_series_id($this->params['series']) : 0;
            $data = [
                'id_parent'             => $id_parent,
                $this->ifld_data_id     => 0,
                $this->ifld_data_name   => $post['name'],
                $this->ifld_data_entete => 0,
                $this->ifld_data_csv    => '',
                $this->ifld_data_series => $this->params['series'],
            ];
            $post[$this->ifld_content_id_data] = $this->save($data);
        }
        
        $q = 'INSERT INTO '.$this->bddt_content.' SET id_data=?, field_identifier=?, id_item=?';
        $DB->prepared_query($q, 'isi', [$post[$this->ifld_content_id_data], $this->field_identifier, $this->field_id]);
        $id = $DB->last_insert_id();
        
        return $this->display_widget($post, $this->field_identifier, $this->field_id);
    }
    public function delete_content($post, $from_master = false) {
        global $DB;

        if (!$from_master) { // delete one
            
            if (!isset($post[$this->ifld_content_id]) || !$post[$this->ifld_content_id]){
                Utils::error_log('missing content id for deleting group association');
                Utils::error_log($post);
                return false;
            }
    
            $q = 'DELETE FROM '.$this->bddt_content.' WHERE id = ?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_content_id]]);

        } else { // delete all
            
            $q = 'DELETE FROM '.$this->bddt_content.' WHERE id_group_data = ?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);

        }
        
        return $this->display_widget($post, $this->field_identifier, $this->field_id);
        // return true;
    }
    
    /**
     * return categories informations
     *
     * @param  string $field_identifier identify the entry
     * @param  integer $field_id        entry's id
     * @param  boolean $parent          with parents
     * @param  boolean $without_id      format the data to be used by DB->sql_from_json
     * @return array categorie's list
     */
    public static function get($field_identifier, $field_id, $parent = false, $for_db_json = false) {
        global $DB;

        $q = 'SELECT DISTINCT data.id, data.series, data.name FROM '.$DB->table('category_content').' cont JOIN ';
        $q.= $DB->table('category_data').' data ON (data.id=cont.id_data) WHERE cont.field_identifier=? AND cont.id_item=?';
        $data = $DB->prepared_query_list($q, 'si', [$field_identifier, $field_id]);
        foreach($data as $key => $entry){
            if ($for_db_json) {
                
                $t = [];
                $id_entry = $entry['id'];
                unset($entry['id']);
                array_push($t, $entry);

                if ($parent) {
                    $p = self::get_parent($id_entry, true);
                    if ($p) $t = \array_merge($t, $p);
                }

                $entry = $t;

            } else {
                if ($parent) $entry['parent'] = self::get_parent($entry['id'], false);
                $entry['name'] = Language::get_name('category_data', $entry['id']);
            }
            $data[$key] = $entry;
        }

        return $data;
    }
    /**
     * Return all the parent of a category, empty if there isn't
     * If a parent is found, will also get its parent to finally have the list of all the parents until root
     *
     * @param  integer $id          of the category we want the parents
     * @param  boolean $for_db_json format the data to be used by DB->sql_from_json
     * @return array list of all the parent ordonnate by order, first is the direct parent, second is parent of the first, etc
     */
    public static function get_parent($id, $for_db_json = false){
        global $DB;

        if ($id <= 0) return;

        // $parents = [];

        $q = 'SELECT data.id, data.series, data.name FROM '.$DB->table('category_data'.self::hierarchy_suffix).' asso JOIN ';
        $q.= $DB->table('category_data').' data ON (data.id=asso.id_parent) WHERE asso.id_item=?';
        $parent = $DB->prepared_query_line($q, 'i', [$id]);
        if ($parent) {
            if ($for_db_json) {
                
                $t = [];
                $id_parent = $parent['id'];
                unset($parent['id']);
                array_push($t, $parent);
                
                $p = self::get_parent($id_parent, true);
                if ($p) $t = \array_merge($t, $p);
                
                $parent = $t;

            } else {
                $parent['parent'] = self::get_parent($parent['id'], false);
                $parent['name'] = Language::get_name('category_data', $parent['id']);
            }
            // $parent['parent'] = self::get_parent($parent['id'], $for_db_json);
            // if ($for_db_json) unset($parent['id']);
            // else $parent['name'] = Language::get_name('category_data', $parent['id']);
        }

        return $parent;
    }
    /**
     * Return the serie id, create it if not found
     *
     * @param  string $name serie's name
     * @return int id serie's id
     */
    public static function get_series_id($name){
        global $DB;

        $q = 'SELECT dat.id FROM '.$DB->table('category_data').' dat LEFT JOIN '.$DB->table('category_data'.self::hierarchy_suffix).' par ON';
        $q.=' (dat.id=par.id_item AND par.id_parent=0) WHERE dat.series LIKE ?';
        $id = $DB->prepared_query_value($q, 's', [$name]);
        if (!$id){
            $q = 'INSERT INTO '.$DB->table('category_data').' SET name=?, series=?';
            $DB->prepared_query($q, 'ss', [$name, $name]);
            $id = $DB->last_insert_id();

            // add to parenting
            $q = 'INSERT INTO '.$DB->table('category_data'.self::hierarchy_suffix).' SET id_item=?, id_parent=?, sort_order=0';
            $res = $DB->prepared_query($q, 'ii', [$id, 0]);
        }

        return $id;
    }
    /**
     * Return all the categories belonging to a series or to none
     *
     * @param  string $name serie's name
     * @param  boolean $for_select to format the return array for an HTML select
     * @return array list categories
     */
    public static function get_list($series = false, $for_select = false){

        $id_series = ($series !== false) ? self::get_series_id($series) : 0;
        $list = !$for_select ? HelPHP_module::module_data_tree('category', 'data', 'name', $id_series, true) : HelPHP_module::module_data_tree_for_select('category', 'data', 'name', $id_series, true);

        return $list;
    }
}