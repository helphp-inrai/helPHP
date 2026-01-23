<?php

$module_name = 'burger';

$files = array(
    0=>['burger/admin/index.php', 'admin/burger/index.php', 0755],
    1=>['burger/public/index.php', 'public/burger/index.php', 0755],
);

$folders = array(
    0=>['admin/burger', 0755],
    1=>['public/burger', 0755],
);

$config_part = array(
    'burger'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;