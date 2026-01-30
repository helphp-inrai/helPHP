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

namespace helPHP\modules\tabs\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Htmlgroup;
use helPHP\libs\Utils;

class Tabs extends HelPHP_module{

    const module_name = 'tabs';

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct();
    }
    
    public function process_data(&$post,$toreturn=false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        $this->dom_id = isset($post['domId']) ? $post['domId'] : $this->dom_id;
        $this->dom_container='';

        $master_output = H::group('alert_display');
        switch($post[$this->input_action_identifier]){
            default:
                $master_output->add_child( $this->Menu($post) );
            break;
        }
        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    /**
     * Display tabs bar and the home
     */
    public function Menu ($post) {
        global $CONFIG;
        
        $output = H::group('bar');

        $parent = H::DIV(['id'=>'tabs_parent-'.$this->dom_id, 'class'=>'tabs_parent '.$this->dom_id]);
        // $js = 'var main_tab = new Tabs("'.$this->dom_id.'");';
        
        if (isset($CONFIG::MODULES_LIST['preview'])) {
            global $module_html_content;
            $preview = H::DIV(['id'=>'tab_elem-0', 'class'=>'tab_elem preview '.$this->dom_id, 'data-number'=>0, 'data-url'=>'preview']);
                $name = H::SPAN(['class'=>'name', 'id'=>'tab_elem_name-0']);
            $preview->add_child($name);
            $parent->add_child($preview);
            $mod_preview = new \helPHP\modules\preview\admin\Preview();
            $t=[];
            $t['core_insert']=true;
            $t['tab'] = 0;
            $mod_preview->process_data($t);
            $mod_preview->publish_output();
            $container = H::DIV(['id'=>'tab_container-0', 'class'=>'tab_container preview hidden '.$this->dom_id], $module_html_content['preview']);
            $parent->add_child($container);
            // $js.= 'main_tab.init(true);';
        } else {
            // $js.= 'main_tab.init();';
        }

        $js = 'helphp_timeout(\'h.main_tab = Tabs_a.create_instance("'.$this->dom_id.'", '.json_encode(isset($CONFIG::MODULES_LIST['preview'])).');\');';
        $script = H::script($js);
        $output->add_child([$parent,$script]);

        return $output;
    }
}