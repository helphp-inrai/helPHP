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

class Media_a extends H_module {

    constructor(dom_id, settings) {
        super(dom_id);

        this.media_id = settings.media_id;
        this.params = settings.params;

        this.item_selected = [];

        this.dom_elems = {};
        this.dom_elems.container = document.getElementById('media_uploader' + this.dom_id);

        // drop zone
        this.dom_elems.droper = document.getElementById('media_droper' + this.dom_id);
        h.e.add_event_click(this.dom_elems.droper, this.on_click_droper.bind(this));
        h.e.enable_drop(this.dom_elems.droper, this.on_drop_file.bind(this));

        this.dom_elems.droper_txt = document.getElementById('media_droper_txt' + this.dom_id);
        this.dom_elems.droper_selected = document.getElementById('media_droper_selected' + this.dom_id);

        // hidden input
        this.dom_elems.input_file = document.getElementById('media_input' + this.dom_id);
        h.e.add_event(this.dom_elems.input_file, 'change', this.on_change_file.bind(this));

        // btn toggle list
        if (this.params.list) {
            this.dom_elems.toggle_list = document.getElementById('media_toggle_list' + this.dom_id);
            if (this.dom_elems.toggle_list) h.e.add_event_click(this.dom_elems.toggle_list, this.display_list.bind(this));

            this.dom_elems.input_list = document.getElementById('media_input_list' + this.dom_id);
            h.e.add_event(this.dom_elems.input_list, 'change', this.on_change_file_list.bind(this));
        }

        // hidden field to indicate this media input is modified
        this.dom_elems.inp_modified = document.getElementById('media_modified' + this.dom_id);

        //big view
        if (this.params.big_view) {
            this.dom_elems.big_view = document.getElementById('media_big_view' + this.dom_id);
            if (this.dom_elems.big_view) h.e.add_event(this.dom_elems.big_view, 'change', this.toggle_big_view.bind(this));
        }

        // btn activate lang
        this.dom_elems.activate_lang = document.getElementById('media_btn_activate_lang' + this.dom_id);
        if (this.dom_elems.activate_lang) h.e.add_event_click(this.dom_elems.activate_lang, this.switch_language_mode.bind(this));
        this.dom_elems.lang_list = document.getElementById('media_lang_list' + this.dom_id);
        // Lang buttons
        if (this.dom_elems.lang_list){
            this.lang = [];
            let btns = this.dom_elems.lang_list.getElementsByClassName('media_admin_lang_item');
            Array.from(btns).forEach((button)=>{
                let iso = button.dataset.iso;
                let obj = {
                    iso,
                    button,
                    uploader: document.getElementById('media_uploader' + this.dom_id + (iso ? '-' + iso : ''))
                };
                h.e.add_event_click(button, ()=>{this.toggle_language(iso);});
                this.lang.push(obj);
            });
        }

        // progress bar
        this.dom_elems.progress_state = document.getElementById('media_progress_state' + this.dom_id);
        this.dom_elems.progress_label = document.getElementById('media_progress_label' + this.dom_id);

        // current images
        let lst = document.getElementById('media_current_lst' + this.dom_id);
        if (lst) {
            for (let i = 0; i < lst.children.length; i++) {
                let child = lst.children[i];
                let use_key = child.dataset.use_key;

                if (!this.params.multiple) {
                    // will replace the current element, detect if the media is shared, in this case ask to replace everywhere or only there.
                    let nb_instances = child.dataset.nb_instances;
                    if (nb_instances > 1) {
                        // this.#media_is_shared = true;
                        let input_shared = document.getElementById('media_input_shared' + this.dom_id);
                        if (input_shared) input_shared.value = 1;
                    }
                }

                if (this.params.edit) {
                    let edit = document.getElementById('media_toggle_edit' + use_key + this.dom_id);
                    if (edit) h.e.add_event_click(edit, (event) => { this.display_edit(event, use_key); });
                }

                if (this.params.delete) {
                    let del = document.getElementById('media_delete' + use_key + this.dom_id);
                    if (del) h.e.add_event_click(del, (event) => { this.delete_media(event, use_key); });
                }
            }
        }
    }

    on_click_droper(evt) {
        h.e.stop_event(evt);
        this.dom_elems.input_file.click();
    }
    on_drop_file(evt) {
        h.e.stop_event(evt);
        if (evt.dataTransfer) {
            this.dom_elems.input_file.files = evt.dataTransfer.files;
            this.on_change_file(evt);
        }
    }
    on_change_file(evt){
        let shtml = '';
        let files = this.dom_elems.input_file.files;
        let length = files.length;
        if (length > 1) shtml += H_constants.get_text('media_upload_file', (length));
        else shtml += files[0].name;

        this.dom_elems.droper_selected.innerHTML = shtml;
        this.dom_elems.inp_modified.value = 1;

        if (this.params.submit) {
            // detect if inside a form
            if (this.dom_elems.input_file.form !== null) {
                h.e.send_event(this.dom_elems.input_file.form, 'submit');
            } else {
                this.send_files();
            }
        }
    }

