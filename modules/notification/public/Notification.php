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

namespace helPHP\modules\notification\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Htmlgroup;
use helPHP\libs\Datetime;
use helPHP\libs\User;
use helPHP\libs\Notification as Notification_class;

class Notification extends HelPHP_module{

    const module_name = 'notification';
    
    private $ACTION_NOTIFICATION_WIDGET = self::module_name.'_widget';
    private $ACTION_NEW = self::module_name.'_new';
    //~ private $ACTION_SAVE = self::module_name.'_save';
    private $ACTION_EDIT = self::module_name.'_edit';
    private $ACTION_SHOW = self::module_name.'_show';
    private $ACTION_SHOW_MODAL = self::module_name.'_modal';
    private $ACTION_DELETE = self::module_name.'_delete';
    private $ACTION_CLEARALL = self::module_name.'_clearall';
    private $ACTION_DELETE_MODAL = self::module_name.'_delete_modal';
    
    private $ACTION_SEARCH_recherche = self::module_name.'_recherche_search';
    private $ACTION_SHOW_recherche = self::module_name.'_recherche_show';
    
    private $results_default_count = 12;
    
    function __construct($dom_container = null) {
        //nomme le module,  et les variables qui en découle, indique si c'est un module admin ou pas en second param.
        // $this->input_action_identifier  et $this->dom_container 
        $this->prepare_module(self::module_name,false);
        // exécution de la classe parent qui initialise la langue et les données de traduction et le nomage de quelques variables utiles :
        parent::__construct();
    }
    //action additionnelles si il y a des sous sections

    public function process_data(&$post){
        global $USER;
        //~ UTILS_Class::error_log($post);
        //quelques check usuels...
        parent::process_data($post);
        //en fonction de l'action, appele la bonne méthode, mais aussi définit si il faut un domcontainer, 
        //et si on a affaire à une formulaire d'édition le controle des données/l'apply bdd, le reset et le language_load/save
        $master_output = H::group(self::module_name.'_display');
        
        if ($USER->connection_state == User::state_logged){
            switch($post[$this->input_action_identifier]){
                //action complémentaires...
                case $this->ACTION_DELETE:
                    $this->check_posted_data($post,'notification_data');
                    $this->Data_Delete($post);
                    //si il y a des champs multilingue faire appel à delete_translation_data ici.


                    $post[$this->ifld_data_id]=0;
                    $this->reset_fields($post, 'notification_data');
                    //$master_output->add_child( $this->menu($post) );
                    // $master_output->add_child( $this->Data_Edit($post) );
                break;
                
                case $this->ACTION_DELETE_MODAL:
                    $this->check_posted_data($post,'notification_data');
                    $master_output->add_child( $this->Data_Delete_modal($post) );
                break;
                //les autres appels de méthodes si il y a des sous sections

                case $this->ACTION_SHOW_recherche:
                    $this->prepare_fields($post, 'notification_data');
                    $master_output->add_child( $this->Menu($post) );
                    //si il y a des champs multilingue faire appel à load_translation_data ici.

                    $master_output->add_child( $this->Menu_Search_recherche($post) );
                    // $master_output->add_child( $this->Data_Show_recherche($post) );
                break;
                
                case $this->ACTION_SEARCH_recherche:
                    $this->prepare_fields($post, 'notification_data');
                    $master_output->add_child( $this->Menu($post) );
                    $master_output->add_child( $this->Menu_Search_recherche($post) );
                    $master_output->add_child( $this->Data_Search_recherche($post) );
                break;
                
                case $this->ACTION_NOTIFICATION_WIDGET:
                    $this->dom_container='notification_widget';
                    $master_output->add_child( $this->Show_Widget($post) );
                break;
                
                case $this->ACTION_SHOW:
                    $master_output->add_child( $this->Show($post) );
                break;
                break;
                case $this->ACTION_SHOW_MODAL:
                    $master_output->add_child( $this->ShowModal() );
                break;
                case $this->ACTION_CLEARALL:
                    $this->Data_Delete_all();
                    $this->reset_fields($post, 'notification_data');
                    unset($post['closed']);
                    $post['defaultmode']=true;
                    $master_output->add_child( $this->Menu($post) );
                    // si les sous sections ont des affichages a ajouté à celui de base

                    $master_output->add_child( $this->Menu_Search_recherche($post) );
                    $master_output->add_child( $this->Data_Search_recherche($post) );
                
                break;    
                default:
                    // if (is_array($post)) $this->reset_fields($post, 'data');
                    // unset($post['closed']);
                    $post['defaultmode']=true;
                    $master_output->add_child( $this->Menu($post) );
                    // si les sous sections ont des affichages a ajouté à celui de base

                    $master_output->add_child( $this->Menu_Search_recherche($post) );
                    $master_output->add_child( $this->Data_Search_recherche($post) );
                
                break;
            }
        }else{
            $master_output->add_child(H::tag(H::SPAN, ['id'=>'not_connected_message', 'class'=>'not_connected_message'], $this->get_tl('tlc_notconnected')));
        }
        //~ if ($toreturn){
                //~ return $master_output;
        //~ }else{
            $this->display->add_child( $master_output );
            //~ $chron->step_chrono('fin traitement module généré ');
            //~ $this->display->add_child( $chron->get_chrono());
        //~ }
    }
     
