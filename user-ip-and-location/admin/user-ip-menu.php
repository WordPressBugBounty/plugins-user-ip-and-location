<?php
/**
 * User IP and Location Admin File for Creating the Menu and Sub-Menus
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function user_ip_and_location_menu(){
    add_options_page( 'User IP and Location', 'User IP and Location', 'manage_options', 'user-ip-and-location', 'user_ip_and_location_settings_page' );
}
add_action( 'admin_menu', 'user_ip_and_location_menu' );