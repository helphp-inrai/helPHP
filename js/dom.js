/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
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

//-------------------------------------------------------------
// all manipulation around ONE dom element
/**
 * @class H_dom
 * @classdesc
 * Utility class for DOM manipulation and element management.<br>
 * Provides static methods for creating, modifying, positioning, styling, and interacting with DOM elements.<br>
 * Used as h.libs.dom.<br>
 * <br>
 * It permit also to create dom elements for your UI, complexe UI ones are made with H_ui.<br>
 * 
 */
class H_dom {

    static debug = false;
    static dom_parser = new DOMParser();

    // static elements_pool = {};
    // static focused_element = null;

    static current_global_cursor = '';

    constructor() {}

    /**
     * Insert HTML code inside a dom element<br>
     * 
     * Ensures scripts are executed. Replace content by default.
     * 
     * @param {*} target Element to insert inside.
     * @param {string|HTMLElement} html_data HTML to insert.
     * @param {boolean} [append=false] - True to append new content.
     * @param {boolean} [before=false] - True to prepend the content.
     */
    static write_html(target, html_data, append = false, before = false) {

        target = H_dom.get_dom_element(target);

        if (H_generics.is_string(html_data)) {
            let html = H_dom.dom_parser.parseFromString(html_data.trim(), "text/html");
            html_data = [];
            // check for child in body and in head, important to begin by head because parseFromString will put script into head if they come before
            // any other html elements inside the string
            for (let i = 0; i < html.head.childNodes.length; i++) {
                html_data.push(html.head.childNodes[i]);
            }
            for (let i = 0; i < html.body.childNodes.length; i++) {
                html_data.push(html.body.childNodes[i]);
            }
        }

        if (target) {
            if (append) {
                html_data.forEach(element => {
                    target.appendChild(element);
                });
            } else if (before) {
                let before_me = target.firstChild;
                H_dom.insert_before(html_data, before_me);
            } else {
                // replace all child
                if (target.hasChildNodes()) {
                    while (target.firstChild) {
                        target.removeChild(target.firstChild);
                    }
                }
                html_data.forEach(element => {
                    target.appendChild(element);
                });
            }


            // when append, detect script that was present before and trig them again
            let scripts = target.querySelectorAll("script");
            for (let i = 0; i < scripts.length; i++) {
                if (scripts[i].textContent.includes('debugger;')) {
                    console.log(scripts[i].textContent);
                }
                H_dom.add_script_tag(scripts[i], i);
            }

        } else {
            console.warn('Cannot find dom target ', dom_target);
        }
    }

    /**
     * Adds a script tag to the DOM, handling defer if present.<br>
     * @param {HTMLElement} script_tag - The script tag element.
     * @param {number} delay - Optional delay for deferred script_tag.
     */
    static add_script_tag(script_tag, delay) {
        if (script_tag.hasAttribute('defer')) {
            if (!delay) {
                delay = 1;
            }
            setTimeout(function () { H_dom.insert_script_tag(script_tag); }, 50 + delay);
        } else {
            H_dom.insert_script_tag(script_tag);
        }
    }
    
    /**
     * Inserts a script tag into the DOM and executes it.<br>
     * Handles both external and inline scripts.
     * @param {HTMLElement} script_tag - The script tag element.
     */
    static insert_script_tag(script_tag) {

        if (script_tag.hasAttribute('src')) {
            let src = script_tag.getAttribute('src');
            setTimeout(function () { script_tag.remove(); }, 100);

            return H_dom.load_script(src);

        } else {
            // console.log(script_tag, script_tag.innerHTML);
            if (!script_tag.evaldone) {
                eval(script_tag.innerHTML || script_tag.text);
                script_tag.evaldone = true;

                H_dom.remove_element(script_tag);
            }
        }

        return true;
    }

    /**
     * Reloads the page or navigates to a given URL.<br>
     * @param {string} [url] - Optional URL to navigate to.
     */
    static reload_page(url) {

        // Utile ?? Je ne pense pas
        if (H_generics.is_empty(url) && H_generics.is_string(this.url)) url = this.url;

        if (H_generics.is_string(url)) {
            if (url === H_constants.reload_noparams) {
                document.location = document.location.origin + document.location.pathname;
            } else {
                document.location = url;
            }
        } else {
            document.location.reload();
        }
    }

    /**
     * Moves a script or link element to the document head.
     * @param {string} id - The element ID.
     */
    static move_to_header(id) {

        let obj = document.getElementById(id);

        if (obj) {
            let type = obj.tagName.toLowerCase();
            let attr_name = '';
            let ref_val = '';

            switch (type) {
                case 'script':
                    attr_name = 'src';
                    break;

                case 'link':
                    attr_name = 'href';
                    break;

                default:
                    console.warn('!!!');
                    return false;
            }

            ref_val = obj.getAttribute(attr_name);

            let elements = document.head.getElementsByTagName(type);

            for (let i = 0; i < elements.length; i++) {
                let s = elements[i];
                if (s.getAttribute(attr_name) == ref_val) {
                    if (H_dom.debug) {
                        console.log('script ', url, ' already in header');
                    }
                    obj.remove();
                    return false;
                }
            }
            document.head.appendChild(obj);
        }
    }

    /**
     * Loads a script into the document head if not already present.
     * @param {string} url - Script URL.
     * @returns {boolean} True if loaded, false if already present.
     */
    static load_script(url) {
        let scripts = document.head.getElementsByTagName("script");

        for (let i = 0; i < scripts.length; i++) {
            let s = scripts[i];
            if (s.getAttribute('src') == url) {
                if (H_dom.debug) {
                    console.log('script ', url, ' already in header');
                }
                return false;
            }
        }

        let script_tag = H_dom.create_element('SCRIPT');
        script_tag.onload = function () {
            if (H_dom.debug) {
                console.log(url, 'loaded');
            }
        };
        script_tag.src = url;
        document.head.appendChild(script_tag);
        return true;
    }

    /**
     * Converts an HTML string to DOM nodes.<br>
     * Optionally activates scripts and returns multiple nodes.
     * @param {string} str - HTML string.
     * @param {boolean} [multiple=false] - Return all nodes.
     * @param {boolean} [active_script=false] - Activate scripts.
     * @returns {Node|NodeList} The DOM node(s).
     */
    static string_to_dom(str, multiple = false, active_script = false) {
        let res = H_dom.dom_parser.parseFromString(str, 'text/html');
        if (active_script) {
            let new_doc = document.createDocumentFragment();
            while (res.body.firstChild) {
                new_doc.appendChild(res.body.firstChild);
            }

            let scripts = new_doc.querySelectorAll('script');
            for (let i = 0; i < scripts.length; i++) {
                let parent = scripts[i].parentNode || new_doc;
                let new_script = document.createElement('script');
                if (scripts[i].src) {
                    new_script.src = scripts[i].src;
                } else {
                    new_script.textContent = scripts[i].textContent;
                }
                parent.replaceChild(new_script, scripts[i]);
            }
            res = new_doc;
            if (!multiple) return res.firstChild;
            else return res.childNodes;
        } else {
            if (!multiple) return res.firstChild.lastChild.firstChild;
            else return res.firstChild.lastChild.childNodes;
        }
    }

