<?php

$module_name = 'csseditor';

$files = array(
    0=>['csseditor/admin/index.php', 'admin/csseditor/index.php', 0755],
);

$folders = array(
    0=>['admin/csseditor', 0755],
);

$config_part = array(
    'csseditor'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'source',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = ['', 'source=module'];