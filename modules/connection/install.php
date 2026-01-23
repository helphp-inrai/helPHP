<?php

$module_name = 'connection';

$files = array(
    0=>['connection/admin/index.php', 'admin/connection/index.php', 0755],
    1=>['connection/public/index.php', 'public/connection/index.php', 0755],
);

$folders = array(
    0=>['admin/connection', 0755],
    1=>['public/connection', 0755],

);

$config_part = array(
    'connection'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);