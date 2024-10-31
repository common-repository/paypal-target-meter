<?php
/*
Plugin Name: Paypal Target Meter
Plugin URI: http://www.lokkju.com/
Description: A simple widget that displays the amount collected via paypal for a given time period, versus a target amount
Version: 1.2.4
Author: Lokkju Brennr
Author URI: http://www.lokkju.com/
License: GPL3
*/

require_once('paypal_target_meter_widget.php');
add_action('widgets_init', 'widget_PaypalTargetMeterWidget_init');
function pptm_menu()
{
    global $wpdb;
    include 'pptm-admin.php';
}
 
function pptm_admin_actions()
{
    add_management_page("Paypal Target Meter Tools", "Paypal Target Meter", 1, "pptm_tools_menu", "pptm_menu");
}
add_action('admin_menu', 'pptm_admin_actions');
?>
