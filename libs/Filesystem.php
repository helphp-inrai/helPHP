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

/**
 * @class Filesystem
 * 
 * Our Filesystem class is there to offer two things :
 * 
 * - first, an universal solution working with any kind of mounted storage (local or distant) that choose the best between native
 * PHP features and linux native OS commande to perform a secured operation without headaches.
 * 
 * - Secondary : the possibiliy to follow the filesystem process on huge ones (long multiple copy etc), with a sql or nosql server depending 
 * your configuration.
 * 
 * Of course it can be a real good backend base for a cloud service with user/group rights verification etc, and you'll find in the instance API, all you need
 * to pilot this class with Rest queries and in the instance test/rest folder, an api client with all Fs commands ready to try.
 * This client is calling Restresponse Class, this class is full of exemple related to the FS.
 * 
 * Init.php call its create instance method, so the global  $FS is available everywhere in any HelPHP componant/modules.
 * 
 * one important thing is the root_fs : in the config of the instance, is set its value, indicating where all operations should happened. 
 * the FS will refuse to do any operation outside this root_fs, so it's the root of your users virtual storage.
 * then there is a user / group rights to consider to authorise  each operation.
 * 
 * Manage also images/files sequences with file names formated like this : basename[xxxx-yyyy].ext
 * 
 * @package helPHP\libs
 */
class Filesystem
{
    protected $redis = false;

    public $root_fs = null;
    public $lock_time = null; // in second
    public $file_is_locked = false;
    
    public $skip_check_path = true;
    public $skip_lock = false;
    
    public $save_history = false;
    public $with_permission = false;

    public $follow_redis = false;
    
    // for file or folder name
    public $last_checked_type = '';
    public $last_errors = [];
    public $last_error = false;
    public $err_lst = [];
    public $bad_name = false;

    public $unauthorized_character = ['/','\\','<','>','*','?','"',':','|', '¤'];
    public $unauthorized_character_string = '/\\<>*?":|¤';

    protected $db_lock, $db_history;
    
    public function __construct($root_fs=false)
    {
        global $CONFIG,$DB;
        $this->root_fs = ($root_fs) ? $root_fs : $CONFIG::ROOT_FS;
        $this->lock_time = ($CONFIG::TOKEN_MINUTE * 60) / 10; // 1 min
        
        if ($CONFIG::REDIS) {
            $this->redis = true;
        }else{
            if ($CONFIG::DEVMODE) {
                $this->init_db();
            }else{
                $this->db_lock = $DB->table('filesystem_lock');
                $this->db_history = $DB->table('filesystem_history');
            }
            $this->redis = false;
        }
    }

    /**
     * Check and create table needed for the filesystem to work when redis isn't active.
     * Necessary if you want to keep a filesystem_history 
     */
    public function init_db(){
        global $DB;

        $this->db_lock = $DB->table('filesystem_lock');
        if (!$DB->table_exists($this->db_lock)){
            $json_db = ['table'=>$this->db_lock, 'fields'=>[
                'id'=>          ['type'=>'bigint', 'primary'=>true],
                'lock_key'=>    ['type'=>'text', 'default'=>''],
                'time'=>        ['type'=>'datetime', 'default'=>'current_timestamp']
            ]];
            $DB->query($DB->create_table_from_json(json_encode($json_db)));
        }

        $this->db_history = $DB->table('filesystem_history');
        if (!$DB->table_exists($this->db_history)){
            $json_db = ['table'=>$this->db_history, 'fields'=>[
                'id'=>          ['type'=>'bigint', 'primary'=>true],
                'process_key'=> ['type'=>'varchar', 'default'=>''],
                'user_login'=>  ['type'=>'varchar', 'default'=>''],
                'end_time'=>    ['type'=>'bigint'],
                'action'=>      ['type'=>'enum', 'values'=>['move','delete']],
                'source'=>      ['type'=>'text'],
                'destination'=> ['type'=>'text']
            ]];
            $DB->query($DB->create_table_from_json(json_encode($json_db)));
        }
    }

    /**
     * Check and connect to redis
     */
    public function init_redis(){
        global $redisproc,$CONFIG;
        if ($redisproc == null ) {
            $redisproc = new \Redis();
            $redisproc->connect($CONFIG::REDIS_HOST, $CONFIG::REDIS_PORT);
        }
    }
    
    /**
     * Create an instance in the global $FS
     *
     * @param bool $root_fs the root storage folder, no 
     * @param bool $forceNewInstance
     * 
     * @return Object Filesystem instance
     * 
     */
    public static function create_instance($root_fs = false,$forceNewInstance = false)
    {
        global $FS;
        
        if ($FS != null && $forceNewInstance == false) {
            return $FS;
        }

        $FS = new Filesystem($root_fs);
    }
    
    /**
     * verify if it's an accessible and authorized path
     * 
     * Each detected error is pushed to err_lst array.
     * err_list should be emptied before each call to check_path if you don't want to get previous errors.
     *
     * @param String $path
     * @param Bool $skip_lock=false  true | false, when displaying we don't need to check the redis lock
     * @param String $type type of action for check_permission write | read | delete | move | new_folder
     * If type given, will return the path otherwise will return an array with each permission and the path
     * @param mixed $skip_exist=false
     * 
     * @return Bool true if ok .
     * 
     */
    public function check_path($path,$skip_lock=false,$type='',$skip_exist=false){
        global $CONFIG;
        $this->err_lst = [];
        $this->last_errors = [];
        $this->last_error = false;
        
        $this->last_checked_type = $type;
        
        if ($skip_lock){
            $this->skip_lock = true;
        }
        
        $granted=0; //scoring
        if ($this->skip_check_path){
            return $path;
        }
        
        //examinating base path (must start with same base as root_fs):
        if (!str_starts_with($path,$this->root_fs)){
            $this->last_error = 'bad_path';
            array_push($this->last_errors,'bad_path');
            return false;
        }
        if (!$skip_exist && !is_dir($path) && !is_file($path) && !is_link($path)){
            $this->last_error = 'bad_path';
            array_push($this->last_errors,'bad_path');
            return false;
        }
        if (str_contains($path,'../')) {
            $this->last_error = 'bad_path';
            array_push($this->last_errors,'bad_path');
            return false;
        }
        
        if (!$this->with_permission) $permission = $path;
        else $permission = $this->check_permission($path,$type);
        if ($permission == 'no_permission'){
            $this->last_error = $permission;
            array_push($this->last_errors,$permission);
            return false;
        } else {
            $granted++;
        }
        
        if(is_link(substr($path,0,-1))){ //malicious links/symlinks
            $granted--;
            $linkcont=readlink(substr($path,0,-1));
            if (!str_contains($linkcont,'../')){
                $granted++;
            } else {
                $this->last_error = 'bad_symlink';
                array_push($this->last_errors,'bad_symlink');
                return false;
            }
            if (str_starts_with($linkcont,$this->root_fs)){
                $granted++;
            } else {
                $this->last_error = 'bad_symlink';
                array_push($this->last_errors,'bad_symlink');
                return false;
            }
        }
        
        if (!$this->skip_lock && $this->check_lock($path)){
            $this->last_error = 'locked';
            $this->file_is_locked = true;
            array_push($this->last_errors,'locked');
            return false;
        } else {
            $this->file_is_locked = false;
        }
        return $permission;
    }
    

    /**
     * copy the "sources" to the destination folder.
     * can be simply used like this : $FS->copy($file,$destination);
     * but can take care of a bunch of files
     * 
     * @param Array!String $sources 
     * for a single file, it can be a simple path string.
     * but it can be also an array with for each file :
     * path =>  the file path , name => a new name,  replace => a bool to indicate if we overwrite or not a possibly existing file  
     * @param String $destination
    *  @param bool $no_progress=false to indicate not to go through the system_to_process_to_redis and to stay in the same php execution pipe. 
    *  @param bool|string $key to follow the process identified by this $key

    * in the case of a copy with a file that must change its name on arrival because it conflicts with an already present file 
    * We use two variables $cmd_before and $cmd_after, to save the mv commands needed to rename the copied file 
    * 1) $cmd_before    -> Rename the file in the destination to ¤tokeep¤FILE_NAME (be careful not to forget the linked folders like ¤proxy¤FOLDER_NAME) 
    * 2) $cmd           -> rsync the file with the same name from the origin to the destination 
    * 3) $cmd_after     -> Renames the copied file to its new name 
    * 4) $cmd_after     -> Rename the ¤tokeep¤FILE_NAME file to FILE_NAME
    *
    * at the end , all the cmds are sent as one process to follow
    * 
    * @return Bool true/false to indicate if all cmds have benn sent to process to follow, it's Utils::follow_system_process_redis($key) 
    * (or not with _redis if your prefer an sql follow) that will return correct infos.
    */

