<?php
/*
    COPYRIGHT M666 moi@myke666.fr 40600 BISCARROSSE FRANCE 2009-2024 and STEUZ steineremile@duck.com
    ALL RIGHTS RESERVED
    TOUS DROITS RESERVES
    THIS CODE CAN'T BE DUPLICATED OR MODIFY WITHOUT M666 (moi@myke666.fr) and STEUZ steineremile@duck.com AGREEMENT
    CE CODE NE PEUT PAS ÊTRE DUPLIQUE OU MODIFIE SANS L'ACCORD D'M666 moi@myke666.fr et STEUZ steineremile@duck.com

    ADMIN MODULE GENERATED WITH helPHP ! 
*/

namespace helPHP\modules\icons\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use SimpleXMLElement;

/**
 * Little module to see all the icons from feather (https://feathericons.com/)
 */
class Icons extends HelPHP_module {

    const module_name = 'icons';

    // protected $sprite_name = 'feather-sprite.svg';
    protected $path, $icons;

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container);

        global $CONFIG;
        $this->path = $CONFIG::HOME_FOLDER.'images/icons/'.H::svg_sprite;

        $this->parse_stripe();
    }

    public function process_data(&$post, $to_return=false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        $this->inst_js = 'helPHP_'.self::module_name.'["'.$this->domId.'"]';

        // when opening module from the preview
        if (isset($post['id']) && intval($post['id'] > 0)){
            $post[$this->ifld_data_id] = $post['id'];
        }

        // if no right to edit
        if (!$this->user_can_edit){
            $this->css = 'no_edit '.$this->css;
        }

        $master_output = H::group('icons_display');
        switch($post[$this->input_action_identifier]){
            default:
                $master_output->add_child( $this->display($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }


    public function parse_stripe(){
        
        $content = file_get_contents($this->path);

        $this->icons = [];

        $svg = new SimpleXMLElement($content);
        $svg->registerXPathNamespace('svg', 'http://www.w3.org/2000/svg');
        $lst = $svg->xpath('/svg:svg//svg:symbol');
        foreach ($lst as $sym) {
            array_push($this->icons, (string) $sym->attributes()->{'id'}); // (string) before to parse it into a string
        }
    }

    public function display($post) {

        $output = H::group('icons_container');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
            
            $actions = H::DIV(['class'=>$this->css.'action_buttons action_buttons']);
                $input_search = H::input_text(['name'=>'icons_search', 'class'=>$this->css.'inp_search', 'id'=>self::module_name.'_input_search'.$this->dom_id]);
                $input_reset = H::BUTTON(['class'=>$this->css.'inp_reset', 'id'=>self::module_name.'_input_reset'.$this->dom_id, 'title'=>$this->get_tl('reset')], H::icon('x-circle'));
            $actions->add_child([$input_search, $input_reset]);
            
            $list_icons = H::DIV(['class'=>$this->css.'list', 'id'=>self::module_name.'_container_icons'.$this->dom_id]);
            foreach($this->icons as $name){
                $dom_icon = H::DIV(['class'=>$this->css.'card_icon', 'data-name'=>$name, 'title'=>$this->get_tl('copy_to_clipboard', $name)]);
                    $img = H::icon($name);
                    $dom_name = H::SPAN(['class'=>$this->css.'icon_name'], $name);
                        $copy_name = H::icon('copy', ['class'=>$this->css.'copy_name', 'title'=>$this->get_tl('clipboard_name', $name)]);
                    $dom_name->add_child($copy_name);
                $dom_icon->add_child([$img,$dom_name]);
                $list_icons->add_child($dom_icon);
            }

            $js= 'Icons_a.create_instance("'.$this->dom_id.'");';
            $script = H::script($js, ['autoremove'=>true]);
            
        $output->add_child([$title, $actions, $list_icons, $script]);

        return $output;
    }
}