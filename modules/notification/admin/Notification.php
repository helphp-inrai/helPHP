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
    
namespace helPHP\modules\notification\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;

class Notification extends HelPHP_module {

    const module_name = 'notification';

    function __construct() {
        $this->prepare_module(self::module_name,true);
        parent::__construct();
    }
    
    private $ACTION_NEW = self::module_name.'_new';

    private $ACTION_SAVE = self::module_name.'_save';
    // private $ACTION_SEND = self::module_name.'_send';
    private $ACTION_DELETE = self::module_name.'_delete';

    private $ACTION_SEARCH_recherche = self::module_name.'_recherche_search';
    private $ACTION_SHOW_recherche = self::module_name.'_recherche_show';
    
    private $ACTION_WIDGET = self::module_name.'_widget';
    
    private $results_default_count = 12;
    
    
    public function process_data(&$post,$toreturn=false){
        global $USER;
        if (parent::process_data($post) == false) {
            // utilisateur non autorisé sur ce module
            return null;
        }

        $master_output = H::group('notification_display');
        
        $allowed_groups = $USER->allowed_groups();
        // Utils::error_log($allowed_groups);

        if (!in_array(2,$allowed_groups) || $USER->admin){
            switch($post[$this->input_action_identifier]){
                case $this->ACTION_NEW:
                    $post[$this->ifld_data_id]=0;
                    $this->reset_fields($post, 'notification_data');
                    $master_output->add_child( $this->data_edit($post) );
                break;
                
                case $this->ACTION_SAVE:
                    $this->check_posted_data($post,'notification_data');
                    $this->data_save($post);
                    $master_output->add_child( $this->data_edit($post) );
                break;
                
                case $this->ACTION_DELETE:
                    $this->check_posted_data($post,'notification_data');
                    $this->data_delete($post);
                    $post[$this->ifld_data_id] = 0;
                    $this->reset_fields($post, 'notification_data');
                    $master_output->add_child( $this->data_edit($post) );
                break;

                case $this->ACTION_SHOW_recherche:
                    $this->prepare_fields($post, 'notification_data');
                    $master_output->add_child( $this->display($post) );
                    $master_output->add_child( $this->form_search($post) );
                break;
                
                case $this->ACTION_SEARCH_recherche:
                    $this->prepare_fields($post, 'notification_data');
                    $master_output->add_child( $this->display($post) );
                    $master_output->add_child( $this->form_search($post) );
                    $master_output->add_child( $this->data_search($post) );
                break;
                
                case $this->ACTION_WIDGET:
                    $this->dom_container='notification_widget';
                    $master_output->add_child( $this->display_widget() );
                break;

                // case $this->ACTION_SEND:
                    // $this->dom_container='notification_widget';
                    // $master_output->add_child( $this->Alert_Send($post) );
                    // $master_output->add_child( $this->data_edit($post) );
                // break;

                default:
                    $this->reset_fields($post, 'notification_data');
                    unset($post['closed']);
                    $post['defaultmode']=true;
                    $master_output->add_child( $this->display($post) );

                    $master_output->add_child( $this->form_search($post) );
                    $master_output->add_child( $this->data_search($post) );
                break;
            }

            if ($toreturn){
                return $master_output;
            }else{
                $this->display->add_child( $master_output );
            }
        }
    }
    
    //affiche le bouton pour ajouter un nouvel utilisateur
    public function display ($post) {

        $output = H::group('add_notification');

            $title=H::SPAN(array('class'=>'notification_admin_title'), $this->get_tl('title'));
        
            $params = [$this->input_action_identifier=>$this->ACTION_NEW];
            $btn_create = H::BUTTON(['type'=>'button','class'=>$this->css.'btn_create','onclick'=>'H_ui.open_popup_modal(event,"notification",'.json_encode($params).');','title'=>$this->get_tl('newalert')],$this->get_tl('newalert'));
            
        $output->add_child([$title,$btn_create]);

        return $output;
    }

