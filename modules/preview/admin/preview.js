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

class Preview_a extends H_module {
    constructor(dom_id, settings, texts) {
        super(dom_id, texts);

        this.iframe, this.iframe_document, this.iframe_window, this.picker_overlay, this.prevBtnSwitch;
        this.prev_mode = 'desktop';

        let picker = document.getElementById('preview_picker' + this.dom_id);
        if (picker) h.e.add_event_click(picker, this.init_picker_module.bind(this));

        let cssPicker = document.getElementById('preview_picker_css'+this.dom_id);
        if (cssPicker) h.e.add_event_click(cssPicker, this.start_picker_css.bind(this));
        this.picker_css_overlay = false; // overlay used for detecting css rules
        this.picker_hover_overlay = false; // overlay used to show wich element is selected
        this.picker_hover_elem = false; // picker css is hover this element
        this.csseditor_is_open = false;

        let refresh = document.getElementById('preview_refresh'+this.dom_id);
        h.e.add_event_click(refresh, this.refresh_iframe.bind(this));

        // let smartphone = document.getElementById('preview_smartphone'+dom_id);
        // let tablet = document.getElementById('preview_tablet'+dom_id);
        // let desktop = document.getElementById('preview_desktop'+dom_id);
        // h.e.add_event_click(smartphone, toggle_preview_mode);
        // h.e.add_event_click(tablet, toggle_preview_mode);
        // h.e.add_event_click(desktop, toggle_preview_mode);
        // prevBtnSwitch = desktop;

        this.bar_outils = document.getElementById('preview_bar_outils'+this.dom_id);

        this.context_menu = false;

        let class_css = 'preview_admin_iframe desktop';
        this.is_admin = false;
        if (settings.preview_is_admin !== undefined){
            this.is_admin = settings.preview_is_admin ? true : false;
            delete(settings.preview_is_admin);
        }

        this.css_source = 'theme';
        if (settings.css_source){
            this.css_source = settings.css_source;
            delete(settings.css_source);
        }

        this.css_selectors = settings.css_selectors;
        delete(settings.css_selectors);

        this.current_language = settings.language;

        let url = H_constants.base_url;
        if (this.is_admin) url += H_constants.admin_folder;
        if (H_generics.is_filled_object(settings)){
            url = H_constants.base_url + 'public/preview/preview.php?';
            for(var key in settings){
                url += key + '=' + settings[key] + '&';
            }
            url = url.slice(0, -1);
            if (this.is_admin) url += '&admin';
            class_css += ' module';
        }

        this.iframe = H_dom.create_element('IFRAME', {
            'name': 'preview_iframe' + this.dom_id,
            'class': class_css,
            'id': 'preview_iframe' + this.dom_id,
            'src': url,
            'frameborder': '0',
            'sandbox': 'allow-same-origin allow-scripts'
        });
        H_dom.append_content(document.getElementById('preview_container_iframe'+this.dom_id), this.iframe);
        this.resize_observer = false;
        this.current_size = false;
        this.current_scale = false;
        this.iframe.addEventListener('load', this.init_iframe.bind(this));
    }
    init_picker_module(evt){
        if (this.iframe_document){
            if (!this.picker_overlay){
                let style = 'position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: #00000047; z-index: 9999;';
                this.picker_overlay = H_dom.create_element('DIV', {'class':'preview_picker_overlay', 'id':'preview_picker_overlay', 'style':style});
                this.iframe_document.body.appendChild(this.picker_overlay);
                this.picker_overlay.addEventListener('mousedown', this.picker_detect_module.bind(this));
            }
            
            H_dom.toggle_class(this.picker_overlay, 'hidden', false);
        }
    }
    picker_detect_module(evt){
        let elems = this.picker_elements_list(evt.x, evt.y);
        
        let modules = [];
        elems.forEach(function(elt){
            // console.log(elt);
            let name = elt.id.split('_')[0];
            let id = elt.dataset.currentid;
            modules.push({"name": name, "id": id});
        });
        
        if (modules.length > 0){
            if (modules.length > 1){
                this.show_modal(modules);
            } else {
                this.change_hash(modules[0].name, modules[0].id);
            }
        }
    }
    picker_elements_list(x, y){
        var element, elements = [];
        var old_visibility = [];
        
        var doc = this.iframe_document.documentElement;
        var left = (this.iframe_window.pageXOffset || doc.scrollLeft) - (doc.clientLeft || 0) - (this.iframe.scrollX || 0);
        var top = (this.iframe_window.pageYOffset || doc.scrollTop)  - (doc.clientTop || 0) - (this.iframe.scrollY || 0);
        top = 0;
        
        var modules = [];
        while (true) {
            element = this.iframe_document.elementFromPoint(x - left, y - top);

            if (!element || element === doc) {
                break;
            }

            let parent = element;
            while(parent && parent.dataset.current_id === undefined) {
                parent = parent.parentElement;
            }
            if (parent === null) {
                elements.push(element);
                old_visibility.push(element.style.visibility);
                element.style.visibility = 'hidden';
            } else {
                elements.push(parent);
                old_visibility.push(parent.style.visibility);
                parent.style.visibility = 'hidden';
                modules.push(parent);
            }
        }
        for (var k = 0; k < elements.length; k++) {
            elements[k].style.visibility = old_visibility[k];
        }
        
        return modules;
    }
    start_picker_css(){
        if (this.iframe_document){
            // console.log('start picker css');
            if (!this.picker_css_overlay){ // initialize the overlay that cover the whole document
                let style = 'position: fixed; top: 0; bottom: 0; left: 0; right: 0; background: transparent; z-index: 9999;';
                this.picker_css_overlay = H_dom.create_element('DIV', {'class':'preview_picker_overlay', 'id':'preview_picker_overlay', 'style':style});
                this.iframe_document.body.appendChild(this.picker_css_overlay);
                h.e.add_event_click(this.picker_css_overlay, this.picker_css_get_rule.bind(this));
                h.e.add_event(this.picker_css_overlay, 'mousemove', this.picker_css_move.bind(this));
            } else {
                this.picker_css_overlay.style.display = 'block';
            }

            if (!this.picker_hover_overlay) {
                let style = 'background: rgba(232, 183, 0, 0.2); z-index: 9998; border: 1px solid rgb(56, 177, 249); border-radius: 1px; box-sizing: border-box; cursor: crosshair; position: absolute; margin: 0; padding: 0;';
                this.picker_hover_overlay = H_dom.create_element('DIV',{'style': style});
                H_dom.append_content(this.iframe_document.body, this.picker_hover_overlay);
            } else {
                this.picker_hover_overlay.style.display = 'block';
            }
        }
    }
    stop_picker_css(){
        if (this.iframe_document){
            // console.log('stop picker css');
            this.picker_css_overlay.style.display = 'none';
            this.picker_hover_overlay.style.display = 'none';
            this.picker_hover_overlay.style.visibility = 'hidden';
        }
    }
    picker_css_move(evt){
        this.picker_css_overlay.style.visibility = 'hidden'; // hide the overlay that handle the move 
        this.picker_hover_overlay.style.visibility = 'hidden'; // and the overlay that display the selected element

        let target = this.iframe_document.elementFromPoint(evt.x, evt.y); // retrieve the first dom element
        
        if (!this.picker_hover_elem || this.picker_hover_elem != target) {
            this.picker_hover_elem = target;

            // retrieve the position of the element to move the overlay in front
            let rect = this.iframe_window.h.libs.dom.get_global_rect(this.picker_hover_elem, true);
            this.picker_hover_overlay.style.left = rect.x + "px";
            this.picker_hover_overlay.style.top = rect.y + "px";
            this.picker_hover_overlay.style.width = rect.width + "px";
            this.picker_hover_overlay.style.height = rect.height + "px";
        }

        this.picker_css_overlay.style.visibility = 'visible';
        this.picker_hover_overlay.style.visibility = 'visible';
    }
    picker_css_get_rule(evt){
        // first, get existing rules that apply to that element
        let result = {
            existing: [],
            new: []
        };

        this.#selectors = [];

        // check for existing selectors
        this.css_selectors.forEach((selector, i)=> {
            try {
                if (this.picker_hover_elem.matches(selector)) {
                    result.existing.push(selector);
                }
            } catch(e){
                this.css_selectors.splice(i, 1);
            }
        });

        let btns = [];
        
        if (result.existing.length > 0){
            btns.push(H_dom.create_element('DIV',{
                'class': 'preview_pick_css_selector separator existing',
                'style': 'pointer-events: none;'
            }, H_constants.get_text('preview_existing_rules')));

            result.existing.forEach((selector) => {
                let btn = H_dom.create_element('DIV',{
                    'class': 'preview_pick_css_selector existing'
                }, selector);
                h.e.add_event_click(btn, ()=>{this.rule_to_csseditor(selector);});
                btns.push(btn);
            });
        }

        this.generate_css_selector(this.picker_hover_elem);
        
        let sep_new = H_dom.create_element('DIV',{
            'class': 'preview_pick_css_selector separator new',
            'style': 'pointer-events: none;'
        }, H_constants.get_text('preview_new_rules'));
        H_dom.append_content(sep_new, H_dom.create_button_info(H_constants.get_text('add_rule_info'), {'style': 'pointer-events: auto;'}));
        btns.push(sep_new);


        let new_rule_added = false;
        this.#selectors.forEach((level, i) => {
            btns.push(H_dom.create_element('DIV',{
                'class': 'preview_pick_css_selector separator element',
                'style': 'pointer-events: none;'
            }, H_constants.get_text('preview_element', [i+1])));
            level.rules.forEach((selector) => {
                if (result.existing.indexOf(selector) == -1) {
                    new_rule_added = true;
                    let btn = H_dom.create_element('DIV',{
                        'class': 'preview_pick_css_selector new'
                    }, selector);
                    h.e.add_event_click(btn, ()=>{this.rule_to_csseditor(selector);});
                    btns.push(btn);
                }
            });
        });

        if (!new_rule_added){
            btns.push(H_dom.create_element('DIV',{
            'class': 'preview_pick_css_selector new empty',
            'style': 'pointer-events: none;'
        }, H_constants.get_text('preview_new_rules_empty')));
        }
        
        // menu déroulant pour sélectionner la rule à édité
        this.context_menu = H_ui_context_menu.create_instance(this.dom_id,{
            'class': 'preview_pick_css',
            buttons: btns
        });

        // the coordinates of evt are from the iframe.
        // Need to add the offsetX and Y of the iframe to it
        // also need to apply the same scale ratio to the coordinate inside the iframe
        // let rect_iframe = H_dom.get_global_rect(this.iframe, true);
        let rect_iframe = h.libs.dom.get_global_rect(this.iframe, true);
        let modified_evt = {
            clientX: (evt.clientX * this.current_scale) + rect_iframe.left,
            clientY: (evt.clientY * this.current_scale) + rect_iframe.top,
            pointerType: evt.pointerType
        };
        this.context_menu.open(modified_evt);

        this.stop_picker_css();
    }
    generate_css_selector(target){
        
        let level = 0;

        while (target && level < 10 && target.id != 'preview_module') {

            // ignore tinymce elements
            if (target.className.startsWith('tox-')) {
                target = target.parentElement;
                continue;
            }

            let rule_added = false;
            // for each level, if there is an id, add it. otherwise is class or tagName if no class
            if (target.id && !this.is_generated_id(target.id)) {
                // id for this level
                this.add_to_list_selectors('#' + target.id, level, 'id');
                rule_added = true;
            }

            if (target.className) {

                if (target.hasAttribute('data-documentid')){
                    if (target.classList[3]) this.add_to_list_selectors('.' + target.classList[3], level, 'name');
                    if (target.classList[2]) this.add_to_list_selectors('.' + target.classList[2], level, 'class_id');
                } else if (target.hasAttribute('data-block_id')) {
                    if (target.classList[2]) this.add_to_list_selectors('.' + target.classList[2], level, 'name');
                    if (target.classList[1]) this.add_to_list_selectors('.' + target.classList[1], level, 'class_id');
                } else {
                    this.add_to_list_selectors('.' + Array.from(target.classList).join("."), level, 'class');
                }

                rule_added = true;
            }

            if (!rule_added) this.add_to_list_selectors(target.tagName, level, 'class');

            // detect where to stop in the hierarchy, don't want to display every chain until body
            if (target.hasAttribute('data-current_id')) break;

            level++;
            target = target.parentElement;
        }
    }
    

    // will store selector generated by add_to_list_selectors() in an array of object
    #selectors = [];
    // each time a rule is added to a level, each smaller level will have a rule prefixed with the added one
    // type is either id, class, name or class_id (class_id is specific to block and document. it's a classname they have formed like document_ID_IN_DB where ID_IN_DB is a number)
    add_to_list_selectors(new_selector, level, type) {
        if (!this.#selectors[level]) this.#selectors[level] = {id: '', class: '', name: '', class_id: '', rules: []};

        if (type) this.#selectors[level][type] = new_selector;
        
        if (this.#selectors[level].rules.indexOf(new_selector) < 0){
            this.#selectors[level].rules.push(new_selector);
        }

        let ind = level - 1;
        let str_class = new_selector;
        let str_name = new_selector;
        let str_class_id = new_selector;
        while(ind > -1 && this.#selectors[ind]){

            if (this.#selectors[ind].class) {
                str_class = str_class + ' ' + this.#selectors[ind].class;
                if (this.#selectors[ind].rules.indexOf(str_class) < 0){
                    this.#selectors[ind].rules.push(str_class);
                }

                if (str_name != new_selector) {
                    if (this.#selectors[ind].rules.indexOf(str_name) < 0){
                        this.#selectors[ind].rules.push(str_name + ' ' + this.#selectors[ind].class);
                    }
                }
                if (str_class_id != new_selector) {
                    if (this.#selectors[ind].rules.indexOf(str_class_id) < 0){
                        this.#selectors[ind].rules.push(str_class_id + ' ' + this.#selectors[ind].class);
                    }
                }
            } else {
                if (this.#selectors[ind].name) {
                    str_name = str_name + ' ' + this.#selectors[ind].name;
                    if (this.#selectors[ind].rules.indexOf(str_name) < 0){
                        this.#selectors[ind].rules.push(str_name);
                    }
                }

                if (this.#selectors[ind].class_id) {
                    str_class_id = str_class_id + ' ' + this.#selectors[ind].class_id;
                    if (this.#selectors[ind].rules.indexOf(str_class_id) < 0){
                        this.#selectors[ind].rules.push(str_class_id);
                    }
                }
            }

            ind--;
        }
    }
    is_generated_id(id){
        return id.includes('¤DOM_ID') || id.includes('DOM_');
    }
    rule_to_csseditor(rule){
        if (!h.modules['csseditor_a'] || !h.modules['csseditor_a'][this.dom_id] || !h.modules['csseditor_a'][this.dom_id].exist()){
            let settings = this.ajax_settings();
            settings.url = H_constants.base_url + H_constants.admin_folder + 'csseditor/index.php';
            settings.data = {
                ...settings.data,
                preview: 1,
                admin: this.is_admin,
                force_admin_or_public: 1,
                source: this.css_source
            };
            settings.skip_container = false;
            settings.success = (res) => {
                let dom_elem = document.getElementById('preview_admin_container' + this.dom_id);
                H_dom.append_content(dom_elem.parentElement, res);

                h.modules['csseditor_a'][this.dom_id].load_rules({},rule);

                if (!H_ui_disposition.instances[this.dom_id] || !H_ui_disposition.instances[this.dom_id].exist()){
                    H_ui_disposition.create_instance(this.dom_id, {
                        'type': 'vertical',
                        'containers': ['preview_admin_container' + this.dom_id, 'csseditor_admin_container' + this.dom_id],
                        'open': true,
                    });
                } else {
                    H_ui_disposition.instances[this.dom_id].add_container(dom_elem.parentElement.lastElementChild, {
                        open: "open", 
                        separator: true, 
                        no_resize: true
                    });
                }
            };
            h.a.send(settings);
        } else {
            h.modules['csseditor_a'][this.dom_id].load_rules({},rule);
        }

        H_dom.remove_class(document.getElementById('preview_container_css' + this.dom_id), 'hidden');
        this.csseditor_is_open = true;
    }
    show_modal(modules){
        let settings = {};
        settings.content = '';
        settings.contentClass = 'pickerPreview';
        settings.buttons = [];
        modules.forEach((elem) => {
            let btn = {};
            btn.label = elem.name;
            btn.handler = ()=>{change_hash(elem.name, elem.id);};
            settings.buttons.push(btn);
        });
        let modal = new H_ui_prompt(settings);
        modal.set_parent(document.body);
    }
    change_hash(mod, param){
        let hash = mod;
        if (param) hash += '='+param;
        H_history.set_hash(hash, false, true);
    }
    toggle_preview_mode(evt){
        let targ = evt.target;
        let mode = targ.value;
        if (mode != this.prev_mode){
            if (this.prev_mode != '') {
                H_dom.toggle_class(this.iframe, this.prev_mode, false);
                H_dom.toggle_class(this.iframe.parentElement, this.prev_mode, false);
            }
            if (mode != '') {
                H_dom.toggle_class(this.iframe, mode, true);
                H_dom.toggle_class(this.iframe.parentElement, mode, true);
            }
            this.resize_iframe();
            this.prev_mode = mode;
        }
        // if (targ != prevBtnSwitch){
        //     H_dom.toggle_class(prevBtnSwitch, 'selected', false);
        //     H_dom.toggle_class(targ, 'selected', true);
        //     prevBtnSwitch = targ;
        // }
    }
    refresh_iframe() {
        this.toggle_ready_state_iframe();
        top.frames['preview_iframe' + this.dom_id].document.location.reload();
    }
    load_rules(selector, medias){

        let sheet_id = 'insertedFromDB';
        if (this.css_source != 'theme' && this.css_source != 'module') sheet_id = 'css_' + this.css_source;

        // add the selector to the list if not already present
        if (this.css_selectors.indexOf(selector) == -1) this.css_selectors.push(selector);
        if (!medias) return;
        
        for(let media in medias){
            let css = medias[media].replaceAll(/\r\n|\n|\r/g, '');
            if (css == '') continue;
            media = media.replace('@media ', '');
            let res = false;
            if (this.css_source == 'theme' || this.css_source == 'module') {
                top.frames['preview_iframe' + this.dom_id].h.libs.dom.remove_css_rule('insertedFromDB', selector, media);
                res = top.frames['preview_iframe' + this.dom_id].h.libs.dom.add_css_rule('insertedFromDB', selector, css, media);
            } else {
                top.frames['preview_iframe' + this.dom_id].h.libs.dom.remove_css_rule('css_' + this.css_source, selector, media);
                res = top.frames['preview_iframe' + this.dom_id].h.libs.dom.add_css_rule('css_' + this.css_source, selector, css, media);
            }

            if (res === false){
                // full refresh the iframe to change load the style
                this.refresh_iframe();
            }
        }
    }
    remove_rule(selector){
        let ind = this.css_selectors.indexOf(selector);
        if (ind > -1) this.css_selectors.splice(ind, 1);
    }
    init_iframe(){
        this.iframe_document = this.iframe.contentDocument ? this.iframe.contentDocument : this.iframe.document;
        this.iframe_window = this.iframe.contentWindow ? this.iframe.contentWindow : this.iframe.window;
        
        // after each load of the iframe, need to create overlay again
        this.picker_css_overlay = this.picker_hover_overlay = this.picker_overlay = false;
        
        this.toggle_ready_state_iframe(false);

        // resize_iframe();
        this.init_resize_observer();
    }
    toggle_ready_state_iframe(disable = true){
        H_dom.toggle_class(this.bar_outils, 'disable', disable);
    }
    init_resize_observer(){
        
        if (this.resize_observer.disconnect === "function" ){
            this.resize_observer.disconnect();
        }

        this.resize_observer = new ResizeObserver(entries => {
            if ((this.current_size.width != entries[0].contentRect.width || this.current_size.height != entries[0].contentRect.height) && entries[0].contentRect.height != 0 && entries[0].contentRect.width != 0) {
                this.current_size = {
                    width: entries[0].contentRect.width,
                    height: entries[0].contentRect.height
                };
                this.resize_iframe();
            }
        });
    
        this.resize_observer.observe(this.iframe.parentElement);
    }
    resize_iframe(){
        let rect_parent = H_dom.get_global_rect(this.iframe.parentElement, true);
        
        if (!this.iframe._ComputedStyleReady) {
            H_dom.get_style(this.iframe);
        }

        // reinit scale to get the real size of the iframe
        this.iframe.style.transform = 'scale(1)';
        let rect_iframe = H_dom.get_global_rect(this.iframe, true);

        // add a margin to the frame width, we prefer it a bit too small because otherwise the scale will hide a little bit of the top or bottom of the frame in certain size.
        rect_iframe.width += 10;
        rect_iframe.height += 10;

        // reduce size of the iframe to see it full size

        let scale = Math.round(Math.min(Math.round(rect_parent.width) / Math.round(rect_iframe.width), Math.round(rect_parent.height) / Math.round(rect_iframe.height), 1) * 100) / 100;
        this.iframe.style.transform = 'scale(' + scale + ')';
        this.current_scale = scale;
        
        // center it
        rect_iframe = H_dom.get_global_rect(this.iframe, true);
        let left = 'calc(50% - (' + (rect_iframe.width) + 'px / 2))';
        let top = 'calc(50% - (' + (rect_iframe.height) + 'px / 2))';
        
        this.iframe.style.left = left;
        this.iframe.style.top = top;
    }
    change_lang(evt){
        let targ = evt.target;
        let iso = targ.dataset.iso;
        if (iso == this.current_language) return;

        H_dom.remove_class(document.getElementById('preview_lang_' + this.current_language + this.dom_id), 'selected');
        H_dom.add_class(targ, 'selected');

        this.iframe.src = this.iframe.src.replace('language='+this.current_language, 'language='+iso);
        this.current_language = iso;
    }

    
    static modal = false;
    // function called by other module to load their preview
    static open_preview(data){
        if (!Preview_a.modal){
            let modalSettings = {
                class: 'modal_preview',
                modal: true,
                close: 'x',
                hidden: true,
                nodrag: true
            };
            Preview_a.modal = H_ui.add_window(document.body, modalSettings);
        }

        let settings = {};
        settings.url = H_constants.base_url + H_constants.admin_folder + 'preview/index.php';
        settings.dom_target = '';
        settings.data = data;
        settings.success = (res) =>{
            Preview_a.modal.set_content(res);
            Preview_a.modal.close = 'x';
            Preview_a.modal.show();
            setTimeout(function(){H_dom.set_alignment(Preview_a.modal.dom_element, 0.5 , 0.5, false, true);},50);
        };
        h.a.send(settings);
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Preview_a = Preview_a;