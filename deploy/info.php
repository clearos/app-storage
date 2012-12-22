<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'storage';
$app['version'] = '1.4.12';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('storage_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('storage_app_name');
$app['category'] = lang('base_category_system');
$app['subcategory'] = lang('base_subcategory_storage');

// Wizard extras
$app['controllers']['storage']['wizard_name'] = lang('storage_app_name');
$app['controllers']['storage']['wizard_description'] = lang('storage_wizard_help');
$app['controllers']['storage']['inline_help'] = array(
    lang('storage_big_picture') => lang('storage_inline_help_overview'),
    lang('base_user_guide') => lang('storage_user_guide_preamble'),
);

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_requires'] = array(
    'app-base >= 1:1.4.7',
    'initscripts',
);

$app['core_directory_manifest'] = array(
    '/store' => array(),
    '/etc/clearos/storage.d' => array(),
    '/var/clearos/storage' => array(),
    '/var/clearos/storage/plugins' => array(),
    '/var/clearos/storage/state' => array(),
);

// TODO: make storage.conf noreplace?

$app['core_file_manifest'] = array( 
    'storage.conf' => array ( 
        'target' => '/etc/clearos/storage.conf',
        'config' => TRUE,
        'config_params' => 'noreplace',
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
