<?php
/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */

namespace helPHP\modules\preview\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Utils;
use helPHP\modules\csseditor\admin\Csseditor;

class Preview extends HelPHP_module {

    const module_name = 'preview';

    // protected $ACTION_MODULE = self::module_name.'_module';
    protected $ACTION_UPDATE_SESSION = self::module_name.'_update_session';

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct();
    }
    
    public function process_data(&$post, $toreturn = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        // $this->inst_js = 'h.modules.'.self::module_name.'_a["'.$this->dom_id.'"]';

        $master_output = H::group('preview_display');
        switch($post[$this->input_action_identifier]){
            // case $this->ACTION_MODULE:
                // $master_output->add_child( $this->DisplayModule($post) );
            // break;
            case $this->ACTION_UPDATE_SESSION:
                if (isset($post['iso'])) $_SESSION['preview_language'] = $post['iso'];
                if (isset($post['theme'])){
                    if (isset($post['admin'])) $_SESSION['current_csseditor_theme_preview_admin'] = $post['theme'];
                    else $_SESSION['current_csseditor_theme_preview_public'] = $post['theme'];
                    $_SESSION['current_csseditor_theme'] = $post['theme'];
                }
            break;
            default:
                $master_output->add_child( $this->display($post) );
                // si les sous sections ont des affichages a ajouté à celui de base
            break;
        }
        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    public function display (&$post) {
        global $DB, $MEDIA, $LANG, $CONFIG;

        $modal = (isset($post['module']) && isset($post['id'])) ? true : false;
        $admin = (($post['prevmode'] ?? '') === 'admin') ? true : false;

        if ($modal) {
            $this->dom_container = '';
            $output = H::DIV(['class'=>$this->css.'in_modal', 'id'=>$this->dom_target]);
        }
        else $output = H::group('preview');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));

        $output->add_child( $title );

            $outils = H::DIV(['class'=>$this->css.'bar_outils disable', 'id'=>'preview_bar_outils'.$this->dom_id]);

                // languages flag
                $current_language = isset($_SESSION['preview_language']) ? $_SESSION['preview_language'] : $LANG->current_language;

                $q = 'SELECT DISTINCT a.id, a.id_data, d.iso, d.own_language as own FROM '.$DB->table('languages_allowed').' a';
                $q.=' LEFT JOIN '.$DB->table('languages_data').' d ON (d.id=a.id_data)';
                $languages = $DB->query_list($q);
                if (count($languages) > 1){
                    $btns = [];
                    $selected = 0;
                    foreach($languages as $key => $language){
                        $lang = H::DIV(['class'=>$this->css.'lang', 'onclick'=>$this->inst_js.'.change_lang(event);', 'data-iso'=>$language['iso'], 'id'=>self::module_name.'_lang_'.$language['iso'].$this->dom_id]);
                        if ($current_language == $language['iso']) $selected = $key;
                        
                        $lab = H::SPAN([], $language['own']);
                        $lang->add_child($lab);

                        $media = $MEDIA->get_html($this->build_module_field_name('languages', 'allowed', 'flag'), $language['id']);
                        if ($media) $lang->add_child($media);

                        array_push($btns, $lang);
                    }

                    $langs = H::multi_state_button(['class'=>$this->css.'sel_lang'], $btns, $selected, 'down');
                    $outils->add_child( $langs );
                }
                
                $btn_refresh = H::button_icon('refresh-ccw', ['class'=>$this->css.'refresh', 'id'=>'preview_refresh'.$this->dom_id, 'title'=>$this->get_tl('refresh')]);
                $btn_picker = H::button_icon_with_text('feather', 'right', $this->get_tl('module'), ['class'=>$this->css.'picker', 'id'=>'preview_picker'.$this->dom_id, 'title'=>$this->get_tl('picker')]);
                
                $radios[] = ['label'=>$this->get_tl('smartphone'), 'value'=>'smartphone'];
                $radios[] = ['label'=>$this->get_tl('tablet'), 'value'=>'tablet'];
                $radios[] = ['label'=>$this->get_tl('desktop'), 'value'=>'desktop'];
                $mode = H::input_multiple_radios(['name'=>'mode', 'values'=>$radios, 'class'=>$this->css.'switch_mode', 'selected'=>'desktop', 'callback'=>$this->inst_js.'.toggle_preview_mode(event);']);

                $current_theme = Csseditor::get_current_theme(true, $admin);
                // theme selection
                $q = 'SELECT id, CONCAT("'.$this->get_tl('select_theme').'", name) as name, id_source as "data-id_source" FROM '.$DB->table('csseditor_theme').' WHERE admin='.($admin ? 1 : 0);
                $themes = $DB->query_list($q);
                $opts_data = ['first_empty'=>false, 'label_key'=>'name', 'value_key'=>'id', 'options'=>$themes];
                $select_theme = H::select(['id'=>self::module_name.'_select_theme'.$this->dom_id, 'name'=>'preview_theme', 'onchange'=>$this->inst_js.'.change_theme(event);', 'class'=>$this->css.'select_theme'], $opts_data, $current_theme);

                $css_picker = H::BUTTON(['class'=>$this->css.'picker_css', 'id'=>'preview_picker_css'.$this->dom_id, 'title'=>$this->get_tl('picker_css')], $this->get_tl('picker_css'));

            if (!$modal) $outils->add_child([$btn_refresh,$btn_picker,$mode,$select_theme,$css_picker]);
            else $outils->add_child([$btn_refresh,$mode,$css_picker]);

            // title, what are we previewing
            $outils->add_child( $this->display_title($post) );
            
            $container_iframe = H::DIV(['id'=>'preview_container_iframe'.$this->dom_id, 'class'=>$this->css.'container_iframe desktop']);
            
            $params = [];
            if ($modal){
                $container_iframe->add_class('module');
                $params = (isset($post['action'])) ? $post : ['module'=>$post['module'], 'id'=>$post['id']];
            }
            $params['preview_is_admin'] = $admin;
            
            // need to get the selectors for the source that is linked to the preview
            global $DB;
            if (isset($post['css_source']) && $post['css_source']){
                $q = 'SELECT DISTINCT selector FROM '.$DB->table('csseditor_rules').' WHERE id_source = (SELECT id';
                $q.=' FROM '.$DB->table('csseditor_source').' WHERE type=? AND admin=?)';
                $selectors = $DB->prepared_query_list($q, 'si', [$post['css_source'], $admin]);
            } else {
                // $id_theme = \helPHP\modules\csseditor\admin\Csseditor::get_current_theme(true, $admin);
                $q = 'SELECT DISTINCT selector FROM '.$DB->table('csseditor_rules').' WHERE id_source = (SELECT id_source';
                $q.=' FROM '.$DB->table('csseditor_theme').' WHERE id='.$current_theme.')';
                $selectors = $DB->query_list($q);
            }
            $params['css_selectors'] = $selectors;
            $params['language'] = $current_language;
            $params['theme'] = $current_theme;
            // add connection to transfer connection to the context session of preview
            if (isset($_SESSION[\helPHP\libs\User::session_connection_data])) $params['co_hash'] = urlencode($_SESSION[\helPHP\libs\User::session_connection_data]);
            $translate = [
                'preview_new_rules' => $this->get_tl('preview_new_rules'),
                'preview_existing_rules' => $this->get_tl('preview_existing_rules'),
                'preview_element' => $this->get_tl('preview_element'),
                'add_rule_info' => $this->get_tl('add_rule_info')
            ];

            $js = 'helphp_timeout(\'Preview_a.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($params)).', '.\addslashes(json_encode($translate)).');\', 200);';
            if (isset($post['tab'])){
                $name = $this->get_tl('module_name');
                $js.= 'helphp_timeout("h.main_tab.add_name_to_tab('.$post['tab'].',\"'.htmlentities($name).'\")", 100);';
            }
            $script = H::script($js, ['autoremove'=>true]);

        $output->add_child([$outils, $container_iframe, $script]);

        return $output;
    }

    public function display_title($post){

        $css_source = (isset($post['css_source']) && $post['css_source']) ? $post['css_source'] : false;
        $admin = (($post['prevmode'] ?? '') === 'admin') ? true : false;

        $title = H::SPAN(['class'=>$this->css.'previewing_title']);

        if (!$css_source) $title->add_child( $this->get_tl('site_public') );
        else {
            $t = explode('¤', $css_source);
            $type = $t[0];
            $id = $t[1];
            if ($type == 'block'){
                global $DB;
                $name = Language::get_name('block_data', $id);
                $name = str_replace('¤', ' ', $name);
            } else if ($type == 'document'){
                $name = Language::get_name('document_data', $id);
            } else {
                $name = '';
                Utils::error_log('Unknown css source : ' .$css_source);
            }

            $title->add_child( $this->get_tl($type, $name) );
            if ($admin) $title->add_child( $this->get_tl('admin') );
            else $title->add_child( $this->get_tl('public') );
        }

        return $title;
    }
}