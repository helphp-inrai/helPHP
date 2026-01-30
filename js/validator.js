/*
 * COPYRIGHT (c) 2024-2026 INRAI / Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2017-2024 Mickaël Bourgeoisat / Emile Steiner
 * COPYRIGHT (c) 2009-2017 Mickaël Bourgeoisat
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the \"Software\"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modif_y, merge, publish, distribute, sublicense, and/or sell
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

/**
 * HTML form validation class with event handling and input controls<br>
 * Essentialy called and used automaticaly by H.php class on inputs of forms...
 */

class H_validator{
    static  date_separator = ' / ';
    static  username_valid_string = H_constants && H_constants.username_valid_string ? H_constants.username_valid_string : 'abcdefghijklmnopqrstuvwxyz-ABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789';
    static  alpha_valid_string = ' abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    static  integer_valid_string = '-0123456789';
    static  float_valid_string = '-0123456789.,';
    static  OK = 0;
    static  too_small = 1;
    static  too_big = 2;
    static  modal = null;

    constructor() {
        
    }

    /**
     * Parses and prepares all fields of a form for validation
     * Configures events and navigation between fields
     * @param {HTMLFormElement|string} formDomObject - The DOM object of the form or its ID
     * @returns {boolean} false if the form is not found
     * @example
     * const validator = new H_validator();
     * validator.parse_fields('myForm');
     */
    parse_fields(formDomObject){
        if (H_generics.is_string(formDomObject)){
            let test = document.getElementById(formDomObject);
            if (test){
                formDomObject = test;
            } else {
                setTimeout(()=>{this.parse_fields(formDomObject);},100);
                formDomObject = false;
            }
        }
        
        if(!formDomObject){
            console.log('Form not found...');
            return false;
        }

        let lst = formDomObject.elements;
        formDomObject._validator = this;
        H_event.remove_all_events(formDomObject);
        H_event.add_event(formDomObject,'submit',this.on_submit_form);
        var previous_field = null;
        var first_field = null;
        for(let i = 0; i < lst.length ; i++){
            let input = lst[i];
            
            this.display_error_field(input,[]);
            
            // skip tinymce fields or unidentified fields
            let parent_id = input.parentNode.getAttribute('id');
            if(parent_id){
                if(parent_id.substr(0,4)=='mce_' || parent_id.substr(0,5)=='mceu_' || input.id.substr(0,4)=='mce_' || input.id.substr(0,5)=='mceu_' || (!input.id && !input.name)){
                    //console.log('skip',input.id);
                    continue;
                }
            }

            if(!this.protect_input(input)){
                continue;
            }

            if(!first_field){
                first_field = input;
            }

            if(previous_field){
                input._prev = previous_field;
                previous_field._next = input;
            }
            previous_field = input;
        }

        if(first_field){
            first_field._prev = previous_field;
            previous_field._next = first_field;
        }
    }

    /**
     * Protects an input field by adding appropriate validation events
     * @param {HTMLInputElement} input - The input element to protect
     * @returns {boolean} true if the field was protected, false otherwise
     * @private
     */
    protect_input(input){

        let type = input.getAttribute('data-type') || input.type;
        input._validator = this;
        if (type != 'submit' || type != 'button') input.classList.toggle('hlp_validator_field',true);
        input._originalValue = input.value;

        switch(type){
            case 'hidden':
                return false;

            case 'text':
            case 'login':
            case 'password':
            case 'email':
            case 'select-one':
                H_event.add_event(input , 'change' , this.check_event_text);
                H_event.add_event(input , 'keydown' , this.check_event_text);
                H_event.add_event(input , 'blur' , this.check_event_text);
                H_event.add_event(input , 'input' , this.check_event_validity);
            break;

            case 'int':
            case 'float':
                H_event.add_event(input , 'change' , this.check_event_text);
                H_event.add_event(input , 'keydown' , this.check_event_text);
                H_event.add_event(input , 'input' , this.check_event_validity);
            break;

            case 'date':
                H_event.add_event(input , 'change' , this.check_event_date);
            break;
        }

        H_event.add_event(input , 'dblclick' , H_event.stop_propagation);

        return true;
    }

