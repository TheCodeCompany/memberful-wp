(function() {
    'use strict';

    if (typeof wp === 'undefined') {
        return;
    }

    function isBlockProtectable(blockName) {
        // Get all registered blocks dynamically
        const blockRegistry = wp.blocks.getBlockTypes();
        const block = blockRegistry.find(block => block.name === blockName);
        
        if (!block) {
            return false;
        }
        
        // Exclude certain block types that don't make sense to protect
        const nonProtectableBlocks = [
            'core/button',
            'core/buttons', 
            'core/separator',
            'core/spacer',
            'core/more',
            'core/nextpage',
            'core/block',
            'core/legacy-widget',
            'core/widget-group',
            'core/navigation',
            'core/navigation-link',
            'core/navigation-submenu',
            'core/site-logo',
            'core/site-title',
            'core/site-tagline',
            'core/loginout',
            'core/home-link'
        ];

        let isProtectable = !nonProtectableBlocks.includes(blockName);
        
        // Allow developers to filter which blocks are protectable
        if (typeof wp.hooks !== 'undefined' && wp.hooks.applyFilters) {
            isProtectable = wp.hooks.applyFilters('memberful.isBlockProtectable', isProtectable, blockName, block);
        }
        
        return isProtectable;
    }

    // Add protection attributes to blocks
    wp.hooks.addFilter(
        'blocks.registerBlockType',
        'memberful/add-protection-attributes',
        function(settings, name) {
            console.log('Registering block type:', name);
            if (isBlockProtectable(name)) {
                if (!settings.attributes) {
                    settings.attributes = {};
                }
                
                settings.attributes.memberfulProtection = {
                    type: 'object',
                    default: {
                        enabled: false,
                        accessType: 'subscription',
                        requiredItems: [],
                        unauthorizedAction: 'hide'
                    }
                };
                
                console.log('Added memberfulProtection to block:', name);
                console.log('Block settings after adding attributes:', settings);
                
            }
            
            return settings;
        }
    );

    // Add Memberful protection panel to the right sidebar
    wp.hooks.addFilter(
        'editor.BlockEdit',
        'memberful/add-sidebar-panel',
        function(BlockEdit) {
            return function(props) {
                if (!isBlockProtectable(props.name)) {
                    return wp.element.createElement(BlockEdit, props);
                }

                const { attributes, setAttributes } = props;
                const protectionSettings = {
                    enabled: false,
                    accessType: 'subscription',
                    requiredItems: [],
                    unauthorizedAction: 'hide',
                    ...attributes.memberfulProtection
                };
                
                

                return wp.element.createElement(
                    wp.element.Fragment,
                    null,
                    wp.element.createElement(BlockEdit, props),
                    wp.element.createElement(
                        wp.blockEditor.InspectorControls,
                        null,
                        wp.element.createElement(
                            wp.components.PanelBody,
                            { 
                                title: 'Memberful Protection',
                                initialOpen: false,
                                icon: 'lock'
                            },
                            wp.element.createElement(
                                wp.components.ToggleControl,
                                {
                                    label: 'Enable Protection',
                                    checked: protectionSettings.enabled || false,
                                    onChange: function(value) {
                                        const newSettings = {
                                            ...protectionSettings,
                                            enabled: value
                                        };
                                        console.log('Saving memberfulProtection:', newSettings);
                                        console.log('Current attributes before save:', attributes);
                                        setAttributes({
                                            memberfulProtection: newSettings
                                        });
                                        console.log('Attributes set, checking if saved...');
                                        console.log('New attributes after save:', attributes);
                                    }
                                }
                            ),
                            protectionSettings.enabled && wp.element.createElement(
                                wp.element.Fragment,
                                null,
                                wp.element.createElement(
                                    wp.components.SelectControl,
                                    {
                                        label: 'Access Type',
                                        value: protectionSettings.accessType || 'subscription',
                                        options: [
                                            { value: 'registered', label: 'All Members' },
                                            { value: 'subscription', label: 'All Active Subscribers' },
                                            { value: 'product', label: 'Product Purchasers' }
                                        ],
                                        onChange: function(value) {
                                            setAttributes({
                                                memberfulProtection: {
                                                    ...protectionSettings,
                                                    accessType: value
                                                }
                                            });
                                        }
                                    }
                                ),
                                wp.element.createElement(
                                    wp.components.SelectControl,
                                    {
                                        label: 'Unauthorized Action',
                                        value: protectionSettings.unauthorizedAction || 'hide',
                                        options: [
                                            { label: 'Hide Block', value: 'hide' },
                                            { label: 'Show Message', value: 'message' },
                                            { label: 'Show Login Form', value: 'login_form' }
                                        ],
                                        onChange: function(value) {
                                            setAttributes({
                                                memberfulProtection: {
                                                    ...protectionSettings,
                                                    unauthorizedAction: value
                                                }
                                            });
                                        }
                                    }
                                ),
                                (protectionSettings.accessType === 'subscription' || protectionSettings.accessType === 'product') && wp.element.createElement(
                                    wp.components.CheckboxControl,
                                    {
                                        label: 'Require Specific Plans/Products',
                                        checked: (protectionSettings.requiredItems && protectionSettings.requiredItems.length > 0) || false,
                                        onChange: function(value) {
                                            setAttributes({
                                                memberfulProtection: {
                                                    ...protectionSettings,
                                                    requiredItems: value ? [''] : []
                                                }
                                            });
                                        }
                                    }
                                ),
                                (protectionSettings.accessType === 'subscription' && protectionSettings.requiredItems && protectionSettings.requiredItems.length > 0) && (function() {
                                    var subscriptions = [];
                                    if (typeof memberfulBlockProtection !== 'undefined' && memberfulBlockProtection.subscriptions) {
                                        subscriptions = memberfulBlockProtection.subscriptions;
                                    }
                                    
                                    return wp.element.createElement(
                                        'div',
                                        { style: { marginBottom: '10px' } },
                                        subscriptions.map(function(sub) {
                                            var isChecked = protectionSettings.requiredItems.indexOf(sub.id) !== -1;
                                            return wp.element.createElement(
                                                wp.components.CheckboxControl,
                                                {
                                                    key: sub.id,
                                                    label: sub.name,
                                                    checked: isChecked,
                                                    onChange: function(checked) {
                                                        var newItems = protectionSettings.requiredItems.slice();
                                                        if (checked) {
                                                            if (newItems.indexOf(sub.id) === -1) {
                                                                newItems.push(sub.id);
                                                            }
                                                        } else {
                                                            var index = newItems.indexOf(sub.id);
                                                            if (index !== -1) {
                                                                newItems.splice(index, 1);
                                                            }
                                                        }
                                                        setAttributes({
                                                            memberfulProtection: {
                                                                ...protectionSettings,
                                                                requiredItems: newItems
                                                            }
                                                        });
                                                    }
                                                }
                                            );
                                        })
                                    );
                                })(),
                                (protectionSettings.accessType === 'product' && protectionSettings.requiredItems && protectionSettings.requiredItems.length > 0) && (function() {
                                    var products = [];
                                    if (typeof memberfulBlockProtection !== 'undefined' && memberfulBlockProtection.products) {
                                        products = memberfulBlockProtection.products;
                                    }
                                    
                                    return wp.element.createElement(
                                        'div',
                                        { style: { marginBottom: '10px' } },
                                        products.map(function(prod) {
                                            var isChecked = protectionSettings.requiredItems.indexOf(prod.id) !== -1;
                                            return wp.element.createElement(
                                                wp.components.CheckboxControl,
                                                {
                                                    key: prod.id,
                                                    label: prod.name,
                                                    checked: isChecked,
                                                    onChange: function(checked) {
                                                        var newItems = protectionSettings.requiredItems.slice();
                                                        if (checked) {
                                                            if (newItems.indexOf(prod.id) === -1) {
                                                                newItems.push(prod.id);
                                                            }
                                                        } else {
                                                            var index = newItems.indexOf(prod.id);
                                                            if (index !== -1) {
                                                                newItems.splice(index, 1);
                                                            }
                                                        }
                                                        setAttributes({
                                                            memberfulProtection: {
                                                                ...protectionSettings,
                                                                requiredItems: newItems
                                                            }
                                                        });
                                                    }
                                                }
                                            );
                                        })
                                    );
                                })()
                            )
                        )
                    )
                );
            };
        }
    );


})();
