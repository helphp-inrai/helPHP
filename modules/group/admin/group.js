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
class Group_a extends H_module{
    constructor(dom_id, settings) {
        super(dom_id);

        this.widget_id = settings.widget_id;

        this.dom_container = document.getElementById('group_widget' + dom_id);
    }

    add(evt){
        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            group_action: 'group_add_content',
            widget_id: this.widget_id,
        };
        settings.success = (result) => {
            H_dom.append_content(this.dom_container, result);
        };
        h.a.send(settings);
    }
    save(evt, id){
        let targ = evt.target;
        let value = targ.selectedOptions[0].value;

        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            group_action: 'group_save_content',
            'group_content-id': id,
            'group_content-id_group_data': value
        };
        settings.success = (msg) => {
            H_ui.message_popup_timed(msg);
        };
        h.a.send(settings);
    }
    delete(evt, id){
        let targ = evt.target;

        let settings = this.ajax_settings();
        settings.data.group_action = 'group_delete_content';
        settings.data['group_content-id'] = id;
        settings.success = (res) => {
            H_dom.remove_element(targ.parentElement);
        };
        h.a.send(settings);
    }

    exist(){
        let domElem = document.getElementById(this.dom_container.id);
        return (domElem) ? true : false;
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Group_a = Group_a;