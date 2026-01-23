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
 * @class Notification
 * 
 * The notification class, is used by the notification module to send notifications messages
 * to a user, a group of user, or administrator.
 * 
 * it can get various level 1 to 3 and different types :
 * - 1 a simple notification visible in notification module display
 * - 2 a popup modal dialog for more urgent ones.
 * - 3 a totaly invisible one that carrying some js code to execute. this one is for maintenance or user UI debug
 * or for emergencies (like forced logout due to urgent maintenance) 
 * 
 * @package helPHP\libs
 */
class Notification {
  
    public function __construct() {
        
    }
    
    /**
     * Create a notification 
     * 
     * @param string    $name           Notification's name
     * @param string    $description    Notification's description
     * @param int       $level          Notification's level
     *                                  1 (normal), 2 (warning), 3 (alert).
     *                                  Defaults to 1.
     * @param string    $type           Type of target to send the notification, the value of this field determine what is 
     *                                  the parameter $target
     *                                  '1' user id given as target
     *                                  '2' group name given as target
     *                                  '3' all users
     *                                  '4' admin users
     *                                  '11' same as 1 but in a popup modale box
     *                                  '12' same as 2 but in a popup modale box
     *                                  '13' same as 3 but in a popup modale box
     *                                  '21' same as 1 but it's an hidden js call contained in action field or description field.
     *                                  '22' same as 2 but it's an hidden js call contained in action field or description field.
     *                                  '23' same as 3 but it's an hidden js call contained in action field or description field.
     * @param string    $target         The target that will receive the notification, may be an user id or a group name
     * @param string    $action         Optional. Will be applied when the notification is received.
     *                                  Defaults to ''.
     * @param string    $action_name    Optional. Complete the previous parameter.
     *                                  Defaults to ''.
     * @param bool      $must_inc       Optional. Tell to increment or not the occurence of a same notification.
     *                                  Defaults to true.
     * @param bool      $force_new      Optional. Force the creation of a new notification
     */
    public static function create($name, $description, $level=1, $type='', $target='', $action='', $action_name='', $must_inc=true, $force_new=false) {
        global $DB;

        global $CONFIG_DB;
        if ($CONFIG_DB::DB_CENTRAL){
            global $DB_CENTRAL;
            $USER_DB=$DB_CENTRAL;
        } else {
            $USER_DB=$DB;
        }
        
        // TODO: Make the clean and delay an option
        //cleaning 
        $delaypurge=31*24*60*60;
        // clean db from too old notif
        $q = 'DELETE LOW_PRIORITY IGNORE FROM '.$DB->table('alert_data').' WHERE (unix_timestamp(date)+'.$delaypurge.') < unix_timestamp(now())';
        $clean = $DB->query($q);

        if ($type=='' && $target==''){
            $type=4;
        }

        $type=intval($type);

        switch($type){
            case 1:
            case 11:
            case 21:
                $users_ids=array($target);
            break;
            case 2:
            case 12:
            case 22:
                $q = 'SELECT DISTINCT grpuser.id_users_data from '. $DB->table('group_users').' grpuser, '.$DB->table('group_data').' grp where grpuser.id_group_data = grp.id and grp.name=?';
                $users_ids = $USER_DB->prepared_query_list($q,'s',[$target]);
            break;
            case 3:
            case 13:
            case 23:
                $q = 'SELECT DISTINCT id from '.$DB->table('users_data');
                $users_ids = $USER_DB->query_list($q);
            break;
            case 4:
                $q = 'SELECT DISTINCT id from '.$DB->table('users_data').' where admin = 1';
                $users_ids = $USER_DB->prepared_query_list($q);
            break;
        }

        foreach($users_ids as $user_id){
            if (Notification::check($name,$description,$action,$user_id,$must_inc,$DB) == 0 || $force_new){
                $q = 'INSERT INTO '. $DB->table('alert_data').' SET name=?,description=?,occurence=?,level=?,action=?,action_name=?,type=?,id_user=?';
                $success = $DB->prepared_query($q,'ssiissii',[$name,$description,1,$level,$action,$action_name,$type,$user_id]);
            }
        }
    }
    
    /**
     *  Delete a notification
     * 
     * @param string    $name           Notification's name
     * @param string    $description    Notification's description
     * @param string    $action         Notification's action
     * @param string    $action_name    Notification's action_name
     */
    public static function delete($name,$description,$action,$action_name){
        global $DB;
        $q = 'DELETE FROM '. $DB->table('alert_data').' WHERE name=? and description=? and action=? and action_name=?';
        $success = $DB->prepared_query($q,'ssss',[$name,$description,$action,$action_name]);
    }  
    
    /**
     *  Check if the same notification exist.
     * 
     * @param string    $name           Notification's name.
     * @param string    $description    Notification's description.
     * @param string    $action         Notification's action.
     * @param string    $action_name    Notification's action_name.
     * 
     * @return int Indicates the number of this notification.
     */
    public static function check($name,$description,$action,$id_user,$must_inc=false){
        global $DB;

        $q = 'SELECT COUNT(*) from '. $DB->table('alert_data').' where name=? and description=? and action=? and id_user=?';
        $total = $DB->prepared_query_value($q,'sssi',[$name,$description,$action,$id_user]);
        if ($total>0 && $must_inc){
            $q = 'Update '. $DB->table('alert_data').' set occurence=occurence+1 where name=? and description=? and action=? and id_user=?';
            $res = $DB->prepared_query($q,'sssi',[$name,$description,$action,$id_user]);
            $total=$total+1;
        }
        return $total;
    }
}