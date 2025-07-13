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

    // Public endpoint for AJAX loading
    register_rest_route('user-ip/v1', '/data', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'user_ip_location_public_rest_callback',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'user_ip_location_register_rest_route');

/**
 * The callback function for the public REST API endpoint.
 * This enriches the data with extra values needed by the frontend.
 *
 * @return WP_REST_Response
 */
function user_ip_location_public_rest_callback(): WP_REST_Response
{
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
        $data['localtime'] = $ip_instance->getLocalTime();

        $options = get_option('user_ip_location_options', ['text_for_yes' => 'Yes', 'text_for_no' => 'No']);
        $text_yes = $options['text_for_yes'] ?? 'Yes';
        $text_no = $options['text_for_no'] ?? 'No';

        $data['mobile_text'] = $data['mobile'] ? $text_yes : $text_no;
        $data['proxy_text'] = $data['proxy'] ? $text_yes : $text_no;
        $data['hosting_text'] = $data['hosting'] ? $text_yes : $text_no;

        return new WP_REST_Response($data, 200);
    }

    return new WP_REST_Response(['error' => 'Could not retrieve location data.'], 500);
}

/**
 * The callback function for the REST API endpoint.
 *
 * @return WP_REST_Response
 */
function user_ip_location_rest_callback(): WP_REST_Response
{
    $data = get_user_ip_data();

    if ($data) {
        return new WP_REST_Response($data, 200);
    }

    return new WP_REST_Response(['error' => 'Could not retrieve location data.'], 500);
} 