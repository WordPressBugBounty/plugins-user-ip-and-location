<?php
/**
 * Registers the REST API endpoints and ensures their responses are never cached.
 */

namespace UserIPLocation;

use WP_REST_Server;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Rest_Controller')) {

    class Rest_Controller
    {
        /**
         * REST namespace.
         */
        const REST_NAMESPACE = 'user-ip/v1';

        /**
         * Hook into WordPress.
         *
         * @return void
         */
        public function register()
        {
            add_action('rest_api_init', array($this, 'register_routes'));
            add_action('init', array($this, 'maybe_send_cache_headers'), 1);
        }

        /**
         * Register both routes.
         *
         * @return void
         */
        public function register_routes()
        {
            // Admin-only endpoint (full raw data).
            register_rest_route(self::REST_NAMESPACE, '/location', array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'admin_data'),
                'permission_callback' => array($this, 'admin_permission'),
            ));

            // Public endpoint used by the frontend script.
            register_rest_route(self::REST_NAMESPACE, '/data', array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'public_data'),
                'permission_callback' => '__return_true',
            ));
        }

        /**
         * Permission check for the admin endpoint.
         *
         * @return bool
         */
        public function admin_permission()
        {
            return current_user_can('manage_options');
        }

        /**
         * Emit no-cache headers early for any request to our REST endpoints.
         *
         * @return void
         */
        public function maybe_send_cache_headers()
        {
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            if (strpos($uri, '/wp-json/user-ip/v1/') === false) {
                return;
            }
            $this->send_no_cache_headers();
        }

        /**
         * Public endpoint callback. Enriches raw data for the frontend script.
         *
         * @return WP_REST_Response
         */
        public function public_data()
        {
            $geo  = Geolocation::get_instance();
            $data = $geo->get_all_data();

            if (!$data) {
                return $this->error_response();
            }

            // Normalize keys to match the shortcode "type" attribute names.
            $data['ip']          = isset($data['query']) ? $data['query'] : '';
            $data['countrycode'] = isset($data['countryCode']) ? $data['countryCode'] : '';
            $data['regionname']  = isset($data['regionName']) ? $data['regionName'] : '';

            $browser = Browser::get_instance();
            $data['browser'] = $browser->get_browser_name();
            $data['os']      = $browser->get_operating_system();

            $options = get_option('user_ip_location_options', array());
            if (!is_array($options)) {
                $options = array();
            }

            $time_format = isset($options['time_format']) ? $options['time_format'] : 'g:i A';
            $date_format = isset($options['date_format']) ? $options['date_format'] : 'F j, Y';
            $text_yes    = isset($options['text_for_yes']) ? $options['text_for_yes'] : 'Yes';
            $text_no     = isset($options['text_for_no']) ? $options['text_for_no'] : 'No';

            $data['localtime'] = $geo->getLocalTime($time_format);
            $data['localdate'] = $geo->getLocalDate($date_format);

            $data['mobile_text']  = !empty($data['mobile']) ? $text_yes : $text_no;
            $data['proxy_text']   = !empty($data['proxy']) ? $text_yes : $text_no;
            $data['hosting_text'] = !empty($data['hosting']) ? $text_yes : $text_no;

            return $this->success_response($data);
        }

        /**
         * Admin endpoint callback. Returns the raw data array.
         *
         * @return WP_REST_Response
         */
        public function admin_data()
        {
            $data = get_user_ip_data();
            if (!$data) {
                return $this->error_response();
            }
            return $this->success_response($data);
        }

        /**
         * Build a successful, non-cacheable response.
         *
         * @param array $data
         * @return WP_REST_Response
         */
        private function success_response($data)
        {
            $this->send_no_cache_headers();
            $response = new WP_REST_Response($data, 200);
            $this->attach_no_cache_headers($response);
            return $response;
        }

        /**
         * Build an error response.
         *
         * @return WP_REST_Response
         */
        private function error_response()
        {
            $response = new WP_REST_Response(array('error' => 'Could not retrieve location data.'), 500);
            $this->attach_no_cache_headers($response);
            return $response;
        }

        /**
         * Send no-cache headers via PHP header() before output.
         *
         * @return void
         */
        private function send_no_cache_headers()
        {
            if (headers_sent()) {
                return;
            }
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('X-Robots-Tag: noindex, nofollow');
            header('X-LiteSpeed-Cache-Control: no-cache');
            header('X-Nginx-Cache: BYPASS');
            header('X-Proxy-Cache: BYPASS');
            header('X-Cache: BYPASS');
            header('X-FlyingPress-Cache: no-cache');
        }

        /**
         * Attach no-cache headers to a REST response object.
         *
         * @param WP_REST_Response $response
         * @return void
         */
        private function attach_no_cache_headers($response)
        {
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        }
    }
}
