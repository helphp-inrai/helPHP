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

// this file is used to test the import of an db.json into the database

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

    import_object_sql($target, $table, true);
}

function import_object_sql($instance_path, $table_name, $CLI = false){
    include_once($instance_path.'/config/main.php');
    include_once(Config::HELPHP_FOLDER.'/autoload.php');
    
    global $DB;
    // $json = $DB->json_from_sql($table_name);

    global $CONFIG;
    $base_path = $CONFIG::HELPHP_FOLDER.'generated/sql_objects/';
    $DB->sql_from_json(file_get_contents($base_path.$table_name.'.json'));
    
    if ($CLI) echo $base_path.$table_name.'.json imported'.PHP_EOL;
    return true;
}