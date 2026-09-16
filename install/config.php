<?php

$aConfig = array(
    'type' => BX_DOL_MODULE_TYPE_MODULE,
    'name' => 'my_una_world',
    'title' => 'My UNA World',
    'note' => 'Native UNA Studio backup/recovery with background backup jobs, live progress, disaster import, verified restore, rollback, lifecycle protection, and retention.',
    'version' => '1.3.3',
    'vendor' => 'MyUNAWorld',
    'compatible_with' => array('15.0.x'),
    'home_dir' => 'newton/my_una_world/',
    'home_uri' => 'my_una_world',
    'db_prefix' => 'my_una_world_',
    'class_prefix' => 'MyUnaWorld',
    'language_category' => 'My UNA World',
    'install' => array(
        'execute_sql' => 1,
        'update_languages' => 1,
        'clear_db_cache' => 1,
    ),
    'uninstall' => array(
        'execute_sql' => 1,
        'update_languages' => 1,
        'clear_db_cache' => 1,
    ),
    'enable' => array(
        'execute_sql' => 1,
        'clear_db_cache' => 1,
    ),
    'disable' => array(
        'execute_sql' => 1,
        'clear_db_cache' => 1,
    ),
    'dependencies' => array(),
);
