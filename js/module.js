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
 * @class H_module
 * @classdesc
 * Base class for HelPHP client-side modules.<br>
 * Handles module instantiation, AJAX settings, and instance management.<br>
 * Used as h.libs.module.<br>
 * Extend this class to create your own modules.
 */
class H_module {
    constructor(dom_id, texts){
        this.dom_id = dom_id;

        this.module_name = this.constructor.name.endsWith('_a') ? this.constructor.name.substring(0, this.constructor.name.length - 2) : this.constructor.name;

        this.url = H_constants.base_url + (H_constants.admin_folder ? H_constants.admin_folder : 'public/' ) + this.module_name.toLowerCase() + '/index.php';

        if (texts){
            for (const key in texts) {
                H_constants.texts[key] = texts[key];
            }
        }
    }

    /**
     * Parameters to make an ajax call.<br>
     * Used by ajax_settings().
     * @typedef {Object} Settings
     * @property {string} url Url to call
     * @property {boolean} skip_container return come with main container
     * @property {undefined|boolean} no_timer wether to display a blocking timer during request
     * @property {string} dom_target indicates the dom element that will received the result of the request
     * @property {boolean} replace_dom_target indicates if the dom_target will be replaced by the result of the request
     * @property {boolean} data object that store the posted data of the request
     * @property {undefined|Function} success function called on success
     * @property {undefined|Function} error function called on error
     * @property {undefined|Function} up_progress function called on upload progress
     * @property {undefined|Function} down_progress function called on down progress
     */

    /**
     * Returns default AJAX settings for this module.<br>
     * You can override or extend this in your module.
     * @returns {Settings} Default AJAX settings object.
     */
    ajax_settings(){
        return {
            url: this.url,
            skip_container: true,
            no_timer: undefined,
            dom_target: '',
            replace_dom_target: false,
            data: {
                'dom_id': this.dom_id,
            },
            success: undefined,
            error: undefined,
            up_progress: undefined,
            down_progress: undefined,
        };
    }

    /**
     * Checks if the module instance exists.<br>
     * Must be overrided in your module.
     * @returns {boolean} True if exists.
     */
    exist(){
        return true;
    }

    /**
     * Cleans up the module instance.<br>
     * Should be overrided in your module for custom cleanup.
     * @returns {boolean} True when cleaned.
     */
    clean (){
        return true;
    }

    /**
     * Stores all module instances by class name and dom_id.<br>
     * @type {Object}
     */
    static instances = {};
    
    /**
     * Creates a new instance of the module for a given dom_id.<br>
     * Cleans up any existing instance for the same dom_id.<br>
     * @param {string} dom_id - The DOM element id.
     * @param {Object} settings - Optional settings for the instance.
     * @returns {H_module} The created module instance.
     */
    static create_instance(dom_id, settings, texts) {
        let name = this.name.toLowerCase(); // this.name is the name of the current class (the extending one)
        if (!this.instances[name]) this.instances[name] = {};
        if (this.instances[name][dom_id]) {
            this.instances[name][dom_id].clean();
            delete (this.instances[name][dom_id]);
        }

        this.instances[name][dom_id] = new this(dom_id, settings, texts);
        this.clean_instances();

        return this.instances[name][dom_id];
    }

    /**
     * Cleans up all module instances that no longer exist.<br>
     * Calls clean() (should be overided) on each and removes from the instances list.
     */
    static clean_instances() {
        let toClean = [];
        let name = this.name.toLowerCase(); // this.name is the name of the current class (the extending one)
        for (var key in this.instances[name]) {
            if (!this.instances[name][key].exist()) {
                this.instances[name][key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key) => {
            delete (this.instances[name][key]);
        });
    }
}

h.libs.module = H_module;