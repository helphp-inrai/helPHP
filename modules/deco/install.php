<?php

$module_name = 'deco';

$files = array(
    0=>['deco/admin/index.php', 'admin/deco/index.php', 0755],
    1=>['deco/public/index.php', 'public/deco/index.php', 0755],
);

$folders = array(
    0=>['admin/deco', 0755],
    1=>['public/deco', 0755],
);

$config_part = array(
    'deco'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'id',
        'public_param'=>'id',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;
