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

class Csseditor_a extends H_module {

    #theme = {
        id: 0,
        admin: false
    };

    #source = {
        id: 0,
        type: 'theme',
        name: '',
        id_item: ''
    };

    #order_pool_update = [];
    #order_timer = false;

    constructor(dom_id, params) {
        super(dom_id);

        // is linked to a preview module ? (same dom_id than preview)
        this.preview = params.preview;
        this.full_preview = false;

        // type of sources and their parameters
        this.#source.type = params.source;
        if (this.#source.type.includes('¤')){
            let t = this.#source.type.split('¤');
            this.#source.type = t[0];
            this.#source.id_item = t[1];
        }
        

        // this.current_module = '';
        // this.current_source_id = 0;

        // what we are editing
        this.current_rules = false;

        this.inputs_rule = [];
        this.input_selected = false;
        this.last_cursor_positon = false;

        this.dom_container = document.getElementById('csseditor_admin_container' + this.dom_id);
        this.hidden_id_theme = Array.from(document.getElementById('csseditor_admin_container' + this.dom_id).getElementsByClassName('csseditor_admin_hidden_id_theme'));

        this.previous_media = 1;
        this.modifying_rule = false;
        this.creating_rule = false;

        this.variables = {};
        
        this.init_form_rules();

        this.form_state = true;
        this.toggle_form_state();
    }
    get_ajax_basics_data() {
        let data = {
            'dom_id': this.dom_id,
            'preview': this.preview,
            'source': this.#source.type + (this.#source.id_item ? '¤'+this.#source.id_item : ''),
            'csseditor_theme-id': this.#theme.id,
            'csseditor_source-admin': this.#theme.admin,
            'csseditor_source-id': this.#source.id
        };
        if (this.#source.type == 'module'){
            let checkbox_admin = document.getElementById('csseditor_module_is_admin' + this.dom_id);
            data['csseditor_source-admin'] = checkbox_admin.checked;
            // if (this.#source.id == 0) {
                // data['csseditor_source-id'] = this.#source.id;
                data['source_module'] = this.#source.name;
            // }
        }
        return data;
    }
    init_form_rules() {
        // get all media textearea
        let container_medias = document.getElementsByClassName('csseditor_admin_media_detail');
        for (let i = 0; i < container_medias.length; i++) {
            this.init_input_media(container_medias[i]);
        }

        this.dom_current_rules = document.getElementById('csseditor_current_selector' + this.dom_id);
        this.btn_save_selector = document.getElementById('csseditor_btn_save_selector' + this.dom_id);
        this.btn_delete_selector = document.getElementById('csseditor_btn_del_rules' + this.dom_id);
        this.btn_save_rules = document.getElementById('csseditor_btn_save_rules' + this.dom_id);
        this.btn_add_rule = document.getElementById('csseditor_btn_prepare_add_one_rule' + this.dom_id);

        if (this.current_rules) {
            this.modifying_rule = true;
            this.toggle_state_buttons_selector(true);
        }

        // h.e.add_event(this.dom_current_rules, 'blur', this.blur_input_current_rule);
        h.e.add_event_key(this.dom_current_rules, this.keypress_input_current_selector.bind(this));
    }
    add_data_to_submit(form_settings){
        form_settings.extra_data = {
            'source': this.#source.type,
            'csseditor_theme-id': this.#theme.id,
            'csseditor_source-admin': this.#theme.admin ? 1 : 0,
            'csseditor_source-id': this.#source.id
        };
        if (this.#source.type == 'module'){
            form_settings.extra_data['source_module'] = this.#source.name;
        }
        h.a.send(form_settings);
    }

    set theme(new_theme){
        if (this.#theme.id == new_theme.id) return;

        let first_time = this.#theme.id === 0; // don't want to reload thing that are already loaded at first load

        if (this.#source.type == 'theme'){
            this.#source.id = parseInt(new_theme.id_source);
            this.#source.admin = parseInt(new_theme.admin);
        } else if (this.#source.type == 'module' && this.#source.admin != new_theme.admin){
            this.#source.admin = parseInt(new_theme.admin);
            let select = document.getElementById('csseditor_module_select' + this.dom_id);
            if (select) h.e.send_event(select, 'change');
            return;
        }
        delete(new_theme.id_source);

        this.#theme = new_theme;

        if (!first_time){
            // there are hidden inputs in some form in the module content, will change their value to the new selected theme
            this.hidden_id_theme.forEach(inp => inp.value = this.#theme.id);
            // id display aside of the select
            let id_info = document.getElementById('csseditor_info_id' + this.dom_id);
            if (id_info) id_info.textContent = id_info.textContent.replace(/\d+/, this.#theme.id); // replace number by theme id

            // reload the currently selected rules if there is one for this theme
            this.reset_rules();
            // change variable
            this.refresh_all_variable();

            if (this.#source.type == 'theme'){
                // refresh fonts and keyframe forms
                let settings = this.ajax_settings();
                settings.dom_target = document.getElementById('csseditor_form_font' + this.dom_id).parentElement;
                settings.data = {
                    ...this.get_ajax_basics_data(),
                    'csseditor_action': 'csseditor_form_font',
                };
                h.a.send(settings);

                settings.dom_target = document.getElementById('csseditor_form_keyframe' + this.dom_id).parentElement;
                settings.data = {
                    ...this.get_ajax_basics_data(),
                    'csseditor_action': 'csseditor_form_keyframes',
                };
                h.a.send(settings);
            }
        }
    }

    set source(new_source){
        if (this.#source.id == new_source.id && this.#source.name == new_source.name) return;

        this.#source.id = parseInt(new_source.id);
        this.#source.name = new_source.name;

        if (this.#source.type == 'module'){
            this.reset_rules();
            this.refresh_all_variable();

            // refresh fonts and keyframe forms
            let settings = this.ajax_settings();
            settings.dom_target = document.getElementById('csseditor_form_font' + this.dom_id).parentElement;
            settings.data = {
                ...this.get_ajax_basics_data(),
                'csseditor_action': 'csseditor_form_font',
            };
            h.a.send(settings);

            settings.dom_target = document.getElementById('csseditor_form_keyframe' + this.dom_id).parentElement;
            settings.data = {
                ...this.get_ajax_basics_data(),
                'csseditor_action': 'csseditor_form_keyframes',
            };
            h.a.send(settings);
        }

        this.toggle_form_state();
    }
    toggle_form_state(){
        if (this.#source.type != 'module') return;

        if (this.#source.name == '' && this.form_state){
            let btn_var = document.getElementById('csseditor_btn_add_variable' + this.dom_id);
            if (btn_var) {
                btn_var.disabled = true;
                H_dom.add_class(btn_var, 'disabled');
            }

            let btn_font = document.getElementById('csseditor_btn_save_font' + this.dom_id);
            if (btn_font) {
                btn_font.disabled = true;
                H_dom.add_class(btn_font, 'disabled');
            }

            let btn_keyframe = document.getElementById('csseditor_btn_save_keyframe' + this.dom_id);
            if (btn_keyframe) {
                btn_keyframe.disabled = true;
                H_dom.add_class(btn_keyframe, 'disabled');
            }
        } else if (!this.form_state) {
            this.form_state = true;

            let btn_var = document.getElementById('csseditor_btn_add_variable' + this.dom_id);
            if (btn_var) {
                btn_var.disabled = true;
                H_dom.add_class(btn_var, 'disabled');
            }

            let btn_font = document.getElementById('csseditor_btn_save_font' + this.dom_id);
            if (btn_font) {
                btn_font.disabled = true;
                H_dom.add_class(btn_font, 'disabled');
            }

            let btn_keyframe = document.getElementById('csseditor_btn_save_keyframe' + this.dom_id);
            if (btn_keyframe) {
                btn_keyframe.disabled = true;
                H_dom.add_class(btn_keyframe, 'disabled');
            }
        }
    }

    // INPUT MEDIA
    init_input_media(container) {
        let id = container.dataset.id;
        let rule = {
            container: container,
            media: id,
            property: document.getElementById('csseditor_rule_property_' + id + this.dom_id),
            active: document.getElementById('csseditor_rule_active_' + id + this.dom_id),
            default: document.getElementById('csseditor_rule_default_' + id + this.dom_id)
        };
        this.inputs_rule.push(rule);

        h.e.add_event(rule.property, 'focus', this.focus_input_media.bind(this));
        h.e.add_event(rule.property, 'blur', this.blur_input_media.bind(this));
        h.e.add_event_key(rule.property, this.on_keydown_input_media.bind(this));
    }
    refresh_input_rules() {
        let settings = this.ajax_settings();
        settings.dom_target = 'csseditor_subcontainer_edit_rules' + this.dom_id;
        settings.replace_dom_target = true;
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_form_rules',
            'csseditor_theme-id': this.#theme.id,
        };
        settings.success = () => {
            this.inputs_rule = [];
            this.init_form_rules();
            if (this.current_rules) {
                this.load_rules(false, this.current_rules);
            }
        };
        h.a.send(settings);
    }
    // var wait_for_switch_media = false; // timer to handle media switch.
    
    focus_input_media(evt) {

        this.input_selected = evt.target;
        H_dom.toggle_class(this.input_selected, 'selected', true);

        let id = evt.target.dataset.id;
        if (this.previous_media != id) {
            this.previous_media = id;
            // if (wait_for_switch_media) clearTimeout(wait_for_switch_media);
            this.switch_media_variable(id);
        }
    }
    blur_input_media(evt) {
        this.last_cursor_positon = { 'start': this.input_selected.selectionStart, 'end': this.input_selected.selectionEnd };

        // if (this.previous_media != 1){
        //     this.previous_media = 1;
        //     if (wait_for_switch_media) clearTimeout(wait_for_switch_media);
        //     wait_for_switch_media = setTimeout(()=>{console.log('from blur');this.switch_media_variable(1);},100);
        // }
    }
    on_keydown_input_media(evt) {
        if (evt.ctrlKey) {
            switch (evt.key) {
                case 'A': // a for qwerty, q for azerty
                case 'a': // a for qwerty, q for azerty
                    // add a calc
                    // this.insert_at_cursor(evt.target, 'calc(px + var(--fhd-vr));', 5);
                    break;
            }
        }
    }
    insert_at_cursor(input, value, posCaret = false) {
        if (input.selectionStart || input.selectionStart == '0') {
            var startPos = input.selectionStart;
            var endPos = input.selectionEnd;
            input.value = input.value.substring(0, startPos) + value + input.value.substring(endPos, input.value.length);
            if (posCaret !== false) {
                input.selectionStart = startPos + posCaret;
                input.selectionEnd = startPos + posCaret;
            } else {
                input.selectionStart = startPos + value.length;
                input.selectionEnd = startPos + value.length;
            }
        } else {
            input.value += value;
            if (posCaret !== false) {
                input.selectionStart = 0 + posCaret;
                input.selectionEnd = 0 + posCaret;
            }
        }
    }
    insert_at_last_cursor_position(input, str) {
        if (this.last_cursor_positon.start || this.last_cursor_positon.start == '0') {
            input.value = input.value.substring(0, this.last_cursor_positon.start) + str + input.value.substring(this.last_cursor_positon.end, input.value.length);
            this.last_cursor_positon.end = this.last_cursor_positon.start + str.length;
        } else {
            input.value = str;
        }
    }
    tool_calc(evt) {
        if (!this.input_selected) return;
        this.insert_at_last_cursor_position(this.input_selected, 'calc(px * var(--fhd-hr));');
        this.input_selected.focus();
        // put the cursor before the px in the formula
        this.input_selected.selectionStart = this.last_cursor_positon.start + 5;
        this.input_selected.selectionEnd = this.last_cursor_positon.start + 5;
    }
    // clean all field from previous selected rules
    clean_rules() {
        this.inputs_rule.forEach((rule) => {
            rule.container.open = false;
            rule.property.value = '';
            rule.active.checked = true;
            H_dom.add_class(rule.default, 'hidden');
        });
    }
    restore_default_rule(id_media) {
        this.clean_rules();

        let settings = this.ajax_settings();
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_restore_default_rule',
            'csseditor_rules-selector': this.current_rules,
            'csseditor_rules-id_media': id_media
        };
        settings.success = this.apply_rules.bind(this);
        h.a.send(settings);
    }
    keypress_input_current_selector(event) {
        if (event.target.value != '') this.toggle_state_buttons_selector(true);
        else this.toggle_state_buttons_selector(false);
    }

    // THEME
    on_click_edit_theme(evt) {
        H_ui.open_popup_modal(evt, 'csseditor', {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_form_theme'
        });
    }
    on_click_del_theme() {
        let settings = this.ajax_settings();
        settings.dom_target = this.dom_container.id;
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_delete_theme',
            'csseditor_theme-id': this.#theme.id
        };
        h.a.send(settings);
    }
    on_change_theme(evt) {
        let id = evt.target.selectedOptions[0].value;
        let admin = evt.target.selectedOptions[0].dataset.admin;
        let id_source = evt.target.selectedOptions[0].dataset.id_source;
        this.theme = {id, admin, id_source};
    }
    on_change_module(evt) {
        let checkbox_admin = document.getElementById('csseditor_module_is_admin' + this.dom_id);
        let select_module = document.getElementById('csseditor_module_select' + this.dom_id);
        let module = {
            id: checkbox_admin.checked ? select_module.selectedOptions[0].value : select_module.selectedOptions[0].dataset.id_public,
            name: select_module.selectedOptions[0].dataset.name,
        };
        this.source = module;
        this.#theme.admin = checkbox_admin.checked;
    }
    load_rules(evt, force_rule = false) {
        // clean all field from previous selected rules
        this.clean_rules();

        // get the rule to load
        let rule = '';
        if (force_rule !== false) {
            rule = force_rule;
        } else {
            rule = evt.target.textContent;
        }

        this.current_rules = rule;
        this.dom_current_rules.value = this.current_rules;
        // this.dom_current_rules.title = this.current_rules;
        // load properties of a rule
        let settings = this.ajax_settings();
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_load_rules',
            'csseditor_rules-selector': this.current_rules
        };
        settings.success = this.apply_rules.bind(this);
        h.a.send(settings);
    }
    apply_rules(json) {
        if (json) {
            let data = JSON.parse(json);
            this.modifying_rule = true;
            this.creating_rule = false;
            let one_opened = false;
            this.inputs_rule.forEach((rule) => {
                if (data[rule.media]) {
                    if (data[rule.media].properties != ''){
                        one_opened = true;
                        // open inputs only if there are some properties to display
                        rule.container.open = true;
                        rule.property.value = this.readable_css(data[rule.media].properties);
                    }
                    rule.active.checked = data[rule.media].active;
                    H_dom.toggle_class(rule.default, 'hidden', !data[rule.media].initial);
                }
            });
            if (!one_opened){
                this.inputs_rule[0].container.open = true;
            }
        } else {
            this.modifying_rule = false;
            this.creating_rule = true;
        }
        
        this.toggle_state_buttons_selector(true);
    }
    toggle_state_buttons_selector(state = false) {
        if (this.#source.type == 'module' && this.#source.name == '') state = false;
        this.btn_save_selector.disabled = !state;
        H_dom.toggle_class(this.btn_save_selector, 'disabled', !state);

        // only if we are modifying an existing rule
        state = this.modifying_rule ? true : false;
        this.btn_delete_selector.disabled = !state;
        H_dom.toggle_class(this.btn_delete_selector, 'disabled', !state);
        
        H_dom.toggle_class(this.btn_add_rule, 'hidden', !state);

        state = (this.modifying_rule || this.creating_rule) ? true : false;
        this.btn_save_rules.disabled = !state;
        H_dom.toggle_class(this.btn_save_rules, 'disabled', !state);
    }
    /**
     * Clean all rules fields to add a new one.
     */
    prepare_add_one_rule() {
        this.clean_rules();
        this.current_rules = '';
        this.modifying_rule = false;
        this.creating_rule = false;
        this.dom_current_rules.value = '';
        this.toggle_state_buttons_selector(true);
    }
    // var clean_rule
    readable_css(css) {
        css = css.replaceAll(';', ';\r\n');
        if (css.includes('{')) {
            css = css.replaceAll('{', ' {\r\n');
            css = css.replaceAll('}', '}\r\n');
            css = css.replaceAll(/(.+;)+/g, '    $1');
        }
        return css;
        // return css.replaceAll(';',';\r\n').replaceAll('\\"', '"');
    }
    // this.openEditSelector = function(evt){
    //     if (this.current_rules) {
    //         H_ui.open_popup_modal(evt,'csseditor',{
    //             'dom_id': this.dom_id,
    //             'csseditor_action': 'csseditor_form_selector',
    //             'csseditor_rules-id_theme': this.#theme.id,
    //             'csseditor_rules-selector': this.current_rules
    //         });
    //     } else {
    //         evt.target.disabled = true;
    //         H_dom.add_class(evt.target,'disabled');
    //     }
    // };
    save_selector(evt) {
        if (!this.current_rules && (this.modifying_rule || this.creating_rule)) return;

        let val = this.dom_current_rules.value;
        if (!val) return;

        let settings = this.ajax_settings();
        settings.data = {
            ...this.get_ajax_basics_data(),
            csseditor_action: 'csseditor_save_selector',
            previous_selector: this.creating_rule ? '' : this.current_rules,
            'csseditor_rules-selector': val
        };
        settings.success = (res) => {
            if (this.#source.id == 0) {
                if (this.#source.type == 'module'){
                    // in the case of the first item added to a source module, the id of the new source
                    // is returned. Have to change the value of the option in the select
                    let select = document.getElementById('csseditor_module_select' + this.dom_id);
                    for(let i = 0; i < select.options.length; i++){
                        let opt = select.options[i];
                        if (opt.dataset.name == this.#source.name) {
                            this.#source.id = parseInt(res);
                            break;
                        }
                    }
                } else if (this.#source.type == 'block' || this.#source.type == 'document'){
                    this.#source.id = parseInt(res);
                }
            }

            // H_ui.message_popup_timed(H_constants.get_text('tlc_saved'));
            this.refresh_list_rules();
            this.current_rules = val;
            this.load_rules({}, this.current_rules);

            if (this.preview) {
                if (settings.data.previous_selector != ''){
                    // remove previous selector
                    h.modules.preview_a[this.dom_id].css_selectors.splice(h.modules.preview_a[this.dom_id].css_selectors.indexOf(settings.data.previous_selector), 1);
                }
                h.modules.preview_a[this.dom_id].load_rules(this.current_rules, false);
            }
        };
        h.a.send(settings);
    }
    open_list_rules(evt) {
        H_ui.open_popup_modal(evt, 'csseditor', {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_list_rules',
        });
    }
    save_rules(evt) {
        if (this.current_rules) {
            let settings = this.ajax_settings();
            settings.data = {
                ...this.get_ajax_basics_data(),
                csseditor_action: 'csseditor_save_rules',
                'csseditor_rules-selector': this.current_rules,
                create_rules: this.creating_rule
            };
            let styles = {};
            this.inputs_rule.forEach((rule) => {
                let val = rule.property.value;
                settings.data[rule.property.name] = val;
                settings.data[rule.active.name] = rule.active.checked;
                styles[rule.property.dataset.value] = this.readable_css(val);
            });
            settings.success = (res) => {
                this.refresh_input_rules();
                this.refresh_list_rules();
                if (this.preview) {
                    h.modules.preview_a[this.dom_id].load_rules(this.current_rules, styles);
                }
            };
            h.a.send(settings);
        }
    }
    delete_rules(evt, confirm = false) {
        if (this.current_rules) {
            if (!confirm) {
                H_ui.confirm_popup(evt.target.dataset.confirm, (event) => { this.delete_rules(event, true); });
                return false;
            }

            let settings = this.ajax_settings();
            settings.data = {
                ...this.get_ajax_basics_data(),
                'csseditor_action': 'csseditor_delete_rules',
                'csseditor_rules-selector': this.current_rules,
            };
            settings.success = (res) => {
                if (this.preview) {
                    h.modules.preview_a[this.dom_id].refresh_iframe();
                    h.modules.preview_a[this.dom_id].remove_rule(this.current_rules);
                }
                this.reset_rules();
            };
            h.a.send(settings);
        } else {
            evt.target.disabled = true;
            H_dom.add_class(evt.target, 'disabled');
        }
    }

    change_rule_order(id, value) {
        if(this.#order_timer !== false){
            clearTimeout(this.#order_timer);
            this.#order_timer = false;
        }
        this.#order_pool_update.push({id, value});

        this.#order_timer = setTimeout(this.send_order_update.bind(this, id), 500);

        
    }
    send_order_update(){
        let settings = this.ajax_settings();
        settings.no_timer = true;
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_change_order_rule',
            'orders': this.#order_pool_update,
        };
        h.a.send(settings);

        this.#order_pool_update = [];
        clearTimeout(this.#order_timer);
        this.#order_timer = false;
    }

    // MULTIPLE CLASSES
    save_multi_rules(evt) {
        let settings = this.ajax_settings();
        settings.data = {
            ...this.get_ajax_basics_data(),
            csseditor_action: 'csseditor_parse_rules'
        };
        
        let inp = document.getElementById('csseditor_multirules_inp' + this.dom_id);
        settings.data[inp.name] = inp.value;

        let sel = document.getElementById('csseditor_multirules_sel' + this.dom_id);
        settings.data[sel.name] = sel.value;
        
        settings.success = (res) => {
            if (this.#source.type == 'module' && this.#source.id == 0) {
                // in the case of the first item added to a source module, the id of the new source
                // is returned. Have to change the value of the option in the select
                let select = document.getElementById('csseditor_module_select' + this.dom_id);
                for(let i = 0; i < select.options.length; i++){
                    let opt = select.options[i];
                    if (opt.dataset.name == this.#source.name) {
                        this.#source.id = parseInt(res);
                        break;
                    }
                }
            } else if (this.#source.type == 'block' || this.#source.type == 'document'){
                this.#source.id = parseInt(res);
            }

            this.refresh_list_rules(false);

            if (this.preview) {
                h.modules.preview_a[this.dom_id].refresh_iframe();
            }
        };
        h.a.send(settings);
    }

    // VARIABLE
    // change the properties display in the variable to the selected media (when focus textarea)
    switch_media_variable(id_media) {
        let i = 0;
        for (let source in this.variables) {
            for (let key in this.variables[source]) {
                i++;
                let variable = this.variables[source][key];
                if (variable.medias[id_media]) {
                    let properties = Array.from(document.getElementById('csseditor_var-' + i + this.dom_id).getElementsByClassName('csseditor_admin_var_properties'));
                    properties.forEach(property => {
                        H_dom.toggle_class(property, 'hidden', property.dataset.id != id_media);
                    });
                }
            }
        }
    }
    edit_variable(evt, name, index) {
        H_ui.open_popup_modal(evt, 'csseditor', {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_form_variable',
            'csseditor_variables-name': name,
            'index': index
        });
    }
    add_variable(evt) {
        H_ui.open_popup_modal(evt, 'csseditor', {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_form_variable',
            'index': -1
        });
    }
    refresh_variable(id_source, index, name) {
        let settings = this.ajax_settings();
        settings.dom_target = 'csseditor_var-' + id_source + '-' + index + this.dom_id;
        settings.replace_dom_target = true;
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_refresh_variable',
            'csseditor_variables-name': name,
            'index': index
        };
        if (this.preview) {
            settings.success = () => {
                h.modules.preview_a[this.dom_id].refresh_iframe();
            };
        }
        h.a.send(settings);
    }
    refresh_all_variable() {
        let settings = this.ajax_settings();
        settings.dom_target = 'csseditor_container_variables' + this.dom_id;
        settings.data = {
            ...this.get_ajax_basics_data(),
            csseditor_action: 'csseditor_refresh_all_variable',
            preview_embed: this.preview
        };
        if (this.preview) {
            settings.success = () => {
                h.modules.preview_a[this.dom_id].set_theme(this.#theme.id);
            };
        }
        
        h.a.send(settings);

    }
    delete_variable(id_source, index) {
        H_ui.popup_modal.hide();
        H_dom.remove_element(document.getElementById('csseditor_var-' + id_source + '-' + index + this.dom_id));
        if (this.preview) h.modules.preview_a[this.dom_id].refresh_iframe();
    }
    add_variable_to_input(evt) {
        h.e.stop_event(evt);
        let name = evt.target.dataset.name;
        if (this.input_selected) this.insert_at_last_cursor_position(this.input_selected, 'var(' + name + ')');
    }
    switch_variable_type(evt) {
        let inps = evt.target.form.getElementsByClassName('csseditor_admin_variable_input');
        for (let i = 0; i < inps.length; i++) {
            if (inps[i].dataset.color == '1') {
                H_dom.toggle_class(inps[i], 'hidden', !evt.target.checked);
            } else {
                H_dom.toggle_class(inps[i], 'hidden', evt.target.checked);
            }
        }
    }

    reset_rules() {
        this.current_rules = false;
        this.dom_current_rules.value = '';

        this.clean_rules();

        this.modifying_rule = false;
        this.creating_rule = false;
        this.toggle_state_buttons_selector(false);

        this.refresh_list_rules(false);
    }
    refresh_list_rules(keep_user_entry = true) {
        let user_entry = '';
        if (keep_user_entry) user_entry = H_ui_precomplete.instances[this.dom_id].input_search.value;
        let settings = this.ajax_settings();
        settings.dom_target = 'csseditor_rules_precomplete' + this.dom_id;
        settings.replace_dom_target = true;
        settings.data = {
            ...this.get_ajax_basics_data(),
            'csseditor_action': 'csseditor_refresh_precomplete',
        };
        settings.success = (id_source) => {
            if (user_entry != '') {
                H_ui_precomplete.instances[this.dom_id].input_search.value = user_entry;
                H_ui_precomplete.instances[this.dom_id].on_input(false);
            }
        };
        h.a.send(settings);
    }
    on_change_duplic(target) {
        let inp = document.getElementById('csseditor_select_theme_duplic' + this.dom_id + '_parent');
        let lab = document.getElementById('csseditor_select_theme_duplic' + this.dom_id + '_label');
        if (target.checked) {
            H_dom.toggle_class(inp, 'hidden', false);
            H_dom.toggle_class(lab, 'hidden', false);
        } else {
            H_dom.toggle_class(inp, 'hidden', true);
            H_dom.toggle_class(lab, 'hidden', true);
        }
    }
    open_export(evt) {
        window.open(this.url + '?csseditor_action=csseditor_open_export&csseditor_theme-id=' + this.#theme.id);
    }

    extract_into_file(extract_type){
        let settings = this.ajax_settings();
        settings.data = {
            ...this.get_ajax_basics_data(),
            csseditor_action: 'csseditor_extract',
            extracting: extract_type
        };
        settings.success = (res) => {
            let data = JSON.parse(res);
            
            if (data.type == 'theme') {
                fetch(this.url + '?csseditor_action=csseditor_download_theme').then(resp => {
                    if (resp.status === 200) {
                        return resp.blob();
                    } else {
                        return Promise.reject('Error: Unable to fetch the file.');
                    }
                }).then(blob => {
                    let url = window.URL.createObjectURL(blob);
                    let a = document.createElement("a");
                    a.href = window.URL.createObjectURL(blob);
                    a.setAttribute("download", data.name);
                    a.style.display = "none";
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                });
            } else {
                H_ui.message_popup_timed(data.msg);
            }
        };
        h.a.send(settings);
    }

    exist() {
        let parent = document.getElementById('csseditor_admin_container' + this.dom_id);
        return (parent);
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Csseditor_a = Csseditor_a;