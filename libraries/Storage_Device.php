<?php

/**
 * Storage device class.
 *
 * Class to assist in the discovery of mass storage devices on the server.
 *
 * @category   apps
 * @package    storage
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/pptpd/
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

clearos_load_language('stroarge');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\base\Shell as Shell;
use \clearos\apps\storage\Storage as Storage;

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Shell');
clearos_load_library('storage/Storage');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;
use \clearos\apps\base\Validation_Exception as Validation_Exception;

clearos_load_library('base/Engine_Exception');
clearos_load_library('base/Validation_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Storage device class.
 *
 * Class to assist in the discovery of mass storage devices on the server.
 *
 * @category   apps
 * @package    storage
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/pptpd/
 */

class Storage_Device extends Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const FILE_MDSTAT = '/proc/mdstat';
    const FILE_MTAB = '/etc/mtab';
    const FILE_FSTAB = '/etc/fstab';
    const FILE_CREATE_LOG = 'storage_create.log';
    const FILE_INITIALIZING = '/var/clearos/storage/lock/initializing';
    const PATH_IDE = '/proc/ide';
    const PATH_IDE_DEVICES = '/sys/bus/ide/devices';
    const PATH_USB_DEVICES = '/sys/bus/usb/devices';
    const PATH_SCSI_DEVICES = '/sys/bus/scsi/devices';
    const PATH_XEN_DEVICES = '/sys/bus/xen/drivers/vbd';

    const COMMAND_MOUNT = '/bin/mount';
    const COMMAND_PARTED = '/sbin/parted';
    const COMMAND_SFDISK = '/sbin/sfdisk';
    const COMMAND_SWAPON = '/sbin/swapon -s %s';
    const COMMAND_MKFS_EXT3 = '/sbin/mkfs.ext3';
    const COMMAND_MKFS_EXT4 = '/sbin/mkfs.ext4';
    const COMMAND_STORAGE_CREATE = '/usr/sbin/app-storage-create';

    const STATUS_INITIALIZED = 'initialized';
    const STATUS_INITIALIZING = 'initializing';
    const STATUS_UNINITIALIZED = 'uninitialized';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $devices = array();
    protected $is_scanned = FALSE;
    protected $types = array();
    protected $file_system_types = array();

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Storage device constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->file_system_types = array(
            'ext3' => 'ext3',
            'ext4' => 'ext4',
        );

        $this->types = array(
            '82' => lang('storage_swap'),
            '83' => 'Linux',
            '8e' => 'LVM',
            '85' => lang('storage_linux_extended'),
        );
    }

    /**
     * Creates data drive.
     *
     * @param string $device device
     * @param string $type   file system type
     *
     * @return array storage devices
     * @throws Engine_Exception
     */

    public function create_data_drive($device, $type)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_device($device));
        Validation_Exception::is_valid($this->validate_file_system_type($type));

        // Lock state file
        //----------------

        $lock_file = new File(self::FILE_INITIALIZING);
        $initializing_lock = fopen(self::FILE_INITIALIZING, 'w');

        if (!flock($initializing_lock, LOCK_EX | LOCK_NB)) {
            clearos_log('storage', 'storage creation is already running');
            return;
        }

        try {
            // Initialize create log
            //----------------------

            $file = new File(CLEAROS_TEMP_DIR . '/' . self::FILE_CREATE_LOG);

            if ($file->exists())
                $file->delete();

            // Run mkfs
            //---------

            clearos_log('storage', 'creating data store: ' . $device);

            $shell = new Shell();

            $options['validate_exit_code'] = FALSE;
            $options['log'] = self::FILE_CREATE_LOG;

            if ($type === 'ext4') 
                $retval = $shell->execute(self::COMMAND_MKFS_EXT4, '-F ' . $device, TRUE, $options);
            else if ($type === 'ext3') 
                $retval = $shell->execute(self::COMMAND_MKFS_EXT3, '-F ' . $device, TRUE, $options);

            // Add fstab entry
            //----------------

            $storage = new Storage();
            $storage_base = $storage->get_base();

            $file = new File(self::FILE_FSTAB);

            $entry = sprintf("%-23s %-23s %-7s %-15s %s %s\n", $device, $storage_base, $type, 'defaults', '1', '2');
            $file->add_lines($entry);

            // Do mount
            //---------

            $shell = new Shell();
            $shell->execute(self::COMMAND_MOUNT, $device . ' ' . $storage_base, TRUE);

            $storage->do_mount();
        } catch (Exception $e) {
            $lock_file->delete();
             throw new Engine_Exception(clearos_exception_message($e));
        }

        // Cleanup file / file lock
        //-------------------------

        flock($initializing_lock, LOCK_UN);
        fclose($initializing_lock);

        if ($lock_file->exists())
            $lock_file->delete();
    }

    /**
     * Returns obvious storage device.
     *
     * In some circumstances (e.g. ClearBOX, Cloud instances), there is a
     * single unformatted disk ready for use.  This method can be used to 
     * handle this scenario.
     *
     * @return string data drive state
     * @throws Engine_Exception
     */

    public function find_obvious_storage_device()
    {
        clearos_profile(__METHOD__, __LINE__);

        $devices = $this->get_devices();

        $count = 0;

        foreach ($devices as $device => $details) {
            if (!$details['in_use'] && !$details['is_store'] && !$details['removable']) {
                $count++;
                $obvious = $device;
            }
        }

        if ($count === 1)
            return $obvious;
        else
            return '';
    }

    /**
     * Returns data drive state.
     *
     * @return string data drive state
     * @throws Engine_Exception
     */

    public function get_data_drive_state()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Check initializing
        //-------------------

        $file = new File(self::FILE_INITIALIZING);

        if ($file->exists()) {
            $initializing_lock = fopen(self::FILE_INITIALIZING, 'r');

            if (!flock($initializing_lock, LOCK_SH | LOCK_NB))
                return self::STATUS_INITIALIZING;
        }

        // Check initialized
        //------------------

        $devices = $this->get_devices();
        foreach ($devices as $device => $details) {
            if ($details['is_store'])
                return self::STATUS_INITIALIZED;
        }

        return self::STATUS_UNINITIALIZED;
    }

    /**
     * Returns information on storage device.
     *
     * @param string $device device
     *
     * @return array storage device information
     * @throws Engine_Exception
     */

    public function get_device_details($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_device($device));

        if (! $this->is_scanned)
            $this->_scan();

        return $this->devices[$device];
    }

    /**
     * Returns information on all storage devices.
     *
     * @return array storage devices
     * @throws Engine_Exception
     */

    public function get_devices()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_scanned)
            $this->_scan();

        return $this->devices;
    }

    /**
     * Returns supported file system types.
     *
     * @return array file system types
     * @throws Engine_Exception
     */

    public function get_file_system_types()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->file_system_types;
    }

    /** 
     * Returns mount point of device if one exists.
     *
     * @param string $device device name
     *
     * @return string mount point of given device if one exists
     * @throws Engine_Exception
     */

    public function get_mount_point($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $mounts = $this->get_mounts();

        if (array_key_exists($device, $mounts))
            return $mounts[$device]['mount_point'];
    }

    /** 
     * Returns mount information.
     *
     * @return array
     * @throws Engine_Exception
     */

    public function get_mounts()
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::FILE_MTAB);

        if (!$file->exists())
            return array();

        $mounts = array();
        $lines = $file->get_contents_as_array();

        foreach ($lines as $line) {
            $details = preg_split('/\s+/', $line);
            $mounts[$details[0]]['mount_point'] = $details[1];
            $mounts[$details[0]]['file_system'] = $details[2];
            $mounts[$details[0]]['options'] = $details[3];
        }
    
        return $mounts;
    }

    /**
     * Returns partition table.
     *
     * @param string $device device
     *
     * @return array partition table information
     * @throws Engine_Exception
     */

    public function get_partition_info($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load information from sfdisk if no partitions
        //----------------------------------------------

        $options['validate_exit_code'] = FALSE;
        $options['env'] = 'LANG=en_US';

        $shell = new Shell();
        $retval = $shell->execute(self::COMMAND_SFDISK, '-d ' . $device, TRUE, $options);
        $lines = $shell->get_output();

        if ($retval != 0) {
            // Must be a better way to detect CD-ROM devices with no disks
            foreach ($lines as $line) {
                if (preg_match('/No medium found/', $line))
                    return array();
            }

            $has_partitions = FALSE;
        } else {
            $has_partitions = (empty($lines)) ? FALSE : TRUE;
        }

        // Load information from parted if partitions exist
        //-------------------------------------------------
        // The parted command shows partitionless disks the same way
        // a single partition is shown (though the partition type is "loop"
        // instead of "msdos").  For now, we'll identify this whole disk
        // partitioning as "partition 0" in the partition list.

        // Get mount information
        $mounts = $this->get_mounts();

        // Run parted
        try {
            $shell = new Shell();
            $shell->execute(self::COMMAND_PARTED, '-m ' . $device . ' print', TRUE);
        } catch (Exception $e) {
            $details['interface'] = '';
            $details['logical_size'] = '';
            $details['physical_size'] = '';
            $details['partition_format'] = '';

            return $details;
        }

        $lines = $shell->get_output();

        $device_regex = preg_quote($device, '/');

        foreach ($lines as $line) {
            $matches = array();

            if (preg_match("/^$device_regex:([^:]*):([^:]*):([^:]*):([^:]*):([^:]*):([^:]*);/", $line, $matches)) {
                $details['table']['interface'] = $matches[2];
                $details['table']['logical_size'] = $matches[3];
                $details['table']['physical_size'] = $matches[4];
                $details['table']['partition'] = $matches[5];

            } else if (preg_match("/^([0-9]*):([^:]*):([^:]*):([^:]*):([^:]*):([^:]*):([^:]*);/", $line, $matches)) {
                if ($has_partitions) {
                    $partition_num = $matches[1];
                    $partition_device = $device . $partition_num;
                } else {
                    $partition_num = 0;
                    $partition_device = $device;
                }

                $details['partitions'][$partition_num]['start'] = $matches[2];
                $details['partitions'][$partition_num]['end'] = $matches[3];
                $details['partitions'][$partition_num]['size'] = preg_replace('/[A-Za-z]*$/', '', $matches[4]);
                $details['partitions'][$partition_num]['size_units'] = preg_replace('/^[0-9\.]*/', '', $matches[4]);
                $details['partitions'][$partition_num]['file_system'] = $matches[5];
                $details['partitions'][$partition_num]['unknown'] = $matches[6];
                $details['partitions'][$partition_num]['flags'] = $matches[7];

                $details['partitions'][$partition_num]['is_lvm'] = (preg_match('/lvm/', $matches[7])) ? TRUE : FALSE;
                $details['partitions'][$partition_num]['is_bootable'] = (preg_match('/boot/', $matches[7])) ? TRUE : FALSE;

                if (array_key_exists($partition_device, $mounts)) {
                    $details['partitions'][$partition_num]['mount_point'] = $mounts[$partition_device]['mount_point'];
                    $details['partitions'][$partition_num]['is_mounted'] = TRUE;
                } else {
                    $details['partitions'][$partition_num]['mount_point'] = NULL;
                    $details['partitions'][$partition_num]['is_mounted'] = FALSE;
                }
            }
        }

        return $details;
    }

    /** 
     * Returns software RAID devices.
     *
     * @return array
     * @throws Engine_Exception
     */

    public function get_software_raid_devices()
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!($fh = fopen(self::FILE_MDSTAT, 'r')))
            return FALSE;

        $devices = array();

        while (!feof($fh)) {
            $matches = array();

            if (!preg_match('/^(md[0-9]+)\s+:\s+(\w+)\s+(\w+)\s+(.*$)/', chop(fgets($fh, 8192)), $matches))
                continue;

            $device = array();
            $device['status'] = $matches[2];
            $device['type'] = strtoupper($matches[3]);
            $nodes = explode(' ', $matches[4]);

            foreach ($nodes as $node)
                $device['node'][] = '/dev/' . preg_replace('/\[[0-9]+\]/', '', $node);

            $devices['/dev/' . $matches[1]] = $device;
        }

        fclose($fh);

        return $devices;
    }

    /** 
     * Checks state of swap device.
     *
     * @param string $device device name
     *
     * @return boolean state of swap device
     * @throws Engine_Exception
     */

    public function is_swap($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!($ph = popen(sprintf(self::COMMAND_SWAPON, $device), 'r')))
            return FALSE;

        while (!feof($ph)) {
            list($name) = explode(' ', fgets($ph, 4096));
            if ($name == $device) {
                pclose($ph);
                return TRUE;
            }
        }

        pclose($ph);

        return FALSE;
    }

    /**
     * Checks if device is software RAID device.
     *
     * @param string $device device name
     *
     * @return boolean TRUE if software RAID device
     * @throws Engine_Exception
     */

    public function is_software_raid_device($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $raid = $this->get_software_raid_devices();

        if (array_key_exists($device, $raid))
            return TRUE;

        return FALSE;
    }

    /**
     * Checks if device is software RAID node.
     *
     * @param string $device device name
     *
     * @return boolean TRUE if software RAID node
     * @throws Engine_Exception
     */

    public function is_software_raid_node($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $raid = $this->get_software_raid_devices();
        foreach ($raid as $dev) {
            if (in_array($device, $dev['node']))
                return TRUE;
        }
        return FALSE;
    }

    /**
     * Runs create data drive.
     *
     * @param string $device device
     * @param string $type   file system type
     *
     * @return array storage devices
     * @throws Engine_Exception
     */

    public function run_create_data_drive($device, $type)
    {
        clearos_profile(__METHOD__, __LINE__);

        Validation_Exception::is_valid($this->validate_device($device));
        Validation_Exception::is_valid($this->validate_file_system_type($type));

        $options['background'] = TRUE;

        $shell = new Shell();
        $shell->execute(self::COMMAND_STORAGE_CREATE, '-d ' . $device . ' -t ' . $type, TRUE, $options);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // V A L I D A T I O N   R O U T I N E S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Validation routine for device.
     *
     * @param string $device device
     *
     * @return string error message if device is invalid
     */

    public function validate_device($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $devices = $this->get_devices();

        if (!array_key_exists($device, $devices))
            return lang('storage_device_invalid');
    }

    /**
     * Validation routine for file system type.
     *
     * @param string $type file system type
     *
     * @return string error message if file system type is invalid
     */

    public function validate_file_system_type($type)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (!array_key_exists($type, $this->file_system_types))
            return lang('storage_file_system_type_invalid');
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Scans for devices.
     *   
     * @access private
     * @return void
     */

    private function _scan()
    {
        clearos_profile(__METHOD__, __LINE__);

        // The "model" and "vendor" provided by drivers shows unexpected results, e.g.
        //
        // A VirtualBox CD-ROM:
        //   [vendor] => VBOX
        //   [model] => CD-ROM
        //
        // A VirtualBox hard disk:
        //   [vendor] => ATA
        //   [model] => VBOX HARDDISK
        //
        // Create an "indentifier" field that munges the two together.
        
        // ATAPI Scan
        //-----------

        $scan = $this->_scan_atapi();

        $atapi = array();

        foreach ($scan as $parent => $device) {
            if (!isset($device['partition']))
                continue;

            foreach ($device['partition'] as $partition) {
                $atapi[$partition]['vendor'] = $device['vendor'];
                $atapi[$partition]['model'] = $device['model'];
                $atapi[$partition]['identifier'] = $device['vendor'] . ' ' . $device['model'];
                $atapi[$partition]['type'] = $device['type'];
                $atapi[$partition]['parent'] = $parent;
            }
        }

        // SCSI and Xen Scan
        //------------------

        // TODO: not sure if Xen and SCSI should really be combined here.

        $scsi_scan = $this->_scan_scsi();
        $xen_scan = $this->_scan_xen();

        $scan = array_merge($scsi_scan, $xen_scan);

        $scsi = array();

        foreach ($scan as $device) {
            if (!isset($device['partition'])) {
                $scsi[$device['device']]['vendor'] = $device['vendor'];
                $scsi[$device['device']]['model'] = $device['model'];
                $scsi[$device['device']]['removable'] = $device['removable'];
                $scsi[$device['device']]['identifier'] = $device['vendor'] . ' ' . $device['model'];

                if ($device['size_in_blocks'] > 1000000) {
                    $scsi[$device['device']]['size'] = round(($device['size_in_blocks'] * 512) / (1000000000));
                    $scsi[$device['device']]['size_units'] = lang('base_gigabytes');
                } else {
                    $scsi[$device['device']]['size'] = round(($device['size_in_blocks'] * 512) / 1000000);
                    $scsi[$device['device']]['size_units'] = lang('base_megabytes');
                }

                if ($device['bus'] == 'usb')
                    $scsi[$device['device']]['type'] = 'USB';
                else
                    $scsi[$device['device']]['type'] = 'SCSI/SATA';
            }
        }

        $this->devices = array_merge($atapi, $scsi);

        // Software RAID
        //--------------

        $raid_devices = $this->get_software_raid_devices();
        $purge = array();

        foreach ($this->devices as $device => $details) {
            foreach ($raid_devices as $raid) {
                if (!in_array($device, $raid['node']))
                    continue;
                $purge[] = $device;
            }
        }

        foreach ($purge as $device)
            unset($this->devices[$device]);

        $purge = array();

        foreach ($raid_devices as $device => $details) {
            $this->devices[$device]['vendor'] = 'Software';
            $this->devices[$device]['model'] = 'RAID';
            $this->devices[$device]['identifier'] = 'Software RAID';
            $this->devices[$device]['type'] = $details['type'];

            $sys_device = preg_replace('/^\/dev\//', '', $device);
            if ($fh = fopen("/sys/block/$sys_device/size", 'r')) {
                $size_in_blocks = chop(fgets($fh, 4096));
                if ($size_in_blocks > 1000000) {
                    $this->devices[$device]['size'] = round(($size_in_blocks * 512) / (1000000000));
                    $this->devices[$device]['size_units'] = lang('base_gigabytes');
                } else {
                    $this->devices[$device]['size'] = round(($size_in_blocks * 512) / 1000000);
                    $this->devices[$device]['size_units'] = lang('base_megabytes');
                }
            } else {
                $this->devices[$device]['size'] = '';
                $this->devices[$device]['size_units'] = lang('base_gigabytes');
            }
        }

        // Add partition information
        // Add "in_use" flag (i.e. check if any partition is in use)
        // Add "is_store" flag (i.e. check if any partition is /store)
        //------------------------------------------------------------

        $storage = new Storage();
        $mount_points = $storage->get_mount_points();

        foreach ($this->devices as $device => $details) {
            $this->devices[$device]['partitioning'] = $this->get_partition_info($device);
            $this->devices[$device]['in_use'] = FALSE;
            $this->devices[$device]['is_store'] = FALSE;

            if (!empty($this->devices[$device]['partitioning']['partitions'])) {
                foreach ($this->devices[$device]['partitioning']['partitions'] as $id => $details) {
                    // TODO: Amazon EC2 is bringing up a swap disk?  Avoid it for now.
                    if (($details['is_mounted']) || ($details['is_lvm']) || (preg_match('/linux-swap/', $details['file_system'])))
                        $this->devices[$device]['in_use'] = TRUE;
                    
                    if (in_array($details['mount_point'], $mount_points))
                        $this->devices[$device]['is_store'] = TRUE;
                }
            }
        }

        // Purge unwanted devices
        //-----------------------

        /*
        $purge = array();

        if ($hide_in_use) {
            foreach ($this->devices as $device => $details) {
                if (!$details['in_use'])
                    continue;

                $purge[] = $device;
            }
        }

        if ($hide_swap) {
            foreach ($this->devices as $device => $details) {
                if (!$this->is_swap($device))
                    continue;

                $purge[] = $device;
            }
        }

        foreach ($purge as $device)
            unset($this->devices[$device]);
        */

        ksort($this->devices);

        $this->is_scanned = TRUE;
    }

    /**
     * Scans ATAPI devices.
     *
     * @access private
     * @return array ATAPI devices
     */

    private function _scan_atapi()
    {
        clearos_profile(__METHOD__, __LINE__);

        $scan = array();

        // Find IDE devices that match: %d.%d
        $entries = $this->_scan_directory(self::PATH_IDE_DEVICES, '/^\d.\d$/');

        // Scan all ATAPI/IDE devices.
        foreach ($entries as $entry) {
            $path = self::PATH_IDE_DEVICES . "/$entry";
            $block_devices = $this->_scan_directory("$path/block", '/^dev$/');

            if (empty($block_devices)) {
                $block_devices = $this->_scan_directory($path, '/^block:.*$/');
                if (empty($block_devices))
                    continue;

                $path .= '/' . $block_devices[0];
            } else {
                $path .= '/block';
            }

            if (($block = basename(readlink("$path"))) === FALSE)
                continue;

            $info = array();
            $info['type'] = 'IDE/ATAPI';

            try {
                $file = new File(self::PATH_IDE . "/$block/model", TRUE);
                if ($file->exists())
                    list($info['vendor'], $info['model']) = preg_split('/ /', $file->get_contents(), 2);
            } catch (Exception $e) {
                clearos_log('storage', $e->GetMessage());
            }

            // Here we are looking for detected partitions
            $partitions = $this->_scan_directory($path, "/^$block\d$/");
            if (!empty($partitions)) {
                foreach($partitions as $partition)
                    $info['partition'][] = "/dev/$partition";
            }

            $scan["/dev/$block"] = $info;
        }

        return $scan;
    }
 
    /**
     * Scans SCSI devices.
     *
     * @access private
     * @return array ATAPI devices
     */

    private function _scan_scsi()
    {
        clearos_profile(__METHOD__, __LINE__);

        $devices = array();

        try {
            // Find USB devices that match: %d-%d
            $entries = $this->_scan_directory(self::PATH_USB_DEVICES, '/^\d-\d$/');

            // Walk through the expected USB -> SCSI /sys paths.
            foreach ($entries as $entry) {
                $path = self::PATH_USB_DEVICES . "/$entry";
                $devid = $this->_scan_directory($path, "/^$entry:\d\.\d$/");
                if (empty($devid))
                    continue;

                if (count($devid) != 1)
                    continue;

                $path .= '/' . $devid[0];
                $host = $this->_scan_directory($path, '/^host\d+$/');
                if (empty($host))
                    continue;

                if (count($host) != 1)
                    continue;

                $path .= '/' . $host[0];

                $target = $this->_scan_directory($path, '/^target\d+:\d:\d$/');
                if (empty($target))
                    continue;

                if (count($target) != 1)
                    continue;

                $path .= '/' . $target[0];

                $lun = $this->_scan_directory($path, '/^\d+:\d:\d:\d$/');
                if (empty($lun))
                    continue;

                if (count($lun) != 1)
                    continue;

                $path .= '/' . $lun[0];

                $dev = $this->_scan_directory("$path/block", '/^dev$/');
                if (empty($dev))
                    continue;

                if (count($dev) != 1)
                    continue;

                // Validate USB mass-storage device
                if (!($fh = fopen("$path/vendor", 'r')))
                    continue;

                $device['vendor'] = chop(fgets($fh, 4096));
                fclose($fh);

                if (!($fh = fopen("$path/model", 'r')))
                    continue;

                $device['model'] = chop(fgets($fh, 4096));
                fclose($fh);

                if (!($fh = fopen("$path/block/dev", 'r')))
                    continue;

                $device['nodes'] = chop(fgets($fh, 4096));
                fclose($fh);
                $device['path'] = $path;
                $device['bus'] = 'usb';

                // Valid device found (almost, continues below)...
                $devices[] = $device;
            }

            // Find SCSI devices that match: %d:%d:%d:%d
            $entries = $this->_scan_directory(self::PATH_SCSI_DEVICES, '/^\d:\d:\d:\d$/');

            // Scan all SCSI devices.
            if (! empty($entries)) {
                foreach ($entries as $entry) {
                    $block = 'block';
                    $path = self::PATH_SCSI_DEVICES . "/$entry";
                    $devname = $this->_scan_directory("$path/block", '/^[a-z0-9]*$/');

                    if (count($devname) != 1)
                        continue;

                    $dev = $this->_scan_directory("$path/block/" . $devname[0], '/^dev$/');

                    if (count($dev) != 1)
                        continue;

                    // Validate SCSI storage device
                    if (!($fh = fopen("$path/vendor", 'r')))
                        continue;

                    $device['vendor'] = chop(fgets($fh, 4096));
                    fclose($fh);

                    if (!($fh = fopen("$path/model", 'r')))
                        continue;

                    $device['model'] = chop(fgets($fh, 4096));
                    fclose($fh);

                    if (!($fh = fopen("$path/$block/" . $devname[0] . "/dev", 'r')))
                        continue;
    
                    $device['nodes'] = chop(fgets($fh, 4096));
                    fclose($fh);

                    if (!($fh = fopen("$path/$block/" . $devname[0] . "/size", 'r')))
                        continue;
    
                    $device['size_in_blocks'] = chop(fgets($fh, 4096));
                    fclose($fh);

                    if (!($fh = fopen("$path/$block/" . $devname[0] . "/removable", 'r')))
                        continue;
    
                    $device['removable'] = (chop(fgets($fh, 4096))) ? TRUE : FALSE;
                    fclose($fh);

                    $device['path'] = "$path/$block";
                    $device['bus'] = 'scsi';

                    // Valid device found (almost, continues below)...
                    $unique = TRUE;
                    foreach ($devices as $usb) {
                        if ($usb['nodes'] != $device['nodes'])
                            continue;
                        $unique = FALSE;
                        break;
                    }

                    if ($unique)
                        $devices[] = $device;
                }
            }

            if (count($devices)) {
                // Create a hashed array of all device nodes that match: /dev/s*
                // XXX: This can be fairly expensive, takes a few seconds to run.
                if (!($ph = popen('/usr/bin/stat -c 0x%t:0x%T:%n /dev/s*', 'r')))
                    throw new Engine_Exception("Error running stat command");

                $nodes = array();
                $major = '';
                $minor = '';
                
                while (!feof($ph)) {
                    $buffer = chop(fgets($ph, 4096));

                    if (sscanf($buffer, '%x:%x:', $major, $minor) != 2)
                        continue;

                    if ($major == 0)
                        continue;

                    $nodes["$major:$minor"] = substr($buffer, strrpos($buffer, ':') + 1);
                }

                // Clean exit?
                if (pclose($ph) != 0)
                    throw new Engine_Exception("Error running stat command");

                // Hopefully we can now find the TRUE device name for each
                // storage device found above.  Validation continues...
                foreach ($devices as $key => $device) {
                    if (!isset($nodes[$device['nodes']])) {
                        unset($devices[$key]);
                        continue;
                    }

                    // Set the block device
                    $devices[$key]['device'] = $nodes[$device['nodes']];

                    // Here we are looking for detected partitions
                    $partitions = $this->_scan_directory($device['path'], '/^' . basename($nodes[$device['nodes']]) . '\d$/');
                    if (! empty($partitions)) {
                        foreach($partitions as $partition)
                            $devices[$key]['partition'][] = dirname($nodes[$device['nodes']]) . '/' . $partition;
                    }

                    unset($devices[$key]['path']);
                    unset($devices[$key]['nodes']);
                }
            }
        } catch (Exception $e) {
            clearos_log('storage', $e->GetMessage());
        }

        return $devices;
    }

    /**
     * Scans Xen devices.
     *
     * @access private
     * @return array ATAPI devices
     */

    private function _scan_xen()
    {
        clearos_profile(__METHOD__, __LINE__);

        $devices = array();

        try {
            // Find Xen devices that match:vbd-x 
            $entries = $this->_scan_directory(self::PATH_XEN_DEVICES, '/^vbd-.*/');

            // Scan all Xen devices.
            if (! empty($entries)) {
                foreach ($entries as $entry) {
                    $block = 'block';
                    $path = self::PATH_XEN_DEVICES . "/$entry";
                    $devname = $this->_scan_directory("$path/block", '/^[a-z0-9]*$/');
                    $device['device'] = '/dev/' . $devname[0];

                    if (count($devname) != 1)
                        continue;

                    $dev = $this->_scan_directory("$path/block/" . $devname[0], '/^dev$/');

                    if (count($dev) != 1)
                        continue;

                    // Validate Xen storage device
                    if (file_exists("$path/vendor") && ($fh = fopen("$path/vendor", 'r'))) {
                        $device['vendor'] = chop(fgets($fh, 4096));
                        fclose($fh);
                    } else {
                        // TODO: a bit of a kludge to present Amazon volumes
                        $device['vendor'] = file_exists('/usr/clearos/apps/amazon_ec2') ? 'Amazon' : 'Xen';
                    }

                    if (file_exists("$path/model") && ($fh = fopen("$path/model", 'r'))) {
                        $device['model'] = chop(fgets($fh, 4096));
                        fclose($fh);
                    } else {
                        $device['model'] = 'Drive';
                    }

                    if (!($fh = fopen("$path/$block/" . $devname[0] . "/dev", 'r')))
                        continue;
    
                    $device['nodes'] = chop(fgets($fh, 4096));
                    fclose($fh);

                    if (!($fh = fopen("$path/$block/" . $devname[0] . "/size", 'r')))
                        continue;
    
                    $device['size_in_blocks'] = chop(fgets($fh, 4096));
                    fclose($fh);

                    if (!($fh = fopen("$path/$block/" . $devname[0] . "/removable", 'r')))
                        continue;
    
                    $device['removable'] = (chop(fgets($fh, 4096))) ? TRUE : FALSE;
                    fclose($fh);

                    $device['path'] = "$path/$block";
                    $device['bus'] = 'scsi';

                    // Valid device found (almost, continues below)...
                    $unique = TRUE;
                    foreach ($devices as $usb) {
                        if ($usb['nodes'] != $device['nodes'])
                            continue;
                        $unique = FALSE;
                        break;
                    }

                    if ($unique)
                        $devices[] = $device;
                }
            }
        } catch (Exception $e) {
            clearos_log('storage', $e->GetMessage());
        }

        return $devices;
    }

    /**
     * Scans a directory returning files that match the pattern.
     *
     * @param string $directory directory
     * @param string $pattern   file pattern
     *
     * @access private
     * @return array list of files
     */

    private function _scan_directory($directory, $pattern)
    {
        if (!file_exists($directory) || !($dh = opendir($directory)))
            return array();

        $matches = array();

        while (($file = readdir($dh)) !== FALSE) {
            if (!preg_match($pattern, $file))
                continue;
            $matches[] = $file;
        }

        closedir($dh);
        sort($matches);

        return $matches;
    }
}
