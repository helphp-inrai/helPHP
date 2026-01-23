<?php

$module_name = 'blockeditor';

$files = array(
    0=>['blockeditor/admin/index.php', 'admin/blockeditor/index.php', 0755],
    1=>['blockeditor/public/index.php', 'public/blockeditor/index.php', 0755],
    2=>['blockeditor/index.php', 'admin/block/index.php', 0755],
);

$folders = array(
    0=>['admin/blockeditor', 0755],
    1=>['admin/block', 0755],
    2=>['public/blockeditor', 0755],
    3=>['public/block', 0755],
);

$config_part = array(
    'blockeditor'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;