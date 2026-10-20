import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { useConditionSearch, useConditionValueLabel } from '@/services/conditions';
import { useDebounce } from '@/hooks/use-debounce';

interface AsyncComboboxProps {
    /** Condition name — scopes the server-side search. */
    condition: string;
    /** Currently selected id (empty string = "All"). */
    value: string;
    onChange: (value: string) => void;
    /** The condition always names a value: no "All" choice is offered. */
    required?: boolean;
}

interface Option {
    value: string;
    label: string;
}

/**
 * Debounced, server-backed value picker for `search`-type conditions, following
 * the WAI-ARIA combobox pattern: the search input owns a listbox of options,
 * Up/Down move through them, Enter picks one and Escape closes without
 * changing the value. Focus returns to the picker once it closes. The listbox
 * is a Radix popover layer, so inside a dialog, Escape and outside clicks
 * close the picker and leave the dialog open.
 */
export function AsyncCombobox({ condition, value, onChange, required = false }: AsyncComboboxProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const debouncedQuery = useDebounce(query, 300);
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const restoreFocus = useRef(false);
    const listboxId = useId();
    const optionId = (index: number) => `${listboxId}-option-${index}`;

    const { data: results = [], isFetching } = useConditionSearch(condition, debouncedQuery, open);
    const { data: hydrated = [], isLoading: isLoadingLabel } = useConditionValueLabel(condition, value);

    const options: Option[] = required ? results : [{ value: '', label: __('All', 'elemacy') }, ...results];
    const emptyLabel = required ? __('Choose a post…', 'elemacy') : __('All', 'elemacy');
    // The server names a saved item that no longer exists itself; this covers
    // a lookup that failed outright, so the picker never waits forever.
    const fallbackLabel = isLoadingLabel
        ? __('Loading…', 'elemacy')
        /* translators: %s: ID of the saved item. */
        : sprintf(__('#%s (unavailable)', 'elemacy'), value);
    const selectedLabel = hydrated.find((o) => o.value === value)?.label ?? fallbackLabel;

    const close = (focusTrigger: boolean) => {
        restoreFocus.current = focusTrigger;
        setOpen(false);
        setQuery('');
    };

    useEffect(() => {
        if (!open && restoreFocus.current) {
            restoreFocus.current = false;
            triggerRef.current?.focus();
        }
    }, [open]);

    // A new result set starts from its first entry.
    useEffect(() => setActive(0), [debouncedQuery, results.length]);

    const select = (next: string) => {
        onChange(next);
        close(true);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setActive((index) => Math.min(index + 1, options.length - 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                setActive((index) => Math.max(index - 1, 0));
                break;
            case 'Enter':
                event.preventDefault();
                if (options[active]) {
                    select(options[active].value);
                }
                break;
        }
    };

    return (
        <Popover open={open} onOpenChange={(next) => !next && close(true)}>
            <PopoverAnchor asChild>
                <div ref={containerRef} className="relative flex-1 min-w-0">
                    {open ? (
                        <Input
                            autoFocus
                            role="combobox"
                            aria-expanded="true"
                            aria-controls={listboxId}
                            aria-autocomplete="list"
                            aria-activedescendant={options[active] ? optionId(active) : undefined}
                            aria-label={__('Search for a value', 'elemacy')}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder={__('Search…', 'elemacy')}
                        />
                    ) : (
                        <button
                            ref={triggerRef}
                            type="button"
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            onClick={() => setOpen(true)}
                            className={cn(
                                'border-input flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]'
                            )}
                        >
                            <span className={cn('truncate', !value && 'text-muted-foreground')}>
                                {value ? selectedLabel : emptyLabel}
                            </span>
                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
                        </button>
                    )}
                </div>
            </PopoverAnchor>
            <PopoverContent
                className="w-(--radix-popover-trigger-width) p-1"
                // Focus stays in the search input while the list is open.
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                // The search input sits outside the list but is part of the picker.
                onInteractOutside={(event) => {
                    if (containerRef.current?.contains(event.target as Node)) event.preventDefault();
                }}
            >
                <ul id={listboxId} role="listbox" aria-label={__('Values', 'elemacy')} className="max-h-60 overflow-auto">
                    {options.map((option, index) => (
                        <ComboboxOption
                            key={option.value || '__any__'}
                            id={optionId(index)}
                            label={option.label}
                            selected={option.value === value}
                            active={index === active}
                            onSelect={() => select(option.value)}
                            onHover={() => setActive(index)}
                        />
                    ))}
                    {isFetching && (
                        <li role="presentation" className="text-muted-foreground flex items-center gap-2 px-2 py-1.5 text-sm">
                            <Loader2 className="size-3.5 animate-spin" aria-hidden />
                            {__('Searching…', 'elemacy')}
                        </li>
                    )}
                    {!isFetching && results.length === 0 && (
                        <li role="presentation" className="text-muted-foreground px-2 py-1.5 text-sm">{__('No results', 'elemacy')}</li>
                    )}
                </ul>
            </PopoverContent>
        </Popover>
    );
}

function ComboboxOption({
    id,
    label,
    selected,
    active,
    onSelect,
    onHover,
}: {
    id: string;
    label: string;
    selected: boolean;
    active: boolean;
    onSelect: () => void;
    onHover: () => void;
}) {
    return (
        <li
            id={id}
            role="option"
            aria-selected={selected}
            // Keep focus in the search input; a click still selects.
            onMouseDown={(event) => event.preventDefault()}
            onClick={onSelect}
            onMouseEnter={onHover}
            className={cn(
                'flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm',
                active && 'bg-accent text-accent-foreground'
            )}
        >
            <Check className={cn('size-4 shrink-0', selected ? 'opacity-100' : 'opacity-0')} aria-hidden />
            <span className="truncate">{label}</span>
        </li>
    );
}
