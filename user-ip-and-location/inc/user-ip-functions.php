<?php
/**
 * This function returns the user's IP address and location information.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include the required classes.
require_once USER_IP_AND_LOCATION_INC_PATH . "class.IP.php";
require_once USER_IP_AND_LOCATION_INC_PATH . "class.Browser.php";

/**
 * This function generates a shortcode to display the user's IP address and location information.
 *
 * @param array $atts Shortcode attributes.
 * @return string The user's IP address and location information.
 */
function user_ip_and_location($atts)
{
    // Extract the shortcode attributes.
    extract(
        shortcode_atts(
            [
                "type" => "",
                "height" => "auto",
                "width" => "50px",
            ],
            $atts
        )
    );

    // Convert the shortcode attribute to lowercase.
    $type = strtolower(sanitize_text_field($type));

    // Initialize the IP and Browser objects.
    $ip = new User_IP_and_Location();
    $browser = new User_Browser();

    // Switch statement to determine the type of information to display.
    switch ($type) {
        case "ip":
            return $ip->getIP();
        case "continent":
            return $ip->getContinent();
        case "country":
            return $ip->getCountry();
        case "countrycode":
            return $ip->getCountryCode();
        case "region":
            return $ip->getRegion();
        case "regionname":
            return $ip->getRegionName();
        case "city":
            return $ip->getCity();
        case "lat":
            return $ip->getLat();
        case "lon":
            return $ip->getLon();
        case "timezone":
            return $ip->getTimezone();
        case "currency":
            return $ip->getCurrency();
        case "isp":
            return $ip->getISP();
        case "mobile":
            return $ip->getMobile();
        case "proxy":
            return $ip->getProxy();
        case "hosting":
            return $ip->getHosting();
        case "browser":
            return $browser->get_browser_name();
        case "os":
            return $browser->get_operating_system();
        case "flag":
            // Get the user's country flag.
            $flag_country = $ip->getflag() ?: "us";
            $flag_url = sprintf(
                "%s/%s/flags/%s.png",
                plugins_url(),
                explode('/', plugin_basename(__FILE__))[0],
                strtolower($flag_country)
            );
            $flag_html = sprintf(
                '<img src="%s" style="height:%s!important;width:%s!important;" onmouseover="%s">',
                esc_url($flag_url),
                esc_attr($height),
                esc_attr($width),
                esc_attr('') // Sanitized onmouseover attribute
            );
            return $flag_html;
        default:
            return "<p>Invalid type</p>";
    }
}
add_shortcode("userip_location", "user_ip_and_location"); // Add the shortcode.
