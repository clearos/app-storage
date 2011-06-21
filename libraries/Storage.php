<?php

/**
 * Storage class.
 *
 * @category   Apps
 * @package    Storage
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/storage/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\storage;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('storage');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Folder as Folder;
use \clearos\apps\base\Shell as Shell;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Folder');
clearos_load_library('base/Shell');

// Exceptions
//-----------

use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\File_Not_Found_Exception as File_Not_Found_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/File_Not_Found_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Storage class.
 *
 * @category   Apps
 * @package    Storage
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2003-2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/storage/
 */

class Storage extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = '/etc/clearos/storage.conf';
    const PATH_CONFIGLETS = '/etc/clearos/storage.d';
    const PATH_PLUGINS = '/var/clearos/storage/plugins';

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Storage constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    /**
     * Returns detailed plugin list.
     *
     * @return array plugin details
     * @throws Engine_Exception
     */

    public function get_plugins()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load base configuration
        //------------------------

        include self::FILE_CONFIG;

        // Load plugin configlets
        //-----------------------

        $folder = new Folder(self::PATH_PLUGINS);

        $plugin_list = $folder->get_listing();

        foreach ($plugin_list as $plugin_file) {
            if (! preg_match('/\.php$/', $plugin_file))
                continue;

            $plugin = array();
            $plugin_basename = preg_replace('/\.php/', '', $plugin_file);

            // Load base plugin information
            //-----------------------------

            $plugin = array();

            include self::PATH_PLUGINS . '/' . $plugin_file;

            $plugins[$plugin_basename]['info'] = $plugin;

            // Load plugin configuration
            //--------------------------

            $storage = array();

            if (file_exists(self::PATH_CONFIGLETS . '/' . $plugin_basename . '.conf')) {
                include self::PATH_CONFIGLETS . '/' . $plugin_basename . '.conf';
            } else if (file_exists(self::PATH_CONFIGLETS . '/' . $plugin_basename . '-cluster.conf')) {
                include self::PATH_CONFIGLETS . '/' . $plugin_basename . '-cluster.conf';
            } else if (file_exists(self::PATH_CONFIGLETS . '/' . $plugin_basename . '-default.conf')) {
                include self::PATH_CONFIGLETS . '/' . $plugin_basename . '-default.conf';
            }

            $plugins[$plugin_basename]['config'] = $storage;
        }

        return $plugins;
    }

    /**
     * Returns the system time (in seconds since Jan 1, 1970).
     *
     * @return integer system time in seconds since Jan 1, 1970
     */

    public function generate_fstab_entries()
    {
        clearos_profile(__METHOD__, __LINE__);

        $plugins = $this->get_plugins();

        $entries = "# Storage engine - start\n";

        foreach ($plugins as $plugin => $metadata) {
            foreach ($metadata['config'] as $source => $details) {
                $entries .= sprintf("%-23s %-23s %-7s %-15s %s %s\n", 
                    $details['target'], $source, 'none', 'bind,rw', '0', '0'
                );
            }
        }

        $entries .= "# Storage engine - end\n";

        return $entries;
    }
}
