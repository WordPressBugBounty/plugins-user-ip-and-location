<?php
/**
 * PSR-4 style autoloader for the UserIPLocation namespace, with backward
 * compatible aliases for the historical (non-namespaced) class names.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Autoloader
{
    /**
     * Historical class names mapped to their modern namespaced equivalents.
     * Third-party code calling the old names keeps working through aliases.
     *
     * @var array<string,string>
     */
    private static $legacy_aliases = array(
        'User_IP_and_Location'            => 'UserIPLocation\\Geolocation',
        'User_Browser'                    => 'UserIPLocation\\Browser',
        'User_IP_Location_Admin_Settings' => 'UserIPLocation\\Admin\\Settings',
    );

    /**
     * Register the autoloader with SPL.
     *
     * @return void
     */
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Resolve and load a class.
     *
     * @param string $class Fully qualified class name.
     * @return void
     */
    public static function autoload($class)
    {
        // Backward compatible alias for a historical class name.
        if (isset(self::$legacy_aliases[$class])) {
            $target = self::$legacy_aliases[$class];
            self::autoload($target); // Ensure the modern class file is loaded.
            if (class_exists($target, false)) {
                class_alias($target, $class);
            }
            return;
        }

        $prefix = 'UserIPLocation\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $relative = str_replace('\\', '/', $relative);
        $file = USER_IP_AND_LOCATION_PLUGIN_PATH . 'src/' . $relative . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
}
