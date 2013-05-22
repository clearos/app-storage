<?php

/**
 * Partition summary view.
 *
 * @category   apps
 * @package    storage
 * @subpackage views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/storage/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.  
//  
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// Load dependencies
///////////////////////////////////////////////////////////////////////////////

$this->lang->load('base');
$this->lang->load('storage');

///////////////////////////////////////////////////////////////////////////////
// General information
///////////////////////////////////////////////////////////////////////////////

$device_encoded = strtr(base64_encode($device),  '+/=', '-_.');

echo form_open('storage/devices/view');
echo form_header(lang('base_settings'));

echo field_view(lang('storage_device'), $device, lang('storage_size'));
echo field_input('size', $details['size'] . ' ' . $details['size_units'], lang('storage_size'), TRUE);
echo field_input('identifier', $details['identifier'], lang('storage_model'), TRUE);

echo form_footer();
echo form_close();

///////////////////////////////////////////////////////////////////////////////
// Show create partition if disk is partitionless and not mounted
///////////////////////////////////////////////////////////////////////////////

if (!$details['in_use']) {
    echo form_open('storage/devices/create_data_drive/' . $device_encoded);
    echo form_header(lang('base_create'));

    echo field_view(lang('storage_mount_point'), $storage_base);
    echo field_dropdown('type', $types, $type, lang('storage_file_system'));

    echo field_button_set(
        array(
            form_submit_custom('submit', lang('base_create')),
            anchor_cancel('/app/storage/devices')
        )
    );

    echo form_footer();
    echo form_close();
    return;
}

///////////////////////////////////////////////////////////////////////////////
// Partitions
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    '',
    lang('storage_size'),
    lang('storage_file_system'),
    lang('storage_bootable'),
    lang('storage_mount')
);

$anchors = array(anchor_custom('/app/storage/devices', lang('base_return_to_summary')));

foreach ($details['partitioning']['partitions'] as $id => $partition_info) {

    // TODO: discuss icon strategy
    $bootable_icon = ($partition_info['is_bootable']) ? '<span class="theme-icon-ok">&nbsp;</span>' : '';

    if (empty($partition_info['mount_point']))
        $mount = ($partition_info['is_lvm']) ? lang('storage_lvm') : '';
    else
        $mount = $partition_info['mount_point'];

    $item['title'] = $device;
    $item['action'] = '';
    $item['anchors'] = button_set(array());
    $item['details'] = array(
        $id,
        round($partition_info['size']) . ' ' . $partition_info['size_units'],
        $partition_info['file_system'],
        $bootable_icon,
        $mount,
    );

    $items[] = $item;
}

sort($items);

$options['no_action'] = TRUE;

echo summary_table(
    lang('storage_partitions'),
    $anchors,
    $headers,
    $items,
    $options
);
