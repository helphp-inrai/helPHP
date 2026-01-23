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
class Tabs_a extends H_module {
    constructor(dom_id, preview = false) {
        super(dom_id);
        
        this.selected = -1;
        this.tab_list = {};
        this.total = 0;

        this.reah_hash = '';

        this.menu_container = document.getElementById('tabs_parent-' + dom_id);
        if (preview) {
            let preview_menu = document.getElementById('tab_elem-0');
            let preview_container = document.getElementById('tab_container-0');
            if (preview_menu) {
                h.e.add_event_click(preview_menu, this.on_click_tab.bind(this));
                
                let btn_refresh = preview_menu.firstElementChild;
                h.e.add_event_click(btn_refresh, this.refresh_tab.bind(this));

                H_dom.append_content(document.getElementById('lemain'), preview_container);
                H_dom.remove_class(preview_container, 'hidden');

                this.tab_list['preview'] = [0];
                this.toggle_tab(0, true);
            }
        }
    }

    change_hash(query_settings, force_new_tab = false) {
        
        let tab_number = false;
        // tab_number is set when we want to reload a tab to force the order of the tab
        if (query_settings.data.tab_number) {
            tab_number = parseInt(query_settings.data.tab_number);
            delete (query_settings.data.tab_number);
        }

        this.reah_hash = document.location.hash.substring(1);
        document.location.hash = 'tabs';
        h.table_hash.push('tabs');

        // check if the hash has been loaded previously
        force_new_tab = (h.e.keys.shiftKey) ? true : force_new_tab;
        if (this.tab_list[query_settings.url] && !force_new_tab) {
            this.toggle_tab(this.selected);
            if (this.tab_list[query_settings.url].length == 1) this.toggle_tab(this.tab_list[query_settings.url][0], true);
            else {
                let found = -1;
                this.tab_list[query_settings.url].forEach((number, key) => {
                    if (this.selected == number) {
                        found = key;
                    }
                });
                if (found > -1 && found + 1 < this.tab_list[query_settings.url].length) {
                    this.toggle_tab(this.tab_list[query_settings.url][found + 1], true);
                } else {
                    this.toggle_tab(this.tab_list[query_settings.url][0], true);
                }
            }
            return;
        }
        this.add_tab(query_settings, tab_number);

    }

