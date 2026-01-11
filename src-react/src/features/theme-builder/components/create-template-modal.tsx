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
import { Button } from "@/components/ui/button"
import { useState } from "react"

interface CreateTemplateModalProps {
    onSuccess?: (template: Template) => void
    isOpen?: boolean
    isDisabled?: boolean
}

export function CreateTemplateModal({ onSuccess, isOpen = false, isDisabled = false }: CreateTemplateModalProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(isOpen);
    const { mutateAsync: createTemplate, isPending } = useCreateTemplateMutation();

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
                size="lg"
                onClick={() => setIsCreateOpen(true)}
                disabled={isDisabled}
            >
                Create New Template
            </Button>
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
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
        </>
    )
}
