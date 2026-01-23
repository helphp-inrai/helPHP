/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modif_y, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
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
 * @class H_generics
 * @classdesc
 * Utility class for basic type and object operations.<br>
 * Provides static methods for type checking, object manipulation, and utility helpers.<br>
 * and sometimes little functions just to make the code easier to read and undeerstand.<br>
 * Used as h.libs.generics.<br>
 * <br> In generics.js, you'll find also H_math, and H_strings, two small classes containing generics method<br>
 * for math and strings.
 */
class H_generics {
    static debug = false;
    static unique_id = 0;

    constructor() {}
    
    /**
     * Copies all properties from object a to object b.
     * @param {Object} a - Source object.
     * @param {Object} b - Target object.
     */
    static copy_object(a, b) {
        let keys = Object.keys(a);
        for (let k = 0; k < keys.length; k++) {
            b[keys[k]] = a[keys[k]];
        }
    }
    /**
     * Returns a unique incremental ID.<br>
     * Used a lot for html elements without IDs or objects.
     * @returns {number} Unique ID.
     */
    static get_unique_id() {
        return H_generics.unique_id++;
    }

    /**
     * Checks if a variable is an object.<br>
     * shortcut more readable than test with "typeof"
     * @param {*} a - Value to check.
     * @returns {boolean} True if object.
     */
    static is_object(a) {
        if (!a) {
            return false;
        }
        return typeof (a) == 'object';
    }

    /**
     * Checks if a variable is empty.<br>
     * Works for arrays, objects, and false values.
     * @param {*} a - Value to check.
     * @returns {boolean} True if empty.
     */
    static is_empty(a) {
        if (!a) return true;
        if (H_generics.is_array(a) && a.length == 0) return true;
        if (H_generics.is_object(a) && !H_generics.is_filled_object(a)) return true;

        return false;
    }

    /**
     * Checks if a variable is a DOM object.
     * @param {*} a - Value to check.
     * @returns {boolean} True if DOM object.
     */
    static is_dom_object(a) {
        if (!a) {
            return false;
        }
        if (typeof (a) == 'object') {
            return a.nodeType ? true : false;
        }

        return false;
    }
    
    /**
     * Checks if a variable is a FormData object.
     * @param {*} a - Value to check.
     * @returns {boolean} True if FormData.
     */
    static is_formdata(a) {
        return Object.prototype.toString.apply(a) === '[object FormData]';
    }

    /**
     * Checks if a variable is a string.
     * @param {*} a - Value to check.
     * @returns {boolean} True if string.
     */
    static is_string(a) {
        return typeof (a) == 'string';
    }

    /**
     * Checks if a variable is a function.
     * @param {*} a - Value to check.
     * @returns {boolean} True if function.
     */
    static is_function(a) {
        let t = typeof (a);
        return t == 'function';
    }

    /**
     * Checks if a variable is a property (not a function, null, or undefined).
     * @param {*} a - Value to check.
     * @returns {boolean} True if property.
     */
    static is_property(a) {
        return (typeof (a) != "function" && a !== null && a !== undefined);
    }

    /**
     * Checks if a variable is an array.
     * @param {*} a - Value to check.
     * @returns {boolean} True if array.
     */
    static is_array(a) {
        return Object.prototype.toString.apply(a) === '[object Array]';
    }

    /**
     * Checks if a variable is an Event object.
     * @param {*} a - Value to check.
     * @returns {boolean} True if Event.
     */
    static is_event(a) {
        return Object.prototype.toString.apply(a).indexOf('Event]') > 0;
    }

    /**
     * Checks if an array is filled (not empty).
     * @param {*} a - Value to check.
     * @returns {boolean|number} Length if filled, false otherwise.
     */
    static is_filled_array(a) {
        if (Object.prototype.toString.apply(a) === '[object Array]') {
            return a.length;
        }
        return false;
    }

    /**
     * Checks if an object is filled (has keys).
     * @param {*} a - Value to check.
     * @returns {boolean|number} Number of keys if filled, false otherwise.
     */
    static is_filled_object(a) {
        if (H_generics.is_object(a)) {
            return Object.keys(a).length;
        }
        return false;
    }

    /**
     * Checks if a variable is undefined.
     * @param {*} a - Value to check.
     * @returns {boolean} True if undefined.
     */
    static is_undefined(a) {
        return a === undefined;
    }

    /**
     * Checks if a variable is numeric.
     * just because it's more readable than !isNaN(x)
     * @param {*} a - Value to check.
     * @returns {boolean} True if numeric.
     */
    static is_numeric(a) {
        return !isNaN(a);
    }

    /**
     * Checks if a variable is a File object.
     * @param {*} a - Value to check.
     * @returns {boolean} True if File.
     */
    static is_file(a) {
        return Object.prototype.toString.apply(a) === '[object File]';
    }
    /**
     * Checks if a variable is a FileList object.
     * @param {*} a - Value to check.
     * @returns {boolean} True if FileList.
     */
    static is_file_list(a) {
        return Object.prototype.toString.apply(a) === '[object FileList]';
    }

    /**
     * Returns the type of a variable as a string.
     * @param {*} a - Value to check.
     * @returns {string} Type name.
     */
    static get_type(a) {
        let str = Object.prototype.toString.apply(a).split(' ');
        return str[1].substr(0, str[1].length - 1);
    }
    