    /**
     * Hides a DOM element, storing its original display value.
     * @param {HTMLElement} element - The element to hide.
     */
    static hide_element(element) {
        if (!element._hidden) {
            if (element.style.display != 'none') {
                element._originalVisibility = element.style.display;
            } else {
                element._originalVisibility = '';
            }

        }

        element.style.display = 'none';
        element._hidden = true;
    }

    /**
     * Shows a previously hidden DOM element.<br>
     * and restore its visibility to the original one or to an opationnal force_visibility_value
     * @param {HTMLElement} element - The element to show.
     * @param {string} [force_visibility_value] - Optional display value.
     */
    static show_element(element, force_visibility_value) {

        if (element._hidden) {
            if (force_visibility_value) {
                element.style.display = force_visibility_value;
            } else {
                element.style.display = element._originalVisibility;
            }

        } else {
            //Get the value of style attribute
            var original_style = element.getAttribute('style') || '';

            //var regex = new RegExp(/(width:|height:).+?(;[\s]?|$)/g);
            var regex = new RegExp(/(display:).+?(;[\s]?|$)/g);

            //Replace matches with null
            var mod_style = original_style.replace(regex, '');

            element.setAttribute('style', mod_style);

        }
        element._hidden = false;

    }

    /**
     * Checks if two elements are the same.<br>
     * @param {*} a - First element.
     * @param {*} b - Second element.
     * @returns {boolean} True if same.
     */
    static is_same_element(a, b) {

        return Object.is(a, b);
    }
   
    /**
     * Finds the index of a child element.
     * @param {HTMLElement} parent_element - Parent element.
     * @param {HTMLElement} child_element - Child element.
     * @returns {number} Index or -1 if not found.
     */
    static find_child_index(parent_element, child_element) {
        let result = [];
        for (let i = 0; i < parent_element.childNodes.length; i++) {
            if (parent_element.childNodes[i] === child_element) return i;
        }

        return -1;
    }

    /**
     * Gets a DOM element by ID or returns the dom object.
     * @param {string|object} dom_id_or_object - ID or element.
     * @returns {HTMLElement|null} The DOM element or null.
     */
    static get_dom_element(dom_id_or_object) {
        if (H_generics.is_object(dom_id_or_object)) {
            if (H_generics.is_dom_object(dom_id_or_object)) {
                return dom_id_or_object;
            } else if (H_generics.is_dom_object(dom_id_or_object.dom_element)) {
                return dom_id_or_object.dom_element;
            } else {
                return null;
            }

        } else if (H_generics.is_string(dom_id_or_object)) {
            if (!dom_id_or_object) {
                return null;
            }
            return document.getElementById(dom_id_or_object);
        }

        return null;
    }

    /**
     * Creates a new DOM or SVG element with attributes and content.<br>
     * and add it to the targetted parent?
     * @param {string} tag_name - Tag name.
     * @param {object} [params] - Attributes.
     * @param {*} [content] - Content to append.
     * @param {HTMLElement} [parent] - Parent to append to.
     * @returns {HTMLElement} The created element.
     */
    static create_element(tag_name, params, content, parent) {

        var element;
        params = params || {};
        tag_name = tag_name.toUpperCase();

        var svg_element = tag_name.indexOf('SVG_') === 0;
        let xmlns = 'http:/' + '/www.w3.org/2000/svg';
        if (tag_name == 'SVG') {

            element = document.createElementNS(xmlns, 'svg');
            element.setAttribute('xmlns', xmlns);
            element.setAttribute('version', '1.2');

        } else if (svg_element) {
            tag_name = tag_name.substring(4, tag_name.length).toLowerCase();

            element = document.createElementNS(xmlns, tag_name);

        } else {
            element = document.createElement(tag_name);
        }

        var attr_list = element.attributes;
        for (let attr in attr_list) {
            if (attr != 'xmlns' && attr != 'version') {
                element.removeAttribute(attr);
            }
        }

        if (svg_element) {
            for (let attr in params) {
                element.setAttributeNS(null, attr, params[attr]);
            }

        } else {
            for (let attr in params) {
                if (tag_name == 'SVG') {
                    element.setAttribute(attr, params[attr]);
                } else if (element[attr] !== undefined) {
                    element[attr] = params[attr];
                } else {
                    element.setAttribute(attr, params[attr]);
                }
            }
        }

        element._custom_classes = {};
        element._event_listeners = [];

        if (content) {
            if (H_generics.is_dom_object(content)) {
                element.appendChild(content);
            } else if (H_generics.is_string(content)) {
                element.innerHTML = content;
            } else {
                element.append(content);
            }
        }

        if (H_generics.is_dom_object(parent)) {
            parent.appendChild(element);
        }

        return element;
    }

    /**
     * Creates an SVG icon element.<br>
     * @param {string} name - Icon name.
     * @param {object} [params={}] - SVG attributes.
     * @returns {SVGElement} The SVG icon element.
     */
    static create_icon(name, params = {}){
        params.class = params.class ? 'hlp_icon ' + params.class : 'hlp_icon';
        params.style = params.style ? params.style + 'pointer-events: none;' : 'pointer-events: none;';
        let use = H_dom.create_element('SVG_USE', {href: H_constants.base_url + 'images/icons/feather-sprite.svg#'+name});
        return H_dom.create_element('SVG', params, use);
    }

    /**
     * Creates an button info element.<br>
     * @param {object} [params={}] - attributes.
     * @param {object} [content}] - content of the tooltip.
     * @param {string} [type='text'] - type of content (text or link).
     * @returns {SVGElement} The SVG icon element.
     */
    static create_button_info(content, params = {}, type = 'text'){
        let container = H_dom.create_element('DIV', {class: 'hlp_button_info_container'});

            let css = params.class ? ' ' + params.class : '';
            let id_content = 'hlp_btn_info_content¤DOM_ID' + H_generics.get_unique_id();

            params.class = 'hlp_button_info_button' + css;
            let button = H_dom.create_element('DIV', params, H_dom.create_icon('help-circle'));
            h.e.add_event_click(button, (evt)=>{H_ui.tooltip(evt, id_content);});

            if (H_generics.is_string(content) && (type == 'link')){
                content = H_dom.create_element('A', {href: content, target: '_blank'}, content);
            }
            let container_content = H_dom.create_element('DIV', {class: 'hlp_button_info_content hidden' + css, id: id_content}, content);

        H_dom.append_content(container, [button, container_content]);
        return container;
    }

