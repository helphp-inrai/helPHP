<?php

namespace helPHP\modules\block\blockbackground\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Blockbackground extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/blockbackground/public/Blockbackground.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_blockbackground_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_blockbackground');
                if (!$post[$this->ifld_blockbackground_id]) $this->reset_fields($post, 'block_blockbackground');

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
        
        global $CONFIG;
        
        $data_display = H::div(['class'=>'block_container block_2 blockbackground','data-block_type'=>'blockbackground','data-block_id'=>$post[$this->ifld_blockbackground_id],'id'=>'block_blockbackground_'.$post[$this->ifld_blockbackground_id]]);
        //affichage du contenu des champs
        switch($post[$this->ifld_blockbackground_type]){
            case 'css':
                $content=H::DIV(['id'=>$this->ifld_blockbackground_content_public.'-'.$this->dom_id, 'class'=>'background_type css','style'=>html_entity_decode($post[$this->ifld_blockbackground_content])] );
                $data_display->add_child($content);
            break;
            case 'image':
                $content =H::IMG(['id'=>$this->ifld_blockbackground_content_public.'-'.$this->dom_id, 'class'=>'background_type image', 'src'=>html_entity_decode($post[$this->ifld_blockbackground_content])]);
                $data_display->add_child($content);
            break;
            case 'video':
                $content =H::VIDEO(['id'=>$this->ifld_blockbackground_content_public.'-'.$this->dom_id, 'class'=>'background_type video', 'src'=>html_entity_decode($post[$this->ifld_blockbackground_content])]);
                $data_display->add_child($content);
            break;
            case 'shadertoy':
                $load_shader=H::script(null, ['src'=>$CONFIG::BASE_URL.'js/externals/shadertoylite.js']);
                $data_display->add_child($load_shader);
                $content =H::CANVAS( ['width'=>'1200','height'=>'700','id'=>'canvastoy_'.$this->dom_id, 'class'=>'background_type shadertoy']);  
                $data_display->add_child($content);
                $shadercode=H::input_hidden(['id'=>'shadercode_'.$this->dom_id,'value'=>$post[$this->ifld_blockbackground_content]]);
                $data_display->add_child([$shadercode]); 
                $data_display->add_child(H::script('window.h.block.Block_blockbackground.create_instance("'.$this->dom_id.'");',['autoremove'=>true,'defer'=>true]));
            break;
        }

        return $data_display;
    }
}