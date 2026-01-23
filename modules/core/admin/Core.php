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
namespace helPHP\modules\core\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\modules\connection\admin\Connection as Connection;

class Core extends HelPHP_module {

    const module_name = 'core';

    protected $root_module = true;

    //----------------------------------------------------------------------------------------------
    // variables specific to this module
    //-------
    protected $MODULE_LIST;
    
    private $ACTION_DISPLAY_EDIT = self::module_name.'_display_editor';
    private $ACTION_NEW = self::module_name.'_new';
    private $ACTION_EDIT = self::module_name.'_edit';
    private $ACTION_SAVE = self::module_name.'_save';
    private $ACTION_DELETE = self::module_name.'_delete';

    private $ACTION_SAVE_SUBMODULE = self::module_name.'_save_submodule';
    private $ACTION_ADD_SUBMODULE = self::module_name.'_add_submodule';
    private $ACTION_DELETE_SUBMODULE = self::module_name.'_del_submodule';

    private $ACTION_DISPLAY_MONO = self::module_name.'_mono';
    
    protected $preview = false;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);

        global $CONFIG;
        $this->MODULE_LIST = $CONFIG::MODULES_LIST;
        ksort($this->MODULE_LIST,SORT_STRING);
        if (isset($this->options['preview']) && $this->options['preview']) {
            $this->preview = true;
        }
    }
    public function process_data(&$post, $toreturn = false) {
        if (parent::process_data($post) == false) {
            //user not authorized
            return null;
        }
        
        global $USER, $CONFIG;

        $master_output = H::group('core_display');
        if ($USER->admin) {
            switch ($post[$this->input_action_identifier]) {

                case $this->ACTION_EDIT:
                    $this->prepare_fields($post, 'core_disposition');
                    $master_output->add_child( $this->select_disposition($post) );
                    $master_output->add_child( $this->form_disposition($post) );
                break;
                case $this->ACTION_NEW:
                    $post[$this->ifld_disposition_id] = 0;
                    $this->reset_fields($post, 'core_disposition');
                    $master_output->add_child( $this->select_disposition($post) );
                    $master_output->add_child( $this->form_disposition($post) );
                break;
                case $this->ACTION_SAVE:
                    $this->check_posted_data($post,'core_disposition');
                    $this->save($post);
                    $master_output->add_child( $this->select_disposition($post) );
                    $master_output->add_child( $this->form_disposition($post) );
                break;
                case $this->ACTION_DELETE:
                    $this->check_posted_data($post,'core_disposition');
                    $this->delete($post);
                    $post[$this->ifld_structure_id] = 0;
                    $this->reset_fields($post, 'core_disposition');
                    $master_output->add_child( $this->select_disposition($post) );
                break;
                case $this->ACTION_DISPLAY_EDIT:
                    $this->check_posted_data($post,'core_disposition');
                    $master_output->add_child( $this->select_disposition($post) );
                break;

                case $this->ACTION_SAVE_SUBMODULE:
                    $this->save_submodule($post);
                    $master_output->add_child( $this->form_sub_modules($post[$this->ifld_disposition_id]) );
                break;

                case $this->ACTION_ADD_SUBMODULE:
                    $this->add_submodule($post);
                    $master_output->add_child( $this->form_sub_modules($post[$this->ifld_disposition_id]) );
                break;

                case $this->ACTION_DELETE_SUBMODULE:
                    $this->delete_submodule($post);
                    $master_output->add_child( $this->form_sub_modules($post[$this->ifld_disposition_id]) );
                break;

                case $this->ACTION_DISPLAY_MONO:
                    // single module display
                    $this->dom_container = '';
                    $master_output->add_child($this->display_one_module($post));
                break;

                default:
                    //displays the default layout or the one indicated by the post
                    $this->dom_container = '';
                    $master_output->add_child($this->process_disposition($post));
                break;
            }
        } else {
            $allowed_admin_modules = $USER->allowed_admin_modules();

            if (count($allowed_admin_modules) > 0) {
                if ($post[$this->input_action_identifier] == $this->ACTION_DISPLAY_MONO) {
                    // single module display
                    $this->dom_container= '';
                    $master_output->add_child($this->display_one_module($post));
                } else {
                    //displays the default layout or the one indicated by the post
                    $this->dom_container='';
                    $master_output->add_child($this->process_disposition($post));
                }
            } else {

                if ($USER->connection_state==0) {
                    $this->dom_container='';
                    $master_output->add_child($this->process_disposition($post));
                } elseif ($USER->connection_state==-1) {
                    Utils::error_log('ban '.$CONFIG::CONNECTION_TRY_BAN_HOURS.'h');
                    echo 'banned for '.$CONFIG::CONNECTION_TRY_BAN_HOURS.'h';
                    exit;
                }
            }
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    //----------------------------------------------------------------------------------------------------
    // LAYOUT EDITOR: Select modules for a given layout 
    //----------------------------------------------------------------------------------------------------
    public function select_disposition($post) {
        global $DB;

        $output = H::group('select_disposition');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('diposition_editor_title'));
            if ($post[$this->ifld_disposition_id] > 0) {
                $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_disposition_id])));
            }

        $output->add_child($title);

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));
                
                $button_new=H::submit_button_single(array('class'=>$this->css.'button_new button_new','name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW, 'title'=>$this->get_tl('tlc_new')), $this->get_tl('tlc_new'));

                $q = 'SELECT DISTINCT * FROM '.$this->bddt_disposition;
                $list = $DB->prepared_query_list($q);
                $opts_data = array('first_empty'=>true , 'value_key'=>'id' , 'label_key'=>'name' , 'options'=>$list);
                $select = H::select(['name'=>$this->ifld_disposition_id, 'label'=>$this->get_tl('tlc_select')], $opts_data, $post[$this->ifld_disposition_id], $this->input_action_identifier, $this->ACTION_EDIT);

            $form->add_child([$button_new, $select->label_tag(), $select]);

        $output->add_child( $form );

        return $output;
    }

    // layout edit form
    public function form_disposition($post) {
        $output = H::group('dispo_editor');

            $form = H::form(array('action'=>$this->get_index_relative_path() , 'class'=>$this->css.'form_edit form_edit', 'dom_target'=>$this->dom_target));

            $form->add_child( H::input_hidden(['name'=>$this->ifld_disposition_id, 'value'=>$post[$this->ifld_disposition_id], 'data-alwaysposted'=>1]) );

                //layout name
                $name_input = H::input_text(['name'=>$this->ifld_disposition_name, 'data-required'=>1, 'value'=>$post[$this->ifld_disposition_name], 'label'=>$this->get_tl($this->ifld_disposition_name)]);

            $form->add_child([$name_input->label_tag(),$name_input]);

                //description of the layout
                $decription_input = H::input_textarea(array('name'=>$this->ifld_disposition_description, 'value' => $post[$this->ifld_disposition_description],'label'=>$this->get_tl($this->ifld_disposition_description) ));

            $form->add_child([$decription_input->label_tag(),$decription_input]);

                // indexable module selector
                $indexable_module_select_input = $this->select_module_input(false, true, $post[$this->ifld_disposition_module_indexable]);

            $form->add_child([$indexable_module_select_input->label_tag(),$indexable_module_select_input]);

                //Indexable module param value
                $module_param_input = H::input_text(array('name'=>$this->ifld_disposition_module_param , 'value' => $post[$this->ifld_disposition_module_param],'label'=>$this->get_tl($this->ifld_disposition_module_param) ));

            $form->add_child([$module_param_input->label_tag(),$module_param_input]);

                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(['class'=>$this->css.'btn_save button_save', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE], $this->get_tl('tlc_save'));
                $block_btns->add_child($btn_save);
                if ($post[$this->ifld_disposition_id] > 0) {
                    $btn_del = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'data-confirm'=>$this->get_tl('confirm_delete_dispostion', $post[$this->ifld_disposition_name])], $this->get_tl('tlc_del'));
                    $block_btns->add_child($btn_del);
                }

            $form->add_child($block_btns);

        $output->add_child($form);

            $sub_modules = H::DIV(['id'=>self::module_name.'_admin_container_submodules'.$this->dom_id, 'class'=>$this->css.'submodules']);
            if ($post[$this->ifld_disposition_id] > 0){
                $sub_modules->add_child($this->form_sub_modules($post[$this->ifld_disposition_id]));
            }

        $output->add_child($sub_modules);
        
        return $output;
    }

    // layout selector
    //module selector indexable or not
    public function select_module_input($field_name = false, $indexable = false, $module_name = null) {
        $temp_module_list=array();

        foreach ($this->MODULE_LIST as $moduleName => $module) {
            if ($indexable && $module['indexable']) {
                array_push($temp_module_list, ['name'=>$moduleName]);
            }
            if (!$indexable && !$module['indexable']) {
                array_push($temp_module_list, ['name'=>$moduleName]);
            }
        }

        if (!$field_name) {
            $field_name = ($indexable) ? $this->ifld_disposition_module_indexable : $this->ifld_disposition_module;
        } else {
            $temp_label = '';
        }
       
        $temp_label = $this->get_tl($field_name);
        $options_data = array('first_empty'=>true, 'value_key'=>'name', 'label_key'=>'name', 'options'=>$temp_module_list);
        return H::select(['name'=>$field_name, 'label'=>$temp_label], $options_data, $module_name);
    }
    //save or update the layout
    public function save($post) {
        global $DB;

        if ($post[$this->ifld_disposition_id] == 0) {
            // création
            $q = 'INSERT INTO '. $this->bddt_disposition.' SET name=? ,description=?, module_indexable=?, module_param=?';
            $success = $DB->prepared_query($q, 'ssss', array($post[$this->ifld_disposition_name] , $post[$this->ifld_disposition_description] , $post[$this->ifld_disposition_module_indexable], $post[$this->ifld_disposition_module_param]));
            $post[$this->ifld_disposition_id] = $DB->last_insert_id();
        } else {
            // mise à jour
            $q = 'UPDATE '. $this->bddt_disposition.' SET name=? , description=? , module_indexable=? , module_param=? WHERE id=?';
            Utils::error_log($q);
            Utils::error_log(array($post[$this->ifld_disposition_name] , $post[$this->ifld_disposition_description] , $post[$this->ifld_disposition_module_indexable], $post[$this->ifld_disposition_module_param], $post[$this->ifld_disposition_id]));
            $success = $DB->prepared_query($q, 'ssssi', array($post[$this->ifld_disposition_name] , $post[$this->ifld_disposition_description] , $post[$this->ifld_disposition_module_indexable], $post[$this->ifld_disposition_module_param], $post[$this->ifld_disposition_id]));
        }
    }
    //delete the layout and its descendants
    public function delete($post) {
        global $DB;

        $q = 'DELETE FROM '.$this->bddt_submodules.' WHERE id_disposition=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_disposition_id]]);

        $q = 'DELETE FROM '.$this->bddt_disposition.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_disposition_id]]);
    }

    //----------------------------------------------------------------------------------------------------
    //LAYOUT SUBMODULES EDITOR: Selects the submodules for a given layout
    //----------------------------------------------------------------------------------------------------

    //LOADING THE SUBMODULES OF THE LAYOUT: or display of the default editor
    public function form_sub_modules($id_disposition) {
        global $DB;

        $form = H::form(['action'=>$this->get_index_relative_path() , 'class'=>$this->css.'submodules' , 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);

            $hidden_id = H::input_hidden(['name'=>$this->ifld_disposition_id, 'value'=>$id_disposition, 'data-alwaysposted'=>1]);
            $title = H::DIV(['class'=>$this->css.'submodules_title module_title'], $this->get_tl('submodules_editor_title'));
            $btn_add = H::submit_button_single(['class'=>$this->css.'btn_add_submodule button_new', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_ADD_SUBMODULE, 'data-parameters'=>[$this->ifld_disposition_id=>$id_disposition]], $this->get_tl('submodules_editor_add'));
        
        $form->add_child( [$hidden_id, $title, $btn_add] );

        $q = 'SELECT DISTINCT * FROM '.$this->bddt_submodules.' WHERE id_disposition=? ORDER BY `sort_order`';
        $list = $DB->prepared_query_list($q, 'i', [$id_disposition]);

        $grid = [];
        foreach ($list as $submodule) {
            $gridItem = [];
            $gridItem['¤params¤'] = ['data-order_parent'=>$this->ifld_submodules_sort_order.'['.$submodule['id'].']'];
            $gridItem[''] = H::button_icon('trash-2', ['type'=>'submit', 'class'=>$this->css.'btn_del button_delete', 'name' => $this->input_action_identifier , 'value'=>$this->ACTION_DELETE_SUBMODULE , 'data-confirm'=>$this->get_tl('confirm_delete_submodule', $submodule['module_name']) , 'data-parameters'=>[ $this->ifld_submodules_id=>$submodule['id'], $this->ifld_disposition_id=>$id_disposition ] ]);
            $gridItem['Name'] = $this->select_module_input($this->ifld_submodules_moduleName.'['.$submodule['id'].']', false, $submodule['module_name']);
            $gridItem['Param'] = H::input_text(['name'=>$this->ifld_submodules_moduleParam.'['.$submodule['id'].']','value'=>$submodule['module_param']]);
            $gridItem['Sub Container Name'] = H::input_text(['name'=>$this->ifld_submodules_moduleSubcontainer.'['.$submodule['id'].']','value'=>$submodule['module_subcontainer']]);
            $gridItem[' '] = H::input_order(['name'=>$this->ifld_submodules_sort_order.'['.$submodule['id'].']', 'value'=>$submodule['sort_order']]);
            array_push($grid, $gridItem);
        }
        $form->add_child(H::simple_data_grid($grid));
        if (count($grid) > 0) {
            $block_btns = H::DIV(['class'=>$this->css.'edit_buttons edit_buttons']);
            $block_btns->add_child(H::submit_button(array('name' => $this->input_action_identifier , 'value'=>$this->ACTION_SAVE_SUBMODULE, 'class'=>$this->css.'button_save button_save submodules'), $this->get_tl('disposition-save')));
            $form->add_child($block_btns);
        }
        return $form;
    }
    //add an empty submodule to the list
    public function add_submodule($post) {
        global $DB;
        $order = $DB->query_value('SELECT MAX(`sort_order`) FROM '.$this->bddt_submodules.' WHERE id_disposition='.$post[$this->ifld_disposition_id]);
        $order++;
        $DB->query('INSERT INTO '.$this->bddt_submodules.' SET id_disposition='.$post[$this->ifld_disposition_id].',module_name="",module_param="", `sort_order`='.$order);
    }
    //delete a submodule
    public function delete_submodule($post) {
        global $DB;
        $q = 'DELETE FROM '.$this->bddt_submodules.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_submodules_id]]);
    }
    //update the chosen module and their parameters
    public function save_submodule($post) {
        global $DB;
        $params = $post[$this->ifld_submodules_moduleParam];
        $subcontainer = $post[$this->ifld_submodules_moduleSubcontainer];
        foreach ($post[$this->ifld_submodules_moduleName] as $submodule_id => $submodule_name) {
            $q = 'UPDATE '. $this->bddt_submodules.' SET module_name=?, module_param=?, module_subcontainer=?, sort_order=? WHERE id=?';
            $success = $DB->prepared_query($q, 'sssii', array($submodule_name , $params[$submodule_id] ,$subcontainer[$submodule_id] , $post[$this->ifld_submodules_sort_order][$submodule_id], $submodule_id));
        }
    }
    
    //----------------------------------------------------------------------------------------------------
    //DISPLAY THE INDICATED LAYOUT OR SEARCH THE DEFAULT LAYOUT
    //----------------------------------------------------------------------------------------------------
    public function process_disposition(&$post) {
        global $DB, $USER, $CONFIG;

        $post['core_insert'] = true;

        //First of all we must check the user

        //login or logout form
        $metas = false;
        if (isset($this->options['metas'])) {
            $metas = $this->options['metas'];
        }
        $output = H::new_document($CONFIG::SITE_NAME.' administration', '', '', $metas);
        $body = $output->find_child('body');
        // include_once('connection/admin/connection_admin_class.php');
        
        if ($USER->connection_state == 0){
            $connectionForm=new Connection();
            $connectionForm->process_data($post);
            $body->add_child($connectionForm->get_output());
        }

        //if connected
        if ($USER->connection_state == 1) {//and admin
            // if ($CONFIG::THEME_ID > 0) {
            //     $q = 'SELECT custom FROM '.$this->build_module_table_name('csseditor', 'theme').' WHERE id='.$CONFIG::THEME_ID;
            //     $custom = json_decode(htmlspecialchars_decode($DB->query_value($q)), true);
            //     $main = $custom[':root']['general'];
            //     $customCss = ':root {'.$main.';}';
            //     $head = $output->find_child('head');
            //     $head->add_child(H::tag(H::STYLE, array('rel'=>'stylesheet' , 'type'=>'text/css', 'title'=>'variableFromPublic'), $customCss));
            // }

            if (!isset($post['id'])) { //no layout was requested
                //search for default admin layout
                $dispositio_admin = $DB->query_value('SELECT DISTINCT id FROM '.$this->bddt_disposition.' WHERE name="admin"');
                if ($dispositio_admin != null) {
                    $body->add_child($this->display_disposition($dispositio_admin, $post));
                } else {
                    //there is no admin layout you have to switch to creation mode
                    $disp_editor = H::DIV(array('id'=>self::module_name.'_admin_container_disposition'.$this->dom_id , 'class'=>$this->css.'disposition'));
                    // $disp_editor->add_child($this->process_data_disposition_editor($post));
                    $body->add_child($disp_editor);
                    //$output->add_child($this->get_output());
                }
            } else {
                //a layout is requested
                $body->add_child($this->display_disposition($post['id'], $post));
            }
            
            if ($this->preview) {
                $div = H::DIV(['class'=>$this->css.'container_preview']);
                $iframe = H::tag('IFRAME', ['name'=>'preview_public', 'class'=>'preview_public', 'id'=>'preview_public'.$this->dom_id, 'src'=>$CONFIG::BASE_URL.'index.php', 'frameborder'=>0, 'sandbox'=>"allow-same-origin allow-scripts"]);
                $div->add_child($iframe, 'preview_public');
                $body->add_child($div);
            }

            // add variable admin folder to js
            $script = H::script('H_constants.admin_folder = "'.$CONFIG::ADMIN_FOLDER.'";', ['autoremove'=>true]);
            $body->add_child($script);
            
            // if (is_file($CONFIG::HOME_FOLDER.'admin/koldron.js')) {
            //     $body->add_child(H::script('', ['src'=>'koldron.js']));
            //     $body->add_child(H::script('spd2_koldron.texts = {"choix_mod": "'.$this->get_tl('choix_mod').'"}'));
            // }
        }

        return $output;
    }

    //layout display
    public function display_disposition($id, $post) {
        global $DB,$module_html_content,$CONFIG;

        $original_post = $post;

        $displayModules=H::group('modules');
        //this good old friend 'le main'
        $diplayIndexable=H::DIV(array('id'=>'lemain' , 'class'=>'le_main'));
        //we pick the indexable module 
        $indexable=$DB->query_line('SELECT * FROM '.$this->bddt_disposition.' WHERE id='.$id);
        if ($indexable!=null) {
            //check to see if the indexable module is in the config
            foreach ($this->MODULE_LIST as $moduleName => $module_data) {
                if ($moduleName == $indexable['module_indexable']) {
                    //he is in the config, we can display it

                    $_POST = $original_post;

                    if ($indexable['module_param']!='') {
                        //we passed a query in get mode, we explode this
                        if (strpos($indexable['module_param'], '=') !== false) {
                            parse_str($indexable['module_param'], $_POST);
                        } else {
                            //we did not pass a query so we just call the param set in conf
                            $_POST[$module_data['admin_param']] = $indexable['module_param'];
                        }
                    }
                    $_POST['core_insert'] = true;

                    $module_html_content[$moduleName] = '';

                    include($CONFIG::ADMIN_FOLDER.$moduleName.'/index.php');

                    $diplayIndexable->add_child($module_html_content[$moduleName]);
                }
            }
        }

        $displayModules->add_child($diplayIndexable);
        $subcontainers=[];
        //now we take care of the submodules
        $q = 'SELECT DISTINCT * FROM '.$this->bddt_submodules.' WHERE id_disposition=? ORDER BY `sort_order`';
        $submodules = $DB->prepared_query_list($q, 'i', [$id]);
        if ($submodules!=null) {
            foreach ($submodules as $submodule) {
                //check to see if the module is in conf
                foreach ($this->MODULE_LIST as $moduleName => $module_data) {
                    if ($moduleName == $submodule['module_name'] && $moduleName != 'core') {
                        //it is in the config, it is displayed.

                        $_POST = $original_post;

                        if ($submodule['module_param']!='') {
                            //we passed a query in get mode, we explode this
                            if (strpos($submodule['module_param'], '=') !== false) {
                                parse_str($submodule['module_param'], $_POST);
                            } else {
                                //we did not pass a query so we just call the param set in conf
                                $_POST[$module_data['admin_param']] = $submodule['module_param'];
                            }
                        }
                        $_POST['core_insert'] = true;

                        $module_html_content[$moduleName] = '';

                        include($CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER.$moduleName.'/index.php');
                        // Utils::error_log($module_html_content[$moduleName]);
                        if (isset($submodule['module_subcontainer']) && $submodule['module_subcontainer']!='') {
                            if (!isset($subcontainers[$submodule['module_subcontainer']])) {
                                $subcontainers[$submodule['module_subcontainer']]=H::DIV(array('id'=>$submodule['module_subcontainer'] , 'class'=>'subcontainer '.$submodule['module_subcontainer']));
                                $displayModules->add_child($subcontainers[$submodule['module_subcontainer']]);
                            }
                            $subcontainers[$submodule['module_subcontainer']]->add_child($module_html_content[$moduleName]);
                        }else{
                            $displayModules->add_child($module_html_content[$moduleName]);
                        }
                    }
                }
            }
        }

        $_POST = $original_post;

        return $displayModules;
    }

    public function display_one_module(&$post) {
        global $CONFIG, $module_html_content;
        $post['core_insert']=true;
        $original_post = $post;
        foreach ($this->MODULE_LIST as $moduleName => $module_data) {
            if (isset($post[$moduleName])) {
                //it is in the config it is displayed. 
                $_POST = $original_post;
                if ($_POST[$moduleName]!='' && $moduleName != 'core') {
                    //in this mode there is no query we only pass the default param
                    $_POST[$module_data['admin_param']]=$_POST[$moduleName];
                }

                $module_html_content[$moduleName] = '';

                include($CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER.$moduleName.'/index.php');
                return $module_html_content[$moduleName];
            }
        }
    }
}