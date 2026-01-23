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

use \ReflectionMethod;

/**
 * @class DB
 * 
 * Made for MariaDB and MySQL, but should work with any SQL server, this class is used to manage the connection to the SQL server, 
 * all kind of queries, and the management of the results.
 * It also manages the connection to a master/slave cluster of SQL servers, allowing to switch between them in case of failure.   
 * It can also manage a centralized database, which is useful for multi-node applications with centralized user accounts, user groups etc.
 * All connections are maintained as global variables, so you can access them from anywhere in the application.
 * Config variables are set in the config/db.php file of the helPHP instance.  
 * default charset is utf8mb4, which is the recommended charset for MySQL and MariaDB.
 * see constructor for the parameters to pass to create an instance of this class.
 * or create_instance() to create a classic instance of the DB class for a helPHP instance.
 * 
 * basic usage :
 * ----------
 * $DB = new DB(array('host'=>'localhost', 'user'=>'myuser', 'password'=>'mypassword', 'dbname'=>'mydb', 'table_prefix'=>'mytableprefix'));
 * $DB->connect(); // to establish the connection
 * $DB->query('SELECT * FROM mytable'); // to execute a query
 * $DB->prepared_query('SELECT * FROM mytable WHERE id = ?','i', [$id]); // to execute a prepared query (query,typedef,params)
 * $DB->close(); // to close the connection when you are done
 * ----------
 * 
 * @package helPHP\libs
 */
class DB
{
    /**
     * indicates if the connection is in master/slave mode
     * we write on the master server and read on both the slave and the master server
     * in case of faillure of the master we must switch it off and reconfigure to connect to surviving mysql slave server
     *
     * @var bool
     */
    public $master_slave_mode = false;

    /**
     * mysql master server hostname/ip in cluster mode 
     *
     * @var string
     */

    public $master_host = ""; 

    /**
     * username for the slave server in cluster mode
     *
     * @var string
     */
    public $slave_user="";

    /**
     * password for the slave server in cluster mode
     * 
     * @var string
     */
    public $slave_password="";

    /**
     * mysql server hostname/ip for the slave server in cluster mode
     * or for the only server in non-cluster mode
     *
     * @var string
     */
    public $host = "";
    
    /**
     * mysql server username for the master server in cluster mode
     * or for the only server in non-cluster mode
     * (be careful, this is not the user of the slave server in cluster mode !)
     *
     * @var string
     */
    public $user = "";
    
    /**
     * mysql server password for the master server in cluster mode
     * or for the only server in non-cluster mode
     * (be careful, this is not the password of the slave server in cluster mode !)
     *
     * @var string
     */ 
    public $password = "";

    /**
     * database name to use.
     * 
     * @var string
     */
    public $db_name = ""; // database name

    /**
     * total number of requests 
     *
     * @var int
     */
    public $query_count = 0;  
    
    /**
     * total duration of requests in seconds
     *
     * @var int
     */
    public $query_time = 0; 
    
    /**
     * total number of failed requests
     * 
     * @var int
     */
    public $query_error_count = 0; // total number of failed request

    /**
     * counting of requests by type (insert, select, count, update, etc ...)
     *
     * @var array
     */
    public $query_count_debug = array();
    
    /**
     * total duration of each type of request
     * 
     * @var array
     */
    public $query_time_debug = array();

    /**
     * last error message from the SQL server
     * 
     * @var string
     */
    public $last_error = '';
    /**
     * pointer to the SQL connection
     * in default mode, this is the only connection used
     * in master/slave mode, this is the connection to the slave server
     * in case of failure of the master server, this connection will be used as the master server connection
     * 
     * @var resource
     */
    public $link_SQL = false;

    /**
     * pointer to the Master SQL connection in master/slave mode,
     *
     * @var int
     */
    public $link_master_SQL = 0;
    
    /**
     * value of the last ID created via an INSERT request
     *
     * @var int
     */
    public $last_insert_id = 0;

    /**
     * total number of lines (without the limit) of the last request made in gettable_data
     * this is used to manage the pagination of the results
     * 
     * @var int
     */
    public $_last_count = 0;

    // public $_split_query_label = array();
    // public $_split_query_count = array();
    // public $_split_query_time = array();

    /**
     * indicates if the connection to the master server is established
     *
     * @var bool
     */
    public $master_connected = false;

    /**
     * indicates if the connection to the SQL server is established
     * or if the connection to the slave server is established in master/slave mode
     *
     * @var bool
     */
    public $connected = false;
    
    /**
     * last limit start for LIMIT queries
     *
     * @var int
     */
    public $_last_limit_start = 0;

    /**
     * last limit count for LIMIT queries
     *
     * @var int
     */
    public $_last_limit_count = 0;

    /**
     * table prefix to use for the SQL queries
     * This is used to avoid conflicts with other applications using the same database.
     *
     * @var string
     */
    public $table_prefix = '';

    // protected $crypt = null;

    // CONNECTION AND MANAGEMENT OF INSTANCES

    /**
     * Create an instance of the DB class in $DB global variable.
     * This function checks if the global $DB variable is set, and if not, it creates a new instance of the DB class with the parameters defined in the CONFIG_DB class.
     * if the CONFIG_DB::DB_CENTRAL is set, it will also create a global $DB_CENTRAL variable that will be used to connect to the centralized database.
     * 
     * @return bool $can_be_disconnected
     * 
     */
    public static function create_instance()
    {
        global $DB,$CONFIG_DB;
        
        $can_be_disconnected = false;

        if (!$DB) {
            $DB = new DB(array('host'=>$CONFIG_DB::DB_HOST , 'user'=>$CONFIG_DB::DB_USER , 'password'=>$CONFIG_DB::DB_PASS , 'dbname'=>$CONFIG_DB::DB_BASE , 'table_prefix'=>$CONFIG_DB::DB_TABLE_PREFIX));

            $DB->table_prefix = $CONFIG_DB::DB_TABLE_PREFIX;

            $can_be_disconnected = true;
        }
        if (!$DB->connected) {
            $DB->connect();
        }
        //CHECKING IF THERE IS ANOTHER CENTRALIZED DB AND CREATE $DB_CENTRAL
        if ($CONFIG_DB::DB_CENTRAL){
            global $DB_CENTRAL; 
            if (!$DB_CENTRAL) {
                $DB_CENTRAL = new DB(array('host'=>$CONFIG_DB::DB_CENTRAL_HOST , 'user'=>$CONFIG_DB::DB_CENTRAL_USER , 'password'=>$CONFIG_DB::DB_CENTRAL_PASS , 'dbname'=>$CONFIG_DB::DB_CENTRAL_BASE , 'table_prefix'=>$CONFIG_DB::DB_TABLE_PREFIX));
            }
            if (!$DB_CENTRAL->connected) {
                $DB_CENTRAL->connect();
            }
        }else{
            global $DB_CENTRAL;
            $DB_CENTRAL = $DB;
        }
        return $can_be_disconnected;
    }

