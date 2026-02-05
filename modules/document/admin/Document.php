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
namespace helPHP\modules\document\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Datetime;
use helPHP\libs\Language;
use helPHP\modules\indexation\public\Indexation;
use helPHP\modules\indexation\admin\Indexation as Indexation_admin;


class Document extends HelPHP_module {

    const module_name = 'document';

    function __construct($domContainer = null) {
        $this->prepare_module(self::module_name, true);
        // exécution de la classe parent qui initialise la langue et les données de traduction et le nomage de quelques variables utiles :
        parent::__construct($domContainer);
        $this->prepared_anim = [
            ['id'=>0,'name'=>$this->get_tl('Aucune'), 'opts'=>''],
            // apparition scrollup
            ['id'=>1,'name'=>$this->get_tl('scroll_fade_in'), 'opts'=>'"preset": true,"event": "scroll","easing": "OutQuad","duration": "700", "opacity": ["0", "1"]'],
            ['id'=>2,'name'=>$this->get_tl('scroll_smooth_scale'), 'opts'=>'"preset": true,"event": "scroll","easing": "OutQuad","duration": "500", "transform": ["scale(0.7,0.7)", "scale(1,1)"], "opacity": ["0", "1"]'],
             // Click
            ['id'=>3,'name'=>$this->get_tl('click_fade_in'), 'opts'=>'"event":"hover","opacity": ["0", "1"], "duration": "1000"'],
            // apparition fondu
            ['id'=>4,'name'=>$this->get_tl('apparition'), 'opts'=>'"opacity": ["0", "1"], "duration": "3000"'],
            ['id'=>5,'name'=>$this->get_tl('zoom_in'), 'opts'=>'"easing": "InQuad", "duration": "1000", "transform": ["scale(0.1)", "scale(1)"]'],
            ['id'=>6,'name'=>$this->get_tl('zoom_out'), 'opts'=>'"easing": "InQuad", "duration": "1000", "transform": ["scale(2)", "scale(1)"]'],
            // apparition fondu avec glissement d'un coté
            ['id'=>7,'name'=>$this->get_tl('arrive_par_la_gauche'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateX(-100%)", "translateX(0%)"], "opacity": ["0", "1"]'],
            ['id'=>8,'name'=>$this->get_tl('arrive_par_la_droite'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateX(100%)", "translateX(0%)"], "opacity": ["0", "1"]'],
            ['id'=>9,'name'=>$this->get_tl('arrive_par_le_haut'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateY(-100%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>10,'name'=>$this->get_tl('arrive_par_le_bas'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateY(100%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>11,'name'=>$this->get_tl('appear_up-250'), 'opts'=>'"easing": "OutQuart",duration": "250", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>12,'name'=>$this->get_tl('appear_up-500'), 'opts'=>'"easing": "OutQuart","duration": "500", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>13,'name'=>$this->get_tl('appear_up-750'), 'opts'=>'"easing": "OutQuart","duration": "750", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>14,'name'=>$this->get_tl('appear_up-1000'), 'opts'=>'"easing": "OutQuart","duration": "1000", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            // rotation
            ['id'=>15,'name'=>$this->get_tl('rotation_horaire'), 'opts'=>'"easing": "InOutCubic", "duration": "1000", "transform": ["rotate(0deg)", "rotate(360deg)"]'],
            ['id'=>16,'name'=>$this->get_tl('rotation_anti-horaire'), 'opts'=>'"easing": "InOutCubic", "duration": "1000", "transform": ["rotate(0deg)", "rotate(-360deg)"]'],
            // pulse
            ['id'=>17,'name'=>$this->get_tl('pouls'), 'opts'=>'"easing": "InOutExpo", "duration": "500", "direction": "alternate", "transform": ["scale(1, 1)", "scale(1.2, 1.2)"], "loop": true']
        ];
    
    }

    //début du code à copier dans le public si on désire des fonctions de saisie dans le public 
    private $ACTION_NEW_document_data = self::module_name.'_new';
    private $ACTION_SAVE_document_data = self::module_name.'_save';
    private $ACTION_SAVE_document_properties = self::module_name.'_properties';
    private $ACTION_EDIT_document_data = self::module_name.'_edit';
    private $ACTION_COPY_document_data = self::module_name.'_copy';
    private $ACTION_PUBLISH = self::module_name.'_publish';
    private $ACTION_DELETE_document_data = self::module_name.'_delete';
    private $ACTION_SAVE_blocks_order = self::module_name.'_block_save_sort_order';
    //action additionnelles si il y a des sous sections

    private $ACTION_LOAD_block = self::module_name.'_block_load';
    // private $ACTION_EDIT_block = self::module_name.'_block_edit';
    // private $ACTION_NEW_block = self::module_name.'_block_new';
    private $ACTION_SAVE_block = self::module_name.'_block_save';
    private $ACTION_DELETE_block = self::module_name.'_block_delete';
    private $ACTION_ANIMATION_block = self::module_name.'_block_animation';
    private $ACTION_SAVE_ANIMATION_block = self::module_name.'_block_save_animation';
    private $ACTION_COPY_block = self::module_name.'_block_copy';
    
    // when no right to edit
    // private $ACTION_DISPLAY_blocks = self::module_name.'_block_display';
    
    private $ACTION_SEARCH = self::module_name.'_search';
    private $ACTION_SEARCH_RESULT = self::module_name.'_search_result';
    
    private $results_default_count = 12;
    public $mmode = false; // true if we are in modele mode

    public $prepared_anim = [];
    
    public function process_data(&$post, $toreturn=false){
        global $DB;
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post['document_data-id'] = $post['id'];
        }
        
        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'noedit '.$this->css;
        }

        if (isset($post['modele']) && $post['modele'] != ''){
            $id_exist = $this->check_modele_exists($post['modele']);
            if ($id_exist > 0) {
                $this->mmode = $post['modele'];
            }
        }
        
        //because the new button is a modal call 
        $tmp = explode('-', $post[$this->input_action_identifier]);
        if ($tmp[0] == 'document_new' && isset($tmp[1])) {
            $this->mmode = $tmp[1];
            $post[$this->input_action_identifier]=$this->ACTION_NEW_document_data;
        }
        if ($tmp[0] == 'document_edit' && isset($tmp[1])) {
            $this->mmode = $tmp[1];
            $post[$this->input_action_identifier]=$this->ACTION_EDIT_document_data;
        }

        $master_output = H::group(self::module_name.'_display');
        switch($post[$this->input_action_identifier]){
            //les diverses action sur l'objet maitre donc sur la table data !    
            case $this->ACTION_NEW_document_data:
                if ($this->user_can_edit){ // needed for security
                    if($this->mmode){
                        $post['document_data-id'] = $DB->prepared_query_value('SELECT id FROM '.$DB->table('document_data').' WHERE ismodele=1 AND modele=?', 's', [$this->mmode]);
                        
                        $post['document_data-id'] = $this->copy_document_data($post);

                        $master_output->add_child( $this->module_header($post) );
                        // si les sous sections ont des affichages a ajouté à celui de base
                        $master_output->add_child( $this->form_search($post) );
                        $master_output->add_child( $this->result_search($post) );
                        
                    }else{
                        $post['document_data-id'] = 0;
                        $this->reset_fields($post, 'document_data');

                        $master_output->add_child( $this->module_header($post) );
                        // si les sous sections ont des affichages a ajouté à celui de base
                        $master_output->add_child( $this->form_search($post) );
                        $master_output->add_child( $this->result_search($post) );
                        // $post['document_data-id']=0;
                        
                    }
                    $script = H::script('H_search.modal_edit('.$post['document_data-id'].', "'.self::module_name.'", "data", "edit","'.$this->dom_id.'");', ['autoremove'=>true]);
                    $master_output->add_child($script);
                }
            break;
            
            case $this->ACTION_EDIT_document_data:
                if ($this->user_can_edit){ // nedded for security

                    if (isset($post[self::module_name.'_id']) && $post[self::module_name.'_id']){
                        $post['document_data-id'] = $post[self::module_name.'_id'];
                    }
                    $this->prepare_fields($post, 'document_data');

                    global $LANG;
                    $LANG->load_translation_data($post, 'document', 'data', $post['document_data-id']);


                    $title = H::DIV(['class'=>$this->css.'title_modal_edit module_title'], $this->get_tl('ttl_edit'));
                    $master_output->add_child( $title );
                    if (isset($post['document_data-id']) && $post['document_data-id'] != 0) {
                        $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post['document_data-id'])));
                    }

                    if ($post[$this->ifld_data_id] > 0) $tabs = $this->display_tabs($post);
                    else {
                        $this->reset_fields($post, 'document_data');
                        // $tabs = H::tabs(['dom_id'=>$this->dom_target], [$this->get_tl('tl_properties')], [$this->edit_document_properties($post)]);
                        $tabs = H::tabs(['class'=>$this->css.'tabs', 'dom_id'=>$this->dom_target], [$this->get_tl('tl_properties')], [$this->edit_document_properties($post)]);
                    }

                    $master_output->add_child( $tabs );
                }
            break;
            case $this->ACTION_COPY_document_data:
                if ($this->user_can_edit){ // nedded for security
                    if (isset($post[self::module_name.'_id']) && $post[self::module_name.'_id']){
                        $post['document_data-id'] = $post[self::module_name.'_id'];
                    }

                    $post['document_data-id']=$this->copy_document_data($post);
                    $master_output->add_child( $this->module_header($post) );
                    // si les sous sections ont des affichages a ajouté à celui de base
                    $master_output->add_child( $this->form_search($post) );
                    $master_output->add_child( $this->result_search($post) );
                    $script = H::script('H_search.modal_edit('.$post['document_data-id'].', "'.self::module_name.'", "data", "edit","'.$this->dom_id.'");', ['autoremove'=>true]);
                    $master_output->add_child($script);
                }
            break;
            
            case $this->ACTION_SAVE_document_properties:
                if ($this->user_can_edit){ // nedded for security
                    $this->check_posted_data($post, 'document_data');

                    $post['document_data-id'] = $this->save_document_data($post);
                    
                    Language::save_translation_data($post, $post['document_data-id']);
                    if (isset($post['document_data-id']) && $post['document_data-id']!=''){
                        if (isset($post[self::module_name.'_id']) && $post[self::module_name.'_id']){
                            $post['document_data-id'] = $post[self::module_name.'_id'];
                        }
                        $this->prepare_fields($post, 'document_data');

                        global $LANG;
                        $LANG->load_translation_data($post, 'document', 'data', $post['document_data-id']);


                        $title = H::DIV(['class'=>$this->css.'title_modal_edit module_title'], $this->get_tl('ttl_edit'));
                        $master_output->add_child( $title );
                        $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post['document_data-id'])));
                        
                        $master_output->add_child( $this->display_tabs($post));
                        
                    
                    }else{
                        $master_output->add_child( $this->edit_document_properties($post) );
                    //     
                    }
                }
            break;
            
            case $this->ACTION_DELETE_document_data:
                if ($this->user_can_edit){ // nedded for security
                    $this->check_posted_data($post, 'document_data');
                    
                    $this->delete_document_data($post);

                    Language::delete_translation_data($post, 'document', 'data', $post['document_data-id']);

                    $_POST['indexation_data-module_name'] = 'document';
                    $_POST['indexation_data-module_param'] = $post['document_data-id'];
                    $_POST['indexation_action'] = 'indexation_delete';
                    $module_indexation=new Indexation_admin();
                    $module_indexation->process_data($_POST);

                }
            break;

            case $this->ACTION_PUBLISH:
                if ($this->user_can_edit){ // nedded for security
                    $this->check_posted_data($post, 'document_data'); 
                    
                    $master_output->add_child( $this->form_publish($post) );
                    $master_output->add_child( $this->publish_document($post));
                }
            break;
            
            
            case $this->ACTION_SAVE_blocks_order:
                $this->save_blocks_order($post);
            break;
    
            case $this->ACTION_SEARCH_RESULT:
                $this->prepare_fields($post, 'document_data');
                $master_output->add_child( $this->module_header($post) );

                global $LANG;
                $LANG->load_translation_data($post, 'document','data');

                $master_output->add_child( $this->form_search($post) );
                $master_output->add_child( $this->result_search($post) );
            break;
            
            case $this->ACTION_SEARCH:
                $this->prepare_fields($post, 'document_data');
                $master_output->add_child( $this->module_header($post) );
                $master_output->add_child( $this->form_search($post) );
                $master_output->add_child( $this->result_search($post) );
            break;

            case $this->ACTION_LOAD_block:
                if ($this->user_can_edit){
                    global $DB;
                    $q = 'SELECT * FROM '.$DB->table('document_blocks').' WHERE id_document_data=? AND blockname=? AND id_block=?';
                    $line = $DB->prepared_query_line($q, 'isi', [$post[$this->ifld_data_id], $post['block_name'], $post['block_id']]);
                    $master_output->add_child( $this->add_block_control($line) );
                }
            break;
            case $this->ACTION_SAVE_block:
                if ($this->user_can_edit){
                    $master_output->add_child( $this->save_block($post) );
                }
            break;
            case $this->ACTION_DELETE_block:
                if ($this->user_can_edit){
                    $master_output->add_child($this->delete_block($post));
                }
            break;
            case $this->ACTION_COPY_block:
                if ($this->user_can_edit){
                    $nblock = $this->copy_block($post['block_name'], $post['block_id'], $post['sort_order'], $post['document_data_id']) ;
                    $master_output->add_child(json_encode($nblock));
                }
            break;

            case $this->ACTION_ANIMATION_block:
                if ($this->user_can_edit){
                    $master_output->add_child($this->edit_anim_block($post));
                }
            break;
            case $this->ACTION_SAVE_ANIMATION_block:
                if ($this->user_can_edit){
                    $master_output->add_child($this->save_animation_block($post));
                    $master_output->add_child($this->edit_anim_block($post));
                }
            break;

            case 'clear':
                // reset post but keep important data
                $t = [
                    'posted_from_container'=>$post['posted_from_container'],
                    'dom_id'=>$post['dom_id']
                ];
                $post = $t;
            break;

            default:
                $this->reset_fields($post, 'document_data');
                $post['defaultmode'] = true;
                $master_output->add_child( $this->module_header($post) );

                $master_output->add_child( $this->form_search($post) );
                $master_output->add_child( $this->result_search($post) );
            
            break;
        }
        
        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    
    }

    public function display_tabs($post){

        // $params = ['dom_id'=>$this->dom_target];
        $params = ['class'=>$this->css.'tabs', 'dom_id'=>$this->dom_target];

        $labels = [
            $this->get_tl('tl_content'),
            $this->get_tl('tl_preview'),
            $this->get_tl('tl_properties'),
            $this->get_tl('tl_css'),
            $this->get_tl('tl_publish')
        ];

        $contents = [
            $this->edit_document_data($post),
            $this->document_preview($post),
            $this->edit_document_properties($post),
            $this->form_css_edit($post),
            $this->form_publish($post)
        ];

        $tabs = H::tabs($params, $labels, $contents);

        return $tabs;
    }

    public function copy_document_data($post){
        global $DB, $USER, $MEDIA;

        // Copy data object
        $q = 'INSERT INTO '.$DB->table('document_data').' (route,creation_date,publication_date,ismodele,modele,id_user_data) SELECT route,creation_date,publication_date,?,modele,? FROM '.$DB->table('document_data').' WHERE id=?';
        $res = $DB->prepared_query($q,'iii',[0,$USER->id,$post['document_data-id']]);
        $new_id = $DB->last_insert_id();

        // Copy traduction from languages_long and languages_short
        $q = 'INSERT INTO '.$DB->table('languages_long').' (id_data,id_item,field_identifier,value) SELECT id_data,?,field_identifier,value FROM '.$DB->table('languages_long').' WHERE id_item=? AND field_identifier LIKE "document_data-%"';
        $res = $DB->prepared_query($q,'ii',[$new_id,$post['document_data-id']]);
        $q = 'INSERT INTO '.$DB->table('languages_short').' (id_data,id_item,field_identifier,value) SELECT id_data,?,field_identifier,value FROM '.$DB->table('languages_short').' WHERE id_item=? AND field_identifier LIKE "document_data-%"';
        $res = $DB->prepared_query($q,'ii',[$new_id,$post['document_data-id']]);

        // Change title of the new page to add -COPY
        $q = 'UPDATE '.$DB->table('languages_short').' SET value=CONCAT(value,\'-COPY\') WHERE id_item=? AND field_identifier=?';
        $res = $DB->prepared_query($q,'is',[$new_id, 'document_data-label']);

        // Copy blocks
        $blocks = $DB->prepared_query_list('SELECT * FROM '.$DB->table('document_blocks').' WHERE id_document_data=?', 'i', [$post['document_data-id']]);
        $block_ids_to_replace = [];
        foreach ($blocks as $key => $line) {
            $new_block = $this->copy_block($line['blockname'], $line['id_block'], $line['sort_order'], $new_id);
            $block_ids_to_replace[$line['blockname'].'_'.$line['id_block']] = $new_block['block_name'].'_'.$new_block['id_block'];
        }

        // Copy css
        \helPHP\modules\csseditor\admin\Csseditor::duplicate_source('document', $post['document_data-id'], $new_id);
        // Modifying blocks selectors to correspond to the new id
        $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type=?';
        $sources = $DB->prepared_query_list($q, 's', ['document¤'.$new_id]);
        foreach($sources as $source){
            // copy rules
            $q = 'SELECT DISTINCT * FROM '.$DB->table('csseditor_rules').' WHERE id_source=?';
            $rules = $DB->prepared_query_list($q,'i',[$source['id']]);
            foreach ($rules as $key => $line) {
                foreach($block_ids_to_replace as $old_block_id=>$new_block_id){
                    $q = 'update '.$DB->table('csseditor_rules').' set selector=replace(selector, \''.$old_block_id.'\', \''.$new_block_id.'\') WHERE id='.$line['id'];
                    $DB->query($q);
                }
                
            }
        }

        return $new_id;
    }

    public function edit_anim_block($post){
        global $DB;
          // $document_data_id,$block_id;
        //check if block got animation
        if (isset($post['document_id']) && isset($post['block_name_id']) && $post['block_name_id'] !=''){
            $q='SELECT DISTINCT id_animation from '.$DB->table('block_animation').' where id_document=? AND block_id =?';
            $id=$DB->prepared_query_value($q,'is',[$post['document_id'],$post['block_name_id']]);
            
        }else{
            $id=0;
        }
        $mode=($id==0 || $id =='' || $id==false)? H::input_hidden(['name'=>'mode','value'=>'insert']):H::input_hidden(['name'=>'mode','value'=>'update']);
        $post['block_animation'] = array_search($id, array_column($this->prepared_anim, "id"));

        
        $output = H::DIV(['id'=>'animation_edition'.$this->dom_id, 'class'=>$this->css.'subcontainer blockanimation']);
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_edit form_edit']);
                // $selected = isset($post['block_animation']) ? $post['block_animation'] : 0;
                $opts_data = array('value_key'=>'id', 'label_key'=>'name', 'options'=>$this->prepared_anim);
                $select = H::select(['id'=>self::module_name.'_block_anime'.$this->dom_id, 'name'=>'block_animation', 'label'=>$this->get_tl('block_animation'), 'data-alwaysposted'=>1], $opts_data, $post['block_animation']);
                $btn_save = H::submit_button(['id'=>self::module_name.'_btn_save'.$this->dom_id, 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_ANIMATION_block], $this->get_tl('tlc_save'));
                $document_id=H::input_hidden(['name'=>'document_id','value'=>$post['document_id']]);
                $block_name_id=H::input_hidden(['name'=>'block_name_id','value'=>$post['block_name_id']]);
                $form->add_child([$select->label_tag(),$select,$document_id,$mode,$block_name_id,$btn_save]);
        $output->add_child($form);
        return $output;
    }
    public function save_animation_block($post){
        global $DB;
        if ($post['block_animation']==0){
                $q='DELETE FROM '.$DB->table('block_animation').' where id_document=? AND block_id =?';
                $DB->prepared_query($q,'is',[$post['document_id'],$post['block_name_id']]);
        }else{
            if ($post['mode']=='update'){
                $q='UPDATE '.$DB->table('block_animation').' set id_animation=? where id_document=? AND block_id =?';
                $DB->prepared_query($q,'iis',[$post['block_animation'],$post['document_id'],$post['block_name_id']]);
            }else{
                    $q='INSERT INTO '.$DB->table('block_animation').' set id_animation=?, id_document=?, block_id =?';
                    $DB->prepared_query($q,'iis',[$post['block_animation'],$post['document_id'],$post['block_name_id']]);
            }
        }
    }

    public function copy_block($blockname, $id_block, $sort_order, $document_id){
        global $DB, $MEDIA;
        
        // Copy block entry
        $q = "CREATE TEMPORARY TABLE ".$DB->table('block_'.$blockname)."_copy as SELECT * FROM ".$DB->table('block_'.$blockname)." WHERE id=?";
        $res = $DB->prepared_query($q,'i',[$id_block]);

        // Change entry id
        $q = 'ALTER TABLE '.$DB->table('block_'.$blockname).'_copy DROP id';
        $res = $DB->query($q);

        // Insert the new entry
        $q = 'INSERT INTO '.$DB->table('block_'.$blockname).' SELECT NULL,'.$DB->table('block_'.$blockname).'_copy.* FROM '.$DB->table('block_'.$blockname).'_copy'; 
        $res = $DB->query($q);
        // Get the new id
        $new_id_block = $DB->last_insert_id();

        // Delete temporary table
        $q = 'DROP TABLE '.$DB->table('block_'.$blockname).'_copy';
        $res = $DB->query($q);

        // Copy traduction data if any
        $q = 'INSERT INTO '.$DB->table('languages_long').' (id_data, id_item, field_identifier, value) SELECT id_data, ?, field_identifier, value FROM '.$DB->table('languages_long').' WHERE id_item=? AND field_identifier LIKE "block_'.$blockname.'-%"';
        $res = $DB->prepared_query($q, 'ii', [$new_id_block, $id_block]);
        $q = 'INSERT INTO '.$DB->table('languages_short').' (id_data, id_item, field_identifier, value) SELECT id_data, ?, field_identifier, value FROM '.$DB->table('languages_short').' WHERE id_item=? AND field_identifier LIKE "block_'.$blockname.'-%"';
        $res = $DB->prepared_query($q, 'ii', [$new_id_block, $id_block]);

        // Retrieve media if any to copy them
        $q = 'SELECT * FROM '.$DB->table('media_use').' WHERE field_identifier LIKE "block_'.str_replace('¤','-',$blockname).'_%¤'.$id_block.'"';
        $res = $DB->query_list($q);
        if ($res){
            foreach ($res as $media) {
                // copy media
                $new_field_identifier = str_replace('¤'.$id_block, '¤'.$new_id_block, $media['field_identifier']);
                $MEDIA->copy_use($media['field_identifier'], $new_field_identifier);
            }
        }
        
        // Insert entry in document block table
        $q = 'INSERT INTO '.$DB->table('document_blocks').' (id_document_data, id_block, blockname, sort_order) VALUES (?, ?, ?, ?)';
        $res = $DB->prepared_query($q, 'iisi', [$document_id, $new_id_block, $blockname, $sort_order]);
        $block_infos = [
            'id_block' => $new_id_block,
            'block_name' => $blockname,
        ];

        // Copy css
        $q = 'INSERT INTO '.$DB->table('csseditor_rules').' (id_source, admin, id_media, selector, properties, sort_order, active)';
        $q.=' SELECT id_source, admin, id_media, ?, properties, sort_order, active FROM '.$DB->table('csseditor_rules');
        $q.=' WHERE selector LIKE ? AND id_source=(SELECT id FROM '.$DB->table('csseditor_source').' WHERE type=?)';
        $DB->prepared_query($q, 'sss', ['#block_'.$blockname.'_'.$new_id_block, '#block_'.$blockname.'_'.$id_block, 'document¤'.$document_id]);

        // Copy animation
        $q = 'INSERT INTO '.$DB->table('block_animation').' (id_document, block_id, id_animation) SELECT ?, ?, id_animation';
        $q.=' FROM '.$DB->table('block_animation').' WHERE block_id=?';
        $DB->prepared_query($q, 'iss', [$document_id, $blockname.'_'.$new_id_block, $blockname.'_'.$id_block]);

        return $block_infos;
    }

    public function save_blocks_order($post){
        global $DB;
        if (isset($post['blocks_order'])){
            $blocks_order = json_decode(stripslashes($post['blocks_order']));
            foreach ($blocks_order as $id=>$value) {
                $q = 'UPDATE '.$DB->table('document_blocks').' SET sort_order=? WHERE id=?';
                $res = $DB->prepared_query($q, 'ii', [$value, $id]);
            }
        }
    }

    public function check_modele_exists($modele){
        global $DB;
        $q = 'SELECT id FROM '.$DB->table('document_data').' WHERE modele=? AND ismodele=1';
        $res = $DB->prepared_query_value($q, 's', [$modele]);
        return $res;
    }
    public function module_header ($post) {

        $output = H::group('add_document');
        
        if ($this->user_can_edit){
            $formc = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_search']);
                $actions = H::DIV(['class'=>$this->css.'action_buttons action_buttons']);
                if ($this->mmode){
                    $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('editingof').' : '.$this->mmode);
                    //  $button_new = H::BUTTON(['class'=>$this->css.'btn_new', 'title'=>$this->get_tl('tlc_new'), 'onclick'=>'H_search.modal_edit(0, "'.self::module_name.'", "data", "new-'.$this->mmode.'", "'.$this->dom_id.'")'], $this->get_tl('tlc_new'));
                    $button_new = H::submit_button_single(['class'=>$this->css.'btn_new button_new', 'title'=>$this->get_tl('tlc_new'), 'name'=>$this->input_action_identifier , 'id'=>self::module_name.'_btn_new_'.$this->dom_id, 'value'=>$this->ACTION_NEW_document_data.'-'.$this->mmode], $this->get_tl('tlc_new'));
                    $actions->add_child( $button_new );
                }   else {
                    $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('module_name'));
                    $button_new = H::submit_button_single(['class'=>$this->css.'btn_new button_new', 'title'=>$this->get_tl('tlc_new'), 'name'=>$this->input_action_identifier , 'id'=>self::module_name.'_btn_new_'.$this->dom_id, 'value'=>$this->ACTION_NEW_document_data], $this->get_tl('tlc_new'));  
                    //  $button_new = H::BUTTON(['class'=>$this->css.'btn_new', 'title'=>$this->get_tl('tlc_new'), 'onclick'=>'H_search.modal_edit(0, "'.self::module_name.'", "data", "new", "'.$this->dom_id.'")'], $this->get_tl('tlc_new'));
                    $actions->add_child( $button_new );
                }
            $formc->add_child($actions);
            $output->add_child([$title,$formc]);
        }

