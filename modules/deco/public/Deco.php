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
namespace helPHP\modules\deco\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;

class Deco extends HelPHP_module {

    const module_name = 'deco';
    
    protected $scroll = false;

    protected $prepared_anim = false;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);

        $this->prepared_anim = [
            ['id'=>0,'name'=>$this->get_tl('Aucune'), 'opts'=>''],
            // apparition scrollup
            ['id'=>1,'name'=>$this->get_tl('scroll_fade_in'), 'opts'=>'"preset": true,"event": "scroll","easing": "OutQuad","duration": "700", "opacity": ["0", "1"]'],
            ['id'=>2,'name'=>$this->get_tl('scroll_smooth_scale'), 'opts'=>'"preset": true,"event": "scroll","easing": "OutQuad","duration": "500", "transform": ["scale(0.7,0.7)", "scale(1,1)"], "opacity": ["0", "1"]'],
             // Click
            ['id'=>3,'name'=>$this->get_tl('click_fade_in'), 'opts'=>'"event":"hover","opacity": ["0", "1"], "duration": "1000"'],
            // apparition fondu
            ['id'=>4,'name'=>$this->get_tl('apparition'), 'opts'=>'"opacity": ["0", "1"], "duration": "3000"'],
            ['id'=>5,'name'=>$this->get_tl('zoom_in'), 'opts'=>'"easing": "InQuad", "duration": "1000", "transform": ["scale(0.1)", "scale(1)"]'],
            ['id'=>6,'name'=>$this->get_tl('zoom_out'), 'opts'=>'"easing": "InQuad", "duration": "1000", "transform": ["scale(2)", "scale(1)"]'],
            // apparition fondu avec glissement d'un coté
            ['id'=>7,'name'=>$this->get_tl('arrive_par_la_gauche'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateX(-100%)", "translateX(0%)"], "opacity": ["0", "1"]'],
            ['id'=>8,'name'=>$this->get_tl('arrive_par_la_droite'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateX(100%)", "translateX(0%)"], "opacity": ["0", "1"]'],
            ['id'=>9,'name'=>$this->get_tl('arrive_par_le_haut'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateY(-100%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>10,'name'=>$this->get_tl('arrive_par_le_bas'), 'opts'=>'"easing": "OutQuart", "duration": "1000", "transform": ["translateY(100%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>11,'name'=>$this->get_tl('appear_up-250'), 'opts'=>'"easing": "OutQuart",duration": "250", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>12,'name'=>$this->get_tl('appear_up-500'), 'opts'=>'"easing": "OutQuart","duration": "500", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>13,'name'=>$this->get_tl('appear_up-750'), 'opts'=>'"easing": "OutQuart","duration": "750", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            ['id'=>14,'name'=>$this->get_tl('appear_up-1000'), 'opts'=>'"easing": "OutQuart","duration": "1000", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"]'],
            // rotation
            ['id'=>15,'name'=>$this->get_tl('rotation_horaire'), 'opts'=>'"easing": "InOutCubic", "duration": "1000", "transform": ["rotate(0deg)", "rotate(360deg)"]'],
            ['id'=>16,'name'=>$this->get_tl('rotation_anti-horaire'), 'opts'=>'"easing": "InOutCubic", "duration": "1000", "transform": ["rotate(0deg)", "rotate(-360deg)"]'],
            // pulse
            ['id'=>17,'name'=>$this->get_tl('pouls'), 'opts'=>'"easing": "InOutExpo", "duration": "500", "direction": "alternate", "transform": ["scale(1, 1)", "scale(1.2, 1.2)"], "loop": true']
        ];
    }

    public function process_data(&$post){
        if (parent::process_data($post) == false) {
            // utilisateur non autorisé sur ce module
            return null;
        }

        $this->display->add_child($this->display($post));
    }

    public function display(&$post){
        if (!isset($post['id'])) {
            Utils::error_log('missing id in post');
            Utils::error_log($post);
            return;
        }
        
        $this->scroll = isset($post['scroll']) ? isset($post['scroll']) : $this->scroll;

        global $DB;
        $q = ' SELECT * FROM '.$this->bddt_data.' WHERE id=?';
        $data = $DB->prepared_query_line($q, 'i', [$post['id']]);
        if (!$data) {
            Utils::error_log('data not found in db for id '.$post['id']);
            return;
        }

        $this->dom_container.= ' '.$data['name'];

        if ($data['link']) {
            $output = H::A(['class'=>$this->css.$data['name'], 'href'=>$data['link']]);
        } else {
            $output = H::group('deco_display'.$this->dom_id);
        }

        $output->add_child( \helPHP\modules\block\Bridge::load('deco', [], $data['block_name'], $data['id_block']) );

        $js = '';
        if ($data['id_animation']){
            $id_anim = array_search($data['id_animation'], array_column($this->prepared_anim, "id"));
            $js.= 'helphp_timeout(\'new H_anim({elements: "#block_'.$data['block_name'].'_'.$data['id_block'].'", '.$this->prepared_anim[$id_anim]['opts'].'});\');';
        }
        if ($this->scroll){
            $js.= 'H_ui.scroll_toggle_class("'.$this->dom_container.'", '.$this->scroll.', "fixed");';
        }
        if ($js != ''){
            $output->add_child( H::script($js, ['autoremove'=>true]) );
        }

        return $output;
    }

    // public function showDeco($output)
    // {
    //     $this->dom_container .= '_'.$this->css;
        
    //     $output = $this->integrate_module($output);
        
    //     if ($this->scroll !== false) {
    //         return [$output, H::script('H_ui.scroll_toggle_class("'.$this->dom_container.'", '.$this->scroll.', "fixed");')];
    //     } else {
    //         return $output;
    //     }
    // }
}