    /**
     * Constructor of the DB class.
     * This function initializes the connection parameters to the SQL server.
     * ex : $myDb = new DB(array('host'=>'localhost', 'user'=>'myuser', 'password'=>'mypassword', 'dbname'=>'mydb', 'table_prefix'=>'mytableprefix')); 
     * can be followeb by $myDb->connect() to establish the connection.
     * then, after usage : $myDb->close() to close the connection.
     * 
     * @param array The connection parameters to the SQL server 
     * @return void don't return anything
     */
    public function __construct($params = array())
    {
        global $CONFIG_DB;

        if ($CONFIG_DB::MASTER_SLAVE_MODE && $params['host'] != $CONFIG_DB::DB_CENTRAL_HOST){
            $this->host = $CONFIG_DB::DB_SLAVE_HOST; //secondary reader
            $this->master_host = $CONFIG_DB::DB_HOST; // writer
            $this->master_slave_mode = true;
            $this->user = $params['user'];
            $this->password = $params['password'];
            $this->slave_user = $params['user'];
            $this->slave_password = $params['password'];
            $this->master_slave_mode = true;
        }else{
            $this->host = $params['host'];
            $this->user = $params['user'];
            $this->password = $params['password'];
        }
        if (isset($params['table_prefix'])) {
            $this->table_prefix = trim($params['table_prefix']);
        }
        if (isset($params['dbname'])) {
            $this->db_name = $params['dbname'];
        }
    }
    /**
     * Check if the connection to the SQL server is established.
     * 
     * @return bool true if the connection is established, false otherwise
     */
    public function is_connected()
    {
        return $this->connected;
    }
    /**
     * Check if the connection to the master SQL server is established.
     * if not connected, it will try to connect.
     * and in case of failure, it will try to set a master server with the survivor.
     * 
     * @return bool true if the connection to the server is established, false otherwise
     */
    public function connect()
    {
        if ($this->connected) {
            return true;
        }

        if ($this->link_SQL) {
            @mysqli_close($this->link_SQL);
            if ($this->master_slave_mode && $this->link_master_SQL){
                @mysqli_close($this->link_master_SQL);
            }
        }
        if ($this->master_slave_mode){
            if(filter_var(gethostbyname($this->master_host), FILTER_VALIDATE_IP)){
                try {
                    $this->link_master_SQL = mysqli_connect(gethostbyname($this->master_host), $this->user, $this->password);
                    $this->master_connected = true;
                } catch (\mysqli_sql_exception $e) {
                    error_log("can't connect to writer mariadb, error ");
                }
            }
            if(filter_var(gethostbyname($this->host), FILTER_VALIDATE_IP)){ 
                try {
                    $this->link_SQL = mysqli_connect(gethostbyname($this->host), $this->slave_user, $this->slave_password);
                    $this->connected = true;
                } catch (\mysqli_sql_exception $e) {
                    error_log("can't connect to reader mariadb, error");
                }
            }
            if (!$this->master_connected && $this->connected){
                $this->master_slave_mode=false; // we'll run only with the reader that will be also the writer.
            }
            if ($this->master_connected && !$this->connected){
                $this->master_slave_mode=false; // we'll run only with the writer that will be also the reader.
                $this->link_SQL = $this->link_master_SQL;
            }
            if (!$this->master_connected && !$this->connected){
                die("can't connect to no mariadb member of the cluster");
            }
        } else {
            $this->link_SQL = mysqli_connect(gethostbyname($this->host), $this->user, $this->password) or die("can't connect to mysql or mariadb : " . mysqli_error($this->link_SQL));
        }

        if ($this->db_name) {
            $this->set_data_base($this->db_name);
        }

        if ($this->master_slave_mode){
            mysqli_set_charset($this->link_master_SQL, "utf8mb4"); //<-php7+
        }
        mysqli_set_charset($this->link_SQL, "utf8mb4"); //<-php7+

        $this->connected = true;

        return $this->connected;
    }
    /**
     * Close the connection to the SQL server.
     * This function disconnects from the SQL server and sets the connection variables to null.
     * Don't forget to call this function when you are done with the database connection to free resources.
     * 
     * @return void
     */
    public function close()
    {
        $this->disconnect();
    }

    /**
     * replace mysqli_ping
     *
     * @param mixed $db_object
     * 
     * @return [type]
     * 
     */
    public function test_connection($db_object){
        $test = $db_object->query("DO 1");
        return $test;
    }


    /**
     * will disconnect from the SQL server.
     * or from the master and slave SQL server in master/slave mode.
     *
     * @return void
     * 
     */
    public function disconnect()
    {
        if ($this->link_SQL) {
            $test = @mysqli_close($this->link_SQL);

            // if (function_exists("mysqli_ping") && !$test) {
            //     if (@mysqli_ping($this->link_SQL)) {
            //         $test = @mysqli_close($this->link_SQL);
            //     }
            // }
            if ($this->test_connection($this->link_SQL)) {
                $test = @mysqli_close($this->link_SQL);
            }
            $this->link_SQL = null;
            
            $this->connected = false;
        }
        if ($this->master_slave_mode && $this->link_master_SQL){
            $test = @mysqli_close($this->link_master_SQL);

            // if (function_exists("mysqli_ping") && !$test) {
            //     if (@mysqli_ping($this->link_master_SQL)) {
            //         $test = @mysqli_close($this->link_master_SQL);
            //     }
            // }
            if ($this->test_connection($this->link_master_SQL)) {
                $test = @mysqli_close($this->link_master_SQL);
            }
            $this->link_master_SQL = null;
            
            $this->master_connected = false;
        }
    }
    /**
     * Set the database to use.
     * This function selects the database to use for the SQL queries.
     * If the database does not exist, it will return false and log an error.
     * in master/slave mode, it will also select the database on the master server.
     * 
     * @param string $dbname The name of the database to use
     * @return bool true if the database was selected successfully, false otherwise
     */
    public function set_data_base($dbname)
    {
        if ($dbname === "") {
            return false;
        }

        $this->db_name = $dbname;

        // verification of the existence of a previous connection
        // if (function_exists("mysqli_ping")) {
        //     $test = @mysqli_ping($this->link_SQL);
        //     if (!$test) {
        //         $this->link_SQL = $this->connect();
        //     }
        // }
        if ($this->test_connection($this->link_SQL) === false) {
            $this->link_SQL = $this->connect();
        }

        $result = mysqli_select_db($this->link_SQL, $this->db_name);

        if (!$result) {
            Utils::error_log('Error selecting '.$dbname.' from '.$this->host);
            error_log('Error selecting '.$dbname.' from '.$this->host);
        }
        
        if ($this->master_slave_mode){
            
            // if (function_exists("mysqli_ping")) {
            //     $test = @mysqli_ping($this->link_master_SQL);
            //     if (!$test) {
            //         $this->link_master_SQL = $this->connect();
            //     }
            // }
            if ($this->test_connection($this->link_master_SQL) === false) {
                $this->link_master_SQL = $this->connect();
            }
            $result2 = mysqli_select_db($this->link_master_SQL, $this->db_name);

            if (!$result2) {
                Utils::error_log('Error selecting '.$dbname.' from '.$this->master_host);
                error_log('Error selecting '.$dbname.' from '.$this->master_host);
                $this->master_slave_mode=false;
            }else{
                if(!$result){
                    $this->master_slave_mode=false; // we'll run only with the writer that will be also the reader.
                    $this->link_SQL = $this->link_master_SQL;
                    $result=$result2; //to be able to continue on one leg.
                }
            }
            
        }
        return $result;
    } // set_data_base
    
    //***************************************************************************************************
    // Queries and functions linked to queries, be careful to go further to see the prepared queries ...

