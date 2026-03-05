<?php
/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */

namespace helPHP\modules\Blockeditor\admin;

use helPHP\libs\Filesystem;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Utils;
use helPHP\modules\csseditor\admin\Csseditor;

class Blockeditor extends HelPHP_module {

    const module_name = 'blockeditor';

    private $ACTION_ADD_FIELD = self::module_name.'_add_field';
    private $ACTION_NEW = self::module_name.'_new';
    private $ACTION_SAVE = self::module_name.'_save';
    private $ACTION_EDIT = self::module_name.'_edit';
    private $ACTION_MODAL_DELETE = self::module_name.'_modal_delete';
    private $ACTION_DELETE = self::module_name.'_delete';

    private $ACTION_SAVE_JS = self::module_name.'_save_js';
    private $ACTION_DELETE_JS = self::module_name.'_delete_js';

    private $ACTION_PREVIEW = self::module_name.'_preview';

    private $ACTION_GENERATE = self::module_name.'_generate';
    private $ACTION_ADD_MULTIRAD = self::module_name.'_add_multirad';
    private $ACTION_BUILD_BLOCK = self::module_name.'_build_block';

    private $ACTION_EXTRACT_ZIP = self::module_name.'_extract_zip';
    
    const types = [
        'short_text',               // varchar
        'long_text',                // text
        'short_multilangue',        // table des langues short
        'long_multilangue',         // table des langues long
        'phone',                    // varchar
        'email',                    // varchar
        'price',                    // float
        'boolean',                  // tinyint
        'multiple_radios',          // varchar
        // 'order',                    // integer
        'integer',                  // integer
        'float',                    // float
        'date',                     // date
        'datetime',                 // datetime
        'time',                     // time
        // 'association',              // integer, id autre objet
        'image',                    // table des medias
        'video',                    // table des medias
        'file',                      // table des medias
        'hcode'                      // HCODE
    ];

    const forbidden_name = ['data', 'animation'];

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $to_return = false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $CONFIG;

        if (isset($_GET['action']) && $_GET['action'] == $this->ACTION_PREVIEW) {
            $post = $_GET;
            $post[$this->input_action_identifier] = $this->ACTION_PREVIEW;
        }
        if (isset($_GET[$this->input_action_identifier]) && $_GET[$this->input_action_identifier] == $this->ACTION_EXTRACT_ZIP) {
            $post = $_GET;
        }
        
        if (isset($post[self::module_name]) && is_string($post[self::module_name])) {
            global $DB;
            $q = 'SELECT id FROM '. $DB->table('block_data').' WHERE name=?';
            $id = $DB->prepared_query_value($q, 's', [$post[self::module_name]]);
            if ($id) {
                $post['block_data-id'] = $id;
                $post[$this->input_action_identifier] = $this->ACTION_EDIT;
            }
        }
        
        $master_output = H::group('blockeditor_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_ADD_FIELD:
                $master_output->add_child( $this->display_fields($post) );
            break;

            case $this->ACTION_NEW:
                $this->reset_fields($post, 'block_data');
                Language::load_translation_data($post, 'block', 'data');

                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->display_forms($post) );
            break;
            case $this->ACTION_SAVE:
                $this->check_posted_data($post, 'block_data');

