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

var h = h || {};
h.libs = h.libs || {};

/**
 * @class H_ui
 * @classdesc
 * Utility class for complex UI objects and methods not related to a single DOM element (see dom.js for that).<br>
 * Provides static methods for color pickers, modals, popups, accordions, tooltips, clipboard, and more.<br>
 * H_ui contains tons of little utils and UI tools, and when a widget become too much complex, it has become a subclass, like H_ui_tabs, H_ui_autocomplete etc...<br>
 * those subclasses are most of the time the controler conterpart of H.php widgets and initialized by them.<br>
 * Used as h.libs.ui.
 */
class H_ui {


    static input_order_list = {};

    static popup_modal = false;
    static popup_special = [];

    // static modal_key_down_handler = [];
    // static modal_key_down_handler_max_level = 1;
    // static modal_err = false;

    constructor() {}

    // color picker related variables and function 
    static color_pickers = [];
    /**
     * Displays a color picker for a given input.<br>
     * Initializes and manages the color picker instance.<br>
     * the colorpicker source in in /js/externals/alwan<br>
     * as there is already tons of colopicker, we choose this one : https://github.com/sefianecho/alwan<br>
     * 
     * @param {Event} evt - The triggering event.
     * @param {string} dom_id - The DOM id of the input.
     */
    static display_color_picker(evt, dom_id) {
        let target = evt.target;

        let input = document.getElementById(dom_id);
        if (!input) {
            console.log('can\'t find the target with dom_id', dom_id);
            return;
        }

        let current_color = input.value;

        if (!target._colorpicker) {
            target._colorpicker = new Alwan('#' + dom_id + 'picker', {
                'preset': false,
                'format': 'hex',
                'color': current_color,
                'theme': 'dark',
                'target': target,
                'swatches': [current_color], // array of colors that saved the previous color of this picker
                'position': 'bottom-start'
            });
            target._colorpicker.on('color', function (evt) {
                target.firstElementChild.textContent = evt.hex;
                target.style.backgroundColor = evt.hex;
            });
            target._colorpicker.on('open', function (evt) {
                if (!target._colorpicker_overlay) {
                    target._colorpicker_overlay = H_dom.create_element('DIV', {class: 'hlp_colorpicker_overlay'});
                    H_dom.append_content(document.body, target._colorpicker_overlay);
                }

                H_dom.remove_class(target._colorpicker_overlay, 'hidden');
            });
            target._colorpicker.on('close', function (evt) {
                let hex = evt.hex;

                // add the new color to the swatches
                // need to be done first because when adding to the swatches there is a bug that modify a little bit the hex value of the colorPicker
                if (!evt.source.config.swatches.includes(hex)) {
                    evt.source.addSwatches(hex);
                }
                input.value = hex;
                target.firstElementChild.textContent = hex;
                target.style.backgroundColor = hex;
                if (evt.source.config.swatches.length > 7) evt.source.removeSwatches(0);

                H_dom.add_class(target._colorpicker_overlay, 'hidden');
            });
            H_ui.color_pickers.push(target._colorpicker);
        }
        if (!target._colorpicker.isOpen()) {
            target._colorpicker.open();
        }

        H_ui.clean_color_pickers();
    }
    /**
     * Cleans up unused color pickers.<br>
     * Destroys pickers that are no longer open or present in the DOM.
     */
    static clean_color_pickers() {
        H_ui.color_pickers.forEach((picker, key) => {
            if (!picker.isOpen) return;
            if (!picker.isOpen() && !document.getElementById(picker.config.target.id)) {
                picker.destroy();
                H_ui.color_pickers.splice(key, 1);
            }
        });
    }

    /**
     * Initializes an accordion UI component.<br>
     * Adds toggling and indicator logic to elements with the given class.<br>
     * The target must have "data-target_id" that points to the element to toggle
     * @param {string} target - CSS class of toggler elements.
     * @param {string} css_class - CSS class to toggle.
     * @param {Function|boolean} [custom_func=false] - Custom function on open.
     * @param {string|boolean} [indicator=false] - Id of the collapse indicator
     */
    static toggle_accordion(target, css_class, custom_func = false, indicator = false) {
        var elems = document.getElementsByClassName(target);
        for (let i = 0; i < elems.length; i++) {
            var elem = elems[i];
            // add class 
            H_dom.add_class(elem, 'hlp_accordion_toggler');
            let indic = false;
            if (!indicator) {
                indic = elem.previousElementSibling;
            } else {
                indic = document.getElementById(indicator);
            }
            if (!h.e.has_event(elem, H_event.EVENT_CLICK)) {
                h.e.add_event(elem, H_event.EVENT_CLICK, on_click);
            }
            if (indic && !h.e.has_event(indic, H_event.EVENT_CLICK)) {
                indic.dataset.target = '1';
                h.e.add_event(indic, H_event.EVENT_CLICK, on_click);
            }
        }

        function on_click(e) {
            let elem = e.target;
            if (elem.dataset.target) {
                elem = elem.nextElementSibling;
            }
            var targ = document.getElementById(elem.dataset.target_id);
            H_dom.toggle_class(targ, css_class);
            var indicator = elem.previousElementSibling;
            if (!indicator) {
                indicator = document.getElementById(indicator);
            }
            if (indicator) {
                if (indicator.textContent.includes('►')) {
                    indicator.textContent = '▼';
                    if (custom_func) {
                        custom_func(e);
                    }
                } else {
                    indicator.textContent = '►';
                }
            }
        }
    }
    /**
     * Updates the selected state for multi-radio blocks.<br>
     * Toggles the 'selected' class on labels.
     * @param {Event} event - The change event.
     */
    static change_multi_radio(event) {
        let targ = event.target;
        let parent = targ.parentElement;
        if (!parent.classList.contains('multi_radio_block')) {
            parent = parent.parentElement;
        }
        let inputs = parent.querySelectorAll('[name="' + targ.name + '"]');
        inputs.forEach((inp) => {
            let lab = parent.querySelector('[for="' + inp.id + '"]');
            H_dom.toggle_class(lab, 'selected', inp.checked);
        });
    }

    /**
     * Displays a big image overlay.<br>
     * Creates and shows a modal for the image.
     * @param {Event} evt - The triggering event.
     * @param {string|boolean} [new_url=false] - Optional new image URL.
     */
    static show_big_image(evt, new_url = false) {
        let url = document.baseURI;
        if (evt && url.replace(H_constants.base_url, '').split('/').length == 1) {
            let targ = evt.target;

            // make container big image
            let big = document.getElementById('hlp_container_big_image');
            if (!big) {
                big = H_dom.create_element('DIV', { 'class': 'hlp_container_big_image', 'id': 'hlp_container_big_image' });
                let div = H_dom.create_element('DIV');
                let img = H_dom.create_element('IMG', { 'class': 'hlp_big_image' });
                let croix = H_dom.create_element('DIV', { 'class': 'galerie_close' });
                H_dom.append_content(div, img);
                H_dom.append_content(div, croix);
                H_dom.append_content(big, div);
                H_dom.append_content(document.body, big);

                h.e.add_event_click(croix, close_big_image);
            }
            let img = big.firstElementChild.firstElementChild;
            if (new_url) img.src = new_url;
            else img.src = targ.src;

            // make overlay
            let overlay = document.getElementById('hlp_overlay_big_image');
            if (!overlay) {
                overlay = H_dom.create_element('DIV', { 'class': 'hlp_overlay_big_image', 'id': 'hlp_overlay_big_image' });
                H_dom.append_content(document.body, overlay);
            }
            H_dom.toggle_class(big, 'hidden', false);
            H_dom.toggle_class(overlay, 'hidden', false);

            h.e.add_event_click(overlay, close_big_image);

            function close_big_image(evt) {
                H_dom.toggle_class(big, 'hidden', true);
                H_dom.toggle_class(overlay, 'hidden', true);
            }
        }
        return;
    }

    /**
     * Adds or removes a CSS class on scroll.<br>
     * Toggles the class when scrolling past a threshold.
     * @param {string} elem_id - Target element id.
     * @param {number} nbr_px - Scroll threshold in pixels.
     * @param {string} class_name - CSS class to toggle.
     */
    static scroll_toggle_class(elem_id, nbr_px, class_name) {
        h.e.add_event(window, 'scroll', function (event) {
            let elem = document.getElementById(elem_id);
            if (elem) {
                let py = event.pageY || document.body.scrollTop || window.pageYOffset;
                if (py > nbr_px && !H_dom.has_class(elem, class_name)) {
                    H_dom.toggle_class(elem, class_name, true);
                }
                if (py <= nbr_px && H_dom.has_class(elem, class_name)) {
                    H_dom.toggle_class(elem, class_name, false);
                }
            }
        });
    }
    /**
     * Scrolls to a DOM element with an optional offset.<br>
     * @param {string} dom_id - Target element id.
     * @param {number} [offset=0] - Offset in pixels.
     */
    static scroll_to(dom_id, offset = 0) {
        let dom_elem = document.getElementById(dom_id);
        let rect = H_dom.get_global_rect(dom_elem, true);

        let top = Math.round(rect.top - offset);

        window.scrollTo(0, top);
    }

    /**
     * Retrieves a token via AJAX and inserts it into the form.<br>
     * this token will permit to identify one authorized submit, and reduce DDOS risk.
     * @param {Event} evt - The triggering event.
     * @param {boolean} [from_form=false] - Whether the event is from a form.
     */
    static get_token(evt, from_form = false) {

        let form = (from_form) ? evt.target : evt.target.form;
        let settings = {};
        settings.url = form.action;
        settings.skip_container = true;
        settings.dom_target = '';
        settings.data = {
            'action': 'formgettoken'
        };
        settings.success = function (res) {
            if (res) {
                let id = form.id + '-token';
                let inp_hidden = document.getElementById(id);
                if (inp_hidden) {
                    inp_hidden.value = res.trim();
                } else {
                    inp_hidden = H_dom.create_element('INPUT', { 'id': id, 'type': 'hidden', 'name': 'js_token', 'value': res.trim(), 'data-alwaysposted': 1 });
                }
                H_dom.append_content(form, inp_hidden);
                h.e.send_event(form, 'submit');
            }
        };
        h.a.send(settings);
    }

    /**
     * Opens a popup window.<br>
     * @param {string} lapage - URL to open.
     * @param {string} titre - Window title.
     * @param {number} w - Width.
     * @param {number} h - Height.
     * @param {boolean|number} scroll - Enable scrollbars.
     */
    static popup(lapage, titre, w, h, scroll) {
        var sx = screen.availWidth;
        var sy = screen.availHeight;

        if (String(scroll) == "undefined") scroll = 1;

        if (String(w) != "undefined" && String(h) == "undefined") {
            var h = Math.floor(sy * w / 100);
            var w = Math.floor(sx * w / 100);
        }

        if (h < 2) { h = sy * h; }
        if (w < 2) { w = sx * w; }

        if (h > sy) h = sy - 64;
        if (w > sx) w = sx;

        titre = String(titre);
        if (titre == "undefined") titre = "_new";

        var px = ((sx - w) / 2);
        var py = ((sy - (h + 64)) / 2);

        var params = 'scrollbars=' + scroll + ',status=1,resizable=1,width=' + w + ',height=' + h + ',screenx=' + px + ',left=' + px + ',screeny=' + py + ',top=' + py;
        //titre="";
        open(String(lapage), String(titre), String(params));
        //~ open(String(lapage),String(titre));
        //~ top.opener.window.open('',String(titre));
    }

    /**
     * Opens a new browser tab.
     * @param {string} url - URL to open.
     */
    static new_tab(url) {
        params = '_blank';
        window.open(String(url), String(params));
    }

    /**
     * Initializes input order management for a group of inputs.
     * @param {string} base_name - Base name for the group.
     * @param {string} base_id - Base id for the input.
     * @param {boolean} editable - Whether the input is editable.
     * @param {Function} callback - Callback on order change.
     */
    static init_input_order(base_name, base_id, editable, callback) {
        if (!H_ui.input_order_list[base_name]) {
            H_ui.input_order_list[base_name] = new H_ui_inputs_order_manager(base_name);
        }
        H_ui.input_order_list[base_name].add_field(base_id, editable, callback);
    }

    /**
     * Opens a popup modal for a public module.<br>
     * Supports special modals (saved as special instances), with level of display priority (z-index).
     * @param {Event} evt - The triggering event.
     * @param {string} module_name - Module name.
     * @param {Object} params - Parameters for the module.
     * @param {boolean|number} [special=false] - Special modal index.
     * @param {number} [special_level=1] - Special modal level.
     */
    static open_popup_modal(evt, module_name, params, special = false, special_level = 1, title=false) {

        if (!module_name) {
            console.log('no module_name');
            return;
        }

        let is_public = false;
        if (module_name.includes('public/')) {
            module_name = module_name.replace('public/', '');
            is_public = true;
        }

        // waiting_to_resize = true;
        if (special !== false) {
            if (!H_ui.popup_special[special]) {
                H_ui.popup_special.push(special);
                H_ui.popup_special[special] = H_ui.add_window(document.body, {
                    nodrag: false,
                    hidden: true,
                    modal: true,
                    title: title,
                    class: 'special ' + module_name,
                    special: true,
                    special_level: special_level
                });
                H_ui.popup_special[special].dom_element.setAttribute('id', 'popup_special_' + special);
                H_ui.popup_special[special]._modal_content.setAttribute('id', 'popup_special_content_' + special);
            } else {
                H_ui.popup_special[special].dom_element.setAttribute('class', 'hlp_window special hidden ' + module_name);
            }
        } else {
            // if (!H_ui.popup_modal) {
            H_ui.popup_modal = H_ui.add_window(document.body, {
                nodrag: false,
                hidden: true,
                title: title,
                modal: true,
                class: module_name,
                remove_on_close: true
            });
            H_ui.popup_modal.dom_element.setAttribute('id', 'popup_modal');
            H_ui.popup_modal._modal_content.setAttribute('id', 'popup_modal_content');
            // } else {
            //     H_ui.popup_modal.dom_element.setAttribute('class', 'hlp_window hidden ' + module_name);
            // }
        }

        var url = H_constants.base_url;
        url += (is_public) ? 'public/' : H_constants.admin_folder;
        url += module_name + '/index.php';

        let settings = {};
        settings.url = url;
        settings.dom_target = '';
        settings.skip_container = true;
        if (params && H_generics.is_object(params)) settings.data = params;
        else settings.data = {};
        settings.success = function (res) {
            let mod = (special !== false) ? H_ui.popup_special[special] : H_ui.popup_modal;
            mod.set_content(res);
            mod.close = 'x';
            mod.show();
            mod.set_alignment(.5,.5);
        };
        h.a.send(settings);
    }

    /**
     * Cleans up TinyMCE auxiliary elements.
     * Removes all elements with class 'tox-tinymce-aux' left by tynimce.
     */
    static clean_tox() {
        let toxes = document.getElementsByClassName('tox-tinymce-aux');
        for(var i = toxes.length - 1; i >= 0; i--) {
            toxes[i].parentNode.removeChild(toxes[i]);
        }
    }

    /**
     * Adds a new window (modal/dialog) to the UI.
     * @param {HTMLElement} parent_node - Parent node to append to.
     * @param {Object} settings - Window settings
     * @param {String|Array|HTMLElement} settings.title Add a title on top
     * @param {String} settings.class Additional CSS classes for the modal
     * @param {Boolean} settings.modal Display as modal with background mask
     * @param {Boolean|String} settings.close Label for the close button, or true for default
     * @param {Function|String} settings.on_close Callback executed when closed
     * @param {Boolean} settings.hidden Hide it on create
     * @param {Boolean} settings.content_resizable Make the window partialy resizable. Depends its CSS, if there is min-width or min-height set
     * @param {Boolean} settings.remove_on_close Remove from DOM when closed
     * @param {Boolean} settings.nodrag Disable drag of whole modal
     * @param {Boolean} settings.disable_background_click Disable closing by clicking background and pressing ESC key
     * @param {Boolean} settings.disable_auto_resize Disable auto-resize/alignment on content change
     * @param {Boolean} settings.special special modal, higher z-index
     * @param {Number|String} settings.special_level Priority level for special modals, or 'err' for error modals
     * @returns {H_ui_window} The created window instance.
     */
    static add_window(parent_node, settings) {
        let win = new H_ui_window(settings);

        if (parent_node) {
            win.set_parent(parent_node);
        }

        return win;
    }

    /**
     * Displays a message popup with an OK button.
     * @param {string|Array} message - Message to display.
     * @param {Function} ok_handler - Handler for OK button.
     * @returns {H_ui_prompt} The prompt instance.
     */
    static message_popup(message, ok_handler) {
        let settings = {};

        if (H_generics.is_array(message)) message = message.join('<br>');

        settings.content = message;

        settings.buttons = [];
        settings.buttons.push({
            'label': H_constants.get_text('tlc_ok'),
            'css': 'btnokpopup',
            'handler': ok_handler,
            'keyPress': ['Enter', 'Escape']
        });

        let w = new H_ui_prompt(settings);
        w.set_parent(document.body, 0.5, 0.3);

        return w;
    }

    /**
     * Displays a timed message popup.
     * @param {string|Array} message - Message to display.
     * @param {Function} ok_handler - Handler for OK button.
     * @param {number} [time=3000] - Time in ms before auto-close.
     */
    static message_popup_timed(message, ok_handler, time = 3000) {

        let time_indicator = H_dom.create_element('DIV', {
            'class': 'hlp_modal_timer'
        });

        let w = H_ui.message_popup(message, ok_handler);
        w.dom_element.appendChild(time_indicator);

        setTimeout(() => {
            time_indicator.style.transition = 'width linear ' + time + 'ms';
            time_indicator.style.width = '100%';
            setTimeout(() => {
                w.clean();
            }, time);
        }, 100);

    }

    /**
     * Displays a confirmation popup with OK and Cancel buttons.
     * @param {string|Array} message - Message to display.
     * @param {Function} ok_handler - Handler for OK button.
     * @param {Function} cancel_handler - Handler for Cancel button.
     */
    static confirm_popup(message, ok_handler, cancel_handler) {
        let settings = {};

        if (H_generics.is_array(message)) message = message.join('<br>');
        settings.content = message;

        settings.buttons = [];
        settings.buttons.push({
            label: H_constants.get_text('tlc_confirm'),
            handler: ok_handler,
            keyPress: 'Enter',
            css: 'button_action'
        });
        settings.buttons.push({
            label: H_constants.get_text('tlc_annuler'),
            handler: cancel_handler,
            keyPress: 'Escape'
        });

        settings.remove_on_close = true;

        let w = new H_ui_prompt(settings);
        w.set_parent(document.body, .5, .3);
    }

