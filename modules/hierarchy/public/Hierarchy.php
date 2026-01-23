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

namespace helPHP\modules\hierarchy\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Utils;
use helPHP\libs\Media;
use helPHP\libs\User;

class Hierarchy extends HelPHP_module {
    const module_name = 'hierarchy';

    private $scroll = false;
    private $first_level = 0;
    private $link_count = 0;
    private $media = false;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);
        
        global $CONFIG;
        if (isset($CONFIG::MODULES_LIST['media'])) {
            $this->media = true;
        }
    }

    public function process_data(&$post) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        // if (isset($post['id'])) {
        //     $this->id_root = $post['id'];
        // }
        
        if (isset($post['scroll'])) {
            $this->scroll = $post['scroll'];
        }

        switch ($this->input_action_identifier) {
            default:
                $this->display->add_child($this->display($post));
            break;
        }
    }

    //----------------------------------------------------------------------------------------------
    
    public function display(&$post){
        global $DB, $CONFIG;

        if (!isset($post['id']) || !$post['id']) {
            Utils::error_log('missing id to load hierarchy');
            return;
        }

        // disable the display of admin hierarchy in public side.
        if ($post['id'] == 1) return;
        
        $q = 'SELECT * FROM '.$this->bddt_structure.' WHERE id=?';
        $root = $DB->prepared_query_line($q, 'i', [$post['id']]);
        
        $this->css = str_replace(' ', '_', $root['name']);
        $this->dom_container.= ' '.$this->css.'_parent';
        
        $hierarchy = $this->hierarchy_recurse_tree($post['id']);
        
        $this->first_level = 0;
        
        $output = H::group('hierarchy');
        
        $output->add_child($this->display_list($hierarchy));
        
        if ($root['create_result']) {
            $subcontainer = H::DIV(['id'=>$this->css.'_result', 'class'=>$this->css.'_result']);
            // in the case of a ? load
            // extra is added by the core if hierarchy need to load a module
            // for exemple in the 'my account' menu
            if (isset($_POST['module_extra'])){
                Utils::error_log($_POST['module_extra']);
                $extra = explode('-',$_POST['module_extra']);
                $_POST = [];
                // attention au lien custom
                if (strpos($extra[0], '|') !== false){
                    $extraData = explode('|',$extra[0]);
                    $extraParam = parse_str($extraData[1], $_POST);
                    $extraName = str_replace('/index.php', '', $extraData[0]);
                    $_POST[$extraName] = '';
                } else {
                    $extraData = explode('=',$extra[0]);
                    $extraName = $extraData[0];
                    $extraParam = $extraData[1];
                    $_POST[$extraName] = $extraParam;
                }
                $_POST['core_insert'] = 1;
                
                $extraLang = $extra[1];
                $extraContainer = $extra[2];
                
                if ($extraContainer == $subcontainer->id){
                    
                    foreach ($CONFIG::MODULES_LIST as $moduleName => $module_data) {
                        if ($moduleName == $extraName) {
                            global $module_html_content;
                            $_POST[$module_data['public_param']]=$_POST[$moduleName];
                            $module_html_content[$extraName] = '';
                            Utils::error_log($_POST);
                            include('public/'.$extraName.'/index.php');
                            $subcontainer->add_child($module_html_content[$extraName]);
                        }
                    }
                } else {
                    Utils::error_log('Problem with the extra module in hierarchy, the subcontainer doesn\'t correspond');
                    Utils::error_log('extra :');
                    Utils::error_log($_POST['module_extra']);
                    Utils::error_log('subcontainer :');
                    Utils::error_log($subcontainer->id);
                }
            }
            $output->add_child($subcontainer);
        }
        
        return $output;
    }

    public function hierarchy_recurse_tree($id, $level = 0){
        global $DB, $LANG, $USER;

        $q = 'SELECT * FROM '.$this->bddt_structure.' WHERE id_parent=? AND active=1 ORDER BY `sort_order`';
        $liste = $DB->prepared_query_list($q, 'i', [$id]);

        if (is_array($liste)) {
            $result = [];
            
            $groups = $USER->allowed_groups();
            $str_groups = implode(',', $groups);

            $table_item = $this->bddt_item;
            $table_asso = $this->bddt_modular_association;
            $table_group = $this->build_module_table_name('group', 'content');
            $field_identifier = $this->build_field_name('item', 'id');

            $q_groups = '';
            if ($str_groups !='') {
                $q_groups= ' AND ('.$table_group.'.id_group_data IS NULL OR '.$table_group.'.id_group_data IN ('.$str_groups.') )';
            } else {
                $q_groups= ' AND '.$table_group.'.id_group_data IS NULL';
            }

            foreach ($liste as $index => $line) {
                $q = 'SELECT DISTINCT '.$table_item.'.module, '.$table_item.'.params, '.$table_item.'.target, '.$table_item.'.id';
                $q.= ' FROM '.$table_item.' ';
                $q.= ' LEFT JOIN '.$table_asso.' ON '.$table_item.'.id='.$table_asso.'.id_item ';
                $q.= ' LEFT JOIN '.$table_group.' ON ('.$table_group.'.id_item = '.$table_item.'.id AND '.$table_group.'.field_identifier="'.$field_identifier.'")';
                $q.= ' WHERE '.$table_asso.'.id_structure=? '.$q_groups;
                $line['item'] = $DB->prepared_query_line($q, 'i', [$line['id']]);
                if (!$line['item']) continue;
                
                if ($this->media && $line['item'] && Media::has_media($this->ifld_item_image, $line['item']['id'])) {
                    $line['item']['image'] = true;
                }
                
                if (is_array($line['item'])) {
                    $line['childs'] = $this->hierarchy_recurse_tree($line['id'], $level+1);

                    $params = json_decode($line['item']['params'], true);

                    $line['name'] = $this->get_item_name($line['item']['id'], $line['item']['module'], $params);
                    if (isset($params['icon']) && $params['icon'] != '') {
                        $line['item']['icon'] = $params['icon'];
                    }

                    array_push($result, $line);
                }
            }
        }

        return $result;
    }
    public function get_item_name($id, $type, $params) {
        $name = '';
        switch ($type){
            case 'custom':
                global $LANG;
                $name = $LANG->load_short_translation_value($this->ifld_item_name, $id);
            break;
            case 'module':
                global $LANG;
                $name = $LANG->load_short_translation_value($this->ifld_item_name, $id);
                if (!$name) {
                    $moduleName = $params['module'];
                    $name = $this->get_translated_text_from_other_module($moduleName, true, 'module_name');
                    if (str_contains($name, '{')) $name = $this->get_translated_text_from_other_module($moduleName, false, 'module_name');
                }
            break;
            default:
                $name = Language::get_name($type.'_data', $params['params']);
            break;
        }
        return $name;
    }
    
    public function extend_data_tree($data_tree, $to_add = false, $level = 0){
        global $DB;
        $newData = [];
        
        foreach ($data_tree as $key=>$line) {
            $temp = [];
            $temp['name'] = $line['name'];
            $temp['params'] = 'id='.$line['id'].'|';
            $temp['module'] = $to_add['module'];
            $temp['target'] = $to_add['target'];
            $temp['dataTree'] = true;
            if (isset($line['children'])) {
                $temp['children'] = $this->extend_data_tree($line['children'], $to_add, $level+1);
            }

            $newData[$key] = $temp;
        }
        
        return $newData;
    }
    
    public function display_list($list){
        global $USER, $CONFIG;

        $this->first_level++;

        if ($this->first_level == 1) {
            $ul = H::UL(array('class'=>$this->css, 'id'=>$this->css));
            if ($this->scroll !== false) {
                $ul->add_after(H::script('H_ui.scroll_toggle_class("'.$this->css.'", '.$this->scroll.', "fixed");'));
            }
        } else {
            $ul = H::UL();
        }
        
        if (strstr($this->css, 'compte')) {
            if ($USER->connection_state == User::state_not_logged) {
                $this->display->add_child(H::script('H_history.change_hash(event, "connection=true");'));
            }
        }

        foreach ($list as $index=>$line) {
            if (isset($line['dataTree']) && $line['dataTree']) {
                // element from data tree (parentage)
                
                $hash = $this->make_hash($line);
                $class_a = Utils::filter_string($line['name']);
                $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a,'onclick'=>'H_history.change_hash(event, "'.$hash.'");'), $line['name']);
                $li = H::LI(null, $a);
                if (isset($line['children'])) {
                    $li->add_child($this->display_data_tree($line['children']));
                }
                
                $item = $line;
                
            } else {
                // normal element
                
                $item = $line['item'];
                //~ $this->link_count++;

                $class_a = Utils::filter_string($line['name']);
                $name = H::DIV(['class'=>$this->css.'_text_link'], $line['name']);

                if ($line['name'] == '') {
                    continue;
                }

                $li = H::LI();
                $hash = $this->make_hash($item);
                if (isset($item['image']) && $item['image'] != '') {
                    $img = H::DIV(['class'=>$this->css.'_block_img'], H::IMG(['class'=>$this->css.'_image', 'src'=>$CONFIG::BASE_URL.'public/hierarchy/images/'.$item['image']]));
                }

                if (isset($item['icon'])) {
                    $icon = H::icon($item['icon'], ['class'=>$this->css.'_icon']);
                }

                if (strstr($this->css, 'mon_compte') && strstr($hash, 'disconnect')) {
                    $li->add_child(H::A(array('href'=>'#', 'onclick'=>'event.preventDefault(); Connection.Disconnect();'), $line['name']));
                } else {
                    $params = json_decode($item['params'],true);
                    $open_type = $params['open_type'];
                    $childs = [];
                    if (isset($img)) {
                        array_push($childs, $img);
                    }
                    if (isset($icon)) {
                        array_push($childs, $icon);
                    }
                    array_push($childs, $name);
                    if ($open_type == 1) {
                        $hash = $params['url'];
                        $a = H::A(array('href'=>$hash, 'class'=>$class_a, 'target'=>'_blank'), $childs);
                    } elseif ($open_type == 2) {
                        $hash = $params['url'];
                        $a = H::A(array('class'=>$class_a, 'onclick'=>'H_ui.popup("'.$hash.'", "'.$line['name'].'");'), $childs);
                    } elseif ($hash != '') {
                        $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");'), $childs);
                    } else {
                        $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a, 'onclick'=>'return false;'), $childs);
                    }
                    
                    $li->add_child($a);
                }

                if (count($line['childs']) > 0) {
                    $li->add_child($this->display_list($line['childs']));
                }
                if (isset($line['data_tree'])) {
                    $li->add_child($this->display_data_tree($line['data_tree']));
                }
            }
            
            if (explode(',', $item['module'])[0] == 'categorie') {
                if (isset($line['entete']) && $line['entete'] == 1) {
                    $li->set_attribute('class', $this->css.'_categorie categorie_en_avant');
                } else {
                    $li->set_attribute('class', $this->css.'_categorie');
                }
            }
            $ul->add_child($li);
            if (isset($img)) {
                unset($img);
            }
            if (isset($icon)) {
                unset($icon);
            }
        }
        
        return $ul;
    }
    
    public function display_data_tree($dataTree){
        $ul = H::UL();
        
        foreach ($dataTree as $key=>$line) {
            $hash = $this->make_hash($line);
            $class_a = Utils::filter_string($line['name']);
            $a = H::A(array('href'=>'?'.$hash, 'class'=>$class_a, 'onclick'=>'H_history.change_hash(event, "'.$hash.'");'), $line['name']);
            $li = H::LI(null, $a);
            if (isset($line['children'])) {
                $li->add_child($this->display_data_tree($line['children']));
            }
            $ul->add_child($li);
        }
        
        return $ul;
    }

    public function make_hash($data){
        global $LANG;

        $res = '';

        $params = json_decode($data['params'], true);
	
        if ($data['module'] == 'custom'){

            $res.= $params['url'];

        } else if ($data['module'] == 'module'){

            $moduleName = $params['module'];
            $res.= $moduleName;
            if ($params['params'] != ''){
                $res.= '|'.str_replace('&', '|', $params['params']);
            }

        } else {

            $res.= $data['module'].'='.$params['params'];

        }

        if ($data['target'] != 'lemain' && $data['target'] != '') {
            // language
            $res .= '-'.$LANG->current_language;
            // target
            $res .= '-'.$data['target'];
            
            /* it's not the same target, need to add in front of the link
             * the previous one to make the history system working.
             * Exemple: 
             * The 'My account' hierarchy need to display it's item along whith their content
             * all hash will be something like : #hierarchy=ID¤item-lang-subcontainer
             */
            $res = self::module_name.'='.$this->id_root.'¤'.$res;
        }
	
        return $res;
    }
}
