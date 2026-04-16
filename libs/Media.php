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
 * @class Media
 * 
 * The media class take care of all images and video format compatible with navigators in multiple aspects.
 * To use it, you need the GD library at minimum (which is distributed with PHP in general) and if you want to do some
 * process on videos, you'll need to install a recent ffmpeg or make run your HelPHP with our docker container for helPHP tagged with ffmpeg.
 * 
 * Note that a media can be instanciated, and called with its identifier from multiple place of your project.
 * When you call the delete method on a media, it will be totaly deleted if no more referenced in the media_use table.
 * 
 * Note that there is also a media module, which is offerring an UI for this class to offer multiple features :
 * the upload, some modifications on images, a media galery to permit exchange and cloning, but also the streaming of video images and files,
 * some anti-download features on video and security.
 * 
 * so it's better to manage medias directly thru the media module, but the Media class is often used from any module to resize, format, manipulate medias.
 * You can set all process to do as an automated process in a "process" array before calling media admin module to do the same process for every upload, but this
 * system can be used from anywhere.
 * 
 * Send_file is the main streaming function, it support all kind of streaming operations
 * 
 * The global $MEDIA is available from init which is calling create_instance.
 * 
 * Take a look on the training dedicated to media to use correctly this class and Media module
 * 
 * @package helPHP\libs
 *   
 */
class Media {

    /**
     * audio extentions accepted, used to filter uploads
     *
     * @var array
     */
    const audio_ext = ['mp3', 'm4a', 'aac', 'oga', 'ogg', 'wav', 'flac'];
    /**
     * image extentions accepted, used to filter uploads
     *
     * @var array
     */
    const image_ext = ['jpg', 'jpeg', 'png', 'bmp', 'gif','svg','svgz','webp'];
    /**
     * video extentions accepted, used to filter uploads
     *
     * @var array
     */
    const video_ext = ['mp4','ogg','avi','mpeg','mts','wmv','qt','mov','mpg','mkv', 'ogv','ts','m4v','mxf','webm'];

    public $current_media = '';
    
    private $process_image = false;
    private $process_video = false;
    private $is_svg = false;
    private $video_info = false;
    
    public $base_path;
    private $db_data, $db_process, $db_use;

    /**
     * Create the global $MEDIA instance of this class
     *
     * @param bool $forceNewInstance to be sure that $MEDIA is restarted from scractch
     * 
     * @return global $MEDIA
     * 
     */
    public static function create_instance($forceNewInstance = false){
        global $MEDIA;
        if ($MEDIA != null && $forceNewInstance == false) {
            return $MEDIA;
        }
        $MEDIA = new Media();
        return $MEDIA;
    }
    
    public function __construct(){
        global $CONFIG;
        $this->base_path = $CONFIG::HOME_FOLDER.'files/';

        global $DB;
        $this->db_data = $DB->table('media_data');
        $this->db_process = $DB->table('media_process');
        $this->db_use = $DB->table('media_use');
    }