    /**
     * Displays a tooltip for a DOM element.<br>
     * Positions and shows the tooltip content.
     * @param {Event} evt - The triggering event.
     * @param {string} id - Tooltip content element id.
     */
    static tooltip(evt, id) {
        let btn = evt.target;
        let content = document.getElementById(id);

        // position
        content.style.visibility = 'hidden';
        content.style.position = 'absolute';
        H_dom.remove_class(content, 'hidden');

        function close(evt) {
            H_dom.add_class(content, 'hidden');
            h.e.remove_event_click_outside(content, close);
        }

        if (content.parentElement != document.body) {
            H_dom.set_alignment(content, 0, 0, document.body);
        }

        let btn_rect = H_dom.get_global_rect(btn, true);
        let rect = H_dom.get_global_rect(content, true);
        let parent = content.parentElement;
        // let window = H_dom.get_global_rect(document.body, true);

        // verify if it's smaller than the window
        let display = false;
        if (window.screen.height > rect.height) display = true;

        let left = btn_rect.left;
        let top = btn_rect.bottom;

        // will check each parent to move the adapt the position if it's absolute or fixed element.
        let not_fixed = true;
        while (parent && parent.tagName != 'BODY') {
            let parent_style = H_dom.get_style(parent);
            if (parent_style.POS == 'absolute') {
                left -= parent_style.rect.left;
                top -= parent_style.rect.top;
            }
            if (parent_style.POS == 'fixed') {
                not_fixed = false;
                left -= parent_style.rect.left;
                top -= parent_style.rect.top;
            }
            if (parent.scrollTop > 0) top += parent.scrollTop;
            if (parent.scrollLeft > 0) left += parent.scrollLeft;
            parent = parent.parentElement;
        }

        // add the scroll only if there is not fixed parent
        if (not_fixed) {
            left += window.scrollX;
            top += window.scrollY;
        }

        // verify if it's going outside the window
        let right = left + rect.width;
        if (right < window.screen.width) {
            content.style.left = left + 'px';
        } else {
            content.style.left = (left - rect.width) + 'px';
        }

        // let top = btn_rect.bottom;
        
        let bottom = top + rect.height;
        if (bottom < window.screen.height) {
            content.style.top = top + 'px';
        } else {
            content.style.top = (window.screen.height - rect.height - 1) + 'px';
        }

        content.style.visibility = 'visible';

        h.e.add_event_click_outside(content, close);
    }

    /**
     * Block for translation UI logic.<br>
     * Contains methods for changing language and translating content.
     * @type {Object}
     */
    static translate_block = {
        change_lang: function(evt){
            let button = evt.target;

            let input_hidden = false;

            let selector = button.parentNode;
            for(let i = 0; i < selector.children.length; i++){
                let child = selector.children[i];
                if (child.name && child.name == 'hlp_translate_selected_lang') {
                    input_hidden = child;
                    continue;
                }
                if (child.dataset.iso == button.dataset.iso) {
                    child.classList.toggle('selected', true);
                } else {
                    child.classList.toggle('selected', false);
                }
            }

            let container = selector.parentNode;
            for(let i = 0; i < container.children.length;i++){
                let child = container.children[i];
                if(child.dataset && child.dataset.iso){
                    if(child.dataset.iso == button.dataset.iso){
                        child.classList.toggle('hidden', false);
                        child.classList.toggle('selected', true);
                    }else{
                        child.classList.toggle('hidden', true);
                        child.classList.toggle('selected',false);
                    }
                }
            }
        },
        translate: function(event){
            let container = event.target.parentNode;
            //getting languages for this translate block;
            var iso_targets = [];
            var iso_targets_code = [];
            var iso_original = '';
            var id_original = '';
            var sources = [];
            var destinations = [];
            for(let i = 0; i < container.children.length;i++){
                let child = container.children[i];
                if(child.dataset && child.dataset.iso){        
                    if(child.classList.contains('selected')){
                        iso_original = child.dataset.iso;
                        id_original = child.dataset.coid;
                        let inputs = child.querySelectorAll('input[type="text"], textarea');
                        sources = Array.from(inputs).map(input => [input.name,input.value,input.type]);
                    }else{
                        iso_targets_code[child.dataset.iso]=child.dataset.coid;
                        iso_targets.push(child.dataset.iso);
                        let inputs = child.querySelectorAll('input[type="text"], textarea');
                        destinations[child.dataset.coid] = Array.from(inputs).map(input => [input.name,input.value]);
                    }

                }

            }
            for(let i = 0; i < sources.length;i++){
                //now we can do the job
                let current_source = sources[i];
                let texte = current_source[1];
                let basename = current_source[0].slice(0, current_source[0].lastIndexOf('['+id_original+']'));
                let format = (current_source[2] == 'text') ? 'text' : 'html';
                let settings = {};
                
                settings.url = H_constants.base_url + (H_constants.admin_folder ? H_constants.admin_folder : '');
                settings.skip_container = true;
                settings.domTarget = '';
                settings.data = {
                    'action': 'translate',
                    'texte': texte,
                    'format': format,
                    'iso_original': iso_original,
                    'iso_targets': iso_targets
                };
                settings.data.core_action = 'core_mono';
                settings.success = function(res){
                    var results = JSON.parse(res);
                    Object.keys(results).forEach(key => {
                        if( results[key]!="nothing"){
                            let trad = JSON.parse(results[key]);
                            if (format == 'text') {
                                document.getElementsByName(basename+'['+iso_targets_code[key]+']')[0].value=trad.translatedText;
                            }else{
                                let tiny_target = document.getElementsByName(basename+'['+iso_targets_code[key]+']')[0];
                                if (tiny_target._tinymce != undefined){
                                    tiny_target._tinymce.setContent(trad.translatedText);
                                }else{
                                    tiny_target.innerHTML = trad.translatedText;
                                }
                            }
                        }else{  
                            console.log('no connection to libretranslate or nothing to translate');
                        }
                    });
                };
                h.a.send(settings);
            }
        }
    };
    /**
     * Detects screen info and sets CSS variables.<br>
     * window screen logarythmique ratio calculator is used to create some CSS vars that can reduce issues with some screens.<br>
     * e.g : for a 4k screen, you don't display the navigator in full screen size, in general the user reduce the window size because it's more easy.<br>
     * but in this case all vars based on screen width height should be reduced by a ratio based on fullHD as base.<br>
     * --fhd-hr and --fhd-vr are those horizontal and vertical ratio that can be used to multiple othe size vars in your theme to auto adapt it to huge screen.<br>
     * Called from init.js to set screen and window size variables.
     */
    static detect_screen_infos(){
        let root = document.documentElement;
        root.style.setProperty('--screen-x', window.screen.width * window.devicePixelRatio);
        root.style.setProperty('--screen-y', window.screen.height * window.devicePixelRatio);
        root.style.setProperty('--inner-x', window.innerWidth);
        root.style.setProperty('--inner-y', window.innerHeight);
        root.style.setProperty('--ar', window.screen.width / window.screen.height);
        root.style.setProperty('--innerar', window.innerWidth / window.innerHeight);
        if (window.screen.height <= 1080) {
            root.style.setProperty('--fhd-hr', 1);
            root.style.setProperty('--fhd-vr', 1);
            fhdVR=1;
        }else{
            if (window.outerWidth > 2230 ){
                let hr = 1 + (Math.log(window.screen.width / window.devicePixelRatio) - Math.log(1920));
                hr = (hr < 1) ? 1 : hr;
                let vr = 1 + (Math.log(window.screen.height / window.devicePixelRatio) - Math.log(1080));
                vr = (vr < 1) ? 1 : vr;
                root.style.setProperty('--fhd-hr', hr);
                root.style.setProperty('--fhd-vr', vr);
                fhdVR = vr;
            }else{
                let hr = 1 + (Math.log(window.outerWidth / window.devicePixelRatio) - Math.log(1920));
                hr = (hr < 1) ? 1 : hr;
                let vr = 1 + (Math.log(window.outerHeight / window.devicePixelRatio) - Math.log(1080));
                vr = (vr < 1) ? 1 : vr;
                root.style.setProperty('--fhd-hr', hr);
                root.style.setProperty('--fhd-vr', vr);
                fhdVR = vr;
            }
        }
    }

    /**
     * Updates CSS variable for FHD vertical ratio.<br>
     * Called on window resize.
     */
    static update_css_fhd_vr(){
        let root = document.documentElement;
        if (window.innerWidth > 1920 && window.innerWidth < 2230 ) {
            let hr = 1 + (Math.log(window.outerWidth / window.devicePixelRatio) - Math.log(1920));
            hr = (hr < 1) ? 1 : hr;
            root.style.setProperty('--fhd-vr', hr);
            root.style.setProperty('--innerar', window.innerWidth / window.innerHeight);
            fhdVR = hr;
        }
        if (window.innerWidth > 2230){
            let vr = 1+(Math.log(window.screen.height / window.devicePixelRatio) - Math.log(1080));
            root.style.setProperty('--fhd-vr',vr);
            fhdVR = vr;
        }
    }

    /**
     * Copies the value of an input to the clipboard.<br>
     * Optionally shows a popup and supports HTML content.
     * @param {string} id - Input element id.
     * @param {boolean|string} [popup=true] - Show popup or custom message.
     * @param {boolean} [html=false] - Copy as HTML.
     */
    static input_copy_to_clipboard(id, popup = true, html = false){
        let inp = document.getElementById(id);
        // Select the text field
        inp.select();
        if (is_mobile){
            inp.setSelectionRange(0, 99999); // For mobile devices
        }
        // Copy the text inside the text field
        if (html){
            if(String(typeof ClipboardItem) === "undefined") {
                //ff polyfill
                const el = document.createElement('textarea');
                el.value = inp.value;
                document.body.appendChild(el);
                el.select();
                el.addEventListener('copy', (event) => {
                    event.clipboardData.setData('text/html',content);
                });
                const result = document.execCommand('copy');
                document.body.removeChild(el);
            }else{
                const blob = new Blob([inp.value], { type: "text/html" });
                const richTextInput = new ClipboardItem({ "text/html": blob });
                navigator.clipboard.write([richTextInput]);
            }
        }else{
            navigator.clipboard.writeText(inp.value);
        }
        if(popup){
            popup=(H_generics.is_string(popup))?popup:'Copied to clipboard';
            H_ui.message_popup(popup);
        }
    }
    /**
     * Copies content to the clipboard.<br>
     * Optionally shows a popup and supports HTML content.
     * @param {string} content - Content to copy.
     * @param {boolean|string} [popup=true] - Show popup or custom message.
     * @param {boolean} [html=false] - Copy as HTML.
     */
    static copy_to_clipboard(content, popup = true, html = false){
        // Copy the text inside the text field
        if (html){
            const el = document.createElement('textarea');
            el.value = content;
            document.body.appendChild(el);
            el.select();
            el.addEventListener('copy', (event) => {
                event.clipboardData.setData('text/html',content);
            });
            const result = document.execCommand('copy');
            document.body.removeChild(el);
        }else{
            navigator.clipboard.writeText(content);
        }
        if(popup){
            popup = (H_generics.is_string(popup)) ? popup : H_constants.get_text('tlc_to_clipboard');
            H_ui.message_popup_timed(popup);
        }
    }

    /**
     * Initializes styled input range tracks.<br>
     * Updates CSS variables for custom range inputs.
     */
    static init_range_tracks(){
        //update size of track subitem for a styled input range with styled_progress class
        for (let e of document.querySelectorAll('input[type="range"].styled_progress')) {
            e.style.setProperty('--value', e.value);
            e.style.setProperty('--min', e.min == '' ? '0' : e.min);
            e.style.setProperty('--max', e.max == '' ? '100' : e.max);
            e.addEventListener('input', () => e.style.setProperty('--value', e.value));
        }
    }
}
h.libs.ui = H_ui;
window.H_ui = H_ui;

/**
 * @class H_ui_window
 * @classdesc
 * UI class for modal windows and dialogs.<br>
 * Handles modal logic, alignment, content, and close behavior.<br>
 * Possible settings for H_ui_window:<br>
 * - title: {string|HTMLElement|Array} Accepts string, DOM element, or array to add a title on top
 * - class: string (additional CSS classes for the modal)<br>
 * - modal: boolean (show as modal with background mask)<br>
 * - close: string|boolean (label for the close button, or true for default)<br>
 * - on_close: function (callback executed when the modal is closed)<br>
 * - hidden: boolean (start hidden)<br>
 * - content_resizable: boolean to make the window partialy resizable (it depends its CSS, if there is min-width or min-height set)<br>
 * - remove_on_close: boolean (remove from DOM when closed)<br>
 * - nodrag: boolean (disable drag)<br>
 * - disable_background_click: boolean (disable closing by clicking background and pressing ESC key)<br>
 * - auto_alignement: boolean (enable auto-alignment on content change)<br>
 * - special: boolean (special modal, higher z-index)<br>
 * - special_level: number|string (priority level / css z_index for special modals, or 'err' for error modals with +100 as z_index)<br>
 * Used as h.libs.ui_window.
 */
class H_ui_window {

    /**
     * By default, will appear with an overlay hiding others elements than the modal. A click on the overlay will close the modal
     * or pressing the key ESC on your keyboard.
     * If this variable is set to true, this behavior is deactivate. Often use to force the user to make a choice between action in the modal
     */
    #disable_background_click = false;

    /**
     * 
     * @param {Object} settings Window's settings
     * @param {String|Array|HTMLElement} settings.title Add a title on top
     * @param {String} settings.class Additional CSS classes for the modal
     * @param {Boolean} settings.modal Display as modal with background mask
     * @param {Boolean|String} settings.close Label for the close button, or true for default
     * @param {Function|String} settings.on_close Callback executed when closed
     * @param {Boolean} settings.hidden Hide it on create
     * @param {Boolean} settings.content_resizable Make the window partialy resizable. Depends its CSS, if there is min-width or min-height set
     * @param {Boolean} settings.remove_on_close Remove from DOM when closed
     * @param {Boolean} settings.nodrag Disable drag of whole modal
     * @param {Boolean} settings.disable_background_click Disable closing by clicking background and pressing ESC key
     * @param {Boolean} settings.auto_alignement Enable auto-alignment on content change
     * @param {Boolean} settings.special special modal, higher z-index
     * @param {Number|String} settings.special_level Priority level for special modals, or 'err' for error modals
     */
    constructor(settings) {
        settings = settings || {};

        if (!settings.class) settings.class = '';

        this.dom_element = H_dom.create_element('DIV', { 'class': 'hlp_window ' + settings.class });
        this.dom_element._ui_object = this;

        H_event.enable_drag(this.dom_element);

        this._modal = false;
        this._modal_mask = null; // background div
        this._modal_close = null; // close button
        if (settings.title){
            this._modal_title = H_dom.create_element('DIV', { 'class': 'hlp_window_title' });
            this.set_title(settings.title);
            this.dom_element.appendChild(this._modal_title);
        }
        this._modal_content = H_dom.create_element('DIV', { 'class': 'hlp_window_content ' + settings.class });
        this.dom_element.appendChild(this._modal_content);

        this._special = false;
        this._special_level = 1;

        this.align_vertical = 0.5;
        this.align_horizontal = 0.5;

        this.remove_on_close = false;
        this.auto_alignement = false; // auto position the modal to always respect align_horizotal and vertical values

        if (settings.disable_background_click) this.#disable_background_click = true;
        // has to be before modal
        if (settings.special) this._special = true;
        if (settings.special_level) {
            this._special_level = settings.special_level;
        }
        if (settings.content_resizable) this._modal_content.style.resize='both';
        if (settings.modal) this.modal = true;
        if (settings.close) this.close = settings.close;
        if (settings.on_close) this.on_close = settings.on_close;
        if (settings.hidden) this.hide(true);
        else this.show();
        if (settings.remove_on_close) this.remove_on_close = true;
        if (settings.nodrag) H_event.disable_drag(this.dom_element);

        // if (!this._disable_background_click) {
            // H_ui.modal_key_down_handler.push(this);
        // }

        if (settings.auto_alignement) this.auto_alignement = true;
        
        // set observer for this modal
        if (this.auto_alignement) {
            this.observer = new MutationObserver(this.mutation.bind(this));
            this.observer.observe(this.dom_element, {
                subtree: true,
                childList: true
            });
        }
    }
    /**
     * Handles key press events for the modal.<br>
     * Closes modal on ESC key.
     * @param {KeyboardEvent} evt - The keyboard event.
     */
    on_key_press(evt) {
        // When key ESC press, close the modal
        if (evt.key == 'Escape' || evt.key == 'Esc') {

            this.hide();
        }
    }

    /**
     * Sets the content of the modal window.<br>
     * Accepts string, DOM element, or array.
     * @param {string|HTMLElement|Array} content - Content to display.
     */
    set_content(content) {
        if (H_generics.is_string(content)) {
            H_dom.write_html(this._modal_content, content);
        } else if (H_generics.is_dom_object(content)) {
            this._modal_content.innerHTML = '';
            this._modal_content.appendChild(content);
        } else if (H_generics.is_array(content)) {
            this._modal_content.innerHTML = '';
            content.forEach((elem) => {
                if (H_generics.is_dom_object(elem)) {
                    this._modal_content.appendChild(elem);
                } else if (H_generics.is_string(content)) {
                    this._modal_content.innerHTML += content;
                }
            });
        }
    }

    /**
     * Sets the title of the modal window if it's set in the settings<br>
     * Accepts string, DOM element, or array.
     * @param {string|HTMLElement|Array} title - Content to display.
     */
    set_title(title) {
        if (H_generics.is_string(title)) {
            H_dom.write_html(this._modal_title, title);
        } else if (H_generics.is_dom_object(title)) {
            this._modal_title.innerHTML = '';
            this._modal_title.appendChild(title);
        } else if (H_generics.is_array(title)) {
            this._modal_title.innerHTML = '';
            title.forEach((elem) => {
                if (H_generics.is_dom_object(elem)) {
                    this._modal_title.appendChild(elem);
                } else if (H_generics.is_string(title)) {
                    this._modal_title.innerHTML += title;
                }
            });
        }
    }

