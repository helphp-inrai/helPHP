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
namespace helPHP\modules\languages\public;

use \Config;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Media;
use helPHP\libs\Utils;

class Languages extends HelPHP_module {

    const module_name = 'languages';

    protected $scroll = false;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $toreturn=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        // global $DB;
        // if (isset($post['language_admin'])) {
        //     $this->dom_container = self::module_name.'_admin_flags_container';
        // }
        $master_output = H::group('media_display');
        $master_output->add_child( $this->ShowFlags($post) );
        
        if ($toreturn) {
            return $master_output;
        } else {
            $this->display->add_child( $master_output );
        }
    }
    
    public function ShowFlags($post) {
        global $DB, $LANG, $MEDIA;
        
        if (isset($post['scroll'])) {
            $this->scroll = $post['scroll'];
        }

        $output = H::group('flags');

        $q = 'SELECT DISTINCT a.id as id, a.id_data as id_data, d.iso, d.own_language as own FROM '.$this->bddt_allowed.' a,'.$this->bddt_data.' d';
        $q.=' WHERE a.id_data=d.id ORDER BY d.iso';
        $flags = $DB->query_list($q);
        
        foreach ($flags as $flag) {
            // link
            $link = H::A(['href'=>'?language='.$flag['iso'], 'class'=>$this->css.'link']);
            if ($flag['iso'] == $LANG->current_language) $link->add_class('languages_current');
            // img
            $media = $MEDIA->get_html($this->ifld_allowed_flag, $flag['id']);
            if ($media) {
                $media->add_class($this->css.'img');
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

        if ($this->scroll !== false) {
            $output->add_child(H::script('H_ui.scroll_toggle_class("'.$this->dom_target.'", '.$this->scroll.', "fixed");'));
        }

        return $output;
    }
}