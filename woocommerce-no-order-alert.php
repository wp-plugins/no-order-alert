<?php
/**
 * Plugin Name: WooCommerce No Order Alert
 * Plugin URI: http://multidots.com/
 * Description: User will set custom time duration to send the emails related to last placed order to admin.
 * Author: Multidots
 * Author URI: http://multidots.com/
 * Version: 1.0.0
 *
 * Copyright: (c) 2014-2015 Multidots Solutions PVT LTD (info@multidots.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    Multidots
 * @category  Plugin
 * @copyright Copyright (c) 2014-2015 Multidots Solutions Pvt. Ltd.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */



/**
 * prevent direct access data leaks
 *
 * This is the condition to prevent direct access data leaks.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}


//Check if WooCommerce is active
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins' , get_option( 'active_plugins' ) ) ) ) {
	add_action( 'admin_init', 'woocommerce_parent_plugin_not_installed' );   
}


/**
 * parent_plugin_not_installed function
 *
 * This is the function executes when plugin is activated but parent woocommerce plugin is not installed or activated.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_parent_plugin_not_installed() {
    if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        
    	add_action( 'admin_notices', 'woocommerce_parent_plugin_notice' );

        deactivate_plugins( plugin_basename( __FILE__ ) ); 

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}

//This is the Filter for initialise a query variable.
add_filter('query_vars','woocommerce_no_order_alert_cron_notification');

/**
 * no_order_alert_cron_notification function
 *
 * This is the function executes when query_vars filter runs.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_no_order_alert_cron_notification($no_order_alert_vars) {
    $no_order_alert_vars[] = 'no_order_alert_activation';
    return $no_order_alert_vars;
}
 
//This is the Hook for the executes the cron module and run the no order alert email.
add_action('template_redirect', 'woocommerce_no_order_alert_notification_executes');

/**
 * no_order_alert_notification_executes function
 *
 * This is the function executes when cron url is executes.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_no_order_alert_notification_executes() {
	
	$secure_key    = "order@mdots";
	$url_parameter = md5($secure_key);
	   
	if( intval( mysql_real_escape_string(get_query_var( 'no_order_alert_activation' ) ) ) == $url_parameter ) {
		
    	//Constant variable used for check last placed order duration.
    	$constant_duration = "1";    
    	
        //woocommerce global variable for the get all order statuses used because sometimes user have its own order status so this is for get all the statuses. author multidots.
        global $woocommerce;
        
        //This is for the calculating the time difference 
        $order_posts         = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array_keys( wc_get_order_statuses() )
        ));
        
        $to_time             = strtotime( current_time( 'mysql' ) );
        $from_time           = strtotime( $order_posts[0]->post_date );
        $last_order_placed   = date( 'd-m-Y' ,strtotime($order_posts[0]->post_date) );
        $last_order_time   = date( 'H:m' ,strtotime($order_posts[0]->post_date) );
		$last_order_placed = $last_order_placed.",".$last_order_time;
        
		$difference 		 = round( abs($to_time - $from_time) / 60 );
        $total_added_minutes = ( get_option('hour_interval') * 60) + get_option('minute_interval');
        $diff                = human_time_diff( $from_time, $to_time );
        $site_title			 = get_bloginfo( 'name' );

        $to_time_alert=current_time( 'mysql' );
     	$last_alert_sent=get_option( 'last_alert' );
		$difference_of_last_alert=round(abs(strtotime($to_time_alert) - strtotime($last_alert_sent)) / 60);
        
        $rec_email      = get_option( 'admin_email' ) . "," . get_option( 'reciever_emails' );
        $rec_email_array = explode( ",", $rec_email );
        $rec_email_string = implode( "," , array_unique($rec_email_array) );
        
		if( 1 == $constant_duration ) {
			$email_template = " Hi Admin, \n\nYour store have not received any order from last {order} hour. \n\nYou received last order on: {last_order} \n\nRegards, \n\n{thanks}"; 
		} else {   
			$email_template = " Hi Admin, \n\nYour store have not received any order from last {order} hours. \n\nYou received last order on: {last_order} \n\nRegards, \n\n{thanks}"; 
		}
		
        $email_template = str_replace( '{order}' , $constant_duration, $email_template );
        $email_template = str_replace( '{last_order}' , $last_order_placed, $email_template );
        $email_template = str_replace( '{thanks}' , $site_title, $email_template);
        
		/**
		 * users_new_mail_from_name function
		 *
		 * This is the function for change default from name in mail
		 *
		 * @version		1.0.0
		 * @author 		Multidots
		 */
		function users_new_mail_from_name() {
			return 'WooCommerce No Order Alert';
		}
		add_filter('wp_mail_from_name', 'users_new_mail_from_name');
      
        if( ( $constant_duration * 60 ) < $difference_of_last_alert ) {
        	
	        //Sending email notification to users
			$subject = "WooCommerce No Order Alert";
	        $headers = "From: " . get_option( 'admin_email' ) . "\r\n";
			wp_mail( $rec_email_string, sanitize_text_field( $subject ), wp_strip_all_tags( $email_template ), $headers );
	        update_option( 'last_alert' , $to_time_alert );
        }
        wp_safe_redirect(site_url());
    }
}


