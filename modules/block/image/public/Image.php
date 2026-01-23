<?php

namespace helPHP\modules\block\image\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Image extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/image/public/Image.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_image_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_image');
                if (!$post[$this->ifld_image_id]) $this->reset_fields($post, 'block_image');

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
        
        $data_display = H::div(['class'=>'block_container block_5 image','data-block_type'=>'image','data-block_id'=>$post[$this->ifld_image_id],'id'=>'block_image_'.$post[$this->ifld_image_id] ]);
                $image_label =H::SPAN( ['id'=>$this->ifld_image_image_label, 'class'=>'label'], $this->get_tl('image'));
                global $MEDIA;
                $media = $MEDIA->get_html($this->ifld_image_image, $post[$this->ifld_image_id]);
                $image = ($media)? $media:'';
                $data_display->add_child([$image]);



        return $data_display;
    }
}