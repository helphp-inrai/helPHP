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
 * OUT OF OR  IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 * Licence type : MIT.
 */
class Burger_a extends H_module {

    #init_try_nbr_max = 5;
    #init_try_nbr = 0;

    constructor(dom_id, settings) {
        super(dom_id);
        
        this.menu_id = settings.menu_id;
        this.start_closed = settings.closed ?? false;

        this.burger, this.burger_icon, this.menu;
        this.previous_collapsed = false;
        this.first = true;
        this.collapsable = [];
        
        this.init();
    }

    init() {
        if (this.first) {
            this.menu = document.getElementById(this.menu_id);
            if (!this.menu){
                if (this.#init_try_nbr < this.#init_try_nbr_max) {
                    this.#init_try_nbr++;
                    setTimeout(this.init.bind(this), 100);
                }
                return;
            }

            this.burger = document.getElementById('burger_admin_container' + this.dom_id);
            if (!this.burger) return false;
            // h.e.disable_double_click.push(this.burger.id);
            h.e.add_event_click(this.burger, this.toggle.bind(this));

            this.burger_icon = H_dom.create_icon('menu');
            H_dom.append_content(this.burger, this.burger_icon);

            for (let i = 0; i < this.menu.children.length; i++) {
                this.parse(this.menu.children[i]);
            }
            this.first = false;

            if (this.start_closed) this.close();
        }
    }
    /**
     * Parse each element of the hierarchy
     * 
     * Add to their click event, close or collapse depending the need
     * 
     * Details about the onclick specificity on link :
     * onclick for the link intercept the href change to change the hash instead. Our event from our class execute after the original onclick
     * so we can't simply add a classic add_event_click. We need to add to the original attribute the call to close the burger. To
     * do so we have to set/get the textuel value of onclick, with getAttribute and setAttribute.
     * 
     * @param {Element} dom_elem
     */
    parse(dom_elem){
        if (dom_elem.tagName != 'LI') return;

        let link = this.#get_link(dom_elem);

        let child_ul = dom_elem.getElementsByTagName('ul');
        child_ul = child_ul ? child_ul[0] : false;
        let index = 0;
        if (child_ul){
            // it's a collapsable item, add the indicator and the event to collapse, save it to the array for ulterior access
            let indicator = H_dom.create_element('DIV', { 'class': 'burger_admin_collapse' });
            let use = H_dom.create_element('SVG_USE', {'href': H_constants.base_url + 'images/icons/feather-sprite.svg#plus'});
            H_dom.create_element('SVG', {class: 'hlp_icon', style: 'pointer-events: none;'}, use, indicator);
            H_dom.insert_before(indicator, child_ul);

            index = this.collapsable.push({
                parent: dom_elem,
                ul: child_ul,
                indicator: indicator,
                opened: false
            }) - 1;

            h.e.add_event_click(indicator, (evt) => { this.collapse(evt, index); });

            for (let i = 0; i < child_ul.children.length; i++) {
                this.parse(child_ul.children[i]);
            }
        }

        if (link.getAttribute('href') == '?'){
            link.setAttribute('onclick', link.getAttribute('onclick')+'h.modules.burger_a["' + this.dom_id + '"].collapse(event, ' + index + ');');
        } else {
            link.setAttribute('onclick', link.getAttribute('onclick')+'h.modules.burger_a["' + this.dom_id + '"].close(event);');
        }
    }

    toggle_state(index){
        H_dom.toggle_class(this.collapsable[index].ul, 'burger_admin_show');
        let use_elem = this.collapsable[index].indicator.getElementsByTagName('use')[0];
        let new_href = use_elem.getAttributeNS(null, 'href');
        if (this.collapsable[index].opened) new_href = new_href.replace('#minus', '#plus');
        else new_href = new_href.replace('#plus', '#minus');
        use_elem.setAttributeNS(null, 'href', new_href);
        this.collapsable[index].opened = this.collapsable[index].opened ? false : true;
    }
    collapse(evt, index) {
        this.toggle_state(index);
    }
    toggle(){
        if (this.closed) this.open();
        else this.close();
    }
    open() {
        // console.log('open menu burger');
        // change the icon
        let use_elem = this.burger_icon.getElementsByTagName('use')[0];
        let new_href = use_elem.getAttributeNS(null, 'href');
        new_href = new_href.replace('#menu', '#x');
        use_elem.setAttributeNS(null, 'href', new_href);

        H_dom.toggle_class(this.menu, 'hidden');
        
        if (document.body.style.overflow != 'hidden') {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
        window.scrollTo(0, 0);

        this.closed = false;

        h.e.add_event_click_outside(this.menu, this.close.bind(this));
        this.menu.other_inside_elements = [this.burger];
    }
    close() {
        // console.log('close menu burger');
        if (this.burger && this.menu) {
            // change the icon
            let use_elem = this.burger_icon.getElementsByTagName('use')[0];
            let new_href = use_elem.getAttributeNS(null, 'href');
            new_href = new_href.replace('#x', '#menu');
            use_elem.setAttributeNS(null, 'href', new_href);

            H_dom.toggle_class(this.menu, 'hidden', true);
            this.collapsable.forEach((obj, i) => {
                if (obj.opened) this.toggle_state(i);
            });
            document.body.style.overflow = 'unset';

            this.closed = true;

            h.e.remove_event_click_outside(this.menu, this.close.bind(this));
        }
    }
    #get_link(li){
        let link = li.firstElementChild;
        while(link != null && link.tagName != 'A') link = link.nextElementSibling;
        return link;
    }
}

h.modules_class = h.modules_class || {};
h.modules_class.Burger_a = Burger_a;