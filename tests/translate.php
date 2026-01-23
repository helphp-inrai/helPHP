<?php
namespace helPHP\tests;

// $baseroot = dirname($_SERVER['DOCUMENT_ROOT']);
// $siteroot = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['SCRIPT_FILENAME'])[0];
include_once('../config/main.php');
include_once(\Config::HELPHP_FOLDER.'autoload.php');

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;

class Test extends helPHP_Module
{

    //----------------------------------------------------------------------------------------------
    // variables indispensables à tous les modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'test';

    protected $module_name = self::module_name;
    protected $SCRIPT_PATH = __DIR__;

    protected $admin = false;

    // nom du champ d'action intégré au formulaire (ici c'est soit un bouton submit soit un select)
    protected $input_action_identifier = self::module_name.'_action';

    //----------------------------------------------------------------------------------------------
    // variables spécifiques à ce module
    //----------------------------------------------------------------------------------------------

    private $css_class = self::module_name.'_admin_';
    
    private $groups = [];
    
    
    private $ACTION_TRANSLATE = self::module_name.'_translate';
    
    // nom du div conteneur dom où afficher le retour html
    protected $dom_container = '';
    // protected $dom_container = self::module_name.'_container';

    public function __construct($dom_container = null, $comments = false)
    {
        // global $DB;
        // execution de la classe parent qui initialise la langue et les données de traduction

        parent::__construct($dom_container);
        
        // if (Config::DB_MEMORY){
        //     global $DB_MEMORY;
        //     $DB_GRP = $DB_MEMORY;
        // } else {
        //     global $DB;
            // $DB_GRP = $DB;
        // }
        // Utils::error_log($post);
    }

    public function process_data(&$post)
    {
        if (parent::process_data($post) == false) {
            //utilisateur non autorisé sur ce module
           return null;
        }
        // Utils::error_log($post);
        $this->display=H::new_document('translatation test HelPHP', '', '', false, true);
        switch ($post[$this->input_action_identifier]) {

            // -------------------------------------------------------------------------------------------
            case $this->ACTION_TRANSLATE:
                $this->display->add_child($this->ProcessData_Translate($post));
            break;
            
            // -------------------------------------------------------------------------------------------
            default:
                $this->display->add_child($this->ShowFields($post));
            break;
        }

    }

    public function ShowFields($post){
        // Utils::error_log('truc');
        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_container, 'class'=>$this->css_class.'form_edit']);
        $form->add_child($this->translate_block($post, [$this->ifld_data_titre, $this->ifld_data_description, $this->ifld_data_text], 'sll', [[],[],[]]));
        return $form;
    }

    public function ProcessData_Translate($post){
        global $LANG;
        $res=$LANG->translate('','','');
    }
    
}

$module_test = new Test();

$module_test->process_data($_POST);

$module_test->echo_output();

?>