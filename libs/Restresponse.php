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

use helPHP\libs\Ajax;
use helPHP\libs\Crypt;
use helPHP\libs\Media;
use helPHP\libs\User;
use helPHP\libs\Utils;

/**
 * @class Restresponse 
 * 
 * REST response handler for the REST server.
 * This class manages REST API requests, including module/action routing, security checks,
 * OID (Origin Identifier) management, and filesystem/network operations.
 * 
 * It automates module action calls, handles authentication, and provides utility methods
 * for error handling and response formatting.
 *
 * The main security measure is the requirement to be connected (OID/token).
 * The OID is used to maintain the connection and authenticate the unique user.
 * 
 * It's working behind the api/index.php in your instance and with the help of 
 * libs/externals/Restserver.php as REST micro server.
 * 
 * @see api/index.php for instance usage.
 * @see tests/rest/ in your instance to find an api client for testing
 * 
 */
Class Restresponse{
   
     /**
     * @var string $module The module name to be called.
     */
    private $module;

    /**
     * @var string $action The action that the module should perform.
     */
    private $action;

    /**
     * @var array $params The array of variables received (raw, possibly encrypted).
     */
    private $params;

    /**
     * @var array $data Decoded data (usually from JSON).
     */
    public $data;

    /**
     * @var bool $no_crypt Whether to decode data (false = decode, true = raw).
     */
    private $no_crypt;

    /**
     * @var mixed $id Unique id of the object to be manipulated by module/action.
     */
    private $id;

    /**
     * @var string|false $oid Communication token (Origin Identity).
     */
    public $oid;

    /**
     * @var string $format Response format.
     */
    public $format;

    /**
     * @var array $command_list List of hardcoded commands not requiring OID.
     */
    private $command_list=array(
        'connection',
    );

    /**
     * @var array $command_list_with_oid List of hardcoded commands requiring OID.
     */
    private $command_list_with_oid=array(   
        'upload_new',
        'upload_chunk',
        'upload_end',
        'download',
        'ls',
        'recurse_ls',
        'move',
        'remove',
        'copy',
        'mkdir',
        'lock',
        'unlock',
        'upd_lock',
        'storage_info',
        'get_path_perm',
        'get_time_utc',

    );
     /**
     *Decodes and checks request data, check the user agent and ip and route the command.
     *
     * @param string $module   The module name.
     * @param string $action   The action to perform.
     * @param mixed  $params   The parameters (raw or JSON/encrypted).
     * @param bool   $no_crypt If true, do not decrypt data.
     */
    public function __construct($module,$action,$params,$no_crypt=false){
        $this->module=$module;
        $this->action=$action;
        $this->params=$params;
        $this->no_crypt=$no_crypt;
        $this->check_request_data();
        $this->check_ua_and_ip();
        $this->command_route();        
    }

    /**
     * Checks and decodes the request data, 
     * 
     * sets $this->data, $this->oid, and $this->id.
     *
     * @throws \Exception If required parameters are missing.
     * @return void
     */
    public function check_request_data(){
        if (!($this->params) || $this->params==''){
            throw new \Exception('Incomplete request, missing params', 400);
        }
        if(!($this->module) || $this->module==''){
            throw new \Exception('Incomplete request, missing module', 400);
        }
        if(!($this->action) || $this->action==''){
            throw new \Exception('Incomplete request, missing action', 400);
        }
        if ($this->no_crypt){
            $_POST=json_decode(stripslashes($this->params),true);
        }else{
            $crypt=new Crypt();
            $_POST=json_decode($crypt->decrypt($this->params),true);
        }
        $this->oid=isset($_POST['OID'])?$_POST['OID']:false;
        $this->id=isset($_POST['id'])?$_POST['id']:false;
        $this->data=$_POST;
        
    }

     /**
     * Make sure that user agent and client IP are correctly set in $this->data and $_SERVER.
     * Helps maintain connection in difficult network configurations.
     *
     * @return void
     */
    public function check_ua_and_ip(){
        //fixing user agent and ip detection depending the configuration to help maintening connection thru difficult configuration.
        if (!isset($this->data['lastua'])) {
            $this->data['lastua']= isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            // to identify the connection
            if (!isset($_SERVER["HTTP_USER_AGENT"])) {
                $this->data['lastua']=isset($_SERVER['UNIQUE_ID']) ? str_replace('"','',$_SERVER["UNIQUE_ID"]) : '';
            } else {
                $this->data['lastua']=str_replace('"','',$_SERVER["HTTP_USER_AGENT"]);
            }
        }
        if ( (!isset($_SERVER['HTTP_USER_AGENT']) || $_SERVER['HTTP_USER_AGENT'] == '' ) && (isset($this->data['lastua']) && $this->data['lastua']!='' ) ){
            $_SERVER['HTTP_USER_AGENT']=$this->data['lastua'];
        }
        if (!isset($this->data['CLIENT_IP'])) {
            $this->data['CLIENT_IP']=isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'none'));
        }
    }

    /**
     * Routes the command to the appropriate handler:
     * - Hardcoded commands without OID
     * - Hardcoded commands with OID
     * - Module action (default)
     *
     * @throws \Exception 400 If OID is missing or command/module is unknown.
     * @return void
     */
    public function command_route(){

        //we should check it it's a command hard_coded with no oid:
        if (in_array($this->module,$this->command_list)){
            return $this->rest_commands();
        }
        //now for the two last possibilities, we should have to check the OID
        //OID = Origin IDentity, permit to association, ip, UA and a token, transmitted by the rest client when cors or environment are blocking automatic retrieval
        if (!$this->oid){
            throw new \Exception('missing Oid',400);
        }
        $this->oid = $this->checkOid($this->oid);
        //checking if it's an hardcoded command with OID: 
        if (in_array($this->module,$this->command_list_with_oid)){
            return $this->rest_commands_with_oid();
        }
        global $CONFIG;
        $module_path=$CONFIG::HELPHP_FOLDER.'modules/'.strtolower($this->module).'/public/'.ucfirst($this->module).'.php';
        if (is_file($module_path)){
            include_once($module_path);
            $mod="helPHP\modules\deco\public\\";
            $mod.=ucfirst($this->module);
            $module= new $mod;
            $module->process_data($_POST);
            $this->data=$module->get_output();
            
        }else{
            throw new \Exception('Unkown command',400);
        }
        
    }
     /**
     * Handles hardcoded REST commands that do not require OID (e.g., connection).
     *
     * @return Array The response data.
     */
    public function rest_commands(){
        //there we can add some harcoded command that are not tracked by an OID so without user/session tracking.
        switch ($this->module){
            case 'connection':
                global $USER,$CONFIG;
                // prepare data for user class
                $params = [];
                $params['user_action'] = 'connect';
                $oktoconnect=true;
                if (!isset($_POST['login']) || $_POST['login'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing login';
                    $oktoconnect=false;
                }
                if (!isset($_POST['password']) || $_POST['password'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing password';
                    $oktoconnect=false;
                }
                if ($oktoconnect){ 
                    $params['login'] = $_POST['login'];
                    $params['password'] = $_POST['password'];
                    $USER->check_connection_data($params);
                    if ($USER->connection_state == 1){
                        // connected
                        $resp['status'] = 200;
                        $resp['message'] = 'You are connected';
                        // generate Oid
                        $resp['OID'] = $this->generateOid();
                        $resp['api_url'] = $CONFIG::BASE_URL.'api/';
                    } else {
                        $resp['nb_attempt'] = $CONFIG::MAX_USER_CONNECTION_ATTEMPS - $USER->nb_attempt;
                        if (in_array('bloquer', $USER->error_list)){
                            $resp['status'] = 400;
                            $resp['message'] = 'Access blocked by owner';
                        } else if (in_array('bad_password', $USER->error_list)){
                            $resp['status'] = 400;
                            $resp['message'] = 'Error with your password';
                        } else if (in_array('account_banned', $USER->error_list)){
                            $resp['status'] = 400;
                            $resp['message'] = 'Account temporarily banned';
                        } else {
                            $resp['status'] = 400;
                            $resp['message'] = 'tell dev';
                        }
                    }
                }
            break;
          
            default:
            break;
        }
        return $this->return_response($resp);
    }
    /**
     * Handles hardcoded REST commands that require OID. 
     * (so you should call "connection" to get first OID before using them)
     * 
     * Each case checks the required parameters, performs the requested file or system operation 
     * (such as upload, download, move, copy, delete, lock, etc.), and returns a response array
     * with an HTTP status and message. 
     *      
     * These commands are designed for secure, authenticated REST API file management
     * and are executed by the Filesystem ($FS) class.
     * 
     * As they are executed by REST, it's not possible to follow process on very long operation.
     * So it's better to make multiple small operations.
     * 
     * The upd_lock permit also a form of follow, because if it answer that there is no lock on the path, there is no operation on the path.
     * So if one of your process launch a lock/command/unlock, another one can check if it's finished with upd_lock.
     * 
     * NOTE ! At the end of the command, a new OID is returned and should be used for next command !
     * 
     * - commands:
     *      - upload_new : Starts a new chunked file upload. Checks for required parameters (destination, filename, size), available disk space, and file existence. Generates an operation ID for the upload session.
     *      - upload_chunk : Receives a file chunk for an ongoing upload. Checks the operation ID and chunk number, then saves the chunk to a temporary location.
     *      - upload_end : Finalizes the chunked upload. Verifies all chunks are present, checks file size, merges chunks, moves the file to its final destination, and updates the modification date.
     *      - download : Handles file download requests. Checks the file path and permissions, then streams the file to the client.
     *      - ls : Lists the contents of a directory (non-recursive). Checks the path and returns the list of files and folders.
     *      - recurse_ls : Recursively lists the contents of a directory and its subdirectories.
     *      - storage_info : Returns information about disk space: total, used, percentage used, and remaining space.
     *      - move : Moves a file or directory from one location to another. Checks source and destination paths.
     *      - remove : Deletes a file or directory. Checks the path and performs the deletion.
     *      - copy : Copies a file or directory to a new location. Checks source and destination paths and available disk space.
     *      - mkdir : Creates a new directory at the specified path.
     *      - get_path_perm : Returns the permissions for a given path.
     *      - lock : Places a lock on a file or directory to prevent concurrent access.
     *      - unlock : Removes a lock from a file or directory.
     *      - upd_lock : Updates the lifetime of an existing lock.
     *      - get_time_utc : Returns the current server UTC timestamp in milliseconds.
     *
     * @return Array The response data with http "status" and a new OID !
     */
    public function rest_commands_with_oid(){
        //all harcoded commands not depending a module 
        switch ($this->module){
            // UPLOAD
            case 'upload_new':
                if(!isset($_POST['destination']) || $_POST['destination'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing destination';
                    break;
                }
                if (!isset($_POST['filename']) || $_POST['filename'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing filename';
                    break;
                }
                if (!isset($_POST['size']) || $_POST['size'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing size';
                    break;
                }
                
                global $USER,$DB,$FS;

                //check id db exist
                $db_upload = $DB->table('upload_process');
                if (!$DB->table_exists($db_upload)){
                    $json_db = ['table'=>$db_upload, 'fields'=>[
                        'id'=>                  ['type'=>'bigint', 'primary'=>true],
                        'id_users_data'=>       ['type'=>'int', 'size'=>11, 'default'=>0],
                        'token'=>               ['type'=>'varchar', 'size'=>255, 'default'=>''],
                        'destination'=>         ['type'=>'varchar', 'size'=>500, 'default'=>''],
                        'filename'=>            ['type'=>'varchar', 'size'=>500, 'default'=>''],
                        'size'=>                ['type'=>'int', 'size'=>11, 'default'=>0],
                        'status'=>              ['type'=>'int', 'size'=>11, 'default'=>0],
                        'date'=>                ['type'=>'timestamp', 'default'=>'current_timestamp'],
                    ]];
                    $DB->query($DB->create_table_from_json(json_encode($json_db)));
                }
        
                $spaceInfos = $FS->home_du();
                if ($spaceInfos[3] < $_POST['size']){
                    $this->missing_free_space($resp);
                    break;
                }
                
                $_POST['destination'] = trim($_POST['destination'],'/').'/';
                $overwrite = (isset($_POST['overwrite']) && $_POST['overwrite']) ? true : false;
                
                $file_name = $FS->get_file_name($_POST['filename']);
                $realPath = $FS->check_path($FS->root_fs.$_POST['destination'].$file_name, false, 'write',true);
                if (!$realPath){
                    $this->error_on_file($resp);
                    break;
                }
                
                if (file_exists($realPath)){
                    if ($overwrite){
                        $FS->save_history = false;
                        $FS->delete_with_follow($realPath,false,true);
                        $FS->save_history = true;
                        usleep(100);
                    } else {
                        $resp['status'] = 400;
                        $resp['message'] = 'File already exist and no overwrite flag passed';
                        break;
                    }
                }
                
                // generate an id to identify the upload
                $operation_id = bin2hex(random_bytes(20));
                $q = 'INSERT INTO '.$DB->table('upload_process').' SET token=?, destination=?, filename=?, size=?';
                $res = $DB->prepared_query($q,'sssi',[$operation_id,$_POST['destination'],$_POST['filename'],$_POST['size']]);
                if ($res){
                    $resp['status'] = 200;
                    $resp['operation_id'] = $operation_id;
                    // add lock
                    $FS->add_lock($realPath, $operation_id, false);
                } else {
                    $resp['status'] = 400;
                    $resp['message'] = 'Internal error';
                }
                
            break;
            case 'upload_chunk':

                if(!isset($_POST['operation_id']) || $_POST['operation_id'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing operation id';
                    break;
                }
                if (!isset($_POST['number']) || $_POST['number'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing chunk number';
                    break;
                }
                    
                global $DB,$FS;
                // check the operation id
                $q = 'SELECT filename FROM '.$DB->table('upload_process').' WHERE token=?';
                $name = $DB->prepared_query_value($q,'s',[$_POST['operation_id']]);
                if (!$name){
                    $resp['status'] = 400;
                    $resp['message'] = 'Operation id not found';
                    break;
                }
                
                $name .= $_POST['operation_id'];
                $index = $_POST['number'];
                
                // if not exist, create a temporary folder to save the chunks
                //~ $destinationDir = 'temp/'.$name.'/';
                $destinationDir = 'temp/chunk_'.$name;
                $chunk = $index;
                $file = Ajax::process_files($destinationDir,null,$chunk);
                // Utils::error_log($file);
                if ($file){
                    $resp['status'] = 200;
                    $resp['message'] = 'Chunk received';
                } else {
                    $resp['status'] = 400;
                    $resp['message'] = 'Can\'t process the chunk';
                }
            break;

            case 'upload_end':

                if(!isset($_POST['operation_id']) || $_POST['operation_id'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing operation id';
                    break;
                }
                if (!isset($_POST['total']) || $_POST['total'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing total';
                    break;
                }
                if (!isset($_POST['timestamp']) || $_POST['timestamp'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing timestamp';
                    break;
                }
                
                global $DB, $FS,$CONFIG;
                // check the operation id
                $q = 'SELECT id, filename, destination, size FROM '.$DB->table('upload_process').' WHERE token=?';
                $file = $DB->prepared_query_line($q,'s',[$_POST['operation_id']]);
                if (!$file){
                    $resp['status'] = 400;
                    $resp['message'] = 'Operation id not found';
                    break;
                }
        
                $spaceInfos = $FS->home_du();
                if ($spaceInfos[3] < $file['size']){
                    $this->missing_free_space($resp);
                    break;
                }
                
                $name = $_POST['operation_id'];
                $total = $_POST['total'];
                
                $destinationDir = $FS->check_path($FS->root_fs.trim($file['destination'],'/',).'/', true, 'write',true);
                if (!$destinationDir){
                    $this->error_on_file($resp);
                    break;
                }
                
                $res = Ajax::merge_chunks($file['filename'], $total, $_POST['operation_id']);
                if ($res != 'ok'){
                    $resp['status'] = 400;
                    $resp['message'] = 'Some chunks are missing';
                    $resp['data'] = $res;
                    break;
                }
                
                $tempFolder = $CONFIG::HOME_FOLDER.'temp/';
                $tempFile = $file['filename'].'!h!e!l!P!H!P!'.$_POST['operation_id'];
                if (filesize($tempFolder.$tempFile) != $file['size']) {
                    $resp['status'] = 400;
                    $resp['message'] = 'The size of the file you sent at the start of the upload process doesn\'t correspond to the chunks size';
                    break;
                }
                
                if (!is_dir($destinationDir)){
                    if (!$FS->mkdir($FS->root_fs.trim($file['destination'],'/',).'/')) {
                        Utils::error_log('ERROR while creating folder '.$destinationDir);
                    }
                }
                
                $destinationDir = rtrim($destinationDir,'/').'/';
                Ajax::move_from_temp($destinationDir, [$tempFile]);
                
                $FS->delete_lock($destinationDir.$file['filename'], $_POST['operation_id'], false, false);
                
                $resp['status'] = 200;
                $resp['message'] = 'upload successful';
                
                $milis = substr($_POST['timestamp'], -3);
                $timestamp = substr($_POST['timestamp'], 0, -3);
                
                $date = date('Y-m-d H:i:s', intval($timestamp));
                $date .= '.'.(intval($milis) * 1000);
                
                $cmd = 'touch -m -d "'.$date.'" "'.$destinationDir.$file['filename'].'"';
                exec($cmd);
            break;

            case 'download':
                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    ob_end_clean();
                    session_write_close();
                    ob_start();
                    ob_end_flush();
                    header('HTTP/1.1 400 Bad Request');
                    echo '';
                    exit;
                }
                
                global $FS;
                
                $path = $FS->check_path($FS->root_fs.ltrim($_POST['path'],'/'), true, 'read');
                if (!$path){
                    ob_end_clean();
                    session_write_close();
                    ob_start();
                    ob_end_flush();
                    if ($FS->last_error == 'bad_path'){
                        header('HTTP/1.1 404 Not Found');
                    }
                    if ($FS->last_error == 'bad_symlink'){
                        header('HTTP/1.1 401 Unauthorized');
                    }
                    if ($FS->last_error == 'no_permission'){
                        header('HTTP/1.1 401 Unauthorized');
                    }
                    if ($FS->last_error == 'locked'){
                        header('HTTP/1.1 401 Unauthorized');
                    }
                    echo '';
                    exit;
                }
                
                ob_end_clean();
                $res = Media::send_file($path,false,true);
                exit;
            break;
            
            case 'ls':
            case 'recurse_ls':
                global $FS;
                // check path
                $path =  $FS->root_fs.ltrim($_POST['path'],'/');
                $absolutPath = $FS->check_path($path, true, 'read');
                if (!$absolutPath){
                    $this->error_on_file($resp);
                    break;
                }
                if (!file_exists($absolutPath)){
                    $resp['status'] = 400;
                    $resp['message'] = 'Path not found';
                    break;
                }
        
                if ($this->module == 'recurse_ls'){
                    $data = $FS->shell_ls($path, '', true, true,true, true, false, true);
                } else {
                    $data = $FS->shell_ls($path, '', true, true,true, false, false, true);
                }
                
                $resp['data'] = $data;
                $resp['status']=200;
            break;
               
            case 'storage_info':

        
                global $FS;
                $sizes = $FS->home_du();
                if ($sizes){
                    $resp['status'] = 200;
                    $resp['data'] = [
                        'total'=>$sizes[0],
                        'occupied'=>$sizes[1],
                        'occupied_p'=>$sizes[2],
                        'remaining'=>$sizes[3]
                    ];
                } else {
                    $resp['status'] = 400;
                    $resp['message'] = 'Internal error';
                }
            break;
            
            case 'move':

                if (!isset($_POST['destination']) || $_POST['destination'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing destination';
                    break;
                }
                if (!isset($_POST['origin']) || $_POST['origin'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing origin';
                    break;
                }
                
                global $FS;
                $origin = $FS->root_fs.ltrim($_POST['origin'], '/');
                $destination = $FS->root_fs.ltrim($_POST['destination'], '/');
                
                $origin_name = $FS->get_file_name($origin);
                $destination_name = $FS->get_file_name($destination);
                $destination = $FS->get_file_path($destination);
                if ($origin_name != $destination_name) {
                    $origin = [['name'=>$destination_name, 'path'=>$origin]];
                }
        
                $success = $FS->move($origin,$destination,false,true);
                if (!$success){
                    $this->error_on_file($resp);
                    break;
                }
                
                usleep(100);
                $resp['status'] = 200;
            break;
            
            case 'remove':

                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing path';
                    break;
                }
                global $FS;
                // check path
                $path = $FS->root_fs .ltrim($_POST['path'], '/');
                $success = $FS->delete_with_follow($path,false,true);
                if (!$success){
                    $this->error_on_file($resp);
                    break;
                }
        
                usleep(100);
                $resp['code']=0;
            break;
            
            case 'copy':

                if (!isset($_POST['destination']) || $_POST['destination'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing destination';
                    break;
                }
                if (!isset($_POST['origin']) || $_POST['origin'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing origin';
                    break;
                }
                global $FS;
                
                // need to protect all path from beginning with a /
                $origin = $FS->root_fs . ltrim($_POST['origin'], '/');
                $destination = $FS->root_fs . ltrim($_POST['destination'], '/');
                
                $origin_name = $FS->get_file_name($origin);
                $destination_name = $FS->get_file_name($destination);
                $destination = $FS->get_file_path($destination);
                if ($origin_name != $destination_name) {
                    $origin = [['name'=>$destination_name, 'path'=>$origin]];
                }
                
                $success = $FS->copy($origin,$destination);
                shell_exec('chmod 2774 "'.$destination.'"');
                if ($success == 'missing_free_space'){
                    $this->missing_free_space($resp);
                    break;
                }
                if (!$success){
                    $this->error_on_file($resp);
                    break;
                }
                
                usleep(100);
                $resp['code']=0;
            break;
            
            case 'mkdir':

                if (!isset($_POST['name']) || $_POST['name'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing name';
                    break;
                }
                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing path';
                    break;
                }
                global $FS;
                // need to protect all path from beginning with a /
                $realPath = $FS->root_fs . trim($_POST['path'],'/').'/';
                $realPath.= $FS->get_file_name($_POST['name']);
                
                if (file_exists($realPath)){
                    $resp['message'] = 'path exist';
                    $resp['status'] = 200;
                    break;
                }
                
                $res_fs = $FS->mkdir($realPath);
                if (!$res_fs){
                    $this->error_on_file($resp);
                    break;
                }
                usleep(100);
                $resp['status'] = 200;
            break;

            case 'get_path_perm':
                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing path';
                    break;
                }
                
                global $FS;
                
                $path = $FS->root_fs . trim($_POST['path'], '/');
                
                $path_data=$FS->check_path($path, true);
                if (!$path_data){
                    $this->error_on_file($resp);
                    break;
                }
                
                if (isset($path_data['path'])){
                    unset($path_data['path']);
                }
                $resp['status'] = 200;
                $resp['data'] = $path_data;
            break;

            case 'lock':
                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing path';
                    break;
                }
                if (!isset($_POST['timestamp']) || $_POST['timestamp'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing timestamp';
                    break;
                }
                
                global $FS;
                
                $path = $FS->root_fs . trim($_POST['path'], '/');
                
                $path = $FS->check_path($path, true);
                if (!$path){
                    $this->error_on_file($resp);
                    break;
                }
                
                $path = $path['path'];
                if ($FS->check_lock($path)){
                    $resp['status'] = 200;
                    $resp['message'] = 'Already locked';
                } else {
                    $FS->add_lock($path, $_POST['timestamp']);
                    $resp['status'] = 200;
                    $resp['message'] = 'Locked';
                }
            break;

            case 'unlock':
                
                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing path';
                    break;
                }
                if (!isset($_POST['timestamp']) || $_POST['timestamp'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing timestamp';
                    break;
                }
                
                global $FS;
                
                $path = $FS->root_fs . trim($_POST['path'], '/');
                
                $path = $FS->check_path($path, true);
                if (!$path){
                    $this->error_on_file($resp);
                    break;
                }
                
                $path = $path['path'];
                if (!$FS->check_lock($path)){
                    $resp['status'] = 200;
                    $resp['message'] = 'Already unlocked';
                } else {
                    $FS->delete_lock($path, $_POST['timestamp']);
                    $resp['status'] = 200;
                    $resp['message'] = 'Unlocked';
                }
            break;

            case 'upd_lock':

                if (!isset($_POST['path']) || $_POST['path'] == ''){
                    $resp['status'] = 400;
                    $resp['message'] = 'Missing path';
                    break;
                }
                
                global $FS;
                
                $path = $FS->root_fs . trim($_POST['path'], '/');
                
                $path = $FS->check_path($path, true);
                if (!$path){
                    $this->error_on_file($resp);
                    break;
                }
                
                $path = $path['path'];
                if (!$FS->check_lock($path)){
                    $resp['status'] = 400;
                    $resp['message'] = 'No lock on path';
                } else {
                    $FS->update_lock($path);
                    $resp['status'] = 200;
                    $resp['message'] = 'lock updated';
                }
            break;
            case 'get_time_utc':

                $resp['status'] = 200;
                $resp['timestamp'] = round(microtime(true)*1000);
            break;
            default:
            break;
        
        }
        return $this->return_response($resp);
    }
    /**
     * Formats and sets the response data, encrypting if needed.
     * Throws an exception if status > 200.
     *
     * @param array $resp The response array.
     * @throws \Exception If status > 200.
     * @return void
     */
    public function return_response($resp){
        if ($resp['status']>200){
            throw new \Exception($resp['message'], $resp['status']);
        }else{
            if ($this->oid && !isset($resp['oid'])){
                $resp['OID']=$this->oid;
            }
            if ($this->no_crypt){
                $this->data=json_encode($resp,JSON_UNESCAPED_UNICODE);
            }else{
                // there is a limit on string length we pass to encrypt
                // for avoiding it check the length of json with slashes because cpp_encrypt will add them automaticly
                // remove them before the call to encrypt
                $crypt=new Crypt();
                $json = addslashes(json_encode($resp,JSON_UNESCAPED_UNICODE));
                $len = strlen($json);
                $limit = 100000;
                if ($len > $limit){
                    $response_array = [];
                    $splits = str_split($json, $limit);
                    foreach($splits as $str){
                        array_push($response_array, $crypt->Encrypt(stripslashes($str)));
                    }
                    $this->data=json_encode($response_array);
                } else {
                    $this->data=$crypt->Encrypt(json_encode($resp,JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }
     /**
     * Generates a new OID (Origin Identifier) token or updates an existing one.
     * Stores the token in the database and returns it.
     *
     * @param array|false $previous Previous token data, or false to generate new.
     * 
     * @return string|false The OID token, or false on error.
     */
    public function generateOid($previous=false){
        global $DB,$USER;
        
        // if we have to update a token, the user agent and the ip have been checked and they are corresponding to the previous one.
        // we should never update a user agent or an ip because if they change it means that the client need to connect again.
        if (!$previous){
            // generate token
            $tk=bin2hex(random_bytes(20));
            // add it to the db
            $q = 'INSERT INTO '.$DB->table('users_token').' SET id_users_data=?, token=?, lastua=?, lastua_hash=?, client_ip=?';
            $res = $DB->prepared_query($q,'issss',[$USER->id,$tk,$this->data['lastua'],md5($this->data['lastua']),$this->data['CLIENT_IP']]);
        } else {
            $diff = time() - $previous['time'];
            if ($diff > 60*5) { // 5 min
                // oid older than 5 min we must change it
                // generate token
                $tk=bin2hex(random_bytes(20));
                $q = 'UPDATE '.$DB->table('users_token').' SET token=?, created_time=CURRENT_TIMESTAMP WHERE id=?';
                $res = $DB->prepared_query($q,'si',[$tk,$previous['id']]);
            } else {
                if ($diff> 60*1){ // 1 min
                    // oid recent, just updating its creation time
                    $q = 'UPDATE '.$DB->table('users_token').' SET created_time=CURRENT_TIMESTAMP WHERE id=?';
                    $res = $DB->prepared_query($q,'i',[$previous['id']]);
                } else{
                    $res = 1;
                }
                $tk = $previous['token'];
            }
        }
        
        if ($res){
            $this->oid=$tk;
            return $tk;
        } else {
            Utils::error_log('error when trying to set the token in db');
            return false;
        }
    }

    /**
     * Checks and renews the OID, restoring the connection if valid.
     * Loads user data to update the token if needed.
     *
     * @param string $oid The OID token to check.
     * 
     * @throws \Exception If OID is unknown or invalid.
     * 
     * @return string|false The (possibly renewed) OID token, or false on error.
     */
    public function checkOid($oid)
    {
        global $DB,$USER,$CONFIG;
        
        // delay after a token is removed
        $delaypurge=intval($CONFIG::TOKEN_MINUTE)*60;
        // clean db from old token
        $q = 'DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('users_token').' WHERE (unix_timestamp(created_time)+'.$delaypurge.') < unix_timestamp(now())';
        $DB->query($q);
        
        // if there is no user agent or ip, end the request
        if (isset($this->data['lastua']) && $this->data['lastua']!='' && isset($this->data['CLIENT_IP']) && $this->data['CLIENT_IP']!=''){
            // check if the token correspond with one in db
            $q = 'SELECT id, unix_timestamp(created_time) as time, token, id_users_data FROM '.$DB->table('users_token').' where token=? AND lastua_hash=? AND client_ip=?';
            $exist=$DB->prepared_query_line($q,'sss',[trim($oid),md5(trim($this->data['lastua'])),trim($this->data['CLIENT_IP'])]);
            if ($exist){
                $q = 'SELECT * FROM '.$DB->table('users_data').' WHERE id='.$exist['id_users_data'];
                $user_data = $DB->query_line($q);                
                $USER->user_data = $user_data;
                $USER->id = $user_data['id'];
                $USER->login = $user_data['login'];
                $USER->admin = $user_data['admin']>0;
                $USER->connection_state = User::state_logged;           
                // generate and send a new token
                $tk = $this->generateOid($exist);
                return $tk;
            }else{
                throw new \Exception('Unkown OID', 400);
            }
        } else {
            Utils::error_log('missing parameters in post');
        }

        return false;
    }
    /**
     * Sets an error message in the response array based on the last filesystem error.
     *
     * @param array &$return The response array (by reference).
     * 
     * @return string in $return['message'] (the error message)
     */
    public function error_on_file(&$return){
        global $FS;
        
        $return['status'] = 400;
        
        // Utils::error_log($FS->last_errors);
        if (in_array('bad_path',$FS->last_errors)){
            $return['message'] = 'Incorrect path';
        }
        if (in_array('bad_symlink',$FS->last_errors)){
            $return['message'] = 'Incorrect path';
        }
        if (in_array('no_permission',$FS->last_errors)){
            switch ($FS->last_checked_type){
                case 'read':
                    $return['message'] = 'Missing read permission';
                break;
                case 'write':
                    $return['message'] = 'Missing write permission';
                break;
                case 'delete':
                    $return['message'] = 'Missing delete permission';
                break;
                case 'new_folder':
                    $return['message'] = 'Missing folder creation permission';
                break;
                case 'move':
                    $return['message'] = 'Missing move permission';
                break;
                default:
                    $return['message'] = 'Missing permission';
                break;
            }
        }
        if (in_array('locked',$FS->last_errors)){
            $return['message'] = 'Path is locked';
        }
    }
    /**
     * Sets a "missing free space" error message in the response array.
     *
     * @param array &$return The response array (by reference).
     * 
     * @return string in $return['message'] (the error message)
     * @return int in $return['status'] (400)
     */
    public function missing_free_space(&$return){
        $return['status'] = 400;
        $return['message'] = 'Your remaining space is too small';
    }
}