<?php
/* 
 * @copyright
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
use Config;
 /**
 * @class Ajax 
 * 
 * This class is used to handle Ajax requests, file uploads, and data processing.
 * It provides methods to process data, handle file uploads, and manage storage status.
 * it's working on par with the js lib ajax.js (see helphp/js/ajax.js) 
 * 
 * @package helPHP\libs
 */
class Ajax
{
    const json_list_identifier = '_AJAX_json_decode_list';
    const json_data_identifier = '_AJAX_json_decode_data';

    /**
     * default max upload size in bytes (1.6 GB)
     * this value can be overridden by the configuration file
     * 
     * @var int
     * 
     * @see Ajax\file_upload_max_size()
     * 
     */
    public static $max_upload_size = 1677721600;
    
    /**
     * default max number of file to upload in a single request
     * this value can be overridden by the configuration file
     * 
     * @var int
     * 
     * @see Ajax\file_upload_max_count()
     * 
     */

    public static $max_file_uploads = 20;

    /**
     * default storage quota in bytes (5 GB)
     * this value is generaly overridden by the configuration file main.php in the "config" instance folder
     * 
     * @var int
     * 
     */
    public static $stockage_quota = Config::STORAGE_COTA; // 5368709120; // 5 GB
    
    /**
     * current storage usage in bytes
     * this value is updated by the file_upload_max_size() function
     * 
     * @var int
     * 
     * @see Ajax\file_upload_max_size()
     * 
     */
    public static $stockage_usage = 0;
    
    
    /**
     * current storage usage percentage
     * this value is updated by the file_upload_max_size() function
     * and can be used to display the storage usage in the UI
     * 
     * @var int
     * 
     * @see Ajax\file_upload_max_size()
     * 
     */
    public static $stockage_percentage = 0;
    
    /**
     * current storage left in bytes
     * this value is updated by the file_upload_max_size() function
     * and can be used to display the storage left in the UI
     * 
     * @var int
     * 
     * @see Ajax\file_upload_max_size()
     * 
     */
    public static $stockage_left =5368709120;
    
    /**
     * default uploads directory, that should normaly not be used, because in the process_files() function,
     * we specify the destination directory for the uploaded files. and "uploads" is too obvious for an upload directory,
     * and can be targeted by hackers.
     * without this directory in the instance, it will trig an error in the process_files() function if no destination directory is specified.
     * so it is a good practice to do not create this directory and keep it like that as a reminder to specify a correct destination folder.
     * 
     * @var string
     */
    private static $uploads_dir = 'uploads';

    public function __construct()
    {
        global $CONFIG;
        if ($CONFIG::BASE_URL != '') {
            Ajax::origin_security_test();
        }
    }

    /**
     * This function checks the HTTP_ORIGIN header against a list of accepted origins.
     * do not forget to set the BASE_URL in the configuration file main.php
     * 
     * @return bool true if the origin is accepted, false if not
     * 
     */
    public static function origin_security_test()
    {
        global $CONFIG;
        $accepted_origins = array($CONFIG::BASE_URL , 'http://localhost', 'https://localhost');

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // same-origin requests won't set an origin. If the origin is set, it must be valid.
            if (in_array($_SERVER['HTTP_ORIGIN'], $accepted_origins)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            } else {
                header('HTTP/1.0 403 Origin Denied');
                return false;
            }
        }

