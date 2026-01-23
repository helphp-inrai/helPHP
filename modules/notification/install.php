<?php

$module_name = 'notification';

$files = array(
    0=>['notification/admin/index.php', 'admin/notification/index.php', 0755],
    1=>['notification/public/index.php', 'public/notification/index.php', 0755],
);

$folders = array(
    0=>['admin/notification', 0755],
    1=>['public/notification', 0755],
);

$config_part = array(
    'notification'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);