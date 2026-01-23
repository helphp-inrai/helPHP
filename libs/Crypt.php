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
 * @class Crypt
 * 
 * This class is used to encrypt and decrypt data, create password hashes, and manage cryptographic operations.
 * the default key used for encryption and decryption is 'randomkey', but it can be overridden by passing a different key to the constructor.
 * It can be easily rewritten in any language, as it uses basic operations like XOR and string manipulation, to create encrypted exchange thru 
 * the public API, and it's fast.
 * 
 * @package helPHP\libs
 */
class Crypt
{
    /**
     * The default cryptographic key used for encryption and decryption.
     * This key can be overridden by passing a different key to the constructor.
     * 
     * @var string
     */
    private $crypt_key = 'randomkey';
    /**
     * The cost factor for bcrypt password hashing.
     * This determines the computational cost of hashing passwords.
     * 
     * @var int
     */
    public $bcrypt_cost = 8;

    public function __construct($crypt_key = '')
    {
        $this->crypt_key = $crypt_key;
    }

    /**
     * Creates an instance of the Crypt class if it does not already exist.
     * This method initializes the global $CRYPT variable with a new Crypt instance.
     * It uses the cryptographic key defined in the Config class of the instance .
     */   
    public static function create_instance()
    {
        global $CRYPT, $CONFIG;

        if (!$CRYPT) {
            $CRYPT = new Crypt($CONFIG::CRYPT_KEY);
        }
    }

    /**
     * Redefines the bcrypt cost factor based on a target time for hashing.
     * This method adjusts the cost factor until the hashing operation takes at least the specified time.
     * 
     * @param int $timeTarget The target time in milliseconds for the password hashing operation.
     * @return int The new bcrypt cost factor.
     */
    public function redefine_bcrypt_cost($timeTarget = 50)
    { // 50 milliseconds
        $timeTarget /= 1000;
        $cost = 8;
        do {
            $cost++;
            $start = microtime(true);
            password_hash("test", PASSWORD_BCRYPT, ["cost" => $cost]);
            $end = microtime(true);
        } while (($end - $start) < $timeTarget);

        $this->bcrypt_cost = $cost;

        return $this->bcrypt_cost;
    }

    /**
     * Creates a password hash using bcrypt.
     * This method hashes the provided password using the bcrypt algorithm with the defined cost factor.
     * With PASSWORD_BCRYPT algo, the password length is limited to 72 characters (should be enough for most cases).
     * 
     * @param string $password The password to be hashed.
     * @return string The hashed password.
     */
    public function create_password_hash($password)
    {
        if (strlen($password) > 72) {
            Utils::error_log('Crypt.create_password_hash : Password "'.$password.'" is too long !');
        }
        return password_hash($password, PASSWORD_BCRYPT, array('cost' => $this->bcrypt_cost));
    }

