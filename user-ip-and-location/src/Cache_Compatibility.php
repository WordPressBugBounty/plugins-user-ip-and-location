<?php
/**
 * Integrates with popular caching plugins so the visitor-specific REST endpoints
 * are excluded from caching, and shows a one-time admin notice about it.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Cache_Compatibility')) {

    class Cache_Compatibility
    {
        /**
         * REST paths that must never be cached.
         *
         * @var array<int,string>
         */
        private $rest_paths = array(
            '/wp-json/user-ip/v1/data',
            '/wp-json/user-ip/v1/location',
        );

        /**
         * Hook into WordPress.
         *
         * @return void
         */
        public function register()
        {
            add_action('init', array($this, 'add_exclusions'));
            add_action('admin_enqueue_scripts', array($this, 'register_notice_script'));
            add_action('admin_notices', array($this, 'compatibility_notice'));
            add_action('wp_ajax_user_ip_location_dismiss_cache_notice', array($this, 'dismiss_notice'));
        }

        /**
         * Register the notice-dismissal script so it can be enqueued when the
         * notice is shown.
         *
         * @return void
         */
        public function register_notice_script()
        {
            wp_register_script(
                'user-ip-location-admin-notice',
                USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/js/admin-notice.js',
                array(),
                USER_IP_AND_LOCATION_VERSION,
                true
            );
        }

        /**
         * Register cache-exclusion filters for detected caching plugins.
         *
         * @return void
         */
        public function add_exclusions()
        {
            $paths = $this->rest_paths;

            if (defined('LSCWP_V')) {
                add_filter('litespeed_cache_rest_api_exclude', function ($excludes) {
                    $excludes[] = 'user-ip/v1/data';
                    $excludes[] = 'user-ip/v1/location';
                    return $excludes;
                });
            }

            if (defined('W3TC')) {
                add_filter('w3tc_cache_page_exclude', function ($excludes) use ($paths) {
                    return array_merge($excludes, $paths);
                });
            }

            if (defined('WP_ROCKET_VERSION')) {
                add_filter('rocket_cache_reject_uri', function ($excludes) use ($paths) {
                    return array_merge($excludes, $paths);
                });
            }

            if (defined('WPCACHEHOME')) {
                add_filter('wp_super_cache_exclude_uri', function ($excludes) use ($paths) {
                    return array_merge($excludes, $paths);
                });
            }

            if (defined('FLYING_PRESS_VERSION') || class_exists('FlyingPress\\Config')) {
                add_filter('flying_press_cache_exclude_urls', function ($excludes) use ($paths) {
                    return array_merge($excludes, $paths);
                });
            }
        }

        /**
         * Detect active caching plugins.
         *
         * @return array<int,string>
         */
        private function detect_cache_plugins()
        {
            $plugins = array();
            if (defined('LSCWP_V')) {
                $plugins[] = 'LiteSpeed Cache';
            }
            if (defined('W3TC')) {
                $plugins[] = 'W3 Total Cache';
            }
            if (defined('WP_ROCKET_VERSION')) {
                $plugins[] = 'WP Rocket';
            }
            if (defined('WPCACHEHOME')) {
                $plugins[] = 'WP Super Cache';
            }
            if (defined('FLYING_PRESS_VERSION') || class_exists('FlyingPress\\Config')) {
                $plugins[] = 'FlyingPress';
            }
            return $plugins;
        }

        /**
         * Show a dismissible notice when a supported caching plugin is active.
         *
         * @return void
         */
        public function compatibility_notice()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (get_user_meta(get_current_user_id(), 'user_ip_location_cache_notice_dismissed', true)) {
                return;
            }

            $cache_plugins = $this->detect_cache_plugins();
            if (empty($cache_plugins)) {
                return;
            }

            $cache_list = implode(', ', $cache_plugins);

            echo '<div class="notice notice-info is-dismissible" id="user-ip-location-cache-notice">';
            echo '<p><strong>' . esc_html__('User IP and Location:', 'user-ip-and-location') . '</strong> ';
            printf(
                /* translators: %s: comma-separated list of caching plugin names. */
                esc_html__('Cache compatibility enabled for: %s. REST API endpoints are automatically excluded from caching.', 'user-ip-and-location'),
                esc_html($cache_list)
            );
            echo '</p></div>';

            // Enqueue the (already registered) dismissal script with its nonce.
            wp_enqueue_script('user-ip-location-admin-notice');
            wp_localize_script('user-ip-location-admin-notice', 'userIpLocationNotice', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('user_ip_location_dismiss_notice'),
            ));
        }

        /**
         * AJAX handler to persist the notice dismissal for the current user.
         *
         * @return void
         */
        public function dismiss_notice()
        {
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'user_ip_location_dismiss_notice')) {
                wp_die('', '', array('response' => 403));
            }

            if (!current_user_can('manage_options')) {
                wp_die('', '', array('response' => 403));
            }

            update_user_meta(get_current_user_id(), 'user_ip_location_cache_notice_dismissed', true);
            wp_die();
        }
    }
}
