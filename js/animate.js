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

//this class purpose is to store the animations to play.

var h = h || {};
h.libs = h.libs || {};
/**
 * @class H_animation
 * @classdesc
 * Stores and manages the list of animations to play.<br>
 * Provides static methods to check and process animations.<br>
 * Used as h.libs.animation.
 */
class H_animation{
    constructor(){

    }
    /**
     * Checks if there are animations to play and processes them.<br>
     * Iterates over the _animate array and calls process_anim for each.
     */
    static check_anim(){
        if (H_animation._animate.length > 0){
            for (let i = 0; i < H_animation._animate.length; i++){
                H_animation.process_anim(H_animation._animate[i]);
            }
        }
    }
    /**
     * Processes a single animation object.<br>
     * Depending on the type, instantiates the appropriate animation class.
     * Actually there is only H_anim type.
     * @param {Object} a - Animation object to process.
     * @returns {boolean|void} False if type is unknown.
     */
    static process_anim(a){
        if (a){
            let type = a.type;
            switch(type){
            
                case 'H_anim':
                    console.log(a);
                    new H_anim(a.opts);
                break;
                
                default:
                return false;
            }
        }
    }
    /**
     * Recursively traverses the DOM to find elements with animation attributes.<br>
     * Calls test_anim_exist on each element.
     * @function
     * @param {HTMLElement} domElem - The DOM element to start from.
     */
    static recurse_dom(domElem){
        if (domElem){
            
            H_animation.test_anim_exist(domElem);
            var children = domElem.children;
            if (children){
                for (let i = 0; i < children.length; i++){
                    H_animation.recurse_dom(children[i]);
                }
            }
        }
    }
    /**
     * Detects and initializes all animations in the DOM or in a given HTML string.<br>
     * Recursively scans elements for data-animate attributes and populates H_animation._animate.<br>
     * it's a bit a heavy process but it automate the detection of all you animations.<br>
     * instead you can push them in you own script by using new H_anim({params...})
     * @function
     * @param {string|boolean} [res=false] - Optional HTML string to parse, or false to use document.body.
     */
    static detect_anime(res=false){
        H_animation._animate = [];
        if (res) {
            let dom = H_dom.string_to_dom(res);
            H_animation.recurse_dom(dom);
        } else {
            let dom = document.body;
            H_animation.recurse_dom(dom);
        }
        H_animation.check_anim();
    }

    /**
    * Tests if a DOM element has animation attributes and adds it to the animation queue if so.<br>
    * Looks for data-animate="H_anim" and parses data-anim_opts.
    * @function
    * @param {HTMLElement} domElem - The DOM element to test.
    */
    static test_anim_exist(domElem){
        if (domElem.dataset && domElem.dataset.animate){
            var type = domElem.dataset.animate;
            if (type == 'H_anim') {
                var opts = JSON.parse(domElem.dataset.anim_opts);
                //added originally by page js
                console.log(domElem);
                opts.elements = '#'+domElem.id;
                opts.preset = true;
                
                //------------------
                if(domElem.id!=''){
                    H_animation._animate.push({type, opts});
                    }
            } 
        }
    }           
}
h.libs.animation = H_animation;
H_animation._animate = [];




/**
 * @class H_bezier
 * @classdesc
 * Implements cubic Bezier easing functions for animation timing.<br>
 * Used internally by H_anim for custom easing curves.<br>
 * can be accessed from h.libs.bezier<br>
 * Original script from https://github.com/gre/Bezier-easing
 * @param {number} mX1 - X coordinate of first control point (0..1).
 * @param {number} mY1 - Y coordinate of first control point.
 * @param {number} mX2 - X coordinate of second control point (0..1).
 * @param {number} mY2 - Y coordinate of second control point.
 */
