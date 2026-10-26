<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Plugin;
use Elementor\Repeater;
use Elemacy\Core\Container;
use Elemacy\Elementor\Widgets\BaseWidget;
use Elemacy\Forms\Definition\FieldIdResolver;
use Elemacy\Forms\FieldTypeRegistry;
use Elemacy\Forms\FormActionRegistry;
use Elemacy\Forms\FormsBootstrap;
use Elemacy\Forms\Rendering\FormRenderer;
use Elemacy\Modules\Widgets\Forms\ElementorFormDefinitions;
use Elemacy\Modules\Widgets\Forms\ElementorSettingControls;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * The Elementor UI of the forms engine: field types, actions and their
 * settings come from the engine's registries, and the markup from its
 * FormRenderer. Every control ID below is saved with the widget, so none may
 * be renamed, and a non-text default is stored behavior (see
 * tests/architecture/persisted-identifiers.php).
 */
class Form extends BaseWidget
{
    private const COLUMNS = ['20', '25', '33', '40', '50', '60', '66', '75', '80', '100'];

    public function get_name()
    {
        return 'elemacy-form';
    }

    public function get_title()
    {
        return esc_html__('Form Builder', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-form-horizontal';
    }

    public function get_keywords()
    {
        return ['form', 'builder', 'contact', 'mail', 'submission'];
    }

    public function get_style_depends()
    {
        return [FormsBootstrap::ASSET_HANDLE];
    }

    public function get_script_depends()
    {
        return [FormsBootstrap::ASSET_HANDLE];
    }

    protected function is_dynamic_content(): bool
    {
        // Rendered per request so each instance gets its own DOM IDs (a form in a
        // repeated loop item or template must not share them).
        return true;
    }

    protected function register_controls()
    {
        $this->register_form_fields_section();
        $this->register_submit_button_section();
        $this->register_actions_after_submit_section();
        $this->register_action_sections();

        $this->register_form_style_section();
        $this->register_field_style_section();
        $this->register_button_style_section();
        $this->register_messages_style_section();
    }

    protected function register_form_fields_section()
    {
        $this->start_controls_section(
            'section_form_fields',
            [
                'label' => esc_html__('Form Fields', 'elemacy'),
            ]
        );

        $types = Container::instance()->make(FieldTypeRegistry::class)->all();
        $type_options = [];

        foreach ($types as $type) {
            $type_options[$type->key()] = $type->label();
        }

        $repeater = new Repeater();

        $repeater->add_control(
            'type',
            [
                'label' => esc_html__('Type', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'text',
                // Stored even when it equals the default, so a saved field never
                // changes type if the default ever does.
                'save_default' => true,
                'options' => $type_options,
            ]
        );

        $repeater->add_control(
            'label',
            [
                'label' => esc_html__('Label', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Label', 'elemacy'),
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );

        $repeater->add_control(
            'custom_id',
            [
                'label' => esc_html__('ID', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'description' => esc_html__('Letters, digits, "_" or "-". Use it in emails as [field id="your-id"]. Keep it unchanged once the form is live.', 'elemacy'),
            ]
        );

        foreach (ElementorSettingControls::field_settings($types) as $key => $entry) {
            $repeater->add_control($key, ElementorSettingControls::control($entry['setting'], [
                'condition' => [
                    'type' => $entry['types'],
                ],
            ]));
        }

        $repeater->add_control(
            'width',
            [
                'label' => esc_html__('Column Width', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '100',
                'options' => self::column_options(),
            ]
        );

        $this->add_control(
            'form_fields',
            [
                'label' => esc_html__('Form Fields', 'elemacy'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'type' => 'text',
                        'label' => esc_html__('Name', 'elemacy'),
                        'placeholder' => esc_html__('Name', 'elemacy'),
                        'width' => '100',
                        'custom_id' => 'name',
                    ],
                    [
                        'type' => 'email',
                        'label' => esc_html__('Email', 'elemacy'),
                        'placeholder' => esc_html__('Email', 'elemacy'),
                        'width' => '100',
                        'custom_id' => 'email',
                    ],
                    [
                        'type' => 'textarea',
                        'label' => esc_html__('Message', 'elemacy'),
                        'placeholder' => esc_html__('Message', 'elemacy'),
                        'width' => '100',
                        'custom_id' => 'message',
                    ],
                ],
                'title_field' => '{{{ label }}}',
            ]
        );

        $this->add_control(
            'labels_display',
            [
                'label' => esc_html__('Label', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Show', 'elemacy'),
                'label_off' => esc_html__('Hide', 'elemacy'),
                'return_value' => 'yes',
                'default' => 'yes',
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();
    }

    protected function register_submit_button_section()
    {
        $this->start_controls_section(
            'section_submit_button',
            [
                'label' => esc_html__('Submit Button', 'elemacy'),
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => esc_html__('Text', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Send', 'elemacy'),
            ]
        );

        $this->add_responsive_control(
            'button_align',
            [
                'label' => esc_html__('Alignment', 'elemacy'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'start' => [
                        'title' => esc_html__('Left', 'elemacy'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elemacy'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'end' => [
                        'title' => esc_html__('Right', 'elemacy'),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'stretch' => [
                        'title' => esc_html__('Justified', 'elemacy'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => 'stretch',
                'prefix_class' => 'elemacy-form-button-align%s-',
            ]
        );

        $this->add_control(
            'button_width',
            [
                'label' => esc_html__('Column Width', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '100',
                'options' => self::column_options(),
            ]
        );

        $this->end_controls_section();
    }

    protected function register_actions_after_submit_section()
    {
        $this->start_controls_section(
            'section_actions_after_submit',
            [
                'label' => esc_html__('Actions After Submit', 'elemacy'),
            ]
        );

        $options = [];

        foreach (Container::instance()->make(FormActionRegistry::class)->all() as $action) {
            $options[$action->key()] = $action->label();
        }

        $this->add_control(
            'submit_actions',
            [
                'label' => esc_html__('Add Action', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $options,
                'default' => [
                    'email',
                ],
                // Stored even when it equals the default, so a saved form never
                // starts or stops an action if the default ever changes.
                'save_default' => true,
            ]
        );

        $this->end_controls_section();
    }

    /** One section per registered action, shown while that action is selected. */
    protected function register_action_sections()
    {
        foreach (Container::instance()->make(FormActionRegistry::class)->all() as $action) {
            $settings = $action->settings();

            if (!$settings) {
                continue;
            }

            $this->start_controls_section(
                'section_' . $action->key(),
                [
                    'label' => $action->label(),
                    'condition' => [
                        'submit_actions' => $action->key(),
                    ],
                ]
            );

            foreach ($settings as $setting) {
                $this->add_control($setting->key(), ElementorSettingControls::control($setting));
            }

            $this->end_controls_section();
        }
    }

    protected function register_form_style_section()
    {
        $this->start_controls_section(
            'section_form_style',
            [
                'label' => esc_html__('Form', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label' => esc_html__('Column Gap', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form' => 'margin-left: calc(-{{SIZE}}{{UNIT}}/2); margin-right: calc(-{{SIZE}}{{UNIT}}/2);',
                    '{{WRAPPER}} .elemacy-form-field' => 'padding-left: calc({{SIZE}}{{UNIT}}/2); padding-right: calc({{SIZE}}{{UNIT}}/2);',
                ],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label' => esc_html__('Row Gap', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form' => 'margin-bottom: -{{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .elemacy-form-field' => 'padding-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'heading_label_style',
            [
                'label' => esc_html__('Label', 'elemacy'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'label_spacing',
            [
                'label' => esc_html__('Spacing', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .elemacy-form-label',
            ]
        );

        $this->end_controls_section();
    }

    protected function register_field_style_section()
    {
        $this->start_controls_section(
            'section_field_style',
            [
                'label' => esc_html__('Field', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'field_text_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-control' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'field_placeholder_color',
            [
                'label' => esc_html__('Placeholder Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-control::placeholder' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'field_typography',
                'selector' => '{{WRAPPER}} .elemacy-form-control',
            ]
        );

        $this->add_control(
            'field_bg_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-control' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'field_border',
                'selector' => '{{WRAPPER}} .elemacy-form-control',
            ]
        );

        $this->add_control(
            'field_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-control' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'field_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-control' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function register_button_style_section()
    {
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => esc_html__('Buttons', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .elemacy-form-button',
            ]
        );

        $this->start_controls_tabs('tabs_button_style');

        $this->start_controls_tab(
            'tab_button_normal',
            [
                'label' => esc_html__('Normal', 'elemacy'),
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .elemacy-form-button',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_button_hover',
            [
                'label' => esc_html__('Hover', 'elemacy'),
            ]
        );

        $this->add_control(
            'button_text_color_hover',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_color_hover',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button:hover' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'button_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .elemacy-form-button',
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();
    }

    protected function register_messages_style_section()
    {
        $this->start_controls_section(
            'section_messages_style',
            [
                'label' => esc_html__('Messages', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'message_spacing',
            [
                'label' => esc_html__('Spacing', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => -100,
                        'max' => 150,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-messages' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'message_typography',
                'selector' => '{{WRAPPER}} .elemacy-form-message',
            ]
        );

        $this->add_control(
            'message_border_type',
            [
                'label' => esc_html__('Border Type', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Default', 'elemacy'),
                    'none' => esc_html__('None', 'elemacy'),
                    'solid' => esc_html__('Solid', 'elemacy'),
                    'double' => esc_html__('Double', 'elemacy'),
                    'dotted' => esc_html__('Dotted', 'elemacy'),
                    'dashed' => esc_html__('Dashed', 'elemacy'),
                    'groove' => esc_html__('Groove', 'elemacy'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message' => 'border-style: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'message_border_width',
            [
                'label' => esc_html__('Border Width', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', 'vw', 'custom'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    'message_border_type!' => ['', 'none'],
                ],
                'responsive' => true,
            ]
        );

        $this->add_responsive_control(
            'message_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => '2',
                    'right' => '2',
                    'bottom' => '2',
                    'left' => '2',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'message_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => '15',
                    'right' => '15',
                    'bottom' => '15',
                    'left' => '15',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->start_controls_tabs('tabs_message_style');

        $this->start_controls_tab(
            'tab_message_success',
            [
                'label' => esc_html__('Success', 'elemacy'),
            ]
        );

        $this->add_control(
            'success_message_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'default' => '#155724',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message-success' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'success_message_background_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'default' => '#d4edda',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message-success' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'success_message_border_color',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'default' => '#c3e6cb',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message-success' => 'border-color: {{VALUE}};',
                ],
                'condition' => [
                    'message_border_type!' => ['', 'none'],
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_message_error',
            [
                'label' => esc_html__('Error', 'elemacy'),
            ]
        );

        $this->add_control(
            'error_message_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'default' => '#721c24',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message-error' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'error_message_background_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8d7da',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message-error' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'error_message_border_color',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f5c6cb',
                'selectors' => [
                    '{{WRAPPER}} .elemacy-form-message-error' => 'border-color: {{VALUE}};',
                ],
                'condition' => [
                    'message_border_type!' => ['', 'none'],
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        // The submit handler looks the form up in the document that stores it.
        // In a Theme Builder header/footer, popup or loop template that is the
        // template Elementor has switched to, not the viewed page.
        $document = Plugin::$instance->documents->get_current();
        $owner = $document ? (int) $document->get_main_id() : (int) get_the_ID();
        $form = ElementorFormDefinitions::from_settings($owner, $this->get_id(), $settings);

        if (Plugin::$instance->editor && Plugin::$instance->editor->is_edit_mode()) {
            $this->render_id_notice(is_array($settings['form_fields'] ?? null) ? $settings['form_fields'] : []);
        }

        $html = Container::instance()->make(FormRenderer::class)->render($form, [
            'show_labels' => 'yes' === ($settings['labels_display'] ?? 'yes'),
            'button_text' => (string) ($settings['button_text'] ?? ''),
            'button_column' => (string) ($settings['button_width'] ?? '100'),
        ]);

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FormRenderer escapes every attribute and text it writes.
    }

    /** Editor only: which field IDs could not be used as typed. */
    private function render_id_notice(array $rows): void
    {
        $result = FieldIdResolver::resolve($rows);
        $problems = [];

        foreach ($result['invalid'] as $id) {
            /* translators: %s: the field ID the author typed. */
            $problems[] = sprintf(__('"%s" is not a valid field ID: use letters, digits, "_" or "-".', 'elemacy'), $id);
        }

        foreach ($result['duplicate'] as $id) {
            /* translators: %s: the field ID the author typed. */
            $problems[] = sprintf(__('The field ID "%s" is used more than once; only the first field keeps it.', 'elemacy'), $id);
        }

        if ($problems) {
            echo '<div class="elemacy-alert elemacy-alert-warning">' . implode('<br>', array_map('esc_html', $problems)) . '</div>';
        }
    }

    private static function column_options(): array
    {
        $options = [];

        foreach (self::COLUMNS as $column) {
            $options[$column] = $column . '%';
        }

        return $options;
    }
}
