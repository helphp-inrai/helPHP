<?php

$module_name = 'users';

$files = array(
    0=>['users/admin/index.php', 'admin/users/index.php', 0755],
    1=>['users/public/index.php', 'public/users/index.php', 0755],
);

$folders = array(
    0=>['admin/users', 0755],
    1=>['public/users', 0755],
);

$config_part = array(
    'users'=>array(
        'options'=>'"address": true,
                "auto_groupe_id": 2',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;