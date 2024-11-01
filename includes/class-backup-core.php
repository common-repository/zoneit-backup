<?php
/**
 * Base Class
 * initialize class
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/11/06 16:38
 * Last Modified Time: 2024/08/12 22:41:37
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackup;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class Backup_Core
{
    /**
     * Start up
     */
    public function __construct()
    {
        // initialize db
        self::init_db();

        // create backup
        add_action( 'oping_create_backup_event', array( __CLASS__ , 'create_backup'), 10, 2 );
        add_action( 'oping_create_user_backup_event', array( __CLASS__ , 'create_backup'), 1, 2 );
        
    }

    /**
     * Initialize database
     */
    public function init_db()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // backups table
        $table_name = $wpdb->prefix . OPING_DB_PREFIX . "backups";
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            $sql = "CREATE TABLE $table_name (
                oping_backup_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                creator_user_id bigint(20) NOT NULL,
                service_type int(2) NOT NULL, /* localhost: 1 # FTP: 2 # Google Drive: 3 # Amazon: 4 */
                backup_url text DEFAULT NULL,
                backup_path text DEFAULT NULL,
                status int(2) NOT NULL, /* 0 : Error # 1 : IN Progress # 2 : Uploading # 3 : Downloading # 4 : Completed # 5 : Restored */
                message text DEFAULT NULL,
                is_deleted tinyint(1) NOT NULL,
                date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (oping_backup_id),
                KEY creator_user_id (creator_user_id)
            ) $charset_collate;";
            dbDelta($sql);
        }
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public static function sanitize( $input )
    {
        $new_input = array();
        $service_list = [];
        
        // check input id
        if( isset( $input['id'] ) )
        {
            $new_input['id'] = absint( $input['id'] );
        }
        
        // check creator user id
        if( isset( $input['user_id'] ) )
        {
            $new_input['user_id'] = absint( $input['user_id'] );
        }

        // check input service_type
        if( isset( $input['service_type'] ) )
        {
            $service_types = Backup_Service_Core::get_service_types();
            if( in_array( $input['service_type'], array_column( $service_types , 'id' ) ) )
            {
                $founded_key = array_search( $input['service_type'] , array_column( $service_types , 'id' ) );
                $new_input['service_type'] = $service_types[$founded_key]['id'];
            }
        }

        // check status
        if( isset( $input['status'] ) )
        {
            if( in_array( $input['status'] , array( 0, 1, 2, 3, 4, 5 ) ) )
            {
                $new_input['status'] = absint( $input['status'] );
            }
            
        }

        // check backup_url
        if( isset( $input['backup_url'] ) )
        {
            $new_input['backup_url'] = sanitize_url( $input['backup_url'] );
        }
        
        // check backup_path
        if( isset( $input['backup_path'] ) )
        {
            $new_input['backup_path'] = $input['backup_path'];
        }


        // check message
        if( isset( $input['message'] ) )
        {
            $new_input['message'] = sanitize_text_field( $input['message'] );
        }

        return $new_input;
    }

    /**
     * create backup from db
     *
     * @param timestamp
     * @return result
     */
    public static function create_db_backup( $timestamp )
    {
        ini_set('max_execution_time', '0');
        $result = [];
    
        require_once OPING_BACKUP_PLUGIN_DIR . 'vendor/autoload.php';
        $file_name = 'oping_db_' . md5(sha1("oPING" . get_site_url() . "BackUp")) . '_' . $timestamp . '.sql';
    
        // Initialize the WP Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
    
        if (!$wp_filesystem->is_dir(OPING_BACKUP_DIR)) {
            $wp_filesystem->mkdir( OPING_BACKUP_DIR, 0755 );
        }
    
        try {
            $dump = new \Ifsnop\Mysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
            $dump->start(OPING_BACKUP_DIR . $file_name);
            $result = ['status' => true, 'url' => OPING_BACKUP_URL . $file_name, 'filename' => $file_name];
        } catch (\Exception $e) {
            $result = ['status' => false, 'message' => $e->getMessage()];
        }
    
        return $result;
    }
    
    /**
     * create zip archive from files
     *
     * @param void
     * @return result
     */
    public static function create_file_archive( $sql_dump_filename, $timestamp )
    {
        ini_set('max_execution_time', '0');
        $result = [];
    
        // Get real path for our folder
        $rootPath = realpath(ABSPATH);
        $file_name = 'oping_archive_' . md5(sha1("oPING" . get_site_url() . "BackUp")) . '_' . $timestamp . '.zip';
    
        // Initialize the WP Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
    
        // Initialize archive object
        $zip = new ZipArchive();
        $zip->open(OPING_BACKUP_DIR . '/' . $file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
        // Remove Backup folder files
        $exclusions = [];
        $exclusions_files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(OPING_BACKUP_DIR),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($exclusions_files as $name => $file) {
            if (!$file->isDir() && $file->getFilename() !== $sql_dump_filename) {
                $exclusions[] = $file->getFilename();
            }
        }
    
        // Create recursive directory iterator
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    
        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir() && !in_array($file->getFilename(), $exclusions)) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
    
                if ($file->getFilename() === 'xmlrpc.php') {
                    // Use WP_Filesystem's chmod instead of PHP's chmod
                    $wp_filesystem->chmod($filePath, 0644);
                }
    
                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
    
        // Zip archive will be created only after closing the object
        $zip->close();
    
        if ($wp_filesystem->exists(OPING_BACKUP_DIR . $file_name)) {
            $result = ['status' => true, 'url' => OPING_BACKUP_URL . $file_name, 'path' => OPING_BACKUP_DIR . $file_name];
        } else {
            $result = ['status' => false, 'message' => 'Error while creating archive file'];
        }
    
        return $result;
    }

    /**
     * run backup event
     *
     * @param void
     */
    public static function run_backup_event( $params = [] )
    {
        if( !wp_next_scheduled( 'oping_create_user_backup_event' ) )
        {
            // Get the current time in the site's timezone
            if( !empty( get_option('timezone_string') ) )
                date_default_timezone_set( get_option('timezone_string') );
            wp_schedule_single_event(time(), 'oping_create_user_backup_event', $params);
        }
    }

    /**
     * create backup and save to db
     *
     * @param void
     */
    public static function create_backup( $service_type, $user_id = 1 )
    {
        $params = array();
        if( !empty( $service_type ) && !empty( $user_id ) )
        {
            $backup_services_list = Backup_Service_Core::get_service_types();
            $service_type_key = array_search( $service_type, array_column( $backup_services_list , 'name') );
            $params = [ 'user_id' => absint( $user_id ), 'service_type' => $backup_services_list[ $service_type_key ]['id'] ];
            $backup_id = self::save( $params );
            if( !empty( $backup_id ) && $backup_id > 0 )
            {
                // run mysq db dump
                $timestamp = date("YmdHis");
                
                $db_backup_result = self::create_db_backup( $timestamp );
                if( !empty($db_backup_result) && $db_backup_result['status'] )
                {
                    // run zip archive for backup from files and db
                    $file_archive_result = self::create_file_archive( $db_backup_result['filename'], $timestamp );
                    if( !empty($file_archive_result) && $file_archive_result['status'] )
                    {
                        $backup_info = self::get( [ 'id' => $backup_id ] );
                        if(!empty($backup_info))
                        {
                            if( $backup_info[0]['service_type'] == 1 ) // localhost
                            {
                                // update backup url in db
                                self::update( [ 'id' => $backup_id, 'status' => 4, 'backup_url' => $file_archive_result['url'] ] );
                            }
                            else // create archive file and upload on other service
                            {
                                // update backup with 'uploading' status
                                self::update( [ 'id' => $backup_id, 'status' => 2 ] );
                                
                                // upload to other service
                                $backup_address = Backup_Service_Core::upload_file( $backup_services_list[ $service_type_key ]['id'], $file_archive_result['path'] );
                                
                                if( !empty( $backup_address ) && $backup_address['status'] )
                                {
                                    // update backup with 'completed' status
                                    error_log( implode(",", $backup_address ), 0 );
                                    self::update( [ 'id' => $backup_id, 'status' => 4, 'backup_url' => $backup_address['url'], 'backup_path' => $backup_address['path'] ] );
                                    
                                    // delete files
                                    //$backup_file_name = substr( basename( $file_archive_result['path'], '.zip') , 14 ); // get file name without prefix oping and extension
                                    $db_file_path = str_replace('_archive_', '_db_', $file_archive_result['path'] );
                                    $db_file_path = str_replace('.zip', '.sql', $file_archive_result['path'] );
                                    wp_delete_file( $file_archive_result['path'] );
                                    wp_delete_file( $db_file_path );
                                }
                                else
                                {
                                    // update backup with 'error' status
                                    self::update( [ 'id' => $backup_id, 'status' => 0, 'message' => $backup_address['error'] ] );
                                }
                            }
            
                            // final request to endpoint of internal zoneit cloud services
                            self::connect_zoneit_api( $backup_id );
                        }
                    }
                    else
                    {
                        // update db with file archive error
                        self::update( [ 'id' => $backup_id, 'status' => 0, 'message' => $file_archive_result['message'] ] );
                    }
                }
                else
                {
                    // update db with db dump error
                    self::update( [ 'id' => $backup_id, 'status' => 0, 'message' => $db_backup_result['message'] ] );
                }
            }
        }
    }

    /**
     * insert to db
     * 
     * @param array contains all fields for insert into database
     * @return result
     */
    public static function save( $params )
    {
        global $wpdb;
        $result = 0;
        if( isset( $params ) )
        {
            $sanitized_params = self::sanitize( $params );
            if( !empty( $sanitized_params ) )
            {
                $status = 1; // status : in progress
    
                // insert to backups table
                $backups_table = $wpdb->prefix . OPING_DB_PREFIX . "backups";
                $add_result = $wpdb->insert( $backups_table, [
                    'creator_user_id' => $sanitized_params['user_id'],
                    'service_type' => $sanitized_params['service_type'],
                    'status' => $status,
                    'is_deleted' => 0
                ]);
                
                if( $add_result )
                    $result = $wpdb->insert_id;
                    
            }
            
        }

        return $result;
    }

    /**
     * update db 
     * 
     * @param array contains all fields for insert into database
     * @return result
     */
    public static function update( $params )
    {
        global $wpdb;
        $result = 0;
        
        $backups_table = $wpdb->prefix . OPING_DB_PREFIX . "backups";
        if( isset( $params ) && is_array( $params ) )
        {
            $sanitized_params = self::sanitize( $params );
            if( !empty( $sanitized_params ) )
            {
                // check oping_backup_id is exists
                $oping_backup_info = self::get( [ 'id' => $sanitized_params['id'] ] );
                if(!empty($oping_backup_info))
                {
                    // check status field
                    if( isset( $sanitized_params['status'] ) )
                        $update_params['status'] = $sanitized_params['status'];
                    
                    // check url field
                    if( isset( $sanitized_params['backup_url'] ) )
                        $update_params['backup_url'] = $sanitized_params['backup_url'];
                        
                    // check url field
                    if( isset( $sanitized_params['backup_path'] ) )
                        $update_params['backup_path'] = $sanitized_params['backup_path'];
    
                    // check message field
                    if( isset( $sanitized_params['message'] ) )
                        $update_params['message'] = $sanitized_params['message'];
    
                    // update backups table
                    $backups_table = $wpdb->prefix . OPING_DB_PREFIX . "backups";
                    $result = $wpdb->update( $backups_table, $update_params, [ 'oping_backup_id' => $sanitized_params['id'] ] );
                }
            }
        }

        return $result;
    }

    /**
     * get db rows
     * 
     * @param array params for filter
     * @return backups_list
     */
    public static function get( $params = [] )
    {
        global $wpdb;
        $backups_table = $wpdb->prefix . OPING_DB_PREFIX . "backups";
        $prepared_query = "SELECT * FROM $backups_table WHERE is_deleted=false";

        if( isset( $params ) && !empty( $params ) )
        {
            if( !empty( $params['id'] ) )
                $prepared_query .= $wpdb->prepare(" AND oping_backup_id=%d", absint( $params['id'] ) );
                
            if( !empty( $params['status'] ) )
                $prepared_query .= $wpdb->prepare(" AND status=%d", absint( $params['status'] ) );
                
            if( !empty( $params['orderby'] ) && !empty( $params['order'] ) )
                $prepared_query .= $wpdb->prepare(" order by %s %s", sanitize_text_field( $params['orderby'] ), sanitize_text_field( $params['order'] ) );

        }

        $backups_list = $wpdb->get_results( $prepared_query, ARRAY_A );

        return $backups_list;
    }

    /**
     * delete db row
     * 
     * @param oping_backup_id params for filter
     * @return backups_list
     */
    public static function delete( $oping_backup_id )
    {
        global $wpdb;
        $delete_update_status = 0;
        
        // delete file for localhost service type
        self::delete_file( $oping_backup_id );
            
        // remove row
        $backups_table = $wpdb->prefix . OPING_DB_PREFIX . "backups";
        $delete_update_status = $wpdb->update( $backups_table, array( 'is_deleted' => true ), array( 'oping_backup_id' => absint( $oping_backup_id ) ) );
        return $delete_update_status;
    }
    
    /**
     * delete file : only for service type localhost
     * 
     * @param oping_backup_id
     * @return delete_file_status
     */
    public static function delete_file( $oping_backup_id )
    {
        $delete_file_status = 0;
        $oping_backup_info = self::get( ['id' => absint( $oping_backup_id ) ] );
        if( !empty( $oping_backup_info ) )
        {
            $backup_file_name = substr( basename( $oping_backup_info[0]['backup_url'], '.zip') , 14 ); // get file name without prefix oping and extension
            $db_file_name = 'oping_db_'. $backup_file_name . '.sql';
            
            // remove archive file from backup folders
            if( file_exists( OPING_BACKUP_DIR . basename( $oping_backup_info[0]['backup_url'] ) ) )
                wp_delete_file( OPING_BACKUP_DIR . basename( $oping_backup_info[0]['backup_url'] ) );
                
            // remove db file from backup folders
            if( file_exists( OPING_BACKUP_DIR . $db_file_name ) )
                wp_delete_file( OPING_BACKUP_DIR . $db_file_name );
        }
        
        return $delete_file_status;
    }
    
    /**
     * Get backup urls
     * 
     * @param int $oping_backup_id
     * @return array $backup_urls backup and db url for specific backup
     */
    public static function get_backup_url( $oping_backup_id )
    {
        $backup_urls = [];
        
        $oping_backup_info = self::get( [ 'id' => absint( $oping_backup_id ) ] );
        if( !empty( $oping_backup_info ) )
        {
            if( !empty( $oping_backup_info[0]['backup_url'] ) )
            {
                $backup_file_name = substr( basename( $oping_backup_info[0]['backup_url'], '.zip') , 14 ); // get file name without prefix oping and extension
                $db_file_name = 'oping_db_'. $backup_file_name . '.sql';
                
                $backup_urls = [
                    'file' => OPING_BACKUP_DIR . basename( $oping_backup_info[0]['backup_url'] ),
                    'db' => OPING_BACKUP_DIR . $db_file_name
                ];
            }
            
        }

        return $backup_urls;
    }

    /**
     * Get backup urls
     * 
     * @param service_type
     * @param last_link
     * @return backup_urls
     */
    public static function get_backup_urls( $service_type = 0 , $last_link = 0)
    {
        $backup_urls = [];
        
        // fetch all urls from db
        if( ! $service_type )
            $backups_list = self::get( [ 'status' => 3 ] );
        else
            $backups_list = self::get( [ 'service_type' => $service_type , 'status' => 3 ] );

        if(!empty($backups_list))
        {
            $backup_urls = cols_from_array( $backups_list, array( 'oping_backup_id' , 'backup_url' ) );
            if( $last_link )
                $backup_urls = reset( $backup_urls );
        }

        return $backup_urls;
    }

    /**
     * Get status name
     * 
     * @param status_id
     * @return status_name
     */
    public static function get_status_name( $status_id )
    {
        $status_name = '';
        switch( $status_id )
        {
            case 0:
                $status_class = 'error_cl';  
                $status_name = __('Error', 'zoneit-backup');
                break;
            case 1:
                $status_class = 'progress_cl';
                $status_name = __('In Progress', 'zoneit-backup');
                break;
            case 2:
                $status_class = 'uploading_cl';
                $status_name = __('Uploading', 'zoneit-backup');
                break;
            case 3:
                $status_class = 'downloading_cl';
                $status_name = __('Downloading', 'zoneit-backup');
                break;
            case 4:
                $status_class = 'downloading_cl';
                $status_name = __('Completed', 'zoneit-backup');
                break;
            case 5:
                $status_class = 'downloading_cl';
                $status_name = __('Restored', 'zoneit-backup');
                break;
            default:
                $status_class = 'error_cl';
                $status_name = __('Error', 'zoneit-backup');
                break;
        }

        return sprintf('<span class="%s">%s</span>', $status_class, $status_name );
    }
    
    
    /**
     * Request to Oping Cloud api
     * 
     * @param bigint $oping_backup_id
     * 
     * @return void
     */
    public static function connect_zoneit_api( $oping_backup_id )
    {
        $backup_info = self::get( ['id' => $oping_backup_id ] );
        if( !empty( $backup_info ) && !empty( $backup_info[0]['backup_url'] ) && !empty(get_transient('oping_cloud_id') ) )
        {
            $request = wp_remote_post("https://api.zoneit.cloud/v2/api/wp/backup/", [
                'body' => [
                    'backup_id' => get_transient('oping_cloud_id'),
                    'token' => Oping_Backup_REST_API::generate_token(),
                    'domain' => Oping_Backup_REST_API::get_domain_name( get_site_url() ),
                    'link' => $backup_info[0]['backup_url']
                ]
            ]);
            
            delete_transient('oping_cloud_id');
        }
    }

    /**
     * Get array elements with multi-dimensional keys
     * 
     * @param array $input_array
     * @param array $keys
     * 
     * @return array $sanitized_array
     */
    public static function cols_from_array($input_array, $keys)
    {
        return array_map(function ($el) use ($keys) {
            return array_map(function ($c) use ($el) {
                return $el[$c];
            }, $keys);
        }, $input_array);
    }
}

if (class_exists('Backup_Core'))
    new Backup_Core();