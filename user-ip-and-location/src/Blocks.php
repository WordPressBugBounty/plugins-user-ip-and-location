<?php
/**
 * Registers the editor blocks. Both blocks are dynamic: their server render
 * reuses the Shortcodes logic, so they inherit AJAX/server-side mode, caching,
 * and escaping with no duplicated behaviour.
 */

namespace UserIPLocation;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('UserIPLocation\\Blocks')) {

    class Blocks
    {
        /**
         * Shared shortcodes instance (so block + shortcode enqueue is deduped).
         *
         * @var Shortcodes
         */
        private $shortcodes;

        /**
         * @param Shortcodes $shortcodes
         */
        public function __construct(Shortcodes $shortcodes)
        {
            $this->shortcodes = $shortcodes;
        }

        /**
         * Hook into WordPress.
         *
         * @return void
         */
        public function register()
        {
            add_action('init', array($this, 'register_blocks'));
        }

        /**
         * Register the editor assets and both block types.
         *
         * @return void
         */
        public function register_blocks()
        {
            // Block editor isn't available before WP 5.0 / Gutenberg.
            if (!function_exists('register_block_type')) {
                return;
            }

            wp_register_script(
                'user-ip-location-blocks',
                USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/js/blocks.js',
                array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-hooks'),
                USER_IP_AND_LOCATION_VERSION,
                true
            );

            // Make the block editor JS strings translatable.
            if (function_exists('wp_set_script_translations')) {
                wp_set_script_translations(
                    'user-ip-location-blocks',
                    'user-ip-and-location',
                    USER_IP_AND_LOCATION_PLUGIN_PATH . 'languages'
                );
            }

            // Single source of truth for the field list (see Fields).
            wp_localize_script('user-ip-location-blocks', 'userIpLocationBlocks', array(
                'fields' => Fields::for_editor(),
            ));

            wp_register_style(
                'user-ip-location-blocks-editor',
                USER_IP_AND_LOCATION_PLUGIN_URL . 'assets/css/blocks-editor.css',
                array(),
                USER_IP_AND_LOCATION_VERSION
            );

            register_block_type('user-ip-location/visitor-info', array(
                'editor_script'   => 'user-ip-location-blocks',
                'editor_style'    => 'user-ip-location-blocks-editor',
                'render_callback' => array($this, 'render_visitor_info'),
                'attributes'      => array(
                    'type'           => array('type' => 'string', 'default' => 'ip'),
                    'ajax'           => array('type' => 'string', 'default' => ''),
                    'height'         => array('type' => 'string', 'default' => 'auto'),
                    'width'          => array('type' => 'string', 'default' => '50px'),
                    'vertical_align' => array('type' => 'string', 'default' => 'middle'),
                ),
            ));

            register_block_type('user-ip-location/conditional', array(
                'editor_script'   => 'user-ip-location-blocks',
                'editor_style'    => 'user-ip-location-blocks-editor',
                'render_callback' => array($this, 'render_conditional'),
                'attributes'      => array(
                    'country'     => array('type' => 'string', 'default' => ''),
                    'country_not' => array('type' => 'string', 'default' => ''),
                    'region'      => array('type' => 'string', 'default' => ''),
                    'region_not'  => array('type' => 'string', 'default' => ''),
                    'city'        => array('type' => 'string', 'default' => ''),
                    'city_not'    => array('type' => 'string', 'default' => ''),
                    'ajax'        => array('type' => 'string', 'default' => ''),
                ),
            ));
        }

        /**
         * Render the Visitor Info block by delegating to the matching shortcode.
         *
         * @param array $attributes
         * @return string
         */
        public function render_visitor_info($attributes = array())
        {
            $type = isset($attributes['type']) ? sanitize_key($attributes['type']) : 'ip';
            $ajax = isset($attributes['ajax']) ? $attributes['ajax'] : '';

            if ($type === 'localtime') {
                return $this->shortcodes->localtime(array('ajax' => $ajax));
            }
            if ($type === 'localdate') {
                return $this->shortcodes->localdate(array('ajax' => $ajax));
            }

            return $this->shortcodes->location(array(
                'type'           => $type,
                'height'         => isset($attributes['height']) ? $attributes['height'] : 'auto',
                'width'          => isset($attributes['width']) ? $attributes['width'] : '50px',
                'vertical_align' => isset($attributes['vertical_align']) ? $attributes['vertical_align'] : 'middle',
                'ajax'           => $ajax,
            ));
        }

        /**
         * Render the Conditional Content block by delegating to the shortcode.
         *
         * @param array  $attributes
         * @param string $content Inner blocks markup.
         * @return string
         */
        public function render_conditional($attributes, $content = '')
        {
            if (trim((string) $content) === '') {
                return '';
            }

            $atts = array();
            $keys = array('country', 'country_not', 'region', 'region_not', 'city', 'city_not');
            foreach ($keys as $key) {
                if (!empty($attributes[$key])) {
                    $atts[$key] = $attributes[$key];
                }
            }
            if (isset($attributes['ajax']) && $attributes['ajax'] !== '') {
                $atts['ajax'] = $attributes['ajax'];
            }

            // No conditions set: just show the content.
            if (empty($atts) || (count($atts) === 1 && isset($atts['ajax']))) {
                return $content;
            }

            return $this->shortcodes->conditional($atts, $content);
        }
    }
}
