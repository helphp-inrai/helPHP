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
 * @class Ssh
 * 
 * Provides SSH communication capabilities, including command execution and optional tunneling.
 * Supports authentication via username/password or public/private key files.
 * Can establish a direct SSH connection or tunnel through an intermediate host.
 * 
 * Can permit communication between a docker container to a hosting server as tunnel for a container running on it !
 *
 * @package helPHP\libs
 */
Class Ssh{
      
    /**
     * @var string $host Destination host.
     */
    public $host;

    /**
     * @var int $port Destination port.
     */
    public $port;

    /**
     * @var string $username SSH username.
     */
    public $username;

    /**
     * @var string $password SSH password (if using password authentication).
     */
    public $password;

    /**
     * @var string $pubkeyfile Path to the public key file (if using key authentication).
     */
    public $pubkeyfile;

    /**
     * @var string $privatekeyfile Path to the private key file (if using key authentication).
     */
    public $privatekeyfile;

    /**
     * @var string $command The command to execute on the remote host.
     */
    public $command;

    /**
     * @var string|false $tunneltarget Optional. Tunnel target in the same format as destination.
     */
    public $tunneltarget;

    /**
     * @var bool $withkey Whether to use key authentication.
     */
    public $withkey;

    /**
     * @var bool $tunnelwithkey Whether to use key authentication for the tunnel.
     */
    public $tunnelwithkey;

    /**
     * @var mixed $result The result of the executed command.
     */
    public $result;

    /**
     * Ssh constructor.
     *
     * Parses the destination string, sets up authentication parameters, and executes the command.
     * Supports both direct SSH and SSH tunneling.
     *
     * @param string $destination Connection string in the format user:pass@host:port or if with keys:
     *               username:path_to_pub_file/pub_file_name:path_to_priv_file/priv_file_name@host:port.
     * 
     * @param string $command     The command to execute on the remote host.
     * 
     * @param string|false $tunneltarget Optional. Tunnel target in the same format as destination.
     */
    public function __construct($destination,$command,$tunneltarget=false){
        
        $tmp = explode('@',trim($destination));
        $tmphost = explode(':',$tmp[1]);
        $this->host = $tmphost[0];
        $this->port = $tmphost[1];
        
        //now we parse for credentials
        $tmpcred = explode(':',$tmp[0]);
        //classic case if $tmp got 2 args we are in a user / pass connection case...
        $this->username = $tmpcred[0];
        $this->password = $tmpcred[1];
        if(isset($tmpcred[2])){
        //of course we perhaps need to discuss with ssh keys instead of user pass in clear...
        //in that case we need to indicate in the connection or the tunnel to use files...
        //so destination or tunneltarget must be written like this: username:path_to_pub_file/pub_file_name:path_to_priv_file/priv_file_name@host:port
        //example : user:/home/user/.ssh/id_dsa.pub:/home/user/.ssh/id_dsa@server:22
            $this->withkey=true;
            $this->pubkeyfile=$tmpcred[1];
            $this->privatekeyfile=$tmpcred[2];
        }else{
            $this->withkey=false;
            $this->pubkeyfile=false;
            $this->privatekeyfile=false;
        }
        
        $this->command = $command;
        
        $this->tunneltarget = $tunneltarget;
        //if we need to make an ssh tunnel with another network module or to pingpong with our own container.
        // as the www-data user can't be elevated, it's possible to call another container to resend a command to our elevated user.
        $this->result=$this->send();
       
    }

     /**
     * Establishes the SSH connection, authenticates, and executes the command.
     * Handles both direct and tunneled connections, and supports password or key authentication.
     *
     * @return string The output of the executed command, or an error message on failure.
     */

    public function send() {
        // Utils::error_log('sending');
        if (!$connection = ssh2_connect($this->host, $this->port)){
            sleep(10);
            if (!$connection = ssh2_connect($this->host, $this->port)){
                // Utils::error_log('1');
                return ('1 Unable to connect to host "'.$this->host.'" on port '.$this->port);
            }
        }
        if ($this->withkey){
            if (!ssh2_auth_pubkey_file($connection, $this->username, $this->pubkeyfile, $this->privatekeyfile)){
                // Utils::error_log('2');
                return ('Unable to authenticate. bad user - key file ?');
            }
        }else{
            if (!ssh2_auth_password($connection, $this->username, $this->password)){
                // Utils::error_log('3');
                return ('Unable to authenticate. bad log-pass ?');
            }
        }
        
        //if we pingpong or tunnel :
        if ($this->tunneltarget){
            //we need to make the connection to the final target with same logic as main connection with temporary vars
            $tmp = explode('@',trim($this->tunneltarget));
            $tmphost = explode(':',$tmp[1]);
            $thost = $tmphost[0];
            $tport = $tmphost[1];
            
            //now we parse for credentials
            $tmpcred = explode(':',$tmp[0]);
            //classic case if $tmp got 2 args we are in a user / pass connection case...
            $tusername = $tmpcred[0];
            $tpassword = $tmpcred[1];
            if(isset($tmpcred[2])){
            //same as in construct
                $twithkey=true;
                $tpubkeyfile=$tmpcred[1];
                $tprivatekeyfile=$tmpcred[2];
            }else{
                $twithkey=false;  
            }
            
            if (!$tconnection = ssh2_connect($thost, $tport)){
                return ('1 Unable to connect to tunnel target host "'.$thost.'" on port '.$tport);
            }
            if ($twithkey){
                if (!ssh2_auth_pubkey_file($tconnection, $tusername, $tpubkeyfile, $tprivatekeyfile)){
                    return ('Unable to authenticate to tunnel target . bad user - key file ?');
                }
            }else{
                if (!ssh2_auth_password($tconnection, $tusername, $tpassword)){
                    return ('Unable to authenticate to tunnel target . bad log-pass ?');
                }
            }
            $grantedconnection=$tconnection;
        }else{
            $grantedconnection=$connection;
        }
        $stream = ssh2_exec($grantedconnection, $this->command);
        stream_set_blocking( $stream, true );
        $toreturn=stream_get_contents($stream);

        fclose($stream);
        ssh2_disconnect($grantedconnection);
        if ($this->tunneltarget){
            ssh2_disconnect($tconnection);
        }
        return $toreturn;
    }
}