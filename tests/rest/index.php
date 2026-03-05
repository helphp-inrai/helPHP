<?php
namespace helPHP\tests;

use helPHP\libs\Crypt;
use helPHP\libs\Utils;
use helPHP\libs\Rest;

include_once(dirname(dirname(__DIR__)).'/config/main.php');
include_once(\Config::HELPHP_FOLDER.'autoload.php');

global $APIURL;
$APIURL=\Config::BASE_URL.'api/';

$shellMode='json'; // You can choose 'argv' or 'json' when you request this client from shell. 

//first testing if we're under shell ...
if (isset($_SERVER['HTTP_HOST'])){
    if (isset($_GET['command']) && $_GET['command']=='download' && isset($_GET['shouldsend'])){
        $_REQUEST=$_GET;
        //cleaning request :
        $datatosend['command']=$_REQUEST['command'];
        $datatosend['action']=$_REQUEST['action'];
        $route='json/';
        unset($_REQUEST['command'],$_REQUEST['action'],$_REQUEST['route'],$_REQUEST['ok'],$_REQUEST['shouldsend']);
        $datatosend['param']=json_encode($_REQUEST);
        $crypt = new Crypt();
        $datatosend['param']=($_REQUEST['crypted']=="crypted")?$crypt->encrypt(json_encode($_REQUEST)):json_encode($_REQUEST);
        // Utils::error_log($_REQUEST);
        $client=new \helPHP\libs\Rest($APIURL,$_REQUEST['type'],$route,$datatosend,false,false);
        $Return=$client->client_request();
    } else {
        $display=new Client_UI();
        echo $display->show_UI();
    }
}else{
    //we're in shell mode
    //in argv mode
    if ($shellMode=='argv'){
        $client=new Rest('argv',$commandsParams,'');
    }
    if ($shellMode=='json'){
        $_REQUEST=json_decode($argv[1],true);
        //cleaning request :
        $datatosend['command']=$_REQUEST['command'];
        $datatosend['action']=$_REQUEST['action'];
        $route='json/';
        unset($_REQUEST['command'],$_REQUEST['action'],$_REQUEST['route'],$_REQUEST['ok'],$_REQUEST['shouldsend']);
        $datatosend['param']=json_encode($_REQUEST);
        // Utils::error_log($_REQUEST);
        $crypt = new Crypt();
        $datatosend['param']=($_REQUEST['crypted']=="crypted")?$crypt->encrypt(json_encode($_REQUEST)):json_encode($_REQUEST);
        $client=new \helPHP\libs\Rest($APIURL,$_REQUEST['type'],$route,$datatosend,false,false);
        $Return=$client->client_request();
    }
}

class Client_ui{
    
    //~ public $comParams;
    public $commands;
    
