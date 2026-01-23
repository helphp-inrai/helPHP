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

namespace helPHP\modules\config\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;

class Config extends HelPHP_module {

    const module_name = 'config';

    protected $ACTION_SAVE = self::module_name.'_save';

    private $base_path;
    protected $files = ['main', 'email'];
    protected $ignore_field = [
        // main
        'MINIFICATION_TIME',
        'AVAILABLE_LANGUAGES',
        'LIBTRANSLATE_URL',
        'LIBTRANSLATE_APIKEY',
        'REDIS_ADDRESS',
        'CLUSTER_INFO',
        'GLUSTER_STORAGE',
        'CLUSTER_MODE',
        'ROOT_FS',
        'SITE_FOLDER',
        'HOME_FOLDER',
        'LOG_FOLDER',
        'HELPHP_FOLDER',
        'CRYPT_KEY',
        'MODULES_BASIC',

        //email
        'EMAIL_SIGNATURE_BODY',
        'EMBEDED',
    ];

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);

        global $CONFIG;
        $this->base_path = $CONFIG::HOME_FOLDER.'config/';
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

        // $this->inst_js = 'helPHP_maintenance';

        $master_output = H::group('config_display');
        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_SAVE:
                $master_output->add_child( $this->save($post) );
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

    public function display($post){
        $output = H::group('display_main');

            $info = H::DIV(['class'=>$this->css.'info'], $this->get_tl('info'));

        $output->add_child($info);

        foreach($this->files as $key => $filename){
            $filepath = $this->base_path.$filename.'.php';
            
            if(!is_file($filepath)){
                Utils::error_log($filepath.' doesn\'t exist');
                continue;
            }

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form '.$filename]);
            
                $hidden_filename = H::input_hidden(['name'=>'filename', 'value'=>$filename]);

                $details = H::detail(['class'=>$this->css.'details'], $this->get_tl($filename));
                if ($key == 0) $details->set_attribute('open', 1);
                    $content = H::DIV(['class'=>$this->css.'block_content']);
                    $content->add_child( $this->{$filename.'_fields'}($filepath) );
                        $blk_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons '.$filename]);
                            $btn_submit = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save'.$filename]);
                        $blk_btns->add_child( $btn_submit );
                    $content->add_child( $blk_btns );
                $details->add_child( $content );
                // $block = H::DIV(['class'=>$this->css.'block']);
                //     $ttl = H::DIV(['class'=>$this->css.'block_title', 'data-target_id'=>self::module_name.'_block_content_'.$key.$this->dom_id], $this->get_tl($filename));
                //     $content = H::DIV(['class'=>$this->css.'block_content', 'id'=>self::module_name.'_block_content_'.$key.$this->dom_id]);
                //     $content->add_child( $this->{$filename.'_fields'}($filepath) );
                //         $blk_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons '.$filename]);
                //             $btn_submit = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save'.$filename]);
                //         $blk_btns->add_child( $btn_submit );
                //     $content->add_child( $blk_btns );
                // $block->add_child( [$ttl, $content] );

            $form->add_child([$hidden_filename, $details]);
            
            $output->add_child($form);
        }

        // $script = H::script('H_ui.toggle_accordion("'.$this->css.'block_title", "hidden");');
        // $output->add_child($script);

        // $receiver_result = H::DIV(['class'=>$this->css.'receiver_result', 'id'=>self::module_name.'_receiver_result'.$this->dom_id]);
        // $script = H::script($this->inst_js.' = new Maintenance("'.$this->dom_id.'", '.json_encode($this->actions).');', ['autoremove'=>1]);
        // $output->add_child([$receiver_result]);

        return $output;
    }

    public function main_fields($filepath){
        global $DB;

        $output = H::group('main_fields');

        $file_content = file_get_contents($filepath);
        $lines = explode(PHP_EOL , $file_content);

            // $blk_general = H::DIV(['class'=>$this->css.'sub_block main general']);
            // $blk_theme = H::DIV(['class'=>$this->css.'sub_block main theme']);
            // $blk_storage = H::DIV(['class'=>$this->css.'sub_block main storage']);
            // $blk_libtranslate = H::DIV(['class'=>$this->css.'sub_block main libtranslate']);
            // $blk_redis = H::DIV(['class'=>$this->css.'sub_block main redis']);
            // $blk_security = H::DIV(['class'=>$this->css.'sub_block main security']);
            $blk_general = H::FIELDSET(['class'=>$this->css.'sub_block main general'], $this->get_tl('general'));
            $blk_theme = H::FIELDSET(['class'=>$this->css.'sub_block main theme'], $this->get_tl('theme'));
            $blk_storage = H::FIELDSET(['class'=>$this->css.'sub_block main storage'], $this->get_tl('storage'));
            $blk_folder_domain = H::FIELDSET(['class'=>$this->css.'sub_block main folder_domain'], $this->get_tl('folder_domain'));
            // $blk_libtranslate = H::FIELDSET(['class'=>$this->css.'sub_block main libtranslate'], $this->get_tl('libtranslate'));
            $blk_redis = H::FIELDSET(['class'=>$this->css.'sub_block main redis'], $this->get_tl('redis'));
            $blk_security = H::FIELDSET(['class'=>$this->css.'sub_block main security'], $this->get_tl('security'));

        $output->add_child( [$blk_general, $blk_theme, $blk_storage, $blk_folder_domain, $blk_redis, $blk_security] );

        foreach($lines as $l) {
            if (str_contains($l, '//>>>modules>>>')) break;
            if (!str_contains($l, 'const') || str_starts_with($l, '    //')) continue;
            $t = explode('const', $l)[1];
            $t = explode('=', $t);
            $varname = trim($t[0]);
            $value = rtrim(trim($t[1]), ';');
            if (in_array($varname, $this->ignore_field)) continue;
            switch($varname){
                case 'THEME_ID':
                case 'THEME_ID_ADMIN':
                    $type_hidden = H::input_hidden(['name'=>'type['.$varname.']', 'value'=>'i']);
                    $admin = $varname == 'THEME_ID_ADMIN' ? 1 : 0;
                    $q = 'SELECT DISTINCT id, name FROM '.$DB->table('csseditor_theme').' WHERE admin='.$admin;
                    $lst = $DB->query_list($q);
                    
                    $opts_data = ['value_key'=>'id', 'label_key'=>'name', 'options'=>$lst];
                    $select = H::select(['name'=>$varname, 'label'=>$this->get_tl($varname)], $opts_data, $value);
                    $blk_theme->add_child( [$type_hidden, $select->label_tag(), $select] );
                break;

                case 'STORAGE_COTA':
                case 'GLUSTER_STORAGE':
                    if ($varname == 'STORAGE_COTA'){
                        // global $FS;
                        $gb_size = number_format($value/(1<<30),2);
                        $type_hidden = H::input_hidden(['name'=>'type['.$varname.']', 'value'=>'i']);
                        $block_inp = H::DIV(['class'=>$this->css.'inp_suffixed']);
                            $inp = H::input_float(['name'=>$varname, 'label'=>$this->get_tl($varname), 'value'=>$gb_size]);
                            $unit = H::SPAN(['class'=>$this->css.'unit'], 'GB');
                        $block_inp->add_child([$inp, $unit]);
                        $blk_storage->add_child( [$type_hidden, $inp->label_tag(), $block_inp] );

                        // $readable = $FS->human_readable_size($value);

                    } else {
                        $blk_storage->add_child( $this->default_field($varname, $value) );
                    }
                break;

                case 'DOMAIN':
                case 'ADMIN_FOLDER':
                case 'APACHE_USER':
                case 'BASE_URL':
                    if ($varname == 'BASE_URL'){
                        $value = explode(':', trim($value, '\''))[0];
                        $type_hidden = H::input_hidden(['name'=>'type[protocol]', 'value'=>'i']);
                        $values = [['value'=>'http', 'label'=>'http'], ['value'=>'https', 'label'=>'https']];
                        $bool = H::input_multiple_radios(['values'=>$values, 'name'=>'protocol', 'selected'=>$value, 'label'=>$this->get_tl('protocol')]);
                        $blk_folder_domain->add_child( [$type_hidden, $bool->label_tag(), $bool] );
                    } else {
                        $blk_folder_domain->add_child( $this->default_field($varname, $value) );
                    }
                    // automaticly compose url with domain and site folder
                    
                break;

                // case 'LIBTRANSLATE_URL':
                // case 'LIBTRANSLATE_APIKEY':
                //     $blk_libtranslate->add_child( $this->default_field($varname, $value) );
                // break;

                case 'REDIS':
                case 'REDIS_HOST':
                case 'REDIS_PORT':
                    $blk_redis->add_child( $this->default_field($varname, $value) );
                break;
                
                case 'SESSION_HOURS':
                case 'TOKEN_MINUTE':
                case 'CRYPT_KEY':
                case 'USERNAME_VALID_STRING':
                case 'USERPASSWORD_MINIMUM_LENGTH':
                case 'MAX_USER_CONNECTION_ATTEMPTS':
                case 'CONNECTION_TRY_BAN_HOURS':
                    $blk_security->add_child( $this->default_field($varname, $value) );
                break;
                

                default:
                    $blk_general->add_child( $this->default_field($varname, $value) );
                break;
            }
            $prev_val = H::input_hidden(['name'=>'prev['.$varname.']', 'value'=>$value]);
            $output->add_child($prev_val);
        }

        return $output;
    }
    public function db_fields($filepath){
        $output = H::group('db_fields');

        $file_content = file_get_contents($filepath);
        $lines = explode(PHP_EOL , $file_content);

        foreach($lines as $l) {
            if (str_contains($l, '//>>>modules>>>')) break;
            if (!str_contains($l, 'const') || str_starts_with($l, '    //')) continue;
            $t = explode('const', $l)[1];
            $t = explode('=', $t);
            $varname = trim($t[0]);
            $value = trim(rtrim($t[1], ';'));
            if (in_array($varname, $this->ignore_field)) continue;
            switch($varname){
                case '':

                break;
                default:
                    $output->add_child( $this->default_field($varname, $value) );
                break;
            }
            $prev_val = H::input_hidden(['name'=>'prev['.$varname.']', 'value'=>$value]);
            $output->add_child($prev_val);
        }

        return $output;
    }
    public function email_fields($filepath){
        $output = H::group('email_fields');

        $file_content = file_get_contents($filepath);
        $lines = explode(PHP_EOL , $file_content);

        $blk_general = H::FIELDSET(['class'=>$this->css.'sub_block email general'], $this->get_tl('general_email'));
        $blk_smtp = H::FIELDSET(['class'=>$this->css.'sub_block main smtp'], $this->get_tl('smtp'));

        $output->add_child([$blk_general,$blk_smtp]);

        foreach($lines as $l) {
            if (str_contains($l, '//>>>modules>>>')) break;
            if (!str_contains($l, 'const') || str_starts_with($l, '    //')) continue;
            $t = explode('const', $l)[1];
            $t = explode('=', $t);
            $varname = trim($t[0]);
            $value = rtrim(trim($t[1]), ';');
            if (in_array($varname, $this->ignore_field)) continue;
            // switch($varname){

            //     default:
            if (str_contains($varname, 'SMTP')){
                $blk_smtp->add_child( $this->default_field($varname, $value) );
            } else {
                $blk_general->add_child( $this->default_field($varname, $value) );
            }
                    
                // break;
            // }
            $prev_val = H::input_hidden(['name'=>'prev['.$varname.']', 'value'=>$value]);
            $output->add_child($prev_val);
        }

        return $output;
    }
    public function default_field($varname, &$value){
        if ($value == 'false' || $value == 'true'){
            $type_hidden = H::input_hidden(['name'=>'type['.$varname.']', 'value'=>'i']);
            $values = [['value'=>'true', 'label'=>$this->get_tl('tlc_yes')], ['value'=>'false', 'label'=>$this->get_tl('tlc_no')]];
            $bool = H::input_multiple_radios(['values'=>$values, 'name'=>$varname, 'selected'=>$value, 'label'=>$this->get_tl($varname)]);
            return [$type_hidden, $bool->label_tag(), $bool];
        } else if (str_starts_with($value, '\'')) {
            // string
            $type_hidden = H::input_hidden(['name'=>'type['.$varname.']', 'value'=>'s']);
            $value = trim($value, '\'');
            $inp = H::input_text(['name'=>$varname, 'label'=>$this->get_tl($varname), 'value'=>$value]);
            return [$type_hidden, $inp->label_tag(), $inp];
        } else {
            $type_hidden = H::input_hidden(['name'=>'type['.$varname.']', 'value'=>'i']);
            $inp = H::input_integer(['name'=>$varname, 'label'=>$this->get_tl($varname), 'value'=>$value]);
            return [$type_hidden, $inp->label_tag(), $inp];
        }
    }

    public function save(&$post){

        $to_save_name = [];
        $to_save_val = [];
        $to_save_type = [];
        foreach($post['prev'] as $varname => $oldvalue){
            if (isset($post[$varname]) && $post[$varname] != $oldvalue){
                if ($varname == 'STORAGE_COTA'){
                    $post[$varname] = $post[$varname]  * 1024 * 1024 * 1024;
                    // Utils::error_log($post[$varname]);
                    // continue;
                }

                // if ($varname == 'HOME_FOLDER' || $varname == 'HELPHP_FOLDER') {
                //     $post[$varname] = '/'.trim($post[$varname],'/').'/';
                // }
                if ($varname == 'SITE_FOLDER' || $varname == 'ADMIN_FOLDER') {
                    $post[$varname] = trim($post[$varname],'/').'/';
                }
                
                // if ($varname == 'HOME_FOLDER') {
                //     $post['type'][$varname] = 'i';
                //     $post[$varname] = '\''.$post[$varname].'\'.Config::SITE_FOLDER';
                // }

                if ($varname == 'protocol'){
                    $protocol = $post[$varname];
                    $varname = 'BASE_URL';
                    $post['type'][$varname] = 'i';
                    $post[$varname] = '\''.$protocol.'://\'.Config::DOMAIN.\'/\'.Config::SITE_FOLDER';
                }

                array_push($to_save_name, $varname);
                array_push($to_save_val, $post[$varname]);
                array_push($to_save_type, $post['type'][$varname]);

                $post['prev'][$varname] = $post[$varname];
            }
        }
        
        if ($to_save_name){
            Utils::write_in_config($to_save_name, $to_save_type, $to_save_val);
        }

        return $this->display($post);
    }
}