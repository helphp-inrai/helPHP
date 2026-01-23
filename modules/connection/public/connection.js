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

class Connection extends H_module {
    constructor(dom_id, settings = {}) {
        super(dom_id);

        this.open = settings.open ?? false;

        this.to_toggle = document.getElementById('connection_form_to_toggle' + this.dom_id);
        if (this.open) this.toggle_form();
    }
    clean(){
        if (h.e.has_event(this.to_toggle, h.e.EVENT_MOUSEDOWN_OUTSIDE)) h.e.remove_event_click_outside(this.to_toggle, this.toggle_form.bind(this));
    }
    toggle_form(){
        if (!this.to_toggle) return; // missing element to toggle

        H_dom.toggle_class(this.to_toggle, 'hidden');
        this.open = !H_dom.has_class(this.to_toggle, 'hidden');
        
        if (this.open) h.e.add_event_click_outside(this.to_toggle, this.toggle_form.bind(this));
        else h.e.remove_event_click_outside(this.to_toggle, this.toggle_form.bind(this));
    }
    modal_lost_password() {
        this.toggle_form();

        this.modal = H_ui.add_window(document.body, {
            hide: true,
            modal: true,
            close: true,
            class: 'password_modal',
            remove_on_close: true
        });
        
        let settings = this.ajax_settings();
        settings.data = {
            ...settings.data,
            connection_action: 'connection_form_password'
        };
        settings.success = (res) => {
            this.modal.set_content(res);
            this.modal.set_alignment(0.5, .5);
            this.modal.show();
        };
        h.a.send(settings);
    }
    hide_modal_password(){
        if (this.modal) this.modal.hide();
    }

    static disconnect(){
        let settings = {};
        settings.dom_target = 'lemain';
        settings.url = H_constants.base_url + 'public/connection/index.php';
        settings.data = {
            connection_action: 'connection_disconnect',
            user_action: 'disconnect'
        };
        h.a.send(settings);
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Connection = Connection;

/*
Connection.iconestate= 0;
Connection.modalstate=0;

var containerrect=0;
Connection.modalInit = function(){
    var iconecompte=document.getElementById('deco_public_container_menu_compte');
    if (iconecompte){
        if(is_ios || is_ipad_os){
            h.e.add_event_click(document.getElementById('deco_mon_compte_icone'), Connection.iosclick.bind(this));
        }else{
            h.e.add_event(iconecompte, 'mouseenter', Connection.entering.bind(this));
        }
    //    h.e.add_event(iconecompte, 'mouseleave', Connection.outering.bind(this));
    }
    setTimeout(function(){
        var iconesearch=document.getElementById('deco_public_container_menu_search');
            if (iconesearch){
                h.e.add_event(iconesearch, 'mouseenter', Connection.containerhide);
            }
        }, 1000);
    
};
Connection.iosclick = function(){
    var connexcontainer=document.getElementById('connection_public_container');
    if (Connection.modalstate==0){
        Connection.modalstate=1;
        connexcontainer.style.display="block";
    }else{
        Connection.modalstate=0;
        connexcontainer.style.display="none";
    }
};
Connection.entering = function(){
    var connexcontainer=document.getElementById('connection_public_container');
    h.e.add_event(connexcontainer, 'mouseleave', Connection.outering.bind(this));
    Connection.modalstate=1;
    connexcontainer.style.display="block";
    containerrect = H_dom.get_global_rect(connexcontainer);
    //~ console.log(containerrect);
};
Connection.outering = function(e){
    // if (e.clientX <= containerrect.left || e.clientY >= containerrect.bottom || (e.clientY < containerrect.top) ) { 
    if (e.clientX <= containerrect.left || e.clientY >= (containerrect.bottom - window.scrollY) || (e.clientY < (containerrect.top - window.scrollY) && e.clientX <= ( containerrect.left + containerrect.width / 2) ) ) { 
    
        Connection.modalstate=0;
        Connection.containerhide();
    }
};
Connection.containerhide = function(){
    var connexcontainer=document.getElementById('connection_public_container');
    connexcontainer.style.display="none";
};
*/
/*
Connection.Modalcreate = function(){
        if (!modal){
            modal = H_ui.add_window(document.body, {'hide': true, 'modal': true});
            modal.dom_element.setAttribute('id', 'creation_compte_modal');
        } else {
            while(modal.dom_element.firstChild){
                modal.dom_element.removeChild(modal.dom_element.firstChild);
            }
        }
        modal.dom_element.setAttribute('id', 'creation_compte_modal');
        modal.dom_element.setAttribute('class', 'ui_window creation_compte_modal');
         //~ var url = document.baseURI;
        //~ url = url.split('#');
        //~ url = url[0].split('?');
        //~ url = url[0] + 'public/Connection/index.php';
        let settings = {};
        //~ settings.url = url;
        settings.url=H_constants.base_url + 'public/connection/index.php';
        settings.dom_target = 'creation_compte_modal';
        settings.skip_container = false;
        settings.data = {
            'connection_action': 'connection_form_create'
        };
        settings.success = function(res){
            modal.show();
            //~ let btn_close = document.getElementById('creation_compte_modal_close');
            //~ console.log(btn_close);
            //~ h.e.add_event(btn_close, 'mousedown', function(){
                //~ modal.hide();
            //~ });
        };
        h.a.send(settings);
};
*/