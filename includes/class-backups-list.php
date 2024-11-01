<?php
/**
 * Backups List Class
 * backups list using wp list table
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/11/06 16:38
 * Last Modified Time: 2024/08/16 01:45:04
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

class BackupsList extends WP_List_Table
{

	var $backups_data = array();

    function __construct()
	{
		global $status, $page;

			parent::__construct( array(
				'singular'  => esc_attr__( 'oPing Backup', 'zoneit-backup' ),     //singular name of the listed records
				'plural'    => esc_attr__( 'oPing Backups', 'zoneit-backup' ),   //plural name of the listed records
				'ajax'      => false        //does this table support ajax?

		) );

        add_action( 'admin_menu', array( $this, 'add_submenu' ), 100 );
        
        // ajax request for create backup
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_files' ) );
        add_action( 'admin_footer', array( $this, 'admin_footer_scripts' ) );
        
        add_action( 'wp_ajax_oping_restore_backup', array( $this, 'ajax_restore_backup' ) );
        add_action( 'wp_ajax_nopriv_oping_restore_backup', array( $this, 'ajax_restore_backup' ) );
        
    }
    
    /**
     * Admin Enqueue Files
     */
    public function admin_enqueue_files( $hook )
    {
        if( $hook != 'toplevel_page_oping-backups' )
            return;
        
        // Enqueue styles with versioning using plugin version constant
        wp_enqueue_style('sweetalert', OPING_BACKUP_PLUGIN_URL . 'assets/css/sweetalert2.min.css', array(), OPING_BACKUP_PLUGIN_VERSION);
        wp_enqueue_style('main', OPING_BACKUP_PLUGIN_URL . 'assets/css/main.css', array(), OPING_BACKUP_PLUGIN_VERSION);
        
        // Enqueue scripts with versioning using plugin version constant
        wp_enqueue_script('sweetalert', OPING_BACKUP_PLUGIN_URL . 'assets/js/sweetalert2.min.js', array(), OPING_BACKUP_PLUGIN_VERSION, true );
    }
    
    /**
     * Admin footer scripts
     */
    public function admin_footer_scripts()
    {
        $current_screen = get_current_screen();
        if( $current_screen->parent_base != 'oping-backups' )
            return;
        ?>
        <script>
        jQuery(document).on('click', '.restore-backup', function(e){
            e.preventDefault();
            var backup_id = jQuery(this).data('id');
			var oping_restore_nonce = '<?php echo esc_attr( wp_create_nonce('oping_restore_nonce') ); ?>';
			Swal.fire({
				title: "<?php echo esc_attr__('Warning!', 'zoneit-backup'); ?>",
				text: "<?php echo esc_attr__('Do you want to restore this backup?', 'zoneit-backup'); ?>",
				icon: "warning",
				dangerMode: true,
				showCancelButton: true,
				confirmButtonText: "<?php echo esc_attr__('Yes, I\'m Sure!', 'zoneit-backup'); ?>",
				cancelButtonText: "<?php echo esc_attr__('No', 'zoneit-backup'); ?>",
				customClass: {
					confirmButton: 'btn btn-success',
				},
			}).then(function (result) {
			    if (result.isConfirmed) {
			        jQuery.ajax({
                        method: 'POST', 
                        url: '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',
                        data: { action: 'oping_restore_backup', backup_id : backup_id, oping_restore_nonce: oping_restore_nonce },
                        dataType: 'json',
                        beforeSend: function() {
                        },
                        success: function(response) {
                            if(response.status=="ok")
                            {
                                Swal.fire(
                                  '<?php echo esc_attr__('Success!', 'zoneit-backup'); ?>',
                                  response.msg,
                                  'success'
                                ).then(function() {
                                    window.location.href = response.url;
                                });
                            }
                            else
                            {
                                Swal.fire(
                                  '<?php echo esc_attr__('Error!', 'zoneit-backup'); ?>',
                                  response.msg,
                                  'error'
                                );
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
			    }
			    
			});
        });
        </script>
        <script>
        jQuery(document).ready(function(){
            jQuery('.copyButton').on('click', function(event) {
                event.preventDefault();
                var index = jQuery('.copyButton').index(this); // Get the index of the clicked button
                var copyText = jQuery('.download_link').eq(index);
                copyText.focus(); // Ensure the input field is focused
                copyText.select(); // Select text inside input field
                try {
                    var successful = document.execCommand('copy'); // Copy selected text
                    var msg = successful ? 'successful' : 'unsuccessful';
                } catch (err) {
                    console.error('Unable to copy:', err);
                }
            });
        });
        jQuery(document).ready(function() {
            // Toggle dropdown when the dropbtn is clicked
            jQuery('.dropbtn').on('click', function(event) {
                event.stopPropagation(); // Prevent the click event from reaching the window
                
                var dropdown = jQuery(this).next('.dropdown-content');
                dropdown.toggleClass('show');
                event.preventDefault();
            });
        
            // Close dropdown when clicking outside the button or dropdown content
            jQuery(document).on('click', function(event) {
                if (!jQuery(event.target).closest('.dropbtn').length && !jQuery(event.target).closest('.dropdown-content').length) {
                    jQuery('.dropdown-content').removeClass('show');
                }
            });
        }); 
        </script>
        <?php
    }
    
    /**
     * Restore backup
     * 
     */
    public function ajax_restore_backup()
    {
        $result = [];
		
		if( isset( $_POST['oping_restore_nonce'] ) && wp_verify_nonce( $_POST['oping_restore_nonce'] ,'oping_restore_nonce'))
		{
		    $backup_id = absint( $_POST['backup_id'] );

            if( !empty( $backup_id ) && $backup_id > 0 )
            {
                $oping_backup_info = Backup_Core::get( [ 'id' => $backup_id ] );
                if( !empty( $oping_backup_info ) )
                {
                    Restore_Core::restore_backup_event( [ 'backup_id' => $oping_backup_info[0]['oping_backup_id'] ] );
                    $target_url = esc_url( add_query_arg( array( 'page' => 'oping-backups' ), admin_url('admin.php') ) );
    
                    $result = array( 'status' => 'ok', 'msg' => esc_attr__('The backup restore has been started. It will be restored soon.', 'zoneit-backup') , 'url' => $target_url );
                }
                else
                {
                    $result = array( 'status' => 'no' , 'msg' => esc_attr__('This backup is invalid', 'zoneit-backup') );
                }
            }
            else
            {
                $result = array( 'status' => 'no' , 'msg' => esc_attr__('This backup is invalid', 'zoneit-backup') );
            }
		}
		else
		{
		    $result = array( 'status' => 'no' , 'msg' => esc_attr__('The nonce key is invalid', 'zoneit-backup') );
		}
        
        echo wp_json_encode( $result );
        wp_die();
    }

    /**
     * Add submenu page
     */
    public function add_submenu()
    {
        // Menu
        add_menu_page (
			esc_attr__('oPing Backup', 'zoneit-backup'), 
            esc_attr__('oPing Backup', 'zoneit-backup'), 
            'manage_options', 
            'oping-backups',
			array( $this, 'backups_list_page' ),
            'data:image/svg+xml;base64,' . base64_encode('<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 512 512" xml:space="preserve"><style>.oping-svg{fill:#fff;}</style><path class="oping-svg" d="M366.1,84.4C334.8,64.5,297.7,53,257.9,53C146.2,53,55.6,143.7,55.6,255.5c0,22.7,3.7,44.5,10.6,64.9c-33.1,44.1-46.7,80.6-32.8,98.4c13.4,17.3,49.8,14,98.1-5.2c0.1,0.1,0.1,0.1,0.2,0.2c34.6,27.7,78.4,44.2,126.2,44.2c111.7,0,202.3-90.7,202.3-202.5c0-28.7-6-56.1-16.8-80.8c34.8-45.5,49.4-83.3,35.2-101.6C463.9,54.2,421.5,59.9,366.1,84.4L366.1,84.4z M388.2,255.5c0,72-58.3,130.4-130.3,130.4c-17.9,0-34.9-3.6-50.5-10.1c31.2-18.3,64.4-40.7,97.6-66.5c30.4-23.6,58-47.8,81.9-71.4C387.8,243.6,388.2,249.5,388.2,255.5z M356.8,170.6c-25.1,26.7-56.8,55.7-93.8,84.4c-39.9,31-78.7,56-112.7,74.1c-14.3-21-22.7-46.3-22.7-73.6c0-72,58.3-130.4,130.3-130.4C297.5,125.1,332.9,142.7,356.8,170.6z"/></svg>')
		);

		// Submenu
		add_submenu_page(
			'oping-backups',
			esc_attr__('Backups List', 'zoneit-backup'), 
            esc_attr__('Backups List', 'zoneit-backup'), 
			'manage_options', 
			'oping-backups',
			array( $this, 'backups_list_page' )
		);
    }

	function column_default( $item, $column_name )
	{
	    global $wpdb;
		$current_screen = get_current_screen();
		
		switch( $column_name ) {
			case 'service_type':
				$backup_service_types = Backup_Service_Core::get_service_types();
			    $founded_key = array_search( $item[ $column_name ] , array_column( $backup_service_types , 'id' ) );
				return $backup_service_types[ $founded_key ]['name'];
			case 'backup_url':
				return (!empty($item[ $column_name ])) ? '<div class="dl_row"><input type="text" value="'.esc_url( $item[ $column_name ] ).'" class="download_link"><button class="copyButton" ><img src="'.OPING_BACKUP_PLUGIN_URL.'assets/img/copy.svg" /></button></div><div class="dropdown"><button class="dropbtn" >...</button><div id="myDropdown" class="dropdown-content"><a class="dl_link dr_link" href="'.esc_url( $item[ $column_name ] ).'">'. esc_attr__('Download', 'zoneit-backup') .'</a><a class="dr_link restore_link restore-backup" data-id="'. $item['oping_backup_id'] .'" href="#">'. esc_attr__('Restore', 'zoneit-backup') .'</a><a href="?page=' . $current_screen->parent_base . '&action=delete&backup='. $item['oping_backup_id'] . '" class="dr_link delete_link red">'. esc_attr__('Delete Permanently', 'zoneit-backup') .'</a></div></div> ' : '-';
            
            case 'status':
                $status_name = Backup_Core::get_status_name( $item[ $column_name ] );
                $status_message = (!empty($item['message'])) ? '<br />'.$item['message'] : '';
                return $status_name.$status_message;
			case 'date_created':
            case 'date_modified':
				return date_i18n('Y/m/d H:i:s', strtotime( $item[ $column_name ] ) );
			default:
				return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
		}
            ?>

            <?php
	}

	function get_sortable_columns()
	{
		$sortable_columns = array(
			'service_type'  => array( 'service_type', false ),
			'date_created'  => array( 'date_created', false ),
          //  'date_modified'  => array( 'date_modified', false ),
		);
		return $sortable_columns;
	}

	function get_columns()
	{
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'service_type' => esc_attr__( 'Service Type', 'zoneit-backup' ),
			'status' => esc_attr__( 'Status', 'zoneit-backup' ),
			'date_created' => esc_attr__( 'Date', 'zoneit-backup' ),
			'backup_url' => esc_attr__( 'Backup/Restore', 'zoneit-backup' ),
			//'date_modified' => esc_attr__( 'Updated', 'zoneit-backup' ),
		);
		return $columns;
	}

	function usort_reorder( $a, $b )
	{
		// If no sort, default to title
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : $item['oping_backup_id'];
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
		$current_screen = get_current_screen();
		
		$views = array();
		$current = ( !empty($_REQUEST['backup_status']) ? $_REQUEST['backup_status'] : 'all');

		// All link
		$all_data = Backup_Core::get();
		$class = ($current == 'all' ? ' class="current"' :'');
		$all_url = esc_url( add_query_arg( array( 'page' => esc_attr( $current_screen->parent_base ) ), admin_url('admin.php') ) );
		$views['all'] = "<a href='{$all_url}' {$class} >".esc_attr__('All')." (".count($all_data).")</a>";
	   
	   return $views;
	}

	function column_service_type( $item )
	{
		$current_screen = get_current_screen();
	    
		$actions = array(
            'delete'    => sprintf('<a href="?page=%s&action=%s&backup=%s">%s</a>', esc_attr( $current_screen->parent_base ), 'delete', $item['oping_backup_id'], esc_attr__('Delete Permanently', 'zoneit-backup') )
        );
		
		$backup_service_types = Backup_Service_Core::get_service_types();
	    $founded_key = array_search( $item[ 'service_type' ] , array_column( $backup_service_types , 'id' ) );
		return sprintf( '%1$s', $backup_service_types[ $founded_key ]['name'] );
	}

	function get_bulk_actions()
	{
		$actions = array(
            'delete'    => esc_attr__('Delete Permanently', 'zoneit-backup')
        );
		
		return $actions;
	}

	function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="backup[]" value="%s" />', $item['oping_backup_id']
		);   
	}
	
	function delete_element($elements)
	{
		global $wpdb;
		
		// Delete From Oping backups
		foreach($elements as $item)
            Backup_Core::delete( $item );

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
            $params['orderby'] = "oping_backup_id";
		
        /* If the value is not NULL, do a search for it. */
        if( $search != NULL ){
            
            /* Notice how you can search multiple columns for your search term easily, and return one data set */
            $params['search'] = trim( $search );
        }

        $this->backups_data = Backup_Core::get( $params );
        
        if( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) )
            usort($this->backups_data, array(&$this, 'usort_reorder'));   
        else
            usort($this->backups_data, function (array $a, array $b) { return -($a["oping_backup_id"] - $b["oping_backup_id"]); } );
        
        $current_page = $this->get_pagenum();
        
        $total_items = count( $this->backups_data );
        
        $this->backups_data = array_slice( $this->backups_data, ( ( $current_page - 1 ) * $per_page ), $per_page );
        
        $this->items = $this->backups_data;
        
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }

    public function backups_list_page()
    {
        ?>
        <div class="wrap">
            <?php
            $show_backup_button = 1;
            if( !empty( Backup_Core::get( [ 'status' => 1 ] ) ) ) // get backup with status in_progress
            {
                self::show_message( 'success', esc_attr__('The backup will start in a few moments. You cannot create a new backup until this backup is completed.', 'zoneit-backup') );
                $show_backup_button = 0;
            }
            
            if( !empty( get_transient('oping_restore_backup_running') ) )
            {
                self::show_message( 'success', esc_attr__('The backup restore process has started. We recommend that you avoid making changes to the website because the new changes will be lost after the restore operation.', 'zoneit-backup' ) );
                $show_backup_button = 0;
            }
                
            echo '<h2 class="oping-title">'.esc_attr__( 'Backups List', 'zoneit-backup' ).'</h2>';
            if( 'delete' === self::current_action() )
            {
                if(isset($_GET['backup']))
                {
                    if(is_array($_GET['backup']) && count($_GET['backup']) > 1)
                    {
                        self::delete_element( array_map( 'absint', $_GET['backup'] ) );
                        echo '<div class="notice notice-success">';
                            echo '<p>'.esc_attr__('Backups has been deleted.', 'zoneit-backup').'</p>';
                        echo '</div>';
                    }
                    else
                    {
                        if(is_array($_GET['backup']))
                        {
                            $delete_elements = array_map('absint', $_GET['backup'] );
                        }
                        else
                        {
                            $delete_elements=array();
                            $delete_elements[]= absint( $_GET['backup'] );
                        }
                        self::delete_element( $delete_elements );
                        unset($delete_elements);
                        echo '<div class="notice notice-success">';
                            echo '<p>'.esc_attr__('Backup has been deleted.', 'zoneit-backup').'</p>';
                        echo '</div>';
                    }
                }
                $flag=0;
            }
            ?>
            <?php if(isset($_POST['oping_backup_nonce']) && wp_verify_nonce( $_POST['oping_backup_nonce'], 'oping-nonce-key' ) ) : ?>
            
                <?php if(isset($_POST['service_type'])) : ?>
                    <?php Backup_Core::run_backup_event( [ 'service_type' => $_POST['service_type'], 'user_id' => get_current_user_id() ] ); ?>
                    <?php self::show_message( 'success', esc_attr__('The backup will start in a few moments. You cannot create a new backup until this backup is completed.', 'zoneit-backup') ); ?>
                    <?php $show_backup_button = 0; ?>
                <?php else : ?>
                    <?php self::show_message( 'error', esc_attr__('Service Type is not valid.', 'zoneit-backup') ); ?>
                <?php endif; ?>
            <?php endif; ?>
            <form method="post" class="s_l_bk" action="<?php echo esc_url( add_query_arg( array( 'page' => esc_attr( $_REQUEST['page'] ) ), admin_url('admin.php') ) );  ?>">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr class="s_l_row">
                            <th scope="row"><?php echo esc_attr__('Backup Services', 'zoneit-backup'); ?></th>
                            <td>
                                <?php $backup_services_list = Backup_Service_Core::get_service_types(); ?>
                                <select name="service_type" id="service_type">
                                    <?php if( !empty( $backup_services_list ) ) : ?>
                                    <?php foreach( $backup_services_list as $backup_service ) : ?>
                                    <option value="<?php echo esc_attr( $backup_service['name'] ); ?>"><?php echo esc_attr( $backup_service['name'] ); ?></option>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </td>
                    </tbody>
                </table>
                <?php wp_nonce_field('oping-nonce-key','oping_backup_nonce'); ?>
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Create Backup', 'zoneit-backup'); ?>" <?php if( ! $show_backup_button ) echo 'disabled="disabled"'; ?> >
            </form>
            <?php 
            if( isset( $_GET['s'] ) ) {
                self::prepare_items( $_GET['s'] );
            } else {
                self::prepare_items();
            }
            self::views();
            ?>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
            <?php
                self::search_box( esc_attr__('Search', 'zoneit-backup'), 'search_id' );
                self::display(); 
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
            $service_types = Backup_Service_Core::get_service_types();
            if( in_array( $input['service_type'], array_column( $service_types , 'name' ) ) )
            {
                $founded_key = array_search( $input['service_type'] , array_column( $service_types , 'name' ) );
                $new_input['service_type'] = $service_types[$founded_key]['name'];
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


if (class_exists('BackupsList'))
    new BackupsList();