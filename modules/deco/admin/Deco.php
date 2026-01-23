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
namespace helPHP\modules\deco\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Utils;

class Deco extends HelPHP_module {

    const module_name = 'deco';

    private $ACTION_NEW = self::module_name.'_new';
    private $ACTION_SAVE = self::module_name.'_save';
    private $ACTION_EDIT = self::module_name.'_edit';
    private $ACTION_DELETE = self::module_name.'_delete';

    private $ACTION_DISPLAY = self::module_name.'_display';

    private $ACTION_CHANGE_BLOCK = self::module_name.'_change_block';
    private $ACTION_BLOCK_SAVE = self::module_name.'_block_save';
    private $ACTION_BLOCK_DELETE = self::module_name.'_block_delete';

    protected $prepared_anim = false;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
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

    public function process_data(&$post, $to_return = false) {
        if (parent::process_data($post) == false) {
            // utilisateur non autorisé sur ce module
            return null;
        }
        
        if (isset($post['id']) && $post['id']) {
            $post[$this->ifld_data_id] = $post['id'];
            $post[$this->input_action_identifier] = $this->ACTION_EDIT;
        }

        $master_output = H::group('deco_display');
        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_DISPLAY:
                $master_output->add_child($this->display($post));
            break;

            case $this->ACTION_CHANGE_BLOCK:
                $this->check_posted_data($post, 'deco_data');
                $master_output->add_child( $this->edit_block($post) );
                // $master_output->add_child( $this->form_select($post) );
                // $master_output->add_child( $this->form_edit($post) );
            break;
            case $this->ACTION_BLOCK_SAVE:
                $this->save_block($post);
            break;
            case $this->ACTION_BLOCK_DELETE:
                $this->delete_block($post);
                // get select and toggle change event to reload the form's block
                $master_output->add_child( H::script('h.e.send_event(document.getElementById("'.self::module_name.'_select_block'.$this->dom_id.'"), "change");') );
            break;

            case $this->ACTION_EDIT:
                $this->prepare_fields($post, 'deco_data');
                // global $LANG;
                // $LANG->load_translation_data($post,'deco','data');
                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->form_edit($post) );
            break;
            case $this->ACTION_NEW:
                $post[$this->ifld_data_id] = 0;
                $this->reset_fields($post, 'deco_data');
                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->form_edit($post) );
            break;
            case $this->ACTION_SAVE:
                $this->check_posted_data($post,'deco_data');
                $this->save($post);
                $this->prepare_fields($post, 'deco_data');
                $master_output->add_child( $this->form_select($post) );
                $master_output->add_child( $this->form_edit($post) );
            break;
            case $this->ACTION_DELETE:
                $this->check_posted_data($post,'deco_data');
                $this->delete($post);
                $post[$this->ifld_data_id] = 0;
                $this->reset_fields($post, 'deco_data');
                $master_output->add_child( $this->form_select($post) );
            break;

            default:
                $this->check_posted_data($post, 'deco_data');
                $master_output->add_child($this->form_select($post));
            break;

        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    public function form_select($post) {
        global $DB;

        $q = 'SELECT id, name FROM '.$this->bddt_data;
        $list = $DB->prepared_query_list($q);

        $output = H::group('select_deco');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
            if (isset($post[$this->ifld_data_id]) && $post[$this->ifld_data_id] > 0) {
                $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_data_id])));
            }

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));

                $button_new = H::submit_button_single(array('class'=>$this->css.'button_new button_new','name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW, 'title'=>$this->get_tl('tlc_new')), $this->get_tl('tlc_new'));

                $opts_data = array('first_empty'=>true , 'value_key'=>'id' , 'label_key'=>'name' , 'options'=>$list);
                $select = H::select(['name'=>$this->ifld_data_id, 'label'=>$this->get_tl('tlc_select')], $opts_data, $post[$this->ifld_data_id], $this->input_action_identifier, $this->ACTION_EDIT);

            $form->add_child([$button_new,$select->label_tag(), $select]);

        $output->add_child([$title,$form]);

        if (isset($post[$this->ifld_data_id]) && $post[$this->ifld_data_id] > 0) {
            $btn_preview = H::preview_button(self::module_name, $post[$this->ifld_data_id], $this->get_tl('tlc_preview'));
            $form->add_child($btn_preview);
        }

        return $output;
    }

    public function form_edit(&$post) {

        // Utils::error_log($post);

        $output =  H::group('deco_form_edit');
        
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);
                
                $hidden_id = H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id], 'data-alwaysposted'=>1]);
                // $post['previous_block_name'] = isset($post['previous_block_name']) ? $post['previous_block_name'] : $post[$this->ifld_data_block_name];
                // $hidden_block_name = H::input_hidden(['name'=>'previous_block_name', 'value'=>$post['previous_block_name'], 'data-alwaysposted'=>1]);
                // $hidden_id_block = H::input_hidden(['name'=>$this->ifld_data_id_block, 'value'=>$post[$this->ifld_data_id_block], 'data-alwaysposted'=>1]);
                
            $form->add_child([$hidden_id]);

                // name
                $inp_name = H::input_text(array('name'=>$this->ifld_data_name, 'data-alwaysposted'=>1, 'value'=>$post[$this->ifld_data_name], 'label'=>$this->get_tl('deco-data-name')));
        
            $form->add_child([$inp_name->label_tag(), $inp_name]);

                // link
                $link = H::input_text(['name'=>$this->ifld_data_link, 'value'=>$post[$this->ifld_data_link], 'class'=>$this->css.'link', 'label'=>$this->get_tl('link')]);
                $label = $link->label_tag();
                $label->add_child( H::button_info(['class'=>$this->css.'btn_info'], $this->get_tl('info_link')) );

            $form->add_child([$label, $link]);

                // animation
                $opts_data = ['first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$this->prepared_anim];
                $select_animation = H::select(['name'=>$this->ifld_data_id_animation, 'class'=>$this->css.'select_animation', 'label'=>$this->get_tl('select_animation'), 'id'=>self::module_name.'_select_animation'.$this->dom_id, 'data-alwaysposted'=>1], $opts_data, $post[$this->ifld_data_id_animation]);
            
            $form->add_child( [$select_animation->label_tag(), $select_animation] );

                $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
                    $btn_save = H::submit_button(array('name'=>$this->input_action_identifier , 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save', 'title'=>$this->get_tl('tlc_save') ), $this->get_tl('tlc_save'));
                $block_btns->add_child([$btn_save]);
                if ($post[$this->ifld_data_id] > 0) {
                    $btn_delete = H::submit_button(array('class'=>$this->css.'btn_del button_delete', 'name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'title'=>$this->get_tl('tlc_del'), 'data-confirm'=>$this->get_tl('confirm_delete')), $this->get_tl('tlc_del'));
                    $block_btns->add_child([$btn_delete]);
                }

            $form->add_child($block_btns);

        $output->add_child( $form );

        if ($post[$this->ifld_data_id]){

            $container_block = H::DIV(['class'=>$this->css.'edit_block', 'id'=>self::module_name.'_edit_block'.$this->dom_id]);
            $post['current_block_name'] = $post[$this->ifld_data_block_name];
            $container_block->add_child( $this->edit_block($post) );
            $output->add_child( $container_block );

        }

        return $output;
    }

    public function edit_block(&$post){

        $output = H::group('deco_edit_block');
            // select block
            global $DB;
            $categ = \helPHP\modules\category\admin\Category::get_list('block');
            $list = [];
            $selected_block = false;
            foreach ($categ as $key => $line) {
                $q = 'SELECT block.id, block.name FROM '.$DB->table('block_data').' block, '.$DB->table('category_content').' categ';
                $q.=' WHERE categ.id_data=? AND block.id=categ.id_item AND categ.field_identifier="block" ORDER BY block.name';
                $blocks = $DB->prepared_query_list($q, 'i', [$line['id']]);
                foreach ($blocks as $ind => $block) {
                    $name = Language::get_name('block_data', $block['id']);
                    array_push($list, ['name'=>$block['name'], 'label'=>$name]);
                    if ($block['name'] == $post[$this->ifld_data_block_name]) $selected_block = $block;
                }
            }
            $opts_data = ['first_empty'=>true, 'label_key'=>'label', 'value_key'=>'name', 'options'=>$list];
            $select = H::select(['name'=>$this->ifld_data_block_name, 'class'=>$this->css.'select_block', 'label'=>$this->get_tl('select_block'), 'id'=>self::module_name.'_select_block'.$this->dom_id, 'data-alwaysposted'=>1], $opts_data, $post[$this->ifld_data_block_name], $this->input_action_identifier, $this->ACTION_CHANGE_BLOCK);

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>self::module_name.'_edit_block'.$this->dom_id, 'class'=>$this->css.'form_edit form_edit', 'dom_id'=>$this->dom_id]);
            $form->add_child( H::input_hidden(['name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id], 'data-alwaysposted'=>1]) );
            $form->add_child( H::input_hidden(['name'=>'current_block_name', 'value'=>$post['current_block_name'], 'data-alwaysposted'=>1]) );
            $form->add_child( [$select->label_tag(), $select] );

        $output->add_child( $form );

        // display block admin
        if ($selected_block !== false){

            $block_id = 0;
            if ($selected_block['name'] == $post['current_block_name']){
                if ($post[$this->ifld_data_id_block] > 0){
                    $block_id = $post[$this->ifld_data_id_block];
                } else {
                    $q = 'SELECT id_block FROM '.$this->bddt_data.' WHERE id=?';
                    $block_id = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_data_id]]);
                }
            }

            $block = \helPHP\modules\block\Bridge::load(self::module_name, [
                $this->ifld_data_id => $post[$this->ifld_data_id]
            ], $selected_block['name'], $block_id, $this->dom_id, true);

            $output->add_child( $block );
        }

        return $output;
    }

    public function save_block($post){
        global $DB;

        Utils::error_log($post);

        $q = 'SELECT block_name, id_block FROM '.$this->bddt_data.' WHERE id=?';
        $prev = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_data_id]]);
        if ($prev['block_name'] != $post['block_name']){
            $q = 'UPDATE '.$this->bddt_data.' SET block_name=?, id_block=? WHERE id=?';
            $DB->prepared_query($q, 'sii', [$post['block_name'], $post['block_id'], $post[$this->ifld_data_id]]);
        }
    }
    public function delete_block($post){
        global $DB;

        Utils::error_log($post);

        $q = 'UPDATE '.$this->bddt_data.' SET block_name="", id_block=0 WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
    }

    //----------------------------------------------------------------------------------------------------

    public function save(&$post) {
        global $DB;

        $post[$this->ifld_data_name] = str_replace(' ', '_', $post[$this->ifld_data_name]);

        if ($post[$this->ifld_data_id] == 0) {
            // création
            $q = 'INSERT INTO '. $this->bddt_data.' SET name=?, link=?, id_animation=?';
            $success = $DB->prepared_query($q, 'ssi', [$post[$this->ifld_data_name], $post[$this->ifld_data_link], $post[$this->ifld_data_id_animation]]);
            $post[$this->ifld_data_id] = $DB->last_insert_id();
        } else {
            // mise à jour
            $q = 'UPDATE '. $this->bddt_data.' SET name=?, link=?, id_animation=? WHERE id=?';
            $success = $DB->prepared_query($q, 'ssii', array($post[$this->ifld_data_name], $post[$this->ifld_data_link], $post[$this->ifld_data_id_animation], $post[$this->ifld_data_id]));
        }
    }

    public function delete(&$post){
        global $DB;

        $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_data_id]]);
    }

    public function display(&$post) {
        global $LANG;

        if (isset($post['id'])) {
            $post[$this->ifld_data_id] = $post['id'];

            $this->check_posted_data($post, 'deco_data', ['id', 'name']);

            $this->apply_bdd_data($post, 'deco_data', ['id', 'name']);

            $post[$this->ifld_data_content] = $LANG->load_long_translation_value($this->ifld_data_content, $post['id']);

            if (isset($post[$this->ifld_data_name])) {
                $this->css = str_replace(' ', '_', $post[$this->ifld_data_name]);
            }
            $this->dom_container .= '_'.$this->css;
            return $post[$this->ifld_data_content];
        }
    }
}