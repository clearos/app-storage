<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'storage';
$app['version'] = '5.9.9.0';
$app['release'] = '1';
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

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_directory_manifest'] = array(
    '/etc/clearos/storage' => array(),
);

$app['core_file_manifest'] = array( 
   'homes.php' => array( 'target' => '/etc/clearos/storage/homes.php' ),
);
