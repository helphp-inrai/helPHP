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

class Icons_a extends H_module {
    constructor(dom_id) {
        super(dom_id);
        this.input_search = document.getElementById('icons_input_search' + dom_id);
        this.input_reset = document.getElementById('icons_input_reset' + dom_id);

        this.container_icons = document.getElementById('icons_container_icons' + dom_id);
        this.icons = Array.from(this.container_icons.getElementsByClassName('icons_admin_card_icon'));
        this.icons.forEach((icon)=>{
            h.e.add_event_click(icon, (event)=>{
                H_ui.copy_to_clipboard("H::icon('" + icon.dataset.name + "')");
            });
            h.e.add_event_click(icon.getElementsByClassName('icons_admin_copy_name')[0], (event)=>{
                H_ui.copy_to_clipboard(icon.dataset.name);
            });
        });

        h.e.add_event_click(this.input_reset, this.clear_search.bind(this));
        h.e.add_event_key(this.input_search, this.on_key_press.bind(this));

    }

    on_key_press(evt) {
        let value = this.input_search.value;
        
        this.icons.forEach((icon)=>{
            let name = icon.dataset.name;
            if (name.includes(value)){
                H_dom.remove_class(icon, 'hidden');
            } else {
                H_dom.add_class(icon, 'hidden');
            }
        });
    }

    clear_search() {
        this.input_search.value = '';
        
        this.icons.forEach((icon)=>{
            H_dom.remove_class(icon, 'hidden');
        });
    }

}

h.modules_class = h.modules_class || {};
h.modules_class.Icons_a = Icons_a;