                $this->save($post);
                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->display_forms($post) );
            break;

            case $this->ACTION_SAVE_JS:
                $this->check_posted_data($post, 'block_data');

                $admin_js = (isset($post['admin_js'])) ? true : false;
                $master_output->add_child( $this->save_JS($post, $admin_js) );
                $master_output->add_child( $this->form_js_edit($post, $admin_js) );
            break;
            case $this->ACTION_DELETE_JS:
                $this->check_posted_data($post, 'block_data');
                $admin_js = (isset($post['admin_js'])) ? true : false;
                $this->delete_JS($post, $admin_js);
                $master_output->add_child( $this->form_js_edit($post, $admin_js) );
            break;

            case $this->ACTION_EDIT:
                // to install new block, get the name from id field before prepare_fiels that will erase it
                if (!is_numeric($post['block_data-id']) && $post['block_data-id'] != ''){
                    $post['block_data-id'] = $this->install_block($post['block_data-id']);
                }
                $this->prepare_fields($post, 'block_data');
                Language::load_translation_data($post, 'block', 'data', $post['block_data-id']);
                
                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->display_forms($post) );
            break;
            case $this->ACTION_MODAL_DELETE:
                $this->check_posted_data($post, 'block_data', ['id']);
                $master_output->add_child( $this->display_modal_delete($post) );
            break;
            case $this->ACTION_DELETE:
                $master_output->add_child( $this->delete($post));
            break;

            case $this->ACTION_BUILD_BLOCK:
                $this->prepare_fields($post, 'block_data');
                Language::load_translation_data($post, 'block', 'data');
                
                $this->build_block($post);

                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->display_forms($post) );
            break;

            case $this->ACTION_GENERATE:
                $this->prepare_fields($post, 'block_data');
                Language::load_translation_data($post, 'block', 'data');
                
                $this->save_sql($post);

                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->display_forms($post) );
            break;

            case $this->ACTION_ADD_MULTIRAD:
                $master_output->add_child( $this->multirad_line($post) );
            break;

            case $this->ACTION_PREVIEW:
                $this->dom_container = '';
                $master_output->add_child( $this->preview($post) );
            break;

            case $this->ACTION_EXTRACT_ZIP:
                $master_output->add_child( $this->extract_to_zip($post) );
            break;

            default:
                $this->check_posted_data($post, 'block_data');

                $master_output->add_child( $this->form_select($post) );

                $load_ace = H::script_loader($CONFIG::BASE_URL.'js/externals/ace/ace.js');
                $master_output->add_child($load_ace);
                
                // init js
                $js = ucfirst(self::module_name).'_a.create_instance("'.$this->dom_id.'");';
                $script = H::script('helphp_timeout(\''.$js.'\');');
                $master_output->add_child($script);
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    //----------------------------------------------------------------------------------------------------
    public function save(&$post) {
        global $DB, $CONFIG;

        $this->check_posted_data($post, 'block_data', ['id', 'name']);

        $post['block_data-name'] = str_replace([' ','-','¤','_'], '', Utils::remove_accents(strtolower($post['block_data-name'])));
        // check if authorized name
        if (in_array($post['block_data-name'], self::forbidden_name)){
            $str = '<ul><li>'.implode('</li><li>', self::forbidden_name).'</li></ul>';
            $this->add_error('reserved_name', $str);
            return;
        }

        $post['block_data-json'] = $this->post_to_json($post);
        if($post['block_data-id'] == 0){
            // création
            $q = 'INSERT INTO '. $DB->table('block_data').' SET name=?, json=?';
            $success = $DB->prepared_query($q, 'ss', [$post['block_data-name'], $post['block_data-json']]);
            $post['block_data-id'] = $DB->last_insert_id();
        }else{
            // mise à jour
            $q = 'UPDATE '.$DB->table('block_data').' SET name=?, json=? WHERE id=?';
            $success = $DB->prepared_query($q, 'ssi', [$post['block_data-name'], $post['block_data-json'], $post['block_data-id']]);

            if ($post['previous_name'] != $post['block_data-name']){
                $query_css = 'SELECT id, path FROM '.$DB->table('csseditor_source').' WHERE type=? AND admin=?';
                $css_admin = $DB->prepared_query_line($query_css, 'si', ['block¤'.$post['block_data-id'], 1]);
                if ($css_admin) {
                    $old_name = Filesystem::get_file_name_noext($css_admin['path']);
                    $new_path = \str_replace($old_name, $post['block_data-name'], $css_admin['path']);
                    $q = 'UPDATE '.$DB->table('csseditor_source').' SET path="'.$new_path.'" WHERE id='.$css_admin['id'];
                    $DB->query($q);
                }

                $css_public = $DB->prepared_query_line($query_css, 'si', ['block¤'.$post['block_data-id'], 0]);
                if ($css_public) {
                    $old_name = Filesystem::get_file_name_noext($css_public['path']);
                    $new_path = \str_replace($old_name, $post['block_data-name'], $css_public['path']);
                    $q = 'UPDATE '.$DB->table('csseditor_source').' SET path="'.$new_path.'" WHERE id='.$css_public['id'];
                    $DB->query($q);
                }
            }
        }

        Language::save_translation_data($post, $post['block_data-id']);

        $this->generate_db_json($post);
        
        if (is_file($CONFIG::HELPHP_FOLDER.'/modules/block/'.$post['block_data-name'].'/admin/'.ucfirst($post['block_data-name']).'.php')){
            return H::span(['class'=>'success'], $this->get_tl('save_ok').' '.$this->get_tl('block_already_builded'));
        } else {
            return H::span(['class'=>'success'], $this->get_tl('save_ok'));
        }
    }

    public function preview($post) {
        global $CONFIG,$DB;
        $path = $CONFIG::HELPHP_FOLDER.'/modules/block/'.$post['block_data-name']; 
        $path.= ($post['prevmode'] == 'admin')?'/admin/':'/public/';
        $path.= ucfirst($post['block_data-name']).'.php';
        $_POST = [];
        $_POST['core_insert'] = true;
        $_POST['block_'.$post['block_data-name'].'-id'] = 1;
        include_once($path);
        $modulep = '\helPHP\modules\block\\'.$post['block_data-name'].'\\'.$post['prevmode'].'\\'.ucfirst($post['block_data-name']);
        $modulep = new $modulep();
        $cssadmin = false;
        if($post['prevmode'] != 'admin'){
            $js = $DB->prepared_query_value('SELECT jspublic FROM '.$DB->table('block_data').' WHERE name=?', 's', [$post['block_data-name']]);
        }else{
            $cssadmin = true;
            $js = $DB->prepared_query_value('SELECT js FROM '.$DB->table('block_data').' WHERE name=?', 's', [$post['block_data-name']]);
        }
        if ($js != ''){
            $js = H::script($js);
        }
        
        $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('block', $post['id'], $cssadmin);
        if ($css != ''){
            $css = H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css', 'id'=>'css_block¤'.$post['id']), $css);
        }
        return $css.$js.$this->parse_hcode($modulep->process_data($_POST, true), ($post['prevmode'] == 'admin'));
    }

    public function install_block($name){
        // install block from it's name
        global $DB, $CONFIG;

        $path = $CONFIG::HELPHP_FOLDER.'modules/block/'.$name.'/';

        // insert in db
        $DB->sql_from_json(file_get_contents($path.\ucfirst($name).'.json'));

        $q = 'SELECT id FROM '.$DB->table('block_data').' WHERE name=?';
        $id_block = $DB->prepared_query_value($q, 's', [$name]);

        $css_file_public = $path.'public/'.$name.'.css';
        if (\file_exists($css_file_public)) {
            $md5 = \md5_file($css_file_public);
            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=0';
            $DB->prepared_query($q, 'ss', [$css_file_public, $md5]);
            $id_source = $DB->last_insert_id();
            \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_public, $id_source);
        }

        $css_file_admin = $path.'admin/'.$name.'.css';
        if (\file_exists($css_file_admin)) {
            $md5 = \md5_file($css_file_admin);
            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=1';
            $DB->prepared_query($q, 'ss', [$css_file_admin, $md5]);
            $id_source = $DB->last_insert_id();
            \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_admin, $id_source);
        }

        return $id_block;
    }
    
    public function post_to_json($post) {
        $json = [];

        if (isset($post['type'])){
            foreach($post['type'] as $key => $type){
                $arr = [];
                $arr['type'] = $type;
                if (isset($post['name'][$key])){
                    $arr['name'] = $post['name'][$key];
                }
                if (isset($post['limit'][$key])){
                    $arr['limit'] = $post['limit'][$key];
                }
                // if (isset($post['asso'][$key])){
                //     $arr['id_object'] = $post['asso'][$key];
                // }
                if (isset($post['multirad'][$key])){
                    $arr['values'] = $post['multirad'][$key];
                }
                if (isset($post['index'][$key])){
                    $arr['index'] = $post['index'][$key];
                }
                if (isset($post['sort_order'][$key])){
                    $arr['sort_order'] = $post['sort_order'][$key];
                }
                if (isset($post['hcode_admin'][$key])){
                    $arr['hcode_admin'] = $post['hcode_admin'][$key];
                }
                if (isset($post['hcode_public'][$key])){
                    $arr['hcode_public'] = $post['hcode_public'][$key];
                }
                array_push($json, $arr);
            }
        }
        
        return json_encode($json);
    }

    public function display_modal_delete($post){
        global $DB;

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id, 'class'=>$this->css.'form_modal_edit']);

        $hidden_id = H::input_hidden(['name'=>'block_data-id', 'value'=>$post['block_data-id']]);
        $form->add_child( $hidden_id );

        $title = H::DIV(['class'=>$this->css.'title_delete module_title'], $this->get_tl('title_delete_modal'));
        $form->add_child( $title );
        
        $q = 'SELECT id FROM '.$DB->table('document_blocks').' WHERE id_block=?';
        $used = $DB->prepared_query_list($q, 'i', [$post['block_data-id']]);
        if ($used){
            $info_used = H::DIV(['class'=>$this->css.'info_delete used'], $this->get_tl('info_del_used'));
            $form->add_child( $info_used );
        } else {
            $info_used = H::DIV(['class'=>$this->css.'info_delete not_used'], $this->get_tl('info_del_not_used'));
            $form->add_child( $info_used );
        }

        $block_btns = H::DIV(['class'=>$this->css.'block_btns']);
            $delete_with_data = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'class'=>$this->css.'btn_del with_data', 'data-parameters'=>['with_data'=>1],'data-confirm'=>$this->get_tl('confirm_delete')], $this->get_tl('delete_with_data'));
            $delete_keep_data = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'class'=>$this->css.'btn_del keep_data', 'data-parameters'=>['with_data'=>0],'data-confirm'=>$this->get_tl('confirm_delete')], $this->get_tl('delete_keep_data'));
            $cancel = H::BUTTON(['class'=>$this->css.'btn_cancel', 'onclick'=>'H_ui.popup_modal.hide();'], $this->get_tl('tlc_cancel'));
        $block_btns->add_child( [$delete_with_data, $delete_keep_data, $cancel] );
        $form->add_child( $block_btns );
        
        return $form;
    }
    public function delete(&$post) {
        global $DB, $LANG;

        $this->check_posted_data($post, 'block_data', ['id']);

        $delete_with_data = isset($post['with_data']) ? $post['with_data'] : 0;

        if ($delete_with_data) {
            // retrieve name and delete table of the block 
            $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id=?';
            $name = $DB->prepared_query_value($q, 'i', [$post['block_data-id']]);
            $q = 'DROP TABLE IF EXISTS '.$DB->table('block_'.$name);
            $DB->query($q);
            
            // delete from documents
            $q = 'DELETE FROM '.$DB->table('document_blocks').' WHERE id_block=?';
            $DB->prepared_query($q, 'i', [$post['block_data-id']]);
        }

        // $LANG::delete_long_translation_value('block-data_content' , $post['block_data-id']);

        \helPHP\modules\category\admin\Category::delete('block', $post['block_data-id']);

        $q = 'DELETE FROM '. $DB->table('block_data').' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post['block_data-id']]);

        Language::delete_translation_data($post, 'block', 'data', $post['block_data-id']);

        // delete css
        \helPHP\modules\csseditor\admin\Csseditor::delete_css_source('block', $post['block_data-id']);

        $this->reset_fields($post, 'block_data');

        return H::script('H_ui.popup_modal.hide(); h.main_tab.refresh_active();');
    }

    //----------------------------------------------------------------------------------------------------
    function form_select($post) {
        global $DB;

        $q = 'SELECT id, name FROM '. $DB->table('block_data').' ORDER BY name';
        $list = $DB->prepared_query_list($q);
        $names = \array_column($list, 'name');
        foreach($list as $key => $line){
            $list[$key]['name'] = Language::get_name('block_data', $line['id']);
            $list[$key]['installed'] = 1;
        }
        
        // get the list of not installed block from the folder modules/block
        global $CONFIG, $FS;
        $path = $CONFIG::HELPHP_FOLDER.'modules/block';
        $folders = $FS->shell_ls($path)['folders'];
        
        foreach($folders as $key => $folder){
            // if not in array and db json exist otherwise can't install it
            if (!in_array($folder['name'], $names) && file_exists($CONFIG::HELPHP_FOLDER.'modules/block/'.$folder['name'].'/'.\ucfirst($folder['name']).'.json')){
                array_push($list, ['id'=>$folder['name'], 'name'=>$folder['name'], 'installed'=>0]);
            }
        }
        
        $output = H::group('select_'.self::module_name);

            $title = H::DIV(array('class'=>$this->css.'title module_title'), $this->get_tl('title'));
            if (isset($post['block_data-id']) && $post['block_data-id'] > 0) {
                $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post['block_data-id'])));
            }

        $output->add_child( $title );
        
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select']);

                $btn_new = H::submit_button_single(['class'=>$this->css.'button_new button_new', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW, 'title'=>$this->get_tl('tlc_nouv')], $this->get_tl('tlc_nouv'));

                $selected_id = isset($post['block_data-id']) ? $post['block_data-id'] : 0;
                $opts_data = array('first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$list, 'groups'=>['installed'=>[0=>$this->get_tl('not_installed'), 1=>$this->get_tl('installed')], 'null']);
                $select = H::select(['name'=>'block_data-id', 'label'=>$this->get_tl('select_objerator')], $opts_data, $selected_id, $this->input_action_identifier, $this->ACTION_EDIT);
            
            $form->add_child([$btn_new, $select->label_tag(), $select]);

        $output->add_child($form);

        return $output;
    }

    public function display_forms($post){

        $params = ['dom_id'=>$this->dom_target];
        $labels = [$this->get_tl('block_fields'), $this->get_tl('block_js'), $this->get_tl('block_js_public')];
        $contents = [$this->form_edit($post), $this->form_js_edit($post,true), $this->form_js_edit($post,false)];
        if ($post['block_data-id'] > 0) {
            array_push($labels, $this->get_tl('block_css'), $this->get_tl('block_css_public'));
            array_push($contents, $this->form_css_edit($post, true), $this->form_css_edit($post, false));
        }
        $tabs = H::tabs($params, $labels, $contents);
        $container_tabs = H::DIV(['class'=>$this->css.'subcontainer_tab'], $tabs);
        
        return $container_tabs;
    }
    public function form_edit($post) {
        global $CONFIG;
        
        $current_key = 0;
        
        $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'dom_id'=>$this->dom_id, 'class'=>$this->css.'form_edit form_edit'));

            $hidden_id = H::input_hidden(['name'=>'block_data-id', 'value'=>$post['block_data-id'], 'data-alwaysposted'=>1, 'id'=>self::module_name.'_current_id'.$this->dom_id]);

        $form->add_child($hidden_id);
            
            $prev_name = H::input_hidden(['name'=>'previous_name', 'value'=>str_replace('¤',' ', html_entity_decode($post['block_data-name']))]);
            $inp_name = H::input_text(['name'=>'block_data-name', 'value'=>str_replace('¤',' ', html_entity_decode($post['block_data-name'])), 'label'=>$this->get_tl('tlc_name'), 'class'=>$this->css.'inp_name']);

        $form->add_child([$prev_name, $inp_name->label_tag(), $inp_name]);

            $lang_block = $this->translate_block($post, ['block_data-label'], 's');
            
        $form->add_child($lang_block);

            if ($post['block_data-id'] != 0){
                $widget = \helPHP\modules\category\admin\Category::widget(['series'=>'block'], 'block', $post['block_data-id']);
                $form->add_child( $widget );
            }
            
            if (isset($post['block_data-id']) && $post['block_data-id'] != 0){

                $block = H::DIV(['class'=>$this->css.'oversize_content']);
            
                    $fieldset = H::fieldset(['class'=>$this->css.'fieldset lst_field', 'id'=>self::module_name.'_fields'.$this->dom_id], $this->get_tl('descr'));
                        // champs déjà enregistrer
                        if ($post['block_data-json'] != ''){
                            $json = json_decode(html_entity_decode($post['block_data-json']), true);
                            // réordonne les champs par rapport à l'ordre dans le json
                            usort($json, function($a, $b){
                                return $a['sort_order'] - $b['sort_order'];
                            });
                            foreach($json as $key => $line){
                                $current_key = $key+1;
                                $line['current_id'] = $post['block_data-id'];
                                $line['key'] = $key; // pour avoir le bon num de champs, fields fait key++;
                                $fieldset->add_child( $this->display_fields($line) );
                            }
                        }

                    $block->add_child($fieldset);
                    
                        $fieldset = H::fieldset(['class'=>$this->css.'fieldset add_field'], $this->get_tl('add_field'));
                        $fieldset->add_child($this->display_add_field());
                        
                    $block->add_child($fieldset);

                $form->add_child( $block );
            }
        
            $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                $btn_save = H::submit_button(['name'=>$this->input_action_identifier , 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save', 'title'=>$this->get_tl('tlc_save')] , $this->get_tl('tlc_save'));
            $block_btns->add_child($btn_save);
            if ($post['block_data-id'] > 0){
                $params_js = [
                    'dom_id'=>$this->dom_id,
                    $this->input_action_identifier=>$this->ACTION_MODAL_DELETE,
                    'block_data-id'=>$post['block_data-id']
                ];
                $btn_delete = H::BUTTON(['class'=>$this->css.'btn_del button_delete', 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($params_js).');'], $this->get_tl('tlc_del'));
                // $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del', 'name'=>$this->input_action_identifier , 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')), $this->get_tl('tlc_del'));
                $block_btns->add_child($btn_delete);
                // $modal_param = [];
                // $modal_param['dom_id'] = $this->dom_id;
                // $modal_param['module'] = self::module_name;
                // $modal_param['id'] = $post['block_data-id'];
                // $modal_param['block_data-name'] = $post['block_data-name'];
                // $modal_param['admin'] = true;
                // $modal_param['action'] = $this->ACTION_PREVIEW;
                // $modal_param['prevmode'] = 'public';
                // $btn_preview = H::BUTTON(['onclick'=>'H_ui.open_popup_modal(event,"preview",'.json_encode($modal_param).');','class'=>$this->css.'btn_generate', 'title'=>$this->get_tl('preview')],$this->get_tl('preview'));
                // $modal_param['prevmode'] = 'admin';
                // $btn_previewadd = H::BUTTON(['onclick'=>'H_ui.open_popup_modal(event,"preview",'.json_encode($modal_param).');','class'=>$this->css.'btn_generate', 'title'=>$this->get_tl('preview_admin')],$this->get_tl('preview_admin'));
                $post['block_data-name'] = str_replace([' ','-','¤','_'], '', Utils::remove_accents(html_entity_decode(strtolower($post['block_data-name']))));
                $extra_params = [
                    'block_data-name'=>$post['block_data-name'],
                    'action'=>$this->ACTION_PREVIEW,
                    'prevmode'=>'public',
                    'css_source'=>'block¤'.$post['block_data-id'],
                    'dom_id' => $this->dom_id
                ];
                $btn_preview = H::preview_button(self::module_name, $post['block_data-id'], $this->get_tl('preview'), true, $extra_params);
                $extra_params['prevmode'] = 'admin';
                $btn_previewadd = H::preview_button(self::module_name, $post['block_data-id'], $this->get_tl('preview_admin'), true, $extra_params);

                // $btn_preview = H::submit_button_single(['name'=>$this->input_action_identifier , 'value'=>$this->ACTION_PREVIEW, 'class'=>$this->css.'btn_generate', 'title'=>$this->get_tl('preview')], $this->get_tl('preview'));
                // $btn_previewadd = H::submit_button_single(['name'=>$this->input_action_identifier , 'value'=>$this->ACTION_PREVIEW_ADM, 'class'=>$this->css.'btn_generate', 'title'=>$this->get_tl('preview_admin')], $this->get_tl('preview_admin'));
                $btn_generate = H::submit_button_single(['name'=>$this->input_action_identifier , 'value'=>$this->ACTION_GENERATE, 'class'=>$this->css.'btn_generate', 'title'=>$this->get_tl('generate')], $this->get_tl('generate'));
                $btn_build = H::submit_button_single(['name'=>$this->input_action_identifier , 'value'=>$this->ACTION_BUILD_BLOCK, 'class'=>$this->css.'btn_build', 'title'=>$this->get_tl('build')], $this->get_tl('build'));
                if (is_file($CONFIG::HELPHP_FOLDER.'modules/block/'.$post['block_data-name'].'/admin/'.ucfirst($post['block_data-name']).'.php')) {
                    $btn_build->set_attribute('data-confirm', $this->get_tl('confirm_build'));
                }

                $url = $this->get_index_relative_path().'?'.$this->input_action_identifier.'='.$this->ACTION_EXTRACT_ZIP.'&block_data-id='.$post['block_data-id'];
                $btn_extract = H::A(['class'=>$this->css.'link_extract', 'href'=>$url, 'target'=>'_blank']);
                $btn_extract->add_child(H::BUTTON(['class'=>$this->css.'btn_extract_zip', 'title'=>$this->get_tl('extract_zip')], $this->get_tl('extract_zip')));
                // $btn_extract = H::submit_button_single(['name'=>$this->input_action_identifier , 'value'=>$this->ACTION_EXTRACT_ZIP, 'class'=>$this->css.'btn_extract_zip', 'title'=>$this->get_tl('extract_zip')], $this->get_tl('extract_zip'));

                $block_btns->add_child([$btn_previewadd, $btn_preview, $btn_generate, $btn_build, $btn_extract]);

            }
            
        $form->add_child($block_btns);
        
        $form->add_child( H::script($this->inst_js.'.last_key = '.$current_key.';', ['autoremove'=>true]) );

        return $form;
    }
    public function form_js_edit($post, $admin_js = true) {
        $jspublic=($admin_js)?'_a':'';
        $suffix = ($admin_js)?'':'public';
        $mode=($admin_js)?'admin':'public';    
        $output = H::group('js_edit_'.self::module_name);
            $form_edit = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_js', 'dom_id'=>$this->dom_id]);
                // Utils::error_log($post);
                $js_class_name='Block_'.str_replace([' ','-','¤','_'], '',$post['block_data-name']).$jspublic;
                $defaultjs= "window.h.block = window.h.block || {};
class ".$js_class_name." extends H_module {
    constructor(dom_id){
        super(dom_id);
    }
    static instances = {};
    static create_instance(dom_id, settings){
        if (".$js_class_name.".instances[dom_id]){
            ".$js_class_name.".instances[dom_id].clean();
            delete(".$js_class_name.".instances[dom_id]);
        }
        ".$js_class_name.".instances[dom_id] = new ".$js_class_name."(dom_id, settings);

        ".$js_class_name.".clean_instances();
        
        return ".$js_class_name.".instances[dom_id];
    }
    static clean_instances(){
        let toClean = [];
        for (var key in ".$js_class_name.".instances) {
            if (".$js_class_name.".instances[key].exist()){
                ".$js_class_name.".instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key)=>{
            delete(".$js_class_name.".instances[key]);
        });
    }
}
window.h.block.".$js_class_name." = ".$js_class_name.";";

                // Utils::error_log($post[$this->build_module_field_name('block','data','js')]);
                $post[$this->build_module_field_name('block','data','js').$suffix]=(!isset($post[$this->build_module_field_name('block','data','js').$suffix]) || $post[$this->build_module_field_name('block','data','js').$suffix]=="")?$defaultjs:$post[$this->build_module_field_name('block','data','js').$suffix];
                //check to save no js
                if($post[$this->build_module_field_name('block','data','js').$suffix] == '' || isset($post['nojs'.$suffix])){
                    $checknojs = H::input_checkbox(['name'=>'nojs'.$suffix, 'value'=>true, 'checked'=>true, 'label'=>$this->get_tl('no_js'), 'class'=>$this->css.'no_js']);
                }else{
                    $checknojs = H::input_checkbox(['name'=>'nojs'.$suffix, 'value'=>true, 'label'=>$this->get_tl('no_js'), 'class'=>$this->css.'no_js']);
                }
                $form_edit->add_child([$checknojs->label_tag(), $checknojs  ]);

                if($admin_js==true){
                    $hidden_admin_js= H::input_hidden(['name'=>'admin_js', 'value'=>true],);
                    $form_edit->add_child([$hidden_admin_js]);
                }
                $hidden_id = H::input_hidden(['name'=>'block_data-id', 'value'=>$post['block_data-id'], 'data-alwaysposted'=>1]);
                $hidden_name = H::input_hidden(['name'=>'block_data-name', 'value'=>$post['block_data-name']]);
            
                $editor_id = self::module_name.$suffix.'_ace_editor'.$this->dom_id;
                $editor = H::DIV(['id'=>$editor_id, 'class'=>$this->css.'js_ace_editor'], $post[$this->build_module_field_name('block','data','js').$suffix]);
                $js_hidden = H::input_hidden(['id'=>$this->build_module_field_name('block','data','js').$suffix.$this->dom_id, 'name'=>$this->build_module_field_name('block','data','js').$suffix, 'value'=>'']);
            
                $div_btns = H::DIV(['class'=>$this->css.'form_parts_btns']);
                    $btn_save = H::BUTTON([ 'class'=>$this->css.'btn_save button_save', 'onmousedown'=>$this->inst_js.'.save_js("'.$suffix.'","'.$mode.'");', 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));
                    // there is a frontend processing to do before sending
                    $hidden_save = H::submit_button(['id'=>self::module_name.'_save_js_hidden_button'.$suffix.$this->dom_id, 'name'=>$this->input_action_identifier , 'value'=>$this->ACTION_SAVE_JS, 'style'=>'display:none;'], $this->get_tl('tlc_save'));
                $div_btns->add_child([$btn_save, $hidden_save]);
                if (isset($post['block_data-id']) && $post['block_data-id'] != 0){
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del parts button_delete', 'name'=>$this->input_action_identifier , 'value'=>$this->ACTION_DELETE_JS, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('ask_del_part')], $this->get_tl('tlc_del'));
                    $div_btns->add_child($btn_delete);
                }
            $form_edit->add_child([ $hidden_id,$hidden_name, $editor, $div_btns, $js_hidden]);


        $output->add_child( $form_edit );
            
            $script = H::script($this->inst_js.'.activate_ace_editor("'.$editor_id.'","'.$mode.'");', ['autoremove'=>true]);
        
        $output->add_child( [$script] );
        
        return $output;
    }
    public function delete_JS(&$post,$admin_js) {
        global $DB;
        $ispublic=($admin_js)?'':'public';
        $q = 'UPDATE '. $DB->table('block_data').' set js'.$ispublic.'="" WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post['block_data-id']]);

    }
    public function save_JS(&$post,$admin_js) {
        global $DB;
        // Utils::error_log($post );
        $ispublic=($admin_js)?'':'public';
        $suffix = ($admin_js)?'_a':'';
        if (isset($post['nojs'.$ispublic])){
            $post[$this->build_module_field_name('block','data','js').$ispublic] = '';
        }
        $q = 'UPDATE '. $DB->table('block_data').' set js'.$ispublic.'=? WHERE id=?';
        $res = $DB->prepared_query($q, 'si', [$post[$this->build_module_field_name('block','data','js').$ispublic],$post['block_data-id']]);
        return H::span(['class'=>'success'], $this->get_tl('save_ok'));
    }

    public function form_css_edit($post, $admin){

        $css_source = 'block¤'.$post['block_data-id'];

        $div = H::DIV(['id'=>$this->css.'csseditor_container'.$this->dom_id.'-'.($admin ? 'admin' : 'public'), 'data-css_source'=>$css_source]);
            
            $css_editor = new \helPHP\modules\csseditor\admin\Csseditor();
            $post = [];
            $post['source'] = $css_source;
            $post['force_admin_or_public'] = true;
            $post['admin'] = $admin;
            $css_editor->process_data($post);

        $div->add_child( $css_editor->get_output() );

        return $div;
    }

    public function display_add_field() {

        $output = H::group('add_field');
        
        foreach(self::types as $val){
            $span = H::SPAN(['class'=>$this->css.'field_to_add '.$val, 'data-value'=>$val, 'onclick'=>$this->inst_js.'.add_field(event);'], $this->get_tl($val));
            $output->add_child( $span );
        }
        
        return $output;
    }
    
    public function display_fields($post) {
        if (isset($post['ignore']) && $post['ignore']) return;

        if (!isset($post['key'])) return 'manque la clé';
        if (!isset($post['type'])) return 'manque le type';
        if (!isset($post['current_id'])) return 'manque l\'id';

        $post['key']++;

        // $output = H::DIV(['class'=>$this->css.'field', 'data-order_parent'=>'sort_order['.$post['key'].']']);
        $output = H::group('field_'.$post['key']);

        // to add in each field to be moved with input_order
        $order_dataset = 'sort_order['.$post['key'].']';

            $btn_del = H::button_icon('trash-2', ['class'=>$this->css.'btn_del_field button_delete', 'onclick'=>$this->inst_js.'.del_field(event);', 'data-order'=>$order_dataset, 'title'=>$this->get_tl('tlc_delete')]);

        $output->add_child($btn_del);

            // type du champs
            $type = H::SPAN(['class'=>$this->css.'field_type', 'data-order'=>$order_dataset], $this->get_tl($post['type']));
            $hidden_type = H::input_hidden(['name'=>'type['.$post['key'].']', 'value'=>$post['type'], 'data-order'=>$order_dataset]);
        
        $output->add_child( [$type, $hidden_type] );

        // nom du champs
        switch ($post['type']){
            // case 'order':
            // case 'association':
            //     $name = H::SPAN(['class'=>$this->css.'field_name'], $this->get_tl($post['type']));
            //     $output->add_child($name);
            // break;
            default:
                $val_name = isset($post['name']) ? $post['name'] : '';
                $name = H::input_text(['name'=>'name['.$post['key'].']', 'value'=>$val_name, 'data-order'=>$order_dataset, 'class'=>$this->css.'inp inp_name', 'placeholder'=>$this->get_tl('name'), 'data-required'=>1]);
                $output->add_child($name);
            break;

        }
        
        // limite du champs
        if ($post['type'] == 'short_text' || $post['type'] == 'integer') {
            $limit_value = isset($post['limit']) ? $post['limit'] : '';
            $limit = H::input_integer(['name'=>'limit['.$post['key'].']', 'class'=>$this->css.'inp inp_limit', 'data-order'=>$order_dataset, 'placeholder'=>$this->get_tl('limit'), 'value'=>$limit_value]);
            $output->add_child($limit);
        } else if ($post['type'] != 'hcode' && $post['type'] != 'multiple_radios') {
            $output->add_child(H::DIV(['class'=>$this->css.'empty_cell', 'data-order'=>$order_dataset]));
        }
        
        // cas particulier
        switch ($post['type']) {
            case 'hcode':
                $block_hcode = H::DIV(['class'=>$this->css.'hcode', 'data-order'=>$order_dataset]);
                    $hcode_admin = H::input_text(['name'=>'hcode_admin['.$post['key'].']', 'value'=>$post['hcode_admin'], 'class'=>$this->css.'inp inp_hcode_admin', 'placeholder'=>$this->get_tl('hcode_admin')]);
                    $hcode_public = H::input_text(['name'=>'hcode_public['.$post['key'].']', 'value'=>$post['hcode_public'], 'class'=>$this->css.'inp inp_hcode_public', 'placeholder'=>$this->get_tl('hcode_public')]);
                    $hcode_descritpion= H::SPAN(['class'=>$this->css.'hcode_descritpion'], $this->get_tl('hcode_desc'));
                $block_hcode->add_child([$hcode_admin,$hcode_public,$hcode_descritpion]);
                $output->add_child( $block_hcode );
            break;

            case 'multiple_radios': // multiple radios, permettre l'ajout des valeurs des radios
                // Utils::error_log($post);
                $block_multirad = H::DIV(['class'=>$this->css.'multirad', 'id'=>self::module_name.'_multirad'.$post['key'].$this->dom_id, 'data-order'=>$order_dataset]);
                    $info = H::DIV(['class'=>$this->css.'info'], $this->get_tl('info_multirad'));
                    $btn_add = H::BUTTON(['class'=>$this->css.'btn_add multirad', 'onclick'=>$this->inst_js.'.add_multirad('.$post['key'].');'], $this->get_tl('tlc_add'));
                    $block_values = H::DIV(['class'=>$this->css.'multirad_values', 'id'=>self::module_name.'_multirad_values'.$post['key'].$this->dom_id]);
                    if (isset($post['values'])){
                        $data = ['field_key'=>$post['key']];
                        foreach($post['values'] as $ind => $val){
                            $data['index'] = $ind;
                            $data['value'] = $val;
                            $block_values->add_child( $this->multirad_line($data) );
                        }
                    }
                $block_multirad->add_child([$info, $btn_add, $block_values]);
                $output->add_child($block_multirad);
            default:
            break;
        }

            // index if not hcode
        if ($post['type'] != 'hcode') {
            $index_data = [['val'=>'index'],['val'=>'fulltext'],['val'=>'unique']];
            $opts_data = ['first_empty'=>true, 'label_key'=>'val', 'value_key'=>'val', 'options'=>$index_data];
            $selected = isset($post['index']) ? $post['index'] : '';
            $select = H::select(['name'=>'index['.$post['key'].']', 'class'=>$this->css.'inp sel_index', 'data-order'=>$order_dataset], $opts_data, $selected);
            $output->add_child($select);
        } else {
            $output->add_child(H::DIV(['class'=>$this->css.'empty_cell', 'data-order'=>$order_dataset]));
        }
        
        $val_order = (isset($post['sort_order'])) ? $post['sort_order'] : ($post['key']+1);
        
        $order = H::input_order(['name'=>'sort_order['.$post['key'].']', 'value'=>$val_order, 'class'=>$this->css.'order']);
        $output->add_child($order);
        return $output;
    }

    public function multirad_line($post) {
        if (!isset($post['field_key'])) return 'missing field key';
        if (!isset($post['index'])) return 'missing index';
        if (!isset($post['value'])) return 'missing value';

        $line = H::DIV(['class'=>$this->css.'line_multirad', 'id'=>self::module_name.'_line_multirad'.$post['index'].$this->dom_id, 'data-index'=>$post['index']]);
            $btn_del = H::BUTTON(['class'=>$this->css.'btn_del multirad', 'onclick'=>$this->inst_js.'.del_multirad(event);'], 'x');
            $inp = H::input_text(['name'=>'multirad['.$post['field_key'].']['.$post['index'].']', 'value'=>$post['value'], 'class'=>$this->css.'input multirad']);
        $line->add_child([$btn_del, $inp]);

        return $line;
    }
    
    function build_block($post) {
        global $DB,$CONFIG,$FS;

        //le form peut être buildé depuis la preview, ou l'éditeur ou embedé d'où le blindage un peu lourd du départ.
        $id_block = (!isset($post[$this->build_module_field_name('block','data','id_block')]) ? $post['block_data-id'] : $post[$this->build_module_field_name('block','data','id_block')]);
        $post[$this->build_module_field_name('block','data','id_block')]=$id_block;
        
        if (isset($post['json']) && $post['json'] != ''){
            $object_data = $post['json'];
            $jsonfields = json_decode(html_entity_decode($post['json']), true);
        }else{
            $q = 'SELECT name, json FROM '.$DB->table('block_data').' where id='.$id_block;
            $object_data = $DB->query_line($q);
            $jsonfields = json_decode(html_entity_decode($object_data['json']), true);
        }
        $name_block = (!isset($post[$this->build_module_field_name('block','data','name_block')])) ? $object_data['name'] : $post[$this->build_module_field_name('block','data','name_block')];
        $name_block = str_replace([' ','-','¤','_'], '', Utils::remove_accents(strtolower($name_block)));

        $post[$this->build_module_field_name('block','data','name_block')] = $name_block;

        //json sorting
        $sorted_json = array();
        $ksortkey = 0;
        foreach($jsonfields as $keyy => $line){
            $keyt = (isset($line['sort_order'])) ? $line['sort_order'] : $ksortkey;
            $line['sort_order'] = $keyt;
            $sorted_json[$keyt] = $line;
            $ksortkey++;
        }
        ksort($sorted_json);
        //traitement des tl à voir côté ui translate !
        
        //preparation à la mode de l'ancien modulator style master.
        $fields = array();
        $fields['fields'] = '';
        $fields['fields_type'] = '';
        $fields['fields_values'] = '';
        $fields['fields_input'] = '';
        $fields['fields_display'] = '';
        $fields['multilang'] = '';
        // $fields['image_or_video'] = false;
        $fields['datetime'] = false;
        $fields['media'] = false;
        $fields['save_media'] = false;
        $fields['delete_media'] = false;
        // $fields['$hidden_name'] = false;
        $fields['tl_block_name'] = '';
        $fields['tl_block_type'] = '';
        // $fields['order']=false;
        // $fields['default_method'] = '';
        // $fields['delete_method'] = '';
        $fields['out_form']='';
        $fields['out_form_name']=[];
        //pour l'ordre d'insertion final des input fields et display fields
        $fields['childs'] = array();
        $this->add_to_commons($commons, 'use', '');
        // $objeratordata['name'] = $name_block;
        $base_field_name='$this->ifld_'.$name_block.'_';

        //pour mémoriser la key d'affichage en cas de block multlingue pour virer le label_tag en sortie
        $multikey=-1;
        $ind='                ';
        $creation_date=['type'=>'datetime', 'name'=>'creadate', 'index'=>'', 'sort_order'=>0];
        array_push($jsonfields, $creation_date);
        
        //~ UTILS_Class::error_log($options);
        foreach($jsonfields as $key => $line){
            //composition du nom dans le cas du champ d'asso
            if ($line['type']=='association'){
                $q = 'SELECT name FROM '.$DB->table('dataobjecteditor_data').' WHERE id=?';
                $assoName = $DB->prepared_query_value($q, 'i', [$line['id_object']]);
                $line['name']= 'id_'.$assoName;
            }
            //et pour le champ d'ordre
            if ($line['type']=='order'){
                $line['name']= 'ordre';
            }
            //on prepare les query et value l'ordre d'insertion pour les champs qui ne sont pas des multilingues:
            if ($line['type']!='short_multilangue' && $line['type']!='long_multilangue' && $line['type']!='hcode'){
                $fields['childs']['$sort_order['.$key.']']=$line['name'];
                if($line['type']!='image' && $line['type']!='video'){
                    $fields['fields'].=$line['name'].'=?,';
                    $fields['fields_values'].='$post['.$base_field_name.$line['name'].'],';
                }
                if($line['type']=='image' || $line['type']=='video'){
                    $fields['childs']['$sort_order['.$key.']'].='¤span';
                }
                if($line['type']=='multiple_radios' ){
                    $fields['childs']['$sort_order['.$key.']'].='¤nolabel';
                }

            }else{
                if ($fields['multilang']=='' && $line['type']!='hcode') { //c'est le premier mutilingue ! on insère le multiblock une seule fois donc.
                    $fields['childs']['$sort_order['.$key.']']='multiblock';
                    $multikey='$sort_order['.$key.']';
                }
            }
            //param communss à tous les champs pour pouvoir insérer un type unique
            $base_param='\'name\'=>'.$base_field_name.$line['name'].',\'id\'=>'.$base_field_name.$line['name'].', \'label\'=>$this->get_tl(\''.$line['name'].'\'), \'value\'=>$post['.$base_field_name.$line['name'].'], \'class\'=>';
            $base_display='\'id\'=>'.$base_field_name.$line['name'].'_public, \'class\'=>';
            //label pour l'affihage publique...
            if( $line['name']!= 'creadate'){
                $fields['fields_display'].=$ind.'$'.$line['name'].'_label =H::SPAN( [\'id\'=>'.$base_field_name.$line['name'].'_label, \'class\'=>\'label\'], $this->get_tl(\''.$line['name'].'\'));'.PHP_EOL;
            }
            // $spec=(isset($options['spec['.$key.']']))?' , '.$options['spec['.$key.']']:'';
            // en fonction du type de champs
            $spec='';
            switch ($line['type']){

                case 'short_multilangue':
                    //pas de fields_type, c'est un short multi 
                    $fields['multilang'].=$line['name'].'_label,$'.$line['name'].',';
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_shortmulti\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                    // $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_shortmulti\'], stripslashes(Language::load_short_translation_value('.$base_field_name.$line['name'].',$post['.$base_field_name.'id],$LANG->current_id_data)));'.PHP_EOL;
                    $fields['tl_block_name'].=$base_field_name.$line['name'].',';
                    $fields['tl_block_type'].='s';
                break;
                case 'long_multilangue':
                    //pas de fields_type, c'est un long multi
                    $fields['multilang'].=$line['name'].'_label,$'.$line['name'].',';
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::DIV( ['.$base_display.'\'disp_longmulti\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                    // $fields['fields_display'].=$ind.'$'.$line['name'].' =H::DIV( ['.$base_display.'\'disp_longmulti\'], stripslashes(Language::load_long_translation_value('.$base_field_name.$line['name'].',$post['.$base_field_name.'id],$LANG->current_id_data)));'.PHP_EOL;
                    $fields['tl_block_name'].=$base_field_name.$line['name'].',';
                    $fields['tl_block_type'].='l';
                break;
                case 'short_text':
                    $fields['fields_type'].='s';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_text(['.$base_param.'\'inp_short_text\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_text\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                
                break;
                case 'image':
                    if (!$fields['media']){
                        // premier fields de type image ou vidéo, on a besoin de la libs media.
                        $this->add_to_commons($commons, 'use', 'use helPHP\modules\media\admin\Media as Media_UI;'.PHP_EOL);
                        $fields['media'] = true;

                        // ajouter seulement une fois la globale media pour le delete mais il y aura autant d'appel à la fonction de delete qu'il y a de widget
                        $fields['delete_media'].= $ind.$ind.'global $MEDIA;'.PHP_EOL;

                        $fields['save_media'].= $ind.$ind.'global $MEDIA;'.PHP_EOL;
                        $fields['save_media'].= $ind.$ind.'$res = $MEDIA->process_media($post, $post['.$base_field_name.'id]);'.PHP_EOL;
                        $fields['save_media'].= $ind.$ind.'if (!$res) $this->add_error(\'media_error\');'.PHP_EOL;
                    }

                    $fields['delete_media'].= $ind.$ind.'$MEDIA->delete_media('.str_replace('¤','_',$base_field_name.$line['name']).', $post['.$base_field_name.'id]);'.PHP_EOL;

                    $fields['fields_input'].= $ind.'$label_'.$line['name'].' = H::SPAN([\'class\'=>$this->css.\'label\'], $this->get_tl(\''.$line['name'].'\'));'.PHP_EOL;
                    $fields['fields_input'].= $ind.'$params = [\'accept\'=>\'image/*\', \'list\'=>true];'.PHP_EOL;
                    $fields['fields_input'].= $ind.'$process[\'process\'] = [[\'type\'=>\'image_to_file\', \'quality\'=>80]];'.PHP_EOL;
                    $fields['fields_input'].= $ind.'$'.$line['name'].' = Media_UI::display(\'uploader\', $params, '.str_replace('¤','_',$base_field_name.$line['name']).', $post['.$base_field_name.'id], $process);'.PHP_EOL;

                    $fields['fields_display'].= $ind.'global $MEDIA;'.PHP_EOL;
                    $fields['fields_display'].= $ind.'$media = $MEDIA->get_html('.str_replace('¤','_',$base_field_name.$line['name']).', $post['.$base_field_name.'id]);'.PHP_EOL;
                    $fields['fields_display'].= $ind.'$'.$line['name'].' = ($media)? $media:\'\';'.PHP_EOL;
                
                break;
                case 'video':

                    
                    if (!$fields['media']){
                        // premier fields de type image ou vidéo ? on a besoin de la libs media.
                        $this->add_to_commons($commons, 'use', 'use helPHP\modules\media\admin\Media as Media_UI;'.PHP_EOL);
                        $fields['media'] = true;

                        $fields['delete_media'].= $ind.'global $MEDIA;'.PHP_EOL;

                        $fields['save_media'].= $ind.'global $MEDIA;'.PHP_EOL;
                        $fields['save_media'].= $ind.'$res = $MEDIA->process_media($post, $post['.$base_field_name.'id]);'.PHP_EOL;
                        $fields['save_media'].= $ind.'if (!$res) $this->add_error(\'media_error\');'.PHP_EOL;
                    }
                    $fields['delete_media'].= $ind.$ind.'$MEDIA->delete_media(\'block_'.str_replace('¤','-',$name_block).'_'.$line['name'].'\', $post['.$base_field_name.'id]);'.PHP_EOL;

                    $fields['fields_input'].= $ind.'$label_'.$line['name'].' = H::SPAN([\'class\'=>$this->css.\'label\'], $this->get_tl(\''.$line['name'].'\'));'.PHP_EOL;
                    $fields['fields_input'].= $ind.'$params = [\'accept\'=>\'video/*\'];'.PHP_EOL;
                    $fields['fields_input'].= $ind.'$'.$line['name'].' = Media_UI::display(\'uploader\', $params, '.str_replace('¤','_',$base_field_name.$line['name']).', $post['.$base_field_name.'id]);'.PHP_EOL;
                    $fields['fields_display'].= $ind.'global $MEDIA;'.PHP_EOL;
                    $fields['fields_display'].= $ind.'$media = $MEDIA->get_html('.str_replace('¤','_',$base_field_name.$line['name']).', $post['.$base_field_name.'id]);'.PHP_EOL;
                    $fields['fields_display'].= $ind.'$'.$line['name'].' = ($media)? $media:\'\';'.PHP_EOL;
                
                break;
                case 'file':
                    $fields['fields_type'].='s';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_file(['.$base_param.'\'inp_file\''.$spec.']);'.PHP_EOL;
                break;
                case 'long_text':
                    $fields['fields_type'].='s';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_textarea([\'tinymce\'=>[],'.$base_param.'\'inp_textarea\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::tag(H::DIV, ['.$base_display.'\'disp_textarea\'], html_entity_decode($post['.$base_field_name.$line['name'].']));'.PHP_EOL;
                break;
                case 'phone':
                    $fields['fields_type'].='s';
                    //todo : ajouter validateur de champs tél de pocrifs...
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_text(['.$base_param.'\'inp_tel\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\' disp_tel\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                break;
                case 'email':
                    $fields['fields_type'].='s';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_email(['.$base_param.'\'inp_email\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_email\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                break;
                case 'integer':
                    $fields['fields_type'].='i';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_integer(['.$base_param.'\'inp_int\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_int\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                break;

                case 'price':
                    $fields['fields_type'].='d';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_float(['.$base_param.'\'inp_prix\''.$spec.']);'.PHP_EOL;
                    //ajout du symbole monétaire à coup de css ?
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_prix\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                break;
                case 'float':
                    $fields['fields_type'].='d';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_float(['.$base_param.'\'inp_float\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_float\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                break;
                case 'boolean':
                    if (isset($options['option_mutual']) && $options['option_mutual']){
                        $base_param='\'name\'=>\''.$base_field_name.$line['name'].'\', \'id\'=>\''.$base_field_name.$line['name'].'\', \'label\'=>$this->get_tl(\''.$line['name'].'\'), \'value\'=>1, \'class\'=>';
                    } else {
                        $base_param = '\'name\'=>'.$base_field_name.$line['name'].',\'id\'=>'.$base_field_name.$line['name'].', \'label\'=>$this->get_tl(\''.$line['name'].'\'), \'value\'=>1, \'class\'=>';
                    }
                    $fields['fields_type'].='i';
                    $fields['fields_input'].=$ind.'$checked=($post['.$base_field_name.$line['name'].']==1)?true:false;'.PHP_EOL;
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_checkbox(['.$base_param.'\' inp_check\' , \'checked\'=>$checked'.$spec.']);'.PHP_EOL;
                    //on manipule la class disp_check pour lui ajouter 0 ou 1 pour afficher l'état checked ou autre à coup de css
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_check\'.$post['.$base_field_name.$line['name'].']]);'.PHP_EOL;
                break;
                case 'multiple_radios':
                    $fields['fields_type'].='s';
                    $fields['fields_input'].= $this->ind.'$values = [';
                    $values = array_values($line['values']); // to reset key, otherwise first key can be more than 0 and add an indesirable comma
                    foreach($values as $index => $val){
                        if ($index > 0) {
                            $fields['fields_input'].= ', ';
                        }
                        $fields['fields_input'].= '[\'value\'=>\''.$val.'\', \'label\'=>\''.$val.'\']';
                    }
                    $fields['fields_input'].= '];'.PHP_EOL;
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_multiple_radios(['.$base_param.'\' inp_radio\''.$spec.', \'selected\'=>$post['.$base_field_name.$line['name'].'], \'values\'=>$values]);'.PHP_EOL;
                    //on manipule la class disp_check pour lui ajouter 0 ou 1 pour afficher l'état checked ou autre à coup de css
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_radio\'.$post['.$base_field_name.$line['name'].']]);'.PHP_EOL;
                break;
                case 'date':
                    $fields['datetime']=true;
                    $fields['fields_type'].='s';
                    $base_param='\'name\'=>'.$base_field_name.$line['name'].',\'id\'=>'.$base_field_name.$line['name'].', \'label\'=>$this->get_tl(\''.$line['name'].'\'), \'value\'=>Datetime::mysql_to_html_date($post['.$base_field_name.$line['name'].']), \'class\'=>';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_date(['.$base_param.'\'inp_float\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_date\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                break;
                case 'datetime':
                    $fields['datetime']=true;
                    $fields['fields_type'].='s';
                    if( $line['name']!= 'creadate'){
                        $base_param='\'name\'=>'.$base_field_name.$line['name'].',\'id\'=>'.$base_field_name.$line['name'].', \'label\'=>$this->get_tl(\''.$line['name'].'\'), \'value\'=>Datetime::mysql_to_html_datetime($post['.$base_field_name.$line['name'].']), \'class\'=>';
                        $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_datetime(['.$base_param.'\'inp_float\''.$spec.']);'.PHP_EOL;
                        $fields['fields_display'].=$ind.'$'.$line['name'].' =H::SPAN( ['.$base_display.'\'disp_datetime\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
                    }
                break;
                case 'time':
                    $fields['fields_type'].='s';
                    $base_param='\'name\'=>'.$base_field_name.$line['name'].',\'id\'=>'.$base_field_name.$line['name'].', \'label\'=>$this->get_tl(\''.$line['name'].'\'), \'value\'=>$post['.$base_field_name.$line['name'].'], \'class\'=>';
                    $fields['fields_input'].=$ind.'$'.$line['name'].' = H::input_time(['.$base_param.'\'inp_float\''.$spec.']);'.PHP_EOL;
                    $fields['fields_display'].=$ind.'$'.$line['name'].' = H::SPAN( ['.$base_display.'\'disp_time\'], $post['.$base_field_name.$line['name'].']);'.PHP_EOL;
            break;
                case 'hcode':
                    Utils::error_log($line);
                    $fields['out_form'].= $ind.'$'.$line['name'].' = H::DIV( ['.$base_display.'\'admin_hcode\'], "['.$line['hcode_admin'].']");'.PHP_EOL;
                    array_push($fields['out_form_name'],'$'.$line['name']);
                    $fields['fields_display'].= $ind.'$'.$line['name'].' = H::DIV( ['.$base_display.'\'disp_hcode\'], "['.$line['hcode_public'].']");'.PHP_EOL;
                break;
                default:
                //manque les images, les videos , et les fichiers joints..
                break;
            }
        }
        //a t'on eu des multilingues ?
        if ($fields['multilang']){
            $fields['fields_input'].=$ind.'$multiblock=$this->translate_block($post, ['.rtrim($fields['tl_block_name'],',').'], \''.$fields['tl_block_type'].'\');'.PHP_EOL;
        }
        //la ligne d'insertion 
        $fields['fields_input'].=$ind.'$form->add_child([';
        $fields['fields_display'].=$ind.'$data_display->add_child([';
        
        // to rearrange the order we have to sort the array
        ksort($fields['childs']);
        foreach($fields['childs'] as $key => $child){
            //pour pallier aux souci de label en fonction du type de champs
            if( $child!='creadate'){
                $tchild=explode('¤',$child);
                $child=$tchild[0];
                $speclabel=(isset($tchild[1]) && $tchild[1]!='')?$tchild[1]:false;
                if ($key!=$multikey && !$speclabel){
                    $fields['fields_input'].='$'.$child.'->label_tag(),$'.$child.',';
                    $fields['fields_display'].='$'.$child.'_label,$'.$child.',';
                }else{
                    if ($speclabel=='nolabel' || $key==$multikey){
                        $fields['fields_input'].='$'.$child.',';
                        $fields['fields_display'].='$'.$child.',';
                    }
                    if($speclabel=='span'){
                        $fields['fields_input'].='$label_'.$child.',$'.$child.',';
                        $fields['fields_display'].='$'.$child.',';
                    }
                }
            }
        }
        
        $fields['fields_input'] = rtrim($fields['fields_input'],',').']);'.PHP_EOL;
        $fields['fields_display'] = rtrim($fields['fields_display'],',').']);'.PHP_EOL;
        //cleaning
        $fields['fields']=rtrim($fields['fields'],',');
        $fields['fields_values']=rtrim($fields['fields_values'],',');
        $fields['multilang']=rtrim($fields['multilang'],',');

        $part='<?php

namespace helPHP\modules\block\¤object_name¤\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
¤use¤

class ¤ucfirst_module_name¤ extends HelPHP_module {

    const module_name = \'block\';

    const block_name = \'¤object_name¤\';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container, $CONFIG::HELPHP_FOLDER.\'modules/block/¤object_name¤/admin/¤ucfirst_module_name¤.php\');
    }

    private $ACTION_NEW_¤object_name¤ = self::module_name.\'_new\';
    private $ACTION_SAVE_¤object_name¤ = self::module_name.\'_save\';
    private $ACTION_EDIT_¤object_name¤ = self::module_name.\'_edit\';
    private $ACTION_DELETE_¤object_name¤ = self::module_name.\'_delete\';
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $CONFIG;

        // when opening module from the preview
        if (isset($post[\'id\']) && intval($post[\'id\'] > 0)){
            $post[$this->ifld_¤object_name¤_id] = $post[\'id\'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = \'no_edit \'.$this->css;
        }

        $master_output = H::group($this->module_name.\'_display\');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW_¤object_name¤:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_¤object_name¤_id]);
                    $this->reset_fields($post, \'block_¤object_name¤\');
                    $master_output->add_child( $this->edit_¤object_name¤($post) );
                }
            break;
            case $this->ACTION_EDIT_¤object_name¤:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, \'block_¤object_name¤\');
    ¤load_translation¤
                    $master_output->add_child( $this->edit_¤object_name¤($post) );
                }
            break;
            case $this->ACTION_SAVE_¤object_name¤:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, \'block_¤object_name¤\');
                    $this->save_¤object_name¤($post);
    ¤save_translation¤
                    $master_output->add_child( $this->edit_¤object_name¤($post) );
                }
            break;
            
            case $this->ACTION_DELETE_¤object_name¤:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, \'block_¤object_name¤\');
                    $this->delete_¤object_name¤($post);
    ¤delete_translation¤
                    $master_output->add_child( H::SPAN([\'class\'=>$this->css.\'block_deleted\'], $this->get_tl(\'tlc_deleted\')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, \'block_¤object_name¤\');
                $master_output->add_child( $this->edit_¤object_name¤($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function edit_¤object_name¤($post) {
        global $DB, $CONFIG;
        
        $output = H::div([\'class\'=>\'block_container_a ¤object_name¤_a\',\'data-block_type\'=>\'¤object_name¤\',\'data-block_id\'=>$post['.$base_field_name.'id],\'id\'=>\'block_admin_¤object_name¤_\'.$post['.$base_field_name.'id].$this->dom_id ]);
            
¤out_form¤
¤out_form_name¤

            $form = H::form([\'action\'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.\'block/index.php\', \'dom_target\'=>\'.parent\', \'class\'=>$this->css.\'form_edit form_edit\', \'dom_id\'=>$this->dom_id]);

            $form->add_child( H::input_hidden([\'name\'=>\'block_name\', \'value\'=>self::block_name, \'data-alwaysposted\'=>1]) );

            if (isset($post[\'caller\'])) $form->add_child( H::input_hidden([\'name\'=>\'caller\', \'value\'=>$post[\'caller\'], \'data-alwaysposted\'=>1]) );
            if (isset($post[\'caller_params\'])){
                foreach($post[\'caller_params\'] as $name => $value){
                    $form->add_child( H::input_hidden([\'name\'=>\'caller_params[\'.$name.\']\', \'value\'=>$value, \'data-alwaysposted\'=>1]) );
                }
            }

            $form->add_child(H::input_hidden([\'name\'=>$this->ifld_¤object_name¤_id, \'value\'=>$post[$this->ifld_¤object_name¤_id], \'data-alwaysposted\'=>1]));

¤fields_input¤

                $block_btns = H::DIV([\'class\'=>$this->css.\'block_btns edit_buttons\']);
                    $btn_save = H::submit_button([\'class\'=>$this->css.\'btn_save button_save\', \'name\'=>$this->input_action_identifier, \'value\'=>$this->ACTION_SAVE_¤object_name¤, \'title\'=>$this->get_tl(\'tlc_save\')], $this->get_tl(\'tlc_save\'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_¤object_name¤_id] > 0) {
                    $btn_delete = H::submit_button([\'class\'=>$this->css.\'btn_del button_delete\', \'name\'=>$this->input_action_identifier, \'value\'=>$this->ACTION_DELETE_¤object_name¤, \'title\'=>$this->get_tl(\'tlc_del\'), \'data-confirm\'=>$this->get_tl(\'tlc_ask_delete\')], $this->get_tl(\'tlc_del\'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);
        $output->add_child($form);
        
        ¤js_admin¤

        return $output;
    }
    
    public function save_¤object_name¤(&$post) {
        global $DB;

        if($post[$this->ifld_¤object_name¤_id] == 0 || !isset($post[$this->ifld_¤object_name¤_id])){
            // create
            $post[$this->ifld_¤object_name¤_creadate] = date("Y-m-d H:i:s"); 
            $q = \'INSERT INTO \'.$DB->table(\'block_¤object_name¤\').\' SET ¤fields¤\';
            $success = $DB->prepared_query($q,\'¤fields_type¤\',[¤fields_values¤]);
            $post[$this->ifld_¤object_name¤_id] = $DB->last_insert_id();
            
        }else{
            $post[$this->ifld_¤object_name¤_creadate] = date("Y-m-d H:i:s"); 
            $q = \'UPDATE \'.$DB->table(\'block_¤object_name¤\').\' SET ¤fields¤ where id=?\';
            $success = $DB->prepared_query($q,\'¤fields_type¤i\',[¤fields_values¤¤comma¤$post[$this->ifld_¤object_name¤_id]]);
        }
        if (isset($post[\'need_id\'])) {
            $_SESSION[$post[\'need_id\']] = $post[$this->ifld_¤object_name¤_id];
        }

¤save_media¤

    }

    public function delete_¤object_name¤(&$post) {
        global $DB;

        $q = \'DELETE FROM \'.$DB->table(\'block_¤object_name¤\').\' WHERE id=?\';
        $res = $DB->prepared_query($q, \'i\', [$post[$this->ifld_¤object_name¤_id]]);

¤delete_media¤

    }
}
$json_sql=\'¤json_sql¤\';';

$public_part='<?php

namespace helPHP\modules\block\¤object_name¤\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class ¤ucfirst_module_name¤ extends HelPHP_module {

    const module_name = \'block\';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.\'modules/block/¤object_name¤/public/¤ucfirst_module_name¤.php\');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post[\'block\'])){
            $post['.$base_field_name.'id] = $post[\'block\'];
        }
        
        $master_output = H::group($this->module_name.\'_display\');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, \'block_¤object_name¤\');
                if (!$post['.$base_field_name.'id]) $this->reset_fields($post, \'block_¤object_name¤\');
¤load_translation¤
                $master_output->add_child( $this->display($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    public function display($post) {
        
        global $DB,$LANG;
        
        $data_display = H::div([\'class\'=>\'block_container block_'.$id_block.' ¤object_name¤\',\'data-block_type\'=>\'¤object_name¤\',\'data-block_id\'=>$post['.$base_field_name.'id],\'id\'=>\'block_¤object_name¤_\'.$post['.$base_field_name.'id] ]);
¤fields_display¤
¤out_form_name¤
¤js_public¤
        return $data_display;
    }
}';

        
        //insertion dans la part, replace ¤fields_input¤ ¤fields¤ ¤fields_type¤ ¤fields_values¤ ¤fields_display¤
        $part = str_replace('¤fields¤', $fields['fields'], $part);
        $part = str_replace('¤fields_input¤', $fields['fields_input'], $part);
        $part = str_replace('¤fields_type¤', $fields['fields_type'], $part);
        $part = str_replace('¤fields_values¤', $fields['fields_values'], $part);
        $comma = ($fields['fields_values'] != '') ? ',' : '';
        $part = str_replace('¤comma¤', $comma, $part);
        $part = str_replace('¤fields_display¤', $fields['fields_display'], $part);
        $part = str_replace('¤save_media¤', $fields['save_media'], $part);
        $part = str_replace('¤delete_media¤', $fields['delete_media'], $part);
        $part = str_replace('¤object_name¤', $name_block, $part);
        $part = str_replace('¤ucfirst_module_name¤', ucfirst($name_block), $part);
        
        $part = str_replace('¤out_form¤', $fields['out_form'], $part);
        if ($fields['out_form_name']) $out_form_name = $ind.'$output->add_child(['.implode(',',$fields['out_form_name']).']);';
        else $out_form_name = '';
        
        $part = str_replace('¤out_form_name¤', $out_form_name, $part);
        //js part
        //cleaning name_block
        $jsname_block = str_replace('¤', '_', $name_block);

        $jsadmin = $DB->query_value('SELECT js FROM '.$DB->table('block_data').' WHERE id='.$id_block);
        $jsinit = '$output->add_child(H::script(\'window.h.block.Block_'.$jsname_block.'_a.create_instance("\'.$this->dom_id.\'");\',[\'defer\'=>true,\'autoremove\'=>true]));';
        
        $jsinit = ($jsadmin != '') ? $jsinit : '';
        $part = str_replace('¤js_admin¤', $jsinit, $part);
        
        // Utils::error_log(json_encode($object_data));

        if (isset($fields['datetime'])){
            $this->add_to_commons($commons, 'use', 'use helPHP\libs\Datetime;');
        }
        foreach($commons as $key => $list_val){
            $part = str_replace('¤'.$key.'¤', implode('', $list_val), $part);
        }

        $part = str_replace('¤json_sql¤', '"'.addslashes(stripslashes(json_encode($object_data))), $part);

        $public_part = str_replace('¤fields¤', $fields['fields'], $public_part);
        $public_part = str_replace('¤fields_input¤', $fields['fields_input'], $public_part);
        $public_part = str_replace('¤fields_type¤', $fields['fields_type'], $public_part);
        $public_part = str_replace('¤fields_values¤', $fields['fields_values'], $public_part);

        $public_part = str_replace('¤comma¤', $comma, $public_part);
        $fields['fields_display'] = str_replace('multiblock', $fields['multilang'], $fields['fields_display']);
        
        if (count($fields['out_form_name'])>0){
            $out_form_name= $ind.'$data_display->add_child(['.implode(',',$fields['out_form_name']).']);';
            $public_part = str_replace('¤out_form_name¤', $out_form_name,$public_part);
        }else{
            $public_part = str_replace('¤out_form_name¤', '',$public_part);
        }
            
        $public_part = str_replace('¤fields_display¤', $fields['fields_display'], $public_part);
        $public_part = str_replace('¤object_name¤', $name_block, $public_part);
        $public_part = str_replace('¤ucfirst_module_name¤', ucfirst($name_block), $public_part);

        //nous avons des champs multilingues, nous devons insérer les méthodes les concernants...¤loadtranslation¤ ¤savetranslation¤ ¤deletetranslation¤
        if ($fields['multilang']){
            $loadt = $ind.'Language::load_translation_data($post, self::module_name, \''.$name_block.'\', $post['.$base_field_name.'id]);';
            $part = str_replace('¤load_translation¤', $loadt, $part);
            $loadt = $ind.'Language::load_public_translation_data($post, self::module_name, \''.$name_block.'\', $post['.$base_field_name.'id]);';
            $public_part = str_replace('¤load_translation¤', $loadt, $public_part);
            
            $savet = $ind.'Language::save_translation_data($post, $post['.$base_field_name.'id]);';
            $part = str_replace('¤save_translation¤', $savet, $part);
            $public_part = str_replace('¤save_translation¤', $savet, $public_part);
            
            $delt = $ind.'Language::delete_translation_data($post, self::module_name, \''.$name_block.'\', $post['.$base_field_name.'id]);';
            $part = str_replace('¤delete_translation¤', $delt, $part);
            $public_part = str_replace('¤delete_translation¤', $delt, $public_part);
        }else{
            $part = str_replace('¤load_translation¤', '', $part);
            $public_part = str_replace('¤load_translation¤', '', $public_part);
            $part = str_replace('¤save_translation¤', '', $part);
            $public_part = str_replace('¤save_translation¤', '', $public_part);
            $part = str_replace('¤delete_translation¤', '', $part);
            $public_part = str_replace('¤delete_translation¤', '', $public_part);
        }
        //enrichissement des query si on a un champ order (non employé sur le module standard ou le sous objet standard qui n'affichent qu'un seul item
        if (isset($fields['order'])){
            $qplus = $ind.'$q.= \' ORDER BY order\';'.PHP_EOL;
            $part = str_replace('¤order¤',$qplus,$part);
            $public_part = str_replace('¤order¤',$qplus,$public_part);
        }
        //jspart
        $jspublic = $DB->query_value('SELECT jspublic FROM '.$DB->table('block_data').' WHERE id='.$id_block);
        $jsinit = '$data_display->add_child(H::script(\'window.h.block.Block_'.$jsname_block.'.create_instance("\'.$this->dom_id.\'");\',[\'autoremove\'=>true]));';
        $jsinit = ($jspublic!='') ? $jsinit : '';
        $public_part = str_replace('¤js_public¤', $jsinit, $public_part);
        

        $output_path=$CONFIG::HELPHP_FOLDER.'modules/block';
        if(!is_dir($output_path.'/'.$name_block.'/admin')) {
            mkdir($output_path.'/'.$name_block.'/admin', 0775, true);
            mkdir($output_path.'/'.$name_block.'/public', 0775, true);
        }
        $FS->save_content($output_path.'/'.$name_block.'/admin/'.ucfirst($name_block).'.php', $part);
        $FS->save_content($output_path.'/'.$name_block.'/public/'.ucfirst($name_block).'.php', $public_part);
        if ($post[$this->build_module_field_name('block','data','js')] != ""){
            $FS->save_content($output_path.'/'.$name_block.'/admin/'.$name_block.'.js', str_replace($name_block,$jsname_block,$jsadmin));
        }
        if ($post[$this->build_module_field_name('block','data','jspublic')]!=""){
            $FS->save_content($output_path.'/'.$name_block.'/public/'.$name_block.'.js', str_replace($name_block,$jsname_block,$jspublic));
        }

        $this->generate_db_json($post);

        $this->add_message('build_ok');
    }
    public function add_to_commons(&$commons, $key, $val) {
        if (!isset($commons[$key])) $commons[$key] = [];
        if (!in_array($val, $commons[$key])) array_push($commons[$key], $val);
    }

    public function prepare_json_sql($post){

        $name = 'block_'.str_replace([' ','-','¤','_'], '',html_entity_decode($post['block_data-name']));
        $json = json_decode(html_entity_decode($post['block_data-json']), true);
        $creation_date = ['type'=>'datetime', 'name'=>'creadate', 'index'=>'', 'sort_order'=>0];
        array_push($json, $creation_date);

        $sql_array = [
            'name' => $name,
            'fields' => [
                [
                    'name'      => 'id',
                    'type'      => 'int',
                    'limit'     => 11,
                    'null'      => false,
                    'primary'   => true
                ]
            ]
        ];

        foreach ($json as $key => $line){
            if ($line['type'] != 'image' && $line['type'] != 'video' && $line['type'] != 'short_multilangue' && $line['type'] != 'long_multilangue' && $line['type'] != 'hcode') {

                $field = [];

                $line['name'] = (!isset($line['name']) && $line['type'] == 'order') ? 'sort_order' : $line['name'];

                // nom du champs
                if (isset($line['name'])){
                    $field['name'] = $line['name'];
                }
            
                // type de champs
                switch ($line['type']){
                    case 'short_text':
                        $field['type'] = 'varchar';
                        if ($line['limit'] != '') $field['limit'] = intval($line['limit']);
                        else $field['limit'] = 255;
                        $field['null'] = false;
                        $field['default'] = '';
                    break;
                    case 'long_text':
                        $field['type'] = 'text';
                    break;
                    case 'phone':
                        $field['type'] = 'varchar';
                        $field['limit'] = 15;
                        $field['null'] = false;
                        $field['default'] = '';
                    break;
                    case 'email':
                    case 'file':
                    case 'multiple_radios':
                        $field['type'] = 'varchar';
                        $field['limit'] = 255;
                        $field['null'] = false;
                        $field['default'] = '';
                    break;
                    case 'integer':
                        $field['type'] = 'int';
                        if ($line['limit'] != '') $field['limit'] = intval($line['limit']);
                        else $field['limit'] = 11;
                        $field['null'] = false;
                        $field['default'] = '0';
                    break;
                    // case 'order':
                    // case 'association':
                    //     $sql.= ' int(11) NOT NULL DEFAULT \'0\'';
                    // break;
                    case 'price':
                    case 'float':
                        $field['type'] = 'float';
                        $field['null'] = false;
                        $field['default'] = '0';
                    break;
                    case 'boolean':
                        $field['type'] = 'tinyint';
                        $field['limit'] = 1;
                        $field['null'] = false;
                        $field['default'] = '0';
                    break;
                    case 'date':
                        $field['type'] = 'date';
                        $field['null'] = false;
                        $field['default'] = 'CURRENT_TIMESTAMP';
                    break;
                    
                    case 'datetime':
                        $field['type'] = 'datetime';
                        $field['null'] = false;
                        $field['default'] = 'CURRENT_TIMESTAMP';
                    break;

                    case 'time':
                        $field['type'] = 'time';
                        $field['null'] = false;
                        $field['default'] = 'CURRENT_TIMESTAMP';
                    break;

                    case 'user':
                        $field['name'] = 'id_users_data';
                        $field['type'] = 'int';
                        $field['limit'] = 11;
                        $field['null'] = false;
                        $field['default'] = '0';
                    break;
                    
                    default:
                    break;
                }

                if (isset($line['index']) && $line['index']){
                    $field['index'] = $line['index'];
                }

                array_push($sql_array['fields'], $field);
            }
        }

        return ['tables'=>[$sql_array]];
    }
    public function save_sql($post, $to_return = false) {
        global $DB, $CONFIG_DB;

        $sql_array = $this->prepare_json_sql($post);

        if ($to_return) return $sql_array;
        
        $DB->sql_from_json($sql_array);

        $this->add_message('sql_saved');
    }
    public function generate_db_json($post){
        global $CONFIG, $FS, $DB;
        // generate the sql for creating the table for the block
        $json = $this->save_sql($post, true);

        $json['entries'] = [
            [
                'table'=>'block_data',
                'fields'=>[
                    ['name' => 'name',      'type' => 's', 'value' => $post['block_data-name']],
                    ['name' => 'json',      'type' => 's', 'value' => addslashes(html_entity_decode($post['block_data-json']))],
                    ['name' => 'js',        'type' => 's', 'value' => addslashes(html_entity_decode($post['block_data-js']))],
                    ['name' => 'jspublic',  'type' => 's', 'value' => addslashes(html_entity_decode($post['block_data-jspublic']))]
                ],
                'languages'=>[
                    'short'=>[
                        'label'=>[]
                    ]
                ],
                'categories'=>[]
            ]
        ];

        // get for each lang iso the name of the block
        $q = 'SELECT DISTINCT d.iso, l.value FROM '.$DB->table('languages_data').' d, '.$DB->table('languages_short').' l WHERE';
        $q.=' d.id=l.id_data AND l.field_identifier="block_data-label" AND l.id_item=?';
        $lang_data = $DB->prepared_query_list($q, 'i', [$post['block_data-id']]);
        if ($lang_data){
            foreach($lang_data as $l){
                $json['entries'][0]['languages']['short']['label'][$l['iso']] = $l['value'];
            }
        } else {
            unset($json['entries'][0]['languages']);
        }

        // retrieve category parent and it's hierarchy and prepare it for the json
        $categories = \helPHP\modules\category\admin\Category::get('block', $post['block_data-id'], true, true);
        foreach($categories as $key => $category) {
            $json['entries'][0]['categories']['block'] = $category;
        }
        
        $name_block = str_replace([' ','-','¤','_'], '', Utils::remove_accents(strtolower($post['block_data-name'])));
        $name_block = html_entity_decode($name_block);
        
        $output_path = $CONFIG::HELPHP_FOLDER.'modules/block';
        if (!file_exists($output_path.'/'.$name_block)){
            $FS->mkdir($output_path.'/'.$name_block);
        }
        $FS->save_content($output_path.'/'.$name_block.'/'.ucfirst($name_block).'.json', json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    public function indent($nbr, $size = 4) {
        $s = '';
        str_pad($s, $nbr * $size, ' ');
        return $s;
    }

    public function extract_to_zip(&$post) {
        global $DB, $CONFIG, $CONFIG_DB, $FS;

        $this->apply_bdd_data($post, 'block_data');

        $q = 'SELECT * FROM '.$DB->table('block_data').' WHERE id=?';
        $line = $DB->prepared_query_line($q, 'i', [$post['block_data-id']]);
        
        // generate the sql for creating the table for the block
        $this->generate_db_json($post);
        
        $name_block = str_replace([' ','-','¤','_'], '', Utils::remove_accents(strtolower($post['block_data-name'])));
        $name_block = html_entity_decode($name_block);
        
        $output_path = $CONFIG::HELPHP_FOLDER.'modules/block';


        // generate css public file and admin
        $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('block', $post['block_data-id'], false);
        if ($css != '') {
            // force a call to extract font
            $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type=? AND admin=0';
            $source = $DB->prepared_query_line($q, 's', ['block¤'.$post['block_data-id']]);
            $temp = [];
            \helPHP\modules\csseditor\admin\Csseditor::get_css_fonts($temp, $source, true);

            $FS->save_content($output_path.'/'.$name_block.'/public/'.$name_block.'.css', $css);
        }
        $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('block', $post['block_data-id'], true);
        if ($css != '') {
            // force a call to extract font
            $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type=? AND admin=1';
            $source = $DB->prepared_query_line($q, 's', ['block¤'.$post['block_data-id']]);
            $temp = [];
            \helPHP\modules\csseditor\admin\Csseditor::get_css_fonts($temp, $source, true);
            
            $FS->save_content($output_path.'/'.$name_block.'/admin/'.$name_block.'.css', $css);
        }

        $tmp_path = $CONFIG::HOME_FOLDER.'temp/';

        // create folder block that will be back with block copied inside
        if (!file_exists($tmp_path.'block/')) {
            $FS->mkdir($tmp_path.'block/');
        }

        $FS->copy($output_path.'/'.$name_block, $tmp_path.'block/');
        $FS->pack([$tmp_path.'block'], $tmp_path, $name_block, 'zip');
        \helPHP\libs\Media::send_file($tmp_path.$name_block.'.zip', false);

        $FS->delete($tmp_path.'block/');
        $FS->delete($tmp_path.$name_block.'.zip');
    }
}