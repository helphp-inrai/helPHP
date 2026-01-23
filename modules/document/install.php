<?php

$module_name = 'document';

$files = array(
    0=>['document/admin/index.php', 'admin/document/index.php', 0755],
    1=>['document/public/index.php', 'public/document/index.php', 0755],
);

$folders = array(
    0=>['admin/document', 0755],
    1=>['public/document', 0755],
    2=>['public/document/cache', 0755],
);

$config_part = array(
    'document'=>array(
        'options'=>'',
        'indexable'=>true,
        'admin_param'=>'',
        'public_param'=>'id',
        'hierarchy'=>true
    )
);

$add_hierarchy_admin = true;