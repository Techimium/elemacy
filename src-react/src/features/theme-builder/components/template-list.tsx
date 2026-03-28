import { __ } from "@wordpress/i18n";
import { useState } from "react";
import type { Template } from "../schemas/template";
import { useDeleteTemplateMutation, useTemplates } from "../services/template";
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


function TemplateList({ setIsOpen }: { setIsOpen: (open: boolean) => void }) {
    const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
    const [deletingTemplate, setDeletingTemplate] = useState<Template | null>(null);
    const [isEditOpen, setIsEditOpen] = useState(false);

    const { data: templates, isLoading } = useTemplates();
    const { mutateAsync: deleteTemplate, isPending: isDeleting } = useDeleteTemplateMutation();

    const handleEdit = (template: Template) => {
        setEditingTemplate(template);
        setIsEditOpen(true);
    };

    const handleDelete = (template: Template) => {
        setDeletingTemplate(template);
    };

    const confirmDelete = async () => {
        if (!deletingTemplate) return;
        await deleteTemplate(deletingTemplate.id);
        setDeletingTemplate(null);
    };

    if (isLoading) {
        return (
            <div className="flex justify-center items-center h-64">
                <Spinner />
            </div>
        );
    }
    return (
        <>
            {templates && templates.length > 0 ? (
                <div
                    className={`grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4`}
                >
                    {templates.map((template) => (
                        <TemplateCard
                            key={template.id}
                            template={template}
                            onEdit={handleEdit}
                            onEditWithElementor={() => window.open(template.edit_with_elementor, '_blank')}
                            onDelete={handleDelete}
                        />
                    ))}
                </div>
            ) : (
                <Card>
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia className="w-16 h-16" variant="icon">
                                <LayoutTemplateIcon />
                            </EmptyMedia>
                            <EmptyTitle>{__('No Templates Yet', 'elemacy')}</EmptyTitle>
                            <EmptyDescription>
                                {__("You haven't created any templates yet. Get started by creating your first template.", 'elemacy')}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <div className="flex gap-2">
                                <Button onClick={() => setIsOpen(true)}>{__('Create Template', 'elemacy')}</Button>
                                <Button variant="outline">{__('Import Template', 'elemacy')}</Button>
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
