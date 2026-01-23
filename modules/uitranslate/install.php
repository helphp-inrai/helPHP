<?php

$module_name = 'uitranslate';

$files = array(
    0=>['uitranslate/admin/index.php', 'admin/uitranslate/index.php', 0755],
    1=>['uitranslate/admin/tl_uitranslate-en.php', 'admin/uitranslate/tl_uitranslate-en.php', 0755],
    2=>['uitranslate/admin/tl_uitranslate-fr.php', 'admin/uitranslate/tl_uitranslate-fr.php', 0755],
);

$folders = array(
    0=>['admin/uitranslate', 0755],
);

$config_part = array(
    'uitranslate'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;