class H_bezier {
    constructor(mX1, mY1, mX2, mY2) {
        if (!(0 <= mX1 && mX1 <= 1 && 0 <= mX2 && mX2 <= 1)) {
            throw new Error('H_bezier x values must be in [0, 1] range');
        }
        this.mX1 = mX1;
        this.mY1 = mY1;
        this.mX2 = mX2;
        this.mY2 = mY2;
        this.sampleValues = H_bezier.float32ArraySupported ? new Float32Array(H_bezier.kSplineTableSize) : new Array(H_bezier.kSplineTableSize);
        if (mX1 !== mY1 || mX2 !== mY2) {
            for (let i = 0; i < H_bezier.kSplineTableSize; ++i) {
                this.sampleValues[i] = H_bezier.calcH_bezier(i * H_bezier.kSampleStepSize, mX1, mX2);
            }
        }
    }
    /**
    * Gets the parameter t for a given x value on the Bezier curve.<br>
    * @param {number} aX - The x value (0..1).
    * @returns {number} The parameter t.
    */
    getTForX(aX) {
        let intervalStart = 0.0;
        let currentSample = 1;
        let lastSample = H_bezier.kSplineTableSize - 1;
        let sampleValues = this.sampleValues;
        for (; currentSample !== lastSample && sampleValues[currentSample] <= aX; ++currentSample) {
            intervalStart += H_bezier.kSampleStepSize;
        }
        --currentSample;
        let dist = (aX - sampleValues[currentSample]) / (sampleValues[currentSample + 1] - sampleValues[currentSample]);
        let guessForT = intervalStart + dist * H_bezier.kSampleStepSize;
        let initialSlope = H_bezier.getSlope(guessForT, this.mX1, this.mX2);
        if (initialSlope >= H_bezier.NEWTON_MIN_SLOPE) {
            return H_bezier.newtonRaphsonIterate(aX, guessForT, this.mX1, this.mX2);
        } else if (initialSlope === 0.0) {
            return guessForT;
        } else {
            return H_bezier.binarySubdivide(aX, intervalStart, intervalStart + H_bezier.kSampleStepSize, this.mX1, this.mX2);
        }
    }
    /**
     * Computes the easing value for a given progress x.<br>
     * @param {number} x - The progress (0..1).
     * @returns {number} The eased value.
     */
    easing(x) {
        if (this.mX1 === this.mY1 && this.mX2 === this.mY2) return x;
        if (x === 0) return 0;
        if (x === 1) return 1;
        return H_bezier.calcH_bezier(this.getTForX(x), this.mY1, this.mY2);
    }

    // ...static methods for Bezier math...
    static A(aA1, aA2) { return 1.0 - 3.0 * aA2 + 3.0 * aA1; }
    static B(aA1, aA2) { return 3.0 * aA2 - 6.0 * aA1; }
    static C(aA1)      { return 3.0 * aA1; }
    static calcH_bezier(aT, aA1, aA2) { return ((H_bezier.A(aA1, aA2) * aT + H_bezier.B(aA1, aA2)) * aT + H_bezier.C(aA1)) * aT; }
    static getSlope(aT, aA1, aA2) { return 3.0 * H_bezier.A(aA1, aA2) * aT * aT + 2.0 * H_bezier.B(aA1, aA2) * aT + H_bezier.C(aA1); }
    static binarySubdivide(aX, aA, aB, mX1, mX2) {
        let currentX, currentT, i = 0;
        do {
            currentT = aA + (aB - aA) / 2.0;
            currentX = H_bezier.calcH_bezier(currentT, mX1, mX2) - aX;
            if (currentX > 0.0) {
                aB = currentT;
            } else {
                aA = currentT;
            }
        } while (Math.abs(currentX) > H_bezier.SUBDIVISION_PRECISION && ++i < H_bezier.SUBDIVISION_MAX_ITERATIONS);
        return currentT;
    }
    static newtonRaphsonIterate(aX, aGuessT, mX1, mX2) {
        for (let i = 0; i < H_bezier.NEWTON_ITERATIONS; ++i) {
            let currentSlope = H_bezier.getSlope(aGuessT, mX1, mX2);
            if (currentSlope === 0.0) return aGuessT;
            let currentX = H_bezier.calcH_bezier(aGuessT, mX1, mX2) - aX;
            aGuessT -= currentX / currentSlope;
        }
        return aGuessT;
    }
}
H_bezier.NEWTON_ITERATIONS = 4;
H_bezier.NEWTON_MIN_SLOPE = 0.001;
H_bezier.SUBDIVISION_PRECISION = 0.0000001;
H_bezier.SUBDIVISION_MAX_ITERATIONS = 10;
H_bezier.kSplineTableSize = 11;
H_bezier.kSampleStepSize = 1.0 / (H_bezier.kSplineTableSize - 1.0);
H_bezier.float32ArraySupported = typeof Float32Array === 'function';

