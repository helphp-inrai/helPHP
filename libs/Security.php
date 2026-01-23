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

namespace helPHP\libs;

/**
 * @class Security
 * 
 * Small but important class, it secured all entering requests to avoid any kind of injection.
 * 
 * There is security measure at server level (.htaccess mod_security etc), 
 * in the javascript side (validator.js etc), but it's still possible to forge a request directly to the server
 * from cli or another unknow method, 
 * 
 * so the execution of Security::process_all_data is made at init to avoid any kind of injection.
 * It protect also against script injection in UI, partial protection against URL spoofing.
 * 
 * HelPHP_module check also if the column names of data exist in the DB, plus the DB class that offer prepared queries.
 * 
 * The session protect itself too (against session spoofing and cookie stealing etc)...
 * 
 * We have the chance to never suffer from hacking (except from a bit of ddos on hosting server a long time ago)
 * and we hope it will continue like that. But of course if you find a security hole in HelPHP, 
 * we'll be pleased to discuss about it and modify our code.
 * 
 * Thanks a lot for your concern.
 * 
 * @package helPHP\libs
 * 
 */
class Security
{
    public function __construct()
    {
    }

    public static function process_all_data()
    {
        if (isset($_POST)) {
            $_POST = Security::secure_sql_string($_POST);
        }
        if (isset($_GET)) {
            $_GET = Security::secure_sql_string($_GET);
        }
        if (!isset($_GET) && !isset($_POST) && isset($_REQUEST)) {
            $_REQUEST = Security::secure_sql_string($_REQUEST);
        }
        if (isset($_FILES)) {
            $_FILES = Security::secure_sql_string($_FILES);
        }
    }

    public static function secure_sql_string($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $value) {
                $str[$key] = Security::secure_sql_string($value);
            }
        } elseif (is_string($str)) {
            $str = Security::my_real_escape_string($str);
        }

        return $str;
    }

    public static function my_real_escape_string($str)
    {
        return strtr($str, array(
            "\x00" => '\x00',
            "\n" => '\n',
            "\r" => '\r',
            '\\' => '\\\\',
            "'" => "\'",
            '"' => '\"',
            "\x1a" => '\x1a',
            "<script>" => '<NOscript>',
            "<SCRIPT>" => '<NOscript>',
            "<Script>" => '<NOscript>'
        ));
    }

    public static function get_base_url()
    {
        global $CONFIG;
        
        $url = $_SERVER['REQUEST_SCHEME'].'//';

        $url .= $_SERVER['SERVER_NAME'];

        $url .= '/'.$CONFIG::SITE_FOLDER;

        return $url;
    }
}