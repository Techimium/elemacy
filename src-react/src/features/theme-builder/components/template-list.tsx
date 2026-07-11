import { __ } from "@wordpress/i18n";
import { useState } from "react";
import type { Template } from "../schemas/template";
import {
    useDeleteTemplateMutation,
    useDuplicateTemplateMutation,
    useTemplates,
    useTemplateTypes,
} from "../services/template";
import TemplateCard from "./template-card";
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from "@/components/ui/empty";
import { Card } from "@/components/ui/card";
import { LayoutTemplateIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { EditTemplateModal } from "./edit-template-modal";
import { DeleteTemplateDialog } from "./delete-template-dialog";
import Spinner from "@/components/spinner";
import { ListToolbar, type FilterOption } from "@/components/list/list-toolbar";
import { InfiniteScrollSentinel } from "@/components/list/infinite-scroll-sentinel";
import { useDebounce } from "@/hooks/use-debounce";

const ALL_FILTER = "all";

function TemplateList({ setIsOpen }: { setIsOpen: (open: boolean) => void }) {
    const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
    const [deletingTemplate, setDeletingTemplate] = useState<Template | null>(null);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [filter, setFilter] = useState<string>(ALL_FILTER);
    const [search, setSearch] = useState("");
    const debouncedSearch = useDebounce(search, 300);

    const { data: types } = useTemplateTypes();
    const filters: FilterOption[] = [
        { value: ALL_FILTER, label: __("All", "elemacy") },
        ...(types ?? []).map((type) => ({ value: type.value, label: type.label })),
    ];

    const {
        data,
        isLoading,
        fetchNextPage,
        hasNextPage,
        isFetchingNextPage,
    } = useTemplates({
        search: debouncedSearch || undefined,
        type: filter === ALL_FILTER ? undefined : filter,
    });

    const { mutateAsync: deleteTemplate, isPending: isDeleting } = useDeleteTemplateMutation();
    const { mutate: duplicateTemplate } = useDuplicateTemplateMutation();

    const templates = data?.pages.flatMap((page) => page.results) ?? [];

    const handleEdit = (template: Template) => {
        setEditingTemplate(template);
        setIsEditOpen(true);
    };

    const confirmDelete = async () => {
        if (!deletingTemplate) return;
        await deleteTemplate(deletingTemplate.id);
        setDeletingTemplate(null);
    };

    return (
        <>
            <ListToolbar
                search={search}
                onSearchChange={setSearch}
                searchPlaceholder={__("Search templates…", "elemacy")}
                filters={filters}
                filter={filter}
                onFilterChange={setFilter}
            />

            {isLoading ? (
                <div className="flex justify-center items-center h-64">
                    <Spinner />
                </div>
            ) : templates.length > 0 ? (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        {templates.map((template) => (
                            <TemplateCard
                                key={template.id}
                                template={template}
                                onEdit={handleEdit}
                                onEditWithElementor={() => window.open(template.edit_with_elementor, '_blank')}
                                onDuplicate={(template) => duplicateTemplate(template.id)}
                                onDelete={(template) => setDeletingTemplate(template)}
                            />
                        ))}
                    </div>
                    <InfiniteScrollSentinel
                        onLoadMore={fetchNextPage}
                        hasMore={hasNextPage}
                        isLoading={isFetchingNextPage}
                    />
                </>
            ) : (
                <Card>
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia className="w-16 h-16" variant="icon">
                                <LayoutTemplateIcon />
                            </EmptyMedia>
                            <EmptyTitle>{__('No Templates Yet', 'elemacy')}</EmptyTitle>
                            <EmptyDescription>
                                {filter === ALL_FILTER && !debouncedSearch
                                    ? __("You haven't created any templates yet. Get started by creating your first template.", 'elemacy')
                                    : __('No templates match your search or filter.', 'elemacy')}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <div className="flex gap-2">
                                <Button className="cursor-pointer" onClick={() => setIsOpen(true)}>{__('Create Template', 'elemacy')}</Button>
                            </div>
                        </EmptyContent>
                    </Empty>
                </Card>
            )}

            <EditTemplateModal
                template={editingTemplate}
                open={isEditOpen}
                onOpenChange={setIsEditOpen}
            />

            <DeleteTemplateDialog
                open={!!deletingTemplate}
                onOpenChange={(open) => !open && setDeletingTemplate(null)}
                onConfirm={confirmDelete}
                templateName={deletingTemplate?.title}
                isDeleting={isDeleting}
            />
        </>
    );
}

export default TemplateList;