    /**
     * Sets the parent node for the modal window.
     * Optionally aligns the modal.
     * @param {HTMLElement} parentNode - Parent node.
     * @param {number} [align_horizontal] - Horizontal alignment.
     * @param {number} [align_vertical] - Vertical alignment.
     */
    set_parent(parentNode, align_horizontal, align_vertical) {
        if (this._modal_mask) {
            parentNode.appendChild(this._modal_mask);
        }
        parentNode.appendChild(this.dom_element);
        H_dom.set_rect_dirty(this.dom_element);
        if (align_horizontal) this.set_alignment(align_vertical, align_horizontal);
        if (this.dom_element._ComputedStyle && this.dom_element._ComputedStyle.POS != 'fixed') {
            this.update_scroll_position();
        }
    }

    /**
     * Mutation observer callback for modal content changes.
     * @param {Array} records - Mutation records.
     */
    mutation(records) {
        let applied = false;
        records.forEach((record) => {
            if (!applied && record.type == 'childList' && record.addedNodes.length > 0) {
                // something changes in modal, move it to her original position
                // mod.dom_element.style.visibility = 'visible';
                H_dom.set_alignment(this.dom_element, this.align_horizontal, this.align_vertical, false, true);
                applied = true;
            }
        });
    }

    /**
     * Sets the alignment of the modal window.
     * @param {number} align_vertical - Vertical alignment.
     * @param {number} align_horizontal - Horizontal alignment.
     */
    set_alignment(align_vertical, align_horizontal) {
        this.align_vertical = align_vertical;
        this.align_horizontal = align_horizontal;
        H_dom.set_alignment(this.dom_element, this.align_horizontal, this.align_vertical, false, true);
    }

    /**
     * Updates the scroll position of the modal window.<br>
     * Adjusts top position if the page is scrolled.
     */
    update_scroll_position() {
        let scroll_y = window.scrollY;
        if (scroll_y > 0) {
            if (this.dom_element) {
                let top = parseInt(this.dom_element.style.top.replace('px', ''));
                this.dom_element.style.top = top + scroll_y + 'px';
            }
        }
    }

    /**
     * Toggles the modal window's visibility.<br>
     * Shows or hides the modal.
     */
    toggle() {
        if (this.visible) {
            this.hide();
        } else {
            this.show();
        }
        H_dom.set_rect_dirty(this.dom_element);
    }

    /**
     * Hides the modal window.<br>
     * Optionally triggers on_close callback.
     * @param {boolean} [at_start=false] - If true, hides at start.
     */
    hide(at_start = false) {
        if(at_start === false && this.on_close !== undefined ){
            if (H_generics.is_string(this.on_close)) H_generics.execute_function_by_name(this.on_close, window);
            else this.on_close();
        }
        if (this._modal) {
            this._modal_mask.classList.toggle('hidden', true);
        }
        this.dom_element.classList.toggle('hidden', true);

        if (!this._special && at_start === false) H_ui_window.current_open_z_index -= 2;

        if (this.remove_on_close) this.remove();
    }

    /**
     * Shows the modal window.<br>
     * Makes the modal visible and brings it to front.
     */
    show() {
        if (this._modal) {
            this._modal_mask.classList.toggle('hidden', false);
            // change the zindex
            if (!this._special) this.zindex = H_ui_window.current_open_z_index;
        }
        this.dom_element.classList.toggle('hidden', false);

        // can be disabled by keypress
        if (!this.#disable_background_click) {
            H_ui_window.opened.push(this);
            h.e.add_event_key(document, H_ui_window.handle_key_press);
        }

        if (!this._special) H_ui_window.current_open_z_index += 2;
    }

    /**
     * Removes the modal window from the DOM.<br>
     * Cleans up event handlers and observers.
     */
    remove() {
        if (this._modal) {
            this._modal_mask.remove();
        }
        this.dom_element.remove();

        if (this.auto_resize && this.observer && H_generics.is_function(this.observer.disconnect)){
            this.observer.disconnect();
            this.observer = false;
        }

        if (!this.#disable_background_click) {
            H_ui_window.opened.splice(H_ui_window.opened.indexOf(this), 1);
        }
        H_ui.clean_tox();
    }

    /**
     * Handles double click on the modal mask.<br>
     * Hides the modal.
     * @param {Event} event - The double click event.
     */
    on_double_click_mask(event) {
        H_event.stop_event(event);
        this.hide();
    }

    /**
     * Adds or removes the modal mask as needed.
     * if the special_level is not an int but 'err' like error, the z_index += 100 !
     * @param {boolean} value - True to enable modal.
     */
    set modal(value){
        if(value){
            if(!this._modal_mask){
                this._modal_mask = H_dom.create_element('DIV',{'class':'hlp_modal_mask'});
                if(!this._disable_background_click){
                    h.e.add_event(this._modal_mask , h.e.EVENT_CLICK , this.on_double_click_mask.bind(this));
                }
            }
            if(this.dom_element.parentNode){
                this.dom_element.parentNode.insertBefore(this._modal_mask , this.dom_element);
            }
            this._modal = true;
            let base_zIndex = H_ui_window.current_open_z_index;
            if (this._special) {
                if (this._special_level == 'err'){
                    base_zIndex += 100;
                } else {
                    base_zIndex = base_zIndex + (this._special_level+2);
                }
            }
            this.zindex = base_zIndex;
        }else{
            if(this._modal){
                this._modal_mask.remove();
            }
            this._modal = false;
            H_dom.set_style_value(this.dom_element , 'z-index', '');
        }
    }

    /**
     * Change the zindex for the modal and overlay
     * @param {integer} value the z-index to apply to overlay, modal take +1
     */
    set zindex(value){
        H_dom.set_style_value(this._modal_mask , 'z-index', value);
        H_dom.set_style_value(this.dom_element , 'z-index', value + 1);
    }

    /**
     * Gets the visibility state of the modal window.
     * @returns {boolean} True if visible.
     */
    get visible(){
        return (document.body.contains(this.dom_element) && !this.dom_element.classList.contains('hidden'));
    }

    /**
     * Sets the close button for the modal window.
     * @param {boolean} value - Button label or true.
     */
    set close(value){
        if (value){
            if (!this._modal_close) {
                this._modal_close = H_dom.create_element('BUTTON',{'class':'hlp_window_close'}, H_dom.create_icon('x-circle'));
            }
            if(this.dom_element){
                this.dom_element.appendChild(this._modal_close);
                if (!h.e.has_event(this._modal_close, h.e.EVENT_CLICK)) h.e.add_event_click(this._modal_close, this.toggle.bind(this));
            }
        }
    }

    /**
     * Current open z-index for modals.
     * @type {number}
     */
    static current_open_z_index = 100;

    static opened = [];
    /**
     * Handle key event for all the modal, determine wich modal will receive the event. (generally the last opened)
     * @param {KeyboardEvent} evt 
     */
    static handle_key_press(evt){
        if (H_ui_window.opened) {
            if (H_ui_window.opened[H_ui_window.opened.length - 1]!=undefined) H_ui_window.opened[H_ui_window.opened.length - 1].on_key_press(evt);
            if (H_ui_window.opened.length == 0) h.e.remove_event_key(document, H_ui_window.handle_key_press);
        }
    }
}
h.libs.ui_window = H_ui_window;
window.H_ui_window = H_ui_window;

/**
 * @class H_ui_quick_edit
 * @classdesc
 * Utility class for quick edit UI functionality.<br>
 * Handles inline editing, keyboard shortcuts, and AJAX save/cancel.<br>
 * Used as h.libs.quick_edit.
 */
class H_ui_quick_edit{
    /**
     * Handles quick edit keyboard events (Enter to save, Esc to cancel).
     * @param {KeyboardEvent} event - The keyboard event.
     */
   static check(event){

        if(event.key == "Enter"){
            H_event.stop_event(event);

            H_ui_quick_edit.send({'target':event.target,'action':'save'});
        }

        if(event.key == 'Escape'){
            H_event.stop_event(event);

            H_ui_quick_edit.send({'target':event.target,'action':'cancel'});
        }
    }
    /**
     * Sends a quick edit action (save or cancel).
     * @param {Event|Object} event - The event or settings object.
     */
    static send(event){

        let action = '';
        let targetTag = null;
        let field_ID = '';
        let id_lang_data = null;

        if(!H_generics.is_event(event)){
            targetTag = event.target.parentNode;
            action = event.action;
            field_ID = event.target.getAttribute('id');
        }else{
            H_event.stop_event(event);
            let buttonTag = event.target;
            action = buttonTag.getAttribute('value');
            targetTag = buttonTag.parentNode;
            field_ID = buttonTag.getAttribute('data-'+H_constants.quick_edit_field);
        }

        let value_field = document.getElementById(field_ID);

        if(action == 'cancel'){
            if(targetTag._quick_edit_initial_value !== undefined){
                targetTag.innerHTML = targetTag._quick_edit_initial_value;
                delete(targetTag._quick_edit_initial_value);
                H_dom.remove_class(targetTag, 'quick_edit_open');
                return;
            }
        }

        if(!h.v.check_field(value_field)){
            alert(H_constants.get_text('error')+' : '+value_field._validatorIcon.getAttribute('title'));
            value_field.focus();
            return false;
        }

        let data = value_field.getAttribute('data-'+H_constants.quick_edit_data);
        id_lang_data = value_field.getAttribute('data-id_lang_data');

        let dataTag = null;
        if(!data){
            dataTag = value_field.parentNode;
            let loops = 0;
            while(!data && dataTag && loops < 1000){
                data = dataTag.getAttribute('data-'+H_constants.quick_edit_data);
                if(!data){
                    dataTag = dataTag.parentNode;
                }
                loops++;
            }
        }

        if(!data){
            return false;
        }

        let id = value_field.getAttribute('data-'+H_constants.quick_edit_id);
        let type = value_field.getAttribute('data-'+H_constants.quick_edit_type);

        let settings = {};
        settings.url = dataTag.getAttribute('action');
        settings.data = {'quick_edit':action , 'action':'formgettoken', 'id':id , 'type':type , 'data':data , 'value':value_field.value , 'id_lang_data':id_lang_data};
        settings.success = function(js_token){
            if (js_token == 'error'){
                return;
            }
            settings.data.delete('action');
            settings.data.append('js_token', js_token);
            settings.success = function(responseText , ajaxSender){
                if(responseText == 'ERROR!'){
                    if(targetTag._quick_edit_initial_value !== undefined){
                        targetTag.innerHTML = targetTag._quick_edit_initial_value;
                    }
                    console.warn('Error in quick edit !');
                }else{
                    targetTag.innerHTML = responseText;
                }
                targetTag.setAttribute('data-placeholder','off');
                delete(targetTag._quick_edit_initial_value);
                H_dom.remove_class(targetTag, 'quick_edit_open');
            };

            settings.error = function(event){
                debugger;
            };

            h.a.send(settings);
        };

        h.a.send(settings);
    }
    /**
     * Initializes quick edit mode for a target element.
     * @param {HTMLElement} targetTag - The target element.
     * @returns {boolean} False if already in quick edit mode.
     */
    static qedit(targetTag){

        if(targetTag._quick_edit_initial_value !== undefined){
            return false;
        }


        let data = targetTag.getAttribute('data-'+H_constants.quick_edit_data);
        let dataTag = null;
        if(!data){
            dataTag = targetTag.parentNode;
            let loops = 0;
            while(!data && dataTag && loops < 1000){
                data = dataTag.getAttribute('data-'+H_constants.quick_edit_data);
                if(!data){
                    dataTag = dataTag.parentNode;
                }
                loops++;
            }
        }

        if(!data){
            return false;
        }

        let id = targetTag.getAttribute('data-'+H_constants.quick_edit_id);
        let type = targetTag.getAttribute('data-'+H_constants.quick_edit_type_index);
        let id_lang_data = targetTag.getAttribute('data-id_lang_data');
        let placeholder_mode=targetTag.getAttribute('data-placeholder');
        if (placeholder_mode=='on'){
            targetTag.innerHTML=''; 
            targetTag._quick_edit_initial_value=targetTag.getAttribute('placeholder');       
        }else{
            targetTag._quick_edit_initial_value = targetTag.innerHTML;
        }

        

        let settings = {};
        settings.url = dataTag.getAttribute('action');
        settings.dom_target = '';
        settings.data = {'quick_edit':true , 'action':'formgettoken', 'id':id , 'type':type , 'data':data , 'value':targetTag.innerHTML , 'id_lang_data':id_lang_data };
        if (targetTag.hasAttribute('min')){
            settings.data.min = targetTag.getAttribute('min');
        }
        if (targetTag.hasAttribute('max')){
            settings.data.max = targetTag.getAttribute('max');
        }
        settings.success = function(js_token){
            if (js_token == 'error'){
                return;
            }
            settings.dom_target = targetTag;
            settings.data.delete('action');
            settings.data.append('js_token', js_token);
            settings.success = null;
            h.a.send(settings);
            H_dom.add_class(targetTag, 'quick_edit_open');
        };
        h.a.send(settings);
    }
}
h.libs.quick_edit = H_ui_quick_edit;
window.H_ui_quick_edit = H_ui_quick_edit;

/**
 * @class H_ui_button
 * @classdesc
 * UI class for custom buttons.<br>
 * Handles icon, label, tooltip, and click/keyboard events.<br>
 * Possible settings for H_ui_button:<br>
 * - dom_element: HTMLElement or string id (optional, will create a new button if not provided)<br>
 * - icon: string (CSS class for icon, e.g. 'feather-edit')<br>
 * - label: string (button text)<br>
 * - css: string (additional CSS classes)<br>
 * - tooltip: string (sets the title attribute)<br>
 * - onclick: function (click handler)<br>
 * - select_active: boolean (only active if selection is present)<br>
 * - action: any (custom action value)<br>
 * - returnclick: boolean (handle Enter key as click)<br>
 * 
 * Used as h.libs.ui_button.
 */
class H_ui_button {
    constructor(settings) {
        settings = settings || {};

        let dom_element = settings.dom_element;
        if(H_generics.is_string(dom_element)){
            dom_element = H_dom.get_dom_element(dom_element);
        }
        if(H_generics.is_dom_object(dom_element)){
            this.dom_element = dom_element;
            this.dom_element.classList.toggle('hlp_btn',true);
            this.dom_element.classList.toggle('default',true);
        }else{
            this.dom_element = H_dom.create_element('BUTTON',{'class':'hlp_btn default'});
        }
        this.dom_element._ui_object = this;

        if(settings.icon){
            this.dom_element.innerHTML = '';
            this.dom_element.classList.toggle('icon' , true);
            this.dom_element.classList.toggle(settings.icon , true);
            this.dom_element.classList.toggle('default' , false);
        }else if(settings.label){
            this.dom_element.innerHTML = settings.label;
        }

        if(settings.css){
            this.dom_element.classList.add(settings.css);
        }

        if(settings.tooltip){
            this.tooltip = settings.tooltip;
            this.dom_element.title = this.tooltip;
        }

        // indicates that this element is activated
        this.select_active = settings.select_active || false;

        this.action = settings.action || null;

        if(settings.onclick){
            this._handler = settings.onclick;
        }

        H_event.add_event(this.dom_element , H_event.EVENT_MOUSEUP , this.on_click.bind(this) );
        if(settings.returnclick){
            H_event.add_event(document, H_event.EVENT_KEYDOWN , this.return_click.bind(this) );
        }
    }

    /**
     * Sets the parent node for the button.
     * @param {HTMLElement} parent_node - Parent node.
     */
    set_parent(parent_node){
        if(!parent_node){
            this.dom_element.remove();
        }else{
            parent_node.appendChild(this.dom_element);
        }
        H_dom.set_rect_dirty(this.dom_element);
    }

    /**
     * Handles click events on the button.
     * @param {Event} event - The mouse event.
     */
    on_click(event){
        if(event.button === 0){
            H_event.stop_event(event);
            if(H_generics.is_function(this._handler)){
                this._handler(event);
            }
        }
    }

    /**
     * Handles Enter key events for the button.
     * @param {Event} event - The keyboard event.
     */
    return_click(event){
        if(event.key == "Enter"){
            H_event.stop_event(event);
            if(H_generics.is_function(this._handler)){
                this._handler(event);
            }
        }
    }
}
h.libs.ui_button = H_ui_button;
window.H_ui_button = H_ui_button;

/**
 * @class H_ui_prompt
 * @classdesc
 * UI class for prompt dialogs and confirmation popups.<br>
 * Extends H_ui_window.<br>
 * Handles buttons, keypress, and cleanup.<br>
 * Possible settings for H_ui_prompt:<br>
 * - content: string|HTMLElement|Array (content to display in the prompt)<br>
 * - contentClass: string (optional, CSS class for the content area)<br>
 * - fields: object (optional, additional fields to pass to handlers)<br>
 * - buttons: array of button settings, each with:<br>
 *      - label: string (button text)<br>
 *      - css: string (optional, additional CSS classes)<br>
 *      - handler: function(fields, dom_element) (callback on click)<br>
 *      - keyPress: string|array (key(s) to trigger this button, e.g. 'Enter', 'Escape')<br>
 * - cancel_handler: function (optional, handler for cancel action)<br>
 * - modal: boolean (default true, show as modal)<br>
 * - disable_background_click: boolean (default false)<br>
 * - special: boolean (default true, special modal)<br>
 * - special_level: number (default 1)<br>
 * - remove_on_close: boolean (remove from DOM when closed)<br>
 * Used as h.libs.ui_prompt.
 */
