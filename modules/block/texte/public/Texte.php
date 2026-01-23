<?php

namespace helPHP\modules\block\texte\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Texte extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/texte/public/Texte.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_texte_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_texte');
                if (!$post[$this->ifld_texte_id]) $this->reset_fields($post, 'block_texte');
                Language::load_public_translation_data($post, self::module_name, 'texte', $post[$this->ifld_texte_id]);
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
        
        $data_display = H::div(['class'=>'block_container block_9 texte','data-block_type'=>'texte','data-block_id'=>$post[$this->ifld_texte_id],'id'=>'block_texte_'.$post[$this->ifld_texte_id] ]);
                $contenu_label =H::SPAN( ['id'=>$this->ifld_texte_contenu_label, 'class'=>'label'], $this->get_tl('contenu'));
                $contenu =H::DIV( ['id'=>$this->ifld_texte_contenu_public, 'class'=>'disp_longmulti'], $post[$this->ifld_texte_contenu]);
                $data_display->add_child([$contenu_label,$contenu]);


        return $data_display;
    }
}