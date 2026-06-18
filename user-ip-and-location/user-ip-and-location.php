<?php

/**
 * Plugin Name: User IP and Location
 * Plugin URI: https://theguidex.com/
 * Version: 5.0.0
 * Author: TheGuideX
 * Author URI: https://theguidex.com/author/sunny/
 * Description: Allows you to insert user's IP address, Location, ISP, City in your WordPress blog post and page using shortcode.
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.2
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Text Domain: user-ip-and-location
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Version and path constants. Names are kept identical to previous releases so
// any third-party code referencing them keeps resolving.
define('USER_IP_AND_LOCATION_VERSION',        '5.0.0');
define('USER_IP_AND_LOCATION_PLUGIN_FILE',    __FILE__);
define('USER_IP_AND_LOCATION_PLUGIN_URL',     plugin_dir_url(__FILE__));
define('USER_IP_AND_LOCATION_PLUGIN_PATH',    plugin_dir_path(__FILE__));
define('USER_IP_AND_LOCATION_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('USER_IP_AND_LOCATION_FLAGS',          plugin_dir_url(__FILE__) . 'flags/');
define('USER_IP_AND_LOCATION_INCLUDES_PATH',  USER_IP_AND_LOCATION_PLUGIN_PATH . 'src/');
define('USER_IP_AND_LOCATION_ADMIN_PATH',     USER_IP_AND_LOCATION_PLUGIN_PATH . 'src/Admin/');

// Register the namespaced autoloader (also handles legacy class-name aliases).
require_once USER_IP_AND_LOCATION_PLUGIN_PATH . 'src/Autoloader.php';
UserIPLocation\Autoloader::register();

// Public global helper functions (backward compatible).
require_once USER_IP_AND_LOCATION_PLUGIN_PATH . 'src/functions.php';

// Activation / deactivation hooks.
register_activation_hook(__FILE__, array('UserIPLocation\\Activation', 'activate'));
register_deactivation_hook(__FILE__, array('UserIPLocation\\Activation', 'deactivate'));

// Boot the plugin once all plugins are loaded.
add_action('plugins_loaded', 'user_ip_and_location_boot');

/**
 * Boots the plugin orchestrator.
 *
 * @return void
 */
function user_ip_and_location_boot()
{
    UserIPLocation\Plugin::instance()->boot();
}

// Load translations.
add_action('init', 'user_ip_and_location_load_textdomain');

/**
 * Loads the plugin text domain for translations.
 *
 * @return void
 */
function user_ip_and_location_load_textdomain()
{
    load_plugin_textdomain(
        'user-ip-and-location',
        false,
        dirname(USER_IP_AND_LOCATION_PLUGIN_BASENAME) . '/languages'
    );
}