    //-----------------------------------------------------------------------------
    // media and db manipulation
    //-----------------------------------------------------------------------------
    /**
     * Process and save media after upload or from a media list.
     *
     * Handles the processing of images and videos, including moving uploaded files,
     * applying transformations (resize, crop, rotate, etc.), saving processed files,
     * and updating the database with media and usage information. Also manages
     * replacement of existing media, handling of multiple files, and selection of
     * media from an existing list.
     *
     * @param array $post   The POST data containing media information and files.
     * it can contain the following keys:
     * - media_data (array) : Associative table of media to be processed, indexed by identifier or logical field.
     *      Each entry is an array that can contain: :
     *      - files (array) : Uploaded files (ex: $_FILES-like structure, or temporary path array)...
     *      - list (array)  : List of existing media identifiers to associate.
     *      - process (array) : Table of operations to apply (ex: [['type'=>'image_resize', ...], ...]).
     *      - replace (bool|int) : Should the media replace the existing one ?
     *      - other fields customized according to the call context.
     * - media_replace_all (int|bool) : replaces all existing media related to the entity.
     * 
     * @param mixed $created_id Optional. If provided, used to update the media identifier.
     *
     * @return bool Returns true if all media were processed successfully, false if errors occurred.
     */
    public function process_media($post, $created_id = false){
        global $USER,$FS;
        // Utils::error_log($post);
        $errors = false;

        if (!isset($post['media_data'])) {
            return true;
        }

        $replace_all = false;
        if (isset($post['media_replace_all']) && $post['media_replace_all'] == 1){
            $replace_all = true;
        }
        
        foreach($post['media_data'] as $media_id => $data){

            // detect if there is a new image in this media_data
            if (!isset($data['files']) && (!isset($data['list']) || !$data['list'])) continue;

            $this->process_video = false;
            $this->process_image = false;
            $this->is_svg = false;
            $this->video_info = false;

            $process = isset($_SESSION['media_list'][$media_id]['process']) ? json_decode($_SESSION['media_list'][$media_id]['process'], true) : false;

            if ($created_id !== false){
                $media_id = explode('¤', $media_id)[0].'¤'.$created_id;
            }

            $use_key = -1;
            if (isset($data['multiple']) && $data['multiple']){
                $use_key = $this->get_last_use_key($media_id);
            } else if (!$replace_all){
                $t = explode('¤', $media_id);
                $field_identifier = $t[0];
                $field_id = $t[1];
                $this->delete_media_strict($field_identifier, $field_id, 0);
            }
            
            if (isset($data['files'])){
                $uploaded_files = [];
                if (!is_array($data['files'])) $data['files'] = [$data['files']];
                foreach($data['files'] as $filename){
                    foreach($post['lstFilePath'] as $key => $tmp_filename){
                        if (str_starts_with($tmp_filename, $filename)) array_push($uploaded_files, $tmp_filename);
                    }
                }
                if ($uploaded_files) {
                    $module_name = explode('_', $media_id)[0];
                    $path = $this->base_path.$module_name.'/'.$USER->id.'/';
                    if (!is_dir($path)) {
                        $res = $FS->mkdir($path);
                        if (!$res) {
                            $log = 'problem when creating the folder '.$path.PHP_EOL;
                            $log.= 'can\'t process following file '.PHP_EOL;
                            $log.= json_encode($data['files']).PHP_EOL;
                            Utils::error_log($log);
                            $errors = true;
                            continue;
                        }
                    }

                    $files = Ajax::move_from_temp($path, $uploaded_files);
                    if ($files){
                        // launch the process !
                        foreach($files as $file_path){
                            $media_processed = false;
                            $use_key++;
                            if (in_array(strtolower($FS->get_file_ext($file_path)), self::image_ext)) {
                                if ($process){
                                    if (isset($process['image'])) {
                                        unset($process['video']);
                                        foreach ($process['image'] as $key => $val) {
                                            $process[$key] = $val;
                                        }
                                        unset($process['image']);
                                    }                                    
                                } else {
                                    // create a simple process to save the image as an original
                                    $process = [];
                                    $process['original'] = true;
                                }
                                $process['media_id'] = $media_id;
                                $process['input'] = $file_path;
                                $process['use_key'] = $use_key;

                                $this->image_process($process);
                                $media_processed = true;
                            }
                            if (in_array(strtolower($FS->get_file_ext($file_path)), self::video_ext)) {
                                if ($process){
                                    if (isset($process['video'])) {
                                        unset($process['image']);
                                        foreach ($process['video'] as $key => $val) {
                                            $process[$key] = $val;
                                        }
                                        unset($process['video']);
                                    }
                                } else {
                                    $process = [];
                                }
                                $process['input'] = $file_path;
                                $process['use_key'] = $use_key;
                                $process['pid'] = 'video'.$media_id;
                                $process['media_id'] = $media_id;
                                $this->video_process($process);
                                $media_processed = true;
                            }

                            // at this point, if the media is not processed, it's not a image or a video, will saved the audio for future implementation
                            if (!$media_processed){
                                $filename = $FS->get_file_name($file_path);
                                
                                $ext = $FS->get_file_ext($file_path);
                                $type = in_array($ext, self::audio_ext) ? 3 : 0;

                                // // change name of the file
                                // // $new_path = str_replace($FS->get_file_name_noext($file_path), $media_id.'¤'.$use_key.'¤'.$key, $file_path);
                                // $new_name = $media_id.'¤'.$use_key.'¤'.$key.'.'.$ext;
                                // $new_path = Filesystem::replace_file_name($file_path, $media_id.'¤'.$use_key.'¤'.$key, $file_path);
                                // // Utils::error_log($process);
                                // if ($process && isset($process['no_rename']) && $process['no_rename']) {
                                //     $new_name = $filename;
                                // }
                                // if ($process && isset($process['path'])){
                                //     // a different path is set, need to move the file to wanted path
                                //     // $FS->move($file_path, $process['path']);
                                //     $new_path = $process['path'].$new_name;
                                //     if (!\file_exists($process['path'])){
                                //         $FS->mkdir($process['path']);
                                //     }
                                // }
                                
                                // // Utils::error_log('move from '.$file_path.' to '.$new_path);
                                // $FS->move([['path'=>$file_path, 'name'=>$new_name]], $FS->get_file_path($new_path), true);
                                // shell_exec('chmod 2774 "'.$new_path.'"');
                                
                                // change name of the file
                                // $new_path = str_replace($FS->get_file_name_noext($file_path), $media_id.'¤'.$use_key.'¤'.$key, $file_path);
                                $new_path = Filesystem::replace_file_name($file_path,$media_id.'¤'.$use_key.'¤'.$key, $file_path);
                                $new_name = $media_id.'¤'.$use_key.'¤'.$key.'.'.$ext;
                                $FS->move([['path'=>$file_path, 'name'=>$new_name]], $FS->get_file_path($file_path), true);
                                shell_exec('chmod 2774 "'.$new_path.'"');

                                $id_media = $this->save_media($media_id, 0, 0, $new_path, $filename, 1, $type);
                                $this->save_use($media_id, $use_key, 0, $id_media, 0);
                            }
                        }
                    }
                }
            }
            
            // media selected from the list
            if (isset($data['list']) && $data['list']) {
                global $DB;

                $data['list'] = json_decode(stripslashes($data['list']), true);
                // Utils::error_log($data['list']);
                
                foreach($data['list'] as $line){
                    $use_key++;
                    $q = 'SELECT * FROM '.$this->db_use.' WHERE field_identifier=? AND use_key=?';
                    $medias = $DB->prepared_query($q, 'si', [$line['media_id'],$line['use_key']]);
                    foreach($medias as $media){
                        $q = 'INSERT INTO '.$this->db_use.' SET field_identifier=?, use_key=?, process_key=?, id_media=?, id_process=?, share=1';
                        $DB->prepared_query($q,'siiii', [$media_id,$use_key,$media['process_key'],$media['id_media'],$media['id_process']]);
                    }
                }
            }

        }

        return !$errors;
    }
    /**
     * Save the process done on a file in case we must redo
     *
     * @param string $process
     * 
     * @return int id in DB
     * 
     */
    public function save_process($process){
        global $DB;

        $q = 'SELECT DISTINCT p.id FROM '.$this->db_process.' p INNER JOIN '.$this->db_use.' u';
        $q.=' ON u.field_identifier=? AND u.use_key=? AND u.id_process=p.id';
        $id = $DB->prepared_query_value($q, 'si', [$process['media_id'], $process['use_key']]);

        if (!$id) {

            $q ='INSERT INTO '.$this->db_process.' SET process=?';
            $r = $DB->prepared_query($q,'s',[json_encode($process)]);
            $id = $DB->last_insert_id();

        } else {

            $q ='UPDATE '.$this->db_process.' SET process=? WHERE id='.intval($id);
            $r = $DB->prepared_query($q,'s',[json_encode($process)]);

        }

        return $id;
    }
    /**
     * This method inserts or updates a record in the media_data table for the given media file,
     *
     * It check that the path is relative to the base media directory.
     * If the media already exists for the given identifiers, it updates the record instead of inserting.
     *
     * Also saves the filename as a short translation value for the current language.
     *
     * @param string $media_id   The logical media identifier (ex: 'flag¤123').
     * @param int    $use_key    The use key for this media (for multi-use cases).
     * @param int    $process_key The process key (for processed versions), or 0 for original.
     * @param string $path       The file path (relative to the base media directory).
     * @param string $filename   The original filename of the media.
     * @param int    $original   1 if this is the original file, 0 otherwise.
     * @param int    $type       The media type (1=image, 2=video, etc.).
     *
     * @return int   The ID of the media record in the database.
     */
    public function save_media($media_id, $use_key, $process_key, $path, $filename, $original=0, $type=0){
        global $DB, $LANG;

        $path = str_replace($this->base_path, '', $path);

        $q = 'SELECT DISTINCT d.id FROM '.$this->db_data.' d';
        $q.=' INNER JOIN '.$this->db_use.' u';
        $q.=' ON u.field_identifier = ?';
        $q.=' AND u.use_key = ?';
        $q.=' AND u.process_key=?';
        $q.=' AND u.id_media = d.id';
        $q.=' WHERE d.original=?';
        $id = $DB->prepared_query_value($q, 'siii', [$media_id, $use_key, $process_key, $original]);

        if (!$id) {
            $q ='INSERT INTO '.$this->db_data.' SET original=?, type=?, path=?';
            if ($this->process_image && !$this->is_svg) {
                $q.=' , width='.imagesx($this->current_media);
                $q.=' , height='.imagesy($this->current_media);
            }
            if ($this->process_video){
                $q.=' , width='.$this->video_info['width'];
                $q.=' , height='.$this->video_info['height'];
                $q.=' , fps='.$this->video_info['fps'];
                $q.=' , duration='.$this->video_info['duration'];
            }
            $DB->prepared_query($q,'iis', [$original, $type, $path]);
            $id = $DB->last_insert_id();
        } else {
            $q ='UPDATE '.$this->db_data.' SET type=?, path=?';
            if ($this->process_image && !$this->is_svg) {
                $q.=' , width='.imagesx($this->current_media);
                $q.=' , height='.imagesy($this->current_media);
            }
            if ($this->process_video){
                $q.=' , width='.$this->video_info['width'];
                $q.=' , height='.$this->video_info['height'];
                $q.=' , fps='.$this->video_info['fps'];
                $q.=' , duration='.$this->video_info['duration'];
            }
            $q.=' WHERE id='.intval($id);
            
            $DB->prepared_query($q,'is', [$type, $path]);
        }

        $lang_data = $LANG->get_languages_data();
        foreach($lang_data as $lang){
            Language::save_short_translation_value('media_data-filename', $id, explode('.',$filename)[0], $lang['id_data']);
        }

        return $id;
    }
    /**
     * Saves or updates a usage record for a media file in the database.
     *
     * Associates a media file with an identifier, use_key, and process_key.
     * If it already exists , it updates the media and process IDs.
     *
     * @param string $media_id   The logical media identifier (e.g., 'flag¤123').
     * @param int    $use_key    The use key for this media (for multi-use cases).
     * @param int    $process_key The process key (for processed versions), or 0 for original.
     * @param int    $id_media   The ID of the media in the media_data table.
     * @param int    $id_process The ID of the process in the media_process table.
     *
     * @return void
     */
    public function save_use($media_id, $use_key, $process_key, $id_media, $id_process){
        global $DB;

        $q = 'SELECT id FROM '.$this->db_use.' WHERE field_identifier=? AND use_key=? AND process_key=?';
        $id = $DB->prepared_query_value($q, 'sii', [$media_id, $use_key, $process_key]);
        if (!$id){
            $q = 'INSERT INTO '.$this->db_use.' SET field_identifier=?, use_key=?, process_key=?, id_media=?, id_process=?';
            $DB->prepared_query($q,'siiii',[$media_id, $use_key, $process_key, $id_media, $id_process]);
        } else {
            $q = 'UPDATE '.$this->db_use.' SET id_media=?, id_process=? WHERE id='.$id;
            $DB->prepared_query($q,'ii',[$id_media, $id_process]);
        }

    }

    /**
     * Copies a usage record from one media identifier to another.
     *
     * Duplicates all entries in the media_use table for the given source media ID to the target media ID (including language media).
     *
     * @param string $media_id_to_copy The source logical media identifier.
     * @param string $media_id         The target logical media identifier.
     *
     * @return bool True on success, false if no usage found.
     */
    public function copy_use($media_id_to_copy, $media_id){
        global $DB;

        $t = explode('¤', $media_id_to_copy);
        $field_identifier_to_copy = $t[0];
        $field_id_to_copy = $t[1];

        $t = explode('¤', $media_id);
        $field_identifier = $t[0];
        $field_id = $t[1];

        $q = 'SELECT * FROM '.$this->db_use.' WHERE field_identifier=? OR field_identifier LIKE ?';
        $uses = $DB->prepared_query_list($q, 'ss', [$media_id_to_copy, $field_identifier_to_copy.'-__¤'.$field_id_to_copy]);
        if (!$uses) return false;
        foreach($uses as $use){
            $new_media_id = str_replace($field_identifier_to_copy, $field_identifier, $use['field_identifier']);
            $new_media_id = str_replace($field_id_to_copy, $field_id, $new_media_id);
            $q = 'INSERT INTO '.$this->db_use.' SET field_identifier=?, use_key=?, process_key=?, id_media=?, id_process=?, share=1';
            $DB->prepared_query($q,'siiii', [$new_media_id, $use['use_key'], $use['process_key'], $use['id_media'], $use['id_process']]);
        }
        return true;
    }
    /**
     * return the last use_key to use or -1 if it's the first one
     * before use, have to increment by one
     * 
     * @param string $media_id The logical media identifier.
     *
     * @return int The last use_key found, or -1 if none exists.
     */
    public function get_last_use_key($media_id){
        global $DB;

        $q = 'SELECT IFNULL(MAX(use_key),-1) FROM '.$this->db_use.' WHERE field_identifier=?';
        $use_key = $DB->prepared_query_value($q,'s',[$media_id]);

        return $use_key;
    }

