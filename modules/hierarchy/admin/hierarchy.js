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
class Hierarchy_a extends H_module {

    #last_target;

    constructor(dom_id, ids_list) {
        super(dom_id);

        this.add_event_on_items(ids_list);
    }

    add_event_on_items(list) {
        list.forEach((id) => {
            let item = document.getElementById(id);
            if (item) {
                if (!h.e.has_drag(item)) {
                    h.e.add_event_dbl_click(item, this.on_toggle_collapse.bind(this));
                    h.e.enable_drag(item, this.on_start_drag.bind(this), this.on_drag_move.bind(this), this.on_drag_end.bind(this), this.on_make_avatar.bind(this));
                    h.e.enable_drop(item, this.on_drop.bind(this));
                }
                let btn_del = document.getElementById(id + '_btn_del');
                if (btn_del) h.e.add_event_click(btn_del, this.delete_item.bind(this));

                let sub_container = document.getElementById(id + '_subtree');
                item._subcontainer = sub_container;
            }
        });
    }
    update_item(id_structure, html) {
        H_ui.popup_modal.hide();
        let id = 'hierarchy_item' + this.dom_id + '_' + id_structure;
        let dom_elem = document.getElementById(id);
        if (dom_elem) {
            H_dom.replace_element(dom_elem, html);
        } else {
            let parent  = document.getElementById('hierarchy_admin_tree_parent' + this.dom_id);
            if (parent.lastElementChild) {
                // to preserve the events on the other elements use H_dom.insert_after and not H_dom.append_content
                // when there already is some child
                H_dom.insert_after(html, parent.lastElementChild);
            } else {
                // otherwise it's the first element of the tree
                H_dom.append_content(parent, html);
            }
        }
        this.add_event_on_items([id]);
    }
    delete_item(evt) {
        let targ = evt.target;
        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            'hierarchy_action': 'hierarchy_delete_structure',
            'hierarchy_structure-id': targ.dataset.id,
            'fromjs': true
        };
        settings.success = (res) => {
            let dom_elem = document.getElementById('hierarchy_item' + this.dom_id + '_' + targ.dataset.id);
            if (dom_elem) H_dom.remove_element(dom_elem);
        };
        h.a.send(settings);
    }
    on_toggle_collapse(evt){
        H_event.stop_event(evt);
        H_dom.toggle_class(evt.target, 'closed');
    }
    on_start_drag(evt, dragged_element) {
        this.#last_target = null;

        if (dragged_element._subcontainer) {
            H_dom.add_class(dragged_element._subcontainer, 'drop_disabled');
        }
    }
    on_drag_move(evt, dragged_element) {
        let target = evt.target;

        if (this.#last_target && this.#last_target != target) {
            H_dom.remove_class(this.#last_target, 'forbidden');
            H_dom.remove_class(this.#last_target, 'insert_before');
            H_dom.remove_class(this.#last_target, 'insert_after');
            H_dom.remove_class(this.#last_target, 'insert_inside');
        }

        if (h.e.has_drop(target) && target.id && target.id.includes('hierarchy')) {
            H_dom.remove_class(target, 'forbidden');
            H_dom.remove_class(target, 'insert_before');
            H_dom.remove_class(target, 'insert_after');
            H_dom.remove_class(target, 'insert_inside');

            let state = this.get_drop_state(evt, dragged_element);
            if (state !== null) {
                H_dom.add_class(target, state);
            }

            this.#last_target = target;
        }
    }
    on_drag_end(evt, dragged_element) {
        if (this.#last_target) {
            H_dom.remove_class(this.#last_target, 'forbidden');
            H_dom.remove_class(this.#last_target, 'insert_before');
            H_dom.remove_class(this.#last_target, 'insert_after');
            H_dom.remove_class(this.#last_target, 'insert_inside');
        }
        this.#last_target = null;

        if (dragged_element._subcontainer) {
            H_dom.remove_class(dragged_element._subcontainer, 'drop_disabled');
        }
    }
    on_make_avatar(dragged_element, event) {
        let avatar = H_dom.clone_dom_element(dragged_element, true, false);

        // we need the width and the offset of the click inside parent. 
        let rect = H_dom.get_global_rect(dragged_element, true);

        avatar.style.setProperty('pointer-events', 'none', 'important');
        avatar.style.width = rect.width + 'px';
        avatar.style.height = rect.height + 'px';
        avatar.style.opacity = '.5';
        return '<div class="hierarchy_dragged_item">' + avatar.outerHTML + '</div>';
    }
    on_drop(evt) {
        h.e.stop_event(evt);
        let data = h.e.extract_data_from_drop_event(evt);

        if (!data.dropData.elements.length) return;
        let dragged_element = data.dropData.elements[0];
        if (!dragged_element) return;

        let target = evt.target;
        data.dropData.target = target;
        let state = this.get_drop_state(data.dropData, dragged_element);
        if (state == 'forbidden') return;
        switch (state) {
            case 'insert_before':
                H_dom.insert_before(dragged_element, target);
                break;

            case 'insert_after':
                H_dom.insert_after(dragged_element, target);
                break;

            case 'insert_inside':
                target._subcontainer.appendChild(dragged_element);
                break;
        }

        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            'hierarchy_action': 'hierarchy_update_structure',
            'items': []
        };
        
        let id_parent = dragged_element.parentNode.dataset.id;
        for (let i = 0; i < dragged_element.parentNode.childNodes.length; i++) {
            let item = dragged_element.parentNode.childNodes[i];
            let data = { 'id_parent': id_parent, 'order': H_dom.find_child_index(item.parentNode, item), 'id': item.dataset.id };
            settings.data.items.push(data);
        }

        settings.success = (res) => {
            if (res.errors) {
                // reload current tree
                this.resfresh_tree();
            }
        };

        h.a.send(settings);

        return false;
    }
    get_drop_state(evt, dragged_element) {
        let target = evt.target;

        let state = null;

        let rect = H_dom.get_global_rect(target, true);
        let dist_top = Math.abs(rect.top - evt.pageY);
        let dist_bottom = Math.abs(rect.bottom - evt.pageY);

        let dist = Math.min(20, rect.height / 2);
        let middle = 4;
        
        if (dragged_element === target || dragged_element.contains(target)) return 'forbidden';
        if (dist_top < (dist - middle)) {
            state = 'insert_before';
        } else if (dist_bottom < (dist - middle)) {
            state = 'insert_after';
        } else {
            state = 'insert_inside';
        }

        return state;
    }
    resfresh_tree() {
        let select = document.getElementById('hierarchy_admin_select_root' + this.dom_id);
        h.e.send_event(select, 'change');
    }
    toggle_image(evt) {
        let target = evt.target;
        H_dom.toggle_class(target.parentNode.nextElementSibling, 'hidden');
    }
    on_delete_media(){
        this.resfresh_tree();
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Hierarchy_a = Hierarchy_a;