h.libs.bezier = H_bezier;
/**
 * @class H_anim
 * @classdesc
 * Main animation engine for keyframe-based and property-based animations.<br>
 * Supports events (load, click, hover, scroll), custom keyframes, easing, looping, and callbacks.<br>
 * Used for animating DOM elements with advanced options.<br>
 * Returns a Promise that resolves when the animation ends and start next animation.
 * @param {Object} opts - Animation options and keyframes.
 * @returns {Promise} Resolves when animation completes.
 * @example
 * on an html tag, with an ID, you can set data-animate="H_anim" to indicate that this element is animated.<br>
 * then in in data-anim_opts attributes you can set all your keyframes like this. :<br>
 * --------------------------------------------------------<br>
 * {"easing": "OutQuart","duration": "6000", "transform": ["translateY(30%)", "translateY(0%)"], "opacity": ["0", "1"],"end":{
 *  "easing": "OutQuart","duration": "6000", "transform": ["translateY(0%)", "translateY(30%)"], "opacity": ["1", "0"]}
 * }
 * --------------------------------------------------------<br>
 * please note the "end" value : its another set of param for another keyframe.<br> 
 * but it can also contain a callaback like : ()=>{callback();} to be trigerered at the end of the annimation.
 * you can also set a new H_anim({params...}) successive calls with a delay value to trig each anim at different moment, or using "end" :
 * --------------------------------------------------------<br>
 * new H_anim({ elements: "#id", duration: 1000, easing : "OutQuart", transform: ["translateY(30%)","translateY(0%)"], opacity: ["0", "1"]});
 * new H_anim({ elements: "#id", delay:1000, duration: 1000, easing : "OutQuart", transform: ["translateY(0%)","translateY(30%)"], opacity: ["1", "0"], end :()=>{console.log("test");}});
 * new H_anim({ elements: "#id", delay:2000, duration: 1000, easing : "OutQuart", transform: ["translateY(30%)","translateY(-10%)"], opacity: ["0", "1"], end :()=>{console.log("test");}});
 * --------------------------------------------------------
 * 
 */
class H_anim {
    constructor(opts) {
        if (!opts.elements) { console.log('noelements'); return false; }
        const rest = Object.keys(opts).filter(key => !H_anim.default_settings.hasOwnProperty(key));
        let keyframes = {};
        rest.forEach(key => { keyframes[key] = opts[key]; });

        opts.elements = H_anim.get_elements(opts.elements);
        opts.preset = (!opts.preset) ? false : true;
        opts.duration = (!opts.duration) ? 300 : opts.duration;
        opts.delay = (!opts.delay) ? 0 : opts.delay;
        opts.loop = (!opts.loop) ? false : opts.loop;
        opts.direction = (!opts.direction) ? 'normal' : opts.direction;
        opts.event = (!opts.event) ? 'load' : opts.event;
        opts.speed = (!opts.speed) ? 1 : opts.speed;
        opts.easing = (!opts.easing) ? 'OutCubic' : opts.easing;
        opts.change = (!opts.change) ? null : opts.change;
        opts.end = (!opts.end) ? null : opts.end;

        if (opts.event == 'click' || opts.event == 'hover' || opts.event == 'scroll') opts.loop = false;
        if ((opts.event == 'hover' || opts.event == 'scroll') && opts.direction == 'alternate') opts.direction = 'normal';

        if (opts.preset) keyframes = H_anim.make_keyframe(opts.elements, keyframes);
        if (typeof (opts.loop) == 'number') {
            opts.loop = Math.trunc(opts.loop);
            if (opts.loop == 1) opts.loop = true;
            if (opts.direction == 'alternate' && opts.loop != true) opts.loop = 2 * opts.loop;
        }

        return new Promise(resolve => H_anim.add_animations(opts, keyframes, resolve));
    }

