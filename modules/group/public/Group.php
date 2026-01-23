<?php
/**
 * COPYRIGHT LEFT ANGLE- M666 moi@myke666.fr 40600 BISCARROSSE FRANCE 2009-2021
 * ALL RIGHTS RESERVED
 * TOUS DROITS RESERVES
 * THIS CODE CAN'T BE DUPLICATED OR MODIFY WITHOUT M666 (moi@myke666.fr) and LEFT ANGLE AGREEMENT
 * CE CODE NE PEUT PAS ETRE DUPLIQUE OU MODIFIE SANS L'ACCORD D'M666 moi@myke666.fr and LEFT ANGLE AGREEMENT
 * 
 * PUBLIC MODULE GENERATED WITH SPADE 3 MODULATOR ! 
 */
namespace helPHP\modules\group\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\Utils;
use helPHP\libs\H;
use helPHP\libs\Htmlgroup;

class Group extends HelPHP_module{

    const module_name = 'group';

     function __construct($dom_container = null) {
        //nomme le module,  et les variables qui en découle, indique si c'est un module admin ou pas en second param.
        // $this->input_action_identifier  et $this->dom_container 
        $this->prepare_module(self::module_name,false);
        // exécution de la classe parent qui initialise la langue et les données de traduction et le nomage de quelques variables utiles :
        parent::__construct();
    }
    //action additionnelles si il y a des sous sections

    public function process_data(&$post){
        Utils::error_log($post);
        //quelques check usuels...
        parent::process_data($post);
        //en fonction de l'action, appele la bonne méthode, mais aussi définit si il faut un domcontainer, 
        //et si on a affaire à une formulaire d'édition le controle des données/l'apply bdd, le reset et le language_load/save
        switch($post[$this->input_action_identifier]){
            //action complémentaires...

            default:
                $this->display->add_child( $this->show($post) );
            break;
        }
    }
    
    public function show($post){
        //test pour savoir si le user est connecté, renvoi un message demandant à se co si nécessaire

        global $DB;
        if(isset($post['group'])){
            $post[$this->ifld_data_id] = $post['group'];
        }
        if(isset($post[$this->ifld_data_id]) &&  $post[$this->ifld_data_id] != 0){
            //l'on emploi une query et non pas applybdddata pour des questions de perf et de customisation, comme le test des groupes.
            $q = 'SELECT DISTINCT * from '. $this->bddt_data.' where id=?';
            //enrichissemnt de la query si option group

            $data = $DB->prepared_query($q,'i',[$post[$this->ifld_data_id]]);
            //ajout dans le post
            $post = array_merge($post, $data);
            //si il y a des champs multilingue faire appel à load_translation_data.

        }else{
         return;
        }
        $output = H::group('show_group');
        //un petit div en cas de besoin...
        $data_display = H::div(['class'=>$this->css.'fiche']);
        //affichage du contenu des champs
                $name_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_name_label, 'class'=>'label'], $post[$this->get_tl('name')]);
                $name =H::tag(H::SPAN, ['id'=>$this->ifld_data_name, 'class'=>'disp_text'], $post[$this->ifld_data_name]);
                $active_label =H::tag(H::SPAN, ['id'=>$this->ifld_data_active_label, 'class'=>'label'], $post[$this->get_tl('active')]);
                $active =H::tag(H::SPAN, ['id'=>$this->ifld_data_active, 'class'=>'disp_check'.$post[$this->ifld_data_active]]);
                $data_display->add_child([$name_label,$name,$active_label,$active]);

        $output->add_child($data_display);
        //appel aux autres méthodes d'affichage des sous-sections.

        return $output;
    }
       //les méthodes venues des autres sections

    //
}
?>
