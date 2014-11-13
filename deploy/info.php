<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'storage';
$app['version'] = '1.6.7';
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

/////////////////////////////////////////////////////////////////////////////
// Controllers
/////////////////////////////////////////////////////////////////////////////

$app['controllers']['storage']['title'] = $app['name'];
$app['controllers']['devices']['title'] = lang('storage_devices');
$app['controllers']['mappings']['title'] = lang('storage_mappings');

// Wizard extras
$app['controllers']['storage']['wizard_name'] = lang('storage_app_name');
$app['controllers']['storage']['wizard_description'] = lang('storage_wizard_help');
$app['controllers']['storage']['inline_help'] = array(
    lang('storage_big_picture') => lang('storage_inline_help_overview'),
    lang('base_user_guide') => lang('storage_user_guide_preamble') . 
        "<p><a target='_blank' href='http://www.clearcenter.com/redirect/ClearOS/6.2.0/userguide/storage'>" . lang('storage_storage_manager_guide') . "</a></p>",
);

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_requires'] = array(
    'app-base >= 1:1.4.31',
    'app-events-core',
    'csplugin-filewatch',
    'initscripts',
    'parted',
    'util-linux-ng',
);

$app['core_directory_manifest'] = array(
    '/store' => array(),
    '/etc/clearos/storage.d' => array(),
    '/var/clearos/events/storage' => array(),
    '/var/clearos/storage' => array(),
    '/var/clearos/storage/plugins' => array(),
    '/var/clearos/storage/state' => array(),
    '/var/clearos/storage/lock' => array(
        'mode' => '0775',
        'owner' => 'root',
        'group' => 'webconfig',
    ),
);

// TODO: make storage.conf noreplace?

$app['core_file_manifest'] = array( 
    'filewatch-storage-event.conf' => array('target' => '/etc/clearsync.d/filewatch-storage-event.conf'),
    'app-storage-create' => array ( 
        'target' => '/usr/sbin/app-storage-create',
        'mode' => '0755',
    ),
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
    'system-database-event'=> array(
        'target' => '/var/clearos/events/storage/system-database',
        'mode' => '0755'
    ),
);