    /**
     * Returns the first item of an array.<br>
     * @param {Array} arr - The array.
     * @returns {*} The first item.
     */
    static first([item]) { return item; }
     /**
     * Checks if the operand is a function.<br>
     * @param {*} operand - The value to check.
     * @returns {boolean} True if function.
     */
    static is_function(operand) { return typeof operand == "function"; }
     /**
     * Gets DOM elements from a selector, array, or element.<br>
     * @param {string|Array|HTMLElement} elements - Selector, array, or element.
     * @returns {Array} Array of DOM elements.
     */
    static get_elements(elements) {
        if (Array.isArray(elements)) return elements;
        if (! H_generics.is_string(elements) && elements.nodeType) return [elements];
        return Array.from( H_generics.is_string(elements) ? document.querySelectorAll(elements) : elements);
    }
     /**
     * Generates keyframes for preset animations based on element position.<br>
     * manipulating transform and translate values.
     * @param {Array} elems - Elements to animate.
     * @param {Object} keyframe - Keyframe definition.
     * @returns {Object} Modified keyframe.
     */
    static make_keyframe(elems, keyframe) {
        let maxRect = { bottom: 0, height: 0, left: 0, right: 0, top: 0, width: 0, x: 0, y: 0 };
        elems.forEach(elem => {
            let rect =  H_dom.get_global_rect(elem, true);
            for (let key in rect) {
                maxRect[key] = Math.max(rect[key], maxRect[key]);
            }
        });
        let screenW = window.screen.width;
        let screenY = window.screen.height;
        for (let property in keyframe) {
            if (property == 'transform') {
                let arr = keyframe[property][0].split('(');
                let type = arr[0];
                switch (type) {
                    case 'translateX':
                        let left = arr[1].includes('-');
                        if (left) {
                            let nbr = maxRect.width + maxRect.left;
                            type += '(-' + nbr + 'px)';
                        } else {
                            let nbr = screenW - maxRect.left;
                            type += '(' + nbr + 'px)';
                        }
                        keyframe[property][0] = type;
                        break;
                    case 'translateY':
                        let top = arr[1].includes('-');
                        if (top) {
                            let nbr = maxRect.height + maxRect.top;
                            type += '(-' + nbr + 'px)';
                        } else {
                            let nbr = screenY - maxRect.bottom;
                            type += '(' + nbr + 'px)';
                        }
                        keyframe[property][0] = type;
                        break;
                    default:
                        break;
                }
            }
        }
        return keyframe;
    }
    /**
     * Splits a hex color into pairs for RGBA conversion.
     * @param {string} color - Hex color string.
     * @returns {Array} Array of hex pairs.
     */
    static hex_pairs(color) {
        const split = color.split("");
        const pairs = color.length < 5 ? split.map(string => string + string) : split.reduce((array, string, index) => {
            if (index % 2) array.push(split[index - 1] + string);
            return array;
        }, []);
        if (pairs.length < 4) pairs.push("ff");
        return pairs;
    }
    /**
     * Converts hex color pairs to integer values.
     * @param {string} color - Hex color string.
     * @returns {Array} Array of RGBA values.
     */
    static convert(color) {
        return H_anim.hex_pairs(color).map(string => parseInt(string, 16));
    }
    /**
     * Converts a hex color to an rgba() CSS string.
     * @param {string} hex - Hex color string.
     * @returns {string} CSS rgba() string.
     */
    static rgba(hex) {
        const color = hex.slice(1);
        const [r, g, b, a] = H_anim.convert(color);
        return `rgba(${r}, ${g}, ${b}, ${a / 255})`;
    }

    static ease_names = ['Quad', 'Cubic', 'Quart', 'Quint', 'Sine', 'Expo', 'Circ', 'Back'];
    /**
     * Elastic easing function.
     * @param {number} t - Progress (0..1).
     * @param {number} p - Period.
     * @returns {number} Eased value.
     */
    static elastic(t, p) {
        return t === 0 || t === 1 ? t :
            -Math.pow(2, 10 * (t - 1)) * Math.sin((((t - 1) - (p / (Math.PI * 2.0) * Math.asin(1))) * (Math.PI * 2)) / p);
    }

    static equations = {
        In: [
            [0.550, 0.085, 0.680, 0.530],
            [0.550, 0.055, 0.675, 0.190],
            [0.895, 0.030, 0.685, 0.220],
            [0.755, 0.050, 0.855, 0.060],
            [0.470, 0.000, 0.745, 0.715],
            [0.950, 0.050, 0.795, 0.035],
            [0.600, 0.040, 0.980, 0.335],
            [0.600, -0.280, 0.735, 0.045]
        ],
        Out: [
            [0.250, 0.460, 0.450, 0.940],
            [0.215, 0.610, 0.355, 1.000],
            [0.165, 0.840, 0.440, 1.000],
            [0.230, 1.000, 0.320, 1.000],
            [0.390, 0.575, 0.565, 1.000],
            [0.190, 1.000, 0.220, 1.000],
            [0.075, 0.820, 0.165, 1.000],
            [0.175, 0.885, 0.320, 1.275]
        ],
        InOut: [
            [0.455, 0.030, 0.515, 0.955],
            [0.645, 0.045, 0.355, 1.000],
            [0.770, 0.000, 0.175, 1.000],
            [0.860, 0.000, 0.070, 1.000],
            [0.445, 0.050, 0.550, 0.950],
            [1.000, 0.000, 0.000, 1.000],
            [0.785, 0.135, 0.150, 0.860],
            [0.680, -0.550, 0.265, 1.550]
        ]
    };

