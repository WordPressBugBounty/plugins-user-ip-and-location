<?php
/**
 * Browser Class for getting the user's browser details.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('User_Browser')) {
    class User_Browser
    {
        private static ?User_Browser $instance = null;
        private string $user_agent;

        public static function get_instance(): User_Browser
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct()
        {
            $this->user_agent = $this->get_user_agent();
        }

        private function get_user_agent(): string
        {
            return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        }

        public function get_browser_name(): string
        {
            $browser_name = 'Unknown';

            // The order is important here. More specific agents first.
            $browser_array = [
                '/edge/i'    => 'Edge',
                '/chrome/i'  => 'Chrome',
                '/firefox/i' => 'Firefox',
                '/safari/i'  => 'Safari',
                '/msie/i'    => 'Internet Explorer',
                '/opera/i'   => 'Opera',
                '/netscape/i' => 'Netscape',
                '/maxthon/i' => 'Maxthon',
                '/konqueror/i' => 'Konqueror',
                '/mobile/i'  => 'Handheld Browser',
            ];

            foreach ($browser_array as $regex => $value) {
                if (preg_match($regex, $this->user_agent)) {
                    // Check to ensure Chrome and Edge are not identified as Safari
                    if ($value === 'Safari' && (preg_match('/chrome/i', $this->user_agent) || preg_match('/edge/i', $this->user_agent))) {
                        continue;
                    }
                    $browser_name = $value;
                    break; // Stop after first match
                }
            }
            return $browser_name;
        }

        public function get_operating_system(): string
        {
            $os_platform = 'Unknown OS';

            // The order is important here. More specific agents first.
            $os_array = [
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
                '/macintosh|mac os x/i' => 'Mac OS X',
                '/mac_powerpc/i'        => 'Mac OS 9',
                '/linux/i'              => 'Linux',
                '/ubuntu/i'             => 'Ubuntu',
                '/iphone/i'             => 'iPhone',
                '/ipod/i'               => 'iPod',
                '/ipad/i'               => 'iPad',
                '/android/i'            => 'Android',
                '/blackberry/i'         => 'BlackBerry',
                '/webos/i'              => 'Mobile',
            ];

            foreach ($os_array as $regex => $value) {
                if (preg_match($regex, $this->user_agent)) {
                    $os_platform = $value;
                    break; // Stop after first match
                }
            }
            return $os_platform;
        }
    }
} 