    /**
     * Event handler for form submission
     * Checks form validity before submission
     * @param {Event} event - The submission event
     * @param {boolean} [confirmed] - Indicates if the submission has been confirmed
     * @returns {boolean} true if submission can continue
     */
    on_submit_form(event, confirmed = undefined){
        let formDomObject = event.currentTarget;
        
        if (event.submitter) formDomObject._submitter = event.submitter;

        if(formDomObject._disableNextSubmit){
            H_event.stop_event(event);
            formDomObject._disableNextSubmit = false;
            return false;
        }

        let validator = formDomObject._validator;
        let test = validator.check_form(formDomObject);

        if(!test){
            H_event.stop_event(event);

            if(validator.error_field){
                validator.error_field.focus();
            }

            return false;
        }else{
            H_event.stop_event(event);
            if(formDomObject._submitter){
                if (formDomObject._submitter.dataset.with_token){
                    let id = formDomObject.id + '-token';
                    let inpHidden = document.getElementById(id);
                    if (!inpHidden){
                        H_ui.get_token(event, formDomObject);
                        return;
                    }
                } 
                
                let tmp = formDomObject._submitter.getAttribute('data-submit_function');
                if(tmp){
                    submit_handler_name = tmp;
                }

                let confirm_message = formDomObject._submitter.getAttribute('data-confirm');
                if(confirm_message){
                    H_ui.confirm_popup(confirm_message, ()=>{validator.on_submit_form_confirmed(formDomObject);}, null);
                    return false;
                }else{
                    validator.on_submit_form_confirmed(formDomObject);
                }
            }else{

                validator.on_submit_form_confirmed(formDomObject);
            }

        }

        return true;
    }

    /**
     * Processes the confirmed form submission
     * Prepares data and launches sending according to configuration
     * @param {HTMLFormElement} formDomObject - The form to submit
     * @returns {boolean} false (prevents default submission)
     */
    on_submit_form_confirmed(formDomObject){
        var settings = {};

        let submit_handler_name = formDomObject.getAttribute('data-submit_function');
        let dom_target_name = formDomObject.getAttribute('data-dom_target');

        if(dom_target_name){
            
            if(dom_target_name == '.parent'){
                settings.dom_target = formDomObject.parentNode;
            }else{
                let dom_target = document.getElementById(dom_target_name);
                if(dom_target){
                    settings.dom_target = dom_target;
                }
            }
        }

        let upload_tinymce = false;
        if(formDomObject._submitter){

            if(formDomObject._submitter.getAttribute('data-tinymce_save')){
                if(tinymce && tinymce.activeEditor){
                    tinymce.triggerSave();
                    upload_tinymce = true;
                }
            }

            // if a target is specified in the submit button
            // it replaces the one on the form
            let dom_target_name = formDomObject._submitter.getAttribute('data-dom_target');

            if(dom_target_name){
                let dom_target = document.getElementById(dom_target_name);
                if(dom_target){
                    settings.dom_target = dom_target;
                }
            }
            // send only the data set in the submit button
            let tmp = formDomObject._submitter.getAttribute('data-only');
            if(tmp){
                settings.data = {};
                // action / value of the submit button
                settings.data[formDomObject._submitter.name] = formDomObject._submitter.value;

                // add fields that must always be posted
                for(let i = 0; i < formDomObject.length ;i++){
                    let input = formDomObject[i];
                    if(input.getAttribute('data-alwaysposted')){
                        settings.data[input.name] = input.value;
                    }
                }

                // if a select is used for the submit  of the form
                // it can (and should) contain two attributes to determine the action to be taken
                // data-actionname = name of the variable to post (equivalent to the name of a submit button)
                // data-actionvalue = value to post in this variable (equivalent to the value of a submit button)
                //
                // it also works with any other input
                let actionName = formDomObject._submitter.getAttribute('data-actionname');
                if(actionName){
                    let actionValue = formDomObject._submitter.getAttribute('data-actionvalue');
                    if(actionValue !== null){
                        settings.data[actionName] = actionValue;
                    }
                }

            }else{
                settings.data = formDomObject;
            }
            
            let extra_parameters = formDomObject._submitter.dataset.parameters;
            if(extra_parameters){
                extra_parameters = JSON.parse(extra_parameters);
                if(H_generics.is_filled_object(extra_parameters)){
                    settings.extra_data = H_generics.merge_objects(settings.extra_data,extra_parameters);
                }
            }

            // use the action from the submit button if one is defined
            settings.url = formDomObject._submitter.getAttribute('action') || formDomObject.getAttribute('action');

        }else{
            settings.data = formDomObject;
            settings.url = formDomObject.getAttribute('action');
        }

        if(upload_tinymce){
            // tinymce images must be uploaded before the form is submitted
            tinymce.activeEditor.uploadImages(function(success) {
                if(submit_handler_name){
                    H_generics.execute_function_by_name(submit_handler_name, window, settings);
                }else{
                    h.a.send(settings);
                }
            });
        }else{

            if(submit_handler_name){
                H_generics.execute_function_by_name(submit_handler_name, window, settings);
            }else{

                h.a.send(settings);
            }
        }
        return false;
    }

