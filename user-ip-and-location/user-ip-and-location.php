<?php

/**
 * Plugin Name: User IP and Location
 * Plugin URI: https://theguidex.com/
 * Version: 4.0.1
 * Author: TheGuideX
 * Author URI: https://theguidex.com/author/sunny/
 * Description: Allows you to insert user's IP address, Location, ISP, City in your WordPress blog post and page using shortcode.
 * License: GPL2
 * Requires PHP: 7.2
 * Tested up to: 6.8.1
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
define('USER_IP_AND_LOCATION_INCLUDES_PATH',           USER_IP_AND_LOCATION_PLUGIN_PATH . 'includes/');
define('USER_IP_AND_LOCATION_FLAGS',                   plugin_dir_url(__FILE__) . 'flags/');
define('USER_IP_AND_LOCATION_VERSION',                 '4.0.1');

# Load core classes and functions
require_once USER_IP_AND_LOCATION_INCLUDES_PATH . 'class-user-ip-location.php';
require_once USER_IP_AND_LOCATION_INCLUDES_PATH . 'class-user-browser.php';
require_once USER_IP_AND_LOCATION_INCLUDES_PATH . 'functions-developer.php';
require_once USER_IP_AND_LOCATION_INCLUDES_PATH . 'functions-shortcodes.php';

# Load admin class
if (is_admin()) {
    require_once USER_IP_AND_LOCATION_ADMIN_PATH . 'class-admin-settings.php';
    $admin_settings = User_IP_Location_Admin_Settings::get_instance();
    add_filter('plugin_action_links_' . USER_IP_AND_LOCATION_PLUGIN_BASENAME, [$admin_settings, 'add_settings_link']);
}

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
