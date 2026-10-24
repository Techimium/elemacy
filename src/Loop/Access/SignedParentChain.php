<?php

namespace Elemacy\Loop\Access;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Address\ItemAddress;
use Elemacy\Loop\Address\ItemAddressResolver;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;
use Elemacy\Loop\Rendering\TemplateRecursionException;
use Elemacy\Loop\Rendering\TemplateRecursionGuard;
use Throwable;

/**
 * Puts a later-page render back inside the loop items its first page rendered
 * inside: rebuilds the signed parent chain, refuses it when any item is gone,
 * no longer public or changed since the token was signed, then enters every
 * item root first with its template on the lineage stacks, exactly as the
 * first page had them. leave() undoes all of it.
 */
final class SignedParentChain
{
    /** @var \Elemacy\Loop\Contracts\LoopItemInterface[] Entered items, outermost first. */
    private array $entered = [];
    private int $frames = 0;
    private int $templates = 0;
    private bool $assumed = false;
    private ?int $previous_post = null;

    /**
     * @param array               $facts Facts from a verified PaginationContextSigner token.
     * @param ItemAddressResolver $resolver A resolver for this request.
     * @throws ValidationException When the chain cannot be rebuilt as signed.
     */
    public static function enter(array $facts, ItemAddressResolver $resolver): self
    {
        $chain = new self();

        if (is_int($facts[HostFacts::POST_ID] ?? null)) {
            $chain->previous_post = HostFacts::assume_post($facts[HostFacts::POST_ID]);
            $chain->assumed = true;
        }

        if (!array_key_exists(HostFacts::PARENT, $facts)) {
            return $chain;
        }

        try {
            $chain->enter_parent($facts, $resolver);
        } catch (Throwable $error) {
            $chain->leave();

            throw PublicRenderPolicy::unavailable();
        }

        return $chain;
    }

    public function leave(): void
    {
        foreach (array_reverse($this->entered) as $item) {
            $item->exit();
        }
        $this->entered = [];

        for (; $this->frames > 0; $this->frames--) {
            LoopContext::pop();
        }

        for (; $this->templates > 0; $this->templates--) {
            LoopTemplateRenderStack::pop();
            TemplateRecursionGuard::exit();
        }

        if ($this->assumed) {
            HostFacts::assume_post($this->previous_post);
            $this->assumed = false;
        }
    }

    /**
     * @throws ValidationException When the chain no longer rebuilds or no longer matches its signed content.
     * @throws TemplateRecursionException When the signed lineage would exceed the nesting limit.
     */
    private function enter_parent(array $facts, ItemAddressResolver $resolver): void
    {
        $address = ItemAddress::from_canonical((string) $facts[HostFacts::PARENT]);
        $items = $address ? $resolver->resolve($address) : null;
        $lineage = array_values((array) ($facts['lineage'] ?? []));

        if (
            null === $items
            || count($items) !== count($lineage)
            || !hash_equals($resolver->fingerprint($address, $items), (string) $facts[HostFacts::PARENT_FP])
        ) {
            throw PublicRenderPolicy::unavailable();
        }

        foreach ($lineage as $template_id) {
            TemplateRecursionGuard::enter((int) $template_id);
            LoopTemplateRenderStack::push((int) $template_id);
            ++$this->templates;
        }

        $last = count($items) - 1;

        foreach ($items as $index => $item) {
            $frame = LoopContext::push($item, '', (int) $lineage[$index], 0, $index === $last ? (string) $facts[HostFacts::PARENT_RENDER] : '');
            ++$this->frames;

            $item->enter();
            $this->entered[] = $item;
        }

        // The rebuilt items must compose back into exactly the signed address.
        if (null === $frame->address() || $frame->address()->canonical() !== $address->canonical()) {
            throw PublicRenderPolicy::unavailable();
        }
    }
}
