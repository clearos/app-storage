<?php

/**
 * Storage mappings controller.
 *
 * @category   apps
 * @package    storage
 * @subpackage controllers
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
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Storage mappings controller.
 *
 * @category   apps
 * @package    storage
 * @subpackage controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/storage/
 */

class Mappings extends ClearOS_Controller
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
        $this->load->library('storage/Storage');

        // Load view data
        //---------------

        try {
            $data['mapping_details'] = $this->storage->get_mapping_details();
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('mappings/summary', $data, lang('storage_mappings'));
    }

    /**
     * Storage detail view.
     *
     * @param string $source encoded source name
     *
     * @return view
     */

    function view($source)
    {
        // Load libraries
        //---------------

        $this->lang->load('storage');
        $this->load->library('storage/Storage');

        // Load view data
        //---------------

        try {
            $data['source'] = base64_decode(strtr($source, '-_.', '+/='));
            $data['details'] = $this->storage->get_store_details($data['source']);
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('mappings/item', $data, lang('storage_store'));
    }
}
