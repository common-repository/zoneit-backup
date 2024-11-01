<?php
/**
 * Oping Backup API Class
 * This class is adding two routes to wordpress api routes.
 * 
 * Package: Oping Backup
 * Author: Rasool Vahdati
 * DateTime: 2022/10/08 11:55:03
 * Last Modified Time: 2024/06/11 01:56:56
 * License: GPL-3.0+
 */

class Oping_Backup_REST_API extends WP_REST_Controller {
 
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        $version = '1';
        $namespace = 'oping-backup/v' . $version;
        //$base = 'terms';
        register_rest_route( $namespace, '/get', array(
            array(
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => array( $this, 'get_oping_backup_links' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( true ),
            ),
        ) );
        register_rest_route( $namespace, '/request', array(
			array(
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => array( $this, 'create_oping_backup_links' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( true ),
            ),
        ) );
    }

    /**
     * Get a collection of items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items( $request ) {
        $items = array(); //do a query, call another class, etc
        $data = array();
        foreach( $items as $item ) {
            $itemdata = $this->prepare_item_for_response( $item, $request );
            $data[] = $this->prepare_response_for_collection( $itemdata );
        }

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Get Oping backup links
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_oping_backup_links( $request ) {

        //get parameters from request
        $params = $request->get_params();
        $generate_token = self::generate_token();
        $results = array();

		if(isset($params['token']))
		{
            $user_token = sanitize_text_field( $params['token'] );
            if( $user_token == $generate_token )
            {
                $urls = Backup_Core::get_backup_urls( 0, 1 ); // all service type and only last link
                if( !is_null($urls) )
                {
                    $results = $urls;
                }
                else
                {
                    $results = [
                        'status' => true,
                        'msg' => __('backup links not found', 'zoneit-backup')
                    ];
                }
                return new WP_REST_Response( $results, 200 );
            }
            else
            {
                return new WP_Error( 'error', __('api token is not valid', 'zoneit-backup'), array( 'status' => 400 ) );
            }
		}
		else
		{
			return new WP_Error( 'error', __('api token not found', 'zoneit-backup'), array( 'status' => 400 ) );
		}
    }

    /**
     * Create one item from the collection
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function create_oping_backup_links( $request ) {
        
        //get parameters from request
        $params = $request->get_params();
        $geneate_token = self::generate_token();
        $results = array();

		if(isset($params['token']))
		{
            $user_token = sanitize_text_field( $params['token'] );
            if( $user_token == $geneate_token )
            {
                if(isset($params['backup_id']))
                {
                    if( !empty( Backup_Core::get( [ 'status' => 1 ] ) ) )
                    {
                        $results = [
                            'status' => false,
                            'msg' => __('There is a backup with \'in progress\' status. You cannot create a new backup until this backup is completed.', 'zoneit-backup')
                        ];
                        return new WP_REST_Response( $results, 200 );
                    }
                    else
                    {
                        set_transient('oping_cloud_id', sanitize_text_field( $params['backup_id'] ), 90 * MINUTE_IN_SECONDS ); // set receive backup_id for 
                        Backup_Core::run_backup_event( [ 'service_type' => 'Localhost', 'user_id' => 1 ] );
                        $results = [
                            'status' => true,
                            'msg' => __('The plugin is creating the backups of db and files...Please wait...', 'zoneit-backup')
                        ];
                        return new WP_REST_Response( $results, 200 );
                    }
                }
                else
                {
                    return new WP_Error( 'error', __('Backup UUID not found', 'zoneit-backup'), array( 'status' => 400 ) );
                }
            }
            else
            {
                return new WP_Error( 'error', __('The API Token is not valid', 'zoneit-backup'), array( 'status' => 400 ) );
            }
		}
		else
		{
			return new WP_Error( 'error', __('The API Token not found', 'zoneit-backup'), array( 'status' => 400 ) );
		}
    }

    /**
     * Generate token with salt
     *
     * @param void
     * @return token
     */
    public static function generate_token()
    {
        $site_url = self::get_domain_name( get_site_url() );
        $salt_key = "wMmqaGA.+P+q}(Yw%MwkA-Zi18L#9S)^U!9++O@F+/nJbV21Pfe|)Fyq+-}eh8>x";
        return md5( sha1( "oPING". $site_url."BaCk". $salt_key ) );
    }

    /**
     * Get domain name from url
     * @param full_url
     * @return domain_name
     */
    public static function get_domain_name( $full_url )
    {
        $domain_name = '';

        // Remove the http://, www., and slash(/) from the Query URL string 
        $user_query_uri = sanitize_url( $full_url );

        // If URI is like, eg. www.oping.cloud
        $user_query_uri = trim($user_query_uri, '/');

        // If not have http:// or https:// then prepend it
        if (!preg_match('#^http(s)?://#', $user_query_uri)) {
            $user_query_uri = 'http://' . $user_query_uri;
        }

        $urlParts = wp_parse_url($user_query_uri);

        // Remove www.
        $domain_name = preg_replace('/^www\./', '', $urlParts['host']);

        return $domain_name;
    }

    /**
     * Check if a given request has access to get items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check( $request ) {
        return true; //use to make readable by all
        //return current_user_can( 'edit_something' );
    }

    /**
     * Check if a given request has access to get a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_item_permissions_check( $request ) {
        return $this->get_items_permissions_check( $request );
    }

    /**
     * Check if a given request has access to create items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function create_item_permissions_check( $request ) {
        //return current_user_can( 'edit_something' );
        return true;
    }

    /**
     * Check if a given request has access to update a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function update_item_permissions_check( $request ) {
        return $this->create_item_permissions_check( $request );
    }

    /**
     * Check if a given request has access to delete a specific item
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function delete_item_permissions_check( $request ) {
        //return $this->create_item_permissions_check( $request );
        return true;
    }

    /**
     * Prepare the item for create or update operation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_Error|object $prepared_item
     */
    protected function prepare_item_for_database( $request ) {
        return array();
    }

    /**
     * Prepare the item for the REST response
     *
     * @param mixed $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     * @return mixed
     */
    public function prepare_item_for_response( $item, $request ) {
        return array();
    }

}

/**
 * Function to register our new routes from the controller.
 */
function oping_backup_init_rest_api() {
	$controller = new Oping_Backup_REST_API();
	$controller->register_routes();
}

add_action( 'rest_api_init', 'oping_backup_init_rest_api' );