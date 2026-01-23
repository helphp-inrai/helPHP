<?php

namespace helPHP\modules\block\imagetext\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Imagetext extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/imagetext/public/Imagetext.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_imagetext_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_imagetext');
                if (!$post[$this->ifld_imagetext_id]) $this->reset_fields($post, 'block_imagetext');
                Language::load_public_translation_data($post, self::module_name, 'imagetext', $post[$this->ifld_imagetext_id]);
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
        
        $data_display = H::div(['class'=>'block_container block_16 imagetext','data-block_type'=>'imagetext','data-block_id'=>$post[$this->ifld_imagetext_id],'id'=>'block_imagetext_'.$post[$this->ifld_imagetext_id] ]);
            global $MEDIA;
            $media = $MEDIA->get_html($this->ifld_imagetext_image, $post[$this->ifld_imagetext_id]);
            $image = ($media)? $media:'';
            if($post[$this->ifld_imagetext_alignement]!='' && $image){
                $image->set_attribute('style','float:'.substr($post[$this->ifld_imagetext_alignement],5));
            }

            $contenu =H::DIV( ['id'=>$this->ifld_imagetext_contenu_public, 'class'=>'disp_longmulti'], $image.$post[$this->ifld_imagetext_contenu]);
        $data_display->add_child([$contenu]);



        return $data_display;
    }
}