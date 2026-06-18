<?php
/**
 * Default location provider, backed by the ip-api.com service.
 */

namespace UserIPLocation\Providers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Providers\\Ip_Api_Provider')) {

    class Ip_Api_Provider implements Location_Provider
    {
        /**
         * Fields requested from ip-api.com — defines the canonical data schema.
         */
        const FIELDS = 'status,message,continent,country,countryCode,region,regionName,city,zip,lat,lon,timezone,currency,isp,mobile,proxy,hosting,query';

        /**
         * Request timeout in seconds.
         */
        const TIMEOUT = 10;

        /**
         * @return string
         */
        public function get_id()
        {
            return 'ip-api';
        }

        /**
         * @param string $ip
         * @param array  $options
         * @return array
         */
        public function lookup($ip, array $options)
        {
            $api_key  = isset($options['api_key']) ? $options['api_key'] : '';
            $api_lang = isset($options['api_lang']) ? $options['api_lang'] : 'en';

            $base_url = $api_key ? 'https://pro.ip-api.com/json/' : 'http://ip-api.com/json/';
            $url = $base_url . $ip;

            $query_args = array('fields' => self::FIELDS);
            if ($api_key) {
                $query_args['key'] = $api_key;
            }
            if ($api_lang && $api_lang !== 'en') {
                $query_args['lang'] = $api_lang;
            }
            $url = add_query_arg($query_args, $url);

            $response = wp_remote_get($url, array(
                'timeout'   => self::TIMEOUT,
                'sslverify' => true,
                'headers'   => array(
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                ),
            ));

            if (is_wp_error($response)) {
                return $this->failure('API request failed', $response->get_error_message());
            }

            $http_code = (int) wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                return $this->failure('API service unavailable', 'HTTP ' . $http_code);
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return $this->failure('Empty API response', 'empty body');
            }

            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return $this->failure('Invalid API response format', json_last_error_msg());
            }

            if (!isset($data['status'])) {
                return $this->failure('Malformed API response', 'missing status field');
            }

            return $data;
        }

        /**
         * Build a failure result. The `_error` key carries a detailed reason for
         * debug logging; the caller strips it before the data is cached/returned.
         *
         * @param string $message Public message.
         * @param string $detail  Detailed reason for logs.
         * @return array
         */
        private function failure($message, $detail)
        {
            return array(
                'status'  => 'fail',
                'message' => $message,
                '_error'  => $detail,
            );
        }
    }
}