    public function copy($sources, $destination, $no_progress=false, $key = false)
    {
        if (!is_array($sources)){
            $sources = [$sources];
        }
        
        $destination = $this->check_path($destination,false,'write');
        if ($destination){
            if (!$no_progress){ // if progress
                $destination = rtrim($destination,'/').'/';
                
                $suc = [];
                $err = [];

                // to compare with remaining space
                $total_size = 0;

                $cmd = [];
                $toLock = [];
                $count = 0;
                
                foreach($sources as $i => $source){
                    $newName = false;
                    $replace = false;
                    $filesize = 0;
                    if (!is_string($source)){
                        if (isset($source['name'])) $newName = $source['name'];
                        if (isset($source['replace'])) $replace = true;
                        $source = $source['path'];
                    }
                    
                    $sequences = $this->get_sequence_data($source,'read');
                    if ($sequences){
                        $source = $sequences['path'];
                    } else {
                        $source = $this->check_path($source,false,'read');
                    }
                    if ($source){
                        array_push($suc, $source);
                        $parentPath = $this->get_file_path($source);
                        $fileName = ($sequences) ? $sequences['basename'] : $this->get_file_name($source);
                        if ($sequences){
                            for($i=$sequences['first'];$i<=$sequences['last'];$i++){
                                $k = $i;
                                $k = str_pad($k,$sequences['digit'], '0', STR_PAD_LEFT);
                                $file = $sequences['basename'].$k.'.'.$sequences['ext'];
                                $filesize = filesize($parentPath.$file);
                                $total_size += $filesize;
                                array_push($toLock, $parentPath.rtrim($file,'/'));
                                if ($newName){
                                    array_push($cmd, '(dd if=\''.$parentPath.$file.'\' bs=16M status=none conv=fsync | pv -n -s '.$filesize.' | dd of=\''.$destination.str_replace($fileName,$newName,$file).'\' bs=16M status=none conv=fsync) 2>&1');
                                } else {
                                    if ($replace && file_exists($destination.$file)) exec('rm \''.$destination.$file.'\'');
                                    array_push($cmd, '(dd if=\''.$parentPath.$file.'\' bs=16M status=none conv=fsync | pv -n -s '.$filesize.' | dd of=\''.$destination.$file.'\' bs=16M status=none conv=fsync) 2>&1');
                                }
                            }
                        } else if (is_file($source)){
                            $filesize = filesize($source);
                            $total_size += $filesize;
                            array_push($toLock, $source);
                            if ($newName){
                                array_push($cmd, '(dd if=\''.$source.'\' bs=16M status=none conv=fsync | pv -n -s '.$filesize.' | dd of=\''.$destination.$newName.'\' bs=16M status=none conv=fsync) 2>&1');
                            } else {
                                if ($replace && file_exists($destination.$fileName)) exec('rm \''.$destination.$fileName.'\'');
                                array_push($cmd, '(dd if=\''.$source.'\' bs=16M status=none conv=fsync | pv -n -s '.$filesize.' | dd of=\''.$destination.$fileName.'\' bs=16M status=none conv=fsync) 2>&1');
                            }
                        } else { // is dir
                            array_push($toLock, $source);
                            if ($newName){
                                $folderData = $this->recurse_copy($source, $destination, $newName);
                                $cmd = array_merge($cmd , $folderData['cmd']);
                                $total_size += $folderData['size'];
                            } else {
                                if ($replace && file_exists($destination.$fileName)) exec('rm -r \''.$destination.$fileName.'\'');
                                $folderData = $this->recurse_copy($source, $destination);
                                $cmd = array_merge($cmd , $folderData['cmd']);
                                $total_size += $folderData['size'];
                            }
                            
                        }
                    }
                }

                $spaceInfos = $this->home_du();
                if ($spaceInfos[3] <= $total_size){ // remaining space smaller, don't copy
                    return 'missing_free_space';
                }

                $cmds = [];
                $args = [];
                $count = count($cmd);
                if ($count > 100){
                    $chunks = array_chunk($cmd, 100);
                    $toLock = array_chunk($toLock, 100);
                } else {
                    $chunks = [$cmd];
                    $toLock = [$toLock];
                }
                foreach($chunks as $i => $chunk){
                    $cmd = implode(' && ', $chunk);
                    array_push($cmds, $cmd);
                    array_push($args, count($chunk));
                    $toLock[$i] = array_merge($toLock[$i], [$destination]);
                }
                $key = $key ? $key : 'copy-'.time();

                if ($this->follow_redis) {
                    Utils::system_process_with_tracking($cmds, $key, 'copy', $args, $toLock);
                } else {
                    Utils::system_process_no_tracking($cmds, $key, 'copy', $args, $toLock);
                }
                

                return ['success'=>$suc, 'error'=>$err];
            } else {
                foreach($sources as $source) {
                    if (copy($source, $destination)) {
                        chmod($destination, fileperms($source));
                        chgrp($destination, filegroup($source));
                        
                        // detect of ¤ linked to target
                        if (is_file($source)){
                            // source and destination path
                            $source_path = $this->get_file_path($source);
                            $destination_path = $this->get_file_path($destination);
                            // source and destination name
                            $source_name = $this->get_file_name($source);
                            $destination_name = $this->get_file_name($destination);
                            
                            $cmd_ls = 'ls ' . $source_path . ' | grep -i '.$this->clean_filter('¤'.$source_name.'¤');
                            $result_str = shell_exec($cmd_ls);
                            if ($result_str != ''){
                                $lst = explode("\n",trim($result_str));
                                $destination_path = $this->get_file_path($destination);
                                $err_copy = false;
                                foreach($lst as $key => $name){
                                    if ($name != ''){
                                        if (copy($source_path.$name, $destination_path.str_replace($source_name,$destination_name,$name))){
                                            chmod($destination_path.$name, fileperms($source_path.$name));
                                            chgrp($destination_path.$name, filegroup($source_path.$name));
                                        } else {
                                            $err_copy = true;
                                            Utils::error_log('COPY ERROR : '.$source_path.$name.' -> '.$destination_path.$name);
                                        }
                                    }
                                }
                                if ($err_copy) return false;
                            }
                        }
                        
                        return true;
                    } else {
                        Utils::error_log('COPY ERROR : '.$source.' -> '.$destination);
                    }
                }
            }
            
            return false;
        }
        Utils::error_log('COPY PATH ERROR : '.json_encode($sources).' -> '.$destination);
        return false;
    }

    /**
     * Sub-function of copy()
     * 
     * @param mixed $path the origine folder
     * @param mixed $destination the destination folder
     * @param mixed $newName='' if it's necessary to rename
     * 
     * @return Array cmd to execute
     * 
     */
    private function recurse_copy($path,$destination,$newName='')
    {
        $cmd = [];
        $totalSize = 0;
        $path = rtrim($path,'/');
        $fileName = $this->get_file_name($path);
        $name = $newName ? $newName : $fileName;
        if (!file_exists($destination.$name)){
            mkdir($destination.$name);
        }
        foreach(scandir($path) as $subnode) {
            if ($subnode=='.' || $subnode=='..') continue;
            if (is_file($path.'/'.$subnode)){
                $filesize = filesize($path.'/'.$subnode);
                $totalSize += $filesize;
                array_push($cmd, '(dd if=\''.$path.'/'.$subnode.'\' bs=16M status=none conv=fsync | pv -n -s '.$filesize.' | dd of=\''.$destination.$name.'/'.$subnode.'\' bs=16M status=none conv=fsync) 2>&1');
            } else {
                $folderData = $this->recurse_copy($path.'/'.$subnode,$destination.$name.'/','');
                $cmd = array_merge($cmd, $folderData['cmd']);
                $totalSize += $folderData['size'];
            }
        }
        return ['cmd'=>$cmd, 'size'=>$totalSize];
    }
    
    /**
     * save_content do the same job as file_put_contents, but make a check to verify if it can write,
     * and if it's not the case, can optionnaly stop the execution .( file_put_contents do no stop execution)
     *
     * @param String $path the path to save the file
     * @param mixed $content to be saved in the fail
     * @param boolean $stop=false execution of the current script and alert the permission denied
     * 
     */
    public static function save_content($path, $content, $stop = false, $rights = 02664) {
        if (is_writable(Filesystem::get_file_path($path))) {
            $res = file_put_contents($path, $content);
            if ($res !== false) {
                chmod($path, $rights);
                // if this chmod fail, you have perhaps some permissions issue, 
                // to let apache and your user handle files, you should use some shell commands like:
                // usermod -a -G www-data your_user
                // chown -R your_user:www-data /instance
                // chmod 664 -R  /instance

            }
            return $res;
        } else {
            Utils::error_log("Writing permission denied on ".$path);
            if ($stop){
                echo "Writing permission denied on ".$path;
                exit;
            }
        }
    }

    /**
     * If save_history is true, we send process time consuming recognize by its process key to history.
     *
     * @param String $key
     * 
     */
    public static function update_history($key)
    {
        global $FS, $DB;
        if ($FS->save_history){
            $end_time = round(microtime(true)*1000);
            $q = 'UPDATE '.$DB->table('filesystem_history').' SET process_key="",end_time=? where process_key=?';
            $res = $DB->prepared_query($q,'is',[$end_time,$key]);
        }
    }
    
