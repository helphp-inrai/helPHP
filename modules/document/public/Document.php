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
namespace helPHP\modules\document\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Datetime;

class Document extends HelPHP_module {

    const module_name = 'document';

    private $name = '';
    private $modele = '';
    public $prepared_anim = [];

    function __construct($domContainer = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($domContainer);
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
    //action additionnelles si il y a des sous sections

    public function process_data(&$post){
        parent::process_data($post);

        $this->display->add_child( $this->Show($post) );
    }

    /**
     * Surclass to ease adding of css custom
     */
    public function get_display_tree($name, &$data){
        $output = parent::get_display_tree($name, $data);

        if ($output) {
            $output->add_class('document_'.$this->current_id);
            $output->add_class($this->name);
            if ($this->modele != '') $output->add_class('modele_'.$this->modele);
            $output->set_attribute('data-documentid', $this->current_id);
            return $output;
        }

        return false;
    }
    
    public function Show($post){
        global $CONFIG,$DB,$LANG;
        if(isset($post['id'])){
            $post['document_data-id'] = $post['id'];
        }
        //checking conditions before displaying
        if (isset($post['document_data-id']) && $post['document_data-id'] > 0) {
            $this->prepare_fields($post, 'document_data');
            $this->name = str_replace(' ', '_', $post[$this->ifld_data_name]);
            $this->modele = str_replace(' ', '_', $post[$this->ifld_data_modele]);

            //checking activation and publication_date
            $q = 'SELECT active, route, publication_date FROM '.$this->bddt_data.' WHERE id=?';
            $data = $DB->prepared_query_line($q, 'i', [$post['document_data-id']]);
            
            if ($data['active'] == 0 || Datetime::is_after($data['publication_date']) ) {
                return;
            }
            //getting the cached document path for next check : 
            $path_document = $CONFIG::HOME_FOLDER.'public/document/cache/'.str_replace('/', '¤', rtrim($data['route'],'/')).'-'.$LANG->current_language.'.php';
            
            //should we display the cached document or rebuild everything :
            $cached_mode=($CONFIG::DEVMODE == false && !isset($post['preview_action']) && is_file($path_document))?true:false;

        }else{
            return;
        }
        if ($cached_mode){

            include_once($path_document);
            // must check if we can include a script that update the social instead of double query
            if ($_SESSION['module_param']!=$post[$this->ifld_data_id]){
                $social_scripts = H::script('update_socials("'.self::module_name.'", "'.$post[$this->ifld_data_id].'");',['autoremove'=>true]);
                $document_cache['content'].=$social_scripts;
                $_SESSION['module_param']=$post[$this->ifld_data_id];
            }
            return $document_cache['content'];

        } else {

            $output = H::group('show_document');

            $q = 'SELECT dblocks.* , block.id as block_id FROM '.$DB->table('document_blocks').' as dblocks, '.$DB->table('block_data').' as block WHERE dblocks.id_document_data=? and dblocks.blockname = block.name order by sort_order';
            $list = $DB->prepared_query_list($q, 'i', [$post[$this->ifld_data_id]]);
            if ($list){
                $scriptloaded=[];
                $cssloaded=[];
                foreach ($list as $key=>$line) {
                    $path = $CONFIG::HELPHP_FOLDER.'/modules/block/'.$line['blockname'].'/public/';
                    $path.= ucfirst($line['blockname']).'.php';
                    if (is_file($path)) {
                        include_once($path);

                        $_POST = [];
                        $_POST['core_insert'] = true;
                        $_POST['block_'.$line['blockname'].'-id'] = intval($line['id_block']);

                        $moduleb = '\helPHP\modules\block\\'.$line['blockname'].'\public\\'.ucfirst($line['blockname']);
                        $moduleb = new $moduleb();
                        $module_content = $this->parse_hcode($moduleb->process_data($_POST, true));

                        if(!isset($scriptloaded[$line['blockname']])) {
                            $js = $DB->prepared_query_value('SELECT jspublic FROM '.$DB->table('block_data').' WHERE name=?', 's', [$line['blockname']]);
                            $scriptloaded[$line['blockname']]=$js;
                            if($scriptloaded[$line['blockname']] != ''){
                                $js = H::script($scriptloaded[$line['blockname']], ['autoremove'=>true]);
                                $module_content = $js.$module_content;
                            }
                        }

                        if(!isset($cssloaded[$line['blockname']])) {
                            $css = \helPHP\modules\csseditor\admin\Csseditor::get_css_source('block', $line['block_id']);
                            $cssloaded[$line['blockname']] = $css;
                            if($cssloaded[$line['blockname']] != ''){
                                $css = H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css'), $css);
                                $module_content = $css.$module_content;
                            }
                        }
                        $output->add_child($module_content);
                    }
                }
            }

            //document_properties
            $data_display = H::div(['class'=>$this->css.'fiche']);
            
                $summary_label = H::SPAN(['id'=>'document_data-summary_label', 'class'=>'label'], $this->get_tl('summary'));
                $summary_content = $LANG::load_long_translation_value($this->ifld_data_summary, $post[$this->ifld_data_id]) ?? '';
                $summary = H::SPAN(['class'=>'disp_longmulti'], stripslashes($summary_content));

                $title_label = H::SPAN(['id'=>'document_data-name_label', 'class'=>'label'], $this->get_tl('title'));
                $title_content = $LANG::load_long_translation_value($this->ifld_data_title, $post[$this->ifld_data_id]) ?? '';
                $title = H::SPAN(['class'=>'disp_shortmulti'], stripslashes($title_content));

            $data_display->add_child( [$title_label,$title,$summary_label,$summary] );

                $creation_date_label = H::SPAN(['id'=>'document_data-creation_date_label', 'class'=>'label'], $this->get_tl('creation_date'));
                $creation_date = H::SPAN(['class'=>'disp_date'], $post[$this->ifld_data_creation_date]);

            $data_display->add_child( [$creation_date_label,$creation_date] );

                $publication_date_label = H::SPAN(['id'=>'document_data-publication_date_label', 'class'=>'label'], $this->get_tl('publication_date'));
                $publication_date = H::SPAN(['class'=>'disp_date'], $post[$this->ifld_data_publication_date]);

            $data_display->add_child( [$publication_date_label,$publication_date] );

            $output->add_child($data_display);
            
            //animation :
            $q = 'SELECT DISTINCT * FROM '.$DB->table('block_animation').' where id_document='.$post[$this->ifld_data_id];
            $animations = $DB->query_list($q);
            if (count($animations) > 0){
                $animscript = '';
                foreach($animations as $key => $line){
                    $id_anim = array_search($line['id_animation'], array_column($this->prepared_anim, "id"));
                    $animscript.= 'new H_anim({elements: "#block_'.$line['block_id'].'", '.$this->prepared_anim[$id_anim]['opts'].'});';
                }
                $anim_script = H::script($animscript, ['autoremove'=>false]);
                // $anim_script=H::script($animscript,['autoremove'=>true]);
                $output->add_child($anim_script);
            }

            // must check if we can include a script that update the social instead of double query
            if (isset($_SESSION['module_param']) && $_SESSION['module_param'] != $post[$this->ifld_data_id]){
                $social_scripts = H::script('update_socials("'.self::module_name.'", "'.$post[$this->ifld_data_id].'");',['autoremove'=>true]);
                $output->add_child($social_scripts);
                $_SESSION['module_param']=$post[$this->ifld_data_id];
            }
            $css= \helPHP\modules\csseditor\admin\Csseditor::get_css_source('document', $post[$this->ifld_data_id]);
            if($css!=''){
                $css = H::STYLE(array('rel'=>'stylesheet' , 'type'=>'text/css', 'id'=>'css_document¤'.$post[$this->ifld_data_id]), $css);
                $output = $css.$output;
            }
           
            
            return $output;
        }
    }
}