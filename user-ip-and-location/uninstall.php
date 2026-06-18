<?php
/**
 * Removes all plugin data when the plugin is deleted from WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit; // Exit if not called by WordPress during uninstall.
}

delete_option('user_ip_location_options');
delete_option('user_ip_location_cache_version');
delete_transient('user_ip_location_rate_limit');
delete_metadata('user', 0, 'user_ip_location_cache_notice_dismissed', '', true);

// Remove any cached location transients (covers sites without an object cache).
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_user_ip_location_') . '%',
        $wpdb->esc_like('_transient_timeout_user_ip_location_') . '%'
    )
);
