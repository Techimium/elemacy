import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { AlertTriangle } from "lucide-react"

interface DeleteTemplateDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    onConfirm: () => void
    templateName?: string
    isDeleting?: boolean
}

export function DeleteTemplateDialog({
    open,
    onOpenChange,
    onConfirm,
    templateName,
    isDeleting = false
}: DeleteTemplateDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-destructive">
                        <AlertTriangle className="h-6 w-6" />
                        <DialogTitle>Delete Template</DialogTitle>
                    </div>
                    <DialogDescription className="pt-2">
                        Are you sure you want to delete <span className="font-semibold text-foreground">{templateName}</span>?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        disabled={isDeleting}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={isDeleting}
                    >
                        {isDeleting ? "Deleting..." : "Delete Template"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
