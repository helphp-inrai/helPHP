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

global $helphp_folder;
$helphp_folder = dirname(__DIR__).'/';

$CLI = isset($argc); // true, called from CLI

if ($CLI){ // call from CLI
    if (!isset($argv[1])){
        echo 'Need a module name.'.PHP_EOL;
        exit;
    }

    $module_name = $argv[1];

    create_module($module_name);
}

/**
 * create a basic module for helPHP
 * 
 * To call from CLI
 *      php create_module.php module_name
 * 
 * @param string    $module_name            name of the module
 * @return string error or success message
 * 
 * @package helPHP\utils
 */
function create_module($module_name) {
   global $helphp_folder;
$part='<?php

namespace helPHP\modules\¤module_name¤\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;

class ¤ucfirst_module_name¤ extends HelPHP_module {

    const module_name = \'¤module_name¤\';

    function __construct($dom_container = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container);
    }
    //CRUD ACTIONS
    private $ACTION_NEW_¤module_name¤ = self::module_name.\'_new\';
    private $ACTION_SAVE_¤module_name¤ = self::module_name.\'_save\';
    private $ACTION_EDIT_¤module_name¤ = self::module_name.\'_edit\';
    private $ACTION_DELETE_¤module_name¤ = self::module_name.\'_delete\';
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = \'no_edit \'.$this->css;
        }

        $master_output = H::group($this->module_name.\'_display\');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW_¤module_name¤:
                if ($this->user_can_edit){ // needed for security
                    unset($post[$this->ifld_data_id]);
                    $this->reset_fields($post, \'¤module_name¤_data\');
                    $master_output->add_child( $this->edit_¤module_name¤($post) );
                }
            break;
            case $this->ACTION_EDIT_¤module_name¤:
                if ($this->user_can_edit){
                    $this->prepare_fields($post, \'¤module_name¤_data\');
                    //if you have a translation block in your form uncoment next line
                    //Language::load_translation_data($post, self::module_name, \'data\');
                    $master_output->add_child( $this->edit_¤module_name¤($post) );
                }
            break;
            case $this->ACTION_SAVE_¤module_name¤:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, \'¤module_name¤_data\');
                    $this->save_¤module_name¤($post);
                    //if you have a translation block in your form uncoment next line
                    //Language::save_translation_data($post, $post[$this->ifld_data_id]);
                    $master_output->add_child( $this->edit_¤module_name¤($post) );
                }
            break;
            
            case $this->ACTION_DELETE_¤module_name¤:
                if ($this->user_can_edit){
                    $this->check_posted_data($post, \'¤module_name¤_data\');
                    $this->delete_¤module_name¤($post);
                    //if you have a translation block in your form uncoment next line
                    //Language::delete_translation_data($post, self::module_name, \'data\', $post[$this->ifld_data_id]);
                    $master_output->add_child( H::SPAN([\'class\'=>$this->css.\'_deleted\'], $this->get_tl(\'tlc_deleted\')) );
                }
            break;
            
            default:
                $this->check_posted_data($post, \'¤module_name¤_data\');
                $master_output->add_child( $this->edit_¤module_name¤($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function edit_¤module_name¤($post) {
        global $DB, $CONFIG;
        
        $output = H::div([\'class\'=>\'module_container_a ¤module_name¤_a ¤module_name¤_admin_container\',\'id\'=>\'¤module_name¤_admin_container\'.$this->dom_id ]);
            
            $form = H::form([\'action\'=>$CONFIG::BASE_URL.$CONFIG::ADMIN_FOLDER.\'/¤module_name¤/index.php\', \'dom_target\'=>\'.parent\', \'class\'=>$this->css.\'form_edit form_edit\', \'dom_id\'=>$this->dom_id]);
            $form->add_child(H::input_hidden([\'name\'=>$this->ifld_data_id, \'value\'=>$post[$this->ifld_data_id], \'data-alwaysposted\'=>1]));

                //insert here you other input fields, and add them as child in $form

                $block_btns = H::DIV([\'class\'=>$this->css.\'block_btns edit_buttons\']);
                    $btn_save = H::submit_button([\'class\'=>$this->css.\'btn_save button_save\', \'name\'=>$this->input_action_identifier, \'value\'=>$this->ACTION_SAVE_¤module_name¤, \'title\'=>$this->get_tl(\'tlc_save\')], $this->get_tl(\'tlc_save\'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_data_id] > 0) {
                    $btn_delete = H::submit_button([\'class\'=>$this->css.\'btn_del button_delete\', \'name\'=>$this->input_action_identifier, \'value\'=>$this->ACTION_DELETE_¤module_name¤, \'title\'=>$this->get_tl(\'tlc_del\'), \'data-confirm\'=>$this->get_tl(\'tlc_ask_delete\')], $this->get_tl(\'tlc_del\'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);
        $output->add_child($form);
        
        //if you have a JS script to launch, here is the good place to insert its init :
        //$js = H::script(\'¤ucfirst_module_name¤_a.create_instance("\'.$this->dom_id.\'",\'.json_encode($js_params).\');\', [\'autoremove\'=>true]);

        return $output;
    }
    
    public function save_¤module_name¤(&$post) {
        global $DB;

        if($post[$this->ifld_data_id] == 0 || !isset($post[$this->ifld_data_id])){
            // create
            // HERE YOU NEED TO SET ¤fields¤ ¤fields_type¤ and  ¤fields_values¤
            $q = \'INSERT INTO \'.$DB->table(\'¤module_name¤_data\').\' SET ¤fields¤\';
            $success = $DB->prepared_query($q,\'¤fields_type¤\',[¤fields_values¤]);
            $post[$this->ifld_data_id] = $DB->last_insert_id();
            
        }else{
            // HERE YOU NEED TO SET ¤fields¤ ¤fields_type¤ and  ¤fields_values¤
            $q = \'UPDATE \'.$DB->table(\'¤module_name¤_data\').\' SET ¤fields¤ where id=?\';
            $success = $DB->prepared_query($q,\'¤fields_type¤i\',[¤fields_values¤,$post[$this->ifld_data_id]]);
        }
        if (isset($post[\'need_id\'])) {
            $_SESSION[$post[\'need_id\']] = $post[$this->ifld_data_id];
        }

        //if you have some media input fields :
        //global $MEDIA;
        //$res = $MEDIA->process_media($post, $post[$this->ifld_data_id]);
        //if (!$res) $this->add_error(\'media_error\');

    }

    public function delete_¤module_name¤(&$post) {
        global $DB;

        $q = \'DELETE FROM \'.$DB->table(\'¤module_name¤_data\').\' WHERE id=?\';
        $res = $DB->prepared_query($q, \'i\', [$post[$this->ifld_data_id]]);
        
        //if you have some media input fields :    
        //global $MEDIA;
        //$MEDIA->delete_media($this->ifld_data_id, $post[$this->ifld_data_id]);

    }
}
';

$public_part='<?php

namespace helPHP\modules\¤module_name¤\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class ¤ucfirst_module_name¤ extends HelPHP_module {

    const module_name = \'¤module_name¤\';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer);
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post[\'block\'])){
            $post[$this->ifld_data_id] = $post[\'¤module_name¤\'];
        }
        
        $master_output = H::group($this->module_name.\'_display\');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, \'¤module_name¤_data\');
                if (!$post[$this->ifld_data_id]) $this->reset_fields($post, \'¤module_name¤\');
                //if you have a translation block in your form uncoment next line
                //Language::load_translation_data($post, self::module_name, \'data\');
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
        
        $data_display = H::div([\'class\'=>\'¤module_name¤_container ¤module_name¤\',\'id\'=>\'¤module_name¤_\'.$this->dom_id ]);
        //here add the display of your fields to $data_display as childs ...

        //if you have a JS script to launch, here is the good place to insert its init :
        //$js = H::script(\'¤ucfirst_module_name¤.create_instance("\'.$this->dom_id.\'",\'.json_encode($js_params).\');\', [\'autoremove\'=>true]);
        return $data_display;
    }
}';
    //admin part replacement
    $part = str_replace('¤module_name¤', $module_name, $part);
    $part = str_replace('¤ucfirst_module_name¤', ucfirst($module_name), $part);
    //public part replacement
    
    $public_part = str_replace('¤module_name¤', $module_name, $public_part);
    $public_part = str_replace('¤ucfirst_module_name¤', ucfirst($module_name), $public_part);

    $output_path=$helphp_folder.'modules';
    if(!is_dir($output_path.'/'.$module_name.'/admin')) {
        mkdir($output_path.'/'.$module_name.'/admin', 0775, true);
        mkdir($output_path.'/'.$module_name.'/public', 0775, true);
    }

    file_put_contents($output_path.'/'.$module_name.'/admin/'.ucfirst($module_name).'.php', $part);
    file_put_contents($output_path.'/'.$module_name.'/public/'.ucfirst($module_name).'.php', $public_part);
    $jsadmin="window.h.¤module_name¤ = window.h.¤module_name¤ || {};
class ¤ucfirst_module_name¤_a extends H_module {
    constructor(dom_id){
        super(dom_id);
    }
    static instances = {};
    static create_instance(dom_id, settings){
        if (¤ucfirst_module_name¤_a.instances[dom_id]){
            ¤ucfirst_module_name¤_a.instances[dom_id].clean();
            delete(¤ucfirst_module_name¤_a.instances[dom_id]);
        }
        ¤ucfirst_module_name¤_a.instances[dom_id] = new ¤ucfirst_module_name¤_a(dom_id, settings);

        ¤ucfirst_module_name¤_a.clean_instances();
        
        return ¤ucfirst_module_name¤_a.instances[dom_id];
    }
    static clean_instances(){
        let toClean = [];
        for (var key in ¤ucfirst_module_name¤_a.instances) {
            if (¤ucfirst_module_name¤_a.instances[key].exist()){
                ¤ucfirst_module_name¤_a.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key)=>{
            delete(¤ucfirst_module_name¤_a.instances[key]);
        });
    }
}
window.h.¤module_name¤.¤ucfirst_module_name¤_a = ¤ucfirst_module_name¤_a;";
$jsadmin = str_replace('¤module_name¤', $module_name, $jsadmin);
$jsadmin = str_replace('¤ucfirst_module_name¤', ucfirst($module_name), $jsadmin);
file_put_contents($output_path.'/'.$module_name.'/admin/'.$module_name.'.js', $jsadmin);


$jspublic="window.h.¤module_name¤ = window.h.¤module_name¤ || {};
class ¤ucfirst_module_name¤ extends H_module {
    constructor(dom_id){
        super(dom_id);
    }
    static instances = {};
    static create_instance(dom_id, settings){
        if (¤ucfirst_module_name¤.instances[dom_id]){
            ¤ucfirst_module_name¤.instances[dom_id].clean();
            delete(¤ucfirst_module_name¤.instances[dom_id]);
        }
        ¤ucfirst_module_name¤.instances[dom_id] = new ¤ucfirst_module_name¤(dom_id, settings);

        ¤ucfirst_module_name¤.clean_instances();
        
        return ¤ucfirst_module_name¤.instances[dom_id];
    }
    static clean_instances(){
        let toClean = [];
        for (var key in ¤ucfirst_module_name¤.instances) {
            if (¤ucfirst_module_name¤.instances[key].exist()){
                ¤ucfirst_module_name¤.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key)=>{
            delete(¤ucfirst_module_name¤.instances[key]);
        });
    }
}
window.h.¤module_name¤.¤ucfirst_module_name¤ = ¤ucfirst_module_name¤;";
    $jspublic = str_replace('¤module_name¤', $module_name, $jspublic);
    $jspublic = str_replace('¤ucfirst_module_name¤', ucfirst($module_name), $jspublic);
    
    file_put_contents($output_path.'/'.$module_name.'/public/'.$module_name.'.js', $jspublic);
    
    $cmd = 'chmod -R 2774 "'.$output_path.'/'.$module_name.'"';
    $res = \shell_exec($cmd);
    $cmd = 'chown -R www-data:www-data "'.$output_path.'/'.$module_name.'"';
    $res = \shell_exec($cmd);

    echo "new module created there : ".$output_path.'/'.$module_name.PHP_EOL;
}