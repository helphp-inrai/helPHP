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

//manage all events and avoid redundancies... 
//to switch event activation on dom element without removing it look at H_dom.XXXMouseEvents in dom.js
/**
 * @class H_event
 * @classdesc
 * Manages all DOM and custom events for the application.<br>
 * Provides static methods for adding, removing, dispatching, and handling events.<br>
 * Handles mouse, keyboard, touch, pen tablet, drag, drop, resize, and custom events.<br>
 * <br>
 * Its main purpose is to permit to add multiple callbacks on one event.<br>
 * To do that, inline event attributes like onclick, are transformed (except on A tag) by h.php class, as js events stored in h.e.events_listeners.<br><br>
 * Secondary, this class unify them , to manage a simple "click", with a mouse, a pen, a touch handler etc... scripts become often so big !<br>
 * What about drag&drop on a touch system and a mouse or a touch pad ? This class unify them all, for all kind of deviceS.<br>
 * to switch event activation on dom element without removing it look at H_dom.disable_mouse_events and  H_dom.enable_mouse_events in dom.js<br>
 * <br>
 * take a look too to shortcut functions at the end of the class.<br>
 * Used as h.libs.event or h.e.<br>
 */
class H_event {

    static debug = false;

    static events_listeners = {};

    static current_mouse_over_element = null;
    static current_mousedown_event = null;
    static previous_mousedown_event = null;
    static previous_mouseup_time = 0;

    static hide_on_mousedown_outside = [];
    static event_on_mousedown_outside = [];

    static click_timer = 0;

    static keys = {
        shiftKey: false,
        ctrlKey: false,
        altKey: false
    };
    static mouse = {
        left: false,
        right: false,
        middle: false
    };

    static lock_events_removing = false;
    static events_to_remove = [];

    // For disabling double click on element
    static disable_double_click = [];

    static outside_events = {};

    static default_drop_callback = null;

    static window_resize_timer = 0;
    static resize_data = {
        left: false,
        top: false,
        right: false,
        bottom: false,
        dom_element: null,
        cursor: 'auto',
        resizing: false
    };

    static move_list = [];
    static dragging = false;
    static drag_initialized = false;
    // long press indicate if we start the drag & drop after a long press (1), normal (0), not indicated (-1)
    static long_press = -1;

    static window_on_load_handlers = [];
    static window_on_resize_handlers = [];
    static window_loaded = false;

    static touch_handler_is_active = false;

    static DROP_FILES = 'Files';
    static DROP_HTML = 'text/html';
    static DROP_TEXT = 'text/plain';

    static touch = {
        mouse_down_elem: null,
        prev_over_elem: null,
        history: [],
        prev_event_time: 0,
        prev_btn: '',
        events_list: [],
        prev_touch: '',
        prev_click_time: 0,
        click_count: 0,
        prev_x: 0,
        prev_y: 0,
        has_moved: 0,

    };

    static EVENT_FOCUS = 'focus';
    static EVENT_BLUR = 'blur';
   
    static EVENT_MOUSEDOWN = 'MouseDown';
    static EVENT_MOUSEUP = 'MouseUp';
    static EVENT_CLICK = 'Click';
    static EVENT_DOUBLECLICK = 'DblClick';

    static EVENT_MOUSEENTER = 'mouseenter';
    static EVENT_MOUSEOVER = 'mouseover';

    static EVENT_MOUSEOUT = 'mouseout';
    static EVENT_MOUSELEAVE = 'mouseleave';

    static EVENT_KEYUP = 'KeyUp';
    static EVENT_KEYDOWN = 'KeyDown';
    static EVENT_CHANGE = 'change';

    static EVENT_MOUSEUP_OUTSIDE = 'MouseUpOutSide';
    static EVENT_MOUSEDOWN_OUTSIDE = 'MouseDownOutSide';

    static EVENT_TOUCHSTART = 'touchstart';
    static EVENT_TOUCHEND = 'touchend';
    static EVENT_TOUCHMOVE = 'touchmove';

    static is_mac = navigator.userAgent.toUpperCase().indexOf('MAC') >= 0;
    static is_mac_like = /(Mac|iPhone|iPod|iPad)/i.test(navigator.userAgent);
    static is_ios = (() => {
        if (/iPad|iPhone|iPod/.test(navigator.platform)) {
            return true;
        } else {
            return navigator.maxTouchPoints &&
            navigator.maxTouchPoints > 2 &&
            /MacIntel/.test(navigator.platform);
        }
    })();
    static is_ipad_os = (() => {
        return navigator.maxTouchPoints && 
            navigator.maxTouchPoints > 2 &&
            /MacIntel/.test(navigator.platform);
    })();

    constructor() {}

    /**
     * Initializes global event listeners for mouse, touch, keyboard, resize, and load events.<br>
     * Detects platform and sets up appropriate handlers.
     */
    static init() {
        if ((H_event.is_mac || H_event.is_mac_like) && !H_event.is_ios) {
            if (H_event.debug) {
                console.log('mac');
            }
            document.addEventListener('mouseup', H_event.global_mouse_up);
            document.addEventListener('mousedown', H_event.global_mouse_down);
            document.addEventListener('mousemove', H_event.global_mouse_move);
        } else {
            document.addEventListener('pointerup', H_event.global_mouse_up);
            document.addEventListener('pointerdown', H_event.global_mouse_down);
            document.addEventListener('pointermove', H_event.global_mouse_move);
        }

        H_event.touch_handler_is_active = true;
        document.addEventListener("touchmove", H_event.disable_scroll_on_touch, { 'passive': false });
        document.addEventListener("touchend", () => {
            H_event.long_press = -1;
            if (!H_event.touch_handler_is_active) {
                H_event.touch_handler_is_active = true;
                document.addEventListener("touchmove", H_event.disable_scroll_on_touch, { 'passive': false });
            }
        });

        document.addEventListener('keydown', H_event.global_key_down);
        document.addEventListener('keyup', H_event.global_key_up);

        H_event.enable_drop(document, H_event.global_drop);

        window.addEventListener('resize', H_event.window_resize);
        window.addEventListener('load', H_event.window_load);
    }

    /**
     * Adds a handler to be called on window load.<br>
     * If the window is already loaded, executes immediately.
     * @param {Function|string} handler - Handler function or string to eval.
     * @param {boolean} [evall=false] - If true, eval the handler.
     */
    static add_load_handler(handler, evall = false) {
        if (evall) {
            evall = eval(handler);
        }
        if (H_generics.is_string(handler)) {
            setTimeout(H_event.add_load_handler, 50, handler, true);
        } else {
            if (handler === undefined) {
                console.log('!!! LOAD HANDLER BAD REGISTRATION !!!');
            } else {

                if (H_event.window_on_load_handlers.indexOf(handler) < 0) {
                    if (H_event.window_loaded) {
                        handler();
                    } else {
                        H_event.window_on_load_handlers.push(handler);
                    }
                } else {
                    console.log('load already registered ', handler);
                }
            }
        }
    }
    /**
     * Removes a handler from the window load handlers list.<br>
     * @param {Function} handler - Handler to remove.
     */    
    static remove_load_handler(handler) {
        let pos = H_event.window_on_load_handlers.indexOf(handler);
        if (pos >= 0) {
            H_event.window_on_load_handlers.splice(pos, 1);
        } else {
            console.log('handler not found ', handler);
        }
    }
    
    /**
     * Executes all registered window load handlers.<br>
     * Sets window_loaded to true.
     * @param {Event} event - The load event.
     */
    static window_load(event) {
        for (let i = 0; i < H_event.window_on_load_handlers.length; i++) {
            let func = H_event.window_on_load_handlers[i];
            if (H_generics.is_function(func)) {
                func();
            } else {
                console.log('wrong onload handler registered ', func.name);
            }
        }
        H_event.window_loaded = true;
    }
    /**
     * Returns true if mouse is interacting (dragging, resizing, etc).<br>
     * @returns {boolean} True if interacting.
     */
    static mouse_interacting() {
        return H_event.dragging || H_event.drag_initialized || H_event.resize_data.resizing;
    }


