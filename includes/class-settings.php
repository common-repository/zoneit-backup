<?php
/**
 * Backup Settings Class
 * This class is settings of oping backup.
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/12/03 23:22
 * Last Modified Time: 2024/06/10 23:01:30
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackup;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class Backup_Settings
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * variable for name of options
     */
    public $option_name;

    /**
     * Start up
     */
    public function __construct()
    {
        // initialize option name
        $this->option_name = 'oping_backup_settings';
		
		// initialize
		$init_value = [];
		if ( get_option( $this->option_name ) === false ){  // Nothing yet saved
		    $init_value['api'] = "yes";
            update_option( $this->option_name , $init_value );
		}
    }
}

if (class_exists('Backup_Settings'))
    new Backup_Settings();