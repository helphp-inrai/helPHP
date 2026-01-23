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

//collections of network utils...

namespace helPHP\libs;

/**
 * @class Rest
 * 
 * Simple REST client for forging HTTP requests (GET, POST, PUT, DELETE) with support for
 * custom headers, file uploads (multipart/form-data), JSON payloads, and URL-encoded data.
 * Uses PHP streams (no cURL required).
 *
 * @package helPHP\libs
 */
Class Rest{
    
     /**
     * @var string $api_url The base URL of the API endpoint.
     */
    private $api_url = '';

    /**
     * @var string $command The command or endpoint to call.
     */
    public $command;

    /**
     * @var string $command_type HTTP method to use (GET, POST, PUT, DELETE).
     */
    public $command_type;

    /**
     * @var array $data Data to send with the request (query or body).
     */
    public $data;

    /**
     * @var array $header Custom headers to include in the request.
     */
    public $header;

    /**
     * @var bool $header_array If true, headers are sent as an array (for stream context).
     */
    public $header_array;

    /**
     * @var bool $encode If true, data is URL-encoded for POST/PUT.
     */
    public $encode;

    /**
     * @var array|false $files Files to send (for multipart/form-data).
     */
    public $files;

    /**
     * @var bool $json If true, data is sent as JSON.
     */
    public $json;

    /**
     * Rest constructor.
     *
     * @param string $apiurl        The base API URL.
     * @param string $command_type  HTTP method (GET, POST, PUT, DELETE).
     * @param string $command       The endpoint or command to call.
     * @param array|false $data     Data to send (default: empty array).
     * @param array|false $header   Headers to send (default: empty array).
     * @param bool $echo            If true, echoes the response directly.
     * @param bool $encode          If true, URL-encode data (default: true).
     * @param array|false $files    Files to send (for multipart/form-data).
     * @param bool $json            If true, send data as JSON.
     * @param bool $header_array    If true, headers are sent as an array.
     */
    public function __construct($apiurl,$command_type,$command,$data=false,$header=false,$echo=false,$encode=true,$files=false,$json=false,$header_array=false){
        $this->api_url=$apiurl;
        $this->command_type=$command_type;
        $this->command=$command;
        $this->data=($data)?$data:[];
        $this->header=($header)?$header:[];
        $this->encode=$encode;
        $this->files=($files)?$files:false;
        $this->json=$json;
        $this->header_array = $header_array;
        if ($echo){
            echo $this->client_request();
        }
        
    }
    
     /**
     * Executes the HTTP request to the API endpoint.
     *
     * Handles GET, POST, PUT, DELETE methods, custom headers, file uploads, and JSON payloads.
     * Uses PHP streams (fopen) for the request.
     *
     * @return string The response body from the API, or an error message.
     */
    public function client_request(){
        $serverAPI=$this->api_url;
        $result='nothing';
        //request done only with PHP, no CURL
        //preparing headers : 
        if ($this->header_array===true){
            $header_array = [];
            foreach ($this->header as $key=>$value) {
                array_push($header_array, $key.': '.$value);
            }
        } else {
            $headertxt='';
            foreach ($this->header as $key=>$value) {
                $headertxt.=$key.': '.$value.'\r\n';
            }
        }
        if ($this->command_type =="GET"){
            
            if ($this->command=='streaming'){ 
                if ($this->header_array===true){
                    array_push($header_array, "RANGE: ".$this->data['range']);
                } else {
                    $headertxt.="\r\nRANGE: ".$this->data['range'];
                }
            }
            if ($this->header_array===true){
                array_unshift($header_array, "Content-type: application/x-www-form-urlencoded");
            } else {
                 $headertxt = (strlen($headertxt)>0) ? "Content-type: application/x-www-form-urlencoded\r\n".$headertxt:"Content-type: application/x-www-form-urlencoded";
            }
            $options = array(
                'http' => array(
                    'header' => ($this->header_array) ? $header_array : $headertxt,
                    'max_redirects' => 1800,
                    'method' => 'GET'
                ),
            );
            $serverAPI.=$this->command.'?'.http_build_query($this->data);
        }else{
            if ($this->files){
                global $FS;
                //preparation du post avec fichier
                $boundary = "---------------------".microtime(true);
                $content='';
                $content .=  "--".$boundary."\r\n";
                
                $content.="--".$boundary."\r\n";
                foreach($this->files as $key => $filePath){
                    // Utils::error_log($filePath);
                    $file_contents = file_get_contents($filePath['tmp_name']); 
                    $ext=$FS->get_file_ext($filePath['name']);
                    $content.="Content-Disposition: form-data; name=\"file".$key."\"; filename=\"".basename($filePath['name'])."\"\r\n";
                    $content.="Content-Type: ".Utils::get_mime_type($ext)."\r\n\r\n";
                    $content.=$file_contents."\r\n";
                }
                

                foreach($this->data as $key => $val)
                {
                    $content .= "--".$boundary."\r\n"; 
                    $content .= "Content-Disposition: form-data; name=\"".$key."\"\r\n\r\n".$val."\r\n"; 
                }
                $content.="--".$boundary."--\r\n";
                
                if ($this->header_array===true){
                    array_push($header_array, 'Content-Type: multipart/form-data; boundary='.$boundary);
                } else {
                    $headertxt.="\r\n".'Content-Type: multipart/form-data; boundary='.$boundary;
                }
                // use key 'http' even if you send the request to https://...
                $options = array(
                    'http' => array(
                        'header'  => ($this->header_array) ? $header_array : $headertxt,
                        'method'  => 'POST',
                        'content' => $content
                    )
                );
            } else {
                if ($this->json){
                    if ($this->header_array===true){
                        array_unshift($header_array, "Content-type: application/json");
                    } else {
                        $headertxt = (strlen($headertxt)>0) ? "Content-type: application/json\r\n".$headertxt:"Content-type: application/x-www-form-urlencoded";
                    }
                    $options = array(
                        'http' => array(
                            'header' => ($this->header_array) ? $header_array : $headertxt,
                            'max_redirects' => 1800,
                            'method'  => 'POST',
                            'content' => json_encode($this->data)
                        )
                    );
                }else{
                    if ($this->header_array===true){
                        array_unshift($header_array, "Content-type: application/x-www-form-urlencoded");
                    } else {
                        $headertxt = (strlen($headertxt)>0) ? "Content-type: application/x-www-form-urlencoded\r\n".$headertxt:"Content-type: application/x-www-form-urlencoded";
                    }
                    $options = array(
                        'http' => array(
                            'header' => ($this->header_array) ? $header_array : $headertxt,
                            'max_redirects' => 1800,
                            'content' => $this->encode?http_build_query($this->data):$this->data,
                            'method' => $this->command_type
                        )
                    );
                }
            }
            $serverAPI.=$this->command;
        }

        try{
            $context  = stream_context_create($options);
            $fp = fopen($serverAPI, 'rb', false, $context);
            
        }catch (\Exception $e) {
            $result= 'Cannot access to '.$this->api_url.' , exception : '.$e ;
        }
        if($fp){
            $result = stream_get_contents($fp);
        }
        return $result;
    }
}