    check_number_exist(number){
        let exist = document.getElementById('tab_elem-' + number);
        let elem = document.querySelector('[');
    }
    add_tab(query_settings, forced_number = false) {
        if (this.selected > -1) {
            this.toggle_tab(this.selected);
        }

        let number;
        if (forced_number === false) {
            this.total++;
            number = this.total;
        } else {
            number = forced_number;
        }

        // check if a tab with this number exist and change it to last in the case
        if (document.getElementById('tab_elem-' + number)){
            this.total++;
            number = this.total;
        }

        // let moduleName = query_settings.url.split('#')[1].split('/')[0].split('=')[0];
        // add a parameters to the query strings to indicate to the module that it need to send his name for the tab
        // query_settings.data.tab = number;
        let menu = H_dom.create_element('DIV', { 'class': 'tab_elem ' + this.dom_id, 'id': 'tab_elem-' + number, 'data-number': number, 'data-url': query_settings.url });
        let refresh_btn = H_dom.create_icon('refresh-ccw', {'id': 'tab_elem_btn_refresh-' + number + this.dom_id, 'class': 'btn refresh'});
        // let refresh_btn = H_dom.create_element('SPAN', { 'id': 'tab_elem_btn_refresh-' + number + this.dom_id, 'class': 'btn refresh' }, 'r');
        let label_name = (H_history.from_link) ? H_history.last_link_clicked : this.reah_hash.split('|')[0];
        let name = H_dom.create_element('SPAN', { 'class': 'name', 'id': 'tab_elem_name-' + number }, label_name);
        // let close_btn = H_dom.create_element('SPAN', { 'class': 'btn delete' }, 'x');
        let close_btn = H_dom.create_icon('x', {'class': 'btn delete'});
        H_dom.append_content(menu, [refresh_btn, name, close_btn]);
        if (forced_number === false) {
            H_dom.append_content(this.menu_container, menu);
        } else {
            // retrieve the previous tab we
            let prev = this.menu_container.firstElementChild;
            let prev2 = prev.nextElementSibling;
            while (parseInt(prev.dataset.number) < forced_number && prev2 && parseInt(prev2.dataset.number) < forced_number) {
                prev = prev2;
                prev2 = prev.nextElementSibling;
            }
            H_dom.insert_after(menu, prev);
        }

        h.e.add_event_click(menu, this.on_click_tab.bind(this));
        h.e.add_event_click(close_btn, this.close_tab.bind(this));
        h.e.add_event_click(refresh_btn, this.refresh_tab.bind(this));

        let container = H_dom.create_element('DIV', { 'class': 'tab_container hidden ' + this.dom_id, 'id': 'tab_container-' + number });
        H_dom.append_content(document.getElementById('lemain'), container);

        query_settings.dom_target = 'tab_container-' + number;
        h.a.send(query_settings, 100);

        if (this.tab_list[query_settings.url]) this.tab_list[query_settings.url].push(number);
        else this.tab_list[query_settings.url] = [number];
        this.toggle_tab(number, true);
    }
    toggle_tab(number, state = false) {
        H_dom.toggle_class(document.getElementById('tab_elem-' + number), 'selected', state);
        H_dom.toggle_class(document.getElementById('tab_container-' + number), 'selected', state);
        H_dom.toggle_class(document.getElementById('tab_container-' + number), 'hidden', !state);
        if (state) {
            document.getElementById('tab_elem-' + number).scrollIntoView();
            this.selected = number;
        }
    }
    on_click_tab(evt) {
        let number = evt.target.dataset.number;
        if (this.selected != number) {
            this.toggle_tab(this.selected);
            this.toggle_tab(number, true);
        }
        document.location.hash = 'tabs';
        h.table_hash.push('tabs');
        H_ui.clean_tox();
    }
    close_tab(evt) {
        let targ = evt.target.parentElement;
        let number = targ.dataset.number;
        // si l'onglet fermer est celui sélectionné, change vers le prochain
        if (number == this.selected) {
            if (targ.nextElementSibling) {
                this.toggle_tab(targ.nextElementSibling.dataset.number, true);
            } else if (targ.previousElementSibling) {
                this.toggle_tab(targ.previousElementSibling.dataset.number, true);
            }
        }

        let url = targ.dataset.url;
        H_dom.remove_element(document.getElementById('tab_elem-' + number));
        H_dom.remove_element(document.getElementById('tab_container-' + number));
        if (this.tab_list[url].length == 1) delete (this.tab_list[url]);
        else {
            let removeKey = -1;
            this.tab_list[url].forEach((element, key) => {
                if (element == number) removeKey = key;
            });
            this.tab_list[url].splice(removeKey, 1);
        }
        document.location.hash = 'tabs';
        h.table_hash.push('tabs');
    }
    refresh_tab(evt) {
        let targ = evt.target.parentElement;

        let number = targ.dataset.number;
        let name = document.getElementById('tab_elem_name-' + number);
        H_history.from_link = true;
        H_history.last_link_clicked = name.textContent;

        let url = targ.dataset.url;
        this.close_tab(evt);
        let hash = url.split('#')[1];

        document.location.hash = hash + '|tab_number=' + targ.dataset.number;
    }
    add_name_to_tab(number, name) {
        // the html's entities name are encoded, go through the parseFromString to decode them
        var doc = H_dom.dom_parser.parseFromString(name, "text/html");
        name = doc.documentElement.textContent;
        document.getElementById('tab_elem_name-' + number).textContent = name;
    }
    refresh_active() {
        let btn_refresh = document.getElementById('tab_elem_btn_refresh-' + this.selected + this.dom_id);
        if (btn_refresh) h.e.send_event_click(btn_refresh);
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Tabs_a = Tabs_a;