    /**
     * Sends the form via AJAX
     * @param {Object} settings - The sending parameters
     */
    send_form(settings){
        h.a.send(settings);
    }

    /**
     * Checks the validity of all form fields
     * @param {HTMLFormElement} formDomObject - The form to check
     * @returns {boolean} true if all fields are valid
     */
    check_form(formDomObject){

        let lst = formDomObject.elements;
        let validator = formDomObject._validator;
        let submitter = formDomObject._submitter;
        let errorCount = 0;

        this.error_field = null;

        let only_submiter = false;
        if(submitter){
            only_submiter = submitter.getAttribute('data-only') !== null;
            if(submitter.getAttribute('data-cancel') !== null){
                return true;
            }
        }

        for(let i = 0; i < lst.length ; i++){
            let field = lst[i];
            if(only_submiter && field.getAttribute('data-alwaysposted') === null){
                continue;
            }
            if (field.tagName == 'FIELDSET'){   // fieldset doesn't need validation
                continue;
            }
            if(!validator.check_field(field)){
                if(!this.error_field){
                    this.error_field = field;
                }
                errorCount++;
            }
        }

        return errorCount === 0;
    }

    /**
     * Checks the validity of an individual field
     * Applies appropriate CSS classes and manages error display
     * @param {HTMLInputElement} input - The field to check
     * @returns {boolean} true if the field is valid
     */
    check_field(input){

        let validator = input._validator;
        if(!validator){
            return true;
        }

        let type = input.getAttribute('data-type') || input.type;
        if(type == "submit"){
            return true;
        }

        let test = true;
        let formDomObject = input.form;
        let icon = input._validatorIcon;
        
        let err_list = [];

        let sizeTest = validator.evaluate_size(input);
        let required = input.getAttribute('data-required') !== null ? parseInt(input.getAttribute('data-required')) > 0 : false;
        let optional = input.getAttribute('data-optional') !== null ? parseInt(input.getAttribute('data-optional')) > 0 : false;

        if(optional && input.value == ''){
            return true;
        }

        // replace tinymce \n
        let parent_id = input.parentNode.getAttribute('id');
        if(input.id){
            if(input.id.substr(0,8)=='tinymce_'){
                input.value = input._tinymce.getContent({format: 'raw'}).replace(/data-mce(.*?)"(.*?)"/g, '');
            }
        }

        // original field
        // input = confirmation field
        let check_field_id = input.getAttribute('data-check_field');
        if(check_field_id){
            let check_field = document.getElementById(check_field_id);
            if(check_field){
                let ok = true;
                // if the original field has changed :
                if(check_field._originalValue != check_field.value && check_field.value != input.value){
                    
                    ok = false;
                }else if(input.value.length > 0 && check_field.value != input.value){
                    ok = false;
                }

                if(ok){
                    input.classList.toggle('wrong',false);
                    input.classList.toggle('good',required);
                }else{
                    input.classList.toggle('wrong',true);
                    input.classList.toggle('good',false);
                    validator.display_error_field(input, ['not_the_same_value']);
                    return false;
                }
            }
        }

        let confirm_id = input.getAttribute('data-confirm_field');
        if(confirm_id){
            let confirm = document.getElementById(confirm_id);
            if(confirm){
                let ok = true;
                if(input._originalValue != input.value){
                    if(confirm.value != input.value){
                        ok = false;
                    }
                }else{
                    if(confirm.value.length > 0 && confirm.value != input.value){
                        ok = false;
                    }
                }
                if(ok){
                    input.classList.toggle('wrong',false);
                    input.classList.toggle('good',true);
                    confirm.classList.toggle('wrong',false);
                }else{
                    confirm.classList.toggle('wrong',true);
                    confirm.classList.toggle('good',false);
                }
            }
        }

        if(input.value === '' && !required && sizeTest == H_validator.OK){
            input.classList.toggle('wrong',false);
            input.classList.toggle('good',false);
            if(icon){
                icon.classList.toggle('wrong',false);
                icon.classList.toggle('good',false);
            }
            return true;
        }

        switch(type){
            case 'text':
            case 'login':
            case 'password':
                let restriction = input.getAttribute('data-restrict');
                let exclusion = input.getAttribute('data-exclude');
                test = validator.check_text(input.value,restriction,exclusion);
            break;

            case 'int':
                test = validator.check_int(input.value);
            break;

            case 'float':
                test = validator.check_float(input.value);
            break;

            case 'date':
                test = validator.check_date(input.value);
            break;

            case 'email':
                test = validator.check_email(input.value);
            break;

        }


        if(icon){
            icon.title = '';
            input.title = '';
        }

        let length = input.value.trim().length;
        if (input.type == 'checkbox' && !input.checked) {
            length = 0;
        }
        let cur_lng = H_constants.current_lang_iso;

        if(!test){
            if(icon){ icon.title += H_constants.get_text('invalid_content'); }
            input.title += H_constants.get_text('invalid_content');
            
            err_list.push('invalid_content');
        }
        
        if(length === 0 && required){
            // nothing entered in a required field.
            test = false;
            if(icon){ icon.title = H_constants.get_text('required_field')+'\n'; }
            input.title = H_constants.get_text('required_field')+'\n';
            
            err_list.push('required_field');
        }

        if(sizeTest !== H_validator.OK){
            test = false;
            if(sizeTest == H_validator.too_small){
                if(icon){ icon.title += H_constants.get_text('short_content'); }
                input.title += H_constants.get_text('short_content');
                err_list.push('short_content');
            }
            if(sizeTest == H_validator.too_big){
                if(icon){ icon.title += H_constants.get_text('long_content'); }
                input.title += H_constants.get_text('long_content');
                err_list.push('long_content');
            }

        }

        if(type == 'int' || type=='float'){
            if(validator.check_input_limits(input) !== H_validator.OK){
                test = false;
                if(icon){ icon.title += H_constants.get_text('out_of_bounds'); }
                input.title += H_constants.get_text('out_of_bounds');
                
                err_list.push('out_of_bounds');
            }
        }

        if(test && input.value !== ''){

            input.classList.toggle('wrong',false);
            input.classList.toggle('good',true);

            if(icon){
                icon.classList.toggle('wrong',false);
                icon.classList.toggle('good',true);
            }
        }else if(!test){

            input.classList.toggle('wrong',true);
            input.classList.toggle('good',false);

            if(icon){
                icon.classList.toggle('wrong',true);
                icon.classList.toggle('good',false);
            }
        }
        
        validator.display_error_field(input, err_list);

        return test;
    }

