<?php

/**
 * Nested loops: item addresses, step resolvers, frames and the parent-aware
 * query dispatch. Included by run.php.
 */

use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\ItemAddress;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Support\LoopLimits;

/* ── Loop\Address\AddressStep / ItemAddress ──────────────── */

check('ItemAddress round-trips steps whose locators contain / : | . > and percent signs', static function () {
    $address = new ItemAddress([
        new AddressStep('post', '42'),
        new AddressStep('acf_row', '42|field_sec.1'),
        new AddressStep('acf_row', 'field_grp>field_items.0'),
        new AddressStep('acf_row', 'a/b:c%2Fd'),
    ]);

    $again = ItemAddress::from_canonical($address->canonical());

    return null !== $again
        && $again->canonical() === $address->canonical()
        && 4 === $again->depth()
        && 'a/b:c%2Fd' === $again->last()->locator()
        && 'acf_row' === $again->steps()[1]->kind()
        && 3 === substr_count($address->canonical(), '/');
});

check('ItemAddress refuses to grow past LoopLimits::MAX_DEPTH, and from_canonical() returns null for a too deep string', static function () {
    $address = new ItemAddress();
    for ($i = 0; $i < LoopLimits::MAX_DEPTH; $i++) {
        $address = $address->append(new AddressStep('post', (string) $i));
    }

    $threw = false;
    try {
        $address->append(new AddressStep('post', 'one-too-many'));
    } catch (\InvalidArgumentException $error) {
        $threw = true;
    }

    $too_deep = $address->canonical() . '/post:11';

    return $threw && LoopLimits::MAX_DEPTH === $address->depth() && null === ItemAddress::from_canonical($too_deep);
});

check('ItemAddress::from_canonical() returns null for malformed strings instead of a partial address', static function () {
    foreach (['post', 'post:', 'POST:1', 'post:1//post:2', ':1', 'post:1/bad kind:2'] as $bad) {
        if (null !== ItemAddress::from_canonical($bad)) {
            return false;
        }
    }

    return ItemAddress::from_canonical('')->is_empty();
});

check('ItemAddress parent()/last()/hash(): the parent drops the last step, the hash is a 12-character token, and opaque steps make it unrebuildable', static function () {
    $address = new ItemAddress([new AddressStep('post', '7'), AddressStep::opaque('ext-3')]);

    return 'post:7' === $address->parent()->canonical()
        && $address->last()->is_opaque()
        && !$address->is_rebuildable()
        && $address->parent()->is_rebuildable()
        && 1 === preg_match('/\A[0-9a-f]{12}\z/', $address->hash())
        && $address->hash() !== $address->parent()->hash()
        && !(new ItemAddress())->is_rebuildable();
});

/* ── Loop\Address\ItemAddressResolver / AddressStepResolverRegistry ──────────────── */

final class CountingStepResolver implements \Elemacy\Loop\Address\AddressStepResolverInterface
{
    public int $calls = 0;
    /** @var array<int, string|null> */
    public array $parents = [];
    private string $kind;
    /** @var string[] Locators that no longer resolve. */
    private array $gone;

    public function __construct(string $kind, array $gone = [])
    {
        $this->kind = $kind;
        $this->gone = $gone;
    }

    public function get_kind(): string
    {
        return $this->kind;
    }

    public function resolve(AddressStep $step, ?\Elemacy\Loop\Contracts\LoopItemInterface $parent): ?\Elemacy\Loop\Contracts\LoopItemInterface
    {
        $this->calls++;
        $this->parents[] = $parent ? $parent->get_identity() : null;

        return in_array($step->locator(), $this->gone, true) ? null : new FixedLoopItem($this->kind . '-' . $step->locator());
    }

    public function fingerprint(\Elemacy\Loop\Contracts\LoopItemInterface $item): ?string
    {
        return 'fp-' . $item->get_identity();
    }
}

