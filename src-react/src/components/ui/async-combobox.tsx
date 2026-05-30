import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { useConditionSearch, useConditionValueLabel } from '@/features/theme-builder/services/conditions';
import { useDebounce } from '@/hooks/use-debounce';

// Mirrors the sentinel used by the static value select: empty value means "Any".
const ANY_VALUE = '__any__';

interface AsyncComboboxProps {
    /** Condition name — scopes the server-side search. */
    condition: string;
    /** Currently selected id (empty string = "Any"). */
    value: string;
    onChange: (value: string) => void;
}

/**
 * Debounced, server-backed value picker for `search`-type conditions. Built from
 * the base Input + a positioned results list so it needs no extra dependencies.
 */
export function AsyncCombobox({ condition, value, onChange }: AsyncComboboxProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const debouncedQuery = useDebounce(query, 300);
    const containerRef = useRef<HTMLDivElement>(null);

    const { data: results = [], isFetching } = useConditionSearch(condition, debouncedQuery, open);
    const { data: hydrated = [] } = useConditionValueLabel(condition, value);

    const selectedLabel = hydrated.find((o) => o.value === value)?.label ?? '';

    // Close on outside click.
    useEffect(() => {
        if (!open) return;
        const onClick = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, [open]);

    const select = (next: string) => {
        onChange(next === ANY_VALUE ? '' : next);
        setOpen(false);
        setQuery('');
    };

    return (
        <div ref={containerRef} className="relative flex-1 min-w-0">
            {open ? (
                <Input
                    autoFocus
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={__('Search…', 'elemacy')}
                />
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        'border-input flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]'
                    )}
                >
                    <span className={cn('truncate', !value && 'text-muted-foreground')}>
                        {value ? selectedLabel || __('Loading…', 'elemacy') : __('Any', 'elemacy')}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
                </button>
            )}

            {open && (
                <ul className="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border p-1 shadow-md">
                    <ComboboxOption label={__('Any', 'elemacy')} selected={value === ''} onSelect={() => select(ANY_VALUE)} />
                    {isFetching && (
                        <li className="text-muted-foreground flex items-center gap-2 px-2 py-1.5 text-sm">
                            <Loader2 className="size-3.5 animate-spin" aria-hidden />
                            {__('Searching…', 'elemacy')}
                        </li>
                    )}
                    {!isFetching && results.length === 0 && (
                        <li className="text-muted-foreground px-2 py-1.5 text-sm">{__('No results', 'elemacy')}</li>
                    )}
                    {results.map((option) => (
                        <ComboboxOption
                            key={option.value}
                            label={option.label}
                            selected={option.value === value}
                            onSelect={() => select(option.value)}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}

function ComboboxOption({
    label,
    selected,
    onSelect,
}: {
    label: string;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <li>
            <button
                type="button"
                onClick={onSelect}
                className="hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
            >
                <Check className={cn('size-4 shrink-0', selected ? 'opacity-100' : 'opacity-0')} aria-hidden />
                <span className="truncate">{label}</span>
            </button>
        </li>
    );
}
