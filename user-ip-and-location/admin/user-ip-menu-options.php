<?php
/**
 * User IP and Location Admin File for Creating Menu Content
 */

 if ( ! defined( 'ABSPATH' ) ) {
     exit; // Exit if accessed directly.
 }

 function user_ip_and_location_settings_page(){ ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">User IP and Location</h1>
            <p class="description">
                This plugin will display the user's IP address and location on the front end of your website.
            </p>
            <hr class="wp-header-end">
            <div class="user-ip-container">
              <h3 class="user-ip-title">Shortcodes for Displaying User IP and Location</h3>
              <ul style="display: block; list-style-type: disc; padding-inline-start: 40px;">
                <li><strong>Display IP:</strong> [userip_location type="ip"]</li>
                <li><strong>Display Continent Name:</strong> [userip_location type="continent"]</li>
                <li><strong>Display Country Name:</strong> [userip_location type="country"]</li>
                <li><strong>Display Country Code:</strong> [userip_location type="countrycode"]</li>
                <li><strong>Display Region:</strong> [userip_location type="region"]</li>
                <li><strong>Display Region Name:</strong> [userip_location type="regionname"]</li>
                <li><strong>Display City:</strong> [userip_location type="city"]</li>
                <li><strong>Display Latitude:</strong> [userip_location type="lat"]</li>
                <li><strong>Display Longitude:</strong> [userip_location type="lon"]</li>
                <li><strong>Display Timezone:</strong> [userip_location type="timezone"]</li>
                <li><strong>Display Currency:</strong> [userip_location type="currency"]</li>
                <li><strong>Display ISP Information:</strong> [userip_location type="isp"]</li>
                <li><strong>If Mobile (Return 0 for false and 1 for true):</strong> [userip_location type="mobile"]</li>
                <li><strong>If Proxy (Return 0 for false and 1 for true):</strong> [userip_location type="proxy"]</li>
                <li><strong>If Hosting (Return 0 for false and 1 for true):</strong> [userip_location type="hosting"]</li>
                <li><strong>Display Browser Name:</strong> [userip_location type="browser"]</li>
                <li><strong>Display Operating System:</strong> [userip_location type="os"]</li>
                <li><strong>Display Country Flag:</strong> [userip_location type="flag" height="auto" width="50px"]</li>
              </ul>
              <p>For support and queries, you can visit our <a href="https://wordpress.org/plugins/user-ip-and-location/">WordPress.org plugin page here</a>...</p>
            </div>
            
        </div>
<?php }
