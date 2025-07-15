<?php
/**
 * Developer-focused functions for the User IP and Location plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Global function for developers to get all location data for the current user.
 *
 * This function provides a simple way to access the raw location data array.
 * It leverages the same singleton instance and caching as the shortcodes.
 *
 * @return array|null An associative array of the user's location data, or null on failure.
 */
function get_user_ip_data(): ?array
{
    return User_IP_and_Location::get_instance()->get_all_data();
}

/**
 * Register the custom REST API endpoint.
 */
function user_ip_location_register_rest_route()
{
    // Secure endpoint for developers
    register_rest_route('user-ip/v1', '/location', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'user_ip_location_rest_callback',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);

    // Public endpoint for AJAX loading - CACHE-BUSTING ENABLED
    register_rest_route('user-ip/v1', '/data', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'user_ip_location_public_rest_callback',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'user_ip_location_register_rest_route');

/**
 * Add cache-busting headers to prevent caching of user-specific data
 */
function user_ip_location_add_cache_headers()
{
    // Only add headers for our REST API endpoints
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/user-ip/v1/') !== false) {
        // Prevent caching by all caching plugins and browsers
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        header('X-Robots-Tag: noindex, nofollow');
        
        // Add specific headers for popular caching plugins
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Nginx-Cache: BYPASS');
        header('X-Proxy-Cache: BYPASS');
        header('X-Cache: BYPASS');
        
        // FlyingPress specific headers
        header('X-FlyingPress-Cache: no-cache');
        header('Vary: *');
    }
}
add_action('init', 'user_ip_location_add_cache_headers', 1);

/**
 * The callback function for the public REST API endpoint.
 * This enriches the data with extra values needed by the frontend.
 *
 * @return WP_REST_Response
 */
function user_ip_location_public_rest_callback(): WP_REST_Response
{
    // Add cache-busting headers
    user_ip_location_add_cache_headers();
    
    $ip_instance = User_IP_and_Location::get_instance();
    $data = $ip_instance->get_all_data();

    if ($data) {
        // Normalize keys to match shortcode "type" attributes for the frontend script.
        $data['ip'] = $data['query'] ?? '';
        $data['countrycode'] = $data['countryCode'] ?? '';
        $data['regionname'] = $data['regionName'] ?? '';

        // Add extra data needed for various shortcodes
        $browser_instance = User_Browser::get_instance();
        $data['browser'] = $browser_instance->get_browser_name();
        $data['os'] = $browser_instance->get_operating_system();
        
        $options = get_option('user_ip_location_options', [
            'text_for_yes' => 'Yes', 
            'text_for_no' => 'No',
            'time_format' => 'g:i A',
            'date_format' => 'F j, Y'
        ]);
        
        $time_format = $options['time_format'] ?? 'g:i A';
        $date_format = $options['date_format'] ?? 'F j, Y';
        
        $data['localtime'] = $ip_instance->getLocalTime($time_format);
        $data['localdate'] = $ip_instance->getLocalDate($date_format);

        $text_yes = $options['text_for_yes'] ?? 'Yes';
        $text_no = $options['text_for_no'] ?? 'No';

        $data['mobile_text'] = $data['mobile'] ? $text_yes : $text_no;
        $data['proxy_text'] = $data['proxy'] ? $text_yes : $text_no;
        $data['hosting_text'] = $data['hosting'] ? $text_yes : $text_no;

        $response = new WP_REST_Response($data, 200);
        
        // Add cache-busting headers to response
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        $response->header('Vary', '*');
        
        return $response;
    }

    $error_response = new WP_REST_Response(['error' => 'Could not retrieve location data.'], 500);
    $error_response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
    return $error_response;
}

/**
 * The callback function for the REST API endpoint.
 *
 * @return WP_REST_Response
 */
function user_ip_location_rest_callback(): WP_REST_Response
{
    // Add cache-busting headers
    user_ip_location_add_cache_headers();
    
    $data = get_user_ip_data();

    if ($data) {
        $response = new WP_REST_Response($data, 200);
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        return $response;
    }

    $error_response = new WP_REST_Response(['error' => 'Could not retrieve location data.'], 500);
    $error_response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
    return $error_response;
}

/**
 * Add cache exclusion rules for popular caching plugins
 */
