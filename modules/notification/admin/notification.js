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
    
class Notification_a extends H_module{
    constructor(dom_id, actions) {
        super(dom_id);
        this.container_id = 'notification_admin_container' + this.dom_id;
        this.interval = false;
        this.init();
    }
    init(){
        if (!this.interval){
            this.interval = setInterval(refresh, 60000); // every minute
        }
    }

    refresh(){
        let settings = {};
        settings.url = H_constants.base_url + H_constants.admin_folder + 'notification/index.php';
        settings.dom_target = 'notification_widget';
        settings.skip_container = true;
        settings.no_timer = true;
        settings.data = {
            'notification_action': 'notification_widget'
        };
        h.a.send(settings);
    }

    clean(){
        super.clean();
        if (this.interval) clearInterval(this.interval);
    }

}

h.modules_class = h.modules_class || {};
h.modules_class.Notification_a = Notification_a;