    static easings = {
        linear: new H_bezier(0.250, 0.250, 0.750, 0.750)
    };
    /**
     * Fills the easings map with Bezier curves for all supported types.
     */
    static easings_fill(){
        for (let type in H_anim.equations) {
            H_anim.equations[type].forEach((f, i) => {
                H_anim.easings[type + H_anim.ease_names[i]] = new H_bezier(...f);
            });
        }
        
    }
    /**
     * Decomposes an easing string into its components.
     * @param {string} string - Easing string.
     * @returns {Object} Easing parameters.
     */
    static decompose_easing(string) {
        let [easing, amplitude = 1, period = .4] = string.trim().split(" ");
        return { easing, amplitude, period };
    }
    /**
     * Applies the specified easing to a progress value.<br>
     * if used after decompose_easing, only first value is used : easing. amplitude and period not used...
     * @param {Object} easing - Easing parameters.
     * @param {number} progress - Progress (0..1).
     * @returns {number} Eased value.
     */
    static ease({ easing, amplitude, period }, progress) {
        const H_bezier = H_anim.easings[easing];
        if (H_bezier && typeof H_bezier.easing === 'function') {
            return H_bezier.easing(progress);
        }
        return progress;
    }

    static valid_transforms = ['translateX', 'translateY', 'translateZ', 'rotate', 'rotateX', 'rotateY', 'rotateZ', 'scale', 'scaleX', 'scaleY', 'scaleZ', 'skewX', 'skewY', 'perspective'];

    static extract_reg_exp = /-?\d*\.?\d+/g;

    static extractStrings(value) {
        return value.split(H_anim.extract_reg_exp);
    }

    static extractNumbers(value) {
        return value.match(H_anim.extract_reg_exp).map(Number);
    }

    static sanitize(values) {
        return values.map(value => {
            let string = String(value);
            return string.startsWith("#") ? H_anim.rgba(string) : string;
        });
    }
     /**
     * Adds property keyframes for an animation.
     * @param {string} property - CSS property.
     * @param {Array} values - Keyframe values.
     * @param {HTMLElement} element - Target element.
     * @returns {Object} Keyframe object.
     */
    static add_property_keyframes(property, values, element) {
        let animatable = H_anim.sanitize(values);
        let strings = H_anim.extractStrings(H_anim.first(animatable));
        let numbers = animatable.map(H_anim.extractNumbers);
        let round = H_anim.first(strings).startsWith("rgb");
        return { property, strings, numbers, round };
    }
     /**
     * Creates animation keyframes for an element.
     * @param {Object} keyframes - Keyframes definition.
     * @param {number} index - Element index.
     * @param {HTMLElement} element - Target element.
     * @returns {Array} Array of keyframe objects.
     */
    static create_animation_keyframes(keyframes, index, element) {
        return Object.entries(keyframes).map(([property, values]) =>
            H_anim.add_property_keyframes(property, H_anim.is_function(values) ? values(index) : values, element));
    }
     /**
     * Computes the current value between two numbers using easing.
     * @param {number} from - Start value.
     * @param {number} to - End value.
     * @param {number} easing - Easing progress.
     * @returns {number} Interpolated value.
     */
    static get_current_value(from, to, easing) {
        return from + (to - from) * easing;
    }
    /**
     * Recomposes a CSS value from numbers and strings.<br>
     * @param {Array} fromTo - [from, to] values.
     * @param {Array} strings - String parts.
     * @param {boolean} round - Whether to round values.
     * @param {number} easing - Easing progress.
     * @returns {string} Recomposed CSS value.
     */
    static recompose_value([from, to], strings, round, easing) {
        let tstr = Object.assign([], strings);
        return tstr.reduce(function (style, string, index) {
            let previous = index - 1;
            let value = H_anim.get_current_value(from[previous], to[previous], easing);
            return style + (round && index < 4 ? Math.round(value) : value) + string;
        });
    }
    /**
     * Creates a styles object for the current keyframes and easing.<br>
     * @param {Array} keyframes - Keyframe objects.
     * @param {number} easing - Easing progress.
     * @returns {Object} Styles object.
     */
    static create_styles(keyframes, easing) {
        return keyframes.reduce((styles, { property, numbers, strings, round }) => {
            styles[property] = H_anim.recompose_value(numbers, strings, round, easing);
            return styles;
        }, {});
    }
    /**
     * Reverses the keyframes for inverse direction.<br>
     * @param {Array} keyframes - Keyframe objects.
     */
    static reverse_keyframes(keyframes) {
        keyframes.forEach(({ numbers }) => numbers.reverse());
    }