    public function __construct(){
        // retrieve documentation from db
        $this->commands = [
            'Start'=>array(
                'type'=>'head',
                'title'=>'Welcome on HelPHP rest client',
                'description'=>'The api url is your config home URL + "api/".<br>You must add "command/" or "module/" to this URL depending if your calling an hardcoded command or a module action.<br>
                Some Usual or filesystem related commands (like "connection" or "download") are hardcorded in the libs/Restreponse.php file,<br>
                but the main purpose of this API is to bridge module actions.<br><br>
                You can use POST GET DELETE PUT on all commands...<br><br>
                Bellow there is some commands examples, and at the end the "post" and "get" bridge to module.
                For all commands or call to module you\'ll need an OID ...<br>
                OID : string, the origine id, this id permit to maintain an equivalent of a session/user id and can be obtained with the first "connection" post.<br>It is refreshed after each command.<br>
                <br><br>You can also select to encrypt or not the data sent and received, for this be careful to use same encryption key in the main config file of the instance using the client and the instance delivering the API.<br>
                <br>so firstly you need to use the "connection" command to obtain an oid necessary to make calls to other commands that need it (not necessary for all ).',
                'documentation'=>'',
                'command'=>'',
                'command_params'=>[]
            ),
            'connection'=>array(
                'type'=>'POST',
                'title'=>'connection',
                'with_oid'=>0,
                'description'=>'The first command you should use ! it will return the OID. Duration of 30j <br><br>It is working like a "module post" to the "connection" module with "connect" action.Please read the documentation on the right',
                'documentation'=>'Connection :<br> Connect to user account with the connection module, you must send "connect" as action to get the OID.<br><br>Method : POST<br><br>Return : Json string<br><br>',
                'command'=>'sign_in',
                'command_params'=>['action'=>'string','login'=>'string','password'=>'string']
            ),
            'upload_new'=>array(
                'type'=>'POST',
                'title'=>'Upload new file : upload_new',
                'with_oid'=>1,
                'description'=>'Starting a new upload with chunks need a little preparation. First you indicate where you want to upload, it will check the permission and, if it\'s ok will return an operation_id necessary to upload chunks (next operation to see : upload_chunk)',
                'documentation'=>'upload_new :<br>Prepare a new upload and return the operation_id necessary for upload. <br>Destination : path to where the file should be put.<br>filename: name of the file<br>size : its original size in bytes to check if the file is complete after joining chuncks.<br>Method : POST<br><br>Return : Json string with the operation ID needed for next upload_chunk and upload_end command.<br><br>',
                'command'=>'upload_new',
                'command_params'=>['destination'=>'string','filename'=>'string','size'=>'int']
            ),
            'upload_chunk'=>array(
                'type'=>'POST',
                'title'=>'Upload file Chunk : upload_chunk',
                'with_oid'=>1,
                'description'=>'After using "upload_new", you can upload your file in one chunk if it\'s a small one or multiple chunks as attached file.<br>
                the operation_id permit to identify the upload operation and link chunks to it.<br>So if you have ten chunks, you use 10 times "upload_chunk" with the same operation_id and just change the "number" parameter and the attached chunk.',
                'documentation'=>'upload_chunk :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'upload_chunk',
                'command_params'=>['operation_id'=>'string','number'=>'string','chunckfile'=>'file']
            ),
            'upload_end'=>array(
                'type'=>'POST',
                'title'=>'Upload end : upload_end',
                'with_oid'=>1,
                'description'=>'After uploading all chunks we need to send and order to join all chunks.',
                'documentation'=>'upload_end :<br>total : the total number of chunks<br>timestamp: the date the file should have (to keep its original date and not the date of its upload) <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'upload_end',
                'command_params'=>['operation_id'=>'string','total'=>'string','timestamp'=>'string']
            ),
            'download'=>array(
                'type'=>'GET',
                'title'=>'Download',
                'with_oid'=>1,
                'description'=>'',
                'documentation'=>'Download :<br> <br><br>Method : GET<br><br>Return : File raw data <br><br>',
                'command'=>'ls',
                'command_params'=>['path'=>'string']
            ),
            'ls'=>array(
                'type'=>'GET',
                'title'=>'LS',
                'with_oid'=>1,
                'description'=>'Display the content of a path',
                'documentation'=>'ls :<br> <br><br>Method : GET<br><br>Return : Json string <br><br>',
                'command'=>'ls',
                'command_params'=>['path'=>'string']
            ),
            'recurse_ls'=>array(
                'type'=>'GET',
                'title'=>'Recursive LS :recurse_ls',
                'with_oid'=>1,
                'description'=>'Display the content of a path recursively',
                'documentation'=>'recurse_ls :<br> <br><br>Method : GET<br><br>Return : Json string <br><br>',
                'command'=>'recurse_ls',
                'command_params'=>['path'=>'string']
            ),
            'move'=>array(
                'type'=>'POST',
                'title'=>'Move',
                'with_oid'=>1,
                'description'=>'Move a file or directory from "origin" to "destination"',
                'documentation'=>'move :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'move',
                'command_params'=>['origin'=>'string','destination'=>'string']
            ),
            'remove'=>array(
                'type'=>'POST',
                'title'=>'Remove',
                'with_oid'=>1,
                'description'=>'Remove a file or directory ',
                'documentation'=>'remove :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'remove',
                'command_params'=>['path'=>'string']
            ),
            'copy'=>array(
                'type'=>'POST',
                'title'=>'Copy',
                'with_oid'=>1,
                'description'=>'Copy a file or directory from "origin" to "destination"',
                'documentation'=>'copy :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'copy',
                'command_params'=>['origin'=>'string','destination'=>'string']
            ),
            'mkdir'=>array(
                'type'=>'POST',
                'title'=>'Mkdir',
                'with_oid'=>1,
                'description'=>'Create a directory in "path" ',
                'documentation'=>'mkdir :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'mkdir',
                'command_params'=>['path'=>'string','name'=>'string']
            ),
            'lock'=>array(
                'type'=>'POST',
                'title'=>'Lock',
                'with_oid'=>1,
                'description'=>'The lock feature block a file or directory to avoid filesystem operation conflict. So it should be used before a huge operation on it.',
                'documentation'=>'lock :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'lock',
                'command_params'=>['path'=>'string','timestamp'=>'string']
            ),
            'unlock'=>array(
                'type'=>'POST',
                'title'=>'Unlock',
                'with_oid'=>1,
                'description'=>'Remove the lock',
                'documentation'=>'unlock :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'unlock',
                'command_params'=>['path'=>'string','timestamp'=>'string']
            ),
            'upd_lock'=>array(
                'type'=>'POST',
                'title'=>'Update lock : upd_lock',
                'with_oid'=>1,
                'description'=>'Add a default duration on existing lock ',
                'documentation'=>'upd_lock :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'upd_lock',
                'command_params'=>['path'=>'string']
            ),
            'storage_info'=>array(
                'type'=>'GET',
                'title'=>'Storage Info : storage_info',
                'with_oid'=>1,
                'description'=>'Get all infos about available storages',
                'documentation'=>'storage_info :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'storage_info',
                'command_params'=>['action'=>'string']
            ),
            'get_path_perm'=>array(
                'type'=>'GET',
                'title'=>'Permissions : get_path_perm',
                'with_oid'=>1,
                'description'=>'Get permission on a given path',
                'documentation'=>'get_path_perm :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'get_path_perm',
                'command_params'=>['path'=>'string']
            ),
            'get_time_utc'=>array(
                'type'=>'GET',
                'title'=>'Get time UTC : get_time_utc',
                'with_oid'=>1,
                'description'=>'Permit to get the utc time of the server to compare with local machine and fix good timestamps on uploaded files.',
                'documentation'=>'get_time_utc :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'get_time_utc',
                'command_params'=>[]
            ),
            'module_post'=>array(
                'type'=>'POST',
                'title'=>'Post Module action',
                'with_oid'=>1,
                'description'=>'You can call any module by its name, and use any actions it got, all other params should be provided as a json string in "params" field.<br>
                it will return an html output in which we can observe two things :<br>
                if the return contain a form , the OID is also in it to permit to submit to the API, so you just need to check the action.<br>
                for tags with src attributes, the position of the api will broke the src value with a wrong number of parenting : ../../ so you should change it depending the pressand of the OID value (indicating that the origine of the call was the api)',
                'documentation'=>'module_post :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'module',
                'command_params'=>['module_name'=>'string','action'=>'string','params'=>'json string']
            ),
            'module_get'=>array(
                'type'=>'GET',
                'title'=>'Get Module action',
                'with_oid'=>1,
                'description'=>'You can call any module by its name, and use any actions it got, all other params should be provided as a json string in "params" field.<br>
                it will return an html output in which we can observe two things :<br>
                if the return contain a form , the OID is also in it to permit to submit to the API, so you just need to check the action.<br>
                for tags with src attributes, the position of the api will broke the src value with a wrong number of parenting : ../../ so you should change it depending the pressand of the OID value (indicating that the origine of the call was the api)',
                'documentation'=>'module_get :<br> <br><br>Method : POST<br><br>Return : Json string <br><br>',
                'command'=>'module',
                'command_params'=>['module_name'=>'string','action'=>'string','params'=>'json string']
            )
        ];
    }
    public function show_UI(){
        $html='<!DOCTYPE html><html><head><title>HelPHP API client and documentation</title><meta charset="utf-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $html.='<link href="client.css" rel="stylesheet" type="text/css"></head><body>';
        $html.='<div id="menu">'.$this->command_list().'</div>';
        $html.='<div id="documentation">'.$this->show_documentation().'</div>';
        $html.='<div id="main">'.$this->parse_request().'</div>';
        $html.='</body></html>';
        return $html;
    }
    public function command_list(){
        global $_POST, $_GET;
        if(isset($_POST['command'])){
            $_GET['command']=$_POST['command'];
        }
        if (!isset($_GET['command'])){
            $_GET['command']='Start';
        }
        $output='<ul>';
        $CSSclass='';
        foreach( $this->commands as $name => $command) {
            $CSSclass=($_GET['command']==$name)?' current':'';
            
            $output.='<li class="'.$command['type'].$CSSclass.'"><a href="?command='.$name.'">'.$command['title'].'</a></li>';
        }
        $output.='</ul>';

        return $output;
    }
    public function show_documentation(){
        global $_POST, $_GET;
        if(isset($_POST['command'])){
            $_GET['command']=$_POST['command'];
        }
        if($this->commands[$_GET['command']]){
            $current=$this->commands[$_GET['command']];
        }else{

            $current=$this->commands['Start'];
        }
        $output='<span class="doc_begin">'.$current['documentation'].'</span>';
        if ($current['type'] !='head'){
            $curComPar = $current['command_params'];
            if ($curComPar != ''){
                $output.='<hr>Parameters : <hr>';
                foreach( $curComPar as $name => $paramType){
                    $output.= '<span class="param_name">'.$name.'</span>';   
                    $output.= '<span class="param_type"> - Type : '.$paramType.'</span><br>';
                }
            }
        }
        return $output;
    }
    public function parse_request(){
        global $_POST, $_GET,$APIURL;
        // Utils::error_log($_REQUEST);
        if(isset($_REQUEST['command'])){
            $_GET['command']=$_REQUEST['command'];
        }
        if(isset($_GET['command'])){
            $current=$this->commands[$_GET['command']];
        }else{
            $current=$this->commands['start'];
        }
        $output='<span class="main_title">'.$current['title'].'</span>';

        $output.='<br><span class="main_description">'.$current['description'].'</span>';
        if (isset($current['command']) && $current['type'] !='head'){
            $output.='<form id="formulaire" action="index.php" method="post" enctype="multipart/form-data">';
            $output.='<input type="hidden" name="command" value="'.$_GET['command'].'">';
            $output.='<hr>';
            $route=((isset($current['command']) && $current['command']=='module'))?'module':'command';
            $output.='Current API URL : '.$APIURL.$route;
            // $checked=(isset($_REQUEST['route']) && $_REQUEST['route']=="raw")?'checked':'';
            // $output.='or Raw : <input type="radio" id="raw" name="route" value="raw" '.$checked.' />';
            $output.='<hr>';
            $checked=((isset($_REQUEST['crypted']) && $_REQUEST['crypted']=="nocrypt") || !isset($_REQUEST['crypted']))?'checked':'';
            $output.='Encrypted send : no encryption : <input type="radio" id="crypted" name="crypted" value="nocrypt" '.$checked.' />';
            $checked=(isset($_REQUEST['crypted']) && $_REQUEST['crypted']=="crypted")?'checked':'';
            $output.='or Encrypted : <input type="radio" id="crypted" name="crypted" value="crypted" '.$checked.' />';
            if ($current['with_oid'] == 1){
                $output.='<hr>';
                $OID=(isset($_POST['OID']))?$_POST['OID']:'';
                $output.='<span> OID : </span><input type="text" name="OID" placeholder="Please indicate your OID" value="'.$OID.'">';
                
            }
                
            $output.='<hr>';
            $curComPar = $current['command_params'];
            $output.='<span class="params">Parameters : </span><br>';
            if ($curComPar){
                foreach( $curComPar as $name => $paramType){
                    $Tvalue=(isset($_POST[$name]))?$_POST[$name]:'';
                    if ($name=='params'){
                        $Tvalue=str_replace('\"','&quot;',$Tvalue);
                    }
                    $output.='<span style="display:block;width=100%;text-align:right;"><b>'.$name.'</b> ('.$paramType.') : ';
                    if ($paramType=='file') {
                        $output.='<input name="'.$name.'" type="file" value="'.$Tvalue.'">';
                    }else{
                        $output.='<input type="text" size=90 name="'.$name.'" value="'.$Tvalue.'"><br>';
                    }
                    $output.='</span>';
                }
                
            }else{
                 $output.='<span class="noparam">None</span>';
            }
            $output.='<input type="hidden" name="shouldsend" value="true"><hr><input type="submit" name="ok" value="OK">';
            if (isset($_POST['shouldsend'])){
                GLOBAL $APIURL;
                //cleaning request :
                // Utils::error_log($_REQUEST);
                if ($route=='command'){
                    $datatosend['name']=$_REQUEST['command'];
                }else{
                    $datatosend['module']=$_REQUEST['module_name'];
                }
                $datatosend['action']=(isset($_REQUEST['action']) && $_REQUEST['action']!='')?$_REQUEST['action']:$_REQUEST['command'];
                unset($_REQUEST['command'],$_REQUEST['action'],$_REQUEST['route'],$_REQUEST['ok'],$_REQUEST['shouldsend']);
                $crypt = new Crypt();
                if (isset($_REQUEST['params'])){
                    $tdata=json_decode(stripslashes($_REQUEST['params']),true);
                    $_REQUEST=array_merge($_REQUEST,$tdata);
                }
                $datatosend['param']=($_REQUEST['crypted']=="crypted")?$crypt->encrypt(json_encode($_REQUEST)):json_encode($_REQUEST);

                // Utils::error_log($datatosend);
                $header=['user-agent'=>'helphp'];
                $files=(isset($_FILES))?$_FILES:false;
                $client=new \helPHP\libs\Rest($APIURL,$current['type'],$route,$datatosend,$header,false,true,$files);
                $Return=$client->client_request();
                //~ if ($_GET['command']!='download' && $_GET['command']!='streaming'){
                if ($_GET['command']!='streaming'){
                    $Return=($route=='module')?stripslashes($Return):$Return;
                    $output.='<hr><span>RESULT (prettyfied) :</span><br><textarea cols=90 rows=25 class="result">'.$Return.'</textarea><br>';
                    // $output.='<span>RESULT (RAW !) :</span><br><input type=text class="resultraw" size=90 value=\''.$Return.'\'><br>';
                }else{
                     if ($_POST['inline']=='true'){
                        $output.='<hr><span>RESULT </span><br><iframe width="90%" height="400px" srcdoc="'.$Return.'"></iframe>';
                    }else{
                        //~ $output.='<hr><span>RESULT </span><br><iframe width="90%" height="400px" filename="ApiResult" src="data:application/octet-stream;base64,'.base64_encode($jsonReturn).'"></iframe>';
                        //~ $src = 'embeder.php?filename='.urlencode($jsonReturn['header'][3]).'&mime='.urlencode($jsonReturn['header'][6]).'&data='.urlencode($jsonReturn['data']);
                        //~ UTILS_Class::error_log($src);
                        //~ $output.='<hr><span>RESULT </span><br><iframe width="90%" height="400px" filename="ApiResult" src="'.$src.'"></iframe>';
                    }
                }
            }
            
            $output.='</form>';
        }
        return $output;
    }
    // public function prettyPrint($json){
    //     $result = '';
    //     $level = 0;
    //     $in_quotes = false;
    //     $in_escape = false;
    //     $ends_line_level = NULL;
    //     $json_length = strlen( $json );

