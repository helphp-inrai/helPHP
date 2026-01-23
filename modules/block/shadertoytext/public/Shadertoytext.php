<?php

namespace helPHP\modules\block\shadertoytext\public;

use helPHP\libs\HelPHP_module;
use helPHP\libs\H;
use helPHP\libs\Language;
use helPHP\libs\Utils;

class Shadertoytext extends HelPHP_module
{

    const module_name = 'block';

    function __construct($domContainer = null)
    {
        global $CONFIG;
        $this->prepare_module(self::module_name, false);
        parent::__construct($this->domContainer, $CONFIG::HELPHP_FOLDER . 'modules/block/shadertoytext/public/Shadertoytext.php');
    }

    public function process_data(&$post, $to_return = false)
    {
        if (parent::process_data($post) == false) {
            // user not authorized
            return null;
        }

        if (isset($post['block'])) {
            $post[$this->ifld_shadertoytext_id] = $post['block'];
        }

        $master_output = H::group($this->module_name . '_display');
        switch ($post[$this->input_action_identifier]) {
            default:
                $this->prepare_fields($post, 'block_shadertoytext');
                if (!$post[$this->ifld_shadertoytext_id]) $this->reset_fields($post, 'block_shadertoytext');
                Language::load_public_translation_data($post, self::module_name, 'shadertoytext', $post[$this->ifld_shadertoytext_id]);
                $master_output->add_child($this->display($post));
                break;
        }

        if ($to_return) {
            return $master_output;
        } else {
            $this->display->add_child($master_output);
        }
    }

    public function display($post)
    {

        global $DB, $LANG, $CONFIG;

        $data_display = H::div(['class' => 'block_container block_19 shadertoytext', 'data-block_type' => 'shadertoytext', 'data-block_id' => $post[$this->ifld_shadertoytext_id], 'id' => 'block_shadertoytext_' . $post[$this->ifld_shadertoytext_id]]);

        $load_shader = H::script(null, ['src' => $CONFIG::BASE_URL . 'js/externals/shadertoylite.js']);

        $data_display->add_child($load_shader);
        //affichage du contenu des champs
        if ($post[$this->ifld_shadertoytext_code] != '') {
            $shadercode = H::input_hidden(['id' => 'shadercode_' . $this->dom_id, 'value' => $post[$this->ifld_shadertoytext_code]]);
            $data_display->add_child([$shadercode]);
        } else {
            $shader = [];
            $shader['Error'] = true;
        }

        if (isset($shader['Error']) && $post[$this->ifld_shadertoytext_code] == '') {
            $canvas = H::SPAN(['class' => 'shadererror'], 'Shader not found');
        } else {
            $canvas = H::CANVAS(['width' => '800', 'height' => '470', 'id' => 'canvastoy_' . $this->dom_id, 'class' => 'background_canvas_shader_toy']);
        }
        $post[$this->ifld_shadertoytext_content] = isset($post[$this->ifld_shadertoytext_content]) ? $post[$this->ifld_shadertoytext_content] : '';
        $contenu = H::DIV(['id' => $this->ifld_shadertoytext_contenu_public, 'class' => 'disp_longmulti'], $post[$this->ifld_shadertoytext_content]);
        $data_display->add_child([$canvas, $contenu]);

        $data_display->add_child(H::script('helphp_timeout("window.h.block.Block_shadertoytext.create_instance(\"' . $this->dom_id . '\")",1000);', ['autoremove' => true, 'defer' => true]));
        return $data_display;
    }
}
