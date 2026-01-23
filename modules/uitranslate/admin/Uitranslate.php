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

namespace helPHP\modules\uitranslate\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Htmlgroup;
use helPHP\libs\Utils;
use helPHP\libs\Rest;

class Uitranslate extends HelPHP_module{

    const module_name = 'uitranslate';

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,true);
        parent::__construct();
    }
    
    private $ACTION_LOAD = self::module_name.'_load';
    private $ACTION_EDIT = self::module_name.'_edit'; 
    private $ACTION_SAVE = self::module_name.'_save'; 
    private $ACTION_NEWTL = self::module_name.'_newtl'; 
    private $ACTION_PREPARE = self::module_name.'_prepare';
    
    
    public function process_data(&$post,$toreturn=false){
        global $USER;
        parent::process_data($post);
        $master_output = H::group('uitranslate_display');
        if (!in_array(2,$USER->allowed_groups()) || $USER->admin){
            switch($post[$this->input_action_identifier]){
                case $this->ACTION_LOAD:
                    $master_output->add_child( $this->Menu($post) );
                break;   
                case $this->ACTION_EDIT:
                    $master_output->add_child( $this->Menu($post) );
                    if(isset($post['tlfile']) && $post['tlfile']!=''){
                        $master_output->add_child( $this->Data_Edit($post) );
                    }
                break;
                
                case $this->ACTION_SAVE:
                    $this->Data_Save($post);
                break;
                case $this->ACTION_NEWTL:
                    $master_output->add_child($this->New_TL($post));
                break;

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
    }
    
    //affiche le select pour sélectionner le module
    public function Menu ($post) {
        global $CONFIG,$FS;
        $output = H::group('menu');

            $title = H::DIV(['class'=>$this->css.'title module_title'], $this->get_tl('title'));

            $form = H::form(array('name'=>'uitranslatemenuform','action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form_select form_select'));
                
                $opts = [];
                array_push($opts,['value'=>'common','label'=>'Common']);
                
                if(!isset($post['framemode'])) $post['framemode']='0';
                // Utils::error_log($post['framemode']);
                if($post['framemode']=='1'){
                    //we're working on all module and all languages !
                    $framework_level=H::input_checkbox(array('name'=>'framemode', 'label'=>$this->get_tl('framework_level'),'value'=>'1','CHECKED'=>'CHECKED','onchange'=>'event.target.value="0";H_ajax.submit_on_change(event.target);'));
                    $lst=$FS->shell_ls($CONFIG::HELPHP_FOLDER.'modules','',true,false);
                    foreach($lst['folders'] as $module){
                        $label = $this->get_translated_text_from_other_module($module['name'],true,'module_name');
                        if (!str_contains($label, '{')){
                            array_push($opts,['value'=>$module['name'],'label'=>$label]);
                        }
                        if($label=='{module_name}'){
                            array_push($opts,['value'=>$module['name'],'label'=>$module['name']]);
                        }
                    }
                
                }else{
                    //only those local to this instance.
                    $framework_level=H::input_checkbox(array('name'=>'framemode', 'label'=>$this->get_tl('framework_level'),'value'=>'0','onchange'=>'event.target.value="1";H_ajax.submit_on_change(event.target);'));
                    $lst = $CONFIG::MODULES_LIST;
                    ksort($lst,SORT_STRING);
                    foreach($lst as $moduleName=>$nothing){
                    
                        $label = $this->get_translated_text_from_other_module($moduleName,true,'module_name');
                        //  Utils::error_log($moduleName.'->'.$label);
                        //if (!str_contains($label, '{')){
                        //    array_push($opts,['value'=>$moduleName,'label'=>$label]);
                        //}
                        //if($label=='{module_name}'){
                            array_push($opts,['value'=>$moduleName,'label'=>$moduleName]);
                        //}
                    }
                }
                if(!isset($post['fromtl'])) $post['fromtl']='1';
                if($post['fromtl']=='1'){
                    $fromtl=H::input_checkbox(array('name'=>'fromtl', 'label'=>$this->get_tl('from_tl'),'value'=>'1','CHECKED'=>'CHECKED','onchange'=>'event.target.value="0";H_ajax.submit_on_change(event.target);'));
                }else{
                    $fromtl=H::input_checkbox(array('name'=>'fromtl', 'label'=>$this->get_tl('from_tl'),'value'=>'0','onchange'=>'event.target.value="1";H_ajax.submit_on_change(event.target);'));
                }
                $options_data = array('first_empty'=>true, 'value_key'=>'value', 'label_key'=>'label', 'options'=>$opts);
                $selected = isset($post['module']) ? $post['module'] : 0;
                $select = H::select(array('name'=>'module', 'label'=>$this->get_tl('select_module'), 'class'=>'uitranslate_admin_select', 'data-alwaysposted'=>1,'onchange'=>'H_ajax.submit_on_change(event.target);'), $options_data, $selected);
            $form->add_child([$fromtl->label_tag(),$fromtl,$framework_level->label_tag(),$framework_level,$select->label_tag(),$select]);
            if ($selected!=0){
                $form->add_child( $this->Data_Load_Selector($post) );
            }
        $output->add_child([$title,$form]);    
        return $output;
    }
    //créer un selecteur pour loader les fichiers du module choisit
    public function Data_Load_Selector($post) {
        GLOBAl $CONFIG,$FS;
        $output = H::group('menu_load');
            
                $opts = [];
                if ($post['module']=='common') {
                    array_push($opts,['value'=>$CONFIG::HELPHP_FOLDER.'libs/tl/tl_common.php','label'=>'libs/tl/tl_common.php']);
                    array_push($opts,['value'=>$CONFIG::HELPHP_FOLDER.'libs/tl/tl_constants.php','label'=>'libs/tl/tl_constants.php']);
                }else{
                    $lst=$FS->recurse_ls($CONFIG::HELPHP_FOLDER.'modules/'.$post['module'],$CONFIG::HELPHP_FOLDER.'modules/'.$post['module'].'/');
                    foreach($lst['files'] as $file){
                        $ext=$FS->get_file_ext($file);
                        $fname=$FS->get_file_name($file);
                        if( $ext == 'php' && $fname!='index.php' && $fname!='install.php' && !str_starts_with($fname,'tl_')){
                            array_push($opts,['value'=>$file,'label'=>$file]);
                        }
                    }
                    // foreach($lst['folders'] as $folder){
                    //     foreach($folder['childs']['files'] as $file){
                    //             $ext=$FS->get_file_ext($file['name']);
                    //             if( $ext == 'php' && $file['name']!='index.php' && $file['name']!='install.php' && !str_starts_with($file['name'],'tl_')){
                    //                 array_push($opts,['value'=>$CONFIG::HELPHP_FOLDER.'modules/'.$post['module'].'/'.$folder['name'].'/'.$file['name'],'label'=>$post['module'].'/'.$folder['name'].'/'.$file['name']]);
                    //             }
                    //         }
                    // }
                }
                $options_data = array('first_empty'=>true, 'value_key'=>'value', 'label_key'=>'label', 'options'=>$opts);
                $selected = isset($post['tlfile']) ? $post['tlfile'] : 0;
                $select = H::select(array('name'=>'tlfile', 'label'=>$this->get_tl('select_tlfile'), 'class'=>'uitranslate_admin_select', 'data-alwaysposted'=>1,'onchange'=>'document.uitranslatemenuform.'.$this->input_action_identifier.'.value="'.$this->ACTION_EDIT.'";H_ajax.submit_on_change(event.target);'), $options_data, $selected);
                if ($selected!=0){
                    $output->add_child( H::input_hidden(array('name'=>$this->input_action_identifier, 'value'=>$this->ACTION_EDIT)) );
                }else{
                    $output->add_child( H::input_hidden(array('name'=>$this->input_action_identifier, 'value'=>$this->ACTION_LOAD)) );
                }
        $output->add_child([$select->label_tag(),$select]);
        return $output;
    }
    //edite la tl du fichier choisit
    public function Data_Edit($post) {
        GLOBAl $CONFIG,$FS,$DB;

        $output = H::group('edit');

        $output->add_child(H::div(array('id'=>'uiedittitle'), $post['tlfile']));
        $output->add_child(H::div(array('id'=>'uieditspace')));

        //parsing
        if (!str_starts_with($post['tlfile'], $CONFIG::HELPHP_FOLDER.'libs/tl/') ){
            $filecontent = file_get_contents($post['tlfile']);
            $strings = explode('$this->get_tl(', $filecontent);
            array_shift($strings);
            $founded = [];
            //minimal 
            $founded['module_name'] = '';
            // Utils::error_log($strings);
            foreach($strings as $key => $string) {
                $pos_comma = strpos($string,',');
                $pos_parenthesis = strpos($string,')');
                if ($pos_comma != false && $pos_comma < $pos_parenthesis){
                    $id = trim(substr($string, 0, $pos_comma));
                    $params = trim(substr($string, $pos_comma + 1, $pos_parenthesis));
                }else{
                    $id = trim(substr($string, 0, $pos_parenthesis));
                    $params = '';
                }
                $id = trim($id,'\'"');
                if (!str_starts_with($id,'tlc')){
                    $founded[$id] = $params;
                }
            }
            $libsmode = false;
        }else{
            //flag for tl in libs
            $founded = [];
            $libsmode = true;
        }
        $tabl = H::div(array('id'=>'fieldstab'));
        //searching tl_xx files
        $workingpath = $FS->get_file_path($post['tlfile']);
        $lst = $FS->shell_ls($workingpath, '', true, true, true);
        $tls = [];
        foreach($lst['files'] as $file){
           
                if ($libsmode){
                    if (str_starts_with($file['name'], 'tl_common') && $FS->get_file_name($post['tlfile']) == 'tl_common.php') {
                        include($workingpath.$file['name']);
                        if (isset($tl_data)) {
                            $tls = array_merge($tls, $tl_data);
                            unset($tl_data);
                        }
                    }
                     if (str_starts_with($file['name'], 'tl_constants') && $FS->get_file_name($post['tlfile']) == 'tl_constants.php') {
                        include($workingpath.$file['name']);
                        if (isset($tl_data)) {
                            $tls = array_merge($tls, $tl_data);
                            unset($tl_data);
                        }
                    }
                    
                }else{
                    if(str_starts_with($file['name'], 'tl_'.strtolower($FS->get_file_name_noext($post['tlfile'])))){
                        include($workingpath.$file['name']);
                        if (isset($tl_data)) {
                            $tls = array_merge($tls, $tl_data);
                            unset($tl_data);
                        }
                    }
                }
            
        }
        $post['tlfile']=str_replace('tl_','',$post['tlfile']);
        if(!isset($post['framemode'])) $post['framemode'] = '0';
        $langdata = $CONFIG::AVAILABLE_LANGUAGES;
        if ($post['framemode'] == '1') {
            $q = 'SELECT DISTINCT libretranslate FROM '.$DB->table('languages_data');
            $langdata = $DB->query_list($q);
        }
        
        //little prepare for libs
        if ($libsmode || $post['fromtl'] == 1){
            foreach ($langdata as $lng_data) {
                if (isset($tls[$lng_data])){
                    foreach ($tls[$lng_data] as $id => $string ) {
                        if(!isset($founded[$id])){
                            $founded[$id] = '';
                        }
                    }
                }
                
            }
        }
        $grid = [];
        $count = 0;
        foreach ($founded as $id => $param ) {
            
            $gridItem = [];
            $gridItem['Key'] = H::span(['class'=>'tl_key'], $id);
            $gridItem['Parameters'] = H::span(['class'=>'tl_param'], $param);
            foreach ($langdata as $lng_data) {
                $tval = (isset($tls[$lng_data][$id])) ? $tls[$lng_data][$id] : '';
                $gridItem[$lng_data] = H::input_text(['name'=>$lng_data.'[\''.$id.'\']', 'lang'=>$lng_data, 'value'=>stripslashes($tval)]);
            }
            $gridItem['delete'] = H::button(['class'=>$this->css.'btn_delete', 'data-confirm'=>$this->get_tl('tlc_ask_delete'), 'onclick'=>'h.modules.uitranslate_a["'.$this->dom_id.'"].delete_word(event,"'.$this->dom_id.'");'], $this->get_tl('tlc_del'));
            array_push($grid, $gridItem);
            $count++;
        }
        //exclude of saving or translatation
        $gridItem = [];
        $gridItem['Key'] = '';
        $gridItem['Parameters'] = '';
        foreach ($langdata as $lng_data) {
            $gridItem[$lng_data] = [H::span(['class'=>'tl_exclude'], $this->get_tl('exclude')), H::input_checkbox(['id'=>'check-'.$lng_data,'class'=>$this->css.'check_translate', ], $this->get_tl('autotranslate'))];
        }
        array_push($grid, $gridItem);
        //if we can auto-translate
        $lang_count = sizeof($langdata);
        if($lang_count > 1 && $CONFIG::LIBTRANSLATE_URL != ''){
            $gridItem = [];
            $gridItem['Key'] = '';
            $gridItem['Parameters'] = '';
            foreach ($langdata as $lng_data) {
                $gridItem[$lng_data] = H::BUTTON(['class'=>$this->css.'btn_translate', 'onclick'=>'h.modules.uitranslate_a["'.$this->dom_id.'"].translate("'.$lng_data.'");'], $this->get_tl('tlc_translate'));
            }
            array_push($grid, $gridItem);
            $script = H::script('Uitranslate_a.create_instance("'.$this->dom_id.'");');
            $langs = H::input_hidden(['id'=>'langsiso', 'value'=>json_encode($langdata)]);
            $output->add_child([$langs, $script]);
        }
        $tabl->add_child(H::simple_data_grid($grid, null, 'tlfields'));
        $btn_new = H::button(['id'=>'btnEdit', 'class'=>'btnEdit', 'onclick'=>'h.modules.uitranslate_a["'.$this->dom_id.'"].open_modal("'.$this->dom_id.'");'], 'New word');
        
        $tabl->add_child([$btn_new]);
        $tfile = H::input_hidden(['id'=>'tlfile','value'=>$post['tlfile']]);

        $output->add_child([$tabl,$tfile]);
        
        $block_btn = H::DIV(['class'=>$this->css.'block_btns edit_buttons']);
            $btn_save = H::BUTTON(['class'=>$this->css.'btn_save button_save', 'onclick'=>'h.modules.uitranslate_a["'.$this->dom_id.'"].save_all();'], $this->get_tl('tlc_save'));
        $block_btn->add_child( $btn_save );
        $output->add_child( $block_btn );
        
        return $output;
    }
    //sauve les tl
    public function Data_Save($post) {
        GLOBAl $FS;
        // Utils::error_log($post);
        $path = $FS->get_file_path($post['tlfile']);
        $basename = strtolower($FS->get_file_name_noext($post['tlfile']));
        if ($basename == ''){
            $basename = strtolower($FS->get_file_name($post['tlfile']));
        }
        $targetPath = $path.'tl_'.$basename.'-'.$post['iso'].'.php';
        if (is_file($targetPath)){
            unlink($targetPath);
        }
        //cleaning data
        $tldata = [];
        foreach ($post['translated'] as $key => $value) {
            if ($value != ''){
                $tldata[$key]=$value;
            }
        }
        $post['translated'] = $tldata;

        //output-beginning
        $indent = '    ';
        $output = '<?php'.PHP_EOL.PHP_EOL;
        $output.= '$tl_data = array('.PHP_EOL;
        $output.= $indent."'".$post['iso'].'\' => array('.PHP_EOL;
        $i = 0;
        foreach($post['translated'] as $key => $val){
            $output.= $indent.$indent."'$key' => '$val',".PHP_EOL;
            $i++;
            if ($i == count($post['translated'])) $output = substr($output, 0, -2).PHP_EOL;
        }
        $output.= $indent.')'.PHP_EOL;
        $output.= ');'.PHP_EOL;
        //translated_data
        // $output.= var_export($post['translated'], true);
        //output end
        // $output.= PHP_EOL.');';
        $FS->save_content($targetPath, $output,true);
        //cleaning old tl_file if exist.
        $oldtl = $path.'tl_'.$basename.'.php';
        // if (is_file($oldtl)){
        //     unlink($oldtl);
        // }
        return 'ok';
    }
    public function New_TL($post){
        $output = H::group('new');
        $inp = H::input_text(['name'=>'inpnew', 'id'=>'inpnew', 'placeholder'=>'New TL word']);
        // Utils::error_log($this->dom_id);
        Utils::error_log($post);
        // $btn_new=H::button(['id'=>'btnEdit', 'class'=>'btnEdit', 'onclick'=>'alert("tsoinnnnn");'], 'New word');
        $btn_new = H::button(['id'=>'btnnew', 'class'=>'btnnew', 'onclick'=>'h.modules.uitranslate_a["'.$post['dom_id'].'"].add_word("'.$post['dom_id'].'");'], 'New word');
        $output->add_child([$inp, $btn_new]);
        return $output;
    }
}