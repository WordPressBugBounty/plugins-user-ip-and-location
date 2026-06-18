<?php
/**
 * Activation and deactivation routines.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Activation')) {

    class Activation
    {
        /**
         * Runs on plugin activation.
         *
         * @return void
         */
        public static function activate()
        {
            set_transient('user-ip-and-location-activate', true, 30);

            // Seed sensible defaults on first install only. Caching defaults ON
            // so a busy new site doesn't hammer (and get throttled by) the API.
            // Existing sites keep whatever they already saved.
            if (get_option('user_ip_location_options') === false) {
                add_option('user_ip_location_options', array(
                    'enable_cache'     => 1,
                    'cache_expiration' => 3600,
                    'api_key'          => '',
                    'api_lang'         => 'en',
                    'text_for_yes'     => 'Yes',
                    'text_for_no'      => 'No',
                    'time_format'      => 'g:i A',
                    'date_format'      => 'F j, Y',
                    'render_mode'      => 'ajax',
                ));
            }
        }

        /**
         * Runs on plugin deactivation. Clears transient runtime state.
         *
         * @return void
         */
        public static function deactivate()
        {
            delete_metadata('user', 0, 'user_ip_location_cache_notice_dismissed', '', true);
            delete_transient('user_ip_location_rate_limit');
        }
    }
}
