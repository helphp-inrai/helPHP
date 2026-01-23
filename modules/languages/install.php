<?php

$module_name = 'languages';

$files = array(
    0=>['languages/admin/index.php', 'admin/languages/index.php', 0755],
    1=>['languages/public/index.php', 'public/languages/index.php', 0755],
);

$folders = array(
    0=>['admin/languages', 0755],
    1=>['public/languages', 0755],
);

$config_part = array(
    'languages'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);

$add_hierarchy_admin = true;