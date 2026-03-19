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
    // essential variables for all modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'test';

    protected $module_name = self::module_name;
    protected $SCRIPT_PATH = __DIR__;

    protected $admin = false;

    // name of the form action field (here either a submit button or a select)
    protected $input_action_identifier = self::module_name.'_action';

    protected $action_check_url = self::module_name.'_check_url';
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
        
        switch ($post[$this->input_action_identifier]) {
            case $this->action_check_url:
                echo $this->check_url($post);
            break;
            // -------------------------------------------------------------------------------------------
            // -------------------------------------------------------------------------------------------
            default:
                $this->display=H::new_document('FS test HelPHP', '', '', false, true);
                $this->display->add_child($this->testing($post));
            break;
        }

    }

    private $url_to_test = [
        '/home/default/public/deco/1/test.img',
        '/home/default/public/users/3/unfichier.png',
    ];

    public function testing($post){
        $form = H::form(['action'=>'http://localhost/tests/fs.php','dom_target'=>$this->css.'result']);

            foreach($this->url_to_test as $url){
                $inp = H::input_hidden(['name'=>'url[]','value'=>$url]);
                $form->add_child($inp);
            }
            $btn_submit = H::submit_button(['name'=>$this->input_action_identifier,'value'=>$this->action_check_url],'check');
        $form->add_child($btn_submit);

        $result = H::DIV(['id'=>$this->css.'result']);

        return [$form, $result];
    }

    public function check_url($post){
        global $FS;

        $FS->root_fs = '/home/default/public/';
        $output = H::group('result');
        Utils::error_log('******************************************************************************************************');
        foreach($post['url'] as $key => $url){
            $result = $FS->check_permission($url,'');
            Utils::error_log($result);
            // $output->add_child(H::DIV([], $url.'    -    '.$FS->check_permission($url,'')));
        }
        // Utils::error_log('END');
        return $output;
    }

}

$module_test = new Test();

$module_test->process_data($_POST);

$module_test->echo_output();