class H_ui_prompt extends H_ui_window {
    constructor(settings) {
        settings.modal = true;
        settings.class = 'hlp_prompt_message';
        settings.auto_alignement = true;

        super(settings); // call the parent class constructor

        this.fields = settings.fields || {};

        H_dom.add_class(this._modal_content, 'hlp_prompt_message');
        if (settings.contentClass) {
            H_dom.add_class(this._modal_content, settings.contentClass);
        }
        H_dom.append_content(this._modal_content, settings.content);

        this.keypress_btns = [];

        if (H_generics.is_array(settings.buttons)) {
            this.buttons_div = H_dom.create_element('DIV', { 'class': 'hlp_prompt_buttons' });
            this.dom_element.appendChild(this.buttons_div);

            for (let i = 0; i < settings.buttons.length; i++) {
                let b = settings.buttons[i];
                let btn = new H_ui_button(b);
                btn.set_parent(this.buttons_div);
                btn.dom_element._handler = b.handler;
                h.e.add_event_click(btn.dom_element, this.on_click_button.bind(this));
                if (b.keyPress) {
                    if (H_generics.is_string(b.keyPress)) {
                        this.keypress_btns.push({ 'target': btn.dom_element, 'key': b.keyPress });
                    } else {
                        b.keyPress.forEach(key => {
                            this.keypress_btns.push({ 'target': btn.dom_element, 'key': key });
                        });
                    }

                }
            }
        } else {
            let cancel_btn = new H_ui_button({ 'tooltip': H_constants.get_text('tlc_annuler'), 'label': H_constants.get_text('tlc_annuler') });
            cancel_btn.set_parent(this.dom_element);
            cancel_btn.dom_element._handler = settings.cancel_handler ? settings.cancel_handler : null;
            h.e.add_event_click(cancel_btn.dom_element, this.on_click_button.bind(this));
            this.keypress_btns.push({ 'target': cancel_btn, 'key': 'Escape' });
        }

        if (this.keypress_btns.length > 0) {
            h.e.add_event(document, H_event.EVENT_KEYUP, this.on_key_press.bind(this));
    
        }
    }

    /**
     * Handles click events on prompt buttons.
     * @param {Event} event - The mouse event.
     */
    on_click_button(event) {
        if (event.button === 0) {
            H_event.stop_event(event);
            if (event.target._handler) event.target._handler(this.fields, this.dom_element);
            this.clean();
        }
    }

    /**
     * Cleans up the prompt dialog.<br>
     * Removes event listeners and DOM elements.
     */
    clean() {
        this.remove();
        if (this.keypress_btns.length > 0) {
            h.e.remove_event(document, H_event.EVENT_KEYUP, this.on_key_press.bind(this));
        }
    }

    /**
     * Handles key press events for prompt buttons.
     * @param {Event} event - The keyboard event.
     */
    on_key_press(event) {
        this.keypress_btns.forEach((obj) => {
            if (obj.key == event.key) {
                h.e.send_event_click(obj.target);
            }
        });
    }

    /**
     * Hides the prompt buttons.
     * @param {Event} event - The event.
     */
    hide_buttons(event) {
        this.buttons_div.classList.toggle('hidden', true);
    }

    /**
     * Shows the prompt buttons.
     * @param {Event} event - The event.
     */
    show_buttons(event) {
        this.buttons_div.classList.toggle('hidden', false);
    }
}
h.libs.ui_prompt = H_ui_prompt;
window.H_ui_prompt = H_ui_prompt;

/**
 * @class H_search
 * @classdesc
 * Utility class for search UI logic used by admin modules.<br>
 * Not used by public search module.<br>
 * Handles filtering, pagination, and search actions.<br>
 * Used as h.libs.search.
 */
class H_search {
    constructor() {}
    
    /**
     * Triggers a search on Enter key.
     * @param {Event} event - The keyboard event.
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static should_search(event, module_name = '', dom_id = ''){
        if (event.key == 'Enter') {
            //~ document.getElementById(module_name+'_admin_btn_search').click();
            document.getElementById(module_name + '_btn_search' + dom_id).click();
        }
    }

    /**
     * Sets filter value and triggers search.
     * @param {string} val - Filter value.
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static filter(val, module_name = '', dom_id = ''){
        document.getElementById(module_name + '_order_filter' + dom_id).value = val;
        let page_jumper = document.getElementById(module_name + '_page_jumper' + dom_id);
        if (page_jumper != undefined) {
            page_jumper.value = 1;
        }
        document.getElementById(module_name + '_btn_search' + dom_id).click();
    }

    /**
     * Goes to previous page in search results.
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static previous(module_name = '', dom_id = ''){
        let start_index = document.getElementById(module_name + '_start_index' + dom_id);
        let nbr_res = document.getElementById(module_name + '_nbr_result' + dom_id);
        nbr_res = nbr_res.selectedOptions[0].value;
        let index_value = start_index.value != '' ? parseInt(start_index.value) : 0;
        start_index.value = index_value - parseInt(nbr_res);
        if (start_index.value < 0) start_index.value = 0;
        let page_jumper = document.getElementById(module_name + '_page_jumper' + dom_id);
        if (page_jumper != undefined) {
            page_jumper.value = 1;
        }
        // in this case we don't send with event.js because it's the normal behavior of submit button to submit the form
        // there is no special event click for this
        document.getElementById(module_name + '_btn_search' + dom_id).click();
    }

    /**
     * Goes to next page in search results.
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static next(module_name = '', dom_id = ''){
        let start_index = document.getElementById(module_name + '_start_index' + dom_id);
        let nbr_res = document.getElementById(module_name + '_nbr_result' + dom_id);
        nbr_res = nbr_res.selectedOptions[0].value;
        let index_value = start_index.value != '' ? parseInt(start_index.value) : 0;
        start_index.value = index_value + parseInt(nbr_res);
        let page_jumper = document.getElementById(module_name + '_page_jumper' + dom_id);
        if (page_jumper != undefined) {
            page_jumper.value = 1;
        }
        document.getElementById(module_name + '_btn_search' + dom_id).click();
    }

    /**
     * Jumps to a specific page in search results.
     * @param {Event} e - The event.
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static jump_to(e, module_name = '', dom_id = ''){
        let start_index = document.getElementById(module_name + '_start_index' + dom_id);
        let start_page_index = e.target.selectedOptions[0].value;
        start_index.value = start_page_index;
        let page_jumper = document.getElementById(module_name + '_page_jumper' + dom_id);
        if (page_jumper != undefined) {
            page_jumper.value = 1;
        }
        document.getElementById(module_name + '_btn_search' + dom_id).click();
    }

    /**
     * Adds change event to filter inputs.
     * @param {Event} evt - The event.
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static change_filter(evt, module_name = '', dom_id = ''){
        let form = document.getElementById(module_name + '_form' + dom_id);
        let btn = document.getElementById(module_name + '_btn_send' + dom_id);
        for (let i = 0; i < form.length; i++) {
            let elem = form[i];
            if (elem.tagName == 'INPUT' || elem.tagName == 'SELECT') {
                h.e.add_event(elem, 'change', function () {
                    H_dom.toggle_class(btn, 'filter_change', true);
                });
            }
        }
    }

    /**
     * trig the save action then refresh the search
     * @param {string} [module_name] - Module name.
     * @param {string} [dom_id] - DOM id.
     */
    static save(module_name = '', dom_id = ''){
        let btn = document.getElementById(module_name + '_btn_save' + dom_id);
        let btn_search = document.getElementById(module_name + '_btn_search' + dom_id);
        if (btn) btn.click();
        if (btn_search) setTimeout(() => { btn_search.click(); }, 200);
    }
    /**
     * Deletes an item with confirmation.<br>
     * then refresh the search.
     * @param {Event} evt - The event.
     * @param {string} [module_name] - Module name.
     * @param {number} [id=0] - Item id.
     * @param {string} [dom_id] - DOM id.
     */
    static del(evt, module_name = '', id = 0, dom_id = ''){
        let txt = evt.target.dataset.confirm;
        H_ui.confirm_popup(txt, () => {
            if (id > 0) {
                let btn = document.getElementById(module_name + '_btn_del_' + id + dom_id);
                let btn_search = document.getElementById(module_name + '_btn_search' + dom_id);
                if (H_search.#modal) H_search.#modal.hide();
                btn.click();
                if(btn_search != null){
                    setTimeout(() => { btn_search.click(); }, 500);
                }
            }
        });
    }
    static #previous_class = false;
    static #modal = false;
    /**
     * Opens a modal for editing a search result item.
     * @param {number} [id=0] - Item id.
     * @param {string} [module_name] - Module name.
     * @param {string} [tableName] - Table name.
     * @param {string} [action] - Action name.
     * @param {string} [dom_id] - DOM id.
     */
    static modal_edit(id = 0, module_name = '', tableName = '', action = '', dom_id = ''){
        H_search.#modal = H_ui.add_window(document.body, {
            hidden: true,
            modal: true,
            close: true,
            class: 'search_edit_modal',
            remove_on_close: true
        });
        H_search.#modal.dom_element.setAttribute('id', 'edit_modal');
        H_search.#modal._modal_content.setAttribute('id', 'search_edit_modal_content');
        H_search.#modal._modal_content.classList.add('search_edit_modal_content');

        let settings = {};

