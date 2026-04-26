import { __ } from "@wordpress/i18n"
import {
    Dialog,
    DialogContent,
    DialogHeader,
} from "@/components/ui/dialog"
import { TemplateForm } from "./template-form"
import { type CreateTemplate, type Template } from "@/features/theme-builder/schemas/template"
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
    const { mutateAsync: createTemplate, isPending } = useCreateTemplateMutation();

    const onSubmit = async (template: CreateTemplate) => {
        createTemplate(template, {
            onSuccess: (data) => {
                onSuccess?.(data);
                onOpenChange?.(false);
            }
        });
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
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle asChild><div className="text-lg font-semibold">{__('Create New Template', 'elemacy')}</div></DialogTitle>
                        <DialogDescription asChild>
                            <div className="text-sm text-muted-foreground">
                            {__('Enter the details for your new theme template.', 'elemacy')}
                        </div>
                        </DialogDescription>
                    </DialogHeader>
                    <TemplateForm onSubmit={onSubmit} isLoading={isPending} />
                </DialogContent>
            </Dialog>
        </>
    )
}
