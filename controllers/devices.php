<?php

/**
 * Storage devices controller.
 *
 * @category   apps
 * @package    storage
 * @subpackage controllers
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
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Storage devices controller.
 *
 * @category   apps
 * @package    storage
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/storage/
 */

class Devices extends ClearOS_Controller
{
    /**
     * Storage mappings overview.
     *
     * @return view
     */

    function index()
    {
        // Load libraries
        //---------------

        $this->lang->load('storage');
        $this->load->library('storage/Storage_Device');

        // Load view data
        //---------------

        try {
            $data['devices'] = $this->storage_device->get_devices();
            $found = $this->storage_device->find_obvious_storage_device();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        // If wizard and there's disk ready for storage (e.g. Amazon EBS), then 
        // jump right to the create page.

        $no_redirect = $this->session->userdata('storage_noredirect') ? TRUE : FALSE;

        if ($this->session->userdata('wizard') && !$no_redirect && !empty($found)) {
            $this->session->set_userdata('storage_noredirect', TRUE);
            redirect('/storage/devices/view/' . strtr(base64_encode($found),  '+/=', '-_.'));
        } else {
            $this->page->view_form('devices/summary', $data, lang('storage_devices'));
        }
    }

    /**
     * Creates a data drive.
     *
     * @param string $device encoded device name
     *
     * @return view
     */

    function create_data_drive($device)
    {
        $device_decoded = base64_decode(strtr($device, '-_.', '+/='));

        // Load libraries
        //---------------

        $this->lang->load('storage');
        $this->load->library('storage/Storage_Device');

        // Handle form submit
        //-------------------

        if ($this->input->post('submit')) {
            try {
                $this->storage_device->run_create_data_drive($device_decoded, $this->input->post('type'));

                $this->page->set_status_added();
            } catch (Exception $e) {
                $this->page->view_exception($e);
                return;
            }
        }

        // Load the views
        //---------------

        $this->page->view_form('create', $data, lang('base_create'));
    }

    /**
     * Initializes storage engine.
     *
     * @return void.
     */

    function set_initialized()
    {
        // Load dependencies
        //------------------

        $this->load->library('storage/Storage');

        // Set initialized
        //----------------

        $this->storage->set_initialized();
        redirect($this->session->userdata['wizard_redirect']);
    }

    /**
     * Returns create data store state.
     *
     * @return JSON crate data store state
     */

    function get_data_drive_state()
    {
        // Load dependencies
        //------------------

        $this->load->library('storage/Storage_Device');

        // Get state
        //----------

        try {
            $data['state'] = $this->storage_device->get_data_drive_state();
            $data['error_code'] = 0;
        } catch (Exception $e) {
            $data['error_code'] = clearos_exception_code($e);
            $data['error_message'] = clearos_exception_message($e);
        }

        // Return state
        //-------------

        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Fri, 01 Jan 2010 05:00:00 GMT');
        header('Content-type: application/json');

        $this->output->set_output(json_encode($data));
    }


    /**
     * Storage detail view.
     *
     * @param string $device encoded device name
     *
     * @return view
     */

    function view($device)
    {
        // Load libraries
        //---------------

        $this->lang->load('storage');
        $this->load->library('storage/Storage_Device');
        $this->load->library('storage/Storage');

        // Load view data
        //---------------

        try {
            $data['device'] = base64_decode(strtr($device, '-_.', '+/='));
            $data['details'] = $this->storage_device->get_device_details($data['device']);
            $data['types'] = $this->storage_device->get_file_system_types();
            $data['storage_base'] = $this->storage->get_base();

            // Set default
            $data['type'] = 'ext4';
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('devices/item', $data, lang('storage_store'));
    }
}