function user_ip_location_add_cache_exclusions()
{
    // LiteSpeed Cache
    if (defined('LSCWP_V')) {
        add_filter('litespeed_cache_rest_api_exclude', function($excludes) {
            $excludes[] = 'user-ip/v1/data';
            $excludes[] = 'user-ip/v1/location';
            return $excludes;
        });
    }
    
    // W3 Total Cache
    if (defined('W3TC')) {
        add_filter('w3tc_cache_page_exclude', function($excludes) {
            $excludes[] = '/wp-json/user-ip/v1/data';
            $excludes[] = '/wp-json/user-ip/v1/location';
            return $excludes;
        });
    }
    
    // WP Rocket
    if (defined('WP_ROCKET_VERSION')) {
        add_filter('rocket_cache_reject_uri', function($excludes) {
            $excludes[] = '/wp-json/user-ip/v1/data';
            $excludes[] = '/wp-json/user-ip/v1/location';
            return $excludes;
        });
    }
    
    // WP Super Cache
    if (defined('WPCACHEHOME')) {
        add_filter('wp_super_cache_exclude_uri', function($excludes) {
            $excludes[] = '/wp-json/user-ip/v1/data';
            $excludes[] = '/wp-json/user-ip/v1/location';
            return $excludes;
        });
    }
    
    // FlyingPress
    if (defined('FLYING_PRESS_VERSION') || class_exists('FlyingPress\Config')) {
        add_filter('flying_press_cache_exclude_urls', function($excludes) {
            $excludes[] = '/wp-json/user-ip/v1/data';
            $excludes[] = '/wp-json/user-ip/v1/location';
            return $excludes;
        });
    }
}
add_action('init', 'user_ip_location_add_cache_exclusions');

/**
 * Add admin notice for cache compatibility (dismissible)
 */
function user_ip_location_cache_compatibility_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if notice has been dismissed by current user
    $dismissed = get_user_meta(get_current_user_id(), 'user_ip_location_cache_notice_dismissed', true);
    if ($dismissed) {
        return;
    }
    
    $cache_plugins = [];
    
    if (defined('LSCWP_V')) $cache_plugins[] = 'LiteSpeed Cache';
    if (defined('W3TC')) $cache_plugins[] = 'W3 Total Cache';
    if (defined('WP_ROCKET_VERSION')) $cache_plugins[] = 'WP Rocket';
    if (defined('WPCACHEHOME')) $cache_plugins[] = 'WP Super Cache';
    if (defined('FLYING_PRESS_VERSION') || class_exists('FlyingPress\Config')) $cache_plugins[] = 'FlyingPress';
    
    if (!empty($cache_plugins)) {
        $cache_list = implode(', ', $cache_plugins);
        echo '<div class="notice notice-info is-dismissible" id="user-ip-location-cache-notice">';
        echo '<p><strong>User IP and Location:</strong> Cache compatibility enabled for: ' . esc_html($cache_list) . '. ';
        echo 'REST API endpoints are automatically excluded from caching.</p>';
        echo '</div>';
        
        // Add JavaScript to handle dismissal
        echo '<script>
        jQuery(document).ready(function($) {
            $(document).on("click", "#user-ip-location-cache-notice .notice-dismiss", function() {
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "user_ip_location_dismiss_cache_notice",
                        nonce: "' . wp_create_nonce('user_ip_location_dismiss_notice') . '"
                    },
                    success: function(response) {
                        // Notice dismissed successfully
                        console.log("User IP Location: Cache notice dismissed");
                    },
                    error: function(xhr, status, error) {
                        // Silently fail - notice will still be hidden by WordPress
                        console.warn("User IP Location: Failed to save notice dismissal");
                    }
                });
            });
        });
        </script>';
    }
}
add_action('admin_notices', 'user_ip_location_cache_compatibility_notice');

/**
 * Handle AJAX request to dismiss cache compatibility notice
 */
function user_ip_location_dismiss_cache_notice()
{
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'user_ip_location_dismiss_notice')) {
        wp_die('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Mark notice as dismissed for current user
    update_user_meta(get_current_user_id(), 'user_ip_location_cache_notice_dismissed', true);
    
    wp_die(); // Proper way to end AJAX request
}
add_action('wp_ajax_user_ip_location_dismiss_cache_notice', 'user_ip_location_dismiss_cache_notice');

/**
 * Reset dismissed cache notice for all users (utility function for debugging)
 * Call this function if you want to show the notice again to all users
 */
function user_ip_location_reset_cache_notices()
{
    delete_metadata('user', 0, 'user_ip_location_cache_notice_dismissed', '', true);
} 