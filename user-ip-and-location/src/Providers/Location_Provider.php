<?php
/**
 * Contract for a geolocation data provider. Implement this to add a new source
 * (e.g. a local MaxMind database or an alternative API) and wire it in with the
 * `user_ip_location_provider` filter — no changes to the rest of the plugin.
 */

namespace UserIPLocation\Providers;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

interface Location_Provider
{
    /**
     * Look up an IP address.
     *
     * @param string $ip      The IP address to resolve.
     * @param array  $options Plugin options (api_key, api_lang, …).
     * @return array Result array. Must contain a 'status' key ('success' or
     *               'fail'). On success, uses the canonical field schema
     *               (country, countryCode, regionName, city, query, …).
     */
    public function lookup($ip, array $options);

    /**
     * @return string Stable identifier for this provider (e.g. 'ip-api').
     */
    public function get_id();
}
