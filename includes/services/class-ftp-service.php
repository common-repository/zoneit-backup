<?php
/**
 * FTP Backup Service
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2023/03/25 18:14
 * Last Modified Time: 2024/08/12 22:49:21
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackup;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class FTP_Service extends Backup_Service_Core
{
    // This property is used to store a singleton instance of the class.
    protected static $_instance = null;

    // This static method is used to retrieve the singleton instance of the class.
    public static function instance() {
        
        // If the singleton instance has not been created yet, create it now.
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        // Return the singleton instance of the class.
        return self::$_instance;
    }
    
    /**
     * Start up
     */
    public function __construct()
    {
    }
    
    /**
     * Add FTP service
     * 
     * @param array $backup_services The given backup services list
     * 
     * @return array $backup_services The changed backup services list
     */
    public static function add_service( $backup_services )
    {
        $backup_services[] = [
            'id' => 2,
            'name' => 'FTP'
        ];
        
        return $backup_services;
    }

    /**
     * Render form fields
     *
     * Saves a new item to the database.
     *
     * @param void
     *
     * @return array $fields
     */
    public static function render_form_fields()
    {
        $fields = array(
            array(
                'type' => 'text',
                'id' => 'ftp_server', 
                'class' => 'ftp_server',
                'name' => __('FTP Server URL/IP', 'zoneit-backup'),
                'placeholder' =>  __('Example: ftp.site.com', 'zoneit-backup'),
                'size' => 40
            ),
            array(
                'type' => 'text',
                'id' => 'ftp_path', 
                'class' => 'ftp_path',
                'name' => __('FTP Path', 'zoneit-backup'),
                'placeholder' =>  __('/', 'zoneit-backup'),
                'size' => 30
            ), 
            array(
                'type' => 'text',
                'id' => 'ftp_username', 
                'class' => 'ftp_username',
                'name' => __('FTP Username', 'zoneit-backup'),
                'placeholder' => '',
                'size' => 20
            ), 
            array(
                'type' => 'text',
                'id' => 'ftp_password', 
                'class' => 'ftp_password',
                'name' => __('FTP Password', 'zoneit-backup'),
                'placeholder' => '',
                'size' => 20
            )
        );
        
        return $fields;
    }
    
    /**
     * Upload file to FTP
     *
     * Edits an existing item in the database.
     *
     * @param int $oping_backup_id The ID of the oping backup
     * @return bool $status status of uploading to ftp
     */
    public static function upload( $server_address, $username, $password, $server_path, $local_path )
    {
        $result = array();
        $status = -1;
        if(!empty($server_address) && !empty($username) && !empty($password) && !empty($server_path) && !empty($local_path))
        {
            // Set the FTP login credentials
            $ftp_server = $server_address;
            $ftp_username = $username;
            $ftp_password = $password;
            
            // Set the local file path and the remote FTP file path
            $local_file = $local_path;
            $remote_file = $server_path. '/'. basename($local_file);
            
            // Connect to the FTP server
            $ftp_conn = ftp_connect($ftp_server) or die( esc_attr__('Could not connect to FTP server', 'zoneit-backup') );
            
            // Login to the FTP server
            ftp_login($ftp_conn, $ftp_username, $ftp_password);
            
            // Set passive mode
            ftp_pasv($ftp_conn, true);
            
            // Open the local file for reading
            $handle = fopen($local_file, "r");
            
            // Get the file size
            $file_size = filesize($local_file);
            
            // Initiate the upload
            
            $upload = ftp_nb_put($ftp_conn, $remote_file, $local_path, FTP_BINARY);
            
            // Track the upload progress
            while ($upload == FTP_MOREDATA) {
                // Get the current upload position
                $position = ftell($handle);
            
                // Calculate the percentage of upload completed
                $percent_complete = round($position / $file_size * 100);
            
                // Output the percentage to the console
                //echo "Uploading... $percent_complete% complete\r";
            
                // Continue the upload
                $upload = ftp_nb_continue($ftp_conn);
            }
            
            // Check if the upload was successful
            if ($upload == FTP_FINISHED) {
                //echo "File uploaded successfully";
                $url = 'ftp://' . $ftp_server . '/' . $remote_file;
                $result = array( 'status' => 1, 'url' => $url, 'path' => $remote_file );
            } else {
                //echo "Error uploading file";
                $result = array( 'status' => 0, 'error' => esc_attr__( 'Error Uploading File', 'zoneit-backup' ) );
            }
            
            // Close the file handle and FTP connection
            fclose($handle);
            ftp_close($ftp_conn);
        }
        
        return $result;
    }
    
    /**
     * Download file from FTP
     *
     * Edits an existing item in the database.
     *
     * @param int $oping_backup_id The ID of the oping backup
     *
     * @return bool $status status of download to ftp
     */
    public static function download( $server_address, $username, $password, $server_path, $local_path )
    {
        $result = array();
        if(!empty($server_address) && !empty($username) && !empty($password) && !empty($server_path) && !empty($local_path))
        {
            // Set the FTP login credentials
            $ftp_server = $server_address;
            $ftp_username = $username;
            $ftp_password = $password;
            
            // Set the remote FTP file path
            $remote_file = $server_path . '/'. basename( $local_path );
            
            // Set the local file path to save the downloaded file
            $local_file = $local_path;
            
            // Connect to the FTP server
            $ftp_conn = ftp_connect($ftp_server) or die( esc_attr__('Could not connect to FTP server','zoneit-backup') );
            
            // Login to the FTP server
            ftp_login($ftp_conn, $ftp_username, $ftp_password);
            
            // Set passive mode
            ftp_pasv($ftp_conn, true);
            
            // Open the local file for writing
            $handle = fopen($local_path, "w");
            
            // Get the remote file size
            $file_size = ftp_size($ftp_conn, $remote_file);
            
            // Download the file from the FTP server and write it to the local file
            $download = ftp_nb_fget($ftp_conn, $handle, $remote_file, FTP_BINARY);
            
            // Track the download progress
            while ($download == FTP_MOREDATA) {
                // Get the current download position
                $position = ftell($handle);
            
                // Calculate the percentage of download completed
                $percent_complete = round($position / $file_size * 100);
            
                // Output the percentage to the console
                // echo "Downloading... $percent_complete% complete\r";
            
                // Continue the download
                $download = ftp_nb_continue($ftp_conn);
            }
            
            // Check if the download was successful
            if ($download == FTP_FINISHED) {
                //echo "File downloaded successfully";
                $result = array( 'status' => 1 );
            } else {
                //echo "Error downloading file";
                $result = array( 'status' => 0, 'error' => esc_attr__('Error Downloading File','zoneit-backup') );
            }
            
            // Close the file handle and FTP connection
            fclose($handle);
            ftp_close($ftp_conn);
        }
        
        return $result;
    }
}

if( class_exists('FTP_Service') )
{
    new FTP_Service();
    add_filter( 'oping_backup_service_type', array( 'FTP_Service', 'add_service' ) );
}
    