<?php
/**
 * Restore Class
 * The class for restore db and files
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/12/28 01:00
 * Last Modified Time: 2024/08/14 17:03:40
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackup;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class Restore_Core
{
    /**
     * Start up
     */
    public function __construct()
    {
        // restore backup
        add_action( 'oping_restore_backup_event', array( __CLASS__ , 'restore_backup') );
    }

    /**
     * Restore db backup
     *
     * @param string $db_path path of db in host
     * @return result
     */
    public static function restore_db( $db_path )
    {
        ini_set('max_execution_time', '0');
        global $wpdb;
        $result = [];
        
        $query = '';
        $sqlScript = file($db_path);
        
        foreach ($sqlScript as $line) {
            $startWith = substr(trim($line), 0, 2);
            $endWith = substr(trim($line), -1, 1);
            
            if (empty($line) || $startWith == '--' || $startWith == '/*' || $startWith == '//') {
                continue;
            }
            
            $query .= $line;
            
            // Check if it's the end of a query.
            if ($endWith == ';') {
                // Skip any query related to the backups table
                if (strpos($query, OPING_DB_PREFIX . "backups") !== false) {
                    $query = '';  // Reset query and skip
                    continue;
                }
                
                // Handle CREATE TABLE queries
                if (stripos($query, 'CREATE TABLE') !== false) {
                    // Extract the table name from the CREATE TABLE statement
                    preg_match('/CREATE TABLE `?(.*?)`? \(/', $query, $matches);
                    $tableName = $matches[1];
    
                    // Check if the table exists
                    if ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
                        // If it exists, truncate the table
                        $wpdb->query("TRUNCATE TABLE $tableName");
                    } else {
                        // Otherwise, run the CREATE TABLE query
                        $wpdb->query($query);
                    }
                    
                    $query = '';  // Reset for the next statement
                    continue;
                }
                
                // Handle INSERT INTO queries
                if (stripos($query, 'INSERT INTO') !== false) {
                    // Run the query to insert data
                    $wpdb->query($query);
                }
                
                $query = '';  // Reset for the next statement
            }
        }
        
        return $result;
    }
    
    /**
     * Restore file archive
     *
     * @param string $file_archive_path The file archive path for restore
     * @return result
     */
    public static function restore_file_archive( $file_archive_path )
    {
        ini_set('max_execution_time', '0');
        $result = 0;
        
        $zip = new ZipArchive;
        $res = $zip->open( $file_archive_path );
        if ($res === TRUE) {
            // extract it to the path we determined above
            $zip->extractTo( ABSPATH );
            $zip->close();
            //echo "WOOT! $file extracted to $path";
            $result = 1;
        } else {
            //echo "Doh! I couldn't open $file";
            $result = 0;
        }
        
        return $result;
    }

    /**
     * run backup event
     *
     * @param void
     */
    public static function restore_backup_event( $params = [] )
    {
        if( !wp_next_scheduled( 'oping_restore_backup_event' ) )
        {
            if( !empty( get_option('timezone_string') ) )
                date_default_timezone_set( get_option('timezone_string') );
            wp_schedule_single_event( time(), 'oping_restore_backup_event', $params );
        }
    }

    /**
     * Restore backup
     *
     * @param int $backup_id backup id for restore backup
     */
    public static function restore_backup( $backup_id )
    {
        // Set a transient to indicate the event is running
        set_transient('oping_restore_backup_running', true, 1800); // Set for 30 minutes; adjust as needed
    
        $oping_backup_info = Backup_Core::get( [ 'id' => absint( $backup_id ) ] );
        if( !empty( $oping_backup_info ) )
        {
            if( $oping_backup_info[0]['service_type'] == 1 ) // localhost
            {
                $backup_urls = Backup_Core::get_backup_url( $oping_backup_info[0]['oping_backup_id'] );
                if( !empty( $backup_urls ) )
                {
                    // restore file archive
                    self::restore_file_archive( $backup_urls['file'] );
                    
                    // restore db
                    self::restore_db( $backup_urls['db'] );
                    
                    Backup_Core::update( [ 'id' => $backup_id, 'status' => 5 ] );
                }
            }
            else // other service
            {
                // update backup with 'downloading' status
                Backup_Core::update( [ 'id' => $backup_id, 'message' => '' ] );
                
                $backup_file_name = substr( basename( $oping_backup_info[0]['backup_url'], '.zip') , 14 ); // get file name without prefix oping and extension
                $db_file_name = 'oping_db_'. $backup_file_name . '.sql';
                
                // download file
                $result = Backup_Service_Core::download_file( $oping_backup_info[0]['service_type'], OPING_BACKUP_DIR.'/'. basename( $oping_backup_info[0]['backup_url'] ) );
                
                if( !empty( $result ) && $result['status'] )
                {
                    // restore file archive
                    self::restore_file_archive( OPING_BACKUP_DIR . basename( $oping_backup_info[0]['backup_url'] ) );
                    
                    // restore db
                    self::restore_db( OPING_BACKUP_DIR . $db_file_name );
                    
                    Backup_Core::update( [ 'id' => $backup_id, 'status' => 5 ] );
                    
                    // unlink files
                    wp_delete_file( OPING_BACKUP_DIR . basename( $oping_backup_info[0]['backup_url'] ) );
                    wp_delete_file( OPING_BACKUP_DIR . basename( $db_file_name ) );
                }
                else
                {
                    // update backup with 'error' status
                    Backup_Core::update( [ 'id' => $backup_id, 'status' => 0, 'message' => $result['error'] ] );
                }
            }
        }
        
        delete_transient('oping_restore_backup_running');
    }
}

if (class_exists('Restore_Core'))
    new Restore_Core();