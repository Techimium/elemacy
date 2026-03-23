<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Form extends BaseWidget
{
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
        return ['elemacy-form'];
    }

    public function get_script_depends()
    {
        return ['elemacy-form'];
    }

    protected function register_controls()
    {
        $this->register_form_fields_section();
        $this->register_submit_button_section();
        $this->register_actions_after_submit_section();
        $this->register_email_section();

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

        $repeater = new Repeater();

        $repeater->add_control(
            'type',
            [
                'label' => esc_html__('Type', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text' => esc_html__('Text', 'elemacy'),
                    'email' => esc_html__('Email', 'elemacy'),
                    'textarea' => esc_html__('Textarea', 'elemacy'),
                    'tel' => esc_html__('Tel', 'elemacy'),
                    'select' => esc_html__('Select', 'elemacy'),
                    'radio' => esc_html__('Radio', 'elemacy'),
                    'checkbox' => esc_html__('Checkbox', 'elemacy'),
                ],
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
                'label' => esc_html__('Custom ID', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'description' => esc_html__('Identify the field with a unique ID for use in emails.', 'elemacy'),
            ]
        );

        $repeater->add_control(
            'placeholder',
            [
                'label' => esc_html__('Placeholder', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Placeholder', 'elemacy'),
                'condition' => [
                    'type' => ['text', 'email', 'textarea', 'tel', 'select'],
                ],
            ]
        );

        $repeater->add_control(
            'required',
            [
                'label' => esc_html__('Required', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $repeater->add_control(
            'options',
            [
                'label' => esc_html__('Options', 'elemacy'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 10,
                'description' => esc_html__('Enter each option in a separate line. To differentiate between label and value, separate them with a pipe char ("|"). For example: First Name|f_name', 'elemacy'),
                'condition' => [
                    'type' => ['select', 'checkbox', 'radio'],
                ],
            ]
        );

        $repeater->add_control(
            'width',
            [
                'label' => esc_html__('Column Width', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '100',
                'options' => [
                    '20' => '20%',
                    '25' => '25%',
                    '33' => '33%',
                    '40' => '40%',
                    '50' => '50%',
                    '60' => '60%',
                    '66' => '66%',
                    '75' => '75%',
                    '80' => '80%',
                    '100' => '100%',
                ],
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
                'prefix_class' => 'elemacy-button-align%s-',
            ]
        );

        $this->add_control(
            'button_width',
            [
                'label' => esc_html__('Column Width', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '100',
                'options' => [
                    '20' => '20%',
                    '25' => '25%',
                    '33' => '33%',
                    '40' => '40%',
                    '50' => '50%',
                    '60' => '60%',
                    '66' => '66%',
                    '75' => '75%',
                    '80' => '80%',
                    '100' => '100%',
                ],
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

        $this->add_control(
            'submit_actions',
            [
                'label' => esc_html__('Add Action', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'email' => esc_html__('Email', 'elemacy'),
                ],
                'default' => [
                    'email',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function register_email_section()
    {
        $this->start_controls_section(
            'section_email',
            [
                'label' => esc_html__('Email', 'elemacy'),
                'condition' => [
                    'submit_actions' => 'email',
                ],
            ]
        );

        $this->add_control(
            'email_to',
            [
                'label' => esc_html__('To', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => get_option('admin_email'),
                'placeholder' => get_option('admin_email'),
                'description' => esc_html__('Separate emails with commas', 'elemacy'),
            ]
        );

        $this->add_control(
            'email_subject',
            [
                'label' => esc_html__('Subject', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('New message from ', 'elemacy') . get_bloginfo('name'),
            ]
        );

        $this->add_control(
            'email_content',
            [
                'label' => esc_html__('Message', 'elemacy'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '[all-fields]',
                'description' => esc_html__('Use shortcode [all-fields] to see all fields, or use specific field ID like [field_id]', 'elemacy'),
            ]
        );

        $this->add_control(
            'email_from_name',
            [
                'label' => esc_html__('From Name', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => get_bloginfo('name'),
            ]
        );

        $this->add_control(
            'email_from',
            [
                'label' => esc_html__('From Email', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => get_option('admin_email'),
            ]
        );

        $this->add_control(
            'email_reply_to',
            [
                'label' => esc_html__('Reply-To', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => esc_html__('Email Field', 'elemacy'),
            ]
        );

        $this->end_controls_section();
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
                    '{{WRAPPER}} .elemacy-field-group' => 'padding-left: calc({{SIZE}}{{UNIT}}/2); padding-right: calc({{SIZE}}{{UNIT}}/2);',
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
                    '{{WRAPPER}} .elemacy-field-group' => 'padding-bottom: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .elemacy-field-label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => esc_html__('Text Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-field-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .elemacy-field-label',
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
                    '{{WRAPPER}} .elemacy-field-control' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'field_placeholder_color',
            [
                'label' => esc_html__('Placeholder Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-field-control::placeholder' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'field_typography',
                'selector' => '{{WRAPPER}} .elemacy-field-control',
            ]
        );

        $this->add_control(
            'field_bg_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-field-control' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'field_border',
                'selector' => '{{WRAPPER}} .elemacy-field-control',
            ]
        );

        $this->add_control(
            'field_border_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-field-control' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .elemacy-field-control' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                'selector' => '{{WRAPPER}} .elemacy-button',
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
                    '{{WRAPPER}} .elemacy-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .elemacy-button',
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
                    '{{WRAPPER}} .elemacy-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color_hover',
            [
                'label' => esc_html__('Background Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_color_hover',
            [
                'label' => esc_html__('Border Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .elemacy-button:hover' => 'border-color: {{VALUE}};',
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
                    '{{WRAPPER}} .elemacy-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .elemacy-button',
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => esc_html__('Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .elemacy-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .elemacy-message-container' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

         $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'message_typography',
                'selector' => '{{WRAPPER}} .elemacy-message',
            ]
        );

        $this->add_control('message_border_type',
        [
			'label' => esc_html__( 'Border Type', 'elemacy' ),
			'type' => Controls_Manager::SELECT,
			'options' => [
				'' => esc_html__( 'Default', 'elemacy' ),
				'none' => esc_html__( 'None', 'elemacy' ),
				'solid' => esc_html__( 'Solid', 'elemacy' ),
				'double' => esc_html__( 'Double', 'elemacy' ),
				'dotted' => esc_html__( 'Dotted', 'elemacy' ),
				'dashed' => esc_html__( 'Dashed', 'elemacy' ),
				'groove' => esc_html__( 'Groove', 'elemacy' ),
			],
			'selectors' => [
				'{{SELECTOR}} .elemacy-message' => 'border-style: {{VALUE}};',
			],
		]);

        $this->add_responsive_control(
            'message_border_width',
            [
                'label' => esc_html__( 'Border Width', 'elemacy' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', 'em', 'rem', 'vw', 'custom' ],
                'selectors' => [
                    '{{SELECTOR}} .elemacy-message' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    'message_border_type!' => [ '', 'none' ],
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
                    '{{WRAPPER}} .elemacy-message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .elemacy-message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .elemacy-message-success' => 'color: {{VALUE}};',
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
                    '{{WRAPPER}} .elemacy-message-success' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .elemacy-message-success' => 'border-color: {{VALUE}};',
                ],
                'condition' => [
                    'message_border_type!' => [ '', 'none' ],
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
                    '{{WRAPPER}} .elemacy-message-danger' => 'color: {{VALUE}};',
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
                    '{{WRAPPER}} .elemacy-message-danger' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .elemacy-message-danger' => 'border-color: {{VALUE}};',
                ],
                'condition' => [
                    'message_border_type!' => [ '', 'none' ],
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
        $this->add_render_attribute('wrapper', 'class', 'elemacy-form-wrapper');
        $this->add_render_attribute('form', [
            'class' => 'elemacy-form',
            'method' => 'post',
            'data-id' => $this->get_id(),
        ]);

        ?>
        <div <?php echo $this->get_render_attribute_string('wrapper'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <form <?php echo $this->get_render_attribute_string('form'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <input type="hidden" name="action" value="elemacy_form_submit">
                <input type="hidden" name="widget_id" value="<?php echo esc_attr($this->get_id()); ?>">
                <input type="hidden" name="post_id" value="<?php echo esc_attr(get_the_ID()); ?>">
                <?php /* Honeypot field for simple spam protection */ ?>
                <div style="display:none !important;">
                    <label>Do not fill this field</label>
                    <input type="text" name="elemacy_honeypot" autocomplete="off" tabindex="-1">
                </div>
                <?php wp_nonce_field('elemacy_form_submit', '_wpnonce'); ?>

                <?php foreach ($settings['form_fields'] as $index => $field) :
                    $field_id = 'field_' . $index;
                    $this->add_render_attribute('field_wrapper_' . $index, [
                        'class' => [
                            'elemacy-field-group',
                            'elemacy-column',
                            'elemacy-column-' . $field['width'],
                        ],
                    ]);

                    $this->add_render_attribute('input_' . $index, [
                        'name' => 'form_field[' . $index . ']',
                        'id' => $field_id,
                        'class' => 'elemacy-field-control',
                        'placeholder' => $field['placeholder'],
                    ]);

                    if ($field['required']) {
                        $this->add_render_attribute('input_' . $index, 'required', 'required');
                        $this->add_render_attribute('input_' . $index, 'aria-required', 'true');
                    }
                    ?>
                    <div <?php echo $this->get_render_attribute_string('field_wrapper_' . $index); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                        <?php if ($settings['labels_display'] === 'yes' && !empty($field['label'])) : ?>
                            <label class="elemacy-field-label" for="<?php echo esc_attr($field_id); ?>">
                                <?php echo esc_html($field['label']); ?>
                            </label>
                        <?php endif; ?>

                        <?php
                        switch ($field['type']) {
                            case 'textarea':
                                ?>
                                <textarea <?php echo $this->get_render_attribute_string('input_' . $index); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> rows="5"></textarea>
                                <?php
                                break;
                            case 'select':
                                $options = explode("\n", $field['options']);
                                ?>
                                <select <?php echo $this->get_render_attribute_string('input_' . $index); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                    <?php foreach ($options as $option) :
                                        $option_parts = explode('|', $option);
                                        $label = $option_parts[0];
                                        $value = isset($option_parts[1]) ? $option_parts[1] : $label;
                                        ?>
                                        <option value="<?php echo esc_attr(trim($value)); ?>">
                                            <?php echo esc_html(trim($label)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                break;
                            case 'radio':
                            case 'checkbox':
                                $options = explode("\n", $field['options']);
                                $type = $field['type'];
                                ?>
                                <div class="elemacy-field-subgroup">
                                    <?php foreach ($options as $opt_index => $option) :
                                        $option_parts = explode('|', $option);
                                        $label = $option_parts[0];
                                        $value = isset($option_parts[1]) ? $option_parts[1] : $label;
                                        $sub_id = $field_id . '_' . $opt_index;
                                        ?>
                                        <div class="elemacy-field-option">
                                            <input 
                                                type="<?php echo esc_attr($type); ?>" 
                                                name="form_field[<?php echo esc_attr($index); ?>]<?php echo 'checkbox' === $type ? '[]' : ''; ?>" 
                                                id="<?php echo esc_attr($sub_id); ?>" 
                                                value="<?php echo esc_attr(trim($value)); ?>"
                                                <?php echo $field['required'] ? 'required' : ''; ?>
                                            >
                                            <label for="<?php echo esc_attr($sub_id); ?>"><?php echo esc_html(trim($label)); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                                break;
                            default:
                                ?>
                                <input type="<?php echo esc_attr($field['type']); ?>" <?php echo $this->get_render_attribute_string('input_' . $index); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                <?php
                                break;
                        }
                        ?>
                    </div>
                <?php endforeach; ?>

                <div class="elemacy-field-group elemacy-column elemacy-column-<?php echo esc_attr($settings['button_width']); ?> elemacy-field-type-submit">
                    <button type="submit" class="elemacy-button">
                        <span class="elemacy-button-text"><?php echo esc_html($settings['button_text']); ?></span>
                    </button>
                </div>

                <div class="elemacy-message-container elemacy-field-group elemacy-column elemacy-column-100"></div>
            </form>
        </div>
        <?php
    }

    protected function content_template()
    {
        // For editor live preview if needed
    }
}