    /**
     * Move work a little bit like copy and should be used also for rename
     * 
     * @param Array!String $sources 
     * for a single file, it can be a simple path string.
     * but it can be also an array with for each file :
     * path =>  the file path , name => a new name,  replace => a bool to indicate if we overwrite or not a possibly existing file  
     * @param String $destination
     * @param bool $no_progress=false to indicate not to go through the system_to_process_to_redis and to stay in the same php execution pipe. 
     * @param bool|string $api=false ton indicate if the call is coming from the api, in that case the history is recorded without $key
     *
     * @return Array containing $succes : a list of correctly moved paths, and/or error message in error ['success', 'error']
     *                                                                                                                              
     */
    public function move($source, $destination, $key=false, $api=false)
    {
        global $USER, $DB, $CONFIG;
        
        if (!is_array($source)){
            $source = [$source];
        }
        $destination = $this->check_path($destination,false,'write');
        if ($destination){
            $time = round(microtime(true)*1000);
            if (!$key) $key = 'move-'.$time;
            
            if ($this->save_history){
                $q_history = 'INSERT INTO '.$DB->table('filesystem_history').' (`process_key`, `user_login`, `source`, `destination`, `action`, `end_time`) VALUES ';
                $q_def = '';
                $q_params = [];
            }
            
            $err = [];
            $suc = [];
            
            
            $cmd = [];
            $cmd_after = [];

            $total = 0;
            $toLock = [];
            
            foreach($source as $path){
                $newName = false;
                $replace = false;
                if (!is_string($path)){
                    if (isset($path['name'])) $newName = $path['name'];
                    if (isset($path['replace'])) $replace = true;
                    $path = $path['path'];
                }
                
                $sequences = $this->get_sequence_data($path,'move');
                if ($sequences){
                    $path = $sequences['path'];
                } else {
                    $path = $this->check_path($path,false,'move');
                }
                if ($path){
                    array_push($suc, $path);
                    $parentPath = $this->get_file_path(rtrim($path,'/'));
                    $fileName = $this->get_file_name(rtrim($path,'/'));
                    if ($sequences){
                        for($i=$sequences['first'];$i<=$sequences['last'];$i++){
                            $k = $i;
                            $k = str_pad($k,$sequences['digit'], '0', STR_PAD_LEFT);
                            $file = $sequences['basename'].$k.'.'.$sequences['ext'];
                            if ($replace && file_exists($destination.$file)) exec('rm \''.$destination.$file.'\'');
                            if ($newName === false) {
                                array_push($cmd, '\''.$parentPath.$file.'\'');
                            }
                            else array_push($cmd_after, '&& mv -v \''.$parentPath.$file.'\' \''.rtrim($destination,'/').'/'.str_replace($sequences['basename'], $newName, $file).'\'');
                            $total++;
                            array_push($toLock, $parentPath.$file);
                        }
                    } else {
                        if ($replace && file_exists($destination.$fileName)) exec('rm \''.$destination.$fileName.'\'');
                        if ($newName === false) {
                            array_push($cmd, '\''.$path.'\'');
                        }
                        else array_push($cmd_after, '&& mv -v \''.$path.'\' \''.rtrim($destination,'/').'/'.$newName.'\'');
                        $total++;
                        array_push($toLock, $path);
                    }
                
                    if (is_file($path) || $sequences){ // detect linked folder with ¤ in name
                        $name = ($sequences) ? $sequences['basename'] : $this->get_file_name($path);
                        $cmd_ls = 'ls \'' . $parentPath . '\' | grep -i '.$this->clean_filter('¤'.$name.'¤');
                        $result_str = shell_exec($cmd_ls);
                        if ($result_str != ''){
                            $lst = explode("\n",trim($result_str));
                            foreach($lst as $index => $linked){
                                if ($linked != ''){
                                    if ($replace && file_exists(rtrim($destination,'/').'/'.$linked)) array_push($cmd_before, 'rm -r \''.rtrim($destination,'/').'/'.$linked.'\'');
                                    if ($newName === false) {
                                        // exec('rm -rf \''.rtrim($destination,'/').'/'.$linked.'\'');
                                        array_push($cmd, '\''.$parentPath.$linked.'\'');
                                    }
                                    else array_push($cmd_after, '&& mv -v \''.$parentPath.$linked.'\' \''.rtrim($destination,'/').'/'.str_replace($name, $newName, $linked).'\'');
                                    $total++;
                                    array_push($toLock, $parentPath.$linked);
                                }
                            }
                        }
                    } else {
                        if ($replace && file_exists($destination.$fileName)) exec('rm -r \''.$destination.$fileName.'\'');
                    }
                    
                    if ($this->save_history){
                        // $name = $this->get_file_name($path);
                        $name = ($newName === false) ? $fileName : $newName;
                        if ($api){
                            $q_history.='("", ?, ?, ?, "move", ?),';
                            $q_def .= 'sssi';
                            array_push($q_params, $USER->login, str_replace($CONFIG::ROOT_FS,'/',$path), str_replace($CONFIG::ROOT_FS,'/',rtrim($destination,'/').'/'.$name), $time);
                        } else {
                            $q_history.='(?, ?, ?, ?, "move", ?),';
                            $q_def .= 'ssssi';
                            array_push($q_params, $key, $USER->login, str_replace($CONFIG::ROOT_FS,'/',$path), str_replace($CONFIG::ROOT_FS,'/',rtrim($destination,'/').'/'.$name), $time);
                        }
                    }
                } else {
                    array_push($err, ['path'=>$path,'err'=>'fs']);
                }
            }
            
            $full_cmd = [];
            if ($cmd) $full_cmd = array_merge($full_cmd, $cmd);
            if ($cmd_after) $full_cmd = array_merge($full_cmd, $cmd_after);
            $count = count($full_cmd);

            if ($full_cmd){
                if ($count > 100){
                    $chunks = array_chunk($full_cmd, 100);
                    $toLock = array_chunk($toLock, 100);
                } else {
                    $chunks = [$full_cmd];
                    $toLock = [$toLock];
                }
                $cmds = [];
                foreach($chunks as $i => $chunk){
                    if (str_starts_with($chunk[0], '&&')) {
                        $cmd = '';
                        $chunk[0] = substr($chunk[0], 3);
                    }
                    else $cmd = 'mv -vf -t \''.$destination.'\' ';
                    $cmd .= implode(' ', $chunk);
                    array_push($cmds, $cmd);
                    $toLock[$i] = array_merge($toLock[$i], [$destination]);
                }
                
                if ($this->follow_redis) {
                    Utils::system_process_with_tracking($cmds, $key, 'lineCount', $total, $toLock);
                } else {
                    Utils::system_process_no_tracking($cmds, $key, 'lineCount', $total, $toLock);
                }
                
                if ($this->save_history && $q_def != ''){
                    $DB->prepared_query(substr($q_history, 0, -1), $q_def, $q_params);
                }
                
                
            }
            return ['success'=>$suc, 'error'=>$err];
        }
        Utils::error_log('MOVE PATH ERROR : '.$source.' -> '.$destination);
        return false;
    }

    /**
     * A classic Mkdir plus path authorization checking
     *
     * @param String $destination path
     * @param int $rights level default is 0755
     * @param bool $recursive true by default
     * 
     * @return bool true or false
     * 
     */
    public function mkdir($destination, $rights = 0775, $recursive = true) {
        $path = $this->check_path($destination,false,'new_folder',true);
        if ($path){
            $old = umask(2);
            if (mkdir($path, $rights, $recursive)) {
                umask($old);
                return true;
            } else {
                umask($old);
                Utils::error_log('MKDIR ERROR : '.$path);
            }
            return false;
        }else{
            Utils::error_log('MKDIR PATH ERROR : '.$destination);
            return false;
        }
    }

    /**
     * One command for files or folders and with autorization check.
     * Important : this one do not follow the process advance.
     * It's made for fast action on one item so can't be used on a file/image sequence
     * check delete_with_follow() if needed.
     *
     * @param String $target
     * 
     * @return Bool true or false
     * 
     */
    public function delete($target)
    {
        global $DB,$USER,$CONFIG;
        $target = $this->check_path($target,false,'delete');
        if ($target){
            if(file_exists($target)){
                if (is_dir($target)){
                    exec('rm -R "'.$target.'"');
                    return true;
                }
                else {
                    $cmd = 'rm "'.$target.'"';
                    
                    // detect of ¤ linked to target
                    if (is_file($target)){
                        $path = $this->get_file_path($target);
                        $filename = $this->get_file_name($target);
                        $cmd_ls = 'ls ' . $path . ' | grep -i '.$this->clean_filter('¤'.$filename.'¤');
                        $result_str = shell_exec($cmd_ls);
                        if ($result_str != ''){
                            $cmd = 'rm -R "'.$target.'"';
                            $lst = explode("\n",trim($result_str));
                            foreach($lst as $key => $name){
                                if ($name != ''){
                                    $cmd.= ' "'.$path.$name.'/"';
                                }
                            }
                        }
                    }
                    
                    exec($cmd);
                    //~ unlink($target);
                    if ($this->save_history){
                        $q = 'INSERT INTO '.$DB->table('filesystem_history').' SET process_key="",user_login=?,source=?,action="delete",end_time=?';
                        $DB->prepared_query($q,'ssi',[$USER->login,str_replace($CONFIG::ROOT_FS,'/',$target),round(microtime(true)*1000)]);
                    }
                    return true;
                }
            }else{
                array_push($this->err_lst,'path_not_found');
                array_push($this->last_errors,'path_not_found');
                Utils::error_log('delete ERROR : '.$target);
                return false;
            }
        }else{
            Utils::error_log('delete PATH ERROR : '.$target);
            return false;
        }
    }

    /**
     * Delete anything with process follow after autorization check
     *
     * @param String|Array $paths one or Array of paths
     * @param String $key=false the key to follow the process
     * @param Bool $api=false to follow in history without $key
     * 
     * @return [type]
     * 
     */
    public function delete_with_follow($paths,$key=false,$api=false)
    {
        global $DB, $USER, $CONFIG;
        
        if (!is_array($paths)){
            $paths = [$paths];
        }
        
        $time = round(microtime(true)*1000);
        if (!$key) $key = 'remove-'.$time;
        
        if ($this->save_history){
            $q_history = 'INSERT INTO '.$DB->table('filesystem_history').' (`process_key`, `user_login`, `source`, `action`, `end_time`) VALUES ';
            $q_def = '';
            $q_params = [];
        }
        
        $err = [];
        $suc = [];
        
        $cmd = [];
        $count = 0;
        // $cmd = 'rm -rv';
        foreach($paths as $j => $path){
            $sequences = $this->get_sequence_data($path,'delete');
            if ($sequences){
                $path = $sequences['path'];
            } else {
                $path = $this->check_path($path,false,'delete');
            }
            if ($path){
                
                array_push($suc, $path);
                $parentPath = $this->get_file_path(rtrim($path,'/'));
                
                if ($sequences){
                    // don't do basename* because it will remove file from adjacent sequences too
                    // reduce count because the next test to detect proxy will add one
                    $count--; 
                    for($i=$sequences['first'];$i<=$sequences['last'];$i++){
                        $k = $i;
                        $k = str_pad($k,$sequences['digit'], '0', STR_PAD_LEFT);
                        $file = $sequences['basename'].$k.'.'.$sequences['ext'];
                        array_push($cmd, $parentPath.$file);
                        // $cmd.= ' \''.$parentPath.$file.'\'';
                        $count++;
                    }
                } else {
                    array_push($cmd, $path);
                    // $cmd .= ' \''.$path.'\'';
                }
                
                if (is_file($path) || $sequences){
                    $count++;
                    
                    $filename = ($sequences) ? $sequences['basename'] : $this->get_file_name($path);
                    $cmd_ls = 'ls \'' . $parentPath . '\' | grep -i '.$this->clean_filter('¤'.$filename.'¤');
                    $result_str = shell_exec($cmd_ls);
                    if ($result_str != ''){
                        $lst = explode("\n",trim($result_str));
                        foreach($lst as $index => $linked){
                            if ($linked != ''){
                                $nb = intval(trim(shell_exec('find \''.$parentPath.$linked.'\' | wc -l')));
                                $count += $nb;
                                array_push($cmd, $parentPath.$linked);
                                // $cmd.= ' \''.$parentPath.$linked.'/\'';
                            }
                        }
                    }
                } else {
                    // for folder count the number of entry inside
                    $count += intval(trim(shell_exec('find \''.$path.'\' | wc -l')));
                }
                
                if ($this->save_history){
                    if ($api){
                        $q_history.='("", ?, ?, "delete", ?),';
                        $q_def .= 'ssi';
                        array_push($q_params, $USER->login, str_replace($CONFIG::ROOT_FS,'/',$path), $time);
                    } else {
                        $q_history.='(?, ?, ?, "delete", ?),';
                        $q_def .= 'sssi';
                        array_push($q_params, $key, $USER->login, str_replace($CONFIG::ROOT_FS,'/',$path), $time);
                    }
                }
            } else {
                unset($paths[$j]);
            }
        }
        
        // $cmd.= ' | pv -f -l -s '.trim($count);
        if ($this->save_history && $q_def != ''){
            $DB->prepared_query(substr($q_history, 0, -1), $q_def, $q_params);
        }
        if (count($cmd) > 100){
            $chunks = array_chunk($cmd, 100);
        } else {
            $chunks = [$cmd];
        }
        $cmds = [];
        $toLock = [];
        foreach($chunks as $chunk){
            array_push($cmds, 'rm -rv \''.implode('\' \'', $chunk).'\'');
            array_push($toLock, $chunk);
        }

        if ($this->follow_redis) {
            Utils::system_process_with_tracking($cmds, $key, 'lineCount', $count, $toLock);
        } else {
            Utils::system_process_no_tracking($cmds, $key, 'lineCount', $count, $toLock);
        }
        
        return ['success'=>$suc, 'error'=>$err];
    }

