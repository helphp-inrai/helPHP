<?php
namespace helPHP\tests;

// $baseroot = dirname($_SERVER['DOCUMENT_ROOT']);
// $siteroot = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['SCRIPT_FILENAME'])[0];
include_once(dirname(__DIR__).'/config/main.php');
include_once('../../helPHP/autoload.php');

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

    protected $action_check_user_right = self::module_name.'_check_url';
    //----------------------------------------------------------------------------------------------
    // variables spécifiques à ce module
    //----------------------------------------------------------------------------------------------

    private $css_class = self::module_name.'_admin_';
    
    private $groups = [];
    
    // nom du div conteneur dom où afficher le retour html
    protected $dom_container = '';
    // protected $dom_container = self::module_name.'_container';

    public function __construct($dom_container = null, $comments = false)
    {
        // global $DB;
        // execution de la classe parent qui initialise la langue et les données de traduction

        parent::__construct($dom_container);
        
    }

    public function process_data(&$post)
    {
        if (parent::process_data($post) == false) {
            //utilisateur non autorisé sur ce module
           return null;
        }
        // Utils::error_log($post);
        
        switch ($post[$this->input_action_identifier]) {
            case $this->action_check_user_right:
                echo $this->group_access($post);
            break;
            // -------------------------------------------------------------------------------------------
            // -------------------------------------------------------------------------------------------
            default:
                $this->display=H::new_document('group restriction test', '', '', false, true);
                $this->display->add_child($this->testing($post));
            break;
        }

    }

    public function testing($post){
        $style = 'display: grid; color: black; just; justify-content: center;';
        $form = H::form(['action'=>'http://localhost/tests/group.php', 'dom_target'=>$this->css.'result', 'style'=>$style]);
            $title = H::DIV([], 'Test access to an item from group restriction for connected user');
        $form->add_child( $title );
            $field_identifier = H::input_text(['name'=>'field_identifier', 'value'=>'', 'label'=>'field identifier']);
            $id_item = H::input_integer(['name'=>'id_item', 'value'=>'', 'label'=>'field id']);
        $form->add_child( [$field_identifier->label_tag(), $field_identifier, $id_item->label_tag(), $id_item] );
            $btn_submit = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->action_check_user_right],'check');
        $form->add_child($btn_submit);
            $result = H::DIV(['id'=>$this->css.'result']);
        $form->add_child($result);
        

        return $form;
    }

    public function group_access($post){

        $result = $this->group_has_access($post['field_identifier'], $post['id_item']);

        return $result ? 'User can access' : 'User can\'t access';
    }

}

$module_test = new Test();

$module_test->process_data($_POST);

$module_test->echo_output();