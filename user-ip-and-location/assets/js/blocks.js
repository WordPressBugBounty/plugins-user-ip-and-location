/**
 * Editor scripts for the User IP and Location blocks.
 * Plain JS (no JSX / build step). Both blocks are dynamic — the value is
 * rendered on the server by reusing the plugin's shortcodes, so the editor
 * only shows a labelled preview.
 */
(function (blocks, element, blockEditor, components, i18n, hooks) {
    'use strict';

    var el = element.createElement;
    var __ = i18n.__;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var InnerBlocks = blockEditor.InnerBlocks;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;

    // Field options come from PHP (see Fields::for_editor) via wp_localize_script,
    // so the list is defined once. A tiny fallback keeps the editor usable if the
    // localized data is ever missing.
    var localized = (window.userIpLocationBlocks && window.userIpLocationBlocks.fields) || [];
    var FIELDS = localized.length ? localized : [
        { label: __('IP address', 'user-ip-and-location'), value: 'ip' },
        { label: __('Country', 'user-ip-and-location'), value: 'country' },
        { label: __('City', 'user-ip-and-location'), value: 'city' },
        { label: __('Country flag', 'user-ip-and-location'), value: 'flag' }
    ];

    var RENDER_MODES = [
        { label: __('Default (site setting)', 'user-ip-and-location'), value: '' },
        { label: __('AJAX (cache-friendly)', 'user-ip-and-location'), value: 'true' },
        { label: __('Server-side (forms, RSS, no-JS)', 'user-ip-and-location'), value: 'false' }
    ];

    function fieldLabel(value) {
        for (var i = 0; i < FIELDS.length; i++) {
            if (FIELDS[i].value === value) {
                return FIELDS[i].label;
            }
        }
        return value;
    }

    // --- Visitor Info -----------------------------------------------------
    registerBlockType('user-ip-location/visitor-info', {
        apiVersion: 2,
        title: __('Visitor Info', 'user-ip-and-location'),
        description: __('Display a single piece of the visitor’s IP or location data.', 'user-ip-and-location'),
        icon: 'location-alt',
        category: 'widgets',
        keywords: ['ip', 'location', 'geo', 'country', 'visitor'],
        attributes: {
            type: { type: 'string', default: 'ip' },
            ajax: { type: 'string', default: '' },
            height: { type: 'string', default: 'auto' },
            width: { type: 'string', default: '50px' },
            vertical_align: { type: 'string', default: 'middle' }
        },
        edit: function (props) {
            var a = props.attributes;
            var set = props.setAttributes;
            var blockProps = useBlockProps({ className: 'uipl-block' });
            var isFlag = a.type === 'flag';

            var controls = [
                el(SelectControl, {
                    key: 'field',
                    label: __('Field', 'user-ip-and-location'),
                    value: a.type,
                    options: FIELDS,
                    onChange: function (v) { set({ type: v }); }
                }),
                el(SelectControl, {
                    key: 'mode',
                    label: __('Render mode', 'user-ip-and-location'),
                    value: a.ajax,
                    options: RENDER_MODES,
                    onChange: function (v) { set({ ajax: v }); }
                })
            ];

            if (isFlag) {
                controls.push(el(TextControl, {
                    key: 'width',
                    label: __('Flag width', 'user-ip-and-location'),
                    value: a.width,
                    onChange: function (v) { set({ width: v }); }
                }));
                controls.push(el(TextControl, {
                    key: 'height',
                    label: __('Flag height', 'user-ip-and-location'),
                    value: a.height,
                    onChange: function (v) { set({ height: v }); }
                }));
            }

            return el(
                'div',
                blockProps,
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Settings', 'user-ip-and-location'), initialOpen: true }, controls)
                ),
                el('span', { className: 'uipl-block__chip', key: 'chip' },
                    el('span', { className: 'uipl-block__mark' }, '⌖'),
                    ' ',
                    fieldLabel(a.type)
                )
            );
        },
        save: function () {
            return null; // Dynamic: rendered server-side.
        }
    });

    // --- Conditional Content ---------------------------------------------
    var CONDITIONS = [
        { key: 'country', label: __('Country is', 'user-ip-and-location') },
        { key: 'country_not', label: __('Country is NOT', 'user-ip-and-location') },
        { key: 'region', label: __('Region is', 'user-ip-and-location') },
        { key: 'region_not', label: __('Region is NOT', 'user-ip-and-location') },
        { key: 'city', label: __('City is', 'user-ip-and-location') },
        { key: 'city_not', label: __('City is NOT', 'user-ip-and-location') }
    ];

    function conditionSummary(a) {
        var parts = [];
        for (var i = 0; i < CONDITIONS.length; i++) {
            var k = CONDITIONS[i].key;
            if (a[k]) {
                parts.push(CONDITIONS[i].label + ' ' + a[k]);
            }
        }
        return parts.length
            ? __('Shown when: ', 'user-ip-and-location') + parts.join(' · ')
            : __('Always shown — set a condition in the sidebar', 'user-ip-and-location');
    }

    registerBlockType('user-ip-location/conditional', {
        apiVersion: 2,
        title: __('Conditional Content', 'user-ip-and-location'),
        description: __('Show the inner content only to visitors from the locations you choose.', 'user-ip-and-location'),
        icon: 'visibility',
        category: 'widgets',
        keywords: ['geo', 'conditional', 'country', 'location', 'target'],
        attributes: {
            country: { type: 'string', default: '' },
            country_not: { type: 'string', default: '' },
            region: { type: 'string', default: '' },
            region_not: { type: 'string', default: '' },
            city: { type: 'string', default: '' },
            city_not: { type: 'string', default: '' },
            ajax: { type: 'string', default: '' }
        },
        edit: function (props) {
            var a = props.attributes;
            var set = props.setAttributes;
            var blockProps = useBlockProps({ className: 'uipl-block-conditional' });

            var fields = CONDITIONS.map(function (c) {
                return el(TextControl, {
                    key: c.key,
                    label: c.label,
                    value: a[c.key],
                    placeholder: __('e.g. US, CA', 'user-ip-and-location'),
                    onChange: function (v) {
                        var patch = {};
                        patch[c.key] = v;
                        set(patch);
                    }
                });
            });

            fields.push(el(SelectControl, {
                key: 'mode',
                label: __('Render mode', 'user-ip-and-location'),
                value: a.ajax,
                options: RENDER_MODES,
                onChange: function (v) { set({ ajax: v }); }
            }));

            return el(
                'div',
                blockProps,
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Show when…', 'user-ip-and-location'), initialOpen: true },
                        el('p', { className: 'uipl-block__hint' }, __('Use 2-letter country/region codes. Separate multiple values with commas. Values are case-insensitive.', 'user-ip-and-location')),
                        fields
                    )
                ),
                el('div', { className: 'uipl-block-conditional__head', key: 'head' }, conditionSummary(a)),
                el('div', { className: 'uipl-block-conditional__body', key: 'body' },
                    el(InnerBlocks, { templateLock: false })
                )
            );
        },
        save: function () {
            return el(InnerBlocks.Content);
        }
    });

    // --- Inline autocompleter: type "{" in a paragraph to insert a value ---
    function tokenShortcode(value) {
        if (value === 'localtime') {
            return '[userip_localtime]';
        }
        if (value === 'localdate') {
            return '[userip_localdate]';
        }
        return '[userip_location type="' + value + '"]';
    }

    var visitorCompleter = {
        name: 'user-ip-location/visitor',
        triggerPrefix: '{',
        options: FIELDS,
        getOptionKeywords: function (option) {
            return [option.value, option.label];
        },
        getOptionLabel: function (option) {
            return el('span', null,
                el('span', { className: 'uipl-token__mark', style: { color: '#5b61e8' } }, '⌖'),
                ' ',
                option.label
            );
        },
        getOptionCompletion: function (option) {
            return tokenShortcode(option.value);
        }
    };

    if (hooks && hooks.addFilter) {
        hooks.addFilter(
            'editor.Autocomplete.completers',
            'user-ip-location/autocompleter',
            function (completers, blockName) {
                if (blockName === 'core/paragraph' || blockName === 'core/heading' || blockName === 'core/list-item') {
                    return completers.concat([visitorCompleter]);
                }
                return completers;
            }
        );
    }
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n,
    window.wp.hooks
);