    //-----------------------------------------------------------------------------
    //image manipulation section
    //-----------------------------------------------------------------------------
    /**
     * Process image according to the process array.
     *
     * Handles various image operations such as resize, crop, rotate, and saving to file.
     * Supports SVG detection and special handling. 
     * Saves processed images and some of their data to the database.
     *
     * @param array $process  The process instructions and parameters for the image.
     *                        Must contain at least 'input', 'media_id', and 'use_key'.
     *                        May contain 'original', 'output', and 'process' : 
     *                        'process' is an array, a list of steps, each being an associative array with:
     *                        - type: (string) The operation type. Supported values:
     *                            - 'image_resize'         (requires 'max_width', optional 'max_height', optional 'force')
     *                            - 'image_noprop_resize'  (requires 'width', 'height') (for a non proportional resize)
     *                            - 'image_rotate'         (requires 'angle', optional 'crop')
     *                            - 'image_crop'           (requires 'x', 'y', 'width', 'height')
     *                            - 'square_crop'          (requires 'size')
     *                            - 'image_to_file'        (requires 'output', optional 'quality')
     *                        - Additional keys may include:
     *                            - 'output': (string) Output file path for the step
     *                            - 'suffix': (string) Suffix to add to the output filename
     *                            - 'quality': (int|string) Output quality (0-100 or 'cop' for copy)
     *                            - 'path': (string) Base path for output
     *                            - 'force_jpg': (bool) Force output as JPG
     *                        Example:
     *                        [
     *                          ['type'=>'image_resize', 'max_width'=>200, 'max_height'=>200],
     *                          ['type'=>'image_to_file', 'quality'=>80]
     *                        ]
     *
     * @return string|null Returns 'done' on success, or an error message string on failure.
     */
    public function image_process($process){
        global $DB,$FS,$CONFIG;
        if (!isset($process['input'])) {
            return 'input file error';
        }

        $this->process_image = true;

        //saving process :
        $id_process = $this->save_process($process);

        // if svg we can only save a version and that's it
        if (strtolower($FS->get_file_ext($process['input'])) == 'svg' || strtolower($FS->get_file_ext($process['input'])) == 'svgz') {
            $this->is_svg = true;
        } else {
            //loading original
            $this->current_media=$this->file_to_image($process['input']);
        }

        $filename = $FS->get_file_name($process['input']);

        if ($process['original']) {
            //saving original media in DB :
            $id_media = $this->save_media($process['media_id'], $process['use_key'], -1, $process['input'], $filename, $process['original'], 1);
            $this->save_use($process['media_id'], $process['use_key'], -1, $id_media, $id_process);
        }

        //-------------------------
        //processing
        //-------------------------
        if (isset($process['process'])) {
            // Utils::error_log($process);
            foreach ($process['process'] as $key => $proc) {
                if (isset($proc['path'])){
                    //$proc['output'] = $proc['path'].str_replace($FS->get_file_name_noext($process['input']), $process['media_id'].'¤'.$process['use_key'].'¤'.$key, $FS->get_file_name($process['input']));
                    $proc['output'] = $proc['path'].$process['media_id'].'¤'.$process['use_key'].'¤'.'.'.$key.$FS->get_file_ext($process['input']);
                }
                if (!isset($proc['output'])) {
                    // $proc['output'] = str_replace($FS->get_file_name_noext($process['input']), $process['media_id'].'¤'.$process['use_key'].'¤'.$key,$process['input']);
                    $proc['output'] = Filesystem::replace_file_name($process['input'],$process['media_id'].'¤'.$process['use_key'].'¤'.$key);
                }
                //creating output path from input + suffix
                if (isset($proc['suffix'])) {
                    $proc['output'] = isset($proc['output'])
                        ? $proc['output']=$FS->pre_or_suf_fixing($proc['output'], $proc['suffix'], true)
                        : $proc['output']=$FS->pre_or_suf_fixing($process['input'], $proc['suffix'], true);
                }
                if (isset($process['force_jpg']) && $process['force_jpg']) {
                    $proc['output'] = str_replace($FS->get_file_ext($proc['output']), 'jpg', $proc['output']);
                }

                switch ($proc['type']) {
                    case 'image_rotate':
                        if (!$this->is_svg) {
                            $this->current_media=$this->image_rotate($this->current_media, $proc['angle'], $proc['crop']);
                        }
                    break;
                    case 'image_resize':
                        if (!$this->is_svg) {
                            $proc['max_height'] = isset($proc['max_height']) ? $proc['max_height'] : 200;
                            $this->current_media=$this->image_resize($this->current_media, $proc['max_width'], $proc['max_height'], isset($proc['force'])?$proc['force']:false);
                        }
                    break;
                    case 'image_noprop_resize':
                        if (!$this->is_svg) {
                            $this->current_media=$this->image_noprop_resize($this->current_media, $proc['width'], $proc['height']);
                        }
                    break;
                    case 'image_to_file':
                        if (!$this->is_svg && $proc['quality']!='cop') {
                            $res = $this->image_to_file($this->current_media, $proc['output'], $proc['quality']);
                        } else {
                            $res = $FS->copy($process['input'], $proc['output'],true);
                            shell_exec('chmod 2774 "'.$proc['output'].'"');
                        }

                        //saving children data in DB :
                        $id_media = $this->save_media($process['media_id'], $process['use_key'], $key, $proc['output'], $filename, 0, 1);
                        $this->save_use($process['media_id'], $process['use_key'], $key, $id_media, $id_process);
                    break;
                    case 'image_crop':
                        if (!$this->is_svg) {
                            $this->image_crop($this->current_media, $proc['x'], $proc['y'], $proc['width'], $proc['height']);
                        }
                    break;
                    case 'square_crop':
                        if (!$this->is_svg) {
                            $this->current_media=$this->square_crop($this->current_media, $proc['size']);
                        }
                    break;
                }
            }
        }
        //last saving
        //-------------------------
        //checking if there is an output, if it's not the case we do nothing more...
        if (isset($process['output'])) {
            //checking if there is already a file
            if (is_file($process['output'])) {
                unlink($process['output']);
            }
            //checking destination directory
            if (is_dir($FS->get_file_path($process['output']))) {
                //check if output_quality is given
                $quality=(isset($process['quality']))?$process['quality']:50;
                //final save !!!!
                $this->image_to_file($this->current_media, $process['output'], $quality);
                return 'done';
            } else {
                // Utils::error_log('output path error '.$process['output']);
                return 'output path error '.$process['output'];
            }
        }
        //depracated !
        //memory cleaning
        // if (!$this->is_svg) {
        //     imagedestroy($this->current_media);
        // }
        if (!$process['original']) {
            unlink($process['input']);
        }
    }
    /**
     * Converts an image file to a base64-encoded data URI.
     *
     * Optionally resizes or reformats the image before encoding.
     *
     * @param string $file    The path to the image file.
     * @param int|false $width   Optional. 
     *                  Target width for resizing. Default false (no resize).
     * @param int|false $height  Optional.
     *                  Target height for resizing. Default false (no resize).
     * @param string|false $format Optional. 
     *                  Output format ('jpeg', 'png', 'gif', 'webp', 'bmp'). Default false (keep original).
     * @param int|false $quality  Optional. 
     *                  Output quality (0-100). Default false (uses 50 if format is set).
     *
     * @return string Base64-encoded data URI of the image, or an error message if not compatible.
     */
    public function image_to_base64($file,$width=false,$height=false,$format=false,$quality=false){
        global $FS;
        $ext = strtolower($FS->get_file_ext($file));
        if (in_array($ext, Media::image_ext)){
            if ($ext == 'svg'){
                $img = file_get_contents($file);
                return 'data:image/svg+xml;base64,'.base64_encode($img);
            }
            if ($width || $height || $format) {
                $image=$this->file_to_image($file);
                
                if ($width || $height) {
                    $image=$this->image_resize($image,$width,$height);
                }
                
                ob_start();
                if ($format){
                    $quality=($quality)?$quality:50;
                    
                    if ($format == 'jpg' || $format == 'jpeg') {
                        imagejpeg($image, null, $quality);
                        $format='jpeg';
                    }
                    if ($format == 'gif') {
                        imagegif($image, null);
                    }
                    if ($format == 'png' || $format == 'webp') {
                        imagealphablending($image, false);
                        imagesavealpha($image, true);
                        $quality=floor((100-$quality)/10);
                        if ($format == 'png'){
                            imagepng($image,null, $quality);
                        }
                        if ($format == 'webp'){
                            imagewebp($image, null, $quality);
                        }
                        
                    }
                    if ($format == 'bmp') {
                        imagewbmp($image, null);
                    }
                    
                }else{
                    $format='jpeg';
                    imagejpeg($image, null, 50);
                    
                }
                $outputBuffer = ob_get_clean(); // do ob_get_contents and ob_end_contents
                $base64 = base64_encode($outputBuffer);
                $dataim='data:image/'.$format.';base64,'.$base64;
                //deprecated
                // imagedestroy($image);
            }else{
                $format = $FS->get_file_ext($file);
                $format = ($format=='jpg')?'jpeg':$format;
                $img = file_get_contents($file);
                $base64 = base64_encode($img);
                $dataim = 'data:image/'.$format.';base64,'.$base64;
            }
            
            return $dataim;
            
        }else{
            return 'not an compatible image';
        }
    }

