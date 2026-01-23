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
namespace helPHP\modules\burger\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;

class Burger extends HelPHP_module
{

    //----------------------------------------------------------------------------------------------
    // indispensable variables for all modules
    //----------------------------------------------------------------------------------------------

    const module_name = 'burger';

    public function __construct($dom_container = null){
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);
    }
    
    public function process_data(&$post){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        $this->display->add_child($this->display($post));
    }
    
    public function display($post) {
        if (isset($post['menu_id'])) {
            $params = [
                'menu_id' => $post['menu_id'],
                'closed' => (isset($post['closed']) && $post['closed']) ? true : false
            ];
            $js = H::script('Burger.create_instance("'.$this->dom_id.'", '.json_encode($params).');', ['autoremove'=>true]);
            return $js;
        }
    }
}