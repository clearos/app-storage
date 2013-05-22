<?php

/**
 * Storage javascript helper.
 *
 * @category   apps
 * @package    storage
 * @subpackage javascript
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
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('base');
clearos_load_language('storage');

///////////////////////////////////////////////////////////////////////////////
// J A V A S C R I P T
///////////////////////////////////////////////////////////////////////////////

header('Content-Type:application/x-javascript');
?>

$(document).ready(function() {

    // Translations
    //-------------

    lang_initializing = '<?php echo lang("storage_initializing_storage_warning"); ?>';

    // Wizard next button handling
    //----------------------------

    $("#wizard_nav_next").click(function(){
        window.location = '/app/storage/devices/set_initialized';
    });

/*
    if (($(location).attr('href').match('.*\/devices$') == null)) {
        $('#theme_wizard_nav_next').hide();
        $('#theme_wizard_nav_previous').hide();
    }
*/

    // Manage storage action
    //----------------------

    if ($("#storage_status").length != 0) {
        getStorageStatus();
    }
});

///////////////////////////////////////////////////////////////////////////////
// F U N C T I O N S
///////////////////////////////////////////////////////////////////////////////

/**
 * Returns storage status.
 */

function getStorageStatus() {
    $.ajax({
        url: '/app/storage/devices/get_data_drive_state',
        method: 'GET',
        dataType: 'json',
        success : function(payload) {
            window.setTimeout(getStorageStatus, 2000);
            showStorageStatus(payload);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            window.setTimeout(getStorageStatus, 2000);
        }

    });
}

/**
 * Displays directory information from Ajax request.
 */

function showStorageStatus(payload) {
    if (payload.state == 'initializing')
        $("#storage_status").html('<div class="theme-loading-normal">' + lang_initializing + '</div>');
    else
        window.location = '/app/storage/devices';
}

// vim: ts=4 syntax=javascript