    /**
     * Creates a GdImage from a file.
     *
     * Supports jpg, jpeg, png, bmp, gif, and webp formats.
     *
     * @param string $file Path to the image file.
     * @return resource|false GdImage, or false.
     */
    public function file_to_image($file){
        global $FS;
        $ext = strtolower($FS->get_file_ext($file));
        if ($ext == "jpg" || $ext == "jpeg") {
            $img = imagecreatefromjpeg($file);
        }
        if ($ext == "png") {
            $img = imagecreatefrompng($file);
        }
        if ($ext == "bmp") {
            $img = imagecreatefromwbmp($file);
        }
        if ($ext == "gif") {
            $img = imagecreatefromgif($file);
        }
        if ($ext == "webp") {
            $img = imagecreatefromwebp($file);
        }
        if (isset($img)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            return $img;
        } else {
            return false;
        }
    }
    /**
     * Saves a GdImage to a file, with support for transparency and quality.
     *
     * @param resource $image   The GdImage.
     * @param string   $output  The output file path.
     * @param int      $quality Optional. Output quality (0-100). Default 100.
     *                 0 : Horrible , 100 : the best (and the heaviest)
     *
     * @return bool True on success, false.
     */
    public function image_to_file($image, $output, $quality = 100){
        global $FS;
        $ext = strtolower($FS->get_file_ext($output));
        if (file_exists($output)) {
            unlink($output);
        }
        if ($ext == 'jpg' || $ext == 'jpeg') {
            return imagejpeg($image, $output, $quality);
        }
        if ($ext == 'gif') {
            return imagegif($image, $output);
        }
        if ($ext == 'png' || $ext == 'webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $quality = floor((100 - $quality) / 10);
            
            if ($ext == 'png'){
                return imagepng($image, $output, $quality);
            }
            if ($ext == 'webp'){
                return imagewebp($image, $output, $quality);
            }
            
        }
        if ($ext == 'bmp') {
            return imagewbmp($image, $output);
        }
        return false;
    }

    /**
     * Rotates an GdImage by a given angle, optionally cropping to original size.
     *
     * @param resource $image The GdImage.
     * @param int      $angle The rotation angle in degrees.
     * @param bool     $crop  Optional. Crop to original size. Default false.
     *
     * @return resource The rotated GdImage.
     */
    public function image_rotate($image, $angle, $crop=false){
        if ($angle == -90) {
            $angle = 270;
        }

        if ($angle > 0 && $angle != 360) {
            // original image size
            $imageX = imagesx($image);
            $imageY = imagesy($image);

            // preserve transparency
            $dest = imagecreatetruecolor($imageX, $imageY);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);

            $dest = imagerotate($image, $angle, $transparent);

            imagealphablending($dest, false);
            imagesavealpha($dest, true);

            //cropping to maintain image size
            if ($crop) {
                $tx = imagesx($dest);
                $ty = imagesy($dest);
                $tx = ($tx-$imageX)/2;
                $ty = ($ty-$imageY)/2;
                $dest = $this->image_crop($dest, $tx, $ty, $imageX, $imageY);
            }
        }

