<?php

$module_name = 'category';

$files = array(
    0=>['category/admin/index.php', 'admin/category/index.php', 0755],
    1=>['category/public/index.php', 'public/category/index.php', 0755],
);

$folders = array(
    0=>['admin/category', 0755],
    1=>['public/category', 0755],
);

$config_part = array(
    'category'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;