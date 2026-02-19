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

namespace helPHP\modules\search\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Media;
use helPHP\libs\Utils;

class Search extends HelPHP_module {

    const module_name = 'search';

    protected $ACTION_QUERY = self::module_name.'_query';
    protected $ACTION_DISPLAY_INPUT = self::module_name.'_display_input';

    private $only_input = false;
    private $document_modele = false;
    private $category = false;

    protected $results_per_page = [['val'=>12],['val'=>24],['val'=>48],['val'=>96]];
    protected $result_number_default = 12;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $to_return = false) {
        // begins with parsing the hash to recover the dom_id
        if (isset($post[self::module_name])) {
            $params = explode(':', $post[self::module_name]);
            if ($params[0] == 'search') {
                // $post[$this->input_action_identifier] = $this->ACTION_QUERY;
                $post['dom_id'] = $params[1];
                $post['search_content'] = $params[2];

                $key_nbr = array_search('result_number', $params);
                $post['result_number'] = $params[$key_nbr + 1];

                $key_categorie = array_search('filter_category', $params);
                $post['filter_category'] = ($key_categorie != '') ? $params[$key_categorie + 1] : 0;
                
                $key_start = array_search('start_index', $params);
                if ($key_start) $post['start_index'] = $params[$key_start + 1];
                
                $key_limit = array_search('page_limit', $params);
                if ($key_limit) $post['page_limit'] = $params[$key_limit + 1];
            }
        }

        // Utils::error_log($post);

        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if (!isset($_SESSION['search'.$this->dom_id])) $_SESSION['search'.$this->dom_id] = [];
        if (isset($post['display_only_input']) && $post['display_only_input']) {
            $_SESSION['search'.$this->dom_id]['only_input'] = $post['display_only_input'];
        }
        if (isset($post['document_modele']) && $post['document_modele']) {
            $_SESSION['search'.$this->dom_id]['document_modele'] = $post['document_modele'];
        }
        if (isset($post['category']) && $post['category']) {
            $_SESSION['search'.$this->dom_id]['category'] = $post['category'];
        }

        $this->only_input = isset($_SESSION['search'.$this->dom_id]['only_input']) ? $_SESSION['search'.$this->dom_id]['only_input'] : false;
        $this->document_modele = isset($_SESSION['search'.$this->dom_id]['document_modele']) ? $_SESSION['search'.$this->dom_id]['document_modele'] : false;
        $this->category = isset($_SESSION['search'.$this->dom_id]['category']) ? $_SESSION['search'.$this->dom_id]['category'] : false;
        
        $master_output = H::group('display_'.self::module_name);
        switch ($post[$this->input_action_identifier]) {

            case $this->ACTION_QUERY:
                if (!$this->only_input){
                    $master_output->add_child( $this->display_form($post) );
                }
                $master_output->add_child( $this->query_search($post) );
            break;

            case $this->ACTION_DISPLAY_INPUT:
                $this->dom_container.= ' only_input';
                $master_output->add_child( $this->display_form($post) );
                
                // init js
                $params = [
                    'only_input'=>$this->only_input,
                    'document_modele'=>$this->document_modele,
                    'category'=>$this->category
                ];
                $js = 'helphp_timeout(\'Search.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($params)).');\');';
                $script = H::script($js, ['autoremove'=>true]);
                $master_output->add_child( $script );
            break;
            
            default:
                $master_output->add_child( H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('ttl_search')) );
                $master_output->add_child( $this->display_form($post) );
                $master_output->add_child( $this->query_search($post) );
                
                // init js
                $params = [
                    'only_input'=>$this->only_input,
                    'document_modele'=>$this->document_modele,
                    'category'=>$this->category
                ];
                $js = 'helphp_timeout(\'Search.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($params)).');\');';
                $script = H::script($js, ['autoremove'=>true]);
                $master_output->add_child( $script );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    //----------------------------------------------------------------------------------------------
    
    public function display_form(&$post){

        $output = H::DIV(['class'=>$this->css.'form']);

            $post['search_content'] = isset($post['search_content']) ? $post['search_content'] : '';
            $text = H::input_text([
                'class'=>$this->css.'input',
                'name'=>'search_content',
                'id'=>self::module_name.'_input'.$this->dom_id,
                'value'=>urldecode($post['search_content']),
                'placeholder'=>$this->get_tl('input_placeholder'),
                'onkeypress'=>$this->inst_js.'.on_key_press(event);'
            ]);
            $btn = H::button_icon('search', [
                'id'=>self::module_name.'_btn_send'.$this->dom_id,
                'class'=>$this->css.'btn_send',
                'title'=>$this->get_tl('search'),
                'onclick'=>$this->inst_js.'.make_hash();'
            ]);
        
        $output->add_child( [$text, $btn] );

        if (!$this->only_input){
            $selected = isset($post['result_number']) ? $post['result_number'] : $this->result_number_default;
            $opts_data = array('value_key'=>'val', 'label_key'=>'val', 'options'=>$this->results_per_page);
            $select = H::select(['id'=>self::module_name.'_result_number'.$this->dom_id, 'name'=>'result_number', 'label'=>$this->get_tl('result_number')], $opts_data, $selected);
            $output->add_child(H::DIV(['class'=>$this->css.'result_number'], [$select->label_tag(), $select]));

            $list = \helPHP\modules\category\admin\Category::get_list('document', true);
            // $list = $this->module_data_tree_for_select('category');
            $selected = isset($post['filter_category']) ? $post['filter_category'] : 0 ;
            $opts_data = array('first_empty'=>true, 'indentation'=>$this->indentation_key, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$list);
            $select = H::select(['id'=>self::module_name.'_filter_category'.$this->dom_id, 'label'=>$this->get_tl('category')], $opts_data, $selected);
            $output->add_child(H::DIV(['class'=>$this->css.'category'], [$select->label_tag(), $select]));
        }

        return $output;
    }

    public function query_search(&$post) {
        global $DB, $LANG, $USER;

        // Utils::error_log($post);
        
        $search_string = trim(urldecode($post['search_content']));
        $search_string = str_replace('%', '', $search_string);
        if ($search_string == '') return $this->display_query_result($post);

        // $categories = [];
        $db_indexation = $DB->table('indexation_data');
        $db_lang_long = $DB->table('languages_long');
        $db_lang_short = $DB->table('languages_short');

        $query_params_types = '';
        $query_params = array();
        $query_conditions = '';

        $post['result_number'] = isset($post['result_number']) ? intval($post['result_number']) : $this->result_number_default;
        $post['start_index'] = isset($post['start_index']) ? intval($post['start_index']) : 0;
        $post['page_limit']= isset($post['page_limit']) ? $post['page_limit'] : $this->results_default_count;
        if( $post['result_number'] != $post['page_limit']){
            $post['page_limit']= $post['result_number'];
        } 
        $post['filter_category'] = isset($post['filter_category']) ? $post['filter_category'] : 0;
        
        // Utils::error_log($post);

        //on rend les mots obligatoires dans la recherche :
        $fulltext_string = '';
        $words = explode(' ', $search_string);
        foreach ($words as $word) {
            $word = trim($word);
            if ($word != '' && strlen($word) > 1) {
                $fulltext_string .= ' +'.$word;
            }
        }
        $search_string = trim($fulltext_string);
        
        // condition to add to restrict by groups
        $groups = $USER->allowed_groups();
        $str_groups = implode(',', $groups);
        $db_group = $DB->table('group_content');
        if ($str_groups != '') {
            $q_groups = ' AND ('.$db_group.'.id_group_data IS NULL OR '.$db_group.'.id_group_data IN ('.$str_groups.') )';
        } else {
            $q_groups = ' AND '.$db_group.'.id_group_data IS NULL';
        }
        
        // search in documents
        $db_document = $DB->table('document_data');
        $db_blocks = $DB->table('document_blocks');

        $q = '(SELECT SQL_CALC_FOUND_ROWS '.$db_document.'.id as id, "document" as type, COUNT(MATCH('.$db_lang_long.'.value) AGAINST(? in boolean MODE)) as score';
        $query_params_types.= 's';
        array_push($query_params, $search_string);

        $q.=' FROM '.$db_lang_long.', '.$db_indexation.', '.$db_document;

        // add group with an LEFT OUTER JOIN to also get the row when document id not in id_item in the group_content table
        $q.=' LEFT OUTER JOIN '.$db_group.' ON ('.$db_group.'.field_identifier="document" AND '.$db_group.'.id_item='.$db_document.'.id)';

        // add block table to search in blocks
        $q.=' LEFT JOIN '.$db_blocks.' ON ('.$db_blocks.'.id_document_data = '.$db_document.'.id)';

        // search in lang long
        $q.=' WHERE MATCH('.$db_lang_long.'.value) AGAINST(? in boolean MODE)';
        $query_params_types .= 's';
        array_push($query_params, $search_string);
        
        // conditioned by the link to lang
        $q.=' AND (('.$db_lang_long.'.field_identifier LIKE "document_data-summary" AND '.$db_lang_long.'.id_item = '.$db_document.'.id)';
        
        // conditioned by the indexation's description
        $q.=' OR ('.$db_lang_long.'.field_identifier = "indexation-data-description" AND '.$db_indexation.'.module_name="document"';
        $q.=' AND '.$db_indexation.'.id = '.$db_lang_long.'.id_item AND '.$db_indexation.'.module_param = '.$db_document.'.id)';

        // conditionned by the link to lang from a block
        $q.=' OR ('.$db_lang_long.'.field_identifier LIKE CONCAT("block_", '.$db_blocks.'.blockname, "-%") AND '.$db_lang_long.'.id_item = '.$db_blocks.'.id_block))';
        
        // and in the current language
        $q.=' AND '.$db_lang_long.'.id_data=?';
        $query_params_types .= 'i';
        array_push($query_params, $LANG->current_id_data);

        // and active published document
        $q.=' AND '.$db_document.'.publication_date < CURRENT_TIMESTAMP AND '.$db_document.'.active=1';

        if ($this->document_modele){
            $q.=' AND '.$db_document.'.modele = ? AND '.$db_document.'.ismodele=0';
            $query_params_types .= 's';
            array_push($query_params, $this->document_modele);
        }

        if ($post['filter_category']) {
            $db_category = $DB->table('category_content');
            $q.= ' AND '.$db_document.'.id IN (SELECT DISTINCT '.$db_category.'.id_item FROM '.$db_category.' WHERE ';
            $q.= $db_category.'.id_data = ? AND '.$db_category.'.field_identifier = "document")';
            $query_params_types .= 'i';
            array_push($query_params, $post['filter_category']);
        }
        
        // if ($this->limitByIndexation){
            // $q.='and ('.$db_indexation.'.module_name="document" and '.$db_indexation.'.module_param='.$db_document.'.id) ';
        // }
        
        // verify group access
        $q.= $q_groups;
        
        // and group by id type
        $q.=' GROUP BY id, type) ';

        // same thing for lang short than for lang long
        $q.=' UNION (SELECT '.$db_document.'.id as id, "document" as type, COUNT(MATCH('.$db_lang_short.'.value) AGAINST(? in boolean MODE)) as score';
        $query_params_types .= 's';
        array_push($query_params, $search_string);

        $q.=' FROM '.$db_lang_short.', '.$db_indexation.', '.$db_document;
        
        // add group with an LEFT OUTER JOIN to also get the row when document id not in id_item in the group_content table
        $q.=' LEFT OUTER JOIN '.$db_group.' ON ('.$db_group.'.field_identifier="document" AND '.$db_group.'.id_item='.$db_document.'.id)';

        // add block table to search in blocks
        $q.=' LEFT JOIN '.$db_blocks.' ON ('.$db_blocks.'.id_document_data = '.$db_document.'.id)';
        
        // search in lang short
        $q.=' WHERE MATCH('.$db_lang_short.'.value) AGAINST(? in boolean MODE)';
        $query_params_types .= 's';
        array_push($query_params, $search_string);

        // conditioned by the link to lang
        $q.=' AND (('.$db_lang_short.'.field_identifier LIKE "document_data-label" AND '.$db_lang_short.'.id_item = '.$db_document.'.id)';
        
        // conditioned by the indexation's title or keywords
        $q.=' OR (('.$db_lang_short.'.field_identifier = "indexation-data-title" OR '.$db_lang_short.'.field_identifier = "indexation-data-keywords")';
        $q.=' AND '.$db_indexation.'.module_name="document" AND '.$db_indexation.'.id = '.$db_lang_short.'.id_item';
        $q.=' AND '.$db_indexation.'.module_param = '.$db_document.'.id)';

        // conditionned by the link to lang from a block
        $q.=' OR ('.$db_lang_short.'.field_identifier LIKE CONCAT("block_", '.$db_blocks.'.blockname, "-%") AND '.$db_lang_short.'.id_item = '.$db_blocks.'.id_block))';
        
        // and in the current language
        $q.=' AND '.$db_lang_short.'.id_data=?';
        $query_params_types .= 'i';
        array_push($query_params, $LANG->current_id_data);

        // and active published document
        $q.=' AND '.$db_document.'.publication_date < CURRENT_TIMESTAMP AND '.$db_document.'.active=1';

        if ($this->document_modele){
            $q.=' AND '.$db_document.'.modele = ? AND '.$db_document.'.ismodele=0';
            $query_params_types .= 's';
            array_push($query_params, $this->document_modele);
        }

        if ($post['filter_category']) {
            $db_category = $DB->table('category_content');
            $q.= ' AND '.$db_document.'.id IN (SELECT DISTINCT '.$db_category.'.id_item FROM '.$db_category.' WHERE ';
            $q.= $db_category.'.id_data = ? AND '.$db_category.'.field_identifier = "document")';
            $query_params_types .= 'i';
            array_push($query_params, $post['filter_category']);
        }
        
        // verify group access
        $q.= $q_groups;
        
        // and group by id type
        $q.=' GROUP BY id, type)';
    
        $q.=' ORDER BY score DESC';
        $q.=' LIMIT '.intval($post['start_index']).','.intval($post['page_limit']);
        $results = $DB->prepared_query_list($q, $query_params_types, $query_params);

        if (is_array($results)) {
            $merged_result = [];
            foreach ($results as $index=>$line) {
                if (array_key_exists($line['id'].'-'.$line['type'], $merged_result)) {
                    $merged_result[$line['id'].'-'.$line['type']]['score']+=$line['score'];
                } else {
                    $merged_result[$line['id'].'-'.$line['type']]=$line;
                }
            }
            $pages = $DB->last_pages_data();
            $post['pages'] = $pages;
            $post['results'] = $merged_result;
        } else {
            $post['pages'] = false;
            $post['id'] = [];
        }

        return $this->display_query_result($post);
    }

    public function display_query_result($post) {
        global $DB, $LANG, $CONFIG;

        $output = H::DIV(['class'=>$this->css.'result_list', 'id'=>self::module_name.'_result'.$this->dom_id]);

        $pages = isset($post['pages']) ? $post['pages'] : false;
        if ($pages && $pages['page_count'] > 1) {
            $form_pages = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'class'=>$this->css.'form_pages']);

            $params = [];
            $params['search_content'] = $post['search_content'];
            $params['result_number'] = $post['result_number'];
            $params['filter_category'] = $post['filter_category'];
            $params['page_limit'] = $post['page_limit'];


            $index = $pages['page_index'];
            if ($index > 0) {
                $params['start_index'] = ($index-1) * $post['page_limit'];
                $btn_previous = H::button_icon('chevron-left', ['class'=>$this->css.'prev_page', 'onclick'=>$this->inst_js.'.make_hash(event, true);', 'data-parameters'=>$params]);
                $form_pages->add_child($btn_previous);
                unset($params['start_index']);
            }

            $opts = [];
            for ($i = 0 ; $i < $pages['page_count']; $i++) {
                array_push($opts, ['label'=>$i+1, 'value'=>$i*$post['page_limit']]);
            }
            $options_data = array('label_key'=>'label', 'value_key'=>'value', 'options'=>$opts);
            $selected = $index * $post['page_limit'];
            $select = H::select(['name'=>'start_index', 'class'=>$this->css.'select_page' , 'onchange'=>$this->inst_js.'.make_hash(event, true);', 'data-parameters'=>$params], $options_data, $selected);
            $form_pages->add_child($select);

            if ($index < ($pages['page_count']-1)) {
                $params['start_index'] = ($index+1)*$post['page_limit'];
                $btn_next = H::button_icon('chevron-right', ['class'=>$this->css.'next_page', 'onclick'=>$this->inst_js.'.make_hash(event, true);', 'data-parameters'=>$params]);
                $form_pages->add_child($btn_next);
            }
        }

        if ($pages && $pages['page_count'] > 0) {
            foreach ($post['results'] as $index => $line) {
                $data = false;
                $q = 'SELECT sht.value as title, lng.value as description, ind.id as indexation FROM '.$DB->table('document_data').' dat';
                $q.=' LEFT JOIN '.$DB->table('languages_short').' sht ON (sht.field_identifier = "document_data-label" AND sht.id_item = dat.id AND sht.id_data='.$LANG->current_id_data.')';
                $q.=' LEFT JOIN '.$DB->table('languages_long').' lng ON (lng.field_identifier = "document_data-summary" AND lng.id_item = dat.id AND lng.id_data='.$LANG->current_id_data.')';
                $q.=' LEFT JOIN '.$DB->table('indexation_data').' ind ON (ind.module_name = "document" AND module_param = dat.id AND ind.activated = 1)';
                $q.=' WHERE dat.id = '.$line['id'];
                $data = $DB->query_line($q);

                $hash = $line['type'].'='.$line['id'];

                $one_result = H::A(['class'=>$this->css.'result_item', 'id'=>self::module_name.'_result_box-'.$line['id'].$this->dom_id, 'href'=>'?'.$hash, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");']);
                
                // exclude results without title and description
                if (isset($data['title']) && isset($data['description'])) {
                    // $image_src = $CONFIG::BASE_URL.'public/news/images/default.png';
                    // $imagesrc = (isset($theligne['image']))? CONFIG_Class::BASE_URL.$theligne['image']:CONFIG_Class::BASE_URL.'public/news/images/default.png';
                    if ($data['indexation'] > 0 && Media::has_media('indexation_data-image', $data['indexation'])) {
                        global $MEDIA;
                        $image = $MEDIA->get_html('indexation_data-image', $data['indexation']);
                    }
                    if (!isset($image)){
                        $image = H::DIV(['class'=>$this->css.'result_without_img']);
                    }
                    $title = H::SPAN(['class'=>$this->css.'result_title'], $data['title']);
                    $description = H::SPAN(['class'=>$this->css.'result_description'], $data['description']);
                    
                    $one_result->add_child([$image, $title, $description]);
                    $output->add_child($one_result);
                    unset($image);
                } else {
                    // if (!$this->limitByIndexation) {
                    $link = H::SPAN(['class'=>$this->css.'result_link'], $CONFIG::BASE_URL.'?'.$line['type'].'='.$line['id']);
                    $one_result->add_child([$link]);
                    $output->add_child($one_result);
                    // }
                }
            }
            if ($pages['page_count'] > 1) {
                $output->add_after($form_pages);
            }
        } else {
            $no_result = H::DIV(['class'=>$this->css.'result_item no_result'], $this->get_tl('no_result'));
            $output->add_child($no_result);
        }
        return $output;
    }
}