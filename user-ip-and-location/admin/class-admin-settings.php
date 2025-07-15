<?php
/**
 * Handles the admin settings page and all its functionality.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class User_IP_Location_Admin_Settings
{
    /**
     * The single instance of the class.
     */
    private static ?User_IP_Location_Admin_Settings $instance = null;

    /**
     * Get the singleton instance of the class.
     */
    public static function get_instance(): User_IP_Location_Admin_Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor to hook into WordPress.
     */
    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Adds a "Settings" link to the plugin's action links on the Plugins page.
     *
     * @param array $links An array of plugin action links.
     * @return array An array of plugin action links.
     */
    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=user-ip-and-location') . '">' . __('Settings', 'user-ip-and-location') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Adds the admin menu page.
     */
    public function add_admin_menu()
    {
        add_options_page(
            'User IP and Location',
            'User IP and Location',
            'manage_options',
            'user-ip-and-location',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registers the plugin's settings using the Settings API.
     */
    public function register_settings()
    {
        register_setting('user_ip_location_settings', 'user_ip_location_options', [$this, 'sanitize_options']);

        // API Section
        add_settings_section('user_ip_location_api_section', 'API Settings', [$this, 'api_section_callback'], 'user_ip_location_settings');
        add_settings_field('api_key', 'API Key', [$this, 'api_key_callback'], 'user_ip_location_settings', 'user_ip_location_api_section');
        add_settings_field('api_lang', 'Response Language', [$this, 'api_lang_callback'], 'user_ip_location_settings', 'user_ip_location_api_section');

        // Caching Section
        add_settings_section('user_ip_location_caching_section', 'Caching Settings', [$this, 'caching_section_callback'], 'user_ip_location_settings');
        add_settings_field('enable_cache', 'Persistent Caching', [$this, 'enable_cache_callback'], 'user_ip_location_settings', 'user_ip_location_caching_section');
        add_settings_field('cache_expiration', 'Cache Expiration', [$this, 'cache_expiration_callback'], 'user_ip_location_settings', 'user_ip_location_caching_section');

        // Formatting Section
        add_settings_section('user_ip_location_formatting_section', 'Output Formatting', [$this, 'formatting_section_callback'], 'user_ip_location_settings');
        add_settings_field('text_for_yes', 'Text for "Yes"', [$this, 'text_for_yes_callback'], 'user_ip_location_settings', 'user_ip_location_formatting_section');
        add_settings_field('text_for_no', 'Text for "No"', [$this, 'text_for_no_callback'], 'user_ip_location_settings', 'user_ip_location_formatting_section');
        add_settings_field('time_format', 'Local Time Format', [$this, 'time_format_callback'], 'user_ip_location_settings', 'user_ip_location_formatting_section');
        add_settings_field('date_format', 'Local Date Format', [$this, 'date_format_callback'], 'user_ip_location_settings', 'user_ip_location_formatting_section');
    }

    /**
     * Sanitize the plugin options.
     */
    public function sanitize_options($input)
    {
        $new_input = [];
        $new_input['enable_cache'] = isset($input['enable_cache']) ? 1 : 0;
        $new_input['cache_expiration'] = isset($input['cache_expiration']) ? absint($input['cache_expiration']) : 3600;
        $new_input['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $languages = ['en', 'de', 'es', 'pt-BR', 'fr', 'ja', 'zh-CN', 'ru'];
        $new_input['api_lang'] = isset($input['api_lang']) && in_array($input['api_lang'], $languages) ? $input['api_lang'] : 'en';
        $new_input['text_for_yes'] = isset($input['text_for_yes']) ? sanitize_text_field($input['text_for_yes']) : 'Yes';
        $new_input['text_for_no'] = isset($input['text_for_no']) ? sanitize_text_field($input['text_for_no']) : 'No';
        
        // Time and Date format options
        $time_formats = ['g:i a', 'g:i A', 'H:i', 'h:i a', 'h:i A', 'g:i:s a', 'g:i:s A', 'H:i:s'];
        $new_input['time_format'] = isset($input['time_format']) && in_array($input['time_format'], $time_formats) ? $input['time_format'] : 'g:i A';
        
        $date_formats = ['F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'M j, Y', 'j F Y', 'l, F j, Y', 'D, M j, Y'];
        $new_input['date_format'] = isset($input['date_format']) && in_array($input['date_format'], $date_formats) ? $input['date_format'] : 'F j, Y';
        
        return $new_input;
    }

    // Section callbacks
    public function caching_section_callback()
    {
        echo '<p>Enable and configure server-side caching to reduce API requests and improve performance.</p>';
    }

    public function api_section_callback()
    {
        echo '<p>Configure settings for the ip-api.com service.</p>';
    }

    public function formatting_section_callback()
    {
        echo '<p>Customize the text output for boolean values from shortcodes like <code>[userip_location type="mobile"]</code>, and configure time/date formats for <code>[userip_localtime]</code> and <code>[userip_localdate]</code> shortcodes.</p>';
    }

    // Field callbacks
    public function enable_cache_callback()
    {
        $options = get_option('user_ip_location_options', ['enable_cache' => 0]);
        $checked = isset($options['enable_cache']) && $options['enable_cache'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="user_ip_location_options[enable_cache]" value="1" ' . $checked . ' /> Enable to cache API results in the database.</label>';
    }

    public function cache_expiration_callback()
    {
        $options = get_option('user_ip_location_options', ['cache_expiration' => 3600]);
        $expiration = $options['cache_expiration'] ?? 3600;
        $expirations = ['3600' => '1 Hour', '21600' => '6 Hours', '86400' => '1 Day', '604800' => '1 Week'];
        echo '<select name="user_ip_location_options[cache_expiration]">';
        foreach ($expirations as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($expiration, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function api_key_callback()
    {
        $options = get_option('user_ip_location_options', ['api_key' => '']);
        $api_key = $options['api_key'] ?? '';
        echo '<input type="text" name="user_ip_location_options[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="Optional API Key" />';
        echo '<p class="description">For users of the <a href="https://ip-api.com/pro" target="_blank">Pro service</a>.</p>';
    }

    public function api_lang_callback()
    {
        $options = get_option('user_ip_location_options', ['api_lang' => 'en']);
        $current_lang = $options['api_lang'] ?? 'en';
        $languages = ['en' => 'English', 'de' => 'Deutsch (German)', 'es' => 'Español (Spanish)', 'pt-BR' => 'Português - Brasil (Portuguese)', 'fr' => 'Français (French)', 'ja' => '日本語 (Japanese)', 'zh-CN' => '中国 (Chinese)', 'ru' => 'Русский (Russian)'];
        echo '<select name="user_ip_location_options[api_lang]">';
        foreach ($languages as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($current_lang, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Select the language for country, region, and city names.</p>';
    }

    public function text_for_yes_callback()
    {
        $options = get_option('user_ip_location_options', ['text_for_yes' => 'Yes']);
        $text = $options['text_for_yes'] ?? 'Yes';
        echo '<input type="text" name="user_ip_location_options[text_for_yes]" value="' . esc_attr($text) . '" class="regular-text" />';
    }

    public function text_for_no_callback()
    {
        $options = get_option('user_ip_location_options', ['text_for_no' => 'No']);
        $text = $options['text_for_no'] ?? 'No';
        echo '<input type="text" name="user_ip_location_options[text_for_no]" value="' . esc_attr($text) . '" class="regular-text" />';
    }

    public function time_format_callback()
    {
        $options = get_option('user_ip_location_options', ['time_format' => 'g:i A']);
        $current_format = $options['time_format'] ?? 'g:i A';
        
        $time_formats = [
            'g:i a' => '12-hour with lowercase am/pm (e.g., 3:30 pm)',
            'g:i A' => '12-hour with uppercase AM/PM (e.g., 3:30 PM)',
            'H:i' => '24-hour format (e.g., 15:30)',
            'h:i a' => '12-hour with leading zeros and lowercase am/pm (e.g., 03:30 pm)',
            'h:i A' => '12-hour with leading zeros and uppercase AM/PM (e.g., 03:30 PM)',
            'g:i:s a' => '12-hour with seconds and lowercase am/pm (e.g., 3:30:45 pm)',
            'g:i:s A' => '12-hour with seconds and uppercase AM/PM (e.g., 3:30:45 PM)',
            'H:i:s' => '24-hour with seconds (e.g., 15:30:45)'
        ];
        
        echo '<select name="user_ip_location_options[time_format]" id="time_format_select">';
        foreach ($time_formats as $format => $description) {
            echo '<option value="' . esc_attr($format) . '"' . selected($current_format, $format, false) . '>' . esc_html($description) . '</option>';
        }
        echo '</select>';
        
        // Add preview
        $current_time = date($current_format);
        echo '<p class="description">Preview: <strong><span id="time_preview">' . esc_html($current_time) . '</span></strong></p>';
        
        // Add JavaScript for live preview
        echo '<script>
        document.getElementById("time_format_select").addEventListener("change", function() {
            var format = this.value;
            var preview = document.getElementById("time_preview");
            
            // Create a mapping of PHP date formats to JavaScript equivalents for preview
            var formatMap = {
                "g:i a": "h:mm a",
                "g:i A": "h:mm A", 
                "H:i": "HH:mm",
                "h:i a": "hh:mm a",
                "h:i A": "hh:mm A",
                "g:i:s a": "h:mm:ss a",
                "g:i:s A": "h:mm:ss A",
                "H:i:s": "HH:mm:ss"
            };
            
            var now = new Date();
            var timeString = "";
            
            switch(format) {
                case "g:i a":
                case "g:i A":
                    var hours = now.getHours();
                    var minutes = now.getMinutes();
                    var ampm = hours >= 12 ? (format.includes("A") ? "PM" : "pm") : (format.includes("A") ? "AM" : "am");
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    timeString = hours + ":" + (minutes < 10 ? "0" + minutes : minutes) + " " + ampm;
                    break;
                case "H:i":
                    timeString = (now.getHours() < 10 ? "0" + now.getHours() : now.getHours()) + ":" + (now.getMinutes() < 10 ? "0" + now.getMinutes() : now.getMinutes());
                    break;
                case "h:i a":
                case "h:i A":
                    var hours = now.getHours();
                    var minutes = now.getMinutes();
                    var ampm = hours >= 12 ? (format.includes("A") ? "PM" : "pm") : (format.includes("A") ? "AM" : "am");
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    timeString = (hours < 10 ? "0" + hours : hours) + ":" + (minutes < 10 ? "0" + minutes : minutes) + " " + ampm;
                    break;
                case "g:i:s a":
                case "g:i:s A":
                    var hours = now.getHours();
                    var minutes = now.getMinutes();
                    var seconds = now.getSeconds();
                    var ampm = hours >= 12 ? (format.includes("A") ? "PM" : "pm") : (format.includes("A") ? "AM" : "am");
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    timeString = hours + ":" + (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds < 10 ? "0" + seconds : seconds) + " " + ampm;
                    break;
                case "H:i:s":
                    timeString = (now.getHours() < 10 ? "0" + now.getHours() : now.getHours()) + ":" + (now.getMinutes() < 10 ? "0" + now.getMinutes() : now.getMinutes()) + ":" + (now.getSeconds() < 10 ? "0" + now.getSeconds() : now.getSeconds());
                    break;
            }
            
            preview.textContent = timeString;
        });
        </script>';
    }

    public function date_format_callback()
    {
        $options = get_option('user_ip_location_options', ['date_format' => 'F j, Y']);
        $current_format = $options['date_format'] ?? 'F j, Y';
        
        $date_formats = [
            'F j, Y' => 'Full month name with day and year (e.g., January 15, 2025)',
            'Y-m-d' => 'Year-month-day format (e.g., 2025-01-15)',
            'm/d/Y' => 'US format: month/day/year (e.g., 01/15/2025)',
            'd/m/Y' => 'European format: day/month/year (e.g., 15/01/2025)',
            'M j, Y' => 'Short month name with day and year (e.g., Jan 15, 2025)',
            'j F Y' => 'Day, full month name, year (e.g., 15 January 2025)',
            'l, F j, Y' => 'Full day name, month, day, year (e.g., Wednesday, January 15, 2025)',
            'D, M j, Y' => 'Short day name, month, day, year (e.g., Wed, Jan 15, 2025)'
        ];
        
        echo '<select name="user_ip_location_options[date_format]" id="date_format_select">';
        foreach ($date_formats as $format => $description) {
            echo '<option value="' . esc_attr($format) . '"' . selected($current_format, $format, false) . '>' . esc_html($description) . '</option>';
        }
        echo '</select>';
        
        // Add preview
        $current_date = date($current_format);
        echo '<p class="description">Preview: <strong><span id="date_preview">' . esc_html($current_date) . '</span></strong></p>';
        
        // Add JavaScript for live preview
        echo '<script>
        document.getElementById("date_format_select").addEventListener("change", function() {
            var format = this.value;
            var preview = document.getElementById("date_preview");
            var now = new Date();
            var dateString = "";
            
            var months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            var monthsShort = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            var days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
            var daysShort = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
            
            switch(format) {
                case "F j, Y":
                    dateString = months[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
                    break;
                case "Y-m-d":
                    dateString = now.getFullYear() + "-" + (now.getMonth() + 1 < 10 ? "0" + (now.getMonth() + 1) : now.getMonth() + 1) + "-" + (now.getDate() < 10 ? "0" + now.getDate() : now.getDate());
                    break;
                case "m/d/Y":
                    dateString = (now.getMonth() + 1 < 10 ? "0" + (now.getMonth() + 1) : now.getMonth() + 1) + "/" + (now.getDate() < 10 ? "0" + now.getDate() : now.getDate()) + "/" + now.getFullYear();
                    break;
                case "d/m/Y":
                    dateString = (now.getDate() < 10 ? "0" + now.getDate() : now.getDate()) + "/" + (now.getMonth() + 1 < 10 ? "0" + (now.getMonth() + 1) : now.getMonth() + 1) + "/" + now.getFullYear();
                    break;
                case "M j, Y":
                    dateString = monthsShort[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
                    break;
                case "j F Y":
                    dateString = now.getDate() + " " + months[now.getMonth()] + " " + now.getFullYear();
                    break;
                case "l, F j, Y":
                    dateString = days[now.getDay()] + ", " + months[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
                    break;
                case "D, M j, Y":
                    dateString = daysShort[now.getDay()] + ", " + monthsShort[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
                    break;
            }
            
            preview.textContent = dateString;
        });
        </script>';
    }

    /**
     * Renders the main settings page with tabbed navigation.
     */
    public function render_settings_page()
    {
        if (isset($_GET['action'], $_GET['_wpnonce']) && $_GET['action'] === 'clear_cache' && wp_verify_nonce($_GET['_wpnonce'], 'user_ip_clear_cache')) {
            global $wpdb;
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_user_ip_location_%', '_transient_timeout_user_ip_location_%'));
            add_settings_error('user_ip_location_notices', 'cache_cleared', 'API cache cleared successfully.', 'updated');
        }
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('user_ip_location_notices'); ?>

            <?php $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings'; ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=user-ip-and-location&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=user-ip-and-location&tab=shortcodes" class="nav-tab <?php echo $active_tab == 'shortcodes' ? 'nav-tab-active' : ''; ?>">Shortcodes Guide</a>
                <a href="?page=user-ip-and-location&tab=your_info" class="nav-tab <?php echo $active_tab == 'your_info' ? 'nav-tab-active' : ''; ?>">Your Info</a>
                <a href="?page=user-ip-and-location&tab=developer" class="nav-tab <?php echo $active_tab == 'developer' ? 'nav-tab-active' : ''; ?>">For Developers</a>
            </h2>

            <?php
            switch ($active_tab) {
                case 'your_info':
                    $this->render_your_info_tab();
                    break;
                case 'developer':
                    $this->render_developer_tab();
                    break;
                case 'shortcodes':
                    $this->render_shortcodes_tab();
                    break;
                default:
                    $this->render_settings_tab();
                    break;
            }
            ?>
        </div>
    <?php
    }

    private function render_settings_tab()
    {
    ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('user_ip_location_settings');
            do_settings_sections('user_ip_location_settings');
            submit_button('Save Settings');
            ?>
        </form>
        <hr />
        <h2>Clear API Cache</h2>
        <p>If you are testing or notice stale location data, you can manually clear the cache.</p>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=user-ip-and-location&action=clear_cache'), 'user_ip_clear_cache')); ?>" class="button button-secondary">Clear Cache Now</a>
    <?php
    }

    private function render_your_info_tab()
    {
        $ip_info = User_IP_and_Location::get_instance();
    ?>
        <h2><span class="dashicons dashicons-location-alt" style="vertical-align: middle;"></span> Your Information</h2>
        <p>This is the information we've detected for your current connection.</p>
        <table class="form-table user-ip-info-table" style="width: auto;">
            <tr>
                <th scope="row">IP Address:</th>
                <td><?php echo esc_html($ip_info->getIP()); ?></td>
            </tr>
            <tr>
                <th scope="row">Location:</th>
                <td><?php echo esc_html(implode(', ', array_filter([$ip_info->getCity(), $ip_info->getRegionName(), $ip_info->getCountry()]))); ?></td>
            </tr>
            <tr>
                <th scope="row">ISP:</th>
                <td><?php echo esc_html($ip_info->getISP()); ?></td>
            </tr>
            <tr>
                <th scope="row">Coordinates:</th>
                <td><?php echo esc_html($ip_info->getLat() . ', ' . $ip_info->getLon()); ?></td>
            </tr>
            <tr>
                <th scope="row">User Agent:</th>
                <td><?php echo esc_html($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'); ?></td>
            </tr>
        </table>
    <?php
    }

    private function render_developer_tab()
    {
    ?>
        <h2><span class="dashicons dashicons-embed-php" style="vertical-align: middle;"></span> Developer Tools</h2>
        <p>You can use the following tools to integrate the plugin's data into your own themes and plugins.</p>

        <h3>Global PHP Function</h3>
        <p>Use the global function <code>get_user_ip_data()</code> to get an array of all available location data for the current visitor.</p>
        <p><strong>Example Usage:</strong></p>
        <pre style="background:#f1f1f1; padding: 15px;">&lt;?php
if ( function_exists( 'get_user_ip_data' ) ) {
    $location = get_user_ip_data();
    if ( $location && $location['countryCode'] === 'US' ) {
        echo 'Welcome, visitor from the United States!';
    }
}
?&gt;</pre>
        <p>The function returns an associative array on success or <code>null</code> if the API call fails.</p>

        <hr>

        <h3>REST API Endpoint</h3>
        <p>The plugin provides a REST API endpoint to fetch the visitor's location data. This is useful for headless WordPress setups or for fetching data with JavaScript.</p>
        <p><strong>Note:</strong> This endpoint requires authentication and is only accessible to users with the <code>manage_options</code> capability (administrators).</p>
        <p><strong>Endpoint URL:</strong></p>
        <pre style="background:#f1f1f1; padding: 15px;"><?php echo esc_url(get_rest_url(null, 'user-ip/v1/location')); ?></pre>
        <p>Sending a <code>GET</code> request to this URL will return a JSON object containing the same data as the global PHP function above.</p>
    <?php
    }

    private function render_shortcodes_tab()
    {
    ?>
        <h2><span class="dashicons dashicons-editor-code" style="vertical-align: middle;"></span> Shortcodes Guide</h2>
        <p>Use the following shortcodes to display user information in your posts and pages.</p>
        <hr>
        <h3>Display Visitor Data</h3>
        <ul style="list-style-type: disc; padding-inline-start: 40px;">
            <li><strong>Display IP:</strong> <code>[userip_location type="ip"]</code></li>
            <li><strong>Display Continent Name:</strong> <code>[userip_location type="continent"]</code></li>
            <li><strong>Display Country Name:</strong> <code>[userip_location type="country"]</code></li>
            <li><strong>Display Country Code:</strong> <code>[userip_location type="countrycode"]</code></li>
            <li><strong>Display Region:</strong> <code>[userip_location type="region"]</code></li>
            <li><strong>Display Region Name:</strong> <code>[userip_location type="regionname"]</code></li>
            <li><strong>Display City:</strong> <code>[userip_location type="city"]</code></li>
            <li><strong>Display ZIP/Postal Code:</strong> <code>[userip_location type="zip"]</code></li>
            <li><strong>Display Latitude:</strong> <code>[userip_location type="lat"]</code></li>
            <li><strong>Display Longitude:</strong> <code>[userip_location type="lon"]</code></li>
            <li><strong>Display Timezone:</strong> <code>[userip_location type="timezone"]</code></li>
            <li><strong>Display Currency:</strong> <code>[userip_location type="currency"]</code></li>
            <li><strong>Display ISP Information:</strong> <code>[userip_location type="isp"]</code></li>
            <li><strong>Is Mobile Connection (Returns "Yes" or "No"):</strong> <code>[userip_location type="mobile"]</code></li>
            <li><strong>Is Proxy (Returns "Yes" or "No"):</strong> <code>[userip_location type="proxy"]</code></li>
            <li><strong>Is Hosting Provider (Returns "Yes" or "No"):</strong> <code>[userip_location type="hosting"]</code></li>
            <li><strong>Display Browser Name:</strong> <code>[userip_location type="browser"]</code></li>
            <li><strong>Display Operating System:</strong> <code>[userip_location type="os"]</code></li>
            <li><strong>Display Country Flag:</strong> <code>[userip_location type="flag" height="auto" width="50px"]</code></li>
        </ul>

        <hr>
        <h3>Time & Date Shortcodes</h3>
        <ul style="list-style-type: disc; padding-inline-start: 40px;">
            <li><strong>Display Local Time:</strong> <code>[userip_localtime]</code></li>
            <li><strong>Display Local Date:</strong> <code>[userip_localdate]</code></li>
        </ul>
        <p class="description"><strong>Note:</strong> Time and date formats can be customized in the Settings tab under "Output Formatting".</p>

        <hr>
        <h3>Conditional Content</h3>
        <p>Show or hide content based on the visitor's location. You can combine multiple attributes in a single shortcode.</p>
        <ul style="list-style-type: disc; padding-inline-start: 40px;">
            <li><strong>Show content to specific countries:</strong><br><code>[userip_conditional country="US,CA"]Welcome, North American visitors![/userip_conditional]</code></li>
            <li><strong>Show content to everyone EXCEPT visitors from specific countries:</strong><br><code>[userip_conditional country_not="CN,RU"]Content for visitors outside China and Russia.[/userip_conditional]</code></li>
            <li><strong>Show content to a specific region (state):</strong><br><code>[userip_conditional region="TX"]Howdy, Texan![/userip_conditional]</code></li>
            <li><strong>Show content to a specific city:</strong><br><code>[userip_conditional city="London"]Special offer for Londoners![/userip_conditional]</code></li>
        </ul>
        <p><strong>Available Attributes:</strong> <code>country</code>, <code>country_not</code>, <code>region</code>, <code>region_not</code>, <code>city</code>, <code>city_not</code>. All values are case-insensitive. Use 2-letter country and region codes.</p>

        <hr>
        <p>For support and queries, you can visit our <a href="https://wordpress.org/plugins/user-ip-and-location/" target="_blank">WordPress.org plugin page here</a>.</p>
<?php
    }
} 