import { __ } from "@wordpress/i18n";
import { Dialog, DialogContent, DialogHeader } from "@/components/ui/dialog";
import { BlockTemplateForm } from "./block-template-form";
import { type BlockTemplate, type UpdateBlockTemplate } from "../schemas/block-template";
import { useUpdateBlockTemplateMutation } from "../services/block-template";
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog";

interface EditBlockTemplateModalProps {
  template: BlockTemplate | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess?: (template: UpdateBlockTemplate) => void;
}

export function EditBlockTemplateModal({
  template,
  open,
  onOpenChange,
  onSuccess,
}: EditBlockTemplateModalProps) {
  const { mutateAsync: updateTemplate } = useUpdateBlockTemplateMutation();

  if (!template) return null;

  const onSubmit = async (values: UpdateBlockTemplate) => {
    const data = await updateTemplate({ id: template.id, ...values });
    onSuccess?.(data);
    onOpenChange(false);
  };

  // Save the form first, then open the Elementor editor for this template.
  const onSaveAndEdit = async (values: UpdateBlockTemplate) => {
    const data = await updateTemplate({ id: template.id, ...values });
    onSuccess?.(data);
    const editUrl = data?.edit_with_elementor || template.edit_with_elementor;
    if (editUrl) {
      window.open(editUrl, "_self");
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle asChild>
            <div className="text-lg font-semibold">
              {__("Edit Template", "elemacy")}
            </div>
          </DialogTitle>
          <DialogDescription asChild>
            <div className="text-sm text-muted-foreground">
              {__("Update the details for this template.", "elemacy")}
            </div>
          </DialogDescription>
        </DialogHeader>
        <BlockTemplateForm
          defaultValues={template}
          onSubmit={onSubmit}
          onSaveAndEdit={onSaveAndEdit}
          submitLabel={__("Update Template", "elemacy")}
          saveAndEditLabel={__("Save & Edit with Elementor", "elemacy")}
        />
      </DialogContent>
    </Dialog>
  );
}
