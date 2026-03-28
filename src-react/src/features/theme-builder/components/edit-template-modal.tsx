import {
    Dialog,
    DialogContent,
    DialogHeader,
} from "@/components/ui/dialog"
import { TemplateForm } from "./template-form"
import { type Template, type UpdateTemplate } from "../schemas/template"
import { useUpdateTemplateMutation } from "../services/template"
import { Button } from "@/components/ui/button"


interface EditTemplateModalProps {
    template: Template | null
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
                    <div className="text-lg font-semibold">Edit Template</div>
                    <div className="text-sm text-muted-foreground">
                        Update the details for this template.
                    </div>
                </DialogHeader>
                <TemplateForm
                    defaultValues={template}
                    onSubmit={onSubmit}
                    isLoading={isPending}
                    submitLabel="Update Template"
                />
                <Button
                    onClick={() => window.open(template.edit_with_elementor, '_blank')}
                    className="w-full bg-[#93003F] hover:bg-[#7a0034] text-white"
                >
                    Edit with Elementor
                </Button>
            </DialogContent>
        </Dialog>
    )
}
