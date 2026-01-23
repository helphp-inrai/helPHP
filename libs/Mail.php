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
namespace helPHP\libs;

use PHPMailer\PHPMailer;
use PHPMailer\Exception;
/**
 * @class Mail
 * 
 * Sending email is now quit complecate due to the number of security you have to pass thru, it's not enough to have the perfect
 * mail server, but the format of you email and its headers must be correct too. 
 * 
 * Based on PHPmailer that you can find in libs/external, 
 *
 * this class has the single objective to send mail properly based on the config/email.php from your instance,
 * please take a look at this config file to set options and signatures.
 * 
 * Usage : 
 * $my_mail=new Mail();
 * 
 * to embed an image 
 * $my_mail->mail->AddEmbeddedImage($path, $name);
 * 
 * to attach a file
 * $my_mail->mail->addAttachment($path, $name );
 * 
 * to send it :
 * $my_mail->send($to, $subject, $message, $from = null, $cc = false, $naming=false)
 * 
 * the send function is calling phpmailer, but if for any reason you have to use some simplier function
 * or older ones you'll find :
 * 
 * send0 : the smallest sending function with miminal hearders 
 * 
 * send1 : the same as send 0 but with html utf-8 doubled content
 * 
 * send2 : the same as send 1 but with multipart boundary, that allow you to add attachment as multipart in the message. (the old fashion method)
 *
 * (if you don't use commercial smtp and you need your own mailserver take a look on https://github.com/docker-mailserver/docker-mailserver)
 * 
 *  @see https://github.com/PHPMailer/PHPMailer/ please support their project
 * 
 * @package helPHP\libs
 */
class Mail
{
    public $mail = null;

    public function __construct()
    {
        global $CONFIG,$CONFIG_EMAIL;
        require $CONFIG::HELPHP_FOLDER.'libs/externals/PHP_Mailer/Exception.php';
        require $CONFIG::HELPHP_FOLDER.'libs/externals/PHP_Mailer/PHPMailer.php';
        require $CONFIG::HELPHP_FOLDER.'libs/externals/PHP_Mailer/SMTP.php';
        $this->mail = new PHPMailer\PHPMailer(true);
        $this->mail->setLanguage('fr', 'PHP_Mailer/language/');
        
        include_once($CONFIG::HOME_FOLDER.'config/email.php');
        $CONFIG_EMAIL=new \Config_email();

    }

