<?php

$module_name = 'hierarchy';

$files = array(
    0 => ['hierarchy/admin/index.php', 'admin/hierarchy/index.php', 0755],
    1 => ['hierarchy/public/index.php', 'public/hierarchy/index.php', 0755]
);

$folders = array(
    0 => ['admin/hierarchy', 0755],
    1 => ['public/hierarchy', 0755],
);

$config_part = array(
    'hierarchy'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;