    display_list(evt){
        H_ui.open_popup_modal(evt, 'media', {
            'media_action': 'media_list',
            'media_id': this.media_id,
            'dom_id': this.dom_id
        });
    }
    init_list() {
        this.item_selected = [];

        let parent = document.getElementById('media_list_medias' + this.dom_id);
        if (parent) {
            for (let i = 0; i < parent.children.length; i++) {
                let elem = parent.children[i];
                h.e.add_event_click(elem, this.click_item_list.bind(this), true);
            }
        }

        let btn_send = document.getElementById('media_list_btn_send_select' + this.dom_id);
        if (btn_send) {
            h.e.add_event_click(btn_send, this.add_selected_item.bind(this));
        }
    }
    click_item_list(evt) {
        let target = evt.target;
        if (this.item_selected.indexOf(target) > -1) {
            this.item_selected.splice(this.item_selected.indexOf(target), 1);
            H_dom.remove_class(target, 'selected');
            return;
        }

        this.item_selected.push(target);
        if (this.params.multiple) {
            H_dom.add_class(target, 'selected');
        } else {
            this.add_selected_item();
        }
    }
    on_change_file_list(evt) {
        let shtml = '';
        let files = JSON.parse(this.dom_elems.input_list.value);
        let length = files.length;
        if (length > 1) shtml += H_constants.get_text('media_files_list', length);
        else shtml += files[0].name;

        this.dom_elems.droper_selected.innerHTML = shtml;
        this.dom_elems.inp_modified.value = 1;

        if (this.params.submit) {
            // detect if inside a form
            if (this.dom_elems.input_file.form !== null) {
                h.e.send_event(this.dom_elems.input_list.form, 'submit');
            } else {
                this.send_files();
            }
        }
    }
    add_selected_item(){
        let data = [];
        this.item_selected.forEach((item) => {
            data.push({ 'media_id': item.dataset.media_id, 'use_key': item.dataset.use_key, 'name': item.dataset.name });
        });
        this.dom_elems.input_list.value = JSON.stringify(data);
        this.on_change_file_list();

        // close modal
        H_ui.popup_modal.hide();
    }

    refresh_media(use_key) {
        let img = document.getElementById('media_current_img' + use_key + this.dom_id);
        if (img) {
            img.src = img.src + 't=' + new Date().getTime();
        }
    }

    display_edit(evt, use_key) {
        H_ui.open_popup_modal(evt, 'media', {
            'dom_id': this.dom_id,
            'media_action': 'media_edit',
            'media_id': this.media_id,
            'use_key': use_key,
        });
    }

