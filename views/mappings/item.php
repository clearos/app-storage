<?php

/**
 * Mappings detail view.
 *
 * @category   apps
 * @package    storage
 * @subpackage views
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
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

echo form_open('storage/mappings/view');
echo form_header(lang('base_information'));

echo field_input('mapping', $details['plugin_name'], lang('storage_mapping'), TRUE);
echo field_input('source', $source, lang('storage_source'), TRUE);
echo field_input('store', $details['store'], lang('storage_store'), TRUE);
echo field_input('state_message', $details['state_message'], lang('base_status'), TRUE);

echo field_button_set(
    array(anchor_cancel('/app/storage/mappings'))
);

echo form_footer();
echo form_close();
