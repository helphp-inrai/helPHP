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

/**
 * beautify_module_list is used during install or uninstall module to insert suppress
 * module configuration into module list config in main.php file...
 * it just do the job to keep is readable .
 * 
 * And if you're asking what is $temporary_module ? 
 * It's a surprise ;) job in progress.. 
 *
 * @param mixed|array $module_list 
 * @param mixed|array $temporary_module
 * 
 * @return array the module list beautified 
 * 
 * @package helPHP\utils
 */
function beautify_module_list($module_list, $temporary_module){
    $result = var_export($module_list, true);
    
    // var_export output is not as wanted, following regex purposes is to make it better
    // replace 2 tab indent by 4 tab indent
    $parsed = preg_replace('/ {2}/', '    ', $result);
    // array declaration now follow the variable like core => array(
    $parsed = preg_replace('/(\'[a-z]+\' =>) \n +array \(/', '$1 array(', $parsed);
    // options json as too many space as indent, clear them and make it right
    // this json is the only place to have " in it at the beginning of the line so we can use it to detect it
    $parsed = preg_replace('/ +"/', '            "', $parsed);
    // the end of json need to get the same treatment
    $parsed = preg_replace('/ +(}\')/', '        $1', $parsed);
    // each line except the first need one more tab in front
    // the first line doesn't have newline in front
    $parsed = preg_replace('(\n)', PHP_EOL.'    ', $parsed);

    // >>>temporarymodule>>> need to be added at the end of the list
    if (!$temporary_module) { // if false we only need to add both comment
        $parsed = preg_replace('/( +\)$)/', '        //>>>temporarymodule>>>'.PHP_EOL.'        //<<<temporarymodule<<<'.PHP_EOL.'$1', $parsed);
    } else { // a module need to be between temporary comments
        $splitted = preg_split('/ +\''.$temporary_module.'\'/', $parsed); // get the part of modules list before the module that was temporary
        $parsed = $splitted[0];
        $splitted = preg_split('/ +\),\n/', $splitted[1], 2); // 0 is the module part to add in temporary
        $temp_part = '    //>>>temporarymodule>>>'.PHP_EOL;
        $temp_part.= '        \''.$temporary_module.'\''.$splitted[0].'        )'.PHP_EOL;
        $temp_part.= '        //<<<temporarymodule<<<'.PHP_EOL.'    )';
        
        // add what's following temporary to get all the module
        $parsed.= substr($splitted[1], 0, -1).$temp_part; // remove the last character because it's the last ) of the array and it come with temp_part
    }

    return $parsed;
}