    delete_media(evt, use_key, confirmed = false) {
        if (!confirmed) {
            let confirm = evt.target.dataset.confirm;
            H_ui.confirm_popup(confirm, (event) => { this.delete_media(event, use_key, true); });
            return;
        }

        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            'media_action': 'media_delete',
            'media_id': this.media_id,
            'media_use_key': use_key,
        };
        settings.success = (res) => {
            if (res == 'done') {
                let toDel = document.getElementById('media_current_elem' + use_key + this.dom_id);
                if (toDel) H_dom.remove_element(toDel);

                if (this.params.on_delete) H_generics.execute_function_by_name(this.params.on_delete, window);
            }
        };
        h.a.send(settings);
    }

    toggle_big_view(evt){
        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            'media_action': 'media_big_view',
            'media_id': this.media_id,
            'state': this.dom_elems.big_view.checked
        };
        h.a.send(settings);
    }

    send_files() {
        let settings = this.ajax_settings();
        settings.dom_target = 'media_uploader' + this.dom_id;
        settings.replace_dom_target = true;
        settings.data = {
            ...settings.data,
            'media_action': 'media_upload',
            'media_id': this.media_id,
        };
        let inputs = this.dom_elems.container.querySelectorAll('input');
        for (let i = 0; i < inputs.length; i++) {
            let inp = inputs[i];
            if (inp.type == 'file') settings.data[inp.name] = inp.files;
            else if (inp.name.includes('list') && inp.value != '') settings.data[inp.name] = JSON.parse(inp.value);
            else settings.data[inp.name] = inp.value;
        }
        h.a.send(settings);
    }
    upload_start() {
        this.dom_elems.droper_txt.innerHTML = H_constants.get_text('media_uploading');
        if (this.dom_elems.toggle_list) H_dom.add_class(this.dom_elems.toggle_list, 'hidden');
        this.dom_elems.progress_state.style.width = '0%';
        this.dom_elems.progress_label.textContent = '0%';
    }
    upload_progress(evt, data) {
        let percent = Math.round(data.widget_sender.progress * 100);
        this.dom_elems.progress_state.style.width = percent + '%';
        this.dom_elems.progress_label.textContent = percent + '%';
    }
    upload_end() {
        this.dom_elems.progress_state.style.width = '100%';
        this.dom_elems.progress_label.textContent = '100%';
        this.dom_elems.droper_txt.innerHTML = H_constants.get_text('media_droper');
        if (this.dom_elems.toggle_list) H_dom.remove_class(this.dom_elems.toggle_list, 'hidden');
    }

    init_edit_resize(){
        this.input_width = document.getElementById('media_input_width' + this.dom_id);
        h.e.add_event(this.input_width, 'change', this.check_for_proportion.bind(this));

        this.input_height = document.getElementById('media_input_height' + this.dom_id);
        h.e.add_event(this.input_height, 'change', this.check_for_proportion.bind(this));

        this.input_proportion = document.getElementById('media_input_proportion' + this.dom_id);
    }
    check_for_proportion(evt){
        if (!this.input_proportion.checked) return;

        let ratio = this.input_width.dataset.default / this.input_height.dataset.default;
        if (evt.target == this.input_height) {
            this.input_width.value = Math.floor(this.input_height.value * ratio);
        } else {
            this.input_height.value = Math.floor(this.input_width.value / ratio);
        }
    }
    calculate_aspect_ratio(base_width, base_height, max_width, max_height) {
        var ratio = Math.min(max_width / base_width, max_height / base_height);
        return { width: base_width * ratio, height: base_height * ratio };
    }

    switch_language_mode(evt){
        if (this.params.lang) {
            H_ui.confirm_popup(evt.target.dataset.confirm, ()=>{this.delete_language_media();});
        } else {
            H_dom.remove_class(this.dom_elems.lang_list, 'hidden');
            H_dom.add_class(evt.target, 'active');
            this.toggle_language('');
            this.params.lang = true;
        }
    }
    toggle_language(iso){
        if (iso === this.params.lang_iso) return;

        this.lang.forEach((lang) => {
            if (lang.iso == iso) {
                lang.button.classList.toggle('selected', true);
                lang.uploader.classList.toggle('selected', true);
                lang.uploader.classList.toggle('hidden', false);
            } else {
                lang.button.classList.toggle('selected', false);
                lang.uploader.classList.toggle('selected', false);
                lang.uploader.classList.toggle('hidden', true);
            }
        });
        this.params.lang_iso = iso;
    }
    delete_language_media(){
        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            media_action: 'media_delete_languages',
            media_id: this.media_id
        };
        settings.success = () => {
            H_dom.add_class(this.dom_elems.lang_list, 'hidden');
            H_dom.remove_class(evt.target, 'active');
            this.params.lang = false;
            this.toggle_language('');
            
        };
        h.a.send(settings);
    }

    exist() {
        let domElem = document.getElementById('media_uploader' + this.dom_id);
        // console.log('check if ' + this.dom_id + ' exist ' + (domElem ? true : false));
        return (domElem) ? true : false;
    }

    static ask_for_shared(sender = false) {
        let b1 = {
            'label': H_constants.get_text('media_replace_all'),
            'handler': () => { Media_a.resend_after_choice(sender, true); },
        };
        let b2 = {
            'label': H_constants.get_text('media_replace_one'),
            'handler': () => { Media_a.resend_after_choice(sender, false); },
        };
        let setts = {
            'contentClass': 'media_is_shared',
            'content': H_constants.get_text('media_prompt_is_shared'),
            'buttons': [b1, b2]
        };
        let prompt = new H_ui_prompt(setts);
        prompt.set_parent(document.body);
    }
    static resend_after_choice(sender, all = false) {
        if (sender) {
            sender._data.delete('media_is_shared');
            sender._data.append('media_replace_all', (all ? 1 : 0));
            console.log(sender);
            sender.send();
        } else {
            let settings = {};
            settings.url = H_constants.base_url + H_constants.admin_folder + 'media/index.php';
            settings.skip_container = true;
            settings.dom_target = 'media_edit_result_js';
            settings.data = {
                'post_in_session': true,
                'media_replace_all': (all ? 1 : 0)
            };
            h.a.send(settings);
        }
    }
    // path -> chemin vers la vidéo
    // secs -> seconde où prendre l'image
    // domElem -> Balise HTML IMG qui recoit l'image final
    static get_video_image(path, secs, dom_elem_id) {
        var me = this, video = document.createElement('video');
        video.onloadedmetadata = function () {
            if ('function' === typeof secs) {
                secs = secs(this.duration);
            }
            this.currentTime = Math.min(Math.max(0, (secs < 0 ? this.duration : 0) + secs), this.duration);
        };
        video.onseeked = function (e) {
            var canvas = document.createElement('canvas');
            canvas.height = video.videoHeight;
            canvas.width = video.videoWidth;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            var img = new Image();
            img.src = canvas.toDataURL();
            document.getElementById(dom_elem_id).src = img.src;
        };
        // video.onerror = function (e) {
            //~ callback.call(me, undefined, undefined, e);
        // };
        video.src = H_constants.base_url + path;
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Media_a = Media_a;