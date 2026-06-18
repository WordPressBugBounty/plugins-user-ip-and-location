<?php
/**
 * Parses the visitor's user agent into a browser name and operating system.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Browser')) {

    class Browser
    {
        /**
         * Single instance.
         *
         * @var Browser|null
         */
        private static $instance = null;

        /**
         * Raw user agent string.
         *
         * @var string
         */
        private $user_agent = '';

        /**
         * Get the singleton instance.
         *
         * @return Browser
         */
        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct()
        {
            $this->user_agent = isset($_SERVER['HTTP_USER_AGENT'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
                : '';
        }

        /**
         * Detect the browser name from the user agent.
         *
         * Order matters: brand tokens that masquerade inside other UAs
         * (Edge and Opera both carry "Chrome" and "Safari") are matched first.
         *
         * @return string
         */
        public function get_browser_name()
        {
            $ua = $this->user_agent;
            if ($ua === '') {
                return 'Unknown';
            }

            $patterns = array(
                '/edg(a|ios|e)?\//i' => 'Edge',              // Chromium Edge "Edg/", legacy "Edge/", mobile EdgA/EdgiOS.
                '/opr\/|opera/i'     => 'Opera',             // Modern Opera "OPR/" and legacy "Opera".
                '/firefox|fxios/i'   => 'Firefox',
                '/msie|trident/i'    => 'Internet Explorer',
                '/chrome|crios/i'    => 'Chrome',
                '/safari/i'          => 'Safari',
                '/maxthon/i'         => 'Maxthon',
                '/konqueror/i'       => 'Konqueror',
                '/mobile/i'          => 'Handheld Browser',
            );

            foreach ($patterns as $regex => $name) {
                if (preg_match($regex, $ua)) {
                    // Chrome, Edge and Opera UAs also contain "Safari"; never
                    // report those as Safari.
                    if ($name === 'Safari' && preg_match('/chrome|crios|edg|opr/i', $ua)) {
                        continue;
                    }
                    return $name;
                }
            }

            return 'Unknown';
        }

        /**
         * Detect the operating system from the user agent.
         *
         * @return string
         */
        public function get_operating_system()
        {
            $ua = $this->user_agent;
            if ($ua === '') {
                return 'Unknown OS';
            }

            $patterns = array(
                '/windows nt 10/i'      => 'Windows 10',
                '/windows nt 6.3/i'     => 'Windows 8.1',
                '/windows nt 6.2/i'     => 'Windows 8',
                '/windows nt 6.1/i'     => 'Windows 7',
                '/windows nt 6.0/i'     => 'Windows Vista',
                '/windows nt 5.2/i'     => 'Windows Server 2003/XP x64',
                '/windows nt 5.1/i'     => 'Windows XP',
                '/windows xp/i'         => 'Windows XP',
                '/windows nt 5.0/i'     => 'Windows 2000',
                '/windows me/i'         => 'Windows ME',
                '/win98/i'              => 'Windows 98',
                '/win95/i'              => 'Windows 95',
                '/win16/i'              => 'Windows 3.11',
                '/iphone/i'             => 'iPhone',
                '/ipod/i'               => 'iPod',
                '/ipad/i'               => 'iPad',
                '/android/i'            => 'Android',
                '/blackberry/i'         => 'BlackBerry',
                '/webos/i'              => 'Mobile',
                '/ubuntu/i'             => 'Ubuntu',
                '/macintosh|mac os x/i' => 'Mac OS X',
                '/mac_powerpc/i'        => 'Mac OS 9',
                '/linux/i'              => 'Linux',
            );

            foreach ($patterns as $regex => $name) {
                if (preg_match($regex, $ua)) {
                    return $name;
                }
            }

            return 'Unknown OS';
        }
    }
}
