<?php

$module_name = 'group';

$files = array(
    0=>['group/admin/index.php', 'admin/group/index.php', 0755],
    1=>['group/public/index.php', 'public/group/index.php', 0755],
);

$folders = array(
    0=>['admin/group', 0755],
    1=>['public/group', 0755],
);

$config_part = array(
    'group'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;