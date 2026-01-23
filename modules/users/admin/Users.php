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
namespace helPHP\modules\users\admin;

use helPHP\libs\Filesystem;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;

class Users extends HelPHP_module{

    const module_name = 'users';

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct();
        
        if (isset($this->options['address']) && $this->options['address']) $this->address = true;
    }

    protected $ACTION_NEW = self::module_name.'_new';
    protected $ACTION_SAVE = self::module_name.'_save';
    protected $ACTION_EDIT = self::module_name.'_edit';
    protected $ACTION_DELETE = self::module_name.'_delete';
    
    protected $ACTION_MERGE = self::module_name.'_merge';
    protected $ACTION_APPLY_MERGE = self::module_name.'_merge_apply';

    protected $ACTION_SEARCH_recherche = self::module_name.'_recherche_search';
    protected $ACTION_SHOW_recherche = self::module_name.'_recherche_show';
    
    protected $results_default_count = 12;
    
    protected $ACTION_NEW_group_users = self::module_name.'_group_users_new';
    protected $ACTION_SAVE_group_users = self::module_name.'_group_users_save';
    protected $ACTION_EDIT_group_users = self::module_name.'_group_users_edit';
    protected $ACTION_DELETE_group_users = self::module_name.'_group_users_delete';
    
    protected $address = false;
    
    private $ACTION_AUTOCOMPLETE = self::module_name.'_autocomp';
    
    public function process_data(&$post,$to_return=false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        $master_output = H::group('users_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_NEW:
                $post[$this->ifld_data_id] = 0;
                $this->reset_fields($post, 'users_data');
                $master_output->add_child( $this->Data_Edit($post) );
            break;
            case $this->ACTION_EDIT:
                if (isset($post[self::module_name.'_id']) && $post[self::module_name.'_id']){
                    $post[$this->ifld_data_id] = $post[self::module_name.'_id'];
                }
                $this->prepare_fields($post, 'users_data');

                $master_output->add_child( $this->Data_Edit($post) );
            break;
            case $this->ACTION_SAVE:
                $this->check_posted_data($post,'users_data');
                $this->Data_Save($post);

                $master_output->add_child( $this->Data_Edit($post) );
            break;
            case $this->ACTION_DELETE:
                $this->check_posted_data($post,'users_data');
                
                $this->Data_Delete_group_users($post);
                $this->Data_Delete($post);
            
                $post[$this->ifld_data_id] = 0;
                // $this->reset_fields($post, 'users_data');

                // $master_output->add_child( $this->Data_Edit($post) );
                $this->reset_fields($post, 'users_data');
                unset($post['closed']);
                $post['defaultmode'] = true;

                $master_output->add_child( $this->Menu($post) );
                $master_output->add_child( $this->Menu_Search_recherche($post) );
                $master_output->add_child( $this->Data_Search_recherche($post) );
            break;
            
            case $this->ACTION_SEARCH_recherche:
                $this->prepare_fields($post, 'users_data');

                $master_output->add_child( $this->Menu($post) );
                $master_output->add_child( $this->Menu_Search_recherche($post) );
                $master_output->add_child( $this->Data_Search_recherche($post) );
            break;
    
            case $this->ACTION_NEW_group_users:
                $post[$this->ifld_group_users_id] = 0;
                $this->reset_fields($post, 'group_users', false, true);
                $master_output->add_child( $this->Data_Edit_group_users($post) );
            break;
            
            case $this->ACTION_EDIT_group_users:
                $this->prepare_fields($post, 'group_users', true);

                $master_output->add_child( $this->Data_Edit_group_users($post) );
            break;
            
            case $this->ACTION_SAVE_group_users:
                $this->check_posted_data($post,'group_users', false, true);
                $this->Data_Save_group_users($post);

                $master_output->add_child( $this->Data_Edit_group_users($post) );
            break;
            
            case $this->ACTION_DELETE_group_users:
                $this->check_posted_data($post,'group_users', false, true);
                $this->Data_Delete_group_users($post);

                $post['group_users-id'] = 0;
                $this->reset_fields($post, 'group_users', false, true);
                $master_output->add_child( $this->Data_Edit_group_users($post) );
            break;

            case $this->ACTION_AUTOCOMPLETE:
                $master_output->add_child( $this->Data_Autocomplete($post) );
            break;
            
            case 'clear':
                $post = [];
                $this->prepare_fields($post, 'users_data');

                $master_output->add_child( $this->Menu($post) );
                $master_output->add_child( $this->Menu_Search_recherche($post) );
                $master_output->add_child( $this->Data_Search_recherche($post) );
            break;

            default:
                $this->reset_fields($post, 'users_data');
                unset($post['closed']);
                $post['defaultmode'] = true;

                $master_output->add_child( $this->Menu($post) );
                $master_output->add_child( $this->Menu_Search_recherche($post) );
                $master_output->add_child( $this->Data_Search_recherche($post) );
            break;
        }
        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    //affiche le bouton pour ajouter un nouvel utilisateur
    public function Menu ($post) {

        $output = H::group('add_users');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
        
        $output->add_child([$title]);

            $buttons = H::DIV(['class'=>$this->css.'action_buttons action_buttons']);

                $button_new = H::button(['class'=>$this->css.'btn_new button_new', 'title'=>$this->get_tl('tlc_new'), 'onclick'=>'H_search.modal_edit(0, "'.self::module_name.'", "data", "new");'], $this->get_tl('tlc_new'));
            
            $buttons->add_child([$button_new]);

        $output->add_child( $buttons );

        return $output;
    }
    
    //le formulaire d'édition classique :
    public function Data_Edit($post){
        global $LANG,$USER,$DB_CENTRAL,$DB,$CONFIG;

        $output = H::group('edit_module');
        
            $ttl = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title_edit'));
            if ($post[$this->ifld_data_id] > 0) $ttl->add_child( H::span(['class'=>$this->css.'current_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_data_id])) );
            
        $output->add_child($ttl);
        
            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>'users_modal'.$this->dom_id, 'class'=>$this->css.'form_edit form_edit'));

                //nous sauvons l'id de l'objet
                $form->add_child(H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id], 'data-alwaysposted'=>1, 'id'=>'current_id']));
                
                $email = H::input_email(['name'=>$this->ifld_data_email,'id'=>$this->ifld_data_email, 'label'=>$this->get_tl('email'), 'value'=>$post[$this->ifld_data_email], 'data-required'=>1]);
                $confirm_email = H::input_confirmation($email, $this->get_tl('confirm_email'));
                
                $checked=($post[$this->ifld_data_active]==1)?1:0;
                $active = H::input_checkbox(['name'=>$this->ifld_data_active,'id'=>$this->ifld_data_active, 'label'=>$this->get_tl('active'), 'value'=>1, 'checked'=>$checked]);
                
                if ($post[$this->ifld_data_id] == 0) {
                    $login = H::input_text(['name'=>$this->ifld_data_login,'id'=>$this->ifld_data_login, 'label'=>$this->get_tl('login'), 'value'=>$post[$this->ifld_data_login], 'data-required'=>1]);
                    $login_lab = $login->label_tag();
                } else {
                    $login_lab = H::span(['class'=>$this->css.'login_display_label'], $this->get_tl('login'));
                    $login = H::span(['id'=>$this->ifld_data_login, 'class'=>$this->css.'login_display'], $post[$this->ifld_data_login]);
                }
                
                $attr_pass = ['name'=>$this->ifld_data_password,'id'=>$this->ifld_data_password, 'label'=>$this->get_tl('password_hash'), 'value'=>''];
                if ($post[$this->ifld_data_id] == 0) {
                    $attr_pass = array_merge($attr_pass, ['data-required'=>1]);
                }
                $password = H::input_text($attr_pass);
                $confirm_pass = H::input_confirmation($password, $this->get_tl('confirm_pass'));
                
                $lang_data = $LANG->get_languages_data();
                $selected = isset($post[$this->ifld_data_language]) ? $post[$this->ifld_data_language] : $CONFIG::DEFAULT_LANGUAGE;
                $options_data = ['options'=>$lang_data , 'value_key'=>'iso' , 'label_key'=>'label'];
                $language = H::select(['name'=>$this->ifld_data_language,'id'=>$this->ifld_data_language, 'label'=>$this->get_tl('language'),'data-required'=>1], $options_data, $selected);
                
                if ($USER->admin){
                    $checked=($post[$this->ifld_data_admin]==1)?1:0;
                    $admin = H::input_checkbox(['name'=>$this->ifld_data_admin,'id'=>$this->ifld_data_admin, 'label'=>$this->get_tl('admin'), 'value'=>1, 'checked'=>$checked]);
                }
                
                $lastname = H::input_text(['name'=>$this->ifld_data_lastname,'id'=>$this->ifld_data_lastname, 'label'=>$this->get_tl('lastname'), 'value'=>$post[$this->ifld_data_lastname], 'data-required'=>1 ]);
                $firstname = H::input_text(['name'=>$this->ifld_data_firstname,'id'=>$this->ifld_data_firstname, 'label'=>$this->get_tl('firstname'), 'value'=>$post[$this->ifld_data_firstname], 'data-required'=>1]);
                $entity = H::input_text(['name'=>$this->ifld_data_entity,'id'=>$this->ifld_data_entity, 'label'=>$this->get_tl('entity'), 'value'=>$post[$this->ifld_data_entity]]);
                //~ $vat_id = H::input_text(['name'=>$this->ifld_data_vat_id,'id'=>$this->ifld_data_vat_id, 'label'=>$this->get_tl('vat_id'), 'value'=>$post[$this->ifld_data_vat_id], 'class'=>'inp_short_text']);
                
                if ($USER->admin){
                    $form->add_child([$active->label_tag(),$active,$login_lab,$login,$email->label_tag(),$email,$confirm_email->label_tag(),$confirm_email,$password->label_tag(),$password,$confirm_pass->label_tag(),$confirm_pass,$language->label_tag(),$language,$admin->label_tag(),$admin,$lastname->label_tag(),$lastname,$firstname->label_tag(),$firstname,$entity->label_tag(),$entity]);
                } else {
                    $form->add_child([$active->label_tag(),$active,$login_lab,$login,$email->label_tag(),$email,$confirm_email->label_tag(),$confirm_email,$password->label_tag(),$password,$confirm_pass->label_tag(),$confirm_pass,$language->label_tag(),$language,$lastname->label_tag(),$lastname,$firstname->label_tag(),$firstname,$entity->label_tag(),$entity]);
                }
                
                if ($this->address && $this->address!=false) {
                    $q = 'SELECT id FROM '.$this->bddt_address.' WHERE id_users_data=?';
                    $post[$this->ifld_address_id] = $DB_CENTRAL->prepared_query_value($q,'i',[$post[$this->ifld_data_id]]);
                    if ($post[$this->ifld_address_id]) {
                        $this->apply_bdd_data($post, 'users_address');
                    } else {
                        $this->reset_fields($post, 'users_address');
                    }
                    $fieldset = H::fieldset(['class'=>$this->css.'address_part'],$this->get_tl('adress'));
                    
                    $fieldset->add_child(H::input_hidden(['name'=>$this->ifld_address_id, 'value'=>$post[$this->ifld_address_id], 'data-alwaysposted'=>1, 'id'=>'current_address_id'.$this->dom_id]));
                        
                        $street = H::input_text(['name'=>$this->ifld_address_street, 'id'=>$this->ifld_address_street.$this->dom_id, 'label'=>$this->get_tl('street'), 'value'=>$post[$this->ifld_address_street]]);
                        
                        $post[$this->ifld_address_postal_code]=$post[$this->ifld_address_postal_code]==0?'':$post[$this->ifld_address_postal_code];
                        $postal_code = H::input_text(['name'=>$this->ifld_address_postal_code, 'id'=>$this->ifld_address_postal_code.$this->dom_id, 'label'=>$this->get_tl('postal_code'), 'value'=>$post[$this->ifld_address_postal_code]]);
                        
                        $city = H::input_text(['name'=>$this->ifld_address_city, 'id'=>$this->ifld_address_city.$this->dom_id, 'label'=>$this->get_tl('city'), 'value'=>$post[$this->ifld_address_city]]);
                        $lang_iso = ($LANG->current_language == 'fr' || $LANG->current_language == 'en') ? $LANG->current_language : 'own';
                        $lang_iso='own';
                        $q = 'SELECT '.$lang_iso.' FROM '.$DB_CENTRAL->table('country_data').' WHERE id=?';
                        $lab = $DB_CENTRAL->prepared_query_value($q,'i',[$post[$this->ifld_address_id_country_data]]);
                        $opts = [
                            'name'          => $this->ifld_address_id_country_data,
                            'centraldb'     => true,
                            'id'            => $this->ifld_address_id_country_data.$this->dom_id,
                            'dom_id'        => $this->dom_id, 'label'=>$this->get_tl('country'),
                            'class'         => $this->css, 
                            'value'         => $post[$this->ifld_address_id_country_data],
                            'value_label'   => $lab
                        ];
                        $country = H::input_autocomplete($opts, 'country_data', $lang_iso, false, $this->inst_js.'.on_change_country');
                        
                        $q = 'SELECT id,name FROM '.$DB_CENTRAL->table('country_state').' ORDER BY name';
                        $lst = $DB_CENTRAL->query_list($q);
                        $opts = ['first_empty'=>true,'options'=>$lst, 'label_key'=>'name', 'value_key'=>'id', 'data-required'=>1];
                        $state = H::select(['name'=>$this->ifld_address_id_country_state, 'id'=>$this->ifld_address_id_country_state.$this->dom_id, 'class'=>$this->css.'sel_state', 'label'=>$this->get_tl('state'), 'data-required'=>1], $opts, $post[$this->ifld_address_id_country_state]);
                    
                    $fieldset->add_child([$street->label_tag(), $street, $postal_code->label_tag(), $postal_code, $city->label_tag(), $city, $country->label_tag(), $country, $state->label_tag(), $state]);
                        
                        $script = H::script('Users_a.create_instance("'.$this->dom_id.'", '.$post[$this->ifld_address_id_country_data].');', ['autoremove'=>1]);
                    
                    $fieldset->add_child($script);

                    $form->add_child($fieldset);
                }
                
                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);

                    $btn_save = H::submit_button(['style'=>'display: none;', 'id'=>self::module_name.'_btn_save'.$this->dom_id, 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE], $this->get_tl('tlc_save'));
                    $fake_btn_save = H::button(['type'=>'button', 'class'=>$this->css.'btn_save_fk button_save', 'onclick'=>'H_search.save("'.self::module_name.'", "'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));

                $block_btns->add_child([$btn_save, $fake_btn_save]);

                if ($post[$this->ifld_data_id] > 0) {
                    // $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_delete'), 'data-confirm'=>$this->get_tl('confirm_delete')], $this->get_tl('tlc_delete'));
                    
                    $form_delete = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'users_admin_container'.$post['dom_id'], 'class'=>$this->css.'form_delete form_delete']);
                        $form_delete->add_child(H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id], 'data-alwaysposted'=>1, 'id'=>'current_id']));
                        $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier , 'id'=>self::module_name.'_btn_del_'.$post[$this->ifld_data_id].$this->dom_id, 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'style'=>'display:none;'), $this->get_tl('tlc_delete'));
                        $fake_btn_del = H::BUTTON(['type'=>'button', 'class'=>$this->css.'btn_del_fk button_delete', 'onclick'=>'H_search.del(event, "'.self::module_name.'", '.$post[$this->ifld_data_id].', "'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_delete'), 'data-confirm'=>$this->get_tl('confirm_delete')], $this->get_tl('tlc_delete'));
                    $form_delete->add_child( [$btn_delete, $fake_btn_del] );
                    $block_btns->add_child([$form_delete]);
                }

            $form->add_child( $block_btns );
            
        $output->add_child($form);
        
        // load the group 
        if ($post[$this->ifld_data_id] > 0 && $USER->admin) {

            $group_users_container = H::DIV(['class'=>$this->css.'sub_container module_sub_container', 'id'=>$this->dom_container.'_group_users'.$this->dom_id]);

                $post[$this->input_action_identifier]=$this->ACTION_EDIT_group_users;
                $q = 'SELECT id FROM '.$DB_CENTRAL->table('group_users').' WHERE id_'.self::module_name.'_data=?';
                $post['group_users-id'] = $DB_CENTRAL->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);

                $post['group_users-id_'.self::module_name.'_data'] = $post[$this->ifld_data_id];
                
            $group_users_container->add_child( $this->process_data($post,true) );
            $output->add_child($group_users_container);
        }
        
        return $output;
    }
    
    //sauve les données.
    public function Data_Save(&$post){
        global $CRYPT,$CONFIG,$DB_CENTRAL;

        $error_list = array();
        // if country  is not united states, reset the id_state
        if($post[$this->ifld_data_id] == 0){
            // need to check if some value are not present in the table, like login or email.
            $ok=true;
            $count = $DB_CENTRAL->prepared_query_value('SELECT DISTINCT COUNT(*) FROM '.$this->bddt_data.' WHERE login=?', 's', [$post[$this->ifld_data_login]]);
            if ($count > 0){
                array_push($error_list, ['field'=>'login','value'=>$post[$this->ifld_data_login],'message'=>'exist']);
                $ok = false;
            }
            $count = $DB_CENTRAL->prepared_query_value('SELECT DISTINCT COUNT(*) FROM '.$this->bddt_data.' WHERE email=?', 's', [$post[$this->ifld_data_email]]);
            if ($count > 0){
                array_push($error_list, ['field'=>'email','value'=>$post[$this->ifld_data_email],'message'=>'exist']);
                $ok = false;
            }
        
            if ($ok){
                // crypt password
                $post[$this->ifld_data_password_hash] = $CRYPT->create_password_hash($post[$this->ifld_data_password]);
                // création
                $q = 'INSERT INTO '. $this->bddt_data.' SET email=?,active=?,activation_code=?,login=?,password_hash=?,language=?,admin=?,lastname=?,firstname=?,entity=?';
                $success = $DB_CENTRAL->prepared_query($q,'sissssisss',[$post[$this->ifld_data_email],$post[$this->ifld_data_active],$post[$this->ifld_data_activation_code],$post[$this->ifld_data_login],$post[$this->ifld_data_password_hash],$post[$this->ifld_data_language],$post[$this->ifld_data_admin],$post[$this->ifld_data_lastname],$post[$this->ifld_data_firstname],$post[$this->ifld_data_entity]]);
                $post[$this->ifld_data_id] = $DB_CENTRAL->last_insert_id();
                
                // for the special process like forum account creation / revision account
                if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
                    $data = $post;
                    $_POST = [];
                    $_POST['mode'] = 'create';
                    $_POST['login'] = $post[$this->ifld_data_login];
                    $_POST['password'] = $post[$this->ifld_data_password];
                    $_POST['email'] = $post[$this->ifld_data_email];
                    $_POST['id'] = $post[$this->ifld_data_id];
                    include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
                    $post = $data;
                } else {
                    Utils::error_log('error user_extend.php not found');
                }
                
                if ($this->address && $this->address!=false) {
                    $q = 'INSERT INTO '.$this->bddt_address.' SET id_country_data=?,id_country_state=?,street=?,postal_code=?,city=?,id_users_data=?';
                    $DB_CENTRAL->prepared_query($q,'iisssi',[$post[$this->ifld_address_id_country_data],$post[$this->ifld_address_id_country_state],$post[$this->ifld_address_street],$post[$this->ifld_address_postal_code],$post[$this->ifld_address_city],$post[$this->ifld_data_id]]);
                }
            }
        }else{
            // crypt password
            if ($post[$this->ifld_data_password] != ''){
                $post[$this->ifld_data_password_hash] = $CRYPT->create_password_hash($post[$this->ifld_data_password]);
            } else {
                $post[$this->ifld_data_password_hash] = $DB_CENTRAL->query_value('SELECT password_hash FROM '.$this->bddt_data.' WHERE id='.$post[$this->ifld_data_id]);
            }
            
            // mise à jour
            $q = 'UPDATE '. $this->bddt_data.' SET email=?,active=?,activation_code=?,password_hash=?,language=?,admin=?,lastname=?,firstname=?,entity=? where id=?';
            //attention au i du type de l'id qui est pré-inséré
            $success = $DB_CENTRAL->prepared_query($q,'sisssisssi',[$post[$this->ifld_data_email],$post[$this->ifld_data_active],$post[$this->ifld_data_activation_code],$post[$this->ifld_data_password_hash],$post[$this->ifld_data_language],$post[$this->ifld_data_admin],$post[$this->ifld_data_lastname],$post[$this->ifld_data_firstname],$post[$this->ifld_data_entity], $post[$this->ifld_data_id]]);
            
            if ($this->address && $this->address!=false) {
                if ($post[$this->ifld_address_id] > 0){
                    $q = 'UPDATE '.$this->bddt_address.' SET id_country_data=?,id_country_state=?,street=?,postal_code=?,city=? WHERE id=?';
                    $DB_CENTRAL->prepared_query($q,'iisssi',[$post[$this->ifld_address_id_country_data],$post[$this->ifld_address_id_country_state],$post[$this->ifld_address_street],$post[$this->ifld_address_postal_code],$post[$this->ifld_address_city],$post[$this->ifld_address_id]]);
                } else {
                    $q='INSERT INTO '.$this->bddt_address.' SET id_country_data=?,id_country_state=?,street=?,postal_code=?,city=?,id_users_data=?';
                    $DB_CENTRAL->prepared_query($q,'iisssi',[$post[$this->ifld_address_id_country_data],$post[$this->ifld_address_id_country_state],$post[$this->ifld_address_street],$post[$this->ifld_address_postal_code],$post[$this->ifld_address_city],$post[$this->ifld_data_id]]);
                    $post[$this->ifld_address_id] = $DB_CENTRAL->last_insert_id();
                }
            }
            
            // for the special process like forum account or specific need
            if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
                $data = $post;
                $_POST = [];
                $_POST['mode'] = 'edit';
                $_POST['login'] = $data[$this->ifld_data_login];
                $_POST['email'] = $data[$this->ifld_data_email];
                $_POST['id'] = $data[$this->ifld_data_id];
                if (isset($post[$this->ifld_data_password]) && $post[$this->ifld_data_password] != ''){
                    $_POST['new_password'] = 1;
                    $_POST['password'] = $data['password'];
                } else {
                    $_POST['new_password'] = 0;
                }
                include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
                $post = $data;
            } else {
                Utils::error_log('error user_extend.php not found');
            }
        }
        
        $q = 'SELECT vat_id FROM '.$this->bddt_data.' WHERE id=?';
        $prev_vat_id = $DB_CENTRAL->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);
        if ($prev_vat_id != $post[$this->ifld_data_vat_id]){
            $q = 'UPDATE '.$this->bddt_data.' SET vat_id=?, valid_vat_id=0 WHERE id=?';
            $DB_CENTRAL->prepared_query($q, 'si', [$post[$this->ifld_data_vat_id],$post[$this->ifld_data_id]]);
        }
        
        return $error_list;
    }

    //supprime les données
    public function Data_Delete(&$post) {
        global $CONFIG,$DB_CENTRAL;
        
        $q = 'SELECT * FROM '.$this->bddt_data.' WHERE id=?';
        $data = $DB_CENTRAL->prepared_query_line($q, 'i', [$post[$this->ifld_data_id]]);
        
        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';
        $res = $DB_CENTRAL->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);

        // delete address
        if ($this->address && $this->address!=false) {
            $q = 'DELETE FROM '.$this->bddt_address.' WHERE id_users_data=?';
            $res = $DB_CENTRAL->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
        }
        
        if (is_file($CONFIG::HOME_FOLDER.'public/users/user_extend.php')){
            $_POST = [];
            $_POST['mode'] = 'delete';
            $_POST['login'] = $data['login'];
            $_POST['id'] = $data['id'];
            include($CONFIG::HOME_FOLDER.'public/users/user_extend.php');
        } else {
            Utils::error_log('error user_extend.php not found');
        }
    }
    
    public function Menu_Search_recherche($post) {
        global $CRYPT;

        $output = H::DIV(['class'=>$this->css.'container_search module_search', 'id'=>self::module_name.'_container_search'.$this->dom_id]);

            $title = H::DIV(['class'=>$this->css.'search_title search_title'], $this->get_tl('recherche_search_title'));

        $output->add_child($title);

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_search form_search'));

                $post['rechlnm'] = (!isset($post['rechlnm']))?'':$post['rechlnm'];
                $text = H::input_text(['class'=>$this->css.'input_search search_input default', 'name'=>'rechlnm', 'id'=>self::module_name.'_rechlnm'.$this->dom_id, 'value'=>urldecode($post['rechlnm']), 'data-returnsubmit'=>1]);

                //nombre résultat par page
                $selected = isset($post['nbr_resultat']) ? $post['nbr_resultat'] : $this->results_default_count;
                $results_per_page = [['val'=>12],['val'=>24],['val'=>48],['val'=>96]];
                $opts_data = array('value_key'=>'val', 'label_key'=>'val', 'options'=>$results_per_page);
                $select = H::select(['id'=>self::module_name.'_nbr_result'.$this->dom_id, 'name'=>'nbr_result', 'label'=>$this->get_tl('nbr_res'), 'data-alwaysposted'=>1], $opts_data, $selected);
            
            $form->add_child([$text,$select->label_tag(), $select]);
            
                // some hidden data
                $post['start_index'] = (isset($post['start_index']) && (isset($post['page_jumper']) && $post['page_jumper']==1))?intval($post['start_index']):'0';
                $post['order_filter'] = isset($post['order_filter']) ? $post['order_filter'] : $CRYPT->encrypt('email').'-d';
                $start_index = H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'start_index','id'=>self::module_name.'_start_index', 'value'=>$post['start_index']));
                $order_filter = H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'order_filter','id'=>self::module_name.'_order_filter', 'value'=>$post['order_filter']));
                $page_jumper = H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'page_jumper','id'=>self::module_name.'_page_jumper', 'value'=>'0'));
                
            $form->add_child([$start_index, $order_filter, $page_jumper]);
            
                // advanced  optionnels fields 
                $advanced_fields = H::detail(['class'=>$this->css.'search_advanced_fields search_advanced_fields'], $this->get_tl('search_advanced_fields'));

                    $email = H::input_text(['name'=>$this->ifld_data_email,'id'=>$this->ifld_data_email.'_srch', 'label'=>$this->get_tl('email'), 'value'=>$post[$this->ifld_data_email], 'class'=>'inp_email' , 'data-required'=>0, 'data-returnsubmit'=>1]);

                $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$email->label_tag(),$email]) );

                    $login = H::input_text(['name'=>$this->ifld_data_login,'id'=>$this->ifld_data_login.'_srch', 'label'=>$this->get_tl('login'), 'value'=>$post[$this->ifld_data_login], 'class'=>'inp_short_text','data-returnsubmit'=>1]);

                $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$login->label_tag(),$login]) );

                    $lastname = H::input_text(['name'=>$this->ifld_data_lastname,'id'=>$this->ifld_data_lastname.'_srch', 'label'=>$this->get_tl('lastname'), 'value'=>$post[$this->ifld_data_lastname], 'class'=>'inp_short_text','data-returnsubmit'=>1]);

                $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$lastname->label_tag(),$lastname]) );

                    $firstname = H::input_text(['name'=>$this->ifld_data_firstname,'id'=>$this->ifld_data_firstname.'_srch', 'label'=>$this->get_tl('firstname'), 'value'=>$post[$this->ifld_data_firstname], 'class'=>'inp_short_text','data-returnsubmit'=>1]);
                
                $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$firstname->label_tag(),$firstname]) );
                    
                    $entity = H::input_text(['name'=>$this->ifld_data_entity,'id'=>$this->ifld_data_entity.'_srch', 'label'=>$this->get_tl('entity'), 'value'=>$post[$this->ifld_data_entity], 'class'=>'inp_short_text','data-returnsubmit'=>1]);
                
                $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$entity->label_tag(),$entity]) );

            $form->add_child( $advanced_fields );
                
                $btn_clear = H::submit_button(array('class'=>$this->module_name.'_btn_search','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_btn_clear' , 'value'=>'clear', 'class'=>$this->css.'btn_clear', 'title'=>$this->get_tl('Clear') ) ,'Clear');
                $btn_search = H::submit_button(array('class'=>$this->module_name.'_btn_search','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_btn_search' , 'value'=>$this->ACTION_SEARCH_recherche, 'class'=>$this->css.'btn_search', 'title'=>$this->get_tl('tlc_search') ) , $this->get_tl('tlc_search'));
            
            $form->add_child([$btn_clear,$btn_search]);
        
        $output->add_child([$form]);
        
        return $output;
    }
    
    
    //process et affiche le résultat de la recherche
    public function Data_Search_recherche(&$post){
        global $CRYPT,$DB_CENTRAL;
        $bdd_data = $this->bddt_data;
        
        $query_params_types = '';
        $query_values = array();
        $query_conditions = '';

        $post['nbr_resultat'] = isset($post['nbr_resultat']) ? intval($post['nbr_resultat']) : $this->results_default_count;
        $post['start_index'] = isset($post['start_index']) ? intval($post['start_index']) : 0;
        $post['page_limit']= isset($post['page_limit']) ? $post['page_limit'] : $this->results_default_count;
        if( $post['nbr_resultat'] != $post['page_limit']){
            $post['page_limit']= $post['nbr_resultat'];
        } 
        $post['order_filter'] = isset($post['order_filter']) ? $post['order_filter'] : $CRYPT->encrypt('email').'-d';
        
        if (!isset($post['rechlnm']) || $post['rechlnm']=='') {
            $search_string = '';
            $post['defaultmode']=true;
        }else{
            $search_string=$post['rechlnm'];
            unset($post['defaultmode']);
        }
        
        $search_string = str_replace('%', '', $search_string);
        $s = trim($search_string);
        $s = explode(' ', $s);
        $fulltext_string='';
        foreach ($s as $word) {
            $word = trim($word);
            if ($word != '' && strlen($word) > 1) {
                $fulltext_string .= ' +'.$word;
            }
        }
        $search_string = trim(addslashes($fulltext_string));
        
        if (isset($post[$this->ifld_data_email]) && $post[$this->ifld_data_email]!='') {
            unset($post['defaultmode']);
        }
        if (isset($post[$this->ifld_data_login]) && $post[$this->ifld_data_login]!='') {
            unset($post['defaultmode']);
        }
        if (isset($post[$this->ifld_data_lastname]) && $post[$this->ifld_data_lastname]!='') {
            unset($post['defaultmode']);
        }
        if (isset($post[$this->ifld_data_firstname]) && $post[$this->ifld_data_firstname]!='') {
            unset($post['defaultmode']);
        }
        if (isset($post[$this->ifld_data_entity]) && $post[$this->ifld_data_entity]!='') {
            unset($post['defaultmode']);
        }
        
        $text_fields=$bdd_data.'.email,'.$bdd_data.'.login,'.$bdd_data.'.lastname,'.$bdd_data.'.firstname,'.$bdd_data.'.entity';
        
        //query principale
        $q='(SELECT SQL_CALC_FOUND_ROWS '.$bdd_data.'.id as id, COUNT(MATCH('.$text_fields.') AGAINST(? in boolean MODE)) as score ';
        $query_params_types .= 's';
        array_push($query_values, $search_string);
        
        $q.='FROM '.$bdd_data;
        $q.=' WHERE MATCH('.$text_fields.') AGAINST(? in boolean MODE) ';
        
        $query_params_types .= 's';
        array_push($query_values, $search_string);

        if (isset($post[$this->ifld_data_email]) && $post[$this->ifld_data_email]!='') {
            $query_params_types .= 's';
            $query_conditions .=' OR '.$bdd_data.'.email LIKE ?';
            array_push($query_values, '%'.$post[$this->ifld_data_email].'%');
        }
        if (isset($post[$this->ifld_data_login]) && $post[$this->ifld_data_login]!='') {
            $query_params_types .= 's';
            $query_conditions .=' OR '.$bdd_data.'.login LIKE ?';
            array_push($query_values, '%'.$post[$this->ifld_data_login].'%');
        }
        if (isset($post[$this->ifld_data_lastname]) && $post[$this->ifld_data_lastname]!='') {
            $query_params_types .= 's';
            $query_conditions .=' OR '.$bdd_data.'.lastname LIKE ?';
            array_push($query_values, '%'.$post[$this->ifld_data_lastname].'%');
        }
        if (isset($post[$this->ifld_data_firstname]) && $post[$this->ifld_data_firstname]!='') {
            $query_params_types .= 's';
            $query_conditions .=' OR '.$bdd_data.'.firstname LIKE ?';
            array_push($query_values, '%'.$post[$this->ifld_data_firstname].'%');
        }
        if (isset($post[$this->ifld_data_entity]) && $post[$this->ifld_data_entity]!='') {
            $query_params_types .= 's';
            $query_conditions .=' OR '.$bdd_data.'.entity LIKE ?';
            array_push($query_values, '%'.$post[$this->ifld_data_entity].'%');
        }

        $q .= $query_conditions;
        $q.=' GROUP BY id )';

        //finalisation query :
        if (isset($post['order_filter']) && $post['order_filter'] != ''){
            $order_filter = $CRYPT->decrypt(substr($post['order_filter'], 0, -2));
            $sens = (substr($post['order_filter'], -1) == 'a') ? ' ASC' : ' DESC';
            $q.=' ORDER BY '.$order_filter.$sens;
        } else {
            $q.=' ORDER BY score DESC';
        }
        $q.= ' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
        $results = $DB_CENTRAL->prepared_query_list($q,$query_params_types,$query_values);
        
        if (isset($post['defaultmode'])) {
            $q='SELECT SQL_CALC_FOUND_ROWS '.$bdd_data.'.id as id, 1 as score FROM '.$bdd_data;
            $q.=(isset($post['order_filter']) && $post['order_filter'] != '')?' ORDER BY '.$CRYPT->decrypt(substr($post['order_filter'], 0, -2)).((substr($post['order_filter'], -1) == 'a')?' DESC':' ASC'):' ORDER BY score DESC';
            $q.= ' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
            
            $results = $DB_CENTRAL->prepared_query($q);
        }
        
        if (is_array($results)) {
            $mergedresult=[];
            foreach ($results as $index=>$line) {
                if (array_key_exists($line['id'], $mergedresult)) {
                    $mergedresult[$line['id']]['score']+=$line['score'];
                } else {
                    $mergedresult[$line['id']]=$line;
                }
            }
            $pages = $DB_CENTRAL->last_pages_data();
            $post['pages'] = $pages;
            $post['resultats'] = $mergedresult;
        } else {
            $post['pages'] = 0;
            $post['id'] = [];
        }
        return $this->ProcessData_result_recherche($post);
    }
    
    public function ProcessData_result_recherche(&$post) {
        global $CRYPT,$CONFIG_DB,$CONFIG,$DB_CENTRAL;

        $sub_container_id = 'recherche_recherche_resultat_public_container'.$this->dom_id;
        
        $bdd_data = $this->bddt_data;
        
        $output = H::group('result_search');

        $pages = $post['pages'];
        if ($pages['page_count'] > 1) {
            $pages_display = H::DIV(['class'=>$this->css.'search_pages search_pages']);

            $params = [];
            if (isset($post['rechlnm']) && $post['rechlnm']!='' ) {
                $params['rechlnm'] = $post['rechlnm'];
            }
            $params['page_limit'] = $post['page_limit'];

            $index = $pages['page_index'];
            if ($index > 0) {
                $params['start_index'] = ($index - 1) * $post['page_limit'];
                $btn_previous = H::button_icon('arrow-left-circle', ['class'=>$this->css.'prev_page search_previous_page', 'onclick'=>'H_search.previous("'.self::module_name.'", "'.$this->dom_id.'");', 'data-parameters'=>$params, 'title'=>$this->get_tl('tlc_previous_page')]);
                $pages_display->add_child($btn_previous);
                unset($params['start_index']);
            }

            $opts = [];
            for ($i=0; $i < $pages['page_count']; $i++) {
                array_push($opts, ['label'=>$i + 1, 'value'=>$i * $post['page_limit']]);
            }
            $options_data = array('label_key'=>'label', 'value_key'=>'value', 'options'=>$opts);
            $selected = $index * $post['page_limit'];
            $select = H::select(['name'=>'start_index', 'class'=>$this->css.'select_page search_select_page', 'onchange'=>'H_search.jump_to(event, "'.self::module_name.'", "'.$this->dom_id.'");', 'data-parameters'=>$params], $options_data, $selected);
            $pages_display->add_child($select);

            if ($index < ($pages['page_count'] - 1)) {
                $params['start_index'] = ($index + 1) * $post['page_limit'];
                $btn_next = H::button_icon('arrow-right-circle', ['class'=>$this->css.'next_page search_next_page', 'onclick'=>'H_search.next("'.self::module_name.'", "'.$this->dom_id.'");', 'data-parameters'=>$params, 'title'=>$this->get_tl('tlc_next_page')]);
                $pages_display->add_child($btn_next);
            }
        }

        if ($pages['page_count'] > 0) {
            $table_display = H::table(['class'=>$this->css.'search_result search_result']);
                $tbody = H::tbody();
            $table_display->add_child($tbody);

            $order_filter = false;
            $order_sens = false;
            if (isset($post['order_filter']) && $post['order_filter'] != ''){
                $order_filter = $CRYPT->decrypt(substr($post['order_filter'], 0, -2));
                $order_sens = (substr($post['order_filter'], -1) == 'a')?'.-d"':'.-a"';
            }

            $data_display = H::TR(['class'=>$this->css.'search_result_row search_result_row entete']);

                $email = H::TH(['class'=>$this->css.'search_result_item search_result_item entete email '.($order_filter=='email'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('email').($order_filter=='email'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('email'));
                $login = H::TH(['class'=>$this->css.'search_result_item search_result_item entete login '.($order_filter=='login'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('login').($order_filter=='login'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('login'));
                $lastname = H::TH(['class'=>$this->css.'search_result_item search_result_item entete lastname '.($order_filter=='lastname'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('lastname').($order_filter=='lastname'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('lastname'));
                $firstname = H::TH(['class'=>$this->css.'search_result_item search_result_item entete firstname '.($order_filter=='firstname'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('firstname').($order_filter=='firstname'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('firstname'));
                $entity = H::TH(['class'=>$this->css.'search_result_item search_result_item entete entity '.($order_filter=='entity'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('entity').($order_filter=='entity'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('entity'));

            $data_display->add_child([$email, $login, $lastname, $firstname, $entity]);
            $data_display->add_child( [H::TH(['class'=>$this->css.'search_result_item search_result_item entete buttons'])] );

            $tbody->add_child($data_display);

            foreach ($post['resultats'] as $index => $line) {

                $line_data = false;
                $qres = 'select * FROM '.$bdd_data.' WHERE id='.$line['id'];
                $line_data = $DB_CENTRAL->query_line($qres);

                $data_display = H::TR(['class'=>$this->css.'search_result_row search_result_row','id'=>self::module_name.'search_result_row-'.$line['id'].$this->dom_id]);
                
                    $email = H::TD(['class'=>$this->css.'search_result_item search_result_item email'], H::a(['href'=>'mailto:'.$line_data['email']], $line_data['email']));
                    $login = H::TD(['class'=>$this->css.'search_result_item search_result_item login'], $line_data['login']);
                    $lastname = H::TD(['class'=>$this->css.'search_result_item search_result_item lastname'], $line_data['lastname']);
                    $firstname = H::TD(['class'=>$this->css.'search_result_item search_result_item firstname'], $line_data['firstname']);
                    $entity = H::TD(['class'=>$this->css.'search_result_item search_result_item entity'], $line_data['entity']);

                $data_display->add_child( [$email, $login, $lastname, $firstname, $entity] );

                    $buttons = H::TD(['class'=>$this->css.'search_result_item search_result_item buttons']);

                        $btn_edit = H::button_icon('edit-2', ['class'=>$this->css.'btn_edit button_edit', 'onclick'=>'H_search.modal_edit('.$line['id'].', "'.self::module_name.'", "data", "edit","'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_edit')]);

                    $buttons->add_child( $btn_edit );

                        if (isset($CONFIG::MODULES_LIST['invoice'])){
                            $js_params = [
                                'invoice_action' => 'invoice_load_user',
                                'id_users_data' => $line['id']
                            ];
                            $btn_invoice = H::button_icon('file-text', ['class'=>$this->css.'btn_invoice', 'onclick'=>'H_ui.open_popup_modal(event, "invoice", '.json_encode($js_params).', "user_invoice");', 'title'=>$this->get_tl('invoice')]);
                            $buttons->add_child($btn_invoice);
                        }

                        $form_delete = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$sub_container_id, 'class'=>$this->css.'form_delete form_delete']);
                            $form_delete->add_child(H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$line['id'], 'data-alwaysposted'=>1]));
                            $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'id'=>self::module_name.'_btn_del_'.$line['id'].$this->dom_id, 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_delete'), 'style'=>'display:none;'), H::icon('trash-2'));
                            $fake_btn_del = H::button_icon('trash-2', ['class'=>$this->css.'btn_del_fk button_delete', 'onclick'=>'H_search.del(event, "'.self::module_name.'", '.$line['id'].', "'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_delete'), 'data-confirm'=>$this->get_tl('confirm_delete')]);
                        $form_delete->add_child( [$btn_delete, $fake_btn_del] );

                    $buttons->add_child( $form_delete );

                $data_display->add_child( $buttons );
                $tbody->add_child($data_display);
            }

            $output->add_child($table_display);

            if ($pages['page_count'] > 1) {
                $output->add_child($pages_display);
            }
        } else {

            $output->add_child($this->get_tl('noresult'));
        }
        return $output;
    }
    
    public function Data_Edit_group_users($post){
        global $CONFIG,$CONFIG_DB,$DB_CENTRAL;

        $q = 'SELECT id_group_data FROM '.$DB_CENTRAL->table('group_users').' WHERE id_users_data=?';
        $post['group_users-id_group_data'] = $DB_CENTRAL->prepared_query_list($q, 'i', [$post['group_users-id_users_data']]);
        $output = H::group('edit_module');
        
        $ttl = H::div(['class'=>$this->css.'group_ttl', 'id'=>$this->css.'group_ttl'], $this->get_tl('group'));
        $output->add_child($ttl);
        
        $q = 'SELECT id, name FROM '.$DB_CENTRAL->table('group_data');
        $q.= ' ORDER BY name';
        $lst = $DB_CENTRAL->query_list($q);
        if (!$lst){ $lst=array(); }
        foreach($lst as $key=>$line){
            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_container.'_group_users'.$this->dom_id));
                $fields = $this->init_form_fields();
                $fields->add_child(H::input_hidden(['name'=>'group_users-id_users_data', 'id'=>'group_users-id_users_data', 'value'=>$post['group_users-id_users_data'], 'class'=>'inp_asso_hidden', 'data-alwaysposted'=>'1']));
                $fields->add_child(H::input_hidden(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_group_users, 'data-alwaysposted'=>1, 'id'=>'group_users_action_save']));
                
                $checked = (isset($post['group_users-id_group_data']) && array_search($line['id'], $post['group_users-id_group_data']) === false)?false:true;
                $chec = H::input_checkbox(['name'=>'group_users-id_group_data['.$line['id'].']', 'id'=>'group_users-id_group_data['.$line['id'].']', 'class'=>$this->css.'checkbox_group', 'label'=>$line['name'], 'value'=>$checked?0:1, 'checked'=>$checked, 'onchange'=>'H_ajax.submit_on_change(event.target);']);
                $fields->add_child( [$chec->label_tag(), $chec] );
                    
            $form->add_child($fields);
            $output->add_child($form);
        }
        return $output;
    }
    public function Data_Save_group_users(&$post){
        global $CONFIG,$CONFIG_DB,$DB_CENTRAL;
        
        if (is_array($post['group_users-id_group_data'])){
            foreach($post['group_users-id_group_data'] as $id_group => $val){
                if ($val == 0){ // case user unchecked a box, delete association for this group
                    $q = 'DELETE FROM '.$DB_CENTRAL->table('group_users').' WHERE id_users_data=? AND id_group_data=?';
                    $DB_CENTRAL->prepared_query($q, 'ii', [$post['group_users-id_users_data'], $id_group]);
                } else {
                    $q = 'INSERT INTO '.$DB_CENTRAL->table('group_users').' SET id_group_data=?,id_users_data=?';
                    $success = $DB_CENTRAL->prepared_query($q,'ii',[$id_group,$post['group_users-id_users_data']]);
                }
            }
        }
    }
    public function Data_Delete_group_users(&$post) {
        global $CONFIG,$CONFIG_DB,$DB_CENTRAL;

        $q = 'DELETE FROM '.$DB_CENTRAL->table('group_users').' WHERE id_'.self::module_name.'_data=?';
        $res = $DB_CENTRAL->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
    }
    public function Data_Autocomplete($post){
        global $DB_CENTRAL;

        $post['value']=addslashes($post['value']);
        if ($post['table'] == 'country_data'){
            
            $q = 'SELECT DISTINCT id, '.$post['fields'].' as name FROM '.$DB_CENTRAL->table($post['table']).' WHERE '.$post['fields'].' LIKE ? ORDER BY '.$post['fields'].' LIMIT ?';
            $results = $DB_CENTRAL->prepared_query_list($q, 'si', [$post['value'].'%',20]);
            
        } else if ($post['table'] == 'users_data'){
            
            $q_params=[];
            $q_indic='';
            $q='SELECT DISTINCT id, CONCAT(firstname, \' \', lastname, \' (\', email, \')\') as name FROM '.$DB_CENTRAL->table($post['table']).' WHERE ';
            if (is_array($post['fields'])){
                foreach($post['fields'] as $field){
                    $q_indic.='s';
                    array_push($q_params,$post['value'].'%');
                    $q.=$field.' LIKE ? OR ';
                }
                $q=substr($q,0,-4);
            } else {
                $q_indic.='s';
                array_push($q_params,$post['value'].'%');
                $q.=$post['fields'].' LIKE ?';
            }
            $q.=' ORDER BY name LIMIT ?';
            $q_indic.='i';
            array_push($q_params,20);
            $results=$DB_CENTRAL->prepared_query_list($q, $q_indic,$q_params);
            
        }
        
        return json_encode($results);
    }
}