    /**
     * Displays error messages for a given field
     * Creates a parent container if necessary and displays errors
     * @param {HTMLInputElement} input - The field concerned
     * @param {string[]} err_list - List of error codes to display
     */
    display_error_field(input, err_list){
        if (input.type == 'hidden' || input.tagName == 'FIELDSET' || input.tagName == 'BUTTON' || input.type == 'button') return;
        if (input._tinymce) return;
        
        if (input.id == ''){
            input.id = H_generics.get_unique_id();
        }
        let id = input.id;
        
        let parent = document.getElementById(id+'_parent');
        if (!parent){
            // moved = true;
            parent = H_dom.create_element('DIV',{'id':id+'_parent', 'class': 'hlp_validator_parent'});
            // get the attribute title form the input and put it on the parent
            if (input.title != '' && ((H_constants.is_admin && H_constants.theme_admin.display_title_onfocus) || (!H_constants.is_admin && H_constants.theme_public.display_title_onfocus))) {
                parent.title = input.title;
                H_dom.add_class(parent, 'display_title_onfocus');
            }
            // same thing with attribute class
            if (input.hasAttribute('class')) {
                parent.setAttribute('class', 'hlp_validator_parent ' + input.getAttribute('class'));
            }

            // if the input is hidden, remove the class from it and put it on the parent instead.
            if (H_dom.has_class(input, 'hidden')) {
                H_dom.remove_class(input, 'hidden');
                H_dom.add_class(parent, 'hidden');
            }

            if (input.dataset.order) {
                parent.dataset.order = input.dataset.order;
                delete(input.dataset.order);
            }

            H_dom.insert_after(parent,input);
            H_dom.append_content(parent, input);
            //~ input.focus();
        } else {
            while (parent.lastChild && parent.lastChild.tagName != 'INPUT' && parent.lastChild.tagName != 'SELECT' && parent.lastChild.tagName != 'TEXTAREA'){
                H_dom.remove_element(parent.lastChild);
            }
        }
        
        if (err_list.length > 0){
            err_list.forEach((tl)=>{
                let msg = H_dom.create_element('DIV',{'class':'msg_err'},H_constants.get_text(tl));
                H_dom.append_content(parent, msg);
            });
        }
        
    }

