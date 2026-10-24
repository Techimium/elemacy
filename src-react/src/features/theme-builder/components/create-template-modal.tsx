import { __ } from "@wordpress/i18n"
import {
    Dialog,
    DialogContent,
    DialogHeader,
} from "@/components/ui/dialog"
import { TemplateForm } from "./template-form"
import { isSettableStatus, type CreateTemplate, type Template, type UpdateTemplate } from "@/features/theme-builder/schemas/template"
import { useCreateTemplateMutation } from "@/features/theme-builder/services/template"
import { Button } from "@/components/ui/button"
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog"

interface CreateTemplateModalProps {
    onSuccess?: (template: Template) => void
    isOpen?: boolean
    isDisabled?: boolean
    onOpenChange?: (open: boolean) => void
}

export function CreateTemplateModal({ onSuccess, isOpen = false, isDisabled = false, onOpenChange }: CreateTemplateModalProps) {
    const { mutateAsync: createTemplate } = useCreateTemplateMutation();

    // The create form validates its status as Published or Draft.
    const toCreate = (values: UpdateTemplate): CreateTemplate => ({
        ...values,
        status: isSettableStatus(values.status) ? values.status : 'draft',
    });

    const onSubmit = async (values: UpdateTemplate) => {
        const data = await createTemplate(toCreate(values));
        onSuccess?.(data);
        onOpenChange?.(false);
    }

    // Create the template, then hand off to the Elementor editor for the new post.
    const onSaveAndEdit = async (values: UpdateTemplate) => {
        const data = await createTemplate(toCreate(values));
        onSuccess?.(data);
        if (data?.edit_with_elementor) {
            window.open(data.edit_with_elementor, '_self');
        }
    }

    return (
        <>
            <Button
                className="cursor-pointer"
                size="lg"
                onClick={() => onOpenChange?.(true)}
                disabled={isDisabled}
            >
                {__('Create New Template', 'elemacy')}
            </Button>
            <Dialog open={isOpen} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle asChild><div className="text-lg font-semibold">{__('Create New Template', 'elemacy')}</div></DialogTitle>
                        <DialogDescription asChild>
                            <div className="text-sm text-muted-foreground">
                            {__('Enter the details for your new theme template.', 'elemacy')}
                        </div>
                        </DialogDescription>
                    </DialogHeader>
                    {isOpen && (
                        <TemplateForm
                            onSubmit={onSubmit}
                            onSaveAndEdit={onSaveAndEdit}
                            saveAndEditLabel={__('Create & Edit with Elementor', 'elemacy')}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    )
}
