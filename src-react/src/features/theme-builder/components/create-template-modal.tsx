import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { TemplateForm } from "./template-form"
import { type CreateTemplate, type Template } from "@/features/theme-builder/schemas/template"
import { useCreateTemplateMutation } from "@/features/theme-builder/services/template"

interface CreateTemplateModalProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    onSuccess?: (template: Template) => void
}

export function CreateTemplateModal({ open, onOpenChange, onSuccess }: CreateTemplateModalProps) {
    const { mutateAsync: createTemplate, isPending } = useCreateTemplateMutation();

    const onSubmit = async (template: CreateTemplate) => {
        createTemplate(template, {
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
                    <DialogTitle>Create New Template</DialogTitle>
                    <DialogDescription>
                        Enter the details for your new theme template.
                    </DialogDescription>
                </DialogHeader>
                <TemplateForm onSubmit={onSubmit} isLoading={isPending} />
            </DialogContent>
        </Dialog>
    )
}
