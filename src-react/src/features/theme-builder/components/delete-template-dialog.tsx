import { __, sprintf } from "@wordpress/i18n";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { AlertTriangle } from "lucide-react";
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog";

interface DeleteTemplateDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
  templateName?: string;
  isDeleting?: boolean;
}

export function DeleteTemplateDialog({
  open,
  onOpenChange,
  onConfirm,
  templateName,
  isDeleting = false,
}: DeleteTemplateDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="flex items-center gap-2 text-destructive">
              <AlertTriangle className="h-6 w-6" />
              <div className="text-lg font-semibold">
                {__("Delete Template", "elemacy")}
              </div>
            </div>
          </DialogTitle>
          <DialogDescription asChild>
            <div className="text-sm text-muted-foreground pt-2">
              {sprintf(
                /* translators: %s: Template name */
                __(
                  "Are you sure you want to delete %s? This action cannot be undone.",
                  "elemacy",
                ),
                templateName || "",
              )}
            </div>
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="gap-2 sm:gap-0">
          <Button
            variant="ghost"
            onClick={() => onOpenChange(false)}
            disabled={isDeleting}
          >
            {__("Cancel", "elemacy")}
          </Button>
          <Button
            variant="destructive"
            onClick={onConfirm}
            disabled={isDeleting}
          >
            {isDeleting
              ? __("Deleting...", "elemacy")
              : __("Delete Template", "elemacy")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
