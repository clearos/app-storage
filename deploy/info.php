<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'storage';
$app['version'] = '5.9.9.4';
$app['release'] = '2';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['summary'] = lang('storage_app_summary');
$app['description'] = lang('storage_app_long_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('storage_storage_manager');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_storage');
$app['menu_enabled'] = FALSE;

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_only'] = TRUE;

$app['core_requires'] = array(
    'initscripts',
);

$app['core_directory_manifest'] = array(
    '/store' => array(),
    '/etc/clearos/storage.d' => array(),
    '/var/clearos/storage' => array(),
    '/var/clearos/storage/plugins' => array(),
);

// TODO: make storage.conf noreplace?

$app['core_file_manifest'] = array( 
    'storage.conf' => array ( 
        'target' => '/etc/clearos/storage.conf' 
    ),
    'storage.init' => array ( 
        'target' => '/etc/rc.d/init.d/storage',
        'mode' => '0755',
    ),
    'storage' => array ( 
        'target' => '/usr/sbin/storage',
        'mode' => '0755',
    ),
);