    /**
     * Execute a SQL query.
     * This function executes a SQL query and returns the result.
     * If the query is a multi-query, it will execute all queries in the string.
     * If the query is an INSERT query, it will return the last inserted ID.
     * If the query is a SELECT query, it will return the result set.
     * 
     * @see query_value()
     * @see query_line()
     * @see query_list()  
     * 
     * @param string|array $q The SQL query to execute, or an array of queries to execute as a multi-query
     * @return mixed The result of the query, or false if the query failed
     */
    public function query($q = '')
    {
        $multiquery = false;
        $this->last_error = '';

        if (is_array($q)) {
            $multiquery = true;
            $q = implode(';', $q);
        }

        $q = trim($q);

        if ($q == '') {
            return false;
        }

        // connection to the base
        $rows_affected = 0;
        $success = false;
        $now = microtime(true);
        if (!$this->connected){
            $this->connect();
        }
        if ($this->master_slave_mode){
            // exception to always request the slave when querying slave status
            if ($q == 'SHOW SLAVE STATUS') {
                $link = $this->link_SQL;
            } else if (strtolower(substr($q, 0, 6)) == "select" || $multiquery) {
                if (rand(0,1)) {
                    $link = $this->link_SQL;
                } else {
                    $link = $this->link_master_SQL;
                }
            }else{
                $link = $this->link_master_SQL;
            }
        }else{
            $link = $this->link_SQL;
        }
        if ($multiquery) {
            try {
                $r = mysqli_multi_query($link, $q);
            }catch(\mysqli_sql_exception $e) {
                $r = false;
            }
        } else {
            try {
                $r = mysqli_query($link, $q);
            }catch(\mysqli_sql_exception $e) {
                $r = false;
            }
        }

        if ($r === false) {

            
            if (class_exists('\helPHP\libs\Utils')) {
                Utils::error_log('-------------------------');
                Utils::error_log('QUERY ERROR :');
                Utils::error_log(json_encode(mysqli_error($link)));
                Utils::log_backtrace();
                Utils::error_log('-------------------------');
            } else {
                echo 'QUERRY ERROR :'.PHP_EOL.$q.PHP_EOL;
            }

        }

        if ($r) {
            $success = true;
        }

        $elapsed = (microtime(true) - $now);
        $this->query_time += $elapsed;
        $err_mess = "";

        if ($success) {

            // in the case of an insert, recovery of the generated ID
            if (strtolower(substr($q, 0, 6)) == "insert") {
                $this->last_insert_id = mysqli_insert_id($link);
            }
            
            // if we asked to calculate the total of elements found
            // in the case of a request with a limited number of returns,
            // retrieve this value

            if (stripos($q, "SQL_CALC_FOUND_ROWS") !== false) {
                $rc = mysqli_query($link, "SELECT FOUND_ROWS()");
                $rc = mysqli_fetch_array($rc, MYSQLI_ASSOC);
                $this->_last_count = current($rc);

                $limit_pos = strripos($q, ' limit ');
                if ($limit_pos !== false) {
                    $tmp = substr($q, $limit_pos+7, strlen($q) - $limit_pos - 7);
                    $values = explode(',', $tmp);

                    if (sizeof($values) == 1) { // LIMIT count
                        $this->_last_limit_start = 0;
                        $this->_last_limit_count = floor(trim($values[0]));
                    } else { // LIMIT start,count
                        $this->_last_limit_start = floor(trim($values[0]));
                        $this->_last_limit_count = floor(trim($values[1]));
                    }
                } else {
                    $this->_last_limit_start = 0;
                    $this->_last_limit_count = $this->_last_count;
                }
            } else {
                $this->_last_count = 0;
                if (is_resource($r)) {
                    $this->_last_count = mysqli_num_rows($r);
                }
            }
            $this->query_count++;
        } else {
            if (strtolower(substr($q, 0, 6)) == "insert") {
                $this->last_insert_id = "error";
            }
            // if the request failed, we log what happened

            $r = false;

            $err = mysqli_error($link);
            $this->last_error = $err;
            
            if (class_exists('\helPHP\libs\Utils')) {
                Utils::error_log($err);
            } else {
                echo $err.PHP_EOL;
            }
            

        }

        return $r;
    } 
    /**
    * Returns an array with the data needed to manage the pagination of the results.
    *
    * @return array $result
    *
    * The array contains the following keys:
    * $result['start'] : index of the first result to display
    * $result['limit'] : number of results to display from the first result returned (= number of results per page)
    * $result['total'] : total number of query results
    * $result['page_count'] : total number of pages
    * $result['page_index'] : index of the current page
    */
    public function last_pages_data()
    {
        $result = [];
        $result['start'] = $this->_last_limit_start;
        $result['limit'] = $this->_last_limit_count;
        $result['total'] = $this->_last_count;

        if ($result['limit'] > 0) {
            $nb_pages = ceil($result['total'] / $result['limit']);
        } else {
            $nb_pages = 0;
        }

        $result['page_count'] = $nb_pages;
        if ($result['limit'] > 0) {
            $result['page_index'] = ceil($result['start'] / $result['limit']);
        } else {
            $result['page_index'] = 0;
        }

        return $result;
    }

    /**
     * Execute a SQL query and return the first value of the first row.
     * This function is used to execute a query that returns only one value, such as a COUNT or a single field.
     * If the query is not a SELECT query, it will log an error and return the result of the query.
     * 
     * @param string $q The SQL query to execute
     * @return mixed The first value of the first row of the result set, or null if the query failed or returned no results
     */
    public function query_value($q = '')
    {
        if (!$q) {
            return null;
        }

        $r = $this->query($q);

        if (strtolower(substr($q, 0, 6)) != 'select') {
            Utils::error_log('wrong use of _queryValue()');
            Utils::error_log($q);
            return $r;
        }

        if ($r) {
            $value = mysqli_fetch_array($r);
            // we released the memory
            mysqli_free_result($r);
        } else {
            $value = null;
        }

        if ($value !== null) {
            $value = current($value);
        }

        return $value;
    } 

    /**
     * Execute a SQL query and return the first row of the result set as an associative array.
     * This function is used to execute a query that returns only one row, such as a SELECT query with a LIMIT 1.
     * If the query is not a SELECT query, it will log an error and return the result of the query.
     * 
     * @param string $q The SQL query to execute
     * @return array|null The first row of the result set as an associative array, or null if the query failed or returned no results
     */
    public function query_line($q = "")
    {
        if ($q == '') {
            return null;
        }

        $r = $this->query($q);

        $line = null;

        if ($r) {
            $line = mysqli_fetch_array($r, MYSQLI_ASSOC);
            mysqli_free_result($r);
            if (!$line) {
                $line = null;
            }
        }

        return $line;
    }

    /**
     * Execute a SQL query and return the result as an associative array for use in a select input. (generaly a H::select)
     * This function is used to execute a query that returns multiple rows, such as a SELECT query.
     * It will return an array with the key as the value of the specified key field and the value as the display name field.
     * If the query is not a SELECT query, it will log an error and return null.
     * 
     * @param string $q The SQL query to execute
     * @param string $keyname The name of the field to use as the key in the returned array
     * @param string $displayname The name of the field to use as the value in the returned array
     * @param mixed $firstblank If true or a string, adds a blank option at the beginning of the list
     * @return array|null An associative array for use in a select input, or null if the query failed or returned no results
     */
    public function query_list_for_select($q = "", $keyname ="", $displayname="", $firstblank = false)
    {
        $liste = $this->query_list($q);

        if ($firstblank != false) {
            if (is_string($firstblank)) {
                $liste = array_merge(array(
                    0 => $firstblank
                ), $liste);
            } else {
                $liste = array_merge(array(
                    0 => ""
                ), $liste);
            }
        }
        $data = array();

        foreach ($liste as $values) {
            $data[$values[$keyname]] = $values[$displayname];
        }
        return $data;
    }
    /**
     * Execute a SQL query and return the result as an array of associative arrays.
     * This function is used to execute a query that returns multiple rows, such as a SELECT query.
     * It will return an array with each row as an associative array.
     * If the query is not a SELECT query, it will log an error and return null.
     * The result can be displayed quickly with H::simple_data_grid().
     * 
     * @param string $q The SQL query to execute
     * @return array|null An array of associative arrays, or null if the query failed or returned no results
     */
    public function query_list($q = "")
    {
        if ($q == "") {
            return null;
        }

        $r = $this->query($q);

        if ($r) {
            $tmp = array();

            while ($line = mysqli_fetch_assoc($r)) {
                if (sizeof($line) == 1) {
                    array_push($tmp, current($line));
                } else {
                    array_push($tmp, $line);
                }
            }
            // on libère la mémoire
            mysqli_free_result($r);

            return $tmp;
        } else {
            return null;
        }
    }

