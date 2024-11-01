<?php
/**
 * Backup Service List Class
 * backup service list using wp list table
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/11/06 16:38
 * Last Modified Time: 2024/08/12 22:45:22
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackup;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed 

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if (!function_exists('convert_to_screen')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}

class Backup_Service_List extends WP_List_Table
{
	var $backup_services_data = array();

    function __construct()
	{
		global $status, $page;

		parent::__construct( array(
    		'singular'  => __( 'Backup Service', 'zoneit-backup' ),     //singular name of the listed records
    		'plural'    => __( 'Backup Services', 'zoneit-backup' ),   //plural name of the listed records
    		'ajax'      => false        //does this table support ajax?
		) );
		
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_files' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 103 );
        add_action( 'admin_footer', array( $this, 'fill_fields_scripts' ) );
    }
    
    /**
     * Admin Enqueue Files
     */
    public function admin_enqueue_files( $hook )
    {
        if( $hook != 'oping-backup_page_backup-services' )
            return;
        
        // enqueue styles
        wp_enqueue_style('main', OPING_BACKUP_PLUGIN_URL . 'assets/css/main.css', array(), OPING_BACKUP_PLUGIN_VERSION );
    }
    
    /**
     * Add submenu page
     */
    public function add_submenu()
    {
		// Submenu
		add_submenu_page(
			'oping-backups',
			esc_attr__('Backup Services', 'zoneit-backup'), 
            esc_attr__('Backup Services', 'zoneit-backup'), 
			'manage_options', 
			'backup-services',
			array( $this, 'services_list_page' )
		);
    }
    
    /**
     * Admin footer
     */
    public function fill_fields_scripts()
    {
        $current_screen = get_current_screen();
        if( $current_screen->base != 'oping-backup_page_backup-services' )
            return;
        ?>
        <script>
        jQuery(document).ready(function() {
            jQuery('#service_type').change(function() {
                var selectedOption = jQuery(this).find(':selected');
                jQuery('.fill-fields').empty();
                if(selectedOption.val() == 'none')
                {
                    jQuery('.fill-fields').html('-');
                }
                else
                {
                    var dataFields = JSON.parse(selectedOption.attr('data-fields'));
                    
                    jQuery.each(dataFields, function(index, field) {
                        var inputField = '<label for="' + field.id + '" style="display:inline-block; min-width:170px" >' + field.name + '</label>' +
                                         '<input type="' + field.type + '" id="' + field.id + '" size="' + field.size + '" class="' + field.class + '" name="' + field.id + '" placeholder="' + field.placeholder + '" style="direction:ltr;text-align:left;margin-bottom:10px;" /><br />';
                        jQuery('.fill-fields').append(inputField);
                    });
                }
            });
        });
        </script>
		<?php
    }
    
     /**
     * Get service data fields
     *
     * @param void
     *
     * @return array $fields
     */
    public static function get_service_data_fields( $service_type )
    {
        $fields = '';
        switch( $service_type )
        {
            case "FTP":
                $fields = FTP_Service::render_form_fields();
                break;
            default:
                $fields = '';
                break;
        }
        
        return wp_json_encode( $fields );
    }

	function column_default( $item, $column_name )
	{
		global $wpdb;
		switch( $column_name ) {
			case 'service_type':
			    $backup_service_types = Backup_Service_Core::get_service_types();
			    $founded_key = array_search( $item[ $column_name ] , array_column( $backup_service_types , 'id' ) );
				return $backup_service_types[ $founded_key ]['name'];
			case 'data':
				return $item[ $column_name ];
			case 'date_created':
            case 'date_modified':
				return date_i18n('Y/m/d H:i:s', strtotime( $item[ $column_name ] ) );
			default:
				return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
		}
	}

	function get_sortable_columns()
	{
		$sortable_columns = array(
			'service_type'  => array( 'service_type', false ),
			'date_created'  => array( 'date_created', false ),
            'date_modified'  => array( 'date_modified', false ),
		);
		return $sortable_columns;
	}

	function get_columns()
	{
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'service_type' => esc_attr__( 'Service Type', 'zoneit-backup' ),
			'data' => esc_attr__( 'Encrypted Data', 'zoneit-backup' ),
            'date_created' => esc_attr__( 'Created', 'zoneit-backup' ),
			'date_modified' => esc_attr__( 'Updated', 'zoneit-backup' ),
		);
		return $columns;
	}

	function usort_reorder( $a, $b )
	{
		// If no sort, default to title
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : $item['oping_backup_service_id'];
		// If no order, default to asc
		$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
		// Determine sort order
		$result = strcmp( $a[$orderby], $b[$orderby] );
		// Send final sort direction to usort
		return ( $order === 'asc' ) ? $result : -$result;
	}
	
	function get_views()
	{
		global $wpdb;
		
		$views = array();
		$current = ( !empty($_REQUEST['backup_status']) ? $_REQUEST['backup_status'] : 'all');

		// All link
		$all_data = Backup_Service_Core::get();
		$class = ($current == 'all' ? ' class="current"' :'');
		$all_url = esc_url( add_query_arg( array( 'page' => esc_attr( $_REQUEST['page'] ) ), admin_url('admin.php') ) );
		$views['all'] = "<a href='{$all_url}' {$class} >".esc_attr__('All')." (".count($all_data).")</a>";
	   
	   return $views;
	}

	function column_service_type( $item )
	{
	    $current_screen = get_current_screen();
	    
		$actions = array(
		    'edit'    => sprintf('<a href="?page=%s&action=%s&backup-service=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'edit', $item['oping_backup_service_id'], __('Edit', 'zoneit-backup') ),
            'delete'    => sprintf('<a href="?page=%s&action=%s&backup-service=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'delete', $item['oping_backup_service_id'], __('Delete Permanently', 'zoneit-backup') )
        );
        
		$backup_service_types = Backup_Service_Core::get_service_types();
	    $founded_key = array_search( $item[ 'service_type' ] , array_column( $backup_service_types , 'id' ) );
		return sprintf( '%1$s %2$s', $backup_service_types[ $founded_key ]['name'], $this->row_actions( $actions ) );
	}

	function get_bulk_actions()
	{
		$actions = array(
            'delete'    => esc_attr__('Delete Permanently')
        );
		
		return $actions;
	}

	function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="backup-service[]" value="%s" />', $item['oping_backup_service_id']
		);   
	}
	
	function delete_items( $elements )
	{
		global $wpdb;
		
		// Delete From oping backups
		foreach($elements as $item)
            Backup_Service_Core::delete( $item );

	}
	
	public function prepare_items($search=NULL)
	{
        global $wpdb;
        $params = [];
        $per_page = 30;
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array( $columns, $hidden, $sortable );
		
		if( isset ( $_GET['order'] ) )
	        $params['order_type'] = sanitize_text_field( $_GET['order'] );
        else
            $params['order_type'] = "desc";
            
        if( isset( $_GET['orderby'] ) )
            $params['orderby'] = sanitize_text_field( $_GET['orderby'] );
        else
            $params['orderby'] = "oping_backup_service_id";
		
        /* If the value is not NULL, do a search for it. */
        if( $search != NULL ){
            
            /* Notice how you can search multiple columns for your search term easily, and return one data set */
            $params['search'] = trim( $search );
        }

        $this->backup_services_data = Backup_Service_Core::get( $params );
        
        if( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) )
            usort($this->backup_services_data, array(&$this, 'usort_reorder'));   
        else
            usort($this->backup_services_data, function (array $a, array $b) { return -($a["oping_backup_id"] - $b["oping_backup_id"]); } );
        
        $current_page = $this->get_pagenum();
        
        $total_items = count( $this->backup_services_data );
        
        $this->backup_services_data = array_slice( $this->backup_services_data, ( ( $current_page - 1 ) * $per_page ), $per_page );
        
        $this->items = $this->backup_services_data;
        
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }

    public function services_list_page()
    {
        ?>
        <div class="wrap">
            <?php
            $show_backup_button = 1;
            $service_list_obj = new self();
                
            echo '<h2 class="oping-title">'.esc_attr__( 'Backup Services List', 'zoneit-backup' ).'</h2>';
            if( 'delete' === $service_list_obj->current_action() )
            {
                if(isset($_GET['backup-service']))
                {
                    if(is_array($_GET['backup-service']) && count($_GET['backup-service']) > 1)
                    {
                        $service_list_obj->delete_items( array_map( 'absint', $_GET['backup-service'] ) );
                        echo '<div class="notice notice-success">';
                            echo '<p>'.esc_attr__('Backup Services has been deleted.', 'zoneit-backup').'</p>';
                        echo '</div>';
                    }
                    else
                    {
                        if( is_array( $_GET['backup-service'] ) )
                        {
                            $delete_elements = array_map( 'absint', $_GET['backup-service'] );
                        }
                        else
                        {
                            $delete_elements=array();
                            $delete_elements[]= absint( $_GET['backup-service'] );
                        }
                        $service_list_obj->delete_items( $delete_elements );
                        unset($delete_elements);
                        echo '<div class="notice notice-success">';
                            echo '<p>'.esc_attr__('Backup Service has been deleted.', 'zoneit-backup').'</p>';
                        echo '</div>';
                    }
                }
            }
            
            if( 'edit' === $service_list_obj->current_action() )
            {
                if( !empty( $_REQUEST['backup-service'] ) && absint( $_REQUEST['backup-service'] ) > 0 )
                {
                    $backup_service_details = Backup_Service_Core::get( [ 'id' => absint( $_REQUEST['backup-service'] ) ] );
                    if(!empty($backup_service_details))
                    {
                        if(isset($_POST['edit_service']) && isset($_POST['oping_backup_service_nonce']) && wp_verify_nonce( $_POST['oping_backup_service_nonce'], 'oping-backup-service-nonce-key' ) )
                        {
                            if(isset($_POST['id']))
                            {
                                if( isset( $_POST['service_type'] ) )
                                {
                                    if(Backup_Service_Core::edit( $_POST ) )
                                    {
                                        self::show_message( 'success', esc_attr__('The backup service has been updated in the database.', 'zoneit-backup') );
                                        $backup_service_details = NULL;
                                    }
                                    else
                                    {
                                        self::show_message( 'error', esc_attr__('The backup service hasn\'t been updated in the database.', 'zoneit-backup') );
                                    }
                                }
                                else
                                {
                                    self::show_message( 'error', esc_attr__('The service type was not found.', 'zoneit-backup') );
                                }
                            }
                            else
                            {
                                self::show_message( 'error', esc_attr__('The backup service was not found.', 'zoneit-backup') );
                            }
                        }
                    }
                    else
                    {
                        self::show_message( 'error', esc_attr__('The backup service was not found.', 'zoneit-backup') );
                    }
                }
                else
                {
                    self::show_message( 'error', esc_attr__('The backup service was not found.', 'zoneit-backup') );
                }
            }
            
            ?>
            <?php if(isset($_POST['create_service']) && isset($_POST['oping_backup_service_nonce']) && wp_verify_nonce( $_POST['oping_backup_service_nonce'], 'oping-backup-service-nonce-key' ) ) : ?>
                <?php if(isset($_POST['service_type'])) : ?>
                    <?php if( Backup_Service_Core::save( $_POST ) ) : ?>
                        <?php self::show_message( 'success', esc_attr__('The backup service has been added to the database.', 'zoneit-backup') ); ?>
                    <?php else : ?>
                        <?php self::show_message( 'error', esc_attr__('The backup service hasn\'t been added to the database.', 'zoneit-backup') ); ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
 
            <form method="post" class="bk_s_list" action="<?php if( 'edit' === $service_list_obj->current_action() && !empty( $backup_service_details ) ) echo esc_url( add_query_arg( array( 'page' => esc_attr( $_REQUEST['page'] ), 'action' => 'edit', 'backup-service' => $_REQUEST['backup-service'] ) , admin_url('admin.php') ) ); else echo esc_url( add_query_arg( array( 'page' => esc_attr( $_REQUEST['page'] ) ), admin_url('admin.php') ) );  ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_attr__('Service Type', 'zoneit-backup'); ?></th>
                            <td>
                                <?php if( 'edit' === $service_list_obj->current_action() && !empty($backup_service_details) ) : ?>
                                <p><?php echo esc_attr( $backup_service_details[0]['service_name'] ); ?></p>
                                <input type="hidden" name="id" id="id" value="<?php echo esc_attr( $backup_service_details[0]['oping_backup_service_id'] ); ?>" />
                                <input type="hidden" name="service_type" id="service_type" value="<?php echo esc_attr( $backup_service_details[0]['service_name'] ); ?>" />
                                <?php else : ?>
                                <select name="service_type" id="service_type">
                                    <option value="none"><?php echo esc_attr__('Select A Service Type','zoneit-backup'); ?></option>
                                    <?php if( !empty( Backup_Service_Core::get_filtered_service_types() ) ) : ?>
                                    <?php foreach( Backup_Service_Core::get_filtered_service_types() as $backup_service ) : ?>
                                    <option value="<?php echo esc_attr( $backup_service['name'] ); ?>" data-fields='<?php echo esc_attr( self::get_service_data_fields( $backup_service['name'] ) ); ?>'><?php echo esc_attr( $backup_service['name'] ); ?></option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_attr__('Service Data', 'zoneit-backup'); ?></th>
                            <td>
                                <p class="fill-fields">
                                    <?php if( 'edit' === $service_list_obj->current_action() && !empty($backup_service_details) ) : ?>
                                    <?php
                                    $backup_service_fields = json_decode( self::get_service_data_fields( $backup_service_details[0]['service_name'] ), true );
                                    if(!empty($backup_service_fields))
                                    {
                                        $backup_service_data = get_object_vars( Backup_Service_Core::decode_data( $backup_service_details[0]['data'] ) );
                                        foreach($backup_service_fields as $field)
                                        {
                                            $service_data_value = ( !empty( $backup_service_data[ $field['id'] ] ) ) ? esc_attr( $backup_service_data[ $field['id'] ] ) : '';
                                            echo '<label for="'. esc_attr( $field['id'] ) .'" style="display:inline-block; min-width:170px" >'. esc_attr( $field['name'] ) .'</label>';
                                            echo '<input type="'. esc_attr( $field['type'] ) .'" id="'. esc_attr( $field['id'] ) .'" size="'. esc_attr( $field['size'] )  .'" class="'. esc_attr( $field['class'] ) .'" name="'. esc_attr( $field['id'] ) .'" value="'. esc_attr( $service_data_value ) .'" placeholder="'. esc_attr( $field['placeholder'] ) .'" style="direction:ltr;text-align:left;margin-bottom:10px;" /><br />';
                                        }
                                    }
                                    ?>
                                    <?php else : ?>
                                    -
                                    <?php endif; ?>
                                </p>
                                <p class="description"><?php echo esc_attr__('All service data will be encrypted in the database.', 'zoneit-backup'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php wp_nonce_field('oping-backup-service-nonce-key','oping_backup_service_nonce'); ?>
                <input type="submit" id="submit" name="<?php if( 'edit' === $service_list_obj->current_action() && !empty($backup_service_details) ) echo 'edit_service'; else echo 'create_service'; ?>" class="button button-primary" value="<?php echo ( 'edit' === $service_list_obj->current_action() && !empty($backup_service_details) ) ? esc_attr__('Edit Backup Service', 'zoneit-backup') : esc_attr__('Create Backup Service', 'zoneit-backup'); ?>" >
            </form>
            <hr style="margin-top:20px"/>
            <?php 
            if( isset( $_GET['s'] ) ) {
                $service_list_obj->prepare_items( $_GET['s'] );
            } else {
                $service_list_obj->prepare_items();
            }
            $service_list_obj->views();
            ?>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
            <?php
                $service_list_obj->display(); 
            ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public static function sanitize( $input )
    {
        $new_input = array();

        if( isset( $input['service_type'] ) )
        {
            $service_list = [];
            $backup_services_list = apply_filters('oping_backup_services', $service_list );
            if( in_array( $input['service_type'], $backup_services_list ) )
            {
                $new_input['service_type'] = sanitize_text_field( $input['service_type'] );
            }
        }

        return $new_input;
    }

    /**
     * Show message to user when form submitted
     *
     * @param type message type like error or success
     * @param msg message content or text
     */
    public function show_message( $type, $msg )
    {
        echo '<div class="notice notice-'. esc_attr( $type ).'">';
            echo '<p>';
                echo esc_attr( $msg );
            echo '</p>';
        echo '</div>';
    }

} //class


if (class_exists('Backup_Service_List'))
    new Backup_Service_List();