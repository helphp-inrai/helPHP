/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */

//here start the h object that instantiate everthing
var h = h || {};

h.table_hash = new Array('');

h.timer_modal = false;
H_event.add_load_handler(()=>{
    h.timer_modal =  H_ui.add_window(null, {
        modal: true,
        nodrag: true,
        class: 'timer_requete',
        disable_background_click: true,
        hidden: true,
        special: true,
        special_level: 999999
    });
    h.timer_modal.set_parent(document.body, 0.5, 0.5);
    h.timer_modal._modal_mask.classList.add('load_timer');
});
h.timer_modal_hidden = true;

var helphp_timeout = function(call,time,timed=0){
    try{
        eval(call);
    } catch(e){
        timed++;
        if (timed<5){
            setTimeout(helphp_timeout , time , call, time, timed);
        }
    }
};

//ajax.js
//------------------------------
h.a = new H_ajax();

//module.js modules instanciation manager
h.modules = H_module.instances;

//events.js
//------------------------------
h.e = H_event;
h.e.init(); 

//validator.js
//------------------------------
h.v = new H_validator();

//detect_hash in history.js
//------------------------------
h.e.add_load_handler(H_history.detect_hash); 
window.onhashchange = function(evt){
    H_history.detect_hash();
};

//initialvar for VerticalRatio based on FHD size
var fhdVR=1;

//update root css var on load, detect_screen_infos is in ui.js
h.e.add_load_handler(H_ui.detect_screen_infos); 
//should update fhdVR and some root css vars on resize , still in ui.js
h.e.add_resize_handler(H_ui.update_css_fhd_vr);