    /**
     * Displays an error modal for an invalid field
     * @param {HTMLInputElement} input - The field concerned by the error
     */
    bad_field_modal(input){
        let validator = input._validator;
        if (!validator.modal) validator.modal = [];
        if (!validator.modal[input.name]){
            let cur_lng = H_constants.current_lang_iso;
            validator.modal[input.name] = H_ui.add_window(null, {
                modal: true,
                nodrag: true,
                class: 'modal_validator_info',
                id:input.name+'_validator'
            });

            // modal's content
            let container_info = H_dom.create_element('DIV', {'class': 'validator_info_container'});
            let msg_err = H_dom.create_element('DIV', {'class': 'validator_info_general'}, H_constants.get_text('required_field'));
            H_dom.append_content(container_info, msg_err);

            validator.modal[input.name].set_content(container_info);
            H_dom.insert_after(validator.modal[input.name].dom_element, input);
            let rect = H_dom.get_global_rect(input);
            H_dom.set_position(validator.modal[input.name].dom_element, rect.right, rect.y-rect.height);
            
        } else {
            validator.modal[input.name].show();
        }

        if(validator.error_field){
            validator.error_field.focus();
        }
    }

    /**
     * Validates email address format
     * @param {string} email - The email address to validate
     * @returns {boolean} true if the email is valid
     * @example
     * validator.check_email('test@example.com'); // returns true
     * validator.check_email('invalid-email'); // returns false
     */
    check_email(email){
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
    }

    /**
     * Event handler for date field validation
     * @param {Event} event - The triggered event
     * @private
     */
    check_event_date(event){
        
        let input = event.target;
        let validator = input._validator;
        
        validator.check_field(input);
        return;
    }

    /**
     * Event handler for real-time validation
     * @param {Event} event - The triggered event
     * @private
     */
    check_event_validity(event){
        let input = event.target;
        let validator = input._validator;
        validator.check_field(input);
    }

    /**
     * Validates date format,
     * it's a dummy function because the navigator validate automaticaly the datetime tag actually.
     * @param {string} dateString - The date string to validate
     * @returns {boolean} true if the date is valid
     */
    check_date(dateString){
        return true;
    }