    /**
     * Adds a handler to be called on window resize.<br>
     * @param {Function|string} handler - Handler function or string to eval.
     * @param {boolean} [evall=false] - If true, eval the handler.
     */
    static add_resize_handler(handler, evall = false) {
        if (evall) {
            handler = eval(handler);
        }
        if (H_generics.is_string(handler)) {
            setTimeout(H_event.add_resize_handler, 50, handler, true);
        } else {
            if (handler === undefined) {
                console.log('!!! RESIZE HANDLER BAD REGISTRATION !!!');
            } else {
                if (H_event.window_on_resize_handlers.indexOf(handler) < 0) {
                    H_event.window_on_resize_handlers.push(handler);
                } else {
                    console.log('handler already registered ', handler);
                }
            }
        }
    }
        
    /**
     * Removes a handler from the window resize handlers list.
     * 
     * @param {Function} handler - Handler to remove.
     */
    static remove_resize_handler(handler) {
        let pos = H_event.window_on_resize_handlers.indexOf(handler);
        if (pos >= 0) {
            H_event.window_on_resize_handlers.splice(pos, 1);
        } else {
            console.log('handler not found ', handler);
        }
    }

    /**
     * Executes all registered window resize handlers.<br>
     * Also marks the body rect as dirty.
     * @param {Event} event - The resize event.
     */
    static window_resize(event) {
        if (H_event.window_resize_timer) {
            clearTimeout(H_event.window_resize_timer);
        }
        H_event.window_resize_timer = setTimeout(H_dom.set_rect_dirty, 100, document.body);
        for (let i = 0; i < H_event.window_on_resize_handlers.length; i++) {
            let func = H_event.window_on_resize_handlers[i];
            if (H_generics.is_function(func)) {
                func();
            } else {
                console.log('wrong resize handler registered ', func);
            }
        }
    }
    
    /**
     * Handles global keydown events.<br>
     * Updates key state and applies EVENT_KEYDOWN.
     * @param {KeyboardEvent} event - The keydown event.
     */
    static global_key_down(event) {
        if (H_event.debug) {
            console.log('keydown', event);
        }

        H_event.keys.shiftKey = event.shiftKey;
        H_event.keys.ctrlKey = event.ctrlKey;
        H_event.keys.altKey = event.altKey;
        H_event.keys[event.key] = true;

        H_event.apply_event(H_event.EVENT_KEYDOWN, event);
    }

    /**
     * Handles global keyup events.<br>
     * Updates key state and applies EVENT_KEYUP.
     * @param {KeyboardEvent} event - The keyup event.
     */
    static global_key_up(event) {
        if (H_event.debug) {
            console.log('keyup', event);
        }

        H_event.keys.shiftKey = event.shiftKey;
        H_event.keys.ctrlKey = event.ctrlKey;
        H_event.keys.altKey = event.altKey;
        H_event.keys[event.key] = false;

        H_event.apply_event(H_event.EVENT_KEYUP, event);
    }

    /**
     * Handles global mousedown or pointerdown events.<br>
     * Detects mouse buttons, initializes drag/resize, and applies EVENT_MOUSEDOWN.
     * @param {MouseEvent|PointerEvent} event - The event.
     */
    static global_mouse_down(event) {

        // to prevent double activation for click event.
        let delay = event.timeStamp - H_event.previous_mouseup_time;
        if (delay < 30) {
            return;
        }

        if (event.pointerType == 'touch') {
            H_event.touch_handler_is_active = true;
            document.addEventListener("touchmove", H_event.disable_scroll_on_touch, { 'passive': false });
        }

        if (event.target.hasPointerCapture(event.pointerId)) {
            event.target.releasePointerCapture(event.pointerId);
        }

        H_event.detect_mouse_buttons(event);
        if (H_event.mouse.left) {
            if (H_event.current_mousedown_event) {
                H_event.previous_mousedown_event = H_event.current_mousedown_event;
            }

            H_event.init_mouse_down_event(event);

            if (H_event.has_drag(H_event.current_mousedown_event.target)) {
                H_event.stop_event(event);
            }

            H_event.apply_event(H_event.EVENT_MOUSEDOWN, event, H_event.previous_mousedown_event);

            // don't initiate resize or drag on touch, wait for a long press that will be detect by move
            if (event.pointerType != 'touch') {
                if (H_event.init_resize(H_event.current_mousedown_event)) {
                } else if (H_event.has_drag(H_event.current_mousedown_event.target)) {
                    // disable init drag on mousedown when touch, it will be trig by touchmove after a long press , see function touch_handler below
                    if (H_event.current_mousedown_event.button == 0) {
                        H_event.init_drag(H_event.current_mousedown_event.target);
                    }
                }
            }

        }
    }

    /**
     * Detects which mouse buttons are pressed.
     * Updates H_event.mouse state.
     * @param {MouseEvent|PointerEvent} event - The event.
     */
    static detect_mouse_buttons(event) {
        H_event.mouse.left = (event.buttons & 1) > 0 ? true : false;
        H_event.mouse.right = (event.buttons & 2) > 0 ? true : false;
        H_event.mouse.middle = (event.buttons & 4) > 0 ? true : false;
    }
    
    /**
     * Initializes the current mousedown event and computes offsets.
     * @param {MouseEvent|PointerEvent} event - The event.
     */
    static init_mouse_down_event(event) {

        H_event.current_mousedown_event = event;

        H_event.current_mousedown_event.realTime = new Date().getTime();

        // exemple to compute exact local position of the mouse pointer inside the clicked element if you search for it :)
        // let localPos = H_dom.get_global_to_local(event.target, event.pageX, event.pageY);
        // H_event.current_mousedown_event.local_x = localPos.x;
        // H_event.current_mousedown_event.local_y = localPos.y;

        // get the offset between the mouse position and the out bound of the clicked element
        let rect = H_dom.get_global_rect(event.target, true);
        H_event.current_mousedown_event.offset_left = rect.left - event.pageX;
        H_event.current_mousedown_event.offset_top = rect.top - event.pageY;
        H_event.current_mousedown_event.offset_right = rect.right - event.pageX;
        H_event.current_mousedown_event.offset_bottom = rect.bottom - event.pageY;
    }
    
    /**
     * Prevents default event behavior.
     * @param {Event} event - The event.
     * @returns {boolean} Always false to block callbacks...
     */
    static disable_event_callback(event) {
        event.preventDefault();
        return false;
    }

