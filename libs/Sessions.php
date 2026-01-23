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
 * @class Sessions
 * 
 * Handles session management for the application, supporting both Redis and database-backed sessions.
 * Provides methods for session initialization, ID regeneration, reading, writing, destroying, and garbage collection.
 * Also manages session references and supports secure cookie settings.
 *
 * @package helPHP\libs
 */
class Sessions
{
    /**
     * @var Object $instance instance of the Sessions class.
     */
    public static $instance = null;

    /**
     * @var bool $debug Enable debug logging for session operations.
     */
    public static $debug = false;

    /**
     * @var string $save_path Path where session files are stored (if not using Redis).
     */
    private $save_path;

    /**
     * @var string $name Name of the session.
     */
    private $name;

    /**
     * @var int $last_id_update Timestamp of the last session ID update.
     */
    private $last_id_update = 0;

    /**
     * @var bool $redis is used for session storage.
     */
    public $redis = true;

    /**
     * @var int $id Internal session identifier.
     */
    private $id = 0;

    /**
     * @var int $purge_delay (in seconds) before purging old session references.
     */
    public $purge_delay = 0;

    /**
     * @var string $db_sessions Name of the database table for session data.
     */
    private $db_sessions;

    /**
     * @var string $db_references Name of the database table for session references.
     */
    private $db_references;

    /**
     * Creates or returns the instance of the Sessions class as global $SESSION 
     * this global is used essentialy in this class, 
     * because using the classic PHP $_SESSION global will be the same. 
     *
     * Initializes session handling, sets up Redis or database as backend, and configures session cookie parameters.
     *
     * @param bool $forceNewInstance If true, forces creation of a new instance.
     * @return global $SESSION The session instance.
     */
    public static function create_instance($forceNewInstance = false)
    {
        global $SESSION,$CONFIG;

        if ($SESSION != null && $forceNewInstance == false) {
            return $SESSION;
        }
            
        $SESSION = new Sessions();
        $SESSION->redis=$CONFIG::REDIS;
        $session_name = preg_replace("/[^A-Za-z0-9]/", '', $CONFIG::SITE_NAME);

        session_name($session_name);
        if (!$SESSION->redis) {
            if ($CONFIG::DEVMODE) {
                $SESSION->init_db();
            }
            
            session_set_save_handler(
                array($SESSION, 'open'),
                array($SESSION, 'close'),
                array($SESSION, 'read'),
                array($SESSION, 'write'),
                array($SESSION, 'destroy'),
                array($SESSION, 'gc'),
                array($SESSION, 'create_id')
            );
        }
        
        $SESSION::open_session();
        
        return $SESSION;
    }

    /**
     * Checks and creates the necessary database tables for session storage if Redis is not used
     * and those tables missing.
     *
     * @return void
     */
    public function init_db(){
        global $DB;

        $this->db_sessions = $DB->table('sessions');
        if (!$DB->table_exists($this->db_sessions)){
            $json_db = ['table'=>$this->db_sessions, 'fields'=>[
                'id'=>                  ['type'=>'bigint', 'primary'=>true],
                'session_id'=>          ['type'=>'varchar', 'size'=>32, 'default'=>'', 'index'=>true],
                'session_data'=>        ['type'=>'text', 'default'=>'NULL'],
                'creation_time'=>       ['type'=>'timestamp', 'default'=>'current_timestamp'],
                'session_expiration'=>  ['type'=>'timestamp', 'default'=>'current_timestamp'],
                'last_id_update'=>      ['type'=>'timestamp', 'default'=>'current_timestamp'],
            ]];
            $DB->query($DB->create_table_from_json(json_encode($json_db)));
        }

        $this->db_references = $DB->table('sessions_references');
        if (!$DB->table_exists($this->db_references)){
            $json_db = ['table'=>$this->db_references, 'fields'=>[
                'session_id'=>  ['type'=>'varchar', 'size'=>32, 'default'=>''],
                'id'=>          ['type'=>'bigint', 'default'=>'0'],
                'updatetime'=>  ['type'=>'timestamp', 'default'=>'current_timestamp']
            ]];
            $DB->query($DB->create_table_from_json(json_encode($json_db)));
        }
    }
    /**
     * Opens and configures the PHP session, setting cookie parameters and session lifetime.
     *
     * @param int $update_interval Interval (in seconds) for session ID regeneration. Default 300.
     * @param int $session_life_time Session lifetime in hours. Default 24.
     * @return void
     */
    public static function open_session($update_interval = 300, $session_life_time = 24)
    {
        global $SESSION,$CONFIG;
        if (defined('$CONFIG::SESSION_HOURS')) {
            $session_life_time = max($session_life_time, intval($CONFIG::SESSION_HOURS));
        }
        if ($SESSION->redis) {
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', $CONFIG::REDIS_ADDRESS);
        }
        ini_set('session.gc_maxlifetime', 3600 * $session_life_time);
        ini_set('session.use_only_cookies', true);
        ini_set('session.use_trans_sid', false);
        $secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $secure = true;
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $secure = true;
        }
        $httponly = true;
        // sameSite: none, secure // necessary to access the cookie from the iframe 
        
