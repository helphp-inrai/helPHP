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

/**
 * @brief Init.php is here to help instanciating and launching all what we need for our modules.
 * 
 * NOTA ! When a class should be instanciated, we call its create_instance method instead of manipulating its construct.
 * the construct can be used for other things and it permit to call methods that do not depends the current instance object.
 * 
 * in session class for exemple, the garbage collector work on all possible session and not just the current instance.
 * 
 * other example : in DB class create instance create the mysql connection with config param, but the construct can be used to create another connection
 * 
 * on the contrary, HTML_class the html_group_class force the creation of another html due to this instance confusion.
 * 
 * @package helPHP\libs
 * 
 */

header('Content-type: text/html; charset=UTF-8');

global $CONFIG;
$CONFIG = new \Config();
if ($CONFIG::DEVMODE) {
    global $chron;
    $chron=new helPHP\libs\Datetime(); //useful only for perf testing...
    $chron->start_chrono();
}
//will create $CRYPT global
helPHP\libs\Crypt::create_instance();

include_once($CONFIG::HOME_FOLDER.'config/db.php');
global $CONFIG_DB;
$CONFIG_DB= new \Config_db();
//will create $DB global!
helPHP\libs\DB::create_instance();


//will create $FS global
helPHP\libs\Filesystem::create_instance();

//will create $SESSION global
helPHP\libs\Sessions::create_instance();
// start the session
// global $SESSION;
// $SESSION::open_session();

//error_reporting(E_ALL);
ini_set('error_reporting', E_ALL); // because we are mad guys who hates warning
ini_set('display_errors', 'on'); // so yes we display errors !!!
// -----------------------------------------------

if (isset($_REQUEST) && sizeof($_REQUEST) > 0) {
    // received data are filtered to convert JSON variables
    // must be done before security pass
    // note that for $_FILES should be processed by modules with  H_ajax::process_files
    // and possible call to antivirus filters can be added around it if it's not managed by host server
    helPHP\libs\Ajax::process_all_data();
    // received data are filtered to remove possible injections
    // must be done after ajax json pass
    helPHP\libs\Security::process_all_data();
}

// -----------------------------------------------
//will create $LANG global
helPHP\libs\Language::create_instance();
// user account management
// will create $USER global
helPHP\libs\User::create_instance();
global $USER;
$USER->check_connection_data();

// -----------------------------------------------
//media class
helPHP\libs\Media::create_instance();
// -----------------------------------------------

// init event list array
global $event_lst;
$event_lst = [];

// if ($CONFIG::DEVMODE) {
//     $chron->step_chrono('End of init ');
// }