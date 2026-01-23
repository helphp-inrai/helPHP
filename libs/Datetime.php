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
 * @class Datetime
 * 
 * Contains useful functions to manipulate dates and times.
 * Essentially used to convert dates from mysql format to html input format, and to measure execution time.
 * 
 * @package helPHP\libs
 */
class Datetime
{
    const second = 1;
    const minute = 60; // in seconds
    const hour = 3600; // in seconds
    const day = 86400; // in seconds

    private $chrono_list = [];
    private $chrono_start_time = 0;
    private $chrono_prev_time = 0;

    public function __construct()
    {
    }
    
    /**
     * Convert a timestamp as mysql date string
     * 
     * If no argument passed return the current time
     * 
     * @param string $timestamp
     * 
     * @return string formatted date Y-m-d H:i:s
     */
    public static function mysql_date($timestamp=false){
        $date = ($timestamp==false)? new \DateTime("NOW"): $timestamp;
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Convert a mysql date string to a date value for an html input date
     * 
     * If no argument passed return empty
     * 
     * @param string $mysql_date
     * 
     * @return string formatted date Y-m-d
     */
    public static function mysql_to_html_date($mysql_date=''){
        if ($mysql_date == '') return '';
        else return date('Y-m-d', strtotime($mysql_date));
    }

    /**
     * Convert a mysql date string to a date value for an html input datetime
     * 
     * If no argument passed return the current datetime
     * 
     * @param string $mysql_date
     * 
     * @return string formatted date Y-m-d\TH:i:s
     */
    public static function mysql_to_html_datetime($mysql_date=''){
        if ($mysql_date == '') return date('Y-m-d\TH:i:s');
        else return date('Y-m-d\TH:i:s', strtotime($mysql_date));
    }
    
    /**
     * return timestamp in milliscondes
     * 
     * @return int milliseconds since Unix epoch (0:00:00 January 1,1970 GMT)
     */

    public static function milliseconds()
    {
        return intval(microtime(true) * 1000);
    }

    /**
     * Start a chrono.
     * This will reset the chrono list and start measuring time.
     * It will also add a first step with the label 'start'.
     * Chrono is used to measure the execution time of a script or a process.
     */
    public function start_chrono()
    {
        $this->chrono_list = [];
        $this->chrono_start_time = microtime(true);
        $this->chrono_prev_time = $this->chrono_start_time;
        array_push($this->chrono_list, ['label'=>'start','rel'=>0,'abs'=>0]);
    }

    /**
     * Add a step to the chrono
     * 
     * @param string $label the name that will be displayed with the step
     */
    public function step_chrono($label='')
    {
        $t = microtime(true);
        $r = $t - $this->chrono_prev_time;
        $a = $t - $this->chrono_start_time;
        array_push($this->chrono_list, ['label'=>$label,'rel'=>number_format($r, 4) , 'abs'=>number_format($a, 4)]);
        $this->chrono_prev_time = $t;
    }

    /**
     * Get the chrono list as a string
     * containing the label, relative time and absolute time for each step.
     * 
     * @return string
     */
    public function get_chrono()
    {
        $str = '';
        foreach ($this->chrono_list as $data) {
            $str .= $data['label'].' : +'.$data['rel'].'  ='.$data['abs'].'    | ';
        }
        return $str;
    }

    /**
     * Convert a mysql date as a readable date.
     * 
     * @param string $datetime mysql date
     * 
     * @return string formatted date as "day month year" (e.g. "12 January 2023") (UE format)
     */
    public static function date_to_string($datetime){
        global $LANG,$TLCOMMON;
        $LANG->load_translation_file_common();
        
        $tmp = explode(' ', $datetime);
        $date = explode('-', $tmp[0]);
        $month = $TLCOMMON['month-'.$date[1]];
        return $date[2].' '.$month.' '.$date[0];
    }

    /**
     * Convert a number of minutes into readable time
     * for a time inferior to 24 hours.
     * 
     * @param int $minutes
     * 
     * @return string formatted time as "HMM" (e.g. "1H30") or "MMmn" (e.g. "90mn")
     */
    public static function mins_to_readable($minutes)
    {
        //oldschool
        if ($minutes > 59) {
            $h = floor($minutes / 60);
            $m = $minutes - ($h*60);
            if ($m<10) {
                $m = '0'.$m;
            }
            return $h.'H'.$m;
        } else {
            return $minutes.'mn';
        }
    }

    /**
     * Convert a number of seconds into readable time
     * for a time inferior to 24 hours.
     * 
     * @param int|float $seconds
     * 
     * @return string formatted time as "HH:MM:SS" (e.g. "01:30:45")
     */
    public static function seconds_to_readable($seconds)
    {
        $time = floatval($seconds);
        $s = str_pad(floor(floor($time) % 60), 2, '0', STR_PAD_LEFT);
        $m = str_pad(floor(floor($time) % 3600 / 60), 2, '0', STR_PAD_LEFT);
        $h = str_pad(floor($time / 3600), 2, '0', STR_PAD_LEFT);
        return $h.':'.$m.':'.$s;
    }

    /**
     * Convert a number of days into readable UE date
     * support only french and english languages for now.
     * 
     * @param int|float $d
     * @param string $lang default is 'fr' for French, can be anything else for English
     * 
     * @return string formatted date as "X an(s) Y mois Z jour(s)" (e.g. "2 ans 3 mois 5 jours") in French or "X year(s) Y month(s) Z day(s)" in English
     * 
     */
    public static function days_to_date($d, $lang = 'fr')
    {
        $years = floor($d / 365);
        $months = floor(($d - ($years*365)) / 30);
        $days = $d - ($years * 365 + $months * 30);
        $output = '';
        if ($years>0) {
            $output.= $years;
            if( $lang == 'fr') {
                $output.= ' an';
            } else {
                $output.= ' year';
            }
            if ($years>1) {
                $output.= 's';
            }
        }
        if ($months>0) {
            $output.= ' '.$months;
            if( $lang == 'fr') {
                $output.= ' mois';
            } else {
                $output.= ' month';
            }
        }
        if ($days>0) {
            if ($years>0 || $months>0) {
                $output.= ' et';
            } else {
                $output.= ' ';
            }
            $output.= ' '.$days;
            if( $lang == 'fr') {
                $output.= ' jour';
            } else {
                $output.= ' day';
            }
            // if more than one day, add an 's' 
            if ($days>1) {
                $output.= 's';
            }
        }
        return $output;
    }
     /**
     * Check if the first date (in string format) is after/superior the second one.
     * by default the second date is now.
     * 
     * @param   string  $date1 the first date
     * @param   string  $date2 the second one , time() by default 
     * 
     * @return boolean true / false.
     */
    public static function is_after($date1, $date2=false)
    {
        $date1=date_create($date1);
        $date1=date_timestamp_get($date1);
        if ($date2 === false) {
            $date2 = time();
        }
        return ($date1>$date2);
    }
    /**
     * Check if the first date (in string format) is prior/inferior the second one.
     * by default the second date is now.
     * 
     * @param   string  $date1 the first date
     * @param   string  $date2 the second one , time() by default 
     * 
     * @return boolean true / false.
     */
    public static function is_prior($date1, $date2=false)
    {
        $date1=date_create($date1);
        $date1=date_timestamp_get($date1);
        if ($date2 === false) {
            $date2 = time();
        }
        return ($date1<$date2);
    }

    /**
     * Get an item from a date formatted as "YYYY-MM-DD HH:MM:SS" (mysql date format).
     * 
     * @param   string  $date
     * @param   string  $info   The part requested, can be :
     *                          date
     *                          year
     *                          month
     *                          day
     *                          time
     *                          hour
     *                          minute
     *                          second
     * 
     * @return string the requested part of the date, or false if the info is not recognized.
     */
    public static function get_date_part($date, $info)
    {
        $t=explode(" ", $date);
        $d=explode("-", $t[0]);
        if (sizeof($t)>1) {
            $h=explode(":", $t[1]);
        } else {
            $h=array();
        }
        switch (strtolower($info)) {
            case "year":
                return $d[0];
                break;
            case "month":
                return $d[1];
                break;
            case "day":
                return $d[2];
                break;
            case "hour":
                return $h[0];
                break;
            case "minute":
                return $h[1];
                break;
            case "seconde":
                return $h[2];
                break;
            case "date":
                if (sizeof($d)>1) {
                    return $d[2]."-".$d[1]."-".$d[0];
                } else {
                    return"";
                }
                break;
            case "time":
                return $t[1];
                break;
        }
        return false;
    }
}