    /**
     * Return available space storage available quickly !
     * Some storages like CEPHFS, depending their configuration do not return Bytes.
     * so there is some commented code to test in case of the result seems hilarious.
     *
     * @param mixed $dir
     * 
     * @return [type]
     * 
     */
    public function du($dir)
    {
        $dir=realpath($dir);
        // for FS which does not return the bytes ... like CEPHFS
        //~ $res =shell_exec("du -mDsL '".$dir."'");
        //~ preg_match( '/\d+/', $res, $B );
        //~ return round($B[0]*1024*1024,1);
        $res =shell_exec("du -bDsL '".$dir."'");
        if ($res){
            preg_match('/\d+/', $res, $B);
            if (isset($B[0])) {
                return $B[0];
            }
        }
        return 0;
    }
    
    /**
     * Return available storage assiociated with the home folder of the instance.
     * some filesystem like glusterFS family can note quota for a folder.
     * so we'll check the available quota from the folder, or the config, and if there is none of them,
     * we indicate 5 GB by default/
     *
     * @return Array :
     * 0 => Global disk storage available
     * 1 => Occupied space
     * 2 => in percentage
     * 3 => remaining space
     * 
     */
    public function home_du()
    {
        global $CONFIG;
        if ($CONFIG::GLUSTER_STORAGE){
            $res =shell_exec("df -B1 '".$CONFIG::HOME_FOLDER."'");
            $line=explode("\n",$res);
            $line=$line[1];
            $data=preg_split('/\s+/',$line);
            
            $toReturn[0]= $CONFIG::STORAGE_COTA;   // limit
            $toReturn[1]= intval($data[2]);             // occupied space
            $toReturn[2]= intval($data[4]);             // occupied percentage
            $toReturn[3]= intval($data[3]);             // remaining space
            return $toReturn;
        }else{
            // authorized quota from the config or 5 GB by default
            if (defined('$CONFIG::STORAGE_COTA')) {
                $toReturn[0]= $CONFIG::STORAGE_COTA;
            } else {
                $toReturn[0]= 5368709120;
            }
            $path = ($CONFIG::ROOT_FS != '') ? $CONFIG::ROOT_FS : $CONFIG::HOME_FOLDER;
            $toReturn[1]=$this->du($path); // occupied space
            $toReturn[2]=($toReturn[1]/$toReturn[0]*100); // occupied percentage
            $toReturn[3]=$toReturn[0]-$toReturn[1]; // remaining space
            return $toReturn;
        }
    }
     
    /**
     * Return an array tree with available files / folders / links in each folder.
     *
     * @param String $path the starting folder
     * @param String $basepath='' a string that prefix all result 
     * 
     * @return Array tree
     * 
     */

    public function recurse_ls($path, $basepath='')
    {
        $path = $this->check_path($path,false,'read');
        if ($path){
            $result = array();
            $result['files']= array();
            $result['folders']= array();
            $result['links']= array();
            $listing = scandir($path);
            foreach ($listing as $key => $value) {
                //skiping .. and .
                if (!in_array($value, array(".",".."))) {
                    if (is_dir($path . DIRECTORY_SEPARATOR . $value)) {
                        $result['folders'][]= $basepath.$value;
                        //recursive
                        if ($basepath!='') {
                            $nbase=$basepath . $value. DIRECTORY_SEPARATOR;
                        } else {
                            $nbase=$value. DIRECTORY_SEPARATOR;
                        }
                        $subdir= $this->recurse_ls($path . DIRECTORY_SEPARATOR . $value, $nbase);
                        $result['folders']=array_merge($result['folders'], $subdir['folders']);
                        $result['files']=array_merge($result['files'], $subdir['files']);
                    }else if (is_file($path . DIRECTORY_SEPARATOR . $value) && !is_link($path . DIRECTORY_SEPARATOR . $value)) {
                        $result['files'][]= $basepath.$value;
                    }else if (is_link($path . DIRECTORY_SEPARATOR . $value)){
                        $result['links'][]= $basepath.$value;
                    } 
                }
            }
            return $result;
        }else{
            Utils::error_log('recurse_ls PATH ERROR : '.$path);
            return false;
        }
    }
    
