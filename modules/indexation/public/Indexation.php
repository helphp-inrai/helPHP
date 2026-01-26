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
namespace helPHP\modules\indexation\public;

use helPHP\libs\HelPHP_module;
use \Config;
use helPHP\libs\Language;
use helPHP\libs\Utils;

class Indexation extends HelPHP_module
{

    //----------------------------------------------------------------------------------------------
    // indispensable variables for all modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'indexation';

    protected $module_name = self::module_name;
    protected $SCRIPT_PATH = __DIR__;

    protected $admin = false;

    protected $input_action_identifier = 'indexationAction';

    //----------------------------------------------------------------------------------------------
    // variables specific to this module
    //-------
    
    public $HEADERS=[];
    
    protected $dom_container = self::module_name.'_public_container';

    public function __construct($dom_container = null)
    {
        parent::__construct($dom_container);
    }
    public function process_data(&$post)
    {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        global $DB,$MEDIA;
        
        switch ($post[$this->input_action_identifier]) {
            default:
                if (!isset($post[$this->ifld_data_module_name])) {
                    $post[$this->ifld_data_module_name]=$_SESSION['module_name'];
                }
                if (!isset($post[$this->ifld_data_module_param])) {
                    $post[$this->ifld_data_module_param]=$_SESSION['module_param'];
                }
                if (!isset($post[$this->ifld_data_module_name]) || !isset($post[$this->ifld_data_module_param])) {            
                    echo 'Error : No module_name or module_param provided';
                    exit();
                } else {

                    $q = 'select id from '.$this->bddt_data.' where module_name= ? and module_param= ?';
                    $post[$this->ifld_data_id] = $DB->prepared_query_value($q, 'ss', array($post[$this->ifld_data_module_name],$post[$this->ifld_data_module_param]));
                    if ($post[$this->ifld_data_id]!=false && $post[$this->ifld_data_id] != '') {
                        // $post[$this->ifld_data_id]=$post[$this->ifld_data_module_param];
                        $image = $MEDIA->get_media($this->ifld_data_image, $post[$this->ifld_data_id]);
                        $post[$this->ifld_data_image]=($image!=false && $image['path']!=false)?$image['path']:'images/logo.svg';
                        $this->GetHeaders($post);
                    } else {
                        $this->HEADERS['title']=Config::SITE_NAME;
                        $this->HEADERS['keywords']=Config::SITE_NAME;
                        $this->HEADERS['description']=Config::SITE_NAME;
                        $this->HEADERS['canonical']=Config::BASE_URL;
                        $this->HEADERS['metas']=[];
                    }
                }
            break;
        }
    }
    //----------------------------------------------------------------------------------------------------
    //SINGLE SECTION
    //----------------------------------------------------------------------------------------------------
    
    //return the headers in the form of arrays for the public core.
    public function GetHeaders(&$post)
    {
        global $LANG,$DB;
        $this->HEADERS['title']=Language::load_short_translation_value($this->ifld_data_title, $post[$this->ifld_data_id], $LANG->current_id_data);
        $this->HEADERS['keywords']=Language::load_short_translation_value($this->ifld_data_keywords, $post[$this->ifld_data_id], $LANG->current_id_data);
        $this->HEADERS['description']=Language::load_long_translation_value($this->ifld_data_description, $post[$this->ifld_data_id], $LANG->current_id_data);
        $metas=[];
        $image=Config::BASE_URL.'files/'.$post[$this->ifld_data_image];
        if (isset($post['indexation-data-mode']) && $post['indexation-data-mode']=='start') {
            $url= Config::BASE_URL;
        } else {
            $paramT=(isset($post[$this->ifld_data_module_Full_param]))?$post[$this->ifld_data_module_Full_param]:$post[$this->ifld_data_module_param];
            $url=Config::BASE_URL.'?'.$post[$this->ifld_data_module_name].'='.$paramT;
        }
        $this->HEADERS['canonical']=$url;
        array_push($metas, array('name'=>'author', 'content'=>'helPHP'));
        array_push($metas, array('property'=>'og:locale', 'content'=>$LANG->current_language));
        array_push($metas, array('property'=>'og:type', 'content'=>'article'));
        array_push($metas, array('property'=>'og:title', 'content'=>$this->HEADERS['title']));
        array_push($metas, array('property'=>'name', 'content'=>$this->HEADERS['title']));
        array_push($metas, array('property'=>'og:description', 'content'=>$this->HEADERS['description']));
        array_push($metas, array('property'=>'og:url', 'content'=>$url));
        array_push($metas, array('property'=>'og:site_name', 'content'=>Config::SITE_NAME));
        array_push($metas, array('property'=>'article:author', 'content'=>Config::BASE_URL));
        array_push($metas, array('property'=>'og:image', 'content'=>$image));
        $this->HEADERS['metas']=$metas;
    }
}
?>