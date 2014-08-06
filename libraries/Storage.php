<?php

/**
 * Storage class.
 *
 * @category   apps
 * @package    storage
 * @subpackage libraries
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
 * @category   apps
 * @package    storage
 * @subpackage libraries
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
    const FILE_INITIALIZED = '/var/clearos/storage/initialized';
    const PATH_CONFIGLETS = '/etc/clearos/storage.d';
    const PATH_STATE = '/var/clearos/storage/state';
    const COMMAND_MOUNT = '/bin/mount';
    const COMMAND_FINDMNT = '/bin/findmnt';

    const DELIMITER_START = '# Storage engine - start';
    const DELIMITER_END = '# Storage engine - end';

    const STATE_BASE_NOT_EXIST = 'base_not_exist';
    const STATE_SOURCE_FOLDER_NOT_EXIST = 'source_folder_not_exist';
    const STATE_SOURCE_FOLDER_NON_EMPTY = 'source_folder_non_empty';
    const STATE_SOURCE_ALREADY_HAS_MOUNT = 'already_has_mount';
    const STATE_STORE_ACTIVE = 'active';
    const STATE_STORE_MOUNT_FAILED = 'mount_failed';
    const STATE_UNITIALIZED = 'unitialized';

    const STATE_LEVEL_GOOD = 'good';
    const STATE_LEVEL_BAD = 'bad';

    ///////////////////////////////////////////////////////////////////////////////
    // M E M B E R S
    ///////////////////////////////////////////////////////////////////////////////

    protected $states = array();

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Storage constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->states = array(
            self::STATE_BASE_NOT_EXIST => self::STATE_LEVEL_BAD,
            self::STATE_SOURCE_FOLDER_NOT_EXIST => self::STATE_LEVEL_BAD,
            self::STATE_SOURCE_FOLDER_NON_EMPTY => self::STATE_LEVEL_BAD,
            self::STATE_SOURCE_ALREADY_HAS_MOUNT => self::STATE_LEVEL_GOOD,
            self::STATE_STORE_ACTIVE => self::STATE_LEVEL_GOOD,
            self::STATE_STORE_MOUNT_FAILED => self::STATE_LEVEL_BAD,
            self::STATE_UNITIALIZED => self::STATE_LEVEL_BAD,
        );
    }

    /**
     * Performs storage mounts.
     *
     * @return void
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
        $mounted = $this->_get_mounted();

        foreach ($configlets as $plugin => $metadata) {
            foreach ($metadata['config'] as $source_name => $details) {

                $store_name = $details['base'] . '/' . $details['directory'];

                $store_base_folder = new Folder($details['base'], TRUE);
                $store_folder = new Folder($store_name, TRUE);
                $source_folder = new Folder($source_name, TRUE);

                if (! $store_base_folder->exists()) {
                    $this->_set_state($source_name, self::STATE_BASE_NOT_EXIST);
                    $this->_set_message($source_name, lang('storage_storage_base_does_not_exist:') . ' ' . $details['base']);
                    continue;
                } else if (! $source_folder->exists()) {
                    $this->_set_state($source_name, self::STATE_SOURCE_FOLDER_NOT_EXIST);
                    $this->_set_message($source_name, lang('storage_source_folder_does_not_exist:') . ' ' . $source_name);
                    continue;
                } else if (!array_key_exists($source_name, $mounted) && (count($source_folder->get_listing()) > 0)) {
                    $this->_set_state($source_name, self::STATE_SOURCE_FOLDER_NON_EMPTY);
                    $this->_set_message($source_name, lang('storage_source_folder_non_empty:') . ' ' . $source_name);
                    continue;
                } else if (array_key_exists($source_name, $mounted) && ($mounted[$source_name] != $store_name)) {
                    $this->_set_state($source_name, self::STATE_SOURCE_ALREADY_HAS_MOUNT);
                    $this->_set_message($source_name, lang('storage_source_already_has_mount_point:') . ' ' . $mounted[$source_name]);
                    continue;
                }

                if (! $store_folder->exists()) {
                    $this->_set_message($source_name, lang('storage_creating_store_point'));
                    $store_folder->create($details['owner'], $details['group'], $details['permissions']);
                }

                $entries[] = sprintf("%-45s %-25s %-7s %-13s %s %s", $store_name, $source_name, 'none', 'bind,rw', '0', '0');

                try {
                    if (!array_key_exists($source_name, $mounted)) {
                        $this->_set_message($source_name, lang('storage_mounting_store:') . ' ' . $source_name . ' -> ' . $store_name);
                        $shell = new Shell();
                        $shell->execute(self::COMMAND_MOUNT, '--bind ' . $store_name . ' ' . $source_name, TRUE);
                    }

                    $this->_set_state($source_name, self::STATE_STORE_ACTIVE);
                    $this->_set_message($source_name, lang('storage_store_active'));
                } catch (Exception $e) {
                    $this->_set_state($source_name, self::STATE_STORE_MOUNT_FAILED);
                    $this->_set_message($source_name, lang('storage_store_mount_failed:') . ' ' . $source_name . ' -> ' . $store_name);
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
     * Returns storage base.
     *
     * @return string storage base
     */

    public function get_base()
    {
        clearos_profile(__METHOD__, __LINE__);

        $config = $this->_get_config();

        return $config['base'];
    }

    /**
     * Returns storage mappings.
     *
     * @return array storage mappings
     */

    public function get_mapping_details()
    {
        clearos_profile(__METHOD__, __LINE__);

        $configlets = $this->_get_configlets();

        $mappings = array();

        foreach ($configlets as $basename => $details) {
            $mappings[$basename]['name'] = $details['info']['name'];

            foreach ($details['config'] as $source_name => $store_details) {
                $state = $this->get_state($source_name);
                $state_message = $this->get_state_message($source_name);

                $mappings[$basename]['mappings'][$source_name]['store'] = $store_details['base'] . '/' . $store_details['directory'];
                $mappings[$basename]['mappings'][$source_name]['state'] = $state;
                $mappings[$basename]['mappings'][$source_name]['state_message'] = $state_message;
                $mappings[$basename]['mappings'][$source_name]['state_level'] = $this->states[$state];
            }
        }

        return $mappings;
    }

    /**
     * Returns storage mount points.
     *
     * @return array storage mount points
     */

    public function get_mount_points()
    {
        clearos_profile(__METHOD__, __LINE__);

        $config = $this->_get_config();

        return array($config['base']);
    }

    /**
     * Returns details on store.
     *
     * @param string $store_name store name
     *
     * @return array store detail
     */

    public function get_store_details($store_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        $mapping_details = $this->get_mapping_details();
        $detail = array();

        foreach ($mapping_details as $basename => $mapping_details) {
            foreach ($mapping_details['mappings'] as $basename => $details) {
                if ($store_name == $basename) {
                    $detail = $details;
                    $detail['plugin_name'] = $mapping_details['name'];
                }
            }
        }

        return $detail;
    }

    /**
     * Sets initialized status.
     *
     * @return void
     */

    public function set_initialized()
    {
        clearos_profile(__METHOD__, __LINE__);

        $config = $this->_get_config();

        if (!isset($config['enabled']) || !$config['enabled'])
            return;

        $file = new File(self::FILE_INITIALIZED);

        if (!$file->exists())
            $file->create('root', 'root', '0644');
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

        $file = new File(self::FILE_FSTAB);

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

    /**
     * Returns mounts.
     *
     * @return array mounts
     * @throws Engine_Exception
     */

    protected function _get_mounted()
    {
        clearos_profile(__METHOD__, __LINE__);

        $shell = new Shell();
        $shell->execute(self::COMMAND_FINDMNT, '-s', TRUE);
        $raw_mounts = $shell->get_output();

        $mounts = array();

        foreach ($raw_mounts as $line) {
            $matches = array();
            if (preg_match('/([^\s]+)\s+([^\s]+)\s+/', $line, $matches))
                $mounts[$matches[1]] = $matches[2];
        }

        return $mounts;
    }

    /**
     * Get store state.
     *
     * @param string $source_name source name
     *
     * @return void
     * @throws Engine_Exception
     */

    public function get_state($source_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::PATH_STATE . '/' . preg_replace('/\//', '_', $source_name));

        if (!$file->exists())
            return self::STATE_UNITIALIZED;

        $lines = $file->get_contents_as_array();

        foreach ($lines as $line) {
            $matches = array();
            if (preg_match('/^state\s*=\s*(.*)/', $line, $matches))
                return $matches[1];
        }
    }

    /**
     * Get store state message.
     *
     * @param string $source_name source name
     *
     * @return void
     * @throws Engine_Exception
     */

    public function get_state_message($source_name)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::PATH_STATE . '/' . preg_replace('/\//', '_', $source_name));

        if (!$file->exists())
            return self::STATE_UNITIALIZED;

        $lines = $file->get_contents_as_array();

        foreach ($lines as $line) {
            $matches = array();
            if (preg_match('/^message\s*=\s*(.*)/', $line, $matches))
                return $matches[1];
        }
    }
    /**
     * Sets store message.
     *
     * @param string $source_name source name
     * @param string $message     message
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _set_message($source_name, $message)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::PATH_STATE . '/' . preg_replace('/\//', '_', $source_name));

        if (!$file->exists())
            $file->create('root', 'root', '0644');

        $match = $file->replace_lines('/^message\s*=\s*/', "message = $message\n");

        if (!$match)
            $file->add_lines("message = $message\n");
    }

    /**
     * Sets store state.
     *
     * @param string $source_name source name
     * @param string $state       state of store 
     *
     * @return void
     * @throws Engine_Exception
     */

    protected function _set_state($source_name, $state)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::PATH_STATE . '/' . preg_replace('/\//', '_', $source_name));

        if (!$file->exists())
            $file->create('root', 'root', '0644');

        $match = $file->replace_lines('/^state\s*=\s*/', "state = $state\n");

        if (!$match)
            $file->add_lines("state = $state\n");
    }
}
