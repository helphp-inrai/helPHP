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
namespace helPHP\modules\users\public;

use \Config;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\User;
use helPHP\libs\Mail;
use helPHP\libs\Utils;

class Users extends HelPHP_module{

    const module_name = 'users';
    
    protected $ACTION_EDIT = self::module_name.'_edit';
    protected $ACTION_SAVE = self::module_name.'_save';
    protected $ACTION_DELETE = self::module_name.'_delete';

    protected $ACTION_ACTIVATE = self::module_name.'_activate';
    // protected $ACTION_NEW_ACTIVATE = self::module_name.'_new_activate';
    
    protected $ACTION_NEW_PASSWORD = self::module_name.'_new_password';
    protected $ACTION_SAVE_PASSWORD = self::module_name.'_save_password';
    
    public $privacy = false;
    public $address = false;

    // autocomplete field list
    // : https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,false);
        parent::__construct();
        
        global $DB_CENTRAL;
        $this->address = $DB_CENTRAL->table_exists($DB_CENTRAL->table('users_address'), true);
        
        if (isset($this->options['privacy']) && $this->options['privacy']) $this->privacy = true;
    }

    public function process_data(&$post,$toreturn=false){
        parent::process_data($post);

        $master_output = H::group('users_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_SAVE:
                $this->check_posted_data($post,'users_data');
                
                $master_output->add_child( $this->save($post) );
            break;
            case $this->ACTION_DELETE:
                $this->check_posted_data($post,'users_data');

                $master_output->add_child( $this->delete($post) );
                $post[$this->ifld_data_id] = 0;
            break;
            
            case $this->ACTION_ACTIVATE:
                $master_output->add_child( $this->activate($post) );
            break;
            // case $this->ACTION_NEW_ACTIVATE:
                // $master_output->add_child( $this->new_activate($post) );
            // break;

            case $this->ACTION_NEW_PASSWORD:
                $master_output->add_child( $this->form_new_password($post) );
            break;
            case $this->ACTION_SAVE_PASSWORD:
                $master_output->add_child( $this->save_password($post) );
            break;
            
            case $this->ACTION_EDIT:
            default:
                global $USER;
                $post[$this->ifld_data_id] = isset($post[$this->ifld_data_id]) ? $post[$this->ifld_data_id] : $USER->id;
                $this->prepare_fields($post, 'users_data');
                
                $master_output->add_child( $this->edit($post) );
            break;
        }
        
        $this->sync_with_user();
        
        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    // public function form_activate(&$post) {
    //     global $DB;
        
    //     $form = H::form(array('action'=>$this->get_index_relative_path(),'dom_target'=>'cart_subcontainer_usersauto', 'class'=>'pre_activate'));
    //         $txt = H::SPAN(['class'=>$this->css.'info'], $this->get_tl('account_exist_must_activate'));
    //         $mail_input = H::input_hidden(['name'=>$this->ifld_data_email, 'value'=>$post[$this->ifld_data_email], 'class'=>'users_form_input','data-required'=>1 ]);
    //         $activationcode_input = H::input_text(['name'=>$this->ifld_data_activation, 'class'=>'users_form_input','label'=>$this->get_tl('activation_code') ]);
    //     $form->add_child([$txt,$mail_input,$activationcode_input->label_tag(),$activationcode_input]);
    //     $form->add_child(H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_ACTIVATE, 'class'=>'users_public_btn_send'], $this->get_tl('activate')));
    //     $form->add_child(H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_NEW_ACTIVATE, 'class'=>'users_public_btn_send'], $this->get_tl('new_activation_code')));

    //     return $form;
    // }
    public function resend_activate($login) {
        global $DB, $USER;
        $data = $DB->prepared_query_line('SELECT DISTINCT id, email, active, activation_code FROM '.$DB->table('users_data').' WHERE login=?', 's', [$login]);
        if ($data['active'] == 0 && $data['activation_code'] != ''){
            $code = $USER->generate_activation_code($data['id']);

            return $this->send_mail_activate($data['email'], $login, $code);
        }
    }

    public function send_mail_activate($email, $username, $code){
        global $USER, $CONFIG;

        $mailer = new Mail();

        $url_activate = $CONFIG::BASE_URL.'?users|users_action=users_activate|'.User::activate.'='.$code.'|login='.$username;

        // $link_style = 'style="display: inline-block;text-decoration: underline;outline: none;text-align: center;box-shadow: 0px 0px 0px #5d5d5d;cursor: pointer;border: 1px solid transparent;color: #FAFAFA;padding: 8px 20px;background: #0894D2;border-radius: 3px;text-shadow: none;"'
        $link = '<a href="'.$url_activate.'">'.$this->get_tl('click_me_activate').'</a>';
        $msg = $this->get_tl('email_activate_body', [$username, $link]);

        global $CONFIG_EMAIL;
        if ($CONFIG_EMAIL::EMAIL_SIGNATURE_BODY != ''){
            $msg.= $CONFIG_EMAIL::EMAIL_SIGNATURE_BODY;
            foreach($CONFIG_EMAIL::EMBEDED as $toembed){
                $mailer->mail->AddEmbeddedImage($CONFIG::HOME_FOLDER.$toembed['src'], $toembed['name']);
            } 
        }

        $is_send = $mailer->send($email, $this->get_tl('email_activate_subject', $CONFIG::SITE_NAME), $msg, $CONFIG_EMAIL::EMAIL_CONTACT);
        if (!$is_send) {
            $this->add_error('send_email_error');
            return false;
        }
        return true;
    }
    
    public function activate(&$post) {
        global $CONFIG;
        
        $this->sync_with_user();
        
        if (!$this->error_list){
            array_push($this->message_list, $this->get_tl('activation_success'));
        }
        
        $this->reload_after_message = $CONFIG::BASE_URL.'#connection=true';
        
        return ' ';
    }
    
    public function save(&$post) {
        global $USER, $CRYPT, $DB_CENTRAL, $CONFIG;

        $output = '';
        
        $this->sync_with_user();
        
        $edited_password = false;

        if ($USER->account_created) {
            $id = $USER->id;

            $post[$this->ifld_data_id] = $id;

            $q = 'UPDATE '.$this->bddt_data.' SET lastname=?, firstname=?, entity=?, vat_id=? WHERE id=?';
            $DB_CENTRAL->prepared_query($q, 'ssssi', [$post[$this->ifld_data_lastname],$post[$this->ifld_data_firstname],$post[$this->ifld_data_entity],$post[$this->ifld_data_vat_id],$id]);
            
            $q = 'INSERT INTO '.$this->bddt_address.' SET id_country_data=?, id_country_state=?, street=?, postal_code=?, city=?, id_users_data=?';
            $DB_CENTRAL->prepared_query($q,'iisssi',[$post[$this->ifld_address_id_country_data],$post[$this->ifld_address_id_country_state],$post[$this->ifld_address_street],$post[$this->ifld_address_postal_code],$post[$this->ifld_address_city],$id]);

            $connect_data = [];
            $connect_data['login'] = $post[$this->ifld_data_login];
            $connect_data['password'] = $post['password'];
            $connect_data['user_action'] = 'connect';

            $code = $USER->generate_activation_code($id);

            if (is_string($code)) {
                $this->send_mail_activate($post[$this->ifld_data_email], $post[$this->ifld_data_login], $code);

                $this->reload_after_message = 'create_account_success';
            }
            else $this->add_error('erreur generation code activation');

        } elseif ($post[$this->ifld_data_id] > 0) {
            
            // update account
            $this->check_posted_data($post, 'users_data');
            
            // password update classic
            if (isset($post['old_password']) && $post['old_password'] && isset($post['password']) && $post['password']) {
                $q = 'SELECT password_hash FROM '.$this->bddt_data.' WHERE id=?';
                $hash = $DB_CENTRAL->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);

                $check_pass = $CRYPT->verify_password_hash($post['old_password'], $hash);

                if ($check_pass) {
                    if (!isset($post['password'])) {
                        $post['password'] = '';
                    }

                    $post['password'] = trim($post['password']);

                    $min_size = $CONFIG::USERPASSWORD_MINIMUM_LENGTH == null ? 6 : $CONFIG::USERPASSWORD_MINIMUM_LENGTH;

                    if (strlen($post['password']) < $min_size) {
                        $this->add_error('invalid_new_password');
                    } else {
                        if (isset($post['password']) && $post['password'] == $post['old_password']) {
                            $this->add_error('same_old_new_passwords');
                        } else {
                            $new_pass = $post['password'];
                            $new_hash = $CRYPT->create_password_hash($new_pass);

                            $q = 'UPDATE '.$this->bddt_data.' SET password_hash=? WHERE id=?';
                            $DB_CENTRAL->prepared_query($q, 'si', [$new_hash , $post[$this->ifld_data_id]]);
                            
                            $edited_password = true;
                        }
                    }
                } else {
                    $this->add_error('invalid_old_password');
                }
            } elseif (isset($post['password']) && $post['password']) {
                $this->add_error('invalid_old_password');
            }

            // mise à jour de l'adresse email
            if ($post[$this->ifld_data_email] != '') {
                if (filter_var($post[$this->ifld_data_email], FILTER_VALIDATE_EMAIL)) {
                    $q = 'SELECT DISTINCT COUNT(*) FROM '.$this->bddt_data.' WHERE email=? AND id<>?';
                    $exists = $DB_CENTRAL->prepared_query_value($q, 'si', [$post[$this->ifld_data_email] , $post[$this->ifld_data_id]]);

                    if ($exists) {
                        $this->add_error('email_used', $post[$this->ifld_data_email]);
                    } else {
                        $q = 'UPDATE '.$this->bddt_data.' SET email=? WHERE id=?';
                        $DB_CENTRAL->prepared_query($q, 'si', [$post[$this->ifld_data_email] , $post[$this->ifld_data_id]]);
                    }
                } else {
                    $this->add_error('invalid_email');
                }
            }

            if ($this->get_error_count() == 0) {
                $q = 'UPDATE '.$this->bddt_data.' SET lastname=?, firstname=?, entity=? WHERE id=?';
                $DB_CENTRAL->prepared_query($q, 'sssi', [$post[$this->ifld_data_lastname], $post[$this->ifld_data_firstname], $post[$this->ifld_data_entity], $post[$this->ifld_data_id]]);
                
                if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
                    $temp = $_POST;
                    $_POST = [];
                    $_POST['mode'] = 'edit';
                    $_POST['login'] = isset($temp[$this->ifld_data_login]) ? $temp[$this->ifld_data_login] : '';
                    $_POST['email'] = isset($temp[$this->ifld_data_email]) ? $temp[$this->ifld_data_email] : '';
                    $_POST['lastname'] = isset($temp[$this->ifld_data_lastname]) ? $temp[$this->ifld_data_lastname] : '';
                    $_POST['firstname'] = isset($temp[$this->ifld_data_firstname]) ? $temp[$this->ifld_data_firstname] : '';
                    $_POST['id'] = isset($temp[$this->ifld_data_id]) ? $temp[$this->ifld_data_id] : '';
                    if ($edited_password){
                        $_POST['new_password'] = 1;
                        $_POST['password'] = $temp['password'];
                    } else {
                        $_POST['new_password'] = 0;
                    }
                    include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
                    $_POST = $temp;
                } else {
                    Utils::error_log('error user_extend.php not found');
                }

                $this->add_message('save_success');
            }
        }
        
        if ($this->get_error_count() == 0) {
            $this->check_posted_data($post, 'users_address');
            
            if ($post[$this->ifld_address_id] > 0){
                $q = 'UPDATE '.$this->bddt_address.' SET id_country_data=?,id_country_state=?,street=?,postal_code=?,city=? WHERE id=?';
                $DB_CENTRAL->prepared_query($q,'iisssi',[$post[$this->ifld_address_id_country_data],$post[$this->ifld_address_id_country_state],$post[$this->ifld_address_street],$post[$this->ifld_address_postal_code],$post[$this->ifld_address_city],$post[$this->ifld_address_id]]);
            }
            
            $q = 'SELECT vat_id FROM '.$this->bddt_data.' WHERE id=?';
            $prev_vat_id = $DB_CENTRAL->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);
            if ($prev_vat_id != $post[$this->ifld_data_vat_id]){
                $q = 'UPDATE '.$this->bddt_data.' SET vat_id=?, valid_vat_id=0 WHERE id=?';
                $DB_CENTRAL->prepared_query($q, 'si', [$post[$this->ifld_data_vat_id],$post[$this->ifld_data_id]]);
                // if ($post[$this->ifld_data_vat_id]!='' && $this->check_vies){
                //     include_once('class.vies.php');
                //     $vatValidation = new Vies( array('debug' => false));
                //     if($vatValidation->check($post[$this->ifld_data_vat_id])) {
                //         $q = 'UPDATE '.$this->bddt_data.' SET valid_vat_id=1 WHERE id=?';
                //         $DB_CENTRAL->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
                //     }
                // }
            }
        }

        return $output;
    }
    
    public function edit($post) {
        global $LANG, $DB_CENTRAL, $CONFIG;
        
        $handle_return_id = self::module_name.'edit_handle_return'.$this->dom_id;

        $output = H::group('users_edit');

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$handle_return_id, 'class'=>$this->css.'form edit form_edit', 'dom_id'=>$this->dom_id]);
        
            $form->add_child( H::input_hidden(['name'=>$this->ifld_data_id , 'data-alwaysposted'=>1 , 'value' => $post[$this->ifld_data_id]]) );

        $title = '';
        if ($post[$this->ifld_data_id] > 0) {
            $title = H::SPAN(['class'=>$this->css.'title module_title'], $this->get_tl('modify'));
        } else {
            if ($CONFIG::FIRST_USE == true) {
                $entete_first = H::SPAN(['class'=>'users_account_firstmessage'], $this->get_tl('first_use'));
                $form->add_child($entete_first);
            }
            $title = H::SPAN(['class'=>$this->css.'title module_title'], $this->get_tl('new'));
            $form->add_child(H::input_hidden(array('name'=>'user_action' , 'data-alwaysposted'=>1 , 'value' => 'create' )));
        }

        $output->add_child([$title, $form]);

        if ($post[$this->ifld_data_id] > 0) {
            $form->add_child(H::DIV(['class'=>'users_form_label'], $this->get_tl('login')));
            $form->add_child(H::DIV(['class'=>'users_form_input fixed_value'], $post[$this->ifld_data_login]));
            $form->add_child(H::input_hidden(['name'=>$this->ifld_data_login, 'value'=>$post[$this->ifld_data_login], 'data-alwaysposted'=>1]));

            // ancien password
            //$old_password = H::input_password(['name'=>'old_password', 'class'=>'users_form_input' , 'autocomplete'=>'current-password' , 'data-optional'=>1]);
            $old_password = H::input_password(['name'=>'old_password', 'class'=>'users_form_input' , 'autocomplete'=>'current-password' , 'data-optional'=>1]);
            $form->add_child($old_password->label_tag($this->get_tl('old_password'), ['class'=>'users_form_label']));
            $form->add_child($old_password);
            
            // nouveau password
            $password_input = H::input_password(array('name'=> 'password', 'class'=>'users_form_input', 'label'=>$this->get_tl('new_password') , 'autocomplete'=>'new-password' , 'data-optional'=>1));
        } else {

            $login_input = H::input_text(array('name'=>$this->ifld_data_login, 'class'=>'users_form_input' , 'value'=>$post[$this->ifld_data_login], 'data-required'=>1 ,'data-restrict'=>'_username' ));
            $form->add_child($login_input->label_tag($this->get_tl('login'), ['class'=>'users_form_label']));
            $form->add_child($login_input);
        
            //password
            $password_input = H::input_password(array('name'=> 'password', 'class'=>'users_form_input', 'label'=>$this->get_tl('password') , 'autocomplete'=>'off' ));

        }

        $form->add_child($password_input->label_tag(null, ['class'=>'users_form_label']));
        $form->add_child($password_input);
        
        $mail_input = H::input_email(['name'=>$this->ifld_data_email, 'value'=>$post[$this->ifld_data_email], 'class'=>'users_form_input' , 'autocomplete'=>'email','data-required'=>1 ]);
        $form->add_child($mail_input->label_tag($this->get_tl('email'), ['class'=>'users_form_label']));
        $form->add_child($mail_input);

        $lastname_input = H::input_text(['name'=>$this->ifld_data_lastname, 'value'=>$post[$this->ifld_data_lastname], 'class'=>'users_form_input','data-required'=>1, 'label'=>$this->get_tl('lastname') ,'autocomplete'=>'family-name' ]);
        $form->add_child($lastname_input->label_tag(null, ['class'=>'users_form_label']));
        $form->add_child($lastname_input);

        $firstname_input = H::input_text(['name'=>$this->ifld_data_firstname, 'value'=>$post[$this->ifld_data_firstname], 'class'=>'users_form_input','data-required'=>1, 'label'=>$this->get_tl('firstname') ,'autocomplete'=>'given-name' ]);
        $form->add_child($firstname_input->label_tag(null, ['class'=>'users_form_label']));
        $form->add_child($firstname_input);

        $entity_input = H::input_text(['name'=>$this->ifld_data_entity, 'value'=>$post[$this->ifld_data_entity], 'class'=>'users_form_input', 'label'=>$this->get_tl('entity') ,'autocomplete'=>'organization' ]);
        $form->add_child($entity_input->label_tag(null, ['class'=>'users_form_label']));
        $form->add_child($entity_input);
        
        $vat_id_input = H::input_text(['name'=>$this->ifld_data_vat_id, 'value'=>$post[$this->ifld_data_vat_id], 'class'=>'users_form_input', 'label'=>$this->get_tl('vat_id') ]);
        $form->add_child($vat_id_input->label_tag(null, ['class'=>'users_form_label']));
        $form->add_child($vat_id_input);

        if ($this->privacy){
            $invert = H::DIV(['class'=>$this->css.'invert_input_label']);
                $privacy = H::input_checkbox(['name'=>'accept_privacy','value'=>1,'data-required'=>1,'label'=>$this->get_tl('privacy')]);
            $invert->add_child([$privacy, $privacy->label_tag()]);
            $form->add_child($invert);
        }

        if (isset($CONFIG::MODULES_LIST['abonewsletter'])){
            $invert = H::DIV(['class'=>$this->css.'invert_input_label']);
                $newsletter = H::input_checkbox(['name'=>'newsletter','value'=>1,'label'=>$this->get_tl('newsletter')]);
            // $form->add_child([$newsletter,$newsletter->label_tag(null, ['class'=>'users_form_label invert'])]);
            $invert->add_child([$newsletter,$newsletter->label_tag()]);
            $form->add_child($invert);
        }
        
        // ADDRESS PART
        if ($this->address){
            $q = 'SELECT id FROM '.$this->bddt_address.' WHERE id_users_data=?';
            $id_address = $DB_CENTRAL->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);
            $post[$this->ifld_address_id] = $id_address ? $id_address : 0;
            
            $this->prepare_fields($post, 'users_address');
            
            $form->add_child( H::input_hidden(['name'=>$this->ifld_address_id, 'value'=>$post[$this->ifld_address_id], 'data-alwaysposted'=>1]) );
            
            $street = H::input_text(['name'=>$this->ifld_address_street,'id'=>$this->ifld_address_street, 'value'=>$post[$this->ifld_address_street], 'class'=>'users_form_input', 'label'=>$this->get_tl('street') ,'autocomplete'=>'street-address']);
            $postal_code = H::input_text(['name'=>$this->ifld_address_postal_code,'id'=>$this->ifld_address_postal_code, 'value'=>$post[$this->ifld_address_postal_code], 'class'=>'users_form_input', 'label'=>$this->get_tl('postal_code') ,'autocomplete'=>'postal-code']);
            $city = H::input_text(['name'=>$this->ifld_address_city,'id'=>$this->ifld_address_city, 'value'=>$post[$this->ifld_address_city], 'class'=>'users_form_input', 'label'=>$this->get_tl('city')]);
            $form->add_child([$street->label_tag(null, ['class'=>'users_form_label']),$street,$city->label_tag(null, ['class'=>'users_form_label']),$city,$postal_code->label_tag(null, ['class'=>'users_form_label']),$postal_code]);
            
            $lang_iso = ($LANG->current_language == 'fr' || $LANG->current_language == 'en') ? $LANG->current_language : 'own';
            $q = 'SELECT '.$lang_iso.' FROM '.$DB_CENTRAL->table('country_data').' WHERE id=?';
            $lab = $DB_CENTRAL->prepared_query_value($q, 'i', [$post[$this->ifld_address_id_country_data]]);

            $opts = [
                'name'=>$this->ifld_address_id_country_data,
                'centraldb'=>true,
                'id'=>$this->ifld_address_id_country_data,
                'label'=>$this->get_tl('country'),
                'class'=>$this->css,
                'value'=>$post[$this->ifld_address_id_country_data],
                'value_label'=>$lab
            ];
            $country = H::input_autocomplete($opts, 'country_data', $lang_iso, false, 'h.modules_class.Users.on_change_country');
            $form->add_child([$country->label_tag(null, ['class'=>'users_form_label']), $country]);

            $q = 'SELECT id, name FROM '.$DB_CENTRAL->table('country_state').' ORDER BY name';
            $list = $DB_CENTRAL->query_list($q);
            $opts_data = ['first_empty'=>true, 'label_key'=>'name', 'value_key'=>'id', 'options'=>$list];
            $select_state = H::select(['name'=>$this->ifld_address_id_country_state, 'id'=>$this->ifld_address_id_country_state, 'label'=>$this->get_tl('state')], $opts_data, $post[$this->ifld_address_id_country_state]);
            $label = $select_state->label_tag(null, ['class'=>'users_form_label']);
            // depending the country (USA) will hide/display the state selector
            if ($post[$this->ifld_address_id_country_data] != 64) {
                $label->add_class('hidden');
                $select_state->add_class('hidden');
            }
            $form->add_child([$label, $select_state]);

            // $script = H::script('Users.on_change_country();');
            // $form->add_child( $script );
        }
        
        $block_btns = H::DIV(['class'=>$this->css.'block_btn edit_buttons']);
            $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save'], $this->get_tl('tlc_save'));
        $block_btns->add_child( $btn_save );
        if ($post[$this->ifld_data_id] > 0) {
            $btn_del = H::submit_button_single(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'class'=>$this->css.'btn_delete button_delete', 'data-confirm'=>$this->get_tl('confirm_delete')], $this->get_tl('tlc_delete'));
            $block_btns->add_child( $btn_del );
        }

        $form->add_child($block_btns);

        // to handle form return
        $handle_return = H::DIV(['class'=>$this->css.'handle_return', 'id'=>$handle_return_id]);
        $form->add_child($handle_return);

        // if ($this->address){
        //     $js = H::script('setTimeout(Users.init_change_country, 300);', ['autoremove'=>'autoremove']);
        //     $form->add_child($js);
        // }

        return $output;
    }
    
    public function delete($post) {
        global $USER, $DB_CENTRAL, $CONFIG;

        $q = 'SELECT * FROM '.$this->bddt_data.' WHERE id=?';
        $data = $DB_CENTRAL->prepared_query_line($q, 'i', [$USER->id]);
        if ($this->mailing) {
            $bdd_mail = $this->build_module_table_name('mailing', 'abonnement');
            $q = 'SELECT DISTINCT id FROM '.$bdd_mail.' WHERE email=?';
            $id_email = $DB_CENTRAL->prepared_query_value($q, 's', [$data['email']]);

            if ($id_email) {
                $q = 'DELETE FROM '.$bdd_mail.' WHERE id='.$id_email;
                $DB_CENTRAL->query($q);
            }
        }

        if ($this->address) {
            $q = 'DELETE FROM '.$this->bddt_address.' WHERE id_users_data=?';
            $DB_CENTRAL->prepared_query($q, 'i', [$data['id']]);
        }

        $q = 'UPDATE '.$this->bddt_data.' SET email=\'\', active=0, login=\'\', password_hash=\'\', lastname=\'\', firstname=\'\' WHERE id=?';
        $res = $DB_CENTRAL->prepared_query($q, 'i', [$data['id']]);
        
        if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
            $temp = $_POST;
            $_POST = [];
            $_POST['mode'] = 'delete';
            $_POST['login'] = $temp['login'];
            $_POST['id'] = $temp['id'];
            include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
            $_POST = $temp;
        } else {
            Utils::error_log('error user_extend.php not found');
        }

        if ($res) {
            $output = H::group('delete info');
            //force disconnect
            $connect_data=[];
            $connect_data['user_action'] = 'disconnect';
            $USER->check_connection_data($connect_data);
            $USER->message_list = [];
            $USER->error_list = [];
            $output->add_child(H::DIV(['class'=>'users_public_info_delete'], $this->get_tl('del_success')));
            $output->add_child(H::script('setTimeout(function(){window.location.reload();}, 2000)'));
            return $output;
        }
    }

    public function form_new_password($post){
        global $DB_CENTRAL;

        global $CRYPT, $CONFIG;

        $user_data = false;
        if (isset($post['code']) && $post['code'] != '') {
            $q = 'SELECT id FROM '.$DB_CENTRAL->table('users_data').' WHERE activation_code=?';
            $user_data = $DB_CENTRAL->prepared_query_line($q, 's', [$post['code']]);
        }

        if (!$user_data) {
            $this->add_error('code_unknow');
            return;
        }
        
        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_edit password form_edit', 'dom_id'=>$this->dom_id]);

            $title = H::DIV(['class'=>$this->css.'title password module_title'], $this->get_tl('ttl_password'));
            $hidden_id = H::input_hidden(['name'=>'code', 'value'=>$post['code']]);

        $form->add_child( [$title, $hidden_id] );

            $input_password = H::input_password(['name'=>'password', 'label'=>$this->get_tl('new_password'), 'autocomplete'=>'new-password', 'data-required'=>1]);
            $input_password_bis = H::input_confirmation($input_password, $this->get_tl('new_password_bis'));

        $form->add_child( [$input_password->label_tag(), $input_password, $input_password_bis->label_tag(), $input_password_bis] );

        $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_actions']);
            $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_PASSWORD, 'class'=>$this->css.'btn_save button_save', 'title'=>$this->get_tl('set_new_password')], $this->get_tl('set_new_password'));
        $block_btns->add_child( $btn_save );

        $form->add_child( $block_btns );

        return $form;
    }
    public function save_password($post) {
        global $DB_CENTRAL, $CRYPT;

        $output = H::group('save_password');

        $output->add_child( H::DIV(['class'=>$this->css.'title password module_title'], $this->get_tl('ttl_password')) );

        if (!isset($post['code']) || !$post['code'] || !isset($post['password']) || !isset($post['confirm_password']) || $post['password'] != $post['confirm_password']) {
            $output->add_child( H::DIV(['class'=>$this->css.'error password'], $this->get_tl('err_password')) );
            return $output;
        }

        $hash = $CRYPT->create_password_hash($post['password']);

        $q = 'UPDATE '.$DB_CENTRAL->table('users_data').' SET password_hash=?, activation_code="" WHERE activation_code=?';
        $success = $DB_CENTRAL->prepared_query($q, 'ss', [$hash, $post['code']]);

        $output->add_child( H::DIV(['class'=>$this->css.'success password'], $this->get_tl('success_change_password')) );

        $output->add_child( H::script('setTimeout(()=>{document.location = "";}, 5000);') );

        return $output;
    }
}