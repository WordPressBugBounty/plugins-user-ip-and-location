<?php
/**
 * Shortcode functions for the User IP and Location plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the necessary frontend scripts and localizes data for them.
 * The script is registered here but only enqueued when a shortcode is actually used.
 */
function user_ip_and_location_register_assets()
{
    wp_register_script(
        'user-ip-location-script',
        USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/js/user-ip-location.js',
        [],
        USER_IP_AND_LOCATION_VERSION,
        true
    );

    wp_localize_script('user-ip-location-script', 'userIpLocationData', [
        'apiUrl'   => esc_url_raw(get_rest_url(null, 'user-ip/v1/data')),
        'flagsUrl' => USER_IP_AND_LOCATION_FLAGS,
    ]);
}
add_action('wp_enqueue_scripts', 'user_ip_and_location_register_assets');

/**
 * Enqueues the main script if it hasn't been enqueued yet for the current request.
 * This prevents the script from being loaded multiple times on a page.
 */
function user_ip_and_location_enqueue_script()
{
    static $script_enqueued = false;
    if (!$script_enqueued) {
        wp_enqueue_script('user-ip-location-script');
        $script_enqueued = true;
    }
}

/**
 * This function generates a shortcode to display the user's IP address and location information.
 *
 * @param array $atts Shortcode attributes.
 * @return string The user's IP address and location information.
 */
function user_ip_and_location_shortcode($atts): string
{
    user_ip_and_location_enqueue_script();

    $atts = shortcode_atts(
        [
            "type"           => "ip",
            "height"         => "auto",
            "width"          => "50px",
            "vertical_align" => "middle", // New attribute for vertical alignment
        ],
        $atts,
        'userip_location'
    );

    $type = strtolower(sanitize_text_field($atts['type']));

    $valid_types = ['ip', 'continent', 'country', 'countrycode', 'region', 'regionname', 'city', 'zip', 'lat', 'lon', 'timezone', 'currency', 'isp', 'mobile', 'proxy', 'hosting', 'browser', 'os', 'flag'];
    if (!in_array($type, $valid_types)) {
        return ''; // Return empty for invalid type
    }

    $data_attributes = ' data-type="' . esc_attr($type) . '"';
    if ($type === 'flag') {
        $data_attributes .= ' data-height="' . esc_attr($atts['height']) . '"';
        $data_attributes .= ' data-width="' . esc_attr($atts['width']) . '"';
        $data_attributes .= ' data-vertical-align="' . esc_attr($atts['vertical_align']) . '"';
    }

    return '<span class="user-ip-placeholder"' . $data_attributes . '></span>';
}
add_shortcode("userip_location", "user_ip_and_location_shortcode");

/**
 * Shortcode to display the user's local time.
 *
 * @return string An HTML placeholder to be filled by JavaScript.
 */
function user_ip_and_location_localtime_shortcode(): string
{
    user_ip_and_location_enqueue_script();
    return '<span class="user-ip-placeholder" data-type="localtime"></span>';
}
add_shortcode("userip_localtime", "user_ip_and_location_localtime_shortcode");

/**
 * Shortcode to display the user's local date.
 *
 * @return string An HTML placeholder to be filled by JavaScript.
 */
function user_ip_and_location_localdate_shortcode(): string
{
    user_ip_and_location_enqueue_script();
    return '<span class="user-ip-placeholder" data-type="localdate"></span>';
}
add_shortcode("userip_localdate", "user_ip_and_location_localdate_shortcode");


/**
 * Shortcode to conditionally display content based on user location.
 * NOW CACHE-COMPATIBLE: Uses client-side processing instead of server-side.
 *
 * @param array $atts Shortcode attributes.
 * @param string|null $content The content enclosed by the shortcode.
 * @return string The content wrapper that JavaScript will process.
 */
function user_ip_conditional_shortcode($atts, string $content = null): string
{
    if (empty($content)) {
        return '';
    }

    user_ip_and_location_enqueue_script();

    // Generate unique ID for this conditional block
    $unique_id = 'userip-conditional-' . uniqid();
    
    // Sanitize and prepare attributes for JavaScript
    $conditions = [];
    $allowed_conditions = ['country', 'country_not', 'region', 'region_not', 'city', 'city_not'];
    
    foreach ($allowed_conditions as $condition) {
        if (isset($atts[$condition])) {
            $conditions[$condition] = sanitize_text_field($atts[$condition]);
        }
    }
    
    if (empty($conditions)) {
        // No conditions specified, show content
        return do_shortcode($content);
    }
    
    // Create a wrapper div with conditions as data attributes
    $wrapper_attrs = ' id="' . esc_attr($unique_id) . '"';
    $wrapper_attrs .= ' class="user-ip-conditional"';
    $wrapper_attrs .= ' data-conditions="' . esc_attr(json_encode($conditions)) . '"';
    $wrapper_attrs .= ' style="display: none;"'; // Hide initially until processed
    
    // Process the content and wrap it
    $processed_content = do_shortcode($content);
    
    return '<div' . $wrapper_attrs . '>' . $processed_content . '</div>';
}
add_shortcode('userip_conditional', 'user_ip_conditional_shortcode');

/**
 * DEPRECATED: Server-side conditional shortcode (kept for backward compatibility)
 * This function is deprecated and will be removed in future versions.
 * Use the client-side version above for cache compatibility.
 */
function user_ip_conditional_shortcode_deprecated($atts, string $content = null): string
{
    if (empty($content) || empty($atts)) {
        return '';
    }

    $location = get_user_ip_data();
    if (!$location) {
        return ''; // Don't show content if location lookup fails.
    }

    $conditions = [
        'country' => 'countryCode',
        'country_not' => 'countryCode',
        'region' => 'region',
        'region_not' => 'region',
        'city' => 'city',
        'city_not' => 'city',
    ];

    $display = true; // Assume we will display the content by default

    foreach ($conditions as $attr => $location_key) {
        if (!isset($atts[$attr])) {
            continue;
        }

        // Get comma-separated values from the attribute and trim them.
        $values = array_map('trim', explode(',', strtolower($atts[$attr])));
        $user_value = strtolower($location[$location_key] ?? '');

        $is_not_condition = str_contains($attr, '_not');
        $is_match = in_array($user_value, $values, true);

        // If a 'not' condition matches, or a normal condition does not match, then we hide.
        if (($is_not_condition && $is_match) || (!$is_not_condition && !$is_match)) {
            $display = false;
            break; // A single failed condition is enough to hide.
        }
    }

    return $display ? do_shortcode($content) : '';
}
// Note: This is intentionally not registered as a shortcode anymore 