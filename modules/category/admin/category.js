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

class Category_a extends H_module {
    constructor(dom_id, settings) {
        super(dom_id);
        
        // this.field_identifier = settings.field_identifier;
        // this.id_item = settings.id_item;
        this.widget_id = settings.widget_id;

        this.dom_target = 'category_widget' + this.dom_id;
        this.dom_container = document.getElementById('category_widget' + this.dom_id);
        this.parent_list = document.getElementById('category_widget_list' + this.dom_id);
    }

    add(id, name) {
        id = id > 0 ? id : name;
        // let self = this;
        let settings = this.ajax_settings();
        settings.dom_target = this.dom_target;
        settings.data = {
            ...settings.data,
            category_action: 'category_add_content',
            widget_id: this.widget_id,
            'category_content-id_data': id,
            name
        };
        // settings.data.category_action = 'category_add_content';
        // settings.data['category_content-field_identifier'] = this.field_identifier;
        // settings.data['category_content-id_item'] = this.id_item;
        // settings.data['category_content-id_data'] = id;
        // settings.data['name'] = name;
        // settings.success = function (result) {
        //     // debugger;
        //     H_dom.append_content(self.parent_list, result);

        // };
        h.a.send(settings);
    }
    delete(id) {
        let settings = this.ajax_settings();
        settings.dom_target = this.dom_target;
        settings.data.category_action = 'category_delete_content';
        settings.data['widget_id'] = this.widget_id;
        settings.data['category_content-id'] = id;
        // let self = this;
        // settings.success = function () {
        //     let elem = document.getElementById('category_widget_line-' + id + self.dom_id);
        //     H_dom.remove_element(elem);
        // };
        h.a.send(settings);
    }

    exist() {
        let main_container = document.getElementById(this.dom_container.id);
        return (main_container);
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Category_a = Category_a;