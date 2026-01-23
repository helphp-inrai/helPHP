<?php

$module_name = 'maintenance';

$files = array(
    0=>['maintenance/admin/index.php', 'admin/maintenance/index.php', 0755],
);

$folders = array(
    0=>['admin/maintenance', 0755],
);

$config_part = array(
    'maintenance'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;