<?php
/**
 * Cron Backups Class
 * This class is creating the page for creating backup.
 * 
 * Package: Oping Backup
 * Author: Oping Cloud
 * DateTime: 2022/11/22 21:00
 * Last Modified Time: 2024/07/21 20:32:48
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */ 

//namespace OpingBackups;

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

class Cron_Backup
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
        $this->option_name = 'oping_cron_backup';
        
        // add cronjob for update currencies using api
		add_action( 'cron_schedules', array( $this, 'add_custom_schedules') );
        
        // admin initialize
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 102 );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        
        // ajax request for create backup
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_files' ) );
        add_action( 'admin_footer', array( $this, 'admin_footer_scripts' ) );
        
        // update option
        add_action( 'update_option_'.$this->option_name , array( $this, 'update_cron_event' ), 10, 3 );
		
    }
    
    /**
	 * Add cron half minutes
	 */
	public function add_custom_schedules( $schedules )
	{
		if(!isset($schedules['every_six_hours']))
		{
			$schedules['every_six_hours'] = array(
				'interval' => 6 * HOUR_IN_SECONDS ,
				'display' => esc_attr__('Every 6 Hours', 'zoneit-backup')
			);
		}
		
		if(!isset($schedules['every_twelve_hours']))
		{
			$schedules['every_twelve_hours'] = array(
				'interval' => 12 * HOUR_IN_SECONDS ,
				'display' => esc_attr__('Every 12 Hours', 'zoneit-backup')
			);
		}

		return $schedules;
	}
    
    /**
     * Admin Enqueue Files
     */
    public function admin_enqueue_files( $hook )
    {
        if( $hook != 'oping-backup_page_oping-cron-backup' )
            return;
            
        // Enqueue styles with versioning
        wp_enqueue_style('timepicker', OPING_BACKUP_PLUGIN_URL . 'assets/css/timepicker.css', array(), OPING_BACKUP_PLUGIN_VERSION);
        wp_enqueue_style('main-css', OPING_BACKUP_PLUGIN_URL . 'assets/css/main.css', array(), OPING_BACKUP_PLUGIN_VERSION);
        
        // Register and enqueue scripts with versioning
        wp_enqueue_script('timepicker', OPING_BACKUP_PLUGIN_URL . 'assets/js/timepicker.js', array('jquery'), OPING_BACKUP_PLUGIN_VERSION, true);
        
        // Localize script with parameters
        wp_localize_script('timepicker', 'timepicker_params', [
            'label' => esc_attr__('Pick A Time', 'zoneit-backup')
        ]);
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_submenu_page(
            'oping-backups',
            esc_attr__('Cron Backup', 'zoneit-backup'), 
            esc_attr__('Cron Backup', 'zoneit-backup'), 
            'manage_options',
            'oping-cron-backup', 
            array( $this, 'cron_backup_page' )
        );
    }

    /**
     * Options page callback
     */
    public function cron_backup_page()
    {
        // Set class property
        $this->options = get_option( $this->option_name );
        ?>
        <div class="wrap">
            <h1><?php echo esc_attr__('Cron Backup Settings', 'zoneit-backup'); ?></h1>
            <?php if( !empty( $this->options ) && isset( $this->options['enable'] ) && $this->options['enable'] == "yes" ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        // translators: %s is the cron schedule string.
                        printf( esc_attr__('The cron backup has been scheduled: %s' , 'zoneit-backup' ), esc_attr( $this->get_cron_str() ) );
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            <form method="post" class="cron_list" action="options.php" >
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'oping_cron_backup_group' );
                do_settings_sections( 'oping-cron-backup' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'oping_cron_backup_group', // Option group
            'oping_cron_backup', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'cron_backup_setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'oping-cron-backup' // Page
        );
        
        add_settings_field(
            'enable_cron', // ID
            esc_attr__('Enable Cron Backup', 'zoneit-backup'), // Title 
            array( $this, 'cron_enable_callback' ), // Callback
            'oping-cron-backup', // Page
            'cron_backup_setting_section_id' // Section           
        );

        add_settings_field(
            'cron_type', // ID
            esc_attr__('Cron Type', 'zoneit-backup'), // Title 
            array( $this, 'cron_type_callback' ), // Callback
            'oping-cron-backup', // Page
            'cron_backup_setting_section_id' // Section           
        );      

        add_settings_field(
            'cron_time', // ID
            esc_attr__('Cron Time', 'zoneit-backup'), // Title 
            array( $this, 'cron_time_callback' ), // Callback
            'oping-cron-backup', // Page
            'cron_backup_setting_section_id' // Section           
        );     
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        
        if( isset( $input['enable'] ) && $input['enable'] == 'yes' )
            $new_input['enable'] = 'yes';
        else
            $new_input['enable'] = 'no';

        if( isset( $input['type'] ) )
            $new_input['type'] = sanitize_text_field( $input['type'] );

        if( isset( $input['time'] ) )
            $new_input['time'] = sanitize_text_field( $input['time'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print '';
    }
    
    /** 
     * Get the settings option array and print one of its values
     */
    public function cron_enable_callback()
    {
        printf(
            '<input type="checkbox" name="%s[enable]" id="cron_enable" value="yes" %s> %s',
            esc_attr( $this->option_name ),
            ( isset( $this->options['enable'] ) && $this->options['enable'] =='yes' ) ? 'checked="checked"' : '' ,
            esc_attr__('Enable', 'zoneit-backup')
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function cron_type_callback()
    {
        printf(
            '<select name="%s[type]" id="cron_type" %s>
                <option value="every_six_hours" %s>%s</option>
                <option value="every_twelve_hours" %s>%s</option>
                <option value="daily" %s>%s</option>
                <option value="weekly" %s>%s</option>
            </select>',
            esc_attr( $this->option_name ),
            ( isset( $this->options['enable'] ) && $this->options['enable'] == "yes" ) ? '' : 'disabled="disabled"',
            ( isset( $this->options['type'] ) && $this->options['type'] =='every_six_hours' ) ? 'selected="selected"' : '' ,
            esc_attr__('Every 6 Hours', 'zoneit-backup'),
            ( isset( $this->options['type'] ) && $this->options['type'] =='every_twelve_hours' ) ? 'selected="selected"' : '' ,
            esc_attr__('Every 12 Hours', 'zoneit-backup'),
            ( isset( $this->options['type'] ) && $this->options['type'] =='daily' ) ? 'selected="selected"' : '' ,
            esc_attr__('Daily', 'zoneit-backup'),
            ( isset( $this->options['type'] ) && $this->options['type'] =='weekly' ) ? 'selected="selected"' : '' ,
            esc_attr__('Weekly', 'zoneit-backup')
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function cron_time_callback()
    {
        printf(
            '<input type="text" id="cron_time" name="%s[time]" value="%s" size="17" %s/>',
            esc_attr( $this->option_name ),
            isset( $this->options['time'] ) ? esc_attr( $this->options['time'] ) : '',
            ( isset( $this->options['enable'] ) && $this->options['enable'] == "yes" ) ? '' : 'disabled="disabled"'
        );
    }
    
    /**
     * Admin footer scripts
     */
    public function admin_footer_scripts()
    {
        $current_screen = get_current_screen();
        if( $current_screen->base != 'oping-backup_page_oping-cron-backup' )
            return;
        ?>
        <script>
        jQuery(document).ready(function(){
            jQuery("#cron_time").timepicker();
            
            jQuery("#cron_enable").click(function(){
                if( jQuery(this).is(':checked') )
                {
                    jQuery("#cron_type").prop('disabled', false );
                    jQuery("#cron_time").prop('disabled', false );
                }
                else
                {
                    jQuery("#cron_type").prop('disabled', true );
                    jQuery("#cron_time").prop('disabled', true );
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Update date & time every option field callback
     * @param old_value
     * @param value
     * @param option
     */
    public function update_cron_event( $old_value, $new_value, $option )
    {
        $params = ['service_type' => 'Localhost' ];
        if( $new_value['enable'] == 'yes')
        {
            if( $old_value['type'] != $new_value['type'] || $old_value['time'] != $new_value['time'] )
    		{
    		    // setup cron schedules
    			if($new_value['type']=='every_six_hours')
    			{
    			    $cron_schedules = 'every_six_hours';
    			}
    			elseif($new_value['type']=='every_twelve_hours')
    			{
    			    $cron_schedules = 'every_twelve_hours';
    			}
    			elseif($new_value['type']=='daily')
    			{
    			    $cron_schedules = 'daily';
    			}
    			else
    			{
    			    $cron_schedules = 'weekly';
    			}
    			
    			// setup cron time
    			$cron_time = sanitize_text_field( $new_value['time'] );
    			
    			// remove another schedule
            	wp_unschedule_hook('oping_create_backup_event');
            	
    			// set new schedule event
    			if( !empty( get_option('timezone_string') ) )
                    date_default_timezone_set( get_option('timezone_string') );
    			wp_schedule_event( strtotime( date("Y-m-d")." ".$cron_time ), $cron_schedules, 'oping_create_backup_event', $params );  
    		}
        }
        else
        {
            // remove another schedule
            wp_unschedule_hook('oping_create_backup_event');
        }
    }
    
    /**
     * Return Cron scheduled time to user
     * 
     * @param void
     * @return string cron_str
     */
    public function get_cron_str()
    {
        $cron_schedule = '';
        $cron_options = get_option( $this->option_name );
        if( !empty( $cron_options ) )
        {
            $cron_recurrence = '';
            switch( $cron_options['type'] )
            {
                case "every_six_hours":
                    $cron_recurrence = esc_attr__('Every 6 Hours', 'zoneit-backup');
                    break;
                case "every_twelve_hours":
                    $cron_recurrence = esc_attr__('Every 12 Hours', 'zoneit-backup');
                    break;
                case "daily":
                    $cron_recurrence = esc_attr__('Daily', 'zoneit-backup');
                    break;
                case "weekly":
                    $day_of_date = date('l', $this->get_next_cron_time( 'oping_create_backup_event' ) );
                    // translators: %s is the cron schedule string.
                    $cron_recurrence = sprintf( esc_attr__("Weekly (Every %s)", 'zoneit-backup'), $day_of_date );
                    break;
            }
            $cron_schedule = sprintf('%1$s, %2$s %3$s', $cron_recurrence, esc_attr__('at', 'zoneit-backup'), $cron_options['time'] );
        }
        
        return $cron_schedule;
    }
    
    /**
     * Returns timestamp of next run cron job
     * @param string $cron_name The name of the cron job
     * @param int|bool the timestamp of run cron
     */
    protected function get_next_cron_time( $cron_name )
    {
        foreach( _get_cron_array() as $timestamp => $crons )
        {
            if( in_array( $cron_name, array_keys( $crons ) ) )
            {
                return $timestamp;
            }
        }
    
        return false;
    }
}

if (class_exists('Cron_Backup'))
    new Cron_Backup();