        return $output;
    }
    //le formulaire d'édition classique :
    public function edit_document_data($post){
        global $LANG,$DB;

        // $output = H::group('edit_module');
        $output = H::DIV(['class'=>$this->css.'subcontainer data']);

        $ttl_content = $LANG::load_short_translation_value($this->ifld_data_title, $post[$this->ifld_data_id]);
        $ttl_content = $ttl_content ? \stripslashes($ttl_content) : '';
        
        $action = H::DIV(['class'=>$this->css.'action_buttons action_buttons module_title']);
            // $title = H::SPAN(array('class'=>$this->css.'title_modal_edit module_title'), $this->get_tl('ttl_edit').' '.$ttl_content);
            // $radios[] = ['label'=>$this->get_tl('smartphone'), 'value'=>'smartphone'];
            // $radios[] = ['label'=>$this->get_tl('tablet'), 'value'=>'tablet'];
            // $radios[] = ['label'=>$this->get_tl('desktop'), 'value'=>'desktop'];
            // $mode = H::input_multiple_radios(['name'=>'mode', 'style'=>'float:right;','values'=>$radios, 'class'=>$this->css.'switch_mode', 'selected'=>'desktop', 'callback'=>$this->inst_js.'.toggle_preview_mode(event);']);
            $copy_button = H::button(['id'=>self::module_name.'_btn_copy_'.$this->dom_id,'class'=>$this->css.'btn_copy hidden', 'onclick'=>$this->inst_js.'.copy_block();'], $this->get_tl('tlc_copy'));
            $reorder_label = H::SPAN(array('class'=>$this->css.'label_reorder'), $this->get_tl('tl_reorder'));
            $reorder=H::input_checkbox(['name'=>'reorder', 'id'=>'reorder'.$this->dom_id,'value'=>'1', 'class'=>$this->css.'reorder', 'checked'=>true, 'onclick'=>$this->inst_js.'.toggle_reorder(event);']);
        $action->add_child([ $reorder_label, $reorder, $copy_button]);
        $output->add_child( $action );

        if( !$this->mmode ){
            $output->add_child( H::DIV(['class'=>$this->css.'blocks_list_document_container', 'id'=>$this->dom_id.'_blocks_list'],$this->blocks_list()) );
            $output->add_child( H::DIV(['class'=>$this->css.'document_canvas_document_container', 'id'=>$this->dom_id.'_document_canvas'],$this->document_canvas($post)) );
        }else{
            $output->add_child( H::DIV(['class'=>$this->css.'document_canvas_document_container', 'id'=>$this->dom_id.'_document_canvas'],$this->document_canvas($post))  );
        }

        $js_params = ['id'=>$post[$this->ifld_data_id]];
        $js = H::script('helphp_timeout(\'Document_a.create_instance("'.$this->dom_id.'",'.addslashes(json_encode($js_params)).');\');', ['autoremove'=>true]);
        $output->add_child( $js );
        
        return $output;

    }
    public function blocks_list(){
        global $DB;
        $output = H::group('blocks_list');
        $btitle = H::SPAN(array('class'=>$this->css.'title_blocks_list'), $this->get_tl('tl_blocks_list'));
        $output->add_child( $btitle );
        // $categ=$this->recurse_tree('category', 'data','name',4);
        $categ = \helPHP\modules\category\admin\Category::get_list('block');
        $cur_block=0;
        foreach ($categ as $key=>$line) {
            $section=H::detail(['class'=>$this->css.'section'], $line['name']);
            $q = 'SELECT block.id, block.name FROM '.$DB->table('block_data').' block, '.$DB->table('category_content').' categ';
            $q.=' WHERE categ.id_data=? AND block.id=categ.id_item AND categ.field_identifier="block" ORDER BY block.name';
            $blocks = $DB->prepared_query_list($q, 'i', [$line['id']]);
            
            foreach ($blocks as $keyb=>$lineb) {
                $name = Language::get_name('block_data', $lineb['id']);
                $block = H::div(['id'=>'dbl_'.$cur_block.'_'.$this->dom_id, 'class'=>$this->css.'btn_block block_drag_'.$this->dom_id, 'title'=>$name, 'data-block_type'=>$lineb['name']], $name);
                $cur_block++;
                $section->add_child($block);
            }
            $output->add_child( $section );
        }

        return $output;
    }
    public function document_canvas($post){
        global $CONFIG,$DB;
        
        //loading des block
        $output = H::group('document_blocks');

            $canvas = H::DIV(['class'=>$this->css.'over_canvas', 'id'=>$this->dom_id.'_over_canvas']);

            // add publics css in a scope for the canvas
            $str_css = '@scope (.'.$this->css.'over_canvas) {';
            $css_theme = \helPHP\modules\csseditor\admin\Csseditor::get_css($CONFIG::THEME_ID);
            $css_theme = \str_replace(':root', '.document_canvas_form', $css_theme);
            // add theme
            $str_css.= $css_theme;

        $output->add_child($canvas);

            // $main = H::DIV(['id'=>'lemain'.$this->dom_id, 'class'=>'le_main']);

                $form = H::DIV(['id'=>'form_canvas'.$this->dom_id, 'class'=>'document_canvas_form document_container']);
                
                $q = 'SELECT doc.*, blo.id as id_block_data FROM '.$DB->table('document_blocks').' doc';
                $q.=' LEFT JOIN '.$DB->table('block_data').' blo ON (blo.name=doc.blockname) WHERE doc.id_document_data=? ORDER BY doc.sort_order';
                $list = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_data_id]]);
                if ($list){
                    $script_loaded = [];
                    $css_loaded = [];
                    foreach ($list as $key=>$line) {
                        //ne pas passer par block_bridge, gros souci avec confusion de var postées
                        $block = \helPHP\modules\block\Bridge::load(self::module_name, [
                            $this->ifld_data_id => $post[$this->ifld_data_id],
                            // $this->ifld_blocks_id => $line['id']
                        ], $line['blockname'], $line['id_block'], $this->dom_id);

                        $form->add_child( $block );

                        if(!isset($script_loaded[$line['blockname']])) {
                            $js = $DB->prepared_query_value('SELECT jspublic FROM '.$DB->table('block_data').' WHERE name=?', 's', [$line['blockname']]);
                            $script_loaded[$line['blockname']] = $js;
                            if($script_loaded[$line['blockname']] != ''){
                                $js = H::script($script_loaded[$line['blockname']], ['autoremove'=>true]);
                                $form->add_child( $js );
                            }
                        }
                        
                        if(!isset($css_loaded[$line['blockname']])) {
                            $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('block', $line['id_block_data']);
                            $css_loaded[$line['blockname']] = true;
                            if ($css != '') {
                                $str_css.= $css;
                            }
                            // $css_loaded[$line['blockname']] = $css;
                            // if($css_loaded[$line['blockname']] != ''){
                            //     $css = H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css'), $css);
                            //     $form->add_child( $css );
                            // }
                        }

                        $form->add_child( $this->add_block_control($line) );
                    }
                }
        
        // $main->add_child($form);
        // $canvas->add_child($main);
        $canvas->add_child($form);

        $path = $CONFIG::HELPHP_FOLDER.'modules/document/public/document.css';
        //for when @scope work in ff
        // $debscope=  '@scope (.'.$this->css.'over_canvas) {';
        if (is_file($path)) {
            $str_css.= file_get_contents($path);
            // $doccss=file_get_contents($path);
            // $doccss = H::STYLE(array('rel'=>'stylesheet', 'type'=>'text/css'), $doccss);
            // $doccss = H::STYLE(array('rel'=>'stylesheet', 'type'=>'text/css'), $debscope.$doccss.'}');
            // $output = $doccss.$output;
        } 

        // if ($CONFIG::THEME_ID > 0) {
        //     $str_css = \helPHP\modules\csseditor\admin\Csseditor::get_css($CONFIG::THEME_ID);
        //     $cssdoc=H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css', 'id'=>'insertedFromDB'), $debscope.$str_css.'}');
        //     $output = $cssdoc.$output;
        // }

        $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('document', $post[$this->ifld_data_id]);
        if($css != ''){
            $str_css.= $css;
            // $css = H::STYLE(array('rel'=>'stylesheet', 'type'=>'text/css', 'id'=>'document_style_'.$post[$this->ifld_data_id].$this->dom_id), $css);
            // $output = $css.$output;
        }
        if ($str_css != ''){
            $str_css.='}';
            $css = H::STYLE(array('rel'=>'stylesheet', 'type'=>'text/css', 'id'=>'document_style_'.$post[$this->ifld_data_id].$this->dom_id), $str_css);
            $output = $css.$output;
        }

        return $output;
    }
    public function add_block_control($data){
        $output = H::DIV(['id'=>'block_'.$data['blockname'].'_'.$data['id_block'].$this->dom_id.'_control_temp', 'data-order_parent'=>'document_block_sort_order['.$data['id'].']']);
        $order = H::input_order(['name'=>'document_block_sort_order['.$data['id'].']', 'value'=>$data['sort_order'], 'class'=>$this->css.'order'],false,'h.modules.document_a[\''.$this->dom_id.'\'].save_block_sort_order');
        //insertion input order and anim control!
        $params = [
            'document_action'=>$this->ACTION_ANIMATION_block,
            'document_id'=>$data['id_document_data'],
            'block_name_id'=>$data['blockname'].'_'.$data['id_block']
        ];
        $btn_anim = H::DIV(['class'=>'block_anim_button','onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($params).');'], H::icon('dribbble'));
        $output->add_child( [$order, $btn_anim] );
        // array_push($output, $order, $btn_anim);
        return $output;
        // $module_content = '<div data-order_parent="document_block_sort_order['.$data_block['id'].']"'.substr($html, 4);
        // $module_content = substr($module_content,0,-6).$order.$btn_anim.'</div>';
        // return $module_content;
    }
    public function document_preview($post){
        global $module_html_content;
        
        $mod_preview = new \helPHP\modules\preview\admin\Preview();

        $t = [];
        $t['core_insert'] = true;
        $t['module'] = 'document';
        $t['id'] = $post['document_data-id'];
        $t['action'] = 'show';
        $t['document_data-id'] = $post['document_data-id'];
        $t['css_source'] = 'document¤'.$post['document_data-id'];
        $mod_preview->process_data($t);
        $mod_preview->publish_output();
        $output = H::DIV(['id'=>'document_preview'.$this->dom_id, 'class'=>$this->css.'subcontainer preview'],$module_html_content['preview']);
        return $output;
    }

    public function edit_document_properties($post){
        global $LANG,$DB;
        
        $output = H::DIV(['id'=>'properties_edition'.$this->dom_id, 'class'=>$this->css.'subcontainer properties']);

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'search_edit_modal_content', 'class'=>$this->css.'form_edit form_edit']);
            
            $form->add_child(H::input_hidden(['name'=>'document_data-id', 'value'=>$post['document_data-id'], 'data-alwaysposted'=>1]));
            $form->add_child(H::input_hidden(['name'=>'document_data-active', 'value'=>$post['document_data-active'], 'data-alwaysposted'=>1]));

                    $name = H::input_text(['name'=>$this->ifld_data_name, 'value'=>$post[$this->ifld_data_name], 'label'=>$this->get_tl('name')]);

                $form->add_child( [$name->label_tag(), $name] );

                    $multiblock = $this->translate_block($post, ['document_data-label','document_data-summary'], 'sl');

                    $creation_date = H::input_date(['name'=>'document_data-creation_date', 'label'=>$this->get_tl('creation_date'), 'value'=>Datetime::mysql_to_html_date($post['document_data-creation_date']), 'class'=>'input_date' , ]);

                $form->add_child( [$multiblock, $creation_date->label_tag(), $creation_date]);
                
                if($this->mmode){
                    $ismodele = H::input_hidden(['name'=>'document_data-ismodele', 'value'=>$post['document_data-ismodele'], 'data-alwaysposted'=>1]);
                    $modele = H::input_hidden(['name'=>'document_data-modele', 'value'=>$this->mmode, 'data-alwaysposted'=>1]);
                    $form->add_child( [$ismodele,$modele]);
                }else{
                    $checked = (isset($post['document_data-ismodele']) && $post['document_data-ismodele'] == 1)?'CHECKED':'data-unchecked';
                    $ismodele = H::input_checkbox(['name'=>'document_data-ismodele', $checked=>$checked, 'value'=>1, 'label'=>$this->get_tl('ismodele'), 'class'=>'input_checkbox'  ]);
                    // $modele = H::input_text(['name'=>'document_data-modele', 'value'=>$post['document_data-modele'], 'label'=>$this->get_tl('modele'), 'class'=>'input_short_text' ]);
                    // $opts = [
                    //     'name'=>'document_data-modele',
                    //     'new_value'=>true,
                    //     'id'=>'document_data-modele'.$this->dom_id,
                    //     'label'=>$this->get_tl('modele'),
                    //     'class'=>$this->css.'precomplete',
                    //     'value'=>$post['document_data-modele'],
                    //     'placeholder'=>$post['document_data-modele']
                    // ];
                    // $modele = H::input_autocomplete($opts, 'document_data', 'modele');
                    $modele = H::input_text(['name'=>'document_data-modele', 'value'=>$post['document_data-modele'], 'class'=>$this->css.'modele', 'label'=>$this->get_tl('modele')]);
                    $form->add_child( [$ismodele->label_tag(), $ismodele, $modele->label_tag(), $modele]);
                }

                if ($post['document_data-id'] > 0){
                    // $post['document_categories-id_document_data'] = $post['document_data-id'];
                    // $categories_container = H::DIV(['class'=>$this->css.'subcontainer_multiple_inline categories', 'id'=>$this->dom_target.'_categories']);
                    // $categories_container->add_child( $this->form_categories($post, true) );
                    // $form->add_child( $categories_container );
                    $widget = \helPHP\modules\category\admin\Category::widget(['series'=>self::module_name], 'document', $post['document_data-id']);
                    $form->add_child( $widget );
                    
                    $widget = \helPHP\modules\group\admin\Group::widget([], 'document', $post['document_data-id']);
                    $form->add_child( $widget );
                }

                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);

                    $btn_save = H::submit_button(['style'=>'display: none;', 'id'=>self::module_name.'_btn_save'.$this->dom_id, 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_document_properties], $this->get_tl('tlc_save'));
                    $fake_btn_save = H::button(['type'=>'button', 'class'=>$this->css.'btn_save_fk button_save', 'onclick'=>'H_search.save("'.self::module_name.'", "'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_save')], $this->get_tl('tlc_save'));

                $block_btns->add_child([$btn_save, $fake_btn_save]);

                if ($post['document_data-id'] > 0) {
                    $btn_delete = H::submit_button(['class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_document_data, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('ask_delete')], $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }
            
            $form->add_child($block_btns);
        
        $output->add_child($form);

        return $output;
    }
    
    //sauve les données.
    public function save_document_data(&$post){
        global $DB, $USER;

        if(!isset($post['document_data-creation_date']) || $post['document_data-creation_date']==''){
            $post['document_data-creation_date'] = Datetime::mysql_date();
        }
        if(!isset($post['document_data-publication_date']) || $post['document_data-publication_date']==''){
            $post['document_data-publication_date'] = Datetime::mysql_date();
        }

        $id_exist = $this->check_modele_exists($post['document_data-modele']);
        if ($id_exist > 0 && $post['document_data-ismodele'] == 1 && $id_exist != $post['document_data-id']){
            // on ne peut pas créer un modèle avec le même nom qu'un modèle existant
            $this->display->add_child(H::alert('error_model_exists'));
            $this->add_error($this->get_tl('error_model_exists'));
            $post['document_data-modele'] = '';
            $post['document_data-ismodele'] = 0;
        }

        if($post['document_data-id'] == 0){
            // création
            $q = 'INSERT INTO '.$DB->table('document_data').' SET name=?, `route`=?,`creation_date`=?, active=?, `ismodele`=?,`modele`=?,`id_user_data`='.$USER->id.'';
            $success = $DB->prepared_query($q,'sssiis',[$post['document_data-name'],$post['document_data-route'],$post['document_data-creation_date'],$post['document_data-active'],$post['document_data-ismodele'],$post['document_data-modele']]);
            return $DB->last_insert_id();
        }else{
            // mise à jour
            $q = 'UPDATE '.$DB->table('document_data').' SET name=?, `route`=?,`creation_date`=?, active=?,`ismodele`=?,`modele`=? where id=?';
            $q.= $this->user_query_part();
            //attention au i du type de l'id qui est pré-inséré
            $success = $DB->prepared_query($q,'sssiisi',[$post['document_data-name'],$post['document_data-route'],$post['document_data-creation_date'],$post['document_data-active'],$post['document_data-ismodele'],$post['document_data-modele'], $post['document_data-id']]);
            return $post['document_data-id'];
        }
    }
    //supprime les données
    public function delete_document_data(&$post) {
        global $DB;
        $q = 'DELETE FROM '.$DB->table('document_data').' WHERE id=?';
            $q.= $this->user_query_part();
        $res = $DB->prepared_query($q, 'i', [$post['document_data-id']]);

        \helPHP\modules\category\admin\Category::delete('document', $post['document_data-id']);
        \helPHP\modules\group\admin\Group::delete('document', $post['document_data-id']);
        \helPHP\modules\csseditor\admin\Csseditor::delete_css_source('document', $post['document_data-id']);

        $q = 'DELETE FROM '.$DB->table('group_content').' WHERE field_identifier LIKE "'.'document_data-id'.'" AND id_item=?';
        $DB->prepared_query($q,'i', [$post['document_data-id']]);

        $this->delete_block($post, true);

    }

    public function form_publish($post){

        $output = H::DIV(['class'=>$this->css.'subcontainer publish']);

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_publish form_edit']);

                $indexation = \helPHP\modules\indexation\admin\Indexation::button(self::module_name, $post['document_data-id']);
            
            $form->add_child($indexation);

                $hidden_id = H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id]]);
                $active = H::input_checkbox(['name'=>$this->ifld_data_active, 'value'=>1, 'checked'=>$post['document_data-active'], 'class'=>'input_checkbox', 'label'=>$this->get_tl('active')]);

            $form->add_child( [$hidden_id, $active->label_tag(), $active] );

                $post['document_data-route'] = $post['document_data-route'] ? $post['document_data-route'] : $post['document_data-name'];
                $route = H::input_text(['name'=>'document_data-route', 'value'=>$post['document_data-route'], 'label'=>$this->get_tl('route'), 'class'=>'input_short_text', 'data-required'=>1]);

            $form->add_child( [$route->label_tag(), $route] );

                $published = $post[$this->ifld_data_publication_date] ? true : false;
                $post[$this->ifld_data_publication_date] = $published ? $post[$this->ifld_data_publication_date] : date('Y-m-d H:i:s');
                $publication_date = H::input_date(['name'=>$this->ifld_data_publication_date, 'label'=>$this->get_tl('publication_date'), 'value'=>Datetime::mysql_to_html_date($post['document_data-publication_date']), 'class'=>'input_date', 'data-required'=>1]);

                $btn_publish = H::submit_button(['class'=>$this->css.'btn_publish', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_PUBLISH, 'title'=>$this->get_tl('tl_publish')], $this->get_tl('tl_publish'));

            $form->add_child( [$publication_date->label_tag(), $publication_date, $btn_publish] );

        $output->add_child( $form );

        return $output;
    }
    public function publish_document($post){
        //really simitar to public one, execpt we'll write the cached file.
        global $CONFIG,$DB,$LANG,$FS;

        if(isset($post['document'])){
            $post['document_data-id'] = $post['document'];
        }

        // check if the route doesn't exist
        $q = 'SELECT id FROM '.$this->bddt_data.' WHERE route = ? AND id <> ?';
        $exist = $DB->prepared_query_value($q, 'si', [$post[$this->ifld_data_route], $post[$this->ifld_data_id]]);
        if ($exist) {
            $this->add_error('route_exist');
            return;
        }

        if(isset($post['document_data-id']) && $post['document_data-id'] > 0) {

            // update publish date
            $q = 'UPDATE '.$this->bddt_data.' SET publication_date=?, route=?, active=? WHERE id=?';
            $DB->prepared_query($q, 'ssii', [$post['document_data-publication_date'], $post['document_data-route'], $post['document_data-active'], $post['document_data-id']]);
        
            $this->apply_bdd_data($post, 'document_data', false, $post['document_data-id']);
            
        } else {
            return;
        }

        $current_lang = $LANG->current_language;
        $langs = $LANG->get_languages_data();
        foreach ($langs as $key => $lang) {
            $LANG->set_language_iso($lang['iso']);
            
            $output = H::group('show_document');

            $q = 'SELECT dblocks.* , block.id as block_id FROM '.$DB->table('document_blocks').' as dblocks, '.$DB->table('block_data').' as block WHERE dblocks.id_document_data=? and dblocks.blockname = block.name order by sort_order';
            $list = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_data_id]]);
            if ($list) {
                $script_loaded = [];
                $css_loaded = [];
                
                foreach ($list as $key => $line) {
                    $path = $CONFIG::HELPHP_FOLDER.'/modules/block/'.$line['blockname'].'/public/';
                    $path.= ucfirst($line['blockname']).'.php';
                    if (is_file($path)){
                        include_once($path);
                        $_POST = [];
                        $_POST['core_insert'] = true;
                        $_POST['block_'.$line['blockname'].'-id'] = intval($line['id_block']);
                        $moduleb = '\helPHP\modules\block\\'.$line['blockname'].'\public\\'.ucfirst($line['blockname']);
                        $moduleb = new $moduleb();
                        $module_content = $this->parse_hcode($moduleb->process_data($_POST, true));
                        if(!isset($script_loaded[$line['blockname']])) {
                            $js = $DB->prepared_query_value('SELECT jspublic FROM '.$DB->table('block_data').' WHERE name=?', 's', [$line['blockname']]);
                            $script_loaded[$line['blockname']] = $js;
                            if($script_loaded[$line['blockname']] != '') {
                                $js = H::script($script_loaded[$line['blockname']], ['autoremove'=>true]);
                                $module_content = $js.$module_content;
                            }
                        }
                        if(!isset($css_loaded[$line['blockname']])) {
                            $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('block', $line['block_id']);
                            $css_loaded[$line['blockname']] = $css;
                            if($css_loaded[$line['blockname']] != ''){
                                $css = H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css'), $css);
                                $module_content = $css.$module_content;
                            }
                        }
                        $output->add_child($module_content);
                    }
                }
                //animation :
                $animations=$DB->query_list('SELECT DISTINCT * FROM '.$DB->table('block_animation').' where id_document='.$post[$this->ifld_data_id]);
                if (count($animations)>0){
                    $animscript='';
                    foreach($animations as $key => $line){
                        $id_anim = array_search($line['id_animation'], array_column($this->prepared_anim, "id"));
                        $animscript.='helphp_timeout(\'new H_anim({ elements: "#block_'.$line['block_id'].'", '.$this->prepared_anim[$id_anim]['opts'].'});\');';
                    }
                    $anim_script=H::script($animscript,['autoremove'=>false]);
                    // $anim_script=H::script($animscript,['autoremove'=>true]);
                    $output->add_child($anim_script);
                }
                 //document properties
                $data_display = H::DIV(['class'=>'document_fiche']);
                    $summary_label = H::SPAN(['class'=>'label'], $this->get_tl('summary'));
                    $content = $LANG::load_long_translation_value($this->ifld_data_summary, $post[$this->ifld_data_id]) ?? '';
                    $summary = H::SPAN(['class'=>'disp_longmulti'], stripslashes($content));

                    $title_label = H::SPAN(['class'=>'label'], $this->get_tl('title'));
                    $content = $LANG::load_long_translation_value($this->ifld_data_name, $post[$this->ifld_data_id]) ?? '';
                    $title = H::SPAN(['class'=>'disp_shortmulti'], stripslashes($content));

                $data_display->add_child( [$title_label,$title,$summary_label,$summary] );

                        $creation_date_label = H::SPAN(['class'=>'label'], $this->get_tl('creation_date'));
                        $creation_date = H::SPAN(['class'=>'disp_date'], $post[$this->ifld_data_creation_date]);

                    $data_display->add_child( [$creation_date_label,$creation_date] );

                        $publication_date_label = H::SPAN(['class'=>'label'], $this->get_tl('publication_date'));
                        $publication_date = H::SPAN(['class'=>'disp_date'], $post[$this->ifld_data_publication_date]);

                    $data_display->add_child( [$publication_date_label,$publication_date] );

                $output->add_child($data_display);
                $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('document', $post[$this->ifld_data_id]);
                if ($css != '') {
                    $css = H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css'), $css);
                    $output = $css.$output;
                } else {
                    $output = $output->full_html();
                }
            }

            global $event_lst;
            if ($event_lst){
                $js_str = '';
                foreach($event_lst as $key => $js){
                    $js_str .= $js;
                }
                $output .= '<script>'.$js_str.'</script>';
                $event_lst = [];
            }

            $da_document = '<?php'.PHP_EOL.'$document_cache=';
            $_POST['indexation_data-module_name'] = 'document';
            $_POST['indexation_data-module_param'] = $post[$this->ifld_data_id];
            $_POST['indexation_data-mode'] = 'module';
            $LANG->set_language_iso($lang['iso']);
            $module_index = new Indexation();
            $module_index->process_data($_POST);

            $document_cache = $module_index->HEADERS;
            $document_cache['content'] = $output;
            $document_cache['id'] = $post[$this->ifld_data_id];
            $da_document .= var_export($document_cache, true).PHP_EOL.'?>';
            
            //ajout des JS ET CSS !!!
            $FS->save_content($CONFIG::HOME_FOLDER.'public/document/cache/'.str_replace('/','¤',$post['document_data-route']).'-'.$lang['iso'].'.php', $da_document,true);
        }
        $LANG->set_language_iso($current_lang);
        return "cached";
    }
    
    //sauve les données.
    public function save_block(&$post){
        global $DB;

        $new_block = false;

        $q = 'SELECT DISTINCT id, sort_order FROM '.$DB->table('document_blocks').' WHERE `id_document_data`=? and `blockname`=? and `id_block`=? ';
        $res = $DB->prepared_query_line($q, 'isi', [$post[$this->ifld_data_id], $post['block_name'], $post['block_id']]);
        if (!$res){
            // new block
            $post['last_order'] = (isset($post['last_order']) && intval($post['last_order']) > 0) ? intval($post['last_order']) : 1;
            // update block with order bigger than new order
            $q = 'UPDATE '.$DB->table('document_blocks').' SET sort_order=sort_order+1 WHERE id_document_data=? AND sort_order>=?';
            $DB->prepared_query_value($q, 'ii', [$post[$this->ifld_data_id], $post['last_order']]);
            $q = 'INSERT INTO '.$DB->table('document_blocks').' SET `id_document_data`=?, `blockname`=?, `sort_order`=?, `id_block`=?';
            $DB->prepared_query($q, 'isii', array($post[$this->ifld_data_id], $post['block_name'], $post['last_order'], $post['block_id']));
            $new_block = true;
        }

        // call load block to reload the block with last data
        return H::script($this->inst_js.'.load_block("'.$post['block_name'].'", '.$post['block_id'].', '.$new_block.');', ['autoremove'=>true]);
    }

    //supprime les données
    public function delete_block(&$post, $all_blocks = false) {
        global $DB;
        if ($all_blocks === false){
            if(isset($post['block_id']) && intval($post['block_id']) > 0){
                // comes from the bridge
                $q = 'DELETE FROM '.$DB->table('document_blocks').' WHERE id_block=? and id_document_data=? and blockname=?';
                $res = $DB->prepared_query($q, 'iis', [$post['block_id'], $post[$this->ifld_data_id], $post['block_name']]);

                // delete css
                $q = 'DELETE FROM '.$DB->table('csseditor_rules').' WHERE selector LIKE ? AND id_source=';
                $q.=' (SELECT id FROM '.$DB->table('csseditor_source').' WHERE type=?)';
                $DB->prepared_query($q, 'ss', ['#block_'.$post['block_name'].'_'.$post['block_id'], 'document¤'.$post[$this->ifld_data_id]]);

                return H::script($this->inst_js.'.delete_block("'.$post['block_name'].'", '.$post['block_id'].');', ['autoremove'=>true]);
            }
        } else {
            $q = "SELECT * FROM ".$DB->table('document_blocks')." WHERE id_document_data=?";
            $res = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_data_id]]);
            if (is_array($res) && count($res)>0){
                $post[$this->input_action_identifier] = $this->ACTION_DELETE_block;
                foreach ($res as $key=>$line) {
                    \helPHP\modules\block\Bridge::delete($line['blockname'], $line['id_block']);
                }
            }

            $q = 'DELETE FROM '.$DB->table('document_blocks').' WHERE id_document_data=?';
            $res = $DB->prepared_query($q, 'i', [$post['document_data-id']]);
        }
    }

    //affiche les options de recherche
    public function form_search($post) {
        global $DB;
        
        $output = H::DIV(['class'=>$this->css.'container_search module_search', 'id'=>self::module_name.'_container_search'.$this->dom_id]);
        
            $title = H::DIV(['class'=>$this->css.'search_title search_title'], $this->get_tl('search_title'));

        $output->add_child( $title );
        
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_search form_search']);

                $post['rechlnm'] = (!isset($post['rechlnm'])) ? '': $post['rechlnm'];
                $text = H::input_text(['class'=>$this->css.'input_search search_input default' , 'name'=>'rechlnm', 'id'=>'rechlnm'.$this->dom_id, 'value'=>urldecode($post['rechlnm']), 'placeholder'=>$this->get_tl('search_placeholder'), 'onkeydown'=>'if (event.key == "Enter") recht.makeHash(event);']);

                //nombre résultat par page
                $selected = isset($post['nbr_result']) ? $post['nbr_result'] : $this->results_default_count;
                $results_per_page = [['val'=>12], ['val'=>24], ['val'=>48], ['val'=>96]];
                $opts_data = array('value_key'=>'val', 'label_key'=>'val', 'options'=>$results_per_page);
                $select = H::select(['id'=>self::module_name.'_nbr_result'.$this->dom_id, 'name'=>'nbr_result', 'label'=>$this->get_tl('nbr_res'), 'data-alwaysposted'=>1], $opts_data, $selected);

            $form->add_child( [$text, $select->label_tag(), $select] );

                // some hidden data
                $post['start_index'] = isset($post['start_index'])?intval($post['start_index']):0;
                $post['order_filter'] = isset($post['order_filter'])?$post['order_filter']:'';
                $hidden_index = H::input_hidden(['data-alwaysposted'=>1, 'name'=>'start_index', 'id'=>self::module_name.'_start_index'.$this->dom_id, 'value'=>$post['start_index']]);
                $hidden_filter = H::input_hidden(['data-alwaysposted'=>1, 'name'=>'order_filter', 'id'=>self::module_name.'_order_filter'.$this->dom_id, 'value'=>$post['order_filter']]);
            $form->add_child( [$hidden_index, $hidden_filter] );
            
            // advanced  optionnels fields 
            $advanced_fields = H::detail(['class'=>$this->css.'search_advanced_fields search_advanced_fields'], $this->get_tl('search_advanced_fields'));

                $name = H::input_text(['name'=>'document_data-name', 'label'=>$this->get_tl('name'), 'value'=>$post['document_data-name'], 'class'=>'inp_text']);

            $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$name->label_tag(), $name]) );
            
                $creation_date = H::input_date(['name'=>'document_data-creation_date', 'label'=>$this->get_tl('creation_date'), 'value'=>Datetime::mysql_to_html_date($post['document_data-creation_date']), 'class'=>'inp_date']);

            $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$creation_date->label_tag(), $creation_date]) );
            if ($this->mmode){
                $modele=H::input_hidden(['name'=>'document_data-modele', 'value'=>$this->mmode, 'id'=>'document_data-modele'.$this->dom_id]);
                $advanced_fields->add_child( [$modele] );
            }else{
                $opts = [
                    'name'=>'document_data-modele',
                    'toreturn'=>'text',
                    'id'=>'document_data-modele'.$this->dom_id,
                    'label'=>$this->get_tl('modele'),
                    'class'=>$this->css,
                    'value'=>$post['document_data-modele'],
                    'placeholder'=>$post['document_data-modele'],
                ];
                $modele = H::input_precomplete($opts, 'document_data', 'modele');
                $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$modele->label_tag(), $modele]) );
            }

                $post['document_data-summary']= isset($post['document_data-summary'])?$post['document_data-summary']:'';
                $summary = H::input_text(['name'=>'document_data-summary', 'label'=>$this->get_tl('summary'), 'value'=>$post['document_data-summary'], 'class'=>'inp_short_text']);

            $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$summary->label_tag(), $summary]) );

                $post['document_data-name']= isset($post['document_data-name'])?$post['document_data-name']:'';
                $title = H::input_text(['name'=>'document_data-name', 'label'=>$this->get_tl('title'), 'value'=>$post['document_data-name'], 'class'=>'inp_short_text']);

            $advanced_fields->add_child( H::DIV(['class'=>$this->css.'field_box field_box'], [$title->label_tag(), $title]) );

            $btn_clear = H::submit_button(['class'=>$this->css.'btn_clear', 'name'=>$this->input_action_identifier, 'id'=>self::module_name.'_btn_clear'.$this->dom_id, 'value'=>'clear', 'title'=>$this->get_tl('tlc_clear')], $this->get_tl('tlc_clear'));
            $btn_search = H::submit_button(['class'=>$this->css.'btn_search', 'name'=>$this->input_action_identifier, 'id'=>self::module_name.'_btn_search'.$this->dom_id , 'value'=>$this->ACTION_SEARCH, 'title'=>$this->get_tl('tlc_search')], $this->get_tl('tlc_search'));
            
        $form->add_child([$advanced_fields,$btn_clear,$btn_search]);
        
        $output->add_child( $form );

        return $output;
    }
    

    //process et affiche le résultat de la recherche
    public function result_search(&$post){
        global $DB, $CONFIG_DB, $DB, $LANG, $CRYPT;
        $db_data = $CONFIG_DB::DB_TABLE_PREFIX.'_document_data';
        $db_lang_long = $DB->table('languages_long');
        $db_lang_short = $DB->table('languages_short');
        
        $query_params_types = '';
        $query_values = array();
        $query_conditions = '';
        $post['defaultmode'] = true;

        $post['nbr_result'] = isset($post['nbr_result']) ? intval($post['nbr_result']) : $this->results_default_count;
        $post['start_index'] = isset($post['start_index']) ? intval($post['start_index']) : 0;
        $post['page_limit'] = (isset($post['page_limit'])) ? $post['page_limit'] : $this->results_default_count;
        if( $post['nbr_result'] != $post['page_limit']){
            $post['page_limit']= $post['nbr_result'];
        }
        
        //preparation de la recherche basique sur le name avec séparation par espace
        if (!isset($post['rechlnm']) || $post['rechlnm'] == '') {
            $search_string = '';
            $post['defaultmode'] = true;
        }else{
            $search_string = $post['rechlnm'];
            unset($post['defaultmode']);
        }

        //secu et préparation du champ de recherche fulltext:
        $search_string = str_replace('%', '', $search_string);
        $s = trim($search_string);
        $s = explode(' ', $s);
        //on rend les DIVers mots obligatoires...
        $fulltext_string='';
        foreach ($s as $word) {
            $word = trim($word);
            if ($word != '' && strlen($word) > 1) {
                $fulltext_string.= ' +'.$word;
            }
        }
        $search_string = trim(addslashes($fulltext_string));
        
        if (isset($post['order_filter']) && $post['order_filter'] != ''){
            $order_filter = $db_data.'.'.$CRYPT->decrypt(substr($post['order_filter'], 0, -2));
            $sens = (substr($post['order_filter'], -1) == 'a') ? ' ASC' : ' DESC';
        } else {
            $order_filter = 'score';
            $sens = ' DESC';
        }
        
        if (isset($post['document_data-name']) && $post['document_data-name'] != '') {
            unset($post['defaultmode']);
        }
        if (isset($post['document_data-creation_date']) && $post['document_data-creation_date'] != '') {
            unset($post['defaultmode']);
        }
        if (isset($post['document_data-modele']) && $post['document_data-modele'] != '') {
            unset($post['defaultmode']);
        }
        if (isset($post['document_data-summary']) && $post['document_data-summary'] != '') {
            unset($post['defaultmode']);
        }
        if (isset($post['document_data-name']) && $post['document_data-name'] != '') {
            unset($post['defaultmode']);
        }

        
        if (!isset($post['defaultmode'])){
            //liste des fields sur lesquels ont peut faire une recherche fulltext non multilingue
            $text_fields = $db_data.'.modele';
            
            //query principale
    
            if ($search_string != ''){
                $q='(SELECT DISTINCT SQL_CALC_FOUND_ROWS '.$db_data.'.id as id, COUNT(MATCH('.$text_fields.', '.$db_lang_short.'.value, '.$db_lang_long.'.value) AGAINST(? in boolean MODE)) as score ';
                $query_params_types .= 's';
                array_push($query_values, $search_string);
            } else {
                $q='(SELECT DISTINCT SQL_CALC_FOUND_ROWS '.$db_data.'.id as id, 1 as score ';
            }
            
            
            //tables
            $q.='FROM '.$db_data.','.$db_lang_short.','.$db_lang_long;
            // extra tables, languages or association. Important to left join them to be able to order by their value
            if ($order_filter == $db_data.'.summary'){
                $q.=' LEFT JOIN '.$db_lang_long.' ON ('.$db_lang_long.'.id_item='.$db_data.'.id AND '.$db_lang_long.'.id_data=? AND '.$db_lang_long.'.field_identifier="document_data-summary")';
                $query_params_types .= 'i';
                array_push($query_values, $LANG->current_id_data);
                $order_filter = $db_lang_long.'.value';
            }
            if ($order_filter == $db_data.'.title'){
                $q.=' LEFT JOIN '.$db_lang_short.' ON ('.$db_lang_short.'.id_item='.$db_data.'.id AND '.$db_lang_short.'.id_data=? AND '.$db_lang_short.'.field_identifier="document_data-label")';
                $query_params_types .= 'i';
                array_push($query_values, $LANG->current_id_data);
                $order_filter = $db_lang_short.'.value';
            }

            if ($search_string != ''){
                //recherche valeur dans les champs
                $q.=' WHERE (MATCH('.$text_fields.') AGAINST(? in boolean MODE) ';
                
                $query_params_types .= 's';
                array_push($query_values, $search_string);
            
                $q.= ' OR (MATCH('.$db_lang_long.'.value) AGAINST (? in boolean MODE) AND '.$db_lang_long.'.id_data=? AND ('.$db_lang_long.'.field_identifier="document_data-summary") AND '.$db_lang_long.'.id_item='.$db_data.'.id)';
                $query_params_types .= 'si';
                array_push($query_values, $search_string, $LANG->current_id_data);

                $q.= ' OR (MATCH('.$db_lang_short.'.value) AGAINST (? in boolean MODE) AND '.$db_lang_short.'.id_data=? AND ('.$db_lang_short.'.field_identifier="document_data-label") AND '.$db_lang_short.'.id_item='.$db_data.'.id)';
                $query_params_types .= 'si';
                array_push($query_values, $search_string, $LANG->current_id_data);


                $q.= ')';
            } else {
                $q.=' WHERE ';
            }
    
                if (isset($post['document_data-name']) && $post['document_data-name'] != '') {
                    $query_params_types .= 's';
                    $query_conditions .=' AND '.$db_data.'.name LIKE ?';
                    array_push($query_values, '%'.$post['document_data-name'].'%');
                }
                if (isset($post['document_data-creation_date']) && $post['document_data-creation_date'] != '') {
                    $query_params_types .= 's';
                    $query_conditions .=' AND '.$db_data.'.creation_date = ?';
                    array_push($query_values, $post['document_data-creation_date']);
                }
                if (isset($post['document_data-modele']) && $post['document_data-modele'] != '') {
                    $query_params_types .= 's';
                    $query_conditions .=' AND '.$db_data.'.modele LIKE ?';
                    array_push($query_values, '%'.$post['document_data-modele'].'%');
                }
                if (isset($post['document_data-summary']) && $post['document_data-summary'] != '') {
                    $query_conditions.= ' AND ('.$db_lang_long.'.value LIKE ? AND '.$db_lang_long.'.field_identifier="document_data-summary" AND '.$db_lang_long.'.id_item='.$db_data.'.id AND '.$db_lang_long.'.id_data=?)';
                    $query_params_types.= 'si';
                    array_push($query_values, '%'.$post['document_data-summary'].'%',$LANG->current_id_data);
                }
                if (isset($post['document_data-name']) && $post['document_data-name'] != '') {
                    $query_conditions.= ' AND ('.$db_lang_short.'.value LIKE ? AND '.$db_lang_short.'.field_identifier="document_data-label" AND '.$db_lang_short.'.id_item='.$db_data.'.id AND '.$db_lang_short.'.id_data=?)';
                    $query_params_types.= 'si';
                    array_push($query_values, '%'.$post['document_data-name'].'%',$LANG->current_id_data);
                }

            // clean first AND/OR if no text given
            if (str_ends_with($q, 'WHERE ')){
                if (str_starts_with($query_conditions, ' OR ')) $query_conditions = substr($query_conditions, 4);
                if (str_starts_with($query_conditions, ' AND ')) $query_conditions = substr($query_conditions, 5);
            }
            $q .= $query_conditions;
            $q.=' GROUP BY id )';
            
    
            //finalisation query :
            $q.= ' ORDER BY '.$order_filter.$sens.' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
            $results = $DB->prepared_query_list($q,$query_params_types,$query_values);
        }
        
        if (isset($post['defaultmode'])) {
            $q='SELECT DISTINCT SQL_CALC_FOUND_ROWS '.$db_data.'.id as id, 1 as score FROM '.$db_data;
            if ($order_filter == $db_data.'.summary'){
                $q.=' LEFT JOIN '.$db_lang_long.' ON ('.$db_lang_long.'.id_item='.$db_data.'.id AND '.$db_lang_long.'.id_data=? AND '.$db_lang_long.'.field_identifier="document_data-summary")';
                $query_params_types .= 'i';
                array_push($query_values, $LANG->current_id_data);
                $order_filter = $db_lang_long.'.value';
            }
            if ($order_filter == $db_data.'.title'){
                $q.=' LEFT JOIN '.$db_lang_short.' ON ('.$db_lang_short.'.id_item='.$db_data.'.id AND '.$db_lang_short.'.id_data=? AND '.$db_lang_short.'.field_identifier="document_data-label")';
                $query_params_types .= 'i';
                array_push($query_values, $LANG->current_id_data);
                $order_filter = $db_lang_short.'.value';
            }

            if ($this->mmode){
                $q.=' WHERE '.$db_data.'.modele=? AND ismodele=0';
                $query_params_types = 's';
                array_push($query_values, $this->mmode);
            }

            $q.=' ORDER BY '.$order_filter.$sens.' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
            $results = $DB->prepared_query($q, $query_params_types, $query_values);
        }
        
        if (is_array($results)) {
            $mergedresult=[];
            foreach ($results as $index => $line) {
                if (array_key_exists($line['id'], $mergedresult)) {
                    $mergedresult[$line['id']]['score'] += $line['score'];
                } else {
                    $mergedresult[$line['id']] = $line;
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
    
    public function display_search_result(&$post) {
        global $CONFIG_DB, $DB, $LANG, $CRYPT;
        
        // $sub_container_id = 'recherche_resultat_public_container';
        
        $db_data = $CONFIG_DB::DB_TABLE_PREFIX.'_document_data';
        
        $output = H::group('result_search');
        $pages = $post['pages'];
        if ($pages['page_count'] > 1) {
            // $form_pages = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$sub_container_id, 'class'=>$this->css.'form_pages form_pages']);
            $pages_display = H::DIV(['class'=>$this->css.'search_pages search_pages']);

            $params = [];
            if (isset($post['rechlnm']) && $post['rechlnm']!='' ) {
                $params['rechlnm'] = $post['rechlnm'];
            }
            //$params['nbr_result'] = $post['nbr_result'];
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
            $id = H::TH(['class'=>$this->css.'search_result_item search_result_item entete id '.($order_filter=='id'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('id').($order_filter=='id'?$order_sens:'.-a"').',"'.self::module_name.'", "'.$this->dom_id.'");'], $this->get_tl('id'));

            $data_display->add_child( $id );    
                
                $name = H::TH(['class'=>$this->css.'search_result_item search_result_item entete name '.($order_filter=='name'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('name').($order_filter=='name'?$order_sens:'.-a"').',"'.self::module_name.'", "'.$this->dom_id.'");'], $this->get_tl('name'));

            $data_display->add_child( $name );

                $creation_date = H::TH(['class'=>$this->css.'search_result_item search_result_item entete creation_date '.($order_filter=='creation_date'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('creation_date').($order_filter=='creation_date'?$order_sens:'.-a"').',"'.self::module_name.'", "'.$this->dom_id.'");'], $this->get_tl('creation_date'));

            $data_display->add_child( $creation_date );

                $modele = H::TH(['class'=>$this->css.'search_result_item search_result_item entete modele '.($order_filter=='modele'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('modele').($order_filter=='modele'?$order_sens:'.-a"').',"'.self::module_name.'", "'.$this->dom_id.'");'], $this->get_tl('modele'));

            $data_display->add_child( $modele );

                $summary= H::TH(['class'=>$this->css.'search_result_item search_result_item entete summary '.($order_filter=='summary'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('summary').($order_filter=='summary'?$order_sens:'.-a"').',"'.self::module_name.'", "'.$this->dom_id.'");'], $this->get_tl('summary'));

            $data_display->add_child( $summary );

                $title= H::TH(['class'=>$this->css.'search_result_item search_result_item entete title '.($order_filter=='title'?($order_sens=='.-a"'?'filteron a':'filteron d'):'filteroff'),'onclick'=>'H_search.filter("'.$CRYPT->encrypt('title').($order_filter=='title'?$order_sens:'.-a"').',"'.self::module_name.'", "'.$this->dom_id.'");'], $this->get_tl('title'));

            $data_display->add_child( $title );

            $data_display->add_child( [H::th(['class'=>$this->css.'search_result_item search_result_item entete buttons'])] );
            $tbody->add_child($data_display);
            foreach ($post['resultats'] as $index=>$line) {
                $line_data = false;
                $qres = 'SELECT * FROM '.$db_data.' WHERE id='.$line['id'];
                $line_data = $DB->query_line($qres);
                $data_display = H::TR(['class'=>$this->css.'search_result_row search_result_row','id'=>self::module_name.'search_result_row-'.$line['id'].$this->dom_id]);
                $id = H::TD(['class'=>$this->css.'search_result_item id search_result_item'], $line_data['id']);

            $data_display->add_child( $id );    
                    $name = H::TD(['class'=>$this->css.'search_result_item name search_result_item'], $line_data['name']);

            $data_display->add_child( $name );

                    $creation_date = H::TD(['class'=>$this->css.'search_result_item search_result_item creation_date'], $line_data['creation_date']);

            $data_display->add_child( $creation_date );

                    $modele = H::TD(['class'=>$this->css.'search_result_item search_result_item modele'], $line_data['modele']);

            $data_display->add_child( $modele );

                    $post['document_data-summary'] = Language::load_long_translation_value('document_data-summary', $line['id']);
                    $summary= H::TD(['class'=>$this->css.'search_result_item search_result_item summary'], $post['document_data-summary']);

            $data_display->add_child( $summary );

                    $post['document_data-label'] = Language::load_short_translation_value('document_data-label', $line['id']);
                    $title= H::TD(['class'=>$this->css.'search_result_item search_result_item title'], $post['document_data-label']);

            $data_display->add_child( $title );

                $buttons = H::TD(['class'=>$this->css.'search_result_item search_result_item buttons']);

                    if($this->mmode){

                        $btn_edit = H::button_icon('edit-2', ['class'=>$this->css.'btn_edit button_edit', 'onclick'=>'H_search.modal_edit('.$line['id'].', "'.self::module_name.'", "data", "edit-mmode", "'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_edit')]);
                        // $btn_edit = H::BUTTON(['class'=>$this->css.'btn_edit button_edit', 'onclick'=>'H_search.modal_edit('.$line['id'].', "'.self::module_name.'", "data", "edit-mmode","'.$this->dom_id.'");'], $this->get_tl('tlc_edit'));

                        $form_copy = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_copy']);
                            $btn_copy = H::submit_button(['class'=>$this->css.'btn_copy button_copy', 'data-parameters'=>[$this->ifld_data_id=>$line['id'],'modele'=>$this->mmode], 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_COPY_document_data, 'title'=>$this->get_tl('tlc_copy')], H::icon('copy'));
                        $form_copy->add_child($btn_copy);

                    } else {

                        $btn_edit = H::button_icon('edit-2', ['class'=>$this->css.'btn_edit button_edit', 'onclick'=>'H_search.modal_edit('.$line['id'].', "'.self::module_name.'", "data", "edit","'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_edit')]);
                        // $btn_edit = H::BUTTON(['class'=>$this->css.'btn_edit button_edit', 'onclick'=>'H_search.modal_edit('.$line['id'].', "'.self::module_name.'", "data", "edit","'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_edit')], $this->get_tl('tlc_edit'));

                        $form_copy = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_copy']);
                            $btn_copy = H::submit_button_single(array('class'=>$this->css.'btn_copy button_copy', 'data-parameters'=>[$this->ifld_data_id=>$line['id']], 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_COPY_document_data, 'title'=>$this->get_tl('tlc_copy')), H::icon('copy'));
                        $form_copy->add_child($btn_copy);

                    }

                    $form_delete = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'', 'class'=>$this->css.'form_delete form_delete']);
                        $form_delete->add_child(H::input_hidden(['name'=>'document_data-id', 'value'=>$line['id'], 'data-alwaysposted'=>1]));
                        $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier , 'id'=>self::module_name.'_btn_del_'.$line['id'].$this->dom_id, 'value'=>$this->ACTION_DELETE_document_data, 'title'=>$this->get_tl('tlc_del'), 'style'=>'display:none;'), H::icon('trash-2'));
                        $fake_btn_del = H::BUTTON(['type'=>'button', 'class'=>$this->css.'btn_del_fk button_delete', 'onclick'=>'H_search.del(event, "'.self::module_name.'", '.$line['id'].', "'.$this->dom_id.'");', 'title'=>$this->get_tl('tlc_delete'), 'data-confirm'=>$this->get_tl('confirm_delete')], H::icon('trash-2'));
                    $form_delete->add_child( [$btn_delete, $fake_btn_del] );

                $buttons->add_child( [$btn_edit,$form_copy,$form_delete] );
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

    public function form_css_edit($post){

        $css_source = 'document¤'.$post['document_data-id'];

        $div = H::DIV(['id'=>$this->css.'csseditor_container'.$this->dom_id, 'class'=>$this->css.'container_css', 'data-css_source'=>$css_source]);
            
            $css_editor = new \helPHP\modules\csseditor\admin\Csseditor();
            $post = [];
            $post['source'] = $css_source;
            $post['force_admin_or_public'] = true;
            $post['admin'] = false;
            $css_editor->process_data($post);

        $div->add_child( $css_editor->get_output() );

        return $div;
    }
}