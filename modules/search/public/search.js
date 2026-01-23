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

class Search extends H_module {
    constructor(dom_id, settings) {
        super(dom_id);

        this.only_input = settings.only_input;
        this.document_modele = settings.document_modele;
        this.category = settings.category;

        this.last_search_content = '';

        // get dom elements and init events
        // this.input = document.getElementById('search_input' + this.dom_id);
        // h.e.add_event_key(this.input, (evt)=>{this.on_key_press(evt);});

        // this.btn_send = document.getElementById('search_btn_send' + this.dom_id);
        // h.e.add_event_click(this.btn_send, (evt)=>{this.make_hash(evt);});

        // this.result_number = document.getElementById('search_result_number' + this.dom_id);

        // this.result_container = document.getElementById('search_result' + this.dom_id);
        // h.e.add_event_click(this.result_number, ()=>{this.send();});
    }

    // send(){
    //     let settings = this.ajax_settings();
    //     settings.data = {
    //         ...settings.data,
    //         search_action: 'search_query'

    //     }
    // }
    on_key_press(evt){
        if (evt.key == 'Enter') {
            this.make_hash();
        }
    }

    make_hash(evt, change_page = false) {
        let hash = '#search=search:'+this.dom_id;
        
        if (!change_page) {
            
            let input = document.getElementById('search_input' + this.dom_id);
            let search_txt = input.value;
            hash += ':' + search_txt;

            let dom_result_number = document.getElementById('search_result_number' + this.dom_id);
            if (dom_result_number) hash += ':result_number:' + dom_result_number.selectedOptions[0].value;

            let dom_filter_category = document.getElementById('search_filter_category' + this.dom_id);
            if (dom_filter_category && dom_filter_category.selectedOptions[0].value > 0) hash += ':filter_category:' + dom_filter_category.selectedOptions[0].value;

            this.last_search_content = search_txt;

        }else{

            if (evt && evt.target.dataset.parameters){
                var params = JSON.parse(evt.target.dataset.parameters);
                hash += ':' + params.search_content;
                if (evt.target.tagName == 'SELECT') {
                    let start = evt.target.selectedOptions[0].value;
                    hash += ':start_index:' + start + ':page_limit:' + params['page_limit'];
                } else {
                    hash += ':start_index:' + params['start_index'] + ':page_limit:' + params['page_limit'];
                }
            }

            let result_number = document.getElementById('search_result_number' + this.dom_id).selectedOptions[0].value;
            hash += ':result_number:'+result_number;

            let filter_category = document.getElementById('search_filter_category' + this.dom_id).selectedOptions[0].value;
            if (filter_category > 0) hash += ':filter_category:'+filter_category;
        }

        window.location.hash = hash;
        // window.location.hash = hash + '--' + this.result_container.id;
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Search = Search;