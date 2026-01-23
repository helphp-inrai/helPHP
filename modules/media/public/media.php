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

use helPHP\libs\Media;
use helPHP\libs\Utils;


if (is_file('../../config/main.php')) {
    include_once('../../config/main.php');
} else if (is_file('../config/main.php')){
    include_once('../config/main.php');
}
include_once(Config::HELPHP_FOLDER.'autoload.php');

$field_identifier = isset($_GET['f']) ? $_GET['f'] : false;
$use_key = isset($_GET['i']) ? $_GET['i'] : false;
$secured_params = isset($_GET['pp']) ? $_GET['pp'] : false;
//secured_params is just a encoded scrambled array containing the $field_indentifier and $use_key
//its existence tell us that we want to try to stop video download.

if (($field_identifier && $use_key !== false) || $secured_params){
    global $DB, $CONFIG_DB, $FS;

    if ($secured_params) {
        //MUST CHECK PERMISSION !!!!
        $params = urldecode(str_rot13($secured_params));
        $params = explode('|µ|',$params);
        $field_identifier = $params[0];
        $use_key = $params[1];
    } 
        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');
        $q = 'SELECT DISTINCT d.path FROM '.$DB->table('media_data').' d INNER JOIN '.$DB->table('media_use').' u ON';
        $q.=' u.field_identifier = ? AND u.use_key = ? AND u.id_media = d.id';
        $path = $DB->prepared_query_value($q, 'si', [$field_identifier, $use_key]);

    //if we want to check read auth later :
    // $path = $FS->check_path($FS->root_fs.$path,true,'read');    
    
    if ($path){

        //video protection :
        $its_ok = true;

        if ($secured_params!=false) {

            $its_ok = false;
            $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

            if(isset($_SERVER["HTTP_RANGE"])) {
                if(stripos($_SERVER["HTTP_RANGE"],'bytes=')) $its_ok = true;
                if(isset($_SERVER["HTTP_REFERER"])) {
                    if(stripos($_SERVER["HTTP_REFERER"], Config::DOMAIN)) $its_ok = true;
                    else $its_ok = false;
                } else {
                    $its_ok = false;
                }
            } else {
                if(isset($_SERVER["HTTP_GETCONTENTFEATURES_DLNA_ORG"])) {
                    $its_ok = true;
                }
            }

            if(strpos($ua,'android') && strpos($ua,'applewebkit') && $its_ok == false) {
                if(!isset($_SERVER["HTTP_REFERER"])) $its_ok = true;
                else $its_ok = false;
            }

            if((strpos($ua,'ipad') || strpos($ua,'iphone') || strpos($ua,'ipod' )) && $its_ok == true) {
                if(!isset($_SERVER["HTTP_X_PLAYBACK_SESSION_ID"])) $its_ok = false;
            }
        }
        
        if ($its_ok) {
            Media::send_file($CONFIG::HOME_FOLDER.'files/'.$path);
            
        }else{
            echo 'sorry... downloading is forbidden';
        }
    }
}