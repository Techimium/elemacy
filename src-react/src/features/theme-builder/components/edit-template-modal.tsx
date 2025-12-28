import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { TemplateForm } from "./template-form"
import { type UpdateTemplate } from "../schemas/template"
import { useUpdateTemplateMutation } from "../services/template"

interface EditTemplateModalProps {
    template: UpdateTemplate & { id: number } | null
    open: boolean
    onOpenChange: (open: boolean) => void
    onSuccess?: (template: UpdateTemplate) => void
}

export function EditTemplateModal({ template, open, onOpenChange, onSuccess }: EditTemplateModalProps) {
    const { mutateAsync: updateTemplate, isPending } = useUpdateTemplateMutation();

    if (!template) return null;

    const onSubmit = async (values: UpdateTemplate) => {
        updateTemplate({
            id: template.id,
            ...values,
        }, {
            onSuccess: (data) => {
                onSuccess?.(data);
                onOpenChange(false);
            }
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Template</DialogTitle>
                    <DialogDescription>
                        Update the details for this template.
                    </DialogDescription>
                </DialogHeader>
                <TemplateForm
                    defaultValues={template}
                    onSubmit={onSubmit}
                    isLoading={isPending}
                    submitLabel="Update Template"
                />
            </DialogContent>
        </Dialog>
    )
}
