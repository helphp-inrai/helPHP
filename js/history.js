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
 * @class H_history
 * @classdesc
 * Utility class for managing browser history and hash-based navigation.<br>
 * Handles hash changes, AJAX navigation, and tab management for single-page applications.<br>
 * When the hash change, an AJAX call is sent. Replacing the # by ? in the url will return the same display as a full reload replaying the AJAX call (but managed by the core module).<br>
 * that is useful for search engine indexation. That's why hierachy menu contain links with ? but when you click on it it just change the hash, loading only what is necessary.<br>
 * As there is also route navigation for document, the history will switch to let the navigator manage forward backward action when it's not an hash change event.<br>
 * Used as h.libs.history.
 */
class H_history {
    
    constructor(){}

     /**
     * Handles the hashchange event.<br>
     * Parses the new hash, manages tab navigation, and triggers AJAX calls as needed.<br>
     * Supports multi-part hashes for chained AJAX requests.<br>
     * Launched by init.js.
     * @param {Event} event - The hashchange event.
     */
    static detect_hash(event){

        let hash = document.location;
        hash = hash.toString();
        hash = hash.split('#');

        if (hash[1] == 'tabs') return;

        if (hash[1] !== "" && hash[1] !== undefined) {
            
            // full hash compare
            let full_hash = decodeURI(hash[1]);

            let previous_hash = '';
            if(h.table_hash.length){
                previous_hash = h.table_hash[h.table_hash.length-1];
            }

            if (previous_hash != full_hash){
                // not the same need to do something
                // split by ¤ to handle multiple ajax call in one hash
                // the symbol ¤ is not accepted by javascript, so we use the encodeURI replacement
                // if we add decodeURI on the hash, it will break this code
                let hash_array = full_hash.split("%C2%A4");

                // get the first part of the hash
                let new_hash = hash_array.shift();

                // handle the first part of the hash and pass the rest of the call
                // when first part done will pass the next hash in array
                H_history.set_hash(new_hash,hash_array);
            }
        }else{

            //if we're back to start
            if (h.table_hash.length>1){
                H_history.set_hash('');
            }
            
        }
    }

    /**
     * Processes a new hash and triggers the appropriate AJAX call.<br>
     * Handles tab switching, module loading, and chained hash navigation.<br>
     * If hash_array is provided, processes each part in sequence.<br>
     * e.g: #document|id=10  will call document public module with a post id=10  
     * @param {string} new_hash - The current hash value.
     * @param {Array|boolean} [hash_array=false] - Remaining hash parts to process.
     * @param {boolean} [force_new_tab=false] - Force opening in a new tab.
     */
    static set_hash(new_hash, hash_array = false, force_new_tab = false) {

        //close burger
        if (typeof(helPHP_burger) !== 'undefined'){
            helPHP_burger.close();
        }

        let previous_hash = '';
        if(h.table_hash.length){
            previous_hash = h.table_hash[h.table_hash.length-1];
        }

        // back to the home
        if (new_hash == ''){
            document.location = document.location.toString().split('#')[0].split('?')[0];
            return;
        }

        // if the previous hash contains the new hash, that mean that this part of the hash is already loaded
        // nothing more to do here except (if there is one) loading the next part of the hash 
        if (previous_hash.includes(new_hash) === false || hash_array.length == 0) { //if no hash_array, nothing more to split, need to reload the module (#news case)

            //Parse and send ajax call
            let params = new_hash.split('-');

            // hash format : moduleData-lang-container
            let to_tab = (typeof(h.main_tab) !== 'undefined');

            let settings = {};
            settings.url = document.baseURI;
            if (params[2] !== "" && params[2] !== undefined) {
                settings.dom_target = params[2];
                to_tab = false;
            }else{
                settings.dom_target='lemain';
            }
            settings.data = {};
            // always needed
            // without will try to load the full site not only the wanted module
            settings.data.core_action = 'core_mono';
            let module_name='';
            params[0] = params[0].replace('<>', '-');
            if (params[0].includes('|')){
                // if it's a custom link (with multiple parameters in it)
                // need to get all the parameters and populate them in the settings data.
                let module = params[0].split('|');
                module_name = (module[0].endsWith('=')) ? module[0].slice(0,-1) : module[0];
                module.splice(0,1);
                module.forEach( (str) => {
                    let tt = str.split('=');
                    settings.data[tt[0]] = tt[1];
                });
                // don't forget to add the module name (with no parameters in this case)
                // without that the module will not load (security ?)
                settings.data[module_name] = '';
            } else {
                let module = params[0].split('=');
                module_name = module[0];
                let module_param = module[1] ? module[1] : '';
                settings.data[module_name] = module_param;
                // and a switch of language
            }
            if (params[1] !== "" && params[1] !== undefined) {
                settings.data.language = params[1];
            }
            
            settings.success = function(res){
                if (window.anim){
                    h.libs.animation.detect_anime(res);
                }
                
                // if we have hash_array, that mean that we are doing a multiple call, need to exec the next
                if (hash_array && hash_array.length > 0) {

                    new_hash = hash_array.shift();
                    set_hash(new_hash,hash_array);

                } else {

                    // add the full hash to the list
                    // can't use new_hash because it can be just a part of the full hash (when in a multiple call)
                    h.table_hash.push(document.location.toString().split('#')[1]);

                }
            };

            if (to_tab){
                h.main_tab.change_hash(settings, force_new_tab);
                this.from_link = false;
                return;
            }

            h.a.send(settings,10);
            window.scrollTo(0, 0);

        } else if (hash_array) {

            new_hash = hash_array.shift();
            set_hash(new_hash,hash_array);

        }
    }

