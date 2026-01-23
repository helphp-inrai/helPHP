<?php

namespace helPHP\modules\block\video\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;

class Video extends HelPHP_module {

    const module_name = 'block';

    function __construct($domContainer = null) {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer,$CONFIG::HELPHP_FOLDER.'modules/block/video/public/Video.php');
    }
    
    public function process_data(&$post, $to_return=false) {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if(isset($post['block'])){
            $post[$this->ifld_video_id] = $post['block'];
        }
        
        $master_output = H::group($this->module_name.'_display');
        switch($post[$this->input_action_identifier]){
            default:
                $this->prepare_fields($post, 'block_video');
                if (!$post[$this->ifld_video_id]) $this->reset_fields($post, 'block_video');

                $master_output->add_child( $this->display($post) );
            break;
        }

        if ($to_return){
            return $master_output;
        }else{
            $this->display->add_child( $master_output );
        }
    }
    
    public function display($post) {

        $data_display = H::div(['class'=>'block_container block_1 video','data-block_type'=>'video','data-block_id'=>$post[$this->ifld_video_id],'id'=>'block_video_'.$post[$this->ifld_video_id] ]);
        
        if (isset($post[$this->ifld_video_youtubesrc]) && $post[$this->ifld_video_youtubesrc] !=''){
            //autoplay do not work anymore on youtube...
            // if($post[$this->ifld_video_autoplay]==1){
            //        $post[$this->ifld_video_youtubesrc].="?&autoplay=1";
            // }
            $video='<iframe width="100%" height="100%" src="'.$post[$this->ifld_video_youtubesrc].'" title="YouTube video player" frameborder="0"';
            $video.=' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
        }else{
            global $MEDIA;
            $nodl=($post[$this->ifld_video_nodonwload]==1)?true:false;
            $media = $MEDIA->get_html($this->ifld_video_video, $post[$this->ifld_video_id],'small', false,$nodl);
            $video = ($media)? $media:'';
            if ($video!=''){
                if($post[$this->ifld_video_autoplay]==1){
                    $video->set_attribute('autoplay','true');
                }
                if($post[$this->ifld_video_vloop]==1){
                    $video->set_attribute('loop','true');
                }
                if($post[$this->ifld_video_control]!=1){
                    $video->del_attribute('controls');
                }
            }
        }
        $data_display->add_child($video);

        return $data_display;
    }
}