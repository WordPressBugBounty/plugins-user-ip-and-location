<?php
/**
 * Resolves the visitor's IP address and fetches its location.
 *
 * This class owns the cross-cutting concerns — IP resolution, per-request
 * memoisation, caching, and rate limiting — and delegates the actual lookup to
 * a Location_Provider (ip-api.com by default). Swap providers via the
 * `user_ip_location_provider` filter.
 *
 * Note: the public getX() getters keep the pre-5.0 camelCase API for backward
 * compatibility (see the legacy aliases in Autoloader); new/internal methods
 * use snake_case. Getters are lazy — the first call triggers the lookup (which
 * may perform one cached/rate-limited HTTP request), so they are not free.
 */

namespace UserIPLocation;

use DateTime;
use DateTimeZone;
use Exception;
use UserIPLocation\Providers\Ip_Api_Provider;
use UserIPLocation\Providers\Location_Provider;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Geolocation')) {

    class Geolocation
    {
        /**
         * Shared transient/cache key for the outbound rate-limit window.
         */
        const RATE_KEY = 'user_ip_location_rate_limit';

        /**
         * Max outbound lookups per window. Kept below ip-api.com's ~45/min so a
         * single server IP is not banned.
         */
        const RATE_LIMIT_MAX = 35;

        /**
         * Rate-limit window length, in seconds.
         */
        const RATE_WINDOW = 60;

        /**
         * Default cache lifetime, in seconds, when none is configured.
         */
        const DEFAULT_CACHE_TTL = 3600;

        /**
         * Single instance.
         *
         * @var Geolocation|null
         */
        private static $instance = null;

        /**
         * Fetched location data from the API.
         *
         * @var array|null
         */
        private $data = null;

        /**
         * The resolved visitor IP address.
         *
         * @var string|null
         */
        private $ip_address = null;

        /**
         * Get the singleton instance.
         *
         * @return Geolocation
         */
        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * @return string The visitor's IP address.
         */
        public function getIP()
        {
            return $this->resolve_ip_address();
        }

        /**
         * Resolve the visitor IP, trusting proxy headers by default.
         *
         * @return string
         */
        private function resolve_ip_address()
        {
            if ($this->ip_address !== null) {
                return $this->ip_address;
            }

            $remote = isset($_SERVER['REMOTE_ADDR'])
                ? trim((string) wp_unslash($_SERVER['REMOTE_ADDR']))
                : '';

            /**
             * Whether to trust forwarded/proxy IP headers.
             *
             * Default true so visitors behind Cloudflare or a reverse proxy keep
             * resolving to their real client IP. Set to false on sites not behind
             * a trusted proxy to prevent header spoofing.
             *
             * @param bool $trust
             */
            $trust_proxy = apply_filters('user_ip_location_trust_proxy_headers', true);

            if ($trust_proxy) {
                $headers = array(
                    'HTTP_CF_CONNECTING_IP',
                    'HTTP_X_FORWARDED_FOR',
                    'HTTP_X_FORWARDED',
                    'HTTP_FORWARDED_FOR',
                    'HTTP_FORWARDED',
                    'HTTP_CLIENT_IP',
                );

                foreach ($headers as $header) {
                    if (empty($_SERVER[$header])) {
                        continue;
                    }

                    $candidates = explode(',', (string) wp_unslash($_SERVER[$header]));
                    foreach ($candidates as $candidate) {
                        $candidate = trim($candidate);

                        // Only accept a public, routable address from a header the
                        // client could forge; private/reserved values are skipped.
                        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            $this->ip_address = sanitize_text_field($candidate);
                            return $this->ip_address;
                        }
                    }
                }
            }

            if (filter_var($remote, FILTER_VALIDATE_IP)) {
                $this->ip_address = sanitize_text_field($remote);
                return $this->ip_address;
            }

            $this->ip_address = '0.0.0.0';
            return $this->ip_address;
        }

        /**
         * Resolve the visitor's location once per request, honouring cache,
         * rate limits, and provider/extension hooks.
         *
         * @return void
         */
        private function fetch_data()
        {
            if ($this->data !== null) {
                return;
            }

            $ip = $this->resolve_ip_address();

            $options = get_option('user_ip_location_options', array());
            if (!is_array($options)) {
                $options = array();
            }

            $use_cache = !empty($options['enable_cache']);
            $api_lang  = isset($options['api_lang']) ? $options['api_lang'] : 'en';

            // A cache "version" salt lets the admin clear all cached entries by
            // bumping a single option, which also works with object caches.
            $cache_version = (int) get_option('user_ip_location_cache_version', 1);
            $transient_key = 'user_ip_location_' . md5($cache_version . '|' . $ip . '|' . $api_lang);

            if ($use_cache) {
                $cached = get_transient($transient_key);
                if ($cached !== false) {
                    $this->data = $cached;
                    return;
                }
            }

            /**
             * Short-circuit the lookup. Return a data array (with a 'status'
             * key) to bypass the HTTP provider — e.g. a local-database add-on.
             *
             * @param array|null $data
             * @param string     $ip
             * @param array       $options
             */
            $pre = apply_filters('user_ip_location_pre_fetch', null, $ip, $options);
            if (is_array($pre) && isset($pre['status'])) {
                $this->data = $pre;
                return;
            }

            if (!$this->reserve_rate_slot()) {
                // Prefer stale-but-real cached data over a hard failure.
                $stale = get_transient($transient_key);
                if ($stale !== false) {
                    $this->data = $stale;
                    return;
                }
                $this->log('Rate limit exceeded');
                $this->data = array('status' => 'fail', 'message' => 'Rate limit exceeded');
                return;
            }

            /**
             * The location provider used for the lookup. Return any
             * \UserIPLocation\Providers\Location_Provider implementation.
             *
             * @param Location_Provider $provider
             */
            $provider = apply_filters('user_ip_location_provider', new Ip_Api_Provider());
            if (!($provider instanceof Location_Provider)) {
                $provider = new Ip_Api_Provider();
            }

            $data = $provider->lookup($ip, $options);
            if (!is_array($data) || !isset($data['status'])) {
                $data = array('status' => 'fail', 'message' => 'Invalid provider response');
            }

            if (isset($data['_error'])) {
                $this->log($provider->get_id() . ' lookup failed: ' . $data['_error']);
                unset($data['_error']);
            }

            /**
             * Filter the resolved location data before it is cached and used.
             *
             * @param array  $data
             * @param string $ip
             * @param string $provider_id
             */
            $data = apply_filters('user_ip_location_data', $data, $ip, $provider->get_id());

            if (!isset($data['status']) || $data['status'] !== 'success') {
                $reason = isset($data['message']) ? $data['message'] : 'unknown';
                /**
                 * Fires when a lookup does not succeed (for observability).
                 *
                 * @param string $reason
                 * @param string $ip
                 */
                do_action('user_ip_location_lookup_failed', $reason, $ip);
            }

            $this->data = $data;

            if ($use_cache && isset($this->data['status']) && $this->data['status'] === 'success') {
                $expiration = isset($options['cache_expiration']) ? (int) $options['cache_expiration'] : self::DEFAULT_CACHE_TTL;
                set_transient($transient_key, $this->data, $expiration);
            }
        }

        /**
         * Reserve one slot in the shared outbound rate-limit window. Counts the
         * attempt up front so failed calls also count. Uses an atomic counter on
         * external object caches and falls back to a transient otherwise.
         *
         * @return bool True if a slot was reserved; false if the window is full.
         */
        private function reserve_rate_slot()
        {
            if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()
                && function_exists('wp_cache_incr')) {
                wp_cache_add(self::RATE_KEY, 0, 'user_ip_location', self::RATE_WINDOW);
                $count = wp_cache_incr(self::RATE_KEY, 1, 'user_ip_location');
                if ($count === false) {
                    return true; // Counter unavailable; don't block legitimate traffic.
                }
                return $count <= self::RATE_LIMIT_MAX;
            }

            $rate = get_transient(self::RATE_KEY);
            if (!is_array($rate)) {
                $rate = array('count' => 0, 'time' => time());
            }
            if (time() - $rate['time'] >= self::RATE_WINDOW) {
                $rate = array('count' => 0, 'time' => time());
            }
            if ($rate['count'] >= self::RATE_LIMIT_MAX) {
                return false;
            }
            $rate['count']++;
            set_transient(self::RATE_KEY, $rate, self::RATE_WINDOW);
            return true;
        }

        /**
         * Generic getter for a successful-response field.
         *
         * @param string $field   Field name.
         * @param mixed  $default Default when unavailable.
         * @return mixed
         */
        private function get_field($field, $default = '')
        {
            $this->fetch_data();
            if (isset($this->data['status']) && $this->data['status'] === 'success') {
                return isset($this->data[$field]) ? $this->data[$field] : $default;
            }
            return $default;
        }

        /**
         * @return string
         */
        public function getContinent()
        {
            return $this->get_field('continent');
        }

        /**
         * @return string
         */
        public function getCountry()
        {
            return $this->get_field('country');
        }

        /**
         * @return string
         */
        public function getCountryCode()
        {
            return $this->get_field('countryCode');
        }

        /**
         * @return string
         */
        public function getRegion()
        {
            return $this->get_field('region');
        }

        /**
         * @return string
         */
        public function getRegionName()
        {
            return $this->get_field('regionName');
        }

        /**
         * @return string
         */
        public function getCity()
        {
            return $this->get_field('city');
        }

        /**
         * @return string
         */
        public function getZip()
        {
            return $this->get_field('zip');
        }

        /**
         * @return string
         */
        public function getLat()
        {
            return (string) $this->get_field('lat');
        }

        /**
         * @return string
         */
        public function getLon()
        {
            return (string) $this->get_field('lon');
        }

        /**
         * @return string
         */
        public function getTimezone()
        {
            return $this->get_field('timezone');
        }

        /**
         * @param string $format PHP date format.
         * @return string
         */
        public function getLocalTime($format = 'g:i a')
        {
            return $this->format_in_timezone($format);
        }

        /**
         * @param string $format PHP date format.
         * @return string
         */
        public function getLocalDate($format = 'F j, Y')
        {
            return $this->format_in_timezone($format);
        }

        /**
         * Format the current moment in the visitor's timezone.
         *
         * @param string $format PHP date format.
         * @return string
         */
        private function format_in_timezone($format)
        {
            $timezone = $this->getTimezone();
            if (empty($timezone)) {
                return '';
            }
            try {
                $date = new DateTime('now', new DateTimeZone($timezone));
                return $date->format($format);
            } catch (Exception $e) {
                return '';
            }
        }

        /**
         * @return string
         */
        public function getCurrency()
        {
            return $this->get_field('currency');
        }

        /**
         * @return string
         */
        public function getISP()
        {
            return $this->get_field('isp');
        }

        /**
         * @return bool
         */
        public function getMobile()
        {
            return (bool) $this->get_field('mobile', false);
        }

        /**
         * @return bool
         */
        public function getProxy()
        {
            return (bool) $this->get_field('proxy', false);
        }

        /**
         * @return bool
         */
        public function getHosting()
        {
            return (bool) $this->get_field('hosting', false);
        }

        /**
         * @return string Country code used to pick a flag image.
         */
        public function getflag()
        {
            return $this->getCountryCode();
        }

        /**
         * Returns the full raw data array on success.
         *
         * @return array|null
         */
        public function get_all_data()
        {
            $this->fetch_data();
            if (isset($this->data['status']) && $this->data['status'] === 'success') {
                return $this->data;
            }
            return null;
        }

        /**
         * Log a debug message only when WP_DEBUG is enabled.
         *
         * @param string $message
         * @return void
         */
        private function log($message)
        {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('User IP Location: ' . $message);
            }
        }
    }
}
