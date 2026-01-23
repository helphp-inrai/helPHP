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

namespace helPHP\modules\unban\admin;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;

class Unban extends HelPHP_module{

    const module_name = 'unban';
    
    private $ACTION_UNBAN = self::module_name.'_unban';

    function __construct($dom_container = null) {
        $this->prepare_module(self::module_name, true);
        parent::__construct();
    }
    
    public function process_data(&$post, $to_return = false){
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        $master_output = H::group('unban_display');
        switch($post[$this->input_action_identifier]){
            case $this->ACTION_UNBAN:
                $this->unban($post);
                $master_output->add_child( $this->display($post) );
            break;
            
            default:
                $master_output->add_child( $this->display($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    public function display ($post) {
        global $DB;
        
        $q = 'SELECT DISTINCT user_agent, ip FROM '.$DB->table('connection_try_logs').' WHERE locked=1';
        $lst = $DB->query_list($q);
        foreach($lst as $key => $line){
            $q = 'SELECT login FROM '.$DB->table('connection_try_logs').' WHERE user_agent LIKE ? AND ip LIKE ?';
            $line['logins'] = $DB->prepared_query_list($q,'ss',[$line['user_agent'],$line['ip']]);
            $lst[$key] = $line;
        }
        
        $output = H::group('select_unban');

            $title = H::DIV(array('class'=>$this->css.'title module_title'), $this->get_tl('title'));
        
        $output->add_child($title);
        
        if ($lst) {

            $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form form_edit']);
                $table = H::table(['class'=>$this->css.'lst_banned']);
                $tbody = H::tbody();
                $table->add_child($tbody);
                
                // header table
                $row = H::tr(['class'=>$this->css.'row header']);
                    $user_agent = H::th(['class'=>$this->css.'column header user_agent'], $this->get_tl('user_agent'));
                    $ip = H::th(['class'=>$this->css.'column header ip'], $this->get_tl('ip'));
                    $login = H::th(['class'=>$this->css.'column header login'], $this->get_tl('login'));
                    $btn = H::th(['class'=>$this->css.'column header unban']);
                $row->add_child([$user_agent,$ip,$login,$btn]);
                $tbody->add_child($row);
                
                foreach($lst as $key => $line){
                    $row = H::tr(['class'=>$this->css.'row']);
                        $user_agent = H::td(['class'=>$this->css.'column user_agent'], $line['user_agent']);
                        $ip = H::td(['class'=>$this->css.'column ip'], $line['ip']);
                        $login = H::td(['class'=>$this->css.'column login'], implode('<br>',$line['logins']));
                        $td_btn = H::td(['class'=>$this->css.'column header unban']);
                            $btn = H::submit_button_single(['name'=>$this->input_action_identifier,'value'=>$this->ACTION_UNBAN,'class'=>$this->css.'btn_unban','data-parameters'=>['user_agent'=>$line['user_agent'],'ip'=>$line['ip']]],$this->get_tl('unban'));
                        $td_btn->add_child($btn);
                    $row->add_child([$user_agent,$ip,$login,$td_btn]);
                    $tbody->add_child($row);
                }
            $form->add_child($table);
            $output->add_child($form);

        } else {

            $nothing = H::DIV(['class'=>$this->css.'nothing'], $this->get_tl('nothing'));
            $output->add_child($nothing);

        }
        
        return $output;
    }
    
    public function unban(&$post) {
        global $DB;
        
        $q = 'UPDATE '.$DB->table('connection_try_logs').' SET locked=2 WHERE user_agent LIKE ? AND ip LIKE ?';
        $res = $DB->prepared_query($q,'ss',[$post['user_agent'],$post['ip']]);
        $this->add_message('unban_success');
    }
}