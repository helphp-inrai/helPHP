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
namespace helPHP\modules\indexation\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\modules\media\admin\Media as Media_UI;

class Indexation extends HelPHP_module {

    const module_name = 'indexation';

    protected $ACTION_DELETE = self::module_name.'_delete';
    protected $ACTION_SAVE = self::module_name.'_modify';
    protected $ACTION_SITEMAP = self::module_name.'_sitemap';
    
    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        global $DB;
        if (!isset($post[$this->ifld_data_module_name]) || !isset($post[$this->ifld_data_module_param])) {
            echo 'Error : No module_name or module_param provided';
            exit();
        } else {
            if (!isset($post[$this->ifld_data_id])) {
                $q = 'SELECT id FROM '.$this->bddt_data.' WHERE module_name=? AND module_param=?';
                $result = $DB->prepared_query_value($q, 'ss', array($post[$this->ifld_data_module_name],$post[$this->ifld_data_module_param]));
                if ($result) {
                    $post[$this->ifld_data_id] = $result;
                }
            }
        }

        $master_output = H::group('indexation_display');
        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_DELETE:
                $this->check_posted_data($post, 'indexation_data');
                $this->delete_indexation($post);
            break;
            case $this->ACTION_SAVE:
                $this->check_posted_data($post, 'indexation_data');
                $this->save_indexation($post);

                $master_output->add_child($this->edit_indexation($post));
            break;
            case $this->ACTION_SITEMAP:
                $this->create_sitemap($post);
            break;