    static all = {
        list: [],
        click: [],
        hover: [],
        scroll: [],
        add(object) {
            if (object.event == 'load') {
                this.list.push(object);
                if (this.list.length == 1) {
                    requestAnimationFrame(H_anim.tick);
                }
            }
            if (object.event == 'click') this.click.push(object);
            if (object.event == 'hover') this.hover.push(object);
            if (object.event == 'scroll') this.scroll.push(object);
        }
    };

    static paused = {};

    static track_time(timing, now) {
        if (!timing.startTime) timing.startTime = now;
        timing.elapsed = now - timing.startTime;
    }

    static reset_time(obj) {
        obj.startTime = 0;
    }

    static get_progress({ elapsed, duration }) {
        let prog;
        if (duration > 0) prog = Math.min(elapsed / duration, 1);
        else prog = 1;
        return prog;
    }

    static set_speed(speed, value, index) {
        return speed > 0 ? (H_anim.is_function(value) ? value(index) : value) / speed : 0;
    }
    /**
     * Adds animations to the global animation list and sets up events.
     * @param {Object} opts - Animation options.
     * @param {Object} keyframes - Keyframes.
     * @param {Function} resolve - Promise resolve callback.
     */
    static add_animations(opts, keyframes, resolve) {
        let last = { totalDuration: -1 };
        opts.elements.forEach(async (elem, index) => {
            let keyframe = H_anim.create_animation_keyframes(keyframes, index, elem);
            let animation = {
                elem,
                keyframe,
                'loop': opts.loop,
                'direction': opts.direction,
                'duration': H_anim.set_speed(opts.speed, opts.duration, index),
                'event': opts.event,
                'easing': H_anim.decompose_easing(opts.easing),
                'change': opts.change,
            };
            if (animation.event == 'scroll') {
                let rect =  H_dom.get_global_rect(animation.elem);
                animation.top = rect.top;
                animation.bottom = rect.bottom;
            }
            let animationDelay = H_anim.set_speed(opts.speed, opts.delay, index);
            let totalDuration = animationDelay + animation.duration;
            if (animation.direction != 'normal') H_anim.reverse_keyframes(keyframe);
            if (animation.direction == 'alternate' && !animation.loop) animation.first = true;
            if (totalDuration > last.totalDuration) {
                last.animation = animation;
                last.totalDuration = totalDuration;
            }
            animation.delay = animationDelay;
            if (animationDelay && animation.event == 'load') setTimeout(function () { H_anim.all.add(animation); }, animationDelay);
            else H_anim.all.add(animation);
        });
        let { animation } = last;
        if (!animation) return;
        // animation.end = resolve;
        const animend = opts.end;
        animation.end = (...args) => {
            if (typeof animend === 'function') animend(...args);
            if (typeof resolve === 'function') resolve(...args);
        };
        animation.options = opts;
        if (opts.event == ('click' || 'onclick')) H_anim.init_click(opts);
        if (opts.event == 'hover') H_anim.init_hover(opts);
        if (opts.event == 'scroll') H_anim.init_scroll(opts);
    }
     /**
     * Delays execution for a given duration.<br>
     * @param {number} duration - Delay in ms.
     */
    static delay(duration) {
        new Promise(resolve => H_anim.all.add({
            duration,
            end: resolve
        }));
    }
     /**
     * Initializes click event listeners for animation.<br>
     * @param {Object} opts - Animation options.
     */
    static init_click(opts) {
        opts.active = false;
        opts.elements.forEach((elem) => {
            $_e.add_event(elem, EVENTS_Class.EVENT_CLICK, H_anim.on_click.bind(opts));
        });
    }
    /**
     * Click event handler for animation.<br>
     * @param {Event} evt - Click event.
     */
    static on_click(evt) {
        if (!this.active) {
            H_anim.all.click.forEach((obj) => {
                this.elements.forEach((elem) => {
                    if (elem == obj.elem) {
                        H_anim.reset_time(obj);
                        if (!obj.first) obj.first = true;
                        if (H_anim.all.list.length) {
                            if (obj.delay) setTimeout(() => { H_anim.all.list.push(obj); }, obj.delay);
                            else H_anim.all.list.push(obj);
                        } else {
                            H_anim.all.list.push(obj);
                            if (obj.delay) setTimeout(() => { requestAnimationFrame(H_anim.tick); }, obj.delay);
                            else requestAnimationFrame(H_anim.tick);
                        }
                        this.active = true;
                    }
                });
            });
        }
    }
     /**
     * Initializes hover event listeners for animation.<br>
     * @param {Object} opts - Animation options.
     */
    static init_hover(opts) {
        opts.active = false;
        opts.elements.forEach((elem) => {
            $_e.add_event(elem, EVENTS_Class.EVENT_MOUSEOVER, H_anim.on_mouse_enter.bind(opts));
            $_e.add_event(elem, EVENTS_Class.EVENT_MOUSEOUT, H_anim.on_mouse_leave.bind(opts));
        });
    }
    /**
     * Mouse enter event handler for hover animation.<br>
     * @param {Event} evt - Mouseover event.
     */
    static on_mouse_enter(evt) {
        if (!this.active) {
            H_anim.all.hover.forEach((obj) => {
                this.elements.forEach((elem) => {
                    if (elem == obj.elem) {
                        let tObj = Object.assign({}, obj);
                        tObj.keyframe = JSON.parse(JSON.stringify(obj.keyframe));
                        if (H_anim.all.list.length) {
                            H_anim.all.list.push(tObj);
                        } else {
                            H_anim.all.list.push(tObj);
                            requestAnimationFrame(H_anim.tick);
                        }
                    }
                });
            });
            this.active = true;
        }
    }
    /**
     * Mouse leave event handler for hover animation.<br>
     * @param {Event} evt - Mouseout event.
     */
    static on_mouse_leave(evt) {
        if (H_anim.all.list.length) {
            H_anim.all.list.forEach((obj) => {
                this.elements.forEach((elem) => {
                    if (elem == obj.elem) {
                        let animatable = obj.elem.style;
                        obj.keyframe.forEach(({ property, strings, numbers, round }, index) => {
                            let nbr = H_anim.extractNumbers(animatable[property]);
                            if (strings[0].includes('rgba') && nbr.length < 4) nbr.push(1);
                            numbers[1] = numbers[0];
                            numbers[0] =  H_generics.is_array(nbr) ? nbr : [Number(nbr)];
                            Object.assign(obj.keyframe[index], { property, strings, numbers, round });
                        });
                        H_anim.reset_time(obj);
                        obj.duration = obj.elapsed;
                    }
                });
            });
        }
    }

