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
    
class Notification extends H_module{
    constructor(dom_id) {
        super(dom_id);
        this.interval = false;
        this.ids=[];
        this.isVisible=true;
        this.init();
    }

    init(){
        if (!this.interval){
            // console.log('REFRESH ALERT STOPPED FOR DEV');
            this.interval = setInterval(()=>{
                h.modules.notif[this.dom_id].RefreshAlert();
            }, 5000);
            
        }
        
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
              // L'onglet est désormais visible et la notification n'est plus pertinente
              // on peut la fermer
              this.isVisible=true;
              if (this.currentNotif !=undefined) {
                this.currentNotif.close();
              }
            }else{
                this.isVisible=false;
            }
          });
    }
    
    RefreshAlert(){
        
        let settings = this.ajax_settings();
        settings.url = H_constants.base_url + 'public/notification/index.php';
        settings.no_timer = true;
        settings.data = {
            'notification_action': 'notification_widget',
            'prev_idmsg': this.lastidmsg,
            'prev_totalmsg': this.lasttotalmsg
        };
        h.a.send(settings);
    }
    
    weGotAModal(evt){
        if (H_ui.popup_special["notification"] !=undefined){
            if (!H_ui.popup_special["notification"].visible){
                let params = {
                    'notification_action':'notification_modal'
                    };
                H_ui.open_popup_modal(evt,'public/notification',params,'notification');
            }
        }else{
            let params = {
                'notification_action':'notification_modal'
                };
            H_ui.open_popup_modal(evt,'public/notification',params,'notification');
            
            
        }

        // notification(){
        //     document.title ="";

        // }
    }

    asknotif(e){
        let itsok=false;
        if (Notification.permission === 'granted' ){
            this.nomorecheck=false;
            itsok=true;
        }else{
            Notification.requestPermission().then((permission) => {
                if (permission === "granted") {
                    this.nomorecheck=false;
                    itsok=true;
                }else{
                    this.nomorecheck=true;
                }
            });
        }
        if (itsok){
            const img = 'images/favicon.png';
            this.currentNotif = new Notification('System notification test', { body: 'System notification access is granted', icon: img });
            document.getElementById('notification_sound').play();

         }
    }

    notify(title,msg,id){
        if (!this.ids.includes(id) && !this.isVisible) {
            document.title ="(!)";
            this.ids.push(id);
            if (window.Notification) {
                const img = 'images/favicon.png';
                // Vérifions si les autorisations de notification ont déjà été accordées
                if (Notification.permission === 'granted' ) {
                    // console.log("notify1");
                    this.currentNotif = new Notification(title, { body: msg, icon: img });
                    document.getElementById('alert_sound').play();
                    
                    
                }// Sinon, nous devons demander la permission à l'utilisateur
                else if (this.nomorecheck==undefined && Notification.permission !== 'denied') {
                    Notification.requestPermission().then((permission) => {
                        if (permission === 'granted') {
                            // console.log("notify2");
                            this.currentNotif = new Notification(title, { body: msg, icon: img });
                            document.getElementById('alert_sound').play();
                        }else{
                            this.nomorecheck=true;
                        }
                    });
                }
                else if (Notification.permission == 'denied'){
                    this.nomorecheck=true;
                }
            }

            document.getElementById('alert_sound').play();
        }else{
            if (!this.ids.includes(id)){
                this.ids.push(id);
            }
            if (this.isVisible){
                document.title ="";
            }
        }
    }
    
}

h.modules_class = h.modules_class || {};
h.modules_class.Notification = Notification;