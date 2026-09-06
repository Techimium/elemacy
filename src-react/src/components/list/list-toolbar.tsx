import { __ } from "@wordpress/i18n";
import { SearchIcon } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";

export interface FilterOption {
    value: string;
    label: string;
}

interface ListToolbarProps {
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    filters?: FilterOption[];
    filter?: string;
    onFilterChange?: (value: string) => void;
}

/**
 * Shared header for the template-library list pages: an optional type-filter tab
 * row on the left and a search box on the right. Both drive server-side queries.
 */
export function ListToolbar({
    search,
    onSearchChange,
    searchPlaceholder,
    filters,
    filter,
    onFilterChange,
}: ListToolbarProps) {
    const hasFilters = filters && filters.length > 0 && filter !== undefined && onFilterChange;

    return (
        <div className="flex items-center justify-between gap-4">
            {hasFilters ? (
                <Tabs value={filter} onValueChange={onFilterChange}>
                    <TabsList>
                        {filters.map((option) => (
                            <TabsTrigger key={option.value} value={option.value}>
                                {option.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            ) : (
                <div />
            )}

            <div className="relative w-full max-w-xs">
                <SearchIcon className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder={searchPlaceholder ?? __("Search…", "elemacy")}
                    className="pl-9"
                />
            </div>
        </div>
    );
}
