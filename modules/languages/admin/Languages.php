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
namespace helPHP\modules\languages\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\modules\media\admin\Media;

class Languages extends HelPHP_module {

    const module_name = 'languages';
    
    private $ACTION_NEW = self::module_name.'_new';
    private $ACTION_SAVE = self::module_name.'_save';
    private $ACTION_EDIT = self::module_name.'_edit';
    private $ACTION_DELETE = self::module_name.'_delete';
    private $ACTION_DISPLAY = self::module_name.'_display';

    private $config_path;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct($dom_container);

        global $CONFIG;
        $this->config_path = $CONFIG::HOME_FOLDER.'config/main.php';
    }
    public function process_data(&$post, $to_return = false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $DB, $USER;
        $master_output = H::group('languages_display');
        if ($USER->admin) {
            switch ($post[$this->input_action_identifier]) {
                case $this->ACTION_DISPLAY:
                    $master_output->add_child($this->display_flags($post));
                break;

                case $this->ACTION_EDIT:
                    $this->prepare_fields($post, 'languages_allowed');
                    $master_output->add_child( $this->form_select($post) );
                    $master_output->add_child( $this->form_allowed($post) );
                break;
                case $this->ACTION_NEW:
                    $post[$this->ifld_allowed_id] = 0;
                    $this->reset_fields($post, 'languages_allowed');
                    $master_output->add_child( $this->form_select($post) );
                    $master_output->add_child( $this->form_allowed($post) );
                break;
                case $this->ACTION_SAVE:
                    $this->check_posted_data($post,'languages_allowed');
                    $this->save($post);
                    $master_output->add_child( $this->form_select($post) );
                    $master_output->add_child( $this->form_allowed($post) );
                break;
                case $this->ACTION_DELETE:
                    $this->check_posted_data($post,'languages_allowed');
                    $this->delete($post);
                    $post[$this->ifld_allowed_id] = 0;
                    $this->reset_fields($post, 'languages_allowed');
                    $master_output->add_child( $this->form_select($post) );
                break;

                default:
                    $this->check_language_db();
                    $this->check_posted_data($post, 'languages_allowed');
                    
                    $master_output->add_child($this->form_select($post));
                break;
            }
        } else {
            $this->dom_container = '';
            $this->display->add_child( $this->display_flags($post) );
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }


    public function form_select($post) {
        global $DB;

        $q = 'SELECT DISTINCT a.id as id, d.own_language as name FROM '.$this->bddt_allowed.' a, '.$this->bddt_data.' d WHERE';
        $q.=' a.id_data=d.id ORDER BY d.own_language';
        $list = $DB->prepared_query_list($q);

        $output = H::group('select_deco');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
            if (isset($post[$this->ifld_allowed_id]) && $post[$this->ifld_allowed_id] > 0) {
                $title->add_child(H::SPAN(['class'=>$this->css.'info_id module_current_id'], $this->get_tl('tlc_id', $post[$this->ifld_allowed_id])));
            }

            $form = H::form(array('action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));

                $button_new = H::submit_button_single(array('class'=>$this->css.'btn_add button_new','name'=>$this->input_action_identifier, 'value'=>$this->ACTION_NEW, 'title'=>$this->get_tl('tlc_new')), $this->get_tl('tlc_new'));

                $options_data = array('first_empty'=>true , 'value_key'=>'id' , 'label_key'=>'name' , 'options'=>$list);
                $select = H::select(['name'=>$this->ifld_allowed_id, 'label'=>$this->get_tl('languageSelect')], $options_data, $post[$this->ifld_allowed_id], $this->input_action_identifier, $this->ACTION_EDIT);

            $form->add_child([$button_new, $select->label_tag(), $select]);

        $output->add_child([$title, $form]);

        // if (isset($post[$this->ifld_allowed_id]) && $post[$this->ifld_allowed_id] > 0) {
        //     $btn_preview = H::preview_button(self::module_name, $post[$this->ifld_allowed_id], $this->get_tl('tlc_preview'));
        //     $form->add_child($btn_preview);
        // }

        return $output;
    }
    // the form
    public function form_allowed($post) {

        $form = H::form(array('action'=>$this->get_index_relative_path() , 'class'=>$this->css.'form_edit form_edit', 'dom_target'=>$this->dom_target));

            $hidden_id = H::input_hidden(array('name'=>$this->ifld_allowed_id, 'value'=>$post[$this->ifld_allowed_id], 'data-alwaysposted'=>1));
            $selector_available = $this->language_data_selector_input($post[$this->ifld_allowed_id_data]);

        $form->add_child([$hidden_id, $selector_available->label_tag(), $selector_available]);

            // $label = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('drapeauxImg'));
            $params = ['accept'=>'image/*', 'label'=>$this->get_tl('drapeauxImg')];
            $process['process'] = [['type'=>'image_resize', 'max_width'=>200, 'max_height'=>200], ['type'=>'image_to_file', 'quality'=>80]];
            $input_file = Media::display('uploader', $params, $this->ifld_allowed_flag, $post[$this->ifld_allowed_id], $process);
            // Utils::error_log($input_file);
        $form->add_child([$input_file->label_tag(), $input_file]);

            // Submit button
            $block_btns = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
            if ($post[$this->ifld_allowed_id] == 0) {
                $block_btns->add_child(H::submit_button(array('name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save', 'title'=>$this->get_tl('tlc_save') ), $this->get_tl('add')));
            } else {
                $block_btns->add_child(H::submit_button(array('name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save button_save', 'title'=>$this->get_tl('tlc_save') ), $this->get_tl('modify')));
                $block_btns->add_child(H::submit_button(array('name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE, 'data-confirm'=>$this->get_tl('confirm_del') , 'class'=>$this->css.'btn_del button_delete', 'title'=>$this->get_tl('tlc_del')), $this->get_tl('delete')));
            }
            
        $form->add_child($block_btns);
        
        return $form;
    }
    // language selector
    public function language_data_selector_input($selected_id = null) {
        global $DB;
        
        $q = 'SELECT * FROM '.$this->bddt_data;
        $list = $DB->query_list($q);

        $options_data = array('first_empty'=>true , 'value_key'=>'id' , 'label_key'=>'en' , 'options'=>$list);
        return H::select(array('name'=>$this->ifld_allowed_id_data, 'label'=>$this->get_tl('languageDataSelect') ), $options_data, $selected_id);
    }
    public function save(&$post) {
        global $DB, $MEDIA;

        if ($post[$this->ifld_allowed_id] > 0){
            //get previous id_data :
            $q = 'SELECT DISTINCT id_data from '. $this->bddt_allowed.' WHERE id=?';
            $previous_id_data = $DB->prepared_query_value($q, 'i', array($post[$this->ifld_allowed_id]));
            if ($previous_id_data != $post[$this->ifld_allowed_id_data]) {
                //changing id_data
                $q = 'UPDATE '. $this->bddt_allowed.' SET id_data=? WHERE id=?';
                $DB->prepared_query($q, 'ii', array($post[$this->ifld_allowed_id_data], $post[$this->ifld_allowed_id]));
                
                //changing id_data in languages tables
                $q = 'UPDATE '.$DB->table('languages_short').' SET id_data=? WHERE id_data = ?';
                $DB->prepared_query($q, 'ii', array($post[$this->ifld_allowed_id_data],$previous_id_data));
                $q = 'UPDATE '.$DB->table('languages_long').' SET id_data=? WHERE id_data = ?';
                $DB->prepared_query($q, 'ii', array($post[$this->ifld_allowed_id_data],$previous_id_data));

                $this->update_config_languages();
            }
            
            $res = $MEDIA->process_media($post, $post[$this->ifld_allowed_id]);
            if (!$res) $this->add_error('media_error');
        } else {
            $q = 'INSERT INTO '.$this->bddt_allowed.' SET id_data=?';
            $DB->prepared_query($q, 'i', array($post[$this->ifld_allowed_id_data]));
            $post[$this->ifld_allowed_id] = $DB->last_insert_id();

            $res = $MEDIA->process_media($post, $post[$this->ifld_allowed_id]);
            if (!$res) $this->add_error('media_error');

            $this->update_config_languages();
        }
    }
    public function delete(&$post) {
        global $DB,$MEDIA;

        $q = 'DELETE FROM '.$this->bddt_allowed.' WHERE id = ?';
        $DB->prepared_query($q, 'i', array($post[$this->ifld_allowed_id] ));

        $q = 'DELETE FROM '.$DB->table('languages_long').' WHERE id_data = ?';
        $DB->prepared_query($q, 'i', array($post[$this->ifld_allowed_id_data] ));

        $q = 'DELETE FROM '.$DB->table('languages_short').' WHERE id_data = ?';
        $DB->prepared_query($q, 'i', array($post[$this->ifld_allowed_id_data] ));

        $MEDIA->delete_media($this->ifld_allowed_drapeau,$post[$this->ifld_allowed_id]);
        $this->update_config_languages();
    }

    public function check_language_db() {
        global $DB, $CONFIG;
        $q = $DB->query_value('SELECT count(*) from '.$this->bddt_allowed);
        //there is no languages in database
        if ($q==0) {
            $languages_from_config = $CONFIG::AVAILABLE_LANGUAGES;
            foreach ($languages_from_config as $languages_to_add) {
                $id_data = $DB->query_value('SELECT distinct id_data from '.$this->bddt_data.' where iso="'.$languages_to_add.'"');
                $DB->query('INSERT INTO '. $this->bddt_allowed.' SET id_data='.$id_data);
            }
        }
    }

    public function update_config_languages() {
        global $DB, $FS;
        $q = 'SELECT distinct '.$this->bddt_data.'.iso from '.$this->bddt_data.', '.$this->bddt_allowed.' where '.$this->bddt_allowed.'.id_data = '.$this->bddt_data.'.id';
        $langs = $DB->query_list($q);
        $config = file_get_contents($this->config_path);
        $config_beginning = explode('//>>>lang>>>', $config);
        $config_beginning = $config_beginning[0];
        $config_ending = explode('//<<<lang<<<', $config);
        $config_ending = $config_ending[1];
        $langs_beautified = \preg_replace('/(^ )/m', '  $1', var_export($langs, true));
        $langs_beautified = \preg_replace('/(^.)/m', '    $1', $langs_beautified);
        $new_config = $config_beginning.'//>>>lang>>>'.PHP_EOL.'    const AVAILABLE_LANGUAGES = '.$langs_beautified.'; '.PHP_EOL.'    //<<<lang<<<'.$config_ending;
        $FS->save_content($this->config_path, $new_config, true);

        // make constants
        global $CONFIG;
        shell_exec('php '.$CONFIG::HELPHP_FOLDER.'utils/constants.php '.$CONFIG::HOME_FOLDER);
    }

    public function display_flags($post) {
        global $DB,$LANG,$MEDIA;

        $output = H::DIV(['class'=>'languages_admin_flags_container']);

            $q = 'SELECT DISTINCT a.id_data as id_data, a.id, d.iso, d.own_language as own FROM '.$this->bddt_allowed.' a,'.$this->bddt_data.' d';
            $q.=' WHERE a.id_data=d.id ORDER BY d.iso';
            $flags = $DB->query_list($q);
            foreach ($flags as $flag) {
                // link
                $link = H::A(['href'=>'?language='.$flag['iso'],'class'=>$this->css.'link']);
                if ($flag['iso'] == $LANG->current_language) $link->add_class($this->css.'current');
                // media
                $media = $MEDIA->get_html($this->ifld_allowed_flag, $flag['id']);
                if ($media) {
                  $link->add_child($media);
                  $labelclass=$this->css.'txt';
                }else{
                    $labelclass=$this->css.'txt_no_media';
                }
                // label
                $label = H::SPAN(['class'=>$labelclass], $flag['own']);
                $link->add_child($label);
                
                $output->add_child($link);
            }

        return $output;
    }
}