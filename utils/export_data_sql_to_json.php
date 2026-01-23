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

$CLI = isset($argc); // true, called from CLI

if ($CLI){ // call from CLI
    if(!isset($argv[1])){
        die('home path needed');
    }

    if (!isset($argv[2])){
        die('table sql needed');
    }

    $target = $argv[1];
    $target = rtrim($target, '/');
    if (!is_dir($target)) {
        die('bad path');
    }

    $table = $argv[2];

    export_data_sql_to_json($target, $table);
}

/**
 * Return a json describing all the entries in a table
 * 
 * To call from CLI
 *      php export_data_sql_to_json.php instance_path table_name
 * 
 * @param string    $instance_path          path to the instance
 * @param string    $table_name             table's name
 * 
 * @return string JSON
 * @package helPHP\utils
 */
function export_data_sql_to_json($instance_path, $table_name){
    include_once($instance_path.'/config/main.php');
    include_once(Config::HELPHP_FOLDER.'/autoload.php');

    global $DB;

    $table_data = $DB->table_data($DB->table($table_name));

    $q = 'SELECT DISTINCT * FROM '.$DB->table($table_name);
    $lst = $DB->query_list($q);
    
    $entries = [];
    foreach($lst as $key => $line){
        
        $entry = [
            'table'=>$table_name,
            'fields'=>[]
        ];

        foreach($table_data as $ind => $column){
            if ($column['type'] == 'double' || $column['type'] == 'decimal' || $column['type'] == 'float'){
                array_push($entry['fields'], ['name' => $column['field'], 'type' => 'd', 'value'=>$line[$column['field']]]);
            } else if ($column['type'] == 'int' || $column['type'] == 'tinyint' || $column['type'] == 'smallint' || $column['type'] == 'mediumint' || $column['type'] == 'bigint') {
                array_push($entry['fields'], ['name' => $column['field'], 'type' => 'i', 'value'=>$line[$column['field']]]);
            } else if ($column['type'] == 'mediumtext' ||  $column['type'] == 'text') {
                array_push($entry['fields'], ['name' => $column['field'], 'type' => 's', 'value'=>addslashes($line[$column['field']])]);
            } else {
                array_push($entry['fields'], ['name' => $column['field'], 'type' => 's', 'value'=>$line[$column['field']]]);
            }
        }

        array_push($entries, $entry);
    }

    echo json_encode($entries, JSON_UNESCAPED_UNICODE); // important to unescaped character, otherwise the import in db will be broken
}