/**
 * parent_plugin_notice function
 *
 * This is the notice hook, runs when plugin is not activated.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_parent_plugin_notice(){
    echo '<div class="error"><p>No Order Alert plugin is not activated due to WooCommerce plugin is not installed or active.</p></div>';
}

/**
 * order_notification_plugin_menu function
 *
 * This function is used to create the page interface in the admin panel side.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_order_notification_plugin_menu() {
    //Create admin page interface
    //add_submenu_page( 'No Order Alert', 'No Order Alert', 'manage_options', 'woocommerce-no-order-alert.php', 'woocommerce_order_notifications_plugin_options' );
    add_submenu_page( 'woocommerce', 'WooCommerce No Order Alert', 'WooCommerce No Order Alert', 'manage_options', 'woocommerce-no-order-alert.php', 'woocommerce_order_notifications_plugin_options' ); 
}
add_action('admin_menu', 'woocommerce_order_notification_plugin_menu',99);

/**
 * order_notifications_plugin_options function
 *
 * This function is called when admin_option_page hook initialised.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_order_notifications_plugin_options() {
	
    //Calling method for options updates
    woocommerce_no_order_alert_form_submit_method();
    $hours           = get_option( 'hour_interval' );
    $reciever_emails = get_option( 'reciever_emails' );
    $admin_user_info = get_userdata( 1 );
        
    //This is the security module for the user rights. only those users can view the page who have the manage_options rights.
    if (!current_user_can( 'manage_options' ) ) {
        wp_die(__( 'You do not have sufficient permissions to access this page.' ));
    }
?>
	<div class="wrap">
		<h2>WooCommerce No Order Alert</h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"> Configure below URL to run (execute) automatically at every 1 hour interval. Normally you can do this using your hosting company Control Panel to setup a cron job. <a href="https://documentation.cpanel.net/display/ALD/Cron+Jobs#CronJobs-Addacronjob" rel="nofollow" target="_blank">Click here</a> to know more about setting up a cron job. </th>
				</tr>
				<tr>
					<!-- This is the input label for the url which provides the plugin which you have to place on your server cron job.-->
					<td style="padding:0px;"><input size="110" type="text" readonly value="<?php echo site_url()."?no_order_alert_activation=" . md5("order@mdots") . ""; ?>"></input></td>
				</tr>
			</tbody>
		</table><br />
		
		<h2>Settings</h2>
		<!-- Form creation for the admin page -->
		<form method="post" action=""> 
			<table class="form-table">
				<tbody> 
					<tr>
						<th scope="row">Hours:</th>
						<td><input onKeyUp="this.value = this.value.replace(/[A-Za-z()%^&_?<>~!@#+$\-*/.]/ig, '');" <?php if ( 24 == $hours ) echo 'class="grey_text"';?> type='text' id='hours' name='hour_interval' value="<?php echo $hours;?>"></input><p class="description"> *Please enter the number of last hours to check for. </p>
						<p class="description"> For example, <br />5   = To alert you if you haven't received any order in last 5 hours<br />10 =  To alert you if you haven't received any order in last 10 hours<br />24 = To alert you if you haven't received any order in last 24 hours</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Enter Emails: </th>
						<td><textarea class="reciever_emails" name="reciever_emails" cols="39" id="reciever_emails"><?php echo $reciever_emails; ?></textarea><p class="description"> *Add your reciepent email addresses use comma separated value.</p><p class="description">An alert will be sent to Admin email (<?php echo $admin_user_info->user_email; ?>) by default.</p><p class="description">If you want to send the alert to other emails, please enter other emails recipients separated by comma in above text area. </p></td>
					</tr>
					<tr>
						<td style="padding:0px;" ><?php submit_button( 'Save Changes', 'primary' );?></td>
					</tr>
				</tbody> 
			</table>
		</form>
	</div>
<?php
} 

/**
 * form_submit_method function
 *
 * This method is use to update and insert all options values.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_no_order_alert_form_submit_method() {
	
	if ( isset ( $_POST[ 'hour_interval' ] ) || isset( $_POST[ 'reciever_emails' ] ) ) {
	    
		//Get hourly time interval from the form
	    $hour_interval = $_POST[ 'hour_interval' ];
	    if( $hour_interval == '' ) {
	    	update_option( 'hour_interval' , "24" ); 
	    } else { 
	    	update_option( 'hour_interval' , $hour_interval );
	    }
	    
	    //Get all inserted email address and update them to db.
	    $reciever_emails = $_POST[ 'reciever_emails' ];
	    update_option( 'reciever_emails' , $reciever_emails );
	}
}

//This is the plugin registration hook runs when user activate the plugin.
register_activation_hook( __FILE__, 'woocommerce_no_order_alert_plugin_activation' );

/**
 * plugin_activation function
 *
 * On activation, set a time, frequency and name of an action hook to be scheduled.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_no_order_alert_plugin_activation() {
    //The plugin activation method called when someone activate the plugin. this method is initialise the default options for store the configuration settings of the plugin
    woocommerce_no_order_alert_control_initialise();
}


/**
 * control_initialise function
 *
 * This method is used to initialise the controls which contains plugin setting values and store it into the database.
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_no_order_alert_control_initialise() {
	wp_enqueue_script( 'jquery' );
	add_option( 'hour_interval' , '24', '', 'yes' );
	add_option( 'reciever_emails' , '', '', 'yes' );
	add_option( 'final_time' , '', '', 'yes' );
    add_option( 'last_alert' , '', '', 'yes' );
}

register_deactivation_hook( __FILE__, 'woocommerce_no_order_alert_plugin_deactivation' );

/**
 * plugin_deactivation function
 *
 * The plugin activation method called when someone activate the plugin. this method is initialise the default options for store the configuration settings of the plugin
 *
 * @version		1.0.0
 * @author 		Multidots
 */
function woocommerce_no_order_alert_plugin_deactivation() { 
    delete_option( 'hour_interval' );
    delete_option( 'reciever_emails' );
    delete_option( 'final_time' );
    delete_option( 'last_alert' );
}
