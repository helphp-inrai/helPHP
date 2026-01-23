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

namespace helPHP\modules\connection\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\User;
use helPHP\libs\Utils;

class Connection extends HelPHP_module {

    const module_name = 'connection';

    protected $root_module = true;
    
    private $ACTION_CONNECT = self::module_name.'_connect';
    private $ACTION_DISCONNECT = self::module_name.'_disconnect';
    private $ACTION_UPD_VARS = self::module_name.'_update_vars';
    
    protected $chat = false;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container);

        global $CONFIG;
        if (isset($CONFIG::MODULES_LIST['chat'])) {
            $this->chat = true;
        }
    }
    public function process_data(&$post,$toreturn=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        $master_output = H::group(self::module_name.'_display');

        global $CONFIG;
        if ($CONFIG::FIRST_USE == true) {
            $master_output->add_child($this->display_create_first_admin_form());
            if ($toreturn){
                return $master_output;
            }else{
                $this->display->add_child( $master_output );
            }
        }

        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_CONNECT:
                $master_output->add_child($this->connect($post));
            break;
            case $this->ACTION_DISCONNECT:
                $master_output->add_child($this->disconnect($post));
            break;
            
            case $this->ACTION_UPD_VARS:
                $master_output->add_child($this->upd_user_vars($post));
            break;

            default:
                // display the default form or the disconnect one.
                $master_output->add_child($this->display_form());
            break;
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    public function connect(&$post) {
        $this->force_reload_delay = 1;
        $this->reload_after_message = $this->has_any_message() ? HelPHP_Module::reload_no_params : false;
        return;
    }
    public function disconnect(&$post) {
        global $DB, $CONFIG;
        // if ($CONFIG::DEMO == true) {
        //     $nb = $DB->query('update '.$CONFIG::DB_TABLE_PREFIX.'_users_data set firstname="dead" where login="demo0000"');
        // }
        $this->sync_with_user();
        $this->force_reload_delay = 1;
        $this->reload_after_message = $this->has_any_message() ? HelPHP_Module::reload_no_params : false;
        return ' ';
    }
    public function display_form() {
        global $USER;

        $this->sync_with_user();

        if ($USER->connection_state == User::state_ban) {
            $div = H::DIV(['class'=>$this->css.'banned'], $this->get_tl('banned'));
            return $div;
        }

        if ($USER->connection_state != User::state_logged) {
            $this->dom_container = 'log_in_'.$this->dom_container;
            return $this->form_connect();
        } else {
            $this->dom_container = 'logged_'.$this->dom_container;
            return $this->form_disconnect();
        }
    }
    public function form_connect(){
        $form = H::form(array('action'=>$this->get_index_relative_path(),'dom_target'=>'.parent', 'class'=>$this->css.'form connect'));

            $user_action = H::input_hidden(['name'=>'user_action', 'data-alwaysposted'=>1, 'value'=>'connect']);
            $ttl = H::SPAN(['class'=>$this->css.'ttl connect'], $this->get_tl('connection'));

        $form->add_child([$user_action,$ttl]);
        
        global $USER, $CONFIG;
        // display a message to indicate number of try before ban or ban
        if ($USER->connection_state == User::state_ban) {
            $info = H::SPAN(['class'=>$this->css.'info banned'], $this->get_tl('too_much_fail', $CONFIG::CONNECTION_TRY_BAN_HOURS));
            $form->add_child($info);
        } else if ($USER->nb_attempt > 0){
            $remaining_attempt = $CONFIG::MAX_USER_CONNECTION_ATTEMPTS - $USER->nb_attempt;
            $info = H::SPAN(['class'=>$this->css.'info bad_credential'], $this->get_tl('log_fail', $remaining_attempt));
            $form->add_child($info);
        }

        // display type of error
        // Utils::error_log($USER);

            $login_input = H::input_text(['name'=>'login', 'data-required'=>1, 'label'=>$this->get_tl('login'), 'autocomplete'=>'username', 'placeholder'=>$this->get_tl('placeholder_login')]);
            $password_input = H::input_password(['name'=>'password', 'data-required'=>1, 'label'=>$this->get_tl('password'), 'autocomplete'=>'current-password', 'placeholder'=>$this->get_tl('placeholder_password'), 'data-returnsubmit'=>[$this->input_action_identifier=>$this->ACTION_CONNECT]]);
            $btn_send = H::submit_button(['class'=>$this->css.'btn connect', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_CONNECT], $this->get_tl('connect'));

        $form->add_child([$login_input,$password_input,$btn_send]);

        return $form;
    }
    public function form_disconnect(){
        global $USER;

        $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form disconnect'));
            
            $user_action = H::input_hidden(['name'=>'user_action', 'data-alwaysposted'=>1, 'value'=>'disconnect']);
            $ttl = H::SPAN(['class'=>$this->css.'ttl disconnect'], $this->get_tl('bienvenue').' '.$USER->user_data['lastname'].' '.$USER->user_data['firstname']);
            if ($this->chat) {
                $btn_disconnect = H::BUTTON(['class'=>$this->css.'btn disconnect', 'onclick'=>'spd2_chat.disconnect(event);'], $this->get_tl('deconnection'));
            } else {
                $btn_disconnect = H::submit_button(['class'=>$this->css.'btn disconnect', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DISCONNECT], $this->get_tl('deconnection'));
            }

        $form->add_child([$user_action,$ttl,$btn_disconnect]);
        
        return $form;
    }

    public function display_create_first_admin_form() {
        global $module_html_content;

        $_POST['users_action'] = 'users_edit';
        $_POST['core_insert'] = 1;
        include($CONFIG::HOME_FOLDER.'public/users/index.php');

        $this->reload_after_message = $this->has_any_message();

        return $module_html_content['users'];
    }
    
    public function upd_user_vars($post) {
        global $DB, $USER;
        //~ UTILS_Class::error_log($post);
        $vars = json_decode($USER->user_data['vars'], true);
        if (isset($post['help'])) {
            $vars['help'] = false;
        }
        $vars = json_encode($vars);
        //~ UTILS_Class::error_log($vars);
        $q = 'UPDATE '.$this->build_module_table_name('users', 'data').' SET vars=? WHERE id='.$USER->id;
        $DB->prepared_query($q, 's', [$vars]);
    }
}
?>