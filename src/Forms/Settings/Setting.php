<?php

namespace Elemacy\Forms\Settings;

defined('ABSPATH') || exit;

/**
 * One authored setting of a field type or submit action, described without
 * any builder's control API. Each builder turns it into its own editor
 * control (the Elementor Form widget does so in ElementorSettingControls),
 * so an extension written once works in every builder.
 */
final class Setting
{
    public const TEXT     = 'text';
    public const TEXTAREA = 'textarea';
    public const NUMBER   = 'number';
    public const SWITCHER = 'switcher';
    public const SELECT   = 'select';

    public const KINDS = [self::TEXT, self::TEXTAREA, self::NUMBER, self::SWITCHER, self::SELECT];

    /**
     * Field settings Free defines once, with a fixed kind, so every field type
     * that offers one shares the same saved key. A type opts in by listing
     * Setting::standard($key) in its settings().
     */
    public const STANDARD = [
        'placeholder' => self::TEXT,
        'required'    => self::SWITCHER,
        'options'     => self::TEXTAREA,
        'field_value' => self::TEXT,
    ];

    /** The saved value of an enabled switcher. */
    public const ON = 'yes';

    private string $key;
    private string $kind;
    private string $label;

    /** @var mixed */
    private $default = '';
    private string $description = '';
    private string $placeholder = '';

    /** @var array<string, string> */
    private array $options = [];

    private function __construct(string $key, string $kind, string $label)
    {
        $this->key = $key;
        $this->kind = $kind;
        $this->label = $label;
    }

    public static function text(string $key, string $label): self
    {
        return new self($key, self::TEXT, $label);
    }

    public static function textarea(string $key, string $label): self
    {
        return new self($key, self::TEXTAREA, $label);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, self::NUMBER, $label);
    }

    public static function switcher(string $key, string $label): self
    {
        return new self($key, self::SWITCHER, $label);
    }

    /**
     * @param string                $key     The saved setting key.
     * @param string                $label   The editor label.
     * @param array<string, string> $options Saved value => label.
     */
    public static function select(string $key, string $label, array $options): self
    {
        $setting = new self($key, self::SELECT, $label);
        $setting->options = $options;

        return $setting;
    }

    /**
     * A standard field setting (see STANDARD) with Free's own label.
     *
     * @throws \InvalidArgumentException For a key that is not standard.
     */
    public static function standard(string $key): self
    {
        switch ($key) {
            case 'placeholder':
                return self::text($key, __('Placeholder', 'elemacy'));
            case 'required':
                return self::switcher($key, __('Required', 'elemacy'));
            case 'options':
                return self::textarea($key, __('Options', 'elemacy'))
                    ->with_description(__('Enter each option on its own line. To use a value other than the label, separate them with a pipe, for example: First Name|f_name', 'elemacy'));
            case 'field_value':
                return self::text($key, __('Value', 'elemacy'));
        }

        throw new \InvalidArgumentException(sprintf('"%s" is not a standard form field setting.', esc_html($key)));
    }

    /** @param mixed $value */
    public function with_default($value): self
    {
        $setting = clone $this;
        $setting->default = $value;

        return $setting;
    }

    public function with_description(string $description): self
    {
        $setting = clone $this;
        $setting->description = $description;

        return $setting;
    }

    public function with_placeholder(string $placeholder): self
    {
        $setting = clone $this;
        $setting->placeholder = $placeholder;

        return $setting;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function label(): string
    {
        return $this->label;
    }

    /** @return mixed */
    public function default()
    {
        return $this->default;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function placeholder(): string
    {
        return $this->placeholder;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return $this->options;
    }
}
