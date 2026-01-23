<?php

namespace helPHP\modules\block\title\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Title extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/title/public/Title.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_title_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_title');
                if (!$post[$this->ifld_title_id]) $this->reset_fields($post, 'block_title');

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
        
        global $DB,$LANG;
        
        // $output = H::group('show_Blockeditor);
        if(isset($post['no_subdiv'])){
            $data_display = H::group('block_display');
        }else{
            // $block_order=isset($post['data-order'])?$post['data-order']:0;
            $data_display = H::div(['class'=>'block_container block_1 title','data-blocktype'=>'title','data-blockid'=>$post[$this->ifld_title_id],'id'=>'block_title_'.$post[$this->ifld_title_id] ]);
        }
            //affichage du contenu des champs
                $title_label =H::SPAN( ['id'=>$this->ifld_title_title_label, 'class'=>'label'], $this->get_tl('title'));
                $title =H::SPAN( ['id'=>$this->ifld_title_title_public, 'class'=>'disp_text'], $post[$this->ifld_title_title]);
                $data_display->add_child([$title_label,$title]);


        return $data_display;
    }
}
?>