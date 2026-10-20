<?php

/**
 * The forms engine's pure logic (openspec forms/*): registries, stable field
 * IDs, every built-in field type's server rule, and the renderer's markup
 * guarantees. Submission against real WordPress, mail and the browser runtime
 * are covered in tests/integration/form-submissions.php and
 * tests/e2e/run-form-submissions.mjs.
 */

use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\Contracts\FormActionInterface;
use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Definition\FieldIdResolver;
use Elemacy\Forms\Definition\FormDefinition;
use Elemacy\Forms\FieldTypeRegistry;
use Elemacy\Forms\Fields\AbstractFieldType;
use Elemacy\Forms\FormActionRegistry;
use Elemacy\Forms\Rendering\FormRenderer;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\ActionResult;
use Elemacy\Forms\Submission\Submission;

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong($function_name, $message, $version)
    {
        $GLOBALS['__doing_it_wrong'][] = $function_name . ': ' . $message;
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return false !== filter_var((string) $email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data)
    {
        return json_encode($data);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null)
    {
        return (string) $url;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($content, $allowed_html)
    {
        return strip_tags((string) $content, '<' . implode('><', array_keys($allowed_html)) . '>');
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($class_name)
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class_name);
    }
}

/** A field type that declares whichever settings a test needs. */
final class FixtureFieldType extends AbstractFieldType
{
    private string $key;
    private array $settings;
    private string $layout;

    public function __construct(string $key, array $settings = [], string $layout = self::LAYOUT_FIELD)
    {
        $this->key = $key;
        $this->settings = $settings;
        $this->layout = $layout;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function layout(): string
    {
        return $this->layout;
    }
}

final class FixtureFormAction implements FormActionInterface
{
    private string $key;
    private array $settings;

