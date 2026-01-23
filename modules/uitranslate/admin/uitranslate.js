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

class Uitranslate_a extends H_module {
    static sources = [];
    static destinations = [];
    static iso_targets = '';
    static iso_source = '';
    static sources_counter = 0;
    static iso_counter = 0;
    static tl_file = '';
    static translated = [];

    constructor(dom_id) {
        super(dom_id);
    }

    translate(iso_source){
        this.sources = [];
        this.destinations = [];
        this.iso_source = iso_source;
        this.iso_targets = JSON.parse(document.getElementById('langsiso').value);
        //cleaning depending exclude
        for(let i = 0; i < this.iso_targets.length; i++){
            if (document.getElementById('check-'+this.iso_targets[i]).checked){
                delete this.iso_targets[i];
            }
        }
        for(let i = 0; i < this.iso_targets.length; i++){
            if (this.iso_targets[i] == this.iso_source){
                let inputs = document.getElementById('fieldstab').querySelectorAll('input[lang="'+this.iso_source+'"]');
                this.sources = Array.from(inputs).map(input => [input.name,input.value]);
            }else{
                let inputs = document.getElementById('fieldstab').querySelectorAll('input[lang="'+this.iso_targets[i]+'"]');
                this.destinations[this.iso_targets[i]] = Array.from(inputs).map(input => [input.name,input.value]);
            }
            
        }
        if(String(Object.keys(this.destinations)[0]) != "undefined" || Object.keys(this.destinations).length > 1 ){
            this.sources_counter = 0;
            this.translate_loop();
        }
    }

    translate_loop(){
        //now we can do the job
        let current_source = this.sources[this.sources_counter];
        let texte = current_source[1];
        if (texte != ''){
            let basename = current_source[0].substring(current_source[0].indexOf("['")+2,current_source[0].lastIndexOf("']"));
            let settings = this.ajax_settings();
            settings.data = {
                'action': 'translate',
                'texte': texte,
                'format': 'text',
                'iso_original': this.iso_source,
                'iso_targets': this.iso_targets
            };
            settings.data.core_action = 'core_mono';
            settings.error = async (event) => {
                await h.a.sleep(5000);
                this.next_translate_loop();
            };
            settings.success = (res)=>{
                var results=JSON.parse(res);
                Object.keys(results).forEach(key => {
                    if( results[key]!="nothing"){
                        if (key != this.iso_source && key!="") {
                            let trad = JSON.parse(results[key]);
                            let targetname = key+'[\''+basename+'\']';
                            document.getElementsByName(targetname)[0].value = trad.translatedText;
                        }
                    }else{
                        console.log('no connection to libretranslate or nothing to translate');
                    }
                });
                this.next_translate_loop();
            };
            h.a.send(settings);
        }else{
            this.next_translate_loop();
        }
    }
    
    next_translate_loop(){
        this.sources_counter++;
            if (this.sources_counter < this.sources.length){
                this.translate_loop();
            }else{
                this.sources_counter=0;
                delete this.destinations;
                delete this.iso_targets;
            }
    }
    //saving section
    save_all(){
        this.destinations = [];
        this.iso_targets = JSON.parse(document.getElementById('langsiso').value);
        this.tl_file = document.getElementById('tlfile').value;

        //cleaning depending exclude
        for(let i = 0; i < this.iso_targets.length; i++){
            if (document.getElementById('check-'+this.iso_targets[i]).checked){
                delete this.iso_targets[i];
            }
        }

        for(let i = 0; i < this.iso_targets.length; i++){
            let inputs = document.getElementById('fieldstab').querySelectorAll('input[lang="'+this.iso_targets[i]+'"]');
            this.destinations[this.iso_targets[i]] = Array.from(inputs).map(input => [input.name,input.value]);
        }
        if(String(Object.keys(this.destinations)[0] != "undefined") || Object.keys(this.destinations).length > 1 ){
            this.iso_counter = 0;
            this.save_loop();
        }
    }

    save_loop(){
        // console.log(sources);
        // console.log(destinations);
            //now we can do the job
        var ttiso = this.iso_targets[this.iso_counter];
        //cleaning data
        this.translated = {};
        for(let i = 0; i < this.destinations[this.iso_targets[this.iso_counter]].length; i++){
            let temptrad = this.destinations[this.iso_targets[this.iso_counter]][i];
            var tlkey = temptrad[0].substring(temptrad[0].indexOf("['")+2,temptrad[0].lastIndexOf("']"));
            // this.translated.push({tlkey:temptrad[1]});
            this.translated[tlkey] = temptrad[1];
        }
        let settings = this.ajax_settings();
        settings.data = {
            'uitranslate_action': 'uitranslate_save',
            'iso': ttiso,
            'tlfile': this.tl_file,
            'translated': this.translated
        };
        // settings.data.core_action = 'core_mono';
        settings.error = async (event) => {
            await h.a.sleep(5000);
            this.next_save_loop();
        };
        settings.success = (res) => {
            this.next_save_loop();
        };
        // console.log(translated);
        h.a.send(settings);
    }
    next_save_loop(){
        this.iso_counter++;
        if (this.iso_counter < this.iso_targets.length){
            this.save_loop();
        }else{
            this.iso_counter = 0;
            delete this.destinations; // ?? delete a static ? 
            delete this.iso_targets;
        }
    }

    open_modal(dom_id){
        H_ui.open_popup_modal({}, 'uitranslate', {
            // token: this.token,
            uitranslate_action: 'uitranslate_newtl',
            dom_id: this.dom_id,
        },true);
    }

    add_word(dom_id){
        var tbod = document.getElementById('tlfields').getElementsByTagName('tbody')[0];
        var newRow = tbod.insertRow(tbod.childElementCount-2);
        var newCell = newRow.insertCell();
        var newText = document.createTextNode(document.getElementById('inpnew').value);
        newCell.appendChild(newText);
        newCell = newRow.insertCell();
        this.iso_targets = JSON.parse(document.getElementById('langsiso').value);

        for(let i = 0; i < this.iso_targets.length;i++){
            newCell = newRow.insertCell();
            let inp = H_dom.create_element('input', {'type':'text','name':this.iso_targets[i]+'[\''+document.getElementById('inpnew').value+'\']','lang':this.iso_targets[i],'value':document.getElementById('inpnew').value});
            newCell.appendChild(inp);
        }
    }

    delete_word(targ,dom_id){
        targ.target.parentNode.parentNode.remove();
        // var tbod=document.getElementById('tlfields').getElementsByTagName('tbody')[0];
        // tbod.deleteRow(num);
        h.modules.uitranslate_a[dom_id].save_all();
    }
    
}

h.modules_class = h.modules_class || {};
h.modules_class.Uitranslate_a = Uitranslate_a;