    public static function check_adress($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function send($to, $subject, $message, $from = null, $cc = false, $naming=false)
    {
        global $CONFIG_EMAIL;
        if (!$from) {
            $from = $CONFIG_EMAIL::EMAIL_MAILING;
        }

        $mail = $this->mail;
        $mail->clearAllRecipients();


        try {
            //Server settings
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            //$mail->SMTPDebug = 2;                             // Enable verbose debug output
            $mail->isSMTP();                                    // Set mailer to use SMTP
            $mail->Host = $CONFIG_EMAIL::SMTP_HOST;             // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                             // Enable SMTP authentication
            $mail->Username = $CONFIG_EMAIL::SMTP_USER;         // SMTP username
            $mail->Password = $CONFIG_EMAIL::SMTP_PASS;         // SMTP password
            $mail->SMTPSecure = $CONFIG_EMAIL::SMTP_SECURITY;   // Enable TLS encryption, `ssl` also accepted
            $mail->Port = $CONFIG_EMAIL::SMTP_PORT;             // TCP port to connect to   
            
            //Recipients
            $naming = ($naming) ? $naming : $from;
            
            // $mail->Sender = $from;  // permit to indicate an envelope different from "from", some smtp permit to sent mail with a reference addrress in envelope, 
                                    // and some expect that the "from" is registered !
            // $mail->setFrom($from, $naming, false);
            $mail->setFrom($CONFIG_EMAIL::SMTP_USER);
            $mail->addAddress($to, $to);     // Add a recipient
            $mail->addReplyTo($from, $naming);

            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = html_entity_decode(strip_tags(str_replace('<br>', "\r\n", $message)));

            // Headers
            $mail->XMailer = 'PHP';
            $mail->addCustomHeader('X-Sender', $_SERVER['SERVER_NAME']);
            $mail->addCustomHeader('X-auth-smtp-user', $CONFIG_EMAIL::EMAIL_ADMIN);
            $mail->addCustomHeader('X-abuse-contact', $CONFIG_EMAIL::EMAIL_ADMIN);

            // Send !
            $mail->send();

            return true;
        } catch (Exception $e) {
            Utils::error_log('Message could not be sent.');
            Utils::error_log('Mailer Error: ' . $mail->ErrorInfo);

            return false;
        }
    }

    public static function send0($to, $subject, $message, $from = null)
    {
        global $CONFIG_EMAIL;
        if (!$from) {
            $from = $CONFIG_EMAIL::EMAIL_MAILING;
        }

        $ret = "\r\n";

        $entete  = 'MIME-Version: 1.0'.$ret;
        $entete .= 'Content-type: text/plain; charset=UTF-8'.$ret;
        $entete .= 'From: '.$from.' <'.$from.'>'.$ret;
        $entete .= 'X-Sender: <'.$_SERVER['SERVER_NAME'].'>'.$ret;
        $entete .= 'X-Mailer:PHP'.phpversion().$ret;
        $entete .= 'X-auth-smtp-user: '.$CONFIG_EMAIL::EMAIL_ADMIN.$ret;
        $entete .= 'X-abuse-contact: '.$CONFIG_EMAIL::EMAIL_ADMIN.$ret;
        $entete .= 'Date: '.date('D, j M Y G:i:s O').$ret;

        return mail($to, $subject, $message, $entete);
    }

    
    public static function send1($to, $subject, $message, $from = null)
    {
        global $CONFIG_EMAIL;
        if (!$from) {
            $from = $CONFIG_EMAIL::EMAIL_MAILING;
        }

        $plain_text = strip_tags(str_replace('<br>', "\n", $message));
        $html_text = $message;

        $sepAlternative = '-----='.md5(uniqid(mt_rand()));

        $entete = "From: \"webmaster\" <".$from.">\r\n";
        $entete .= "Reply-to: \"webmaster\" <".$from.">\r\n";
        $entete .= "Organization: \"Octopod Studio\"\r\n";
        $entete .= "X-auth-smtp-user: ".$from."\n";
        $entete .= "X-abuse-contact: ".$from."\n";
        $entete .= "MIME-Version: 1.0\r\n";
        $entete .= "X-Mailer:PHP".phpversion()."\r\n";
        $entete .= "Content-type: multipart/alternative; boundary=\"$sepAlternative\"\r\n";

        $msg = "--$sepAlternative\n";
        $msg .= "Content-Type: text/plain; charset=\"UTF-8\"\n";
        $msg .= "Content-Transfer-Encoding: 8bits\n\n";
        $msg .= $plain_text."\n\n";
        $msg .= "--$sepAlternative\n";
        $msg .= "Content-Type: text/html; charset=\"UTF-8\"\n";
        $msg .= "Content-Transfer-Encoding: 8bits\n\n";
        $msg .= $html_text."\n\n";
        $msg .= "--$sepAlternative--\n";

        return mail($to, $subject, $msg, $entete);
    }
    public static function send2($to, $subject, $message, $from = null)
    {
        global $CONFIG_EMAIL;
        if (!$from) {
            $from = $CONFIG_EMAIL::EMAIL_MAILING;
        }

        $plain_text = strip_tags(str_replace('<br>', "\n", $message));

        $html_text = $message;

        $mime_boundary = '==MULTIPART_BOUNDARY_'.md5(time());

        $ret = "\r\n";

        $body = '';

        $body .= '--'.$mime_boundary.$ret;
        $body .= 'Content-Type: text/plain; charset=UTF-8'.$ret;
        $body .= 'Content-Transfer-Encoding: 8bit'.$ret.$ret;
        $body .= $plain_text.$ret;

        $body .= '--'.$mime_boundary.$ret;
        $body .= 'Content-Type: text/html; charset=UTF-8'.$ret;
        $body .= 'Content-Transfer-Encoding: 8bit'.$ret.$ret;
        $body .= $html_text.$ret;

        $body .= '--'.$mime_boundary."--".$ret;

        $headers = '';

        $headers .= 'From: '.$from.' <'.$from.'>'.$ret;
        $headers .= 'Reply-to: '.$from.' <'.$from.'>'.$ret;
        $headers .= 'Return-Path: '.$from.' <'.$from.'>'.$ret;
        $headers .= 'Organization: '.$CONFIG_EMAIL::SITE_NAME.$ret;
        $headers .= 'X-Sender: <'.$_SERVER['SERVER_NAME'].'>';
        $headers .= 'X-Mailer:PHP'.phpversion().$ret;
        $headers .= 'X-auth-smtp-user: '.$CONFIG_EMAIL::EMAIL_ADMIN.$ret;
        $headers .= 'X-abuse-contact: '.$CONFIG_EMAIL::EMAIL_ADMIN.$ret;
        $headers .= 'X-Priority: 3'.$ret;
        $headers .= 'Date: '.date('D, j M Y G:i:s O').$ret;
        $headers .= 'MIME-Version: 1.0'.$ret;
        $headers .= 'Content-type: multipart/alternative; boundary="'.$mime_boundary.'"';

        return mail($to, $subject, $body, $headers);
    }

}
?>