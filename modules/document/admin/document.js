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
var publish_modal=false;
class Document_a extends H_module {

    static documents_id_to_publish='';
    static number_of_documents_to_publish=0;

    constructor(dom_id, settings) {
        super(dom_id);

        this.document_data_id = settings.id;

        this.prev_mode = '';
        this.timer = false;

        this.nb_blocks = 0;
        this.blocks_order = {};
        this.selected_block = false;

        this.last_order = 1;

        this.block_modal = false;



        this.init_canvas();
        this.init_block_list();
        this.init_document_blocks();
    }

    init_canvas(){
        this.canvas_id = this.dom_id + '_document_canvas';
        this.canvas = document.getElementById(this.canvas_id);
        if (this.canvas) h.e.add_event_click(this.canvas, ()=>{this.unselect_target();});

        this.form_canvas_id = 'form_canvas'+this.dom_id;
        this.form_canvas = document.getElementById(this.form_canvas_id);

        this.over_canvas_id = this.dom_id + '_over_canvas';
        this.over_canvas = document.getElementById(this.over_canvas_id);
        if (this.over_canvas) h.e.add_event_click(this.over_canvas, ()=>{this.unselect_target();});
        
        this.lemain_id = 'lemain' + this.dom_id;
        this.lemain = document.getElementById(this.lemain_id);
        if (this.lemain) h.e.add_event_click(this.lemain, ()=>{this.unselect_target();});
    }

    toggle_reorder(evt){
        let targ = evt.target;
        let order_containers = document.getElementsByClassName('hlp_input_order_container');
        let anim_buttons=document.getElementsByClassName('block_anim_button');
        if (targ.checked){
            for (let i = 0; i < order_containers.length; i++){
                H_dom.remove_class(order_containers[i], 'hidden');
            }
            for (let i = 0; i < anim_buttons.length; i++){
                H_dom.remove_class(anim_buttons[i], 'hidden');
            }
        }else{
            for (let i = 0; i < order_containers.length; i++){
                H_dom.add_class(order_containers[i], 'hidden');
            }
            for (let i = 0; i < anim_buttons.length; i++){
                H_dom.add_class(anim_buttons[i], 'hidden');
            }

        }
    }

    save_block_sort_order(id){
        if(this.timer){
            clearTimeout(this.timer);
            this.timer = false;
        }
        let input_order = document.getElementsByName('document_block_sort_order['+id+']');
        this.blocks_order[id] = input_order[0].value;
        this.timer = setTimeout(this.send_block_sort_order.bind(this, id), 500);
    }
    send_block_sort_order(){
        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            document_action: 'document_block_save_sort_order',
            document_data_id: this.document_data_id,
            blocks_order: JSON.stringify(this.blocks_order)
        };
        h.a.send(settings);

