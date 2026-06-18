<?php
/**
 * Global helper functions exposed for theme and plugin developers.
 * These names are part of the public API and must remain stable.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('get_user_ip_data')) {
    /**
     * Returns the full location data array for the current visitor.
     *
     * Uses the same singleton instance and caching as the shortcodes, so calling
     * this never triggers a duplicate API request within a single page load.
     *
     * @return array|null Associative array of location data, or null on failure.
     */
    function get_user_ip_data()
    {
        return UserIPLocation\Geolocation::get_instance()->get_all_data();
    }
}
