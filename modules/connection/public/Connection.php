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
namespace helPHP\modules\connection\public;

use \Config;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\User;
use helPHP\libs\Mail;
use helPHP\libs\Utils;

class Connection extends HelPHP_module {

    const module_name = 'connection';

    protected $root_module = true;

    protected $ACTION_CONNECT = self::module_name.'_connect';
    protected $ACTION_DISCONNECT = self::module_name.'_disconnect';
    protected $ACTION_FORM_CREATE = self::module_name.'_form_create';

    protected $ACTION_FORM_PASSWORD = self::module_name.'_form_password';
    protected $ACTION_RECUPERATION_PASSWORD = self::module_name.'_recuperation_password';

    protected $ACTION_SEND_ACTIVATION = self::module_name.'_send_activation';

    protected $redirect = false;
    protected $chat = false;

    public function __construct($dom_container = null){
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);

        if ($this->options) {
            if (isset($this->options['redirection'])) {
                $this->redirect = $this->options['redirection'];
            }
        }
        
        global $CONFIG;
        if (isset($CONFIG::MODULES_LIST['chat'])) {
            $this->chat = true;
        }
    }

    public function process_data(&$post, $toreturn = false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        $master_output = H::group('connection_display');
        switch ($post[$this->input_action_identifier]) {

            case $this->ACTION_CONNECT:
                $this->connect($post);
                $master_output->add_child( $this->display_form_connection($post) );

                // open the modal connection
                global $USER;
                $state_open = $USER->connection_state != User::state_logged;
                $master_output->add_child( H::script('helphp_timeout(\'Connection.create_instance("'.$this->dom_id.'", '.addslashes(json_encode(['open'=>$state_open])).');\');', ['autoremove'=>1]) );
            break;
            case $this->ACTION_DISCONNECT:
                $master_output->add_child($this->disconnect($post));
            break;

            case $this->ACTION_FORM_CREATE:
                // $this->dom_target = $this->dom_target.'_create';
                // displays the form for creating a new account
                $master_output->add_child($this->display_form_create());
            break;

            case $this->ACTION_FORM_PASSWORD:
                $master_output->add_child($this->display_form_password());
            break;
            case $this->ACTION_RECUPERATION_PASSWORD:
                $this->recup_password($post);
                // hide modale
                $master_output->add_child( H::script($this->inst_js.'.hide_modal_password();') );
            break;
            
            // case 'recodepass':
                // $master_output->add_child($this->recup_password2($_REQUEST));
            // break;

            case $this->ACTION_SEND_ACTIVATION:
                $master_output->add_child($this->send_activation($post));
            break;

            default:
                if ((isset($post['connection']) && $post['connection']) || isset($post['showform'])) {
                    global $USER;
                    $this->dom_target = 'connection_container_form';
                    $subContainer = H::DIV(['class'=>$this->dom_target.'_sub','id'=>$this->dom_target.'_sub']);
                        //$subContainer->add_child($this->display_form_connection(false, $post));
                        $subContainer->add_child($this->display_form_create());
                    $master_output->add_child($subContainer);
                    if ($USER->connection_state == User::state_logged){
                        $subContainer->add_class('logged');
                    }
                } else if (isset($post['coninpage'])){
                    global $USER;
                    $this->dom_target = 'connection_container_form_inpage';
                    $subContainer = H::DIV(['class'=>$this->dom_target.'_sub','id'=>$this->dom_target.'_sub']);
                        $subContainer->add_child($this->display_form_connection(false, $post));
                        $master_output->add_child($subContainer);
                    if ($USER->connection_state == User::state_logged){
                        $subContainer->add_class('logged');
                    }
                }else{
                    // displays the default form or disconnect form
                    $master_output->add_child($this->display_form_connection(true));
                }

                $master_output->add_child( H::script('helphp_timeout(\'Connection.create_instance("'.$this->dom_id.'");\');') );
            break;
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }

    }
    public function connect(&$post) {
        global $CONFIG, $USER;

        // $this->sync_with_user();

        if (!$USER->error_list && !isset($post['origin'])){
            // user connected, reload the current page
            // $this->force_reload_delay = 0;
            $this->reload_after_message = $CONFIG::BASE_URL;
            // this.document.
        }
        
        if (!$USER->error_list && isset($post['origin'])) {
            $this->display->add_child(H::script('H_history.change_hash(event, "'.$post['origin'].'");'));
        }
        if ($USER->error_list && isset($post['origin'])) {
            $this->force_reload_delay = 5;
            $this->reload_after_message = $CONFIG::BASE_URL.'#'.$post['origin'];
        }

        // display a message to re-send activation code
        if ($USER->error_list && in_array('account_not_activated', $USER->error_list)){
            $form = H::form(['class'=>$this->css.'form_resend_activation', 'action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);
                $login = H::input_hidden(['name'=>'login', 'value'=>$post['login']]);
                $btn = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SEND_ACTIVATION, 'class'=>$this->css.'btn_send activation'], $this->get_tl('resend_activate'));
            $form->add_child( [$login, $btn] );

            $this->add_message('account_not_activated');
            $this->message_list[0] .= $form;
        }
    }

    public function disconnect(&$post){
        // $this->sync_with_user();
        $this->force_reload_delay = 1;
        $this->reload_after_message = $this->has_any_message() ? HelPHP_module::reload_no_params : false;
        // we return a space to clear the display of the connection div
        return ' ';
    }

    public function display_form_connection($flag = true, $post = []){
        global $USER;

        // synchronization with the data of the $USER class
        // in order to retrieve a possible account activation message
        // $this->sync_with_user();

        $output = H::group('mini_connection');

            $btn = H::BUTTON(['class'=>$this->css.'btn_open', 'onclick'=>$this->inst_js.'.toggle_form();', 'title'=>$this->get_tl('connection')], H::icon('user'));

        $output->add_child( $btn );

        if ($USER->connection_state != User::state_logged) {

            $output->add_child( $this->form_connect($post) );
        
        } else {

            $output->add_child( $this->form_disconnect($post) );

        }

        return $output;
    }
    public function form_connect($post){
        global $USER, $CONFIG;

        $form = H::form(['id'=>self::module_name.'_form_to_toggle'.$this->dom_id, 'action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_connect hidden', 'dom_id'=>$this->dom_id]);

            // action to send to lib user 
            $action_user = H::input_hidden(['name'=>'user_action', 'value'=>'connect', 'data-alwaysposted'=>1]);

        $form->add_child($action_user);

        if (isset($post['origin'])) {
            $form->add_child(H::input_hidden(['name'=>'origin', 'value'=>str_replace(':','=',$post['origin']), 'data-alwaysposted'=>1]));
        }
        if (isset($_GET['origin'])) {
            $form->add_child(H::input_hidden(['name'=>'origin', 'value'=>str_replace(':','=',$_GET['origin']), 'data-alwaysposted'=>1]));
        }

        if ($USER->connection_state == User::state_ban) {
            $info = H::SPAN(['class'=>$this->css.'info banned'], $this->get_tl('too_much_fail', $CONFIG::CONNECTION_TRY_BAN_HOURS));
            $form->add_child($info);
        } else if ($USER->nb_attempt > 0){
            $remaining_attempt = $CONFIG::MAX_USER_CONNECTION_ATTEMPTS - $USER->nb_attempt;
            $info = H::SPAN(['class'=>$this->css.'info bad_credential'], $this->get_tl('log_fail', $remaining_attempt));
            $form->add_child($info);
        }

            $login_input = H::input_text(['name'=>'login', 'data-required'=>1, 'label'=>$this->get_tl('login'), 'class'=>$this->css.'input_login', 'autocomplete'=>'off', 'placeholder'=>$this->get_tl('placeholder_login')]);
        
        $form->add_child([$login_input->label_tag() , $login_input ] );

            $password_input = H::input_password(['name'=>'password', 'data-required'=>1, 'label'=>$this->get_tl('password'), 'placeholder'=>$this->get_tl('placeholder_password'), 'autocomplete'=>'off', 'class'=>$this->css.'input_password' , 'data-returnsubmit'=>[$this->input_action_identifier=>$this->ACTION_CONNECT]]);
        
        $form->add_child([$password_input->label_tag() , $password_input ] );

            $ok_btn = H::submit_button(['name' => $this->input_action_identifier , 'value'=>$this->ACTION_CONNECT, 'class'=>$this->css.'btn_connect'], $this->get_tl('ok'));

        $form->add_child($ok_btn);

            // btns after credentials input, forget password, create account
            $btn_lost_password = H::BUTTON(['class'=>$this->css.'btn_lost_password', 'onclick'=>$this->inst_js.'.modal_lost_password();'], $this->get_tl('pass_forget'));
            $btn_create = H::BUTTON(['class'=>'connection_btn_create', 'onclick'=>$this->inst_js.'.toggle_form(); document.location.hash = "users";'], $this->get_tl('create'));

        $form->add_child( [$btn_lost_password, $btn_create] );

        return $form;
    }
    public function form_disconnect($post){
        global $USER;

        // command sent to the user class to disconnect
        $form = H::form(['id'=>self::module_name.'_form_to_toggle'.$this->dom_id, 'action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_connect disconnect hidden', 'dom_id'=>$this->dom_id]);
        
            // action to send to lib user 
            $action_user = H::input_hidden(['name'=>'user_action', 'value'=>'disconnect', 'data-alwaysposted'=>1]);
        
        $form->add_child( [$action_user] );

        $form->add_child(H::SPAN(['class'=>$this->css.'info_user'], $USER->user_data['firstname'].' '.$USER->user_data['lastname'].' '));
        
        // disconnect
        // if ($this->chat) {
        //     $form->add_child(H::BUTTON(array('type'=>'BUTTON', 'class'=>$this->css.'btn_disconnect', 'onclick'=>'spd2_chat.disconnect(event);'), $this->get_tl('deconnection')));
        // } else {
            $account = H::BUTTON(['class'=>$this->css.'btn_account', 'onclick'=>'document.location.hash = "users";'], $this->get_tl('my_account'));
            $disconnect = H::submit_button(['name' => $this->input_action_identifier , 'value'=>$this->ACTION_DISCONNECT], $this->get_tl('disconnect'));

        $form->add_child( [$account, $disconnect] );

        return $form;
    }

    public function display_form_create(){
        global $CONFIG, $module_html_content;

        $_POST['users_action'] = 'users_edit';
        $_POST['core_insert'] = 1;
        include($CONFIG::HOME_FOLDER.'public/users/index.php');

        $this->reload_after_message = $this->has_any_message();

        return $module_html_content['users'];
    }

    public function display_form_password(){
        $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_password', 'dom_id'=>$this->dom_id));

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('reinit_password'));
            // $info = H::DIV(['class'=>$this->css.'info'], $this->get_tl('info_password'));

        $form->add_child([$title]);

            $input = H::input_text(['name'=>'login_mail', 'label'=>$this->get_tl('info_password'), 'data-required'=>1]);

        $form->add_child( [$input->label_tag(), $input]);

            $send = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_RECUPERATION_PASSWORD, 'class'=>$this->css.'btn_send password','with_token'=>1], $this->get_tl('send_password'));

        $form->add_child( $send );

        return $form;
    }
    
    public function recup_password($post){
        global $CONFIG, $DB_CENTRAL;

        $q = 'SELECT * FROM '.$DB_CENTRAL->table('users_data').' WHERE email=? OR login=?';
        $user_data = $DB_CENTRAL->prepared_query_line($q, 'ss', [$post['login_mail'], $post['login_mail']]);

        if (!$user_data){
            $this->add_message('sending_mail_if_account_exist');
            return ;
        }
        
        if (!isset($user_data['email']) || $user_data['email'] == '') {
            $this->add_message('sending_mail_if_account_exist');
            Utils::error_log('Try to send a recuperation link to a user account without saved email. Account id is '.$user_data['id']);
            return;
        }

        // set an activation code to the user account found
        $code = $user_data['id'].md5($user_data['login'].$user_data['email'].time());
        $q = 'UPDATE '.$DB_CENTRAL->table('users_data').' SET activation_code="'.$code.'" WHERE id='.$user_data['id'];
        $DB_CENTRAL->prepared_query($q);
        
        // send the code by mail to the user, with a link to reset password
        $mail_content = $this->get_tl('reinit_password_mail', [$user_data['login'], $CONFIG::DOMAIN]);

        // $link_style = 'style="display: inline-block;text-decoration: none;outline: none;text-align: center;box-shadow: 0px 0px 0px #5d5d5d;cursor: pointer;border: 1px solid transparent;color: #FAFAFA;padding: 8px 20px;background: #0894D2;border-radius: 3px;text-shadow: none;"';
        $mail_content.= '<a href='.$CONFIG::BASE_URL.'?users=|users_action=users_new_password|code='.$code.'>Reset password</a></br>';
        
        $mail = new Mail();
        global $CONFIG_EMAIL;
        if ($CONFIG_EMAIL::EMAIL_SIGNATURE_BODY != ''){
            $mail_content.= $CONFIG_EMAIL::EMAIL_SIGNATURE_BODY;
            foreach($CONFIG_EMAIL::EMBEDED as $to_embed){
                $mail->mail->AddEmbeddedImage($CONFIG::HOME_FOLDER.$to_embed['src'], $to_embed['name']);
            } 
        }

        $is_send = $mail->send($user_data['email'], $this->get_tl('reinit_password'), $mail_content, $CONFIG_EMAIL::EMAIL_MAILING);
        if (!$is_send) Utils::error_log('Error sending mail to '.$user_data['email']);

        $this->add_message( 'sending_mail_if_account_exist' );
    }
    
    public function send_activation($post){

        // call user funtion to resend mail
        $user_mod = new \helPHP\modules\users\public\Users();
        $sent = $user_mod->resend_activate($post['login']);

        if ($sent){
            return H::DIV(['class'=>$this->css.'send_result success'], $this->get_tl('mail_activate_success'));
        } else {
            return H::DIV(['class'=>$this->css.'send_result error'], $this->get_tl('mail_activate_error'));
        }
        
    }
}