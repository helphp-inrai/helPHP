<?php
namespace helPHP\tests;

// $baseroot = dirname($_SERVER['DOCUMENT_ROOT']);
// $siteroot = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['SCRIPT_FILENAME'])[0];
include_once('../config/main.php');
include_once('../../helPHP/autoload.php');

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Utils;
use helPHP\modules\media\admin\Media as Media_ui;

class Media_test extends helPHP_Module
{

    const module_name = 'test';

    public function __construct($dom_container = null)
    {
        $this->prepare_module(self::module_name,false);
        $this->dom_container = '';
        parent::__construct($dom_container);

        $this->process = [];
        $this->process['process'][]=['type'=>'image_resize', 'max_width'=>1000, 'max_height'=>1000];
        $this->process['process'][]=['type'=>'image_to_file', 'quality'=>80];
    }

    private $process;

    public function process_data(&$post)
    {
        if (parent::process_data($post) == false) {
            //utilisateur non autorisé sur ce module
            return null;
        }
        
        $this->display = H::new_document('media helPHP', '', '', false, false);
        switch ($post[$this->input_action_identifier]) {
            case 'save':
                $this->display = $this->save_form($post);
            break;

            default:
                $this->display->add_child( $this->display_media($post) );
            break;
        }
    }

    public function display_media($post){
        global $CONFIG;
        
        $output = H::DIV(['class'=>$this->css.'container']);

        $output->add_child( H::script('CONSTANTS.admin_folder = "'.$CONFIG::ADMIN_FOLDER.'";') );

            $increment = 0;
            
            // $process['process'][]=['type'=>'image_resize', 'max_width'=>500, 'max_height'=>500];
            // $process['process'][]=['type'=>'image_to_file', 'quality'=>80];

            // cas 1
            $uploader = H::DIV(['class'=>$this->css.'uploader cas1']);
            $uploader->add_child(H::SPAN([], '1 seul fichier, auto submit, remplace le précédent, seulement pdf'));
            $params = ['submit'=>true, 'accept'=>'.pdf'];
            $increment++;
            $uploader->add_child( Media_ui::display('uploader',$params,'test_media-image',$increment) );

        $output->add_child( $uploader );

            // cas 2
            $uploader = H::DIV(['class'=>$this->css.'uploader cas2']);
            $process2 = $this->process;
            $process2['process'][]=['type'=>'image_resize', 'max_width'=>500, 'max_height'=>500];
            $process2['process'][]=['type'=>'image_to_file', 'quality'=>80];
            $uploader->add_child(H::SPAN([], '1 seul fichier, auto submit, remplace le précédent, seulement image, multi size'));
            $params = ['submit'=>true, 'accept'=>'image/*'];
            $increment++;
            $uploader->add_child( Media_ui::display('uploader',$params,'test_media-image',$increment,$process2) );

        $output->add_child( $uploader );
            
            // cas 3
            $uploader = H::DIV(['class'=>$this->css.'uploader cas3']);
            $uploader->add_child(H::SPAN([], 'multiple fichier, auto submit, seulement image'));
            $params = ['submit'=>true, 'accept'=>'image/*', 'multiple'=>true, 'big_view'=>true, 'list'=>true];
            $increment++;
            $uploader->add_child( Media_ui::display('uploader',$params,'test_media-image',$increment, $this->process) );

        $output->add_child( $uploader );
            
            // cas 4
            // uploader de fichier multiple, ajoute
            $uploader = H::DIV(['class'=>$this->css.'uploader cas4', 'id'=>'medias_uploader_4']);
            $uploader->add_child($this->display_form_multiple($increment));

        $output->add_child( $uploader );
        
            // cas 5
            // uploader de fichier simple, ajoute
            $uploader = H::DIV(['class'=>$this->css.'uploader cas4', 'id'=>'medias_uploader_5']);
            $uploader->add_child($this->display_form_single($increment));

        $output->add_child( $uploader );

        return $output;
    }

    public function display_form_multiple(&$increment){
        global $CONFIG;
        // Utils::error_log($this->dom_id);
        $form = H::form(['action'=>$CONFIG::BASE_URL.'tests/medias.php', 'dom_target'=>'.parent']);
        $form->add_child(H::SPAN([], 'plusieurs fichiers, dans un form'));
            $increment++;
            $incr = H::input_hidden(['name'=>'increment','value'=>$increment]);
            $params = ['multiple'=>true,'accept'=>'image/*', 'list'=>true];
            $process2 = $this->process;
            // $process2['process'][]=['type'=>'image_resize', 'max_width'=>500, 'max_height'=>500];
            // $process2['process'][]=['type'=>'image_to_file', 'quality'=>80];
            $media = Media_ui::display('uploader',$params,'test_media-image',$increment,$process2);
            $btn_save = H::submit_button(['name'=>$this->input_action_identifier,'value'=>'save'], $this->get_tl('tlc_save'));
        $form->add_child( [$incr,$media,$btn_save] );
        return $form;
    }

    public function display_form_single(&$increment){
        global $CONFIG;
        // Utils::error_log($this->dom_id);
        $form = H::form(['action'=>$CONFIG::BASE_URL.'tests/medias.php', 'dom_target'=>'.parent']);
        $form->add_child(H::SPAN([], '1 seul fichier, dans un form'));
            $increment++;
            $single = H::input_hidden(['name'=>'single','value'=>1]);
            $incr = H::input_hidden(['name'=>'increment','value'=>$increment]);
            $params = ['multiple'=>false, 'accept'=>'.pdf,.jpg', 'list'=>true];
            $process2 = $this->process;
            $process2['process'][]=['type'=>'image_resize', 'max_width'=>500, 'max_height'=>500];
            $process2['process'][]=['type'=>'image_to_file', 'quality'=>80];
            $media = Media_ui::display('uploader', $params, 'test_media-image',$increment,$process2);
            $btn_save = H::submit_button(['name'=>$this->input_action_identifier,'value'=>'save'], $this->get_tl('tlc_save'));
        $form->add_child( [$single, $incr, $media, $btn_save] );
        return $form;
    }

    public function save_form($post){
        // Utils::error_log($post);

        global $MEDIA;
        $MEDIA->process_media($post);
        
        $post['increment'] = $post['increment'] - 1;
        if (isset($post['single'])) return $this->display_form_single($post['increment']);
        else return $this->display_form_multiple($post['increment']);
    }
}

$module_test = new Media_test();

$module_test->process_data($_POST);

$module_test->echo_output();