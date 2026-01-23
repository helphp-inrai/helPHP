<?php

namespace helPHP\modules\block\hcode\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Hcode extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/hcode/public/Hcode.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_hcode_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_hcode');
                if (!$post[$this->ifld_hcode_id]) $this->reset_fields($post, 'block_hcode');

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
        
        $data_display = H::div(['class'=>'block_container block_12 hcode','data-block_type'=>'hcode','data-block_id'=>$post[$this->ifld_hcode_id],'id'=>'block_hcode_'.$post[$this->ifld_hcode_id] ]);
            //affichage du contenu des champs
                $menu_label =H::SPAN( ['id'=>$this->ifld_hcode_menu_label, 'class'=>'label'], $this->get_tl('menu'));
                $menu = H::DIV( ['id'=>$this->ifld_hcode_menu_public, 'class'=>'disp_hcode'], "[hierarchy|hierarchy_action=hierarchy_display_menu&id=44]");
                $data_display->add_child([]);

                $data_display->add_child([$menu]);

        return $data_display;
    }
}
?>