    static obsconfig = {
        root: null,
        rootMargin: '0px',
        threshold: 0.5
    };

    static observer = new IntersectionObserver(H_anim_on_scroll, H_anim.obsconfig);
    /**
     * Initializes scroll event listeners for starting animation.
     * @param {Object} opts - Animation options.
     */
    static init_scroll(opts) {
        opts.active = false;
        if ('IntersectionObserver' in window) {
            opts.elements.forEach((elem) => {
                if (opts.opacity && opts.opacity[0]) {
                    elem.style.opacity = opts.opacity[0];
                }
                H_anim.observer.observe(elem);
            });
        } else {
            H_anim.all.scroll.forEach((obj) => {
                this.elements.forEach((elem) => {
                    H_anim.reset_time(obj);
                    if (!obj.first) obj.first = true;
                    if (H_anim.all.list.length) {
                        if (obj.delay) setTimeout(() => { H_anim.all.list.push(obj); }, obj.delay);
                        else H_anim.all.list.push(obj);
                    } else {
                        H_anim.all.list.push(obj);
                        if (obj.delay) setTimeout(() => { requestAnimationFrame(H_anim.tick); }, obj.delay);
                        else requestAnimationFrame(H_anim.tick);
                    }
                    this.active = true;
                });
            });
        }
    }
    /**
     * Animation frame tick handler.<br>
     * Updates all running animations and applies styles.<br>
     * based on requestAnimationFrame
     * @param {number} now - Current timestamp.
     */
    static tick(now) {
        H_anim.all.list.forEach(function (opts, index) {
            H_anim.track_time(opts, now);
            let progress = H_anim.get_progress(opts);
            if (opts.direction) {
                let curve = progress;
                switch (progress) {
                    case 0:
                        if (opts.loop) {
                            if (typeof (opts.loop) == 'number') {
                                opts.loop--;
                            }
                        }
                        if (opts.direction == "alternate") H_anim.reverse_keyframes(opts.keyframe);
                        break;
                    case 1:
                        if (opts.loop) {
                            H_anim.reset_time(opts);
                            break;
                        }
                        if (opts.direction == 'alternate' && opts.first) {
                            opts.first = false;
                            H_anim.reset_time(opts);
                            break;
                        }
                        H_anim.all.list.splice(index, 1);
                        if (typeof opts.end === 'function') {
                            opts.end();
                        }
                        if (opts.options) {
                            if (opts.options.active) opts.options.active = false;
                            if (opts.options.callback) opts.options.callback(Object.assign({}, opts.options));
                        }
                        break;
                    default:
                        curve = H_anim.ease(opts.easing, progress);
                        break;
                }
                if (opts.change && opts.end) opts.change(progress, opts.elem);
                if (opts.elem) Object.assign(opts.elem.style, H_anim.create_styles(opts.keyframe, curve));
                return;
            }
            if (progress < 1) return;
            H_anim.all.list.delete(opts);
            opts.end(opts.duration);
        });
        if (H_anim.all.list.length) requestAnimationFrame(H_anim.tick);
    }
}
H_anim.easings_fill();
h.libs.anim = H_anim;