        return true;
    }

    public static function process_all_data()
    {
        if (isset($_POST)) {
            $_POST = Ajax::process_data($_POST);
        }
        if (isset($_GET)) {
            $_GET = Ajax::process_data($_GET);
        }
        if (isset($_REQUEST) && !isset($_GET) && !isset($_POST)) {
            $_REQUEST = Ajax::process_data($_REQUEST);
        }
    }

    /**
     *This function retrieves server variables related to storage and file upload limits,
     * and formats them as JavaScript variables for use in the client-side code.
     * Those variables can be used to display storage information in the UI or to handle file uploads.
     * and to allow upload depending on the storage left
     * 
     * @return string containing the server variables as JavaScript variables
     */
    public static function get_server_variables_as_js()
    {
        Ajax::$max_upload_size = Ajax::file_upload_max_size();
        $str = 'h_storage = {};'.PHP_EOL;
        $str.= 'h_storage.quota = '.Ajax::$stockage_quota.';'.PHP_EOL;
        $str.= 'h_storage.usage = '.Ajax::$stockage_usage.';'.PHP_EOL;
        $str.= 'h_storage.percentage = '.Ajax::$stockage_percentage.';'.PHP_EOL;
        $str.= 'h_storage.left = '.Ajax::$stockage_left.';'.PHP_EOL;
        $str.= 'h_storage.max_upload_size = '.Ajax::$max_upload_size.';'.PHP_EOL;
        $str.= 'h_storage.max_file_uploads = '.Ajax::$max_file_uploads.';'.PHP_EOL;
        // Utils::error_log($str);
        return $str;
    }
    
    /**
     * This function updates the storage status by writing the current server variables
     * related to storage and file upload limits into a JavaScript file (storages.js) for instance js side.
     * 
     * @return void
     */
    public static function update_storage_status(){
        global $CONFIG,$FS;

        $output = Ajax::get_server_variables_as_js();

        //writing the file
        $FS->save_content($CONFIG::HOME_FOLDER.'js/storages.js', $output);
    }

    /**
     * This function processes the input data by decoding JSON strings
     * that are identified by the Ajax::json_list_identifier key
     * 
     * @param null $data
     * 
     * @return array processed data
     */
    public static function process_data($data = null)
    {
        if ($data === null) {
            $data = $_POST;
        }

        if (isset($data[Ajax::json_list_identifier])) {
            $tmp = json_decode($data[Ajax::json_list_identifier]);
            if (is_array($tmp)) {
                $toArray = isset($data[Ajax::json_list_identifier.'_mode']) && $data[Ajax::json_list_identifier.'_mode'] == 2;
                foreach ($tmp as $varName) {
                    if (isset($data[$varName])) {
                        $data[$varName] = json_decode($data[$varName], $toArray);
                    }
                }
            }
            unset($data[Ajax::json_list_identifier]);
            unset($data[Ajax::json_list_identifier.'_mode']);
        }

        return $data;
    }

    /**
     * Description for process_tinymce_upload
     *
     * @param null $destination_dir
     * 
     * @return json JSON encoded string with the location of the uploaded file
     * This function processes file uploads specifically for TinyMCE editor.
     * It handles only image uploads (gif, jpg, png, jpeg, svg).
     * 
     * @see helPHP\libs\Tinymce\get_init_javascript() for details on TyniMCE integration.
     * 
     */
    public static function process_tinymce_upload($destination_dir = null)
    {
        $files = Ajax::process_files($destination_dir, array("gif", "jpg", "png", "jpeg", "svg"));

        if (is_array($files) && sizeof($files) > 0) {
            $file = current($files);
            return json_encode(array('location' => $file['path']));
        }

        return '';
    }

    
    /**
     * This function exports data as a JSON response.
     *
     * @param mixed $data
     * 
     * @return string JSON encoded string
     * 
     */
    public static function export_json_data($data)
    {
        header('Content-Type: application/json');

        echo json_encode($data);
    }

    /**
     * Put the received files in the given destination folder
     * This function processes file uploads from the $_FILES superglobal.
     * It handles both single and multiple file uploads, checks for valid file names and extensions
     * and the rights to upload to the destination directory.
     *
     * @param mixed $destination_dir=null (must be specified, otherwise the default uploads directory will be used)
     * @param null $extensions array with all authorized files extensions
     * @param null $chunk
     * 
     * @return array containing all files paths and size associated to original variables names
     * exemple:
     * array(
     *          VARNAME_A => array( array('path' => 'images/photo1' , size => 14543) , array('path' => 'images/photo2' , size => 637684) ) ,
     *          VARNAME_B => array( array('path' => 'images/photo3' , size => 987457) )
     *      );
     * 
     */
    public static function process_files($destination_dir = null, $extensions = null, $chunk = null)
    {
        global $FS, $CONFIG;
        $files = array();
        $errors = array();
        if (!isset($_FILES)) return $files;

        if ($destination_dir === null) {
            $destination_dir = Ajax::$uploads_dir;
        }

        $delete_all = false;

        $destination_dir = trim($destination_dir);
        if (strpos($destination_dir, '../')!==false) {
            Utils::error_log('FORBIDDEN PATH all files removed ! '.$destination_dir);
            $delete_all = true;
        }
        
        if (!str_starts_with($destination_dir, $CONFIG::HOME_FOLDER) && !str_starts_with($destination_dir, $CONFIG::ROOT_FS)){
            $destination_dir = $CONFIG::HOME_FOLDER.trim($destination_dir, '/');
        }
        if (!is_dir($destination_dir)) {
            if (!$FS->mkdir($destination_dir)) {
                Utils::error_log('ERROR while creating folder '.$destination_dir);
            }
        }

        $destination_dir = str_ends_with($destination_dir,'/') ? $destination_dir : $destination_dir.'/';

        foreach ($_FILES as $varname => $data) {
            if (isset($_FILES[$varname]['error']) && is_array($_FILES[$varname]['error'])) {
                // multiples files are received from a single input
                foreach ($_FILES[$varname]['error'] as $key => $error) {
                    if ($_FILES[$varname]['name'][$key] !== '') {
                        $filename = $FS->get_file_name($_FILES[$varname]['name'][$key]);
                        $ext = strtolower($FS->get_file_ext($filename));
                        if ($chunk !== null) $filename.='-'.$chunk;
                        if ($error == UPLOAD_ERR_OK) {
                            if ($delete_all) {
                                unlink($_FILES[$varname]['tmp_name'][$key]);
                                Utils::error_log('Delete '.$filename);
                            } elseif (Ajax::check_filename($filename) && Ajax::check_file_extension($filename, $extensions)) {
                                $path = $destination_dir . $filename;
                                $res = move_uploaded_file($_FILES[$varname]['tmp_name'][$key], $path);

                                if ($res){
                                    if (!isset($files[$varname])) {
                                        $files[$varname] = array();
                                    }

                                    array_push($files[$varname], array('path'=>$path , 'size'=>$_FILES[$varname]['size'][$key]));
                                } else {
                                    \helPHP\libs\Utils::error_log('Can\'t write to '.$destination_dir.' folder see write right');
                                    return 'write_access';
                                }
                                
                            } else {
                                unlink($_FILES[$varname]['tmp_name'][$key]);
                                Utils::error_log('Invalid name on file upload :'.$filename);
                                
                            }
                        } else {
                            Utils::error_log('an error occured during upload, error code is '.$error);
                            Utils::error_log('Error on file upload ' . $filename);
                        }
                    }
                }
            } else {
                if ($data['name'] !== '') {
                    $name = $FS->get_file_name($data['name']);
                    $ext = strtolower($FS->get_file_ext($data['name']));
                    if ($chunk !== null) $name.='-'.$chunk;
                    
                    if ($delete_all) {
                        unlink($data['tmp_name']);
                    } elseif (Ajax::check_filename($name) && Ajax::check_file_extension($name, $extensions)) {
                        if ($data['error'] == UPLOAD_ERR_OK) {
                            $path = $destination_dir.$name;
                            $res = move_uploaded_file($data['tmp_name'], $path);
                            if ($res === true) {
                                if (!isset($files[$varname])) {
                                    $files[$varname] = array();
                                }

                                array_push($files[$varname], array('path'=>$path , 'size'=>$data['size']));
                            } else {
                                
                                \helPHP\libs\Utils::error_log('Can\'t write to '.$destination_dir.' folder see write access');
                                return 'write_access';
                            }
                        } else {
                            Utils::error_log('an error occured during upload, error code is '.$data['error']);
                            if (file_exists($data['tmp_name'])){
                                unlink($data['tmp_name']);
                            }
                        }
                    } else {
                        Utils::error_log('Invalid name on file upload :'.$name);
                        return 'invalid_name';
                    }
                }
            }
        }

        return $files;
    }
    
    /**
     * Move the files from the temporary folder to the specified relative path.
     *
     * @param bool $relativePath
     * @param bool $fileLst 
     * @param null $extensions
     * 
     * @return array|bool
     * 
     */
    public static function move_from_temp($relativePath = false, $fileLst = [], $extensions = null)
    {
        global $FS,$CONFIG;
        
        if (!$relativePath || !$fileLst){
            return false;
        }
        
        $tempPath = $CONFIG::HOME_FOLDER.'temp/';
        $relativePath = '/' . trim($relativePath, '/') . '/';
        
        $result = [];
        if($fileLst!=false){
            foreach($fileLst as $key => $filePath){
                $name = $FS->get_file_name($filePath);
                if (Ajax::check_file_extension($name, $extensions)){
                    $filePath = ltrim($filePath, '/');
                    if (str_contains($filePath,'/')){
                        $tempPath = $CONFIG::HOME_FOLDER.'temp/'.$name;
                        if (str_contains($name, '!h!e!l!P!H!P!')) $name = substr($name, 0, strpos($name, '!h!e!l!P!H!P!'));
                        $path = $relativePath.$FS->get_file_path($filePath).$name;
                    } else {
                        $tempPath = $CONFIG::HOME_FOLDER.'temp/'.$name;
                        if (str_contains($name, '!h!e!l!P!H!P!')) $name = substr($name, 0, strpos($name, '!h!e!l!P!H!P!'));
                        $path = $relativePath.$name;
                    }
                    if (rename($tempPath, $path)){
                        exec('chmod 775 \''.$path.'\'');
                        array_push($result, $path);
                    } else {
                        Utils::error_log('RENAME FAILED FOR '.$tempPath.' to '.$path);
                    }
                } else {
                    Utils::error_log('ERROR! wrong file extension for '.$name);
                    return false;
                }
            }
        }
        return $result;
    }
    
    /* 
     * name : name of the file
     * chunk_number : number total of chunk
     * unique_id : identifiant unique
     */
    /**
     * As the name suggests, this function merges the chunks of a file that were uploaded in parts.
     * It takes the name of the file, the total number of chunks, a unique identifier
     * and a boolean to indicate whether to update the storage status.
     *
     * @param string $name
     * @param int $chunk_number
     * @param string $unique_id
     * @param bool $update_storage
     * 
     * @return string a json encoded string with the missing slices if any, or 'ok' if all chunks were successfully merged.
     * 
     */
    public static function merge_chunks($name = '', $chunk_number = 0, $unique_id = '', $update_storage = true)
    {
        global $CONFIG;
        if (!$name || !$chunk_number) return false;
        
        $missing_slice =  [];
        
        $tempFolder = $CONFIG::HOME_FOLDER.'temp/';
        $dst = fopen($tempFolder.$name.'!h!e!l!P!H!P!'.$unique_id, 'wb'); // on ouvre le stream du fichier complet
        for ($i = 0; $i < $chunk_number; $i++) {
            $slice = $tempFolder.'chunk_'.$name.$unique_id.'/'.$name.'-'.$i; // le nom du chunk courant
            if (is_file($slice)){
                // on lit le slice
                $src = fopen($slice, 'rb');
                // on le copie
                stream_copy_to_stream($src, $dst);
                // on le ferme
                fclose($src);
            } else {
                // missing slice
                array_push($missing_slice, $i);
            }
        }
        // on ferme le stream du fichier complet
        fclose($dst);
        if (count($missing_slice) == 0){
            shell_exec('rm -R '.$tempFolder.'chunk_'.$name.$unique_id.'/');
            if ($update_storage) Ajax::update_storage_status();
            return 'ok';
        } else {
            return json_encode($missing_slice);
        }
    }
    
    public static function check_filename($name)
    {   global $FS;
        if (!$FS->check_name($name)) {
            header("HTTP/1.0 500 Invalid file name.");
            return false;
        }
        return true;
    }

    public static function check_file_extension($name, $extensions)
    {
        if (is_array($extensions)) {
            if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions)) {
                header("HTTP/1.0 500 Invalid extension.");
                return false;
            }
        }
        return true;
    }

    public static function file_upload_max_count()
    {
        Ajax::$max_file_uploads = intval(ini_get('max_file_uploads'));
        return Ajax::$max_file_uploads;
    }

    /**
     * it will calculate the state of the storage and the maximum file upload size possible.
     * This function checks the PHP configuration settings for post_max_size and upload_max_filesize,
     * For js and php process.
     *
     * @return int the maximum file upload size in bytes
     * 
     */
    public static function file_upload_max_size()
    {
        static $max_size = -1;

        if ($max_size < 0) {
            // Start with post_max_size.
            $max_size = Ajax::parse_size(ini_get('post_max_size'));

            // If upload_max_size is less, then reduce. Except if upload_max_size is
            // zero, which indicates no limit.
            $upload_max = Ajax::parse_size(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
            if (Ajax::$max_upload_size > $max_size) {
                $max_size=Ajax::$max_upload_size;
            }
            global $FS;
            $storageInfos=$FS->home_du();
            if(Ajax::$stockage_quota<$storageInfos[0]){
                Ajax::$stockage_quota=$storageInfos[0];
            }
            Ajax::$stockage_usage=$storageInfos[1];
            Ajax::$stockage_percentage=$storageInfos[2];
            Ajax::$stockage_left=$storageInfos[3];
            if ($max_size > $storageInfos[3]) {
                $max_size = $storageInfos[3];
            }
        }
        Ajax::$max_upload_size = $max_size;
        return $max_size;
    }

    private static function parse_size($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
}