    /**
     * Appends content to a DOM element.<br>
     * Handles DOM elements, arrays, objects, and strings.<br>
     * or an object/class who got an attribute dom_element that point to a dom element
     * @param {HTMLElement} dom_element - Target element.
     * @param {*} content - Content to append.
     */
    static append_content(dom_element, content) {
        if (H_generics.is_dom_object(content)) {
            dom_element.appendChild(content);

        } else if (H_generics.is_filled_array(content)) {
            for (let i in content) {
                H_dom.append_content(dom_element, content[i]);
            }

        } else if (H_generics.is_filled_object(content)) {
            for (let i in content) {
                if (content[i].dom_element) {
                    H_dom.append_content(dom_element, content[i].dom_element);
                }
            }
        } else {
            H_dom.write_html(dom_element, content, true);
        }

    }
    /**
     * Removes a DOM element from the document and cleans up events etc.<br>
     * @param {HTMLElement} element - Element to remove.
     */
    static remove_element(element) {

        // remove all events
        H_event.remove_all_events(element);

        // recursive cleaning
        let ttt = 0;
        if (element.childNodes) {
            while (element.childNodes.length > 0 && ttt < 1000) {
                H_dom.remove_element(element.childNodes[0]);
                ttt++;
            }
            if (ttt >= 1000) {
                debugger;
            }
        }

        // remove from DOM
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }

    /**
     * Gets custom class objects linked to a DOM element.<br>
     * @param {HTMLElement} element - The DOM element.
     * @param {string} [type] - Class type.
     * @returns {object|Array} The class object(s).
     */
    static get_class_object(element, type) {
        if (!element._custom_classes) {
            element._custom_classes = {};
        }

        if (!type) {
            let keys = Object.keys(element._custom_classes);
            let list = [];
            if (keys.length > 0) {
                for (let k in keys) {
                    list.push(element._custom_classes[keys[k]]);
                }
            }
            return list;
        }
        return element._custom_classes[type];
    }
    /**
     * Clones a DOM element, optionally recursively.<br>
     * Copies event listeners and custom classes.
     * @param {HTMLElement} dom_element - Element to clone.
     * @param {boolean} recursive - Clone children.
     * @returns {HTMLElement} The cloned element.
     */
    static clone_dom_element(dom_element, recursive, event = true) {

        let new_element = dom_element.cloneNode(recursive);

        if (event && H_generics.is_filled_array(dom_element._event_listeners)) {
            let lst = dom_element._event_listeners;

            if (!H_generics.is_array(new_element._event_listeners)) {
                new_element._event_listeners = [];
            }

            for (let i = 0; i < lst.length; i++) {
                H_event.add_event(new_element, lst[i].event, lst[i].callback);
            }
        }

        if (H_generics.is_filled_array(dom_element._custom_classes)) {
            // TODO
            // add a Clong() function in classes
            let lst = dom_element._custom_classes;

            if (!H_generics.is_array(new_element._custom_classes)) {
                new_element._custom_classes = [];
            }

            for (let i = 0; i < lst.length; i++) {
                if (H_generics.is_function(lst[i].clone)) {
                    H_generics.execute_function_by_name(lst[i].clone, window, new_element);
                } else {
                    console.log('no clone method found on ', lst[i]);
                    debugger;
                }
            }
        }

        if (dom_element._validator) new_element._validator = dom_element._validator;

        return new_element;
    }
    //---------------------------------------------------------------------
    /**
     * Checks if a DOM element can have CSS classes. classList exist.<br>
     * @param {HTMLElement} dom_element - The element.
     * @returns {boolean} True if can have CSS.
     */
    static can_have_css(dom_element) {
        if (H_generics.is_dom_object(dom_element)) {
            return dom_element.classList != undefined;
        }
        return false;
    }
    /**
     * Checks if a DOM element has a specific class.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {string} class_name - Class name.
     * @returns {boolean} True if has class.
     */
    static has_class(dom_element, class_name) {
        if (H_dom.can_have_css(dom_element)) {
            return dom_element.classList.contains(class_name);
        }
        return false;
    }

    /**
     * Adds a class to a DOM element.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {string} class_name - Class name.
     */
    static add_class(dom_element, class_name) {
        if (H_dom.can_have_css(dom_element)) {
            dom_element.classList.add(class_name);
        }
    }

    /**
     * Removes a class from a DOM element.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {string} class_name - Class name.
     */
    static remove_class(dom_element, class_name) {
        if (H_dom.can_have_css(dom_element)) {
            dom_element.classList.remove(class_name);
        }
    }

    /**
     * Replaces a class on a DOM element.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {string} class_name_to_replace - Class to replace.
     * @param {string} new_class_name - New class name.
     */
    static replace_class(dom_element, class_name_to_replace, new_class_name) {
        if (H_dom.can_have_css(dom_element)) {
            dom_element.classList.replace(class_name_to_replace, new_class_name);
        }
    }

    /**
     * Toggles a class on a DOM element.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {string} class_name - Class name.
     * @param {boolean} [force_state] - Force toggle state.
     */
    static toggle_class(dom_element, class_name, force_state) {
        if (H_dom.can_have_css(dom_element)) {
            dom_element.classList.toggle(class_name, force_state);
        }
    }

     /**
     * Replaces a DOM element with another, executing scripts if present.<br>
     * @param {HTMLElement|string} original_element - Element to replace.
     * @param {HTMLElement|string} new_element - Replacement.
     */
    static replace_element(original_element, new_element) {
        // to be able to execute the scripts added this way, need to encapsule the return into a html element to retrieve and execute them
        // add the new_element to a html element that will not be added to the page
        let temp = H_dom.create_element('DIV', false, new_element);
        // retrieve the scripts
        let scripts = temp.querySelectorAll("script");
        // remove temporary html element
        // Be careful, do not recreate the old events!
        if (H_generics.is_string(new_element)) {
            original_element.outerHTML = new_element;
        } else if (H_generics.is_dom_object(new_element)) {
            H_dom.insert_after(new_element, original_element);
            H_dom.remove_element(original_element);
        }
        // execute the scripts
        for (let i = 0; i < scripts.length; i++) {
            H_dom.add_script_tag(scripts[i], i);
        }
    }