    /**
     * Loads a module via AJAX without modifying history.<br>
     * Parses the hash and triggers the appropriate AJAX call.
     * @param {string} new_hash - The hash string to process.
     */
    static jump_hash(new_hash){

        let params = new_hash.split('-');

        // hash format : moduleData-lang-container
        let settings = {};
        settings.url = document.baseURI.split('?')[0];
        if (params[2] !== "" && params[2] !== undefined) {
            settings.dom_target = params[2];
        }else{
            settings.dom_target = 'lemain';
        }

        settings.data = {};
        // always needed
        // without will try to load the full site not only the wanted module
        settings.data.core_action = 'core_mono';
        let module_name = '';
        params[0] = params[0].replace('<>', '-');

        if (params[0].includes('|')){

            // if it's a custom link (with multiple parameters in it)
            // need to get all the parameters and populate them in the settings data.
            let module = params[0].split('|');
            //~ module_name = module[0].split('/')[0];
            module_name = (module[0].endsWith('=')) ? module[0].slice(0,-1) : module[0];
            module.splice(0,1);
            module.forEach( (str) => {
                let tt = str.split('=');
                settings.data[tt[0]] = tt[1];
            });
            // don't forget to add the module name (with no parameters in this case)
            // without that the module will not load (security ?)
            settings.data[module_name]='';

        } else {

            let module=params[0].split('=');
            module_name=module[0];
            let module_param=module[1];
            settings.data[module_name]=module_param;
            // and a switch of language

        }

        if (params[1] !== "" && params[1] !== undefined) {
            settings.data.language = params[1];
        }
        
        settings.success = function(res){
            if (window.anim){
                h.libs.animation.detect_anime(res);
            }
            if (!h.timer_modal_hidden){
                h.timer_modal.hide();
                h.timer_modal_hidden = true;
            }
        };

        h.a.send(settings,10);
        window.scrollTo(0, 0);
    }

    static from_link = false;
    static last_link_clicked = false;

    /**
     * Changes the browser hash from a click event.<br>
     * Prevents default navigation and updates the hash.<br>
     * Stores the last link clicked and sets from_link flag.
     * @param {Event} event - The click event.
     * @param {string} hash - The new hash value.
     */
    static change_hash(event, hash){
        h.libs.event.stop_event(event);
        location.hash = hash;
        let label = event.target.textContent;
        this.last_link_clicked = label;
        this.from_link = true;
    }
}

var h = h || {};
h.libs = h.libs || {};
h.libs.history = H_history;
window.H_history = H_history;

// var try_socials=false;
// /**
//  * Updates the social sharing widgets for the current module and parameters.<br>
//  * Loads the social container via AJAX and initializes the Socials module.<br>
//  * Retries if the container is not yet available in the DOM.<br>
//  * The social sharing widgets will come soon ;)
//  * @function
//  * @param {string} module_name - The module name.
//  * @param {string} module_param - The module parameter.
//  * @param {string|boolean} [language=false] - Optional language code.
//  * @param {boolean} [collapse=false] - Collapse social buttons if true.
//  */
// function update_socials(module_name,module_param,language=false,collapse=false){
//     let social_container = document.getElementById('socials_public_container');
//     if (social_container){
//         let settings = {};
//         let url = H_constants.base_url+'public/socials/index.php';
//         settings.url = url;
//         settings.data = {};
//         if (language) {
//             settings.data.language = language;
//         }
//         if (social_container.children.length == 3 || collapse){
//             settings.data.social_collapse = true;
//         }
//         settings.skip_container = true;
//         settings.data.module_name = module_name;
//         settings.data.module_param = module_param;

//         settings.success = function(res, e){
//             if (!res.includes('Error')) {
//                 let container = document.getElementById('socials_public_container');
//                 container.innerHTML=res;
//                 if (container.children.length == 3){
//                     Socials.init(1);
//                 }
//             }
//         };
//         try_socials=false;
//         h.a.send(settings);
//     }else{
//         if (try_socials==false){
//             try_socials=true;
//             setTimeout(function(){update_socials(module_name,module_param,language);},500);
//         }
//     }
// }