            default:
                $this->prepare_fields($post, 'indexation_data');
                Language::load_translation_data($post, self::module_name, 'data');
                $master_output->add_child($this->edit_indexation($post));
            break;
        }
        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    public function edit_indexation($post) {

        $form = H::form(array('action'=>$this->get_index_relative_path(), 'class'=>$this->css.'form_edit form_edit', 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id));
            $checked = (isset($post[$this->ifld_data_activated]) && $post[$this->ifld_data_activated] == 1) ? 'checked' : '';
            $label_activate = H::SPAN(['class'=>$this->css.'label'], $this->get_tl('activated'));
            $activated = H::input_checkbox(array('data-alwaysposted'=>1, 'name'=>$this->ifld_data_activated, 'checked'=>$checked, 'value'=>1));


        $form->add_child([$label_activate, $activated]);
            $hidden_id = H::input_hidden(array('data-alwaysposted'=>1, 'name'=>$this->ifld_data_id, 'value'=>$post[$this->ifld_data_id]));
            $hidden_name = H::input_hidden(array('data-alwaysposted'=>1, 'name'=>$this->ifld_data_module_name, 'value'=>$post[$this->ifld_data_module_name]));
            $hidden_param = H::input_hidden(array('data-alwaysposted'=>1, 'name'=>$this->ifld_data_module_param, 'value'=>$post[$this->ifld_data_module_param]));
                    $form->add_child( [$hidden_id,$hidden_name,$hidden_param] );

            $tl_block = $this->translate_block($post, [$this->ifld_data_title, $this->ifld_data_keywords, $this->ifld_data_description], 'ssl', [2=>['no_tiny'=>true]]);

        $form->add_child($tl_block);
        

            $params = ['accept'=>'image/*', 'label'=>$this->get_tl('image'), 'list'=>true];
            $process['process'] = [['type'=>'image_to_file', 'quality'=>80]];
            $image = Media_UI::display('uploader', $params, $this->ifld_data_image, $post[$this->ifld_data_id], $process);

        $form->add_child( [$image->label_tag(), $image] );

            $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE, 'class'=>$this->css.'btn_save'], $this->get_tl('tlc_save'));
        
        $form->add_child($btn_save);

        return $form;

    }
    public function save_indexation(&$post, $create_sitemap = true) {
        global $DB, $MEDIA;

        if ($post[$this->ifld_data_id] > 0){
            $q = 'UPDATE '.$this->bddt_data.' SET activated=? WHERE id=? ';
            $DB->prepared_query_value($q, 'ii', [$post[$this->ifld_data_activated],$post[$this->ifld_data_id]]);
        } else {
            $q = 'INSERT INTO '.$this->bddt_data.' SET module_name=?, module_param=?, activated=?';
            $DB->prepared_query_value($q, 'ssi', [$post[$this->ifld_data_module_name], $post[$this->ifld_data_module_param], $post[$this->ifld_data_activated]]);
            $post[$this->ifld_data_id] = $DB->last_insert_id();
        }

        Language::save_translation_data($post, $post[$this->ifld_data_id]);

        $MEDIA->process_media($post, $post[$this->ifld_data_id]);
        
        if ($create_sitemap) {
            $this->create_sitemap();
        }
    }
    public function delete_indexation(&$post) {
        global $DB,$CONFIG;

        $result = null;

        if (isset($post[$this->ifld_data_id])) {
            $q = 'SELECT DISTINCT * FROM '.$this->bddt_data.' WHERE id=?';
            $result = $DB->prepared_query_line($q, 'i', array($post[$this->ifld_data_id]));
        } elseif (isset($post[$this->ifld_data_module_name]) && isset($post[$this->ifld_data_module_param])) {
            $q = 'SELECT DISTINCT * FROM '.$this->bddt_data.' WHERE module_name=? AND module_param=?';
            $result = $DB->prepared_query_line($q, 'ss', array($post[$this->ifld_data_module_name] , $post[$this->ifld_data_module_param]));
        }

        if (is_array($result)) {
            $file = $CONFIG::HOME_FOLDER.$result['image'];

            if (is_file($file)) {
                unlink($file);
            }

            Language::delete_short_translation_value($this->build_field_name('data', 'title'), $result['id']);
            Language::delete_short_translation_value($this->build_field_name('data', 'keywords'), $result['id']);
            Language::delete_long_translation_value($this->build_field_name('data', 'description'), $result['id']);

            $q = 'DELETE FROM '.$this->bddt_data.' WHERE id=?';

            $DB->prepared_query($q, 'i', array($result['id']));
        }
    }

    public function create_sitemap() {
        global $LANG,$DB,$CONFIG,$FS;

        $languages = $LANG->get_languages_data();

        //creating xml file
        $xml = '<?xml version="1.0" encoding="UTF-8"?';
        $xml.= '><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        $url = $CONFIG::BASE_URL;
        $date_time = date("Y-m-d", time());
        //baseurl
        $xml.= '<url>';
        $xml.= '<loc>'.$url.'</loc>';
        foreach ($languages as $lang_data) {
            $xml.= '<xhtml:link rel="alternate" hreflang="'.$lang_data['iso'].'" href="'.$url.'?language='.$lang_data['iso'].'" />';
        }
        $xml.= '<lastmod>'.$date_time.'</lastmod>';
        $xml.= '<priority>0.6</priority>';
        $xml.= '<changefreq>monthly</changefreq>';
        $xml.= '</url>';
        //items
        $q = 'SELECT * FROM '.$this->bddt_data.' WHERE activated=1';
        $list = $DB->query_list($q);
        foreach ($list as $elem) {
            if ($elem['module_name'] == 'document') {
                $route =$DB->prepared_query_value('SELECT route FROM '.$DB->table('document_data').' WHERE id=?', 'i', [$elem['module_param']]);
                if ($route != '') {
                    $xml.= '<url>';
                    $xml.= '<loc>'.$url.$route.'</loc>';
                    $xml.= '<lastmod>'.$date_time.'</lastmod>';
                    $xml.= '<priority>0.6</priority>';
                    $xml.= '<changefreq>monthly</changefreq>';
                    $xml.= '</url>';
                }
            }
            $xml.= '<url>';
            $xml.= '<loc>'.$url.'?'.$elem['module_name'].'='.$elem['module_param'].'</loc>';
            foreach ($languages as $lang_data) {
                $xml.='<xhtml:link rel="alternate" hreflang="'.$lang_data['iso'].'" href="'.$url.'?'.$elem['module_name'].'='.$elem['module_param'].'-'.$lang_data['iso'].'" />';
            }
            $xml.= '<lastmod>'.$date_time.'</lastmod>';
            $xml.= '<priority>0.6</priority>';
            $xml.= '<changefreq>monthly</changefreq>';
            $xml.= '</url>';
        }
        $xml.= '</urlset> ';

        // write to file
        $FS->save_content($CONFIG::HOME_FOLDER.'public/indexation/sitemap.xml', $xml, true);
    }

    public static function button($field_name, $field_id) {
        global $DB, $TLCOMMON;

        $q = 'SELECT id FROM '.$DB->table('indexation_data').' WHERE module_name=? AND module_param=?';
        $id = $DB->prepared_query_value($q, 'ss', [$field_name, $field_id]);

        $css = self::module_name.'_button_open';
        $css.= (!$id) ? ' look_at_me' : '';

        $js_params = [self::module_name.'_data-module_name'=>$field_name, self::module_name.'_data-module_param'=>$field_id];
        
        $label = isset($TLCOMMON['tlc_btn_indexation']) ? $TLCOMMON['tlc_btn_indexation'] : '{tlc_btn_indexation}';
        return H::BUTTON(['class'=>$css, 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($js_params).');'], $label);
    }
}