    public function verify_password_hash($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Generates a random password of a specified length.
     * This method creates a password consisting of letters and numbers, with a mix of uppercase and lowercase characters.
     * can be used as an alternative to bcrypt password hashing for temporary passwords or other purposes.
     * 
     * @param int $length The length of the generated password. Default is 8 characters.
     * @return string The generated password.
     */
    public function generate_password($length=8)
    {
        $letters = 'abcdGefLghQjkmnpSqZrsKtuMXvwyxz2346789_-';
        $t = strlen($letters)-1;
        $result = '';
        for ($i=0; $i < $length; $i++) {
            $l = substr($letters, rand(0, $t), 1);
            if (rand(0, 100)>50) {
                $l = strtoupper($l);
            }
            $result .= $l;
        }

        return $result;
    }
    
    /**
     * Encrypts a string using a specified key and an optional random key.
     * This method compresses the string, applies XOR encryption with the key, and returns the encrypted result.
     * If no key is provided, it uses the default crypt_key defined in the class combined with a randomkey.
     * It's better to specify a key and a randomkey and change them regularly to ensure security.
     * 
     * @param string $str The string to be encrypted.
     * @param string $key The key used for encryption. If empty, the default crypt_key is used.
     * @param string $randomkey An optional random key to modify the encryption process.
     * @return string The encrypted string.
     */ 

    public function encrypt($str, $key = '', $randomkey = '')
    {
        if (!is_string($str)) {
            $str = '{SRZ}'.serialize($str);
        } else {
            $str = '{STR}'.$str;
        }

        // add some compression
        $str = gzdeflate((string)$str);

        $randmode = false;
        if ($key == '' && $randomkey == '') {
            $randmode = true;
        }

        if ($key == '') {
            $key = $this->crypt_key;
        }
        if ($randomkey != '') {
            $key = $randomkey.$key;
        }
        if ($randomkey == '') {
            $randomkey = substr($key, 0, 9);
        }

        $key = Crypt::str_crc($randomkey) . $key;

        if ($randmode) {
            $rand = rand(1, strlen($key));
        } else {
            $rand = (floor(Crypt::str_crc($randomkey)) % (strlen($key)-1)) + 1;
        }

        $key = substr($key, $rand-1, strlen($key)) . substr($key, 0, $rand-1);

        $result = '';
        for ($i=0;$i<strlen($str);$i++) {
            $k = ord(substr($key, $i % strlen($key), 1));
            $l = Crypt::bitXor(ord(substr($str, $i, 1)), $k);
            if ($l < 100) {
                $l = 657 - $l;
            }
            $result .= (string)$l;
        }

        if (strlen((string)$rand) == 1) {
            $rand = '0'.$rand;
        }

        if ($randmode) {
            $pos = rand(1, 9);
        } else {
            $pos = floor(strlen($randomkey)/2);
        }

        $result = $pos . substr($result, 0, $pos) . $rand . substr($result, $pos, strlen($result));

        return $result;
    }
    /**
     * Decrypts a string using a specified key and an optional random key.
     * This method reverses the encryption process, decompresses the string, and returns the original data.
     * If no key is provided, it uses the default crypt_key defined in the class combined with a randomkey.
     * 
     * @param string $str The encrypted string to be decrypted.
     * @param string $key The key used for decryption. If empty, the default crypt_key is used.
     * @param string $randomkey An optional random key to modify the decryption process.
     * @return mixed The decrypted data, which can be a string or an unserialized array.
     */
    public function decrypt($str = '', $key = '', $randomkey = '')
    {
        if ($key == '') {
            $key = $this->crypt_key;
        }
        if ($randomkey != '') {
            $key = $randomkey.$key;
        }
        if ($randomkey == '') {
            $randomkey = substr($key, 0, 9);
        }

        $str = (string)$str;

        $key = Crypt::str_crc($randomkey) . $key;

        $pos = substr($str, 0, 1);
        settype($pos, 'integer');


        $rand = substr($str, $pos+1, 2);
        settype($rand, 'integer');



        $str = substr($str, 1, $pos) . substr($str, $pos+3, strlen($str));

        $key = substr($key, $rand-1, strlen($key)) . substr($key, 0, $rand-1);

        $result = '';
        if ($str != '') {
            $lst = Crypt::str_splitchunk($str, 3);

            $i = 0;
            foreach ($lst as $chunk) {
                $k = ord(substr($key, $i % strlen($key), 1));
                $l = $chunk;

                settype($l, 'integer');

                if ($l > 255) {
                    $l = 657 - $l;
                }

                $l = Crypt::bitXor($l, $k);

                $result .= chr($l);
                $i++;
            }
        } else {
            $result = '';
        }

        // decompress
        $result = gzinflate($result);

        // unserialize
        switch (substr($result, 0, 5)) {
            case '{SRZ}':
                $result = unserialize(substr($result, 5, strlen($result)-5));
            break;

            case '{STR}':
                $result = substr($result, 5, strlen($result)-5);
            break;
        }

        return $result;
    }
    
    /**
     * Splits a string into chunks of a specified size.
     * This method divides the input string into smaller substrings of the given size.
     * 
     * @param string $str The input string to be split.
     * @param int $size The size of each chunk. Default is 3 characters.
     * @return array An array containing the chunks of the input string.
     */
    private function str_splitchunk($str = '', $size = 3)
    {
        $lst = array();
        $j = 0;
        $m = '';
        for ($i = 0 ; $i < strlen($str) ; $i++) {
            $j++;
            $m .= substr($str, $i, 1);
            if ($j == $size) {
                array_push($lst, $m);
                $j = 0;
                $m = '';
            }
        }
        return $lst;
    }

    /**
     * Calculates the CRC (Cyclic Redundancy Check) of a string.
     * This method computes a simple checksum by summing the squares of the first byte of a string (as a value between 0 and 255)
     * of each character in the string.
     * 
     * @param string $str The input string for which the CRC is to be calculated.
     * @return string The computed CRC as a string.
     */
    private function str_crc($str = '')
    {
        $crc = 0;
        for ($i = 0 ; $i < strlen($str) ; $i++) {
            $l = substr($str, $i, 1);
            $crc += floor(pow(ord($l), 2));
        }
        return (string)$crc;
    }

    /**
     * Performs a bitwise XOR operation on two integers.
     * This method takes two integers, converts them to binary strings, and performs a bitwise XOR operation on each bit.
     * The result is returned as an integer.
     * 
     * @param int $n The first integer.
     * @param int $m The second integer.
     * @return int The result of the bitwise XOR operation.
     */
    private function bitXor($n, $m)
    {
        $n=decbin($n);
        $m=decbin($m);

        if (strlen($n)>strlen($m)) {
            $dif=strlen($n)-strlen($m);
            $m=str_repeat('0', $dif).$m;
        } else {
            $dif=strlen($m)-strlen($n);
            $n=str_repeat('0', $dif).$n;
        }

        $r = '';
        for ($i = 0 ; $i < strlen($n) ; $i++) {
            $v1 = substr($n, $i, 1);
            $v2 = substr($m, $i, 1);

            if ($v1 == $v2) {
                $r .= '0';
            } else {
                $r .= '1';
            }
        }
        return bindec($r);
    }
    
    /**
     * Encodes an array to a JSON string and encrypts it.
     * This method converts the input array to a JSON string and then encrypts it using the Crypt class.
     * 
     * @param array $arr The array to be encoded and encrypted.
     * @return string The encrypted JSON string.
     */
    public function json_encode_encrypt($arr)
    {
        return Crypt::encrypt(json_encode($arr));
    }

    /**
     * Decrypts a JSON string and decodes it to an array.
     * This method decrypts the input string using the Crypt class and then decodes the resulting JSON string to an array.
     * 
     * @param string $str The encrypted JSON string to be decrypted and decoded.
     * @return array The decoded array from the decrypted JSON string.
     */
    public function json_decode_decrypt($str)
    {
        return json_decode(Crypt::decrypt($str), true);
    }
   
}