import { __ } from "@wordpress/i18n";
import { Dialog, DialogContent, DialogHeader } from "@/components/ui/dialog";
import { TemplateForm } from "./template-form";
import { isSettableStatus, type Template, type UpdateTemplate } from "../schemas/template";
import { useUpdateTemplateMutation } from "../services/template";
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog";

interface EditTemplateModalProps {
  template: Template | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess?: (template: UpdateTemplate) => void;
}

export function EditTemplateModal({
  template,
  open,
  onOpenChange,
  onSuccess,
}: EditTemplateModalProps) {
  const { mutateAsync: updateTemplate } = useUpdateTemplateMutation();

  if (!template) return null;

  // A status set in Elementor (private, pending, scheduled) that the author
  // left alone is not sent, so the server keeps it.
  const save = ({ status, ...values }: UpdateTemplate) =>
    updateTemplate({ id: template.id, ...values, ...(isSettableStatus(status) ? { status } : {}) });

  const onSubmit = async (values: UpdateTemplate) => {
    const data = await save(values);
    onSuccess?.(data);
    onOpenChange(false);
  };

  // Save the form first, then open the Elementor editor for this template.
  const onSaveAndEdit = async (values: UpdateTemplate) => {
    const data = await save(values);
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
        <TemplateForm
          defaultValues={{ title: template.title, type: template.type, status: template.status, conditions: template.conditions ?? [] }}
          onSubmit={onSubmit}
          onSaveAndEdit={onSaveAndEdit}
          submitLabel={__("Update Template", "elemacy")}
          saveAndEditLabel={__("Save & Edit with Elementor", "elemacy")}
        />
      </DialogContent>
    </Dialog>
  );
}
