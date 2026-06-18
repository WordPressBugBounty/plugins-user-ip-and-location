<?php
/**
 * Registers the plugin's shortcodes and the frontend script they depend on.
 *
 * Shortcodes emit lightweight placeholder markup that the frontend script fills
 * from the REST API, keeping the output cache-friendly.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Shortcodes')) {

    class Shortcodes
    {
        /**
         * Whether the frontend script has been enqueued this request.
         *
         * @var bool
         */
        private $enqueued = false;

        /**
         * Hook everything into WordPress.
         *
         * @return void
         */
        public function register()
        {
            add_action('wp_enqueue_scripts', array($this, 'register_assets'));

            add_shortcode('userip_location', array($this, 'location'));
            add_shortcode('userip_localtime', array($this, 'localtime'));
            add_shortcode('userip_localdate', array($this, 'localdate'));
            add_shortcode('userip_conditional', array($this, 'conditional'));
        }

        /**
         * Register (but do not enqueue) the frontend script and its data.
         *
         * @return void
         */
        public function register_assets()
        {
            wp_register_script(
                'user-ip-location-script',
                USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/js/user-ip-location.js',
                array(),
                USER_IP_AND_LOCATION_VERSION,
                true
            );

            wp_localize_script('user-ip-location-script', 'userIpLocationData', array(
                'apiUrl'   => esc_url_raw(get_rest_url(null, 'user-ip/v1/data')),
                'flagsUrl' => USER_IP_AND_LOCATION_FLAGS,
            ));
        }

        /**
         * Enqueue the script once per request.
         *
         * @return void
         */
        private function enqueue()
        {
            if ($this->enqueued) {
                return;
            }
            wp_enqueue_script('user-ip-location-script');
            $this->enqueued = true;
        }

        /**
         * Decide whether a shortcode renders server-side (plain value, no JS) or
         * emits an AJAX placeholder. An explicit ajax="false" attribute wins;
         * otherwise the global "render_mode" setting decides. Default is AJAX,
         * so existing sites are unaffected.
         *
         * Server-side is the right choice inside form fields (Ninja Forms, etc.),
         * RSS/AMP, and sites without page caching, where injected HTML or absent
         * JavaScript would otherwise break things.
         *
         * @param mixed $atts Shortcode attributes (array, or '' when none).
         * @return bool
         */
        private function is_server_side($atts)
        {
            // An explicit ajax attribute wins. Note shortcode_atts() always sets
            // this key (default ''), so treat empty as "not set" and fall through
            // to the global render_mode option.
            if (is_array($atts) && isset($atts['ajax']) && $atts['ajax'] !== '') {
                $ajax = strtolower(trim((string) $atts['ajax']));
                return in_array($ajax, array('false', '0', 'no', 'off', 'server'), true);
            }
            $options = get_option('user_ip_location_options', array());
            $mode = (is_array($options) && isset($options['render_mode'])) ? $options['render_mode'] : 'ajax';
            return $mode === 'server';
        }

        /**
         * Resolve a single data point server-side as plain text.
         *
         * @param string $type Data type (ip, country, ...).
         * @return string
         */
        private function resolve_value($type)
        {
            $geo = Geolocation::get_instance();
            switch ($type) {
                case 'ip':          return $geo->getIP();
                case 'continent':   return $geo->getContinent();
                case 'country':     return $geo->getCountry();
                case 'countrycode': return $geo->getCountryCode();
                case 'region':      return $geo->getRegion();
                case 'regionname':  return $geo->getRegionName();
                case 'city':        return $geo->getCity();
                case 'zip':         return $geo->getZip();
                case 'lat':         return $geo->getLat();
                case 'lon':         return $geo->getLon();
                case 'timezone':    return $geo->getTimezone();
                case 'currency':    return $geo->getCurrency();
                case 'isp':         return $geo->getISP();
                case 'mobile':      return $this->bool_text($geo->getMobile());
                case 'proxy':       return $this->bool_text($geo->getProxy());
                case 'hosting':     return $this->bool_text($geo->getHosting());
                case 'browser':     return Browser::get_instance()->get_browser_name();
                case 'os':          return Browser::get_instance()->get_operating_system();
            }
            return '';
        }

        /**
         * Map a boolean to the admin-configured Yes/No text.
         *
         * @param bool $value
         * @return string
         */
        private function bool_text($value)
        {
            $options = get_option('user_ip_location_options', array());
            $yes = (is_array($options) && isset($options['text_for_yes'])) ? $options['text_for_yes'] : 'Yes';
            $no  = (is_array($options) && isset($options['text_for_no'])) ? $options['text_for_no'] : 'No';
            return $value ? $yes : $no;
        }

        /**
         * Build the country-flag <img> server-side.
         *
         * @param array $atts Shortcode attributes (height, width, vertical_align).
         * @return string
         */
        private function flag_html($atts)
        {
            $geo  = Geolocation::get_instance();
            $code = strtolower($geo->getCountryCode());
            if ($code === '') {
                $code = 'unknown';
            }
            return sprintf(
                '<img src="%s" alt="%s" style="height:%s;width:%s;vertical-align:%s;" class="user-ip-flag" />',
                esc_url(USER_IP_AND_LOCATION_FLAGS . $code . '.png'),
                esc_attr($geo->getCountry()),
                esc_attr($atts['height']),
                esc_attr($atts['width']),
                esc_attr($atts['vertical_align'])
            );
        }

        /**
         * [userip_location] - a single data point. AJAX placeholder by default,
         * or the resolved value when rendering server-side.
         *
         * @param array $atts Shortcode attributes.
         * @return string
         */
        public function location($atts)
        {
            $atts = shortcode_atts(array(
                'type'           => 'ip',
                'height'         => 'auto',
                'width'          => '50px',
                'vertical_align' => 'middle',
                'ajax'           => '',
            ), $atts, 'userip_location');

            $type = strtolower(sanitize_text_field($atts['type']));

            /**
             * Valid shortcode "type" values. Add-ons can register new fields.
             *
             * @param array $valid_types
             */
            $valid_types = apply_filters('user_ip_location_valid_types', Fields::shortcode_types());
            if (!in_array($type, $valid_types, true)) {
                return '';
            }

            if ($this->is_server_side($atts)) {
                if ($type === 'flag') {
                    return $this->flag_html($atts);
                }
                return esc_html($this->resolve_value($type));
            }

            $this->enqueue();

            $data_attributes = ' data-type="' . esc_attr($type) . '"';
            if ($type === 'flag') {
                $data_attributes .= ' data-height="' . esc_attr($atts['height']) . '"';
                $data_attributes .= ' data-width="' . esc_attr($atts['width']) . '"';
                $data_attributes .= ' data-vertical-align="' . esc_attr($atts['vertical_align']) . '"';
            }

            return '<span class="user-ip-placeholder"' . $data_attributes . '></span>';
        }

        /**
         * [userip_localtime] - the visitor's local time.
         *
         * @param mixed $atts Shortcode attributes.
         * @return string
         */
        public function localtime($atts = array())
        {
            if ($this->is_server_side($atts)) {
                $options = get_option('user_ip_location_options', array());
                $format = (is_array($options) && isset($options['time_format'])) ? $options['time_format'] : 'g:i A';
                return esc_html(Geolocation::get_instance()->getLocalTime($format));
            }
            $this->enqueue();
            return '<span class="user-ip-placeholder" data-type="localtime"></span>';
        }

        /**
         * [userip_localdate] - the visitor's local date.
         *
         * @param mixed $atts Shortcode attributes.
         * @return string
         */
        public function localdate($atts = array())
        {
            if ($this->is_server_side($atts)) {
                $options = get_option('user_ip_location_options', array());
                $format = (is_array($options) && isset($options['date_format'])) ? $options['date_format'] : 'F j, Y';
                return esc_html(Geolocation::get_instance()->getLocalDate($format));
            }
            $this->enqueue();
            return '<span class="user-ip-placeholder" data-type="localdate"></span>';
        }

        /**
         * [userip_conditional] - show enclosed content based on visitor location.
         * Evaluated client-side by default (cache-friendly), or server-side when
         * ajax="false" or the global render mode is server-side.
         *
         * @param array       $atts    Shortcode attributes.
         * @param string|null $content Enclosed content.
         * @return string
         */
        public function conditional($atts, $content = null)
        {
            if (empty($content)) {
                return '';
            }

            $conditions = array();
            $allowed_conditions = array('country', 'country_not', 'region', 'region_not', 'city', 'city_not');
            foreach ($allowed_conditions as $condition) {
                if (isset($atts[$condition])) {
                    $conditions[$condition] = sanitize_text_field($atts[$condition]);
                }
            }

            if (empty($conditions)) {
                return do_shortcode($content);
            }

            if ($this->is_server_side($atts)) {
                return $this->evaluate_conditions_server($conditions) ? do_shortcode($content) : '';
            }

            $this->enqueue();

            $unique_id = 'userip-conditional-' . uniqid();

            $wrapper_attrs  = ' id="' . esc_attr($unique_id) . '"';
            $wrapper_attrs .= ' class="user-ip-conditional"';
            $wrapper_attrs .= ' data-conditions="' . esc_attr(wp_json_encode($conditions)) . '"';
            $wrapper_attrs .= ' style="display: none;"';

            return '<div' . $wrapper_attrs . '>' . do_shortcode($content) . '</div>';
        }

        /**
         * Server-side counterpart of the JavaScript condition evaluator. Mirrors
         * its mapping and logic exactly so both modes behave identically.
         *
         * @param array $conditions
         * @return bool Whether the content should display.
         */
        private function evaluate_conditions_server($conditions)
        {
            $data = get_user_ip_data();
            if (!$data) {
                // Fail closed: don't reveal location-gated content if lookup fails.
                return false;
            }

            $map = array(
                'country'     => 'countryCode',
                'country_not' => 'countryCode',
                'region'      => 'region',
                'region_not'  => 'region',
                'city'        => 'city',
                'city_not'    => 'city',
            );

            foreach ($conditions as $key => $raw) {
                if (!isset($map[$key])) {
                    continue;
                }
                $values = array_map('trim', explode(',', strtolower($raw)));
                $user_value = strtolower(isset($data[$map[$key]]) ? $data[$map[$key]] : '');
                $is_not = (strpos($key, '_not') !== false);
                $is_match = in_array($user_value, $values, true);
                if (($is_not && $is_match) || (!$is_not && !$is_match)) {
                    return false;
                }
            }

            return true;
        }
    }
}