    /**
     * Merges two objects into a new object.
     * Properties from b override those from a.
     * @param {Object} a - First object.
     * @param {Object} b - Second object.
     * @returns {Object} Merged object.
     */
    static merge_objects(a, b) {
        let result = {};
        if (!a) {
            a = {};
        }

        if (!b) {
            b = {};
        }
        let keys = Object.keys(a);

        for (let i = 0; i < keys.length; i++) {
            result[keys[i]] = a[keys[i]];
        }

        keys = Object.keys(b);

        for (let i = 0; i < keys.length; i++) {
            result[keys[i]] = b[keys[i]];
        }

        return result;
    }

    /**
     * Executes a function by its name in a given context.<br>
     * Supports nested namespaces and array access.
     * @param {string} function_name - Name of the function (can be namespaced).
     * @param {Object} context - Context object.
     * @param {...*} args - Arguments to pass to the function.
     * @returns {*} Function result.
     */
    static execute_function_by_name(function_name, context) {
        var args = Array.prototype.slice.call(arguments, 2);
        var namespaces = function_name.split(".");
        var func = namespaces.pop();
        for (var i = 0; i < namespaces.length; i++) {
            if (namespaces[i].includes('[')) {
                // parse declaration as obj["name"]
                let namespace1 = namespaces[i].replace(/\[["|']?.*/, '');
                let namespace2 = namespaces[i].replace(/.*\[["|']?/, '').replace(/["|']?\]/, '');
                context = context[namespace1][namespace2];
            } else {
                context = context[namespaces[i]];
            }
        }
        return context[func].apply(context, args);
    }
}

//----------------------------------------------------------------------------------
// STRINGS

/**
 * @class H_strings
 * @classdesc
 * Small utility class for string operations (you'll find it in generics.js).<br>
 * Provides static methods for string formatting and HTML escaping.<br>
 * Used as h.libs.strings.
 */
class H_strings {
    
    static dom_parser = null;
    static xml_http = null;
    
    constructor() {}
    
    /**
     * Replaces $1, $2, ... in a string with values from an array.<br>
     * uses essentialy in translation files to replace $x in sentences.
     * @param {string} str - The string with placeholders.
     * @param {Array|string} replace_array - Values to insert.
     * @returns {string} Completed string.
     */
    static complete_string(str, replace_array) {
        if (replace_array) {
            if (!H_generics.is_array(replace_array)) {
                replace_array = [replace_array];
            }
            if (H_generics.is_filled_array(replace_array)) {
                for (let i = 0; i < replace_array.length; i++) {
                    str = str.replace('$' + (i + 1), replace_array[i]);
                }
            }
        }

        return str;
    }

    /**
     * Escapes HTML special characters in a string.<br>
     * Converts <, >, ", and ' to HTML entities.
     * @param {string} text - The text to escape.
     * @returns {string} Escaped string.
     */
    static escape_html(text) {
        var map = {
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[<>"']/g, function (m) { return map[m]; });
    }
}

//--------------------------------------------------------------------------
// MATH

/**
 * @class H_math
 * @classdesc
 * Small utility class for math operations (you'll find it in generics.js).<br>
 * Provides static methods for parsing, rounding, and checking numbers.<br>
 * Used as h.libs.math.
 */
class H_math {
    
    constructor() {}
    
    /**
     * Extracts a float from a string.<br>
     * Returns 0.0 if not found.
     * @param {string} str - String to parse.
     * @returns {number} Float value.
     */
    static extract_float(str) {
        let f = parseFloat(str);
        if (isNaN(f)) {
            for (let i = 0; i < str.length; i++) {
                let o = str.charCodeAt(i);
                if (o >= 48 && o <= 57 || o == 46) {
                    str = str.substr(i, str.len);
                    break;
                }
            }
            f = parseFloat(str);
            if (isNaN(f)) {
                return 0.0;
            }
        }
        return f;
    }

    /**
     * Extracts an integer from a string.<br>
     * Returns 0 if not found.
     * @param {string} str - String to parse.
     * @returns {number} Integer value.
     */
    static extract_int(str) {
        let f = parseInt(str);
        if (isNaN(f)) {
            for (let i = 0; i < str.length; i++) {
                let o = str.charCodeAt(i);
                if (o >= 48 && o <= 57) {
                    str = str.substr(i, str.len);
                    break;
                }
            }
            f = parseInt(str);
            if (isNaN(f)) {
                return 0;
            }
        }
        return f;
    }

    /**
     * Checks if a number is odd.
     * @param {number} a - Number to check.
     * @returns {boolean|NaN} True if odd, NaN if not a number.
     */
    static is_odd(a) {
        if (isNaN(a)) {
            return NaN;
        } else {
            return a % 2 > 0;
        }
    }

    /**
     * Checks if a number is even.<br>
     * @param {number} a - Number to check.
     * @returns {boolean|NaN} True if even, NaN if not a number.
     */
    static is_even(a) {
        if (isNaN(a)) {
            return NaN;
        } else {
            return a % 2 === 0;
        }
    }
    
    /**
     * Rounds a float to a given number of decimals.<br>
     * @param {number} fl - Float to round.
     * @param {number} [rn=8] - Number of decimals.
     * @returns {number} Rounded float.
     */
    static round_float(fl, rn) {
        if (!rn) {
            rn = 8;
        }
        rn = Math.pow(10, rn);
        return Math.round(fl * rn) / rn;
    }
}

var h = h || {};
h.libs = h.libs || {};

h.libs.generics = H_generics;
h.libs.strings = H_strings;
h.libs.math = H_math;