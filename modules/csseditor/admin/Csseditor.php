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

namespace helPHP\modules\csseditor\admin;

use helPHP\libs\H;
use helPHP\libs\Ajax;
use helPHP\libs\Utils;
use helPHP\libs\HelPHP_module;
use helPHP\libs\Media;
use helPHP\modules\media\admin\Media as Media_ui;

class Csseditor extends HelPHP_module {
    const module_name = 'csseditor';
    
    protected $mode = false;
    protected $medias, $variables;
    
    protected $ACTION_FORM_VARIABLE = self::module_name.'_form_variable';
    protected $ACTION_SAVE_VARIABLE = self::module_name.'_save_variable';
    protected $ACTION_REFRESH_VARIABLE = self::module_name.'_refresh_variable';
    protected $ACTION_REFRESH_ALL_VARIABLE = self::module_name.'_refresh_all_variable';
    protected $ACTION_DELETE_VARIABLE = self::module_name.'_delete_variable';
    protected $ACTION_DEFAULT_VARIABLE = self::module_name.'_restore_default_variable';
    
    // action des forms
    protected $ACTION_FORM_THEME = self::module_name.'_form_theme';
    protected $ACTION_SAVE_THEME = self::module_name.'_save_theme';
    protected $ACTION_DELETE_THEME = self::module_name.'_delete_theme';
    protected $ACTION_DEFAULT_THEME = self::module_name.'_default_theme';
    protected $ACTION_DOWNLOAD_THEME = self::module_name.'_download_theme';
    
    protected $ACTION_FORM_FONT = self::module_name.'_form_font';
    // protected $ACTION_FORM_FONT_EDIT = self::module_name.'_form_font_add';
    protected $ACTION_SAVE_FONT = self::module_name.'_save_font';
    protected $ACTION_DELETE_FONT = self::module_name.'_font_delete';
    // protected $ACTION_FONT_TO_THEME = self::module_name.'_font_to_theme';
    protected $ACTION_DEFAULT_FONT = self::module_name.'_restore_default_font';
    
    // protected $ACTION_FORM_SELECTOR = self::module_name.'_form_selector';
    protected $ACTION_SAVE_SELECTOR = self::module_name.'_save_selector';

    protected $ACTION_FORM_RULES = self::module_name.'_form_rules';
    protected $ACTION_LOAD_RULES = self::module_name.'_load_rules';
    protected $ACTION_SAVE_RULES = self::module_name.'_save_rules';
    protected $ACTION_DELETE_RULES = self::module_name.'_delete_rules';
    protected $ACTION_LIST_RULES = self::module_name.'_list_rules';
    protected $ACTION_DEFAULT_RULES = self::module_name.'_restore_default_rule';
    protected $ACTION_ORDER_RULE = self::module_name.'_change_order_rule';

    protected $ACTION_FORM_MEDIA = self::module_name.'_form_media';
    protected $ACTION_SAVE_MEDIA = self::module_name.'_save_media';
    protected $ACTION_DELETE_MEDIA = self::module_name.'_delete_media';
    protected $ACTION_DEFAULT_MEDIA = self::module_name.'_restore_default_media';

    protected $ACTION_FORM_KEYFRAMES = self::module_name.'_form_keyframes';
    protected $ACTION_SAVE_KEYFRAMES = self::module_name.'_save_keyframes';
    protected $ACTION_DELETE_KEYFRAMES = self::module_name.'_delete_keyframes';
    protected $ACTION_DEFAULT_KEYFRAMES = self::module_name.'_default_keyframes';

    protected $ACTION_FORM_MULTI_RULES = self::module_name.'_form_multi_rules';
    protected $ACTION_SAVE_MULTI_RULES = self::module_name.'_parse_rules';

    protected $ACTION_REFRESH_PRECOMPLETE = self::module_name.'_refresh_precomplete';

    protected $ACTION_EXTRACT = self::module_name.'_extract';

    protected $ACTION_FORM_IMPORT = self::module_name.'_form_import';
    protected $ACTION_IMPORT = self::module_name.'_import';
    protected $ACTION_UPLOAD_THEME = self::module_name.'_upload_theme';

    protected $preview = false;
    // protected $preview_admin = false;
    protected $force_admin_or_public = false;
    protected $is_admin = false;
    /**
     * Type of source to edit in this instance.
     * Can be theme, module, block or document
     */
    protected $source_type = 'theme';
    protected $source_params = false;

    protected $created_source = false;
    
    /**
     * Array of variables that are required for theme to work, can't be deleted and are added to every theme
     */
    const required_variables = [
        // spacing
        '--spacing-small'           => '.2em',
        '--spacing-small-alt'       => '.2em',
        '--spacing-medium'          => '.4em',
        '--spacing-medium-alt'      => '.4em',
        '--spacing-large'           => '.8em',
        '--spacing-large-alt'       => '.8em',

        // border
        '--border-radius-small'     => '4px',
        '--border-radius-medium'    => '12px',
        '--border-radius-large'     => '20px',

        '--border-color'            => '#000',
        '--border'                  => '1px solid var(--back-color)',

        // color
        '--color-primary'           => '#3b5621',
        '--color-primary-alt'       => '#17260e',
        '--color-secondary'         => '#136b69',
        '--color-secondary-alt'     => '#268d9e',
        '--color-tertiary'          => '#243c58',
        '--color-tertiary-alt'      => '#556b8a',

        '--color-warning'           => '#7b383c',
        '--color-success'           => '#004779',

        '--color-detail'            => '#a7b33c',
        '--color-detail-alt'        => '#777a46',

        // font
        '--font-color'              => '#ffffff',
        '--font-color-alt'          => '#000000',

        '--font-color-warning'      => '#ffffff',
        '--font-color-success'      => '#ffffff',
        
        '--font-size-small'         => '.8em',
        '--font-size'               => '1em',
        '--font-size-large'         => '1.4em',
        
        // background
        '--background1'             => '#bbbbbb',
        '--background2'             => 'var(--color-primary)',
        '--background3'             => 'var(--color-tertiary)',
        '--background4'             => 'var(--color-secondary)',

        // size
        '--banner-size'             => 'calc(50px * var(--fhd-hr))',
        '--main-content-size'       => 'calc(860px * var(--fhd-vr))'
    ];

