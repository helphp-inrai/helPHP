window.h.block = window.h.block || {};
class Block_blockbackground extends H_module {

    constructor(dom_id,block_id){

        super(dom_id);
        let inpshader=document.getElementById('shadercode_'+dom_id);
        if (inpshader !=undefined){
            var shadercode=inpshader.value;
            h.libs.dom.remove_element(inpshader);
            var toy = new ShaderToyLite('canvastoy_'+dom_id);
            toy.setCommon('');
            // toy.setBufferA({source: a});
            toy.setImage({source: shadercode, iChannel0: 'A'});
            toy.play();
        }
    }
    static instances = {};
    clean(){
        delete(this.toy);
    }
    static instances = {};
    static create_instance(dom_id){
        if (Block_blockbackground.instances[dom_id]){
            Block_blockbackground.instances[dom_id].clean();
            delete(Block_blockbackground.instances[dom_id]);
        }
        Block_blockbackground.clean_instances();
        Block_blockbackground.instances[dom_id] = new Block_blockbackground(dom_id);
        return Block_blockbackground.instances[dom_id];
    }
    static clean_instances(current){
        let toClean = [];
        for (var key in Block_blockbackground.instances) {
            if (!Block_blockbackground.instances[key].exist()){
                Block_blockbackground.instances[key].clean();
                toClean.push(key);
            }
        }
        toClean.forEach((key)=>{
            delete(Block_blockbackground.instances[key]);
        });
    }
    
    stopmouse(evt){
        h.e.stop_event(evt);
    }
}
window.h.block.Block_blockbackground = Block_blockbackground;