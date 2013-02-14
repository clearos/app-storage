<?php

/**
 * Devices summary view.
 *
 * @category   ClearOS
 * @package    Storage
 * @subpackage Views
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
// Headers
///////////////////////////////////////////////////////////////////////////////

$headers = array(
    lang('storage_device'),
    lang('storage_model'),
    lang('storage_size'),
    lang('storage_in_use')
);

///////////////////////////////////////////////////////////////////////////////
// Anchors 
///////////////////////////////////////////////////////////////////////////////

$anchors = array();

///////////////////////////////////////////////////////////////////////////////
// Items
///////////////////////////////////////////////////////////////////////////////

foreach ($devices as $device => $details) {
    $device_encoded = strtr(base64_encode($device),  '+/=', '-_.');

    // Skip removable drives
    if ($details['removable'])
        continue;

    // TODO: discuss icon strategy
    $in_use_icon = ($details['in_use']) ? '<span class="theme-icon-ok"> </span>' : '';

    $item['title'] = $device;
    $item['action'] = '';
    $item['anchors'] = button_set(
        array(anchor_custom('/app/storage/devices/view/' . $device_encoded, lang('base_view_details')))
    );
    $item['details'] = array(
        $device,
        $details['identifier'],
        $details['size'] . ' ' . $details['size_units'],
        $in_use_icon
    );

    $items[] = $item;
}

sort($items);

///////////////////////////////////////////////////////////////////////////////
// Summary table
///////////////////////////////////////////////////////////////////////////////

echo summary_table(
    lang('storage_devices'),
    $anchors,
    $headers,
    $items
);
