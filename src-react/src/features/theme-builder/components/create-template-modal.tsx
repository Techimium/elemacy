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
import { useEffect, useState } from "react"
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog"

interface CreateTemplateModalProps {
    onSuccess?: (template: Template) => void
    isOpen?: boolean
    isDisabled?: boolean
}

export function CreateTemplateModal({ onSuccess, isOpen = false, isDisabled = false }: CreateTemplateModalProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(isOpen);
    const { mutateAsync: createTemplate, isPending } = useCreateTemplateMutation();
    
    useEffect(() => {
        setIsCreateOpen(isOpen)
    },  [isOpen])

    const onSubmit = async (template: CreateTemplate) => {
        createTemplate(template, {
            onSuccess: (data) => {
                onSuccess?.(data);
                setIsCreateOpen(false);
            }
        });
    }

    return (
        <>
            <Button
                className="cursor-pointer"
                size="lg"
                onClick={() => setIsCreateOpen(true)}
                disabled={isDisabled}
            >
                {__('Create New Template', 'elemacy')}
            </Button>
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
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
