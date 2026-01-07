/**
 * RIG Accessible Forms - Gutenberg Block Editor
 */
(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, Placeholder, Disabled } = wp.components;
    const { useSelect } = wp.data;
    const { __ } = wp.i18n;
    const { ServerSideRender } = wp.serverSideRender || wp.components;

    /**
     * Register the Accessible Form block
     */
    registerBlockType('rigaf/form', {
        title: __('Accessible Form', 'rigaf'),
        description: __('Display an accessible contact form', 'rigaf'),
        category: 'widgets',
        icon: 'feedback',
        keywords: [__('form', 'rigaf'), __('contact', 'rigaf'), __('accessible', 'rigaf')],
        attributes: {
            formId: {
                type: 'integer',
                default: 0
            }
        },
        supports: {
            html: false,
            align: false
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { formId } = attributes;
            const blockProps = useBlockProps();

            // Fetch available forms from WordPress
            const forms = useSelect(function(select) {
                const query = {
                    per_page: -1,
                    status: 'publish,draft,private'
                };

                return select('core').getEntityRecords('postType', 'rigaf_form', query);
            }, []);

            // Convert forms to options for SelectControl
            const formOptions = [
                { value: 0, label: __('Select a form...', 'rigaf') }
            ];

            if (forms) {
                forms.forEach(function(form) {
                    formOptions.push({
                        value: form.id,
                        label: form.title.rendered || __('(no title)', 'rigaf')
                    });
                });
            }

            // Handle form selection change
            function onChangeForm(newFormId) {
                setAttributes({ formId: parseInt(newFormId, 10) });
            }

            // Show placeholder if no form is selected
            if (!formId) {
                return (
                    wp.element.createElement(
                        'div',
                        blockProps,
                        wp.element.createElement(
                            Placeholder,
                            {
                                icon: 'feedback',
                                label: __('Accessible Form', 'rigaf'),
                                instructions: __('Select a form to display', 'rigaf')
                            },
                            wp.element.createElement(SelectControl, {
                                value: formId,
                                options: formOptions,
                                onChange: onChangeForm,
                                label: __('Choose Form', 'rigaf')
                            })
                        )
                    )
                );
            }

            // Show form preview and sidebar controls
            return (
                wp.element.createElement(
                    wp.element.Fragment,
                    null,
                    // Sidebar Controls
                    wp.element.createElement(
                        InspectorControls,
                        null,
                        wp.element.createElement(
                            PanelBody,
                            {
                                title: __('Form Settings', 'rigaf'),
                                initialOpen: true
                            },
                            wp.element.createElement(SelectControl, {
                                label: __('Select Form', 'rigaf'),
                                value: formId,
                                options: formOptions,
                                onChange: onChangeForm,
                                help: __('Choose which form to display', 'rigaf')
                            })
                        )
                    ),
                    // Block Preview
                    wp.element.createElement(
                        'div',
                        blockProps,
                        wp.element.createElement(
                            'div',
                            {
                                className: 'rigaf-block-preview',
                                style: {
                                    border: '1px solid #ddd',
                                    borderRadius: '4px',
                                    padding: '20px',
                                    backgroundColor: '#f9f9f9'
                                }
                            },
                            wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        marginBottom: '15px',
                                        paddingBottom: '15px',
                                        borderBottom: '2px solid #0073aa',
                                        fontSize: '14px',
                                        fontWeight: '600',
                                        color: '#0073aa'
                                    }
                                },
                                '📝 ',
                                __('Form Preview', 'rigaf'),
                                ' (ID: ',
                                formId,
                                ')'
                            ),
                            wp.element.createElement(
                                Disabled,
                                null,
                                wp.element.createElement(ServerSideRender, {
                                    block: 'rigaf/form',
                                    attributes: { formId: formId }
                                })
                            )
                        )
                    )
                )
            );
        },

        save: function() {
            // Server-side rendering, so save returns null
            return null;
        }
    });
})();
