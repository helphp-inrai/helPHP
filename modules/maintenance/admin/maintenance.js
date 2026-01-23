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
    
class Maintenance_a extends H_module{

    constructor(dom_id, actions) {
        super(dom_id);
        this.actions = actions;
        this.init();

        this.rules_displayed_count = 0;
    }

    init(){
        this.actions.forEach(action => {
            let btn = document.getElementById('maintenance_btn_action_' + action + this.dom_id);
            if (!btn) console.warn('error fetching id "maintenance_btn_action_' + action + this.dom_id +'" for action ');
            h.e.add_event_click(btn, (event)=>{Maintenance_a.send_action(action,this.dom_id);});

        });
    }

    static send_action( action,dom_id){
        let settings = {};
        settings.url = H_constants.base_url + H_constants.admin_folder + 'maintenance/index.php';
        // settings.dom_target = '';
        settings.dom_target = 'maintenance_receiver_result' + dom_id;
        settings.skip_container = true;
        settings.data = {
            'dom_id': dom_id,
            'maintenance_action': 'maintenance_execute',
            'action': action
        };
        settings.success = function(msg){
            if (msg) H_ui.message_popup_timed(msg);
            // console.log('result send action', res);
        };
        h.a.send(settings);
    }

    display_css_edited(html){
        if (!this.modal){
            this.modal = H_ui.add_window(document.body, {
                modal: true,
                class: 'maintenance',
                remove_on_close: true
            });
        }

        this.modal.set_content(html);
        this.modal.set_alignment(.5, .5);
    }
    
    keep_rule(id_rule, index){
        if (id_rule === 0){
            // want to keep the user's rule, nothing to do in db, remove it from display
            this.remove_css_rules(index);
            return;
        }
        
        let settings = this.ajax_settings();
        settings.no_timer = true;
        settings.data = {
            ...settings.data,
            maintenance_action: 'maintenance_keep_rule',
            id_rule
        };
        settings.success = (res)=>{
            if (res) this.remove_css_rules(index);
        };
        h.a.send(settings);
    }
    remove_css_rules(index){
        let dom_elem = document.getElementById('maintenance_row_selector_' + index + this.dom_id);
        if (dom_elem) H_dom.remove_element(dom_elem);
        
        dom_elem = document.getElementById('maintenance_row_media_' + index + this.dom_id);
        if (dom_elem) H_dom.remove_element(dom_elem);
        
        dom_elem = document.getElementById('maintenance_row_properties_new_' + index + this.dom_id);
        if (dom_elem) H_dom.remove_element(dom_elem);
        dom_elem = document.getElementById('maintenance_row_properties_modified_' + index + this.dom_id);
        if (dom_elem) H_dom.remove_element(dom_elem);

        this.rules_displayed_count--;
        if (this.rules_displayed_count == 0){
            if (this.modal) this.modal.hide();
            else H_ui.popup_modal.hide();
        }
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Maintenance_a = Maintenance_a;