    /**
     * Handles global mouseup or pointerup events.<br>
     * Detects clicks end, double clicks end, and applies EVENT_MOUSEUP.
     * @param {MouseEvent|PointerEvent} event - The event.
     */
    static global_mouse_up(event) {

        // to block double event triggering
        let delay = event.timeStamp - H_event.previous_mouseup_time;
        if (delay < 30) {
            return;
        }

        if (H_event.debug) {
            console.log('global mouseup');
            console.log(event);
        }


        // no detect mouse for mouseup because it's already done by previous mousedown event
        // do not work with touch
        if (H_event.current_mousedown_event && H_event.mouse.left) {

            H_event.apply_event(H_event.EVENT_MOUSEUP, event);

            // same target
            if (H_dom.is_same_element(H_event.current_mousedown_event.target, event.target)) {

                // same position, within a circle of 3px
                let dif_x = event.pageX - H_event.current_mousedown_event.pageX;
                let dif_y = event.pageY - H_event.current_mousedown_event.pageY;
                if (dif_x < 3 && dif_x > -3 && dif_y < 3 && dif_y > -3) {

                    let delay = event.timeStamp - H_event.current_mousedown_event.timeStamp;

                    let prev_delay = 1000;

                    if (H_event.previous_mousedown_event) {
                        prev_delay = event.timeStamp - H_event.previous_mouseup_time;
                    }

                    if (H_event.debug) {
                        console.log(H_event.previous_mousedown_event);
                        console.log('prev_delay', prev_delay);
                    }

                    if (prev_delay > 30 && prev_delay < 500 && H_dom.is_same_element(H_event.previous_mousedown_event.target, event.target) && H_event.has_event(event.target, H_event.EVENT_DOUBLECLICK)) {
                    // if (prev_delay > 30 && prev_delay < 500 && H_dom.is_same_element(H_event.previous_mousedown_event.target, event.target) && H_event.disable_double_click.indexOf(event.target.id) == -1) {
                        clearTimeout(H_event.click_timer);

                        H_event.apply_event(H_event.EVENT_DOUBLECLICK, event);

                    } else if (delay < 300) {
                        clearTimeout(H_event.click_timer);
                        var event_a = event;
                        var event_b = H_event.previous_mousedown_event;

                        H_event.click_timer = setTimeout(function () { H_event.apply_event(H_event.EVENT_CLICK, event_a); }, 5);
                    } else {
                        if (H_event.debug) {
                            console.log('long click');
                        }
                    }
                } else {
                    // same target but not same position
                    if (H_event.debug) {
                        console.log('drag over the same element');
                    }
                }
            } else {
                H_event.apply_event(H_event.EVENT_MOUSEUP_OUTSIDE, event, H_event.previous_mousedown_event);
            }
        }

        H_event.end_global_move(event);

        H_event.previous_mousedown_event = H_event.current_mousedown_event;
        H_event.current_mousedown_event = null;
        H_event.previous_mouseup_time = event.timeStamp;
    }
      /**
     * Binds a callback to a class instance and caches it.<br>
     * Useful for event handlers to preserve 'this' context.
     * @param {Object} source_class_instance - The class instance.
     * @param {Function} callback - The callback function.
     * @returns {Function} The bound callback.
     */
    static bind_callback(source_class_instance, callback) {

        // create the internal list of bound callbacks for the specified class object
        if (!source_class_instance.__callbacks) {
            source_class_instance.__callbacks = {};
        }

        // there is now native way to clearly identify a function, so a unique identifier is assigned to it
        if (!callback.__uniqueID) {
            callback.__uniqueID = 'FUNC_' + H_generics.get_unique_id();
        }

        if (!source_class_instance.__callbacks[callback.__uniqueID]) {
            if (arguments.length == 2) {
                source_class_instance.__callbacks[callback.__uniqueID] = callback.bind(source_class_instance);
            } else {
                let args = [];
                for (let i = 2; i < args.length; i++) {
                    args.push('arguments[' + i + ']');
                }
                let command = 'source_class_instance.__callbacks[callback.__uniqueID] = callback.bind(source_class_instance,' + args.join(',') + ');';
                debugger;
                eval(command);
            }
        }

        return source_class_instance.__callbacks[callback.__uniqueID];
    }
   
    /**
     * Broadcasts an event to all listeners.<br>
     * Optionally restricts to listeners from a specific sender.
     * if from is not specified, all elements listening to an event with the name specified in event_name will be invoked!
     * @param {string} event_name - Name of the event.
     * @param {*} data - Data to send.
     * @param {Object} [from] - Sender object (optional).
     */
    static broadcast_event(event_name, data, from) {

        let id = '__all__';

        if (from) {
            if (!from._event_broadcaster_id) {
                from._event_broadcaster_id = H_generics.get_unique_id();
            }
            id = from._event_broadcaster_id;
        }

        if (H_event.events_listeners[event_name] === undefined) {
            H_event.events_listeners[event_name] = {};
        }

        if (H_event.events_listeners[event_name][id] !== undefined) {
            let lst = H_event.events_listeners[event_name][id];
            for (let i = 0; i < lst.length; i++) {
                H_event.send_event(lst[i], event_name, data);
            }
        }
    }

    /**
     * Registers a listener for an event.<br>
     * Optionally restricts to events from a specific sender.<br>
     * if from is not specified, all elements listening to an event with the name specified in event_name will be invoked.
     * @param {Object} to - Target object.
     * @param {string} event_name - Name of the event.
     * @param {Function} callback - Callback function.
     * @param {Object} [from] - Sender object (optional).
     */
    static listen_to_event(to, event_name, callback, from) {
        if (H_event.events_listeners[event_name] === undefined) {
            H_event.events_listeners[event_name] = {};
        }

        let id = '__all__';

        if (from) {
            if (!from._event_broadcaster_id) {
                from._event_broadcaster_id = H_generics.get_unique_id();
            }
            id = from._event_broadcaster_id;
        }

        if (H_event.events_listeners[event_name][id] === undefined) {
            H_event.events_listeners[event_name][id] = [];
        }

        if (H_event.events_listeners[event_name][id].indexOf(to) === -1) {
            H_event.events_listeners[event_name][id].push(to);
            H_event.add_event(to, event_name, callback);
        }
    }

    /**
     * Dispatches a DOM or custom event on an element.
     * @param {HTMLElement} dom_element - Target element.
     * @param {string} type - Event type.
     * @param {*} [data] - Event detail data.
     * @param {boolean} [bubbles=true] - Whether the event bubbles.
     * @param {boolean} [cancelable=true] - Whether the event is cancelable.
     */
    static send_event(dom_element, type, data, bubbles, cancelable) {

        if (bubbles === undefined) {
            bubbles = true;
        }

        if (cancelable === undefined) {
            cancelable = true;
        }

        bubbles = bubbles ? true : false;
        cancelable = cancelable ? true : false;

        var event = false;
        switch (type) {
            // mouse events
            case H_event.EVENT_MOUSEDOWN:
            case H_event.EVENT_MOUSEUP:
            case H_event.EVENT_CLICK:
            case H_event.EVENT_DOUBLECLICK:
                event = new MouseEvent(type, { detail: data, bubbles: bubbles, cancelable: true });
                break;

            // others
            default:
                event = new CustomEvent(type, { detail: data, bubbles: bubbles, cancelable: true });
                break;
        }
        if (event && dom_element != null) dom_element.dispatchEvent(event);
        else console.log('can\'t dispatch event', event);
    }

    /**
     * Adds an event listener to a DOM element.<br>
     * Handles duplicate prevention and delayed registration.
     * @param {HTMLElement} dom_element - Target element.
     * @param {string} event_name - Event type.
     * @param {Function|string} callback - Callback function or string to eval.
     * @param {boolean} [use_capture] - Use capture phase.
     * @param {boolean} [evall=false] - If true, eval the callback.
     * @returns {boolean|undefined} True if added, false if duplicate.
     */
    static add_event(dom_element, event_name, callback, use_capture, evall = false) {

        if (evall) {
            callback = eval(callback);
        }
        if (H_generics.is_string(callback)) {
            setTimeout(H_event.add_event, 50, dom_element, event_name, callback, use_capture, true);
        } else {
            if (callback === undefined) {
                console.log('!!! ADD EVENT BAD CALLBACK !!!');
            } else {
                if (!dom_element._event_listeners) {
                    dom_element._event_listeners = [];
                }

                let found_event = false;
                let found_callback = false;
                for (let i = 0; i < dom_element._event_listeners.length; i++) {
                    if (dom_element._event_listeners[i].event === event_name) {
                        found_event = true;
                        if (dom_element._event_listeners[i].callback === callback) {
                            found_callback = true;
                            break;
                        }
                    }
                }
                //no listener, should add it
                if (!found_event) {
                    use_capture = use_capture ? true : false;
                    dom_element.addEventListener(event_name, H_event.handler_event, use_capture);
                }
                //and push the callback !
                if (!found_callback) {
                    dom_element._event_listeners.push({ 'event': event_name, 'callback': callback });
                    if (H_constants.sids) {
                        if (dom_element.dataset) {
                            let callback_name = callback.name != '' ? callback.name : callback;
                            if (!dom_element.dataset.debug) {
                                dom_element.dataset.debug = 'event:' + event_name + ' - callback:' + callback_name + ' |';
                            } else {
                                dom_element.dataset.debug += 'event:' + event_name + ' - callback:' + callback_name + ' |';
                            }
                        }
                    }
                    switch (event_name) {
                        case H_event.EVENT_MOUSEUP_OUTSIDE:
                        case H_event.EVENT_MOUSEDOWN_OUTSIDE:
                            if (!H_event.outside_events[event_name]) {
                                H_event.outside_events[event_name] = [];
                            }

                            if (H_event.outside_events[event_name].indexOf(dom_element) == -1) {
                                H_event.outside_events[event_name].push(dom_element);
                            }
                            break;
                    }

                    return true;
                }
                return false;
            }
        }


    }

