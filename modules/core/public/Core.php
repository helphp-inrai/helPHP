<?php
/**
 * COPYRIGHT M666 moi@myke666.fr 40600 BISCARROSSE FRANCE 2009-2022
 * ALL RIGHTS RESERVED
 * TOUS DROITS RESERVES
 * THIS CODE CAN'T BE DUPLICATED OR MODIFY WITHOUT M666 moi@myke666.fr AGREEMENT
 * CE CODE NE PEUT PAS ETRE DUPLIQUE OU MODIFIE SANS L'ACCORD D'M666 moi@myke666.fr
 */
namespace helPHP\modules\core\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Htmlgroup;
use helPHP\libs\Language;
use helPHP\libs\Utils;
use helPHP\modules\indexation\public\Indexation;

// use helPHP\libs\;

class Core extends HelPHP_module {

    //----------------------------------------------------------------------------------------------
    // indispensable variables for all modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'core';

    protected $root_module = true;
    //----------------------------------------------------------------------------------------------
    // variables specific to this module
    //-------
    protected $MODULE_LIST;
    private $actual_indexable=false;
    private $ACTION_DISPLAY_MONO = self::module_name.'_mono';
    
    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);

        global $CONFIG;
        $this->MODULE_LIST = $CONFIG::MODULES_LIST;
    }
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        $master_output = H::group('core_display');
        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_DISPLAY_MONO:
                // single module display
                $this->dom_container = '';
                $master_output->add_child($this->ShowOneModule($post));
            break;
            default:
                $this->dom_container = '';
                $master_output->add_child($this->display_disposition($post));
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    //----------------------------------------------------------------------------------------------------
    // DISPLAY THE INDICATED LAYOUT OR SEARCH THE DEFAULT LAYOUT
    //----------------------------------------------------------------------------------------------------
    public function display_disposition(&$post) {
        global $DB,$LANG,$CONFIG;

        $post['core_insert'] = true;
        
        //it is possible to request a specific layout other than the default one
        //this kind of thing is useful if you have to make some kind of non-indexable “subsite. ”
        $indexable = false;
        
        //base url mode for indexing
        $indexation_mode = 'start';

        if (isset($_GET['cordovaApp'])) $_SESSION[get_class()]['is_cordova'] = $_GET['cordovaApp'];
        $is_cordova = isset($_SESSION['is_cordova']);
        if ($is_cordova) $post['disposition'] = $is_cordova;
        if (isset($_GET['disposition'])) $post['disposition'] = $_GET['disposition'];

        //filtering from request or route

        //check if a hroute is requested 
        if (isset($_GET['hroute']) && $_GET['hroute'] != '') {
            $q = 'SELECT active,id FROM '.$DB->table('document_data').' WHERE route=?';
            $document = $DB->prepared_query_line($q, 's', [$_GET['hroute']]);
            if((isset($document['id']) && $document['active'] == 1) && is_file($CONFIG::HOME_FOLDER.'public/document/cache/'.str_replace('/','¤',rtrim($_GET['hroute'],'/')).'-'.$LANG->current_language.'.php')) {
                include_once($CONFIG::HOME_FOLDER.'public/document/cache/'.str_replace('/','¤',rtrim($_GET['hroute'],'/')).'-'.$LANG->current_language.'.php');
                $indexable = array();
                $indexable['module_indexable'] = 'cached_document';
                $indexable['module_param'] = $document_cache['id'];
                $indexable['module_content'] = H::div(['id'=>'document_container'.$this->current_id,'class'=>'module_container document_container document_'.$document['id'] ,'data-current_id'=>$document['id']],$document_cache['content']);
            }else{
                $this->four_o_four('Sorry... Bad requested route...');
            }
        }
        //a module is requested for display in main
        if (isset($this->MODULE_LIST[array_key_first($_GET)])) {        
            // we load the module that is indicated by the url (after the ?)
            $module_name=array_key_first($_GET);
            $indexable = array();
            $indexable['module_indexable'] = $module_name;
            // check if it's a multiple call hash
            $tparameters = explode('¤', $_GET[$module_name]);
            if (isset($tparameters[1])){
                // there is a second call to do after
                $params = explode('-', $tparameters[0]);
                $indexable['module_param'] = $tparameters[0];
                $indexable['module_Full_param'] = $_GET[$module_name];
                if (isset($tparameters[1])) $LANG->set_language_iso($tparameters[1]);
                else $LANG->set_language_iso($CONFIG::DEFAULT_LANGUAGE);
            } else {
                // classic
                $tparameters = explode('-', $_GET[$module_name]);
                $indexable['module_param'] = $tparameters[0];
                $indexable['module_Full_param'] = $_GET[$module_name];
                if (isset($tparameters[1])){
                        $LANG->set_language_iso($tparameters[1]);
                }else {
                    if (!isset($_SESSION[Language::session_language_identifier]) || $_SESSION[Language::session_language_identifier]==''){
                        $LANG->set_language_iso($CONFIG::DEFAULT_LANGUAGE);
                    }else{
                        $LANG->set_language_iso($_SESSION[Language::session_language_identifier]);
                    }
                }    
            }

            //we connect indexable at the moment for modules like socials or indexing
            $this->actual_indexable = $indexable;
            $_SESSION['module_name'] = $indexable['module_indexable'];
            $_SESSION['module_param'] = $indexable['module_param'];
            //url mode module for indexing
            $indexation_mode = 'module';
        }
                    
        //completing disposition after filtering        
            
        if (!isset($post['disposition'])) { //no layout was requested
            //search for default public layout
            $disposition_public = $DB->query_value('SELECT distinct id FROM '. $this->bddt_disposition.' WHERE name="public"');
            if ($disposition_public == null) {
                //not cool, we are supposed to have a public
                $this->four_o_four('Sorry... No public disposition found...');
            }
        } else {
            //a layout is requested
            if ($post['disposition'] != 0 && $post['disposition'] != '') {
                $disposition_public = $post['disposition'];
                if (isset($_GET['indexable'])) $indexable = $_GET['indexable'];
                if (isset($_GET['submodule'])) $post['submodule'] = $_GET['submodule'];
            }else{
                $this->four_o_four('Sorry... Bad requested disposition....');
            }
        }

        //retrieve the modules from the layout:
        $mods = $this->displayDisposition($disposition_public, $post, $indexable);
        // we treat indexation questions according to $this->actual_indexable
        if ($disposition_public > 0 && isset($this->actual_indexable['module_indexable']) && !isset($document_cache['title'])) {
            
            $_POST['indexation_data-module_name'] = $this->actual_indexable['module_indexable'];
            $_POST['indexation_data-module_param'] = $this->actual_indexable['module_param'];
            if (isset($indexable['module_Full_param'])) {
                $_POST['indexation_data-module_Full_param'] = $indexable['module_Full_param'];
            }
            $_POST['indexation-data-mode'] = $indexation_mode;
            
            $module_index = new Indexation();
            $module_index->process_data($_POST);
        }
        if (isset($document_cache['title'])){
            $module_index = (object)[];
            $module_index->HEADERS = $document_cache;
        }
        

        //we add the indexation module to the display
        //header du doc
        
        if (!isset($module_index->HEADERS['title'])) {
            if ($disposition_public == 0) {
                global $LANG;
                $lalang = $LANG->current_language;
                $output = H::HTML(array('lang' => $lalang));
                $head = H::HEAD();
                $head->add_child( H::TITLE(null, 'preview') );
                $head->add_child( H::META(array('charset'=>'utf-8')) );
                $head->add_child( H::META(array('http-equiv'=>'Content-Type' , 'content'=>'text/html; charset=utf-8')) );
                $output->add_child($head);

                //add the basic body
                $body = H::BODY(array('id'=>'dabody_preview', 'class'=>'mon_body'));
                $output->add_child($body, 'body');
            } else {
                $output = H::new_document('HelPHP', '', '', false, true);
            }
        } else {
            $metas = [];
            if (isset($this->options['metas'])) $metas = array_merge($metas, $this->options['metas']);
            $metas = array_merge($metas, $module_index->HEADERS['metas']);
            if (isset($_GET['theme_id'])) $output = H::new_document($module_index->HEADERS['title'], $module_index->HEADERS['keywords'], $module_index->HEADERS['description'], $metas, true, $module_index->HEADERS['canonical'], $_GET['theme_id']);
            else $output = H::new_document($module_index->HEADERS['title'], $module_index->HEADERS['keywords'], $module_index->HEADERS['description'], $metas, true, $module_index->HEADERS['canonical'],false);
        }
        
        $body = $output->find_child('body');

        
        $body->add_child($mods);

        if ($is_cordova) $body->add_child(H::script('', ['src'=>$CONFIG::BASE_URL.'public/app.js']));

        $js = ($CONFIG::INCLUDE_JS_ANIMATE==true) ? 'h.libs.animation.detect_anime();':'';

        global $H_context;
        if ($H_context != '') $js.= 'H_constants.context = "'.$H_context.'";';

        $body->add_child( H::script($js, ['autoremove'=>true]) );

        // PROCESSING HCODE
        $output = $this->parse_hcode($output);
        
        return $output;
    }
    //Display the layout
    public function displayDisposition($id, &$post, $indexable=false)
    {
        global $DB,$module_html_content,$CONFIG,$LANG;
        $post['core_insert']=true;
        $original_post = $post;
        $displayModules=H::group('modules');
        //this good old friend 'le main'
        $diplayIndexable=H::DIV(array('id'=>'lemain' , 'class'=>'le_main'));
        //no indexable requested? we pick the default one of the layout
        if (!$indexable) {
            $indexable=$DB->query_line('SELECT * FROM '.$this->bddt_disposition.' WHERE id='.$id);
        }
        if ($indexable!=null && $indexable['module_indexable'] != '') {
            if ($indexable['module_indexable']== 'document'){
                $q = 'SELECT active,id FROM '.$DB->table('document_data').' WHERE id=?';
                $document = $DB->prepared_query_line($q, 'i', [$indexable['module_param']]);
                if(isset($_GET['hroute']) && (isset($document['id']) && $document['active'] == 1) && is_file($CONFIG::HOME_FOLDER.'public/document/cache/'.str_replace('/','¤',rtrim($_GET['hroute'],'/')).'-'.$LANG->current_language.'.php')) {
                    include_once($CONFIG::HOME_FOLDER.'public/document/cache/'.str_replace('/','¤',rtrim($_GET['hroute'],'/')).'-'.$LANG->current_language.'.php');
                    $indexable = array();
                    $indexable['module_indexable'] = 'cached_document';
                    $indexable['module_param'] = $document_cache['id'];
                    $indexable['module_content'] = H::div(['id'=>'document_container'.$this->current_id,'class'=>'module_container document_container document_'.$document['id'] ,'data-current_id'=>$document['id']],$document_cache['content']);
                }
            }
            if($indexable['module_indexable']!= 'cached_document'){
            //check to see if the indexable module is in the conf
                $module_name = $indexable['module_indexable'];
                $module_data = $this->MODULE_LIST[$module_name];
                //it is in the config it is displayed.
                $_POST = $original_post;
                if ($indexable['module_param']!='') {
                    //we passed a query in get mode, we explode this
                    if (str_contains($indexable['module_param'],'|')){
                        $indexable['module_param'] = str_replace('|','&',$indexable['module_param']);
                    }
                    if (strpos($indexable['module_param'], '=') !== false) {
                        parse_str($indexable['module_param'], $_POST);
                    } else {
                        //we did not pass a query so we just call the param set in conf
                        $_POST[$module_data['public_param']]=$indexable['module_param'];
                    }
                } else {
                    $indexable['module_param']='';
                }
                //we memorize the indexable for displaying the header.

                //we record the indexable of the moment for modules like socials or indexing
                $this->actual_indexable=$indexable;
                $_SESSION['module_name']=$module_name;
                $_SESSION['module_param']=$indexable['module_param'];
                $_POST['core_insert'] = true;
                // in the case of a ¤ in the ?hash, pass to the module (hierarchy or bibliotheque, mb other later)
                // the second call to make after loading the subcontainer
                if (isset($indexable['module_Full_param']) && str_contains($indexable['module_Full_param'],'¤')){
                    $tparameters = explode('¤', $indexable['module_Full_param']);
                    $_POST['module_extra'] = $tparameters[1];
                }
                $module_html_content[$module_name] = '';
                include($CONFIG::HOME_FOLDER.'public/'.$module_name.'/index.php');
                $diplayIndexable->add_child($module_html_content[$module_name]);
                    
                
            } else {
                $_SESSION['module_name']='document';
                $_SESSION['module_param']=$indexable['module_param'];
                $diplayIndexable->add_child($indexable['module_content']);
            }
        }
        $displayModules->add_child($diplayIndexable);
        //now we take care of the submodules
        if ($id > 0) {
            $q = 'SELECT DISTINCT * FROM '.$this->bddt_submodules.' WHERE id_disposition=? ORDER BY `sort_order`';
            $submodules = $DB->prepared_query_list($q, 'i', [$id]);
        } else {
            $submodules = [['module_name'=>$post['submodule'], 'module_param'=>'']];
        }
        $subcontainers=[];
        if ($submodules!=null) {
            foreach ($submodules as $submodule) {
                //check to see if the indexable module is in the conf
                foreach ($this->MODULE_LIST as $module_name => $module_data) {
                    if ($module_name == $submodule['module_name'] && $module_name != 'core') {
                        //it is in the config it is displayed.
                        $_POST = $original_post;

                        if ($submodule['module_param']!='') {
                            //we passed a query in get mode, we explode this

                            if (strpos($submodule['module_param'], '=') !== false) {
                                parse_str($submodule['module_param'], $_POST);
                            } else {
                                //we did not pass a query so we just call the param set in conf
                                $_POST[$module_data['public_param']] = $submodule['module_param'];
                            }
                        }

                        $_POST['core_insert'] = true;

                        $module_html_content[$module_name] = '';

                        include($CONFIG::HOME_FOLDER.'public/'.$module_name.'/index.php');
                        
                        if (isset($submodule['module_subcontainer']) && $submodule['module_subcontainer']!='' && $id > 0) {
                            if (!isset($subcontainers[$submodule['module_subcontainer']])) {
                                $subcontainers[$submodule['module_subcontainer']]=H::DIV(array('id'=>$submodule['module_subcontainer'] , 'class'=>'subcontainer '.$submodule['module_subcontainer']));
                                $displayModules->add_child($subcontainers[$submodule['module_subcontainer']]);
                            }
                            $subcontainers[$submodule['module_subcontainer']]->add_child($module_html_content[$module_name]);
                        }else{
                            $displayModules->add_child($module_html_content[$module_name]);
                        }
                    }
                }
            }
        }

        return $displayModules;
    }
    public function ShowOneModule(&$post)
    {
        global $module_html_content,$CONFIG;
        $post['core_insert']=true;
        $_POST  = $post;
        //protection
        if (isset($post['core'])) {
            echo ' ';
            exit;
        }
         
        foreach ($this->MODULE_LIST as $module_name => $module_data) {
            if (isset($post[$module_name])) {
                if ($post[$module_name]!='') {
                    //in this mode there is no query we only pass the default param
                    $_POST[$module_data['public_param']]=$_POST[$module_name];
                }

                $module_html_content[$module_name] = '';

                include($CONFIG::HOME_FOLDER.'public/'.$module_name.'/index.php');

                return $this->parse_hcode($module_html_content[$module_name]);
                //$_POST=$post;
            }
        }
    }
    public function four_o_four($mess=false){
        http_response_code(404);
        $message=($mess)?$mess:'';
        $output=H::div(['style'=>'display: block;
  margin: 0;
  padding: 0;
    padding-top: 0px;
  text-align: center;
  padding-top: 40vh;
  width: 100%;
  height: 100%;
  position: absolute;
  top: 0px;
  left: 0px;
  background: radial-gradient(circle, rgb(247, 247, 247) 0%, rgb(255, 162, 0) 62%, rgb(121, 28, 9) 85%, rgb(2, 0, 36) 100%);'],H::SPAN(['style'=>'font-size:10vh;font-family:sans;'],'404<br>'.H::SPAN(['style'=>'font-size:2vh;font-family:sans;'],$message)));
        echo $output;
        die();
    }
}
?>