        this.blocks_order = {};
        clearTimeout(this.timer);
        this.timer = false;
    }

    init_block_list(){
        this.blocks = new Map();
        let block_list = document.getElementsByClassName('block_drag_' + this.dom_id);
        for (let i = 0; i < block_list.length; i++){
            let drag_block = new Drag_block(block_list[i].id, this);
            this.blocks.set(block_list[i].id, drag_block);
        }
    }

    init_document_blocks(){
        let document_block_list = document.querySelectorAll('#' + this.canvas_id + ' .block_container');
        for (let i = 0; i < document_block_list.length; i++){
            this.add_block_control(document_block_list[i]);
        }
    }
    add_block_control(da_block){
        let exist = document.getElementById(da_block.id + '_control');
        if(!exist){
            let form_cavas = document.getElementById('form_canvas' + this.dom_id);
            // we want to have buttons inside the display of the block. We can't do that in the back so we retrieve those
            // elements in a temporary div and we move them inside the block.
            let temp_control = form_cavas.querySelector('#' + da_block.id + this.dom_id + '_control_temp');
            if (!temp_control) {
                console.error('temp_control not found, id :' + da_block.id + '_control_temp');
                return;
            }
            da_block.dataset.order_parent = temp_control.dataset.order_parent;
            let controls = Array.from(temp_control.children);
            H_dom.append_content(da_block, controls);
            H_dom.remove_element(temp_control);

            // add the overlay on the block to block click inside
            let control_div = H_dom.create_element('div', {
                class: 'control_div',
                id: da_block.id + '_control',
                style: 'position:absolute; width:100%; height:100%; top:0; left:0; user-select: none;',
                'data-block_id': da_block.id
            });
            
            da_block.appendChild(control_div);
            h.e.add_event_click(control_div, this.select_target.bind(this));
            h.e.add_event_dbl_click(control_div, ()=>{this.edit_block(da_block.dataset.block_type, this.document_data_id, da_block.dataset.block_id, da_block.id, 'edit', da_block.parentNode.id);});
        }
    }

    select_target(evt){
        let block_id = evt.target.dataset.block_id;
        if (this.selected_block){
            this.selected_block.classList.remove('currentblock');
        }
        this.selected_block = document.getElementById(block_id);
        if (this.selected_block){
            this.selected_block.classList.add('currentblock');
            document.getElementById('document_btn_copy_' + this.dom_id).classList.remove('hidden');
        }
    }
    unselect_target(){
        if (this.selected_block){
            this.selected_block.classList.remove('currentblock');
            this.selected_block = false;
            document.getElementById('document_btn_copy_' + this.dom_id).classList.add('hidden');
        }
    }

    copy_block(){
        if (!this.selected_block) return;

        // let block = h.modules.document_a[dom_id].selected_block;
        let new_block = H_dom.clone_dom_element(this.selected_block, true);
        new_block.style = '';
        new_block.classList = 'hidden';
        new_block.classList.add('block_container', this.selected_block.dataset.block_type);
        new_block.id = 'block_new' + this.dom_id;
        new_block.dataset.block_id = 0;
        new_block.classList.remove('currentblock');

        let input_order = document.getElementsByName(this.selected_block.dataset.order_parent);
        this.last_order = (input_order[0] != undefined) ? parseInt(input_order[0].value) + 1 : this.last_order;

        H_dom.insert_after(new_block, this.selected_block);

        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            document_action: 'document_block_copy',
            block_id: this.selected_block.dataset.block_id,
            target: new_block.id,
            block_name: this.selected_block.dataset.block_type,
            document_data_id: this.document_data_id,
            sort_order: this.last_order
        };
        settings.success = (res) => {
            let block_info = JSON.parse(res);
            // new_block.id = 'block_' + block_info.block_name + '_' + block_info.id_block;
            this.load_block(block_info.block_name, block_info.id_block, true);
            let stylesheet = H_dom.get_css_rules('document_style_' + this.document_data_id + this.dom_id);
            if (stylesheet) {
                let search_id = '#block_' + this.selected_block.dataset.block_type + '_' + this.selected_block.dataset.block_id;
                let new_id = '#block_' + this.selected_block.dataset.block_type + '_' + block_info.id_block;
                let rules = [];
                for (const rule of stylesheet.cssRules) {
                    if (rule.selectorText == search_id){
                        rules.push(rule);
                    }
                }
                rules.forEach((rule)=>{
                    stylesheet.insertRule(rule.cssText.replace(search_id, new_id));
                });
            }
        };
        h.a.send(settings);
    }

    delete_block(block_name, block_id){
        let block_id_dom = 'block_' + block_name + '_' + block_id;
        let form_canvas = document.getElementById('form_canvas' + this.dom_id);
        let block = form_canvas.querySelector('#' + block_id_dom);
        if (block){
            block.remove();
            this.block_modal.remove();
            this.block_modal = false;
        }
        this.unselect_target();
    }

    load_block(block_name = '', block_id = '', new_block = false){
        let settings = this.ajax_settings();
        settings.url = settings.url.replace('document', 'block');
        let block_id_dom = 'block_' + block_name + '_' + block_id;
        let block;
        if (new_block) {
            // change the id of the temp block added by the drop to replace it with the real block
            let temp_block = document.getElementById('block_new' + this.dom_id);
            if (temp_block) temp_block.id = block_id_dom;
            block = temp_block;
        } else {
            let canvas = document.getElementById('form_canvas' + this.dom_id);
            block = canvas.querySelector('#' + block_id_dom);
        }
        
        settings.dom_target = block;
        settings.replace_dom_target = true;
        settings.data = {
            ...settings.data,
            block_action: 'block_load',
            block_name,
            caller: 'document',
            caller_params: {
                'document_data-id': this.document_data_id
            },
            block_id
        };
        settings.success = () =>{
            let block = document.getElementById(block_id_dom);
            if (block) this.add_block_control(block);
        };
        h.a.send(settings);
    }

    edit_block(block_name, document_data_id, block_id){
        this.block_modal = H_ui.add_window(document.body, {
            hidden: true,
            modal: true,
            close: true,
            remove_on_close: true,
            on_close: this.remove_temp.bind(this),
            class: 'document_block_edit_modal',
            content_resizable: true,
            title: 'Edit Block, ID : ' + block_id
        });

        this.block_modal.dom_element.setAttribute('id', 'edit_block_modal');
        let div = H_dom.create_element('div', {
            class: 'edit_block_modal_container',
            id: 'edit_block_modal_container'
        });
        this.block_modal.set_content(div);
        

        let block_id_dom = 'block_' + block_name + '_' + block_id + this.dom_id;
        let block = document.getElementById(block_id_dom);
        let last_order = this.last_order;
        if (block) {
            let input_order = document.getElementsByName(block.dataset.order_parent);
            if (input_order[0] != undefined) last_order = input_order[0].value;
        }
        
        let settings = this.ajax_settings();
        settings.url = settings.url.replace('document', 'block');
        settings.dom_target = 'edit_block_modal_container';
        settings.data = {
            ...settings.data,
            block_action: 'block_edit',
            caller: 'document',
            caller_params: {
                last_order: last_order,
                'document_data-id': document_data_id,
                document_action: 'document_block_save'
            },
            block_name,
            block_id,
            back_to_caller: false
        };
        settings.success = (res)=>{
            this.block_modal.show();
            H_dom.set_alignment(this.block_modal.dom_element, 0.5, 0.5);
        };
        h.a.send(settings);
    }

    remove_temp() {
        let dom_new = document.getElementById('block_new' + this.dom_id);
        if (dom_new) dom_new.remove();
    }

    static publish_all(ids){
        Document_a.documents_id_to_publish=ids.split(",");
        Document_a.number_of_documents_to_publish =Document_a.documents_id_to_publish.length;
        if (Document_a.documents_id_to_publish.length > 0){
            Document_a.publish_next();
        }
    }

    static publish_next(){
            if (Document_a.documents_id_to_publish.length > 0){
            var doc_id = Document_a.documents_id_to_publish.pop();
            if (publish_modal==false){
                publish_modal = H_ui.add_window(document.body, {
                    hidden: true,
                    modal: true,
                    close: true,
                    remove_on_close: true,
                    class: 'document_publish_modal',
                    content_resizable: true,
                    title: 'Publishing all documents'
                });

                publish_modal.dom_element.setAttribute('id', 'publish_modal');
                let div = H_dom.create_element('div', {
                    class: 'publish_modal_container',
                    id: 'publish_modal_container'
                });
                publish_modal.set_content(div);
            }

            let settings = {};
            settings.url= H_constants.base_url + H_constants.admin_folder + 'document/index.php';
            settings.dom_target = 'publish_modal_container';
            settings.data = {
                ...settings.data,
                'document_data-id': doc_id,
                'pub_all':true,
                'total_doc':Document_a.number_of_documents_to_publish,
                'published':(Document_a.number_of_documents_to_publish - Document_a.documents_id_to_publish.length),
                document_action: 'document_publish',
            };
            settings.success = (res)=>{
                publish_modal.show();
                H_dom.set_alignment(publish_modal.dom_element, 0.5, 0.5);
            };
            h.a.send(settings);
        }else{
            publish_modal.hide();
            publish_modal=false;
        }

    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Document_a = Document_a;

function Drag_block(id, instance_document) {
    this.dom_drag = document.getElementById(id);
    this.avatar = false;
    this.current_target = false;
    this.spacer_status = false;

    this.start_drag = function(evt){

    };
    this.move_drag = function(evt){
        this.detect_drop_position(evt);

    };
    this.detect_drop_position = function(evt){
        if (evt.target.id !=undefined){
            
            if( evt.target.id == instance_document.over_canvas_id || evt.target.id == instance_document.lemain_id || evt.target.id == instance_document.form_canvas_id){
                instance_document.over_canvas.style.background = '#0000';
            }
            if( evt.target.id.includes('_control') ){
                this.spacer_manager(evt);
            }else{
                if (this.spacer_status && this.current_target != evt.target){
                    this.spacer_status.parentNode.removeChild(this.spacer_status);
                    this.spacer_status = false;
                }
            }
        }
    };
    this.spacer_manager = function(evt){
        
        if (this.spacer_status && this.current_target != evt.target){
            this.spacer_status.parentNode.removeChild(this.spacer_status);
            this.spacer_status = false;
        }
        this.current_target = document.getElementById(evt.target.id.replace('_control',''));
        if (!this.spacer_status){
            //checking position inside the block:
            let coords= H_dom.point_inside_element_data(evt.pageX,evt.pageY, this.current_target);
            let mode = '';
            if (coords.localX > (coords.rect.width/2)){
                let width=30;
                let height=30;
                let spacer = H_dom.create_element('DIV', {
                    id : 'spacer_' + instance_document.dom_id,
                    class: 'block_drop_spacer',
                    style: 'width: ' + width + 'px; height: ' + height + 'px; border: 1px solid #000; background: #7702;display:inline-block;'
                });
                spacer.style.height = coords.rect.height + 'px';
                this.spacer_status = spacer;
                H_dom.insert_after(spacer, this.current_target);
            }
        }
    };
    this.end_drag = function(evt){
        if( evt.target.id == instance_document.over_canvas_id || evt.target.id == instance_document.lemain_id || evt.target.id == instance_document.form_canvas_id || evt.target.id.includes('block_') || evt.target.id.includes('_control') || evt.target.id.includes('spacer_')){
            let temp_block = H_dom.clone_dom_element(this.dom_drag, true);
            temp_block.style = '';
            temp_block.classList = '';
            temp_block.classList.add('block_container', temp_block.dataset.block_type);
            let realtarget = evt.target;
            if (evt.target.id.includes('_control')){
                realtarget = evt.target.parentNode;
            }

            temp_block.id = 'block_new' + instance_document.dom_id;
            var order_name = '';
            if (realtarget.id.includes('block_')){
                order_name = realtarget.dataset.order_parent;
                H_dom.insert_after(temp_block, realtarget);
            }else if (realtarget.id.includes('spacer_')){
                order_name = realtarget.previousNode.dataset.order_parent;
                H_dom.insert_after(temp_block, realtarget);
            }else{
                // let document_block_list = document.querySelectorAll('#form_canvas'+dom_id+' .document_block');
                let last_block = document.getElementById('form_canvas' + instance_document.dom_id).querySelector('.block_container:last-of-type');
                order_name = (last_block) ? last_block.dataset.order_parent : '';
                H_dom.append_content(document.getElementById('form_canvas' + instance_document.dom_id), temp_block);
            }
        
            let input_order = document.getElementsByName(order_name);
            instance_document.last_order = (input_order[0] != undefined) ? parseInt(input_order[0].value) + 1 : instance_document.last_order;

            if (this.spacer_status){
                this.spacer_status.parentNode.removeChild(this.spacer_status);
                this.spacer_status = false;
            }

            instance_document.over_canvas.style.background = '#0002';
            
            //CALL TO EDIT BLOCK !!!!
            instance_document.edit_block(temp_block.dataset.block_type, instance_document.document_data_id, 0);
        }
    };

    this.create_avatar = function(evt){
        let avatar;
        avatar = H_dom.clone_dom_element(this.dom_drag, true);
        return avatar;
    };
    this.clean = function(){
        if (this.avatar){
            document.body.removeChild(this.avatar);
        }
        this.dom_drag.style.display = 'block';
        this.dom_drag = null;
    };
    h.e.enable_drag(this.dom_drag, this.start_drag.bind(this), this.move_drag.bind(this), this.end_drag.bind(this), this.create_avatar.bind(this));
}