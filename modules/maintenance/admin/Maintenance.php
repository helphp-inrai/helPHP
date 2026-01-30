<?php
/**
 * COPYRIGHT M666 moi@myke666.fr 40600 BISCARROSSE FRANCE 2009-2020, STEUZ 65000 TARBES 2024
 * ALL RIGHTS RESERVED
 * TOUS DROITS RESERVES
 * THIS CODE CAN'T BE DUPLICATED OR MODIFY WITHOUT M666 moi@myke666.fr AGREEMENT
 * CE CODE NE PEUT PAS ETRE DUPLIQUE OU MODIFIE SANS L'ACCORD D'M666 moi@myke666.fr
 */
namespace helPHP\modules\maintenance\admin;

use helPHP\libs\Ajax;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\modules\media\admin\Media as Media_ui;
use helPHP\libs\Utils;
use helPHP\modules\config\admin\Config;
use helPHP\modules\csseditor\admin\Csseditor;

class Maintenance extends HelPHP_module {

    const module_name = 'maintenance';

    protected $ACTION_EXECUTE = self::module_name.'_execute';
    protected $ACTION_UPLOAD = self::module_name.'_upload';
    protected $ACTION_PATCH_FILES_FOLDERS = self::module_name.'_files_folders';
    protected $ACTION_PATCH_SQL_SCHEMA = self::module_name.'_sql_schema';
    protected $ACTION_PATCH_SQL_DATA = self::module_name.'_sql_data';
    protected $ACTION_INSTALL_MODULE = self::module_name.'_install';
    protected $ACTION_REMOVE_MODULE = self::module_name.'_remove';
    protected $ACTION_MODULE_UNINSTALL = self::module_name.'_uninstall';

    protected $ACTION_CHECK_CSS = self::module_name.'_check_css';
    protected $ACTION_UPDATE_CSS = self::module_name.'_update_css';
    protected $ACTION_KEEP_RULE_CSS = self::module_name.'_keep_rule';

    protected $ACTION_PROCESS_BLOCK = self::module_name.'_process_block';

    protected $utils_path;

