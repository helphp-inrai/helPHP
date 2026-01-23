<?php
/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */

namespace helPHP\tests;

include_once('../config/main.php');
include_once('../../helPHP/autoload.php');

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\modules\preview\admin\Preview;

class Preview_test extends helPHP_Module {

    const module_name = 'test';

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name,false);
        $this->dom_container = '';
        parent::__construct($dom_container);

    }

    public function process_data(&$post) {
        if (parent::process_data($post) == false) {
            //utilisateur non autorisé sur ce module
            return null;
        }
        
        $this->display = H::new_document('media helPHP', '', '', false, false);
        switch ($post[$this->input_action_identifier]) {
            default:
                $this->display->add_child( $this->display($post) );
            break;
        }
    }

    public function display($post){
        global $CONFIG;
        
        $output = H::DIV(['class'=>$this->css.'container']);

        $output->add_child( H::script('CONSTANTS.admin_folder = "'.$CONFIG::ADMIN_FOLDER.'";') );

        // $ = H::SPAN(['class'=>$this->css.'preview'], 'PREVIEW');

            // $public = H::DIV(['class'=>'public']);
            //     $title = H::DIV(['class'=>'title'], 'PUBLIC');
            // $public->add_child($title);
            //     $preview = new Preview();
            //     $_POST['module'] = 'deco';
            //     $_POST['id'] = '1'; 
            // $public->add_child($preview->process_data($_POST, true));

            $admin = H::DIV(['class'=>'admin']);
                $title = H::DIV(['class'=>'title'], 'ADMIN');
            $admin->add_child($title);
            $preview = new Preview();
            $_POST['module'] = 'deco';
            $_POST['id'] = '1'; 
            $_POST['admin'] = true;
            $admin->add_child($preview->process_data($_POST, true));

        // $output->add_child( [$public, $admin] );
        $output->add_child( [$admin] );

        return $output;
    }

}

$module_test = new Preview_test();

$module_test->process_data($_POST);

$module_test->echo_output();