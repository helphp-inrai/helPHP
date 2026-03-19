<?php

namespace helPHP\modules\block\news\public;

use helPHP\libs\Datetime;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Utils;

class News extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/news/public/News.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_news_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_news');
                if (!$post[$this->ifld_news_id]) $this->reset_fields($post, 'block_news');

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
        
        global $DB, $USER, $LANG, $CONFIG;

        $this->css.= 'news_';
        
        $data_display = H::div(['class'=>'block_container block_20 news','data-block_type'=>'news','data-block_id'=>$post[$this->ifld_news_id],'id'=>'block_news_'.$post[$this->ifld_news_id] ]);
            

            $groups = $USER->allowed_groups();
            $str_groups = implode(',', $groups);
            $db_group = $DB->table('group_content');
            if ($str_groups != '') {
                $q_groups = ' AND (grp.id_group_data IS NULL OR grp.id_group_data IN ('.$str_groups.') )';
            } else {
                $q_groups = ' AND grp.id_group_data IS NULL';
            }

            $q = 'SELECT number_display FROM '.$DB->table('block_news').' WHERE id=?';
            $limit = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_news_id]]);

            $q = 'SELECT DISTINCT doc.id as id, sht.value as title, lng.value as description, ind.id as indexation,doc.publication_date as date FROM '.$DB->table('document_data').' doc';
            $q.=' LEFT JOIN '.$DB->table('languages_short').' sht ON (sht.field_identifier = "document_data-label" AND sht.id_item = doc.id AND sht.id_data='.$LANG->current_id_data.')';
            $q.=' LEFT JOIN '.$DB->table('languages_long').' lng ON (lng.field_identifier = "document_data-summary" AND lng.id_item = doc.id AND lng.id_data='.$LANG->current_id_data.')';
            $q.=' LEFT JOIN '.$DB->table('indexation_data').' ind ON (ind.module_name = "document" AND module_param = doc.id AND ind.activated = 1)';
            $q.=' LEFT OUTER JOIN '.$DB->table('group_content').' grp ON (grp.field_identifier="document" AND grp.id_item=doc.id)';
            $q.=' WHERE doc.active=1';
            $q.= $q_groups;
            $q.=' ORDER BY publication_date DESC LIMIT 0, '.intval($limit);

            $lst = $DB->query_list($q);

                $title = H::DIV(['class'=>$this->css.'title subtitle'], $this->get_tl('title'));

                $container_list = H::DIV(['class'=>$this->css.'list']);

            $data_display->add_child( [$title, $container_list] );

            if ($lst){

                foreach($lst as $line){

                    $hash = 'document='.$line['id'];

                    $one_result = H::A(['class'=>$this->css.'item', 'href'=>'?'.$hash, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");']);
                    
                    // exclude results without title and description
                    if (isset($line['title']) && isset($line['description'])) {
                        // $image_src = $CONFIG::BASE_URL.'public/news/images/default.png';
                        // $imagesrc = (isset($theligne['image']))? CONFIG_Class::BASE_URL.$theligne['image']:CONFIG_Class::BASE_URL.'public/news/images/default.png';
                        if ($line['indexation'] > 0 && \helPHP\libs\Media::has_media('indexation_data-image', $line['indexation'])) {
                            global $MEDIA;
                            $image = $MEDIA->get_html('indexation_data-image', $line['indexation']);
                        }
                        if (!isset($image)){
                            $image = H::DIV(['class'=>$this->css.'result_without_img']);
                        }
                        $date = H::SPAN(['class'=>$this->css.'result_date'], Datetime::date_to_string($line['date']).' - ');
                        $title = H::SPAN(['class'=>$this->css.'result_title'], $line['title']);
                        $description = H::SPAN(['class'=>$this->css.'result_description'], $line['description']);
                        
                        $one_result->add_child([$image, $date, $title, $description]);
                        $container_list->add_child($one_result);
                        unset($image);
                    } else {
                        $link = H::SPAN(['class'=>$this->css.'result_link'], $CONFIG::BASE_URL.'?document='.$line['id']);
                        $one_result->add_child([$link]);
                        $container_list->add_child($one_result);
                    }
                }
            }

        return $data_display;
    }
}