    //     for( $i = 0; $i < $json_length; $i++ ) {
    //         $char = $json[$i];
    //         $new_line_level = NULL;
    //         $post = "";
    //         if( $ends_line_level !== NULL ) {
    //             $new_line_level = $ends_line_level;
    //             $ends_line_level = NULL;
    //         }
    //         if ( $in_escape ) {
    //             $in_escape = false;
    //         } else if( $char === '"' ) {
    //             $in_quotes = !$in_quotes;
    //         } else if( ! $in_quotes ) {
    //             switch( $char ) {
    //                 case '}': case ']':
    //                     $level--;
    //                     $ends_line_level = NULL;
    //                     $new_line_level = $level;
    //                     break;

    //                 case '{': case '[':
    //                     $level++;
    //                 case ',':
    //                     $ends_line_level = $level;
    //                     break;

    //                 case ':':
    //                     $post = " ";
    //                     break;

    //                 case " ": case "\t": case "\n": case "\r":
    //                     $char = "";
    //                     $ends_line_level = $new_line_level;
    //                     $new_line_level = NULL;
    //                     break;
    //             }
    //         } else if ( $char === '\\' ) {
    //             $in_escape = true;
    //         }
    //         if( $new_line_level !== NULL ) {
    //             $result .= "\n".str_repeat( "\t", $new_line_level );
    //         }
    //         $result .= $char.$post;
    //     }

    //     return stripslashes($result);
    // }
}

?>