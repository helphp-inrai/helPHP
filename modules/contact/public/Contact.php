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
namespace helPHP\modules\contact\public;

use \Config;
use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\User;
use helPHP\libs\Mail;
use helPHP\libs\Utils;

class Contact extends HelPHP_module {

    const module_name = 'contact';

    protected $ACTION_SEND = self::module_name.'_send';

    private $email_receiver = false;
    private $mail;

    public function __construct($dom_container = null){
        $this->prepare_module(self::module_name, false);
        parent::__construct($dom_container);

        $this->mail = new Mail();
        global $CONFIG_EMAIL;

        if ($CONFIG_EMAIL::EMAIL_CONTACT !== null){
            $this->email_receiver = [$CONFIG_EMAIL::EMAIL_CONTACT];
        }
    }

    public function process_data(&$post, $toreturn = false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }
        
        $master_output = H::group('contact_display');
        switch ($post[$this->input_action_identifier]) {
            case $this->ACTION_SEND:
                $master_output->add_child( $this->send($post) );
            break;

            default:
                $master_output->add_child( $this->display($post) );
            break;
        }

        if ($toreturn){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }

    }
    public function display($post) {

        if ($this->email_receiver === false) {
            $div = H::DIV(['class'=>$this->css.'info error'], $this->get_tl('missing_email_contact'));
            return $div;
        }

        $form = H::form(['action'=>$this->get_index_relative_path(), 'dom_target'=>$this->dom_target, 'class'=>$this->css.'form']);

            $lastname = H::input_text(['name'=>'contact_lastname', 'label'=>$this->get_tl('lastname'),'data-required'=>1]);
            $firstname = H::input_text(['name'=>'contact_firstname', 'label'=>$this->get_tl('firstname')]);
            $email = H::input_email(['name'=>'contact_email', 'label'=>$this->get_tl('email'), 'placeholder'=>'', 'data-required'=>1]);

        $form->add_child( [$lastname->label_tag(), $lastname, $firstname->label_tag(), $firstname, $email->label_tag(), $email] );
        
            $subject = H::input_text(['name'=>'contact_subject', 'label'=>$this->get_tl('subject'), 'data-required'=>1]);
            $content = H::input_textarea(['name'=>'contact_content', 'label'=>$this->get_tl('content'), 'data-required'=>1]);
            
        $form->add_child( [$subject->label_tag(), $subject, $content->label_tag(), $content] );
            
            $btn_send = H::submit_button(['name'=>$this->input_action_identifier, 'value'=>$this->ACTION_SEND, 'class'=>$this->css.'btn_send', 'with_token'=>1], $this->get_tl('send'));
        
        $form->add_child( $btn_send );

        return $form;
    }
    public function send($post) {

        global $CONFIG_EMAIL, $CONFIG;        
        if ($this->check_token($post,300)) {
            if (isset($post['contact_lastname']) && isset($post['contact_email']) && isset($post['contact_subject']) && isset($post['contact_content'])) {

                
                $email_sender = $post['contact_email'];

                $subject = stripcslashes($post['contact_subject']);

                $msg_info = '';

                $message = '';
                // if (isset($post['contact_societe']) && $post['contact_societe'] != '') {
                //     $message.='Société : '.$post['contact_societe']."<br>";
                // }
                
                $message.= $this->get_tl('mail_lastname').' '.$post['contact_lastname']."<br>";
                $message.= $this->get_tl('mail_firstname').' '.$post['contact_firstname']."<br>";

                $content_mess = stripcslashes($post['contact_content']);
                $content_mess = str_replace(array("\\r\\n","\r","\n","\\r","\\n"), "<br>", $content_mess);
                
                $message.='Message : '."<br>".$content_mess;

                $sended = false;
                if ($CONFIG_EMAIL::EMAIL_SIGNATURE_BODY != ''){
                    $message.= $CONFIG_EMAIL::EMAIL_SIGNATURE_BODY;
                    foreach($CONFIG_EMAIL::EMBEDED as $to_embed){
                        $this->mail->mail->AddEmbeddedImage($CONFIG::HOME_FOLDER.$to_embed['src'], $to_embed['name']);
                    } 
                }
                foreach ($this->email_receiver as $to) {
                    if ($this->mail->Send($to, $subject, $message, $email_sender)) {
                        $sended = true;
                    }
                }

                $div = H::DIV(['class'=>$this->css.'info']);
                if ($sended) {
                    $div->add_class('success');
                    $div->add_child( H::SPAN(null, $this->get_tl('mail_sended')));
                } else {
                    $div->add_class('error');
                    $div->add_child( H::SPAN(null, $this->get_tl('mail_error')));
                }

                return $div;
            }
        }
            
        
    }
}