<?php
/**
 * Single source of truth for the data fields the plugin exposes. Used by the
 * shortcodes (valid types), the admin guide, and the block editor (via localize)
 * so the list is defined exactly once.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Fields')) {

    class Fields
    {
        /**
         * The full catalogue. Each entry has:
         *  - value: the shortcode "type" / block field key
         *  - label: translated, human label
         *  - kind:  'location' (a [userip_location type=] value),
         *           'flag' (the flag image), or
         *           'time' (its own shortcode: localtime / localdate)
         *
         * @return array<int,array<string,string>>
         */
        public static function catalog()
        {
            return array(
                array('value' => 'ip',          'label' => __('IP address', 'user-ip-and-location'),        'kind' => 'location'),
                array('value' => 'continent',   'label' => __('Continent', 'user-ip-and-location'),         'kind' => 'location'),
                array('value' => 'country',      'label' => __('Country', 'user-ip-and-location'),           'kind' => 'location'),
                array('value' => 'countrycode',  'label' => __('Country code', 'user-ip-and-location'),      'kind' => 'location'),
                array('value' => 'region',       'label' => __('Region code', 'user-ip-and-location'),       'kind' => 'location'),
                array('value' => 'regionname',   'label' => __('Region name', 'user-ip-and-location'),       'kind' => 'location'),
                array('value' => 'city',         'label' => __('City', 'user-ip-and-location'),              'kind' => 'location'),
                array('value' => 'zip',          'label' => __('ZIP / postal code', 'user-ip-and-location'), 'kind' => 'location'),
                array('value' => 'lat',          'label' => __('Latitude', 'user-ip-and-location'),          'kind' => 'location'),
                array('value' => 'lon',          'label' => __('Longitude', 'user-ip-and-location'),         'kind' => 'location'),
                array('value' => 'timezone',     'label' => __('Timezone', 'user-ip-and-location'),          'kind' => 'location'),
                array('value' => 'currency',     'label' => __('Currency', 'user-ip-and-location'),          'kind' => 'location'),
                array('value' => 'isp',          'label' => __('ISP', 'user-ip-and-location'),               'kind' => 'location'),
                array('value' => 'mobile',       'label' => __('On mobile? (Yes/No)', 'user-ip-and-location'), 'kind' => 'location'),
                array('value' => 'proxy',        'label' => __('Using proxy? (Yes/No)', 'user-ip-and-location'), 'kind' => 'location'),
                array('value' => 'hosting',      'label' => __('Hosting provider? (Yes/No)', 'user-ip-and-location'), 'kind' => 'location'),
                array('value' => 'browser',      'label' => __('Browser', 'user-ip-and-location'),           'kind' => 'location'),
                array('value' => 'os',           'label' => __('Operating system', 'user-ip-and-location'),  'kind' => 'location'),
                array('value' => 'flag',         'label' => __('Country flag', 'user-ip-and-location'),       'kind' => 'flag'),
                array('value' => 'localtime',    'label' => __('Local time', 'user-ip-and-location'),        'kind' => 'time'),
                array('value' => 'localdate',    'label' => __('Local date', 'user-ip-and-location'),        'kind' => 'time'),
            );
        }

        /**
         * Valid values for [userip_location type="..."] (everything except the
         * standalone time/date shortcodes).
         *
         * @return array<int,string>
         */
        public static function shortcode_types()
        {
            $types = array();
            foreach (self::catalog() as $field) {
                if ($field['kind'] !== 'time') {
                    $types[] = $field['value'];
                }
            }
            return $types;
        }

        /**
         * Catalogue as {value,label} pairs for the block editor.
         *
         * @return array<int,array<string,string>>
         */
        public static function for_editor()
        {
            $out = array();
            foreach (self::catalog() as $field) {
                $out[] = array('value' => $field['value'], 'label' => $field['label']);
            }
            return $out;
        }
    }
}
