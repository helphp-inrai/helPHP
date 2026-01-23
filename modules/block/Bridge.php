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

namespace helPHP\modules\block;

use helPHP\libs\H;
use helPHP\libs\HelPHP_module;
use helPHP\libs\Utils;

/**
 * Do the bridge between a block and it's caller.
 * Will get a call from a module and will send it to the corresponding block.
 * The block will respond and bridge will send it back to the caller.
 * 
 * The caller is responsible of returning something to the client.
 */
class Bridge extends HelPHP_module{

    const module_name = 'block';

    private $back_to_caller = true;

    protected $root_module = true;

    public function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
        parent::__construct($dom_container);
    }

    public function process_data(&$post, $to_return = false) {
        if (parent::process_data($post) == false) {
            // utilisateur non autorisé sur ce module
            return null;
        }

        $this->dom_container = '';

        if (!isset($post['block_name'])) {
            Utils::error_log('ERROR - missing block_name in bridge');
            return;
        }
        if (!isset($post['block_id'])) {
            if (!isset($post['block_'.$post['block_name'].'-id'])) {
                Utils::error_log('ERROR - missing block_id in bridge');
                return;
            }
            $post['block_id'] = $post['block_'.$post['block_name'].'-id'];
        }

        // admin or public
        $folder = $post['block_action'] == 'block_load' ? 'public' : 'admin';

        $this->back_to_caller = (isset($post['back_to_caller'])) ? $post['back_to_caller'] : $this->back_to_caller;

        $post['block_'.$post['block_name'].'-id'] = $post['block_id'];

        $master_output = H::group('bridge_display'.$post['block_name'].$post['block_id']);

        $block_class = '\helPHP\modules\block\\'.$post['block_name'].'\\'.$folder.'\\'.ucfirst($post['block_name']);
        $err = false;
        try {
            $block_instance = new $block_class();
        } catch (\Throwable $th) {
            // can't talk with block, probably not generated
            $err = true;
        }
        
        if (!$err){
            $master_output->add_child( $this->parse_hcode($block_instance->process_data($post, true), ($folder == 'admin')) );

            // send back to caller to let it handle the return
            if ($this->back_to_caller && isset($post['caller'])) {
                if ($this->debug) Utils::error_log('back to caller');
                
                $post['block_id'] = $post['block_'.$post['block_name'].'-id'];
                if (isset($post['caller_params'])){
                    foreach($post['caller_params'] as $name => $value){
                        $post[$name] = $value;
                    }
                }
                unset($post['caller_params']);

                // action to indicate the process in the caller
                // formatted like caller_block_action 
                // exemple: document_block_save, document_block_delete
                $post[$post['caller'].'_action'] = $post['caller'].'_'.$post['block_action'];

                $caller_class = '\helPHP\modules\\'.$post['caller'].'\\admin\\'.ucfirst($post['caller']);
                $caller_instance = new $caller_class();
                $master_output->add_child( $caller_instance->process_data($_POST, true) );
            }
        } else {
            $master_output->add_child( H::DIV(['class'=>$this->css.'error'], $this->get_tl('tlc_block_not_accessible')) );
        }
        
        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }

    static public function load($caller, $params, $block_name, $block_id, $dom_id = false, $edit = false){
        $post['caller'] = $caller;
        $post['caller_params'] = $params;
        $post['block_name'] = $block_name;
        $post['block_id'] = $block_id;

        if (isset($dom_id)) $post['dom_id'] = $dom_id;
        $post['block_action'] = $edit ? 'block_edit' : 'block_load';
        $post['back_to_caller'] = false;

        $inst = new Bridge(null);
        return $inst->process_data($post, true);
    }

    static public function delete($block_name, $block_id){
        $post['block_name'] = $block_name;
        $post['block_id'] = $block_id;

        $post['block_action'] = 'block_delete';
        $post['back_to_caller'] = false;

        $inst = new Bridge(null);
        return $inst->process_data($post, true);
    }
}