        // for clusters
        if ($CONFIG::CLUSTER_MODE){ 
            $samesite = 'none';
        } else {
            $samesite = 'lax';
        }
        $maxlifetime= 3600 * $session_life_time;
        if(PHP_VERSION_ID < 70300) {
            session_set_cookie_params($maxlifetime, '/; samesite='.$samesite, $CONFIG::DOMAIN, $secure, $httponly);
        } else {
            session_set_cookie_params([
                'lifetime' => $maxlifetime,
                'path' => '/',
                'domain' => $CONFIG::DOMAIN,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        }

        // start the session
        session_start();
        if (!$SESSION->redis) {
            // change the session id at a given interval in seconds
            $SESSION->update_id($update_interval);
        } else {
            $SESSION->update_id_redis($update_interval);
        }
    }
    /**
     * Regenerates the session ID at a given interval when using Redis.
     *
     * @param int $interval Interval (in seconds) for session ID regeneration. Minimum 300.
     * @return void
     */
    public static function update_id_redis($interval = 300)
    {
        if ($interval < 300) {
            $interval = 300;
        }

        if (!isset($_SESSION['helpduration'])){
            $_SESSION['helpduration'] = time();
            return;
        }
        
        if ($_SESSION['helpduration'] + $interval < time()) {
            session_regenerate_id();
            $_SESSION['helpduration'] = time();
        }
    }
    /**
     * Regenerates the session ID at a given interval when using database sessions.
     * Updates session references and purges old session IDs.
     *
     * @param int $interval Interval (in seconds) for session ID regeneration. Minimum 10.
     * @return void|false Returns false if update is not needed.
     */
    public static function update_id($interval = 300)
    {
        global $DB,$SESSION;
        if ($interval < 10) {
            $interval = 10;
        }

        if ($SESSION->last_id_update == 0) {
        } elseif ($SESSION->last_id_update + $interval < time()) {
            global $DB,$SESSION;

            // reading the date of the last updated id in the database to be sure to have the correct date
            $SESSION->last_id_update = $DB->prepared_query_value('SELECT unix_timestamp(last_id_update) FROM '.$SESSION->db_sessions.' WHERE session_id=?', 's', array(session_id()));
            if ($SESSION->last_id_update + $interval > time()) {
                return false;
            }

            // run GC
            $delete_count = session_gc();

            // store previous session_id
            $SESSION->check_key(session_id());
            $old = session_id();
            if ($SESSION->id > 0) {
                $DB->prepared_query('INSERT INTO '.$SESSION->db_references.' SET id=? , session_id=? ', 'is', array($SESSION->id , $old));
            }

            // generate new id
            session_regenerate_id();

            // store new session_id
            $new = session_id();
            $DB->prepared_query('UPDATE '.$SESSION->db_sessions.' SET session_id=? , last_id_update=from_unixtime(?) WHERE session_id=?', 'sis', array($new , time() , $old));

            if ($SESSION->id > 0) {
                $DB->prepared_query('INSERT INTO '.$SESSION->db_references.' SET id=? , session_id=? ', 'is', array($SESSION->id , $new));
            }
            // purge too old session_id
            $delay = $SESSION->purge_delay;

            if ($delay == 0) {
                $delay = $interval;
            }

            if ($delay > 0) {

                $q = 'DELETE LOW_PRIORITY IGNORE FROM '.$SESSION->db_references.' WHERE (unix_timestamp(updatetime)+'.intval($delay*2).') < unix_timestamp(now())';
                $test = $DB->query($q);
            }
            $_SESSION['last_id_update'] = time();
        }
    }

    public function __construct()
    {
    }
    /**
     * Opens the session handler (required by session_set_save_handler).
     *
     * @param string $save_path Path where to store/retrieve session.
     * @param string $sessionName Name of the session.
     * @return bool Always returns true.
     */
    public function open($save_path, $sessionName)
    {
        $this->save_path = $save_path;
        $this->name = $sessionName;
        return true;
    }

    public function close()
    {
        return true;
    }
    /**
     * Checks and updates the session key and internal ID.
     *
     * @param string $key The session key to check.
     * @return string|false The valid session key, or false if not found.
     */
    public function check_key($key)
    {
        global $DB;

        $line = $DB->prepared_query_line('SELECT DISTINCT id,session_id FROM '.$this->db_sessions.' WHERE session_id=? OR id=?', 'si', array($key,$this->id));

        if (!$line) {
            $q = 'SELECT DISTINCT '.$this->db_sessions.'.id,'.$this->db_sessions.'.session_id , unix_timestamp(now()) - unix_timestamp('.$this->db_references.'.updatetime) AS purgedelay FROM '.$this->db_sessions.',';
            $q.= ' '.$this->db_references.' WHERE '.$this->db_references.'.id = '.$this->db_sessions.'.id AND '.$this->db_references.'.session_id=? ';
            $line = $DB->prepared_query_line($q, 's', array($key));
        }

        if (!$line) {
            return false;
        }

        if ($line['id'] != $this->id) {
            $this->id = $line['id'];
            return $key;
        }

        if ($line['id'] == $this->id && $line['session_id'] == $key) {
            return $key;
        }

        if ($line['id'] == $this->id) {
            if (isset($line['purgedelay'])) {
                $this->purge_delay = $line['purgedelay'];
            }

            $key = $line['session_id'];
        }

        return $key;
    }
    /**
     * Reads session data from the database.
     *
     * @param string $key The session key.
     * @return string The session data, or an empty string if not found.
     */
    public function read($key)
    {
        global $DB,$CONFIG;

        $can_be_disconnected = DB::create_instance();

        $current_key = $this->check_key($key);

        if ($current_key != false) {
            $key = $current_key;
        }

        $stmt = 'SELECT id , session_data , unix_timestamp(last_id_update) as last FROM '.$this->db_sessions.' ';
        $stmt.= ' WHERE session_id=? OR id=? ';
        $stmt.= ' AND unix_timestamp(session_expiration) > unix_timestamp(date_sub(now(), interval '.$CONFIG::SESSION_HOURS.' hour))';
        $sth = $DB->prepared_query_line($stmt, 'si', array($key,$this->id));
        if ($can_be_disconnected) {
            $DB->close();
        }

        if (is_array($sth)) {
            $this->last_id_update = $sth['last'];
            $this->id = $sth['id'];

            if (Sessions::$debug == true) {
                Utils::error_log('read ('.$this->id.') : '.stripslashes($sth['session_data']));
            }

            return($sth['session_data']);
        } else {
            if (Sessions::$debug == true) {
                Utils::error_log('read ('.$this->id.') : no data !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
                Utils::error_log($stmt);
                Utils::error_log($sth);
            }
            return '';
        }
    }
    /**
     * Writes session data to the database.
     *
     * @param string $key The session key.
     * @param string $val The session data to write.
     * @return bool True on success.
     */
    public function write($key, $val)
    {
        global $DB,$CONFIG;

        $current_key = $this->check_key($key);

        if ($current_key != false) {
            $key = $current_key;
        }

        if ($current_key == false) {
            $insert_stmt  = 'INSERT INTO '.$this->db_sessions.' SET session_id=? , session_data=? , session_expiration=(date_add(now(), interval '.$CONFIG::SESSION_HOURS.' hour)) ';

            $DB->prepared_query($insert_stmt, 'ss', array($key,$val));

            $this->id = $DB->last_insert_id;

            if (Sessions::$debug == true) {
                Utils::error_log('insert in session : ('.$this->id.') '.$key.' ='.$val);
                Utils::error_log('insert ('.$this->id.') '.$val);
            }

            $DB->prepared_query('INSERT INTO '.$this->db_references.' SET id=? , session_id=? ', 'is', array($this->id , $key));
        } else {
            $update_stmt  = 'UPDATE '.$this->db_sessions.' SET session_data=?, ';
            $update_stmt .= 'session_expiration = (date_add(now(), interval '.$CONFIG::SESSION_HOURS.' hour))';
            $update_stmt .= 'WHERE session_id=?';

            $DB->prepared_query($update_stmt, 'ss', array($val,$key));

            if (Sessions::$debug == true) {
                Utils::error_log('update ('.$this->id.') '.$val, true);
            }

            $data = $DB->prepared_query_value('SELECT DISTINCT session_expiration FROM '.$this->db_sessions.' WHERE id=?', 'i', [$this->id]);
        }
        // $DB->close();

        return true;
    }
    /**
     * Destroys a session and removes its references from the database.
     *
     * @param string $key The session key.
     * @return bool True on success.
     */
    public function destroy($key)
    {
        global $DB;
        $can_be_disconnected = DB::create_instance();

        $current_key = $this->check_key($key);

        if ($current_key != false) {
            $key = $current_key;
        }

        $DB->prepared_query('DELETE FROM '.$this->db_sessions.' WHERE session_id=?', 's', array($key));
        $DB->prepared_query('DELETE FROM '.$this->db_references.' WHERE id=?', 'i', array($this->id));

        if ($can_be_disconnected) {
            $DB->close();
        }

        return true;
    }
     /**
     * Garbage collector for sessions. Removes expired sessions from the database.
     *
     * @param int $maxlifetime Maximum session lifetime in seconds.
     * @return int Number of sessions deleted.
     */
    public function gc($maxlifetime = 0)
    {
        global $DB;
        $can_be_disconnected = DB::create_instance();

        if ($maxlifetime == 0) {
            $q = 'DELETE LOW_PRIORITY IGNORE FROM '.$this->db_sessions.' WHERE unix_timestamp(session_expiration) < unix_timestamp(now())';
        } else {
            $q = 'DELETE LOW_PRIORITY IGNORE FROM '.$this->db_sessions.' WHERE unix_timestamp(creation_time)+'.intval($maxlifetime).' < unix_timestamp(now()) AND unix_timestamp(session_expiration) < unix_timestamp(now())';
        }

        $DB->query($q);
        $nb = $DB->query_value('SELECT ROW_COUNT();');

        if ($nb>0) {
        }

        if ($can_be_disconnected) {
            $DB->close();
        }

        return $nb;
    }
    /**
     * Generates a new session ID.
     *
     * @return string The new session ID.
     */
    public function create_id()
    {
        $sid = md5(time() . random_int(0, 1000));
        return $sid;
    }
}
/**
 * Forces the session to be closed and immediately restarted.
 *
 * Useful for updating session data and cookie.
 *
 * @return void
 */
function session_force_update()
{
    session_write_close();
    session_start();
}