check('ItemAddressResolver rebuilds a valid chain root first, giving each step its rebuilt parent', static function () {
    $registry = new \Elemacy\Loop\Address\AddressStepResolverRegistry();
    $posts = new CountingStepResolver('post');
    $rows = new CountingStepResolver('acf_row');
    $registry->register($posts)->register($rows);

    $address = new ItemAddress([new AddressStep('post', '42'), new AddressStep('acf_row', '42|field_sec.1'), new AddressStep('acf_row', 'field_items.0')]);
    $items = (new \Elemacy\Loop\Address\ItemAddressResolver($registry))->resolve($address);

    return is_array($items) && 3 === count($items)
        && 'post-42' === $items[0]->get_identity()
        && 'acf_row-field_items.0' === $items[2]->get_identity()
        && [null] === $posts->parents
        && ['post-42', 'acf_row-42|field_sec.1'] === $rows->parents;
});

check('ItemAddressResolver stops at the first step that no longer resolves, and refuses opaque or unregistered kinds', static function () {
    $registry = new \Elemacy\Loop\Address\AddressStepResolverRegistry();
    $rows = new CountingStepResolver('acf_row', ['field_items.9']);
    $registry->register(new CountingStepResolver('post'))->register($rows);
    $resolver = new \Elemacy\Loop\Address\ItemAddressResolver($registry);

    $gone = $resolver->resolve(new ItemAddress([new AddressStep('post', '1'), new AddressStep('acf_row', 'field_items.9'), new AddressStep('acf_row', 'never.0')]));
    $opaque = $resolver->resolve(new ItemAddress([new AddressStep('post', '1'), AddressStep::opaque('x')]));
    $unknown = $resolver->resolve(new ItemAddress([new AddressStep('meta_box_row', 'a.0')]));

    return null === $gone && 1 === $rows->calls && null === $opaque && null === $unknown;
});

check('ItemAddressResolver memoizes rebuilt prefixes within one instance, so a second resolve of a chain costs no resolver calls', static function () {
    $registry = new \Elemacy\Loop\Address\AddressStepResolverRegistry();
    $posts = new CountingStepResolver('post');
    $rows = new CountingStepResolver('acf_row');
    $registry->register($posts)->register($rows);
    $resolver = new \Elemacy\Loop\Address\ItemAddressResolver($registry);

    $first = $resolver->resolve(new ItemAddress([new AddressStep('post', '5'), new AddressStep('acf_row', '5|f.0')]));
    $sibling = $resolver->resolve(new ItemAddress([new AddressStep('post', '5'), new AddressStep('acf_row', '5|f.1')]));
    $again = $resolver->resolve(new ItemAddress([new AddressStep('post', '5'), new AddressStep('acf_row', '5|f.0')]));

    return null !== $first && null !== $sibling && $first[1] === $again[1] && 1 === $posts->calls && 2 === $rows->calls;
});

check('ItemAddressResolver::fingerprint() changes when any step\'s fingerprint changes, and the registry refuses a different second resolver for one kind', static function () {
    $registry = new \Elemacy\Loop\Address\AddressStepResolverRegistry();
    $registry->register(new CountingStepResolver('post'));
    $resolver = new \Elemacy\Loop\Address\ItemAddressResolver($registry);
    $address = new ItemAddress([new AddressStep('post', '5')]);

    $a = $resolver->fingerprint($address, [new FixedLoopItem('one')]);
    $b = $resolver->fingerprint($address, [new FixedLoopItem('two')]);

    $threw = false;
    try {
        $registry->register(new CountingStepResolver('post', ['a different resolver for the same kind']));
    } catch (\Throwable $error) {
        $threw = true;
    }

    return $a !== $b && $a === $resolver->fingerprint($address, [new FixedLoopItem('one')]) && $threw;
});

/* ── Loop\LoopContext frames ──────────────── */

final class RecordingAddressableItem implements \Elemacy\Loop\Contracts\LoopItemInterface, \Elemacy\Loop\Contracts\AddressableItemInterface
{
    /** @var string[] */
    public static array $log = [];
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function get_identity(): string
    {
        return $this->id;
    }

    public function get_address_step(): AddressStep
    {
        return new AddressStep('post', $this->id);
    }

    public function get_field(string $key)
    {
        return $this->id . ':' . $key;
    }

    public function enter(): void
    {
        self::$log[] = 'enter ' . $this->id;
    }