    /**
     * Execute a SQL query and return the result as an associative array with multiple values per key.
     * This function is used to execute a query that returns multiple rows, such as a SELECT query.
     * It will return an associative array where each key is a field name and the value is an array of values for that field.
     * If the query is not a SELECT query, it will log an error and return null.
     * 
     * @param string $q The SQL query to execute
     * @return array|null An associative array with multiple values per key, or null if the query failed or returned no results
     */
    public function query_list_assoc($q = '')
    {
        if ($q == '') {
            return null;
        }

        $r = $this->query($q);

        if (!is_object($r)) {
            Utils::error_log(' wrong use of query_list_assoc()');
            return $r;
        }

        if ($r) {
            $tmp = array();
            $keys = false;
            while ($line = mysqli_fetch_array($r, MYSQLI_ASSOC)) {
                if ($keys == false) {
                    $keys = array_keys($line);

                    foreach ($keys as $k) {
                        $tmp[$k] = array();
                    }
                }

                foreach ($keys as $k) {
                    array_push($tmp[$k], $line[$k]);
                }
            }
            // we released the memory
            mysqli_free_result($r);

            if (sizeof($tmp) > 0) {
                return $tmp;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
    /**
     * Returns a list of fields of a given table.
     * This function executes a SHOW COLUMNS query on the specified table and returns an array of field names.
     * 
     * @param string $table_name The name of the table to get the fields from
     * @return array An array of field names in the specified table
     */
    public function table_field_list($table_name)
    {
        $data = $this->query_list('SHOW COLUMNS FROM '.$table_name);
        $final = array();

        foreach ($data as $line) {
            array_push($final, $line['Field']);
        }
        return $final;
    }

    private $existing_tables = [];
    
    /**
     * Check if a table exists in the database.
     * This function executes a SHOW TABLES query to check if the specified table exists.
     * If the table exists, it returns true, otherwise it returns false.
     * 
     * @param string $table_name The name of the table to check
     * @param bool $forceRequest If true, it will force a new request to the database, otherwise it will use cached results
     * @return bool true if the table exists, false otherwise
     */
    public function table_exists($table_name, $forceRequest = false)
    {
        if (!$forceRequest) {
            if (isset($existing_tables[$table_name])) {
                return $existing_tables[$table_name];
            }
        }
        
        $data = $this->query_list('SHOW TABLES LIKE "'.$table_name.'"');
        $exists = true;

        if (!$data) {
            $exists = false;
        }
        if (sizeof($data)==0) {
            $exists = false;
        }

        $existing_tables[$table_name] = $exists;
        return $exists;
    }

    /**
     * Get the structure of a table.
     * This function executes a SHOW FULL COLUMNS query on the specified table and returns an associative array with the field names as keys.
     * Each field will have its type, size, extra information, comment, and default value.
     * 
     * @param string $table_name The name of the table to get the structure from
     * @param array $fields An array of fields to be marked as "file" in the comment
     * @return array|null An associative array with the table structure, or null if the table does not exist or has no fields
     */
    public function table_data($table_name, $fields = array())
    {
        $data = $this->query_list('SHOW FULL COLUMNS FROM '.$table_name);
        $final = array();

        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $line) {
            $pos = strpos($line['Type'], "(");
            $size = 0;
            if ($pos !== false) {
                $type = substr($line['Type'], 0, $pos);
                $size = 0;

                switch ($type) {
                    case "enum":
                        $size = $this->parse_enum($line['Type']);
                    break;

                    default:
                        $pos2 = strpos($line['Type'], ")");
                        $size = substr($line['Type'], $pos + 1, $pos2 - ($pos + 1));
                    break;
                }
            } else {
                $type = $line['Type'];
            }

            if (in_array($line['Field'], $fields)) {
                $line['Comment'] = "file";
            }
            $final[$line['Field']] = array(
                "field" => $line['Field'],
                "type" => $type,
                "size" => $size,
                "extra" => $line['Extra'],
                "comment" => $line['Comment'],
                "default" => $line['Default']
            );
        }
        return $final;
    }

    /**
     * Parse an ENUM type string from the database and return an array of values.
     * This function is used to convert the ENUM type string into an array of values.
     * 
     * @param string $str The ENUM type string to parse
     * @return array An array of values from the ENUM type string
     */
    private function parse_enum($str)
    {
        $str = str_replace('enum(\'', '', $str);
        $str = str_replace('\',\'\')', '', $str);
        $liste = explode('\',\'', $str);
        return $liste;
    }

    /**
     * Get the last inserted ID from the last INSERT query.
     * This function returns the last inserted ID from the last INSERT query executed.
     * 
     * @return int The last inserted ID, or 0 if no INSERT query has been executed
     */
    public function last_insert_id()
    {
        return $this->last_insert_id;
    }
    
    //***************************************************************************************************
    // Prepared queries to filter data from an external query, a little slower than standard queries because of filtering
    //


    /**
     * This function is used to execute a prepared query with parameters and return the result as an associative array.
     * If the query is not a SELECT query, it will log an error and return null.
     * the main difference with query() is that this function uses prepared statements to execute the query.
     * So it is more secure against SQL injection attacks, and should be used for queries with user input.
     * 
     * exemple usage: $db->prepared_query("SELECT * FROM mytable WHERE id = ?", "i", array($id));   
     * typedef is a string that defines the types of the parameters, for example "i" for integer, "s" for string, "d" for double, "b" for blob. 
     * 
     * @see prepared_query_list()
     * @see prepared_query_list_assoc()
     * @see prepared_query_value()
     * @see prepared_query_line()
     *     * 
     * 
     * @param string $sql The SQL query to execute
     * @param string|bool $typeDef The type definition for the prepared statement, or false if not needed
     * @param array|bool $params The parameters to bind to the prepared statement, or false if not needed
     * @return array|bool An associative array with the result of the query, or false if the query failed or returned no results
     */
    public function prepared_query($sql, $typeDef = false, $params = false)
    {
        $now = microtime(true);
        $this->last_error = '';
        if (!$this->link_SQL) {
            $this->connect();
        }
        
        $sql_action = strtolower(substr($sql, 0, strpos($sql, ' ')));
        
        if ($this->master_slave_mode){
            if ($sql_action == "select") {
                if (rand(0,1)) {
                    $link=$this->link_SQL;
                } else {
                    $link=$this->link_master_SQL;
                }
            }else{
                $link=$this->link_master_SQL;
            }
        }else{
            $link=$this->link_SQL;
        }     

        if ($stmt = mysqli_prepare($link, $sql)) {
            if ($params===false || !is_array($params)) {
                $params = [];
            }
            if (count($params) == count($params, 1)) {
                $params = array($params);
                $multiQuery = false;
            } else {
                $multiQuery = true;
                if ($this->master_slave_mode){ 
                    $link=$this->link_master_SQL; // can't execute multi on reader by security
                }
            }

            $bindParams = array();
            $bindParamsReferences = array();

            if ($typeDef) {
                $bindParams = array_pad($bindParams, (count($params, 1)-count($params))/count($params), "");
                foreach ($bindParams as $key => $value) {
                    $bindParamsReferences[$key] = &$bindParams[$key];
                }
                array_unshift($bindParamsReferences, $typeDef);
                $bindParamsMethod = new ReflectionMethod('mysqli_stmt', 'bind_param');
                try {
                    $bindParamsMethod->invokeArgs($stmt, $bindParamsReferences);
                } catch (\Exception $e) {
                    Utils::error_log('*-----------------');
                    Utils::error_log($e->message());
                    $this->last_error = $e->message();
                    Utils::error_log($stmt);
                    Utils::error_log($typeDef);
                    Utils::error_log($params);
                    Utils::error_log($bindParamsReferences);
                    Utils::error_log('*-----------------');
                }
            }

            $result = array();
            foreach ($params as $queryKey => $query) {
                foreach ($bindParams as $paramKey => $value) {
                    $bindParams[$paramKey] = $query[$paramKey];
                }
                $queryResult = array();

                if (mysqli_stmt_execute($stmt)) {
                    $resultMetaData = mysqli_stmt_result_metadata($stmt);

                    if ($resultMetaData) {
                        $stmtRow = array();
                        $rowReferences = array();
                        while ($field = mysqli_fetch_field($resultMetaData)) {
                            $rowReferences[] = &$stmtRow[$field->name];
                        }
                        mysqli_free_result($resultMetaData);

                        $bindResultMethod = new ReflectionMethod('mysqli_stmt', 'bind_result');
                        $bindResultMethod->invokeArgs($stmt, $rowReferences);
                        while (mysqli_stmt_fetch($stmt)) {
                            $row = array();
                            foreach ($stmtRow as $key => $value) {
                                $row[$key] = $value;
                            }
                            $queryResult[] = $row;
                        }
                        mysqli_stmt_free_result($stmt);
                    } else {
                        $queryResult[] = mysqli_stmt_affected_rows($stmt);
                    }

                    if (strtolower(substr($sql, 0, 6)) == "insert") {
                        $this->last_insert_id = mysqli_stmt_insert_id($stmt);
                    }
                } else {
                    $queryResult[] = false;
                    Utils::error_log('-------------------------');
                    Utils::error_log('SQL ERROR :' . mysqli_error($link));
                    $this->last_error = 'SQL ERROR :' . mysqli_error($link);
                    Utils::error_log($sql);
                    Utils::error_log($typeDef);
                    Utils::error_log($params);
                    Utils::log_backtrace();
                    return false;
                }

                if ($sql_action == 'update') {
                    if (is_array($queryResult) && sizeof($queryResult) == 1) {
                        $keys = array_keys($queryResult);
                        if ($keys[0] == 0) {
                            $queryResult = $queryResult[0];
                        }
                    }
                }

                $result[$queryKey] = $queryResult;
            }

            $this->query_count++;

            mysqli_stmt_close($stmt);

            if (stripos($sql, "SQL_CALC_FOUND_ROWS") !== false) {
                $rc = mysqli_query($link, "SELECT FOUND_ROWS()");
                $rc = mysqli_fetch_array($rc, MYSQLI_ASSOC);
                $this->_last_count = current($rc);

                $limit_pos = strripos($sql, ' limit ');
                if ($limit_pos !== false) {
                    $tmp = substr($sql, $limit_pos+7, strlen($sql) - $limit_pos - 7);
                    $values = explode(',', $tmp);

                    if (sizeof($values) == 1) { // LIMIT count
                        $this->_last_limit_start = 0;
                        $this->_last_limit_count = floor(trim($values[0]));
                    } else { // LIMIT start,count
                        $this->_last_limit_start = floor(trim($values[0]));
                        $this->_last_limit_count = floor(trim($values[1]));
                    }
                } else {
                    $this->_last_limit_start = 0;
                    $this->_last_limit_count = $this->_last_count;
                }
            } else {
                $this->_last_count = 0;
                $this->_last_limit_start = 0;
                $this->_last_limit_count = $this->_last_count;

                //if (is_resource($r)) $this->_last_count = mysqli_num_rows($r);
            }
        } else {
            Utils::error_log('-------------------------');
            Utils::error_log('SQL ERROR SLAVE:' . mysqli_error($link));
            $this->last_error = 'SQL ERROR SLAVE:' . mysqli_error($link);
            Utils::error_log($sql);
            Utils::log_backtrace();
            return false;
        }

        $elapsed = (microtime(true) - $now);
        $this->query_time += $elapsed;

        if ($sql_action == "insert") {
            if ($this->last_insert_id) {
                return $this->last_insert_id;
            } else {
                return false;
            }
        }

        if ($multiQuery) {
            return $result;
        } else {
            return $result[0];
        }
    }

    /**
     * Execute a prepared SQL query and return the result as an associative array with multiple values per key..
     * 
     * @param string $sql The SQL query to execute
     * @param string|bool $typeDef The type definition for the prepared statement, or false if not needed
     * @param array|bool $params The parameters to bind to the prepared statement, or false if not needed
     * @return array|null An associative array with the result of the query, or null if the query failed or returned no results
     */
    public function prepared_query_list_assoc($sql, $typeDef = false, $params = false)
    {
        $result = $this->prepared_query_list($sql, $typeDef, $params);

        if (is_array($result)) {
            $keys = array_keys($result[0]);
            $tmp = array();

            foreach ($keys as $k) {
                $tmp[$k] = array();
            }

            foreach ($result as $line) {
                foreach ($keys as $k) {
                    array_push($tmp[$k], $line[$k]);
                }
            }
            $result = $tmp;
        }

        if (!$result) {
            $result = null;
        }

        return $result;
    }

    /**
     * Execute a prepared SQL query and return the result as a list of values.
     * This function is used to execute a prepared query with parameters and return the result as a list of values.
     * If the query is not a SELECT query, it will log an error and return null.
     * 
     * @param string $sql The SQL query to execute
     * @param string|bool $typeDef The type definition for the prepared statement, or false if not needed
     * @param array|bool $params The parameters to bind to the prepared statement, or false if not needed
     * @return array|null An array with the result of the query, or null if the query failed or returned no results
     */
    public function prepared_query_list($sql, $typeDef = false, $params = false)
    {
        $result = $this->prepared_query($sql, $typeDef, $params);
        if (is_array($result)) {
            $first = current($result);
            //~ //if ($first instanceof Countable && sizeof($first)==1){
            //if(sizeof($first)==1){
            if (is_array($first) && sizeof($first)==1) {
                $tmp = array();
                foreach ($result as $value) {
                    if (is_array($value)) {
                        array_push($tmp, array_shift($value));
                    } else {
                        array_push($tmp, $value);
                    }
                }
                return $tmp;
            }
        }
        return $result;
    }

    /**
     * Execute a prepared SQL query and return the first value of the first row.
     * This function is used to execute a prepared query with parameters and return the first value of the first row.
     * If the query is not a SELECT query, it will log an error and return null.
     * 
     * @param string $sql The SQL query to execute
     * @param string|bool $typeDef The type definition for the prepared statement, or false if not needed
     * @param array|bool $params The parameters to bind to the prepared statement, or false if not needed
     * @return mixed The first value of the first row of the result set, or null if the query failed or returned no results
     */
    public function prepared_query_value($sql, $typeDef = false, $params = false)
    {
        $result = $this->prepared_query($sql, $typeDef, $params);
        if (!is_array($result)) {
            return $result;
        }
        $first = current($result);
        if (is_array($first)) {
            return array_shift($first);
        } else {
            return $first;
        }
    }

    /**
     * Execute a prepared SQL query and return the first row of the result set.
     * This function is used to execute a prepared query with parameters and return the first row of the result set as an associative array.
     * If the query is not a SELECT query, it will log an error and return null.
     * 
     * @param string $sql The SQL query to execute
     * @param string|bool $typeDef The type definition for the prepared statement, or false if not needed
     * @param array|bool $params The parameters to bind to the prepared statement, or false if not needed
     * @return array|null The first row of the result set as an associative array, or null if the query failed or returned no results
     */
    public function prepared_query_line($sql, $typeDef = false, $params = false)
    {
        $result = $this->prepared_query($sql, $typeDef, $params);
        if (!is_array($result)) {
            return $result;
        }
        return current($result);
    }
    

    //***************************************************************************************************
    // USEFUL FUNCTIONS ...

    /**
     * Returns the current time in seconds as a float.
     *
     * @return float The current time in seconds as a float.
     * 
     */
    public function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
    
    /**
     * Returns the name of a table with the prefix if it is set.
     * This function is used to get the full name of a table with the prefix.
     * 
     * @param string $table_name The name of the table to get
     * @return string The full name of the table with the prefix if set, otherwise just the table name
     */
    public function table($table_name)
    {
        if ($this->table_prefix == '') {
            return $table_name;
        } else {
            return $this->table_prefix.'_'.$table_name;
        }
    }

    /**
     * Formats a list of strings into a comma-separated string with quotes around each value.
     * This function is used to format a list of strings for use in SQL queries.
     * 
     * @param mixed $a A string or an array of strings to format
     * @param string $quote The quote character to use around each value (default is double quote)
     * @return string A comma-separated string with quotes around each value
     */
    public function format_string_list($a, $quote = '"')
    {
        if (is_string($a)) {
            $tmp = explode(',', $a);
        } elseif (is_array($a)) {
            $tmp = $a;
        } else {
            Utils::error_log('WRONG value passed to $DB->format_string_list :'.print_r($a, true));
            return $a;
        }
        $lst = [];
        foreach ($tmp as $v) {
            array_push($lst, $quote.$v.$quote);
        }
        return implode(',', $lst);
    }

    // public function format_fields_list($a)
    // {
    //     return $this->format_string_list($a, '`');
    // }

    /**
     * Duplicate a line in a table.
     * This function is used to duplicate a line in a table by copying all fields except the ID.
     * If the original line is not found, it will log an error and return 0.
     * 
     * @param string $table The name of the table to duplicate the line in
     * @param mixed $original_line The original line to duplicate, can be an ID or an associative array
     * @param array|null $replace_fields An associative array of fields to replace in the duplicated line, or null if no fields to replace
     * @return int The ID of the newly duplicated line, or 0 if the original line was not found
     */
    public function duplicate_line($table, $original_line, $replace_fields = null)
    {
        if (is_numeric($original_line)) {
            $original_line = $this->query_line('SELECT DISTINCT * FROM '.$table.' WHERE id='.$original_line);
        }

        if (!is_array($original_line)) {
            Utils::error_log('line ID not found for duplication in table '.$table);
            return 0;
        }

        unset($original_line['id']);

        $q = 'INSERT INTO '.$table. ' SET ';
        $values = [];
        $tmp = [];
        $types = '';

        if (is_array($replace_fields)) {
            $replace_keys = array_keys($replace_fields);
        } else {
            $replace_keys = null;
        }

        foreach ($original_line as $field=>$value) {
            if (is_null($value)) {
                array_push($tmp, '`'.$field.'`=NULL');
            } else {
                if (is_numeric($value)) {
                    if (is_float($value)) {
                        $types .= 'd';
                    } else {
                        $types .= 'i';
                    }
                } else {
                    $types .= 's';
                }

                if ($replace_keys != null) {
                    if (isset($replace_fields[$field])) {
                        $value = $replace_fields[$field];
                    }
                }
                array_push($tmp, '`'.$field.'`=?');
                array_push($values, $value);
            }
        }
        $q .= implode(',', $tmp);
        
        $this->prepared_query($q, $types, $values);

        return $this->last_insert_id;
    }
    
    public static function create_table_from_json($json)
    {
        $table_data = json_decode($json, true);

        if (is_null($table_data)) {
            echo 'json error';
        } else {
            $table_name = $table_data['table'];
            $fields_data = $table_data['fields'];

            $fields = [];
            $sizes = [
                'tinyint'=>3,
                'mediumint'=>8,
                'int'=>10,
                'bigint'=>20,
                'varchar'=>255,
                'char'=>255
            ];
            $fields_index = [];
            foreach ($fields_data as $name=>$data) {
                $str = '`'.$name.'`';

                $type = $data['type'];

                $str .= ' '.$type;

                if (isset($data['size'])) {
                    $str .= '('.$data['size'].')';
                } elseif (isset($sizes[$type])) {
                    $str .= '('.$sizes[$type].')';
                } elseif ($type=='enum') {
                    $tmp = [];
                    foreach ($data['values'] as $v) {
                        array_push($tmp, '\''.$v.'\'');
                    }
                    $str .= '('.implode(',', $tmp).')';
                }

                if (!isset($data['unsigned'])) {
                    switch ($type) {
                        case 'tinyint':
                        case 'int':
                        case 'mediumint':
                        case 'bigint':
                            $str .= ' UNSIGNED';
                        break;
                    }
                } else {
                    $str .= ' UNSIGNED';
                }

                if (isset($data['default'])) {
                    if (strtolower($data['default'])=='null') {
                        $str .= ' NULL DEFAULT NULL';
                    } else {
                        if (isset($data['null'])) {
                            $str .= ' NULL';
                        } else {
                            $str .= ' NOT NULL';
                        }
                        if ($data['default']=='current_timestamp'){
                            $str .= ' DEFAULT CURRENT_TIMESTAMP';
                        } else {
                            $str .= ' DEFAULT \''.$data['default'].'\'';
                        }
                    }
                } else {
                    if (isset($data['null'])) {
                        $str .= ' NULL';
                    } else {
                        $str .= ' NOT NULL';
                    }

                    if (!isset($data['primary'])) {
                        switch ($type) {
                            case 'tinyint':
                            case 'int':
                            case 'mediumint':
                            case 'bigint':
                                $str .= ' DEFAULT \'0\'';
                            break;
                        }
                    } else {
                        $str .= ' AUTO_INCREMENT PRIMARY KEY';
                    }

                    if (isset($data['index']) && $data['index']){
                        array_push($fields_index, '`'.$name.'`');
                    }
                }

                array_push($fields, $str);
            }

            $str = 'CREATE TABLE `'.$table_name.'` (';
            $str .= implode(",\n", $fields);
            $str .=') ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;';
            if ($fields_index){
                $str.= 'ALTER TABLE `'.$table_name.'` ADD INDEX('.implode(",", $fields_index).');';
            }

            return $str;
        }

        return false;
    }
    /**
     * Synchronize a table with another database.
     * This function is used to synchronize a table in another database with the current database.
     * It can insert, update, or delete records based on the action specified.
     * 
     * @param array $config The configuration for the other database connection
     * @param string $module The module name
     * @param string $table The table name
     * @param mixed $id The ID or IDs of the records to synchronize
     * @param string $action The action to perform: 'insert', 'update', or 'delete'
     * @param string $id_field The field name for the ID (default is 'id')
     * @param array|string|null $exclude_fields Fields to exclude from synchronization
     * @return bool|int Returns false if no ID is provided, or the result of the query execution
     */
    public static function synch_other_DB($config, $module, $table, $id, $action = '', $id_field = 'id', $exclude_fields = null)
    {
        global $DB, $CONFIG_DB;
        if (is_string($exclude_fields)) {
            if (strpos($exclude_fields, ',')!==false) {
                $exclude_fields = explode(',', $exclude_fields);
            } else {
                $exclude_fields = [$exclude_fields];
            }
        }
        
        $other_SGBD = new DB($config);

        $source_table = $CONFIG_DB::DB_TABLE_PREFIX.'_'.$module.'_'.$table;
        $destination_table = $config['table_prefix'].'_'.$module.'_'.$table;

        if (is_array($id)) {
            $id = trim(implode(',', $id));
        }

        if (!$id) {
            return false;
        }

        switch ($action) {
            case 'insert':
            case 'update':
                if (is_string($id)) {
                    $q = 'SELECT DISTINCT * FROM '.$source_table.' WHERE '.$id_field.' IN('.$id.')';
                } else {
                    $q = 'SELECT DISTINCT * FROM '.$source_table.' WHERE '.$id_field.'='.$id;
                }
                $liste = $DB->query_list($q);
            break;

            case 'delete':
                if (is_string($id)) {
                    $other_SGBD->query('DELETE FROM '.$destination_table.' WHERE '.$id_field.' IN('.$id.')');
                } else {
                    $other_SGBD->query('DELETE FROM '.$destination_table.' WHERE '.$id_field.'='.$id);
                }

                return;

            break;
        }

        if (!is_array($liste)) {
            echo '<hr>'.$q.'<br>Synch error';
            return;
        }

        foreach ($liste as $line) {
            $id = $line['id'];
            $exists = $other_SGBD->query_value('SELECT DISTINCT COUNT(*) FROM '.$destination_table.' WHERE id='.intval($id));

            if (!$exists) {
                $action = 'insert';
            } else {
                $action = 'update';
            }

            $q = '';
            $types = '';
            $values = [];
            $tmp = [];
            switch ($action) {
                case 'insert':
                    $q = 'INSERT INTO '.$destination_table.' SET ';
                break;

                case 'update':
                    $q = 'UPDATE '.$destination_table.' SET ';
                break;
            }

            switch ($action) {
                case 'insert':
                case 'update':
                    foreach ($line as $key=>$value) {
                        if (is_array($exclude_fields) && $action != 'insert') {
                            if (in_array($key, $exclude_fields)) {
                                continue;
                            }
                        }

                        if (intval($value).'' === ''.$value) {
                            $value = intval($value);
                        } elseif (floatval($value).'' === ''.$value) {
                            $value = floatval($value);
                        }

                        if (is_string($value)) {
                            $types .= 's';
                            array_push($tmp, $key.'=?');
                            array_push($values, $value);
                        } elseif (is_numeric($value)) {
                            if (is_float($value)) {
                                $types .= 'd';
                            } else {
                                $types .= 'i';
                            }
                            array_push($tmp, $key.'=?');
                            array_push($values, $value);
                        }
                    }

                    $q .= implode(',', $tmp);
                break;
            }

            switch ($action) {
                case 'update':
                    $q .= ' WHERE id=?';
                    $types .= 'i';
                    array_push($values, intval($id));
                break;

            }

            return $other_SGBD->prepared_query($q, $types, $values);
        }
    }
    /**
     * Delete all associations for a given ID in a module.
     * This function is used to delete all associations for a given ID in a module.
     * It will delete records from all association tables related to the module.
     * 
     * @param int $id The ID of the record to delete associations for
     * @param string $module_name The name of the module
     * @param string $other_module Optional, the name of another module to filter association tables
     */
    public function delete_associations($id, $module_name, $other_module = '')
    {
        global $DB;

        $asso_data = $this->get_association_tables($other_module);

        foreach ($asso_data as $data) {
            $q = 'DELETE FROM '.$data['table'].' WHERE id_'.$module_name.'=?';
            $DB->prepared_query($q, 'i', [ $id ]);
        }
    }

    /**
     * 
     * Get a list of association tables for a given module.
     * 
     * @param mixed $module_name
     * @param string $other_module
     * 
     * @return array An array of association tables related to the module.
     * 
     */
    
    public function get_association_tables($module_name,$other_module = '')
    {
        global $DB;

        $list = [];

        if ($other_module == '') {
            $q = 'SHOW TABLES LIKE "%_asso_'.$module_name.'"';
        } else {
            $q = 'SHOW TABLES LIKE "'.$DB->table_prefix.'_'.$other_module.'_asso_'.$module_name.'"';
        }
        $list_a = $DB->query_list($q);
        if (is_array($list_a)) {
            foreach ($list_a as $table) {
                $str = substr($table, strlen($DB->table_prefix)+1);

                $tmp = explode('_asso_', $str);
                array_push($list, ['module'=>$tmp[0] , 'table'=>$table]);
            }
        }

        if ($other_module == '') {
            $q = 'SHOW TABLES LIKE "%_'.$module_name.'_asso_%"';
        } else {
            $q = 'SHOW TABLES LIKE "'.$DB->table_prefix.'_'.$module_name.'_asso_'.$other_module.'"';
        }

        $list_b = $DB->query_list($q);
        if (is_array($list_b)) {
            foreach ($list_b as $table) {
                $tmp = explode('_asso_', $table);
                $other_module = $tmp[1];

                $tmp = explode('_', $table);
                $site = $tmp[0];

                $rel_table = substr($table, strlen($site), strlen($table) - strlen($site));
                array_push($list, ['module'=>$other_module , 'table'=>$table , 'site'=>$site, 'relative_table'=>$rel_table ]);
            }
        }

        return $list;
    }

    
    /**
     * Make sql query to create table or update from a json description of the table
     * The json have to be to the following format:
     * - {
     * -    tables: [
     * -        {
     * -            name: "my_table",
     * -            fields: [
     * -                {
     * -                    name: "field_name",
     * -                    type: varchar|int|tinyint|datetime... (sql type)
     * -                    limit: 255 (an integer)
     * -                    null: (optional) boolean, true to accept null value, false to add NOT NULL 
     * -                    default: (optional), the default value
     * -                    index: (optional) fulltext|index
     * -                    primary: (optional) only one by table, autoincrement
     * -                },
     * -                ...
     * -        },
     * -        ...
     * -    ],
     * -    entries: [
     * -        {
     * -            table: 'block_data',
     * -            fields: [
     * -                field_name: [
     * -                    name: 'field_name',
     * -                    type: 's' or 'i' or 'd',
     * -                    value: 'field_value',
     * -                ]
     * -                ...
     * -            ],
     * -            languages: [
     * -                short: [
     * -                    field: [
     * -                        en: 'trad_en',
     * -                        fr: 'trad_fr',
     * -                        ...
     * -                    ]...
     * -                ],
     * -                long: [
     * -                    field: [
     * -                        en: 'trad_en',
     * -                        fr: 'trad_fr',
     * -                        ...
     * -                    ]...
     * -                ]
     * -            ],
     * -            categories: [
     * -                field_identifier: [
     * -                    // the first element of this list in the category than contains the element
     * -                    // next elements are each time the parent of the previous one
     * -                    [ series: "category's serie", name: "category's name"],
     * -                    [ series: "category's serie", name: "category's name"], This one is the parent of the one before
     * -                    ...
     * -                ],
     * -                ...
     * -            ]
     * -        }
     * -        ...
     * -    ]
     * - }
     *
     * @param  string|array Json describing the table. Formatted as 
     * @return string The sql query
     */
    public function sql_from_json($json, $execute = true){

        if (is_string($json)) $json = json_decode(html_entity_decode($json), true);

        if (!$execute) $str = '';

        if (isset($json['tables'])) {
            foreach($json['tables'] as $key => $table){

                $table_name = '`'.$this->table($table['name']).'`';

                $sql = 'CREATE TABLE IF NOT EXISTS '.$table_name.' (';
                $sql.= '`helphp_fake_column_to_create_table` int(11) UNSIGNED NOT NULL)';
                $sql.= ' ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
                if (!$execute) $str.= $sql.';'.PHP_EOL;
                else  {
                    $res = $this->query($sql);
                    if ($res === false) exit;
                }

                $indexes = [];
                foreach ($table['fields'] as $key => $line){
                    
                    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '.str_replace('`', '"', $table_name).' AND COLUMN_NAME = "'.$line['name'].'"';
                    $no_record = $this->query_value($sql);
                    $sql = ($no_record == false || $no_record == 0 || !$execute) ? 
                        'ALTER TABLE '.$table_name.' ADD COLUMN IF NOT EXISTS ' :
                        'ALTER TABLE '.$table_name.' MODIFY IF EXISTS ';

                    // for each field add the name and the type
                    $sql.= '`'.$line['name'].'` '.$line['type'];

                    // only if the limit is present
                    if (isset($line['limit']) && $line['limit'] != '') $sql.= '('.$line['limit'].')';

                    if (isset($line['primary']) || isset($line['unsigned'])){
                        $sql.=' UNSIGNED';
                    }

                    // for text we don't want null at all, so if not present in the field do nothing
                    if (isset($line['null'])) {
                        if ($line['null']) $sql.=' NULL';
                        else $sql.=' NOT NULL';
                    }

                    // add primary key
                    if (isset($line['primary'])) {
                        $sql.= ' AUTO_INCREMENT, ADD PRIMARY KEY IF NOT EXISTS (`'.$line['name'].'`)';
                    }

                    if (isset($line['default'])) {
                        if ($line['default'] == '""' || $line['default'] == "''") $line['default'] = '';
                        if (strtoupper($line['default']) == 'CURRENT_TIMESTAMP') $sql.= ' DEFAULT '.strtoupper($line['default']);
                        else $sql.= ' DEFAULT \''.$line['default'].'\'';
                    }
                    
                    if (!$execute) $str.= '/* to parse */'.PHP_EOL.$sql.';'.PHP_EOL;
                    else {
                        $res = $this->query($sql);
                        if ($res === false) exit;
                    }

                    if (isset($line['index']) && $line['index']){
                        if (!isset($indexes[$line['index']])) $indexes[$line['index']] = [];
                        array_push($indexes[$line['index']], '`'.$line['name'].'`');
                    }
                    
                    if (isset($line['unique']) && $line['unique']) {
                        if (!isset($indexes['unique'])) $indexes['unique'] = [];
                        array_push($indexes['unique'], '`'.$line['name'].'`');
                    }
                }

                if ($indexes){
                    $i = 1;
                    foreach ($indexes as $type => $fields){
                        $to_keep = array();
                        foreach ($fields as $key => $field){
                            $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = '.str_replace('`', '"', $table_name).' AND INDEX_NAME = \''.$field.'\'';
                            $exist = $this->query_value($sql);
                            if ($exist == false || $exist == 0){
                                array_push($to_keep, $field);
                            }
                        }

                        $sql = 'ALTER TABLE '.$table_name.' ADD '.strtoupper($type).' IF NOT EXISTS index_'.$i.' ('.implode(', ', $to_keep).')';
                        if (!$execute) $str.= '/* to parse */'.PHP_EOL.$sql.';'.PHP_EOL;
                        else {
                            $res = $this->query($sql);
                            if ($res === false) exit;
                        }

                        $i++;

                        // retrieve index that don't exist anymore to delete them
                        // $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = '.str_replace('`', '"', $table_name).' AND INDEX_NAME = \''.$field.'\'';
                        // $exist = $this->query_value($sql);
                    }
                }

                // delete the columln added at first to create the table
                $sql = 'ALTER TABLE '.$table_name.' DROP COLUMN IF EXISTS `helphp_fake_column_to_create_table`';
                if (!$execute) $str.= '/* to parse */'.PHP_EOL.$sql.';'.PHP_EOL;
                else {
                    $res = $this->query($sql);
                    if ($res === false) exit;
                }
            }
        }

        if (isset($json['entries'])) {

            // will store all the primary field detected when inserting to not do the same query multiple if there
            // is more than one addition to a table : table_name => field
            $primary_list = [];
            $unique_list = [];
            // same as previous for languages iso
            $iso_langs = [];

            foreach($json['entries'] as $key => $entry) {

                // get primary field for the table
                if (!\key_exists($entry['table'], $primary_list)){
                    $q = 'SHOW INDEX FROM `'.$this->table($entry['table']).'` WHERE Key_name="PRIMARY"';
                    $primary = $this->query_line($q);
                    if ($primary){
                        $primary_list[$entry['table']] = $primary['Column_name'];
                    }
                }

                // get unique fields for the table
                if (!\key_exists($entry['table'], $unique_list)){
                    $q = 'SHOW INDEX FROM `'.$this->table($entry['table']).'` WHERE Key_name<>"PRIMARY" AND non_unique=0';
                    $unique = $this->query_list($q);
                    if ($unique){
                        $unique_list[$entry['table']] = array_column($unique, 'Column_name');
                    }
                }
                
                // wether to check existence of the line on id or with all the fields
                $id = false;
                $unique = false;

                // each field is added to a string like `name=value` use it next to insert or select the row in db
                $sql = '';
                foreach($entry['fields'] as $ind => $field){

                    if (key_exists($entry['table'], $primary_list) && $primary_list[$entry['table']] == $field['name']) {
                        // the inserted row has the unique id inside
                        $id = $field;
                    }

                    if (key_exists($entry['table'], $unique_list) && in_array($field['name'], $unique_list[$entry['table']])) {
                        // the inserted row has an unique field
                        if ($unique === false) $unique = [];
                        array_push($unique, $field);
                    }
                    
                    $sql.= $field['name'].' = ';
                    if ($field['type'] == 's') $sql.= '"'.$field['value'].'"';
                    else $sql.= $field['value'];
                    $sql.= ', ';

                }
                $sql = substr($sql, 0, -2);

                // check a row exist with the same id or with exactly the same value in the table
                $exist = false;
                if ($id !== false) {
                    $q = 'SELECT '.$primary_list[$entry['table']].' FROM `'.$this->table($entry['table']).'` WHERE '.$id['name'].' = '.$id['value'];
                    $exist = $this->query_value($q);
                } else if ($unique !== false){
                    $q = 'SELECT '.$primary_list[$entry['table']].' FROM `'.$this->table($entry['table']).'` WHERE ';
                    foreach($unique as $uniq_field) {
                        $q.= $uniq_field['name'].' = ';
                        if ($uniq_field['type'] == 's') $q.= '"'.$uniq_field['value'].'"';
                        else $q.= $uniq_field['value'];
                        $q.= ', ';
                    }
                    $q = substr($q, 0, -2);
                    $exist = $this->query_value($q);
                } else {
                    $q = 'SELECT '.$primary_list[$entry['table']].' FROM `'.$this->table($entry['table']).'` WHERE '.str_replace(', ', ' AND ', $sql);
                    $exist = $this->query_value($q);
                }

                if (!$exist) {
                    $sql = 'INSERT INTO `'.$this->table($entry['table']).'` SET '.$sql;
                    // add the row only if not found
                    if (!$execute) $str.= '/* to parse */'.PHP_EOL.$sql.';'.PHP_EOL;
                    else {
                        $res = $this->query($sql);
                        $id_entry = $this->last_insert_id();
                        if ($res === false) exit;
                    }
                } else {
                    $id_entry = $exist;
                    $sql = 'UPDATE `'.$this->table($entry['table']).'` SET '.$sql.' WHERE '.$primary_list[$entry['table']].'='.$exist;
                    if (!$execute) $str.= '/* to parse */'.PHP_EOL.$sql.';'.PHP_EOL;
                    else {
                        $res = $this->query($sql);
                        if ($res === false) exit;
                    }
                }

                // when not executing directly, can't get the id of the entry so ignore the languages
                if (isset($entry['languages']) && $execute) {
                    foreach($entry['languages'] as $type => $names){
                        foreach($names as $name => $values){
                            foreach ($values as $iso => $value) {
                                // check if the iso exist
                                if (!\key_exists($iso, $iso_langs)){
                                    $q = 'SELECT id FROM '.$this->table('languages_data').' WHERE iso="'.$iso.'"';
                                    $iso_exist = $this->query_value($q);
                                    $iso_langs[$iso] = $iso_exist > 0 ? true : false;
                                }

                                // iso not found in db, skip it
                                if (key_exists($iso, $iso_langs) && $iso_langs[$iso] === false) continue;

                                $field_identifier = $entry['table'].'-'.$name;
                                $lang_table = $this->table('languages_'.$type);
                                $l_exist = false;
                                if ($id_entry){
                                    $q = 'SELECT l.id FROM '.$lang_table.' l, '.$this->table('languages_data').' d';
                                    $q.=' WHERE l.id_data=d.id AND d.iso="'.$iso.'" AND l.field_identifier="'.$field_identifier.'" AND';
                                    $q.=' l.id_item='.$id_entry;
                                    $l_exist = $this->query_value($q);
                                    if (!$l_exist){
                                        $q = 'INSERT INTO '.$lang_table.' SET field_identifier="'.$field_identifier.'", id_item='.$id_entry.', value="'.$value.'"';
                                        $q.=', id_data=(SELECT id FROM '.$this->table('languages_data').' WHERE iso="'.$iso.'")';
                                        $this->query($q);
                                    } else {
                                        $q = 'UPDATE '.$lang_table.' SET value="'.$value.'" WHERE id='.$l_exist;
                                        $this->query($q);
                                    }
                                }
                            }
                        }
                    }
                }

                // when not executing directly, can't get the id of the entry so ignore the categories
                if (isset($entry['categories']) && $execute) {
                    foreach($entry['categories'] as $field_identifier => $category){
                        $mod_category = new \helPHP\modules\category\admin\Category(null, $field_identifier, $id_entry);
                        $found = false;
                        $i = 0;
                        $need_to_create = []; // store the category not found
                        // will stop at the first category found in the db
                        while($found === false && $i < count($category)){
                            $item = $category[$i];
                            $q = 'SELECT id FROM '.$this->table('category_data').' WHERE name="'.$item['name'].'" AND series="'.$item['series'].'"';
                            $id = $this->query_value($q);
                            if ($id > 0 || $i == count($category) - 1) {
                                $found = true;

                                // last category, also not found, will create it in the while loop
                                if ($i == count($category) - 1 && !$id) array_push($need_to_create, $item);

                                if ($need_to_create){
                                    
                                    // create categories not found before adding to the last created
                                    // each category will be child of the precedent.
                                    while($need_to_create) {
                                        $item = array_pop($need_to_create);
                                        $tpost = [];
                                        $tpost['id_parent'] = $id;
                                        $tpost['category_data-id'] = 0;
                                        $tpost['category_data-name'] = $item['name'];
                                        $tpost['category_data-entete'] = '';
                                        $tpost['category_data-csv'] = '';
                                        $tpost['category_data-series'] = $item['series'];
                                        $id = $mod_category->save($tpost);
                                    }

                                    $mod_category->add_content(['category_content-id_data'=>$id]);
                                } else {
                                    // first item found, before adding to the category, check if the link to this category already exist
                                    $q = 'SELECT id FROM '.$this->table('category_content').' WHERE id_data='.$id.' AND field_identifier="'.$field_identifier.'" AND id_item='.$id_entry;
                                    $exist = $this->query_value($q);
                                    if (!$exist) $mod_category->add_content(['category_content-id_data'=>$id]);
                                }
                            } else {
                                \array_push($need_to_create, $item);
                                $i++;
                            }
                        }
                    }
                }
            }
        }
        
        if ($execute) return true;
        else return $str;
    }
    /**
     * Return the descriptive json of a table.
     *
     * @param  mixed $table the table name
     * @return string json table's definition
     */
    public function json_from_sql($table){
        $table_name = $this->table($table);
        if (!$this->table_exists($table_name)) {
            Utils::error_log('Error json_from_sql table not found : '.$table);
            return;
        }

        $table_data = $this->query_list('SHOW FULL COLUMNS FROM '.$table_name);

        $indexes = $this->query_list('SHOW INDEX FROM '.$table_name);

        $json = [
            'name'=>$table,
            'fields'=>[]
        ];

        foreach($table_data as $key => $line){
            $field = [];
            $field['name'] = $line['Field'];

            // copied from table_data
            $pos = strpos($line['Type'], "(");
            if ($pos !== false) {
                $type = substr($line['Type'], 0, $pos);
                $size = 0;
                switch ($type) {
                    case "enum":
                        $size = $this->parse_enum($line['Type']);
                    break;

                    default:
                        $pos2 = strpos($line['Type'], ")");
                        $size = substr($line['Type'], $pos + 1, $pos2 - ($pos + 1));
                    break;
                }
                
                $field['type'] = $type;
                $field['limit'] = intval($size);

            } else {
                $field['type'] = $line['Type'];
            }

            if ($line['Null'] == 'YES') $field['null'] = true;
            else $field['null'] = false;

            if ($line['Key'] == 'PRI') $field['primary'] = true;
            if ($line['Key'] == 'UNI') $field['unique'] = true;

            if (!isset($field['primary'])){
                // the default value return is not correct need to adjust it depending the field's type. Many types 
                // can't be empty by default
                $can_be_empty = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'enum', 'set', 'inet6'];
                if ($line['Default'] == '' && !$field['null'] && in_array($field['type'], $can_be_empty)) $field['default'] = '';
                else if ($line['Default'] == 'current_timestamp()') $field['default'] = 'CURRENT_TIMESTAMP';
                else if ($line['Default'] != '') $field['default'] = $line['Default'];
            }
            
            foreach($indexes as $key => $index_data){
                if ($index_data['Column_name'] != $field['name']) continue;
                if ($index_data['Index_type'] == 'FULLTEXT') $field['index'] = 'fulltext';
                if ($index_data['Index_type'] == 'BTREE' && $index_data['Key_name'] != 'PRIMARY') {
                    // to ignore a false positive on unique field, if the index has a cardinality exactly egal to 0 it's only a unique and not an index
                    if ($line['Key'] == 'UNI' && intval($index_data['Cardinality']) >= 0) continue;
                    $field['index'] = 'index';
                }
            }

            array_push($json['fields'], $field);
        }

        return json_encode(['tables'=>[$json]]);
    }
}