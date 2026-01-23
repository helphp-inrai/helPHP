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

class Users_a extends H_module{
    constructor(dom_id, id_country) {
        super(dom_id);

        this.state_select = document.getElementById('users_address-id_country_state' + dom_id);
        this.state_label = document.getElementById('users_address-id_country_state' + dom_id + '_label');

        this.on_change_country(id_country);
    }
    on_change_country(id_country){
        if (id_country == 59){ // united states of america
            
            this.state_select.style.display = 'inline-block';
            this.state_label.style.display = 'inline-block';
            this.state_select.setAttribute('data-required', '1');

        } else {
            
            this.state_select.style.display = 'none';
            this.state_label.style.display = 'none';
            this.state_select.removeAttribute('data-required');
            
        }
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Users_a = Users_a;