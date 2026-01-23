window.h.block = window.h.block || {};
class Block_shadertoytext extends H_module {

    constructor(dom_id){

        super(dom_id);

        let inpshader=document.getElementById('shadercode_'+dom_id);
        if (inpshader !=undefined && inpshader !=''){
            this.shadercode=inpshader.value;
            h.libs.dom.remove_element(inpshader);
            helphp_timeout('h.block.Block_shadertoytext.instances["' + dom_id + '"].init_toy();', 500, -2);
        }

    }
    init_toy(){
        this.toy = new ShaderToyLite('canvastoy_'+this.dom_id);
        this.toy.setCommon('');
        // toy.setBufferA({source: a});
        this.toy.setImage({source: this.shadercode, iChannel0: 'A'});
        this.toy.play();
    }
    clean(){
        delete(this.toy);
    }
    static instances = {};
    static create_instance(dom_id){
        if (Block_shadertoytext.instances[dom_id]){
            Block_shadertoytext.instances[dom_id].clean();
            delete(Block_shadertoytext.instances[dom_id]);
        }
        Block_shadertoytext.clean_instances();
        Block_shadertoytext.instances[dom_id] = new Block_shadertoytext(dom_id);
        return Block_shadertoytext.instances[dom_id];
    }
    static clean_instances(current){
        let toClean = [];
        for (var key in Block_shadertoytext.instances) {
            if (!Block_shadertoytext.instances[key].exist()){
                Block_shadertoytext.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key)=>{
            delete(Block_shadertoytext.instances[key]);
        });
    }
    
    stopmouse(evt){
        h.e.stop_event(evt);
    }
}
window.h.block.Block_shadertoytext = Block_shadertoytext;