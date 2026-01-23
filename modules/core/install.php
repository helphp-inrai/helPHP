<?php

$module_name = 'core';

$files = array(
    0 => ['core/admin/index.php', 'admin/core/index.php', 0755],
    1 => ['core/admin/main_index.php', 'admin/index.php', 0755],
    2 => ['core/public/index.php', 'public/core/index.php', 0755],
    3 => ['core/public/upload.php', 'public/core/upload.php', 0755],
    4 => ['core/public/main_index.php', 'index.php', 0755],
    5 => ['core/public/.htaccess', '.htaccess', 0755]
);

$folders = array(
    0=>['admin/core', 0755],
    1=>['public/core', 0755],
);

$config_part = array(
    'core'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = 'core_action=core_display_editor';