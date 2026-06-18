<?php
/**
 * Central orchestrator. Wires every component into WordPress.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Plugin')) {

    class Plugin
    {
        /**
         * Single instance.
         *
         * @var Plugin|null
         */
        private static $instance = null;

        /**
         * Guards against booting twice.
         *
         * @var bool
         */
        private $booted = false;

        /**
         * Get the singleton instance.
         *
         * @return Plugin
         */
        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
        }

        /**
         * Register all hooks and components.
         *
         * @return void
         */
        public function boot()
        {
            if ($this->booted) {
                return;
            }
            $this->booted = true;

            $shortcodes = new Shortcodes();
            $shortcodes->register();

            $blocks = new Blocks($shortcodes);
            $blocks->register();

            $rest = new Rest_Controller();
            $rest->register();

            $cache = new Cache_Compatibility();
            $cache->register();

            if (is_admin()) {
                $settings = Admin\Settings::get_instance();
                $settings->register();
                add_filter(
                    'plugin_action_links_' . USER_IP_AND_LOCATION_PLUGIN_BASENAME,
                    array($settings, 'add_settings_link')
                );
            }

            add_action('admin_notices', array($this, 'activation_notice'));
        }

        /**
         * One-time admin notice shown right after activation.
         *
         * @return void
         */
        public function activation_notice()
        {
            if (!get_transient('user-ip-and-location-activate')) {
                return;
            }

            // Settings live under the Settings menu (options-general.php), so the
            // link must point there rather than admin.php.
            $url = admin_url('options-general.php?page=user-ip-and-location');

            echo '<div class="updated notice is-dismissible"><p>';
            printf(
                wp_kses(
                    /* translators: %s: settings page URL. */
                    __('User IP and Location is activated. Please go to the <a href="%s">settings page</a> to configure the plugin.', 'user-ip-and-location'),
                    array('a' => array('href' => array()))
                ),
                esc_url($url)
            );
            echo '</p></div>';

            delete_transient('user-ip-and-location-activate');
        }
    }
}