        return $dest;
    }

    /**
     * Proportionally resizes a GdImage to fit within max width and height.
     *
     * @param resource $image      The GdImage.
     * @param int      $max_width  Maximum width.
     * @param int      $max_height Maximum height.
     * @param bool     $force      Optional. Force resize even if smaller. Default false.
     *
     * @return resource The resized GdImage.
     */
    public function image_resize($image, $max_width = 200, $max_height = 200, $force = false){
        // original image size
        $imageX = imagesx($image);
        $imageY = imagesy($image);

        // resize
        if ($imageX < $max_width && !$force) {
            return $image;
        }

        if ($imageY < $max_height && !$force) {
            return $image;
        }

        if ($imageX > $imageY) {
            $ratio = $max_width / $imageX;
            $w = $max_width;
            $h = floor($imageY * $ratio);
        } else {
            $ratio = $max_height / $imageY;
            $h = $max_height;
            $w = floor($imageX * $ratio);
        }

        // final image
        $dest = imagecreatetruecolor($w, $h);

        // preserve transparency
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, $w, $h, $transparent);

        // copy and resize
        imagecopyresampled($dest, $image, 0, 0, 0, 0, $w, $h, $imageX, $imageY);

        return $dest;
    }

    /**
     * Resizes a GdImage to exact width and height (non-proportional).
     *
     * @param resource $image The GdImage.
     * @param int      $width Target width.
     * @param int      $height Target height.
     *
     * @return resource The resized GdImage.
     */
    public function image_noprop_resize($image, $width = 200, $height = 200){
        // original image size
        $imageX = imagesx($image);
        $imageY = imagesy($image);
        // final image
        $dest = imagecreatetruecolor($width, $height);
        // preserve transparency
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, $width, $width, $transparent);
        // copy and resize
        imagecopyresampled($dest, $image, 0, 0, 0, 0, $width, $height, $imageX, $imageY);
        return $dest;
    }

    /**
     * Crops an GdImage to the specified rectangle.
     *
     * @param resource $image The GdImage.
     * @param int      $x     X coordinate of the crop start.
     * @param int      $y     Y coordinate of the crop start.
     * @param int      $width Crop width.
     * @param int      $height Crop height.
     *
     * @return resource The cropped GdImage.
     */
    public function image_crop($image, $x=0, $y=0, $width = 200, $height = 200){
        // final image
        $dest = imagecreatetruecolor($width, $height);
        // preserve transparency
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, $width, $width, $transparent);
        // copy cropped part
        imagecopyresampled($dest, $image, 0, 0, $x, $y, $width, $height, $width, $height);
        return $dest;
    }

    /**
     * Crops an GdImage to a square and resizes to the given size.
     *
     * @param resource $image The GdImage.
     * @param int      $size  The target square size.
     *
     * @return resource The square-cropped and resized GdImage.
     */
    public function square_crop($image, $size){
        $imageX = imagesx($image);
        $imageY = imagesy($image);

        if ($imageX > $imageY) {
            $dest=$this->image_crop($image, floor(($imageX-$imageY)/2), 0, $imageY, $imageY);
        } else {
            $dest=$this->image_crop($image, 0, floor(($imageY-$imageX)/2), $imageX, $imageX);
        }

        $dest=$this->image_resize($dest, $size, $size);
        return $dest;
    }
    /**
     * Fixes the orientation of a JPEG image file based on EXIF data.
     * Some smartphone record badly the orientation in jpeg format !
     *
     * @param string $filename The path to the JPEG file.
     * @return void
     */
    public static function jpeg_fix_orientation($filename){
        $exif = @exif_read_data($filename);
        if (!empty($exif['Orientation'])) {
            $image = imagecreatefromjpeg($filename);
            switch ($exif['Orientation']) {
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;

                case 6:
                    $image = imagerotate($image, -90, 0);
                    break;

                case 8:
                    $image = imagerotate($image, 90, 0);
                    break;
            }

            imagejpeg($image, $filename, 90);
            //deprecated
            // imagedestroy($image);
        }
    }
    /**
     * Retrieves media information (width, height, etc.) for a given media ID and use key.
     *
     * @param string|false $media_id The logical media identifier.
     * @param int|false    $use_key  The use key.
     *
     * @return array|false Array of media info, or false if not found.
     */
    public static function get_info($media_id = false, $use_key = false){
        global $DB, $LANG;

        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');

        $medias = [];

        if ($media_id  !== false && $use_key !== false) {
            $q = 'SELECT d.id, d.width, d.height, u.process_key FROM '.$db_data.' d INNER JOIN '.$db_use.' u';
            $q.=' ON u.field_identifier=? AND u.use_key=? AND u.id_media=d.id ORDER BY u.process_key';
            $medias = $DB->prepared_query_list($q,'si',[$media_id,$use_key]);
            foreach($medias as $key => $media){
                $name = Language::load_short_translation_value('media_data-filename', $media['id'], $LANG->current_id_data);
                if ($name == '') $name = 'format '.($key + 1);
                $format = $media['width'].'px / '.$media['height'].'px';
                $media['name'] = $name.' - '.$format;
                $medias[$key] = $media;
            }
            return $medias;
        }

        return false;
    }
    /**
     * Checks if the given media ID and use key correspond to an image.
     *
     * @param string|false $media_id The logical media identifier.
     * @param int|false    $use_key  The use key.
     *
     * @return bool True if the media is an image, false otherwise.
     */
    public static function is_image($media_id = false, $use_key = false){
        global $DB,$FS;

        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');
        if ($media_id && $use_key) {
            $q = 'SELECT d.path FROM '.$db_data.' d INNER JOIN '.$db_use.' u ON u.field_identifier="'.$media_id.'" AND u.use_key='.$use_key.' ORDER BY d.id LIMIT 1';
            $path = $DB->query_value($q);
            if (in_array(strtolower($FS->get_file_ext($path)), Media::image_ext)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Retrieves image information (width, height) using ffprobe.
     *
     * @param string $path The path to the image file.
     * @return array Image information array.
     */
    public static function get_image_info($path){
        $options='-v error -show_streams -of json';
        $res = shell_exec(sprintf('ffprobe %s %s', $options, escapeshellarg($path)));
        $json = json_decode($res, true);
        foreach ($json['streams'] as $key => $line) {
            $result['width']=$line['width'];
            $result['height']=$line['height'];
        }
        return $result;
    }
    
    /**
     * Returns the HTML tag for the media (image or video) for the given field identifier and ID.
     *
     * @param string|false $field_identifier The field identifier.
     * @param string|false $field_id         The field ID.
     * @param string       $size             The size ('small', 'big', or pixel value).
     * @param int|false    $use_key          The use key.
     * @param boolean      $nodl             activate secured feature in public/media/media.php to stop download
     * 
     * @return mixed HTML output or false if not found.
     */
    public function get_html($field_identifier = false, $field_id = false, $size = 'small', $use_key = false,$nodl=false){
        $media = self::get_media($field_identifier, $field_id, $size, $use_key);
        if ($media === false) return false;
        
        global $CONFIG;
        $mtime = filemtime($this->base_path.$media['path']);
        if ($nodl==false || $CONFIG::SECUVID == false){
            $path = $CONFIG::BASE_URL.'public/media/media.php?f='.$media['media_id'].'&i='.$media['use_key'].'&p='.$media['process_key'].'&t='.$mtime;
        }else{
            $path = $CONFIG::BASE_URL.'public/media/media.php?pp='.$this->encode_video_src([$media['media_id'], $media['use_key']]).'&t='.$mtime;
        }
        
        switch ($media['type']){
            case 1: // img
                $class = 'hlp_media image';
                $class.= ($media['width'] > $media['height']) ? ' landscape' : ' portrait';
                $output = H::IMG(['class'=>$class, 'src'=>$path, 'alt'=>$media['name']]);
            break;
            case 2: // video
                global $FS;
                $output = H::VIDEO(['class'=>'hlp_media video', 'controls'=>1]);
                $output->add_child( H::SOURCE(['src'=>$path, 'type'=>'video/'.$FS->get_file_ext($media['path'])]) );
            break;
            case 3: // audio
            default:

            break;
        }
        
        return $output;
    }
    /**
     * Retrieves a media record for the given field identifier and ID.
     *
     * @param string|false $field_identifier The field identifier.
     * @param string|false $field_id         The field ID.
     * @param string       $size             The size ('small', 'big', or pixel value).
     * @param int|false    $use_key          The use key.
     *
     * @return array|false Media info array or false if not found.
     */
    public static function get_media($field_identifier = false, $field_id = false, $size = 'small', $use_key = false){
        if ($field_identifier === false|| $field_id === false) {
            $log = 'missing parameters'.PHP_EOL;
            $log.= '$field_identifier = "'.$field_identifier.'"'.PHP_EOL;
            $log.= '$field_id = "'.$field_id.'"'.PHP_EOL;
            Utils::error_log($log);
            return false;
        }

        if (!self::has_media($field_identifier,$field_id)) {
            return false;
        }

        global $DB, $CONFIG, $LANG;
        $media_id = $field_identifier.'¤'.$field_id;
        $media_lang = $field_identifier.'-'.$LANG->current_language.'¤'.$field_id;
        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');
        $q = 'SELECT d.id as id_media, u.id as id_use, d.type, d.path, d.big_view, d.width, d.height, u.use_key, u.process_key, u.field_identifier as media_id';
        $q.=' FROM '.$db_data.' d, '.$db_use.' u WHERE u.id_media=d.id AND (u.field_identifier=? OR (u.field_identifier=?';
        $q.=' AND NOT EXISTS (SELECT 1 FROM '.$db_use.' WHERE field_identifier=?)))';
        if ($use_key !== false){
            $q.=' AND u.use_key=?';
        }
        switch (strtolower($size)) {
            case 'small':
                $q .= ' ORDER BY d.width ASC LIMIT 1';
            break;

            case 'big':
                $q .= ' ORDER BY d.width DESC LIMIT 1';
            break;

            default:
                $q .= 'ORDER BY abs(d.width - '.intval($size).') LIMIT 1';
            break;
        }
        if ($use_key !== false){
            $media = $DB->prepared_query_line($q, 'sssi', [$media_lang, $media_id, $media_lang, $use_key]);
        } else {
            $media = $DB->prepared_query_line($q, 'sss', [$media_lang, $media_id, $media_lang]);
        }

        // media exist in db but not in the disk
        if (!\is_file($CONFIG::HOME_FOLDER.'files/'.$media['path']) && !is_file($media['path'])) return false;

        $q = 'SELECT count(*) FROM '.$db_use.' WHERE id_media='.$media['id_media'];
        $media['nbinstances']= $DB->query_value($q);

        global $LANG;
        $media['name'] = Language::load_short_translation_value($media['media_id'], $media['id_media'], $LANG->current_id_data);
        
        return $media;
    }
    /**
     * Retrieves a list of media records in use table for the given field identifier and ID.
     *
     * @param string|false $field_identifier The field identifier.
     * @param string|false $field_id         The field ID.
     * @param string       $size             The size ('small', 'big', or pixel value).
     *
     * @return array|false List of media info arrays or false if not found.
     */
    public static function get_media_list($field_identifier = false, $field_id = false, $size = 'small'){
        
        if ($field_identifier === false|| $field_id === false) {
            $log = 'missing parameters'.PHP_EOL;
            $log.= '$field_identifier = "'.$field_identifier.'"'.PHP_EOL;
            $log.= '$field_id = "'.$field_id.'"'.PHP_EOL;
            Utils::error_log($log);
            return false;
        }

        if (!self::has_media($field_identifier,$field_id)) {
            return false;
        }
        
        global $DB, $LANG;
        $media_id = $field_identifier.'¤'.$field_id;
        $media_lang = $field_identifier.'-'.$LANG->current_language.'¤'.$field_id;
        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');
        // get all the medias for one field identifier as a list of id
        $q = 'SELECT GROUP_CONCAT(id_media SEPARATOR ",") as id_medias, use_key FROM '.$db_use.' WHERE (field_identifier=? OR';
        $q.=' (field_identifier=? AND NOT EXISTS (SELECT 1 FROM '.$db_use.' WHERE field_identifier=?))) GROUP BY use_key';
        $tmp = $DB->prepared_query_list($q, 'sss', [$media_lang, $media_id, $media_lang]);
        $medias = [];
        foreach($tmp as $line){
            // get the nearest to the size wanted for each use key
            $q = 'SELECT d.id as id_media, u.id as id_use, d.type, d.path, d.big_view, d.width, '.$line['use_key'].' as use_key, u.field_identifier as media_id FROM '.$db_data.' d INNER JOIN '.$db_use.' u';
            $q.=' ON u.id_media=d.id AND d.id IN ('.$line['id_medias'].')';
            switch (strtolower($size)) {
                case 'small':
                    $q .= ' ORDER BY d.width ASC LIMIT 1';
                break;

                case 'big':
                    $q .= ' ORDER BY d.width DESC LIMIT 1';
                break;

                default:
                    $q .= 'ORDER BY abs(d.width - '.intval($size).') LIMIT 1';
                break;
            }
            $media = $DB->query_line($q);
            if (!$media) continue;
            
            $q = 'SELECT count(*) FROM '.$db_use.' WHERE id_media='.$media['id_media'];
            $media['nbinstances']= $DB->query_value($q);

            global $LANG;
            $media['name'] = Language::load_short_translation_value($media['media_id'], $media['id_media'], $LANG->current_id_data);

            array_push($medias, $media);
        }
        return $medias;
    }
    /**
     * Checks if there is at least one media for the given field identifier and ID.
     *
     * @param string|false $field_identifier The field identifier.
     * @param string|false $field_id         The field ID.
     *
     * @return bool True if media exists, false otherwise.
     */
    public static function has_media($field_identifier = false, $field_id = false) {
        if ($field_identifier === false|| $field_id === false) {
            return false;
        }
        
        global $DB, $LANG;
        $media_id = $field_identifier.'¤'.$field_id;
        $media_lang = $field_identifier.'-'.$LANG->current_language.'¤'.$field_id;
        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');
        $q = 'SELECT count(d.id) FROM '.$db_data.' d INNER JOIN '.$db_use.' u ON ((u.field_identifier="'.$media_id.'" OR u.field_identifier LIKE "'.$media_lang.'") AND d.id=u.id_media)';
        $count = $DB->query_value($q);
        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * Deletes all media for a given field identifier and use key (including language one).
     * Only deletes the media file if it is not referenced elsewhere.
     *
     * @param string|false $field_identifier The field identifier.
     * @param string|false $field_id         The field ID.
     * @param int|false    $use_key          The use key.
     *
     * @return bool True on success, false on failure.
     */
    public function delete_media($field_identifier = false, $field_id = false, $use_key = false){
        global $LANG;

        if ($field_identifier === false || $field_id === false) {
            return false;
        }

        foreach($LANG->get_languages_data() as $lang){
            $this->delete_media_strict($field_identifier.'-'.$lang['iso'], $field_id, $use_key);
        }

        $this->delete_media_strict($field_identifier, $field_id, $use_key);

        return true;
    }
    /**
     * Deletes all media for a given field identifier and use key. Will act on the exact field_identifier set and will not try to act on language linked media.
     * Only deletes the media file if it is not referenced elsewhere.
     *
     * @param string|false $field_identifier The field identifier.
     * @param string|false $field_id         The field ID.
     * @param int|false    $use_key          The use key.
     *
     * @return bool True on success, false on failure.
     */
    public function delete_media_strict($field_identifier = false, $field_id = false, $use_key = false){
        global $DB, $CONFIG;

        if ($field_identifier === false || $field_id === false) {
            return false;
        }

        $media_id = $field_identifier.'¤'.$field_id;
        $q = 'SELECT DISTINCT d.id, d.path, d.width FROM '.$this->db_data.' d INNER JOIN '.$this->db_use.' u ON u.field_identifier=? AND u.id_media=d.id';
        if ($use_key !== false){
            $q.= ' AND u.use_key=?';
            $medias = $DB->prepared_query_list($q,'si', [$media_id, $use_key]);
        } else {
            $medias = $DB->prepared_query_list($q,'s', [$media_id]);
        }
        
        if ($medias) {
            foreach ($medias as $line) {
                $q = 'SELECT id, field_identifier, use_key, process_key FROM '.$this->db_use.' WHERE id_media='.$line['id'];
                $q.=' AND field_identifier<>?';
                $share_data = $DB->prepared_query_line($q,'s', [$media_id]);
                if (!$share_data){
                    $path = (\str_contains($line['path'], $CONFIG::HOME_FOLDER) || \str_contains($line['path'], $CONFIG::HELPHP_FOLDER)) ? $line['path'] : $this->base_path.$line['path'];
                    if (is_file($path)){
                        unlink($path);
                    }
                    $q = 'DELETE FROM '.$this->db_data.' WHERE id='.$line['id'];
                    $DB->query($q);

                    Language::delete_short_translation_value('media_data-filename',$line['id']);
                } else {
                    // change name of the file and process_id
                    // $new_path = str_replace($FS->get_file_name_noext($line['path']), $share_data['field_identifier'].'¤'.$share_data['use_key'].'¤'.$share_data['process_key'], $line['path']);
                    $new_path = Filesystem::replace_file_name($line['path'],$share_data['field_identifier'].'¤'.$share_data['use_key'].'¤'.$share_data['process_key']);
                    $res = rename($this->base_path.$line['path'], $this->base_path.$new_path);
                    $DB->query('UPDATE '.$this->db_data.' SET path="'.$new_path.'" WHERE id='.$line['id']);
                    $DB->query('UPDATE '.$this->db_use.' SET share=0 WHERE id='.$share_data['id']);
                }
            }
        }

        // check if process is shared
        if ($use_key !== false) {
            $q = 'SELECT COUNT(id) FROM '.$this->db_use.' WHERE field_identifier<>? AND use_key<>? AND id_process=';
            $q.= '(SELECT UNIQUE id_process FROM '.$this->db_use.' WHERE field_identifier=? AND use_key=?)';
            $process_cnt = $DB->prepared_query($q,'sisi',[$media_id, $use_key, $media_id, $use_key]);
        } else {
            $q = 'SELECT COUNT(id) FROM '.$this->db_use.' WHERE field_identifier<>? AND id_process=';
            $q.= '(SELECT UNIQUE id_process FROM '.$this->db_use.' WHERE field_identifier=?)';
            $process_cnt = $DB->prepared_query($q,'ss',[$media_id, $media_id]);
        }
        if ($process_cnt == 0){
            $q = 'DELETE FROM '.$this->db_process.' WHERE id IN (SELECT DISTINCT id_process FROM '.$this->db_use.' WHERE field_identifier=?';
            if ($use_key !== false) {
                $q.=' AND use_key=?)';
                $DB->prepared_query($q,'si',[$media_id, $use_key]);
            } else {
                $q.= ')';
                $DB->prepared_query($q,'s',[$media_id]);
            }
        }
        

        $q = 'DELETE FROM '.$this->db_use.' WHERE field_identifier=?';
        if ($use_key !== false) {
            $q.=' AND use_key=?';
            $DB->prepared_query($q,'si',[$media_id, $use_key]);
        } else {
            $DB->prepared_query($q,'s',[$media_id]);
        }

        return true;
    }
    /**
     * Unshares a media file for a given field identifier, use key, and process key.
     * Copies the media if needed and updates the database.
     *
     * @param string|false $field_identifier The field identifier.
     * @param int|false    $use_key          The use key.
     * @param int|false    $process_key      The process key.
     *
     * @return int|false The new media ID or false on failure.
     */
    public function unshare_media($field_identifier = false, $use_key = false, $process_key = false){
        global $DB, $FS;

        if ($field_identifier === false || $use_key === false || $process_key === false){
            Utils::error_log('missing parameters');
            return false;
        }

        $q = 'SELECT d.* FROM '.$this->db_data.' d INNER JOIN '.$this->db_use.' u ON u.field_identifier=? AND u.use_key=?';
        $q.=' AND u.process_key=? AND u.id_media=d.id';
        $data = $DB->prepared_query_line($q, 'sii', [$field_identifier, $use_key, $process_key]);
        if (!$data){
            Utils::error_log('media not found for field_identifier = "'.$field_identifier.'", use_key = '.$use_key.', process_key='.$process_key);
            return false;
        }

        $new_filename = $field_identifier.'¤'.$use_key.'¤'.$process_key;
        $current_filename = $FS->get_file_name_noext($data['path']);
        if ($new_filename == $current_filename){
            // the original media need to be copied to a new one with the next shared element filename
            $q = 'SELECT field_identifier, use_key, process_key, id FROM '.$this->db_use.' WHERE id_media='.$data['id'];
            $q.=' AND field_identifier<>?';
            $share_data = $DB->prepared_query_line($q, 's', [$field_identifier]);
            
            // $new_path = str_replace($current_filename, $share_data['field_identifier'].'¤'.$share_data['use_key'].'¤'.$share_data['process_key'], $data['path']);
            $new_path = Filesystem::replace_file_name($data['path'],$share_data['field_identifier'].'¤'.$share_data['use_key'].'¤'.$share_data['process_key']);
            $res = copy($this->base_path.$data['path'], $this->base_path.$new_path);
                    
            $q = 'UPDATE '.$this->db_data.' SET path="'.$new_path.'" WHERE id='.$data['id'];
            $DB->query($q);

            // remove share in db
            $DB->query('UPDATE '.$this->db_use.' SET share=0 WHERE id='.$share_data['id']);

        } else {
            // copy a version of the original media for the modification
            // $new_path = str_replace($current_filename, $new_filename, $data['path']);
            $new_path = Filesystem::replace_file_name($data['path'],$new_filename);
            $res = copy($this->base_path.$data['path'], $this->base_path.$new_path);
            $data['path'] = $new_path;
        }

        $q = 'INSERT INTO '.$this->db_data.' SET original='.$data['original'].', path="'.$data['path'].'", width='.$data['width'];
        $q.=', height='.$data['height'].', duration='.$data['duration'].', fps='.$data['fps'].', big_view='.$data['big_view'];
        $DB->query($q);
        $id = $DB->last_insert_id();

        $q = 'UPDATE '.$this->db_use.' SET id_media='.$id.', share=0 WHERE field_identifier=? AND use_key=? AND process_key=?';
        $DB->prepared_query($q, 'sii', [$field_identifier, $use_key, $process_key]);

        return $id;
    }

    //-----------------------------------------------------------------------------
    //Video manipulation section
    //-----------------------------------------------------------------------------

    /**
     * Processes a video file according to the specified process array.
     *
     * Handles resizing, recompression, thumbnail generation, and format conversion using ffmpeg.
     * Saves processed videos and their metadata to the database.
     *
     * @param array $process  The process instructions and parameters for the video.
     * The $process array can contain the following keys:
     * - input (string) : Path to the input video file (required).
     * - output (string) : Path to the output video file (optional, auto-generated if not set).
     * - media_id (string) : Logical media identifier (required).
     * - use_key (int) : Use key for this media (required).
     * - original (bool|int) : If true, marks this as the original file (optional).
     * - pid (string) : Process identifier for system calls (optional).
     * - sequence (array) : Video sequence info (optional, used for image sequences).
     * - process (array) : List of processing steps, each being an associative array with:
     *      - type (string): The operation type. Supported values:
     *          - 'video_resize'            (requires 'max_width', optional 'max_height')
     *          - 'video_resize_from_height' (requires 'max_height')
     *          - 'video_from_sequence'     (requires 'fps', 'start_number', 'total_images', 'max_height')
     *          - 'recomp'                  (forces recompression)
     *          - 'thumbnails'              (requires 'max_width' or 'max_height', 'number')
     *      - desinterlace (string): 'ok' to enable deinterlacing (optional)
     *      - Additional keys may be required depending on the type.
     * - no_bdd (bool): If true, skips database operations (optional, default false).
     *
     * Example:
     * $process = [
     *   'input' => '/tmp/video.mp4',
     *   'output' => '/dest/video.mp4',
     *   'media_id' => 'video¤123',
     *   'use_key' => 0,
     *   'original' => 1,
     *   'process' => [
     *     ['type'=>'video_resize', 'max_width'=>640, 'max_height'=>360],
     *     ['type'=>'thumbnails', 'max_width'=>320, 'number'=>5]
     *   ]
     * ];
     * 
     * @param bool  $no_bdd   Optional. If true, skips database operations. Default false.
     *
     * @return string|null Returns 'done' on success, or an error message string on failure.
     */
    public function video_process($process,$no_bdd = false){
        global $CONFIG,$FS;
        if (isset($process['input'])) {

            $this->process_video = true;
            
            if (!$no_bdd){
                $id_process = $this->save_process($process);
            }

            //loading original
            $new_name = Filesystem::replace_file_name($process['input'], $process['media_id'].'¤'.$process['use_key']);
            $FS->move([['path'=>$process['input'], 'name'=>$FS->get_file_name($new_name)]], $FS->get_file_path($process['input']));
            $process['input'] = $new_name;

            if (!isset($process['output'])) $process['output'] = $process['input'];

            $this->current_media = $process['input'];
            
            if (isset($process['sequence'])){
                $this->video_info = $process['sequence'];
            }else{
                //getting info about original video or sequence
                $this->video_info = $this->get_video_info($this->current_media);
            }

            $filename = $FS->get_file_name($process['input']);
            if (isset($process['original']) && $process['original'] && !$no_bdd) {
                //saving original data in DB :
                $this->save_media($process['media_id'], $process['use_key'], -1, $process['input'],$filename,1, 2);
            }
            //-------------------------
            //processing
            //-------------------------
            //~ $ff_options=' -i \''.$this->current_media.'\' -crf 26 -x264opts keyint=30:min-keyint=30:scenecut=-1 -8x8dct 1 -pix_fmt yuv420p -preset veryslow -b:a 64k -movflags +faststart -vf colormatrix=bt601:bt709';
            //~ $ff_options=' -i \''.$this->current_media.'\' -crf 23 -g 30 -pix_fmt yuv420p -preset veryslow -b:a 64k -movflags +faststart -vf colormatrix=bt601:bt709';
            //~ $ff_options=' -i \''.$this->current_media.'\' -crf 23 -g 20 -pix_fmt yuv420p -copyts -maxrate 5M -bufsize 5M -preset veryfast -b:a 64k -movflags +faststart -vf colormatrix=bt601:bt709';
            $ff_options=' -y -i \''.$this->current_media.'\' -crf 23 -g 20 -pix_fmt yuv420p -copyts -maxrate 5M -preset veryfast -b:a 64k -movflags +faststart';
            //~ $ff_options=' -i \''.$this->current_media.'\' -g 20  -crf 23 -copyts -maxrate 5M -bufsize 5M -preset veryfast -b:a 64k -movflags  -vf zscale=matrix=709,format=yuv420p -c:v libx264 -x264opts colormatrix=bt709 +faststart';
            //~ $recomp_force=true;
            if (isset($process['process'])) {
                foreach ($process['process'] as $key => $proc) {

                    switch ($proc['type']) {
                        case 'video_resize':
                            // future size calculation
                            if ($this->video_info['width'] > $this->video_info['height']) {
                                $ratio = $proc['max_width'] / $this->video_info['width'];
                                $w = $proc['max_width'];
                                $h = floor($this->video_info['height'] * $ratio);
                            } else {
                                $ratio = $proc['max_height'] / $this->video_info['height'];
                                $h = $proc['max_height'];
                                $w = floor($this->video_info['width'] * $ratio);
                            }
                            if ($w !=$this->video_info['width'] || $h != $this->video_info['height']) {
                                $recomp_force=true;
                                if ($h % 2 != 0){ // height odd protection for mp4
                                    $h=$h-1; 
                                }
                                if ($w % 2 != 0){ // width odd protection for mp4
                                    $w=$w-1; 
                                }
                                $ff_options.=' -s '.$w.'x'.$h;
                                $this->video_info['height']=$h;
                                $this->video_info['width']=$w;
                            }
                        break;
                        case 'video_resize_from_height':
                            // future size calculation
                            $ratio = $proc['max_height'] / $this->video_info['height'];
                            $h = $proc['max_height'];
                            $w = floor($this->video_info['width'] * $ratio);
                            if ($w !=$this->video_info['width'] || $h != $this->video_info['height']) {
                                $recomp_force=true;
                                if ($h % 2 != 0){ // height odd protection for mp4
                                    $h=$h-1; 
                                }
                                if ($w % 2 != 0){ // width odd protection for mp4
                                    $w=$w-1; 
                                }
                                $ff_options.=' -s '.$w.'x'.$h;
                                $this->video_info['height']=$h;
                                $this->video_info['width']=$w;
                            }
                        break;
                        case 'video_from_sequence':
                            // future size calculation
                            $ff_options=' -y -r '.$proc['fps'].' -start_number '.$proc['start_number'].' -i \''.$this->current_media.'\' -vframes '.$proc['total_images'].' -crf 23 -g 20 -pix_fmt yuv420p -maxrate 5M -bufsize 5M -preset veryfast -b:a 64k -movflags +faststart -vf "premultiply=inplace=1,colormatrix=bt601:bt709" ';
                            $ratio = $proc['max_height'] / $this->video_info['height'];
                            $h = $proc['max_height'];
                            $w = floor($this->video_info['width'] * $ratio);
                            $recomp_force=true;
                            if ($w !=$this->video_info['width'] || $h != $this->video_info['height']) {
                                if ($h % 2 != 0){ // height odd protection for mp4
                                    $h=$h-1; 
                                }
                                if ($w % 2 != 0){ // width odd protection for mp4
                                    $w=$w-1; 
                                }
                                $ff_options.=' -s '.$w.'x'.$h;
                                $this->video_info['height']=$h;
                                $this->video_info['width']=$w;
                            }
                        break;
                        case 'recomp':
                            $recomp_force=true;
                            if ($this->video_info['height'] % 2 != 0){ // height odd protection for mp4
                                $this->video_info['height']=$this->video_info['height']-1; 
                            }
                            if ($this->video_info['width'] % 2 != 0){ // width odd protection for mp4
                                $this->video_info['width']=$this->video_info['width']-1; 
                            }
                            if ($this->video_info['height'] % 2 != 0 || $this->video_info['width'] % 2 != 0){ // height  odd protection for mp4
                                $ff_options.=' -s '.$this->video_info['width'].'x'.$this->video_info['height'];
                            }
                        break;
                        case 'thumbnails':
                            // future size calculation
                            if ($this->video_info['width'] > $this->video_info['height']) {
                                $ratio = $proc['max_width'] / $this->video_info['width'];
                                $w = $proc['max_width'];
                                $h = floor($this->video_info['height'] * $ratio);
                            } else {
                                $ratio = $proc['max_height'] / $this->video_info['height'];
                                $h = $proc['max_height'];
                                $w = floor($this->video_info['width'] * $ratio);
                            }
                            $recomp_force=true;
                            $intervalsplit=$this->video_info['duration']/$proc['number'];
                            $ff_options=' -y -i \''.$this->current_media.'\' -an -vf "select=isnan(prev_selected_t)+gte(t-prev_selected_t\,'.$intervalsplit.'),scale=w='.$w.':h='.$h.':force_original_aspect_ratio=decrease,tile='.$proc['number'].'x1:nb_frames='.$proc['number'].',format=yuv420p" -frames:v 1 -vsync vfr ';
                            
                        break;
                    }
                    if (isset($proc['desinterlace']) && $proc['desinterlace'] == 'ok' && $proc['type'] !='thumbnails'){
                        $ff_options.=' -filter:v bwdif=mode=send_frame:parity=auto:deint=all';
                    }
                }
            }else{
                if ($this->video_info['height'] % 2 != 0){ // height odd protection for mp4
                    $this->video_info['height']=$this->video_info['height']-1; 
                }
                if ($this->video_info['width'] % 2 != 0){ // width odd protection for mp4
                    $this->video_info['width']=$this->video_info['width']-1; 
                }
                if ($this->video_info['height'] % 2 != 0 || $this->video_info['width'] % 2 != 0){ // height  odd protection for mp4
                    $ff_options.=' -s '.$this->video_info['width'].'x'.$this->video_info['height'];
                }
            }
           //last saving
           //-------------------------
           //checking if there is an output, if it's not the case we do nothing more...
            if (isset($process['output'])) {
                $filename = $FS->get_file_name($process['output']);
                
                // $process['output'] = '';
                // if ($filename == 'video.mp4') {
                    // $process['output'] = str_replace($filename, $FS->get_file_name($process['input']), $process['output']);
                // }
                //checking if there is already a file
                if (is_file($process['output']) && $process['output'] != $process['input']) {
                    unlink($process['output']);
                }
                //checking destination directory
                if (is_dir($FS->get_file_path($process['output']))) {

                    //must verify in videoinfos if recomp is needed
                    if ($FS->get_file_ext($process['output']) != 'mp4') {
                        $process['output'] = str_replace($FS->get_file_ext($process['output']), 'mp4', $process['output']);
                        $recomp_force = true;
                    }

                    if (!isset($recomp_force) && $this->check_video_compat($this->video_info) == false) {
                        $recomp_force = true;
                    }
                    
                    if (isset($recomp_force) && $recomp_force==true) {
                        //we must recompress
                        //FFMPEG PROCESS HERE !
                        $ff_options.=' \''.$process['output'].'\' 2>&1';
                        $process_cmd = '/usr/bin/ffmpeg'.$ff_options;
                        $job_manager_call='php '.$CONFIG::HELPHP_FOLDER.'utils/job_manager.php -a"new" -t"'.$CONFIG::HOME_FOLDER.'" -c"'.$process_cmd.'" -k"'.$process['media_id'].'"';
                        
                        // Add a callback to delete original if needed.
                        if (isset($process['original']) && !$process['original']) {
                            // unlink($process['input']);
                            $job_manager_call.= ' -C"rm -f '.$process['input'].'"';
                        }

                        $res = shell_exec($job_manager_call);

                    } else {
                        //no recompress
                        if (isset($process['original']) && !$process['original']) {
                            rename($process['input'], $process['output']);
                        } else {
                            copy($process['input'], $process['output']);
                        }
                    }
                    
                    if (!$no_bdd){
                        //saving children data in DB :
                        $id_media = $this->save_media($process['media_id'], $process['use_key'], 0, $process['output'], $filename,0, 2);
                        $this->save_use($process['media_id'], $process['use_key'], 0, $id_media, $id_process);
                    }
                    return 'done';
                } else {
                    Utils::error_log('output path error '.$process['output']);
                    return 'output path error '.$process['output'];
                }
            }
        } else {
            Utils::error_log('video input file error '.$process['in']);
            return 'video input file error';
        }
    }
    /**
     * Checks if a video file is compatible (codec, pixel format, etc.).
     *
     * @param array $videoInfos The video information array (from ffprobe).
     * @return bool True if compatible, false otherwise.
     */
    public function check_video_compat($videoInfos){
        $json=$videoInfos['allInfo'];
        foreach ($json['streams'] as $key => $line) {
            if ($line['codec_type'] == 'audio') {
                $videoCheck=($line['codec_name'] =='aac' ? true : false);
            } elseif ($line['codec_type'] == 'video') {
                $videoCheck=($line['codec_name'] =='h264' ? true : false);
                $videoCheck=($line['pix_fmt'] =='yuv420p' ? true : false);
            }
        }
        return $videoCheck;
    }
    /**
     * Checks if the given media ID and use key correspond to a video.
     *
     * @param string|false $media_id The logical media identifier.
     * @param int|false    $use_key  The use key.
     *
     * @return bool True if the media is a video, false otherwise.
     */
    public static function is_video($media_id = false, $use_key = false){
        global $DB,$FS;

        $db_data = $DB->table('media_data');
        $db_use = $DB->table('media_use');
        if ($media_id && $use_key) {
            $q = 'SELECT d.path FROM '.$db_data.' d INNER JOIN '.$db_use.' u ON u.field_identifier="'.$media_id.'" AND u.use_key='.$use_key.' ORDER BY d.id LIMIT 1';
            $path = $DB->query_value($q);
            if (in_array(strtolower($FS->get_file_ext($path)), Media::video_ext)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Retrieves video information (width, height, fps, duration, etc.) using ffprobe.
     *
     * @param string $path The path to the video file.
     * @return array Video information array.
     */
    public static function get_video_info($path){
        $options='-v error -show_streams -show_format -of json';
        $res = shell_exec(sprintf('ffprobe %s %s', $options, escapeshellarg($path)));
        $json = json_decode($res, true);
        foreach ($json['streams'] as $key => $line) {
            if ($line['codec_type'] == 'video') {
                $result['width']=$line['width'];
                $result['height']=$line['height'];
                $result['fps']=explode('/',$line['r_frame_rate']);
                $result['fps'] = $result['fps'][0]/$result['fps'][1];
            }
        }
        $result['size']=$json['format']['size'];
        $result['duration']=$json['format']['duration'];
        $result['allInfo']=$json;
        return $result;
    }
    /**
     * take the classic video path to create the "pp" variable for public/media/media.php player in the instance
     *
     * @param array $video_params   The video params 
     *
     * @return string the pp content
     * 
     * @see in media module public, media.php is there to call media for display images or streaming with security options.
     */
    public function encode_video_src($video_params){
        return str_rot13(urlencode(implode('|µ|',$video_params)));
    }



    /**
     * Streams a file to the browser, supporting range requests and optional locking.
     *
     * @param string $path   The file path to send.
     * @param bool   $inline Optional. Whether to display inline or as attachment. Default true.
     * @param bool   $lock   Optional. Whether to lock the file during transfer. Default false.
     *
     * @return bool True if the file was sent successfully, false otherwise.
     * 
     * @see in media module public, media.php is there to call media for display images or streaming with security options.
     */
    public static function send_file($path,$inline=true,$lock=false){
        global $FS;
        session_write_close();
        ob_start();
        ob_end_flush();
        $path = stripslashes($path);
        if (!is_file($path) || connection_status()!=0) {
            Utils::error_log('path not found or connection aborted/timeout');
            return(false);
        }

        //to prevent long file from getting cut off from    //max_execution_time
        set_time_limit(0);

        $name=basename($path);

        $ext = strtolower($FS->get_file_ext($path));
        if ($ext == 'gz' || $ext=='gzip') {
            header("Content-Encoding: gzip\n");
        } else {
            $mime = Utils::get_mime_type($ext);
            header("Content-Type: ".$mime);
        }
        $fp = fopen($path, 'rb');
        $size = filesize($path);
        $length = $size; // Content length
        $start = 0; // Start byte
        $end = $size - 1; // End byte

        header("Accept-Ranges: bytes");
        if ($ext =="html" || $ext =="htm" || $ext =="xml" || $ext =="xhml") {
            header("Content-Security-Policy: script-src 'none' child-src 'none' object-src 'none'");
        }
        if (isset($_SERVER['HTTP_RANGE']) && !empty($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
            }
            $c_end = ($c_end > $end) ? $end : $c_end;
            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $start-$end/$size");
                exit;
            }
            $start = $c_start;
            $end = $c_end;
            $length = $end - $start + 1;
            fseek($fp, $start);
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$size");
            header("Content-Length: ".$length);
            header("Cache-Control: max-age=31536000");
            header("Pragma: Public");
        } else {
            header("Content-Length: ".$size);
            header("Content-Transfer-Encoding: binary\n");
            header("Cache-Control: max-age=31536000");
            header("Pragma: Public");
        }

        if($inline){
            header('Content-Disposition: inline; filename="'.$name.'"');
        } else {
            header('Content-Disposition: attachment; filename="'.$name.'"');
        }

        $buffer = 1024 * 8;
        if ($lock){
            $time_lock = time();
            $FS->lock_time = 10;
            $FS->add_lock($path,$time_lock);
        }

        while (!feof($fp) && ($p = @ftell($fp)) <= $end) {
            if ($p + $buffer > $end) {
                $buffer = $end - $p + 1;
            }
            set_time_limit(0);
            echo @fread($fp, $buffer);
            flush();
        }

        fclose($fp);

        if ($lock){
            $FS->delete_lock($path,$time_lock);
        }
        
        return((connection_status()==0) and !connection_aborted());
    }
}