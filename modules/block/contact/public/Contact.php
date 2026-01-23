<?php

namespace helPHP\modules\block\contact\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Contact extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/contact/public/Contact.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_contact_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_contact');
                if (!$post[$this->ifld_contact_id]) $this->reset_fields($post, 'block_contact');

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
        
        $data_display = H::div(['class'=>'block_container block_18 contact','data-block_type'=>'contact','data-block_id'=>$post[$this->ifld_contact_id],'id'=>'block_contact_'.$post[$this->ifld_contact_id] ]);
                $contact_label =H::SPAN( ['id'=>$this->ifld_contact_contact_label, 'class'=>'label'], $this->get_tl('contact'));
                $contact = H::DIV( ['id'=>$this->ifld_contact_contact_public, 'class'=>'disp_hcode'], "[contact]");
                $data_display->add_child([]);

                $data_display->add_child([$contact]);

        return $data_display;
    }
}