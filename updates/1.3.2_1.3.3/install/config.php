<?php

$aConfig = array(
    'title' => 'My UNA World',
    'version_from' => '1.3.2',
    'version_to' => '1.3.3',
    'vendor' => 'MyUNAWorld',
    'compatible_with' => array('15.0.x'),
    'home_dir' => 'newton/my_una_world/updates/update_1.3.2_1.3.3/',
    'home_uri' => 'my_una_world_update_132_133',
    'module_dir' => 'newton/my_una_world/',
    'module_uri' => 'my_una_world',
    'db_prefix' => 'my_una_world_',
    'class_prefix' => 'MyUnaWorld',
    'install' => array(
        'execute_sql' => 1,
        'update_files' => 1,
        'clear_db_cache' => 1,
    ),
    'language_category' => 'My UNA World',
    'delete_files' => array(),
);
