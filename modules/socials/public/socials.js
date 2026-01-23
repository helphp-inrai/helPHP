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

class Socials extends H_module {
    constructor(dom_id) {
        super(dom_id);

        this.iconSocials = document.getElementsByClassName('socials_icon');
        
        // this.share = document.getElementById('socials_collapse');
        // h.e.add_event_click(this.share, this.toggle.bind(this));
        
        this.container = document.getElementById('socials_container' + this.dom_id);
        h.last_social_container= this.dom_id;
        this.overlay = H_dom.create_element('DIV', {
            class: 'socials_overlay hidden',
            id: 'socials_overlay' + this.dom_id
        });
        H_dom.append_content(document.body, this.overlay);
        h.e.add_event_click(this.overlay, this.hide.bind(this));
    }

    on_click = function(evt){
        H_dom.toggle_class(this.container, 'hidden');
        H_dom.toggle_class(this.overlay, 'hidden');
        // if (CONSTANTS.include_js_animate && this.anime && !H_dom.HasClass(this.container, this.cssClass)) {
        //     for (let i = 0; i < this.iconSocials.length; i++){
        //         this.iconSocials[i].style = 'opacity: 0';
        //     }
        //     spanim({
        //         elements: '.socials_public_icon',
        //         duration: 50,
        //         preset: true,
        //         opacity: ["0", "1"],
        //         //~ transform: ["translateY(-100%)", "translateY(0%)"],
        //         delay: index => index * 50
        //     });
        // }
    };
    hide = function(){
        H_dom.toggle_class(this.container, 'hidden', true);
        H_dom.toggle_class(this.overlay, 'hidden', true);
    };
    show = function(){
        H_dom.toggle_class(this.container, 'hidden', false);
        H_dom.toggle_class(this.overlay, 'hidden', false);
        // H_dom.toggle_class(document.getElementById(h.last_social_container), 'hidden', true);
        // H_dom.toggle_class(document.getElementById('socials_public_overlay'), 'socials_public_hide', false);
    };
}

var try_socials=false;
/**
 * Updates the social sharing widgets for the current module and parameters.<br>
 * Loads the social container via AJAX and initializes the Socials module.<br>
 * Retries if the container is not yet available in the DOM.<br>
 * @function
 * @param {string} module_name - The module name.
 * @param {string} module_param - The module parameter.
 * @param {string|boolean} [language=false] - Optional language code.
 * @param {boolean} [collapse=false] - Collapse social buttons if true.
 */
function update_socials(module_name,module_param,language=false,collapse=false){
    let social_container = document.getElementById('socials_container' + h.last_social_container);
    if (social_container){
        let settings = {};
        let url = H_constants.base_url+'public/socials/index.php';
        settings.url = url;
        //settings.dom_target='';
        settings.data = {};
        if (language) {
            settings.data.language = language;
        }
        if (social_container.children.length == 3 || collapse){
            settings.data.social_collapse = true;
        }
        //~ settings.data['core_insert']=false;
        settings.skip_container = true;
        settings.notimer = true;
        settings.data.module_name = module_name;
        settings.data.module_param = module_param;

        settings.success = function(res, e){
            //~ console.log(res);
            if (!res.includes('Error')) {
                let container = document.getElementById('socials_container' + h.last_social_container);
                container.innerHTML=res;
                 h.modules.socials[h.last_social_container].show();
                // if (container.children.length == 3){
                //     Socials.init(1);
                // }
            }else{
                h.modules.socials[h.last_social_container].hide();
            }
        };
        try_socials=false;
        h.a.send(settings);
    }else{
        if (try_socials==false){
            try_socials=true;
            //~ console.log(module_name+module_param+language);
            setTimeout(function(){update_socials(module_name,module_param,language);},500);
        }
    }
}
h.last_social_container=false;
h.modules_class = h.modules_class || {};
h.modules_class.Socials = Socials;



// /*jshint esversion: 6 */
// var Socials = function(){};
// Socials.prototype.constructor = Socials;

// Socials.cssClass = 'socials_public_hide';

// Socials.init = function(animate){
    
//     this.anime = animate;
//     this.iconSocials = document.getElementsByClassName('socials_public_icon');
//     //~ for (let i = 0; i < this.iconSocials.length; i++){
//         //~ $_e.AddEvent(this.iconSocials[i], 'click', Socials.Hide.bind(this));
//     //~ }
    
//     this.share = document.getElementById('socials_public_collapse');
//     $_e.AddEventClick(this.share, this.onClick.bind(this));
    
//     this.container = document.getElementById('socials_public_container');
//     if (!document.getElementById('socials_public_overlay')){
//         this.overlay = H_dom.CreateElement('DIV', {class: 'socials_public_overlay socials_public_hide', id: 'socials_public_overlay'});
//         H_dom.AppendContent(document.body, this.overlay);
//         $_e.AddEventClick(this.overlay, Socials.Hide.bind(this));
//     }else{
//         this.overlay = document.getElementById('socials_public_overlay');
//     }
    
// };

// Socials.onClick = function(evt){
//     H_dom.toggle_class(this.container, this.cssClass);
//     H_dom.toggle_class(this.overlay, this.cssClass);
//     if (CONSTANTS.include_js_animate && this.anime && !H_dom.HasClass(this.container, this.cssClass)) {
//         for (let i = 0; i < this.iconSocials.length; i++){
//             this.iconSocials[i].style = 'opacity: 0';
//         }
//         spanim({
//             elements: '.socials_public_icon',
//             duration: 50,
//             preset: true,
//             opacity: ["0", "1"],
//             //~ transform: ["translateY(-100%)", "translateY(0%)"],
//             delay: index => index * 50
//         });
//     }
// };

// Socials.Hide = function(){
//     //~ console.log('hide');
//     H_dom.toggle_class(document.getElementById('deco_public_container_sociaux'), 'socials_public_hide', true);
//     H_dom.toggle_class(document.getElementById('socials_public_overlay'), 'socials_public_hide', true);
// };

// Socials.Show = function(){
//     //~ console.log('show');
//     H_dom.toggle_class(document.getElementById('deco_public_container_sociaux'), 'socials_public_hide', false);
//     H_dom.toggle_class(document.getElementById('socials_public_overlay'), 'socials_public_hide', false);
// };

// var try_socials=false;
// function update_socials(module_name,module_param,language=false,collapse=false){
//     let social_container = document.getElementById('socials_public_container');
//     if (social_container){
//         let settings = {};
//         let url = CONSTANTS.base_url+'public/socials/index.php';
//         settings.url = url;
//         //settings.dom_target='';
//         settings.data = {};
//         if (language) {
//             settings.data.language = language;
//         }
//         if (social_container.children.length == 3 || collapse){
//             settings.data.social_collapse = true;
//         }
//         //~ settings.data['core_insert']=false;
//         settings.skip_container = true;
//         settings.notimer = true;
//         settings.data.module_name = module_name;
//         settings.data.module_param = module_param;

//         settings.success = function(res, e){
//             //~ console.log(res);
//             if (!res.includes('Error')) {
//                 let container = document.getElementById('socials_public_container');
//                 container.innerHTML=res;
//                  Socials.Show();
//                 if (container.children.length == 3){
//                     Socials.init(1);
//                 }
//             }else{
//                 Socials.Hide();
//             }
//         };
//         try_socials=false;
//         $_a.send(settings);
//     }else{
//         if (try_socials==false){
//             try_socials=true;
//             //~ console.log(module_name+module_param+language);
//             setTimeout(function(){update_socials(module_name,module_param,language);},500);
//         }
//     }
// }