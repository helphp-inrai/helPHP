<?php

$module_name = 'search';

$files = array(
    0=>['search/public/index.php', 'public/search/index.php', 0755],
);

$folders = array(
    0=>['public/search', 0755],
);

$config_part = array(
    'search'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);