    public function Show($post){
        //dans le cas d'un affichage urgent en modale.

        global $DB;
        if(isset($post['notification'])){
            $post[$this->ifld_data_id] = $post['notification'];
        }
        if(isset($post[$this->ifld_data_id]) &&  $post[$this->ifld_data_id] != 0){
            //l'on emploi une query et non pas applybdddata pour des questions de perf et de customisation, comme le test des groupes.
            $q = 'SELECT DISTINCT * from '. $this->bddt_data.' where id=?';
            //enrichissemnt de la query si option group

            $data = $DB->prepared_query($q,'i',[$post[$this->ifld_data_id]]);
            //ajout dans le post
            $post = array_merge($post, $data);
            //si il y a des champs multilingue faire appel à load_translation_data.

        }else{
         return;
        }
        $output = H::group('show_notification');
        //un petit div en cas de besoin...
        $data_display = H::tag(H::DIV, ['class'=>$this->css.'fiche']);
        //affichage du contenu des champs
                $name_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_name_label, 'class'=>'label'], $post[$this->get_tl('name')]);
                $name =H::tag(H::SPAN, ['id'=>$this->ifld_data_name, 'class'=>'disp_text'], $post[$this->ifld_data_name]);
                $description_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_description_label, 'class'=>'label'], $post[$this->get_tl('description')]);
                $description =H::tag(H::DIV, ['id'=>$this->ifld_data_description, 'class'=>'disp_textarea'], $post[$this->ifld_data_description]);
                $date_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_date_label, 'class'=>'label'], $post[$this->get_tl('date')]);
                $date =H::tag(H::SPAN, ['id'=>$this->ifld_data_date, 'class'=>'disp_date'], $post[$this->ifld_data_date]);
                $occurence_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_occurence_label, 'class'=>'label'], $post[$this->get_tl('occurence')]);
                $occurence =H::tag(H::SPAN, ['id'=>$this->ifld_data_occurence, 'class'=>'disp_int'], $post[$this->ifld_data_occurence]);
                $level_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_level_label, 'class'=>'label'], $post[$this->get_tl('level')]);
                $level =H::tag(H::SPAN, ['id'=>$this->ifld_data_level, 'class'=>'disp_int'], $post[$this->ifld_data_level]);
                $action_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_action_label, 'class'=>'label'], $post[$this->get_tl('action')]);
                $action =H::tag(H::SPAN, ['id'=>$this->ifld_data_action, 'class'=>'disp_text'], $post[$this->ifld_data_action]);
                $data_display->add_child([$action_label,$action]);

        $output->add_child($data_display);
        //appel aux autres méthodes d'affichage des sous-sections.

        return $output;
    }
    
    public function ShowModal(){
        //dans le cas d'un affichage urgent en modale.

        global $DB, $USER;
        //getting last modal message:
        $q = 'SELECT * FROM '.$this->bddt_data.' where type > 10 and type < 21 and id_user='.$USER->id.' ORDER by date LIMIT 0,1';
        $line = $DB->query_line($q);
        
        $output = H::group('show_notification');
        //un petit div en cas de besoin...
        $data_display = H::tag(H::DIV, ['class'=>$this->css.'content_modal','id'=>$this->css.'content_modal']);
        //affichage du contenu des champs
                //~ $name_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_name_label, 'class'=>'label'], $post[$this->get_tl('name')]);
                $name =H::tag(H::SPAN, ['id'=>$this->ifld_data_name, 'class'=>'disp_text'], $line['name']);
                //~ $description_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_description_label, 'class'=>'label'], $post[$this->get_tl('description')]);
                $description =H::tag(H::DIV, ['id'=>$this->ifld_data_description, 'class'=>'disp_textarea'], $line['description']);
                //~ $date_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_date_label, 'class'=>'label'], $post[$this->get_tl('date')]);
                $date =H::tag(H::SPAN, ['id'=>$this->ifld_data_date, 'class'=>'disp_date'], $line['date']);
                //~ $occurence_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_occurence_label, 'class'=>'label'], $post[$this->get_tl('occurence')]);
                //~ $occurence =H::tag(H::SPAN, ['id'=>$this->ifld_data_occurence, 'class'=>'disp_int'], $post[$this->ifld_data_occurence]);
                //~ $level_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_level_label, 'class'=>'label'], $post[$this->get_tl('level')]);
                //~ $level =H::tag(H::SPAN, ['id'=>$this->ifld_data_level, 'class'=>'disp_int'], $post[$this->ifld_data_level]);
                //~ $action_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_action_label, 'class'=>'label'], $post[$this->get_tl('action')]);
                if ($line['action']!=''){
                    $btnAction=H::tag(H::BUTTON, ['id'=>'btnAction_'.$line['id'], 'class'=>'btnAction', 'onclick'=>$line['action']], $line['actionname']);
                }else{
                    $btnAction=H::tag(H::SPAN, ['class'=>'no_action'], '');
                }
                $formDelete = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->css.'content_modal']);
                    $fields = $this->init_form_fields();
                    $fields->add_child(H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$line['id'], 'data-alwaysposted'=>1]));
                        $btn_delete = H::submit_button(array('class'=>$this->module_name.'_admin_btn_del', 'name'=>$this->input_action_identifier , 'id'=>$this->module_name.'_admin_btn_del_'.$line['id'], 'value'=>$this->ACTION_DELETE_MODAL, 'title'=>$this->get_tl('tlc_del')),  'Delete');
                    $fields->add_child( [$btn_delete] );
                $formDelete->add_child( $fields );
                $data_display->add_child([$date,$name,$description,$btnAction,$formDelete]);


        $output->add_child($data_display);
        //appel aux autres méthodes d'affichage des sous-sections.

        return $output;
    }
    
   //affiche le bouton pour ajouter un nouvel utilisateur
    public function Menu ($post) {

        $output = H::group('add_notification');

        $title=H::tag(H::SPAN, array('class'=>'notification_public_title module_ttl'), $this->get_tl('title'));
        $output->add_child([$title]);
        return $output;
    }
    
    public function Show_Widget($post){
        global $USER,$DB,$CONFIG;
        $output = H::group('notification_wid');

        $scriptContent='setTimeout(function(){Notif.create_instance("'.$this->dom_id.'");';

        
        
        $notifsound='<audio id="notification_sound" preload="auto" style="display:none;"><source src="'.$CONFIG::BASE_URL.'images/notif.mp3" type="audio/mp3" /></audio>';
        $output->add_child($notifsound);
        $q = 'SELECT count(*) as total, MAX(level) as level FROM '.$this->bddt_data.' where type < 11 and id_user='.$USER->id;
        $line = $DB->query_line($q);
        $q = 'SELECT * FROM '.$this->bddt_data.' where type < 11 and id_user='.$USER->id. ' LIMIT 0,1';
        $last = $DB->query_line($q);
        // UTILS_Class::error_log($last_id);
        $notificationtxt=H::tag(H::SPAN, array('class'=>'nb_notification_'.$line['level'], 'onclick'=>'H_history.change_hash(event, "notification")'), $line['total']);
        $output->add_child([$notificationtxt]);
        $notif=($line['total']>0)?'  h.modules.notif["'.$this->dom_id.'"].notify("'.$last['name'].'","'.$last['description'].'","'.$last['id'].'n");':' h.modules.notif["'.$this->dom_id.'"].noalert();';
        $scriptContent.=$notif;
        //checking if there is modal :
        $q = 'SELECT count(*) as total FROM '.$this->bddt_data.' where type > 10 and type < 21 and id_user='.$USER->id;
        $line = $DB->query_line($q);
        if ($line['total']>0){
            $q = 'SELECT * FROM '.$this->bddt_data.' where type > 10 and type < 21 and id_user='.$USER->id. ' LIMIT 0,1';
            $last = $DB->query_line($q);
            $scriptContent.=' h.modules.notif["'.$this->dom_id.'"].weGotAModal(); h.modules.notif["'.$this->dom_id.'"].notify("'.$last['name'].'","'.$last['description'].'","'.$last['id'].'m");';
        }

        //js notification
        $q = 'SELECT * FROM '.$this->bddt_data.' where type > 20 and id_user='.$USER->id;
        // if ($USER->id == 2) UTILS_Class::error_log($q);
        $lines = $DB->query_list($q);
        if ($lines) {
            $somejs='';
            $ids=[];
            foreach ($lines as $index=>$line) {
                $somejs.=$line['description'].$line['action'];
                $ids[]=$line['id'];
            }
            $scriptContent.=' '.$somejs;
            $ids=implode(',',$ids);
            $q = 'DELETE FROM '.$this->bddt_data.' WHERE id in ('.$ids.')';
            $res = $DB->query($q);
        }
        $scriptContent.='},500);';
        $output->add_child(H::script($scriptContent));
        return $output;
    }
    
    
    //sauve les données.
    //~ public function Data_Save(&$post){
        //~ global $DB;
        //~ if($post[$this->ifld_data_id] == 0){
            //~ // création
            //~ $q = 'INSERT INTO '. $this->bddt_data.' SET name=?,description=?,date=?,occurence=?,level=?,action=?';
            //~ $success = $DB->prepared_query($q,'sssiis',[$post[$this->ifld_data_name],$post[$this->ifld_data_description],$post[$this->ifld_data_date],$post[$this->ifld_data_occurence],$post[$this->ifld_data_level],$post[$this->ifld_data_action]]);
            //~ $post[$this->ifld_data_id] = $DB->last_insert_id();
        //~ }else{
            //~ // mise à jour
            //~ $q = 'UPDATE '. $this->bddt_data.' SET name=?,description=?,date=?,occurence=?,level=?,action=? where id=?';
            //~ //attention au i du type de l'id qui est pré-inséré
            //~ $success = $DB->prepared_query($q,'sssiisi',[$post[$this->ifld_data_name],$post[$this->ifld_data_description],$post[$this->ifld_data_date],$post[$this->ifld_data_occurence],$post[$this->ifld_data_level],$post[$this->ifld_data_action], $post[$this->ifld_data_id]]);
        //~ }
    //~ }
    //supprime les données
    public function Data_Delete(&$post) {
        global $DB;
        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);

    }
    //supprime toute les alertes
    public function Data_Delete_all() {
        global $DB,$USER;
        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id_user='.$USER->id;
        // UTILS_Class::error_log($q);
        $res = $DB->query($q);

    }
    public function Data_Delete_modal(&$post) {
        global $DB;
        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';
        $res = $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
        $output = H::group('output_delete');
        $output->add_child(H::script('H_ui.popup_special[\'notification\'].hide();'));
        return $output;
    }
    
    //les méthodes venues des autres sections

    //affiche les options de recherche
    public function Menu_Search_recherche($post) {

        $output = H::group('search_recherche');

        //~ $title=H::tag(H::SPAN, array('class'=>'recherche_admin_search_title'), $this->get_tl('recherche_search_title'));
              
                
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
            $post['start_index'] = isset($post['start_index'])?intval($post['start_index']):0;
            $post['order_filter'] = isset($post['order_filter'])?$post['order_filter']:'';
            $fields->add_child(H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'start_index','id'=>self::module_name.'_start_index', 'value'=>$post['start_index'])));
            $fields->add_child(H::input_hidden(array('data-alwaysposted'=>1, 'name'=>'order_filter','id'=>self::module_name.'_order_filter', 'value'=>$post['order_filter'])));
            
            //fields optionnels advanced
            //~ $advanced_fields_container= H::tag(H::DIV, ['class'=>'subcontainer_advanced_fields']);
            //~ $name = H::input_text(['name'=>$this->ifld_data_name,'id'=>$this->ifld_data_name, 'label'=>$this->get_tl('name'), 'value'=>$post[$this->ifld_data_name], 'class'=>'inp_short_text']);
            //~ $description = H::input_text(['name'=>$this->ifld_data_description,'id'=>$this->ifld_data_description, 'label'=>$this->get_tl('description'), 'value'=>$post[$this->ifld_data_description], 'class'=>'input_text']);
        //~ $advanced_fields_container->add_child([$description->label_tag(),$description]);


            //~ $btn_clear = H::submit_button(array('class'=>$this->module_name.'_admin_btn_search','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_admin_btn_clear' , 'value'=>'clear', 'class'=>$this->css.'btn_clear', 'title'=>$this->get_tl('Clear') ) ,'Clear');
            $btn_search = H::submit_button(array('class'=>$this->module_name.'_admin_btn_search','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_admin_btn_search' , 'value'=>$this->ACTION_SEARCH_recherche, 'class'=>$this->css.'btn_search', 'title'=>$this->get_tl('tlc_search') ) , $this->get_tl('tlc_search'));
            //~ $fields->add_child([$advanced_fields_container,$btn_clear,$btn_search]);
            $fields->add_child([$btn_search]);

        $form->add_child($fields);
        
        //~ $output->add_child([$title,$form]);
        $output->add_child([$form]);

        $notification = H::button(array('class'=>$this->module_name.'_notification', 'id'=>$this->module_name.'_notification' , 'value'=>'System notification ?', 'class'=>$this->css.'btn_notification', 'title'=>$this->get_tl('tlc_search'),'onclick'=>' h.modules.notif["'.$this->dom_id.'"].asknotif(event);' ) , 'System notification');
        $output->add_child([$notification]);   


        return $output;
    }
    
   
    //process et affiche le résultat de la recherche
    public function Data_Search_recherche(&$post){
        // global $DB, $LANG, $CRYPT, $USER;       
        global $DB, $CRYPT, $USER;       

        $bdd_data = $this->bddt_data;
        // $bdd_lang_long = $this->build_module_table_name('languages', 'long');
        // $bdd_lang_short = $this->build_module_table_name('languages', 'short');
        
        $query_params_types = '';
        $query_values = array();
        $query_conditions = '';
        
        $post['defaultmode']=true;

        $post['nbr_resultat'] = isset($post['nbr_resultat']) ? intval($post['nbr_resultat']) : $this->results_default_count;
        $post['start_index'] = isset($post['start_index']) ? intval($post['start_index']) : 0;
        $post['page_limit']= isset($post['page_limit']) ? $post['page_limit'] : 0;
        $post['page_limit'] = ($post['nbr_resultat'] > 0) ? ($post['page_limit']+$post['nbr_resultat']) : $this->results_default_count;
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
            $q .=  'AND type < 11 and id_user='.$USER->id;
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
            $q .=  ' WHERE type < 11 and id_user='.$USER->id;
            $q.=(isset($post['order_filter']) && $post['order_filter'] != '')?' ORDER BY '.$CRYPT->decrypt(substr($post['order_filter'], 0, -2)).((substr($post['order_filter'], -1) == 'a')?' DESC':' ASC'):' ORDER BY score DESC';
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
        return $this->ProcessData_result_recherche($post);
            
    }
    
    public function ProcessData_result_recherche(&$post)
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
                $btn_previous = H::tag(H::BUTTON, ['type'=>'button', 'class'=>self::module_name.'_prev_page' ,'onclick'=>'H_search.previous("'.self::module_name.'");', 'data-parameters'=>$params], H::_LEFT_DASHED_ARROW);
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
                $btn_next = H::tag(H::BUTTON, ['type'=>'button', 'class'=>self::module_name.'_next_page' ,'onclick'=>'H_search.next("'.self::module_name.'");', 'data-parameters'=>$params], H::_RIGHT_DASHED_ARROW);
                $form_pages->add_child($btn_next);
            }
        }


        if ($pages['page_count'] > 0) {
            $data_display = H::tag(H::DIV, ['class'=>$this->css.'resultbox', 'id'=>self::module_name.'_resultboxtitle']);
            
            $order_filter = false;
            $order_sens = false;
            if (isset($post['order_filter']) && $post['order_filter'] != ''){
                $order_filter = $CRYPT->decrypt(substr($post['order_filter'], 0, -2));
                $order_sens = (substr($post['order_filter'], -1) == 'a')?'.-d"':'.-a"';
            }
            $level =H::tag(H::SPAN, [ 'class'=>'entete entete_level '.($order_filter=='level'?($order_sens=='.-a"'?'filteroff':'filteron'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('level').($order_filter=='level'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('level'));
            $name =H::tag(H::SPAN, [ 'class'=>'entete entete_name '.($order_filter=='name'?($order_sens=='.-a"'?'filteroff':'filteron'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('name').($order_filter=='name'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('name'));
            $date =H::tag(H::SPAN, [ 'class'=>'entete entete_date '.($order_filter=='date'?($order_sens=='.-a"'?'filteroff':'filteron'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('date').($order_filter=='date'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('date'));            
            $name =H::tag(H::SPAN, [ 'class'=>'entete entete_name '.($order_filter=='name'?($order_sens=='.-a"'?'filteroff':'filteron'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('name').($order_filter=='name'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('name'));
            $description =H::tag(H::SPAN, ['id'=>$this->ifld_data_description.'_entete', 'class'=>'entete entete_description '.($order_filter=='description'?($order_sens=='.-a"'?'filteroff':'filteron'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('description').($order_filter=='description'?$order_sens:'.-a"').',"'.self::module_name.'");'], $this->get_tl('description'));
            $data_display->add_child([$level,$date,$name,$description]);

            $output->add_child($data_display);
            foreach ($post['resultats'] as $index=>$line) {
                $line_data=false;
                $qres='select * FROM '.$bdd_data.' where id='.$line['id'];
                $line_data = $DB->query_line($qres);
                $data_display = H::tag(H::DIV, ['class'=>$this->css.'resultbox', 'id'=>self::module_name.'_resultbox-'.$line['id']]);
                $level =H::tag(H::SPAN, ['id'=>$this->ifld_data_level.'_'.$line['id'], 'class'=>'disp_text alert_level_'.$line_data['level']], $line_data['level']);
                $date =H::tag(H::SPAN, ['id'=>$this->ifld_data_date.'_'.$line['id'], 'class'=>'disp_text'], $line_data['date']);
                $name =H::tag(H::SPAN, ['id'=>$this->ifld_data_name.'_'.$line['id'], 'class'=>'disp_text'], $line_data['name']);
                $description =H::tag(H::DIV, ['id'=>$this->ifld_data_description.'_'.$line['id'], 'class'=>'disp_textarea'], $line_data['description']);
                if ($line_data['action']!=''){
                    $btnAction=H::tag(H::BUTTON, ['id'=>'btnAction_'.$line['id'], 'class'=>'btnAction', 'onclick'=>$line_data['action']], $line_data['actionname']);
                }else{
                    $btnAction=H::tag(H::SPAN, ['class'=>'no_action'], '');
                }
                $formDelete = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$sub_container_id]);
                    $fields = $this->init_form_fields();
                    $fields->add_child(H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$line['id'], 'data-alwaysposted'=>1]));
                        $btn_delete = H::submit_button(array('class'=>$this->module_name.'_admin_btn_del', 'name'=>$this->input_action_identifier , 'id'=>$this->module_name.'_admin_btn_del_'.$line['id'], 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'style'=>'display:none;'),  'X');
                        $fake_btn_del = H::tag(H::BUTTON, ['type'=>'button', 'class'=>$this->css.'btn_del_fk', 'onclick'=>'H_search.del(event, "'.self::module_name.'", '.$line['id'].');', 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')], 'X');
                    $fields->add_child( [$btn_delete, $fake_btn_del] );
                $formDelete->add_child( $fields );
                $data_display->add_child([$level,$date,$name,$description,$btnAction,$formDelete]);

                $output->add_child($data_display);
                
            }
            if ($pages['page_count'] > 1) {
                
                $output->add_child($form_pages);
            }
            $form_pages = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_container, 'class'=>self::module_name.'_public_form_pages'));
            $btn_clearall = H::submit_button(array('class'=>$this->module_name.'_admin_btn_clear','name'=>$this->input_action_identifier, 'id'=>$this->module_name.'_btn_clear' , 'value'=>$this->ACTION_CLEARALL, 'class'=>$this->css.'btn_search', 'title'=>'Delete all alerts', 'data-confirm'=>'Are you sure?' ) , 'Delete all alerts');
            $form_pages->add_child($btn_clearall);
            $output->add_child($form_pages);
        } else {
            $nores = H::SPAN(['class'=>$this->css.'noresult'], $this->get_tl('noresult'));
            $output->add_child($nores);
        }
        return $output;
    
    }
}
?>