/**
 * IntersectionObserver callback for scroll-triggered animations.<br>
 * Unobserves the element and starts the animation when it enters the viewport.
 * @param {Array} changes - IntersectionObserverEntry array.
 * @param {IntersectionObserver} observer - The observer instance.
 */
function H_anim_on_scroll(changes, observer) {
    changes.forEach(change => {
        if (change.intersectionRatio > 0) {
            observer.unobserve(change.target);
            if (!this.active) {
                H_anim.all.scroll.forEach((obj) => {
                    if (change.target == obj.elem) {
                        H_anim.reset_time(obj);
                        if (!obj.first) obj.first = true;
                        if (H_anim.all.list.length) {
                            if (obj.delay) setTimeout(() => { H_anim.all.list.push(obj); }, obj.delay);
                            else H_anim.all.list.push(obj);
                        } else {
                            H_anim.all.list.push(obj);
                            if (obj.delay) setTimeout(() => { requestAnimationFrame(H_anim.tick); }, obj.delay);
                            else requestAnimationFrame(H_anim.tick);
                        }
                    }
                });
            }
        }
    });
}

// Default animation settings for H_anim
// @typedef {Object} H_anim.default_settings
// @property {Array|NodeList|string} elements - Target elements.
// @property {boolean} preset - Use preset keyframes.
// @property {number} duration - Animation duration in ms.
// @property {number} delay - Animation delay in ms.
// @property {boolean|number} loop - Loop animation.
// @property {string} direction - Animation direction.
// @property {string} event - Trigger event.
// @property {number} speed - Animation speed multiplier.
// @property {string} easing - Easing type.
// @property {Function|null} change - Change callback.
// @property {Function|null} end - End callback.
H_anim.default_settings = {
    elements: null,
    preset: false,
    duration: 1000,
    delay: 0,
    loop: false,
    direction: 'normal',
    event: 'load',
    speed: 1,
    easing: 'out-cubic',
    change: null,
    end: null
};

// stop/start animation when hide/show navigator
document.addEventListener("visibilitychange", () => {
    let now = performance.now();

    if (document.hidden) {
        H_anim.paused.time = now;
        H_anim.paused.all = H_anim.all.list;
        H_anim.all.list = [];
        return;
    }

    if (!H_anim.paused.all) return;
    let elapsed = now - H_anim.paused.time;
    requestAnimationFrame(() =>
        H_anim.paused.all.forEach(object => {
            object.startTime += elapsed;
            H_anim.all.add(object);
        })
    );
});


