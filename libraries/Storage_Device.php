<?php

/**
 * Storage device class.
 *
 * Class to assist in the discovery of mass storage devices on the server.
 *
 * @category   Apps
 * @package    Storage
 * @subpackage Libraries
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

clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('base/Shell');

// Exceptions
//-----------

use \Exception as Exception;
use \clearos\apps\base\Engine_Exception as Engine_Exception;

clearos_load_library('base/Engine_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Storage device class.
 *
 * Class to assist in the discovery of mass storage devices on the server.
 *
 * @category   Apps
 * @package    Storage
 * @subpackage Libraries
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
    const PATH_IDE = '/proc/ide';
    const PATH_IDE_DEVICES = '/sys/bus/ide/devices';
    const PATH_USB_DEVICES = '/sys/bus/usb/devices';
    const PATH_SCSI_DEVICES = '/sys/bus/scsi/devices';
    const COMMAND_PARTED = '/sbin/parted';
    const COMMAND_SFDISK = '/sbin/sfdisk';
    const COMMAND_SWAPON = '/sbin/swapon -s %s';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $devices = array();
    protected $is_scanned = FALSE;
    protected $mount_point = NULL;
    protected $types = array();

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Storage device constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        $this->types = array(
            '82' => lang('storage_swap'),
            '83' => 'Linux',
            '8e' => 'LVM',
            '85' => lang('storage_linux_extended'),
        );
    }

    /**
     * Retrieves a list of all storage devices.
     *
     * @param boolean $mounted mounted devices only
     * @param boolean $swap    swap devices
     *
     * @return array storage devices
     * @throws Engine_Exception
     */

    public function get_devices($mounted = TRUE, $swap = FALSE)
    {
        clearos_profile(__METHOD__, __LINE__);

        if (! $this->is_scanned)
            $this->_scan($mounted, $swap);

        return $this->devices;
    }

    /**
     * Retrieves mount point location set by last is_mounted() call.
     *
     * @return string mount point
     * @throws Engine_Exception
     */

    public function get_mount_point()
    {
        clearos_profile(__METHOD__, __LINE__);

        return $this->mount_point;
    }

    /**
     * Returns partition table.
     *
     * @param string $device device
     *
     * @return array partition table information
     * @throws Engine_Exception
     */

    function get_details($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Load hardware details from scan
        //--------------------------------

        $devices = $this->get_devices();

        $details = $devices[$device];

        // Load information from sfdisk if no partitions
        //----------------------------------------------

        $shell = new Shell();
        $shell->execute(self::COMMAND_SFDISK, '-d ' . $device, TRUE);
        
        $lines = $shell->get_output();

        if (empty($lines)) {
            // FIXME: todo
            $details['interface'] = $matches[2];
            $details['logical_size'] = $matches[3];
            $details['physical_size'] = $matches[4];
            $details['partition_format'] = $matches[5];

            return $details;
        }

        // Load information from parted if partitions exist
        //-------------------------------------------------

        // Get mount information
        $mounts = $this->get_mounts();

        // Run parted
        $shell = new Shell();
        $shell->execute(self::COMMAND_PARTED, '-m ' . $device . ' print', TRUE);

        $lines = $shell->get_output();

        $device_regex = preg_quote($device, '/');

        foreach ($lines as $line) {
            $matches = array();

            if (preg_match("/^$device_regex:([^:]*):([^:]*):([^:]*):([^:]*):([^:]*):([^:]*);/", $line, $matches)) {
                $details['interface'] = $matches[2];
                $details['logical_size'] = $matches[3];
                $details['physical_size'] = $matches[4];
                $details['partition_format'] = $matches[5];

            } else if (preg_match("/^([0-9]*):([^:]*):([^:]*):([^:]*):([^:]*):([^:]*):([^:]*);/", $line, $matches)) {
                $details['partitions'][$matches[1]]['start'] = $matches[2];
                $details['partitions'][$matches[1]]['end'] = $matches[3];
                $details['partitions'][$matches[1]]['size'] = preg_replace('/[A-Za-z]*$/', '', $matches[4]);
                $details['partitions'][$matches[1]]['size_units'] = preg_replace('/^[0-9\.]*/', '', $matches[4]);
                $details['partitions'][$matches[1]]['file_system'] = $matches[5];
                $details['partitions'][$matches[1]]['unknown'] = $matches[6];
                $details['partitions'][$matches[1]]['flags'] = $matches[7];

                $details['partitions'][$matches[1]]['is_lvm'] = (preg_match('/lvm/', $matches[7])) ? TRUE : FALSE;
                $details['partitions'][$matches[1]]['is_bootable'] = (preg_match('/boot/', $matches[7])) ? TRUE : FALSE;

                $partition_device = $device . $matches[1];

                if (array_key_exists($partition_device, $mounts))
                    $details['partitions'][$matches[1]]['mount_point'] = $mounts[$partition_device]['mount_point'];
                else
                    $details['partitions'][$matches[1]]['mount_point'] = NULL;
            }
        }

        return $details;
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
     * Checks state of mount.
     *
     * @param string $device device name
     *
     * @return boolean TRUE if mounted
     * @throws Engine_Exception
     */

    public function is_mounted($device)
    {
        clearos_profile(__METHOD__, __LINE__);

        $file = new File(self::FILE_MTAB);

        if (!$file->exists())
            return FALSE;

        $lines = $file->get_contents_as_array();

        foreach ($lines as $line) {
            list($name, $this->mount_point) = explode(' ', $line);

            if ($name == $device)
                return TRUE;
        }

        $this->mount_point = NULL;

        return FALSE;
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

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Scans for devices.
     *   
     * @param boolean $mounted mounted devices only
     * @param boolean $swap    swap devices
     *
     * @access private
     * @return void
     */

    private function _scan($mounted, $swap)
    {
        clearos_profile(__METHOD__, __LINE__);

        $atapi = $this->_scan_atapi();

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
        // Create an "indentifier" field that munges the two together, or
        // if required in the future, does further munging.
        
        foreach ($atapi as $parent => $device) {
            if (!isset($device['partition']))
                continue;

            foreach ($device['partition'] as $partition) {
                $atapi[$partition]['vendor'] = $device['vendor'];
                $atapi[$partition]['model'] = $device['model'];
                $atapi[$partition]['identifier'] = $device['vendor'] . ' ' . $device['model'];
                $atapi[$partition]['type'] = $device['type'];
                $atapi[$partition]['parent'] = $parent;
            }

            unset($atapi[$parent]);
        }

        $devices = $this->_scan_scsi();
        $scsi = array();

        foreach ($devices as $device) {
            if (!isset($device['partition'])) {
                $scsi[$device['device']]['vendor'] = $device['vendor'];
                $scsi[$device['device']]['model'] = $device['model'];
                $scsi[$device['device']]['identifier'] = $device['vendor'] . ' ' . $device['model'];

                // FIXME: discuss with Darryl - blocks-to-bytes
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

                continue;
            }

            foreach ($device['partition'] as $partition) {
                $scsi[$partition]['vendor'] = $device['vendor'];
                $scsi[$partition]['model'] = $device['model'];
                $scsi[$partition]['identifier'] = $device['vendor'] . ' ' . $device['model'];
                $scsi[$device['device']]['parent'] = $device['device'];

                if ($device['bus'] == 'usb')
                    $scsi[$partition]['type'] = 'USB';
                else
                    $scsi[$partition]['type'] = 'SCSI/SATA';
            }

            unset($scsi[$device['device']]);
        }

        $this->devices = array_merge($atapi, $scsi);

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
            $this->devices[$device]['identifier'] = $device['vendor'] . ' ' . $device['model'];
            $this->devices[$device]['type'] = $details['type'];
        }

        foreach ($this->devices as $device => $details) {
            $this->devices[$device]['mounted'] = $this->is_mounted($device);
            if ($this->devices[$device]['mounted'])
                $this->devices[$device]['mount_point'] = $this->mount_point;
        }

        $purge = array();

        if (!$mounted) {
            foreach ($this->devices as $device => $details) {
                if (!$details['mounted'])
                    continue;

                $purge[] = $device;
            }
        }

        if (!$swap) {
            foreach ($this->devices as $device => $details) {
                if (!$this->is_swap($device))
                    continue;

                $purge[] = $device;
            }
        }

        foreach ($purge as $device) unset($this->devices[$device]);

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