    protected $actions = ['minify', 'constants', 'backup'];

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);

        global $CONFIG, $CONFIG_DB;
        $this->utils_path = $CONFIG::HELPHP_FOLDER.'utils/';

        if ($CONFIG_DB::MASTER_SLAVE_MODE){
            array_push($this->actions, 'check_replica');
        }
    }

    public function process_data(&$post, $toreturn=false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $USER;
        if (!$USER->admin){
            $this->add_error('not_admin');
            $this->reload_after_message = true;
            return;
        }

        $master_output = H::group('users_display');
        if (str_starts_with($post[$this->input_action_identifier],'splited')){
            $t=explode('-',$post[$this->input_action_identifier]);
            $post[$this->input_action_identifier]=$t[1];
            $post['folder']=$t[2];
        }
        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_EXECUTE:
                $master_output->add_child( $this->execute($post) );
            break;
            case $this->ACTION_PATCH_FILES_FOLDERS:
                $master_output->add_child( $this->patch_files_folders($post['folder']) );
                $master_output->add_child( $this->display_patch($post) );
            break;
            case $this->ACTION_PATCH_SQL_SCHEMA:
                $master_output->add_child( $this->patch_sql_schema($post['folder']) );
                $master_output->add_child( $this->display_patch($post) );
                
            break;
            case $this->ACTION_PATCH_SQL_DATA:
                $master_output->add_child( $this->patch_sql_data($post['folder']) );
                $master_output->add_child( $this->display_patch($post) );
            break;
            case $this->ACTION_UPLOAD:
                $master_output->add_child($this->upload_process($post));
            break;
            case $this->ACTION_INSTALL_MODULE:
                $master_output->add_child( $this->install_module($post['folder']) );
            break;
            case $this->ACTION_MODULE_UNINSTALL:
                $master_output->add_child( $this->uninstall_module($post) );
            break;
            case $this->ACTION_REMOVE_MODULE:
                $master_output->add_child( $this->remove_module($post['folder']) ); 
            break;
            
            case $this->ACTION_CHECK_CSS:
                $master_output->add_child( $this->check_css_source($post) );
            break;
            case $this->ACTION_UPDATE_CSS:
                $master_output->add_child( $this->update_css_source($post) );
            break;
            case $this->ACTION_KEEP_RULE_CSS:
                $master_output->add_child( $this->keep_rule_css($post) );
            break;

            case $this->ACTION_PROCESS_BLOCK:
                $master_output->add_child( $this->import_block($post) );
            break;

            default:
                $master_output->add_child( $this->display($post) );
            break;
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    // protected $actions = ['minify', 'constants', 'check_slaves', 'backup'];
    
    
    public function display($post){
        global $CONFIG;

        $tab_title = [];
        $tab_content = [];

        // tab general
        $output = H::group('tab_general');

            $info = H::DIV(['class'=>$this->css.'info'], $this->get_tl('info'));

        $output->add_child($info);

        foreach($this->actions as $key => $action){
            $block = H::DIV(['class'=>$this->css.'block_action '.$action]);
                $info = H::DIV(['class'=>$this->css.'info_action '.$action], $this->get_tl('info_'.$action));
                $btn = H::BUTTON(['class'=>$this->css.'btn_action '.$action, 'id'=>self::module_name.'_btn_action_'.$action.$this->dom_id], $this->get_tl('btn_'.$action));
            $block->add_child([$info,$btn]);
            $output->add_child($block);
        }


        $block = H::DIV(['class'=>$this->css.'block_action update_css']);
            $info = H::DIV(['class'=>$this->css.'info_action update_css'], $this->get_tl('info_update_css'));
            $params = [$this->input_action_identifier=>$this->ACTION_CHECK_CSS, 'dom_id'=>$this->dom_id];
            $btn = H::BUTTON(['class'=>$this->css.'btn_action update_css', 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($params).');'], $this->get_tl('btn_update_css'));
        $block->add_child([$info,$btn]);
        $output->add_child($block);

        array_push($tab_title, $this->get_tl('tab_general'));
        array_push($tab_content, $output);

        // tab patch
        array_push($tab_title, $this->get_tl('tab_patch'));
        array_push($tab_content, $this->display_patch($post));

        // tab module
        array_push($tab_title, $this->get_tl('tab_module'));
        array_push($tab_content, $this->display_module($post));

        // tab config
        $output = H::group('tab_config');
            
            $module_c = new \helPHP\modules\config\admin\Config();
            $t = ['core_insert'=>1];
            $cnt = $module_c->process_data($t, true);

        $output->add_child( $cnt );

        array_push($tab_title, $this->get_tl('tab_config'));
        array_push($tab_content, $output);

        $tab = H::tabs([], $tab_title, $tab_content);
        
        // $receiver_result = H::DIV(['class'=>$this->css.'receiver_result', 'id'=>self::module_name.'_receiver_result'.$this->dom_id]);
        $script = H::script('helphp_timeout(\'Maintenance_a.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($this->actions)).');\');', ['autoremove'=>1]);

        return [$tab, $script];
    }
    public function display_patch($post){
        $output = H::group('tab_patch');

            $block = H::DIV(['class'=>$this->css.'block_patch']);

                $label = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('upload_patches'));
                // $input_file = H::input_file(['class'=>$this->css.'upload']);
                $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);
                    $params = ['submit'=>true, 'accept'=>'.zip', 'options'=>false];
                    $hidden_action = H::input_hidden(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_UPLOAD]);
                $form->add_child( [Media_ui::display('uploader', $params, 'test_media-image',1), $hidden_action] );

            $block->add_child([$label,$form]);

        $output->add_child($block);

        $output->add_child($this->check_patches());

        return $output;
    }
    public function display_module($post){
        global $CONFIG;

        $output = H::group('tab_module');

        $output->add_child($this->check_module());
                
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);
                $labelinst = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('module_uninstall_section'));
                $selected_id = isset($post[$this->ifld_module_to_uninstall]) ? $post[$this->ifld_module_to_uninstall] : 0;
                $module_list=[];
                $module_id=0;
                $tmodule=$CONFIG::MODULES_LIST;
                ksort($tmodule,SORT_STRING);
                foreach($tmodule as $name => $module){
                    array_push($module_list,['key'=>$module_id,'name'=>$name]);
                    $module_id++;
                }
                $opts_data = array('first_empty'=>true , 'value_key'=>'name' , 'label_key'=>'name' , 'options'=>$module_list);
                $select = H::select(array('id'=>$this->css.'select_root'.$this->dom_id, 'name'=>$this->ifld_module_to_uninstall, 'label'=>$this->get_tl('tlc_select')), $opts_data, $selected_id);
                $checked = (isset($post[$this->ifld_keep_files]) && $post[$this->ifld_keep_files] == 1) ? true : false;
                $keep_files=H::input_checkbox(['name'=>$this->ifld_keep_files, 'label'=>$this->get_tl('keep_files'), 'value'=>1, 'class'=>'inp_check', 'checked'=>$checked]);
                $button_action=H::submit_button(array('class'=>$this->css.'btn','name'=>$this->input_action_identifier, 'value'=>$this->ACTION_MODULE_UNINSTALL, 'title'=>$this->get_tl('module_uninstall')), $this->get_tl('module_uninstall'));
            $form->add_child([$labelinst,$select,$keep_files->label_tag(),$keep_files,$button_action]);

        $output->add_child($form);
        
        return $output;
    }

    public function install_module($name) {
        GLOBAL $CONFIG,$DB;
        include_once($CONFIG::HELPHP_FOLDER.'utils/install_module.php');
        $res = install_module($CONFIG::HOME_FOLDER, $name);
        if (isset($res['err'])){
            $this->add_error($res['err'], $res['params']);
        } else {
            $this->add_message('install_module_success');
        }
        
        // adding module to hierarchy admin
        $hierarchy = new \helPHP\modules\hierarchy\admin\Hierarchy();
        $hierarchy->add_item($name, true);

        // installing css public and admin
        $modules_folder = $CONFIG::HELPHP_FOLDER.'modules/';
        $css_paths = [$modules_folder.$name.'/admin/'.$name.'.css', $modules_folder.$name.'/public/'.$name.'.css'];
        foreach($css_paths as $path){
            if (!file_exists($path)) continue;
            $admin = str_contains($path, 'admin') ? 1 : 0;
            $md5 = \md5_file($path);
            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="module", path=?, md5=?, admin='.$admin;
            $DB->prepared_query($q, 'ss', [$path, $md5]);
            $id_source = $DB->last_insert_id();
            \helPHP\modules\csseditor\admin\Csseditor::import_css_source($path, $id_source, true);
        }

        // reload config
        // for now we cant refresh config.php content. So we can't modify on the fly the modules_list constant.
        // so force reload of the tab to ensure that the right display of installed/uninstalled module is displayed.
        // return;
        return $this->display_module([]);
        // return H::script('//h.main_tab.refresh_active();');
    }
    public function remove_module($name) {
        global $CONFIG;
        $modules_path = $CONFIG::HELPHP_FOLDER.'modules/';
        if ($name!='' && is_dir($modules_path.$name)) { $res = shell_exec('rm -r "'.$modules_path.$name.'"'); }
        $this->add_message('remove_module_success');

        // reload config
        // for now we cant refresh config.php content. So we can't modify on the fly the modules_list constant.
        // so force reload of the tab to ensure that the right display of installed/uninstalled module is displayed.
        // return;
        return $this->display_module([]);
        // return H::script('h.main_tab.refresh_active();');
    }
    public function uninstall_module($post) {
        GLOBAL $CONFIG;
        include_once($CONFIG::HELPHP_FOLDER.'utils/uninstall_module.php');
        $res = uninstall_module($CONFIG::HOME_FOLDER, $post[$this->ifld_module_to_uninstall] ,isset($post[$this->ifld_keep_files]));
        if (isset($res['err'])){
            $this->add_error($res['err'], $res['params']);
        } else {
            $this->add_message('uninstall_module_success');
        }

        // reload config
        // for now we cant refresh config.php content. So we can't modify on the fly the modules_list constant.
        // so force reload of the tab to ensure that the right display of installed/uninstalled module is displayed.
        return $this->display_module([]);
        // return H::script('//h.main_tab.refresh_active();');
    }
    public function check_module() {
        global $CONFIG, $FS;

        $modules_path = $CONFIG::HELPHP_FOLDER.'modules/';

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_modules']);

            $info = H::DIV(['class'=>$this->css.'info_module'], $this->get_tl('uninstalled_module_found'));

        $form->add_child( $info );

            // seaching for module folders that are not installed.
            $module_folder_listing = $FS->shell_ls($modules_path);
            $div = H::DIV(['class'=>$this->css.'modules_list']);
            foreach($module_folder_listing['folders'] as $key => $folder){
                if (!isset($CONFIG::MODULES_LIST[$folder['name']])){
                    $btn_install = H::submit_button(['class'=>$this->css.'btn_action install', 'name'=>$this->input_action_identifier, 'value'=>'splited-'.$this->ACTION_INSTALL_MODULE.'-'.$folder['name'], 'title'=>$this->get_tl('install_this_module', $folder['name'])], $this->get_tl('install_this_module', $folder['name']));
                    $btn_remove = H::submit_button(['class'=>$this->css.'btn_action remove','name'=>$this->input_action_identifier, 'value'=>'splited-'.$this->ACTION_REMOVE_MODULE.'-'.$folder['name'], 'title'=>$this->get_tl('remove_this_module', $folder['name'])], $this->get_tl('remove_this_module', $folder['name']));
                    $div->add_child( [$btn_install, $btn_remove] ); 
                }
            }

        $form->add_child( $div );

        return $form;
    }

    public function check_patches(){
        global $CONFIG, $FS;

        $temp_path = $CONFIG::HOME_FOLDER.'temp/';
        if (!is_dir($temp_path.'patches')) {
            // no patches, nothing to do
            return;
        }


        // seaching for zip files.
        $tree_listing = $FS->recurse_ls($temp_path.'patches');
        foreach ($tree_listing['files'] as $key => $file) {
            if($FS->get_file_ext($file) == 'zip'){
                // we got a zip... must unpack
                $FS->unpack($temp_path.'patches/'.$FS->get_file_name($file), $temp_path.'patches', '', false, true);
                $FS->delete($temp_path.'patches/'.$FS->get_file_name($file));
            }
        }

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_upload_patch']);

        $tree_listing = $FS->shell_ls($temp_path.'patches');
        foreach ($tree_listing['folders'] as $key => $folder) {

            if ($this->check_folder_empty($temp_path.'patches/'.$folder['name'])){
                //cleaning empty patch folders...
                rmdir($temp_path.'patches/'.$folder['name']);
                continue;
            }
            
            // folder not empty searching for possible actions...

            $sql_schema = false;
            $sql_data = false;
            $files_folder_to_cp = false;
            
            $patch_listing = $FS->shell_ls($temp_path.'patches/'.$folder['name']);
            foreach($patch_listing['files'] as $fkey => $file){
                //searching for files
                if ($file['name'] == "schema.sql" && $folder['name'] == 'instance'){
                    $sql_schema = true;
                }
                if ($file['name'] != "schema.sql" && $FS->get_file_ext($file['name']) == 'sql' && $folder['name'] == 'instance'){
                    $sql_data = true;
                }
                if ($FS->get_file_ext($file['name']) != 'sql'){
                    $files_folder_to_cp = $folder['name'];
                }
            }

            if (count($patch_listing['folders']) > 0){
                $files_folder_to_cp = $folder['name'];
            }

            if($files_folder_to_cp) {
                if ($files_folder_to_cp == "framework") $button_ttl = $this->get_tl('patch_framework', $folder['name']);
                else if ($files_folder_to_cp == "instance") $button_ttl = $this->get_tl('patch_instance', $folder['name']);
                else if ($files_folder_to_cp == "block") $button_ttl = $this->get_tl('patch_block', $folder['name']);
                else $button_ttl = $this->get_tl('patch_module', $folder['name']);
                $button_action = H::submit_button_single(['class'=>$this->css.'btn', 'name'=>$this->input_action_identifier, 'value'=>'splited-'.$this->ACTION_PATCH_FILES_FOLDERS.'-'.$folder['name']], $button_ttl);
                // $button_action = H::submit_button_single(['class'=>$this->css.'btn', 'name'=>$this->input_action_identifier, 'value'=>'splited-'.$this->ACTION_PATCH_FILES_FOLDERS.'-'.$folder['name']], $this->get_tl('patch_'.$files_folder_to_cp, $folder['name']));
                $form->add_child($button_action); 
            }

            if($sql_schema) {
                $button_action = H::submit_button_single(['class'=>$this->css.'btn', 'name'=>$this->input_action_identifier, 'value'=>'splited-'.$this->ACTION_PATCH_SQL_SCHEMA.'-'.$folder['name'], 'title'=>$this->get_tl('patch_schema', [$folder['name']])], $this->get_tl('patch_schema', [$folder['name']]));
                $form->add_child($button_action); 
            }

            if($sql_data) {
                $button_action = H::submit_button_single(['class'=>$this->css.'btn', 'name'=>$this->input_action_identifier, 'value'=>'splited-'.$this->ACTION_PATCH_SQL_DATA.'-'.$folder['name'], 'title'=>$this->get_tl('patch_data', [$folder['name']])], $this->get_tl('patch_data', [$folder['name']]));
                $form->add_child($button_action); 
            }

        }
        
        return $form;
    }

    public function patch_files_folders($folder) {
        global $CONFIG, $FS;

        $debug = false; 
        $error = 0;

        $output = H::group('result_patch');

        $temp_path = $CONFIG::HOME_FOLDER.'temp/patches/';
        if ($folder == "framework"){
            $target_path = $CONFIG::HELPHP_FOLDER;
            $patch_mode = "framework";
        } else if ($folder == 'instance'){
            $target_path = $CONFIG::HOME_FOLDER;
            $patch_mode = "instance";
        } else if ($folder == 'block'){
            // it's a block
            $patch_mode = "block";
        } else {
            //a module patch can contain 3 folders :
            //framework : contain all that should go in framework folder
            //admin & public : contain what should go in instance admin and public folder not in the framework (some special files...)
            $module_name = $folder;
            $patch_mode = "module";
            return $this->patch_module($module_name);
        }

        // for each css source store the rules that have been modified by the user to display a choice between those rules and the new one
        $css_edited_found = [];

        if ($patch_mode == 'block') {
            $params = [$this->input_action_identifier=>$this->ACTION_PROCESS_BLOCK];
            return H::script('H_ui.open_popup_modal({}, "'.self::module_name.'", '.json_encode($params).');');
        }

        if ($patch_mode == "framework" || $patch_mode == "instance") {
            //parsing everything
            $tree_listing = $FS->recurse_ls($temp_path.$folder);
            foreach ($tree_listing['folders'] as $key => $nfolder) {
                //first pass to create missing folders and check permissions
                // echo $nfolder.'<br>';
                $tpath = ($patch_mode == "instance") ? $this->valid_instance_admin_path($target_path.$nfolder) : $target_path.$nfolder;
                if (!is_dir($tpath) && $this->check_module_path_is_valid($tpath, $patch_mode)){
                    if (!$debug) {
                        $res = shell_exec('mkdir -p "'.$tpath.'"');
                        shell_exec('chmod -R 2774 '.$tpath);
                    }
                    if (!is_dir($tpath)){
                        $error++;
                        $output->add_child( $this->get_tl('error_creating_folder', $tpath) );
                    }else{
                        $output->add_child( $this->get_tl('folder_created', $tpath) );
                    }
                }   
            }

            $files_to_mv = array();
            $json_name_array = array(
                'missing_folders.json',
                'folders_to_delete.json',
                'files_to_delete.json'
            );

            foreach ($tree_listing['files'] as $key => $nfile) {
                
                //second pass to check for json files and files to cp.
                if ($FS->get_file_ext($nfile) == 'json' && in_array($FS->get_file_name($nfile), $json_name_array)){
                    
                    //got a json to manipulate
                    //loading it;
                    $json = file_get_contents($temp_path.$folder.'/'.$nfile);
                    $json = json_decode($json, true);

                    //missing folders
                    if ($FS->get_file_name($nfile) == 'missing_folders.json'){
                        foreach($json as $jkey => $jfolder){
                            $tpath = ($patch_mode == "instance") ? $this->valid_instance_admin_path($target_path.$jfolder) : $target_path.$jfolder;
                            if (!is_dir($tpath) && $this->check_module_path_is_valid($tpath, $patch_mode)){
                                if (!$debug) {
                                    $res = shell_exec('mkdir -p "'.$tpath.'"');
                                    shell_exec('chmod -R 2774 '.$tpath);
                                }
                                if (!is_dir($tpath)) {
                                    $error++;
                                    $output->add_child( $this->get_tl('error_creating_folder', $tpath) );
                                }else{
                                    $output->add_child( $this->get_tl('folder_created', $tpath) );
                                }
                            }
                        }
                    }

                    //folder to delete
                    if ($FS->get_file_name($nfile)=='folders_to_delete.json'){
                        foreach($json as $jkey => $jfolder){
                            $tpath=($patch_mode=="instance")?$this->valid_instance_admin_path($target_path.$jfolder):$target_path.$jfolder;
                            if (is_dir($tpath) && $this->check_module_path_is_valid($tpath,$patch_mode)){
                                if (!$debug) { $res = shell_exec('rm -r "'.$tpath.'"'); }
                                if (is_dir($tpath)){
                                    $error++;
                                    $output->add_child( $this->get_tl('error_folder',$tpath) );
                                }else{
                                    $output->add_child( $this->get_tl('folder_deleted',$tpath) );
                                }
                            }
                        }
                    }

                    //files to delete
                    if ($FS->get_file_name($nfile) == 'files_to_delete.json'){
                        foreach($json as $jkey => $jfile){
                            $tpath = ($patch_mode == "instance") ? $this->valid_instance_admin_path($target_path.$jfile) : $target_path.$jfile;
                            if (is_file($tpath) && $this->check_module_path_is_valid($tpath, $patch_mode)){
                                if (!$debug) $res = shell_exec('rm "'.$tpath.'"');
                                if (is_file($target_path.$jfile)){
                                    $error++;
                                    $output->add_child( $this->get_tl('error_file',$target_path.$jfile) );
                                }else{
                                    $output->add_child( $this->get_tl('file_deleted',$target_path.$jfile) );
                                }
                            }
                        }
                    }

                    if ($error>0) {
                        if (!$debug) exit();
                    }

                }else{
                    $tpath = ($patch_mode == "instance") ? $this->valid_instance_admin_path($target_path.$nfile) : $target_path.$nfile;
                    if ($this->check_module_path_is_valid($tpath, $patch_mode)){
                        $output->add_child( 'File to copy : '.$tpath.' <br>' );
                        $files_to_mv[$nfile] = $tpath;
                        //we'll move those files after foreach main loop
                    }
                }
            }
            
            if (count($files_to_mv) > 0){
                foreach($files_to_mv as $new_file => $target_file){
                    if (is_file($target_file)) {
                        if (!$debug) {
                            $res = unlink($target_file);
                        }
                        if (is_file($target_file)){
                            $error++;
                            $output->add_child( $this->get_tl('error_file', $target_file) );
                            if (!$debug) exit();
                        }else{
                            if (!$debug && $this->compare_version($temp_path.$folder.'/'.$new_file, $target_file)) {
                                $res = shell_exec('mv "'.$temp_path.$folder.'/'.$new_file.'" "'.$target_file.'"');
                                shell_exec('chmod 2774 '.$target_file);
                                
                                $res = $this->detect_css($target_file, $patch_mode);
                                if ($res){
                                    array_push($css_edited_found, $res);
                                }
                            }
                        }
                    }else{
                        //  echo 'cp "'.$temp_path.$new_file.'" "'.$target_file.'"<br>';
                        if (!$debug) {
                            $res = shell_exec('mv "'.$temp_path.$folder.'/'.$new_file.'" "'.$target_file.'"');
                            shell_exec('chmod 2774 '.$target_file);
                            $res = $this->detect_css($target_file, $patch_mode);
                            if ($res){
                                array_push($css_edited_found, $res);
                            }
                        }
                    }
                }
            }
        }

        if ($error==0){
            //cleaning patch
            if (!$debug) $res = shell_exec('rm -r "'.$temp_path.$folder.'"');
            $output->add_child( 'Patch applied and cleaned !<br>' );

            if ($css_edited_found){
                $js = $this->inst_js.'.display_css_edited(`'.$this->display_css_edited($css_edited_found).'`);';
                $output->add_child( H::script($js, ['autoremove'=>1]) );
                return $output;
            }
        }
        
        return H::script('H_ui.message_popup("'.$output.'");');
    }

    public function patch_module($name){
        global $CONFIG, $FS;

        $temp_path = $CONFIG::HOME_FOLDER.'temp/patches/';

        //it can be a new module to install or to upgrade !
        $installable = true;
        if (is_file($temp_path.$name.'/upgrade.php')){
            $installable = (isset($CONFIG::MODULES_LIST[$name])) ? true : false;
        }

        $error = 0;
        $output = H::group('patch_module');

        if ($installable){
            //parsing everything
            $tree_listing = $FS->recurse_ls($temp_path.$name);
            foreach ($tree_listing['folders'] as $key => $nfolder) {
                //first pass to create missing folders and check permissions
                $tpath = $this->module_target_path($name.'/'.$nfolder);
                if (!is_dir($tpath)){
                    $res = shell_exec('mkdir -p "'.$tpath.'"');
                    shell_exec('chmod -R 2774 "'.$tpath.'"');
                    if (!is_dir($tpath)){
                        $error++;
                        $output->add_child( $this->get_tl('error_creating_folder',$tpath) );
                    }else{
                        $output->add_child( $this->get_tl('folder_created',$tpath) );
                    }
                }
            }

            $files_to_mv = array();
            $json_name_array = array(
                'missing_folders.json',
                'folders_to_delete.json',
                'files_to_delete.json'
            );

            foreach ($tree_listing['files'] as $key => $file) {
                // check for json files and files to cp.
                if (in_array($FS->get_file_name($file), $json_name_array)){
                    
                    // got a json to manipulate
                    // loading it
                    $json = file_get_contents($temp_path.$name.'/'.$file);
                    $json = json_decode($json,true);
                    
                    //missing folders
                    if ($FS->get_file_name($file) == 'missing_folders.json'){
                        foreach($json as $jkey => $jfolder){
                            $tpath = $this->module_target_path($name.'/'.$jfolder);
                            if (!is_dir($tpath)){
                                $res = shell_exec('mkdir -p "'.$tpath.'"');
                                shell_exec('chmod -R 2774 "'.$tpath.'"');
                                if (!is_dir($tpath)){
                                    $error++;
                                    $output->add_child( $this->get_tl('error_creating_folder',$tpath) );
                                }else{
                                    $output->add_child( $this->get_tl('folder_created', $tpath) );
                                }
                            }
                        }
                    }

                    //folder to delete
                    if ($FS->get_file_name($file) == 'folders_to_delete.json'){
                        foreach($json as $jkey => $jfolder){
                            $tpath = $this->module_target_path($name.'/'.$jfolder);
                            if (is_dir($tpath)){
                                $res = shell_exec('rm -r "'.$tpath.'"');
                                if (is_dir($tpath)){
                                    $error++;
                                    $output->add_child( $this->get_tl('error_folder',$tpath) );
                                }else{
                                    $output->add_child( $this->get_tl('folder_deleted',$tpath) );
                                }
                            }
                        }
                    }

                    //files to delete
                    if ($FS->get_file_name($file) == 'files_to_delete.json'){
                        foreach($json as $jkey => $jfile){
                            $tpath = $this->module_target_path($name.'/'.$jfile);
                            if (is_file($tpath)){
                                $res = shell_exec('rm "'.$tpath.'"');
                                if (is_file($tpath)){
                                    $error++;
                                    $output->add_child( $this->get_tl('error_file',$tpath) );
                                }else{
                                    $output->add_child( $this->get_tl('file_deleted',$tpath) );
                                }
                            }
                        }
                    }

                    if ($error == 0){
                        //cleaning passed json
                        unlink($temp_path.$name.'/'.$file);
                    }

                }else{
                    $tpath = $this->module_target_path($name.'/'.$file);
                    if ($tpath) {
                        $output->add_child( 'File to copy : '.$tpath.' <br>' );
                        if ($tpath) $files_to_mv[$file] = $tpath;
                    }
                }
            }
            
            // for each css source store the rules that have been modified by the user to display a choice between those rules and the new one
            $css_edited_found = [];
            if (count($files_to_mv) > 0){
                foreach($files_to_mv as $new_file => $target_file){
                    // echo $new_file.'->'.$target_file.' <br>';
                    if (is_file($target_file)){
                        unlink($target_file);
                        if (is_file($target_file)){
                            $error++;
                            $output->add_child( $this->get_tl('error_file',$target_file) );
                        } else if ($this->compare_version($temp_path.$name.'/'.$new_file, $target_file)) {
                            $res = shell_exec('mv "'.$temp_path.$name.'/'.$new_file.'" "'.$target_file.'"');
                            shell_exec('chmod 2774 '.$target_file);
                            $res = $this->detect_css($target_file, 'module');
                            if ($res){
                                array_push($css_edited_found, $res);
                            }
                        } else {
                            // version incorrect
                            $error++;
                            $output->add_child( $this->get_tl('error_version',$target_file) );
                        }
                    }else{
                        $res = shell_exec('mv "'.$temp_path.$name.'/'.$new_file.'" "'.$target_file.'"');
                        shell_exec('chmod 2774 '.$target_file);
                        $res = $this->detect_css($target_file, 'module');
                        if ($res){
                            array_push($css_edited_found, $res);
                        }
                    }
                }
            }
        }

        if (is_file($temp_path.$name.'/upgrade.php')){
            //include to execute
            $output->add_child( 'Finishing upgrading module '.$name.'<br>' );
            include($temp_path.$name.'/upgrade.php');
        }
        
        if ($css_edited_found){
            $js = $this->inst_js.'.display_css_edited(`'.$this->display_css_edited($css_edited_found).'`);';
            $output->add_child( H::script($js, ['autoremove'=>1]) );
            return $output;
        }

        return H::script('H_ui.message_popup("'.$output.'");');
    }

    public function check_module_path_is_valid($path,$mode){
        //check if the path given is corresponding to a module installed in the framework or in an instance
        //return true if the path is not in module folders or if the module is configured !
        GLOBAL $CONFIG;
        if ($mode=="framework") {
            $t=explode('/',$path);
            if ($t[0]=="modules") {
                return (isset($CONFIG::MODULES_LIST[$t[1]]))?true:false;
            }else{
                return true;
            }
        }
        //this test must be called after valid_instance_admin_path !
        if ($mode=="instance") {
            $t=explode('/',$path);
            if ($t[0]==$CONFIG::ADMIN_FOLDER || $t[0=="public"]) {
                return (isset($CONFIG::MODULES_LIST[$t[1]]))?true:false;
            }else{
                return true;
            }
        }
    }

    public function valid_instance_admin_path($path){
        //check if the path given is in admin, in this case switch the admin folder, to whom fixed in config.
        GLOBAL $CONFIG;
        $t=explode('/',$path);
        if ($t[0]=="admin") {
            $t[0]=rtrim($CONFIG::ADMIN_FOLDER,'/');
            return implode('/',$t);
        }else{
            return $path;
        }

    }

    public function module_target_path($path){
        //retun the correct target path with an ugly revert because the name of the module is passed as first folder name. 
        //the secondary can be "framework/admin/public" and come from tree_listing. more easy to manage during patch creation
        GLOBAL $CONFIG;
        $t = explode('/',$path);
        $module_name = $t[0];
        if ($t[1] == "admin") {
            $t[0] = rtrim($CONFIG::HOME_FOLDER.$CONFIG::ADMIN_FOLDER,'/');
            $t[1] = $module_name;
            return implode('/',$t);
        }
        if ($t[1] == "public") {
            $t[0] = $CONFIG::HOME_FOLDER.'public';
            $t[1] = $module_name;
            return implode('/',$t);
        }
        if ($t[1] == "framework") {
            $t[0] = $CONFIG::HELPHP_FOLDER.'modules';
            $t[1] = $module_name;
            return implode('/',$t);
        }
    }

    public function patch_sql_schema($folder){
        global $CONFIG_DB, $CONFIG, $DB, $FS;

        $temp_path = $CONFIG::HOME_FOLDER.'temp/';

        $sql = file_get_contents($temp_path.'patches/'.$folder.'/schema.sql');
        //replace prefixes :
        $sql=str_replace('*pre*_',$CONFIG_DB::DB_TABLE_PREFIX.'_', $sql);
        //splitting by lines 
        $sql_tab = explode(PHP_EOL, $sql);
        array_pop($sql_tab);

        $failed = false;
        foreach($sql_tab as $key => $sql_line){
            if ($sql_line == '') continue;
            $sql_line = rtrim($sql_line,';');
            $test = $DB->query('START TRANSACTION');
            $test = $DB->query($sql_line);
            if ($test === false) {
                Utils::error_log('Error :'.$DB->last_error.PHP_EOL.'on sql query :'.$sql_line.$key.PHP_EOL.'Operation stopped !');
                Utils::error_log($sql_line);
                $DB->query('ROLLBACK');
                $failed = $key;
                break;
            }else{
                $DB->query('COMMIT');
            }
        }
        
        if ($failed !== false){
            //we save the queries not done.
            $to_save = '';
            for($p = $failed; $p <= count($sql_tab); $p++){
                $to_save.= $sql_tab[$p].PHP_EOL;
            }
            $FS->save_content($temp_path.'patches/'.$folder.'/schema.sql', $to_save, true);
            Utils::error_log('Query failed + queries not done saved in '.$temp_path.'patches/'.$folder.'/schema.sql Please fix it before retry.');
        } else {
            unlink($temp_path.'patches/'.$folder.'/schema.sql');
            if ($this->check_folder_empty($temp_path.'patches/'.$folder)){
                rmdir(($temp_path.'patches/'.$folder));
            }
            $this->add_message('patch_sql_schema_success');
            return H::script('setTimeout(h.main_tab.refresh_active,2000);', ['autoremove'=>1]);
        }
    }
    public function check_folder_empty($folder){
        global $FS;
        $listing=$FS->shell_ls($folder);
        if ((count($listing['files']) > 0 || count($listing['folders']) > 0 )){
            return false;
        }else{
            return true;
        }
    }

    public function patch_sql_data($folder){
        global $CONFIG_DB, $CONFIG, $DB, $FS;
        
        $temp_path = $CONFIG::HOME_FOLDER.'temp/';
        $tree_listing = $FS->shell_ls($temp_path.'patches/'.$folder);

        //searching sql files...
        foreach($tree_listing['files'] as $fkey => $file){
            $filename = $file['name'];
            if ($filename != "schema.sql" && $FS->get_file_ext($filename) == 'sql'){
                //we got one !

                $sql = file_get_contents($temp_path.'patches/'.$folder.'/'.$filename);
                //replace prefixes :
                $sql = str_replace('*pre*_',$CONFIG_DB::DB_TABLE_PREFIX.'_',$sql);
                //splitting by lines 
                $sql_tab = explode(PHP_EOL,$sql);
                array_pop($sql_tab);

                $failed = false;
                foreach($sql_tab as $key => $sql_line){
                    $sql_line = rtrim($sql_line, ';');
                    $test=$DB->query('START TRANSACTION');
                    $test=$DB->query($sql_line);
                    if ($test === false) {
                        Utils::error_log('Error :'.$DB->last_error.PHP_EOL.'on sql query :'.$sql_line.PHP_EOL.'in file :'.$temp_path.'patches/'.$folder.'/'.$filename.PHP_EOL.'Operation stopped !');
                        $DB->query('ROLLBACK');
                        $failed = $key;
                        break;
                    }else{
                        $DB->query('COMMIT');
                    }
                }

                if ($failed !== false){
                    break;
                }else{
                    unlink($temp_path.'patches/'.$folder.'/'.$filename);
                    // echo H::script('setTimeout(h.main_tab.refresh_active,2000);', ['autoremove'=>1]);
                }
            }
        }
        if ($failed !== false){
            //we save the queries not done.
            $to_save='';
            for($p = $failed; $p <= count($sql_tab); $p++){
                $to_save.= $sql_tab[$p].PHP_EOL;
            }
            $FS->save_content($temp_path.'patches/'.$folder.'/'.$filename, $to_save, true);
            // echo 'Query failed + queries not done saved in '.$temp_path.'patches/'.$folder.'/'.$filename.' Please fix it before retry.<br>';
            Utils::error_log('Query failed + queries not done saved in '.$temp_path.'patches/'.$folder.'/'.$filename.' Please fix it before retry.');
        }else{
            if ($this->check_folder_empty($temp_path.'patches/'.$folder)){
                rmdir(($temp_path.'patches/'.$folder));
                // echo 'Patch folder '.$temp_path.'patches/'.$folder.' is empty so ... removed. Refresh UI please.<br>';
            }
            $this->add_message('patch_sql_data_success');
            return H::script('setTimeout(h.main_tab.refresh_active,2000);', ['autoremove'=>1]);
        }
    }

    public function upload_process($post){
        global $CONFIG;
        $temp_path = $CONFIG::HOME_FOLDER.'temp/';
        if (!is_dir($temp_path.'patches')){
            mkdir($temp_path.'patches');
        }
        
        Ajax::move_from_temp($temp_path.'patches/',$post['lstFilePath']);

        return $this->display_patch($post);

    }
    public function execute($post){

        if (!isset($post['action']) || $post['action'] == ''){
            Utils::error_log('Missing parameter action');
            return false;
        }
        
        // execute the function that's name exactly the same as the action (from the array actions)
        $msg = $this->cli($post['action']);

        return $msg;
    }

    private function cli($action = false){
        if (!$action){
            Utils::error_log('missing action');
            return;
        }

        global $CONFIG;

        $cmd = 'php '.$this->utils_path.$action.'.php '.$CONFIG::HOME_FOLDER;
        $res = shell_exec($cmd);
        if ($res != 'done' && $res != 'ok'){
            return $this->get_tl(trim('error_'.$res));
        } else {
            return $this->get_tl($action.'_success');
        }

    }

    private function minify(){
        global $CONFIG;

        $cmd = 'php '.$this->utils_path.'minify.php '.$CONFIG::HOME_FOLDER;
        $res = shell_exec($cmd);

        if ($res != 'done'){
            $this->add_error($res);
            return false;
        } else {
            $this->add_message('minify_success');
            return true;
        }
    }

    private function constants(){
        global $CONFIG;

        $cmd = 'php '.$this->utils_path.'constants.php '.$CONFIG::HOME_FOLDER;
        $res = shell_exec($cmd);

        if ($res != 'done'){
            $this->add_error($res);
            return false;
        } else {
            $this->add_message('constants_success');
            return true;
        }

    }

    public function detect_css($path, $patch_mode) {
        global $FS, $DB;

        if ($FS->get_file_ext($path) != 'css') {
            return;
        }

        $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE path lIKE ?';
        $source = $DB->prepared_query_line($q, 's', [$path]);

        if (!$source){
            Utils::error_log('SOURCE NOT FOUND : '.$path. ' - '.$patch_mode);
            return '';
        }

        $md5 = \md5_file($path);
        if ($md5 != $source['md5']){
            $res = Csseditor::compare_css_source($source);

            // update date and md5
            $q = 'UPDATE '.$DB->table('csseditor_source').' SET date=CURRENT_TIMESTAMP, md5="'.$md5.'" WHERE id='.$source['id'];
            $DB->query($q);

            if ($res) return ['source'=>$source, 'list'=>$res];
            else return '';
        }

        // update the date
        $q = 'UPDATE '.$DB->table('csseditor_source').' SET date=CURRENT_TIMESTAMP WHERE id='.$source['id'];
        $DB->query($q);
        
        return '';
    }
    public function display_css_edited($data){
        global $DB, $FS;

        $output = H::group('update_css_result');

            $title = H::DIV(['class'=>$this->css.'css_edited_title module_title'], $this->get_tl('title_edited_css'));
            // some rules that were updated have been modified by user. Ask him what to do with them
            $info = H::DIV(['class'=>$this->css.'css_edited_info'], $this->get_tl('info_updated_rules'));

        $output->add_child( [$title, $info] );

        $rules_displayed_count = 0;
        foreach($data as $key => $line){
            // get name of the source from corresponding table
            $source = $line['source'];
            $type = explode('¤', $source['type'])[0];
            switch ($type){
                case 'theme':
                    $q = 'SELECT name FROM '.$DB->table('csseditor_theme').' WHERE id_source='.$source['id'];
                    $name = $DB->query_value($q);
                break;
                case 'module':
                    $name = $FS->get_file_name_noext($source['path']);
                break;
                case 'block':
                    $id = explode('¤', $source['type'])[1];
                    $name = Language::get_name('block_data', $id);
                    // $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id='.$id;
                    // $name = $DB->query_value($q);
                break;
                case 'document':
                    $id = explode('¤', $source['type'])[1];
                    $name = Language::get_name('document_data', $id);
                    // $q = 'SELECT name FROM '.$DB->table('document_data').' WHERE id='.$id;
                    // $name = $DB->query_value($q);
                break;
            }

            $output->add_child( H::DIV(['class'=>$this->css.'subtitle'], $type.' - '.$name));

            // $table = H::TABLE(['class'=>$this->css.'css_rules_table']);
            //     $tbody = H::TBODY();
            // $table->add_child( $tbody );
            // $tbody->add_child( H::table_header_row([$this->get_tl('new_rule'), $this->get_tl('your_rule')]) );
            $grid = H::DIV(['class'=>$this->css.'css_rules_table']);
            
            // header
            $grid->add_child( H::DIV(['class'=>$this->css.'css_rules_col header'], $this->get_tl('new_rule')) );
            $grid->add_child( H::DIV(['class'=>$this->css.'css_rules_col header'], $this->get_tl('your_rule')) );
            
            foreach($line['list'] as $key => $line) {
                $q = 'SELECT rul.*, med.value as media FROM '.$DB->table('csseditor_rules').' rul LEFT JOIN '.$DB->table('csseditor_media').' med';
                $q.=' ON (med.id=rul.id_media) WHERE rul.id = '.$line['id_initial'].' OR rul.id = '.$line['id_modified'].' ORDER BY rul.id_initial ASC';
                $rules = $DB->query_list($q);
                $new_rule = $rules[0];
                $modified = $rules[1];
                
                // line with selector (table full size)
                $grid->add_child( H::DIV(['class'=>$this->css.'css_rules_col selector', 'id'=>self::module_name.'_row_selector_'.$key.$this->dom_id], $new_rule['selector']) );
                // $tbody->add_child( H::TR(['id'=>self::module_name.'_row_selector_'.$key.$this->dom_id], H::TD(['class'=>$this->css.'css_selector', 'colspan'=>2], $new_rule['selector'])) );

                if ($new_rule['media'] != ''){
                    // line with media (table full size)
                    // $tbody->add_child( H::TR(['id'=>self::module_name.'_row_media_'.$key.$this->dom_id], H::TD(['class'=>$this->css.'css_media', 'colspan'=>2], $new_rule['media'])) );
                    $grid->add_child( H::DIV(['class'=>$this->css.'css_rules_col media', 'id'=>self::module_name.'_row_media_'.$key.$this->dom_id], $new_rule['media']) );
                }
                
                // $row = H::TR(['id'=>self::module_name.'_row_properties_'.$key.$this->dom_id]);
                $col_new = H::DIV(['id'=>self::module_name.'_row_properties_new_'.$key.$this->dom_id, 'data-rule'=>$new_rule['id'], 'class'=>$this->css.'css_rules_col new']);
                    $inp_new = H::DIV(['class'=>$this->css.'col_properties new'], preg_replace('/;(.)/', ";<br>$1", $new_rule['properties']));
                    $btn_keep = H::BUTTON(['class'=>$this->css.'btn_keep_rule new', 'onclick'=>$this->inst_js.'.keep_rule('.$modified['id'].', '.$key.');'], $this->get_tl('keep_rule'));
                $col_new->add_child( [$inp_new, $btn_keep] );
                $grid->add_child( $col_new );

                $col_modified = H::DIV(['id'=>self::module_name.'_row_properties_modified_'.$key.$this->dom_id, 'data-rule'=>$modified['id'], 'class'=>$this->css.'css_rules_col modified']);
                    // $inp_modified = H::input_textarea(['name'=>'modified', 'disable'=>1, 'value'=>\preg_replace('/;(.)/', ";\n$1", $modified['properties'])]);
                    $inp_modified = H::DIV(['class'=>$this->css.'col_properties modified'], preg_replace('/;(.)/', ";<br>$1", $modified['properties']));
                    $btn_keep = H::BUTTON(['class'=>$this->css.'btn_keep_rule modified', 'onclick'=>$this->inst_js.'.keep_rule(0, '.$key.');'], $this->get_tl('keep_rule'));
                $col_modified->add_child( [$inp_modified, $btn_keep] );
                $grid->add_child( $col_modified );

                // $row = H::TR(['id'=>self::module_name.'_row_properties_'.$key.$this->dom_id]);
                //     $col_new = H::TD(['data-rule'=>$new_rule['id']]);
                //         $inp_new = H::DIV(['class'=>$this->css.'col_properties new'], preg_replace('/;(.)/', ";<br>$1", $new_rule['properties']));
                //         // $inp_new = H::DIV(null, str_replace(';', ';'.PHP_EOL, $new_rule['properties']));
                //         $btn_keep = H::BUTTON(['onclick'=>$this->inst_js.'.keep_rule('.$modified['id'].', '.$key.');'], $this->get_tl('keep_rule'));
                //     $col_new->add_child( [$inp_new, $btn_keep] );
                //     $col_modified = H::TD(['data-rule'=>$modified['id']]);
                //         // $inp_modified = H::input_textarea(['name'=>'modified', 'disable'=>1, 'value'=>\preg_replace('/;(.)/', ";\n$1", $modified['properties'])]);
                //         $inp_modified = H::DIV(['class'=>$this->css.'col_properties modified'], preg_replace('/;(.)/', ";<br>$1", $modified['properties']));
                //         $btn_keep = H::BUTTON(['onclick'=>$this->inst_js.'.keep_rule(0, '.$key.');'], $this->get_tl('keep_rule'));
                //     $col_modified->add_child( [$inp_modified, $btn_keep] );
                // $row->add_child( [$col_new, $col_modified] );
                // $tbody->add_child( $row );

                $rules_displayed_count++;
            }

            $output->add_child( $grid );
        }

        $script = H::script($this->inst_js.'.rules_displayed_count = '.$rules_displayed_count.';', ['autoremove'=>true]);
        $output->add_child( $script );

        return $output;
    }
    public function keep_rule_css($post){
        global $DB;

        $db_rules = $DB->table('csseditor_rules');

        $q = 'SELECT * FROM '.$db_rules.' WHERE id=?';
        $rule = $DB->prepared_query_line($q, 'i', [$post['id_rule']]);
        
        if (!$rule['id_initial']) {
            Utils::error_log('Error when trying to restore rule');
            Utils::error_log($post);
            Utils::error_log($rule);
            return 0;
        }

        $q = 'DELETE FROM '.$db_rules.' WHERE id='.$rule['id'];
        $DB->query($q);

        $q = 'UPDATE '.$db_rules.' SET id='.$rule['id'].', id_initial=0, active=1 WHERE id='.$rule['id_initial'];
        $DB->query($q);

        return 1;
    }
    public function check_css_source($post){
        global $DB, $CONFIG, $FS;

        $update = false;

        $q = 'SELECT DISTINCT * FROM '.$DB->table('csseditor_source').' ORDER BY date, type DESC';
        $sources = $DB->query_list($q);
        if (!$sources){
            return H::DIV([], $this->get_tl('update_css_up_to_date'));
        }

        $install_needed = [];
        $base_path = $CONFIG::HELPHP_FOLDER.'modules/';
        foreach($CONFIG::MODULES_LIST as $module_name => $module_params){
            $path = $base_path.$module_name.'/admin/'.$module_name.'.css';
            if (is_file($path)) $install_needed[$path] = ['name'=>$module_name, 'admin'=>1, 'type'=>'module'];
            
            $path = $base_path.$module_name.'/public/'.$module_name.'.css';
            if (is_file($path)) $install_needed[$path] = ['name'=>$module_name, 'admin'=>0, 'type'=>'module'];
        }

        // do the same parse for blocks than modules
        $q = 'SELECT id, name FROM '.$DB->table('block_data');
        $block_lst = $DB->query_list($q);
        foreach ($block_lst as $key => $block) {
            $path = $base_path.'block/'.$block['name'].'/admin/'.$block['name'].'.css';
            if (is_file($path)) $install_needed[$path] = ['name'=>$block['name'], 'admin'=>1, 'type'=>'block', 'id'=>$block['id']];
            
            $path = $base_path.'block/'.$block['name'].'/public/'.$block['name'].'.css';
            if (is_file($path)) $install_needed[$path] = ['name'=>$block['name'], 'admin'=>0, 'type'=>'block', 'id'=>$block['id']];
        }

        $update_needed = [];
        foreach($sources as $source){
            if (!is_file($source['path'])) continue;
            // compare saved md5 with current to determine if update is needed
            $md5 = \md5_file($source['path']);
            if ($md5 != $source['md5']){
                array_push($update_needed, $source);
            }

            if (key_exists($source['path'], $install_needed)) unset($install_needed[$source['path']]);
        }

        if (!$update_needed && !$install_needed){
            $ttl = H::DIV(['class'=>$this->css.'subtitle module_title'], $this->get_tl('ttl_update_css'));
            return [$ttl, H::DIV(['class'=>$this->css.'css_msg'], $this->get_tl('update_css_up_to_date'))];
        }

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);

        if ($update_needed){
            $ttl = H::DIV(['class'=>$this->css.'subtitle module_title'], $this->get_tl('ttl_update_css'));

            $form->add_child( $ttl );

            // head of the grid
            $lst = H::DIV(['class'=>$this->css.'css_list']);
            
                $header = H::DIV(['class'=>$this->css.'css_line header']);
                $header->add_child( H::SPAN(['class'=>$this->css.'css_date header'], $this->get_tl('source_date')) );
                $header->add_child( H::SPAN(['class'=>$this->css.'css_type header'], $this->get_tl('source_type')) );
                $header->add_child( H::SPAN(['class'=>$this->css.'css_name header'], $this->get_tl('source_name')) );
                $header->add_child( H::SPAN(['class'=>$this->css.'css_admin header'], $this->get_tl('source_admin')) );

            $lst->add_child( $header );

            foreach($update_needed as $source){
                $line = H::DIV(['class'=>$this->css.'css_line']);
                    $hidden = H::input_hidden(['name'=>'sources[]', 'value'=>$source['id']]);
                    $parsed_type = str_contains($source['type'], '¤') ? explode('¤', $source['type'])[0] : $source['type'];
                    $type = H::SPAN(['class'=>$this->css.'css_type'], $parsed_type);
                    if ($source['type'] == 'theme') {
                        $output_array = [];
                        $filename = rtrim($FS->get_file_path($source['path']), '/');
                        preg_match('/[^\/]+$/', $filename, $output_array);
                        $filename = $output_array[0];
                    }
                    else $filename = $FS->get_file_name_noext($source['path']);
                    $name = H::SPAN(['class'=>$this->css.'css_name'], $filename);
                    $last_update = H::SPAN(['class'=>$this->css.'css_date'], date('d-m-Y', strtotime($source['date'])));
                    $admin = H::SPAN(['class'=>$this->css.'css_admin'], ($source['admin'] ? $this->get_tl('admin') : $this->get_tl('public')));
                $line->add_child( [$last_update, $hidden, $type, $name, $admin] );
                $lst->add_child( $line );
            }

            $form->add_child( $lst );
        }
        
        if ($install_needed){
            $ttl = H::DIV(['class'=>$this->css.'subtitle module_title'], $this->get_tl('ttl_install_css'));

            $form->add_child( $ttl );

            // head of the grid
            $lst = H::DIV(['class'=>$this->css.'css_list']);
            
                $header = H::DIV(['class'=>$this->css.'css_line header install']);
                // $header->add_child( H::SPAN(['class'=>$this->css.'css_date header'], $this->get_tl('source_date')) );
                $header->add_child( H::SPAN(['class'=>$this->css.'css_type header install'], $this->get_tl('source_type')) );
                $header->add_child( H::SPAN(['class'=>$this->css.'css_name header install'], $this->get_tl('source_name')) );
                $header->add_child( H::SPAN(['class'=>$this->css.'css_name header install'], $this->get_tl('source_admin')) );

            $lst->add_child( $header );

            $i = 0;
            foreach($install_needed as $path => $source){
                
                $line = H::DIV(['class'=>$this->css.'css_line install']);

                    $hidden_path = H::input_hidden(['name'=>'install['.$i.']', 'value'=>$path]);
                    $hidden_type = H::input_hidden(['name'=>'install_type['.$i.']', 'value'=>$source['type']]);

                $line->add_child( [$hidden_path, $hidden_type] );
                if (isset($source['id'])) $line->add_child( H::input_hidden(['name'=>'block_id['.$i.']', 'value'=>$source['id']]) );

                    $type = H::SPAN(['class'=>$this->css.'css_type install'], $source['type']);
                    $name = H::SPAN(['class'=>$this->css.'css_name install'], $source['name']);
                    $admin = H::SPAN(['class'=>$this->css.'css_admin install'], ($source['admin'] ? $this->get_tl('admin') : $this->get_tl('public')));

                $line->add_child( [$type, $name, $admin] );

                $lst->add_child( $line );

                $i++;
            }

            $form->add_child( $lst );
        }

        $submit = H::submit_button(['class'=>$this->css.'btn_css', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_UPDATE_CSS], $this->get_tl('update'));
        $form->add_child( [$submit] );

        return $form;
    }
    /**
     * Update css from file, compare them with db
     */
    public function update_css_source($post){
        global $DB;

        $output = H::group('update_css_result');
        
        $css_edited_found = [];
        if (isset($post['sources'])){
            $q = 'SELECT DISTINCT * FROM '.$DB->table('csseditor_source').' WHERE id IN (';
            $q_i = '';
            $q_v = [];
            foreach($post['sources'] as $id_source){
                $q.= '?,';
                $q_i.= 'i';
                array_push($q_v, $id_source);
            }
            $q = \substr($q, 0, -1).')';
            $sources = $DB->prepared_query_list($q, $q_i, $q_v);

            foreach($sources as $source){
                $res = Csseditor::compare_css_source($source);

                $q = 'UPDATE '.$DB->table('csseditor_source').' SET md5=?, date=CURRENT_TIMESTAMP WHERE id=?';
                $DB->prepared_query($q, 'si', [md5_file($source['path']), $source['id']]);

                if ($res) {
                    array_push($css_edited_found, ['source'=>$source, 'list'=>$res]);
                }
            }
        }

        if (isset($post['install'])){
            $this->install_css_source($post);
        }

        if ($css_edited_found) {
            $output->add_child( $this->display_css_edited($css_edited_found) );
        } else {
            $ttl = H::DIV(['class'=>$this->css.'subtitle module_title'], $this->get_tl('ttl_update_css'));
            $msg = H::DIV(['class'=>$this->css.'css_msg'], $this->get_tl('css_updated'));
            $output->add_child( [$ttl, $msg] );
        }

        return $output;
    }
    public function install_css_source($post){
        global $DB;

        foreach($post['install'] as $key => $path){
            if (!file_exists($path)) continue;

            $admin = str_contains($path, 'admin') ? 1 : 0;
            $md5 = \md5_file($path);
            
            $type = $post['install_type'][$key] != 'module' ? $post['install_type'][$key].'¤'.$post['block_id'][$key] : 'module';

            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type=?, path=?, md5=?, admin='.$admin;
            $DB->prepared_query($q, 'sss', [$type, $path, $md5]);
            $id_source = $DB->last_insert_id();
            
            \helPHP\modules\csseditor\admin\Csseditor::import_css_source($path, $id_source, ($type == 'module'));
        }
    }

    public function check_block_before_import($post){
        global $CONFIG, $FS, $DB;

        // move the uploaded zip to temp/
        $tmp_path = $CONFIG::HOME_FOLDER.'temp/patches/block/';

        $tree_files = $FS->recurse_ls($tmp_path);
        $block_name = $tree_files['folders'][0];
        
        $q = 'SELECT id FROM '.$DB->table('block_data').' WHERE name=?';
        $id = $DB->prepared_query_value($q, 's', [$block_name]);
        if ($id){
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id, 'class'=>$this->css.'form_before_import_block']);

                $hidden_file = H::input_hidden(['name'=>'block_name', 'value'=>$block_name]);
                $hidden_moved = H::input_hidden(['name'=>'block_moved_from_temp', 'value'=>1]);

            $form->add_child( [$hidden_file, $hidden_moved] );

                $ttl = H::DIV(['class'=>$this->css.'subtitle module_title'], $this->get_tl('ttl_block_exist'));

                $info = H::DIV(['class'=>$this->css.'block_exist_info'], $this->get_tl('block_exist', $block_name));

                $btn_replace = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_PROCESS_BLOCK, 'class'=>$this->css.'btn_replace_block', 'data-parameters'=>['replace'=>1]], $this->get_tl('replace_block'));
                $btn_update = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_PROCESS_BLOCK, 'class'=>$this->css.'btn_replace_block', 'data-parameters'=>['update'=>$id]], $this->get_tl('update_block'));
                $btn_cancel = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_PROCESS_BLOCK, 'class'=>$this->css.'btn_cancel_import_block', 'data-parameters'=>['cancel'=>1]], $this->get_tl('cancel_import_block'));

            $form->add_child( [$ttl, $info, $btn_replace, $btn_update, $btn_cancel] );

            return $form;
        }

        $post['block_name'] = $block_name;
        $post['block_moved_from_temp'] = 1;
        return $this->import_block($post);
    }
    public function import_block($post){
        if (!isset($post['block_moved_from_temp'])){
            return $this->check_block_before_import($post);
        }

        global $CONFIG, $FS, $DB;

        $tmp_path = $CONFIG::HOME_FOLDER.'temp/patches/block/';
        $block_name = $post['block_name'];

        if (isset($post['cancel'])){
            $FS->delete($tmp_path.$block_name);

            $this->add_message('block_import_canceled');
            return H::script('H_ui.popup_modal.hide(); h.main_tab.refresh_active();');
        }

        $tree_listing = $FS->recurse_ls($tmp_path);

        // if block already exist and it's not an update, remove it
        $hlp_block_path = $CONFIG::HELPHP_FOLDER.'modules/block/';
        if (!isset($post['update']) && file_exists($hlp_block_path.$block_name)) {
            // delete entry in block_data
            $q = 'DELETE FROM '.$DB->table('block_data').' WHERE name=?';
            $DB->prepared_query($q, 's', [$block_name]);
            // drop table if exist
            $q = 'DROP TABLE IF EXISTS '.$DB->table('block_'.$block_name);
            $DB->query($q);

            $FS->delete($hlp_block_path.$block_name);
        }

        // create each folder
        foreach($tree_listing['folders'] as $path){
            if (!file_exists($hlp_block_path.$path)) {
                $FS->mkdir($hlp_block_path.$path);
                shell_exec('chmod -R 2774 '.$hlp_block_path.$path);
            }
        }

        $css_file_public = false;
        $css_file_admin = false;
        
        // move each file or execute sql
        foreach($tree_listing['files'] as $path){
            $tmp_block_path = $tmp_path.$path;
            Utils::error_log($tmp_block_path.' to '.$hlp_block_path.$path);
            if ($FS->get_file_ext($tmp_block_path) == 'json'){

                $DB->sql_from_json(file_get_contents($tmp_block_path));
                $FS->move($tmp_block_path, $FS->get_file_path($hlp_block_path.$path));

                shell_exec('chmod 2774 '.$hlp_block_path.$path);
    
            } else {
                if ($FS->get_file_ext($tmp_block_path) == 'css') {
                    if (str_contains($path, 'public')) $css_file_public = $hlp_block_path.$path;
                    else $css_file_admin = $hlp_block_path.$path;
                }

                $FS->move($tmp_block_path, $FS->get_file_path($hlp_block_path.$path));
                shell_exec('chmod 2774 '.$hlp_block_path.$path);
            }
        }

        $FS->delete($tmp_path);

        // retrieve block id
        $q = 'SELECT id FROM '.$DB->table('block_data').' WHERE name=?';
        $id_block = $DB->prepared_query_value($q, 's', [$post['block_name']]);

        // import css
        if (isset($post['update'])) {
            // update block, compare source
            if ($css_file_public) {
                $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type="block¤'.$id_block.'" AND admin=0';
                $source = $DB->query_line($q);
                if ($source) \helPHP\modules\csseditor\admin\Csseditor::compare_css_source($source);
                else {
                    $md5 = \md5_file($css_file_public);
                    $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=0';
                    $DB->prepared_query($q, 'ss', [$css_file_public, $md5]);
                    $id_source = $DB->last_insert_id();
                    \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_public, $id_source);
                }
            } else {
                // remove old css source if any
                \helPHP\modules\csseditor\admin\Csseditor::delete_css_source('block', $id_block, 'public');
            }

            if ($css_file_admin) {
                $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type="block¤'.$id_block.'" AND admin=1';
                $source = $DB->query_line($q);
                if ($source) \helPHP\modules\csseditor\admin\Csseditor::compare_css_source($source);
                else {
                    $md5 = \md5_file($css_file_admin);
                    $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=1';
                    $DB->prepared_query($q, 'ss', [$css_file_admin, $md5]);
                    $id_source = $DB->last_insert_id();
                    \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_admin, $id_source);
                }
            } else {
                // remove old css source if any
                \helPHP\modules\csseditor\admin\Csseditor::delete_css_source('block', $id_block, 'admin');
            }

            
        } else {
            // new block import source
            if ($css_file_public) {
                $md5 = \md5_file($css_file_public);
                $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=0';
                $DB->prepared_query($q, 'ss', [$css_file_public, $md5]);
                $id_source = $DB->last_insert_id();
                \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_public, $id_source);
            }

            if ($css_file_admin) {
                $md5 = \md5_file($css_file_admin);
                $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type="block¤'.$id_block.'", path=?, md5=?, admin=1';
                $DB->prepared_query($q, 'ss', [$css_file_admin, $md5]);
                $id_source = $DB->last_insert_id();
                \helPHP\modules\csseditor\admin\Csseditor::import_css_source($css_file_admin, $id_source);
            }
        }

        if (isset($post['update'])) $this->add_message('import_success_update', $post['block_name']);
        else if (isset($post['replace'])) $this->add_message('import_success_replace', $post['block_name']);
        else $this->add_message('import_success');

        return H::script('H_ui.popup_modal.hide();h.main_tab.refresh_active();');
    }

    // get version number from start of file. return false if the version from old file is bigger than new one.
    // Otherwise return true. If no version found return true
    public function compare_version($new_file, $old_file){
        global $FS;

        if (!$old_file) return false;

        // get first 2 lines of the file
        $cmd = 'head -n 2 '.$old_file;
        $lines = shell_exec($cmd);
        
        // try to get version: x.xxxx from lines
        $matches = [];
        preg_match('/version: (.*\d)/m', $lines, $matches);
        if (isset($matches[1])) {
            $t = explode('.', $matches[1]);
            $version_first_part_old = $t[0];
            $version_second_part_old = $t[1];

            // we found a version in the old file, new file should have one
            // get first 2 lines of the file
            $cmd = 'head -n 2 '.$new_file;
            $lines = shell_exec($cmd);
            $matches = [];
            preg_match('/version: (.*\d)/m', $lines, $matches);
            if (!isset($matches[1])) {
                Utils::error_log('a version has been found for file '.$old_file.' but the new file '.$new_file.' have none');
                return false;
            }
            $t = explode('.', $matches[1]);
            $version_first_part_new = $t[0];
            $version_second_part_new = $t[1];

            if ($version_first_part_old > $version_first_part_new) {
                return false;
            }
            if ($version_second_part_old > $version_second_part_new) {
                return false;
            }
        }
        
        // no version found we can replace the file 
        return true;
    }
}