<?php

$module_name = 'preview';

$files = array(
    0=>['preview/admin/index.php', 'admin/preview/index.php', 0755],
    1=>['preview/public/preview.php', 'public/preview/preview.php', 0755],
);

$folders = array(
    0=>['admin/preview', 0755],
    1=>['public/preview', 0755],
);

$config_part = array(
    'preview'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);