    public function exit(): void
    {
        self::$log[] = 'exit ' . $this->id;
    }
}

check('LoopContext::push() composes each frame\'s address from its parent\'s, and an item without a step gets an opaque one', static function () {
    $outer = LoopContext::push(new RecordingAddressableItem('1'), 'posts', 10, 0, 'k1');
    $middle = LoopContext::push(new FixedLoopItem('ext-7'), 'ext');
    $inner = LoopContext::push(new RecordingAddressableItem('3'));

    $ok = 'post:1' === $outer->address()->canonical()
        && 'post:1/opaque:ext-7' === $middle->address()->canonical()
        && 'post:1/opaque:ext-7/post:3' === $inner->address()->canonical()
        && !$inner->address()->is_rebuildable()
        && 'posts' === $outer->source_key() && 10 === $outer->template_id() && 'k1' === $outer->render_key()
        && LoopContext::current_frame() === $inner
        && LoopContext::ancestor(2) === $outer
        && null === LoopContext::ancestor(3);

    LoopContext::pop();
    LoopContext::pop();
    LoopContext::pop();

    return $ok && [] === LoopContext::frames() && null === LoopContext::current_frame();
});

check('LoopContext gives frames past the nesting limit no address instead of failing the render', static function () {
    for ($i = 0; $i < LoopLimits::MAX_DEPTH; $i++) {
        LoopContext::push(new RecordingAddressableItem((string) $i));
    }

    $deep = LoopContext::push(new RecordingAddressableItem('deep'));
    $deeper = LoopContext::push(new RecordingAddressableItem('deeper'));
    $ok = null === $deep->address() && null === $deeper->address()
        && LoopLimits::MAX_DEPTH === LoopContext::ancestor(2)->address()->depth();

    for ($i = 0; $i < LoopLimits::MAX_DEPTH + 2; $i++) {
        LoopContext::pop();
    }

    return $ok && [] === LoopContext::frames();
});

check('LoopContext::at_level() makes the ancestor current while reading, then restores the stack, also when the read throws', static function () {
    RecordingAddressableItem::$log = [];
    $outer = new RecordingAddressableItem('outer');
    $inner = new RecordingAddressableItem('inner');
    LoopContext::push($outer);
    LoopContext::push($inner);

    $read = LoopContext::at_level(1, static fn() => LoopContext::current()->get_field('title'));
    $current = LoopContext::at_level(0, static fn() => LoopContext::current()->get_field('title'));
    $missing = LoopContext::at_level(2, static fn() => 'never read');

    $threw = false;
    try {
        LoopContext::at_level(1, static function () {
            throw new \RuntimeException('render failed');
        });
    } catch (\RuntimeException $error) {
        $threw = true;
    }

    $ok = 'outer:title' === $read && 'inner:title' === $current && null === $missing && $threw
        && LoopContext::current() === $inner && 2 === count(LoopContext::frames())
        && ['enter outer', 'exit outer', 'enter outer', 'exit outer'] === RecordingAddressableItem::$log;

    LoopContext::pop();
    LoopContext::pop();

    return $ok;
});

/* ── Loop\LoopQueryDispatcher ──────────────── */

class SettingsRecordingSource implements \Elemacy\Loop\Contracts\LoopDataSourceInterface
{
    /** @var array[] */
    public array $settings_seen = [];

    public function get_key(): string
    {
        return 'recording';
    }

    public function get_label(): string
    {
        return 'Recording';
    }

    public function register_controls($widget): void
    {
    }

    public function get_items(array $settings): \Elemacy\Loop\DTO\LoopResultDTO
    {
        $this->settings_seen[] = $settings;

        return new \Elemacy\Loop\DTO\LoopResultDTO([new FixedLoopItem('a')], 1, 1, true);
    }

    public function register_preview_controls($document): void
    {
    }

    public function resolve_preview_item(array $settings): ?\Elemacy\Loop\Contracts\LoopItemInterface
    {
        return null;
    }
}

final class QueryRecordingSource extends SettingsRecordingSource implements \Elemacy\Loop\Contracts\ContextualLoopSourceInterface
{
    /** @var \Elemacy\Loop\LoopQuery[] */
    public array $queries = [];

