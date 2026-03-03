<?php

$module_name = 'media';

$files = array(
    0=>['media/admin/index.php', 'admin/media/index.php', 0755],
    1=>['media/public/index.php', 'public/media/index.php', 0755],
    2=>['media/public/video_encode.php', 'public/media/video_encode.php', 0755],
    3=>['media/public/media.php', 'public/media/media.php', 0755],
);

$folders = array(
    0=>['admin/media', 0755],
    1=>['public/media', 0755],
);

$config_part = array(
    'media'=>array(
        'options'=>'',
        'indexable'=>false,
        'admin_param'=>'',
        'public_param'=>'',
        'hierarchy'=>false
    )
);