import { __ } from "@wordpress/i18n"
import {
    Dialog,
    DialogContent,
    DialogHeader,
} from "@/components/ui/dialog"
import { BlockTemplateForm } from "./block-template-form"
import { type BlockTemplate, type CreateBlockTemplate } from "@/features/library/schemas/block-template"
import { useCreateBlockTemplateMutation } from "@/features/library/services/block-template"
import { Button } from "@/components/ui/button"
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog"

interface CreateBlockTemplateModalProps {
    onSuccess?: (template: BlockTemplate) => void
    isOpen?: boolean
    onOpenChange?: (open: boolean) => void
}

export function CreateBlockTemplateModal({ onSuccess, isOpen = false, onOpenChange }: CreateBlockTemplateModalProps) {
    const { mutateAsync: createTemplate } = useCreateBlockTemplateMutation();

    const onSubmit = async (template: CreateBlockTemplate) => {
        const data = await createTemplate(template);
        onSuccess?.(data);
        onOpenChange?.(false);
    }

    // Create the template, then hand off to the Elementor editor for the new post.
    const onSaveAndEdit = async (template: CreateBlockTemplate) => {
        const data = await createTemplate(template);
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
            >
                {__('Create New Template', 'elemacy')}
            </Button>
            <Dialog open={isOpen} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle asChild><div className="text-lg font-semibold">{__('Create New Template', 'elemacy')}</div></DialogTitle>
                        <DialogDescription asChild>
                            <div className="text-sm text-muted-foreground">
                                {__('Create a reusable template, then design it with Elementor.', 'elemacy')}
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    {isOpen && (
                        <BlockTemplateForm
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
