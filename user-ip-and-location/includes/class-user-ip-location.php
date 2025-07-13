<?php
/**
 * IP Class for getting the user's IP address and location.
 */

defined('ABSPATH') || exit; // Exit if accessed directly.

class User_IP_and_Location
{
    /**
     * The single instance of the class.
     * @var User_IP_and_Location|null
     */
    private static ?User_IP_and_Location $instance = null;

    /**
     * Stores the fetched location data from the API.
     * @var array|null
     */
    private ?array $data = null;

    /**
     * Stores the user's IP address.
     * @var string|null
     */
    private ?string $ip_address = null;

    /**
     * Get the singleton instance of the class.
     *
     * @return User_IP_and_Location
     */
    public static function get_instance(): User_IP_and_Location
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getIP(): string
    {
        return $this->getIPAddress();
    }

    private function getIPAddress(): string
    {
        if ($this->ip_address !== null) {
            return $this->ip_address;
        }

        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR', // Fallback
        ];

        foreach ($ipHeaders as $header) {
            if (isset($_SERVER[$header])) {
                // Take the first IP in the list if multiple are provided
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $this->ip_address = sanitize_text_field(wp_unslash($ip));
                    return $this->ip_address;
                }
            }
        }

        $this->ip_address = '0.0.0.0'; // Should not happen
        return $this->ip_address;
    }

    /**
     * Fetches data from the ip-api.com service if it hasn't been fetched yet.
     */
    private function fetch_data(): void
    {
        if ($this->data !== null) {
            return;
        }

        $ip = $this->getIPAddress();
        $options = get_option('user_ip_location_options', []);
        $use_cache = $options['enable_cache'] ?? 0;
        $transient_key = 'user_ip_location_' . md5($ip . ($options['api_lang'] ?? 'en'));

        if ($use_cache) {
            $cached_data = get_transient($transient_key);
            if ($cached_data !== false) {
                $this->data = $cached_data;
                return;
            }
        }

        $api_key = $options['api_key'] ?? '';
        $api_lang = $options['api_lang'] ?? 'en';

        // Per user request, the pro service uses HTTPS and the free service uses HTTP.
        // SECURITY WARNING: The free ip-api.com endpoint supports HTTPS. Using HTTP is a security risk
        // as it sends user IP addresses unencrypted over the internet.
        // It is strongly recommended to use 'https://ip-api.com/json/' for the free tier.
        $base_url = $api_key ? 'https://pro.ip-api.com/json/' : 'http://ip-api.com/json/';
        $url = $base_url . $ip;

        $query_args = [
            'fields' => 'status,message,continent,country,countryCode,region,regionName,city,zip,lat,lon,timezone,currency,isp,mobile,proxy,hosting,query',
        ];

        if ($api_key) {
            $query_args['key'] = $api_key;
        }

        if ($api_lang && $api_lang !== 'en') {
            $query_args['lang'] = $api_lang;
        }

        $url = add_query_arg($query_args, $url);

        $response = wp_remote_get($url, ['timeout' => 5]);

        if (is_wp_error($response)) {
            // Log error if you have a logging system
            $this->data = ['status' => 'fail', 'message' => $response->get_error_message()];
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $this->data = ['status' => 'fail', 'message' => 'Invalid JSON response'];
            return;
        }

        $this->data = $data;

        if ($use_cache && ($this->data['status'] ?? 'fail') === 'success') {
            $expiration = $options['cache_expiration'] ?? 3600;
            set_transient($transient_key, $this->data, $expiration);
        }
    }

    /**
     * Generic getter for a field from the API data.
     *
     * @param string $field The field to retrieve.
     * @param mixed $default The default value if the field is not set.
     * @return mixed
     */
    private function get_field(string $field, $default = '')
    {
        $this->fetch_data();
        if (isset($this->data['status']) && $this->data['status'] === 'success') {
            return $this->data[$field] ?? $default;
        }
        return $default;
    }

    public function getContinent(): string
    {
        return $this->get_field('continent');
    }

    public function getCountry(): string
    {
        return $this->get_field('country');
    }

    public function getCountryCode(): string
    {
        return $this->get_field('countryCode');
    }

    public function getRegion(): string
    {
        return $this->get_field('region');
    }

    public function getRegionName(): string
    {
        return $this->get_field('regionName');
    }

    public function getCity(): string
    {
        return $this->get_field('city');
    }

    public function getZip(): string
    {
        return $this->get_field('zip');
    }

    public function getLat(): string
    {
        return (string) $this->get_field('lat');
    }

    public function getLon(): string
    {
        return (string) $this->get_field('lon');
    }

    public function getTimezone(): string
    {
        return $this->get_field('timezone');
    }

    public function getLocalTime(string $format = 'g:i a'): string
    {
        $timezone = $this->getTimezone();
        if (empty($timezone)) {
            return '';
        }
        try {
            $date = new DateTime('now', new DateTimeZone($timezone));
            return $date->format($format);
        } catch (Exception $e) {
            // In a real application, you might want to log this error.
            return '';
        }
    }

    public function getCurrency(): string
    {
        return $this->get_field('currency');
    }

    public function getISP(): string
    {
        return $this->get_field('isp');
    }

    public function getMobile(): bool
    {
        return (bool) $this->get_field('mobile', false);
    }

    public function getProxy(): bool
    {
        return (bool) $this->get_field('proxy', false);
    }

    public function getHosting(): bool
    {
        return (bool) $this->get_field('hosting', false);
    }

    public function getflag(): string
    {
        return $this->getCountryCode();
    }

    /**
     * Returns the entire raw data array for the location.
     *
     * @return array|null The complete data array, or null if fetch failed.
     */
    public function get_all_data(): ?array
    {
        $this->fetch_data();
        if (isset($this->data['status']) && $this->data['status'] === 'success') {
            return $this->data;
        }
        return null;
    }
} 