    /**
     * Inserts a DOM element before another.
     * @param {HTMLElement|string} dom_element - Element to insert.
     * @param {HTMLElement} before_me - Reference element.
     */
    static insert_before(dom_element, before_me) {
        if (H_generics.is_string(dom_element)) {
            let html = H_dom.dom_parser.parseFromString(dom_element.trim(), "text/html");
            dom_element = [];
            // check for child in body and in head, important to begin by head because parseFromString will put script into head if they come before
            // any other html elements inside the string
            for (let i = 0; i < html.head.childNodes.length; i++) {
                dom_element.push(html.head.childNodes[i]);
            }
            for (let i = 0; i < html.body.childNodes.length; i++) {
                dom_element.push(html.body.childNodes[i]);
            }
        } else if (!H_generics.is_array(dom_element)){
            dom_element = [dom_element];
        }

        let scripts = [];
        dom_element.forEach(element => {
            before_me.insertAdjacentElement('beforebegin', element);
            if (element.tagName == 'SCRIPT'){
                scripts.push(element);
            } else {
                let t = Array.from(element.querySelectorAll("script"));
                if (t.length > 0) {
                    scripts = scripts.concat(t);
                }
            }
        });

        if (scripts){
            for (let i = 0; i < scripts.length; i++) {
                H_dom.add_script_tag(scripts[i], i);
            }
        }

        H_dom.set_rect_dirty(before_me);
    }

    /**
     * Inserts a DOM element after another.<br>
     * @param {HTMLElement|string} dom_element - Element to insert.
     * @param {HTMLElement} after_me - Reference element.
     */
    static insert_after(dom_element, after_me) {
        if (H_generics.is_string(dom_element)) {
            let html = H_dom.dom_parser.parseFromString(dom_element.trim(), "text/html");
            dom_element = [];
            // check for child in body and in head, important to begin by head because parseFromString will put script into head if they come before
            // any other html elements inside the string
            for (let i = 0; i < html.head.childNodes.length; i++) {
                dom_element.push(html.head.childNodes[i]);
            }
            for (let i = 0; i < html.body.childNodes.length; i++) {
                dom_element.push(html.body.childNodes[i]);
            }
        } else if (!H_generics.is_array(dom_element)) {
            dom_element = [dom_element];
        }

        let scripts = [];
        dom_element.forEach(element => {
            after_me.insertAdjacentElement('afterend', element);
            if (element.tagName == 'SCRIPT'){
                scripts.push(element);
            } else {
                let t = Array.from(element.querySelectorAll("script"));
                if (t.length > 0) {
                    scripts = scripts.concat(t);
                }
            }
        });

        if (scripts){
            for (let i = 0; i < scripts.length; i++) {
                H_dom.add_script_tag(scripts[i], i);
            }
        }

        H_dom.set_rect_dirty(after_me);
    }
    
    /**
     * Gets the absolute rect of an element in page coordinates.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {boolean} [force_refresh] - Force recompute.
     * @param {boolean} [debug=false] - Debug output.
     * @returns {object|null} Rect object or null.
     */
    static get_global_rect(dom_element, force_refresh, debug = false) {
        if (!dom_element) {
            return null;
        }

        if (dom_element._rect_ready && !force_refresh) {
            return dom_element._computed_style.rect;
        }

        if (dom_element === document) {
            dom_element = document.body;
        }


        if (!dom_element.getBoundingClientRect) {
            debugger;
        }

        let rect = dom_element.getBoundingClientRect();

        if (dom_element == document.body) {
            rect = { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height };
            if (rect.height === 0) {
                rect.bottom = window.innerHeight;
                rect.height = window.innerHeight;
            }

            if (rect.width === 0) {
                rect.right = window.innerWidth;
                rect.width = window.innerWidth;
            }
        }

        let t = '';
        let sx = window.scrollX || (((t = document.documentElement) || (t = document.body.parentNode)) && typeof t.scrollLeft == 'number' ? t : document.body).scrollLeft || 0;
        let sy = window.scrollY || (((t = document.documentElement) || (t = document.body.parentNode)) && typeof t.scrollTop == 'number' ? t : document.body).scrollTop || 0;
        if (!dom_element._computed_style) {
            dom_element._computed_style = {};
        }
        if (!dom_element._computed_style.rect) {
            dom_element._computed_style.rect = {};
        }

        if (debug) console.log('bounding client rect', rect);

        dom_element._computed_style.rect.left = rect.left + sx;
        dom_element._computed_style.rect.top = rect.top + sy;
        dom_element._computed_style.rect.right = rect.right + sx;
        dom_element._computed_style.rect.bottom = rect.bottom + sy;
        dom_element._computed_style.rect.width = rect.right - rect.left;
        dom_element._computed_style.rect.height = rect.bottom - rect.top;
        dom_element._computed_style.rect.x = dom_element._computed_style.rect.left;
        dom_element._computed_style.rect.y = dom_element._computed_style.rect.top;

        if (debug) console.log('computed rect', dom_element._computed_style.rect);

        dom_element._rect_ready = true;

        return dom_element._computed_style.rect;
    }
    
    /**
     * Converts page coordinates to local coordinates inside an element.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} x - X coordinate.
     * @param {number} y - Y coordinate.
     * @param {boolean} [advanced] - Return ratios.
     * @returns {object} Local coordinates.
     */
    static get_global_to_local(dom_element, x, y, advanced) {
        let style = H_dom.get_style(dom_element);

        x = x - style.rect.left - style.BL;
        y = y - style.rect.top - style.BT;

        if (advanced) {
            return { 'x': x, 'y': y, 'ratiox': x / style.rect.width, 'ratioy': y / style.rect.height };
        } else {
            return { 'x': x, 'y': y };
        }
    }

    /**
     * Gets the position of an element (offsetLeft/offsetTop).
     * @param {HTMLElement} dom_element - The element.
     * @returns {object} Position {x, y}.
     */
    static get_position(dom_element) {
        return { 'x': dom_element.offsetLeft, 'y': dom_element.offsetTop };
    }

    /**
     * Gets the relative position of an element.
     * @param {HTMLElement} dom_element - The element.
     * @returns {object} Position {x, y}.
     */
    static get_relative_position(dom_element) {
        return { 'x': dom_element.left, 'y': dom_element.top };
    }

    /**
     * Gets the global position of an element.
     * @param {HTMLElement} dom_element - The element.
     * @returns {object} Position {x, y}.
     */
    static get_global_position(dom_element) {
        let rect = H_dom.get_global_rect(dom_element);
        return { 'x': rect.x, 'y': rect.y };
    }

    /**
     * Checks if a point is inside an element's global rect.
     * @param {number} x - X coordinate.
     * @param {number} y - Y coordinate.
     * @param {HTMLElement} dom_element - The element.
     * @returns {boolean} True if inside.
     */
    static point_inside_element(x, y, dom_element) {
        let rect = H_dom.get_global_rect(dom_element, true);
        return (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom);
    }
    