    public function clean_filter($filter) {
        $first = substr($filter, 0, 1);
        $last = substr($filter, -1, 1);
        while (substr($filter, 0, 1) == '*') {
            $filter = substr($filter, 1, strlen($filter) - 1);
        }
        while (substr($filter, -1, 1) == '*') {
            $filter = substr($filter, 0, strlen($filter) - 1);
        }
        $filter = str_replace('.', '\\.', $filter);
        $filter = str_replace('[', '\\[', $filter);
        $filter = str_replace(']', '\\]', $filter);
        $filter = str_replace('{', '\\{', $filter);
        $filter = str_replace('}', '\\}', $filter);
        $filter = str_replace('*', '.*', $filter);
        if ($first == '*') $filter = '' . $filter . '$';
        return '"' . $filter . '"';
    }
    /**
     * ls from shell with grep filter to make quick search 
     * and return all infos possible on files/folders/links and sequences
     * if you just need names without details and sequences, check recurse_ls()
     * for file or folder with ¤marker¤, to detect them use this on the output: `preg_match('/^¤{1}.+?¤{1}/', $file_name)`
     * @param string $basePath 
     * @param string $filter to return only element whose contain it in their names
     * @param bool $get_folders
     * @param bool $get_files
     * @param bool $get_links
     * @param bool $recurse default to false, because it's slow, check recurse_ls 
     * @param bool $ignore_hidden for removing hidden file or folder from the final result
     * @param bool $childsequence
     * 
     * @return array with for each folder : 
     *               'type' => 'folder',
     *               'creation-date' => 'date as string',
     *               'name' => 'folder name as string'
     * 
     *               for files :
     *               'type' => 'file',
     *               'mime-type'=>'the mime as string',
     *               'modification-date' => 'date as string',
     *               'name' => 'folder name as string',
     *               'size' => size as int
     * 
     *               for links :
     *               'type' => 'link',
     *               'creation-date' => 'date as string',
     *               'name' => 'link name as string with target of the link separated by ' -> ';
     *               
     *               for sequences :
     *               'type'=>'sequence',
     *               'mime-type'=>'the mime as string',
     *               'modification-date' => 'date as string',
     *               'size' => size as int
     *               'name' => 'sequence name as string',
     *               'start' => Number of the first file in the sequence as int,
     *               'last' => Number of the last file in the sequence as int,
     *               'total' => Number of files as int,
     *               'ext' => file extension as string,
     *               'child' => array containing names of all files.
     */
    public function shell_ls($basePath = '', $filter = '', $get_folders = true, $get_files = true, $get_links = true, $recurse = false, $ignore_hidden = false, $childsequence = false)
    {
        $result = array();
        $files = array();
        $links = array();
        $folders = array();
        // remove root path from result because we want it to be hidden from public side
        $result['path'] = str_replace($this->root_fs,'/',$basePath);
        
        //security check
        $do_scan = $this->check_path($basePath,true,'read');
        
        $basePath = rtrim($basePath, '/');
        if (is_dir($basePath) && $do_scan) {
            //checking recyclebin case :
            $tmp = explode('/', $basePath);
            $folder = array_pop($tmp);
            if ($filter != '') $filter = $this->clean_filter($filter);
            
            // $manual_check_of_linked_folder = ($do_scan == $CONFIG::ROOT_FS && $do_scan != $basePath) ? true : false; // this variable toggle this special treatment
            
            if ($get_folders) {
                // folders
                $command = 'ls -l --time-style +%s%3N \'' . $basePath . '\' | grep ^d';
                if ($filter != '') $command.= ' | grep -i ' . $filter . '';
                $res = shell_exec($command);
                //no symlink in the result because of ls -l instead of ls -lL
                if ($res != ''){    
                    $list = explode("\n", $res);
                    foreach ($list as $value) {
                        $value = trim($value);
                        if ($value != '') {
                            
                            $t = explode(' ', $value);
                            // LS can return extra spaces in some line to facilitate the readability of its return.
                            // The problem is that it can shift the position of the items you want to recover from 1 or more indexes
                            // In a spaceless return, the index of the first element is 4, but it can be shifted to 5/6/7 depending on the case.
                            // Or this test on the position before index 4
                            $pos = 4;
                            foreach($t as $i => $val){
                                if ($i <= $pos && $val == ''){
                                    $pos++;
                                } else if ($i >= $pos){
                                    break;
                                }
                            }
                            
                            $size = $t[$pos];
                            $date = $t[$pos+1];
                            $name = substr($value, strpos($value, $date) + strlen($date) + 1);
                            
                            if ($ignore_hidden && str_contains($name, '¤')) continue;
                            
                            if ($name != '..' && $name != '.') {
                                $item = array(
                                    'type' => 'folder',
                                    'creation-date' => $date,
                                    'name' => $name
                                );
                                if ($recurse){
                                    $childs = $this->shell_ls($basePath.'/'.$item['name'],$filter,$get_folders,$get_files,$get_links,$recurse,$ignore_hidden,$childsequence);
                                    if ($childs) $item['childs'] = $childs;
                                }
                                array_push($folders, $item);
                            }
                        }
                    }
                }
            }

            if ($get_files) {
                $command = 'ls -lL  --time-style +%s%3N \'' . $basePath . '\' | grep ^-';
                if ($filter != '') $command.= ' | grep -i ' . $filter . '';
                $res = shell_exec($command);
                if ($res != ''){
                    
                    $list = explode("\n", $res);
                    
                    $inSequence = false;
                    $sequenceFile = false;
                    $cnt = count($list);
                    
                    foreach ($list as $key => $value) {
                        $value = trim($value);
                        if ($value != '') {
                            $t = explode(' ', $value);
                            
                            $pos = 4;
                            foreach($t as $i => $val){
                                if ($i <= $pos && $val == ''){
                                    $pos++;
                                } else if ($i >= $pos){
                                    break;
                                }
                            }
                            
                            $size = $t[$pos];
                            $date = $t[$pos+1];
                            $name = substr($value, strpos($value, $date) + strlen($date) + 1);
                            
                            if ($ignore_hidden && str_contains($name, '¤')) continue;
                            
                            $file = array(
                                'type' => 'file',
                                'mime-type'=>Utils::get_mime_type($this->get_file_ext($name)),
                                'modification-date' => $date,
                                'name' => $name,
                                'size' => $size
                            );
                            array_push($files, $file);
                            
                            if (!$sequenceFile){
                                $noext = $this->get_file_name_noext($name);
                                $pattern = "/([0-9]+)$/i"; // all digit at end of the file
                                $sequenceName = preg_replace($pattern, '', $noext);
                                if ($sequenceName != ''){
                                    $sequenceIndex = str_replace($sequenceName, '', $noext);
                                    $sequenceFile = array(
                                        'type'=>'sequence',
                                        'mime-type'=>Utils::get_mime_type($this->get_file_ext($name)),
                                        'modification-date' => $date,
                                        'size' => $size,
                                        'name' => $sequenceName,
                                        'start' => $sequenceIndex,
                                        'last' => $sequenceIndex,
                                        'total' => 1,
                                        'ext' => $this->get_file_ext($name),
                                        'child' => [],
                                    );
                                    array_push($sequenceFile['child'], $file);
                                }
                                continue;
                            }
                            
                            // come at this part only if there is a sequence
                            $noext = $this->get_file_name_noext($name);
                            $pattern = "/([0-9]+)$/i"; // all digit at end of the file
                            $sequenceName = preg_replace($pattern, '', $noext);
                            $sequenceIndex = str_replace($sequenceName, '', $noext);
                            $ext = $this->get_file_ext($name);
                            
                            $sameSequence = $sequenceFile['name'] == $sequenceName;
                            $extended_images_ext = array_merge(Media::image_ext, ['exr']);
                            $sameSequence = ($sameSequence && $ext == $sequenceFile['ext'] && in_array($sequenceFile['ext'], $extended_images_ext)) ? true : false;
                            $sameSequence = ($sameSequence && strlen($sequenceIndex) == strlen($sequenceFile['start'])) ? true : false;
                            $sameSequence = ($sameSequence && intval($sequenceIndex) == (intval($sequenceFile['last']) + 1)) ? true : false;
                            if ($sameSequence){
                                
                                // same sequence
                                // so we remove last added line
                                array_pop($files);
                                
                                if (!$inSequence){
                                    // second element of the sequence
                                    // suppress the element because of the doubled line
                                    array_pop($files);
                                    $inSequence = true;
                                }
                                
                                $sequenceFile['modification-date'] = ($date > $sequenceFile['modification-date']) ? $date : $sequenceFile['modification-date'];
                                $sequenceFile['total']++;
                                $sequenceFile['last'] = $sequenceIndex;
                                $sequenceFile['size'] += $size;
                                array_push($sequenceFile['child'], $file);
                                
                            } else {
                                // Not the same name, end of sequence
                                if ($inSequence){
                                    // leaving a sequence, retrieve the line we just added
                                    // add the one for the sequence and reinsert the last one
                                    $last = array_pop($files);
                                    
                                    $sequence = array(
                                        'type' => 'sequence',
                                        'mime-type'=>$sequenceFile['mime-type'],
                                        'modification-date' => $sequenceFile['modification-date'],
                                        'name' => $sequenceFile['name'].'['.$sequenceFile['start'].'-'.$sequenceFile['last'].'].'.$sequenceFile['ext'],
                                        'size' => $sequenceFile['size'],
                                        'total' => $sequenceFile['total']
                                    );
                                    if ($childsequence){
                                        $sequence['child'] = $sequenceFile['child'];
                                    }
                                    array_push($files, $sequence);
                                    array_push($files, $last);
                                    
                                    $inSequence = false;
                                }
                                
                                if ($sequenceName != ''){
                                    // potential start of a new sequence
                                    $sequenceIndex = str_replace($sequenceName, '', $noext);
                                    $sequenceFile = array(
                                        'type'=>'sequence',
                                        'mime-type'=>Utils::get_mime_type($this->get_file_ext($name)),
                                        'modification-date' => $date,
                                        'size' => $size,
                                        'name' => $sequenceName,
                                        'start' => $sequenceIndex,
                                        'last' => $sequenceIndex,
                                        'total' => 1,
                                        'ext' => $this->get_file_ext($name),
                                        'child' => []
                                    );
                                    array_push($sequenceFile['child'], $file);
                                } else {
                                    $sequenceFile = false;
                                }
                            }
                        }
                        if (($key + 1) == $cnt){
                            // last element, added if it s a sequence
                            if ($inSequence){
                                $file = array(
                                    'type' => 'sequence',
                                    'mime-type'=>$sequenceFile['mime-type'],
                                    'modification-date' => $sequenceFile['modification-date'],
                                    'name' => $sequenceFile['name'].'['.$sequenceFile['start'].'-'.$sequenceFile['last'].'].'.$sequenceFile['ext'],
                                    'size' => $sequenceFile['size'],
                                    'total' => $sequenceFile['total']
                                );
                                if ($childsequence){
                                    $file['child'] = $sequenceFile['child'];
                                }
                                array_push($files, $file);
                                
                                $inSequence = false;
                            }
                        }
                    }
                }
            }
             if ($get_links) {
                // links only
                $command = 'ls -lac --time-style +%s%3N \'' . $basePath . '\' | grep "\->"';
                if ($filter != '') $command.= ' | grep -i ' . $filter;
                $res = shell_exec($command);
                // add symlink to the result
                if ($res != ''){    
                    $list = explode("\n", $res);
                    foreach ($list as $value) {
                        $value = trim($value);
                        if ($value != '') {
                            
                            $t = explode(' ', $value);
                            // LS can return extra spaces in some line to facilitate the readability of its return.
                            // The problem is that it can shift the position of the items you want to recover from 1 or more indexes
                            // In a spaceless return, the index of the first element is 4, but it can be shifted to 5/6/7 depending on the case.
                            // Or this test on the position before index 4
                            $pos = 4;
                            foreach($t as $i => $val){
                                if ($i <= $pos && $val == ''){
                                    $pos++;
                                } else if ($i >= $pos){
                                    break;
                                }
                            }
                            
                            $size = $t[$pos];
                            $date = $t[$pos+1];
                            $name = substr($value, strpos($value, $date) + strlen($date) + 1);
                            
                            if ($ignore_hidden && str_contains($name, '¤')) continue;
                            
                            if ($name != '..' && $name != '.') {
                                $item = array(
                                    'type' => 'link',
                                    'creation-date' => $date,
                                    'name' => $name
                                );
                                // if ($recurse){
                                //     $childs = $this->shell_ls($basePath.'/'.$item['name'],$filter,$get_folders,$get_files,$get_links,$recurse,$ignore_hidden,$childsequence);
                                //     if ($childs) $item['childs'] = $childs;
                                // }
                                array_push($links, $item);
                            }
                        }
                    }
                }
            }
            usort($folders, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            usort($files, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            usort($links, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            
            $result['folders'] = $folders;
            $result['files'] = $files;
            $result['links'] = $links;
            
            return $result;
        }
        return false;
    }
    /**
     * Get modified items since a given date
     *
     * @param mixed $path
     * @param mixed $date
     * 
     * @return Array of paths
     * 
     */
    public function get_modified($path,$date)
    {
        $path = $this->check_path($path,false,'read');
        if ($path){
            $to_time = time();
            $from_time = strtotime($date);
            $diff = round(($to_time - $from_time) / 60,2);
            if ($diff > 0){
                $cmd = 'find "'.$path.'" -mmin -'.$diff;
                $res =  shell_exec($cmd);
                if ($res){
                    $list = explode("\n", $res);
                    if ($list){
                        $resp = [];
                        foreach($list as $key => $item){
                            $file = trim($item);
                            if ($file != ''){
                                $name = $this->get_file_name($file);
                                if (str_contains($name,'.')){
                                    array_push($resp,str_replace($this->root_fs,'',$file));
                                }
                            }
                        }
                        return $resp;
                    }
                }
            }
            return [];
        } else {
            return false;
        }
    }
    /**
     * Return a new path with the name of the file replaced securely, without touching to extension :
     * avoid str_replace error, when the file name can be found also in the path
     *
     * @param string $file the full path of the file
     * 
     * @param string $new_name the string to replace the old name with.
     * 
     * @return string
     * 
     */
    public static function replace_file_name($file,$new_name){
        $path=Filesystem::get_file_path($file);
        $ext=Filesystem::get_file_ext($file);
        return $path.$new_name.'.'.$ext;

    }
    /**
     * Return the file extention
     *
     * @param mixed $file
     * 
     * @return string
     * 
     */
    public static function get_file_ext($file)
    {
        if (!str_contains($file,'.')) return '';
        $file=explode(".", $file);
        $ext=$file[sizeof($file)-1];
        return $ext;
    }

    /**
     * remove the filename and return the path
     *
     * @param mixed $file
     * 
     * @return string
     * 
     */
    public static function get_file_path($file)
    {
        $tmp=explode("/", $file);
        array_pop($tmp);
        $file = implode("/", $tmp)."/";
        return $file;
    }
    /**
     * return the filename
     *
     * @param mixed $file
     * 
     * @return string
     * 
     */
    public static function get_file_name($file)
    {
        $tmp=explode("/", $file);
        $file=array_pop($tmp);
        return $file;
    }
    /**
     * return the filename without extention
     *
     * @param mixed $file
     * 
     * @return string
     * 
     */
    public static function get_file_name_noext($file)
    {
        //remove base path
        $tmp=explode("/", $file);
        $file=array_pop($tmp);
        
        $file=explode(".", $file);
        array_pop($file);
        $file=implode(".", $file);
        return $file;
    }
    /**
     * Check if the string can  be used as a filename
     *
     * @param mixed $name
     * 
     * @return bool
     * 
     */
    public function check_name($name)
    {
        $this->bad_name = false;
        if (strpbrk($name,$this->unauthorized_character_string)){
            $this->bad_name = true;
            return false;
        }
        return true;
    }
    
    /**
     * return available infos on folder link or file
     *
     * @param String $path
     * 
     * @return array 
     * 
     */
    public function get_info($path){
        $path = $this->check_path($path,false,'read');
        if ($path){
            if (file_exists($path)){
                if(is_dir($path)){
                    ///test version
                    //if (
                    $result=array(
                    'type'=>'folder',
                    'creation-date' => filectime($path)
                    );
                }else if (is_file($path) && !is_link($path)){
                    $result=array(
                    'filesize'=>filesize($path),
                    'type'=>'file',
                    'mime-type'=>Utils::get_mime_type($this->get_file_ext($path)),
                    'creation-date' => filectime($path),
                    'modification-date' => filemtime($path)
                    );
                }else if (is_link($path)){
                    $linkcont=readlink($path);
                    $linkcont=($this->check_path($linkcont,false,'read'))?$linkcont:'not authorised';
                    $result=array(
                        'type'=>'link',
                        'link_target'=>$linkcont
                    );
                }
                return $result;
            }else{
                return false;
            }
        }else{
            Utils::error_log('get_info PATH ERROR : '.$path);
            return false;
        }
    }
    
    /** 
     * add_lock add a "lock" on files/folders
     * The lock indicates that a file/folder is being used (moving, copying, depressing...) and that I cannot touch it
     * It is to prevent dual use, or deletion by one user while another had started a copy or other.
     * The lock also applies to the relatives of the target. If I copy /fold1/fold2/ and during this time /fold1/ is deleted by someone, there will be some tears !!!
     * $parent determines if we lock the parent too, true by default
     * 
     * the lock time is fixed in the main config of the instance
     *
     * @param String $path
     * @param int $timestamp current time in general to start the lock
     * @param bool $parent=true
     * 
     * @return void
     * 
     */
    public function add_lock($path,$timestamp,$parent=true)
    {
        global $CONFIG;
        if ($this->redis){
            global $redisproc;
            $this->init_redis();
        }
        
        $path = str_replace($CONFIG::ROOT_FS,'',trim($path));
        $path = str_replace(' ', '-', $path);
        $path = ltrim($path,'/');
        if ($parent){
            $parts = explode('/',$path);
            $tpath = [];
            foreach($parts as $part){
                array_push($tpath, $part);
                $lock_key = $CONFIG::SITE_NAME.'-locks-'.implode('/',$tpath);
                if ($this->redis){
                    $redisproc->sAdd($lock_key,$timestamp);
                    $redisproc->expire($lock_key,$this->lock_time);
                } else {
                    global $DB;
                    $DB->query('INSERT INTO '.$this->db_lock.' SET lock_key = "'.$lock_key.'", time = FROM_UNIXTIME('.$timestamp.')');
                }
            }
        } else {
            $lock_key = $CONFIG::SITE_NAME.'-locks-'.$path;
            if ($this->redis){
                $redisproc->sAdd($lock_key,$timestamp);
                $redisproc->expire($lock_key,$this->lock_time);
            } else {
                global $DB;
                $id_lock = $DB->query_value('SELECT id FROM '.$this->db_lock.' WHERE lock_key LIKE "'.$lock_key.'"');
                if ($id_lock){
                    $DB->query('UPDATE '.$this->db_lock.' SET timestamp = CURRENT_TIMESTAMP WHERE id='.$id_lock);
                } else {
                    $DB->query('INSERT INTO '.$this->db_lock.' SET lock_key = "'.$lock_key.'"');
                }
            }
            
        }
    }
    /**
     * Updating the lock will renew its expiration date...
     * so during long operation it's important to update the locks at regular interval (depending the config)
     *
     * @param String $path
     * @param bool $parent=true
     * 
     * @return void
     * 
     */
    public function update_lock($path,$parent=true)
    {
        global $CONFIG;
        if ($this->redis){
            global $redisproc;
            $this->init_redis();
        }
        $path = str_replace($CONFIG::ROOT_FS,'',trim($path));
        $path = str_replace(' ', '-', $path);
        $path = ltrim($path,'/');
        if ($parent){
            $parts = explode('/',$path);
            // $tpath = '';
            $tpath = [];
            foreach($parts as $part){
                array_push($tpath, $part);
                $lock_key = $CONFIG::SITE_NAME.'-locks-'.implode('/',$tpath);
                if ($this->redis){
                    $redisproc->expire($lock_key,$this->lock_time);
                } else {
                    global $DB;
                    $q = 'UPDATE '.$this->db_lock.' SET time = current_timestamp() WHERE lock_key = "'.$lock_key.'"';
                    $DB->query($q);
                }
            }
        } else {
            $lock_key = $CONFIG::SITE_NAME.'-locks-'.$path;
            if ($this->redis){
                $redisproc->expire($lock_key,$this->lock_time);
            } else {
                global $DB;
                $q = 'UPDATE '.$this->db_lock.' SET time = current_timestamp() WHERE lock_key = "'.$lock_key.'"';
                $DB->query($q);
            }
        }
    }

    /**
     * Will simply return the lock state of a path 
     *
     * @param String $path
     * 
     * @return bool
     * 
     */
    public function check_lock($path)
    {
        global $CONFIG;

        if ($this->redis){
            global $redisproc;
            $this->init_redis();
        }

        $path = str_replace($CONFIG::ROOT_FS,'',trim($path));
        $path = str_replace(' ', '-', $path);
        $path = ltrim($path,'/');
        $lock_key = $CONFIG::SITE_NAME.'-locks-'.$path;
        if ($this->redis){
            if ($redisproc->exists($lock_key)){
                return true;
            }
        } else {
            global $DB;
            $q = 'SELECT COUNT(*) FROM '.$this->db_lock.' WHERE lock_key LIKE "'.$lock_key.'" AND time > DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -'.$this->lock_time.' SECOND)';
            $is_locked = $DB->query_value($q);
            if ($is_locked > 0) return true;
        }
        
        return false;
    }

    /**
     * Delete the lock on a path.
     * It use the path and the timestamp given at lock creation to recognize the lock to delete.
     * If $force is true, it will remove all locks for a given path without using timestamp 
     *
     * @param String $path
     * @param int $timestamp
     * @param bool $force=false
     * @param bool $parent=true
     * 
     * @return void
     * 
     */
    public function delete_lock($path,$timestamp,$force=false,$parent=true)
    {
        global $CONFIG;
        if ($this->redis){
            global $redisproc;
            $this->init_redis();
        }
        $path = str_replace($CONFIG::ROOT_FS,'',trim($path));
        $path = str_replace(' ', '-', $path);
        $path = ltrim($path,'/');
        if ($parent){
            $parts = explode('/',$path);
            while ($parts) {
                $last = array_pop($parts);
                $tpath = ($parts) ? implode('/',$parts).'/'.$last : $last;
                $lock_key = $CONFIG::SITE_NAME.'-locks-'.$tpath;
                if (!$force){
                    if ($this->redis){
                        $redisproc->sRem($lock_key, $timestamp);
                        $nb = $redisproc->sCard($lock_key);
                        if ($nb == 0){
                            $redisproc->del($lock_key);
                        } else {
                        }
                    } else {
                        global $DB;
                        $q = 'DELETE FROM '.$this->db_lock.' WHERE lock_key LIKE "'.$lock_key.'" AND time = FROM_UNIXTIME('.$timestamp.')';
                        $DB->query($q);
                    }
                    
                } else {
                    if ($this->redis){
                        $redisproc->del($lock_key);
                    } else {
                        global $DB;
                        $q = 'DELETE FROM '.$this->db_lock.' WHERE lock_key LIKE "'.$lock_key.'"';
                        $DB->query($q);
                    }
                }
            }
        } else {
            $lock_key = $CONFIG::SITE_NAME.'-locks-'.$path;
            if (!$force){
                if ($this->redis){
                    $redisproc->sRem($lock_key, $timestamp);
                    $nb = $redisproc->sCard($lock_key);
                    if ($nb == 0){
                        $redisproc->del($lock_key);
                    }else {
                    }
                } else {
                    global $DB;
                    $q = 'DELETE FROM '.$this->db_lock.' WHERE lock_key LIKE "'.$lock_key.'" AND time = FROM_UNIXTIME('.$timestamp.')';
                    $DB->query($q);
                }
                
            } else {
                if ($this->redis){
                    $redisproc->del($lock_key);
                } else {
                    global $DB;
                    $q = 'DELETE FROM '.$this->db_lock.' WHERE lock_key LIKE "'.$lock_key.'"';
                    $DB->query($q);
                }
            }
        }
    }
    /**
     * to add a prefix or suffix on filename in a full path (prefix by default)
     * (just string manipulation, the file is not mv/rename with this function)
     *
     * @param string $file the path
     * @param string $presufix the string to prefix or suffix
     * @param string $suffixing=false false : prefix , true : suffix
     * 
     * @return String the new path 
     * 
     */
    public function pre_or_suf_fixing($file, $presufix, $suffixing=false)
    {
        $tmp=explode("/", $file);
        $filename=array_pop($tmp);
        $path=implode("/", $tmp)."/";
        $file=explode(".", $filename);
        $ext=array_pop($file);
        $filename=implode(".", $file);
        if ($suffixing) {
            $newpathfile=$path.$filename.$presufix.'.'.$ext;
        } else {
            $newpathfile=$path.$presufix.$filename.'.'.$ext;
        }

        return $newpathfile;
    }
    /**
     * check_permission to read write delete in module folder
     * the module can be here, but a user can have no right to access it 
     *
     * @param String $path 
     * @param String $type if '' return a detailed array of user rights
     * 
     * @return String|array path o detail of user rights
     * 
     */
    public function check_permission($path,$type)
    {
        global $USER,$CONFIG;

        // parse the path
        $path = trim(str_replace($this->root_fs,'',$path),'/');
        $parts = explode('/',$path);
        $module_name = array_shift($parts);
        $id_user = array_shift($parts);
        $filename = implode('/',$parts);

        if (!array_key_exists($module_name,$CONFIG::MODULES_LIST)){
            return 'no_permission';
        }

        // $user_allowed = false;
        $allowed_public_modules = $USER->allowed_public_modules();
        if (in_array($module_name, $allowed_public_modules)){
            if ($type == ''){
                return ['path'=>$CONFIG::ROOT_FS.$path,'read'=>1, 'write'=>1, 'delete'=>1];
            } else {
                return $CONFIG::ROOT_FS.$path;
            }
        } else {
            $allowed_registered_modules = $USER->allowed_registered_modules();
            if (count($allowed_registered_modules) > 0 && in_array($module_name, $allowed_registered_modules)) {
                if ($type == ''){
                    return ['path'=>$CONFIG::ROOT_FS.$path,'read'=>1, 'write'=>1, 'delete'=>1];
                } else {
                    return $CONFIG::ROOT_FS.$path;
                }
            }
        }

        return 'no_permission';
    }

    /**
     * Convert number of bytes in readable format
     *
     * @param mixed $byte
     * 
     * @return string
     * 
     */
    public static function human_readable_size($byte){
        if( $byte >= 1<<40){
            return ['size'=>number_format($byte/(1<<40),2),'unit'=>'TB'];
        }
        if( $byte >= 1<<30){
            return ['size'=>number_format($byte/(1<<30),2),'unit'=>'GB'];
        }
        if( $byte >= 1<<20){
            return ['size'=>number_format($byte/(1<<20),2),'unit'=>'MB'];
        }
        if( $byte >= 1<<10){
            return ['size'=>number_format($byte/(1<<10),2),'unit'=>'KB'];
        }
        return['size'=>$byte,'unit'=>'Bytes'];
    }

    /**
     * Search all files corresponding to sequence description
     *
     * @param mixed $path
     * @param mixed $basename
     * @param mixed $numberDigit
     * @param mixed $ext
     * 
     * @return Array of files found
     * 
     */
    public function get_files_from_sequence($path, $basename, $numberDigit, $ext)
    {
        $cmd = 'ls -l -c --time-style +%s \'' . $path . '\' | grep ^-';
        // start by a space is very important, 
        // otherwise if a sequence is like sequence4444.jpg and there is a file named wwwsequence6666.jpg it will be detected as from the pattern
        $filter = $basename.'[0-9]\{'.$numberDigit.'\}.'.$ext.'$';
        $cmd.= ' | grep -i "' . $filter . '"';
        $res = shell_exec($cmd);
        if ($res != ''){
            $list = explode("\n", $res);
            $result = [];
            foreach ($list as $key => $value) {
                $value = trim($value);
                if ($value != '') {
                    
                    $t = explode(' ', $value);
                    
                    $name = array_pop($t);
                    array_push($result, $name);
                }
            }
            
            return $result;
        }
        return false;
    }
    /* 
     * a sequence path is formatted like basename[xxxx-yyyy].ext
     * type is the action 
     */
    /**
     * Get all possible infos about a sequence
     *
     * @param mixed $path
     * @param mixed $type='' if you need to force another type (very rare)
     * 
     * @return array 
     * 
     */
    public function get_sequence_data($path,$type='')
    {
        $data = $this->check_path($path,true,$type,true);
        if (is_array($data)) $path = $data['path'];
        else $path = $data;
        
        $basePath = $this->get_file_path(rtrim($path,'/'));
        $fullname = $this->get_file_name(rtrim($path,'/'));
        
        $ext = $this->get_file_ext($fullname);
        
        if (!str_contains($fullname, '[')) return false;
        $t = explode('[', $fullname);
        
        $digits = array_pop($t);
        if (!str_contains($digits, '-')) return false;
        $digits = explode('-', $digits);
        if (!isset($digits[0]) || !isset($digits[1])) return false;
        $first = intval($digits[0]);
        $last = intval($digits[1]);
        $number_digit = strlen($digits[0]);
        
        $total = 0;
        
        $basename = implode('[', $t);
        
        $inside = [];
        $adjacent = [];
        $all_files = $this->get_files_from_sequence($basePath,$basename,$number_digit,$ext);
        if ($all_files){
            foreach($all_files as $file){
                $int = explode('.',str_replace($basename, '', $file));
                array_pop($int);
                $int = intval(implode('.', $int));
                if ($int >= $first && $int <= $last){
                    $total++;
                    array_push($inside, $file);
                } else {
                    array_push($adjacent, $file);
                }
            }
        }
        if ($inside && count($inside) == $total){
            return [
                'name'=>$fullname,
                'basename'=>$basename,
                'first'=>$first,
                'last'=>$last,
                'ext'=>$ext,
                'total'=>$total,
                'adjacent'=> ($adjacent) ? true : false,
                'digit'=>$number_digit,
                'path'=>$path
            ];
        } else {
            Utils::error_log('wrong count '.$total.' != '.count($inside));
        }
        
        return false;
    }
    /**
     * Return the basename without extention
     *
     * @param mixed $path
     * 
     * @return String
     * 
     */
    public function get_sequence_base_name($path)
    {
        $name = $this->get_file_name($path);
        $name = explode('[', $name);
        array_pop($name);
        $name = implode('[', $name);
        return $name;
    }
    /*
     * pathList, ARRAY list of path to compress
     * destination, STRING 
     * name, STRING archive name
     * format, STRING archive format
     * level, INTEGER archive compression level
     * password, STRING archive password
     * 
     * stdbuf -o0 7z a -w/tmp -y -tzip '/home/artispace/data/gros_film.zip' @/home/artispace/www/temp/pack-1657720240 -mx3
     * 
     */
    /**
     * Will create an archive
     *
     * @param array $pathList list of path to compress
     * @param string $destination destination folder path
     * @param string $name
     * @param string $format can be 'zip' 'tar' '7z' 'gz'
     * @param int $level=0 to 10
     * @param string $password='' if you want to secure your archive a bit
     * @param string $key='' for following process
     * 
     * @return Array success status and error message
     * 
     */
    public function pack($pathList,$destination,$name,$format,$level=0,$password='',$key='')
    {
        global $CONFIG;
        if (!$this->check_path($destination,false,'write')){
            return false;
        }
        
        $tempFile = $CONFIG::HOME_FOLDER.'temp/pack-'.time();
        if (file_exists($tempFile)){
            $tempFile .= '1';
        }
        
        $to_lock = [];
        $err_path = [];
        
        $tempContent = '';
        foreach($pathList as $path){
            $sequences = $this->get_sequence_data($path,'read');
            if ($sequences){
                $path = $sequences['path'];
            } else {
                $path = $this->check_path($path,false,'read');
            }
            if ($path){
                if ($sequences) {
                    $parentPath = $this->get_file_path(rtrim($path,'/'));
                    for($i=$sequences['first'];$i<=$sequences['last'];$i++){
                        $k = $i;
                        $k = str_pad($k,$sequences['digit'], '0', STR_PAD_LEFT);
                        $file = $sequences['basename'].$k.'.'.$sequences['ext'];
                        $tempContent.= '"'.$parentPath.$file.'"'.PHP_EOL;
                        array_push($to_lock, $file);
                    }
                } else {
                    $tempContent.= '"'.$path.'"'.PHP_EOL;
                    array_push($to_lock, $path);
                }
            } else {
                array_push($err_path, $path);
            }
        }
        
        // temp file is automatically deleted at the end of process_launcher.php
        $res = file_put_contents($tempFile, $tempContent);

        $destination = rtrim($destination,'/').'/'.$name.'.'.$format;
        $archpass = ($password != '') ? ' -p'.$password : '';
        
        $cmd = 'LC_ALL=C.UTF-8 stdbuf -o0 7z a -w/tmp -bsp1 -y -t'.$format.' \''.$destination.'\' @'.$tempFile.' -mx'.$level.$archpass;
        
        $key = ($key == '') ? 'pack-'.time() : $key;
        
        if ($this->follow_redis) {
            Utils::system_process_with_tracking($cmd, $key, 'pack', $tempFile, $to_lock);
        } else {
            Utils::system_process_no_tracking($cmd, $key, 'pack', $tempFile, $to_lock);
        }
    
        return ['success'=>$to_lock, 'error'=>$err_path];
    }
    /**
     * To unpack an archive
     *
     * @param mixed $path
     * @param mixed $destination
     * @param mixed $password=''
     * @param mixed $subfolder=false true if you want to unpack in a folder with the same name as the archive
     * @param mixed $erase=false in case of conflict it will write over the previous files
     * @param mixed $avoid=false update old file and write only files that are not in destination
     * @param mixed $key='' for following the process
     * 
     * @return bool true if success
     * 
     */
    public function unpack($path,$destination,$password='',$subfolder=false,$erase=false,$avoid=false,$key='')
    {
        if (!$this->check_path($destination,false,'write')){
            return 'check_path';
        }
        
        $switch='-y';
        
        $destination = '/'.trim($destination,'/').'/';
        
        if($subfolder) {
            $fileName = $this->get_file_name_noext($this->get_file_name($path));
            $destination.=$fileName.'/';
        }
        
        if($erase) $switch='-aoa';
        if($avoid) $switch='-aou';
        
        if($password != ''){
            $password =' -p'.$password;
        }else{
            $password =' -pNO';
        }
        
        $can_unpack = $this->unpack_test($path,$password);
        
        if ($can_unpack){
            $cmd = 'LC_ALL=C.UTF-8 stdbuf -o0 7z x -bsp1 '.$switch.' \''.$path.'\' -o\''.$destination.'\''.$password;
            
            $key = ($key == '') ? 'unpack-'.time() : $key;
            
            if ($this->follow_redis) {
                Utils::system_process_with_tracking($cmd, $key, 'pack', false, [$path]);
            } else {
                Utils::system_process_no_tracking($cmd, $key, 'pack', false, [$path]);
            }
            
            return 'ok';
        } else {
            return 'password';
        }
    }
    private function unpack_test($path,$pass){
        $ok = true;
        $descriptorspec = array(
            0 => array('pipe', 'r'),   // stdin is a pipe that the child will read from
            1 => array('pipe', 'w'),   // stdout is a pipe that the child will write to
            2 => array('pipe', 'w')    // stderr is a pipe that the child will write to
        );

        $cmd = 'stdbuf -o0 7z t "'.$path.'"'.$pass.' 2>&1';
        $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
        //~ $last_s = '';
        if (is_resource($process)) {
            
            $read = true;
            $test_loops = 0;
            
            while ($read){
                
                if(feof($pipes[1])) {
                    fclose($pipes[1]);
                    $read = false;
                } else {
                    
                    $s = trim(fgets($pipes[1],256));
                    
                    if ($s != ''){
                        if(strstr($s,'Testing')) $test_loops ++;

                        if (strstr($s,'Enter password') || strstr($s,'Wrong password') ) {
                            $ok = false;
                            fclose($pipes[1]);
                            $read = false;
                        }

                        if(strstr($s,'Testing') && !strstr($s,'CRC Failed') && $test_loops > 3){
                            fclose($pipes[1]);
                            $read = false;
                        }
                    }
                }
            }
            proc_close($process);
        } else {
            $ok = false;
        }
        
        return $ok;
    }

    /**
     * to add in the bin if you have already created  $CONFIG::ROOT_FS.'¤bin¤' folder
     *
     * @param mixed $paths
     * @param string $key
     * @param mixed $api=false
     * 
     * @return [type]
     * 
     */
    public function bin_add($paths, $key = '', $api=false)
    {
        global $DB, $USER, $CONFIG;
        
        if (!is_array($paths)){
            $paths = [$paths];
        }
        $time = round(microtime(true)*1000);
        if (!$key) $key = 'remove-'.$time;
        
        $destination = $CONFIG::ROOT_FS.'¤bin¤/';
        if (!file_exists($destination)){
            mkdir($destination);
        }
        
        if ($this->save_history){
            $q_h = 'INSERT INTO '.$DB->table('filesystem_history').' (`process_key`, `user_login`, `source`, `action`, `end_time`) VALUES ';
            $q_h_def = '';
            $q_h_params = [];
        }
        
        $q_bin = 'INSERT INTO '.$DB->table('filesystem_bin').' (id_users_data, name, path, bin_id) VALUES ';
        $q_bin_def = '';
        $q_bin_params = [];
        
        $err = [];
        $suc = [];
        
        $cmd_rm = [];
        $cmd = [];
        $toLock = [];
        $total = 0;

        $time = round(microtime(true)*1000);
        foreach($paths as $k => $path){
            $sequences = $this->get_sequence_data($path,'delete');
            if ($sequences){
                $path = $sequences['path'];
            } else {
                $path = $this->check_path($path,false,'delete');
            }
            if ($path){
                $paths[$k] = $path;
                
                $path = rtrim($path,'/');
                $parentPath = $this->get_file_path($path);
                $bin_id = md5($path).time();
                
                if ($sequences){
                    $bin_id.='-';
                    for($i=$sequences['first'];$i<=$sequences['last'];$i++){
                        $k = $i;
                        $k = str_pad($k,$sequences['digit'], '0', STR_PAD_LEFT);
                        $file = $sequences['basename'].$k.'.'.$sequences['ext'];
                        $newPath = $destination.str_replace($sequences['basename'], $bin_id, $file);
                        array_push($cmd, 'mv -v \''.$parentPath.$file.'\' \''.$newPath.'\'');
                        array_push($toLock, $parentPath.$file);
                        $total++;
                    }
                    $name = $sequences['basename'];
                } else {
                    $name = $this->get_file_name($path);
                    array_push($cmd, 'mv -v \''.$path.'\' \''.$destination.$bin_id.'\'');
                    array_push($toLock, $path);
                    $total++;
                }
                
                if (is_file($path) || $sequences){ // detect linked folder with ¤ in name
                    $cmd_ls = 'ls \'' . $parentPath . '\' | grep -i '.$this->clean_filter('¤'.$name.'¤');
                    $result_str = shell_exec($cmd_ls);
                    if ($result_str != ''){
                        $lst = explode("\n",trim($result_str));
                        foreach($lst as $index => $linked){
                            if ($linked != ''){
                                //~ $count++;
                                array_push($cmd_rm, 'rm -r \''.$parentPath.$linked.'\'');
                            }
                        }
                    }
                    if ($sequences) $name = $sequences['name'];
                }
                
                if ($this->save_history){
                    if ($api){
                        $q_h.='("", ?, ?, "delete", ?),';
                        $q_h_def .= 'ssi';
                        array_push($q_h_params, $USER->login, str_replace($CONFIG::ROOT_FS,'/',$path), $time);
                    } else {
                        $q_h.='(?, ?, ?, "delete", ?),';
                        $q_h_def .= 'sssi';
                        array_push($q_h_params, $key, $USER->login, str_replace($CONFIG::ROOT_FS,'/',$path), $time);
                    }
                }
                
                $q_bin.= '('.$USER->id.', ?, ?, ?),';
                $q_bin_def.= 'sss';
                array_push($q_bin_params, $name, str_replace($CONFIG::ROOT_FS,'/',$parentPath), $bin_id);

                array_push($suc, $parentPath.$name);
            } else {
                $err[str_replace($this->root_fs, '', $paths[$k])] = $this->last_error;
            }
        }
        if ($cmd_rm){
            if (count($cmd_rm) > 100){
                $chunks = array_chunk($cmd_rm, 100);
            } else {
                $chunks = [$cmd_rm];
            }
            foreach($chunks as $chunk){
                exec(implode(' && ', $chunk));
            }
        }
        if (count($cmd) > 100){
            $chunks = array_chunk($cmd, 100);
            $toLock = array_chunk($toLock, 100);
        } else {
            $chunks = [$cmd];
            $toLock = [$toLock];
        }
        $cmds = [];
        foreach($chunks as $chunk){
            $cmd = implode(' && ', $chunk);
            array_push($cmds, $cmd);
        }

        if ($this->follow_redis) {
            Utils::system_process_with_tracking($cmds, $key, 'lineCount', count($chunk), $toLock);
        } else {
            Utils::system_process_no_tracking($cmds, $key, 'lineCount', count($chunk), $toLock);
        }
        
        if ($this->save_history && $q_h_def != ''){
            $DB->prepared_query(substr($q_h, 0, -1), $q_h_def, $q_h_params);
        }
        if ($q_bin_def != '') $DB->prepared_query(substr($q_bin, 0, -1), $q_bin_def, $q_bin_params);
        
        return ['success'=>$suc, 'error'=>$err];
    }

    /**
     * Clean the trash bin
     *
     * @param string $key
     * 
     * @return bool true
     * 
     */
    public function bin_clear($key = '')
    {
        global $DB, $CONFIG;
        
        $time = round(microtime(true)*1000);
        if (!$key) $key = 'bin-'.$time;
        
        $path = $CONFIG::ROOT_FS.'¤bin¤/';
        
        $this->save_history = false;
        $del = $this->delete_with_follow($path, $key);
        $this->save_history = true;
        if ($del){
            $q = 'DELETE FROM '.$DB->table('filesystem_bin');
            $DB->query($q);
        }
    }

    /**
     * to restore a list of file out of the bin
     *
     * @param Array $lst of paths
     * @param string $key
     * 
     * @return bool ok
     * 
     */
    public function bin_restore($lst, $key = '')
    {
        global $DB,$USER,$CONFIG;
        
        $time = round(microtime(true)*1000);
        if (!$key) $key = 'restore-'.$time;
        
        if ($lst){
            $q = 'SELECT * FROM '.$DB->table('filesystem_bin').' WHERE bin_id IN (';
            $q_def = '';
            $q_params = [];
            foreach($lst as $bin){
                $q.='?,';
                $q_def.='s';
                array_push($q_params,$bin);
            }
            $q = substr($q,0,-1).')';
            $lst = $DB->prepared_query_list($q,$q_def,$q_params);
            if ($lst){
                $ids_to_del = [];
                $cmd = [];
                $toLock = [];
                foreach($lst as $key => $line){
                    array_push($ids_to_del, $line['id']);
                    if (str_ends_with($line['bin_id'], '-')){
                        $bin_path = $CONFIG::ROOT_FS.'¤bin¤/';
                        $basename = $this->get_sequence_base_name($line['name']);
                        $seq_path = $bin_path.str_replace($basename, $line['bin_id'], $line['name']);
                        $sequence = $this->get_sequence_data($seq_path);
                        $restore_path = $CONFIG::ROOT_FS.ltrim($line['path'],'/');
                        if ($sequence){
                            if (!is_dir($restore_path)){
                                mkdir($restore_path, 0755, true);
                            }
                            for($i=$sequence['first'];$i<=$sequence['last'];$i++){
                                $k = $i;
                                $k = str_pad($k,$sequence['digit'], '0', STR_PAD_LEFT);
                                $file = $sequence['basename'].$k.'.'.$sequence['ext'];
                                $seq_bin = $bin_path.$file;
                                $seq_rest = $restore_path.str_replace($line['bin_id'], $basename, $file);
                                array_push($cmd, 'mv -v \''.$seq_bin.'\' \''.$seq_rest.'\'');
                                array_push($toLock, $seq_rest);
                            }
                        }
                    } else {
                        $bin_path = $CONFIG::ROOT_FS.'¤bin¤/'.$line['bin_id'];
                        $restore_path = $CONFIG::ROOT_FS.ltrim($line['path'],'/');
                        if (!is_dir($restore_path)){
                            mkdir($restore_path, 0755, true);
                        }
                        $restore_path.= $line['name'];
                        array_push($cmd, 'mv -v \''.$bin_path.'\' \''.$restore_path.'\'');
                        array_push($toLock, $restore_path);
                    }
                }
            }
            $count = count($cmd);
            if ($count > 100){
                $chunks = array_chunk($cmd, 100);
                $toLock = array_chunk($toLock, 100);
            } else {
                $chunks = [$cmd];
                $toLock = [$toLock];
            }
            $cmds = [];
            foreach($chunks as $chunk){
                // $cmd = implode(' && ', $chunk).' | pv -f -l -s '.count($chunk);
                $cmd = implode(' && ', $chunk);
                array_push($cmds, $cmd);
            }

            if ($this->follow_redis) {
                Utils::system_process_with_tracking($cmds, $key, 'lineCount', $count, $toLock);
            } else {
                Utils::system_process_no_tracking($cmds, $key, 'lineCount', $count, $toLock);
            }
            
            
            $q = 'DELETE FROM '.$DB->table('filesystem_bin').' WHERE id IN ('.implode(',',$ids_to_del).')';
            $DB->query($q);
            
            return true;
        }
    }

    /**
     * delete definitly from the bin
     *
     * @param array $lst of paths
     * @param mixed $key
     * @param bool $api
     * 
     * @return [type]
     * 
     */
    public function bin_remove($lst, $key, $api = false)
    {
        global $DB;
        
        // retrieve licenses in list
        $q_seq = 'SELECT * FROM '.$DB->table('filesystem_bin').' WHERE bin_id IN (';
        $q = 'DELETE FROM '.$DB->table('filesystem_bin').' WHERE bin_id IN (';
        $q_def = '';
        $q_params = [];
        foreach($lst as $path){
            $bin = $this->get_file_name($path);
            $q.='?,';
            $q_seq.='?,';
            $q_def.='s';
            array_push($q_params, $bin);
        }
        $q = substr($q,0,-1).')';
        $q_seq = substr($q_seq,0,-1).') AND RIGHT(bin_id,1)="-"';
        $sequences = $DB->prepared_query($q_seq,$q_def,$q_params);
        $DB->prepared_query($q,$q_def,$q_params);
        foreach($sequences as $seq){
            foreach($lst as $key => $path){
                $name = $this->get_file_name($path);
                if ($name == $seq['bin_id']){
                    $basename = $this->get_sequence_base_name($seq['name']);
                    $lst[$key] = str_replace($name, str_replace($basename, $name, $seq['name']), $path);
                }
            }
        }
        
        $res = $this->delete_with_follow($lst, $key, $api);
        return $res;
    }
    /**
     * A little function to replace is_file is_folder is_link 
     *
     * @param string $path
     * 
     * @return bool
     * 
     */
    public function exist($path){
        $exist = false;
        $res = shell_exec('ls "'.$this->get_file_path($path).'"');
        if ($res){
            $fileName = $this->get_file_name($path);
            $lst = preg_split("/\n+/", trim($res));
            if (in_array($fileName, $lst)){
                $exist = true;
            }
        }
        return $exist;
    }
}
