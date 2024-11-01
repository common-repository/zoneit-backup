<?php
/*
Plugin Name: oPing Backup
Description: This plugin is creating a backup from website files and db
Version: 1.4
Author: oPing Cloud
Author URI: https://oping.cloud
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: zoneit-backup
*/

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

if (!class_exists('Oping_Backup')) {
	class Oping_Backup
	{

		/**
		 * Construct the plugin object
		 */
		public function __construct()
		{
            define('OPING_BACKUP_PLUGIN_VERSION', '1.3.2' );
            define('OPING_BACKUP_DIR', ABSPATH.'backup/' );
            define('OPING_BACKUP_URL', get_site_url().'/backup/' );
            define('OPING_BACKUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            define('OPING_BACKUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            define('OPING_DB_PREFIX', 'oping_');

			// include 
			require_once 'includes/services/class-backup-service-core.php';
			require_once 'includes/services/class-backup-service-list.php';
			require_once 'includes/services/class-ftp-service.php';
			require_once 'includes/class-backup-core.php';
			require_once 'includes/class-backups-list.php';
			require_once 'includes/class-cron-backup.php';
			require_once 'includes/class-restore-core.php';
			require_once 'includes/class-settings.php';
			
			// oping backup api route
            require_once('includes/class-rest-api.php');
			
		} // END public function __construct()
		
		/**
		 * Activate the plugin
		 */
		public static function activate()
		{
			// Do nothing
		} // END public static function activate

		/**
		 * Deactivate the plugin
		 */
		public static function deactivate()
		{
            // Do nothing
		} // END public static function deactivate	

	} // END class Oping_Backup
} // END if(!class_exists('Oping_Backup'))

if (class_exists('Oping_Backup')) {
	// instantiate the plugin class
	new Oping_Backup();

    register_activation_hook( __FILE__, array( 'Oping_Backup', 'activate' ) );
    register_deactivation_hook( __FILE__, array( 'Oping_Backup', 'deactivate' ) );
}