    /**
     * Returns data about a point relative to an element.
     * @param {number} x - X coordinate.
     * @param {number} y - Y coordinate.
     * @param {HTMLElement} dom_element - The element.
     * @returns {object} Data {inside, rect, localX, localY}.
     */
    static point_inside_element_data(x, y, dom_element) {
        let rect = H_dom.get_global_rect(dom_element);
        let inside = (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom);
        let localX = x - rect.left;
        let localY = y - rect.top;

        return { 'inside': inside, 'rect': rect, 'localX': localX, 'localY': localY };
    }
    // set the position of the element in page coordinates
        /**
     * Sets the global position of an element in page coordinates.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {number} x - X coordinate.
     * @param {number} y - Y coordinate.
     */
    static set_global_position(dom_element, x, y) {
        let parent_element = dom_element.parentNode;

        let parent_style = H_dom.get_style(parent_element);
        let style = H_dom.get_style(dom_element);

        // debugger;
        if (style.POS == 'fixed'){
            H_dom.set_position(dom_element, x, y);
            return;
        }

        // if (style.POS != 'fixed') {
        if (parent_style.POS == 'relative') {
            x -= parent_style.rect.left + parent_style.BL + style.ML;
            y -= parent_style.rect.top + parent_style.BT + style.MT;
        } else {
            let t = '';
            let sx = window.scrollX || (((t = document.documentElement) || (t = document.body.parentNode)) && typeof t.scrollLeft == 'number' ? t : document.body).scrollLeft || 0;
            let sy = window.scrollY || (((t = document.documentElement) || (t = document.body.parentNode)) && typeof t.scrollTop == 'number' ? t : document.body).scrollTop || 0;
            x -= sx;
            y -= sy;
        }
        // }

        if (style.POS == 'relative') {

            H_dom.set_position(dom_element, 0, 0);
            let new_style = H_dom.get_style(dom_element);

            x -= new_style.rect.x - (parent_style.rect.left + parent_style.BL);
            y -= new_style.rect.y - (parent_style.rect.top + parent_style.BT);
        }

        while (parent_element.tagName != 'BODY') {
            if (parent_element.scrollTop > 0) {
                y += parent_element.scrollTop;
            }
            if (parent_element.scrollLeft > 0) {
                x += parent_element.scrollLeft;
            }
            parent_element = parent_element.parentNode;
        }
        H_dom.set_position(dom_element, x, y);
    }
    
    /**
     * Sets the global left position of an element.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_left - New left position.
     */
    static set_global_left(dom_element, new_left) {
        let style = H_dom.get_style(dom_element);
        let offset = new_left - style.rect.left;

        let parent_element = dom_element.parentNode;

        parent_element._rect_ready = false;
        let parent_style = H_dom.get_style(parent_element);

        if (style.POS != 'fixed') {
            new_left -= parent_style.rect.left + parent_style.BL + style.ML;
        } else {
            let t = '';
            let sx = window.scrollX || (((t = document.documentElement) || (t = document.body.parentNode)) && typeof t.scrollLeft == 'number' ? t : document.body).scrollLeft || 0;
            new_left -= sx;
            console.log(new_left);
        }

        if (style.POS == 'relative') {

            H_dom.set_position(dom_element, 0, parseInt(dom_element.style.top));
            let new_style = H_dom.get_style(dom_element);

            new_left -= new_style.rect.x - (parent_style.rect.left + parent_style.BL) - style.ML;
            console.log(new_left);

        }

        let new_width = style.rect.width - offset;

        if (style.POS != 'fixed') {

            H_dom.set_global_width(dom_element, new_width);
        }

        H_dom.set_rect_dirty(dom_element);
    }
   