    public function __construct(string $key, array $settings = [])
    {
        $this->key = $key;
        $this->settings = $settings;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function run(Submission $submission): ActionResult
    {
        return ActionResult::success();
    }
}

/** A field type registry holding exactly $types, without the config list or hook. */
function forms_types(array $types): FieldTypeRegistry
{
    return new FieldTypeRegistry(static function (FieldTypeRegistry $registry) use ($types): void {
        foreach ($types as $type) {
            $registry->register($type);
        }
    });
}

/** The real registry from src/Config/form_field_types.php. */
function forms_builtin_types(): FieldTypeRegistry
{
    return FieldTypeRegistry::from_config(\Elemacy\Core\Container::instance());
}

function forms_field(string $type, array $settings = [], string $id = 'f', string $label = 'Label'): Field
{
    return new Field($id, $type, $label, $settings + ['type' => $type]);
}

/** [valid?, value-or-message] for one value against one built-in type. */
function forms_check(string $type, array $settings, $raw): array
{
    $result = forms_builtin_types()->get($type)->validate(forms_field($type, $settings), $raw);

    return [$result->is_valid(), $result->is_valid() ? $result->value() : $result->message()];
}

/* ── Registries ─────────────────────────────────────────────────────────── */

check('Forms registries are lazy: the loader and its registration hook run on the first lookup, once', static function () {
    $loads = 0;
    $registry = new FieldTypeRegistry(static function (FieldTypeRegistry $registry) use (&$loads): void {
        ++$loads;
        $registry->register(new FixtureFieldType('alpha'));
    });
    $before = $loads;
    $registry->get('alpha');
    $registry->all();

    return 0 === $before && 1 === $loads && null !== $registry->get('alpha');
});

check('Forms registries refuse a duplicate key with a diagnostic and keep the first registration', static function () {
    $GLOBALS['__doing_it_wrong'] = [];
    $first = new FixtureFormAction('notify');
    $second = new FixtureFormAction('notify');
    $results = [];
    $registry = new FormActionRegistry(static function (FormActionRegistry $registry) use ($first, $second, &$results): void {
        $results[] = $registry->register($first);
        $results[] = $registry->register($second);
    });

    return $registry->get('notify') === $first
        && [true, false] === $results
        && 1 === count($GLOBALS['__doing_it_wrong'])
        && false !== strpos($GLOBALS['__doing_it_wrong'][0], 'already registered');
});

check('Forms registries list items in registration order and refuse malformed keys', static function () {
    $GLOBALS['__doing_it_wrong'] = [];
    $registry = forms_types([new FixtureFieldType('zeta'), new FixtureFieldType('Bad Key'), new FixtureFieldType('alpha')]);
    $keys = array_map(static function (FieldTypeInterface $type) {
        return $type->key();
    }, $registry->all());

    return ['zeta', 'alpha'] === $keys && 1 === count($GLOBALS['__doing_it_wrong']);
});

check('An action setting key must start with "{action key}_"', static function () {
    $GLOBALS['__doing_it_wrong'] = [];
    $registry = new FormActionRegistry(static function (FormActionRegistry $registry): void {
        $registry->register(new FixtureFormAction('webhook', [Setting::text('webhook_url', 'URL')]));
        $registry->register(new FixtureFormAction('crm', [Setting::text('email_to', 'To')]));
    });

    return null !== $registry->get('webhook') && null === $registry->get('crm') && 1 === count($GLOBALS['__doing_it_wrong']);
});

check('Field settings: a shared key keeps one kind, standard keys keep theirs, and the core keys are reserved', static function () {
    $GLOBALS['__doing_it_wrong'] = [];
    $registry = forms_types([
        new FixtureFieldType('one', [Setting::number('field_min', 'Min')]),
        new FixtureFieldType('two', [Setting::number('field_min', 'Min')]),
        new FixtureFieldType('three', [Setting::text('field_min', 'Min')]),
        new FixtureFieldType('four', [Setting::text('required', 'Required')]),
        new FixtureFieldType('five', [Setting::text('width', 'Width')]),
        new FixtureFieldType('six', [Setting::text('custom_id', 'ID')]),
        new FixtureFieldType('seven', [], 'sideways'),
    ]);
    $kept = array_map(static function (FieldTypeInterface $type) {
        return $type->key();
    }, $registry->all());

    return ['one', 'two'] === $kept && 5 === count($GLOBALS['__doing_it_wrong']);
});

check('Free registers its 14 field types and the Email action, each satisfying the setting rules', static function () {
    $GLOBALS['__doing_it_wrong'] = [];
    $types = array_map(static function (FieldTypeInterface $type) {
        return $type->key();
    }, forms_builtin_types()->all());

    return ['text', 'email', 'textarea', 'url', 'tel', 'number', 'date', 'time', 'select', 'radio', 'checkbox', 'acceptance', 'hidden', 'html'] === $types
        && [] === $GLOBALS['__doing_it_wrong'];
});

/* ── Stable field IDs ───────────────────────────────────────────────────── */

check('FieldIdResolver uses a valid author ID, else the item ID, and keeps IDs when rows are reordered', static function () {
    $name = ['_id' => 'a1b2c3d', 'custom_id' => 'name'];
    $plain = ['_id' => 'e4f5a6b', 'custom_id' => ''];
    $forward = FieldIdResolver::resolve([$name, $plain])['ids'];
    $reversed = FieldIdResolver::resolve([$plain, $name])['ids'];

    return ['name', 'e4f5a6b'] === $forward && ['e4f5a6b', 'name'] === $reversed;
});

check('FieldIdResolver reports an invalid author ID and falls back to the item ID', static function () {
    $result = FieldIdResolver::resolve([['_id' => 'abc1234', 'custom_id' => 'has space'], ['_id' => 'def5678', 'custom_id' => str_repeat('x', 65)]]);

    return ['abc1234', 'def5678'] === $result['ids'] && [0 => 'has space', 1 => str_repeat('x', 65)] === $result['invalid'];
});

check('FieldIdResolver gives a duplicated author ID to the first field only', static function () {
    $result = FieldIdResolver::resolve([['_id' => 'aaa1111', 'custom_id' => 'email'], ['_id' => 'bbb2222', 'custom_id' => 'email']]);

    return ['email', 'bbb2222'] === $result['ids'] && [1 => 'email'] === $result['duplicate'];
});

check('FieldIdResolver falls back to the position only for malformed rows, and never repeats an ID', static function () {
    $ids = FieldIdResolver::resolve([[], 'not-a-row', ['custom_id' => 'field_0']])['ids'];

    return 'field_0' === $ids[0] && 'field_1' === $ids[1] && count(array_unique($ids)) === 3;
});

/* ── Field types: today's semantics ─────────────────────────────────────── */

check('Text rejects arrays, NUL bytes, line breaks and oversized values, and sanitizes the rest', static function () {
    return [false, 'Invalid field value.'] === forms_check('text', [], ['x'])
        && [false, 'Invalid field value.'] === forms_check('text', [], "a\0b")
        && [false, 'Invalid field value.'] === forms_check('text', [], "a\r\nBcc: x@example.invalid")
        && [false, 'Invalid field value.'] === forms_check('text', [], str_repeat('a', 16385))
        && [true, 'Ada'] === forms_check('text', [], ' <b>Ada</b> ');
});

check('Required applies to empty and whitespace-only values; optional empty values pass as ""', static function () {
    return [false, 'This field is required.'] === forms_check('text', ['required' => 'yes'], '   ')
        && [false, 'This field is required.'] === forms_check('email', ['required' => 'yes'], null)
        && [true, ''] === forms_check('text', [], null);
});

check('Email validates the address; Tel accepts any single line; Textarea keeps line breaks', static function () {
    return [false, 'Please enter a valid email address.'] === forms_check('email', [], 'not-an-email')
        && [true, 'ada@example.com'] === forms_check('email', [], ' ada@example.com ')
        && [true, '+1 (555) 010-0100'] === forms_check('tel', [], '+1 (555) 010-0100')
        && forms_check('textarea', [], "Hello\nWorld")[0];
});

check('Select and Radio accept only a configured value (Label|value, CRLF-safe, blank lines ignored)', static function () {
    $options = ['options' => "Basic|basic\r\n\r\nPro | pro \nEnterprise"];

    return [true, 'pro'] === forms_check('select', $options, 'pro')
        && [true, 'Enterprise'] === forms_check('radio', $options, 'Enterprise')
        && [false, 'Choose one of the offered options.'] === forms_check('select', $options, 'Pro')
        && [true, ''] === forms_check('radio', $options, '')
        && [false, 'This field is required.'] === forms_check('select', $options + ['required' => 'yes'], '');
});

check('Checkbox needs a list of configured values; required means at least one', static function () {
    $options = ['options' => "One|one\nTwo|two\nThree|three"];

    return [true, ['one', 'three']] === forms_check('checkbox', $options, ['one', 'three', 'one'])
        && [false, 'Invalid field value.'] === forms_check('checkbox', $options, 'one')
        && [false, 'Invalid field value.'] === forms_check('checkbox', $options, ['a' => 'one'])
        && [false, 'Invalid field value.'] === forms_check('checkbox', $options, [['nested']])
        && [false, 'Choose one of the offered options.'] === forms_check('checkbox', $options, ['four'])
        && [true, []] === forms_check('checkbox', $options, null)
        && [false, 'This field is required.'] === forms_check('checkbox', $options + ['required' => 'yes'], null)
        && [true, ['two']] === forms_check('checkbox', $options + ['required' => 'yes'], ['two']);
});

/* ── Field types: the new simple ones ───────────────────────────────────── */

check('URL accepts http(s) addresses only', static function () {
    return [true, 'https://example.com/a?b=1'] === forms_check('url', [], 'https://example.com/a?b=1')
        && !forms_check('url', [], 'not a url')[0]
        && !forms_check('url', [], 'javascript:alert(1)')[0]
        && !forms_check('url', [], 'ftp://example.com')[0];
});

check('Number enforces numeric input, min, max and step', static function () {
    $bounds = ['field_min' => 1, 'field_max' => 10];

    return [true, '5'] === forms_check('number', $bounds, '5')
        && [false, 'Enter a number of at most 10.'] === forms_check('number', $bounds, '11')
        && [false, 'Enter a number of at least 1.'] === forms_check('number', $bounds, '0')
        && [false, 'Enter a number.'] === forms_check('number', $bounds, 'abc')
        && [true, '2.5'] === forms_check('number', [], '2.5')
        && [false, 'Enter a number that matches the allowed steps.'] === forms_check('number', ['field_min' => 1, 'field_step' => 2], '4')
        && [true, '0.3'] === forms_check('number', ['field_step' => '0.1'], '0.3');
});

check('Date accepts real YYYY-MM-DD dates within the authored range', static function () {
    $range = ['min_date' => '2026-01-01', 'max_date' => '2026-12-31'];

    return [true, '2026-06-15'] === forms_check('date', $range, '2026-06-15')
        && [false, 'Enter a valid date.'] === forms_check('date', $range, '2026-02-30')
        && [false, 'Enter a valid date.'] === forms_check('date', $range, '15/06/2026')
        && [false, 'Choose a date on or after 2026-01-01.'] === forms_check('date', $range, '2025-12-31')
        && [false, 'Choose a date on or before 2026-12-31.'] === forms_check('date', $range, '2027-01-01')
        && [true, '1999-01-01'] === forms_check('date', ['min_date' => 'garbage'], '1999-01-01');
});

check('Time accepts 24-hour HH:MM only', static function () {
    return [true, '23:59'] === forms_check('time', [], '23:59')
        && !forms_check('time', [], '24:00')[0]
        && !forms_check('time', [], '7:30')[0]
        && !forms_check('time', [], '07:30 PM')[0];
});

check('Acceptance: required means ticked, the value is yes/no and the email shows it as accepted', static function () {
    $type = forms_builtin_types()->get('acceptance');
    $field = forms_field('acceptance');

    return [false, 'This field is required.'] === forms_check('acceptance', ['required' => 'yes'], null)
        && [true, 'yes'] === forms_check('acceptance', ['required' => 'yes'], 'on')
        && [true, 'no'] === forms_check('acceptance', [], null)
        && [false, 'Invalid field value.'] === forms_check('acceptance', [], 'yes please')
        && 'Accepted' === $type->format($field, 'yes')
        && 'Not accepted' === $type->format($field, 'no');
});

check('Hidden is never required and keeps single-line text; HTML submits nothing', static function () {
    $html = forms_builtin_types()->get('html')->validate(forms_field('html'), 'anything');

    return [true, ''] === forms_check('hidden', ['required' => 'yes'], null)
        && [true, 'spring-sale'] === forms_check('hidden', [], 'spring-sale')
        && !forms_check('hidden', [], "a\nb")[0]
        && $html->is_valid() && !$html->has_value();
});

/* ── Renderer ───────────────────────────────────────────────────────────── */

/** A form owned by post 7 with the given fields (rows, as a builder saves them). */
function forms_definition(array $rows, string $id = 'abc1234'): FormDefinition
{
    $ids = FieldIdResolver::resolve($rows)['ids'];
    $fields = [];

    foreach (array_values($rows) as $index => $row) {
        $fields[] = new Field($ids[$index], $row['type'], $row['label'] ?? '', $row);
    }

    return new FormDefinition('elementor', 7, $id, $fields, ['email'], []);
}

function forms_render(FormDefinition $form, array $view = []): string
{
    return (new FormRenderer(forms_builtin_types()))->render($form, $view);
}

check('Rendered forms post the source, owner, form ID and values under form_fields[<field ID>], with no nonce or token', static function () {
    FormRenderer::reset();
    $html = forms_render(forms_definition([['_id' => 'aaa1111', 'type' => 'text', 'label' => 'Name', 'custom_id' => 'name']]));

    return false !== strpos($html, 'name="action" value="elemacy_form_submit"')
        && false !== strpos($html, 'name="form_source" value="elementor"')
        && false !== strpos($html, 'name="form_owner" value="7"')
        && false !== strpos($html, 'name="form_id" value="abc1234"')
        && false !== strpos($html, 'name="form_fields[name]"')
        && false === strpos($html, '_wpnonce')
        && false === strpos($html, 'elemacy_form_context');
});

check('Two instances of a form on one page never repeat a DOM ID, and each label targets its own control', static function () {
    FormRenderer::reset();
    $form = forms_definition([['_id' => 'aaa1111', 'type' => 'text', 'label' => 'Name', 'custom_id' => 'name'], ['_id' => 'bbb2222', 'type' => 'radio', 'label' => 'Plan', 'options' => "A|a\nB|b"]]);
    $html = forms_render($form) . forms_render($form);
    preg_match_all('/\sid="([^"]+)"/', $html, $ids);
    preg_match_all('/<label[^>]*for="([^"]+)"/', $html, $targets);

    return count($ids[1]) > 4
        && count($ids[1]) === count(array_unique($ids[1]))
        && [] === array_diff($targets[1], $ids[1]);
});

check('Hidden labels stay in the markup for assistive technology; an unlabelled control gets an aria-label', static function () {
    FormRenderer::reset();
    $html = forms_render(forms_definition([
        ['_id' => 'aaa1111', 'type' => 'email', 'label' => 'Email', 'custom_id' => 'email'],
        ['_id' => 'bbb2222', 'type' => 'text', 'label' => '', 'placeholder' => 'Company', 'custom_id' => 'company'],
    ]), ['show_labels' => false]);

    return 1 === preg_match('/<label class="elemacy-form-label elemacy-screen-reader-text" for="[^"]+-email">Email<\/label>/', $html)
        && 1 === preg_match('/<input[^>]*name="form_fields\[company\]"[^>]*aria-label="Company"/', $html);
});

check('A required checkbox group marks the group, not each box; a required radio group may mark each radio', static function () {
    FormRenderer::reset();
    $html = forms_render(forms_definition([
        ['_id' => 'aaa1111', 'type' => 'checkbox', 'label' => 'Topics', 'required' => 'yes', 'options' => "One\nTwo\nThree"],
        ['_id' => 'bbb2222', 'type' => 'radio', 'label' => 'Plan', 'required' => 'yes', 'options' => "A\nB"],
    ]));
    preg_match_all('/<input[^>]*type="checkbox"[^>]*>/', $html, $boxes);
    preg_match_all('/<input[^>]*type="radio"[^>]*>/', $html, $radios);
    $required = static function (array $tags) {
        return count(preg_grep('/\srequired(\s|>)/', $tags));
    };

    return 3 === count($boxes[0]) && 0 === $required($boxes[0])
        && 2 === $required($radios[0])
        && 2 === substr_count($html, '<fieldset class="elemacy-form-group" data-required="true">')
        && 1 === preg_match('/<legend class="elemacy-form-label">Topics<\/legend>/', $html);
});

check('A select placeholder renders as an empty first option', static function () {
    FormRenderer::reset();
    $html = forms_render(forms_definition([['_id' => 'aaa1111', 'type' => 'select', 'label' => 'Plan', 'required' => 'yes', 'placeholder' => 'Choose a plan', 'options' => "Basic|basic\nPro|pro"]]));

    return 1 === preg_match('/<select[^>]*required[^>]*><option value="">Choose a plan<\/option><option value="basic">Basic<\/option>/', $html);
});

check('Every visible field links its error slot through aria-describedby; hidden and HTML fields have none', static function () {
    FormRenderer::reset();
    $html = forms_render(forms_definition([
        ['_id' => 'aaa1111', 'type' => 'number', 'label' => 'Age', 'custom_id' => 'age', 'field_min' => 18],
        ['_id' => 'bbb2222', 'type' => 'hidden', 'custom_id' => 'campaign', 'field_value' => 'spring'],
        ['_id' => 'ccc3333', 'type' => 'html', 'field_html' => '<p>Note</p>'],
    ]));

    preg_match('/<input[^>]*name="form_fields\[age\]"[^>]*>/', $html, $age);
    $age = $age[0] ?? '';

    return 1 === preg_match('/aria-describedby="([^"]+-age-error)"/', $age, $match)
        && false !== strpos($age, 'type="number"') && false !== strpos($age, 'min="18"') && false !== strpos($age, 'step="any"')
        && false !== strpos($html, 'id="' . $match[1] . '" role="alert" hidden')
        && 1 === preg_match('/<input type="hidden" id="[^"]+-campaign" name="form_fields\[campaign\]" value="spring">/', $html)
        && false !== strpos($html, '<p>Note</p>')
        && 1 === substr_count($html, 'class="elemacy-form-error"');
});

check('A field whose type is not registered renders nothing, and the rest of the form still renders', static function () {
    FormRenderer::reset();
    $html = forms_render(forms_definition([['_id' => 'aaa1111', 'type' => 'signature', 'label' => 'Sign'], ['_id' => 'bbb2222', 'type' => 'text', 'label' => 'Name']]));

    return false === strpos($html, 'Sign') && false !== strpos($html, '>Name</label>');
});

/* ── Elementor adapter (settings → controls, saved settings → definition) ── */

require_once __DIR__ . '/fixtures/elementor-controls-manager.php';

check('Builder-neutral settings of a fixture field type and action become the expected Elementor control arrays', static function () {
    $kinds = new FixtureFieldType('fixture_rating', [
        Setting::standard('placeholder'),
        Setting::number('fixture_scale', 'Scale')->with_default(5),
        Setting::select('fixture_shape', 'Shape', ['star' => 'Star', 'heart' => 'Heart'])->with_default('star'),
    ]);
    $other = new FixtureFieldType('fixture_note', [Setting::standard('placeholder'), Setting::switcher('fixture_loud', 'Loud')]);
    $action = new FixtureFormAction('crm', [
        Setting::text('crm_list', 'List')->with_placeholder('newsletter')->with_description('The list to add to'),
        Setting::textarea('crm_tags', 'Tags'),
    ]);

    $fields = \Elemacy\Modules\Widgets\Forms\ElementorSettingControls::field_settings([$kinds, $other]);
    $control = static function (string $key) use ($fields): array {
        return \Elemacy\Modules\Widgets\Forms\ElementorSettingControls::control($fields[$key]['setting'], ['condition' => ['type' => $fields[$key]['types']]]);
    };
    $action_controls = array_map([\Elemacy\Modules\Widgets\Forms\ElementorSettingControls::class, 'control'], $action->settings());

    return ['placeholder', 'fixture_scale', 'fixture_shape', 'fixture_loud'] === array_keys($fields)
        && ['label' => 'Placeholder', 'type' => 'text', 'condition' => ['type' => ['fixture_rating', 'fixture_note']]] === $control('placeholder')
        && ['label' => 'Scale', 'type' => 'number', 'default' => 5, 'condition' => ['type' => ['fixture_rating']]] === $control('fixture_scale')
        && ['label' => 'Shape', 'type' => 'select', 'default' => 'star', 'options' => ['star' => 'Star', 'heart' => 'Heart'], 'condition' => ['type' => ['fixture_rating']]] === $control('fixture_shape')
        && ['label' => 'Loud', 'type' => 'switcher', 'return_value' => 'yes', 'default' => '', 'condition' => ['type' => ['fixture_note']]] === $control('fixture_loud')
        && [
            ['label' => 'List', 'type' => 'text', 'description' => 'The list to add to', 'placeholder' => 'newsletter'],
            ['label' => 'Tags', 'type' => 'textarea', 'rows' => 5],
        ] === $action_controls;
});

check('ElementorFormDefinitions keys saved repeater rows by stable field ID and keeps the saved action order', static function () {
    $settings = [
        'form_fields' => [
            ['_id' => 'bbb2222', 'type' => 'email', 'label' => 'Email', 'custom_id' => 'email'],
            ['_id' => 'aaa1111', 'label' => 'Name', 'custom_id' => 'bad id!'],
            ['_id' => 'ccc3333', 'type' => 'text', 'label' => 'Again', 'custom_id' => 'email'],
        ],
        'submit_actions' => ['crm', 'email', 'crm'],
        'email_to' => 'team@example.invalid',
    ];
    $form = \Elemacy\Modules\Widgets\Forms\ElementorFormDefinitions::from_settings(42, 'f00d123', $settings);
    $types = array_map(static function (Field $field): string {
        return $field->type();
    }, $form->fields());

    return 'elementor' === $form->source() && 42 === $form->owner() && 'f00d123' === $form->id()
        && ['email' => 'email', 'aaa1111' => 'text', 'ccc3333' => 'text'] === $types
        && ['crm', 'email'] === $form->actions()
        && 'team@example.invalid' === $form->setting('email_to')
        && ['email'] === \Elemacy\Modules\Widgets\Forms\ElementorFormDefinitions::from_settings(1, 'x', ['submit_actions' => 'email'])->actions()
        && [] === \Elemacy\Modules\Widgets\Forms\ElementorFormDefinitions::from_settings(1, 'x', ['form_fields' => 'junk'])->fields();
});
