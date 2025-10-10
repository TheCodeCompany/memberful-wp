(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;
    const { __ } = wp.i18n;
    const { 
        ToggleControl, 
        SelectControl, 
        TextareaControl, 
        CheckboxControl,
        PanelBody,
        InspectorControls,
        useBlockProps
    } = wp.blockEditor;
    const { Fragment } = wp.element;

    /**
     * Memberful Protected Content Block
     */
    registerBlockType('memberful/protected-content', {
        title: __('Memberful Protected Content', 'memberful'),
        description: __('Wrap content with Memberful protection settings.', 'memberful'),
        icon: 'lock',
        category: 'memberful',
        keywords: [
            __('memberful', 'memberful'),
            __('protection', 'memberful'),
            __('membership', 'memberful'),
            __('content', 'memberful')
        ],
        attributes: {
            memberfulProtection: {
                type: 'object',
                default: {
                    enabled: false,
                    accessType: 'subscription',
                    requiredItems: [],
                    unauthorizedAction: 'hide',
                    customMessage: ''
                }
            },
            className: {
                type: 'string',
                default: ''
            }
        },
        supports: {
            align: true,
            alignWide: true,
            className: true,
            customClassName: true
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { memberfulProtection } = attributes;
            const blockProps = useBlockProps();

            const updateProtectionSetting = (key, value) => {
                setAttributes({
                    memberfulProtection: {
                        ...memberfulProtection,
                        [key]: value
                    }
                });
            };

            const settings = window.memberfulProtectedContent?.settings || {};
            const subscriptions = window.memberfulProtectedContent?.subscriptions || [];
            const products = window.memberfulProtectedContent?.products || [];

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { 
                        title: __('Memberful Protection Settings', 'memberful'),
                        initialOpen: true 
                    },
                        el(ToggleControl, {
                            label: __('Enable Protection', 'memberful'),
                            checked: memberfulProtection.enabled,
                            onChange: (value) => updateProtectionSetting('enabled', value)
                        }),

                        memberfulProtection.enabled && el(Fragment, {},
                            el(SelectControl, {
                                label: __('Access Type', 'memberful'),
                                value: memberfulProtection.accessType,
                                options: settings.accessTypes || [],
                                onChange: (value) => updateProtectionSetting('accessType', value)
                            }),

                            (memberfulProtection.accessType === 'subscription' || 
                             memberfulProtection.accessType === 'product') && el(SelectControl, {
                                label: memberfulProtection.accessType === 'subscription' 
                                    ? __('Required Subscriptions', 'memberful')
                                    : __('Required Products', 'memberful'),
                                value: memberfulProtection.requiredItems,
                                multiple: true,
                                options: memberfulProtection.accessType === 'subscription' 
                                    ? subscriptions 
                                    : products,
                                onChange: (value) => updateProtectionSetting('requiredItems', value)
                            }),

                            el(SelectControl, {
                                label: __('Unauthorized Action', 'memberful'),
                                value: memberfulProtection.unauthorizedAction,
                                options: settings.unauthorizedActions || [],
                                onChange: (value) => updateProtectionSetting('unauthorizedAction', value)
                            }),

                            (memberfulProtection.unauthorizedAction === 'message' || 
                             memberfulProtection.unauthorizedAction === 'login_form_and_message') && el(TextareaControl, {
                                label: __('Custom Message', 'memberful'),
                                value: memberfulProtection.customMessage,
                                onChange: (value) => updateProtectionSetting('customMessage', value),
                                help: __('Message to show to unauthorized users', 'memberful')
                            })
                        )
                    )
                ),

                el('div', blockProps,
                    el('div', {
                        className: 'memberful-protected-content-editor',
                        style: {
                            border: '2px dashed #ccc',
                            padding: '20px',
                            borderRadius: '4px',
                            backgroundColor: memberfulProtection.enabled ? '#fff3cd' : '#f8f9fa'
                        }
                    },
                        el('div', {
                            style: {
                                marginBottom: '10px',
                                fontWeight: 'bold',
                                color: memberfulProtection.enabled ? '#856404' : '#6c757d'
                            }
                        },
                            memberfulProtection.enabled 
                                ? __('Protected Content - Add blocks here', 'memberful')
                                : __('Protected Content - Enable protection to add blocks', 'memberful')
                        ),

                        memberfulProtection.enabled && el('div', {
                            style: {
                                fontSize: '14px',
                                color: '#6c757d',
                                marginBottom: '15px'
                            }
                        },
                            el('strong', {}, __('Protection Settings:', 'memberful')),
                            el('br'),
                            __('Access Type: ', 'memberful'),
                            settings.accessTypes?.find(opt => opt.value === memberfulProtection.accessType)?.label || memberfulProtection.accessType,
                            el('br'),
                            __('Action: ', 'memberful'),
                            settings.unauthorizedActions?.find(opt => opt.value === memberfulProtection.unauthorizedAction)?.label || memberfulProtection.unauthorizedAction
                        ),

                        memberfulProtection.enabled && el('div', {
                            style: {
                                minHeight: '50px',
                                border: '1px solid #ddd',
                                borderRadius: '4px',
                                padding: '10px',
                                backgroundColor: '#fff'
                            }
                        },
                            el('p', {
                                style: {
                                    margin: 0,
                                    color: '#6c757d',
                                    fontStyle: 'italic'
                                }
                            },
                                __('Add your protected content blocks here...', 'memberful')
                            )
                        )
                    )
                )
            );
        },

        save: function() {
            // Server-side rendering
            return null;
        }
    });

    /**
     * Add Memberful category to block editor
     */
    wp.blocks.registerBlockStyle('memberful/protected-content', {
        name: 'default',
        label: __('Default', 'memberful')
    });

    wp.blocks.registerBlockStyle('memberful/protected-content', {
        name: 'bordered',
        label: __('Bordered', 'memberful')
    });

    wp.blocks.registerBlockStyle('memberful/protected-content', {
        name: 'highlighted',
        label: __('Highlighted', 'memberful')
    });

})();