    /**
     * Sets the global top position of an element.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_top - New top position.
     */
    static set_global_top(dom_element, new_top) {

        let style = H_dom.get_style(dom_element);
        let offset = new_top - style.rect.top;

        let parent_element = dom_element.parentNode;

        let parent_style = H_dom.get_style(parent_element);

        if (style.POS != 'fixed') {
            new_top -= parent_style.rect.top + parent_style.BT + style.MT;
        } else {
            let t = '';
            let sy = window.scrollY || (((t = document.documentElement) || (t = document.body.parentNode)) && typeof t.scrollTop == 'number' ? t : document.body).scrollTop || 0;
            new_top -= sy;
        }

        if (style.POS == 'relative') {
            H_dom.set_position(dom_element, parseInt(dom_element.style.left), 0);
            let new_style = H_dom.get_style(dom_element);

            new_top -= new_style.rect.y - (parent_style.rect.top + parent_style.BT) - style.MT;
        }

        let new_height = style.rect.height - offset;

        if (style.POS != 'fixed') {
            H_dom.set_global_height(dom_element, new_height);
        }


        H_dom.set_rect_dirty(dom_element);
    }
    /**
     * Sets the global right position of an element.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_right - New right position.
     */
    static set_global_right(dom_element, new_right) {
        let style = H_dom.get_style(dom_element);
        let offset = new_right - style.rect.right;

        let new_width = style.rect.width + offset;

        H_dom.set_global_width(dom_element, new_width);
        H_dom.set_rect_dirty(dom_element);
    }
    /**
     * Sets the global bottom position of an element.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_bottom - New bottom position.
     */
    static set_global_bottom(dom_element, new_bottom) {
        let style = H_dom.get_style(dom_element);
        let offset = new_bottom - style.rect.bottom;

        let new_height = style.rect.height + offset;
        H_dom.set_global_height(dom_element, new_height);
        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the global width of an element, accounting for border and padding.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_width - New width in px.
     */
    static set_global_width(dom_element, new_width) {
        let style = H_dom.get_style(dom_element);

        new_width -= style.BH + style.PH;

        if (style.POS == 'relative') {
            switch (style._width_unit) {
                case 'percent':
                    let parent_style = H_dom.get_style(dom_element.parentNode);
                    let percent = (new_width / parseFloat(parent_style.all.width)) * 100;
                    // correction of rounding errors
                    if (percent % 1 < 0.08) {
                        percent = Math.round(percent * 10) / 10;
                    }
                    dom_element.style.setProperty('width', Math.min(100, percent) + '%');
                    break;

                case 'rem':
                    let rem_width = H_dom.px_to_em(new_width);
                    dom_element.style.setProperty('width', rem_width + 'rem');
                    break;

                case 'em':
                    let em_width = H_dom.px_to_em(new_width);
                    dom_element.style.setProperty('width', em_width + 'em');
                    break;

                case '':
                case 'auto':
                case 'max-content':
                case 'min-content':
                case 'fit-content':
                case 'stretch':
                case 'unset':
                case 'inherit':
                    dom_element.style.setProperty('width', style._width_unit);
                    break;

                case 'px':
                    dom_element.style.setProperty('width', new_width + 'px');
                    break;

                default:
                    dom_element.style.setProperty('width', new_width + 'px');
                    break;
            }
        } else {
            dom_element.style.setProperty('width', new_width + 'px');
        }

        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the global min-width of an element.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_width - New min-width in px.
     */
    static set_global_min_width(dom_element, new_width) {
        let style = H_dom.get_style(dom_element);

        switch (style._min_width_unit) {
            case 'percent':
                let parent_style = H_dom.get_style(dom_element.parentNode);
                let percent = (new_width / parseFloat(parent_style.all.width)) * 100;
                dom_element.style.setProperty('min-width', Math.min(100, percent) + '%');
                break;

            case 'rem':
                let rem_width = H_dom.px_to_em(new_width);
                dom_element.style.setProperty('min-width', rem_width + 'rem');
                break;

            case 'em':
                let em_width = H_dom.px_to_em(new_width);
                dom_element.style.setProperty('min-width', em_width + 'em');
                break;

            case 'auto':
                dom_element.style.setProperty('min-width', 'auto');
                break;

            case 'px':
                dom_element.style.setProperty('min-width', new_width + 'px');
                break;

            default:
                dom_element.style.setProperty('min-width', new_width + 'px');
                break;
        }


        H_dom.set_rect_dirty(dom_element);
    }
    /**
     * Sets the global height of an element, accounting for border and padding.
     * 
     * @param {HTMLElement} dom_element - The element.
     * @param {number} new_height - New height in px.
     */
    static set_global_height(dom_element, new_height) {

        let style = H_dom.get_style(dom_element);

        if (new_height !== undefined && new_height != '') {
            new_height -= style.BV + style.PV;
            dom_element.style.setProperty('height', new_height + 'px');
        } else {
            dom_element.style.setProperty('height', '');
        }

        H_dom.set_rect_dirty(dom_element);
    }
    /**
     * Sets the global rect (left, top, right, bottom) of an element.
     * @param {HTMLElement|object} dom_element - The element or rect object.
     * @param {number} left - Left position.
     * @param {number} top - Top position.
     * @param {number} right - Right position.
     * @param {number} bottom - Bottom position.
     */
    static set_global_rect(dom_element, left, top, right, bottom) {

        if (H_generics.is_object(left)) {
            top = left.top;
            right = left.right;
            bottom = left.bottom;
            left = left.left;
        }

        H_dom.set_global_left(dom_element, left);
        H_dom.set_global_top(dom_element, top);

        H_dom.set_global_right(dom_element, right);
        H_dom.set_global_bottom(dom_element, bottom);

        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the width of an element in local coordinates.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} w - Width in px.
     */
    static set_width(dom_element, w) {
        dom_element.style.setProperty('width', w + 'px');
        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the height of an element in local coordinates.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {number} h - Height in px.
     */
    static set_height(dom_element, h) {
        dom_element.style.setProperty('height', h + 'px');
        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the left position of an element in local coordinates.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} x - Left position in px.
     */
    static set_left(dom_element, x) {
        dom_element.style.setProperty('left', x + 'px');
        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the top position of an element in local coordinates.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} y - Top position in px.
     */
    static set_top(dom_element, y) {
        dom_element.style.setProperty('top', y + 'px');
        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Sets the width and height of an element in local coordinates.
     * @param {HTMLElement} dom_element - The element.
     * @param {number} w - Width in px.
     * @param {number} h - Height in px.
     */
    static set_size(dom_element, w, h) {
        dom_element.style.setProperty('width', w + 'px');
        dom_element.style.setProperty('height', h + 'px');

        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Gets the global width of an element.
     * @param {HTMLElement} dom_element - The element.
     * @returns {number} Width in px.
     */
    static get_global_width(dom_element) {
        if (!dom_element._rect_ready) {
            dom_element._computed_style.rect = H_dom.get_global_rect(dom_element);
        }

        return dom_element._computed_style.rect.width;
    }

    /**
     * Gets the global height of an element.
     * @param {HTMLElement} dom_element - The element.
     * @returns {number} Height in px.
     */
    static get_global_height(dom_element) {
        if (!dom_element._rect_ready) {
            dom_element._computed_style.rect = H_dom.get_global_rect(dom_element);
        }

        return dom_element._computed_style.rect.height;
    }

    /**
     * Sets the position of an element in local coordinates.<br>
     * use H_dom.RELATIVE or H_dom.ABSOLUTE for specify the css_position parameters
     * @param {HTMLElement} dom_element - The element.
     * @param {number|object} x - X position or {x, y}.
     * @param {number} y - Y position.
     * @param {string} [css_position] - CSS position value.
     */
    static set_position(dom_element, x, y, css_position) {
        if (css_position !== undefined) {
            dom_element.style.position = css_position; // relative or absolute
        }

        if (H_generics.is_object(x) && x.x) {
            y = x.y;
            x = x.x;
        }

        if (x !== undefined) {
            dom_element.style.left = Math.round(x) + 'px';

        }
        if (y !== undefined) {
            dom_element.style.top = Math.round(y) + 'px';
        }

        H_dom.set_rect_dirty(dom_element);
    }
    /**
     * Sets the alignment of an element within its parent or window.<br>
     * horizontal and vertical are factor from 0 (left) to 1(right) 
     * @param {HTMLElement} dom_element - The element.
     * @param {number} horizontal - Horizontal alignment (0..1).
     * @param {number} vertical - Vertical alignment (0..1).
     * @param {HTMLElement} parent_node - Parent node.
     * @param {boolean} [from_window=false] - Use window as reference.
     */
    static set_alignment(dom_element, horizontal, vertical, parent_node, from_window = false) {
        let parent_rect;
        if (from_window) {
            parent_rect = { 'left': 0, 'top': 0, 'right': 0, 'bottom': 0, 'width': window.innerWidth, 'height': window.innerHeight };
        } else {
            if (!H_generics.is_object(parent_node)) parent_node = dom_element.parentNode;
            parent_rect = H_dom.get_global_rect(parent_node, true);
        }
        let rect = H_dom.get_global_rect(dom_element, true);
        let pos = H_dom.get_style_value(dom_element, 'position');

        if (parent_node.tagName == 'BODY' && pos == 'fixed') parent_rect = { 'left': 0, 'top': 0, 'right': 0, 'bottom': 0, 'width': window.innerWidth, 'height': window.innerHeight };

        let left = parent_rect.left + (parent_rect.width - rect.width) * horizontal;
        let top = parent_rect.top + (parent_rect.height - rect.height) * vertical;

        H_dom.set_global_position(dom_element, left, top);

        let style = H_dom.get_style(dom_element);

        H_dom.set_rect_dirty(dom_element);
    }

    /**
     * Gets a style value for an element.
     * @param {HTMLElement} dom_element - The element.
     * @param {string} attribute_name - CSS attribute.
     * @returns {string|undefined} Value.
     */
    static get_style_value(dom_element, attribute_name) {
        if (dom_element.style[attribute_name]) {
            return dom_element.style[attribute_name];
        }
        let style = H_dom.get_style(dom_element);
        let value = style.all.getPropertyValue(attribute_name) || undefined;
        return value;
    }

    /**
     * Returns the computed styles for an element.
     * @param {HTMLElement} dom_element - The element.
     * @returns {object} Computed style object.
     */
    static get_style(dom_element) {

        if (!dom_element) {
            debugger;
        }

        if (dom_element === document) {
            dom_element = dom_element.body;
        }

        if (!dom_element._computed_style_ready) {
            if (H_dom.debug) {
                console.log('recompute style for ', dom_element);
            }

            if (!dom_element._computed_style) {
                dom_element._computed_style = {};
            }

            if (!dom_element._rect_ready) {
                dom_element._computed_style.rect = H_dom.get_global_rect(dom_element, true);
            }

            let style = window.getComputedStyle(dom_element, '');

            dom_element._computed_style.all = style;

            dom_element._computed_style.BL = parseInt(style.getPropertyValue('border-left-width')) || 0;
            dom_element._computed_style.BT = parseInt(style.getPropertyValue('border-top-width')) || 0;
            dom_element._computed_style.BR = parseInt(style.getPropertyValue('border-right-width')) || 0;
            dom_element._computed_style.BB = parseInt(style.getPropertyValue('border-bottom-width')) || 0;

            dom_element._computed_style.BH = dom_element._computed_style.BL + dom_element._computed_style.BR;
            dom_element._computed_style.BV = dom_element._computed_style.BB + dom_element._computed_style.BT;

            dom_element._computed_style.PL = parseInt(style.getPropertyValue('padding-left')) || 0;
            dom_element._computed_style.PT = parseInt(style.getPropertyValue('padding-top')) || 0;
            dom_element._computed_style.PR = parseInt(style.getPropertyValue('padding-right')) || 0;
            dom_element._computed_style.PB = parseInt(style.getPropertyValue('padding-bottom')) || 0;

            dom_element._computed_style.PH = dom_element._computed_style.PL + dom_element._computed_style.PR;
            dom_element._computed_style.PV = dom_element._computed_style.PB + dom_element._computed_style.PT;

            dom_element._computed_style.ML = parseInt(style.getPropertyValue('margin-left')) || 0;
            dom_element._computed_style.MT = parseInt(style.getPropertyValue('margin-top')) || 0;
            dom_element._computed_style.MR = parseInt(style.getPropertyValue('margin-right')) || 0;
            dom_element._computed_style.MB = parseInt(style.getPropertyValue('margin-bottom')) || 0;

            dom_element._computed_style.MH = dom_element._computed_style.ML + dom_element._computed_style.MR;
            dom_element._computed_style.MV = dom_element._computed_style.MB + dom_element._computed_style.MT;

            dom_element._computed_style.POS = style.getPropertyValue('position') || 'relative';

            dom_element._computed_style_ready = true;
            dom_element._rect_ready = true;
        }

        if (!dom_element._rect_ready) {
            dom_element._computed_style.rect = H_dom.get_global_rect(dom_element);
        }

        if (!dom_element._computed_style._width_unit) {
            let ww = dom_element.style.width;
            if (ww.indexOf('px') > 0) {
                dom_element._computed_style._width_unit = 'px';
            } else if (ww.indexOf('%') > 0) {
                dom_element._computed_style._width_unit = 'percent';

            } else if (ww.indexOf('rem') > 0) {
                dom_element._computed_style._width_unit = 'rem';

            } else if (ww.indexOf('em') > 0) {
                dom_element._computed_style._width_unit = 'em';

            } else {
                dom_element._computed_style._width_unit = 'auto';
            }
        }

        if (!dom_element._computed_style._min_width_unit) {

            let ww = dom_element.style.minWidth;

            if (ww.indexOf('px') > 0) {
                dom_element._computed_style._min_width_unit = 'px';
            } else if (ww.indexOf('%') > 0) {
                dom_element._computed_style._min_width_unit = 'percent';

            } else if (ww.indexOf('rem') > 0) {
                dom_element._computed_style._min_width_unit = 'rem';

            } else if (ww.indexOf('em') > 0) {
                dom_element._computed_style._min_width_unit = 'em';

            } else {
                dom_element._computed_style._min_width_unit = 'rem';
            }
        }


        if (!dom_element._computed_style._align) {
            dom_element._computed_style._align = '';
        }

        return dom_element._computed_style;
    }
    /**
     * Marks the style of an element as dirty for recomputation.<br>
     * @param {HTMLElement} dom_element - The element.
     */
    static set_style_dirty(dom_element) {
        dom_element._computed_style_ready = false;
    }

    /**
     * Marks only the rect of an element and its children as dirty and to recompute.
     */
    static set_rect_dirty(dom_element) {
        dom_element._rect_ready = false;
        for (let i = 0; i < dom_element.children.length; i++) {
            H_dom.set_rect_dirty(dom_element.children[i]);
        }
    }

     /**
     * Sets a style value for an element and marks as dirty if changed.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {string} attribute_name - CSS attribute.
     * @param {string} value - Value to set.
     * @returns {boolean} True if changed.
     */   
    static set_style_value(dom_element, attribute_name, value) {

        let style = H_dom.get_style(dom_element);

        let original_value = style.all.getPropertyValue(attribute_name);

        if (original_value != value || dom_element.style[attribute_name] != value) {

            dom_element.style.setProperty(attribute_name, value);
            H_dom.set_style_dirty(dom_element);

            if (attribute_name == 'display') {
                H_dom.set_rect_dirty(dom_element);
            }
            return true;
        }
        return false;
    }
    
    /**
     * Loads a CSS file into the document head if not already present.<br>
     * of course the url must respect the CORS rules..
     * @param {string} url - CSS file URL.
     * @returns {boolean} True if loaded, false if already present.
     */
    static load_css(url) {
        let styles = document.head.getElementsByTagName("link");

        for (let i = 0; i < styles.length; i++) {
            let s = styles[i];
            if (s.getAttribute('href') == url) {
                if (H_dom.debug) {
                    console.log('style ', url, ' already in header');
                }
                return false;
            }
        }

        let style_sheet_element = H_dom.create_element('LINK');

        style_sheet_element.onload = function () {
            if (H_dom.debug) {
                console.log(url, 'loaded');
            }
        };

        style_sheet_element.setAttribute('type', 'text/css');
        style_sheet_element.setAttribute('rel', 'style_sheet');
        style_sheet_element.setAttribute('href', url);

        document.head.appendChild(style_sheet_element);

        return true;
    }

    /**
     * Gets the cssRulesList of a stylesheet or CSSMediaRule.<br>
     * Both implement insertRule and removeRule methods.
     * @param {int} sheet_id - Stylesheet ID.
     * @param {object} sheet_media - Media condition.
     * @returns {object|false} CSS stylesheet or rule.
     */
    static get_css_rules(sheet_id, sheet_media) {
        if (!document.styleSheets || document.styleSheets.length === 0) {
            return false;
        }

        let style_sheet = false;
        for (let i = 0; i < document.styleSheets.length; i++) {
            if (sheet_id && document.styleSheets[i].ownerNode.id == sheet_id) {
                style_sheet = document.styleSheets[i];
            }
        }

        if (sheet_media && style_sheet) {
            for (let rule of style_sheet.cssRules) {
                if (rule.constructor.name != 'CSSMediaRule') continue;

                if (rule.conditionText == sheet_media) {
                    style_sheet = rule;
                }
            }
        }

        return style_sheet;
    }

    /**
     * Adds a CSS rule to a stylesheet.<br>
     * @param {int} sheet_id - Stylesheet ID.
     * @param {string} selector - CSS selector.
     * @param {string} style - CSS style.
     * @param {object} sheet_media - Media condition.
     * @returns {number|false} Rule index or false.
     */
    static add_css_rule(sheet_id, selector, style, sheet_media) {

        let rules = H_dom.get_css_rules(sheet_id, sheet_media);
        if (!rules) return false;

        let css = selector + '{' + style + '}';
        let index = rules.insertRule(css);

        return index;
    }

    /**
     * Removes a CSS rule from a stylesheet.
     * @param {int} sheet_id - Stylesheet ID.
     * @param {string} ruleName - Selector name.
     * @param {object} sheet_media - Media condition.
     * @returns {boolean} True if removed.
     */
    static remove_css_rule(sheet_id, ruleName, sheet_media) {

        let rules = H_dom.get_css_rules(sheet_id, sheet_media);
        if (!rules) return false;

        for (let i = 0; i < rules.cssRules.length; i++) {
            let rule = rules.cssRules[i];
            if (rule.constructor.name == 'CSSStyleRule' && rule.selectorText == ruleName) {
                rules.deleteRule(i);
                return true;
            }
        }

        return false;
    }
    /**
     * Disables a CSS stylesheet by ID.
     * @param {int} sheet_id - Stylesheet ID.
     * @returns {boolean} True if disabled.
     */
    static disable_css_sheet(sheet_id) {
        if (!sheet_id) {
            return false;
        }

        if (document.styleSheets) {
            for (let i = 0; i < document.styleSheets.length; i++) {
                let style_sheet = document.styleSheets[i];

                if (style_sheet.ownerNode.id == sheet_id) {
                    style_sheet.disabled = true;
                    style_sheet.ownerNode.setAttribute('disabled', 'disabled');
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Enables a CSS stylesheet by ID.
     * @param {int} sheet_id - Stylesheet ID.
     * @returns {boolean} True if enabled.
     */
    static enable_css_sheet(sheet_id) {

        if (!sheet_id) {
            return false;
        }

        if (document.styleSheets) {
            for (let i = 0; i < document.styleSheets.length; i++) {
                let style_sheet = document.styleSheets[i];

                if (style_sheet.ownerNode.id == sheet_id) {
                    style_sheet.disabled = false;
                    style_sheet.ownerNode.removeAttribute('disabled');
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Sets the global cursor style for the page.
     * @param {string} cursor_value - CSS cursor value.
     * @returns {boolean} False if already set.
     */
    static set_global_cursor(cursor_value) {
        if (H_dom.current_global_cursor == cursor_value) {
            return false;
        }
        H_dom.current_global_cursor = cursor_value;
        H_dom.add_css_rule('temp_cursor', '*', 'cursor:' + cursor_value + ' !important;');
        H_dom.enable_css_sheet('temp_cursor');
    }

    /**
     * Unsets the global cursor style.
     */
    static unset_global_cursor() {
        H_dom.current_global_cursor = '';
        H_dom.disable_css_sheet('temp_cursor');
    }

    /**
     * Disables mouse events on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     */
    static disable_mouse_events(dom_element) {
        if (dom_element && !dom_element._firstPointerEvents) {
            dom_element._originalPointerEvents = dom_element.style.pointerEvents;
            dom_element._firstPointerEvents = true;
        }

        dom_element.style.pointerEvents = 'none';
    }

    /**
     * Enables mouse events on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     */
    static enable_mouse_events(dom_element) {
        if (dom_element && !dom_element._firstPointerEvents) {
            dom_element._originalPointerEvents = dom_element.style.pointerEvents;
            dom_element._firstPointerEvents = true;
        }

        dom_element.style.pointerEvents = 'auto';
    }

    /**
     * Restores the original mouse events on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     */
    static restore_mouse_events(dom_element) {
        //if(!dom_element) debugger;

        if (dom_element && dom_element._firstPointerEvents) {
            dom_element._firstPointerEvents = false;
            dom_element.style.pointerEvents = dom_element._originalPointerEvents;
        }

    }

    /**
     * Moves a DOM element to the front by setting a high z-index.
     * @param {HTMLElement} dom_element - The element.
     */
    static move_to_front(dom_element) {

        if (dom_element && !dom_element._firstZindex) {
            dom_element._originalZindex = dom_element.style.zIndex;
            dom_element._firstZindex = true;
        }

        dom_element.style.zIndex = 99999;
    }

    /**
     * Restores the original z-index of a DOM element.
     * @param {HTMLElement} dom_element - The element.
     */
    static restore_zindex(dom_element) {

        if (dom_element && dom_element._firstZindex) {
            dom_element._firstZindex = false;
            dom_element.style.zIndex = dom_element._originalZindex;
        }
    }

    /**
     * Gets the value of 1em in pixels for a parent node.
     * @param {HTMLElement} parent_node - Parent node.
     * @returns {number} Value of 1em in px.
     */
    static get_em_unit_value(parent_node) {
        if (!parent_node) {
            parent_node = document.body;
        }

        if (!H_dom._EMscopeTester) {
            H_dom._EMscopeTester = document.createElement('DIV');
            H_dom._EMscopeTester.style = 'display: block; font-size: 1em; margin: 0; padding:0; height: auto; line-height: 1; border:0;';
            H_dom._EMscopeTester.innerHTML = '&nbsp;';
        }

        parent_node.appendChild(H_dom._EMscopeTester);

        let scope_val = H_dom._EMscopeTester.offsetHeight;
        H_dom._EMscopeTester.remove();

        return parseFloat(scope_val);
    }
    
    /**
     * Converts a pixel value to em units.<br>
     * @param {number} px_value - Pixel value.
     * @param {HTMLElement} parent_node - Parent node.
     * @param {boolean} add_em_suffix - Add 'rem' suffix.
     * @returns {number|string} Value in em/rem.
     */
    static px_to_em(px_value, parent_node, add_em_suffix) {

        if (!parent_node || (px_value + '').toLowerCase().indexOf("rem") >= 0) {
            parent_node = document.body;
        }

        let em_scale = H_dom.get_em_unit_value(parent_node);

        return (parseFloat(px_value) / em_scale) + (add_em_suffix ? 'rem' : 0);
    }
}

var h = h || {};
h.libs = h.libs || {};
h.libs.dom = H_dom;
window.H_dom = H_dom;