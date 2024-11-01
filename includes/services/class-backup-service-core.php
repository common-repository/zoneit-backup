<?php
/**
 * Backup Service Core Class
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/11/06 16:38
 * Last Modified Time: 2024/08/12 22:37:29
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackup;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class Backup_Service_Core
{
    // This property stores a secret key used for JSON Web Token (JWT) authentication.
    private static $jwt_secret_key;
    
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
        
        // initialize db
        self::init_db();
    }
    
    /**
     * Initialize database
     */
    public function init_db()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // backups services
        $table_name = $wpdb->prefix . OPING_DB_PREFIX . "backup_services";
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            $sql = "CREATE TABLE $table_name (
                oping_backup_service_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                creator_user_id bigint(20) NOT NULL,
                service_name varchar(400) NOT NULL,
                service_type int(2) NOT NULL, /* localhost: 1 # FTP: 2 # Google Drive: 3 # Amazon: 4 */
                data text NOT NULL,
                date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (oping_backup_service_id),
                KEY creator_user_id (creator_user_id)
            ) $charset_collate AUTO_INCREMENT=2;";
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

        // check input service_type
        if( isset( $input['service_type'] ) )
        {
            $service_types = self::get_service_types();
            if( in_array( $input['service_type'], array_column( $service_types , 'name' ) ) )
            {
                $founded_key = array_search( $input['service_type'] , array_column( $service_types , 'name' ) );
                $new_input['service_name'] = $service_types[$founded_key]['name'];
                $new_input['service_type'] = $service_types[$founded_key]['id'];
                
                if( $service_types[$founded_key]['name'] == 'FTP' )
                {
                    $new_input['ftp_server'] = wp_kses_data( $input['ftp_server'] );
                    $new_input['ftp_path'] = wp_kses_data( $input['ftp_path'] );
                    $new_input['ftp_username'] = sanitize_text_field( $input['ftp_username'] );
                    $new_input['ftp_password'] = sanitize_text_field( $input['ftp_password'] );
                    $new_input['data'] = self::encode_data([
                        'ftp_server' => $new_input['ftp_server'],
                        'ftp_path' => $new_input['ftp_path'],
                        'ftp_username' => $new_input['ftp_username'],
                        'ftp_password' => $new_input['ftp_password']
                    ]);
                }
            }
        }

        return $new_input;
    }
    
    /**
     * Get backup service types
     * 
     * @param backup_service backup service id
     */
    public static function get_service_types()
    {
        $backup_services = array();
        $backup_services[] = array(
            'id' => 1,
            'name' => 'Localhost'
        );
        
        return apply_filters('oping_backup_service_type', $backup_services );
    }
    
    /**
     * Get filtered backup services (the services that not existing in the database)
     * 
     * @return array $filtered_services The filtered services
     */
    public static function get_filtered_service_types()
    {
        $filtered_services = array();
        
        $original_backup_service_list = self::get_service_types();
        unset( $original_backup_service_list[0] ); // remove localhost
        $backup_services_list = array_values( $original_backup_service_list ); // reindex
        $backup_services_list_ids = array_column( $backup_services_list, 'id' );
        $existing_backup_services_ids = array_column( self::get(), 'service_type' );
        $result = array_diff($backup_services_list_ids, $existing_backup_services_ids);
        if(!empty($result))
        {
            foreach($result as $item)
            {
                $founded_key = array_search( $item['id'], array_column( $backup_services_list , 'id' ) );
                $filtered_services[] = $backup_services_list[$founded_key];
            }
        }
        return $filtered_services;
    }
    
    /**
     * Save a new item.
     *
     * Saves a new item to the database.
     *
     * @param array $data The data for the new item.
     *
     * @return int The ID of the new item.
     */
    public static function save( $data )
    {
        global $wpdb;
        $result = 0;
        if( isset( $data ) && is_array( $data ) )
        {
            $sanitized_params = self::sanitize( $data );
            if( !empty( $sanitized_params ) )
            {
                $creator_user_id = get_current_user_id();
    
                // insert backup_services table
                $backup_services_table = $wpdb->prefix . OPING_DB_PREFIX . "backup_services";
                $add_result = $wpdb->insert( $backup_services_table, [
                    'creator_user_id' => $creator_user_id,
                    'service_name' => $sanitized_params['service_name'],
                    'service_type' => $sanitized_params['service_type'],
                    'data' => $sanitized_params['data']
                ]);
                
                if( $add_result )
                    $result = $wpdb->insert_id;
            }
            
        }

        return $result;
    }
    
    /**
     * Edit an existing item.
     *
     * Edits an existing item in the database.
     *
     * @param array $data The updated data for the item.
     *
     * @return bool Whether the item was successfully edited.
     */
    public static function edit( $data ) {
        // Update the item in the database and return whether the update was successful.
        global $wpdb;
        $update_result = 0;
        if( isset( $data ) && is_array( $data ) )
        {
            $sanitized_params = self::sanitize( $data );
            if( !empty( $sanitized_params ) )
            {
                // update backup_services table
                $backup_services_table = $wpdb->prefix . OPING_DB_PREFIX . "backup_services";
                $update_result = $wpdb->update( $backup_services_table, [
                    'service_name' => $sanitized_params['service_name'],
                    'service_type' => $sanitized_params['service_type'],
                    'data' => $sanitized_params['data']
                ], [ 'oping_backup_service_id' => $sanitized_params['id'] ] );
            }
            
        }

        return $update_result;
    }
    
    /**
     * Delete an item.
     *
     * Deletes an item from the database.
     *
     * @param int $id The ID of the item to delete.
     *
     * @return bool Whether the item was successfully deleted.
     */
    public static function delete( $id ) {
        // Delete the item from the database and return whether the deletion was successful.
        global $wpdb;
        $delete_result = 0;
        if( !empty( $id ) && $id > 0 )
        {
            $backup_service_details = self::get( [ 'id' => $id ] );
            if( !empty( $backup_service_details ) )
            {
                $backup_services_table = $wpdb->prefix . OPING_DB_PREFIX . "backup_services";
                $delete_result = $wpdb->delete( $backup_services_table, [ 'oping_backup_service_id' => $backup_service_details[0]['oping_backup_service_id'] ] );
            }
        }

        return $delete_result;
    }
    
    /**
     * Get items
     *
     * Gets all data from database
     *
     * @param array $data The data for get from database
     *
     * @return bool Whether the item was successfully deleted.
     */
    public static function get( $data = [] )
    {
        global $wpdb;
        $backup_services_table = $wpdb->prefix . OPING_DB_PREFIX . "backup_services";
        $prepared_query = "SELECT * FROM $backup_services_table";

        if( !empty( $data ) )
        {
            
            if( !empty( $data['id'] ) )
                $prepared_query .= $wpdb->prepare(" WHERE oping_backup_service_id = %d", $wpdb->esc_like( absint( $data['id'] ) ) );
                
            if( !empty( $data['service_type'] ) )
                $prepared_query .= $wpdb->prepare(" WHERE service_type = %d", $wpdb->esc_like( absint( $data['service_type'] ) ) );
                
            if( !empty( $data['orderby'] ) && !empty( $data['order'] ) )
                $prepared_query .= $wpdb->prepare(" order by %s %s", $wpdb->esc_like( sanitize_text_field( $data['orderby'] ) ), $wpdb->esc_like( sanitize_text_field( $data['order'] ) ) );

        }

		$backup_services_list = $wpdb->get_results( $prepared_query, ARRAY_A );

		return $backup_services_list;
    }
    
    /**
     * upload to another server/service
     *
     *
     * @param array $data The data for get from database
     *
     * @return bool Whether the item was successfully deleted.
     */
    public static function upload_file( $service_type, $local_file_path )
    {
        $backup_info = array();
        
        switch( $service_type )
        {
            case 2:
                // FTP
                $service_info = self::get( [ 'service_type' => 2 ] );
                if(!empty($service_info))
                {
                    $ftp_data = get_object_vars( self::decode_data( $service_info[0]['data'] ) );
                    if(!empty($ftp_data))
                    {
                        $backup_info = FTP_Service::upload( $ftp_data['ftp_server'] , $ftp_data['ftp_username'], $ftp_data['ftp_password'], $ftp_data['ftp_path'], $local_file_path );
                    }
                }
                break;
            default:
                $backuo_info = array();
                break;
        }
        
        return $backup_info;
    }
    
    /**
     * download from another server/service
     *
     *
     * @param array $data The data for get from database
     *
     * @return bool Whether the item was successfully deleted.
     */
    public static function download_file( $service_type, $local_file_path )
    {
        $backup_info = array();
        
        switch( $service_type )
        {
            case 2:
                // FTP
                $service_info = self::get( [ 'service_type' => 2 ] );
                if(!empty($service_info))
                {
                    $ftp_data = get_object_vars( self::decode_data( $service_info[0]['data'] ) );
                    if(!empty($ftp_data))
                    {
                        $backup_info = FTP_Service::download( $ftp_data['ftp_server'] , $ftp_data['ftp_username'], $ftp_data['ftp_password'], $ftp_data['ftp_path'], OPING_BACKUP_DIR.'/'. basename( $local_file_path ) );
                    }
                }
                break;
            default:
                $backuo_info = array();
                break;
        }
        
        return $backup_info;
    }
    
    /**
     * Generate a random JWT secret key.
     *
     * Generates a random string of bytes and converts it to a hexadecimal string.
     *
     * @return string The generated JWT secret key.
     */
    private static function generate_jwt_secret_key() {
        $length = 15; // number of bytes to generate
        $timestamp = time(); // current timestamp
        $jwt_secret_key = bin2hex( random_bytes( $length ) ) . '_' . $timestamp;
        return $jwt_secret_key;
    }
    
    /**
     * Encode data of every service type using JWT Token
     *
     * @param array $data The data that encodes for saving in database
     * @return string $encoded_data The encoded input data
     */
    public static function encode_data( $data )
    {
        // Include the JWT library
        require OPING_BACKUP_PLUGIN_DIR . 'vendor/autoload.php';
        $jwt_secret_key = "74fb44e70f0#!@oPING@!#dae4c71df31c2e2081bb19c07!@#Back$#@a82b!@##@1744ae0b";
        
        $encoded_data = '';
        if( !empty( $data ) )
        {
            // Encode the JWT token
            $encoded_data = \Firebase\JWT\JWT::encode($data, $jwt_secret_key, 'HS256');
        }

        return $encoded_data;
    }
    
    /**
     * Decode data of every service type using JWT Token
     *
     * @param string $encoded_data The encoded data for show to user (JWT Token)
     * @return array $decoded_data The decoded input data
     */
    public static function decode_data( $encoded_data )
    {
        // Include the JWT library
        require OPING_BACKUP_PLUGIN_DIR . 'vendor/autoload.php';
        $jwt_secret_key = "74fb44e70f0#!@oPING@!#dae4c71df31c2e2081bb19c07!@#Back$#@a82b!@##@1744ae0b";

        $decoded_data_array = [];
        
        if( !empty( $encoded_data ) )
        {
            // Decode the JWT token
            $decoded_data_array = \Firebase\JWT\JWT::decode($encoded_data, new \Firebase\JWT\Key( $jwt_secret_key, 'HS256' ) );
        }
        
        return $decoded_data_array;
    }

}

if( is_admin() )
    new Backup_Service_Core();