    protected $root_module = true;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
        // exécution de la classe parent qui initialise la langue et les données de traduction
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $to_return = false) {
        if (parent::process_data($post) == false) {
            // utilisateur non autorisé sur ce module
            return null;
        }

        $master_output = H::group('users_display');
        
        if (isset($_GET[$this->input_action_identifier])){
            // GET call for download
            $post = array_merge($post,$_GET);
            $this->dom_container = '';
        }

        $this->preview = isset($post['preview']) ? $post['preview'] : $this->preview;

        $this->force_admin_or_public = isset($post['force_admin_or_public']) ? $post['force_admin_or_public'] : $this->force_admin_or_public;
        $this->is_admin = isset($post['admin']) ? $post['admin'] : $this->is_admin;

        // $this->source_type = isset($_SESSION['csseditor_source'.$this->dom_id]) ? $_SESSION['csseditor_source'.$this->dom_id] : $this->source_type;
        $this->source_type = isset($post['source']) ? $post['source'] : $this->source_type;
        if (str_contains($this->source_type, '¤')) {
            $t = explode('¤', $this->source_type);
            $this->source_type = $t[0];
            $this->source_params = $t[1];
        }
        
        switch ($post[$this->input_action_identifier]) {
            
            case $this->ACTION_FORM_VARIABLE:
                $master_output->add_child($this->form_variable($post));
            break;
            case $this->ACTION_SAVE_VARIABLE:
                $master_output->add_child($this->save_variable($post));
            break;
            case $this->ACTION_REFRESH_VARIABLE:
                $master_output->add_child($this->refresh_variable($post));
            break;
            case $this->ACTION_REFRESH_ALL_VARIABLE:
                $master_output->add_child($this->display_variable_list($post));
            break;
            case $this->ACTION_DELETE_VARIABLE:
                $master_output->add_child($this->delete_variable($post));
            break;
            case $this->ACTION_DEFAULT_VARIABLE:
                $master_output->add_child($this->restore_default_variable($post));
            break;
            
            // --------------------------------------------------------------------------------
            
            case $this->ACTION_FORM_THEME:
                $master_output->add_child($this->form_theme_edit($post));
            break;
            case $this->ACTION_SAVE_THEME:
                $master_output->add_child($this->save_theme($post));
            break;
            case $this->ACTION_DELETE_THEME:
                $master_output->add_child($this->delete_theme($post));
            break;
            case $this->ACTION_DEFAULT_THEME:
                $master_output->add_child( $this->restore_default_theme($post) );
            break;
            
            // --------------------------------------------------------------------------------

            case $this->ACTION_FORM_MEDIA:
                $master_output->add_child( $this->form_media($post) );
            break;
            case $this->ACTION_SAVE_MEDIA:
                $master_output->add_child($this->save_media($post));
            break;
            case $this->ACTION_DELETE_MEDIA:
                $master_output->add_child($this->delete_media($post));
            break;
            case $this->ACTION_DEFAULT_MEDIA:
                $master_output->add_child($this->restore_default_media($post));
            break;
            
            // --------------------------------------------------------------------------------

            case $this->ACTION_FORM_FONT:
                $master_output->add_child($this->form_fonts($post));
            break;
            // case $this->ACTION_FORM_FONT_EDIT:
            //     $master_output->add_child($this->form_font_add($post));
            // break;
            case $this->ACTION_SAVE_FONT:
                $master_output->add_child($this->save_font($post));
            break;
            // case $this->ACTION_FONT_TO_THEME:
            //     $master_output->add_child($this->font_to_theme($post));
            // break;
            case $this->ACTION_DELETE_FONT:
                $master_output->add_child($this->delete_font($post));
            break;
            case $this->ACTION_DEFAULT_FONT:
                $master_output->add_child( $this->restore_default_font($post) );
            break;
            
            // --------------------------------------------------------------------------------
            
            case $this->ACTION_FORM_KEYFRAMES:
                $master_output->add_child( $this->form_keyframes($post) );
            break;
            case $this->ACTION_SAVE_KEYFRAMES:
                $master_output->add_child( $this->save_keyframes($post) );
            break;
            case $this->ACTION_DELETE_KEYFRAMES:
                $master_output->add_child( $this->delete_keyframes($post) );
                $master_output->add_child( H::script('if ('.$this->inst_js.'.preview) h.modules.preview_a['.$this->inst_js.'.dom_id].refresh_iframe();', ['autoremove'=>true]) );
            break;
            case $this->ACTION_DEFAULT_KEYFRAMES:
                $master_output->add_child( $this->restore_default_keyframes($post) );
                $master_output->add_child( H::script('if ('.$this->inst_js.'.preview) h.modules.preview_a['.$this->inst_js.'.dom_id].refresh_iframe();', ['autoremove'=>true]) );
            break;

            // --------------------------------------------------------------------------------

            case $this->ACTION_FORM_MULTI_RULES:
                $master_output->add_child( $this->form_multi_rules($post) );
            break;
            case $this->ACTION_SAVE_MULTI_RULES:
                $master_output->add_child( $this->save_multi_rules($post) );
            break;
            
            // case $this->ACTION_FORM_SELECTOR:
            //     $master_output->add_child( $this->form_selector($post) );
            // break;
            case $this->ACTION_SAVE_SELECTOR:
                $master_output->add_child( $this->save_selector($post) );
            break;
            case $this->ACTION_FORM_RULES:
                $master_output->add_child( $this->form_rules($post) );
            break;
            case $this->ACTION_LOAD_RULES:
                $master_output->add_child( $this->load_rules($post) );
            break;
            case $this->ACTION_SAVE_RULES:
                $master_output->add_child( $this->save_rules($post) );
            break;
            case $this->ACTION_LIST_RULES:
                $master_output->add_child( $this->form_list_rules($post) );
            break;
            case $this->ACTION_DELETE_RULES:
                $master_output->add_child( $this->delete_rules($post) );
            break;
            case $this->ACTION_DEFAULT_RULES:
                $master_output->add_child( $this->restore_default_rules($post) );
            break;
            case $this->ACTION_ORDER_RULE:
                $master_output->add_child( $this->change_order_rule($post) );
            break;

            case $this->ACTION_REFRESH_PRECOMPLETE:
                $master_output->add_child( $this->input_precomplete_rules($post) );
            break;

            case $this->ACTION_EXTRACT:
                $master_output->add_child( $this->prepare_extract($post) );
            break;
            case $this->ACTION_DOWNLOAD_THEME:
                $master_output->add_child( $this->download_theme($post) );
            break;

            case $this->ACTION_FORM_IMPORT:
                $master_output->add_child( $this->form_import($post) );
            break;
            case $this->ACTION_IMPORT:
                $master_output->add_child( $this->import($post) );
            break;
            case $this->ACTION_UPLOAD_THEME:
                $master_output->add_child( $this->upload_theme($post) );
            break;
            
            default:
                $master_output->add_child($this->display_editor($post));
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    public static function get_current_theme($preview, $admin, $force_admin_or_public = false){
        global $CONFIG;
        $id_theme = 0;
        if ($preview) {
            if ($admin) $id_theme = isset($_SESSION['current_csseditor_theme_preview_admin']) ? $_SESSION['current_csseditor_theme_preview_admin'] : $CONFIG::THEME_ID_ADMIN;
            else $id_theme = isset($_SESSION['current_csseditor_theme_preview_public']) ? $_SESSION['current_csseditor_theme_preview_public'] : $CONFIG::THEME_ID;
        } else if ($force_admin_or_public){
            if ($admin) $id_theme = isset($_SESSION['current_csseditor_theme_admin']) ? $_SESSION['current_csseditor_theme_admin'] : $CONFIG::THEME_ID_ADMIN;
            else $id_theme = isset($_SESSION['current_csseditor_theme_public']) ? $_SESSION['current_csseditor_theme_public'] : $CONFIG::THEME_ID;
        } else {
            if (isset($_SESSION['current_csseditor_theme']) && $_SESSION['current_csseditor_theme'] > 0) $id_theme = $_SESSION['current_csseditor_theme'];
            else if ($admin) $id_theme = $CONFIG::THEME_ID_ADMIN;
            else $id_theme = $CONFIG::THEME_ID;
        }
        return $id_theme;
    }
    public function display_editor(&$post) {
        global $CONFIG;

        $output = H::group('editor');
            
            $params = [
                'preview'=>$this->preview,
                'source' => $this->source_type.($this->source_params ? '¤'.$this->source_params : ''),
            ];
            $js = 'helphp_timeout(\'Csseditor_a.create_instance("'.$this->dom_id.'", '.addslashes(json_encode($params)).');\');';
            $script = H::script($js, ['autoremove'=>1]);

        $output->add_child($script);

        // title 
        $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));
        $output->add_child($title);

        // get the current theme id
        $post[$this->ifld_theme_id] = self::get_current_theme($this->preview, $this->is_admin, $this->force_admin_or_public);
        $this->prepare_fields($post, 'csseditor_theme');

        // set source admin as theme admin for next methods
        $post[$this->ifld_source_admin] = $post[$this->ifld_theme_admin];

        // retrieve the source id and set in the post (may be 0)
        switch ($this->source_type){
            case 'theme':
                $post[$this->ifld_source_id] = $post[$this->ifld_theme_id_source];
                $output->add_child( $this->select_theme($post) );
            break;

            case 'module':
                $post[$this->ifld_source_id] = 0;
                $output->add_child( $this->select_module($post) );
            break;
            
            case 'block':
            case 'document':
                // theme selection
                $output->add_child( $this->select_theme($post) );

                global $DB;
                $q = 'SELECT id FROM '.$this->bddt_source.' WHERE type=? AND admin=?';
                $id = $DB->prepared_query_value($q, 'si', [$this->source_type.'¤'.$this->source_params, $post[$this->ifld_source_admin]]);
                $post[$this->ifld_source_id] = $id ? $id : 0;
                if ($post[$this->ifld_source_id] > 0){
                    $js = $this->inst_js.'.source = '.json_encode([
                        'id'=>$post[$this->ifld_source_id],
                        'name'=>$this->source_type.'¤'.$this->source_params
                    ]).';';
                    $output->add_child(H::script($js, ['autoremove'=>'autoremove']));
                }
            break;
            default:
                Utils::error_log('source type unknown : '.$this->source_type);
            break;
        }

        $div = H::DIV(['class'=>$this->css.'oversize_content']);
        
            $grid_left = H::DIV(['class'=>$this->css.'grid_left']);
            // rules selection
            $grid_left->add_child($this->form_select_rules($post));
            // rules edition
            $grid_left->add_child( $this->form_rules($post) );

        $div->add_child( $grid_left );

            // multiple rules edition
            $grid_right = H::DIV(['class'=>$this->css.'grid_right']);
                // rules / variable / media / font
                $tabs = [$this->get_tl('add_multiple_rules'), $this->get_tl('variables'), $this->get_tl('fonts'), $this->get_tl('keyframes')];
                $contents = [$this->form_multi_rules($post), $this->display_variable_list($post), $this->form_fonts($post), $this->form_keyframes($post)];
            $grid_right->add_child( H::tabs(null, $tabs, $contents) );
        
        $div->add_child( $grid_right );

        $output->add_child( $div );

        return $output;
    }
    
    public function select_module(&$post){
        global $CONFIG, $DB;

        $q = 'SELECT id, path FROM '.$this->bddt_source.' WHERE type LIKE "module"';
        $lst_existing = $DB->query_list($q);
        $lst_existing = array_combine(array_column($lst_existing, 'path'), array_column($lst_existing, 'id'));

        $output = H::DIV(['class'=>$this->css.'subcontainer select_module']);

            $lst = $CONFIG::MODULES_LIST;
            $opts = [];
            foreach($lst as $module_name => $useless_data){
                // if (in_array($module_name, $CONFIG::MODULES_BASIC)) continue;

                $label = $this->get_translated_text_from_other_module($module_name,true,'module_name');
                $label = !str_contains($label, '{') ? ucfirst($label) : ucfirst($module_name);

                $post['source_module'] = $module_name;
                
                $post[$this->ifld_source_admin] = 1;
                $path_admin = $this->get_source_path($post);
                $id_admin = 0;
                if (key_exists($path_admin, $lst_existing)) $id_admin = $lst_existing[$path_admin];
                
                $post[$this->ifld_source_admin] = 0;
                $path_public = $this->get_source_path($post);
                $id_public = 0;
                if (key_exists($path_public, $lst_existing)) $id_public = $lst_existing[$path_public];

                array_push($opts,['value'=>$id_admin,'label'=>$label, 'data-name'=>$module_name, 'data-id_public'=>$id_public]);
            }
            usort($opts, fn($a, $b) => $a['label'] <=> $b['label']);


            $post['module'] = isset($post['module']) ? $post['module'] : '';
            $post['module_admin'] = isset($post['module_admin']) ? $post['module_admin'] : 1;
            $post[$this->ifld_source_admin] = $post['module_admin'];

            $options_data = array('first_empty'=>true, 'value_key'=>'value', 'label_key'=>'label', 'options'=>$opts);
            $select = H::select(['name'=>'source_module', 'label'=>$this->get_tl('module'), 'class'=>$this->css.'select module', 'onchange'=>$this->inst_js.'.on_change_module(event);', 'id'=>self::module_name.'_module_select'.$this->dom_id], $options_data, $post['module']);

            $admin = H::input_checkbox(['id'=>self::module_name.'_module_is_admin'.$this->dom_id, 'name'=>$this->ifld_source_admin, 'label'=>$this->get_tl('admin'), 'value'=>1, 'onchange'=>$this->inst_js.'.on_change_module(event);', 'checked'=>$post['module_admin']]);

        $output->add_child( [$select->label_tag(), $select, $admin->label_tag(), $admin] );

            $action_buttons = H::DIV(['class'=>$this->css.'action_buttons action_buttons']);
                $extract_all = H::BUTTON(['class'=>$this->css.'btn tool extract', 'onclick'=>$this->inst_js.'.extract_into_file("all_module");'], $this->get_tl('extract_all_module'));
                $extract_current = H::BUTTON(['class'=>$this->css.'btn tool extract', 'onclick'=>$this->inst_js.'.extract_into_file("module");'], $this->get_tl('extract_module'));
            $action_buttons->add_child( [$extract_all, $extract_current] );
        
        $output->add_child( $action_buttons );
        
        return $output;
    }

    /**
     * update selector each rule's media
     */
    public function save_selector($post) {
        global $DB;

        $this->check_posted_data($post, 'csseditor_source', ['id']);
        $this->check_posted_data($post, 'csseditor_rules', ['selector']);

        // Utils::error_log($post);

        if ($post['previous_selector'] === $post[$this->ifld_rules_selector]) return false;

        // if ($this->source_type == 'module' && $post[$this->ifld_source_id] <= 0) return false;

        $q = 'SELECT DISTINCT id, initial, active FROM '.$this->bddt_rules.' WHERE selector LIKE ? AND id_source = ? AND active > -1';
        $lst = $DB->prepared_query_list($q, "si", [$post['previous_selector'], $post[$this->ifld_source_id]]);
        if ($lst){
            foreach($lst as $line){
                if ($line['initial']){
                    // make a copy
                    $this->copy_rule($line['id']);
                }
                $q = 'UPDATE '.$this->bddt_rules.' SET selector=? WHERE id='.$line['id'];
                $DB->prepared_query($q,'s',[$post[$this->ifld_rules_selector]]);
            }
        } else {
            $id_source = $post[$this->ifld_source_id] ?: $this->create_source($post);
            $this->save_or_update_class($post[$this->ifld_rules_selector], 'force', $id_source, 1, 1);
        }

        // Utils::error_log($id_source);
        // Utils::error_log($post[$this->ifld_source_id]);
        // Utils::error_log((isset($id_source) ? $id_source : $post[$this->ifld_source_id]));

        return (isset($id_source) ? $id_source : $post[$this->ifld_source_id]);
    }
    public function copy_rule($id_rule){
        global $DB;

        // copy row
        $id_copy = $DB->duplicate_line($this->bddt_rules, $id_rule, ['active'=>0]);

        $q = 'UPDATE '.$this->bddt_rules.' SET initial=0, id_initial='.$id_copy.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$id_rule]);

        return $id_copy;
    }
    /**
     * Display the precomplete select for the rules
     */
    public function form_select_rules($post){

        $output = H::DIV(['class'=>$this->css.'subcontainer select_rule']);

            $form =  H::form(['action'=>$this->get_index_relative_path(),'dom_target'=>'','class'=>$this->css.'form select_rules','dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');

                $precomp = $this->input_precomplete_rules($post);
                
                $btn_list = H::BUTTON(['class'=>$this->css.'btn list_rules','onclick'=>$this->inst_js.'.open_list_rules(event);'],$this->get_tl('list_rules'));

            $form->add_child([$precomp,$btn_list]);
        
        $output->add_child($form);

        return $output;
    }
    public function input_precomplete_rules($post){
        global $DB;

        // useless is needed for value_key and label_key to work properly
        $q = 'SELECT DISTINCT selector, "useless" FROM '.$this->bddt_rules.' WHERE id_source = ? AND active > -1 AND id NOT IN ';
        $q.= '(SELECT DISTINCT id_initial FROM '.$this->bddt_rules.') ORDER BY active DESC, selector ASC, "sort_order" ASC';
        $data_rules = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_source_id]]);
        
        $lst_rules = ['data'=>$data_rules, 'value_key'=>'selector', 'label_key'=>'selector'];
        $attributes = [
            'id'=>self::module_name.'_rules_precomplete'.$this->dom_id,
            'dom_id'=>$this->dom_id, 'name'=>$this->ifld_rules_selector,
            'label'=>$this->get_tl('select_rules'),
            'class'=>$this->css.'choice_rules',
            'placeholder'=>$this->get_tl('placeholder_search_rules'),
            'empty'=>$this->get_tl('empty_rules_label')
        ];
        $precomp = H::input_precomplete($attributes, $lst_rules, false,$this->inst_js.'.load_rules');

        return $precomp;
    }
    /**
     * Display the input for editing properties of a rule for each media
     */
    public function form_rules($post) {

        $output = H::DIV(['class'=>$this->css.'subcontainer edit_rule', 'id'=>self::module_name.'_subcontainer_edit_rules'.$this->dom_id]);

            $form =  H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'', 'class'=>$this->css.'form edit_rules', 'dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');
                
                $div_current = H::DIV(['class'=>$this->css.'current_selector']);
                    // $active = H::input_checkbox(['name'=>$this->ifld_rules_active, 'value'=>1, 'title'=>$this->get_tl('inp_active_rules')]);
                    $btn_new = H::button_icon('plus', ['id'=>self::module_name.'_btn_prepare_add_one_rule'.$this->dom_id, 'onclick'=>$this->inst_js.'.prepare_add_one_rule();', 'class'=>$this->css.'btn_new add_one_rule hidden']);
                    $selector = H::input_text(['name'=>$this->ifld_rules_selector, 'value'=>'', 'autocomplete'=>'off', 'class'=>$this->css.'input_text selector', 'id'=>self::module_name.'_current_selector'.$this->dom_id, 'placeholder'=>$this->get_tl('plh_current_selector')]);
                    $btn_save_rule = H::button_icon('save', ['class'=>$this->css.'btn_save button_save selector disabled', 'disabled'=>1, 'onclick'=>$this->inst_js.'.save_selector(event);', 'id'=>self::module_name.'_btn_save_selector'.$this->dom_id]);
                    $btn_del_rule = H::button_icon('x', ['class'=>$this->css.'btn_del button_delete rules disabled', 'disabled'=>1, 'onclick'=>$this->inst_js.'.delete_rules(event);', 'id'=>self::module_name.'_btn_del_rules'.$this->dom_id, 'data-confirm'=>$this->get_tl('ask_del_rule')]);
                $div_current->add_child( [$btn_new, $selector, $btn_save_rule, $btn_del_rule] );
                //     $current_selector = H::SPAN(['class'=>$this->css.'current_selector_label','id'=>self::module_name.'_current_selector'.$this->dom_id]);
                //     $btn_edit_selector = H::BUTTON(['class'=>$this->css.'btn edit_selector disabled','disabled'=>1,'onclick'=>$this->inst_js.'.openEditSelector(event);','id'=>self::module_name.'_btn_edit_selector'.$this->dom_id],$this->get_tl('open_edit_selector'));
                //     $btn_del_rules = H::BUTTON(['class'=>$this->css.'btn_del rules disabled','disabled'=>1,'onclick'=>$this->inst_js.'.delete_rules(event);','id'=>self::module_name.'_btn_del_rules'.$this->dom_id, 'data-confirm'=>$this->get_tl('ask_del_rule')],$this->get_tl('del_rules'));
                // $div_current->add_child([$btn_edit_selector,$current_selector,$btn_del_rules]);
            
            $form->add_child($div_current);

            $tools = H::DIV(['class'=>$this->css.'tools']);
                $calc = H::BUTTON(['class'=>$this->css.'btn tool calc','onclick'=>$this->inst_js.'.tool_calc(event);'], $this->get_tl('calc'));
            $tools->add_child($calc);
            $form->add_child($tools);

            $this->get_medias_list();
            foreach($this->medias as $key => $media){
                if ($media['active'] == 0) continue;
                $detail = H::DETAILS(['class'=>$this->css.'media_detail', 'data-id'=>$media['id']]);
                    $summary = H::SUMMARY(['class'=>$this->css.'media_summary']);
                        $name = H::SPAN(['class'=>$this->css.'media_name'], $media['name']);
                        $btn_default = H::BUTTON(['class'=>$this->css.'btn default_rule hidden', 'id'=>self::module_name.'_rule_default_'.$media['id'].$this->dom_id, 'onclick'=>$this->inst_js.'.restore_default_rule('.$media['id'].');'], $this->get_tl('tlc_default'));
                        $active = H::input_checkbox(['id'=>self::module_name.'_rule_active_'.$media['id'].$this->dom_id, 'name'=>'active['.$media['id'].']', 'value'=>1, 'class'=>$this->css.'inp_check active', 'label'=>$this->get_tl('active')]);
                    $summary->add_child( [$name, $btn_default, $active->label_tag(), $active] );
                    $value = H::DIV(['class'=>$this->css.'media_value'], $media['data-value']);
                    $textarea = H::input_textarea(['id'=>self::module_name.'_rule_property_'.$media['id'].$this->dom_id, 'data-id'=>$media['id'], 'name'=>'property['.$media['id'].']', 'class'=>$this->css.'textarea_media', 'spellcheck'=>'false', 'data-value'=>$media['data-value']]);
                $detail->add_child( [$summary, $value, $textarea] );
                $form->add_child($detail);
                // $div_media = H::DIV(['class'=>$this->css.'media']);
                //     $id_toggle = self::module_name.'_toggle_media'.$media['id'].$this->dom_id;
                //     $name = H::DIV(['class'=>$this->css.'media_name', 'data-target_id'=>$id_toggle], $media['name']);
                //     $div = H::DIV(['class'=>$this->css.'media_toggle', 'id'=>$id_toggle]);
                //         $value = H::DIV(['class'=>$this->css.'media_value'], $media['data-value']);
                //         $textarea = H::input_textarea(['data-id'=>$media['id'],'id'=>self::module_name.'_input_media'.$media['id'].$this->dom_id, 'name'=>'media['.$media['id'].']', 'class'=>$this->css.'textarea_media', 'spellcheck'=>'false', 'data-value'=>$media['data-value']]);
                //     $div->add_child([$value,$textarea]);
                // $div_media->add_child([$name,$div]);
                // $form->add_child($div_media);
            }
            // $script = H::script('H_ui.toggle_accordion("'.$this->css.'media_name'.'","hidden");');
            // $form->add_child($script);

            $btn_save = H::BUTTON(['disabled'=>1,'class'=>$this->css.'button_save button_save rules','onclick'=>$this->inst_js.'.save_rules(event);','id'=>self::module_name.'_btn_save_rules'.$this->dom_id], $this->get_tl('tlc_save'));
            $form->add_child($btn_save);

        $output->add_child($form);

        return $output;
    }
    /**
     * Display the list of all the rules
     */
    public function form_list_rules($post) {
        global $DB;

        $this->check_posted_data($post,'csseditor_rules',['id_media']);
        $this->check_posted_data($post,'csseditor_source',['id']);
        if (!$post[$this->ifld_rules_id_media]) {
            $post[$this->ifld_rules_id_media] = 1;
        }
        
        $q = 'SELECT DISTINCT id, selector, `sort_order` FROM '.$this->bddt_rules.' WHERE id_media=? AND id_source=? AND active > -1 AND';
        $q.=' id NOT IN (SELECT id_initial FROM '.$this->bddt_rules.') ORDER BY active ASC, `sort_order` ASC, selector ASC';
        $lst = $DB->prepared_query_list($q, 'ii', [$post[$this->ifld_rules_id_media], $post[$this->ifld_source_id]]);

        $output = H::group('list_rules');

            $ttl = H::DIV(['class'=>$this->css.'title_modal list module_title'], $this->get_tl('ttl_list'));
            $info = H::DIV(['class'=>$this->css.'info_action list'], $this->get_tl('info_list'));

        $output->add_child([$ttl, $info]);

            $form = H::form(['action'=>$this->get_index_relative_path(),'dom_target'=>'popup_modal_content','class'=>$this->css.'form list_rules','dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');

                $this->get_medias_list();
                $opts_data = ['label_key'=>'name','value_key'=>'id','options'=>$this->medias];
                $select = H::select(['name'=>$this->ifld_rules_id_media,'class'=>$this->css.'select media','label'=>$this->get_tl('select_media_list')],$opts_data,$post[$this->ifld_rules_id_media],$this->input_action_identifier,$this->ACTION_LIST_RULES);

            $form->add_child([$select->label_tag(),$select]);

                $div_lst = H::DIV(['class'=>$this->css.'lst_rules']);
                foreach ($lst as $key => $rule){
                    $div = H::DIV(['class'=>$this->css.'lst_rules_elem','onclick'=>$this->inst_js.'.load_rules(event, "'.addslashes($rule['selector']).'");H_ui.popup_modal.hide();', 'data-order_parent'=>$this->ifld_rules_sort_order.'['.$rule['id'].']']);
                        // $sort_order = H::quick_edit($form,$rule['id'],$this->ifld_rules_order,['class'=>$this->css.'rule_order','dom_id'=>$this->dom_id,'id'=>self::module_name.'_rule_order_'.$key, 'min'=>'0'],$rule['sort_order'],'int');
                        $sort_order = H::input_order(['class'=>$this->css.'rule_order', 'name'=>$this->ifld_rules_sort_order.'['.$rule['id'].']', 'value'=>$rule['sort_order']], true, $this->inst_js.'.change_rule_order');
                        $selector = H::SPAN(['class'=>$this->css.'selector'], $rule['selector']);
                    $div->add_child([$sort_order,$selector]);
                    $div_lst->add_child($div);
                }

            $form->add_child($div_lst);

        $output->add_child($form);

        return $output;
    }
    /**
     * retrieve all existing media
     */
    public function get_medias_list() {
        global $DB;
        
        if ($this->medias) return;
        $q = 'SELECT DISTINCT id, name, value as "data-value", `sort_order`, active FROM '.$this->bddt_media.' WHERE id NOT IN (';
        $q.= 'SELECT DISTINCT id_initial FROM '.$this->bddt_media.') AND active<>-1 ORDER BY `sort_order`, name ASC';
        $this->medias = $DB->query_list($q);
    }
    /**
     * display media edit form
     */
    public function form_media($post) {
        $this->prepare_fields($post,'csseditor_media');

        // retrieve max sort_order if new media
        if (!$post[$this->ifld_media_id]){
            global $DB;
            $q = 'SELECT MAX(`sort_order`) FROM '.$this->bddt_media;
            $post[$this->ifld_media_sort_order] = $DB->query_value($q) + 1;
            $post[$this->ifld_media_active] = 1;
        }

        $output = H::group('form_media');

            $ttl = H::DIV(['class'=>$this->css.'title_modal media module_title'], $this->get_tl('ttl_media'));
            $info = H::DIV(['class'=>$this->css.'info_action media'], $this->get_tl('info_media'));

        $output->add_child([$ttl, $info]);

            // form select
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'popup_modal_content', 'class'=>$this->css.'form_media_select form_edit', 'dom_id'=>$this->dom_id]);

                $this->get_medias_list();
                usort($this->medias, function($a,$b) {
                    return strcmp($a['name'], $b['name']);
                });
                $opts_data = ['first_empty'=>['name'=>$this->get_tl('add_one'),'id'=>0], 'label_key'=>'name', 'value_key'=>'id', 'options'=>$this->medias, 'groups'=>['active'=>[0=>$this->get_tl('inactive'), 1=>$this->get_tl('active')]]];
                $select = H::select(['name'=>$this->ifld_media_id,'class'=>$this->css.'select media','label'=>$this->get_tl('select_media')],$opts_data,$post[$this->ifld_media_id],$this->input_action_identifier,$this->ACTION_FORM_MEDIA);

            $form->add_child([$select->label_tag(),$select]);

                // $hidden_initial = H::input_hidden(['name'=>$this->ifld_media_initial, 'value'=>$post[$this->ifld_media_initial]]);
                $active = H::input_checkbox(['name'=>$this->ifld_media_active, 'value'=>1, 'checked'=>$post[$this->ifld_media_active], 'class'=>$this->css.'input_checkbox active', 'label'=>$this->get_tl('active')]);

            $form->add_child([$active->label_tag(), $active]);

                $name = H::input_text(['name'=>$this->ifld_media_name,'value'=>$post[$this->ifld_media_name],'class'=>$this->css.'input_text media name','label'=>$this->get_tl('name')]);
                $value = H::input_text(['name'=>$this->ifld_media_value,'value'=>$post[$this->ifld_media_value],'class'=>$this->css.'input_text media value','label'=>$this->get_tl('value')]);
                $sort_order = H::input_integer(['name'=>$this->ifld_media_sort_order,'value'=>$post[$this->ifld_media_sort_order],'class'=>$this->css.'input_int media sort_order','label'=>$this->get_tl('sort_order')]);

            $form->add_child([$name->label_tag(), $name, $value->label_tag(), $value,$sort_order->label_tag(), $sort_order]);

                $div_btn = H::DIV(['class'=>$this->css.'edit_buttons edit_buttons']);
                    $btn_save = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_SAVE_MEDIA,'class'=>$this->css.'button_save button_save media'],$this->get_tl('tlc_save'));
                $div_btn->add_child($btn_save);
                if ($post[$this->ifld_media_id_initial] > 0){
                    $btn_default = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_DEFAULT_MEDIA,'class'=>$this->css.'button_default button_default media', 'data-confirm'=>$this->get_tl('ask_default_media')],$this->get_tl('tlc_default'));
                    $div_btn->add_child($btn_default);
                }
                if ($post[$this->ifld_media_id] > 1){
                    $btn_del = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_DELETE_MEDIA,'class'=>$this->css.'button_delete button_delete media', 'data-confirm'=>$this->get_tl('ask_del_media')],$this->get_tl('tlc_del'));
                    $div_btn->add_child($btn_del);
                }

            $form->add_child($div_btn);

        $output->add_child($form);

        return $output;
    }
    public function save_media(&$post) {
        global $DB;
        
        $this->check_posted_data($post,'csseditor_media');

        $q = 'SELECT initial FROM '.$this->bddt_media.' WHERE id=?';
        $initial = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_media_id]]);

        $refresh_ui = false;

        if ($initial > 0){
            // copy row
            $id_copy = $DB->duplicate_line($this->bddt_media, $post[$this->ifld_media_id], ['active'=>0]);

            $q = 'UPDATE '.$this->bddt_media.' SET initial=0, id_initial='.$id_copy.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);
        }
        
        $post[$this->ifld_media_active] = isset($post[$this->ifld_media_active]) ? $post[$this->ifld_media_active] : 0;
        // $output = H::group('save_media');

        if ($post[$this->ifld_media_id] > 0){
            $q = 'UPDATE '.$this->bddt_media.' SET name=?, value=?, `sort_order`=?, active=? WHERE id=?';
            $DB->prepared_query($q,'ssiii',[$post[$this->ifld_media_name], $post[$this->ifld_media_value], $post[$this->ifld_media_sort_order], $post[$this->ifld_media_active], $post[$this->ifld_media_id]]);
        } else {
            $q = 'INSERT INTO '.$this->bddt_media.' SET name=?, value=?, `sort_order`=?, active=?';
            $DB->prepared_query($q,'ssii',[$post[$this->ifld_media_name], $post[$this->ifld_media_value], $post[$this->ifld_media_sort_order], $post[$this->ifld_media_active]]);
            $post[$this->ifld_media_id] = $DB->last_insert_id();
            $refresh_ui = true;
            // $output->add_child( H::script($this->inst_js.'.refresh_input_rules();', ['autoremove'=>'autoremove']) );
        }

        $output = [$this->form_media($post)];
        if ($refresh_ui) array_push($output, H::script($this->inst_js.'.refresh_input_rules();', ['autoremove'=>'autoremove']));
        // $output->add_child($this->form_media($post));
        return $output;
    }
    public function delete_media($post) {
        global $DB;

        $this->check_posted_data($post,'csseditor_media');

        if ($post[$this->ifld_media_id] == 0){
            Utils::error_log('missing id to delete media');
            Utils::error_log($post);
            $this->add_error('error_deleting_media');
            return [$this->form_media($post), H::script($this->inst_js.'.refresh_input_rules();', ['autoremove'=>'autoremove'])];
        }

        $q = 'SELECT initial, id_initial FROM '.$this->bddt_media.' WHERE id=?';
        $media = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_media_id]]);

        if ($media['initial'] == 1) {
            // this is a default media, can't delete it. Force it to inactive state
            $q = 'UPDATE '.$this->bddt_media.' SET active=-1 WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);
            
        } else {
            // not a default media, can delete it
            $q = 'DELETE FROM '.$this->bddt_media.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);

            if ($media['id_initial'] > 0) {
                // this is a modified default media, restore id to the inactive copy stored in db
                $q = 'UPDATE '.$this->bddt_media.' SET id=?, id_initial=0, active=-1 WHERE id='.$media['id_initial'];
                $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);
            } else {
                // we can delete all the rules linked to this media, they can't be used anymore
                $q = 'DELETE FROM '.$this->bddt_rules.' WHERE id_media=?';
                $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);
            }
        }

        $this->reset_fields($post,'csseditor_media');

        return [$this->form_media($post), H::script($this->inst_js.'.refresh_input_rules();', ['autoremove'=>'autoremove'])];
    }
    public function restore_default_media($post){
        global $DB;

        $this->prepare_fields($post, 'csseditor_media');

        if (!$post[$this->ifld_media_id_initial]) {
            Utils::error_log('Error when trying to restore font');
            Utils::error_log($post);
            return;
        }

        $q = 'DELETE FROM '.$this->bddt_media.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);

        $q = 'UPDATE '.$this->bddt_media.' SET id=?, id_initial=0, active=1 WHERE id='.$post[$this->ifld_media_id_initial];
        $DB->prepared_query($q, 'i', [$post[$this->ifld_media_id]]);

        return $this->form_media($post);
    }
    // -----------------------------------------------------------------------------------------------
    /**
     * get variable list for a source
     * 
     */
    public function get_variable_list($id_source) {
        global $DB;

        if ($this->variables && isset($this->variables[$id_source])) return;

        // retrieve all variable
        $q = 'SELECT * FROM '.$this->bddt_variables.' WHERE id_source=? AND id NOT IN (';
        $q.= 'SELECT DISTINCT id_initial FROM '.$this->bddt_variables.') AND active<>-1 ORDER BY active DESC, name ASC';
        $data = $DB->prepared_query_list($q,'i',[$id_source]);

        $this->variables = ($this->variables) ? $this->variables : [];
        $this->variables[$id_source] = [];
        foreach($data as $line){
            $name = $line['name'];
            if (!isset($this->variables[$id_source][$name])) {
                $this->variables[$id_source][$name] = [];
                $this->variables[$id_source][$name]['is_color'] = $this->detect_color_in_variable($name, $line['properties']);
                $this->variables[$id_source][$name]['active'] = $line['active'];
                $this->variables[$id_source][$name]['medias'] = [];
                $this->variables[$id_source][$name]['id_source'] = $id_source;
            }
            $this->variables[$id_source][$name]['medias'][$line['id_media']] = $line['properties'];
        }
    }
    public function get_variable($id_source, $name) {
        global $DB;

        // retrieve one variable by it's name
        $q = 'SELECT var.* FROM '.$this->bddt_variables.' var LEFT JOIN ';
        $q.= $this->bddt_media.' med ON (med.id=var.id_media) WHERE var.id_source=? AND var.name LIKE ?';
        $q.=' AND var.id NOT IN (SELECT DISTINCT id_initial FROM '.$this->bddt_variables.') ORDER BY med.sort_order';
        $data = $DB->prepared_query_list($q,'is',[$id_source,$name]);

        $variable = [];
        if ($data){
            $variable['is_color'] = $this->detect_color_in_variable($name, $data[0]['properties']);
            $variable['name'] = $data[0]['name'];
            $variable['active'] = $data[0]['active'];
            $variable['initial'] = $data[0]['initial'];
            $variable['id_initial'] = $data[0]['id_initial'] ? true : false;
            $variable['id_source'] = $id_source;
            $variable['medias'] = [];
            foreach($data as $line){
                $variable['medias'][$line['id_media']] = [
                    'id'=>$line['id'],
                    'properties'=>$line['properties'],
                    'id_initial'=>$line['id_initial']
                ];
            }
        }

        return $variable;

    }
    public function display_variable_list($post){

        $this->check_posted_data($post,'csseditor_source', ['id']);

        // there is a change of editing theme. save it to session for next loading
        if ($this->preview) {
            if ($post[$this->ifld_source_admin]) $_SESSION['current_csseditor_theme_preview_admin'] = $post[$this->ifld_theme_id];
            else $_SESSION['current_csseditor_theme_preview'] = $post[$this->ifld_theme_id];
        } else if ($this->force_admin_or_public){
            if ($post[$this->ifld_source_admin]) $_SESSION['current_csseditor_theme_admin'] = $post[$this->ifld_theme_id];
            else $_SESSION['current_csseditor_theme_public'] = $post[$this->ifld_theme_id];
        } else $_SESSION['current_csseditor_theme'] = $post[$this->ifld_theme_id];

        $container = H::DIV(['class'=>$this->css.'subcontainer variables', 'id'=>self::module_name.'_container_variables'.$this->dom_id]);

        if ($this->source_type != 'theme'){
            // display the theme's variables in a collapsable (hidden by default)

            $details = H::detail(['class'=>$this->css.'variables_list_theme', 'open'=>1], $this->get_tl('variable_theme'));

            global $DB;
            if ($this->source_type == 'module'){
                global $CONFIG;
                if ($post[$this->ifld_source_admin]) $post[$this->ifld_theme_id] = $CONFIG::THEME_ID_ADMIN;
                else $post[$this->ifld_theme_id] = $CONFIG::THEME_ID;
            }
            $q = 'SELECT id_source FROM '.$this->bddt_theme.' WHERE id=?';
            $source_id = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_theme_id]]);
            $this->get_variable_list($source_id);

            $i = 0;

            foreach($this->variables[$source_id] as $name => $data){
                $i++;
                $details->add_child( $this->display_variable($name, $data, $i, false, false) );
            }

            $container->add_child( $details );
        }

            $btn_add = H::BUTTON(['id'=>self::module_name.'_btn_add_variable'.$this->dom_id, 'class'=>$this->css.'button_new button_new variable','onclick'=>$this->inst_js.'.add_variable(event);'], $this->get_tl('add_variable'));

        $container->add_child( $btn_add );

            $div_variables = H::DIV(['class'=>$this->css.'variables_list', 'id'=>self::module_name.'_container_variables'.$this->dom_id]);

                $this->get_variable_list($post[$this->ifld_source_id]);

                $i = 0;
                foreach($this->variables[$post[$this->ifld_source_id]] as $name => $data){
                    $i++;
                    $div_variables->add_child($this->display_variable($name, $data, $i));
                }

            $div_variables->add_child(H::script($this->inst_js.'.variables = '.json_encode($this->variables).';'));

        $container->add_child( $div_variables );

        return $container;
    }
    public function display_variable($name, $data, $index, $refresh = false, $edit = true){

        $css_inactive = $data['active'] ? '' : ' inactive';
        $container = H::DIV(['class'=>$this->css.'var'.$css_inactive, 'id'=>self::module_name.'_var-'.$data['id_source'].'-'.$index.$this->dom_id, 'data-name'=>$name]);
        // add onclick event to add the var(--name) to the focused input if the variable is active.
        if ($data['active']) $container->set_attribute('onclick', $this->inst_js.'.add_variable_to_input(event);');

            $lab_name = H::SPAN(['class'=>$this->css.'var_name'], $name.':');

        $container->add_child( $lab_name );

        $default_media_id = 1;
        foreach($data['medias'] as $id => $val){
            if (is_array($val)) $val = $val['properties']; // data come from get_variable and not get_variable_list
            $css = $this->css.'var_properties' . ($id != $default_media_id ? ' hidden' : '');
            $subcontainer = H::DIV(['class'=>$css, 'data-id'=>$id]);
            if ($data['is_color']){
                $color = H::SPAN(['class'=>$this->css.'var_properties_color', 'style'=>'background: '.$val.';']);
                $text = H::SPAN(['class'=>$this->css.'var_properties_text'], $val.';');
                $subcontainer->add_child([$color, $text]);
            } else {
                $text = H::SPAN(['class'=>$this->css.'var_properties_text'], $val.';');
                $subcontainer->add_child($text);
            }
            $container->add_child( $subcontainer );
        }

        if ($edit){
            $btn_edit = H::button_icon('edit-2', ['class'=>$this->css.'button_edit button_edit var','onclick'=>$this->inst_js.'.edit_variable(event, "'.$name.'", '.$index.');', 'title'=>$this->get_tl('tlc_edit')]);
            $container->add_child( $btn_edit );
        }

        if ($refresh){
            // change value in js
            $script = H::script($this->inst_js.'.variables["'.$name.'"] = '.json_encode($data).';');
            $container->add_child($script);
        }

        return $container;
    }
    public function refresh_variable($post){
        $data = $this->get_variable($post[$this->ifld_source_id], $post[$this->ifld_variables_name]);
        return $this->display_variable($post[$this->ifld_variables_name], $data, $post['index'], true);
    }
    // for a new variable, index = -1
    public function detect_color_in_variable($name,$properties){
        $is_color = (str_contains($name, 'color') || str_contains($name, 'col')) ? true : false;
        $is_color = str_starts_with($properties,'#') ? true : $is_color;
        $is_color = str_starts_with($properties,'rgba') ? true : $is_color;
        return $is_color;
    }
    public function form_variable($post){

        $this->check_posted_data($post,'csseditor_variables', ['name']);
        $this->check_posted_data($post,'csseditor_source', ['id']);

        if ($post[$this->ifld_variables_name]) $variable = $this->get_variable($post[$this->ifld_source_id], $post[$this->ifld_variables_name]);
        else $variable = false;

        $output = H::group('form_variable');

            $ttl = H::DIV(['class'=>$this->css.'title_modal variable module_title'], $this->get_tl('ttl_variable'));

        $output->add_child($ttl);

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id, 'class'=>$this->css.'form_edit form_edit variable'], '', $this->inst_js.'.add_data_to_submit');

                $index = H::input_hidden(['name'=>'index','value'=>$post['index']]);
                $previous_name = H::input_hidden(['name'=>'previous_name', 'value'=>$post[$this->ifld_variables_name]]);

            $form->add_child( [$index, $previous_name] );

                $checked = $variable ? $variable['active'] : true;
                $inp_active = H::input_checkbox(['name'=>$this->ifld_variables_active, 'value'=>1, 'checked'=>$checked, 'label'=>$this->get_tl('active')]);

            $form->add_child( [$inp_active->label_tag(), $inp_active] );

                $inp_name = H::input_text(['data-required'=>1, 'name'=>$this->ifld_variables_name, 'value'=>$post[$this->ifld_variables_name], 'class'=>$this->css.'inp_txt variable_name', 'label'=>$this->get_tl('name')]);

            $form->add_child( [$inp_name->label_tag(), $inp_name] );

                
                $is_color = $variable ? $variable['is_color'] : false;
                $checkbox_color = H::input_checkbox(['name'=>'is_color', 'value'=>1, 'checked'=>$is_color, 'label'=>$this->get_tl('lab_is_color'), 'onchange'=>$this->inst_js.'.switch_variable_type(event);']);

            $form->add_child( [$checkbox_color->label_tag(), $checkbox_color] );
                
                $this->get_medias_list();
                foreach($this->medias as $media){
                    
                    if ($media['active'] == 0) continue;

                    $val = ($variable && isset($variable['medias'][$media['id']])) ? $variable['medias'][$media['id']]['properties'] : '';
                    $div_properties = H::DIV(['class'=>$this->css.'variable_input property', 'data-color'=>'0']);
                        $inp_media = H::input_text(['name'=>'properties['.$media['id'].']','value'=>$val, 'class'=>$this->css.'inp_txt variable_properties', 'label'=>$media['name']]);
                        // $media_lab = H::SPAN(['class'=>$this->css.'var_media_value'], $media['data-value']);
                    $div_properties->add_child( [$inp_media->label_tag(), $inp_media] );
                    if ($is_color) $div_properties->add_class('hidden');

                    $form->add_child($div_properties);

                    // if ($media['id'] == 1){
                    $div_colors = H::DIV(['class'=>$this->css.'variable_input color', 'data-color'=>'1']);
                        if ($media['id'] != 1){
                            $checked = ($val == '' || !$is_color) ? 0 : 1;
                            $checkbox = H::input_checkbox(['name'=>'active_colors['.$media['id'].']', 'value'=>1, 'checked'=>$checked, 'label'=>$this->get_tl('active_media', $media['name']), 'onchange'=>'H_dom.toggle_class(document.getElementById("hlp_colorpicker-'.self::module_name.'_inp_color_'.$media['id'].$this->dom_id.'"), "hidden");']);
                            $div_colors->add_child( [$checkbox->label_tag(), $checkbox] );
                        } else {
                            $checked = true;
                            $label = H::LABEL(null, $media['name']);
                            $div_colors->add_child( $label );
                        }
                        if ($val == '' || !$is_color) $val = '#000000';
                        $inp_media_color = H::input_colorpicker(['name'=>'colors['.$media['id'].']', 'value'=>$val, 'class'=>$this->css.'inp_col variable_colors', 'id'=>self::module_name.'_inp_color_'.$media['id'].$this->dom_id]);
                        if (!$checked) $inp_media_color->add_class('hidden');
                        // $media_lab = H::SPAN(['class'=>$this->css.'var_media_value'], $media['data-value']);
                    $div_colors->add_child( $inp_media_color );
                    if (!$is_color) $div_colors->add_class('hidden');

                    $form->add_child($div_colors);
                    // }
                }
                
                $div_btn = H::DIV(['class'=>$this->css.'form_variable_btns edit_buttons']);
                    $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_VARIABLE,'class'=>$this->css.'button_save button_save variable'],$this->get_tl('tlc_save'));
                $div_btn->add_child($btn_save);
                
                // display default button if initial value
                if ($variable && $variable['id_initial']) {
                    $btn_default = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DEFAULT_VARIABLE, 'class'=>$this->css.'button_default button_default variable', 'data-confirm'=>$this->get_tl('ask_default_variable')], $this->get_tl('tlc_default'));
                    $div_btn->add_child( $btn_default );
                }

                if ($post['index'] > -1 && $variable && !key_exists($variable['name'], $this::required_variables)){
                    $btn_delete = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DELETE_VARIABLE,'class'=>$this->css.'button_delete button_delete variable', 'data-confirm'=>$this->get_tl('confirm_del_variable')],$this->get_tl('tlc_del'));
                    $div_btn->add_child($btn_delete);
                }
                
            $form->add_child($div_btn);

        $output->add_child($form);

        return $output;
    }
    public function save_variable($post){
        global $DB;

        $this->check_posted_data($post, 'csseditor_variables', ['name', 'active']);
        $this->check_posted_data($post, 'csseditor_source', ['id']);

        // if ($post[$this->ifld_source_id])
        $post[$this->ifld_source_id] = $post[$this->ifld_source_id] ?: $this->create_source($post);

        $variable = $this->get_variable($post[$this->ifld_source_id], $post['previous_name']);

        $new_var = ($post['previous_name'] == '');

        if (!$new_var && $variable['initial'] == 1){
            foreach($variable['medias'] as $key => $media) {
                // copy row
                $id_copy = $DB->duplicate_line($this->bddt_variables, $media['id'], ['active'=>0]);

                $q = 'UPDATE '.$this->bddt_variables.' SET initial=0, id_initial='.$id_copy.' WHERE id=?';
                $DB->prepared_query($q, 'i', [$media['id']]);
                $variable['medias'][$key]['id_initial'] = $id_copy;
            }
            $variable['medias']['id_initial'] = true;
        }
        
        $post[$this->ifld_variables_active] = $post[$this->ifld_variables_active] ? $post[$this->ifld_variables_active] : 0;

        // force -- before variable name
        $post[$this->ifld_variables_name] = str_starts_with($post[$this->ifld_variables_name], '--') ? $post[$this->ifld_variables_name] : '--'.$post[$this->ifld_variables_name];

        // check if the name changed
        // $name_changed = ($post['previous_name'] != $post[$this->ifld_variables_name]);
        $saved = false;
        if (isset($post['is_color']) && $post['is_color']) {
            // save the colors if checkox is checked otherwise delete it
            // $main_color = $post['colors'][1];
            foreach($post['colors'] as $id_media => $val){
                if ($id_media != 1 && !isset($post['active_colors'][$id_media])) {
                    if (isset($variable['medias'][$id_media])){
                        $q = 'DELETE FROM '.$this->bddt_variables.' WHERE id=?';
                        $DB->prepared_query($q, 'i', [$variable['medias'][$id_media]['id']]);
                    }
                    continue;
                }
                // check if the value changed
                $new_val = (!isset($variable['medias'][$id_media]) && $val != ''); // if no previous value and new value not empty
                $saved = true;
                if (!$new_val && isset($variable['medias'][$id_media])){
                    $q = 'UPDATE '.$this->bddt_variables.' SET name=?, properties=?, active=? WHERE id=?';
                    $DB->prepared_query($q,'ssii',[$post[$this->ifld_variables_name], $val, $post[$this->ifld_variables_active], $variable['medias'][$id_media]['id']]);
                } else {
                    $q = 'INSERT INTO '.$this->bddt_variables.' SET id_source=?, id_media=?, name=?, properties=?, active=?';
                    $DB->prepared_query($q,'iissi',[$post[$this->ifld_source_id], $id_media, $post[$this->ifld_variables_name], $val, $post[$this->ifld_variables_active]]);
                }
            }
            // if (isset($variable['medias'][1])){
            //     foreach($variable['medias'] as $id_media => $media){
            //         if ($id_media == 1){
            //             $q = 'UPDATE '.$this->bddt_variables.' SET name=?, properties=?, active=? WHERE id=?';
            //             $DB->prepared_query($q,'ssii',[$post[$this->ifld_variables_name], $post['color'], $post[$this->ifld_variables_active], $media['id']]);
            //         } else {
            //             $q = 'DELETE FROM '.$this->bddt_variables.' WHERE id=?';
            //             $DB->prepared_query($q,'i',[$variable['medias'][$id_media]['id']]);
            //         }
            //     }
            // } else {
            //     $q = 'INSERT INTO '.$this->bddt_variables.' SET id_source=?, id_media=?, name=?, properties=?, active=?';
            //     $DB->prepared_query($q,'iissi', [$post[$this->ifld_source_id], 1, $post[$this->ifld_variables_name], $post['color'], $post[$this->ifld_variables_active]]);
            // }
        } else {
            foreach($post['properties'] as $id_media => $val){
                // check if the value changed
                $new_val = (!isset($variable['medias'][$id_media]) && $val != ''); // if no previous value and new value not empty
                $saved = true;
                if (!$new_val && isset($variable['medias'][$id_media])){
                    $q = 'UPDATE '.$this->bddt_variables.' SET name=?, properties=?, active=? WHERE id=?';
                    $DB->prepared_query($q,'ssii',[$post[$this->ifld_variables_name], $val, $post[$this->ifld_variables_active], $variable['medias'][$id_media]['id']]);
                } else {
                    $q = 'INSERT INTO '.$this->bddt_variables.' SET id_source=?, id_media=?, name=?, properties=?, active=?';
                    $DB->prepared_query($q,'iissi',[$post[$this->ifld_source_id], $id_media, $post[$this->ifld_variables_name], $val, $post[$this->ifld_variables_active]]);
                }
            }
        }
        
        if (!$saved && $new_var){ // force save for media id 1 when only a name is filled in the form.
            $q = 'INSERT INTO '.$this->bddt_variables.' SET id_source=?, id_media=1, name=?, active=?';
            $DB->prepared_query($q,'isi',[$post[$this->ifld_source_id], $post[$this->ifld_variables_name], $post[$this->ifld_variables_active]]);
        }
        
        $js = '';
        if ($this->created_source) $js.= $this->inst_js.'.source = '.json_encode(['id'=>$this->created_source, 'admin'=>$post[$this->ifld_source_admin]]).';';
        if ($new_var) $js.= $this->inst_js.'.refresh_all_variable(); H_ui.popup_modal.hide();';
        else $js.= $this->inst_js.'.refresh_variable('.$post[$this->ifld_source_id].', '.$post['index'].',"'.$post[$this->ifld_variables_name].'");';

        if ($new_var) return H::script($js, ['autoremove'=>true]);
        return [
            $this->form_variable($post),
            H::script($js, ['autoremove'=>true])
        ];
    }
    public function delete_variable($post) {
        global $DB;

        $this->check_posted_data($post, 'csseditor_source', ['id']);

        $variable = $this->get_variable($post[$this->ifld_source_id], $post['previous_name']);
        if ($variable['initial'] == 1) {
            // this is a default media, can't delete it. Force it to inactive state except for required one
            if (!key_exists($variable['name'], $this::required_variables)) {
                foreach($variable['medias'] as $media) {
                    $q = 'UPDATE '.$this->bddt_variables.' SET active=-1 WHERE id=?';
                    $DB->prepared_query($q, 'i', [$media['id']]);
                }
            }
        } else {
            // not a default variable, can delete it
            foreach($variable['medias'] as $media) {
                $q = 'DELETE FROM '.$this->bddt_variables.' WHERE id=?';
                $DB->prepared_query($q, 'i', [$media['id']]);

                if ($media['id_initial'] > 0) {
                    // this is a modified default media, restore id to the inactive copy stored in db
                    $q = 'UPDATE '.$this->bddt_variables.' SET id=?, id_initial=0, active=-1 WHERE id='.$media['id_initial'];
                    $DB->prepared_query($q, 'i', [$media['id']]);
                }
            }
        }

        return H::script($this->inst_js.'.delete_variable('.$post[$this->ifld_source_id].', '.$post['index'].');', ['autoremove'=>'autoremove']);
    }
    public function restore_default_variable($post) {
        global $DB;

        $this->check_posted_data($post, 'csseditor_source', ['id']);

        $variable = $this->get_variable($post[$this->ifld_source_id], $post['previous_name']);
        if (!$variable['id_initial']) {
            Utils::error_log('Error when trying to restore variable');
            Utils::error_log($post);
            Utils::error_log($variable);
            return;
        }

        foreach($variable['medias'] as $media) {
            $q = 'DELETE FROM '.$this->bddt_variables.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$media['id']]);

            $q = 'UPDATE '.$this->bddt_variables.' SET id=?, id_initial=0, active=1 WHERE id='.$media['id_initial'];
            $DB->prepared_query($q, 'i', [$media['id']]);
        }

        $output = H::group('restore_variable');
        $output->add_child( $this->form_variable($post) );
        $output->add_child( H::script($this->inst_js.'.refresh_variable('.$post[$this->ifld_source_id].', '.$post['index'].', "'.$post['previous_name'].'");', ['autoremove'=>true]) );
        return $output;
    }
    // --------------------------------------------------------------------------------------------------------
    /**
     * Display the select and some buttons for the theme
     */
    public function select_theme(&$post) {
        global $DB, $CONFIG;

        $output = H::DIV(['class'=>$this->css.'subcontainer select_theme']);
        if ($this->source_type != 'theme'){
            $output->add_class('reduce_mode');
        }
        
        $q = 'SELECT DISTINCT name, id, admin as "data-admin", id_source as "data-id_source" FROM '.$this->bddt_theme;
        if ($this->force_admin_or_public) $q.=' WHERE admin='.$post[$this->ifld_source_admin];
        $list = $DB->query_list($q);
        if ($list) {
            
            $opts_data = ['value_key'=>'id', 'label_key'=>'name', 'options'=>$list];
            $select = H::select(['name'=>$this->ifld_theme_id, 'label'=>$this->get_tl('theme'), 'class'=>$this->css.'select_theme', 'onchange'=>$this->inst_js.'.on_change_theme(event);', 'id'=>self::module_name.'_select_theme'.$this->dom_id], $opts_data, $post[$this->ifld_theme_id]);
            
            $output->add_child([$select->label_tag(), $select]);

            if ($this->source_type == 'theme'){
                $id = H::SPAN(['class'=>$this->css.'info_id', 'id'=>self::module_name.'_info_id'.$this->dom_id], 'ID : '.$post[$this->ifld_theme_id]);
                $output->add_child($id);

                if ($post[$this->ifld_theme_id] > 0 && $this->source_type == 'theme'){
                    // display id
                    
                    // $btn_edit = H::BUTTON(['class'=>$this->css.'btn_edit theme','onclick'=>$this->inst_js.'.on_click_edit_theme(event);'], $this->get_tl('tlc_edit'));
                    $btn_edit = H::button_icon('edit-2', ['class'=>$this->css.'btn_edit button_edit theme','onclick'=>$this->inst_js.'.on_click_edit_theme(event);', 'title'=>$this->get_tl('tlc_edit')]);
                    $output->add_child($btn_edit);
                    
                    $btn_del = H::button_icon('trash-2', ['class'=>$this->css.'btn_del button_delete theme','onclick'=>'H_ui.confirm_popup("'.$this->get_tl('tlc_ask_delete').'", '.$this->inst_js.'.on_click_del_theme.bind('.$this->inst_js.'));', 'title'=>$this->get_tl('tlc_delete')]);
                    $output->add_child($btn_del);
                    // disable and add a title to the delete button if it's the last admin or public theme
                    $q = 'SELECT COUNT(id) FROM '.$this->bddt_theme.' WHERE admin=(SELECT admin FROM '.$this->bddt_theme.' WHERE id=?)';
                    $cnt = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_theme_id]]);
                    if ($cnt <= 1){
                        $btn_del->set_attribute('disabled', '');
                        $btn_del->set_attribute('title', $this->get_tl('no_delete_last_theme'));
                    }
                }

                $params_click = ['dom_id'=>$this->dom_id, $this->input_action_identifier=>$this->ACTION_FORM_THEME, $this->ifld_theme_id=>0];
                // $btn_add = H::BUTTON(['class'=>$this->css.'btn_add theme', 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($params_click).');', 'title'=>$this->get_tl('add_theme_duplic')], '+');
                $btn_add = H::button_icon('plus', ['class'=>$this->css.'btn_add theme', 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($params_click).');', 'title'=>$this->get_tl('add_theme_duplic')]);
                $output->add_child($btn_add);
            }
            
            if ($post[$this->ifld_theme_id] > 0) {
                $js = $this->inst_js.'.theme = '.json_encode([
                    'id'=>$post[$this->ifld_theme_id],
                    'admin'=>$post[$this->ifld_theme_admin],
                    'id_source'=>$post[$this->ifld_theme_id_source]
                ]).';';
                $output->add_child(H::script($js, ['autoremove'=>1]));
            }
            
        } else {

            $output->add_child(H::SPAN([],$this->get_tl('theme')));

                $params_click = ['dom_id'=>$this->dom_id, $this->input_action_identifier=>$this->ACTION_FORM_THEME, $this->ifld_theme_id=>0];
                $btn_add = H::button_icon('plus', ['class'=>$this->css.'btn_add theme', 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'", '.json_encode($params_click).');', 'title'=>$this->get_tl('add_theme_duplic')]);

            $output->add_child($btn_add);
        }

        $tools = H::DIV(['class'=>$this->css.'bar_tools']);
            $modal_param = [$this->input_action_identifier=>$this->ACTION_FORM_MEDIA,'dom_id'=>$this->dom_id];
            $media = H::BUTTON(['class'=>$this->css.'btn tool media','onclick'=>'H_ui.open_popup_modal(event,"'.self::module_name.'",'.json_encode($modal_param).');'], $this->get_tl('media'));
        $tools->add_child( [$media] );
        

        if ($this->source_type == 'theme') {
            // extract buttons
            $extract = H::BUTTON(['class'=>$this->css.'btn tool extract', 'onclick'=>$this->inst_js.'.extract_into_file("theme");'], $this->get_tl('extract_theme'));

            // import buttons
            $modal_param = [$this->input_action_identifier=>$this->ACTION_FORM_IMPORT, 'dom_id'=>$this->dom_id];
            $import = H::BUTTON(['class'=>$this->css.'btn tool import', 'onclick'=>'H_ui.open_popup_modal(event, "'.self::module_name.'",'.json_encode($modal_param).');'], $this->get_tl('import'));

            $tools->add_child([$import, $extract]);
        }

        $output->add_child($tools);

        return $output;
    }
    public function form_theme_edit($post) {

        $this->prepare_fields($post,'csseditor_theme');

        $theme_opts = json_decode(htmlspecialchars_decode($post[$this->ifld_theme_options]),true);

        $output = H::group(['theme_edit']);
            
            $title = H::DIV(['class'=>$this->css.'title_modal theme_edit module_title'], $this->get_tl('title_theme'));

        $output->add_child($title);

        // display the import fieldset
        // $output->add_child( $this->form_import($post) );
        
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_theme'], '', $this->inst_js.'.add_data_to_submit');

                $inp_name = H::input_text(['class'=>$this->css.'form_theme_inp', 'name'=>$this->ifld_theme_name, 'value'=>$post[$this->ifld_theme_name], 'label'=>$this->get_tl('name'), 'data-required'=>1, 'title'=>$this->get_tl('name')]);

            $form->add_child([$inp_name->label_tag(), $inp_name]);

                if ($post[$this->ifld_theme_id] == 0){
                    // add hidden that indicate it's a new theme
                    $hidden_new_theme = H::input_hidden(['name'=>'new_theme', 'value'=>1]);
                    $form->add_child($hidden_new_theme);

                    global $DB;
                    // retrieve existing list theme
                    $list = $DB->query_list('SELECT DISTINCT id, name FROM '.$this->bddt_theme);
                    $domId_select = self::module_name.'_select_theme_duplic'.$this->dom_id;
                    $check_duplic = H::input_checkbox(['class'=>$this->css.'form_theme_inp', 'name'=>'duplic_theme', 'value'=>1, 'label'=>$this->get_tl('duplic_theme'), 'onchange'=>$this->inst_js.'.on_change_duplic(event.target);']);
                    $selected = isset($post[$this->ifld_theme_id]) ? $post[$this->ifld_theme_id] : 0;
                    $opts_data = ['first_empty'=>true, 'value_key'=>'id', 'label_key'=>'name', 'options'=>$list];
                    $select_duplic = H::select(['id'=>$domId_select, 'name'=>'duplic_theme_id', 'class'=>$this->css.'choix_duplic_theme hidden', 'label'=>$this->get_tl('lab_select_duplic')], $opts_data, $selected);
                    $form->add_child([$check_duplic->label_tag(), $check_duplic, $select_duplic->label_tag(null, ['class'=>'hidden']), $select_duplic]);
                }
            
                $inp_admin = H::input_checkbox(['name'=>$this->ifld_theme_admin, 'value'=>1, 'class'=>$this->css.'inp_check theme_admin', 'checked'=>$post[$this->ifld_theme_admin], 'label'=>$this->get_tl('lab_admin')]);

                $checked = isset($theme_opts['display_title_onfocus']) && $theme_opts['display_title_onfocus'];
                $inp_display_title_onfocus = H::input_checkbox(['name'=>$this->ifld_theme_options.'[display_title_onfocus]', 'value'=>1, 'class'=>$this->css.'inp_check theme_disp_ttl_onclick', 'checked'=>$checked, 'label'=>$this->get_tl('display_title_onfocus')]);
            
            $form->add_child([$inp_admin->label_tag(), $inp_admin, $inp_display_title_onfocus->label_tag(), $inp_display_title_onfocus]);
            
                $div_btn = H::DIV(['class'=>$this->css.'form_theme_btns edit_buttons']);
                    $btn_save = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_THEME, 'class'=>$this->css.'button_save button_save theme'], $this->get_tl('tlc_save'));
                $div_btn->add_child( $btn_save );
                if ($post[$this->ifld_theme_id] > 0){
                    $btn_default = H::submit_button_single(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DEFAULT_THEME, 'class'=>$this->css.'button_default button_default theme','data-confirm'=>$this->get_tl('ask_default_theme')], $this->get_tl('tlc_default'));
                    $div_btn->add_child($btn_default);
                }
            
            $form->add_child($div_btn);

        $output->add_child($form);
        
        return $output;
    }
    public function save_theme(&$post) {
        global $DB;

        $post[$this->ifld_theme_options] = isset($post[$this->ifld_theme_options]) ? json_encode($post[$this->ifld_theme_options]) : '';
        
        if (isset($post['new_theme']) && $post['new_theme']){
            $post[$this->ifld_source_id] = 0;
            $post[$this->ifld_theme_id] = 0;
        }
        $this->check_posted_data($post, 'csseditor_theme', ['id', 'name', 'admin', 'options']);
        $post[$this->ifld_source_admin] = $post[$this->ifld_theme_admin];

        // test if the name already exist
        $q = 'SELECT id FROM '.$this->bddt_theme.' WHERE name=? AND id<>?';
        $exist = $DB->prepared_query_value($q, 'si', [$post[$this->ifld_theme_name], $post[$this->ifld_theme_id]]);
        if (!$exist) {
            if ($post[$this->ifld_theme_id] > 0){

                $q = 'UPDATE '.$this->bddt_theme.' SET name=?, admin=?, options=? WHERE id=?';
                $DB->prepared_query($q, 'sisi', [$post[$this->ifld_theme_name], $post[$this->ifld_theme_admin], $post[$this->ifld_theme_options], $post[$this->ifld_theme_id]]);
                
                $post['changing_theme'] = false;

            } else {
        
                $post[$this->ifld_source_id] = $this->create_source( $post );

                if (isset($post['duplic_theme']) && $post['duplic_theme'] && isset($post['duplic_theme_id']) && $post['duplic_theme_id'] > 0) {
                    // Duplicate a theme, copy every data linked to the previous source theme
                    $q = 'SELECT id_source FROM '.$this->bddt_theme.' WHERE id=?';
                    $id_source = $DB->prepared_query_value($q, 'i', [$post['duplic_theme_id']]);

                    // insert new theme
                    $q = 'INSERT INTO '.$this->bddt_theme.' SET id_source=?, name=?, options=?, admin=?';
                    $DB->prepared_query($q, 'issi', [$post[$this->ifld_source_id], $post[$this->ifld_theme_name], $post[$this->ifld_theme_options], $post[$this->ifld_theme_admin]]);
                    $_SESSION['current_csseditor_theme'] = $DB->last_insert_id();

                    // copy variables
                    $q = 'INSERT INTO '.$this->bddt_variables.'(id_source,id_media,name,properties) SELECT ?,id_media,name,properties FROM '.$this->bddt_variables.' WHERE id_source=?';
                    $DB->prepared_query($q,'ii',[$post[$this->ifld_source_id],$id_source]);
                    
                    // copy keyframes
                    $q = 'INSERT INTO '.$this->bddt_keyframes.'(id_source,name,value) SELECT ?,name,value FROM '.$this->bddt_keyframes.' WHERE id_source=?';
                    $DB->prepared_query($q,'ii',[$post[$this->ifld_source_id],$id_source]);
                    
                    // copy fonts
                    $q = 'INSERT INTO '.$this->bddt_fonts.'(id_source,name) SELECT ?,name FROM '.$this->bddt_fonts.' WHERE id_source=?';
                    $DB->prepared_query($q,'ii',[$post[$this->ifld_source_id],$id_source]);

                    // copy rules
                    $q = 'INSERT INTO '.$this->bddt_rules.'(id_source,id_media,selector,properties,`sort_order`) SELECT ?,id_media,selector,properties,`sort_order` FROM '.$this->bddt_rules.' WHERE id_source=?';
                    $DB->prepared_query($q,'ii',[$post[$this->ifld_source_id],$id_source]);

                } else {

                    // $variable = '{":root":{"general":"--back-color: #FFFFFF;--back-color2: #FFFFFF;--back-color3: #FFFFFF;--mid-color: #FFFFFF;--high-color: #ffffff;--shadow-color: #000000;--shadow-color-hover: #8d8d8d;--text-color: #000000;--overlay-color: rgba(52,52,52,0.5);--default-radius: 20px;--default-menu-top: 15px;--default-left: 210px;--default-margin: 10px;--default-font-size: 15px;--default-title-font-size: 25px;"}}';
                    $q = 'INSERT INTO '.$this->bddt_theme.' SET id_source=?, name=?, admin=?, options=?';
                    $DB->prepared_query($q, 'isis', [$post[$this->ifld_source_id], $post[$this->ifld_theme_name], $post[$this->ifld_theme_admin], $post[$this->ifld_theme_options]]);
                    $_SESSION['current_csseditor_theme'] = $DB->last_insert_id();

                    // insert default variable
                    $q = 'INSERT INTO '.$this->bddt_variables.' (id_source, id_media, name, properties, initial, active) VALUES';
                    foreach($this::required_variables as $name => $value){
                        $q.=' ('.$post[$this->ifld_source_id].', 1, "'.$name.'", "'.$value.'", 1, 1),';
                    }
                    $DB->query(substr($q, 0, -1)); // remove last comma 
                }

                $post['changing_theme'] = true;
            }
            
            // Utils::make_constant();

        } else {

            $_SESSION['current_csseditor_theme'] = $exist;

        }

        // return a js call to close the modal and the updated form
        return [H::script('H_ui.popup_modal.hide();', ['autoremove'=>1]), $this->display_editor($post)];
    }
    public function delete_theme(&$post) {
        global $DB;

        $this->check_posted_data($post,'csseditor_theme',['id']);
        $this->check_posted_data($post,'csseditor_source',['id']);

        $q = 'SELECT COUNT(id) FROM '.$this->bddt_theme.' WHERE admin=(SELECT admin FROM '.$this->bddt_theme.' WHERE id=?)';
        $cnt = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_theme_id]]);
        if ($cnt == 1){
            $this->add_error('delete_last_theme');
            return;
        }

        if (isset($post[$this->ifld_theme_id]) && $post[$this->ifld_theme_id] > 0){

            $tables_to_delete_from = [$this->bddt_variables, $this->bddt_fonts, $this->bddt_keyframes, $this->bddt_rules];
            foreach($tables_to_delete_from as $table){
                $q = 'DELETE FROM '.$table.' WHERE id_source=?';
                $DB->prepared_query($q,'i',[$post[$this->ifld_source_id]]);
            }

            $q = 'DELETE FROM '.$this->bddt_source.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_source_id]]);

            $q = 'DELETE FROM '.$this->bddt_theme.' WHERE id=?';
            $DB->prepared_query($q,'i',[$post[$this->ifld_theme_id]]);
        }

        if ($_SESSION['current_csseditor_theme'] == $post[$this->ifld_theme_id]) unset($_SESSION['current_csseditor_theme']);
        unset($post[$this->ifld_theme_id]);
        unset($post[$this->ifld_source_id]);

        // return a js call to close the modal and the updated form
        return $this->display_editor($post);
    }
    /**
     * restore to default the theme
     * 
     * <p>Will go threw each table to restore the default value.<br>
     * A default value is either en entry with active = -1 (a deleted one).<br>
     * Or an entry referenced by an other one in the id_initial (replaced one).</p>
     */
    public function restore_default_theme($post) {
        global $DB;

        $this->check_posted_data($post, 'csseditor_theme');
        $this->check_posted_data($post, 'csseditor_source', ['id']);

        $table_to_restore = [$this->bddt_fonts, $this->bddt_rules, $this->bddt_variables, $this->bddt_keyframes];
        foreach($table_to_restore as $table){
            // restore the deleted
            $q = 'UPDATE '.$table.' SET active=1 WHERE id_source=? AND active=-1 AND initial=1';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_source_id]]);
            // restore the modified (replaced by a new rule)
            $q = 'SELECT DISTINCT id, id_initial FROM '.$table.' WHERE id_source=? AND id_initial > 0';
            $entries = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_source_id]]);
            if ($entries){
                foreach($entries as $entry){
                    $q = 'DELETE FROM '.$table.' WHERE id='.$entry['id'];
                    $DB->query($q);
            
                    $q = 'UPDATE '.$table.' SET id='.$entry['id'].', id_initial=0, active=1 WHERE id='.$entry['id_initial'];
                    $DB->query($q);
                }
            }
            // delete the added
            $q = 'DELETE FROM '.$table.' WHERE id_source=? AND initial = 0';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_source_id]]);
        }

        // MEDIA
        // retrieve the list of medias used by the theme
        $q = 'SELECT DISTINCT id_media FROM '.$this->bddt_rules.' WHERE id_source=?';
        $medias = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_source_id]]);
        if ($medias) {
            // deleted
            $q = 'UPDATE '.$this->bddt_media.' SET active=1 WHERE id IN ('.implode(',', $medias).') AND active=-1 AND initial=1';
            $DB->query($q);
            //replaced
            $q = 'SELECT DISTINCT id, id_initial FROM '.$this->bddt_media.' WHERE id IN ('.implode(',', $medias).') AND id_initial > 0';
            $medias = $DB->prepared_query_list($q);
            if ($medias){
                foreach($medias as $media){
                    $q = 'DELETE FROM '.$this->bddt_media.' WHERE id='.$media['id'];
                    $DB->query($q);
            
                    $q = 'UPDATE '.$this->bddt_media.' SET id='.$media['id'].', id_initial=0, active=1 WHERE id='.$media['id_initial'];
                    $DB->query($q);
                }
            }
        }

        $this->add_message('success_default_theme');
        
        return [H::script('h.main_tab.refresh_active();'), $this->form_theme_edit($post)];
    }

    public function create_source($post){
        global $DB;

        $this->check_posted_data($post, 'csseditor_source', ['id', 'admin']);

        $path = $this->get_source_path($post);

        $q = 'INSERT INTO '.$this->bddt_source.' SET type=?, path=?, admin=?';
        $DB->prepared_query($q, 'ssi', [$post['source'], $path, $post[$this->ifld_source_admin]]);
        $id = $DB->last_insert_id();

        $this->created_source = $id;

        return $id;
    }
    public function get_source_path($post){
        global $CONFIG;

        $path = '';

        switch($this->source_type) {
            case 'theme':
                if (!isset($post[$this->ifld_theme_admin])) $post[$this->ifld_theme_admin] = $post[$this->ifld_source_admin];
                $this->apply_bdd_data($post, 'csseditor_theme', ['name']);
                $path = $CONFIG::HOME_FOLDER.'css/theme/';
                $path.= (isset($post[$this->ifld_theme_admin]) && $post[$this->ifld_theme_admin]) ? 'admin/' : 'public/';
                $path.= $post[$this->ifld_theme_name].'/'.$post[$this->ifld_theme_name].'.css';
            break;
            case 'module':
                $path = $CONFIG::HELPHP_FOLDER.'modules/'.$post['source_module'];
                $path.= (isset($post[$this->ifld_source_admin]) && $post[$this->ifld_source_admin]) ? '/admin/' : '/public/';
                $path.= $post['source_module'].'.css';
            break;
            case 'block':
                global $DB;
                $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id=?';
                $name = $DB->prepared_query_value($q, 'i', [$this->source_params]);
                
                $path = $CONFIG::HELPHP_FOLDER.'modules/block/'.$name;
                $path.= (isset($post[$this->ifld_source_admin]) && $post[$this->ifld_source_admin]) ? '/admin/' : '/public/';
                $path.= $name.'.css';
            break;
            case 'document':
                global $DB;
                $q = 'SELECT name FROM '.$DB->table('document_data').' WHERE id=?';
                $name = $DB->prepared_query_value($q, 'i', [$this->source_params]);
                
                $path = $CONFIG::HOME_FOLDER.'public/document/cache/'.$name.'.css';
            break;
        }

        return $path;
    }
    
    // --------------------------------------------------------------------------------------------------------
    // FONT

    public function form_fonts($post) {
        global $DB;

        // Utils::error_log($post);

        $this->check_posted_data($post, 'csseditor_source', ['id']);
        $this->prepare_fields($post, 'csseditor_fonts');

        if (!$post[$this->ifld_fonts_id]){
            $post[$this->ifld_fonts_active] = 1;
        }

        $output = H::group('form_fonts');

            // $ttl = H::DIV(['class'=>$this->css.'title_modal font module_title'], $this->get_tl('ttl_font'));
            $info = H::DIV(['class'=>$this->css.'info_action font'], $this->get_tl('info_font'));

        $output->add_child([$info]);

            // form select
            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'id'=>self::module_name.'_form_font'.$this->dom_id, 'class'=>$this->css.'form_fonts form_edit', 'dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');

            $form->add_child( H::input_hidden(['name'=>$this->ifld_source_admin, 'value'=>$post[$this->ifld_source_admin], 'data-alwaysposted'=>1]) );

            if ($this->source_type == 'module') {
                $form->add_child( H::input_hidden(['name'=>'source_module', 'value'=>$post['source_module'], 'data-alwaysposted'=>1]) );
            }

                $q = 'SELECT * FROM '.$this->bddt_fonts.' WHERE id_source=? AND active<>-1 AND id NOT IN (';
                $q.= 'SELECT id_initial FROM '.$this->bddt_fonts.') ORDER BY name';
                $fonts = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_source_id]]);
                $opts_data = ['first_empty'=>['name'=>$this->get_tl('add_one_font'), 'id'=>0], 'label_key'=>'name', 'value_key'=>'id', 'options'=>$fonts, 'groups'=>['active'=>[0=>$this->get_tl('inactive'), 1=>$this->get_tl('active')]]];
                $select = H::select(['name'=>$this->ifld_fonts_id,'class'=>$this->css.'select fonts','label'=>$this->get_tl('select_fonts')], $opts_data, $post[$this->ifld_fonts_id], $this->input_action_identifier, $this->ACTION_FORM_FONT);

            $form->add_child([$select->label_tag(),$select]);

                $active = H::input_checkbox(['name'=>$this->ifld_fonts_active, 'value'=>1, 'checked'=>$post[$this->ifld_fonts_active], 'class'=>$this->css.'input_checkbox active', 'label'=>$this->get_tl('active')]);

            $form->add_child([$active->label_tag(), $active]);

                $name = H::input_text(['name'=>$this->ifld_fonts_name,'value'=>$post[$this->ifld_fonts_name],'class'=>$this->css.'input_text fonts name','label'=>$this->get_tl('name')]);
                $label_file = H::SPAN(['class'=>$this->css.'label_file_font'], $this->get_tl('font_file'));
                
                $params = ['accept'=>'.ttf,.otf,.gsf,.bdf,.pcf,.pfa,.psf,.snf,.woff,.woff2', 'options'=>false, 'delete'=>false, 'edit'=>false, 'list'=>false, 'label'=>$this->get_tl('font_file'), 'display_current'=>true];
                $file = Media_ui::display('uploader', $params, $this->ifld_fonts_file, $post[$this->ifld_fonts_id]);

            $form->add_child([$name->label_tag(), $name, $file->label_tag(), $file]);

                $div_btn = H::DIV(['class'=>$this->css.'form_btns_fonts edit_buttons']);
                    $btn_save = H::submit_button(['id'=>self::module_name.'_btn_save_font'.$this->dom_id, 'name'=>$this->input_action_identifier,'value'=>$this->ACTION_SAVE_FONT,'class'=>$this->css.'btn_save fonts button_save'],$this->get_tl('tlc_save'));
                $div_btn->add_child($btn_save);
                // display default button if initial value
                if ($post[$this->ifld_fonts_id_initial]) {
                    $btn_default = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DEFAULT_FONT, 'class'=>$this->css.'btn_default fonts', 'data-confirm'=>$this->get_tl('ask_default_fonts')], $this->get_tl('tlc_default'));
                    $div_btn->add_child( $btn_default );
                }
                if ($post[$this->ifld_fonts_id] > 0){
                    $btn_del = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_DELETE_FONT,'class'=>$this->css.'btn_del fonts button_delete', 'data-confirm'=>$this->get_tl('ask_del_fonts')],$this->get_tl('tlc_del'));
                    $div_btn->add_child($btn_del);
                }

            $form->add_child($div_btn);

        $output->add_child($form);

        return $output;
    }
    public function save_font($post){
        global $DB;
        
        $this->check_posted_data($post, 'csseditor_fonts');
        $this->check_posted_data($post, 'csseditor_source', ['id']);

        $post[$this->ifld_source_id] = $post[$this->ifld_source_id] ?: $this->create_source($post);

        $q = 'SELECT initial FROM '.$this->bddt_fonts.' WHERE id=?';
        $initial = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_fonts_id]]);

        if ($initial > 0){
            // copy row
            $id_copy = $DB->duplicate_line($this->bddt_fonts, $post[$this->ifld_fonts_id], ['active'=>0]);

            $q = 'UPDATE '.$this->bddt_fonts.' SET initial=0, id_initial='.$id_copy.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_fonts_id]]);

            // need to make a new use for this file in media db
            global $MEDIA;
            $MEDIA->copy_use($this->ifld_fonts_file.'¤'.$post[$this->ifld_fonts_id], $this->ifld_fonts_file.'¤'.$id_copy);
        }
        
        $post[$this->ifld_fonts_active] = isset($post[$this->ifld_fonts_active]) ? $post[$this->ifld_fonts_active] : 0;

        if ($post[$this->ifld_fonts_id] > 0){
            // check if the name has changed
            $q = 'SELECT name FROM '.$this->bddt_fonts.' WHERE id=?';
            $previous_name = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_fonts_id]]);
            if ($previous_name != $post[$this->ifld_fonts_name]){
                // replace old name in each rules that use it
                $q = 'SELECT * FROM '.$this->bddt_rules.' WHERE properties LIKE ? AND (id_source=? OR id_source=(SELECT id_source FROM '.$this->bddt_theme.' WHERE id=?))';
                $lst = $DB->prepared_query_list($q, 'sii', ['%'.$previous_name.'%', $post[$this->ifld_source_id], $post[$this->ifld_theme_id]]);
                if ($lst){
                    foreach($lst as $line){
                        $new_properties = \preg_replace('/(font-family:.*|font:.*)('.$previous_name.')/m', '$1'.$post[$this->ifld_fonts_name], $line['properties']);
                        $q = 'UPDATE '.$this->bddt_rules.' SET properties="'.addslashes($new_properties).'" WHERE id='.$line['id'];
                        $DB->query($q);
                    }
                }
            }

            $q = 'UPDATE '.$this->bddt_fonts.' SET name=?, active=? WHERE id=?';
            $DB->prepared_query($q,'sii',[$post[$this->ifld_fonts_name], $post[$this->ifld_fonts_active], $post[$this->ifld_fonts_id]]);
        } else {
            // Utils::error_log($post);
            $q = 'INSERT INTO '.$this->bddt_fonts.' SET id_source=?, name=?, active=?';
            $DB->prepared_query($q,'isi',[$post[$this->ifld_source_id], $post[$this->ifld_fonts_name], $post[$this->ifld_fonts_active]]);
            $post[$this->ifld_fonts_id] = $DB->last_insert_id();
        }
        
        global $MEDIA;
        $res = $MEDIA->process_media($post, $post[$this->ifld_fonts_id]);
        if (!$res) $this->add_error('media_error');

        $js = 'if ('.$this->inst_js.'.preview) h.modules.preview_a['.$this->inst_js.'.dom_id].refresh_iframe();';
        if ($this->created_source) $js.= $this->inst_js.'.source = '.json_encode(['id'=>$this->created_source, 'admin'=>$post[$this->ifld_source_admin]]).';';

        return [
            $this->form_fonts($post),
            H::script($js, ['autoremove'=>true])
        ];
    }
    public function delete_font($post){
        global $DB;

        $this->check_posted_data($post,'csseditor_fonts');

        if ($post[$this->ifld_fonts_id] == 0){
            Utils::error_log('missing id to delete fonts');
            Utils::error_log($post);
            $this->add_error('error_deleting_fonts');
            return $this->form_fonts($post);
        }

        $q = 'SELECT initial, id_initial FROM '.$this->bddt_fonts.' WHERE id=?';
        $fonts = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_fonts_id]]);

        if ($fonts['initial'] == 1) {
            // this is a default fonts, can't delete it. Force it to inactive state
            $q = 'UPDATE '.$this->bddt_fonts.' SET active=-1 WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_fonts_id]]);
            
        } else {
            // not a default fonts, can delete it
            $q = 'DELETE FROM '.$this->bddt_fonts.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_fonts_id]]);

            if ($fonts['id_initial'] > 0) {
                // this is a modified default fonts, restore id to the inactive copy stored in db
                $q = 'UPDATE '.$this->bddt_fonts.' SET id=?, id_initial=0, active=-1 WHERE id='.$fonts['id_initial'];
                $DB->prepared_query($q, 'i', [$post[$this->ifld_fonts_id]]);

                // delete use's copy
                global $MEDIA;
                $MEDIA->delete_media($this->ifld_fonts_file, $fonts['id_initial']);

            } else {
                global $MEDIA;

                $media_data = $MEDIA->get_media('csseditor_fonts-file', $post[$this->ifld_fonts_id]);
                
                $q = 'SELECT * FROM '.$this->bddt_source.' WHERE id=?';
                $source = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_source_id]]);
                global $CONFIG, $FS;
                $path = $CONFIG::HELPHP_FOLDER.'modules/';
                if ($source['type'] == 'module') {
                    $output_array = [];
                    preg_match('/modules\/(\w*)/', $source['path'], $output_array);
                    $path .= $output_array[1].'/'.($source['admin'] ? 'admin/' : 'public/');
                } else if ($source['type'] == 'theme') {
                    $path = $FS->get_file_path($source['path']);
                } else {
                    $id = explode('¤', $source['type'])[1];
                    $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id=?';
                    $name = $DB->prepared_query_value($q, 'i', [$id]);
                    $path .= 'block/'.$name.'/'.($source['admin'] ? 'admin/' : 'public/');
                }

                $path.= $post[$this->ifld_fonts_name].'.'.$FS->get_file_ext($media_data['path']);
                if (\file_exists($path)){
                    \unlink($path);
                }

                $MEDIA->delete_media($this->ifld_fonts_file, $post[$this->ifld_fonts_id]);
            }
        }

        // $id_theme = $post[$this->ifld_theme_id];
        $this->reset_fields($post,'csseditor_fonts');
        // $post[$this->ifld_theme_id] = $id_theme;

        return [$this->form_fonts($post), H::script('if ('.$this->inst_js.'.preview) h.modules.preview_a['.$this->inst_js.'.dom_id].refresh_iframe();', ['autoremove'=>true])];
    }
    public function restore_default_font($post){
        global $DB;

        $this->prepare_fields($post, 'csseditor_fonts');

        if (!$post[$this->ifld_fonts_id_initial]) {
            Utils::error_log('Error when trying to restore font');
            Utils::error_log($post);
            return;
        }

        $q = 'DELETE FROM '.$this->bddt_fonts.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_fonts_id]]);

        $q = 'UPDATE '.$this->bddt_fonts.' SET id=?, id_initial=0, active=1 WHERE id='.$post[$this->ifld_fonts_id_initial];
        $DB->prepared_query($q, 'i', [$post[$this->ifld_fonts_id]]);

        return [$this->form_fonts($post), H::script('if ('.$this->inst_js.'.preview) h.modules.preview_a['.$this->inst_js.'.dom_id].refresh_iframe();', ['autoremove'=>true])];
    }

    /**
     * Display the form to add / edit multiple classes at a time
     */
    public function form_multi_rules($post){
        $output = H::DIV(['class'=>$this->css.'subcontainer multi_rules']);

            $form_field = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'', 'class'=>$this->css.'multi_rules_form field', 'dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');

                
                $info = H::DIV(['class'=>$this->css.'info multi_rules'], $this->get_tl('multi_rules'));
                $textarea = H::input_textarea(['name'=>$this->ifld_rules_properties, 'id'=>self::module_name.'_multirules_inp'.$this->dom_id, 'class'=>$this->css.'textarea_content', 'spellcheck'=>'false']);

                $this->get_medias_list();
                $post[$this->ifld_rules_id_media] = isset($post[$this->ifld_rules_id_media]) ? $post[$this->ifld_rules_id_media] : 1;
                $opts_data = ['value_key'=>'id', 'label_key'=>'name', 'options'=>$this->medias];
                $select = H::select(['name'=>$this->ifld_rules_id_media, 'id'=>self::module_name.'_multirules_sel'.$this->dom_id, 'label'=>$this->get_tl('multi_rules_media'), 'class'=>$this->css.'choix'], $opts_data, $post[$this->ifld_rules_id_media]);

                $parse = H::BUTTON(['class'=>$this->css.'btn_parse button_action','onclick'=>$this->inst_js.'.save_multi_rules(event);'], $this->get_tl('parse'));

            $form_field->add_child([$info,$textarea,$select->label_tag(), $select,$parse]);

        $output->add_child( $form_field );

            $form_file = H::form(['action'=>$this->get_index_relative_path(),'dom_target'=>self::module_name.'_script_receiver'.$this->dom_id,'class'=>$this->css.'multi_rules_form file','dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');

                $action = H::input_hidden(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SAVE_MULTI_RULES]);
                $info = H::DIV(['class'=>$this->css.'info multi_rules file'], $this->get_tl('multi_rules_file'));
                $input_file = Media_ui::display('uploader', ['accept'=>'text/css','delete'=>false,'edit'=>false, 'options'=>false, 'submit'=>true], 'filecss', 1);
                $result = H::DIV(['id'=>self::module_name.'_script_receiver'.$this->dom_id]);

            $form_file->add_child([$info,$input_file,$action,$result]);

        $output->add_child( $form_file );

        return $output;
    }
    public function save_multi_rules(&$post) {

        $this->check_posted_data($post,'csseditor_rules',['id_media','properties']);

        $fromFile = false;
        
        if (isset($post['lstFilePath']) && $post['lstFilePath']){
            global $CONFIG;
            $file = Ajax::move_from_temp($CONFIG::HOME_FOLDER.'temp/', $post['lstFilePath']);
            if ($file){
                $str_css = file_get_contents($file[0]);
            }
            $css_parsed = $this->parse_css($str_css);
            unlink($file[0]);
            $fromFile = true;
        } else {
            $str_css = $post[$this->ifld_rules_properties];
            $css_parsed = $this->parse_css($str_css);
        }

        if (!isset($css_parsed) || !$css_parsed) {
            $this->add_error('missing_rules');
            return '';
        }

        global $DB;
        $id_source = $post[$this->ifld_source_id] ?: $this->create_source($post);
        foreach($css_parsed as $media => $css){
            if ($media == 'main') $media = '';

            // @keyframe and other @ rules are declared here
            $first_world = explode(' ', $media)[0];
            switch ($first_world){
                case '@keyframes':

                    $name = explode(' ', $media)[1];
                    $value = '';
                    foreach($css as $key => $rule) {
                        $value .= $rule['selector'] . '{' . $rule['properties'] . '}';
                    }
                    
                    $data = [];
                    // check if the keyframe exist in db
                    $q = 'SELECT id FROM '.$this->bddt_keyframes.' WHERE name=? AND id_source=?';
                    $id_keyframe = $DB->prepared_query_value($q,'si',[$name, $id_source]);

                    $data[$this->ifld_keyframes_id] = $id_keyframe ? $id_keyframe : 0;
                    $data[$this->ifld_keyframes_value] = $value;
                    $data[$this->ifld_keyframes_active] = 1;
                    $data[$this->ifld_keyframes_name] = $name;
                    $this->save_keyframes($data);

                    // nothing to do anymore in this loop
                continue 2;

                case '@media':
                case '':
                    if (!$fromFile && $media == ''){
                        // force media to the selected one
                        $id_media = $post[$this->ifld_rules_id_media];
                    } else {
                        //check if the media exist in db
                        $q = 'SELECT id FROM '.$this->bddt_media.' WHERE value=?';
                        $id_media = $DB->prepared_query_value($q,'s',[$media]);
                        if (!$id_media){ 
                            // not found, add it 
                            // retrieve max order
                            $q = 'SELECT MAX(sort_order) FROM '.$this->bddt_media;
                            $sort_order = $DB->query_value($q) + 1;
                            $q = 'INSERT INTO '.$this->bddt_media.' SET name=?, value=?, sort_order='.$sort_order;
                            $DB->prepared_query($q, 'ss', [$media, $media]);
                            $id_media = $DB->last_insert_id();
                        }
                    }
                    
                break;
                default:
                    Utils::error_log('not supported @ rules :');
                    Utils::error_log($media);
                break;
            }

            $q = 'SELECT MAX(sort_order) FROM '.$this->bddt_rules.' WHERE id_source=? AND id_media=?';
            $order = $DB->prepared_query_value($q, 'ii', [$id_source, $id_media]);
            $order = ($order) ? $order : 0;
            foreach($css as $key => $line){
                switch ($line['selector']) {
                    case 'font-face':
                        Utils::error_log('need to parse this');
                        Utils::error_log($line);
                        continue 2;
                    case ':root':
                        Utils::error_log($line);
                    break;
                    case 'keyframes':
                        Utils::error_log($line);
                    break;
                    default:
                        $properties = preg_replace('#\v|\s{2,}|#', '', stripslashes($line['properties']));
                        $properties = preg_replace('#\t+#', '', $properties);

                        
                        $this->save_or_update_class($line['selector'], $properties, $id_source, $id_media, 1);
                    break;
                }
            }
        }

        // if ($fromFile){
        //     return H::script($this->inst_js.'.refresh_list_rules();');
        // }
        return $id_source;
    }
    /**
     * Parse css to an array ordonate by media
     */
    public static function parse_css($css) {
        if (!$css) return false;

        // remove all comment
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        // clean all vertical whitespace and undesirable horizontal whitespace
        $css = preg_replace('#\v|\s{2,}|#', '', $css);
        
        // start by splitting the css by character
        $chars = str_split($css);
        
        $current_media = 'main'; // the current media
        $reading_media = false; // true when reading the media, between @ and {
        $nbr_bracket = 0; // if more than 1 when saving properties, we are inside a media, otherwise it's in the main media
        $medias = []; // to save the ordoned rules
        $str = '';
        $selector = '';
        $properties = '';
        foreach($chars as $char){
            // if ($char == ' ') continue;
            switch($char){
                case '{':
                    // either a selector or a media, reading_media indicate it
                    $nbr_bracket++;
                    if ($reading_media){
                        $current_media = '@'.trim($str);
                        $reading_media = false;
                    } else {
                        $selector = trim($str);
                    }
                    $str = '';
                break;
                case '}':
                    // end of properties or a media
                    // nbr bracket at 0 indicates that we are back to the main media
                    $nbr_bracket--;
                    $properties = trim($str);
                    $str = '';

                    if ($selector == '' && $properties != ''){
                        // for specific @ rules like font-face, they don't have sub selector
                        // put the @ rules as selector, they will be added to main media and parsed
                        $selector = $current_media;
                    }

                    if ($nbr_bracket == 0) {
                        $current_media = 'main';
                    }

                    if ($selector != '' && $properties != ''){
                        if (!isset($medias[$current_media])) $medias[$current_media] = [];
                        array_push($medias[$current_media], ['selector'=>$selector,'properties'=>$properties]);
                        $selector = '';
                        $properties = '';
                    }
                break;
                case '@':
                    $reading_media = true;
                break;
                default:
                    $str.= $char;
                break;
            }
        }
        
        return $medias;
    }
    public function load_rules($post) {
        global $DB;

        $this->check_posted_data($post,'csseditor_rules', ['selector']);
        $this->check_posted_data($post,'csseditor_source', ['id']);

        $q = 'SELECT DISTINCT active, id_media, properties, active, if (id_initial > 0, 1, 0) as initial FROM '.$this->bddt_rules;
        $q.=' WHERE selector=? AND id_source=? AND active > -1 AND id NOT IN (SELECT DISTINCT id_initial FROM '.$this->bddt_rules.')';
        $lst = $DB->prepared_query_list($q, 'si', [$post[$this->ifld_rules_selector], $post[$this->ifld_source_id]]);

        $ids = array_column($lst, 'id_media');
        $data = array_combine($ids, $lst);

        return $data ? json_encode($data) : '';
    }
    public function save_rules($post) {
        global $DB;

        $this->check_posted_data($post,'csseditor_rules',['selector']);
        $this->check_posted_data($post,'csseditor_source',['id']);

        if (!isset($post['property'])) return false;
        
        $id_source = $post[$this->ifld_source_id] ?: $this->create_source($post);
        foreach($post['property'] as $id_media => $value){
            $this->save_or_update_class($post[$this->ifld_rules_selector], str_replace('\r\n', '', trim($value)), $id_source, $id_media, $post['active'][$id_media]);
        }

        return true;
    }

    public function save_or_update_class($selector, $properties, $id_source, $id_media, $active){
        global $DB;

        $properties = preg_replace('#\v|\s{2,}|#', '', stripslashes($properties));
        $properties = preg_replace('#\t+#', '', $properties);

        $q = 'SELECT id, initial, rule.id_initial FROM '.$this->bddt_rules.' rule WHERE selector=? AND id_media=? AND id_source=?';
        $q.=' AND id NOT IN (SELECT DISTINCT id_initial FROM '.$this->bddt_rules.')';
        $data = $DB->prepared_query_line($q, 'sii', [$selector, $id_media, $id_source]);
        // $data = $DB->prepared_query_line($q, $q_t, $q_v);
        if ($data){
            if ($data['initial'] > 0){
                // copy row
                $id_copy = $DB->duplicate_line($this->bddt_rules, $data['id'], ['active'=>0]);
                $q = 'UPDATE '.$this->bddt_rules.' SET initial=0, id_initial='.$id_copy.' WHERE id=?';
                $DB->prepared_query($q, 'i', [$data['id']]);
            }

            $q = 'UPDATE '.$this->bddt_rules.' SET properties=?, active=? WHERE id='.$data['id'];
            $DB->prepared_query($q, 'si', [$properties, $active]);

        } else if ($properties != '' || $properties == 'force' ) {

            // get sort_order
            $q = 'SELECT MAX(sort_order) FROM '.$this->bddt_rules.' WHERE id_media=? AND id_source=?';
            $order = $DB->prepared_query_value($q, 'ii', [$id_media, $id_source]);
            $order++;

            // in some case we need to insert a rules with no properties. To make that possible we set the properties to force
            // otherwise it will fail the test on empty and we want to control if the rule can be created with no properties.
            $properties = $properties == 'force' ? '' : $properties;

            $q = 'INSERT INTO '.$this->bddt_rules.' SET id_source=?, id_media=?, selector=?, properties=?, active=?, sort_order='.$order;
            $DB->prepared_query($q, 'iissi', [$id_source, $id_media, $selector, $properties, $active]);
        }
    }

    public function delete_rules($post) {
        global $DB;

        $this->check_posted_data($post, 'csseditor_rules', ['selector']);
        $this->check_posted_data($post, 'csseditor_source', ['id']);

        $q = 'SELECT * FROM '.$this->bddt_rules.' WHERE selector=? AND id_source=? AND active<>-1';
        $rules = $DB->prepared_query_list($q, 'si', [$post[$this->ifld_rules_selector], $post[$this->ifld_source_id]]);
        $initial = $rules[0]['initial'];

        if ($initial == 1) {
            // this is a default media, can't delete it. Force it to inactive state
            foreach($rules as $rule) {
                $q = 'UPDATE '.$this->bddt_rules.' SET active=-1 WHERE id=?';
                $DB->prepared_query($q, 'i', [$rule['id']]);
            }
        } else {
            // not a default variable, can delete it
            foreach($rules as $rule) {
                $q = 'DELETE FROM '.$this->bddt_rules.' WHERE id=?';
                $DB->prepared_query($q, 'i', [$rule['id']]);

                if ($rule['id_initial'] > 0) {
                    // this is a modified default media, restore id to the inactive copy stored in db
                    $q = 'UPDATE '.$this->bddt_rules.' SET id=?, id_initial=0, active=-1 WHERE id='.$rule['id_initial'];
                    $DB->prepared_query($q, 'i', [$rule['id']]);
                }
            }
        }

        return true;
    }
    public function restore_default_rules($post){
        global $DB;

        $this->check_posted_data($post, 'csseditor_source', ['id']);
        $this->check_posted_data($post, 'csseditor_rules', ['id_media', 'selector']);

        $q = 'SELECT * FROM '.$this->bddt_rules.' WHERE id_media=? AND selector=? AND id_source=? AND id NOT IN ';
        $q.= '(SELECT id_initial FROM '.$this->bddt_rules.')';
        $rule = $DB->prepared_query_line($q, 'isi', [$post[$this->ifld_rules_id_media], $post[$this->ifld_rules_selector], $post[$this->ifld_source_id]]);
        
        if (!$rule['id_initial']) {
            Utils::error_log('Error when trying to restore variable');
            Utils::error_log($post);
            Utils::error_log($rule);
            return;
        }

        $q = 'DELETE FROM '.$this->bddt_rules.' WHERE id='.$rule['id'];
        $DB->query($q);

        $q = 'UPDATE '.$this->bddt_rules.' SET id='.$rule['id'].', id_initial=0, active=1 WHERE id='.$rule['id_initial'];
        $DB->query($q);

        return $this->load_rules($post);
    }
    public function change_order_rule($post){
        global $DB;

        if (!isset($post['orders']) || !$post['orders']){
            $this->add_error('missing_data_request');
            return;
        }
        
        foreach($post['orders'] as $key => $data){
            $q = 'UPDATE '.$this->bddt_rules.' SET sort_order=? WHERE id=?';
            $DB->prepared_query($q, 'ii', [$data['value'], $data['id']]);
        }
    }

    public function form_import($post){
        global $DB, $CONFIG, $FS;

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'dom_id'=>$this->dom_id]);

            $fieldset = H::fieldset(null, $this->get_tl('ttl_import'));

        $form->add_child($fieldset);

        $q = 'SELECT DISTINCT * FROM '.$this->bddt_source.' WHERE type LIKE "theme"';
        $lst = $DB->query_list($q);
        
        $base_path = $CONFIG::HOME_FOLDER.'css/theme/';
        $themes = $FS->recurse_ls($base_path)['folders'];
        // if (file_exists($base_path.'admin/')) $themes = array_merge($themes, $FS->recurse_ls($base_path.'admin/')['folders']);
        // if (file_exists($base_path.'public/')) $themes = array_merge($themes, $FS->recurse_ls($base_path.'public/')['folders']);
        // $admin = $FS->recurse_ls($base_path.'admin/)['folders'];
        // $folder
        
        // $files = $FS->recurse_ls($path_files)['files'];
        // Utils::error_log($files);
        if (!$themes){
            $fieldset->add_child( H::DIV(['class'=>$this->css.'import_no_file'], $this->get_tl('no_theme_to_import')) );
            // return $fieldset;
        }
        
        $not_imported = [];
        foreach($themes as $theme_path){
            if (!str_contains($theme_path, '/')) continue; // ignore admin and public folder to get only theme's folder
            foreach($lst as $line){
                if ($line['path'] == $base_path.$theme_path.'/theme.css'){
                    continue 2;
                }
            }
            array_push($not_imported, $theme_path);
        }

        // if (!$not_imported) {
        //     $fieldset->add_child( H::DIV(['class'=>$this->css.'import no_file'], $this->get_tl('all_theme_imported')) );
        //     return $fieldset;
        // }

        if ($not_imported){
            foreach($not_imported as $theme_path){
                // theme file name are composed like name¤type.css
                // if (!str_contains($theme_path, '¤')){
                //     Utils::error_log('Theme filename badly formatted : '.$theme_path.PHP_EOL.'It should be formatted like theme_name¤type.css type is either admin or public');
                //     continue;
                // }
                // // type is admin or public
                // $noext = $FS->get_file_name_noext($theme_path);
                // $t = explode('¤', $noext);
                // $name = $t[0];
                // $type = $t[1];

                if (str_contains($theme_path, 'admin')) $type = 'admin';
                else $type = 'public';
                $t = explode('/', $theme_path);
                $name = array_pop($t);
    
                $line = H::DIV();
                    $dom_type = H::SPAN(null, ' - '.$type);
                    $dom_name = H::SPAN(null, $name);
                    $button = H::submit_button_single(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_IMPORT, 'data-parameters'=>['path'=>$base_path.$theme_path, 'name'=>$name, 'admin'=>$type == 'admin' ? 1 : 0]], $this->get_tl('import'));
                $line->add_child( [$dom_name, $dom_type, $button] );
                $fieldset->add_child( $line );
            }
        } else {
            $fieldset->add_child( H::DIV(['class'=>$this->css.'import no_file'], $this->get_tl('all_theme_imported')) );
        }
        
        $fieldset = H::fieldset(['class'=>$this->css.'info multi_rules file'], $this->get_tl('import_file_theme'));
            $action = H::input_hidden(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_UPLOAD_THEME]);
            $input_file = Media_ui::display('uploader', ['accept'=>'.zip', 'delete'=>false, 'edit'=>false, 'options'=>false, 'submit'=>true], 'filecss_theme', 1);
        $fieldset->add_child( [$action, $input_file] );
        $form->add_child( $fieldset );

        return $form;
    }
    public function upload_theme(&$post){
        global $MEDIA;

        if (isset($post['lstFilePath']) && $post['lstFilePath']){
            global $CONFIG, $DB, $FS;
            $name = preg_replace('/-(admin|public)\.zip.*/', '', $FS->get_file_name($post['lstFilePath'][0]));
            $admin = str_contains($FS->get_file_name($post['lstFilePath'][0]), '-admin.zip');
            $q = 'SELECT id FROM '.$this->bddt_theme.' WHERE name=? AND admin=?';
            $exist = $DB->prepared_query($q, 'si', [$name, ($admin ? 1 : 0)]);
            if ($exist){
                $this->add_error('theme_same_name_exist', $name);
                return;
            }

            $path = $CONFIG::HOME_FOLDER.'css/theme/';
            $path.= ($admin) ? 'admin/' : 'public/';
            $file = Ajax::move_from_temp($CONFIG::HOME_FOLDER.'css/theme', $post['lstFilePath']);
            if ($file){
                $FS->unpack($file[0], $path);
                $FS->delete($file[0]);

                $post['path'] = $path.$name;
                $post['name'] = $name;
                $post['admin'] = $admin;

                $this->import($post);

                return [H::script('h.modules.tabs_a.main.refresh_active();'), $this->get_tl('import_success')];
            }
        } else {
            $this->add_error('theme_upload_no_file');
        }
    }
    public function import($post){
        global $CONFIG, $DB, $FS;

        $path = rtrim($post['path'], '/').'/theme.css';
        $md5 = \md5_file($path);

        $q = 'INSERT INTO '.$this->bddt_source.' SET type="theme", path=? , md5=?, admin=?';
        $DB->prepared_query($q, 'ssi', [$path, $md5, $post['admin']]);
        $id_source = $DB->last_insert_id();

        $q = 'INSERT INTO '.$this->bddt_theme.' SET name=?, id_source=?, admin=?';
        $DB->prepared_query($q, 'sii', [$post['name'], $id_source, $post['admin']]);

        $this->import_css_source($path, $id_source);

        $q = 'SELECT DISTINCT * FROM '.$this->bddt_source.' WHERE type LIKE "module"';
        $lst = $DB->query_list($q);

        // check if all the modules have been imported and import missing
        $not_imported = [
            'admin'=>[],
            'public'=>[]
        ];
        foreach($CONFIG::MODULES_LIST as $module => $data){
            $path_admin = $CONFIG::HELPHP_FOLDER.'modules/'.$module.'/admin/'.$module.'.css';
            if (is_file($path_admin)){
                $found = false;
                foreach($lst as $line){
                    if ($line['path'] == $path_admin){
                        $found = true;
                    }
                }
                if (!$found) array_push($not_imported['admin'], $path_admin);
            }
            
            $path_public = str_replace('admin', 'public', $path_admin);
            if (is_file($path_public)){
                $found = false;
                foreach($lst as $line){
                    if ($line['path'] == $path_public){
                        $found = true;
                    }
                }
                if (!$found) array_push($not_imported['public'], $path_public);
            }
        }

        if ($not_imported){
            foreach($not_imported as $type => $lst_path){
                foreach ($lst_path as $path) {
                    $md5 = \md5_file($path);
                    $q = 'INSERT INTO '.$this->bddt_source.' SET type="module", path=?, md5=?, admin='.($type == 'admin' ? '1' : '0');
                    $DB->prepared_query($q, 'ss', [$path, $md5]);
                    $id_source = $DB->last_insert_id();

                    $this->import_css_source($path, $id_source, true);
                }
            }
        }

        return [H::script('h.modules.tabs_a.main.refresh_active();'), $this->get_tl('import_success')];
    }

    public function download_theme($post){
        // Utils::error_log($post);

        if (!isset($_SESSION['theme_file_to_download'])) return;

        global $FS, $CONFIG;
        $tmp_path = $CONFIG::HOME_FOLDER.'temp/';

        \helPHP\libs\Media::send_file($tmp_path.$_SESSION['theme_file_to_download'], false);

        $res = $FS->delete($tmp_path.'theme/');
        $res = $FS->delete($tmp_path.$FS->get_file_name($_SESSION['theme_file_to_download']));
    }
    public function prepare_extract($post){
        global $DB, $FS;

        $output = H::group('extract_result');

        $data_return = ['type'=>$post['extracting']];

        switch($post['extracting']) {
            case 'theme':
                $q = 'SELECT source.* FROM '.$this->bddt_source.' source, '.$this->bddt_theme.' theme WHERE';
                $q.=' theme.id_source=source.id AND theme.id=?';
                $source = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_theme_id]]);
                $res = $this->extract_source($source);
                if ($res){
                    $q = 'SELECT name FROM '.$this->bddt_theme.' WHERE id=?';
                    $name = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_theme_id]]);
                    $output->add_child( $this->get_tl('extract_success_theme', $name) );
                } else {
                    $output->add_child( $this->get_tl('error_extract') );
                }
                $data_return['name'] = $name;
                
                // create zip
                global $CONFIG;
                $tmp_path = $CONFIG::HOME_FOLDER.'temp/theme/';
                if (!file_exists($tmp_path)) {
                    $FS->mkdir($tmp_path);
                }

                $path = $FS->get_file_path($source['path']);
                if (str_contains($path, 'admin')) {
                    $data_return['name'].= '-admin';
                } else {
                    $data_return['name'].= '-public';
                }
            
                $FS->copy($path, $tmp_path);

                $FS->pack([$tmp_path], $CONFIG::HOME_FOLDER.'temp/', $data_return['name'], 'zip');
                // store the zip name in the session. Use it in the download theme function
                $_SESSION['theme_file_to_download'] = $data_return['name'].'.zip';
                
            break;
            case 'module':
                if ($post[$this->ifld_source_id] == 0) return json_encode(['type'=>$post['extracting'], 'msg'=>$this->get_tl('no_module_selected')], JSON_UNESCAPED_UNICODE);;

                $q = 'SELECT * FROM '.$this->bddt_source.' WHERE id=?';
                $source = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_source_id]]);
                $res = $this->extract_source($source);
                if ($res){
                    $module_name = $FS->get_file_name_noext($source['path']);
                    $name = $this->get_translated_text_from_other_module($module_name,true,'module_name');
                    $output->add_child( $this->get_tl('extract_success_module', $name) );
                } else {
                    $output->add_child( $this->get_tl('error_extract') );
                }
            break;
            case 'all_module':
                $q = 'SELECT * FROM '.$this->bddt_source.' WHERE type="module"';
                $list = $DB->query_list($q);
                if ($list){
                    foreach($list as $source){
                        $res = $this->extract_source($source);
                        $module_name = $FS->get_file_name_noext($source['path']);
                        $name = $this->get_translated_text_from_other_module($module_name,true,'module_name');
                        if ($res){
                            $output->add_child( $this->get_tl('extract_success_module', $name) );
                        } else {
                            $output->add_child( $this->get_tl('extract_error_module', $name) );
                        }
                        $output->add_child( H::BR() );
                    }
                }
            break;
            default:
                Utils::error_log('extracting type not known');
            break;
        }

        $data_return['msg'] = ''.$output;

        return json_encode($data_return, JSON_UNESCAPED_UNICODE);
    }
    public function extract_source($source, $to_return = false){
        global $DB,$FS;

        $css = [];

        $parent = false;
        if (str_contains($source['type'], '¤')){
            $type = explode('¤', $source['type'])[0];
            $id = explode('¤', $source['type'])[1];
            $parent = '.'.$type.'_'.$id;
        }

        self::get_css_variables($css, $source['id'], $parent, true);
        self::get_css_fonts($css, $source, true);
        self::get_css_keyframes($css, $source['id'], $parent);

        // all the rules
        $q = 'SELECT DISTINCT rul.id_media, med.value as media, med.sort_order, rul.selector, rul.properties FROM '.$this->bddt_rules.' rul';
        $q.=' LEFT JOIN '.$this->bddt_media.' med ON (med.id=rul.id_media AND med.active=1) WHERE';
        $q.=' rul.id_source=? AND rul.active=1 ORDER BY med.sort_order,rul.sort_order';
        $list = $DB->prepared_query_list($q, 'i', [$source['id']]);
        foreach ($list as $key => $line) {
            if (!isset($css[$line['media']])) {
                $css[$line['media']] = '';
                // if ($css[$line['media']] != '') $css[$line['media']] .= $line['media'].'{';
            }
            $css[$line['media']] .= $line['selector'].' {'.$line['properties'].'}'.PHP_EOL;
        }

        // concat everything to form the final file and add it to the dom
        $str_css = '';
        foreach ($css as $media => $val) {
            if ($media == '') {
                $str_css .= $val;
            } else {
                $str_css .= $media.'{'.$val.'}';
            }
        }

        if ($to_return) {
            return $str_css;
        }

        if ($str_css) {
            // create folder if needed
            $folder = $FS->get_file_path($source['path']);
            if (!\file_exists($folder)){
                $FS->mkdir($folder);
            }
            // Utils::error_log($source);
            $res = $FS->save_content($source['path'], $str_css);
        }else if (is_file($source['path'])) {
            global $FS;
            $FS->delete($source['path']);
            $res = true;
        } else $res = true;
        

        return $res !== false;
    }

    public static function get_css_variables(&$css, $id_source, $parent = false, $extracting = false) {
        global $DB;

        // retrieve variables and add them to :root{}
        $q = 'SELECT var.id_media, med.value as media, med.sort_order, var.name, var.properties FROM '.$DB->table('csseditor_variables').' var LEFT JOIN '.$DB->table('csseditor_media').' med ON (var.id_media=med.id AND med.active=1) WHERE var.id_source=? AND var.active=1 ORDER BY med.sort_order';
        $variables = $DB->prepared_query_list($q,'i',[$id_source]);
        if ($variables) {
            $to_close = [];
            foreach($variables as $key => $variable){
                if ($variable['properties'] == '') continue;
                if (!isset($css[$variable['media']])) {
                    if ($variable['id_media'] == 1 || $extracting) {
                        if ($parent === false) $css[$variable['media']] = ':root {';
                        else $css[$variable['media']] = $parent.' {';
                    } else {
                        if ($parent === false) $css[$variable['media']] = $variable['media'].' { :root {';
                        else $css[$variable['media']] = $variable['media'].' { '.$parent.' {';
                    }
                }
                $css[$variable['media']].= $variable['name'].':'.$variable['properties'].';';
                if (!isset($to_close[$variable['media']])) $to_close[$variable['media']] = true;
            }
            // close :root
            foreach($to_close as $key => $bool){
                $css[$key].= '}'.PHP_EOL;
            }
        }
    }
    public static function get_css_fonts(&$css, $source, $extract = false){
        global $DB, $CONFIG, $MEDIA;

        // add font-face
        $q = 'SELECT DISTINCT id, name FROM '.$DB->table('csseditor_fonts').' WHERE id_source=? AND active=1';
        $fonts = $DB->prepared_query_list($q,'i',[$source['id']]);
        if ($fonts){
            
            if ($extract && !\str_contains($source['type'], 'document')) {
                // preparing data to extract in foreach
                $path = $CONFIG::HELPHP_FOLDER.'modules/';
                if ($source['type'] == 'module') {
                    $output_array = [];
                    preg_match('/modules\/(\w*)/', $source['path'], $output_array);
                    $path .= $output_array[1].'/'.($source['admin'] ? 'admin/' : 'public/');
                } else if ($source['type'] == 'theme') {
                    global $FS;
                    $path = $FS->get_file_path($source['path']);
                } else {
                    $id = explode('¤', $source['type'])[1];
                    $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id=?';
                    $name = $DB->prepared_query_value($q, 'i', [$id]);
                    $path .= 'block/'.$name.'/'.($source['admin'] ? 'admin/' : 'public/');
                }
                $to_keep = [];
            }

            foreach($fonts as $font) {
                $file = $MEDIA->get_media('csseditor_fonts-file', $font['id']);
                if ($file){
                    $str = '@font-face {font-family: "'.$font['name'].'"; src: url("'.$CONFIG::BASE_URL.'files/'.$file['path'].'");}';
                    if (!isset($css[''])) $css[''] = '';
                    $css[''] .= $str.PHP_EOL;
                    if ($extract && !\str_contains($source['type'], 'document')) {
                        // copy the file to the folder to permit it's extraction with the .css file
                        global $FS;
                        $new_name = $font['name'].'.'.$FS->get_file_ext($file['path']);
                        $file_path = $CONFIG::HOME_FOLDER.'files/'.$file['path'];
                        $FS->copy([['path'=>$file_path, 'name'=>$new_name]], $path);
                        array_push($to_keep, $new_name);
                    }
                }
            }
        }
    }
    public static function get_css_keyframes(&$css, $id_source){
        global $DB;

        // add keyframe
        $q = 'SELECT DISTINCT id, name, value FROM '.$DB->table('csseditor_keyframes').' WHERE id_source=? AND active=1';
        $keyframes = $DB->prepared_query_list($q,'i',[$id_source]);
        if ($keyframes){
            global $MEDIA;
            foreach($keyframes as $keyframe) {
                $keyframe['name'] = str_starts_with($keyframe['name'], '@keyframes') ? $keyframe['name'] : '@keyframes '.$keyframe['name'];
                $str = $keyframe['name'].'{'.$keyframe['value'].'}';
                $css[''] .= $str.PHP_EOL;
            }
        }
    }
    /**
     * retrieve css from a theme and from the module.
     * To display all the css needed for the display
     */
    public static function get_css($id_theme) {
        global $DB;

        $css = [];

        $q = 'SELECT id_source as id, admin FROM '.$DB->table('csseditor_theme').' WHERE id=?';
        $source =  $DB->prepared_query_line($q, 'i', [$id_theme]);
        if (!$source) return;

        self::get_css_variables($css, $source['id']);
        self::get_css_fonts($css, $source);
        self::get_css_keyframes($css, $source['id']);

        // add keyframe and fonts from module too
        $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type="module" AND admin=?';
        $lst = $DB->prepared_query_list($q, 'i', [$source['admin']]);
        if ($lst){
            foreach ($lst as $key => $line) {
                // create parent to add the variable to other than :root
                $output_array = [];
                preg_match('/modules\/(.*)\//', $line['path'], $output_array);
                $module_name = $output_array[1];
                
                $parent = '.'.$module_name.'_';
                $parent.= ($line['admin']) ? 'admin_' : '';
                $parent.= 'container';

                self::get_css_variables($css, $line['id'], $parent);
                self::get_css_fonts($css, $line);
                self::get_css_keyframes($css, $line['id']);
            }
        }

        // all the rules
        $q = 'SELECT DISTINCT rul.id_media, med.value as media, med.sort_order, rul.selector, rul.properties FROM '.$DB->table('csseditor_rules').' rul';
        $q.=' LEFT JOIN '.$DB->table('csseditor_media').' med ON (med.id=rul.id_media AND med.active=1) WHERE';
        $q.=' (rul.id_source=? OR rul.id_source IN (SELECT id FROM '.$DB->table('csseditor_source').' WHERE type="module" AND admin=?)) AND rul.active=1 ORDER BY med.sort_order, rul.sort_order';
        $list = $DB->prepared_query_list($q, 'ii', [$source['id'], $source['admin']]);
        foreach ($list as $key => $line) {
            if ($line['properties'] == '') continue;
            if (!isset($css[$line['media']])) {
                if ($line['media'] == '') $css[$line['media']] = $line['media'];
                else $css[$line['media']] = $line['media'].'{';
            }
            $css[$line['media']] .= $line['selector'].' {'.$line['properties'].'}';
        }

        // concat everything to form the final file and add it to the dom
        $str_css = '';
        foreach ($css as $media => $val) {
            if ($media == '') {
                $str_css .= $val;
            } else {
                $str_css .= $val.'}';
            }
        }

        // a réactiver si les images public ne se charge pas
        // $str_css = preg_replace('/(url\(["|\'])(.*\))/', "$1../../$2", $str_css);

        return $str_css;
    }

    public function form_keyframes($post){
        global $DB;

        $this->prepare_fields($post, 'csseditor_keyframes');
        $this->prepare_fields($post, 'csseditor_source', ['id']);

        if (!$post[$this->ifld_keyframes_id]){
            $post[$this->ifld_keyframes_active] = 1;
        }

        // form select
        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>'.parent', 'id'=>self::module_name.'_form_keyframe'.$this->dom_id, 'class'=>$this->css.'form_keyframes form_edit', 'dom_id'=>$this->dom_id], '', $this->inst_js.'.add_data_to_submit');

            $q = 'SELECT * FROM '.$this->bddt_keyframes.' WHERE id_source=? AND active<>-1 AND id NOT IN (';
            $q.= 'SELECT id_initial FROM '.$this->bddt_keyframes.') ORDER BY name';
            $keyframes = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_source_id]]);
            $opts_data = ['first_empty'=>['name'=>$this->get_tl('add_one_keyframes'), 'id'=>0], 'label_key'=>'name', 'value_key'=>'id', 'options'=>$keyframes, 'groups'=>['active'=>[0=>$this->get_tl('inactive'), 1=>$this->get_tl('active')]]];
            $select = H::select(['name'=>$this->ifld_keyframes_id,'class'=>$this->css.'select keyframes','label'=>$this->get_tl('select_keyframes')], $opts_data, $post[$this->ifld_keyframes_id], $this->input_action_identifier, $this->ACTION_FORM_KEYFRAMES);

        $form->add_child([$select->label_tag(),$select]);

            $active = H::input_checkbox(['name'=>$this->ifld_keyframes_active, 'value'=>1, 'checked'=>$post[$this->ifld_keyframes_active], 'class'=>$this->css.'input_checkbox active', 'label'=>$this->get_tl('active')]);

        $form->add_child([$active->label_tag(), $active]);

            $name = H::input_text(['name'=>$this->ifld_keyframes_name,'value'=>$post[$this->ifld_keyframes_name],'class'=>$this->css.'input_text keyframes name','label'=>$this->get_tl('name')]);
            $value = H::input_textarea(['id'=>self::module_name.'_keyframes_input_value'.$this->dom_id, 'name'=>$this->ifld_keyframes_value, 'value'=>'', 'class'=>$this->css.'textarea keyframes', 'label'=>$this->get_tl('keyframes_content')]);

        $form->add_child([$name->label_tag(), $name, $value->label_tag(), $value]);

            $div_btn = H::DIV(['class'=>$this->css.'form_btns_keyframes edit_buttons']);
                $btn_save = H::submit_button(['id'=>self::module_name.'_btn_save_keyframe'.$this->dom_id, 'name'=>$this->input_action_identifier,'value'=>$this->ACTION_SAVE_KEYFRAMES,'class'=>$this->css.'btn_save keyframes button_save'],$this->get_tl('tlc_save'));
            $div_btn->add_child($btn_save);
            // display default button if initial value
            if ($post[$this->ifld_keyframes_id_initial]) {
                $btn_default = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_DEFAULT_KEYFRAMES, 'class'=>$this->css.'btn_default keyframes', 'data-confirm'=>$this->get_tl('ask_default_keyframes')], $this->get_tl('tlc_default'));
                $div_btn->add_child( $btn_default );
            }
            if ($post[$this->ifld_keyframes_id] > 0){
                $btn_del = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_DELETE_KEYFRAMES,'class'=>$this->css.'btn_del keyframes button_delete', 'data-confirm'=>$this->get_tl('ask_del_keyframes')],$this->get_tl('tlc_del'));
                $div_btn->add_child($btn_del);
            }

        $form->add_child($div_btn);

        if ($post[$this->ifld_keyframes_id] > 0){
            // add the value threw js function that parse the css to make it readable
            $js = 'document.getElementById("'.self::module_name.'_keyframes_input_value'.$this->dom_id.'").value = '.$this->inst_js.'.readable_css("'.$post[$this->ifld_keyframes_value].'");';
            $form->add_child(H::script($js, ['autoremove'=>1]));
        }

        return $form;
    }
    public function save_keyframes(&$post){
        global $DB;
        
        $this->check_posted_data($post, 'csseditor_keyframes');
        $this->check_posted_data($post, 'csseditor_source', ['id']);

        $post[$this->ifld_source_id] = $post[$this->ifld_source_id] ?: $this->create_source($post);

        $q = 'SELECT initial FROM '.$this->bddt_keyframes.' WHERE id=?';
        $initial = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_keyframes_id]]);

        if ($initial > 0){
            // copy row
            $id_copy = $DB->duplicate_line($this->bddt_keyframes, $post[$this->ifld_keyframes_id], ['active'=>0]);

            $q = 'UPDATE '.$this->bddt_keyframes.' SET initial=0, id_initial='.$id_copy.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_keyframes_id]]);
        }
        
        $post[$this->ifld_keyframes_active] = isset($post[$this->ifld_keyframes_active]) ? $post[$this->ifld_keyframes_active] : 0;
        $post[$this->ifld_keyframes_value] = $this->reduce_css($post[$this->ifld_keyframes_value]);

        if ($post[$this->ifld_keyframes_id] > 0){
             // check if the name has changed
            $q = 'SELECT name FROM '.$this->bddt_keyframes.' WHERE id=?';
            $previous_name = $DB->prepared_query_value($q, 'i', [$post[$this->ifld_keyframes_id]]);
            if ($previous_name != $post[$this->ifld_keyframes_name]){
                // replace old name in each rules that use it
                $q = 'SELECT * FROM '.$this->bddt_rules.' WHERE properties LIKE ? AND (id_source=? OR id_source=(SELECT id_source FROM '.$this->bddt_theme.' WHERE id=?))';
                $lst = $DB->prepared_query_list($q, 'sii', ['%'.$previous_name.'%', $post[$this->ifld_source_id], $post[$this->ifld_theme_id]]);
                if ($lst){
                    foreach($lst as $line){
                        $new_properties = \preg_replace('/(animation-name:.*|animation:.*)('.$previous_name.')/m', '$1'.$post[$this->ifld_keyframes_name], $line['properties']);
                        $q = 'UPDATE '.$this->bddt_rules.' SET properties="'.addslashes($new_properties).'" WHERE id='.$line['id'];
                        $DB->query($q);
                    }
                }
            }

            $q = 'UPDATE '.$this->bddt_keyframes.' SET name=?, value=?, active=? WHERE id=?';
            $DB->prepared_query($q,'ssii',[$post[$this->ifld_keyframes_name], $post[$this->ifld_keyframes_value], $post[$this->ifld_keyframes_active], $post[$this->ifld_keyframes_id]]);
        } else {
            $q = 'INSERT INTO '.$this->bddt_keyframes.' SET id_source=?, value=?, name=?, active=?';
            $DB->prepared_query($q,'issi',[$post[$this->ifld_source_id], $post[$this->ifld_keyframes_value], $post[$this->ifld_keyframes_name], $post[$this->ifld_keyframes_active]]);
            $post[$this->ifld_keyframes_id] = $DB->last_insert_id();
        }

        $js = 'if ('.$this->inst_js.'.preview) h.modules.preview_a['.$this->inst_js.'.dom_id].refresh_iframe();';
        if ($this->created_source) $js.= $this->inst_js.'.source = '.json_encode(['id'=>$this->created_source, 'admin'=>$post[$this->ifld_source_admin]]).';';

        return [
            $this->form_keyframes($post),
            H::script($js, ['autoremove'=>true])
        ];
    }
    public function delete_keyframes($post){
        global $DB;

        $this->check_posted_data($post, 'csseditor_keyframes');

        if ($post[$this->ifld_keyframes_id] == 0){
            Utils::error_log('missing id to delete keyframes');
            Utils::error_log($post);
            $this->add_error('error_deleting_keyframes');
            return $this->form_keyframes($post);
        }

        $q = 'SELECT initial, id_initial FROM '.$this->bddt_keyframes.' WHERE id=?';
        $keyframes = $DB->prepared_query_line($q, 'i', [$post[$this->ifld_keyframes_id]]);

        if ($keyframes['initial'] == 1) {
            // this is a default keyframes, can't delete it. Force it to inactive state
            $q = 'UPDATE '.$this->bddt_keyframes.' SET active=-1 WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_keyframes_id]]);
        } else {
            // not a default keyframes, can delete it
            $q = 'DELETE FROM '.$this->bddt_keyframes.' WHERE id=?';
            $DB->prepared_query($q, 'i', [$post[$this->ifld_keyframes_id]]);

            if ($keyframes['id_initial'] > 0) {
                // this is a modified default keyframes, restore id to the inactive copy stored in db
                $q = 'UPDATE '.$this->bddt_keyframes.' SET id=?, id_initial=0, active=-1 WHERE id='.$keyframes['id_initial'];
                $DB->prepared_query($q, 'i', [$post[$this->ifld_keyframes_id]]);
            }
        }

        // $id_theme = $post[$this->ifld_theme_id];
        $this->reset_fields($post, 'csseditor_keyframes');
        // $post[$this->ifld_theme_id] = $id_theme;

        return $this->form_keyframes($post);
    }
    public function restore_default_keyframes($post){
        global $DB;

        $this->prepare_fields($post, 'csseditor_keyframes');

        if (!$post[$this->ifld_keyframes_id_initial]) {
            Utils::error_log('Error when trying to restore keyframes');
            Utils::error_log($post);
            return;
        }

        $q = 'DELETE FROM '.$this->bddt_keyframes.' WHERE id=?';
        $DB->prepared_query($q, 'i', [$post[$this->ifld_keyframes_id]]);

        $q = 'UPDATE '.$this->bddt_keyframes.' SET id=?, id_initial=0, active=1 WHERE id='.$post[$this->ifld_keyframes_id_initial];
        $DB->prepared_query($q, 'i', [$post[$this->ifld_keyframes_id]]);

        return $this->form_keyframes($post);
    }

    /**
     * parse css to one row
     */
    public function reduce_css($css){

        // remove all comment
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        // remove useless spaces
        $css = preg_replace('#\v|\s{2,}|#', '', $css);
        // remove useless tab
        $css = preg_replace('#\t+#', '', $css);

        return $css;
    }

    // both following function are specific of managing sources from file.
    public static function import_css_source($path, $id_source, $is_module = false){
        global $DB;

        $is_admin = \str_contains($path, 'admin') ? 1 : 0;

        $css_str = \file_get_contents($path);
        if (!$css_str) return;

        $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE id=?';
        $source = $DB->prepared_query_line($q, 'i', [$id_source]);

        $css_parsed = Csseditor::parse_css($css_str);
        foreach($css_parsed as $media => $css){
            if ($media == 'main') $media = '';
            
            // @keyframe and other @ rules are declared here
            $first_world = explode(' ', $media)[0];
            switch ($first_world){
                
                case '@keyframes':
                
                    $name = explode(' ', $media)[1];
                    $value = '';
                    foreach($css as $key => $rule){
                        $value .= $rule['selector'] . '{' . $rule['properties'] . '}';
                    }

                    $q = 'INSERT INTO '.$DB->table('csseditor_keyframes').' SET id_source=?, name=?, value=?, active=1, initial=1';
                    $DB->prepared_query($q, 'iss', [$id_source, $name, $value]);
                
                // nothing more to do in this loop
                continue 2;

                case '@media':
                case '':
                    //check if the media exist in db
                    $q = 'SELECT id FROM '.$DB->table('csseditor_media').' WHERE value=?';
                    $id_media = $DB->prepared_query_value($q,'s',[$media]);
                    if (!$id_media){
                        // not found, add it 
                        // retrieve max order
                        $q = 'SELECT MAX(sort_order) FROM '.$DB->table('csseditor_media');
                        $sort_order = $DB->query_value($q) + 1;
                        $q = 'INSERT INTO '.$DB->table('csseditor_media').' SET name=?, value=?, active=1, sort_order='.$sort_order;
                        $DB->prepared_query($q, 'ss', [$media, $media]);
                        $id_media = $DB->last_insert_id();
                    }
                break;
                default:
                    Utils::error_log('not supported @ rules :');
                    Utils::error_log($media);
                break;
            }
            
            $q = 'SELECT MAX(sort_order) FROM '.$DB->table('csseditor_rules').' WHERE id_source=? AND id_media=?';
            $order = $DB->prepared_query_value($q, 'ii', [$id_source, $id_media]);
            $order = ($order) ? $order : 0;
            foreach($css as $key => $line){
                switch ($line['selector']) {
                    case '@font-face':
                        global $MEDIA, $CONFIG, $FS;
                        $vals = explode(';', $line['properties']);
                        $name = '';
                        $src = '';
                        foreach ($vals as $key => $val) {
                            $val = trim($val);
                            if (\str_starts_with($val, 'font-family')) {
                                $t = explode(':', $val);
                                $name = trim(trim($t[1]), '"');
                            }
                            if (\str_starts_with($val, 'src')) {
                                $matches = [];
                                preg_match('/"(?:[^\/]*\/){3}(.*)"/', $val, $matches); // get everything after the third / in url
                                $src = $matches[1];
                            }
                        }

                        if (!$name){
                            Utils::error_log('name empty when importing font for source id '.$id_source.PHP_EOL.'name : '.$name.' - file');
                            Utils::error_log($line);
                            continue 2;
                        }

                        // retrieve the file in the right folder depending source type (except document)
                        $path = $CONFIG::HELPHP_FOLDER.'modules/';
                        if ($source['type'] == 'module') {
                            $output_array = [];
                            preg_match('/modules\/(\w*)/', $source['path'], $output_array);
                            $path .= $output_array[1].'/'.($source['admin'] ? 'admin/' : 'public/');
                        } else if ($source['type'] == 'theme') {
                            global $FS;
                            $path = $FS->get_file_path($source['path']);
                        } else {
                            $id = explode('¤', $source['type'])[1];
                            $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id=?';
                            $name = $DB->prepared_query_value($q, 'i', [$id]);
                            $path .= 'block/'.$name.'/'.($source['admin'] ? 'admin/' : 'public/');
                        }

                        $ext = $FS->get_file_ext($src);
                        $path .= $name.'.'.$ext;
                        if (!\file_exists($path)){
                            Utils::error_log('file not found when importing font for source id '.$id_source.PHP_EOL.'name : '.$name.' - file : '.$path);
                            Utils::error_log($line);
                            continue 2;
                        }

                        $q = 'INSERT INTO '.$DB->table('csseditor_fonts').' SET id_source=?, name=?, active=1, initial=1, id_initial=0';
                        $DB->prepared_query($q, 'is', [$id_source, $name]);
                        $id_font = $DB->last_insert_id();

                        global $USER;
                        // create the path that will be saved in media_data
                        $media_path = 'csseditor/'.$USER->id.'/';
                        $media_id = 'csseditor_fonts-file¤'.$id_font;
                        $media_name = $media_id.'¤0¤0.'.$ext;
                        // copy to media folder
                        if (!\file_exists($CONFIG::HOME_FOLDER.'files/'.$media_path)) {
                            $FS->mkdir($CONFIG::HOME_FOLDER.'files/'.$media_path);
                        }
                        $FS->copy([['path'=>$path, 'name'=>$media_name]], $CONFIG::HOME_FOLDER.'files/'.$media_path);
                        
                        // add to media table
                        $id_media_font = $MEDIA->save_media($media_id, 0, 0, $media_path.$media_name, $FS->get_file_name($path), 1, 0);
                        $MEDIA->save_use($media_id, 0, 0, $id_media_font, 0);
                    break;
                    case ':root':
                        $q = 'INSERT INTO '.$DB->table('csseditor_variables').' (id_source, id_media, name, properties, active, initial) VALUES ';
                        $vars = explode(';', $line['properties']);
                        foreach($vars as $var){
                            if (!$var) continue;
                            $t = explode(':', $var);
                            $name = trim($t[0]);
                            $prop = trim($t[1]);
                            $q.= '('.$id_source.', '.$id_media.', "'.$name.'", "'.$prop.'", 1, 1),';
                        }
                        $q = \substr($q, 0, -1);
                        $DB->query($q);
                        continue 2;
                    break;
                    case '@keyframes':
                        Utils::error_log('keyframe detected');
                        Utils::error_log($line);
                    break;
                    default:

                        $properties = preg_replace('#\v|\s{2,}|#', '', stripslashes($line['properties']));
                        $properties = preg_replace('#\t+#', '', $properties);

                        $order++;

                        $q = 'INSERT INTO '.$DB->table('csseditor_rules').' SET admin='.$is_admin.', id_source=?, id_media=?, selector=?, properties=?, active=1, sort_order='.$order.', initial=1';
                        $DB->prepared_query($q, 'iiss', [$id_source, $id_media, $line['selector'], $properties]);
                        
                    break;
                }
            }
        }

    }
    public static function compare_css_source($source, $delete_not_found = true) {
        global $DB;

        if (isset($source['add_css'])){
            $css_str = $source['add_css'];
        } else {
            if (!is_file($source['path'])){
                // problem when importing the new css file. stop execution of this function and return an error message
                Utils::error_log('file '.$source['path'].' not found. Can\'t import.');
                return false;
            }
            $css_str = \file_get_contents($source['path']);
        }

        if (!isset($css_str) || !$css_str) return false;
        
        $db_rules = $DB->table('csseditor_rules');
        // $db_source = $DB->table('csseditor_source');
        $db_media = $DB->table('csseditor_media');
        $db_keyframe = $DB->table('csseditor_keyframes');
        $db_variables = $DB->table('csseditor_variables');
        $db_font = $DB->table('csseditor_fonts');

        $in_file_keyframe_id = [];
        $in_file_variables_id = [];
        $in_file_rules_id = [];
        $in_file_font_id = [];
        $rules_with_modification = [];

        $css_parsed = Csseditor::parse_css($css_str);
        foreach($css_parsed as $media => $css){
            if ($media == 'main') $media = '';
            
            // @keyframe and other @ rules are declared here
            $first_world = explode(' ', $media)[0];
            switch ($first_world){
                case '@keyframes':

                    $name = explode(' ', $media)[1];
                    $value = '';
                    foreach($css as $key => $rule) {
                        $value .= $rule['selector'] . '{' . $rule['properties'] . '}';
                    }
                    
                    // check if the keyframe exist in db
                    $q = 'SELECT id FROM '.$db_keyframe.' WHERE name=? AND id_source=?';
                    $id_keyframe = $DB->prepared_query_value($q,'si',[$name, $source['id']]);
                    if ($id_keyframe){
                        array_push($in_file_keyframe_id, $id_keyframe);
                        $q = 'UPDATE '.$db_keyframe.' SET value=? WHERE id=?';
                        $DB->prepared_query($q,'si',[$value, $id_keyframe]);
                    } else {
                        $q = 'INSERT INTO '.$db_keyframe.' SET id_source=?, name=?, value=?, active=1, initial=1';
                        $DB->prepared_query($q,'iss',[$source['id'], $name, $value]);
                        array_push($in_file_keyframe_id, $DB->last_insert_id());
                    }

                    // nothing to do anymore in this loop
                continue 2;

                case '@media':
                case '':
                    //check if the media exist in db
                    $q = 'SELECT id FROM '.$db_media.' WHERE value=?';
                    $id_media = $DB->prepared_query_value($q,'s',[$media]);
                    if (!$id_media){ 
                        // not found, add it 
                        // retrieve max order
                        $q = 'SELECT MAX(sort_order) FROM '.$db_media;
                        $sort_order = $DB->query_value($q) + 1;
                        $q = 'INSERT INTO '.$db_media.' SET name=?, value=?, sort_order='.$sort_order;
                        $DB->prepared_query($q, 'ss', [$media, $media]);
                        $id_media = $DB->last_insert_id();
                    }
                break;
                default:
                    Utils::error_log('not supported @ rules :');
                    Utils::error_log($media);
                break;
            }

            $q = 'SELECT MAX(sort_order) FROM '.$db_rules.' WHERE id_source=? AND id_media=?';
            $order = $DB->prepared_query_value($q, 'ii', [$source['id'], $id_media]);
            $order = ($order) ? $order : 0;
            foreach($css as $key => $line){
                switch ($line['selector']) {
                    case '@font-face':
                        global $MEDIA, $CONFIG, $FS;
                        $vals = explode(';', $line['properties']);
                        $name = '';
                        $src = '';
                        foreach ($vals as $key => $val) {
                            $val = trim($val);
                            if (\str_starts_with($val, 'font-family')) {
                                $t = explode(':', $val);
                                $name = trim(trim($t[1]), '"');
                            }
                            if (\str_starts_with($val, 'src')) {
                                $matches = [];
                                preg_match('/"(?:[^\/]*\/){3}(.*)"/', $val, $matches); // get everything after the third / in url
                                $src = $matches[1];
                            }
                        }

                        if (!$name){
                            Utils::error_log('name empty when importing font for source id '.$source['id'].PHP_EOL.'name : '.$name.' - file');
                            Utils::error_log($line);
                            continue 2;
                        }

                        // retrieve the file in the right folder depending source type (except document)
                        $path = $CONFIG::HELPHP_FOLDER.'modules/';
                        if ($source['type'] == 'module') {
                            $output_array = [];
                            preg_match('/modules\/(\w*)/', $source['path'], $output_array);
                            $path .= $output_array[1].'/'.($source['admin'] ? 'admin/' : 'public/');
                        } else if ($source['type'] == 'theme') {
                            global $FS;
                            $path = $FS->get_file_path($source['path']);
                        } else {
                            $id = explode('¤', $source['type'])[1];
                            $q = 'SELECT name FROM '.$DB->table('block_data').' WHERE id=?';
                            $name = $DB->prepared_query_value($q, 'i', [$id]);
                            $path .= 'block/'.$name.'/'.($source['admin'] ? 'admin/' : 'public/');
                        }

                        $ext = $FS->get_file_ext($src);
                        $path .= $name.'.'.$ext;
                        if (!\file_exists($path)){
                            Utils::error_log('file not found when importing font for source id '.$source['id'].PHP_EOL.'name : '.$name.' - file : '.$path);
                            Utils::error_log($line);
                            continue 2;
                        }

                        $q = 'SELECT * FROM '.$db_font.' WHERE id_source='.$source['id'].' AND name LIKE ?';
                        $font_data = $DB->prepared_query_line($q, 's', [$name]);
                        if ($font_data){
                            array_push($in_file_font_id, $font_data['id']);
                            $media_data = Media::get_media('csseditor_fonts-file', $font_data['id']);
                            if (md5_file($CONFIG::HOME_FOLDER.'files/'.$media_data['path']) != md5_file($path)){
                                $FS->copy([['path'=>$path, 'name'=>$FS->get_file_name($media_data['path'])]], $FS->get_file_path($CONFIG::HOME_FOLDER.'files/'.$media_data['path']));
                            }
                        } else {
                            $q = 'INSERT INTO '.$DB->table('csseditor_fonts').' SET id_source=?, name=?, active=1, initial=1, id_initial=0';
                            $DB->prepared_query($q, 'is', [$source['id'], $name]);
                            $id_font = $DB->last_insert_id();
                            array_push($in_file_font_id, $id_font);

                            global $USER;
                            // create the path that will be saved in media_data
                            $media_path = 'csseditor/'.$USER->id.'/';
                            $media_id = 'csseditor_fonts-file¤'.$id_font;
                            $media_name = $media_id.'¤0¤0.'.$ext;
                            // copy to media folder
                            if (!\file_exists($CONFIG::HOME_FOLDER.'files/'.$media_path)) {
                                $FS->mkdir($CONFIG::HOME_FOLDER.'files/'.$media_path);
                            }
                            $FS->copy([['path'=>$path, 'name'=>$media_name]], $CONFIG::HOME_FOLDER.'files/'.$media_path);
                            
                            // add to media table
                            $id_media_font = $MEDIA->save_media($media_id, 0, 0, $media_path.$media_name, $FS->get_file_name($path), 1, 0);
                            $MEDIA->save_use($media_id, 0, 0, $id_media_font, 0);
                        }
                    break;
                    case ':root':
                        $vars = explode(';', $line['properties']);
                        foreach($vars as $var){
                            if (!$var) continue;
                            $t = explode(':', $var);
                            $name = trim($t[0]);
                            $prop = trim($t[1]);
                            $q = 'SELECT * FROM '.$db_variables.' WHERE id_source='.$source['id'].' AND name LIKE ?';
                            $db_var = $DB->prepared_query_line($q, 's', [$name]);
                            if ($db_var){
                                array_push($in_file_variables_id, $db_var['id']);
                                if ($db_var['properties'] != $prop){
                                    $q = 'UPDATE '.$db_variables.' SET properties=? WHERE id=?';
                                    $DB->prepared_query($q, 'si', [$prop, $db_var['id']]);
                                }
                            } else {
                                $q = 'INSERT INTO '.$db_variables.' SET id_source='.$source['id'].', id_media='.$id_media.', name=?, properties=?';
                                $DB->prepared_query($q, 'ss', [$name, $prop]);
                                array_push($in_file_variables_id, $DB->last_insert_id());
                            }
                        }
                    break;
                    case '@keyframes':
                        Utils::error_log($line);
                    break;
                    default:
                        $properties = preg_replace('#\v|\s{2,}|#', '', stripslashes($line['properties']));
                        $properties = preg_replace('#\t+#', '', $properties);

                        // initial is the id of the rules corresponding to the one in the file
                        // modified (if any) is the id of the modified rules corresponding to the one in the file
                        // if I modify a rules that come from a source there will be a copy of it and that's the modified.
                        $q = 'SELECT initial.id as id_initial, modified.id as id_modified, initial.properties FROM '.$db_rules.' initial';
                        $q.=' LEFT JOIN '.$db_rules.' modified ON (modified.id_initial = initial.id) WHERE initial.selector=?';
                        $q.=' AND initial.id_media=? AND initial.id_source=?';
                        $data = $DB->prepared_query_line($q, 'sii', [$line['selector'], $id_media, $source['id']]);
                        if ($data){
                            if ($properties == $data['properties']) {
                                \array_push($in_file_rules_id, $data['id_initial']);
                                continue 2;
                            }

                            if ($data['id_modified'] > 0){
                                $data['id_media'] = $id_media;
                                \array_push($rules_with_modification, $data);
                            }
                            
                            \array_push($in_file_rules_id, $data['id_initial']);
                            $q = 'UPDATE '.$db_rules.' SET properties=? WHERE id='.$data['id_initial'];
                            $DB->prepared_query($q, 's', [$properties]);
                        } else {
                            $order++;
                            $q = 'INSERT INTO '.$db_rules.' SET id_source=?, id_media=?, selector=?, properties=?, active=1, sort_order='.$order.', initial=1';
                            $DB->prepared_query($q, 'iiss', [$source['id'], $id_media, $line['selector'], $properties]);
                            array_push($in_file_rules_id, $DB->last_insert_id());
                        }
                    break;
                }
            }
        }

        if ($delete_not_found) {
            // delete rules / variables / keyframes that don't exist in the source anymore
            // only if they weren't created by the user. 
            $in_file_variables_id = count($in_file_variables_id) > 0 ? $in_file_variables_id : [0];
            $q = 'DELETE FROM '.$db_variables.' WHERE id_source='.$source['id'].' AND id NOT IN ('.implode(',', $in_file_variables_id).') AND initial=1';
            $DB->query($q);

            $in_file_rules_id = count($in_file_rules_id) > 0 ? $in_file_rules_id : [0];
            $q = 'DELETE FROM '.$db_rules.' WHERE id_source='.$source['id'].' AND id NOT IN ('.implode(',', $in_file_rules_id).') AND initial=1';
            $DB->query($q);

            $in_file_keyframe_id = count($in_file_keyframe_id) > 0 ? $in_file_keyframe_id : [0];
            $q = 'DELETE FROM '.$db_keyframe.' WHERE id_source='.$source['id'].' AND id NOT IN ('.implode(',', $in_file_keyframe_id).') AND initial=1';
            $DB->query($q);

            $in_file_font_id = count($in_file_font_id) > 0 ? $in_file_font_id : [0];
            // select all the font to delete 
            $q = 'SELECT id FROM '.$db_font.' WHERE id_source='.$source['id'].' AND id NOT IN ('.implode(',', $in_file_font_id).') AND initial=1';
            $fonts = $DB->query_list($q);
            if ($fonts) {
                foreach($fonts as $key => $id_font){
                    global $MEDIA;
                    $MEDIA->delete_media('csseditor_fonts-file', $id_font);
                }
                $q = 'DELETE FROM '.$db_font.' WHERE id_source='.$source['id'].' AND id NOT IN ('.implode(',', $in_file_font_id).') AND initial=1';
                $DB->query($q);
            }
        }

        return $rules_with_modification;
        // return true;
    }

    /**
     * return a string containing the css for a source
     */
    public static function get_css_source($type, $id, $admin = false){
        global $DB;

        $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type=? AND admin=?';
        $source = $DB->prepared_query_line($q, 'si', [$type.'¤'.$id, ($admin ? 1 : 0)]);
        if (!$source){
            return '';
        }
        
        $instance = new Csseditor();
        return $instance->extract_source($source, true);

    }
    /**
     * delete admin/public of a source
     */
    public static function delete_css_source($type, $id, $oly_admin_or_public = false){
        global $DB;

        $id_source = 0;
        if ($type == 'module'){
            $q = 'SELECT id FROM '.$DB->table('csseditor_source').' WHERE type=? AND path=?';
            if ($oly_admin_or_public !== false) $q.=' AND admin='.($oly_admin_or_public == 'admin' ? 1 : 0);
            $id_sources = $DB->prepared_query_list($q, 'ss', [$type, $id]);
        } else {
            $q = 'SELECT id FROM '.$DB->table('csseditor_source').' WHERE type=?';
            if ($oly_admin_or_public !== false) $q.=' AND admin='.($oly_admin_or_public == 'admin' ? 1 : 0);
            $id_sources = $DB->prepared_query_list($q, 's', [$type.'¤'.$id]);
        }
        
        if (!$id_sources){
            return true;
        }

        $tables_to_delete_from = [
            $DB->table('csseditor_variables'),
            $DB->table('csseditor_fonts'),
            $DB->table('csseditor_keyframes'),
            $DB->table('csseditor_rules')
        ];

        foreach($id_sources as $id_source){
            foreach($tables_to_delete_from as $table) {
                $q = 'DELETE FROM '.$table.' WHERE id_source=?';
                $DB->prepared_query($q,'i',[$id_source]);
            }

            $q = 'DELETE FROM '.$DB->table('csseditor_source').' WHERE id=?';
            $DB->prepared_query($q, 'i', [$id_source]);
        }
        
        return true;
    }
    /**
     * duplicate admin/public of a source
     */
    public static function duplicate_source($type, $id, $new_id){
        global $DB;

        $q = 'SELECT * FROM '.$DB->table('csseditor_source').' WHERE type=?';
        $sources = $DB->prepared_query_list($q, 's', [$type.'¤'.$id]);

        foreach($sources as $source){
            $q = 'INSERT INTO '.$DB->table('csseditor_source').' SET type=?, admin=?, path=?, md5=?';
            $DB->prepared_query($q, 'siss', [$type.'¤'.$new_id, $source['admin'], str_replace($type.'¤'.$id, $type.'¤'.$new_id, $source['path']), $source['md5']]);
            $new_id_source = $DB->last_insert_id();

            // copy variables
            $q = 'INSERT INTO '.$DB->table('csseditor_variables').'(id_source,id_media,name,properties,active) SELECT ?,id_media,name,properties,active FROM '.$DB->table('csseditor_variables').' WHERE id_source=?';
            $DB->prepared_query($q,'ii',[$new_id_source, $source['id']]);
            
            // copy keyframes
            $q = 'INSERT INTO '.$DB->table('csseditor_keyframes').'(id_source,name,value,active) SELECT ?,name,value,active FROM '.$DB->table('csseditor_keyframes').' WHERE id_source=?';
            $DB->prepared_query($q,'ii',[$new_id_source, $source['id']]);
            
            // copy fonts
            $q = 'INSERT INTO '.$DB->table('csseditor_fonts').'(id_source,name,active) SELECT ?,name,active FROM '.$DB->table('csseditor_fonts').' WHERE id_source=?';
            $DB->prepared_query($q,'ii',[$new_id_source, $source['id']]);

            // copy rules
            $q = 'INSERT INTO '.$DB->table('csseditor_rules').'(id_source,id_media,selector,properties,`sort_order`,active) SELECT ?,id_media,selector,properties,`sort_order`,active FROM '.$DB->table('csseditor_rules').' WHERE id_source=?';
            $DB->prepared_query($q,'ii',[$new_id_source, $source['id']]);
        }
    }

    
}