    /**
     * Internal event handler that applies the event to registered callbacks.
     * @param {Event} event - The event.
     */
    static handler_event(event) {
        H_event.apply_event(event.type, event);
    }
    /**
     * Adds an event listener with a delay.<br>
     * Why a delay ? to avoid accident trigerring, if we add event on same dom_element that trigger the add_event.
     * @param {HTMLElement} dom_element - Target element.
     * @param {string} event_name - Event type.
     * @param {Function} callback - Callback function.
     * @param {boolean} use_capture - Use capture phase.
     * @param {number} delay - Delay in ms.
     */
    static add_event_delayed(dom_element, event_name, callback, use_capture, delay) {
        if (!delay) {
            delay = 3;
        }
        setTimeout(H_event.add_event, delay, dom_element, event_name, callback, use_capture);
    }
    
    /**
     * Applies an event to all registered handlers for the event name.<br>
     * Handles outside events, bubbling, and nested listeners.
     * @param {string} event_name - Event type.
     * @param {Event} event - The event.
     * @param {Event} [other_event] - Optional related event.
     */
    static apply_event(event_name, event, other_event) {
        // console.log('Apply event ', event);
        switch (event_name) {
            case H_event.EVENT_MOUSEUP_OUTSIDE:
            case H_event.EVENT_MOUSEDOWN_OUTSIDE:

                // every click event send a custom click outside event
                // will check for each dom_element with click outside event applied if the click is not inside and call the 
                // callback
                if (H_generics.is_filled_array(H_event.outside_events[event_name])) {
                    let is_inside = false;
                    let lst = H_event.outside_events[event_name];
                    for (let i = 0; i < lst.length; i++) {
                        let dom_element = lst[i];
                        is_inside = false;

                        if (H_dom.point_inside_element(event.pageX, event.pageY, dom_element)) {
                            is_inside = true;
                            continue;
                        }

                        // dom_element can have a list of other element that when clicked inside don't want the callback to be called
                        // those elements are like extension of the target
                        if (dom_element.other_inside_elements) {
                            if (!H_generics.is_array(dom_element.other_inside_elements)) dom_element.other_inside_elements = [dom_element.other_inside_elements];
                            for (let j = 0; j < dom_element.other_inside_elements.length; j++) {
                                let dom_elem = dom_element.other_inside_elements[j];
                                if (H_dom.point_inside_element(event.pageX, event.pageY, dom_elem)) {
                                    is_inside = true;
                                    break;
                                }
                            }
                            if (is_inside) continue;
                        }

                        // at this point we are sure that pointer is not inside 
                        for (let i = 0; i < dom_element._event_listeners.length; i++) {
                            if (dom_element._event_listeners[i].event === event_name) {
                                dom_element._event_listeners[i].callback(event, other_event);

                                if (H_event.debug) {
                                    console.log('Apply ', event_name, ' to ', dom_element, 'callback', document._event_listeners[i]);
                                }
                            }
                        }
                    }

                    if (is_inside) return false;
                }
                break;

            case H_event.EVENT_MOUSEDOWN:
                H_event.apply_event(H_event.EVENT_MOUSEDOWN_OUTSIDE, event, other_event);
                break;

            case H_event.EVENT_MOUSEUP:
                H_event.apply_event(H_event.EVENT_MOUSEUP_OUTSIDE, event, other_event);
                break;

            case 'contextmenu':
                H_event.apply_event(H_event.EVENT_MOUSEDOWN_OUTSIDE, event, other_event);
                H_event.apply_event(H_event.EVENT_MOUSEUP_OUTSIDE, event, other_event);
                break;
        }

        var class_object = H_dom.get_class_object(event.target);

        H_event.lock_events_removing = true;
        if (H_generics.is_filled_array(class_object)) {
            for (var i = 0; i < class_object.length; i++) {
                if (class_object[i][event_name]) {
                    class_object[i][event_name](event, other_event);

                    if (H_event.debug) {
                        console.log(event_name + ' ' + event.target.id);
                    }
                }
            }
        } else if (class_object && class_object[event_name]) {
            class_object[event_name](event, other_event);

            if (H_event.debug) {
                console.log(event_name + ' ' + event.target.id);
            }
        }

        let dom_element = event.target;

        let executed = false;

        if (H_generics.is_filled_array(dom_element._event_listeners)) {
            for (let i = 0; i < dom_element._event_listeners.length; i++) {
                if (dom_element._event_listeners[i].event === event_name) {

                    dom_element._event_listeners[i].callback(event, other_event);

                    executed = true;

                    if (H_event.debug) {
                        console.log('Apply ', event_name, ' to ', dom_element, 'callback', document._event_listeners[i]);
                    }
                }
            }
        }

        if (event_name == H_event.EVENT_KEYUP || event_name == H_event.EVENT_KEYDOWN) {
            // in case of keyUp or keyDown, much event are on document but the target depends of the last clicked item.
            // this may be the best way to trig event on document when there is no event on the targeted item 
            // this way, when pressing a key in an input the event from document will not be trigged 
            if (H_generics.is_filled_array(document._event_listeners)) {
                for (let i = 0; i < document._event_listeners.length; i++) {
                    if (document._event_listeners[i].event === event_name) {

                        document._event_listeners[i].callback(event, other_event);

                        executed = true;

                        if (H_event.debug) {
                            console.log('Apply ', event_name, ' to ', document, 'callback', document._event_listeners[i]);
                        }
                    }
                }
            }
        }

        if (event.cancelBubble == false) {
            if (dom_element.children) {
                for (let j = 0; j < dom_element.children.length; j++) {
                    let child = dom_element.children[j];
                    if (H_dom.point_inside_element(event.pageX, event.pageY, child)) {

                        let pointer_events_none = getComputedStyle(child)['pointer-events'] == 'none';

                        if (child._event_listeners && (!pointer_events_none || (child.dataset && child.dataset.disabled == 'true'))) {
                            for (let k = 0; k < child._event_listeners.length; k++) {
                                if (child._event_listeners[k].event === event_name) {

                                    child._event_listeners[k].callback(event, other_event);

                                    if (H_event.debug) {
                                        console.log('should Apply ', event_name, ' to ', child, 'callback', child._event_listeners[k]);
                                    }

                                    executed = true;
                                }
                            }
                        }

                    }
                }
            } else {
            }
        }


        H_event.lock_events_removing = false;

        if (H_generics.is_filled_array(H_event.events_to_remove)) {
            for (let c = 0; c < H_event.events_to_remove.length; c++) {
                let infos = H_event.events_to_remove[c];

                if (infos.length == 1) {
                    H_event.remove_all_events(infos[0]);

                } else if (infos.length == 3) {
                    H_event.remove_event(infos[0], infos[1], infos[2]);

                } else {
                    debugger;
                }
            }
            H_event.events_to_remove.length = 0;
        }
    }
    
