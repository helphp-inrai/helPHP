<?php

$module_name = 'indexation';

$files = array(
    0=>['indexation/admin/index.php', 'admin/indexation/index.php', 0755],
    1=>['indexation/public/index.php', 'public/indexation/index.php', 0755],
);

$folders = array(
    0=>['admin/indexation', 0755],
    1=>['public/indexation', 0755],
);

$config_part = array(
    'indexation'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);