    public function query(\Elemacy\Loop\LoopQuery $query): \Elemacy\Loop\DTO\LoopResultDTO
    {
        $this->queries[] = $query;

        return new \Elemacy\Loop\DTO\LoopResultDTO([], 0, 0, false, 'Place this loop inside a repeater row.');
    }
}

check('LoopQueryDispatcher gives a source without the contextual contract exactly its settings through get_items()', static function () {
    $source = new SettingsRecordingSource();
    $settings = ['posts_per_page' => 3, 'paged' => 2];
    LoopContext::push(new RecordingAddressableItem('9'));
    $result = \Elemacy\Loop\LoopQueryDispatcher::items($source, $settings, 2);
    LoopContext::pop();

    return [$settings] === $source->settings_seen && 1 === count($result->items) && null === $result->notice;
});

check('LoopQueryDispatcher gives a contextual source one LoopQuery carrying its settings, page, preview flag and the enclosing frame', static function () {
    $source = new QueryRecordingSource();
    $outer = LoopContext::push(new RecordingAddressableItem('9'));
    $nested = \Elemacy\Loop\LoopQueryDispatcher::items($source, ['f' => 'items'], 3, true);
    LoopContext::pop();
    \Elemacy\Loop\LoopQueryDispatcher::items($source, ['f' => 'top']);

    [$inside, $top] = $source->queries;

    return [] === $source->settings_seen
        && ['f' => 'items'] === $inside->settings() && $outer === $inside->parent() && 3 === $inside->page() && $inside->is_preview()
        && null === $top->parent() && 1 === $top->page() && !$top->is_preview()
        && 'Place this loop inside a repeater row.' === $nested->notice;
});

/* ── Loop\Preview\LoopItemPreviewContextProvider: nested items ──────────────── */

final class NestedPreviewItem implements \Elemacy\Loop\Contracts\LoopItemInterface, \Elemacy\Loop\Contracts\NestedItemInterface
{
    /** @var string[] */
    public static array $log = [];
    private string $id;
    private ?self $parent;
    private bool $fail;

    public function __construct(string $id, ?self $parent = null, bool $fail = false)
    {
        $this->id = $id;
        $this->parent = $parent;
        $this->fail = $fail;
    }

    public function get_identity(): string
    {
        return $this->id;
    }

    public function get_parent_item(): ?\Elemacy\Loop\Contracts\LoopItemInterface
    {
        return $this->parent;
    }

    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
        if ($this->fail) {
            throw new \RuntimeException('gone');
        }
        self::$log[] = 'enter ' . $this->id . ' at depth ' . count(LoopContext::frames());
    }

    public function exit(): void
    {
        self::$log[] = 'exit ' . $this->id;
    }
}

check('LoopItemPreviewContextProvider enters a nested item\'s ancestors first, each frame pushed before its enter(), and unwinds in reverse', static function () {
    NestedPreviewItem::$log = [];
    $section = new NestedPreviewItem('section');
    $item = new NestedPreviewItem('item', $section);
    $tag = new NestedPreviewItem('tag', $item);
    $provider = new \Elemacy\Loop\Preview\LoopItemPreviewContextProvider();

    $token = $provider->enter($tag);
    $inside = LoopContext::current() === $tag && LoopContext::ancestor(2)->item() === $section;
    $provider->leave($token);

    return $inside && [$section, $item, $tag] === $token && [] === LoopContext::frames()
        && ['enter section at depth 1', 'enter item at depth 2', 'enter tag at depth 3', 'exit tag', 'exit item', 'exit section'] === NestedPreviewItem::$log;
});

check('LoopItemPreviewContextProvider unwinds the ancestors it entered when a nested item fails to enter, leaving no frame behind', static function () {
    NestedPreviewItem::$log = [];
    $section = new NestedPreviewItem('section');
    $gone = new NestedPreviewItem('gone', $section, true);
    $provider = new \Elemacy\Loop\Preview\LoopItemPreviewContextProvider();

    $token = $provider->enter($gone);
    $provider->leave($token);

    return [] === $token && [] === LoopContext::frames() && ['enter section at depth 1', 'exit section'] === NestedPreviewItem::$log;
});
