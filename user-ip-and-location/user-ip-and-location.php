<?php

/**
 * Plugin Name: User IP and Location
 * Plugin URI: https://theguidex.com/
 * Version: 3.2
 * Author: TheGuideX
 * Author URI: https://theguidex.com/author/sunny/
 * Description: Allows you to insert user's IP address, Location, ISP, City in your WordPress blog post and page using shortcode.
 * License: GPL2
 * Requires PHP: 7.0
 * Tested up to: 6.5.2
 * Text Domain: user-ip-and-location
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

#Define Constants for the plugin
define('USER_IP_AND_LOCATION_PLUGIN_URL',              plugin_dir_url(__FILE__));
define('USER_IP_AND_LOCATION_PLUGIN_PATH',             plugin_dir_path(__FILE__));
define('USER_IP_AND_LOCATION_PLUGIN_BASENAME',         plugin_basename(__FILE__));
define('USER_IP_AND_LOCATION_ADMIN_PATH',              USER_IP_AND_LOCATION_PLUGIN_PATH . 'admin/');
define('USER_IP_AND_LOCATION_INC_PATH',                USER_IP_AND_LOCATION_PLUGIN_PATH . 'inc/');
define('USER_IP_AND_LOCATION_FLAGS',                   plugin_dir_url(__FILE__) . 'flags/');
define('USER_IP_AND_LOCATION_CSS_PATH',                USER_IP_AND_LOCATION_PLUGIN_PATH . 'assets/css/');
define('USER_IP_AND_LOCATION_VERSION',                 '3.1');


#Load the plugin
function user_ip_and_location_load()
{
    if (current_user_can('activate_plugins')) {
        require_once USER_IP_AND_LOCATION_ADMIN_PATH . 'user-ip-admin.php';
    }
}
add_action('plugins_loaded', 'user_ip_and_location_load');

#Load functions and classes
require USER_IP_AND_LOCATION_INC_PATH . 'user-ip-functions.php';

#Registering activation hook
register_activation_hook(__FILE__, 'user_ip_and_location_activation');

function user_ip_and_location_activation()
{
    set_transient('user-ip-and-location-activate', true, 5);
}

add_action('admin_notices', 'user_ip_and_location_activation_notice');

function user_ip_and_location_activation_notice()
{
    if (get_transient('user-ip-and-location-activate')) { ?>
        <div class="updated notice is-dismissible">
            <p><?php _e('User IP and Location is activated. Please go to the <a href="admin.php?page=user-ip-and-location">User IP and Location</a> page to configure the plugin.', 'user-ip-and-location'); ?></p>
        </div>
<?php delete_transient('user-ip-and-location-activate');
    }
}
