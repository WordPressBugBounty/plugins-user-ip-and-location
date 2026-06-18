<?php
/**
 * Admin settings screen: registers the option, renders the tabbed settings page,
 * and handles the manual cache reset.
 */

namespace UserIPLocation\Admin;

use UserIPLocation\Browser;
use UserIPLocation\Fields;
use UserIPLocation\Geolocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Admin\\Settings')) {

    class Settings
    {
        /**
         * Single instance.
         *
         * @var Settings|null
         */
        private static $instance = null;

        /**
         * The admin page hook suffix, used to scope asset loading.
         *
         * @var string|null
         */
        private $page_hook = null;

        /**
         * Get the singleton instance.
         *
         * @return Settings
         */
        public static function get_instance()
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
         * Hook into WordPress.
         *
         * @return void
         */
        public function register()
        {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        }

        /**
         * Load the admin assets only on this plugin's settings page.
         *
         * @param string $hook Current admin page hook suffix.
         * @return void
         */
        public function enqueue_assets($hook)
        {
            if ($hook !== $this->page_hook) {
                return;
            }
            wp_enqueue_style(
                'user-ip-location-admin',
                USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                USER_IP_AND_LOCATION_VERSION
            );
            wp_enqueue_script(
                'user-ip-location-admin-settings',
                USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/js/admin-settings.js',
                array(),
                USER_IP_AND_LOCATION_VERSION,
                true
            );
        }

        /**
         * Add a "Settings" link on the Plugins screen.
         *
         * @param array $links
         * @return array
         */
        public function add_settings_link($links)
        {
            $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=user-ip-and-location')) . '">'
                . esc_html__('Settings', 'user-ip-and-location') . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

        /**
         * Register the settings page under the Settings menu.
         *
         * @return void
         */
        public function add_admin_menu()
        {
            $this->page_hook = add_options_page(
                __('User IP and Location', 'user-ip-and-location'),
                __('User IP and Location', 'user-ip-and-location'),
                'manage_options',
                'user-ip-and-location',
                array($this, 'render_settings_page')
            );
        }

        /**
         * Register the option and its sanitizer. The fields are rendered by the
         * card layout below, so no add_settings_field() registrations are needed.
         *
         * @return void
         */
        public function register_settings()
        {
            register_setting('user_ip_location_settings', 'user_ip_location_options', array($this, 'sanitize_options'));
        }

        /**
         * Sanitize and validate the options array before it is stored.
         *
         * @param array $input
         * @return array
         */
        public function sanitize_options($input)
        {
            $new_input = array();

            $new_input['enable_cache']     = isset($input['enable_cache']) ? 1 : 0;
            $new_input['cache_expiration'] = isset($input['cache_expiration']) ? absint($input['cache_expiration']) : 3600;
            $new_input['api_key']          = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';

            $languages = array('en', 'de', 'es', 'pt-BR', 'fr', 'ja', 'zh-CN', 'ru');
            $new_input['api_lang'] = (isset($input['api_lang']) && in_array($input['api_lang'], $languages, true)) ? $input['api_lang'] : 'en';

            $new_input['text_for_yes'] = isset($input['text_for_yes']) ? sanitize_text_field($input['text_for_yes']) : 'Yes';
            $new_input['text_for_no']  = isset($input['text_for_no']) ? sanitize_text_field($input['text_for_no']) : 'No';

            $time_formats = array('g:i a', 'g:i A', 'H:i', 'h:i a', 'h:i A', 'g:i:s a', 'g:i:s A', 'H:i:s');
            $new_input['time_format'] = (isset($input['time_format']) && in_array($input['time_format'], $time_formats, true)) ? $input['time_format'] : 'g:i A';

            $date_formats = array('F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'M j, Y', 'j F Y', 'l, F j, Y', 'D, M j, Y');
            $new_input['date_format'] = (isset($input['date_format']) && in_array($input['date_format'], $date_formats, true)) ? $input['date_format'] : 'F j, Y';

            $new_input['render_mode'] = (isset($input['render_mode']) && in_array($input['render_mode'], array('ajax', 'server'), true)) ? $input['render_mode'] : 'ajax';

            return $new_input;
        }

        // --- Field callbacks -----------------------------------------------

        public function enable_cache_callback()
        {
            $options = get_option('user_ip_location_options', array('enable_cache' => 0));
            $checked = !empty($options['enable_cache']) ? 'checked' : '';
            echo '<label><input type="checkbox" name="user_ip_location_options[enable_cache]" value="1" ' . esc_attr($checked) . ' /> '
                . esc_html__('Cache API results in the database.', 'user-ip-and-location') . '</label>';
        }

        public function cache_expiration_callback()
        {
            $options = get_option('user_ip_location_options', array('cache_expiration' => 3600));
            $expiration = isset($options['cache_expiration']) ? $options['cache_expiration'] : 3600;
            $expirations = array(
                '3600'   => __('1 Hour', 'user-ip-and-location'),
                '21600'  => __('6 Hours', 'user-ip-and-location'),
                '86400'  => __('1 Day', 'user-ip-and-location'),
                '604800' => __('1 Week', 'user-ip-and-location'),
            );

            echo '<select name="user_ip_location_options[cache_expiration]">';
            foreach ($expirations as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($expiration, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }

        public function api_key_callback()
        {
            $options = get_option('user_ip_location_options', array('api_key' => ''));
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            echo '<input type="text" name="user_ip_location_options[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="' . esc_attr__('Optional API key', 'user-ip-and-location') . '" />';
            echo '<p class="description">' . wp_kses(
                __('For users of the <a href="https://ip-api.com/pro" target="_blank" rel="noopener">Pro service</a>.', 'user-ip-and-location'),
                array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))
            ) . '</p>';
        }

        public function api_lang_callback()
        {
            $options = get_option('user_ip_location_options', array('api_lang' => 'en'));
            $current_lang = isset($options['api_lang']) ? $options['api_lang'] : 'en';
            // Language endonyms are intentionally not translated.
            $languages = array(
                'en'    => 'English',
                'de'    => 'Deutsch (German)',
                'es'    => 'Español (Spanish)',
                'pt-BR' => 'Português - Brasil (Portuguese)',
                'fr'    => 'Français (French)',
                'ja'    => '日本語 (Japanese)',
                'zh-CN' => '中国 (Chinese)',
                'ru'    => 'Русский (Russian)',
            );

            echo '<select name="user_ip_location_options[api_lang]">';
            foreach ($languages as $code => $name) {
                echo '<option value="' . esc_attr($code) . '"' . selected($current_lang, $code, false) . '>' . esc_html($name) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Select the language for country, region, and city names.', 'user-ip-and-location') . '</p>';
        }

        public function render_mode_callback()
        {
            $options = get_option('user_ip_location_options', array('render_mode' => 'ajax'));
            $current = isset($options['render_mode']) ? $options['render_mode'] : 'ajax';
            $modes = array(
                'ajax'   => __('AJAX — loads after the page (works with page caching)', 'user-ip-and-location'),
                'server' => __('Server-side — printed directly (best for forms, RSS & non-cached sites)', 'user-ip-and-location'),
            );
            echo '<select name="user_ip_location_options[render_mode]">';
            foreach ($modes as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . wp_kses(
                __('AJAX is recommended for cached sites. Pick Server-side if shortcodes go inside form fields, RSS, or AMP. You can also override per shortcode with <code>ajax="false"</code>.', 'user-ip-and-location'),
                array('code' => array())
            ) . '</p>';
        }

        public function text_for_yes_callback()
        {
            $options = get_option('user_ip_location_options', array('text_for_yes' => 'Yes'));
            $text = isset($options['text_for_yes']) ? $options['text_for_yes'] : 'Yes';
            echo '<input type="text" name="user_ip_location_options[text_for_yes]" value="' . esc_attr($text) . '" class="regular-text" />';
        }

        public function text_for_no_callback()
        {
            $options = get_option('user_ip_location_options', array('text_for_no' => 'No'));
            $text = isset($options['text_for_no']) ? $options['text_for_no'] : 'No';
            echo '<input type="text" name="user_ip_location_options[text_for_no]" value="' . esc_attr($text) . '" class="regular-text" />';
        }

        public function time_format_callback()
        {
            $options = get_option('user_ip_location_options', array('time_format' => 'g:i A'));
            $current_format = isset($options['time_format']) ? $options['time_format'] : 'g:i A';

            $time_formats = array(
                'g:i a'   => __('12-hour with lowercase am/pm (e.g., 3:30 pm)', 'user-ip-and-location'),
                'g:i A'   => __('12-hour with uppercase AM/PM (e.g., 3:30 PM)', 'user-ip-and-location'),
                'H:i'     => __('24-hour format (e.g., 15:30)', 'user-ip-and-location'),
                'h:i a'   => __('12-hour with leading zeros and lowercase am/pm (e.g., 03:30 pm)', 'user-ip-and-location'),
                'h:i A'   => __('12-hour with leading zeros and uppercase AM/PM (e.g., 03:30 PM)', 'user-ip-and-location'),
                'g:i:s a' => __('12-hour with seconds and lowercase am/pm (e.g., 3:30:45 pm)', 'user-ip-and-location'),
                'g:i:s A' => __('12-hour with seconds and uppercase AM/PM (e.g., 3:30:45 PM)', 'user-ip-and-location'),
                'H:i:s'   => __('24-hour with seconds (e.g., 15:30:45)', 'user-ip-and-location'),
            );

            echo '<select name="user_ip_location_options[time_format]" id="time_format_select">';
            foreach ($time_formats as $format => $description) {
                echo '<option value="' . esc_attr($format) . '"' . selected($current_format, $format, false) . '>' . esc_html($description) . '</option>';
            }
            echo '</select>';

            echo '<p class="description">' . esc_html__('Preview:', 'user-ip-and-location') . ' <strong><span id="time_preview">' . esc_html(wp_date($current_format)) . '</span></strong></p>';
        }

        public function date_format_callback()
        {
            $options = get_option('user_ip_location_options', array('date_format' => 'F j, Y'));
            $current_format = isset($options['date_format']) ? $options['date_format'] : 'F j, Y';

            $date_formats = array(
                'F j, Y'    => __('Full month name with day and year (e.g., January 15, 2025)', 'user-ip-and-location'),
                'Y-m-d'     => __('Year-month-day format (e.g., 2025-01-15)', 'user-ip-and-location'),
                'm/d/Y'     => __('US format: month/day/year (e.g., 01/15/2025)', 'user-ip-and-location'),
                'd/m/Y'     => __('European format: day/month/year (e.g., 15/01/2025)', 'user-ip-and-location'),
                'M j, Y'    => __('Short month name with day and year (e.g., Jan 15, 2025)', 'user-ip-and-location'),
                'j F Y'     => __('Day, full month name, year (e.g., 15 January 2025)', 'user-ip-and-location'),
                'l, F j, Y' => __('Full day name, month, day, year (e.g., Wednesday, January 15, 2025)', 'user-ip-and-location'),
                'D, M j, Y' => __('Short day name, month, day, year (e.g., Wed, Jan 15, 2025)', 'user-ip-and-location'),
            );

            echo '<select name="user_ip_location_options[date_format]" id="date_format_select">';
            foreach ($date_formats as $format => $description) {
                echo '<option value="' . esc_attr($format) . '"' . selected($current_format, $format, false) . '>' . esc_html($description) . '</option>';
            }
            echo '</select>';

            echo '<p class="description">' . esc_html__('Preview:', 'user-ip-and-location') . ' <strong><span id="date_preview">' . esc_html(wp_date($current_format)) . '</span></strong></p>';
        }

        /**
         * Renders the main settings page with tabbed navigation.
         *
         * @return void
         */
        public function render_settings_page()
        {
            $this->maybe_handle_clear_cache();

            $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';
            $tabs = array(
                'settings'   => __('Settings', 'user-ip-and-location'),
                'shortcodes' => __('Shortcodes', 'user-ip-and-location'),
                'your_info'  => __('Your Info', 'user-ip-and-location'),
                'developer'  => __('Developers', 'user-ip-and-location'),
            );
            if (!isset($tabs[$active_tab])) {
                $active_tab = 'settings';
            }
            ?>
            <div class="wrap uipl-admin">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <div class="uipl-bar">
                    <span class="uipl-logo__mark"><?php echo $this->target_svg(16); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="uipl-logo">
                        <span class="uipl-logo__name">IP<span class="uipl-logo__dot">&middot;</span>Location</span>
                        <span class="uipl-logo__sub"><?php esc_html_e('Visitor geolocation', 'user-ip-and-location'); ?></span>
                    </span>
                    <div class="uipl-bar__meta">
                        <span class="uipl-chip">v<?php echo esc_html(USER_IP_AND_LOCATION_VERSION); ?></span>
                        <span class="uipl-chip uipl-chip--accent">ip-api</span>
                    </div>
                </div>

                <nav class="uipl-tabs">
                    <?php foreach ($tabs as $slug => $label) : ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=user-ip-and-location&tab=' . $slug)); ?>"
                           class="<?php echo $active_tab === $slug ? 'is-active' : ''; ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php settings_errors('user_ip_location_notices'); ?>

                <?php
                switch ($active_tab) {
                    case 'your_info':
                        $this->render_your_info_tab();
                        break;
                    case 'developer':
                        $this->render_developer_tab();
                        break;
                    case 'shortcodes':
                        $this->render_shortcodes_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
            <?php
        }

        /**
         * Reset the cache by bumping the cache version. Works with object caches
         * because it touches a single option instead of deleting transient rows.
         *
         * @return void
         */
        private function maybe_handle_clear_cache()
        {
            if (!isset($_GET['action'], $_GET['_wpnonce']) || $_GET['action'] !== 'clear_cache') {
                return;
            }

            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (!wp_verify_nonce($nonce, 'user_ip_clear_cache')) {
                add_settings_error('user_ip_location_notices', 'cache_nonce', __('Security check failed. Please try clearing the cache again.', 'user-ip-and-location'), 'error');
                return;
            }

            $version = (int) get_option('user_ip_location_cache_version', 1);
            update_option('user_ip_location_cache_version', $version + 1);
            delete_transient('user_ip_location_rate_limit');

            add_settings_error('user_ip_location_notices', 'cache_cleared', __('API cache cleared successfully.', 'user-ip-and-location'), 'updated');
        }

        /**
         * Render one labelled field row by invoking its field callback.
         *
         * @param string $label    Field label (already translated).
         * @param string $callback Method name on this class that echoes the control.
         * @return void
         */
        private function field($label, $callback)
        {
            echo '<div class="uipl-field">';
            echo '<label class="uipl-field__label">' . esc_html($label) . '</label>';
            echo '<div class="uipl-field__control">';
            call_user_func(array($this, $callback));
            echo '</div></div>';
        }

        /**
         * Return the crosshair/target brand mark as inline SVG.
         * Colour is inherited via currentColor.
         *
         * @param int $size Pixel size.
         * @return string Safe, static SVG markup.
         */
        private function target_svg($size = 16)
        {
            $s = (int) $size;
            return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">'
                . '<circle cx="12" cy="12" r="7"></circle>'
                . '<path d="M12 1v3M12 20v3M1 12h3M20 12h3"></path>'
                . '<circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"></circle>'
                . '</svg>';
        }

        private function render_settings_tab()
        {
            ?>
            <form action="options.php" method="post" class="uipl-form">
                <?php settings_fields('user_ip_location_settings'); ?>

                <div class="uipl-grid">
                    <section class="uipl-card">
                        <header class="uipl-card__head">
                            <span class="uipl-eyebrow"><?php esc_html_e('Service', 'user-ip-and-location'); ?></span>
                            <h2><?php esc_html_e('Geolocation API', 'user-ip-and-location'); ?></h2>
                            <p><?php esc_html_e('Connect to the ip-api.com service. The free tier works out of the box.', 'user-ip-and-location'); ?></p>
                        </header>
                        <?php
                        $this->field(__('API key', 'user-ip-and-location'), 'api_key_callback');
                        $this->field(__('Response language', 'user-ip-and-location'), 'api_lang_callback');
                        ?>
                    </section>

                    <section class="uipl-card">
                        <header class="uipl-card__head">
                            <span class="uipl-eyebrow"><?php esc_html_e('Performance', 'user-ip-and-location'); ?></span>
                            <h2><?php esc_html_e('Caching', 'user-ip-and-location'); ?></h2>
                            <p><?php esc_html_e('Cache lookups to cut API requests and speed up repeat visits.', 'user-ip-and-location'); ?></p>
                        </header>
                        <?php
                        $this->field(__('Persistent caching', 'user-ip-and-location'), 'enable_cache_callback');
                        $this->field(__('Cache expiration', 'user-ip-and-location'), 'cache_expiration_callback');
                        ?>
                    </section>

                    <section class="uipl-card">
                        <header class="uipl-card__head">
                            <span class="uipl-eyebrow"><?php esc_html_e('Compatibility', 'user-ip-and-location'); ?></span>
                            <h2><?php esc_html_e('Rendering', 'user-ip-and-location'); ?></h2>
                            <p><?php esc_html_e('How shortcodes output their value. Switch to server-side if forms or feeds break.', 'user-ip-and-location'); ?></p>
                        </header>
                        <?php $this->field(__('Render mode', 'user-ip-and-location'), 'render_mode_callback'); ?>
                    </section>

                    <section class="uipl-card uipl-card--wide">
                        <header class="uipl-card__head">
                            <span class="uipl-eyebrow"><?php esc_html_e('Output', 'user-ip-and-location'); ?></span>
                            <h2><?php esc_html_e('Formatting', 'user-ip-and-location'); ?></h2>
                            <p><?php esc_html_e('Control how yes/no values, time, and date appear in your shortcodes.', 'user-ip-and-location'); ?></p>
                        </header>
                        <?php
                        $this->field(__('Text for "Yes"', 'user-ip-and-location'), 'text_for_yes_callback');
                        $this->field(__('Text for "No"', 'user-ip-and-location'), 'text_for_no_callback');
                        $this->field(__('Local time format', 'user-ip-and-location'), 'time_format_callback');
                        $this->field(__('Local date format', 'user-ip-and-location'), 'date_format_callback');
                        ?>
                    </section>
                </div>

                <div class="uipl-actions">
                    <?php submit_button(__('Save changes', 'user-ip-and-location'), 'primary', 'submit', false); ?>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=user-ip-and-location&action=clear_cache'), 'user_ip_clear_cache')); ?>" class="button uipl-btn-ghost"><?php esc_html_e('Clear cache now', 'user-ip-and-location'); ?></a>
                </div>
            </form>
            <?php
        }

        private function render_your_info_tab()
        {
            $geo     = Geolocation::get_instance();
            $browser = Browser::get_instance();

            $ip       = $geo->getIP();
            $code     = strtolower($geo->getCountryCode());
            $country  = $geo->getCountry();
            $city     = $geo->getCity();
            $region   = $geo->getRegionName();
            $isp      = $geo->getISP();
            $lat      = $geo->getLat();
            $lon      = $geo->getLon();
            $timezone = $geo->getTimezone();
            $currency = $geo->getCurrency();
            $ua       = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

            $coords = ($lat !== '' && $lon !== '') ? $lat . ', ' . $lon : '';
            $place  = implode(' · ', array_filter(array($country, $city)));

            $dash = '&mdash;';
            $val = function ($value) use ($dash) {
                $value = trim((string) $value);
                return $value === '' ? $dash : esc_html($value);
            };
            ?>
            <div class="uipl-info">
                <div class="uipl-info__ip">
                    <span class="uipl-mark"><?php echo $this->target_svg(18); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span><?php echo $ip !== '' ? esc_html($ip) : $dash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php if ($code !== '') : ?>
                        <span class="uipl-flagchip">
                            <img src="<?php echo esc_url(USER_IP_AND_LOCATION_FLAGS . $code . '.png'); ?>" alt="<?php echo esc_attr($country); ?>" />
                            <?php echo esc_html($place !== '' ? $place : strtoupper($code)); ?>
                        </span>
                    <?php elseif ($place !== '') : ?>
                        <span class="uipl-flagchip">&#127758; <?php echo esc_html($place); ?></span>
                    <?php endif; ?>
                </div>

                <div class="uipl-info__grid">
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('Coordinates', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $coords !== '' ? esc_html($coords) : $dash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('Region', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($region); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('ISP', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($isp); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('Timezone', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($timezone); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('Currency', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($currency); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('Browser', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($browser->get_browser_name()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat"><span class="uipl-stat__label"><?php esc_html_e('Operating system', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($browser->get_operating_system()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                    <div class="uipl-stat uipl-stat--full"><span class="uipl-stat__label"><?php esc_html_e('User agent', 'user-ip-and-location'); ?></span><span class="uipl-stat__value"><?php echo $val($ua); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                </div>

                <p class="uipl-info__note"><?php esc_html_e("This is what the plugin detects for your current connection. Private or local addresses (like 127.0.0.1) can't be geolocated.", 'user-ip-and-location'); ?></p>
            </div>
            <?php
        }

        private function render_developer_tab()
        {
            ?>
            <div class="uipl-grid">
                <section class="uipl-card">
                    <header class="uipl-card__head">
                        <span class="uipl-eyebrow"><?php esc_html_e('PHP', 'user-ip-and-location'); ?></span>
                        <h2><?php esc_html_e('Global function', 'user-ip-and-location'); ?></h2>
                        <p><?php echo wp_kses(__('Call <code>get_user_ip_data()</code> for an array of the current visitor\'s location data. Returns an array on success, or <code>null</code> if the lookup fails.', 'user-ip-and-location'), array('code' => array())); ?></p>
                    </header>
                    <pre><code>&lt;?php
if ( function_exists( 'get_user_ip_data' ) ) {
    $location = get_user_ip_data();
    if ( $location && $location['countryCode'] === 'US' ) {
        echo 'Welcome, visitor from the United States!';
    }
}</code></pre>
                </section>

                <section class="uipl-card">
                    <header class="uipl-card__head">
                        <span class="uipl-eyebrow"><?php esc_html_e('REST', 'user-ip-and-location'); ?></span>
                        <h2><?php esc_html_e('API endpoint', 'user-ip-and-location'); ?></h2>
                        <p><?php echo wp_kses(__('Fetch the visitor\'s location over REST &mdash; useful for headless setups or JavaScript. Requires the <code>manage_options</code> capability.', 'user-ip-and-location'), array('code' => array())); ?></p>
                    </header>
                    <pre><code><?php echo esc_url(get_rest_url(null, 'user-ip/v1/location')); ?></code></pre>
                    <p class="description"><?php echo wp_kses(__('A <code>GET</code> request returns the same JSON as the PHP function above.', 'user-ip-and-location'), array('code' => array())); ?></p>
                </section>
            </div>
            <?php
        }

        private function render_shortcodes_tab()
        {
            // Single source: the location data fields from the central catalogue.
            $data_fields = array();
            foreach (Fields::catalog() as $field) {
                if ($field['kind'] === 'location') {
                    $data_fields[] = $field;
                }
            }
            ?>
            <div class="uipl-grid">
                <section class="uipl-card uipl-card--wide">
                    <header class="uipl-card__head">
                        <span class="uipl-eyebrow"><?php esc_html_e('Display data', 'user-ip-and-location'); ?></span>
                        <h2><?php esc_html_e('Visitor data shortcodes', 'user-ip-and-location'); ?></h2>
                        <p><?php esc_html_e('Drop any of these into a post, page, or widget to print a single value.', 'user-ip-and-location'); ?></p>
                    </header>
                    <ul class="uipl-list">
                        <?php foreach ($data_fields as $field) : ?>
                            <li>
                                <span class="uipl-list__label"><?php echo esc_html($field['label']); ?></span>
                                <code>[userip_location type="<?php echo esc_attr($field['value']); ?>"]</code>
                            </li>
                        <?php endforeach; ?>
                        <li>
                            <span class="uipl-list__label"><?php esc_html_e('Country flag', 'user-ip-and-location'); ?></span>
                            <code>[userip_location type="flag" height="auto" width="50px"]</code>
                        </li>
                    </ul>
                </section>

                <section class="uipl-card">
                    <header class="uipl-card__head">
                        <span class="uipl-eyebrow"><?php esc_html_e('Time & date', 'user-ip-and-location'); ?></span>
                        <h2><?php esc_html_e('Local time shortcodes', 'user-ip-and-location'); ?></h2>
                        <p><?php echo wp_kses(__('Formats are set in <strong>Settings &rarr; Formatting</strong>.', 'user-ip-and-location'), array('strong' => array())); ?></p>
                    </header>
                    <ul class="uipl-list">
                        <li><span class="uipl-list__label"><?php esc_html_e('Local time', 'user-ip-and-location'); ?></span><code>[userip_localtime]</code></li>
                        <li><span class="uipl-list__label"><?php esc_html_e('Local date', 'user-ip-and-location'); ?></span><code>[userip_localdate]</code></li>
                    </ul>
                </section>

                <section class="uipl-card">
                    <header class="uipl-card__head">
                        <span class="uipl-eyebrow"><?php esc_html_e('Targeting', 'user-ip-and-location'); ?></span>
                        <h2><?php esc_html_e('Conditional content', 'user-ip-and-location'); ?></h2>
                        <p><?php esc_html_e('Show or hide enclosed content by location. Combine attributes; values are case-insensitive.', 'user-ip-and-location'); ?></p>
                    </header>
                    <ul class="uipl-list">
                        <li><span class="uipl-list__label"><?php esc_html_e('Specific countries', 'user-ip-and-location'); ?></span><code>[userip_conditional country="US,CA"]&hellip;[/userip_conditional]</code></li>
                        <li><span class="uipl-list__label"><?php esc_html_e('Everyone except', 'user-ip-and-location'); ?></span><code>[userip_conditional country_not="CN,RU"]&hellip;[/userip_conditional]</code></li>
                        <li><span class="uipl-list__label"><?php esc_html_e('A region', 'user-ip-and-location'); ?></span><code>[userip_conditional region="TX"]&hellip;[/userip_conditional]</code></li>
                        <li><span class="uipl-list__label"><?php esc_html_e('A city', 'user-ip-and-location'); ?></span><code>[userip_conditional city="London"]&hellip;[/userip_conditional]</code></li>
                    </ul>
                    <p class="description"><?php echo wp_kses(__('Attributes: <code>country</code>, <code>country_not</code>, <code>region</code>, <code>region_not</code>, <code>city</code>, <code>city_not</code>.', 'user-ip-and-location'), array('code' => array())); ?></p>
                </section>

                <section class="uipl-card">
                    <div class="uipl-card__pad">
                        <p><?php echo wp_kses(__('Need a hand? Visit the <a href="https://wordpress.org/plugins/user-ip-and-location/" target="_blank" rel="noopener">plugin page on WordPress.org</a>.', 'user-ip-and-location'), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></p>
                    </div>
                </section>
            </div>
            <?php
        }
    }
}