        settings.url = H_constants.base_url + (H_constants.admin_folder ? H_constants.admin_folder : 'public/');
        settings.url += module_name + '/index.php';
        settings.dom_target = '';
        settings.skip_container = true;
        settings.data = {};
        settings.data[module_name + '_action'] = module_name + '_' + action;
        settings.data[module_name + '_' + tableName + '-id'] = id;
        settings.data['dom_id'] = dom_id;
        settings.success = (res) => {
            H_search.#modal.set_content(res);

            if (H_search.#previous_class !== false) H_dom.remove_class(H_search.#modal.dom_element, H_search.#previous_class);
            H_search.#previous_class = module_name;
            H_dom.add_class(H_search.#modal.dom_element, H_search.#previous_class);

            H_search.#modal.show();
            H_dom.set_alignment(H_search.#modal.dom_element, 0.5, 0.5);
        };
        h.a.send(settings);
    }
}
h.libs.search = H_search;
window.H_search = H_search;

/**
 * @class H_ui_autocomplete
 * @classdesc
 * UI class for autocomplete input fields.<br>
 * Handles search, selection, and keyboard navigation.<br>
 * Possible settings for H_ui_autocomplete:<br>
 * - name: string (name of the field)<br>
 * - table_name: string (table to search in)<br>
 * - field_name: string (field to search in)<br>
 * - submit: boolean (submit the form on selection)<br>
 * - new_value: boolean (allow entering a new value)<br>
 * - toreturn: string ('id' or 'text', value to return on selection)<br>
 * - confirm: string|boolean (confirmation message on selection)<br>
 * - callback: string|function (function name or function to call on selection)<br>
 * Used as h.libs.ui_autocomplete.
 * 
 * @param {string} dom_id dom_id for the created autocomplete
 */
class H_ui_autocomplete {

    #list_focus_index = 0;
    #is_open = false;

    constructor(dom_id, settings) {
        this.dom_id = dom_id;

        if (!settings.name || !settings.table_name || !settings.field_name) {
            console.error('missing some settings for autocomplete to work properly', settings);
            return;
        }

        this.name = settings.name;
        this.table = settings.table_name;
        this.field = settings.field_name;

        this.submit = settings.submit ?? false;
        //let entering a new value 
        this.new_value = settings.new_value ?? false;
        //indicates that the input will return the id of the selected value
        //do not use this if you want to return the text value with new_value
        this.toreturn = settings.toreturn ?? 'id';
        this.confirm = settings.confirm ?? false;
        this.callback = settings.callback ?? false;
        this.centraldb = settings.centraldb ?? false;
        this.base_id = 'autocomplete_' + this.name + this.dom_id + '_';
        console.log(settings);
        // field that save the current selected id, the one used for saving in db
        this.current_id = document.getElementById(this.base_id + 'current_id');

        // input text in which user type his research
        this.input_search = document.getElementById(this.base_id + 'input_search');
        h.e.add_event_key(this.input_search, this.on_key_down.bind(this));
        h.e.add_event(this.input_search, 'input', this.on_input.bind(this));
        h.e.add_event(this.input_search, 'focus', this.on_focus.bind(this));

        this.search_list = document.getElementById(this.base_id + 'search_list');
        H_dom.add_class(this.search_list, 'hidden');
        this.search_list.other_inside_elements = this.input_search;

    }
    /**
     * Handles focus event on the input field.<br>
     * Opens the autocomplete dropdown with show()
     * @param {Event} evt - The focus event.
     */
    on_focus(evt) {
        this.show();
    }
    /**
     * Handles input event on the search field.<br>
     * Sends AJAX request if input length > 1 and displays results.<br>
     * @param {boolean} [open=true] - Whether to open the dropdown after input.
     */
    on_input(open = true) {
        let val = this.input_search.value;
        this.close();

        if (val.length > 1) {
            this.#list_focus_index = -1; // reset
            // now we're getting our datas :
            let settings = {};
            let url = this.input_search.form.action;
            settings.url = url;
            settings.dom_target = '';
            settings.skip_container = true;
            settings.no_timer = true;

            settings.data = {};
            settings.data['action'] = 'autocomplete';
            settings.data['table'] = this.table;
            settings.data['fields'] = this.field;
            settings.data['value'] = val;
            settings.data['centraldb'] = this.centraldb;
            // get the extra data with data-autocomp from parent form
            // a field with data-autocomp has to be included in the sended data
            let form = this.input_search.form;
            for (let i = 0; i < form.length; i++) {
                if (form[i].getAttribute('data-autocomp')) {
                    settings.data[form[i].name] = form[i].value;
                }
            }

            settings.success = (result) => {
                this.display_search_result(result, open);
            };

            h.a.send(settings);
        }
    }
    /**
     * Displays the search results in the dropdown.<br>
     * Adds event listeners to each result item for selection.
     * @param {string} result - HTML string of results.
     * @param {boolean} open - Whether to open the dropdown.
     */
    display_search_result(result, open) {
        this.search_list.innerHTML = result;
        for (let i = 0; i < this.search_list.children.length; i++) {
            let line = this.search_list.children[i];
            h.e.add_event_click(line, this.on_click_search_result.bind(this));
            h.e.add_event(line, 'mousemove', this.on_hover_search_result.bind(this));
        }

        if (open) this.show();
    }

    /**
     * Handles click event on a search result item.<br>
     * Sets the value, closes the dropdown, and triggers callback or confirmation if needed.<br>
     * @param {Event} evt - The click event.
     */
    on_click_search_result(evt) {
        // insert the value for the autocomplete text field:
        this.input_search.value = evt.target.dataset.text;
        this.current_id.value = (this.toreturn == 'id') ? evt.target.dataset.id : evt.target.dataset.text;

        // close the list of autocompleted values,
        this.close();

        if (this.confirm) {
            H_ui.confirm_popup(this.confirm, () => {
                if (this.submit) H_event.send_event(this.input_search.form, 'submit');
                this.on_input(false);
            });
        } else {
            if (this.submit) H_event.send_event(this.input_search.form, 'submit');
            this.on_input(false);
        }

        if (this.callback) H_generics.execute_function_by_name(this.callback, window, this.current_id.value, evt);
    }

    /**
     * Handles mouse hover on a search result item.<br>
     * Updates the active (highlighted) result.
     * @param {Event} evt - The mousemove event.
     */
    on_hover_search_result(evt) {
        let targ = evt.target;

        let index = Array.from(this.search_list.children).indexOf(targ);
        if (index == this.#list_focus_index) return;

        this.remove_active();
        this.#list_focus_index = index;
        this.add_active();
    }

    /**
     * Shows the autocomplete dropdown.<br>
     * Adds click outside event to close it.
     */
    show() {
        if (this.#is_open || this.search_list.children.length == 0) return;

        H_dom.remove_class(this.search_list, 'hidden');
        this.#is_open = true;
        h.e.add_event_click_outside(this.search_list, this.close.bind(this));
    }

    /**
     * Closes the autocomplete dropdown.<br>
     * Removes click outside event.
     */
    close() {
        if (!this.#is_open) return;

        H_dom.add_class(this.search_list, 'hidden');
        this.#is_open = false;
        h.e.remove_event_click_outside(this.search_list, this.close.bind(this));
    }

    /**
     * Handles keydown events for navigation and selection.<br>
     * Supports ArrowUp, ArrowDown, Enter, and new value logic.
     * @param {KeyboardEvent} evt - The keyboard event.
     */
    on_key_down(evt){
        if (evt.key == 'ArrowDown') {
            // If the arrow DOWN key is pressed,
            // increase the this.#list_focus_index variable:
            this.remove_active();
            this.#list_focus_index++;
            if (this.#list_focus_index >= this.search_list.children.length) this.#list_focus_index = 0;
            this.add_active();
            this.scroll_to_active();
        } else if (evt.key == 'ArrowUp') { //up
            // If the arrow UP key is pressed,
            // decrease the this.#list_focus_index variable:
            this.remove_active();
            this.#list_focus_index--;
            if (this.#list_focus_index < 0) this.#list_focus_index = (this.search_list.children.length - 1);
            this.add_active();
            this.scroll_to_active();
        } else if (evt.key == 'Enter') {
            // If the ENTER key is pressed, prevent the form from being submitted
            h.e.stop_event(evt);
            if (this.#list_focus_index > -1) {
                // and simulate a click on the "active" item:
                h.e.send_event_click(this.search_list.children[this.#list_focus_index]);
            }
        } else if (this.new_value) {
            this.current_id.value = this.input_search.value;
        }
    }

    /**
     * Scrolls the dropdown to ensure the active item is visible.
     */
    scroll_to_active(){
        let active = this.search_list.children[this.#list_focus_index];
        let parent = active.parentElement;

        let rect_active = H_dom.get_global_rect(active, true);
        let rect_parent = H_dom.get_global_rect(parent, true);

        if (rect_active.bottom > rect_parent.bottom) {
            active.scrollIntoView(false);
        }

        if (rect_active.top < rect_parent.top) {
            active.scrollIntoView(true);
        }
    }

     /**
     * Adds the active (highlighted) class to the current result item.
     */
    add_active(){
        H_dom.add_class(this.search_list.children[this.#list_focus_index], 'hlp_autocomplete_active');
    }

    /**
     * Removes the active (highlighted) class from the current result item.
     */
    remove_active() {
        H_dom.remove_class(this.search_list.children[this.#list_focus_index], 'hlp_autocomplete_active');
    }

    /**
     * Checks if the autocomplete input exists in the DOM.
     * @returns {boolean} True if exists.
     */
    exist(){
        let exist = document.getElementById(this.input_search.id);
        return exist ? true : false;
    }
      /**
     * Cleans up the autocomplete instance.<br>
     * (Currently empty, override if needed.)
     */
    clean(){

    }

    /**
     * Stores all autocomplete instances by dom_id.
     * @type {Object}
     */
    static instances = {};

    /**
     * Creates a new autocomplete instance for a given dom_id.<br>
     * Cleans up any existing instance for the same dom_id.
     * @param {string} dom_id - The DOM id suffix.
     * @param {Object} settings - Settings for the instance.
     * @returns {H_ui_autocomplete} The created instance.
     */
    static create_instance(dom_id, settings) {
        if (H_ui_autocomplete.instances[dom_id]) {
            H_ui_autocomplete.instances[dom_id].clean();
            delete (H_ui_autocomplete.instances[dom_id]);
        }
        H_ui_autocomplete.instances[dom_id] = new H_ui_autocomplete(dom_id, settings);

        H_ui_autocomplete.clean_instances();

        return H_ui_autocomplete.instances[dom_id];
    }

    /**
     * Cleans up all autocomplete instances that no longer exist.
     */
    static clean_instances() {
        let toClean = [];
        for (var key in H_ui_autocomplete.instances) {
            if (!H_ui_autocomplete.instances[key].exist()) {
                H_ui_autocomplete.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key) => {
            delete (H_ui_autocomplete.instances[key]);
        });
    }
}
h.libs.ui_autocomplete = H_ui_autocomplete;
window.H_ui_autocomplete = H_ui_autocomplete;

/**
 * @class H_ui_multi_state
 * @classdesc
 * UI class for multi-state toggle buttons.<br>
 * Handles avatar, toggling, and active state.<br>
 * Used as h.libs.ui_multi_state.
 * 
 * @param {string} dom_id dom_id for the created multi_state button
 * @param {string} side where is should display the list of buttons.
 * @param {boolean} [toogle_mode=false] is true, stay open.
 */
class H_ui_multi_state {
    constructor(dom_id, side = '', toggle_mode = false) {
        this.buttons = [];
        this.container, this.toggler, this.active, this.toggle;
        this.toggle_mode = toggle_mode;
        this.avatar_active = false;
        this.sided = (side != '') ? true : false;
        this.side = side;
        this.opened = false;

        this.dom_id = dom_id;
        this.avatar = false;

        this.container = document.getElementById(this.dom_id);

        // the element to click on for opening
        this.toggler = document.getElementById(this.dom_id + '-toggler');

        // the element to toggle when opening
        this.toggle = document.getElementById(this.dom_id + '-toggle');

        for (let i = 0; i < this.toggle.children.length; i++) {
            let btn = this.toggle.children[i];
            // add on every buttons the close event
            h.e.add_event_click(btn, this.on_click_btn.bind(this));
            if (!this.toggle_mode) { // no active by default in toggle mode
                if (H_dom.has_class(btn, 'state_active')) {
                    // the currently selected item
                    this.active = btn;
                }
            } else { // but need to add the first item to active for creating the avatar
                this.active = this.buttons[0];
            }
            H_dom.add_class(this.active, 'current_avatar');

            this.buttons.push(btn);
        }

        if (this.sided) {
            // move the element to toggle as a child of document for him to be in front of every other elements (like a modal)
            H_dom.append_content(document.body, this.toggle);
        }

        this.change_avatar();
        this.toggle_buttons();
    }
    /**
     * Handles toggle display buttons.
     * @param {Event} evt - The click event.
     */
    on_click_toggler(evt) {
        if (!this.opened) {
            // show every buttons
            this.toggle_buttons(false);
            // hide toggler
            this.toggler.style.display = 'none';
            this.opened = true;
        } else {
            this.on_close();
        }
    }
    /**
     * Handles click on button and switching state active and set current avatar.<br>
     * @param {Event} evt - The click event.
     */
    on_click_btn(evt, btn = false) {
        let targ = (btn !== false) ? btn : evt.target;
        if (targ.dataset.no_toggle) {
            this.on_close();
            return;
        }
        if (this.toggle_mode) {
            if (H_dom.has_class(targ, 'state_active')) {
                H_dom.remove_class(targ, 'state_active');
            } else {
                H_dom.add_class(targ, 'state_active');
            }
        } else {
            if (targ != this.active) {
                // remove class from previous
                H_dom.remove_class(this.active, 'current_avatar');
                H_dom.remove_class(this.active, 'state_active');

                this.active = targ;
                H_dom.add_class(this.active, 'state_active');
                H_dom.add_class(this.active, 'current_avatar');
                this.change_avatar();
            }
            this.on_close();
        }
    }
    /**
     * Toggle buttons and hide the toggler.
     */
    on_close() {
        this.opened = false;
        this.toggler.style.display = '';
        this.toggle_buttons();
    }

    /**
     * Toggle every button except the active one.
     * @param {boolean} [hide=true] false if you want to keep toogler open 
     */
    toggle_buttons(hide = true) {
        if (hide) {
            this.toggle.style.display = 'none';
            if (!this.sided) this.avatar_active.style.display = '';
            h.e.remove_event_click_outside(this.toggle, this.on_click_outside.bind(this));
        } else {
            this.toggle.style.display = '';
            if (!this.sided) this.avatar_active.style.display = 'none';
            else {
                let rect = H_dom.get_global_rect(this.container, true);
                let rect_toggle = H_dom.get_global_rect(this.toggle, true);
                let dif_width = rect_toggle.width - rect.width;
                switch (this.side) {
                    case 'left':
                        this.toggle.style.top = Math.round(rect.top) + 'px';
                        this.toggle.style.left = Math.round(rect.left - rect_toggle.width) + 'px';
                        break;
                    case 'right':
                        this.toggle.style.top = Math.round(rect.top) + 'px';
                        this.toggle.style.left = Math.round(rect.right) + 'px';
                        break;
                    case 'top':
                        //~ let rect_toggle = H_dom.get_global_rect(toggle,true);
                        if (dif_width >= 0) {
                            this.toggle.style.left = Math.round(rect.left - (dif_width / 2)) + 'px';
                        } else {
                            this.toggle.style.left = Math.round(rect.left + (dif_width / 2)) + 'px';
                        }
                        this.toggle.style.bottom = Math.round(rect.top) + 'px';
                        break;
                    case 'down':
                        this.toggle.style.left = Math.round(rect.left - (dif_width / 2)) + 'px';
                        this.toggle.style.top = Math.round(rect.bottom) + 'px';
                        break;
                }
            }
            h.e.add_event_click_outside(this.toggle, this.on_click_outside.bind(this));
        }
    }

    /**
     * If the click is on toggler, do nothing.<br>
     * the function that handle the click on the toggler will trigger the close<br>
     * this is like a clickOutside except "this" element<br>
     * @param {event} evt 
     */
    on_click_outside(evt) {
        if (evt.target != this.avatar_active) {
            this.on_close();
        }
    }

    /**
     * Take the active element to create an avatar<br>
     * Put the avatar as the toggler.
     */
    change_avatar() {
        // remove old avatar
        let old_avatar = false;
        if (this.avatar_active) {
            old_avatar = this.avatar_active;
        }

        this.avatar_active = this.active.cloneNode();
        this.avatar_active.id += 'clone';
        this.avatar_active.innerHTML = this.active.innerHTML;
        H_dom.add_class(this.avatar_active, 'avatar');
        H_dom.remove_class(this.avatar_active, 'current_avatar');
        // to remove if we add the automatic add_event for onclick
        if (this.avatar_active.onclick) this.avatar_active.onclick = '';
        if (old_avatar) {
            H_dom.replace_element(old_avatar, this.avatar_active);
        } else {
            H_dom.replace_element(this.toggler, this.avatar_active);
        }

        h.e.add_event_click(this.avatar_active, this.on_click_toggler.bind(this));

        this.avatar = this.avatar_active;
    }
    /**
     * Click on next button (so it will display it).
     * @param {boolean} loop if true when the last button is the current, it will loop to first one.
     */
    next(loop = false) {
        let nextBtn = this.active.nextElementSibling;
        if (nextBtn) {
            h.e.send_event_click(nextBtn);
        } else if (loop) {
            h.e.send_event_click(this.buttons[0]);
        }
    }
    /**
     * Click on previous button (so it will display it).
     * @param {boolean} loop if true when the first button is the current, it will loop to last one.
     */
    prev(loop = false) {
        let prevBtn = this.active.previousElementSibling;
        if (prevBtn) {
            h.e.send_event_click(prevBtn);
        } else if (loop) {
            h.e.send_event_click(this.buttons[this.buttons.length - 1]);
        }
    }

    /**
     * Set current avatar by clicking on the corresponding button indicated by its dom_id.
     * 
     * @param {string} dom_id of the button we want to select/click on
     */
    set_avatar(dom_id = '') {
        this.buttons.forEach((btn) => {
            if (btn.id == dom_id) {
                this.on_click_btn(false, btn);
            }
        });
    }

    /**
     * Still alive ?
     */
    exist(){
        let exist = document.getElementById(this.dom_id);
        return exist ? true : false;
    }

    /**
     * thanks for your job, you're fired !
     */
    clean() {
        if (this.sided) {
            H_dom.remove_element(this.toggle);
        }
    }

    /**
     * Stores all multi_state instances.
     * @type {Object}
     */
    static instances = {};

    /**
     * Creates a new multi_state instance for a given dom_id.<br>
     * Cleans up any existing instance for the same dom_id.
     * @param {string} dom_id - The DOM id suffix.
     * @param {Object} settings - Settings for the instance.
     * @returns {H_ui_autocomplete} The created instance.
     */
    static create_instance(dom_id, side = '', toggle_mode = false) {
        if (H_ui_multi_state.instances[dom_id]) {
            H_ui_multi_state.instances[dom_id].clean();
            delete (H_ui_multi_state.instances[dom_id]);
        }
        H_ui_multi_state.instances[dom_id] = new H_ui_multi_state(dom_id, side, toggle_mode);

        H_ui_multi_state.clean_instances();
    }
    /**
     * clean all instances that have disapeared... 
     */
    static clean_instances() {
        let toClean = [];
        for (var key in H_ui_multi_state.instances) {
            if (!H_ui_multi_state.instances[key].exist()) {
                H_ui_multi_state.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key) => {
            delete (H_ui_multi_state.instances[key]);
        });
    }
}
h.libs.ui_multi_state = H_ui_multi_state;
window.H_ui_multi_state = H_ui_multi_state;

/**
 * Creates an instance of H_ui_context_menu.<br>
 * a custom UI context menu with dynamic buttons.
 * Possible settings: <br>
 * - buttons (Array): List of button elements to include in the menu<br>
 * - open_callback (string|function): Function name to call when menu opens<br>
 * - hide_callback (string|function): Function name to call when menu hides<br>
 * - class (string): Additional CSS class for styling<br>
 * Used as h.libs.ui.contex_menu
 * @param {string} dom_id - The DOM element ID for the menu container.
 * @param {Object} settings - Configuration object for the context menu.
 */
class H_ui_context_menu {
    constructor(dom_id, settings) {
        
        this.dom_id = dom_id;

        this.buttons = settings.buttons;

        this.open_callback = (settings.open_callback) ? settings.open_callback : false;
        this.hide_callback = (settings.hide_callback) ? settings.hide_callback : false;

        let modal_settings = {
            modal: false,
            disable_background_click: true,
            class: 'hlp_context_menu',
            nodrag: true,
            hidden: true,
            disable_auto_resize: true,
            special: true
        };
        if (settings.class) modal_settings.class += ' ' + settings.class;

        this.modal = new H_ui_window(modal_settings);
        this.modal.dom_element.style.zIndex = '1000';
        this.content = H_dom.create_element('DIV', { 'id': dom_id, 'class': 'hlp_context_menu' });
        this.buttons.forEach((btn) => {
            if (btn.dataset && btn.dataset.child == 'true') {
                h.e.add_event_click(btn, this.toggle_child.bind(this));
            } else {
                h.e.add_event_click(btn, () => { this.modal.hide(); });
            }
            H_dom.append_content(this.content, btn);
        });
        this.modal.set_content(this.content);
        this.modal.set_parent(document.body, 0.5, 0.5);
    }

    /**
     * Opens the context menu at the event's position.<br>
     * Triggers the open callback if defined.
     * @param {Event} evt - The event triggering the context menu.
     */
    open(evt){
        h.e.stop_event(evt);

        if (this.open_callback) H_generics.execute_function_by_name(this.open_callback, window, evt);

        if (evt.pointerType == 'touch') {
            return false;
        }

        this.modal.dom_element.style.visibility = 'hidden';

        this.modal.show();

        let rect = H_dom.get_global_rect(this.modal.dom_element, true);
        let parent_rect = H_dom.get_global_rect(this.modal.dom_element.parentElement, true);

        // verify if it's smaller than the window
        let display = false;
        if (parent_rect.height > rect.height) display = true;

        // check if position is fixed in case need to add the scroll to the posisiton
        // let position_fixed = this.modal.dom_element._computed_style.POS == 'fixed';

        // verify if it's going outside the window
        let left = evt.clientX - window.scrollX;
        // if (!position_fixed) left += window.scrollX;
        let right = left + rect.width;
        if (right < parent_rect.width) {
            this.modal.dom_element.style.left = left + 'px';
        } else {
            this.modal.dom_element.style.left = (left - rect.width) + 'px';
        }

        let top = evt.clientY - window.scrollY;
        // if (!position_fixed) top += window.scrollY;
        let bottom = top + rect.height;
        if (bottom < parent_rect.height) {
            this.modal.dom_element.style.top = top + 'px';
        } else if ((top - rect.height) > parent_rect.top) {
            this.modal.dom_element.style.top = (top - rect.height) + 'px';
        } else {
            this.modal.dom_element.style.top = (parent_rect.height - rect.height - 1) + 'px';
        }

        this.modal.dom_element.style.visibility = 'visible';
        h.e.add_event_click_outside(this.modal.dom_element, this.hide.bind(this));
    }

    /**
     * Hides the context menu.<br>
     * Triggers the hide callback.
     */
    hide(evt){
        this.modal.hide();
        h.e.remove_event_click_outside(this.modal.dom_element, this.hide.bind(this));
        if (this.hide_callback) H_generics.execute_function_by_name(this.hide_callback, window, evt);
    }

    /**
     * Toggles the visibility of a child menu when a parent button is clicked.
     * @param {Event} evt - The click event on the parent menu item.
     */
    toggle_child(evt){
        let targ = evt.target;
        for (let i = 0; i < targ.children.length; i++) {
            H_dom.remove_class(targ.children[i], 'hidden');
        }
        h.e.add_event_click_outside(targ, this.hide_child.bind(this));
    }
    /**
     * Hide the child of the current evt.target<br>
     * @param {Event} evt - The click event on the item.
     */
    hide_child(evt){
        let targ = evt.target;
        for (let i = 0; i < targ.children.length; i++) {
            H_dom.remove_class(targ.children[i], 'hidden');
        }
        h.e.remove_event_click_outside(targ, this.hide_child.bind(this));
    }
    /**
     * Still alive ?
     * @returns {boolean} true if it's the case
     */
    exist(){
        let exist = document.getElementById(this.dom_id);
        return (exist) ? true : false;
    }
    /**
     * Sarah connord ?
     */
    clean(){
        this.modal.remove();
    }
   /**
     * Stores all context_menu instances.
     * @type {Object}
     */
    static instances = {};

     /**
     * Creates a new context_menu instance for a given dom_id.<br>
     * Cleans up any existing instance for the same dom_id.
     * @param {string} dom_id - The DOM id suffix.
     * @param {Object} settings - Settings for the instance.
     * @returns {H_ui_context_menu} The created instance.
     */
    static create_instance(dom_id, settings) {

        if (H_ui_context_menu.instances[dom_id]) {
            H_ui_context_menu.instances[dom_id].clean();
            delete (H_ui_context_menu.instances[dom_id]);
        }
        H_ui_context_menu.instances[dom_id] = new H_ui_context_menu(dom_id, settings);

        H_ui_context_menu.clean_instances();

        return H_ui_context_menu.instances[dom_id];
    }
    /**
     * Sarah connord family ?
     */
    static clean_instances() {
        let toClean = [];
        for (var key in H_ui_context_menu.instances) {
            if (!H_ui_context_menu.instances[key].exist()) {
                H_ui_context_menu.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key) => {
            delete (H_ui_context_menu.instances[key]);
        });
    }
}
h.libs.ui_context_menu = H_ui_context_menu;
window.H_ui_context_menu = H_ui_context_menu;

/**
 * Displays and manages the UI for tracking upload progress.<br>
 * Creates progress bars and displays file names, sizes, and percentages.
 */
// class H_ui_upload_progress {
//     constructor(files, parent, dom_id) {

//         this.number_files;
//         this.total_percent;
//         this.speed;
//         this.total_bar_progress;

//         this.btn_stop;
//         this.btn_play;

//         this.files = {
//             'dom': false,
//             'lst': [],
//             'hidden': true
//         };

//         this.prev_request = false;

//         this.show(files, parent, dom_id);
//     }

//     static tl = [];
            /**
    //  * Displays the upload progress UI with provided files.<br>
    //  * Initializes DOM elements for each file's progress.
    //  * @param {File[]} files - Files being uploaded.
    //  * @param {HTMLElement} [parent=false] - Optional parent container.
    //  * @param {string} [dom_id=''] - Unique DOM identifier for the upload modal.
    //  */
//     show(files, parent = false, dom_id = '') {
//             if (!files) {
//                 return;
//             }

//             if (!H_ui.popup_special['upload_' + dom_id]) {
//                 H_ui.popup_special['upload_' + dom_id] = new H_ui_window({
//                     'modal': false,
//                     'nodrag': true,
//                     'special': true,
//                     'class': 'upload_progress',
//                     'hidden': true
//                 });

//                 let content = [];
//                 let total = H_dom.create_element('DIV', { 'class': 'upload_total' });
//                 let label = H_dom.create_element('SPAN', { 'class': 'upload_total_label' }, H_ui_upload_progress.tl['upload_ttl']);
//                 this.number_files = H_dom.create_element('SPAN', { 'id': 'fileupload_total_files' });
//                 this.total_percent = H_dom.create_element('SPAN', { 'id': 'fileupload_percent' }, '0%');
//                 this.speed = H_dom.create_element('SPAN', { 'id': 'fileupload_speed' });
//                 this.total_bar_progress = H_dom.create_element('DIV', { 'class': 'filemanager_progress_upload_total_bar_progress', 'id': 'fileupload_bar_progress' });
//                 let total_bar = H_dom.create_element('DIV', { 'class': 'filemanager_progress_upload_total_bar', 'id': 'fileupload_bar' }, this.total_bar_progress);
//                 H_dom.append_content(total, [label, this.number_files, this.total_percent, this.speed, total_bar]);

//                 let btn_cancel = H_dom.create_element('BUTTON', { 'class': 'upload_btn_cancel' }, 'cancel');

//                 H_dom.append_content(total, btn_cancel);
//                 content.push(total);

//                 this.files.dom = H_dom.create_element('DIV', { 'id': 'fileupload_block_files' });
//                 content.push(this.files.dom);

//                 H_ui.popup_special['upload_' + dom_id].set_content(content);
//                 if (!parent) H_ui.popup_special['upload_' + dom_id].set_parent(document.body);
//                 else H_ui.popup_special['upload_' + dom_id].set_parent(parent);

//                 h.e.add_event_click(total, this.toggle_file_block.bind(this));
//                 h.e.add_event_click(btn_cancel, this.cancel.bind(this));
//             }

//             H_dom.add_class(this.files.dom, 'hidden');
//             this.files.hidden = true;

//             files.forEach((file, index) => {
//                 let filename = file.webkitRelativePath != '' ? file.webkitRelativePath : file.name;
//                 this.files.lst[filename] = {};

//                 let line = H_dom.create_element('DIV', { 'class': 'upload_file' });
//                 let name = H_dom.create_element('SPAN', { 'class': 'upload_file_name' }, filename);
//                 let size = H_dom.create_element('SPAN', { 'class': 'upload_file_size' }, H_ajax.readable_file_size(file.size));
//                 this.files.lst[filename].percent = H_dom.create_element('SPAN', { 'class': 'upload_file_percent' }, '0%');
//                 this.files.lst[filename].bar_progress = H_dom.create_element('DIV', { 'class': 'upload_file_bar_progress' });
//                 let bar = H_dom.create_element('DIV', { 'class': 'upload_file_bar' }, this.files.lst[filename].bar_progress);
//                 H_dom.append_content(line, [name, size, this.files.lst[filename].percent, bar]);
//                 H_dom.append_content(this.files.dom, line);
//             });

//             H_ui.popup_special['upload_' + dom_id].show();
//         }

            // /**
            //  * Hides the upload progress UI and resets internal state.
            //  */
//         hide() {
//             H_ui.popup_special['upload_' + dom_id].remove();
//         }
        // /**
        //  * Toggles visibility of the uploaded file list.
        //  * @param {Event} evt - The click event.
        //  */
//         toggle_file_block(evt) {
//             if (this.files.hidden) {
//                 this.update_current_file();
//                 H_dom.remove_class(this.files.dom, 'hidden');
//                 this.files.hidden = false;
//             } else {
//                 H_dom.add_class(this.files.dom, 'hidden');
//                 this.files.hidden = true;
//             }
//         }
//         update(res, req) {
//             if (!req.speed) return;

//             if (!this.prev_request || this.prev_request.file_sended != req.file_sended) {
//                 this.number_files.textContent = (req.file_sended + 1) + '/' + req.file_count;
//             }

//             let percent = Math.round(req.all.progress * 100) + '%';
//             this.total_percent.textContent = percent;
//             this.speed.textContent = '(' + H_ajax.readable_file_size(req.speed) + '/s )';
//             this.total_bar_progress.style.width = percent;

//             this.prev_request = Object.assign({}, req);

//             if (!this.files.hidden) this.update_current_file();
//         }
//         update_current_file() {
//             let req = this.prev_request;
//             let filename = req.current.name;
//             let current_percent = Math.round(req.current.progress * 100) + '%';
//             this.files.lst[filename].percent.textContent = current_percent;
//             this.files.lst[filename].bar_progress.style.width = current_percent;
//         }
//         stop(evt) {
//             if (this.prev_request) {
//                 this.prev_request.pause_file();
//                 H_dom.remove_class(this.btn_play, 'hidden');
//             }
//         }
//         play(evt) {
//             if (this.prev_request) {
//                 this.prev_request.unpause_file();
//                 H_dom.add_class(this.btn_play, 'hidden');
//                 H_dom.remove_class(this.btn_stop, 'hidden');
//             }
//         }
                /**
            //  * Cancels the upload process.<br>
            //  * This should abort any active uploads (logic to be implemented).
            //  * @param {Event} evt - The cancel button click event.
            //  */
//         cancel(evt) {
//             if (this.prev_request) {
//                 this.prev_request.cancel_file(() => {
//                     this.hide();
//                 });
//             }
//         }
// }
// h.libs.ui_upload_progress = H_ui_upload_progress;

/**
 * Creates a "precomplete" input with selectable and filterable options.<br>
 * the difference with "autocomplete", is that the possible data are given to the widget at start<br>
 * and not researched in DB after each keystroke.<br>
 * Supports keyboard navigation, callbacks, and value confirmation.<br>
 * Possible settings:<br>
 * - name (string): Unique name for internal IDs<br>
 * - submit (boolean): Whether to auto-submit form on selection<br>
 * - confirm (string): Optional confirmation message before submission<br>
 * - callback (string|function): Callback to trigger after selection<br>
 * - data (Array): List of selectable data objects with `id` and `name`<br>
 * - new_value (boolean): Allow typing custom value not in list<br>
 * - toreturn (boolean): Return selected ID instead of text value<br>
 * @param {string} dom_id dom_id for the created precomplete.
 * @param {*} settings of the precomplete widget.
 */
class H_ui_precomplete {
    constructor(dom_id, settings) {
        this.dom_id = dom_id;

        this.is_open = false; // state of the field
        this.active = false; // the current selected item

        this.name = (settings.name) ? settings.name : '';
        this.submit = (settings.submit) ? settings.submit : false;
        this.confirm = (settings.confirm) ? settings.confirm : false;
        this.callback = (settings.callback) ? settings.callback : false;

        this.data = (settings.data) ? settings.data : false;

        this.base_id = 'precomplete_' + this.name + this.dom_id + '_';

        //let entering a new value 
        this.new_value = settings.new_value ?? false;
        //indicates that the input will return the id of the selected value
        //do not use this if you want to return the text value with new_value
        this.toreturn = settings.toreturn ?? false;

        // field that save the current selected id, the one used for saving in db
        this.current_id = document.getElementById(this.base_id + 'current_id');

        this.input_search = document.getElementById(this.base_id + 'input_search');
        h.e.add_event_key(this.input_search, this.key_navigation.bind(this));
        h.e.add_event(this.input_search, 'input', this.on_input.bind(this));
        h.e.add_event(this.input_search, 'focus', this.show.bind(this));

        this.dom_list = document.getElementById(this.base_id + 'list_search');
        this.dom_list.other_inside_elements = this.input_search; // elements when clicked on don't close the list (validatorDiv parent, precomplete search block, label before)

        
        if (this.data) {
            this.data.forEach((row, index) => {
                row.visible = true;
                row.dom_elem = document.getElementById(this.base_id + 'row_' + index);
                h.e.add_event_click(row.dom_elem, this.on_click_row.bind(this));
                // this.keep_focus_elem.push(row.dom_elem);
                h.e.add_event(row.dom_elem, 'mousemove', (event) => { this.add_active(event.target); });
            });
        }
        
    }
    /** Handles click on a suggestion row.
     * @param {Event} evt - Click event.
     * @param {string|number} [new_value=false] - Optional custom value to assign.
     */
    on_click_row(evt, new_value = false){

        this.close();

        if (!new_value) {
            let index = evt.target.dataset.key;
            this.current_id.value = this.data[index].id; // change the value in the hidden field (the submitted one)
            this.input_search.value = this.data[index].name; // change the value in the input field
        } else {
            this.current_id.value = new_value;
        }

        if (this.confirm) {
            H_ui.confirm_popup(this.confirm, () => {
                if (this.submit) H_event.send_event(this.input_search.form, 'submit');
                this.on_input();
            });
        } else {
            if (this.submit) H_event.send_event(this.input_search.form, 'submit');
            this.on_input();
        }

        if (this.callback) H_generics.execute_function_by_name(this.callback, window, parseInt(this.current_id.value), this.input_search.value);
    }

    /**
     * Filters the list based on user input.
     * @param {boolean} [open=true] - Whether to reopen the dropdown.
     */
    on_input(){
        let val = this.input_search.value;

        // if (val.length > 1) {
        this.data.forEach((row) => {
            if (val == '' || (row.name && row.name.includes(val))) {
                row.visible = true;
                H_dom.remove_class(row.dom_elem, 'hidden');
            } else {
                row.visible = false;
                H_dom.add_class(row.dom_elem, 'hidden');
            }
        });
    }

    /**
     * Shows the suggestion list.
     */
    show(){
        if (this.is_open) return;

        H_dom.remove_class(this.dom_list, 'hidden');
        h.e.add_event_click_outside(this.dom_list, this.close.bind(this));
        this.is_open = true;
    }

    /**
     * Hides the suggestion list.
     */
    close(){
        if (!this.is_open) return;

        H_dom.add_class(this.dom_list, 'hidden');
        h.e.remove_event_click_outside(this.dom_list, this.close.bind(this));
        this.is_open = false;
        this.remove_active();
    }

    /**
     * Handles keyboard navigation for the dropdown.
     * @param {KeyboardEvent} evt - The key event.
     */
    key_navigation(evt){
        this.show();
        if (this.is_open) {
            let item;
            switch (evt.key) {
                case 'ArrowDown':
                case 'Tab':
                    item = this.get_next_item();
                    if (item) {
                        this.add_active(item.dom_elem);
                        this.scroll_to_active();
                    }
                    break;
                case 'ArrowUp':
                    item = this.get_previous_item();
                    if (item) {
                        this.add_active(item.dom_elem);
                        this.scroll_to_active();
                    }
                    break;
                case 'Enter':
                    if (this.active) h.e.send_event_click(this.active);
                    else if (this.new_value) {
                        this.on_click_row(evt, this.input_search.value);
                    }
                    break;
                default:
                    this.current_id.value = this.input_search.value;
                    if (this.new_value){
                        // display add button
                        this.display_add_button();
                    }
                    break;
            }
        }
    }

    /**
     * Sets a list item as the active selection.
     * @param {HTMLElement} elem - Element to activate.
     */
    add_active(elem){
        this.remove_active();
        this.active = elem;
        H_dom.add_class(this.active, 'hlp_precomplete_active');
    }

    /**
     * Removes the currently active item.
     */
    remove_active(){
        if (this.active) {
            H_dom.remove_class(this.active, 'hlp_precomplete_active');
            this.active = false;
        }
    }

    /**
     * Scrolls the list to bring the active item into view iff needed.
     */
    scroll_to_active(){
        let parent = this.active.parentElement;
        let rect_active = H_dom.get_global_rect(this.active, true);
        let rect_parent = H_dom.get_global_rect(parent, true);

        if (rect_active.bottom > rect_parent.bottom) {
            this.active.scrollIntoView(false);
        }

        if (rect_active.top < rect_parent.top) {
            this.active.scrollIntoView(true);
        }
    }

    /**
     * Gets the next visible list item.
     * @returns {Object|false} Next visible row object or false if none.
     */
    get_next_item(){
        let next = false;
        let index = (this.active) ? this.active.dataset.key : -1; // active item to the first one if not already set
        let checked = [index];

        while (next === false && checked.length != this.data.length) {
            index++;
            if (checked.indexOf(index) == -1) {
                if (index > this.data.length) index = 0; // back to the start if no more item
                if (this.data[index] && this.data[index].visible) {
                    next = this.data[index];
                }
                checked.push(index);
            }
        }

        return next;
    }

    /**
     * Gets the previous visible list item.
     * @returns {Object|false} Previous visible row object or false if none.
     */
    get_previous_item(){
        if (!this.active) this.active = this.data.length + 1;

        let previous = false;
        let index = (this.active) ? this.active.dataset.key : this.data.length + 1; // active item to the last one if not already set
        let checked = [index];

        while (previous === false && checked.length != this.data.length) {
            index--;
            if (checked.indexOf(index) == -1) {
                if (index == -1) index = this.data.length; // go to the end if first item passed
                if (this.data[index] && this.data[index].visible) {
                    previous = this.data[index];
                }
                checked.push(index);
            }
        }

        return previous;
    }
    /**
     * Displays a button to add a new value.
     */
    display_add_button(){
        if (!this.add_button){
            this.add_button = H_dom.create_element('DIV', {'class': 'hlp_precomplete_add_btn hidden'});
            let use = H_dom.create_element('SVG_USE', {'href': H_constants.base_url + 'images/icons/feather-sprite.svg#plus-circle'});
            H_dom.create_element('SVG', {class: 'hlp_icon', style: 'pointer-events: none;'}, use, this.add_button);
            H_dom.insert_before(this.add_button, this.input_search);
            h.e.add_event_click(this.add_button, (evt)=>{
                this.on_click_row(evt, this.input_search.value);
            });
        }

        H_dom.remove_class(this.add_button, 'hidden');
    }
    hide_add_button(){
        H_dom.add_class(this.add_button, 'hidden');
    }

    clean() {

    }
    exist() {
        let exist = document.getElementById(this.input_search.id);
        return exist ? true : false;
    }
    /**
     * Stores all precomplete instances.
     * @type {Object}
     */
    static instances = {};

    /**
     * Creates a new precomplete instance for a given dom_id.<br>
     * Cleans up any existing instance for the same dom_id.
     * @param {string} dom_id - The DOM id suffix.
     * @param {Object} settings - Settings for the instance.
     * @returns {H_ui_precomplete} The created instance.
     */
    static create_instance(dom_id, settings) {
        if (H_ui_precomplete.instances[dom_id]) {
            H_ui_precomplete.instances[dom_id].clean();
            delete (H_ui_precomplete.instances[dom_id]);
        }
        H_ui_precomplete.instances[dom_id] = new H_ui_precomplete(dom_id, settings);

        H_ui_precomplete.clean_instances();

        return H_ui_precomplete.instances[dom_id];
    }
    static clean_instances() {
        let toClean = [];
        for (var key in H_ui_precomplete.instances) {
            if (!H_ui_precomplete.instances[key].exist()) {
                H_ui_precomplete.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key) => {
            delete (H_ui_precomplete.instances[key]);
        });
    }
}
h.libs.ui_precomplete = H_ui_precomplete;
window.H_ui_precomplete = H_ui_precomplete;

/**
 * Represents a dynamic UI layout with draggable resizable containers.<br>
 * that's usefull to present multiple modules in the same screen to compose WebUI disposition for a webapp
 * Possible settings:<br>
 * - containers (Array): DOM element IDs to be added as resizable panes<br>
 * - parent (string): DOM ID of the parent wrapper<br>
 * - type (string): Layout direction, either 'vertical' or 'horizontal'<br>
 * - open (boolean): Whether the containers start in open state<br>
 * @param {string} dom_id dom_id for the created disposition
 * @param {array} settings of the different containers
 */
class H_ui_disposition {
    constructor(dom_id, settings) {
        this.dom_id = dom_id;

        this.global_container;      // the parent element of all the containers and resizer
        this.containers = new Map(); // map of all the elements that need to be resized when moving a separator
        this.separators = [];        // array of all the separator to speed up their access
        this.current_index = 0;

        this.observer = false;

        this.type;

        this.current_drag_index = false;
        this.drag_interval = false;

        if (!settings.containers){
            console.error('need at least one containers in settings');
            return;
        }
        settings.open = settings.open ? 'open' : 'close' ;
        this.type = settings.type ? settings.type : 'vertical';

        // start by the resize observer because it will be used by following functions
        if (!this.observer){
            this.observer = new ResizeObserver(entries => {
                let global_resize=false;
                for (let entry of entries) {
                    if (entry.target == this.global_container){
                        global_resize = true;
                        this.resize_separators();
                    }
                }
            });
        }

        if (settings.parent){
            let parent = document.getElementById(settings.parent);
            this.create_parent(parent);
        }
        
        if (settings.containers){
            let sep = false;
            settings.containers.forEach((id,index)=>{
                let setts = {
                    'open': settings.open,
                    'separator': index > 0 ? true : false,
                    'no_resize': true
                };
                this.add_container(id, setts);
            });

            this.resize_all();

            if (this.type == 'vertical'){
                this.global_container.style.display='flex';
            }
        }
    }
    create_parent(parent){
        let dom_elem = H_dom.create_element('DIV', {'class': 'hlp_disposition_parent', 'id': 'hlp_disposition_container'+this.dom_id});
        H_dom.append_content(parent,dom_elem);
        this.global_container = dom_elem;

        this.observer.observe(this.global_container);
    }

    create_separator(open){
        let css_class = 'hlp_separator ' + this.type + ' ' + (open ? 'open' : 'closed');
        let dom_elem = H_dom.create_element('DIV', {'class': css_class});
            let switch_type = H_dom.create_icon('columns', {'class': 'hlp_separator_switch_type ' + this.type});
            let toggle = H_dom.create_icon('eye-off', {'class': 'hlp_separator_toggle ' + (open ? 'open' : 'close')});
            let remove = H_dom.create_icon('trash-2', {'class': 'hlp_separator_remove'});
        H_dom.append_content(dom_elem, [switch_type, toggle, remove]);

        let index = this.current_index;
        dom_elem.dataset.ui_d_i = this.current_index;

        // create 2 overlay positioned on each side of the draggable bar when moving it the cursor is upon those div
        // and not upon an element like iframe that stop the movement.
        css_class = 'hlp_separator_overlay hidden ' + this.type + ' ' + (open ? 'open' : 'closed');
        let overlay = H_dom.create_element('DIV', {'class': css_class});

        let obj = {};
        obj.dom_elem = dom_elem;
        obj.index = index;
        obj.is_separator = true;
        obj.state = open ? 'open' : 'closed';
        obj.overlay = overlay;
        obj.btn = {
            switch_type,
            toggle,
            remove
        };

        this.containers.set(index, obj);
        this.separators.push(index);
        this.current_index++;

        // H_dom.insert_after(dom_elem,containers.get(index-1).dom_elem);
        let prev = this.previous_container(obj);
        H_dom.insert_after(dom_elem, prev.dom_elem);
        H_dom.insert_after(overlay, dom_elem);

        h.e.add_event_click(switch_type, this.switch_type_all.bind(this));
        h.e.add_event_click(toggle, (event)=>{this.toggle_separator(event, obj);});
        h.e.add_event_click(remove, ()=>{this.remove_separator(obj);});

        this.enable_drag(obj);

        return obj;
    }
    remove_separator(obj){
        let container_after = this.containers.get(obj.index + 1);
        let container_before = this.previous_container(obj);

        // force full size resize on remaining container
        container_before.dom_elem.style.width = '';
        container_before.dom_elem.style.height = '';
        
        // remove container and separator
        this.remove_container(container_after);
        this.remove_container(obj);

        this.separators.splice(this.separators.indexOf(obj.index), 1);

        if (this.separators.length == 0){
            // no more separator, removed the last container
            this.clean();
        }
    }
    resize_separators(){
        this.separators.forEach((i)=>{
            let sep = this.containers.get(i);
            if(sep.state == 'closed'){
            }
            this.resize_container(sep);
        });
    }
    add_container(dom_elem, settings){
        if (H_generics.is_string(dom_elem)){
            dom_elem = document.getElementById(dom_elem);
        } else if (settings.append){
            H_dom.append_content(this.global_container, dom_elem);
        }

        settings.open = settings.open ? settings.open : 'open';
        settings.min_height = settings.min_height ? settings.min_height : 200;
        settings.min_width = settings.min_width ? settings.min_width : 200;
        if (settings.separator){
            this.create_separator(settings.open);
        }

        let index = this.current_index;
        dom_elem.dataset.ui_d_i = index;

        let obj = {};
        obj.index = index;
        obj.dom_elem = dom_elem;
        obj.is_separator = false;
        obj.state = settings.open;
        obj.min_width = settings.min_width;
        obj.min_height = settings.min_height;

        this.containers.set(index, obj);
        this.current_index++;

        // add css class
        H_dom.add_class(dom_elem, 'hlp_disposition_open');

        // first container added, save the global container (parent)
        if (index == 0 && !this.global_container){
            this.create_parent(dom_elem.parentElement);
        }
        // move dom_elem to the parent disposition
        if (obj.dom_elem.parentElement != this.global_container) H_dom.append_content(this.global_container, obj.dom_elem);
        
        // add to resize observer
        this.observer.observe(obj.dom_elem);

        if (!settings.no_resize) this.resize_all();
    }
    remove_container(obj){
        H_dom.remove_element(obj.dom_elem);
        if (obj.overlay) H_dom.remove_element(obj.overlay);
        this.containers.delete(obj.index);
    }
    enable_drag(sep){
        H_event.enable_drag(sep.dom_elem, 
            (event)=>{this.on_start_drag(event, sep);},
            (event)=>{this.on_drag(event, sep);},
            (event)=>{this.on_end_drag(event, sep);}
        );
        h.e.add_event(sep.dom_elem,'touchstart',(event)=>{
            event.preventDefault();
            this.on_start_drag(event, sep);
        });
        h.e.add_event(sep.dom_elem,'touchmove',(event)=>{
            event.preventDefault();
            this.on_drag(event, sep);
        });
        h.e.add_event(sep.dom_elem,'touchend',(event)=>{
            event.preventDefault();
            this.on_end_drag(event, sep);
        });
    }
    disable_drag(dom_elem){
        H_event.disable_drag(dom_elem);
    }
    // obj is the separator
    on_start_drag(event, obj){
        this.current_drag_index = obj.index;
        // to protect the move from iframe and video or other element that interupt the record of the moving cursor
        H_dom.remove_class(obj.overlay, 'hidden');
        // add on each side of the separator a transparent div to let the cursor move thew it when moving.

    }
    on_drag(event, obj){
        if (!this.drag_interval && this.current_drag_index){
            // console.log(event);
            this.resize_container(obj);
            this.drag_interval = setInterval(this.clear_drag_interval.bind(this), 15);
        }
    }
    clear_drag_interval(){
        clearInterval(this.drag_interval);
        this.drag_interval = false;
    }
    on_end_drag(event, obj){
        this.current_drag_index = false;
        H_dom.add_class(obj.overlay, 'hidden');
    }
    resize_all(){
        this.separators.forEach((index)=>{
            if (this.separators.state == 'open') this.resize_container(this.containers.get(index));
        });
    }
    resize_container(sep){
        let container_before = this.previous_container(sep);
        let container_after = this.next_open_container(sep);

        let global_container_rect=H_dom.get_global_rect(this.global_container,true);
        let container_before_rect=H_dom.get_global_rect(container_before.dom_elem,true);

        // may have other separator and container with fixed size
        let other_width = 0;
        let other_height = 0;

        let sep_w_b = 0; // separators width before
        let sep_h_b = 0; // separators height before

        this.containers.forEach((obj,i)=>{

            if (obj.is_separator) {

                // add every separator size
                let obj = this.containers.get(i);
                let rect = H_dom.get_global_rect(obj.dom_elem,true);
                other_width += (rect.width==0 && obj.state!='closed' ) ? 25 : rect.width;
                other_height += (rect.height==0 && obj.state!='closed' ) ? 25 : rect.height;

                if (obj.index > container_before.index && obj.index < sep.index){
                    sep_w_b += rect.width;
                    sep_h_b += rect.height;
                }

            } else {

                if (obj != container_before && obj != container_after){ // ignore containers to resize

                    if (obj.index < container_before.index){ // containers before the resized one
                        let rect = H_dom.get_global_rect(obj.dom_elem,true);
                        other_width += rect.width;
                        other_height += rect.height;
                    }

                    if (obj.index > container_after.index){ // containers after the resized one
                        let rect = H_dom.get_global_rect(obj.dom_elem,true);
                        other_width += rect.width;
                        other_height += rect.height;
                    }
                }

            }
        });

        if (this.type == 'horizontal') {
            let FHeightBefore = parseInt(sep.dom_elem.style.top) - container_before_rect.top - sep_h_b;
            let FHeightAfter = parseInt(global_container_rect.height - FHeightBefore - other_height);
            if ((FHeightBefore > container_before.min_height && FHeightAfter > container_after.min_height) || sep.state=='closed'){
                container_before.dom_elem.style.height=FHeightBefore+'px';
                if (container_after) container_after.dom_elem.style.height=FHeightAfter+'px';
            }
        } else {
            let FWidthBefore = parseInt(sep.dom_elem.style.left) - container_before_rect.left - sep_w_b;
            let FWidthafter = parseInt(global_container_rect.width - FWidthBefore - other_width);
            if ((FWidthBefore > container_before.min_width && FWidthafter > container_after.min_width) || sep.state=='closed' ){
                container_before.dom_elem.style.width = FWidthBefore+'px';
                if (container_after) container_after.dom_elem.style.width = FWidthafter+'px';
            }
        }   
    }
    next_open_container(base_obj){
        let next = this.containers.get(base_obj.index + 1);
        while (next && (next.state == 'closed' || next.is_separator)){
            next = this.containers.get(next.index + 1);
        }
        return next ? next : false;
    }
    previous_container(base_obj){
        let index = base_obj.index;
        let prev;
        while(index > -1 && prev === undefined){
            index--;
            prev = this.containers.get(index);
        }
        return prev ? prev : false;
    }
    toggle_separator(evt, sep){
        let dom_elem_rect = H_dom.get_global_rect(sep.dom_elem, true);
        
        let to_toggle = this.containers.get(sep.index+1); // after

        if (sep.state == "open"){ // close it
            sep.state = 'closed';
            to_toggle.state = 'closed';

            // css
            to_toggle.dom_elem.classList.replace('hlp_disposition_open', 'hlp_disposition_closed');
            sep.dom_elem.classList.replace('open', 'close');

            // calculate the position of the closing separator before hidding the container
            // otherwise the display none change the left position of the next separator and the closing separator seems to close to the right
            // and we want it to close to the left
            let next_separators = this.separators[this.separators.indexOf(sep.index) + 1];
            let next_rect;
            if (next_separators) {
                next_separators = this.containers.get(next_separators);
                next_rect = H_dom.get_global_rect(next_separators.dom_elem,true);
            } else {
                let global_container_rect = H_dom.get_global_rect(this.global_container,true);
                next_rect = {'top': global_container_rect.bottom, 'left': global_container_rect.right};
            }
            
            to_toggle.dom_elem.dataset.previous_display = to_toggle.dom_elem.style.display;
            to_toggle.dom_elem.style.display = 'none';

            if (this.type=='horizontal'){
                sep.dom_elem.style.top = (next_rect.top - dom_elem_rect.height) + 'px';
            }else{
                sep.dom_elem.style.left = (next_rect.left - dom_elem_rect.width) + 'px';
            }

            // change eye icon
            let use_elem = sep.btn.toggle.getElementsByTagName('use')[0];
            let new_href = use_elem.getAttributeNS(null, 'href');
            use_elem.setAttributeNS(null, 'href', new_href.replace('eye-off', 'eye'));

            this.resize_container(sep);
            this.disable_drag(sep.dom_elem);
        }else{
            // open it
            sep.state='open';
            to_toggle.state='open';

            // when opening a separator and the following container, split the previous container size by 2
            // but if many separators are closed the previous container may not be the direct one.
            // so need to get the previous open one and all the separators between
            let container_before = this.previous_container(sep);
            let before_rect = H_dom.get_global_rect(container_before.dom_elem,true);
            let sep_w_b = 0; // separators width before
            let sep_h_b = 0; // separators height before
            this.separators.forEach((i)=>{
                if (i > container_before.index && i < sep.index){
                    let obj = this.containers.get(i);
                    let rect = H_dom.get_global_rect(obj.dom_elem,true);
                    sep_w_b += (rect.width / 2);
                    sep_h_b += (rect.height / 2);
                }
            });

            // css
            to_toggle.dom_elem.classList.replace('hlp_disposition_closed','hlp_disposition_open');
            sep.dom_elem.classList.replace('closed','open');
            to_toggle.dom_elem.style.display = (to_toggle.dom_elem.dataset.previous_display != undefined) ? to_toggle.dom_elem.dataset.previous_display : '';
            
            if (this.type=='horizontal'){
                sep.dom_elem.style.top = ((before_rect.height + sep_h_b) / 2 + before_rect.top) + 'px';
                to_toggle.dom_elem.style.width = '100%';
            }else{
                sep.dom_elem.style.left = ((before_rect.width + sep_w_b) / 2 + before_rect.left) + 'px';
                to_toggle.dom_elem.style.height = '100%';
            }

            // change eye icon
            let use_elem = sep.btn.toggle.getElementsByTagName('use')[0];
            let new_href = use_elem.getAttributeNS(null, 'href');
            use_elem.setAttributeNS(null, 'href', new_href.replace('eye', 'eye-off'));

            this.resize_container(sep);
            this.enable_drag(sep);
        }
    }
    switch_type_all(){
        this.type = (this.type=='horizontal') ? 'vertical' : 'horizontal';

        if (this.type == 'vertical'){
            this.global_container.style.display = "flex";
        } else {
            this.global_container.style.display = "";
        }

        let global_container_rect = H_dom.get_global_rect(this.global_container, true);

        let seps_width = 0;
        let seps_height = 0;
        let containers_open = 1;
        this.separators.forEach((index)=>{
            let sep = this.containers.get(index);
            if (this.type == 'vertical') sep.dom_elem.classList.replace('horizontal', 'vertical');
            else sep.dom_elem.classList.replace('vertical', 'horizontal');

            if (sep.state == 'open') containers_open++;

            let sep_rect = H_dom.get_global_rect(sep.dom_elem,true);
            seps_width += sep_rect.width;
            seps_height += sep_rect.height;
        });

        
        let containers_width = (global_container_rect.width - seps_width) / containers_open;
        let containers_height = (global_container_rect.height - seps_height) / containers_open;

        this.containers.forEach((obj)=>{
            if (!obj.is_separator && obj.state == 'open'){
                if (this.type == 'vertical'){
                    obj.dom_elem.style.width = containers_width+'px';
                    obj.dom_elem.style.height = '100%';
                } else {
                    obj.dom_elem.style.height = containers_height+'px';
                    obj.dom_elem.style.width = '100%';
                }
            }
        });
        
        this.resize_all();
    }

    exist() {
        let exist = document.getElementById(this.global_container.id);
        return exist ? true : false;
    }
    clean(){
        if (this.observer && this.observer.disconnect === "function" ){
            this.observer.disconnect();
        }
        // remove parent container and move all the container outside it
        let parent = this.global_container.parentElement;
        if (parent) {
            this.containers.forEach(obj => H_dom.append_content(parent, obj.dom_elem));
            H_dom.remove_element(this.global_container);
        }
    }
    
    static instances = {};
    static create_instance(dom_id, settings){
        if (H_ui_disposition.instances[dom_id]){
            H_ui_disposition.instances[dom_id].clean();
            delete(H_ui_disposition.instances[dom_id]);
        }
        H_ui_disposition.instances[dom_id] = new H_ui_disposition(dom_id, settings);

        H_ui_disposition.clean_instances();
        
        //~ console.log(H_ui_disposition.instances[dom_id]);
        return H_ui_disposition.instances[dom_id];
    }
    static clean_instances(){
        let toClean = [];
        for (var key in H_ui_disposition.instances) {
            if (!H_ui_disposition.instances[key].exist()){
                H_ui_disposition.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key)=>{
            delete(H_ui_disposition.instances[key]);
        });
    }
}
h.libs.ui_disposition = H_ui_disposition;
window.H_ui_disposition = H_ui_disposition;

/**
 * Represents a tabbed UI component with switchable views.<br>
 * called by H.php class<br>
 * @param {string} dom_id - DOM ID of the tab container.
 */
class H_ui_tabs {
    constructor(dom_id, settings) {

        this.dom_id = dom_id;

        this.callback = settings.callback ? settings.callback : false;
        this.base_id = settings.base_id;

        this.lists = [];
        this.active = false;
        this.container_label = document.getElementById(this.base_id + '_list_label');
        this.container_content = document.getElementById(this.base_id + '_list_content');
        
            // link label to content
        for (let i = 0; i < this.container_label.children.length; i++) {
            let elem = {
                label: this.container_label.children[i],
                content: this.container_content.children[i],
                collapsable: true,
            };
            this.lists.push(elem);

            h.e.add_event_click(elem.label, this.on_click_label.bind(this));
            elem.label.dataset.tab_index = i;
            elem.content.dataset.tab_index = i;

            if (i == 0) {
                this.active = 0;
                H_dom.add_class(elem.label, 'active');
            } else {
                H_dom.add_class(elem.content, 'hidden');
            }
        }
    }
    on_click_label(evt) {
        let index = parseInt(evt.target.dataset.tab_index);
        if (index == this.active) return; // already open 
        if (!this.lists[index].collapsable) return; // can't open it cause

        this.close(this.active);
        this.active = index;
        this.open(this.active);
    }
    open(index) {
        H_dom.add_class(this.lists[index].label, 'active');
        H_dom.remove_class(this.lists[index].content, 'hidden');
    }
    close(index) {
        H_dom.remove_class(this.lists[index].label, 'active');
        H_dom.add_class(this.lists[index].content, 'hidden');
    }
    exist(){
        let exist = document.getElementById(this.base_id + '_list_label');
        return (exist) ? true : false;
    }
    clean() {
        return;
    }
    static instances = {};
    static create_instance(dom_id, settings) {
        if (H_ui_tabs.instances[dom_id]) {
            H_ui_tabs.instances[dom_id].clean();
            delete (H_ui_tabs.instances[dom_id]);
        }
        // console.log(H_ui_disposition.instances);
        H_ui_tabs.instances[dom_id] = new H_ui_tabs(dom_id, settings);
        // console.log(H_ui_disposition.instances);
        H_ui_tabs.clean_instances();

        //~ console.log(H_ui_disposition.instances[dom_id]);
        return H_ui_tabs.instances[dom_id];
    }
    static clean_instances() {
        let toClean = [];
        for (var key in H_ui_tabs.instances) {
            if (!H_ui_tabs.instances[key].exist()) {
                H_ui_tabs.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key) => {
            delete (H_ui_tabs.instances[key]);
        });
    }
}

h.libs.ui_tabs = H_ui_tabs;
window.H_ui_tabs = H_ui_tabs;

/**
 * Constructs a new input order field.
 * @param {H_ui_inputs_order_manager} manager - The manager instance.
 * @param {string} base_id - Base id for the input.
 * @param {boolean} editable - Whether the input is editable.
 * @param {Function} callback - Callback on value change.
 */
class H_ui_input_order {
    constructor(manager, base_id, editable, callback) {
        this.manager = manager;
        this.base_id = base_id;

        this.container = document.getElementById(base_id + '-container');
        this.dom_display = document.getElementById(base_id + '-display');
        this.dom_input = document.getElementById(base_id + '-hidden');

        this.callback = callback;
        this.value = parseInt(this.dom_input.value);
        this.id = parseInt(this.dom_input.dataset.id);

        this.dom_btn_up = document.getElementById(base_id + '-up');
        h.e.add_event_click(this.dom_btn_up, this.up.bind(this));
        this.dom_btn_down = document.getElementById(base_id + '-down');
        h.e.add_event_click(this.dom_btn_down, this.down.bind(this));

        this.dom_drag = document.getElementById(base_id + '-drag');
        this.avatar = false;
        h.e.enable_drag(this.dom_drag, this.start_drag.bind(this), this.move_drag.bind(this), this.end_drag.bind(this), this.create_avatar.bind(this));
        this.drop_indication = false;
        this.drop_indication_active = false;
        this.drop_at_position = false;

        // elements that are linked to this input order and need to move with it
        this.linked_fetched = false;
        this.linked_parent = false;
        this.linked_list = [];
        
        this.state_btn_up = true;
        this.state_btn_down = true;

        this.alone; // set by the manager

        if (editable){
            this.edit_state = false;

            // add a class to the name to activate the color change on hover
            H_dom.add_class(this.dom_display, 'editable');

            this.edit_cancel = document.getElementById(base_id + '-cancel');
            this.edit_input = document.getElementById(base_id + '-edit');
            this.edit_valid = document.getElementById(base_id + '-valid');
            // click on order display toggle input
            h.e.add_event_click(this.dom_display, this.toggle_edit.bind(this));
            
            h.e.add_event(this.edit_input, 'focus', this.focus_edit_value.bind(this));
            h.e.add_event_key(this.edit_input, this.keypress_edit.bind(this));
            
            h.e.add_event_click(this.edit_cancel, this.toggle_edit.bind(this));

            h.e.add_event_click(this.edit_valid, this.valid_edit_value.bind(this));
        }
        
    }
    /**
     * Fetches linked elements that move together with this input order.
     */
    fetch_linked_elements(){
        if (this.alone) return;
        if (this.linked_fetched && this.verify_linked()) return;
        // if (this.linked_fetched && this.verify_linked()) return;
        
        let form = this.dom_input.form;
        if (!form){ // in some case where inputs are not put in a form we retrieve the parent+1 of the container
            form = this.container.parentElement.parentElement;
        }
        
        let parent = form.querySelector('[data-order_parent="' + this.dom_input.name + '"]');
        if (parent) {
            this.linked_parent = parent;
        } else {
            let elems = form.querySelectorAll('[data-order="' + this.dom_input.name + '"], #' + this.base_id + '-container');
            if (elems) {
                this.linked_list = Array.from(elems);
                this.linked_first = this.linked_list[0];
                this.linked_last = this.linked_list[this.linked_list.length - 1];
            }
        }

        this.linked_fetched = true;
    }
    /**
     * Checked if the linked items are still present
     */
    verify_linked(){
        if (this.linked_parent) return document.body.contains(this.linked_parent);
        
        let one_not_found = false;
        for (let index = 0; index < this.linked_list.length; index++) {
            let element = this.linked_list[index];
            if (!document.body.contains(element)) {
                one_not_found = true;
                break;
            }
        }
        if (one_not_found == true) return false;
        return true;
    }
    /**
     * Moves the input up in the order.
     */
    up(){
        if (this.alone){
            this.set_value(this.value + 1);
            return;
        }

        this.fetch_linked_elements();
        this.manager.clean_list();
        if (this.manager.max > this.value || this.manager.list.size == 1){
            let new_value = this.value + 1;
            let next = this.manager.get_input(new_value, this, true);
            
            if (next) {
                next.set_value(this.value);
                this.move('after', next);
            }
            this.set_value(new_value);
        }
    }
    /**
     * Moves the input down in the order.
     */
    down(){
        if (this.alone){
            if (this.value > 1) {
                this.set_value(this.value - 1);
            }
            return;
        }

        this.fetch_linked_elements();
        this.manager.clean_list();
        if (this.value > this.manager.min || (this.manager.list.size == 1) && this.value > 1){
            let new_value = this.value - 1;
            let previous = this.manager.get_input(new_value, this, false);

            if (previous) {
                previous.set_value(this.value);
                this.move('before', previous);
            }
            this.set_value(new_value);
        }
    }
    /**
     * Sets the value and updates display/input.
     * @param {number} value - New value.
     */
    set_value(value){
        this.value = value;
        this.dom_display.innerHTML = value;
        this.dom_input.value = value;
        if (this.edit_input) this.edit_input.value = value;
        this.toggle_buttons();
        if (this.callback) H_generics.execute_function_by_name(this.callback, window, this.id, this.value, this);
    }

    /**
     * Toggles the enabled/disabled state of up/down buttons.
     */
    toggle_buttons(){
        // disable down button
        if ((this.value == this.manager.min && this.manager.list.size > 1) || this.value == 1){
            if (this.state_btn_down) {
                this.state_btn_down = false;
                this.dom_btn_down.disabled = true;
                H_dom.add_class(this.dom_btn_down, 'disabled');
            }
        } else {
            if (!this.state_btn_down) {
                this.state_btn_down = true;
                this.dom_btn_down.disabled = false;
                H_dom.remove_class(this.dom_btn_down, 'disabled');
            }
        }
        
        // disable up button
        if (this.value == this.manager.max && this.manager.list.size > 1){
            if (this.state_btn_up) {
                this.state_btn_up = false;
                this.dom_btn_up.disabled = true;
                H_dom.add_class(this.dom_btn_up, 'disabled');
            }
        } else {
            if (!this.state_btn_up) {
                this.state_btn_up = true;
                this.dom_btn_up.disabled = false;
                H_dom.remove_class(this.dom_btn_up, 'disabled');
            }
        }
    }
    /**
     * Moves the DOM elements after/before another input.
     * @param {string} move_type - 'after' or 'before'.
     * @param {H_ui_input_order} other - The other input order instance.
     */
    move(move_type, other){
        if (this.linked_parent) {
            H_dom['insert_' + move_type](this.linked_parent, other.linked_parent);
        } else {
            if (move_type == 'after') this.linked_list.reverse();
            // console.log('moving ' + move_type, other.linked_first);
            this.linked_list.forEach(elem => {
                if (move_type == 'after') H_dom['insert_' + move_type](elem, other.linked_last);
                else H_dom['insert_' + move_type](elem, other.linked_first);
                // H_dom['insert_' + move_type](elem, other.linked_first);
            });
        }
        this.linked_fetched = false;
    }

    /**
     * Toggles edit mode for the input order.<br>
     */
    toggle_edit(){
        // this.create_edit_input();
        if (this.edit_state){
            // show btns up/down and display
            H_dom.remove_class(this.dom_btn_up, 'hidden');
            H_dom.remove_class(this.dom_display, 'hidden');
            H_dom.remove_class(this.dom_btn_down, 'hidden');
            
            // hide edit btns and input
            H_dom.add_class(this.edit_cancel, 'hidden');
            H_dom.add_class(this.edit_input.parentElement, 'hidden');
            H_dom.add_class(this.edit_valid, 'hidden');

            this.edit_state = false;
        } else {
            // hide btns up/down and display
            H_dom.add_class(this.dom_btn_up, 'hidden');
            H_dom.add_class(this.dom_display, 'hidden');
            H_dom.add_class(this.dom_btn_down, 'hidden');

            // show edit btns and input
            H_dom.remove_class(this.edit_cancel, 'hidden');
            H_dom.remove_class(this.edit_input.parentElement, 'hidden');
            H_dom.remove_class(this.edit_valid, 'hidden');

            // set input to current
            this.edit_input.value = this.value;
            // auto focus and select input content
            this.focus_edit_value();

            this.edit_state = true;
        }
    }
    focus_edit_value(){
        this.edit_input.select();
    }
    valid_edit_value(){
        this.toggle_edit();
        this.fetch_linked_elements();

        let new_value = parseInt(this.edit_input.value);
        this.jump_value(new_value);
    }


    /**
     * Jumps to a specific value in the order.
     * @param {number} new_value - The value to jump to.
     */
    jump_value(new_value){
        let other = false;
        if (new_value > this.value){
            // each input between value and new_value take 1 less
            for (let i = this.value+1; i <= new_value; i++) {
                other = this.manager.get_input(i, this);
                if (other) other.set_value(i - 1);
                else other = false;
            }
            this.set_value(new_value);
            if (other) this.move('after', other);
        } else {
            // each input between new_value and value take 1 more
            for (let i = this.value-1; i >= new_value; i--) {
                other = this.manager.get_input(i, this);
                if (other) other.set_value(i + 1);
                else other = false;
            }
            this.set_value(new_value);
            if (other) this.move('before', other);
        }
    }

    /**
     * Handles keypress events in edit mode.
     * @param {KeyboardEvent} event - The keyboard event.
     */
    keypress_edit(event){
        switch(event.key){
            case 'Enter':
                this.valid_edit_value();
            break;
            case 'Escape':
                this.toggle_edit();
            break;
        }
    }

    /**
     * Checks if the input order field exists in the DOM.
     * @returns {boolean} True if exists.
     */
    exist(){
        let elem = document.getElementById(this.base_id + '-display');
        return (elem) ? true : false;
    }

    /**
     * Toggles drag state for linked elements.
     * @param {boolean} state - True to enable drag state.
     */
    toggle_drag(state){
        if (this.linked_parent) {
            H_dom.toggle_class(this.linked_parent, 'input_order_dragging', state);
        } else {
            this.linked_list.forEach((elem)=>{
                H_dom.toggle_class(elem, 'input_order_dragging', state);
            });
        }
    }
    /**
     * Drag start handler.
     * @param {Event} event - The drag event.
     */
    start_drag(event){
        this.manager.clean_list();
        this.toggle_drag(true);
    }
    /**
     * Drag move handler.
     * @param {Event} event - The drag event.
     */
    move_drag(event){
        this.indicate_drop_position(event);
    }
    /**
     * Drag end handler.
     */
    end_drag(){
        this.toggle_drag(false);
        if (this.drop_indication_active !== false && this.drop_at_position !== false){
            let new_value = this.drop_at_position;
            this.remove_drop_indication();
            this.jump_value(new_value);
        }
    }
    /**
     * Creates a drag avatar for the input order.<br>
     * @returns {HTMLElement} The avatar element.
     */
    create_avatar(elem, event){
        this.fetch_linked_elements();
        let avatar;
        if (this.linked_parent) avatar = H_dom.clone_dom_element(this.linked_parent, true);
        else {
            avatar = H_dom.create_element('DIV');
            this.linked_list.forEach((elem)=>{
                H_dom.append_content(avatar, H_dom.clone_dom_element(elem, true));
            });
            avatar.classList = this.linked_list[0].parentElement.classList;
        }

        H_dom.add_class(avatar, 'hlp_input_order_avatar');

        // want to position the avatar like the original. 
        // calculate the clicked position inside the original, adapt it to the size of the avatar and translate the avatar
        // depending the result.
        let width = false;
        let offsetLeft = false;
        // we need the width and the offset of the click inside parent. 
        if (this.linked_parent) {
            // easy to do when there is a parent 
            let parent_rect = H_dom.get_global_rect(this.linked_parent, true);
            width = parent_rect.width;
            offsetLeft = parent_rect.width - (parent_rect.right - event.clientX);
        } else {
            // when no parent, parse the list of moving item and calculate the width
            let rightest = false;
            let leftest = false;
            this.linked_list.forEach((elem)=>{
                let elem_rect = H_dom.get_global_rect(elem, true);
                if (elem_rect.width > 0 && elem_rect.height > 0) {
                    if (rightest === false || rightest < elem_rect.right) rightest = elem_rect.right;
                    if (leftest === false || leftest > elem_rect.left) leftest = elem_rect.left;
                }
                
            });
            width = rightest - leftest;
            offsetLeft = width - (rightest - event.clientX);
        }

        avatar.style.visibility = 'hidden';
        H_dom.append_content(document.body, avatar);
        let avatar_rect = H_dom.get_global_rect(avatar, true);
        let offset_width_adjusted = offsetLeft - (width - avatar_rect.width);
        avatar.style.transform = 'translateX(-' + offset_width_adjusted + 'px)';
        avatar.style.visibility = 'visible';

        return avatar;
    }
    
    /**
     * Checks if a DOM element is inside the linked elements.
     * @param {HTMLElement} dom_element - The element to check.
     * @returns {boolean} True if inside.
     */
    is_inside(dom_element){
        this.fetch_linked_elements();
        if (this.linked_parent) return this.linked_parent.contains(dom_element);
        else {
            let found = false;
            for (let i = 0; i < this.linked_list.length; i++){
                if (this.linked_list[i].contains(dom_element) || this.linked_list[i] == dom_element){
                    found = true;
                    break;
                }
            }
            return found;
        }
    }

    /**
     * Indicates the drop position during drag-and-drop.
     * @param {Event} event - The drag event.
     */
    indicate_drop_position(event){
        this.manager.get_input_hover(event.target);

        if (this.manager.drag_hover_input && this.manager.drag_hover_input != this){

            let insert_position = this.get_drop_state(event);

            let new_inserted_position;
            // retrieve the value if the element is dropped here
            if (this.value > this.manager.drag_hover_input.value){
                new_inserted_position = (insert_position == 'before') ? this.manager.drag_hover_input.value : this.manager.drag_hover_input.value + 1;
            } else {
                new_inserted_position = (insert_position == 'before') ? this.manager.drag_hover_input.value - 1 : this.manager.drag_hover_input.value;
            }

            // do nothing when 
            if (new_inserted_position == this.value) {
                // end position is the same as current
                // remove indicator if any
                if (this.drop_indication_active) this.remove_drop_indication();
                return;
            }
            // same position than before do nothing
            if (new_inserted_position == this.drop_at_position) return;
            else this.drop_at_position = new_inserted_position;

            this.insert_drop_indication(insert_position);
            
        } else if (this.drop_indication_active){

            if (event.target.dataset && event.target.dataset.drop_indicator == 1) {
                // hover the indicator, do nothing
                return;
            }

            // hover the parent, do nothing
            if (event.target == this.manager.parent) return;

            this.remove_drop_indication();
        }
    }

    /**
     * Gets the drop state ('before' or 'after') based on mouse position.
     * @param {Event} event - The drag event.
     * @returns {string} 'before' or 'after'.
     */
    get_drop_state(event){
        let rect = H_dom.get_global_rect(event.target , true);
        let dist_top = Math.abs(rect.top - event.pageY);
        let dist_bottom = Math.abs(rect.bottom - event.pageY);
        if (dist_top < dist_bottom) return 'before';
        else return 'after';
    }
    /**
     * Creates the drop indication element(s).
     */
    create_drop_indication(){
        if (this.drop_indication) return;

        this.fetch_linked_elements();
        if (this.linked_parent) {

            this.drop_indication = H_dom.clone_dom_element(this.linked_parent, false);
            this.drop_indication_class = 'input_order_drop_indication_parent';
            
            H_dom.get_global_rect(this.linked_parent, true);
            let height = H_dom.get_global_height(this.linked_parent);
            let width = H_dom.get_global_width(this.linked_parent);
            this.drop_indication.style.width = width + 'px';
            this.drop_indication.style.height = height + 'px';
            this.drop_indication.dataset.drop_indicator = '1';
            
        } else {

            this.drop_indication_class = 'input_order_drop_indication_item';
            this.drop_indication = [];
            this.linked_list.forEach((elem)=>{
                // get width and height from dragged elements
                let height = H_dom.get_global_height(elem);
                let width = H_dom.get_global_width(elem);
                if (width > 0 || height > 0) {
                    let clone = H_dom.create_element('DIV', {
                        class: this.drop_indication_class,
                        style: 'width: ' + width + 'px; height: ' + height + 'px;',
                        'data-drop_indicator': 1
                    });
                    this.drop_indication.push(clone);
                }
            });

        }
    }

    /**
     * Inserts the drop indication at the correct position.
     * @param {string} position - 'before' or 'after'.
     */
    insert_drop_indication(position){
        this.create_drop_indication();
        if (H_generics.is_array(this.drop_indication)){
            if (position == 'after') {
                console.log('insert_' + position);
                console.log(this.manager.drag_hover_input.linked_last);
            } else {
                console.log('insert_' + position);
                console.log(this.manager.drag_hover_input.linked_first);
            }
            
            if (position == 'after') this.drop_indication.reverse();
            this.drop_indication.forEach((elem)=>{
                if (position == 'after') H_dom['insert_' + position](elem, this.manager.drag_hover_input.linked_last);
                else H_dom['insert_' + position](elem, this.manager.drag_hover_input.linked_first);
            });
            if (position == 'after') this.drop_indication.reverse();

        } else {

            H_dom['insert_' + position](this.drop_indication, this.manager.drag_hover_input.linked_parent);

        }
        
        this.drop_indication_active = true;
    }
    /**
     * Removes the drop indication element(s).
     */
    remove_drop_indication(){
        console.log('remove drop indication');
        if (H_generics.is_array(this.drop_indication)){
            this.drop_indication.forEach((elem)=>{
                H_dom.remove_element(elem);
            });
        } else {
            H_dom.remove_element(this.drop_indication);
        }

        this.drop_at_position = false;
        this.drop_indication_active = false;
        this.drop_indication = false;
    }
}
h.libs.ui_input_order = H_ui_input_order;
window.H_ui_input_order = H_ui_input_order;

/**
 * @class H_ui_inputs_order_manager
 * @classdesc
 * Manager class for multiple input order fields.<br>
 * Handles adding, removing, cleaning, and drag-hover logic.<br>
 * Used as h.libs.ui_inputs_order_manager.
 * @param {string} base_name - The base name for the group.
 */
class H_ui_inputs_order_manager {
    constructor(base_name){
        this.name = base_name;
        this.list = new Map();
        this.max = false;
        this.min = false;
        this.last_added = false;

        this.drag_hover_input = false;
        this.parent = false;
    }

    /**
     * Adds a new input order field to the manager.
     * @param {string} base_id - Base id for the input.
     * @param {boolean} editable - Whether the input is editable.
     * @param {Function} callback - Callback on value change.
     */
    add_field(base_id, editable, callback){
        let input = new H_ui_input_order(this, base_id, editable, callback);
        this.list.set(base_id, input);

        this.max = (this.max === false || input.value > this.max) ? parseInt(input.value) : this.max;
        this.min = (this.min === false || input.value < this.min) ? parseInt(input.value) : this.min;

        input.toggle_buttons();
        input.alone = this.list.size > 1 ? false : true;
        if (this.last_added) {
            this.last_added.toggle_buttons();
            this.last_added.alone = false;
        }
        this.last_added = input;

        if (!this.parent && !input.alone){
            input.fetch_linked_elements();
            if (input.linked_parent){
                this.parent = input.linked_parent.parentElement;
                // special case for table
                if (this.parent.tagName == 'TBODY') this.parent = this.parent.parentElement;
            } else {
                this.parent = input.container.parentElement;
            }
            
        }
    }
    
    /**
     * Gets an input order instance by value.
     * @param {number} value - The value to search for.
     * @param {H_ui_input_order} caller - Caller is the input order object that call get_input
     * @returns {H_ui_input_order|false} The found input order or false.
     */
    get_input(value, caller){
        let input = false;
        this.list.forEach((obj) => {
            if (obj.value == value && caller != obj) {
                input = obj;
            }
        });

        if (input) input.fetch_linked_elements();
        return input;
    }

    /**
     * Gets the input order instance currently hovered during drag.
     * @param {HTMLElement} dom_element - The DOM element being hovered.
     */
    get_input_hover(dom_element){
        let found = false;
        let input = false;
        this.list.forEach((obj) => {
            if (!found && obj.is_inside(dom_element)){
                found = true;
                input = obj;
            }
        });
        this.drag_hover_input = input;
    }
    /**
     * check if there is element in list that don't exist anymore and reordonne remaining element to remove empty number
     */
    clean_list(){
        // remove deleted element
        this.list.forEach((obj, key) => {
            if (!obj.exist()) {
                this.list.delete(key);
            }
        });
        // ordonne list to ascending order
        this.list = new Map([...this.list.entries()].sort((a, b) => a[1].value - b[1].value));
        let i = 0;
        this.max = this.list.size;
        this.min = 1;
        this.list.forEach(obj => {
            i++;
            if (obj.value != i) obj.set_value(i);
        });
    }
}
h.libs.ui_inputs_order_manager = H_ui_inputs_order_manager;
window.H_ui_inputs_order_manager = H_ui_inputs_order_manager;
