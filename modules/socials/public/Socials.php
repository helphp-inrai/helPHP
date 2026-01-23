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
namespace helPHP\modules\socials\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Media;
use helPHP\libs\Utils;
use helPHP\modules\media\admin\Media as AdminMedia;

class Socials extends HelPHP_module {

    const module_name = 'socials';

    public function __construct($dom_container = null){
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $toreturn = false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if (!isset($post['module_name']) && isset($_SESSION['module_name'])) {
            $post['module_name'] = $_SESSION['module_name'];
        }

        if (!isset($post['module_param']) && isset($_SESSION['module_param'])) {
            $post['module_param'] = $_SESSION['module_param'];
        }

        if (isset($this->options['collapse']) && $this->options['collapse']) {
            $post['social_collapse'] = true;
        }

        if (!isset($post['module_name']) && !isset($post['module_param'])) {
            Utils::error_log('Missing module_name and module_param to load socials');
            return;
        }

        if (!isset($post['module_param'])) {
            $post['module_param'] = '';
        }
        
        $master_output = H::group('socials_display');
        switch ($post[$this->input_action_identifier]) {
            default:
                $master_output->add_child( $this->display($post) );
                
                $js = H::script('Socials.create_instance("'.$this->dom_id.'");', ['autoremove'=>1]);
                $master_output->add_child( $js );
            break;
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }

    }

    public function display($post){
        global $DB;

        $q = 'SELECT id FROM '.$DB->table('indexation_data').' where module_name=? and module_param=?';
        $result = $DB->prepared_query_line($q, 'ss', array($post['module_name'], $post['module_param']));
        if ($result) {
            $post['indexation_id'] = $result['id'];
            
            if (isset($post['no_container'])) {
                $this->dom_container = '';
            }

            return $this->get_socials($post);
            
        } else {
            if (!isset($post['core_insert'])) {
                Utils::error_log('Error : no indexation data');
                return;
            }
        }
    }

    //return social links for display
    public function get_socials(&$post) {
        global $LANG, $DB;
        
        $title = Language::load_short_translation_value('indexation_data-title', $post['indexation_id'], $LANG->current_id_data);
        $description = Language::load_long_translation_value('indexation_data-description', $post['indexation_id'], $LANG->current_id_data);
        if ($title == '' || $description == '') return false;

        $title = str_replace('"', '', $title);
        $description = str_replace('"', '', $description);
        
        $subject = $title;
        
        global $CONFIG;
        $url = $CONFIG::BASE_URL.'?'.$post['module_name'].'='.$post['module_param'].'-'.$LANG->current_language;
        $url = urlencode($url);

        $title = urlencode($title);

        $mail_body = $description;
        $mail_body.= " ".$url;

        $img = Media::get_media('indexation_data-image', $post['indexation_id']);

        $description = urlencode($description);

        $output = H::group('socials');

        // $output->add_child(H::SPAN(['class'=>$this->css.'share'], $this->get_tl('share')));

            //FACEBOOK
            $facebook = H::A([
                'target'=>'_blank',
                'class'=>$this->css.'link facebook',
                'title'=>$this->get_tl('facebook'),
                'href'=>'https://www.facebook.com/sharer.php?u='.$url.'&t=\''.$title.' '.$description.'\'',
                'rel'=>'nofollow',
                //~ 'onclick'=>'javascript:window.open(this.href, \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=500,width=700\');return false;',
            ]);
            $facebook->add_child(H::DIV(['class'=>$this->css.'icon facebook']));

        $output->add_child($facebook);

            //PINTEREST +
            $txt_pint = $title.' '.$description;
            if (strlen($txt_pint) > 500) {
                $txt_pint = substr($txt_pint, 0, 500);
            }
            $pinterest = H::A([
                'target'=>'_blank',
                'class'=>$this->css.'link pinterest',
                'title'=>$this->get_tl('pinterest'),
                'href'=>'https://www.pinterest.com/pin/create/button/?url='.$url.'&title=&description='.$txt_pint.( $img ? '&media='.$CONFIG::BASE_URL.$img['path'] : ''),
                'rel'=>'nofollow',
                'data-pin-do'=>'buttonBookmark',
                //~ 'onclick'=>'javascript:window.open(this.href, \'\', \'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=450,width=650\');return false;',
            ]);
            $pinterest->add_child(H::DIV(['class'=>$this->css.'icon pinterest']));

        $output->add_child($pinterest);
        
            //LINKEDIN
            $linkedin = H::A([
                'target'=>'_blank',
                'class'=>$this->css.'link linkedin',
                'title'=>$this->get_tl('linkedin'),
                'href'=>'https://www.linkedin.com/sharing/share-offsite/?url='.$url,
                'rel'=>'nofollow',
                //~ 'onclick'=>'javascript:window.open(this.href, \'\',\'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=450,width=650\');return false;',
            ]);
            $linkedin->add_child(H::DIV(['class'=>$this->css.'icon linkedin']));

        $output->add_child($linkedin);
        
            //MAIL
            $mail = H::A([
                'target'=>'_blank',
                'class'=>$this->css.'link mail',
                'title'=>$this->get_tl('email'),
                'href'=>'mailto:?subject='.$subject.'&body='.$mail_body,
                'rel'=>'nofollow'
            ]);
            $mail->add_child(H::DIV(['class'=>$this->css.'icon mail']));

        $output->add_child($mail);

        // if (isset($post['social_collapse']) && $post['social_collapse']) {

        //     $collapse = H::DIV(['class'=>$this->css.'collapse', 'id'=>self::module_name.'_collapse'.$this->dom_id]);

        //         global $MEDIA;
        //         $img = $MEDIA->get_html('indexation_data-id', $post['indexation_id']);
        //         if ($img) $collapse->add_child($img);

        //         $collapsable = H::DIV(['class'=>$this->css.'collapsable hidden', 'id'=>self::module_name.'_collapsable'.$this->dom_id], $output);
                

        //     $collapse->add_after( [$collapsable, $js] );

        //     return $collapse;

        // }

        return $output;
    }
}