    public function display_widget(){
        global $DB, $USER;

        $output = H::group('notification_widget');
        
            $q = 'SELECT count(*) as total, MAX(level) as level FROM '.$this->bddt_data.' WHERE id_user='.$USER->id;
            $line = $DB->prepared_query_line($q);
            
            $txt = H::SPAN(array('class'=>'nb_alert_'.$line['level'], 'onclick'=>'H_history.change_hash(event, "'.self::module_name.'");'), $this->get_tl('nbalert').$line['total']);

        $output->add_child([$txt]);

            $js = 'helphp_timeout(\'Notification_a.create_instance("'.$this->dom_id.'");\');';
        
        $output->add_child(H::script($js));
        return $output;
    }
    
    //Editing form new notification :
    public function data_edit($post){
        global $DB;

        $this->check_posted_data($post, 'notification_data');

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'popup_modal_content', 'class'=>$this->css.'form_edit form_edit']);

            // target type
            $post['target_type'] = isset($post['target_type']) ? $post['target_type'] : 'all';
            $lst = [['name'=>'all'], ['name'=>'group'], ['name'=>'user']];
            $opts_data = ['first_empty'=>false, 'value_key'=>'name', 'label_key'=>'name', 'options'=>$lst];
            $select = H::select(['name'=>'target_type', 'label'=>$this->get_tl('select_target_type'), 'class'=>$this->css.'select', 'data-alwaysposted'=>1], $opts_data, $post['target_type'], $this->input_action_identifier, $this->ACTION_NEW);

        $form->add_child([$select->label_tag(),$select]);

            if ( $post['target_type'] == 'group') {
                $q = 'SELECT DISTINCT id, name FROM '.$DB->table('group_data');
                $lst = $DB->query_list($q);
                $opts_data = ['first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$lst];
                $select = H::select(['name'=>'target_value', 'label'=>$this->get_tl('select_group'), 'class'=>$this->css.'select'], $opts_data);
                $form->add_child([$select->label_tag(),$select]);
            }
            if ( $post['target_type'] == 'user') {
                $q = 'SELECT DISTINCT id, CONCAT(firstname, " ", lastname) as name FROM '.$DB->table('users_data');
                $lst = $DB->query_list($q);
                $opts_data = ['first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$lst];
                $select = H::select(['name'=>'target_value', 'label'=>$this->get_tl('select_user'), 'class'=>$this->css.'select'], $opts_data);
                $form->add_child([$select->label_tag(),$select]);
            }

            $name = H::input_text(['name'=>$this->ifld_data_name, 'value'=>$post[$this->ifld_data_name], 'label'=>$this->get_tl('notification_name'), 'data-alwaysposted'=>1]);
            $descr = H::input_text(['name'=>$this->ifld_data_description, 'value'=>$post[$this->ifld_data_description], 'label'=>$this->get_tl('descr'), 'data-alwaysposted'=>1]);
            $level = H::input_integer(['name'=>$this->ifld_data_level, 'value'=>$post[$this->ifld_data_level], 'label'=>$this->get_tl('level'), 'data-alwaysposted'=>1], 1, 3);
            
        $form->add_child([$name->label_tag(),$name,$descr->label_tag(),$descr,$level->label_tag(),$level]);

            $btn_send = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE], $this->get_tl('tlc_send'));

        $form->add_child($btn_send);

        return $form;
    }
    
    //sauve les données.
    public function data_save(&$post){
        global $DB;

        $this->check_posted_data($post, 'notification_data');
        
        if ($post['target_type'] == 'user'){
            $id_users = [$post['target_value']];
        }else if ($post['target_type'] == 'group'){
            $id_users = $DB->query_list('SELECT DISTINCT id FROM '.$DB->table('users_data'));
        } else {
            $id_users = $DB->query_list('SELECT DISTINCT id_users_data  FROM '.$DB->table('group_users').','.$DB->table('group_data').' where '.$DB->table('group_users').'.id_group_data='.$DB->table('group_data').'.id AND '.$DB->table('group_data').'.name="'.$post['target_value'].'"');
        }

        foreach($id_users as $id_user){
            $q = 'INSERT INTO '. $this->bddt_data.' SET name=?,description=?,level=?,id_user=?';
            $success = $DB->prepared_query($q,'ssii',[$post[$this->ifld_data_name],$post[$this->ifld_data_description],$post[$this->ifld_data_level],$id_user]);
        }
    }
    //supprime les données
    public function data_delete(&$post) {
        global $DB;
        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);

    }
    
    //les méthodes venues des autres sections

    //affiche les options de recherche
    public function form_search(&$post) {
        global $DB,$CRYPT;

        $output = H::group('search_recherche');

        $form = H::form(array('action'=>$this->get_index_relative_path(),'dom_target'=>$this->dom_container, 'class'=>$this->css.'_recherche_form_search'));

            $fields = $this->init_form_fields();
            $post['rechlnm'] = (!isset($post['rechlnm']))?'':$post['rechlnm'];
            $text = H::input_text(['class'=>'input_text', 'name'=>'rechlnm', 'id'=>'rechlnm', 'value'=>urldecode($post['rechlnm']), 'placeholder'=>$this->get_tl('placeholder'), 'onkeydown'=>'if (event.key == "Enter") recht.makeHash(event);']);
            //nombre résultat par page
            $selected = isset($post['nbr_resultat']) ? $post['nbr_resultat'] : $this->results_default_count;
            $results_per_page = [['val'=>12],['val'=>24],['val'=>48],['val'=>96]];
            $opts_data = array('value_key'=>'val', 'label_key'=>'val', 'options'=>$results_per_page);
            $select = H::select(['id'=>self::module_name.'_nbr_resultat', 'name'=>'nbr_resultat', 'label'=>$this->get_tl('nbr_res'), 'data-alwaysposted'=>1], $opts_data, $selected);
            $fields->add_child([$text,$select->label_tag(), $select]);
            // some hidden data
            $post['start_index'] = (isset($post['start_index']) && (isset($post['page_jumper']) && $post['page_jumper']==1))?intval($post['start_index']):0;
            $post['order_filter'] = isset($post['order_filter'])?$post['order_filter']:$CRYPT->encrypt('creation_date').'-d';
            $fields->add_child(H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'start_index','id'=>self::module_name.'_start_index', 'value'=>$post['start_index'])));
            $fields->add_child(H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'order_filter','id'=>self::module_name.'_order_filter', 'value'=>$post['order_filter'])));
            $fields->add_child(H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'page_jumper','id'=>self::module_name.'_page_jumper', 'value'=>0)));
            //fields optionnels advanced
            $advanced_fields_container= H::DIV(['class'=>'subcontainer_advanced_fields']);
            $name = H::input_text(['name'=>$this->ifld_data_name,'id'=>$this->ifld_data_name, 'label'=>$this->get_tl('name'), 'value'=>$post[$this->ifld_data_name], 'class'=>'inp_short_text']);
            $description = H::input_text(['name'=>$this->ifld_data_description,'id'=>$this->ifld_data_description, 'label'=>$this->get_tl('description'), 'value'=>$post[$this->ifld_data_description], 'class'=>'input_text']);
        $advanced_fields_container->add_child([$description->label_tag(),$description]);


            $btn_clear = H::submit_button(array('class'=>$this->module_name.'_admin_btn_search','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_admin_btn_clear' , 'value'=>'clear', 'class'=>$this->css.'btn_clear', 'title'=>$this->get_tl('Clear') ) ,'Clear');
            $btn_search = H::submit_button(array('class'=>$this->module_name.'_admin_btn_search','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_admin_btn_search' , 'value'=>$this->ACTION_SEARCH_recherche, 'class'=>$this->css.'btn_search', 'title'=>$this->get_tl('tlc_search') ) , $this->get_tl('tlc_search'));
            $fields->add_child([$advanced_fields_container,$btn_clear,$btn_search]);

        $form->add_child($fields);
        
        //~ $output->add_child([$title,$form]);
        $output->add_child([$form]);

        return $output;
    }
    
   
    //process et affiche le résultat de la recherche
    public function data_search(&$post){
        global $DB, $LANG, $CRYPT, $USER;
        $bdd_data = $this->bddt_data;
        $bdd_lang_long = $this->build_module_table_name('languages', 'long');
        $bdd_lang_short = $this->build_module_table_name('languages', 'short');
        
        $query_params_types = '';
        $query_values = array();
        $query_conditions = '';
        
        $post['defaultmode']=true;

        $post['nbr_resultat'] = isset($post['nbr_resultat']) ? intval($post['nbr_resultat']) : $this->results_default_count;
        $post['start_index'] = (isset($post['start_index']) && isset($post['page_jumper']) && $post['page_jumper']==1) ? intval($post['start_index']) : 0;
        $post['page_limit'] = (isset($post['page_limit']) && $post['page_jumper']==1)?$post['page_limit']+$post['nbr_resultat']:$post['nbr_resultat'];
        //$post['page_limit'] = ($post['nbr_resultat'] > 0) ? ($post['page_limit']+$post['nbr_resultat']) : $this->results_default_count;
        //preparation de la recherche basique sur le name avec séparation par espace
        if (!isset($post['rechlnm']) || $post['rechlnm']=='') {
            $search_string = '';
            $post['defaultmode']=true;
        }else{
            $search_string=$post['rechlnm'];
            unset($post['defaultmode']);
        }
        
        //secu et préparation du champ de recherche fulltext:
        $search_string = str_replace('%', '', $search_string);
        $s = trim($search_string);
        $s = explode(' ', $s);
        $s = trim($search_string);
        $s = explode(' ', $s);
        //on rend les divers mots obligatoires...
        $fulltext_string='';
        foreach ($s as $word) {
            $word = trim($word);
            if ($word != '' && strlen($word) > 1) {
                $fulltext_string .= ' +'.$word;
            }
        }
        $search_string = trim(addslashes($fulltext_string));
        
        if (isset($post[$this->ifld_data_name]) && $post[$this->ifld_data_name]!='') {
            unset($post['defaultmode']);
        }
        if (isset($post[$this->ifld_data_description]) && $post[$this->ifld_data_description]!='') {
            unset($post['defaultmode']);
        }

        
        if (!isset($post['defaultmode'])){
            //liste des fields sur lesquels ont peut faire une recherche fulltext non multilingue
            $text_fields=$bdd_data.'.name,'.$bdd_data.'.description';
            
            //query principale
    
            $q='(SELECT SQL_CALC_FOUND_ROWS '.$bdd_data.'.id as id, COUNT(MATCH('.$text_fields.') AGAINST(? in boolean MODE)) as score ';
            $query_params_types .= 's';
            array_push($query_values, $search_string);
             
            //tables
            $q.='FROM '.$bdd_data;
            
            //recherche valeur dans les champs
            $q.=' WHERE MATCH('.$text_fields.') AGAINST(? in boolean MODE) ';
            
            $query_params_types .= 's';
            array_push($query_values, $search_string);
    
            if (isset($post[$this->ifld_data_name]) && $post[$this->ifld_data_name]!='') {
               $query_params_types .= 's';
               $query_conditions .=' OR '.$bdd_data.'.name LIKE ?';
               array_push($query_values, '%'.$post[$this->ifld_data_name].'%');
            }
            if (isset($post[$this->ifld_data_description]) && $post[$this->ifld_data_description]!='') {
               $query_params_types .= 's';
               $query_conditions .=' OR '.$bdd_data.'.description LIKE ?';
               array_push($query_values, '%'.$post[$this->ifld_data_description].'%');
            }

    
            $q .= $query_conditions;
            $q.=' AND id_user='.$USER->id;
            $q.=' GROUP BY id )';

            //finalisation query :
            if (isset($post['order_filter']) && $post['order_filter'] != ''){
                $order_filter = $CRYPT->decrypt(substr($post['order_filter'], 0, -2));
                $sens = (substr($post['order_filter'], -1) == 'a') ? ' ASC' : ' DESC';
                $q.=' ORDER BY '.$order_filter.$sens;
            } else {
                $q.=' ORDER BY score DESC';
            }
            //$q.=(isset($post['order_filter']) && $post['order_filter'] != '')?' ORDER BY '.$post['order_filter']:' ORDER BY score DESC';
            $q.= ' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
            $results = $DB->prepared_query_list($q,$query_params_types,$query_values);
        }
        
        if (isset($post['defaultmode'])) {
            $q='SELECT SQL_CALC_FOUND_ROWS '.$bdd_data.'.id as id, 1 as score FROM '.$bdd_data;
            $q.=' WHERE id_user='.$USER->id;
            $q.=(isset($post['order_filter']) && $post['order_filter'] != '')?' ORDER BY '.$CRYPT->decrypt(substr($post['order_filter'], 0, -2)).((substr($post['order_filter'], -1) == 'a')?' ASC':' DESC'):' ORDER BY score DESC';
            $q.= ' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);

            $results = $DB->prepared_query($q);
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
            $pages = $DB->last_pages_data();
            $post['pages'] = $pages;
            $post['resultats'] = $mergedresult;
        } else {
            $post['pages'] = 0;
            $post['id'] = [];
        }
        return $this->display_search_result($post);
            
    }
    
    public function display_search_result(&$post)
    {
        global $module_html_content, $DB, $LANG, $CRYPT;
        
        $sub_container_id = 'recherche_recherche_resultat_public_container';
        
        $bdd_data = $this->bddt_data;
        
        $output = H::group('result_search');

        $pages = $post['pages'];
        if ($pages['page_count'] > 1) {
            $form_pages = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$sub_container_id, 'class'=>self::module_name.'_public_form_pages'));

            $params = [];
            if (isset($post['rechlnm']) && $post['rechlnm']!='' ) {
                $params['rechlnm'] = $post['rechlnm'];
            }
            //$params['nbr_resultat'] = $post['nbr_resultat'];
            $params['page_limit'] = $post['page_limit'];

            $index = $pages['page_index'];
            if ($index > 0) {
                $params['start_index'] = ($index-1)*$post['page_limit'];
                $btn_previous = H::BUTTON(['type'=>'button', 'class'=>self::module_name.'_prev_page' ,'onclick'=>'H_search.previous("'.self::module_name.'");', 'data-parameters'=>$params], H::_LEFT_DASHED_ARROW);
                $form_pages->add_child($btn_previous);
                unset($params['start_index']);
            }

            $opts = [];
            for ($i=0 ; $i<$pages['page_count']; $i++) {
                array_push($opts, ['label'=>$i+1, 'value'=>$i*$post['page_limit']]);
            }
            $options_data = array('label_key'=>'label', 'value_key'=>'value', 'options'=>$opts);
            $selected = $index*$post['page_limit'];
            $select = H::select(['name'=>'start_index', 'class'=>self::module_name.'_select_page' , 'onchange'=>'H_search.jump_to(event,"'.self::module_name.'");', 'data-parameters'=>$params], $options_data, $selected);
            $form_pages->add_child($select);

            if ($index < ($pages['page_count']-1)) {
                $params['start_index'] = ($index+1)*$post['page_limit'];
                $btn_next = H::BUTTON(['type'=>'button', 'class'=>self::module_name.'_next_page' ,'onclick'=>'H_search.next("'.self::module_name.'");', 'data-parameters'=>$params], H::_RIGHT_DASHED_ARROW);
                $form_pages->add_child($btn_next);
            }
        }


        if ($pages['page_count'] > 0) {
            $data_display = H::DIV(['class'=>$this->css.'resultbox', 'id'=>self::module_name.'_resultboxtitle']);
            
            $order_filter = false;
            $order_sens = false;
            if (isset($post['order_filter']) && $post['order_filter'] != ''){
                $order_filter = $CRYPT->decrypt(substr($post['order_filter'], 0, -2));
                $order_sens = (substr($post['order_filter'], -1) == 'a')?'.-d"':'.-a"';
            }
            $level =H::SPAN(['id'=>$this->ifld_data_name.'_entete', 'class'=>'entete entete_level '.($order_filter=='level'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('level').($order_filter=='level'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('level'));
            $name =H::SPAN(['id'=>$this->ifld_data_name.'_entete', 'class'=>'entete entete_name '.($order_filter=='name'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('name').($order_filter=='name'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('name'));
            $creation_date =H::SPAN(['id'=>$this->ifld_data_description.'_entete', 'class'=>'entete entete_date '.($order_filter=='creation_date'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('creation_date').($order_filter=='creation_date'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('creation_date'));            
            $name =H::SPAN(['id'=>$this->ifld_data_description.'_entete', 'class'=>'entete entete_name '.($order_filter=='name'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('name').($order_filter=='name'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('name'));
            $description =H::SPAN(['id'=>$this->ifld_data_description.'_entete', 'class'=>'entete entete_description '.($order_filter=='description'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('description').($order_filter=='description'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('description'));
            $data_display->add_child([$level,$creation_date,$name,$description]);

            $output->add_child($data_display);
            foreach ($post['resultats'] as $index=>$line) {
                $line_data=false;
                $qres='select * FROM '.$bdd_data.' where id='.$line['id'];
                $line_data = $DB->query_line($qres);
                $data_display = H::DIV(['class'=>$this->css.'resultbox', 'id'=>self::module_name.'_resultbox-'.$line['id']]);
                $level =H::SPAN(['id'=>$this->ifld_data_level.'_'.$line['id'], 'class'=>'disp_text alert_level_'.$line_data['level']], $line_data['level']);
                $creation_date =H::SPAN(['id'=>$this->ifld_data_date.'_'.$line['id'], 'class'=>'disp_text'], $line_data['creation_date']);
                $name =H::SPAN(['id'=>$this->ifld_data_name.'_'.$line['id'], 'class'=>'disp_text'], $line_data['name']);
                $description =H::DIV(['id'=>$this->ifld_data_description.'_'.$line['id'], 'class'=>'disp_textarea'], $line_data['description']);
                if ($line_data['action']!=''){
                    $btnAction=H::BUTTON(['id'=>'btnAction_'.$line['id'], 'class'=>'btnAction', 'onclick'=>$line_data['action']], $line_data['actionname']);
                }else{
                    $btnAction=H::SPAN(['class'=>'no_action'], '');
                }
                $formDelete = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$sub_container_id]);
                    $fields = $this->init_form_fields();
                    $fields->add_child(H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$line['id'], 'data-alwaysposted'=>1]));
                        $btn_delete = H::submit_button(array('class'=>$this->module_name.'_admin_btn_del', 'name'=>$this->input_action_identifier , 'id'=>$this->module_name.'_admin_btn_del_'.$line['id'], 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'style'=>'display:none;'),  'X');
                        $fake_btn_del = H::BUTTON(['type'=>'button', 'class'=>$this->css.'btn_del_fk', 'onclick'=>'H_search.del(event, "'.self::module_name.'", '.$line['id'].');', 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')], 'X');
                    $fields->add_child( [$btn_delete, $fake_btn_del] );
                $formDelete->add_child( $fields );
                $data_display->add_child([$level,$creation_date,$name,$description,$btnAction,$formDelete]);

                $output->add_child($data_display);
                
            }
            if ($pages['page_count'] > 1) {
                $output->add_child($form_pages);
            }
        } else {
            $output->add_child($this->get_tl('noresult'));
        }
        return $output;
    
    }
}