    /**
     * Validates and formats a date string
     * Automatically corrects out-of-bounds values
     * @param {string} dateString - The date string to validate
     * @returns {string} The formatted and validated date
     */
    validate_date(dateString){
        let chunks = dateString.split(H_validator.date_separator);

        let minimums = [1,1,1900];
        let maximums = [31,12,9999];
        let paddings = ['00','00','0000'];

        for(let i = 0; i < chunks.length ; i++){
            chunks[i] = Math.min(maximums[i],Math.max(isNaN(chunks[i])?0:parseInt(chunks[i]) , minimums[i]));
        }

        let d = new Date(chunks[2], chunks[1] - 1, chunks[0]);
        while(!(d && (d.getMonth() + 1) == parseInt(chunks[1]))){

            d = new Date(chunks[2], chunks[1] - 1, chunks[0]);
        }

        return chunks.join(H_validator.date_separator);
    }

    /**
     * Converts predefined string filters to valid character strings
     * @static
     * @param {string} restriction - The filter name to convert
     * @returns {string} The corresponding valid character string
     * @example
     * H_validator.convert_string_filter('_username'); // returns username valid chars
     * H_validator.convert_string_filter('_alpha'); // returns alphabetic chars
     */
    static convert_string_filter(restriction){
        switch(restriction){
            case '_username':
                restriction = H_validator.username_valid_string;
            break;

            case '_alpha':
                restriction = H_validator.alpha_valid_string;
            break;

            case '_int':
                restriction = H_validator.integer_valid_string;
            break;

            case '_float':
            case '_num':
                restriction = H_validator.float_valid_string;
            break;
        }

        return restriction;
    }

    /**
     * Checks if a string contains only allowed characters
     * @param {string} str - The string to check
     * @param {string} [restriction] - Allowed characters or predefined filter name
     * @param {string} [exclusion] - Forbidden characters or predefined filter name
     * @returns {boolean} false if the string contains unauthorized characters
     * @example
     * validator.check_text('abc123', '_username'); // returns true
     * validator.check_text('abc@123', '_username'); // returns false
     */
    check_text(str, restriction, exclusion){
        restriction = H_validator.convert_string_filter(restriction);
        exclusion = H_validator.convert_string_filter(exclusion);

        if(restriction || exclusion){
            let result = '';
            for(let i = 0 , c = str.length; i < c ; i++){
                if((restriction && restriction.indexOf(str.charAt(i)) == -1) || (exclusion && exclusion.indexOf(str.charAt(i)) > -1) ){
                    return false;
                }
            }
            return true;
        }else{
            return true;
        }
    }

    /**
     * Checks if a string represents a valid integer
     * @param {string} str - The string to check
     * @returns {boolean} false if the string is not a valid integer
     * @example
     * validator.check_int('123'); // returns true
     * validator.check_int('12.3'); // returns false
     * validator.check_int('abc'); // returns false
     */
    check_int(str){
        return (!isNaN(parseInt(str)) && parseInt(str)+'' == str+'');
    }

    /**
     * Checks if a string represents a valid floating point number
     * @param {string} str - The string to check
     * @returns {boolean} false if the string is not a valid floating point number
     * @example
     * validator.check_float('123.45'); // returns true
     * validator.check_float('12,34'); // returns true
     * validator.check_float('abc'); // returns false
     */
    check_float(str){
        return !isNaN(parseFloat(str));
    }

    /**
     * Evaluates if a field size respects min/max constraints
     * @param {HTMLInputElement} input - The field to evaluate
     * @returns {number} H_validator.OK, H_validator.too_small, or H_validator.too_big
     */
    evaluate_size(input){

        let sizemin = parseInt(input.getAttribute('data-sizemin'));
        let sizemax = parseInt(input.getAttribute('data-sizemax'));

        let length = input.value.trim().length;
        let required = input.getAttribute('data-required') !== null ? parseInt(input.getAttribute('data-required')) > 0 : false;

        if(length === 0 && !required){
            return H_validator.OK;
        }

        if(!isNaN(sizemin) && length < sizemin){
            return H_validator.too_small;

        }else if(!isNaN(sizemax) && length > sizemax){
            return H_validator.too_big;
        }

        return H_validator.OK;
    }

