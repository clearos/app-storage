<?php

/**
 * Create data drive view.
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
// Form
///////////////////////////////////////////////////////////////////////////////

$device_encoded = strtr(base64_encode($device),  '+/=', '-_.');

echo form_open('storage/devices/create_data_drive/' . $device_encoded);
echo form_header(lang('base_create'));

echo fieldset_header(lang('storage_device'));
echo field_view(lang('storage_device'), $device, lang('storage_size'));
echo field_input('size', $details['size'] . ' ' . $details['size_units'], lang('storage_size'), TRUE);
echo field_input('identpifier', $details['identifier'], lang('storage_model'), TRUE);
echo fieldset_footer();

echo fieldset_header(lang('storage_device'));
echo field_dropdown('type', $types, $type, lang('storage_file_system'));
echo fieldset_footer();

echo field_button_set(
    array(
        form_submit_custom('submit', lang('base_create')),
        anchor_cancel('/app/storage/devices')
    )
);

echo form_footer();
echo form_close();
