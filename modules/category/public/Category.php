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

namespace helPHP\modules\category\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\libs\Language;
use helPHP\libs\User;

class Category extends HelPHP_module {

    //----------------------------------------------------------------------------------------------
    // indispensable variables for all modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'category';

        

    
    //----------------------------------------------------------------------------------------------
    // variables specific to this module
    //----------------------------------------------------------------------------------------------

    private $ACTION_LOAD_CATEGORY = self::module_name.'_edit';

        
    
    public function __construct($domContainer = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($domContainer);
    }

    public function process_data(&$post) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        $this->display->add_child($this->process_data_Showcategory($post));
    }

    //----------------------------------------------------------------------------------------------

    public function process_data_Showcategory(&$post) {
        global $LANG;

        if (isset($post['id'])) {
            $post[$this->ifld_data_id] = $post['id'];

            $this->check_posted_data($post, 'data', ['id', 'entete']);

            $this->apply_bdd_data($post, 'data', ['id', 'entete']);

            $post[$this->ifld_data_bandeau] = $LANG->load_long_translation_value($this->ifld_data_bandeau, $post['id']);
            $post[$this->ifld_data_name] = $LANG->load_short_translation_value($this->ifld_data_name, $post['id']);

            if ($this->has_data_tree('data')) {
                $parents = $this->TreeRecurseParent($post[$this->ifld_data_id]);
                if ($parents) {
                    $post['ancetre'] = $parents;
                }
            }

            $this->css = $post['id'].'_category_public';

            return $this->Showcategory($post);
        }
    }

    public function TreeRecurseParent($id, $res = [], $key = 0) {
        global $DB, $LANG;

        $q = 'SELECT id_parent FROM '.$this->bddt_data_parentage.' WHERE id_enfant='.$id;
        $id = $DB->query_value($q);
        if ($id > 0) {
            $q = 'SELECT count(*) FROM '.$this->bddt_data.' WHERE id='.$id;
            $exist = $DB->query_value($q);
            if ($exist) {
                $res[$key] = ['name'=>$LANG->load_short_translation_value($this->ifld_data_name, $id), 'id'=>$id];
            }
        }

        if (isset($res[$key])) {
            $key++;
            return $this->TreeRecurseParent($id, $res, $key);
        } else {
            return array_reverse($res);
        }
    }

    //----------------------------------------------------------------------------------------------

    public function Showcategory($post) {
        $this->dom_container = $this->css;

        $output = H::group('category');

        $title = H::SPAN( array('class'=>$this->css.'_title'));
        if (isset($post['ancetre'])) {
            $separator = H::SPAN( ['class'=>$this->css.'separator'], H::_RIGHT_ARROW_HEAD);
            foreach ($post['ancetre'] as $key => $line) {
                
                $mousedown = 'H_history.change_hash(event, "webstore=category:'.$line['id'].'");';
                $ancetre = H::A( ['href'=>'?webstore=category:'.$line['id'], 'onclick'=>$mousedown, 'class'=>$this->css.'ancetre ancetre_'.$key], $line['name']);
                $title->add_child([$ancetre, $separator]);
            }
        }
        $current = H::SPAN( ['class'=>$this->css.'current'], $post[$this->ifld_data_name]);
        $title->add_child($current);
        $output->add_child($title);
        if (trim(strip_tags($post[$this->ifld_data_bandeau], '<video><img>'))) {
            $div_bandeau = H::DIV( array('class'=>$this->css.'_bandeau'), $post[$this->ifld_data_bandeau]);
            $output->add_child($div_bandeau);
        }

        if (isset($post['minifiche'])) {
            $contenu = H::DIV( ['class'=>$this->css.'_produits'], $post['minifiche']);
            $output->add_child($contenu);
        }

        return $output;
    }
}