    /**
     * Checks if a DOM element has a listener for a specific event.
     * @param {HTMLElement} dom_element - The element.
     * @param {string} event_name - Event type.
     * @returns {boolean} True if found.
     */
    static has_event(dom_element, event_name) {
        if (!H_generics.is_filled_array(dom_element._event_listeners)) {
            return false;
        }

        for (let i = 0; i < dom_element._event_listeners.length; i++) {
            if (dom_element._event_listeners[i].event === event_name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a DOM element has a specific callback for an event.
     * @param {HTMLElement} dom_element - The element.
     * @param {string} event_name - Event type.
     * @param {Function} callback - Callback function.
     * @returns {boolean} True if found.
     */
    static has_event_callback(dom_element, event_name, callback) {

        if (!H_generics.is_filled_array(dom_element._event_listeners)) {
            return false;
        }

        for (let i = 0; i < dom_element._event_listeners.length; i++) {
            if (dom_element._event_listeners[i].event === event_name && dom_element._event_listeners[i].callback === callback) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes a specific event listener from a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {string} event_name - Event type.
     * @param {Function} callback - Callback function.
     * @returns {boolean} True if removed.
     */
    static remove_event(dom_element, event_name, callback) {

        if (!H_generics.is_filled_array(dom_element._event_listeners)) {
            return false;
        }

        if (H_event.lock_events_removing) {
            H_event.events_to_remove.push([dom_element, event_name, callback]);
            return false;
        }

        for (let i = 0, len = dom_element._event_listeners.length; i < len; i++) {
            let e = dom_element._event_listeners[i];
            if ((e.event === event_name && e.callback.name === callback.name) || (e.event == event_name && !callback)) {
                if (dom_element.removeEventListener) {
                    dom_element.removeEventListener(e.event, e.callback);

                } else if (dom_element.attach_event) {
                    dom_element.detach_event('on' + e.event, e.callback);
                }

                dom_element._event_listeners.splice(i, 1);

                if (H_event.outside_events) {
                    switch (event_name) {
                        case H_event.EVENT_MOUSEUP_OUTSIDE:
                        case H_event.EVENT_MOUSEDOWN_OUTSIDE:
                            if (H_generics.is_filled_array(H_event.outside_events[event_name])) {
                                let pos = H_event.outside_events[event_name].indexOf(dom_element);
                                if (pos > -1) {
                                    H_event.outside_events[event_name].splice(pos, 1);
                                }
                            }
                            break;
                    }
                }

                len--;
                i--;
            }
        }
    }

    /**
     * Removes all event listeners from a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @returns {boolean} True if removed.
     */
    static remove_all_events(dom_element) {
        if (!H_generics.is_filled_array(dom_element._event_listeners)) {
            return false;
        }

        if (H_event.lock_events_removing) {
            H_event.events_to_remove.push([dom_element]);
            return false;
        }

        for (let i = 0, len = dom_element._event_listeners.length; i < len; i++) {
            let e = dom_element._event_listeners[i];
            if (dom_element.removeEventListener) {
                dom_element.removeEventListener(e.event, e.callback);

            } else if (elem.attach_event) {
                dom_element.detach_event("on" + e.event, e.callback);
            }
        }

        dom_element._event_listeners.length = 0;

        if (H_event.outside_events) {
            let keys = Object.keys(H_event.outside_events);
            for (let i = 0; i < keys.length; i++) {
                let pos = H_event.outside_events[keys[i]].indexOf(dom_element);
                if (pos > -1) {
                    H_event.outside_events[keys[i]].splice(pos, 1);
                }
            }
        }
    }
    
    /**
     * Starts tracking global mouse/touch movement for drag/resize.
     * @param {HTMLElement} dom_element - Target element.
     * @param {Function} move_handler - Move handler.
     * @param {Function} end_move_handler - End handler.
     */
    static start_global_move(dom_element, move_handler, end_move_handler) {

        let lst = H_event.move_list;
        for (let i = 0; i < lst.length; i++) {
            if (lst[i].dom_element === dom_element && lst[i].move_handler === move_handler && H_event.move_list[i].move_handler) {
                return false;
            }
        }

        H_event.move_list.push({ 'dom_element': dom_element, 'move_handler': move_handler, 'end_move_handler': end_move_handler });
        if (H_event.debug) {
            console.log("start move");
        }
    }

    /**
     * Handles global mouse or pointer move events.<br>
     * Updates drag/resize state and triggers mouse enter/leave.
     * @param {MouseEvent|PointerEvent} event - The event.
     */
    static global_mouse_move(event) {
        // on touch, the move is initialised after a long press, do nothing if the move is too little
        // Apparently there is no need to do the test for the little move.
        if (event.pointerType == 'touch') {
            if (H_event.current_mousedown_event === null) {
                return false;
            }
            let dif_x = Math.abs(H_event.current_mousedown_event.screenX - event.screenX);
            let dif_y = Math.abs(H_event.current_mousedown_event.screenY - event.screenY);
            if (dif_x < 5 && dif_y < 5) {
                return false;
            }

            if (H_event.long_press == -1) {
                let now = new Date().getTime();
                let dif_time = now - H_event.current_mousedown_event.realTime;
                if (dif_time < 500) {
                    H_event.long_press = 0;

                    if (H_event.current_mousedown_event.pointerType == 'touch') {
                        H_event.touch_handler_is_active = false;
                        document.removeEventListener("touchmove", H_event.disable_scroll_on_touch);
                    }

                } else {
                    H_event.long_press = 1;

                    if (H_event.init_resize(H_event.current_mousedown_event)) {
                    } else if (H_event.has_drag(H_event.current_mousedown_event.target)) {
                        // disable init drag on mousedown when touch, it will be trig by touchmove after a long press , see function touch_handler below
                        if (H_event.current_mousedown_event.button == 0) {
                            H_event.init_drag(H_event.current_mousedown_event.target);
                        }
                    }
                }
            }

            if (H_event.long_press == 0) {
                return false;
            }
        }

        if (H_generics.is_filled_array(H_event.move_list)) {
            for (let i = 0; i < H_event.move_list.length; i++) {
                if (H_event.move_list[i].move_handler) {
                    H_event.move_list[i].move_handler(event, H_event.move_list[i].dom_element);
                }
            }
        }

        let prev_element = H_event.current_mouse_over_element;

        H_event.current_mouse_over_element = event.target;

        if (prev_element != H_event.current_mouse_over_element) {
            if (prev_element) {
                H_event.apply_event(H_event.EVENT_MOUSELEAVE, event, prev_element);
            }
            H_event.apply_event(H_event.EVENT_MOUSEENTER, event);
        }

        if (!H_event.dragging && !H_event.resize_data.resizing) {

            if (H_event.is_resizable(H_event.current_mouse_over_element)) {
                let do_resize = H_event.check_resize(event);

                if (do_resize) {

                    H_dom.set_global_cursor(H_event.resize_data.cursor);

                    H_event._over_resizable = true;

                } else if (H_event._over_resizable) {

                    H_event._over_resizable = false;
                    H_dom.unset_global_cursor();
                }

            } else if (H_event._over_resizable) {

                H_event._over_resizable = false;

                H_dom.unset_global_cursor();

            } else {
                H_event._over_resizable = false;
            }
        }

        H_event.parsing_mouse_move = false;

    }

    /**
     * Ends global move tracking for drag/resize.<br>
     * @param {Event} event - The event.
     */
    static end_global_move(event) {

        if (H_generics.is_filled_array(H_event.move_list)) {
            for (let i = 0; i < H_event.move_list.length; i++) {
                if (H_event.move_list[i].end_move_handler) {
                    H_event.move_list[i].end_move_handler(event, H_event.move_list[i].dom_element);
                }
            }
            H_event.move_list.length = 0;
        }
    }
    /**
     * Stops event propagation and default behavior.
     * @param {Event} event - The event.
     * @returns {boolean} Always false.
     */
    static stop_event(event) {
        if (event.stopPropagation) {
            event.stopPropagation();
        }

        if (event.preventDefault && event.cancelable) {
            event.preventDefault();
        }
        return false;
    }

    static stop_propagation(event) {
        if (event.stopPropagation) {
            event.stopPropagation();
        }
    }
    /**
     * Prevents default event behavior only.
     * @param {Event} event - The event.
     */
    static prevent_default(event) {
        if (event.preventDefault) {
            event.preventDefault();
        } 
        return false;
    }
    /**
     * Enables resizing for a DOM element.<br>
     * By default, the border detection size is 10 px, and you can use the right and bottom border to resize.<br>
     * It's better practice to display this border, and set the overflow style property of the dom element to hidden<br>
     * Sets up handlers and border detection.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} start_resize_handler - Start handler.
     * @param {Function} resize_handler - Resize handler.
     * @param {Function} end_resize_handler - End handler.
     * @param {number} [border_detection_size=10] - Border size in px.
     */
    static enable_resize(dom_element, start_resize_handler = null, resize_handler = null, end_resize_handler = null, border_detection_size = null) {

        if (!border_detection_size) {
            border_detection_size = 10;
        }

        dom_element.setAttribute('data-resizeable', true);
        dom_element.setAttribute('data-resizeborder', border_detection_size);
        dom_element.draggable = false;

        dom_element._start_resize_handler = start_resize_handler;
        dom_element._resize_handler = resize_handler;
        dom_element._end_resize_handler = end_resize_handler;
    }

    /**
     * Disables resizing for a DOM element.<br>
     * @param {HTMLElement} dom_element - The element.
     */
    static disable_resize(dom_element) {
        dom_element.removeAttribute('data-resizeable');
        dom_element.draggable = false;
    }

    /**
     * Checks if a DOM element is resizable.
     * @param {HTMLElement} dom_element - The element.
     * @returns {boolean} True if resizable.
     */
    static is_resizable(dom_element) {
        return dom_element && dom_element.getAttribute ? dom_element.getAttribute('data-resizeable') : false;
    }

    /**
     * Initializes resize operation.<br>
     * launched by global_mouse_down
     * @param {Event} mouse_down_event - The mousedown event.
     * @returns {boolean} True if resize started.
     */
    static init_resize(mouse_down_event) {
        
        if (!H_event.is_resizable(mouse_down_event.target)) {
            return false;
        }

        let do_resize = H_event.check_resize(mouse_down_event);

        if (do_resize) {
            // update current rect
            H_dom.get_global_rect(mouse_down_event.target);

            H_event.resize_data.resizing = true;

            // activate drag
            H_event.start_global_move(mouse_down_event.target, H_event.resize, H_event.end_resize);

            if (mouse_down_event.target._start_resize_handler) {
                mouse_down_event.target._start_resize_handler();
            }
        }

        return do_resize;
    }

    /**
     * Checks if the mouse is near a resizable border.
     * Updates resize_data and cursor.
     * @param {Event} event - The event.
     * @returns {boolean} True if resize should start.
     */
    static check_resize(event) {
        if (!H_event.is_resizable(event.target)) {
            return false;
        }

        let dom_element = event.target;

        let b = parseInt(dom_element.getAttribute('data-resizeborder'));

        let rect = H_dom.get_global_rect(event.target);

        let offset_right = rect.right - event.pageX;
        let offset_bottom = rect.bottom - event.pageY;

        if (b * 2 >= rect.height || b * 2 >= rect.width) {
            b = Math.max(5, b / 2);
        }
        console.log(b);

        let do_resize = false;

        let css_cursor = '';
        let style = H_dom.get_style(dom_element);
        
        H_event.resize_data.left = false;
        H_event.resize_data.top = false;
        H_event.resize_data.right = false;
        H_event.resize_data.bottom = false;
        H_event.resize_data.dom_element = dom_element;

        if (Math.abs(offset_bottom) < b) {
            H_event.resize_data.bottom = true;
            do_resize = true;

            css_cursor += 's';
        }
        if (Math.abs(offset_right) < b ) {
            H_event.resize_data.right = true;
            do_resize = true;

            css_cursor += 'e';

        }
        // if you want to try to resize from top and left, 
        // comment the 2 previous 'if' and uncomment what is bellow :
        
        //let offset_left = rect.left - event.pageX;
        //let offset_top = rect.top - event.pageY;
        // let left_resize = true;
        // let right_resize = true;
        // if (is_relative && style._align == '') left_resize = false;
        // if (is_relative && style._align == 'right') right_resize = false;
        // if (Math.abs(offset_top) < b ) { 
        //     H_event.resize_data.top = true;
        //     do_resize = true;
        //     css_cursor += 'n';

        // } else if (Math.abs(offset_bottom) < b ) { s
        //     H_event.resize_data.bottom = true;
        //     do_resize = true;

        //     css_cursor += 's';
        // }
        // if (Math.abs(offset_right) < b && !auto_width && right_resize) {
        //     H_event.resize_data.right = true;
        //     do_resize = true;

        //     css_cursor += 'e';

        // } else if (Math.abs(offset_left) < b && !auto_width && left_resize) {
        //     H_event.resize_data.left = true;
        //     do_resize = true;

        //     css_cursor += 'w';
        // }

        if (do_resize) {
            css_cursor += '-resize';
        }

        H_event.resize_data.cursor = css_cursor;
        return do_resize;
    }

    /**
     * Ends resize operation.
     * @param {Event} event - The event.
     */
    static end_resize(event) {
        H_event.resize_data.resizing = false;

        if (event.target._end_resize_handler) {
            event.target._end_resize_handler();
        }
    }

    /**
     * Handles resizing of a DOM element during mouse move.<br>
     * @param {Event} event - The event.
     */
    static resize(event) {
        let dom_element = H_event.current_mousedown_event.target;
        let area_rect = null;

        if (dom_element._drag_area) {
            area_rect = H_dom.get_global_rect(dom_element._drag_area);
        }

        if (H_event.resize_data.left) {
            if (area_rect) {
                H_dom.set_global_left(dom_element, Math.max(area_rect.left, event.pageX + H_event.current_mousedown_event.offset_left));

            } else {
                H_dom.set_global_left(dom_element, event.pageX + H_event.current_mousedown_event.offset_left);
            }

        } else if (H_event.resize_data.right) {
            if (area_rect) {
                H_dom.set_global_right(dom_element, Math.min(area_rect.right, event.pageX + H_event.current_mousedown_event.offset_right));
            } else {
                H_dom.set_global_right(dom_element, event.pageX + H_event.current_mousedown_event.offset_right);
            }
        }

        if (H_event.resize_data.top) {
            if (area_rect) {
                H_dom.set_global_top(dom_element, Math.max(area_rect.top, event.pageY + H_event.current_mousedown_event.offset_top));
            } else {
                H_dom.set_global_top(dom_element, event.pageY + H_event.current_mousedown_event.offset_top);
            }

        } else if (H_event.resize_data.bottom) {
            if (area_rect) {
                H_dom.set_global_bottom(dom_element, Math.min(area_rect.bottom, event.pageY + H_event.current_mousedown_event.offset_bottom));
            } else {
                H_dom.set_global_bottom(dom_element, event.pageY + H_event.current_mousedown_event.offset_bottom);
            }
        }

        if (dom_element._resize_handler) {
            dom_element._resize_handler();
        }
    }

    /**
     * Enables dragging for a DOM element.<br>
     * Sets up handlers and optional avatar to represent the moving element.<br>
     * all handlers are callbacks to call at each moment of the drag& drop : start, move, end, and can be replaced by null.<br>
     * the end_handler can used to replace the drop event on the target.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function|Object} start_handler - Start handler or settings object.
     * @param {Function} move_handler - Move handler.
     * @param {Function} end_handler - End handler.
     * @param {Function|string} avatar_handler - Avatar handler or string.
     * @returns {boolean} True if enabled.
     */
    static enable_drag(dom_element, start_handler, move_handler, end_handler, avatar_handler) {

        if (H_event.has_drag(dom_element)) {
            if (H_event.debug) console.log('Drag already assigned to ', dom_element);
            return false;
        }
        dom_element.setAttribute('data-draggable', true);
        dom_element.draggable = false;

        dom_element._drag_start = undefined;
        dom_element._drag_move = undefined;
        dom_element.drag_end = undefined;

        if (start_handler && !H_generics.is_function(start_handler) && H_generics.is_object(start_handler)) {
            let settings = start_handler;

            dom_element._drag_start = settings.start_handler;
            dom_element._drag_move = settings.move_handler;
            dom_element.drag_end = settings.end_handler;
            dom_element._use_avatar = settings.avatar;

        } else {
            dom_element._drag_start = start_handler;
            dom_element._drag_move = move_handler;
            dom_element.drag_end = end_handler;
            dom_element._use_avatar = avatar_handler;
        }

        return true;
    }
    /**
     * Checks if a DOM element has drag enabled.
     * @param {HTMLElement} dom_element - The element.
     * @returns {boolean} True if draggable.
     */
    static has_drag(dom_element) {
        if (dom_element === document) {
            dom_element = document.body;
        }
        return dom_element.getAttribute('data-draggable');
    }
    /**
     * Disables dragging for a DOM element.
     * @param {HTMLElement} dom_element - The element.
     */
    static disable_drag(dom_element) {
        dom_element.removeAttribute('data-draggable');
        dom_element.draggable = false;
    }
    /**
     * Sets a drag area dom element for a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {HTMLElement} area_dom_element - Area element.
     * @param {number} [padding=0] - Padding in px.
     */
    static set_drag_area(dom_element, area_dom_element, padding) {
        dom_element._drag_area = area_dom_element;
        dom_element._drag_area_padding = (padding === undefined) ? 0 : parseInt(padding);

        if (dom_element._overlay) {
            dom_element._overlay.dom_element._drag_area = area_dom_element;
        }
    }
    /**
     * Unsets the drag area for a DOM element.
     * @param {HTMLElement} dom_element - The element.
     */
    static unset_drag_area(dom_element) {
        dom_element._drag_area = null;
        if (dom_element._overlay) {
            dom_element._overlay.dom_element._drag_area = null;
        }
    }
    /**
     * Initializes drag operation for a DOM element.<br>
     * launched by global_mouse_down 
     * @param {HTMLElement} dom_element - The element.
     */
    static init_drag(dom_element) {
        H_event.drag_initialized = true;

        // update current rect
        let rect = H_dom.get_global_rect(dom_element, true);
        H_dom.get_global_rect(dom_element._drag_area, true);

        dom_element._drag_started = false;

        // activate drag
        H_event.start_global_move(dom_element, H_event.drag, H_event.drag_end);

        H_dom.set_global_cursor('move');

        if (!dom_element._sourceRect) {
            dom_element._sourceRect = {};
        }
        H_generics.copy_object(rect, dom_element._sourceRect);

        dom_element._sourceParent = dom_element.parentNode;

        if (!H_event._current_drag_element) {
            H_event._current_drag_element = [];
        }
        H_event._current_drag_element.push(dom_element);
    }
    /**
     * Handles dragging of a DOM element during mouse move.
     * @param {Event} event - The event.
     * @param {boolean} b - Unused.
     */
    static drag(event, b) {

        H_event.stop_event(event);

        let dif_x = Math.abs(event.pageX - H_event.current_mousedown_event.pageX);
        let dif_y = Math.abs(event.pageY - H_event.current_mousedown_event.pageY);

        if (dif_x < 3 && dif_y < 3) {
            return;
        }

        let dom_element = H_event.current_mousedown_event.target;

        if (!dom_element._drag_started) {

            // add avatar to the dom
            if (H_generics.is_string(dom_element._use_avatar)) {

                dom_element._avatar = H_dom.string_to_dom(dom_element._avatar);
                document.body.appendChild(dom_element._avatar);

            } else if (H_generics.is_function(dom_element._use_avatar)) {

                let tmp = dom_element._use_avatar(dom_element, event);

                if (H_generics.is_string(tmp)) {
                    tmp = H_dom.string_to_dom(tmp);
                }

                dom_element._avatar = tmp;
                document.body.appendChild(dom_element._avatar);

            } else {
                dom_element._avatar = dom_element;
            }

            // position avatar
            if (dom_element._avatar !== dom_element) {
                let style = H_dom.get_style(dom_element._avatar);
                dom_element._avatar._originalPositionStyle = style.POS;

                H_dom.set_style_value(dom_element._avatar, 'position', 'fixed');

                let pos = H_dom.get_global_position(dom_element);
                H_dom.set_global_position(dom_element._avatar, pos.x, pos.y);
            }


            H_dom.disable_mouse_events(dom_element._avatar);
            H_dom.move_to_front(dom_element._avatar);

            // activate custom functions
            //H_event.start_global_move(dom_element , dom_element._drag_move , dom_element.drag_end);
        }

        H_event.dragging = true;
        let avatar = dom_element._avatar || dom_element;

        if (dom_element._drag_area) {
            let rect = H_dom.get_global_rect(avatar);
            let area_rect = H_dom.get_global_rect(dom_element._drag_area);

            let x = event.pageX + H_event.current_mousedown_event.offset_left;
            let y = event.pageY + H_event.current_mousedown_event.offset_top;
            let w = H_dom.get_global_width(avatar);
            let h = H_dom.get_global_height(avatar);
            let right = x + w;
            let bottom = y + h;

            let padding = dom_element._drag_area_padding;

            if (x < area_rect.left - padding) {
                x = area_rect.left - padding;
            }
            if (y < area_rect.top - padding) {
                y = area_rect.top - padding;
            }
            if (right > area_rect.right + padding) {
                x = (area_rect.right + padding) - w;
            }
            if (bottom > area_rect.bottom + padding) {
                y = (area_rect.bottom + padding) - h;
            }

            H_dom.set_global_position(avatar, x, y);

        } else {
            let px = event.clientX + H_event.current_mousedown_event.offset_left;
            let py = event.clientY + H_event.current_mousedown_event.offset_top;
            // console.log(event, 'px', px, 'py', py);
            H_dom.set_global_position(avatar, px, py);
        }

        if (!dom_element._drag_started && dom_element._drag_start) {
            dom_element._drag_start(event, dom_element);
        }

        dom_element._drag_started = true;

        if (dom_element._drag_move) dom_element._drag_move(event, dom_element);
    }

    /**
     * Ends drag operation.
     * @param {Event} event - The event.
     */
    static drag_end(event) {
        H_event.dragging = false;
        H_event.drag_initialized = false;

        H_dom.unset_global_cursor();

        let dom_element = H_event.current_mousedown_event.target;

        if (dom_element._drag_started) {

            // event.detail.target used for touch
            let drop_on_element = event.target || event.detail.target;
            if (drop_on_element.tagName == 'HTML' || drop_on_element == document) {
                drop_on_element = document.body;
            }

            if (H_event.has_drop(drop_on_element)) {
                H_event.send_event(drop_on_element, 'drop', { 'dropEvent': event, 'elements': H_event._current_drag_element.slice() });
            }

            if (dom_element.drag_end) dom_element.drag_end(event, dom_element);
        }

        if (dom_element._avatar && dom_element._avatar !== dom_element) H_dom.remove_element(dom_element._avatar);
        else if (dom_element._avatar) H_dom.set_style_value(dom_element._avatar, 'position', dom_element._avatar._originalPositionStyle);
        else if (dom_element._originalPositionStyle !== undefined) H_dom.set_style_value(dom_element, 'position', dom_element._originalPositionStyle);

        for (let i = 0; i < H_event._current_drag_element.length; i++) {
            H_dom.restore_mouse_events(H_event._current_drag_element[i]);
            H_dom.restore_zindex(H_event._current_drag_element[i]);
        }
        H_event._current_drag_element.length = 0;

        H_event.current_mouse_over_element = document.elementFromPoint(event.pageX, event.pageY);
    }

    /**
     * Prevents scrolling on touch devices during drag/resize.<br>
     * actually similar to H_event.prevent_default(), but it was not the case in the past.<br>
     * so perhaps it will change again in the future...<br>
     * so keeped for compatibility reason.
     * @param {TouchEvent} event - The event.
     * @returns {boolean} Always false.
     */
    static disable_scroll_on_touch(event) {
        if (event.preventDefault) {
            event.preventDefault();
        } 
        return false;
    }
    /**
     * Sets the default drop callback for drag-and-drop.
     * @param {Function} callback - Callback function.
     */
    static set_default_drop_callback(callback) {
        H_event.default_drop_callback = callback;
    }
    /**
     * Handles global drop events.<br>
     * Calls the default drop callback if set.
     * @param {DragEvent|CustomEvent} event - The event.
     */
    static global_drop(event) {
        H_event.stop_event(event);

        if (H_generics.is_function(H_event.default_drop_callback)) {

            H_event.default_drop_callback(event);
        }

    }

    /**
     * Enables drop events on a DOM element.
     * @param {HTMLElement|Document} dom_element - The element or document.
     * @param {Function} callback - Drop callback.
     * @returns {boolean} True if enabled.
     */
    static enable_drop(dom_element, callback) {

        if (dom_element === document) {
            if (!document.body) {
                // body not loaded yet, try again later;
                setTimeout(H_event.enable_drop, 100, dom_element, callback);
                return false;
            }

            H_event.add_event(dom_element, 'dragover', H_event.disable_event_callback, false);
            H_event.add_event(dom_element, 'drop', callback, false);
            dom_element = document.body;
        }

        if (H_event.has_drop(dom_element)) {
            console.log('Drop event already assigned to ', dom_element);
            return false;
        }
        if (dom_element) {
            dom_element.setAttribute('data-drop', true);

            H_event.add_event(dom_element, 'dragover', H_event.disable_event_callback, false);
            H_event.add_event(dom_element, 'drop', callback, false);
        }

        return true;
    }

    /**
     * Disables drop events on a DOM element.
     * @param {HTMLElement|Document} dom_element - The element or document.
     */
    static disable_drop(dom_element) {
        if (dom_element === document) {
            H_event.remove_event(dom_element, 'dragover', H_event.disable_event_callback);
            H_event.remove_event(dom_element, 'drop');
            dom_element = document.body;
        }

        dom_element.setAttribute('data-drop', false);

        H_event.remove_event(dom_element, 'dragover', H_event.disable_event_callback);
        H_event.remove_event(dom_element, 'drop');
    }

    /**
     * Checks if a DOM element has drop enabled.
     * @param {HTMLElement|Document} dom_element - The element or document.
     * @returns {boolean|number} True/1 if enabled, 0 if not.
     */
    static has_drop(dom_element) {
        if (dom_element === document) {
            dom_element = document.body;
        }
        if (!dom_element) debugger;
        let has = dom_element.getAttribute('data-drop');
        if (has) has = (has == 'true') ? 1 : 0;
        return has;
    }

    /**
     * Extracts data from a drop event.<br>
     * Supports files, HTML, and text.
     * @param {DragEvent|CustomEvent} event - The event.
     * @param {Array} [filter] - Types to filter.
     * @returns {Object} Extracted data.
     */
    static extract_data_from_drop_event(event, filter) {

        let result = {};

        let event_type = H_generics.get_type(event);

        switch (event_type) {
            case 'DragEvent':
                for (let i in event.dataTransfer.types) {
                    let type = event.dataTransfer.types[i];

                    if (filter && filter.indexOf(type) == -1) {
                        continue;
                    }

                    switch (type) {
                        case H_event.DROP_FILES:
                            result[type] = event.dataTransfer.files;
                            break;

                        default:
                            result[type] = event.dataTransfer.getData(type);
                            break;
                    }
                }
                result.destination = event.currentTarget;
                break;

            case 'CustomEvent':
                result.dropData = { 'pageX': event.detail.dropEvent.pageX, 'pageY': event.detail.dropEvent.pageY, 'elements': event.detail.elements };
                result.destination = event.target;
                break;
        }

        if (result.destination === document) {
            result.destination = document.body;
        }

        return result;
    }
    
    /*
     * SHORTCUT FUNCTIONS
     * All module use those functions to manage their events
     */

    /* ADD EVENTS */

    /**
     * Adds a click event listener to a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     * @param {boolean} [use_capture] - Use capture phase.
     * @param {boolean} [evall=false] - If true, eval the callback.
     */
    static add_event_click(dom_element, callback, use_capture, evall = false) {
        H_event.add_event(dom_element, H_event.EVENT_CLICK, callback, use_capture, evall);
    }

    /**
     * Adds a double-click event listener to a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     * @param {boolean} [use_capture] - Use capture phase.
     * @param {boolean} [evall=false] - If true, eval the callback.
     */
    static add_event_dbl_click(dom_element, callback, use_capture, evall = false) {
        H_event.add_event(dom_element, H_event.EVENT_DOUBLECLICK, callback, use_capture, evall);
    }

    /**
     * Adds a keyup event listener to a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     * @param {boolean} [use_capture] - Use capture phase.
     * @param {boolean} [evall=false] - If true, eval the callback.
     */
    static add_event_key(dom_element, callback, use_capture, evall = false) {
        H_event.add_event(dom_element, H_event.EVENT_KEYUP, callback, use_capture, evall);
    }
    /**
     * Add a click outside event listener to a DOM element.<br>
     * H_event.other_inside_elements is an array to save other HTMLElement to handle the click like it was inside.<br>
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     * @param {boolean|null} use_capture - Use capture phase.
     * @param {boolean} [evall=false] - If true, eval the callback.
     */
    static add_event_click_outside(dom_element, callback, use_capture, evall = false) {
        H_event.add_event(dom_element, H_event.EVENT_MOUSEDOWN_OUTSIDE, callback, use_capture, evall);
    }
    
    /* REMOVE EVENTS */

    /**
     * Removes a click event listener from a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     */
    static remove_event_click(dom_element, callback) {
        H_event.remove_event(dom_element, H_event.EVENT_CLICK, callback);
    }

    /**
     * Removes a double-click event listener from a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     */
    static remove_event_dbl_click(dom_element, callback) {
        H_event.remove_event(dom_element, H_event.EVENT_DOUBLECLICK, callback);
    }

    /**
     * Removes a keyup event listener from a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     */
    static remove_event_key(dom_element, callback) {
        H_event.remove_event(dom_element, H_event.EVENT_KEYUP, callback);
    }

    /**
     * Removes a click outside event listener from a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {Function} callback - Callback function.
     */
    static remove_event_click_outside(dom_element, callback) {
        H_event.remove_event(dom_element, H_event.EVENT_MOUSEDOWN_OUTSIDE, callback);
    }

    /* SEND EVENTS */
    /* CHECK BEFORE USING FROM ANOTHER EVENT ! NESTED EVENT SYNDROME CAN OCCURED WITH dispatchEvent */

    /**
     * Dispatches a click event on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {*} [data] - Event detail data.
     * @param {boolean} [bubbles] - Whether the event bubbles.
     * @param {boolean} [cancelable] - Whether the event is cancelable.
     */
    static send_event_click(dom_element, data, bubbles, cancelable) {
        H_event.send_event(dom_element, H_event.EVENT_CLICK, data, bubbles, cancelable);
    }

    /**
     * Dispatches a double-click event on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {*} [data] - Event detail data.
     * @param {boolean} [bubbles] - Whether the event bubbles.
     * @param {boolean} [cancelable] - Whether the event is cancelable.
     */
    static send_event_dbl_click(dom_element, data, bubbles, cancelable) {
        H_event.send_event(dom_element, H_event.EVENT_DOUBLECLICK, data, bubbles, cancelable);
    }

    /**
     * Dispatches a keyup event on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {*} [data] - Event detail data.
     * @param {boolean} [bubbles] - Whether the event bubbles.
     * @param {boolean} [cancelable] - Whether the event is cancelable.
     */
    static send_event_key(dom_element, data, bubbles, cancelable) {
        H_event.send_event(dom_element, H_event.EVENT_KEYUP, data, bubbles, cancelable);
    }

    /**
     * Dispatches a click outside event on a DOM element.
     * @param {HTMLElement} dom_element - The element.
     * @param {*} [data] - Event detail data.
     * @param {boolean} [bubbles] - Whether the event bubbles.
     * @param {boolean} [cancelable] - Whether the event is cancelable.
     */
    static send_event_click_outside(dom_element, data, bubbles, cancelable) {
        H_event.send_event(dom_element, H_event.EVENT_MOUSEUP_OUTSIDE, data, bubbles, cancelable);
    }
}

var h = h || {};
h.libs = h.libs || {};
h.libs.event = H_event;
window.H_event = H_event;