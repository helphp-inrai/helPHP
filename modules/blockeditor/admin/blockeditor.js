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
class Blockeditor_a extends H_module {
    constructor(dom_id, settings) {
        super(dom_id);
        this.ace_editor =[];
        this.last_key = 0;
    }
    
    add_field(evt){
        //~ console.log('add field');
        let val = evt.target.dataset.value;
        let id = document.getElementById('blockeditor_current_id' + this.dom_id).value;
        if (val){
            let settings = this.ajax_settings();
            settings.skip_container = true;
            settings.data = {
                'blockeditor_action': 'blockeditor_add_field',
                'dom_id': this.dom_id,
                'key': this.last_key,
                'type': val,
                'current_id': id
            };
            settings.success = (res) =>{
                this.last_key++;
                let dom_target = document.getElementById('blockeditor_fields' + this.dom_id);
                H_dom.append_content(dom_target, res);
                h.v.check_form(dom_target.form);
            };
            h.a.send(settings);
        }
    }
    
    del_field(evt){
        let targ = evt.target;
        var next = targ.nextElementSibling;
        let i = 0;
        
        while(i < 10 && next && !next.classList.contains('button_delete')) {
            H_dom.remove_element(next);
            next = targ.nextElementSibling;
        }

        H_dom.remove_element(targ);
    }

    add_multirad(field_key){
        let parent = document.getElementById('blockeditor_multirad_values' + field_key + this.dom_id);
        let lastChild = parent.lastElementChild;
        let index = 0;
        if (lastChild && lastChild.dataset.index) {
            index = parseInt(lastChild.dataset.index) + 1;
        }
        
        let settings = this.ajax_settings();
        settings.data = {
            'blockeditor_action': 'blockeditor_add_multirad',
            'dom_id': this.dom_id,
            'field_key': field_key,
            'index': index,
            'value': ''
        };
        settings.success = (res) =>{
            H_dom.append_content(parent, res);

            // get last child (the newly added element) to activate validator on it
            let lastChild = parent.lastElementChild;
            for(let i = 0; i < lastChild.children.length; i++){
                if (lastChild.children[i].tagName == 'INPUT'){
                    h.v.display_error_field(lastChild.children[i], []);
                    h.v.protect_input(lastChild.children[i]);
                }
            }
        };
        h.a.send(settings);
    }
    del_multirad(evt){
        let parent = evt.target.parentElement;
        H_dom.remove_element(parent);
    }
    activate_ace_editor(id,mode){
        document.getElementById(id).style.fontSize = '0.8em';
        this.ace_editor[mode] = ace.edit(id, {
            theme: 'ace/theme/tomorrow_night_blue',
            mode: 'ace/mode/javascript',
            autoScrollEditorIntoView: true,
            tabSize: 4,
            enableBasicAutocompletion: true,
            enableSnippets: true,
            enableLiveAutocompletion: false,
            useSoftTabs: true,
        });
    }
    save_js(is_public,mode){
        // console.log("blockeditor_data-js"+is_public+this.dom_id);
        document.getElementById("block_data-js" + is_public + this.dom_id).value = this.ace_editor[mode].getValue();
        document.getElementById("blockeditor_save_js_hidden_button" + is_public + this.dom_id).click();
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Blockeditor_a = Blockeditor_a;