    /**

     * Check input keyboard event to launch text validation.
     * @param {Event} event - keyboard or change
     * @returns {boolean} true if check is ok, false instead.
     * @private
     */
    check_event_text(event){

        let input = event.target;

        let validator = input._validator;

        if(input.tagName == 'TEXTAREA'){
            switch(event.key){
                case 'Backspace': // backspace
                case 'Tab': // tab
                case 'Delete': // delete
                    validator.check_field(input);
                break;
            }
            return true;
        }

        switch(event.key){

            case 'End': // page down
            case 'Home': // page up
            case 'ArrowLeft': // left
            case 'ArrowRight': // right
                return false;


            case 'Enter': // return
                let auto_submit = input.getAttribute('data-returnsubmit');
                if(auto_submit){

                    let form = input.form;
                    form._disableNextSubmit = false;

                    if(H_generics.is_string(auto_submit)){
                        let json = null;
                        try{
                            json = JSON.parse(auto_submit);
                        }catch(e){
                        }
                        if(H_generics.is_object(json)){
                            let k = Object.keys(json)[0];

                            let submitter = H_dom.create_element('INPUT',{'name':k , 'value':json[k] , 'type':'hidden'});
                            form._validator.protect_input(submitter);
                            form.appendChild(submitter);

                            H_ajax.setFormSubmitter(submitter);

                        }else{
                            for(let i=0; i<form.elements.length ; i++){
                                let e = form.elements[i];
                                if(e.name == auto_submit){
                                    H_ajax.setFormSubmitter(e);
                                    break;
                                }
                            }
                        }
                    }

                    H_event.stop_event(event);

                    H_event.send_event(form,'submit');

                    return false;
                }else{

                    let form = input.form;
                    form._disableNextSubmit = true;
                }

                return true;

            case 'Backspace': // backspace
            case 'Tab': // tab

            case 'Delete': // delete
                validator.check_field(input);
                return false;

        }

        let restriction = input.getAttribute('data-restrict');
        let exclusion = input.getAttribute('data-exclude');

        if(event.type == 'keydown'){
            restriction = H_validator.convert_string_filter(restriction);
            exclusion = H_validator.convert_string_filter(exclusion);

            if((restriction && restriction.indexOf(event.key) == -1) || (exclusion && exclusion.indexOf(event.key) > -1)){
                H_event.stop_event(event);
                return false;
            }

        } else {
            if(restriction){
                input.value = validator.restrict_string(input.value , restriction , exclusion);
            }
        }

        validator.check_field(input);
    }

    /**
     * Filter string to keep only authorized chars.
     * @param {string} str - to filter
     * @param {string} [restriction] - Chars authorized only
     * @param {string} [exclusion] - Chars rejected only
     * @returns {string} string filtered
     * @example
     * validator.restrict_string('abc@123', '_username'); // returns 'abc123'
     */
    restrict_string(str, restriction, exclusion){
        restriction = H_validator.convert_string_filter(restriction);
        exclusion = H_validator.convert_string_filter(exclusion);

        if(restriction || exclusion){
            let result = '';
            for(let i = 0 , c = str.length; i < c ; i++){
                if((restriction && restriction.indexOf(str.charAt(i)) > -1) || (exclusion && exclusion.indexOf(str.charAt(i)) == -1) ){
                    result += str.charAt(i);
                }
            }
            return result;
        }else{
            return str;
        }
    }

    /**
     * Check if int input value in min/max limit
     * @param {HTMLInputElement} input to check
     * @returns {number} H_validator.OK, H_validator.too_small, or H_validator.too_big
     */
    check_input_limits(input){

        let min,max,v;
        switch(input.getAttribute('data-type')){
            case 'float':
                min = parseFloat(input.getAttribute('min'));
                max = parseFloat(input.getAttribute('max'));
                v = parseFloat(input.value);
            break;

            case 'int':
                min = parseInt(input.getAttribute('min'));
                max = parseInt(input.getAttribute('max'));
                v = parseInt(input.value);
            break;
        }

        if(!isNaN(min) && !isNaN(v) && v < min){
            return H_validator.too_small;
        }

        if(!isNaN(max) && !isNaN(v) && v > max){
            return H_validator.too_big;
        }

        return H_validator.OK;
    }


}

var h = h || {};
h.libs = h.libs || {};

h.libs.validator = H_validator;
window.H_validator = H_validator;