<?php
namespace helPHP\tests;

use PSpell\Config;

// $baseroot = dirname($_SERVER['DOCUMENT_ROOT']);
// $siteroot = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['SCRIPT_FILENAME'])[0];
include_once(dirname(__DIR__).'/config/main.php');
include_once(\Config::HELPHP_FOLDER.'autoload.php');

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;

class Test extends helPHP_Module
{

    //----------------------------------------------------------------------------------------------
    // essential variables for all modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'test';

    protected $module_name = self::module_name;
    protected $SCRIPT_PATH = __DIR__;

    protected $admin = false;

    // name of the form action field (here either a submit button or a select)
    protected $input_action_identifier = self::module_name.'_action';

    //----------------------------------------------------------------------------------------------
    // module-specific variables
    //----------------------------------------------------------------------------------------------

    private $css_class = self::module_name.'_admin_';
    
    private $groups = [];
    
    // name of the DOM container div to display the HTML output
    protected $dom_container = '';
    // protected $dom_container = self::module_name.'_container';

    public function __construct($dom_container = null, $comments = false)
    {
        // global $DB;
        // execute parent class which initializes the language and translation data

        parent::__construct($dom_container);
        
    }

    public function process_data(&$post)
    {
        if (parent::process_data($post) == false) {
            // user not authorized for this module
           return null;
        }
        // Utils::error_log($post);
        $this->display=H::new_document('BASE_URL test HelPHP', '', '', false, true);
        switch ($post[$this->input_action_identifier]) {

            // -------------------------------------------------------------------------------------------
            // -------------------------------------------------------------------------------------------
            default:
                $this->display->add_child($this->testing($post));
            break;
        }

    }

    public function testing($post){
        global $CONFIG;
        $testurl=$CONFIG::BASE_URL;
        $test = Utils::check_url($testurl);
        if ($test){
            return H::div(['id'=>'test','style'=>'color:#000000;'],$testurl.' is reachable');
        }else{
            return H::div(['id'=>'test','style'=>'color:#000000;'],$testurl.' can\'t be reach... ');
        }
    }

}

$module_test = new Test();

$module_test->process_data($_POST);

$module_test->echo_output();

?>