<?php

/**
 * Storage class.
 *
 * @category   Apps
 * @package    Storage
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011-2012 ClearFoundation
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
 * @copyright  2011-2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/storage/
 */

class Storage extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_CONFIG = '/etc/clearos/storage.conf';
    const FILE_FSTAB = '/etc/fstab';
    const FILE_NEW_FSTAB = '/var/clearos/storage/fstab';
    const PATH_CONFIGLETS = '/etc/clearos/storage.d';
    const COMMAND_MOUNT = '/bin/mount';
    const DELIMITER_START = '# Storage engine - start';
    const DELIMITER_END = '# Storage engine - end';

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
     * Performs storage mounts.
     *
     */

    public function do_mount()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Check state
        //------------

        $config = $this->_get_config();

        if (!isset($config['enabled']) || !$config['enabled'])
            return;

        // Create mount definition
        //------------------------
        
        $configlets = $this->_get_configlets();
        $entries = array();
        $mounted = array('/var/lib/mysql'); // FIXME: implement this // FIXME: implement this // FIXME: implement this // FIXME: implement this

        foreach ($configlets as $plugin => $metadata) {
            foreach ($metadata['config'] as $source => $details) {

                $target = $details['base'] . '/' . $details['directory'];

                $base_folder = new Folder($details['base']);
                $target_folder = new Folder($target);
                $source_folder = new Folder($source);

                if (! $base_folder->exists()) {
                    clearos_log('storage', 'storage base does not exist: ' . $details['base']);
                    continue;
                } else if (! $source_folder->exists()) {
                    clearos_log('storage', 'storage mount point does not exist: ' . $source);
                    continue;
                } else if (!in_array($source, $mounted) && (count($source_folder->get_listing()) > 0)) {
                    clearos_log('storage', 'storage mount point is non-empty: ' . $source);
                    continue;
                } else if (! $target_folder->exists()) {
                    clearos_log('storage', 'creating storage target: ' . $target);
                    $target_folder->create(
                        $details['owner'],
                        $details['group'],
                        $details['permissions']
                    );
                } 

                $entries[] = sprintf("%-45s %-25s %-7s %-13s %s %s", $target, $source, 'none', 'bind,rw', '0', '0');

                try {
                    if (!in_array($source, $mounted)) {
                        clearos_log('storage', 'mounting: ' . $source . ' -> ' . $target);
                        $shell = new Shell();
                        $shell->execute(self::COMMAND_MOUNT, '--bind ' . $source . ' ' . $target, TRUE);
                    } else {
                        clearos_log('storage', 'mount already enabled: ' . $source . ' -> ' . $target);
                    }
                } catch (\Exception $e) {
                    clearos_log('storage', 'mount failed: ' . $source . ' -> ' . $target);
                }
            }
        }

        // Bail if nothing has changed
        //----------------------------

        $fstab = $this->_get_fstab_entries();

        if ($fstab === $entries)
            return;

        // Update fstab
        //-------------

        $file = new File(self::FILE_FSTAB);

        $lines = $file->get_contents_as_array();
        $inside = FALSE;
        $new_lines = array();

        foreach ($lines as $line) {
            if (preg_match('/^' . self::DELIMITER_END . '$/', $line))
                $inside = FALSE;
            else if (preg_match('/^' . self::DELIMITER_START . '$/', $line))
                $inside = TRUE;
            else if (!$inside)
                $new_lines[] = $line;
        }

        $entries[] = self::DELIMITER_END;
        array_unshift($entries, self::DELIMITER_START);

        $new_lines = array_merge($new_lines, $entries);

        $new_file = new File(self::FILE_NEW_FSTAB);

        if ($new_file->exists())
            $new_file->delete();

        $new_file->create('root', 'root', '0644');
        $new_file->dump_contents_from_array($new_lines);
        $new_file->copy_to(self::FILE_FSTAB);
    }

    /**
     * Returns the system time (in seconds since Jan 1, 1970).
     *
     * @return integer system time in seconds since Jan 1, 1970
     */

    public function generate_fstab_entries()
    {
        clearos_profile(__METHOD__, __LINE__);

        $config = $this->_get_configlets();

        $entries = "# Storage engine - start\n";

        foreach ($config as $plugin => $metadata) {
            foreach ($metadata['config'] as $source => $details) {
                $entries .= sprintf("%-45s %-25s %-7s %-13s %s %s\n", 
                    $details['target'], $source, 'none', 'bind,rw', '0', '0'
                );
            }
        }

        $entries .= "# Storage engine - end\n";

        return $entries;
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////
    
    /**
     * Returns configuration.
     *
     * @return array configuration
     * @throws Engine_Exception
     */

    protected function _get_config()
    {
        clearos_profile(__METHOD__, __LINE__);

        include self::FILE_CONFIG;

        return $config;
    }

    /**
     * Returns detailed configlets.
     *
     * @return array config details
     * @throws Engine_Exception
     */

    protected function _get_configlets()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Base configuration
        //-------------------

        $config = $this->_get_config();

        // $base is used in the configlet file
        $base = $config['base'];

        // Load configlets
        //----------------

        $folder = new Folder(self::PATH_CONFIGLETS);

        $configs_list = $folder->get_listing();

        $configlets = array();

        foreach ($configs_list as $configlet) {
            if (! preg_match('/_default\.conf$/', $configlet))
                continue;

            $basename = preg_replace('/_default\.conf/', '', $configlet);

            $storage = array();
            $info = array();

            include self::PATH_CONFIGLETS . '/' . $configlet;

            if (file_exists(self::PATH_CONFIGLETS . '/' . $basename . '-system.conf'))
                include self::PATH_CONFIGLETS . '/' . $basename . '-system.conf';
            else if (file_exists(self::PATH_CONFIGLETS . '/' . $basename . '.conf'))
                include self::PATH_CONFIGLETS . '/' . $basename . '.conf';

            $configlets[$basename]['info'] = $info;
            $configlets[$basename]['config'] = $storage;
        }

        return $configlets;
    }

    /**
     * Returns existing fstab entries
     *
     * @return string fstab entries
     * @throws Engine_Exception
     */

    protected function _get_fstab_entries()
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new FILE(self::FILE_FSTAB);

        $lines = $file->get_contents_as_array();

        $start = FALSE;
        $existing = array();

        foreach ($lines as $line) {
            if (preg_match('/^' . self::DELIMITER_END . '$/', $line))
                break;
            else if ($start)
                $existing[] = $line;
            else if (preg_match('/^' . self::DELIMITER_START . '$/', $line))
                $start = TRUE;
        }

        return $existing;
    }
}
