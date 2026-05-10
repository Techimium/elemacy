import { __ } from "@wordpress/i18n";
import { Dialog, DialogContent, DialogHeader } from "@/components/ui/dialog";
import { TemplateForm } from "./template-form";
import { type Template, type UpdateTemplate } from "../schemas/template";
import { useUpdateTemplateMutation } from "../services/template";
import { Button } from "@/components/ui/button";
import { DialogDescription, DialogTitle } from "@radix-ui/react-dialog";
import { Slot } from "@/components/slot";
import { Slots, type SlotPropsMap } from "@/lib/slots";
import { ProFeaturePlaceholder } from "@/components/pro-feature-placeholder";

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
  const { mutateAsync: updateTemplate, isPending } =
    useUpdateTemplateMutation();

  if (!template) return null;

  const onSubmit = async (values: UpdateTemplate) => {
    updateTemplate(
      {
        id: template.id,
        ...values,
      },
      {
        onSuccess: (data) => {
          onSuccess?.(data);
          onOpenChange(false);
        },
      },
    );
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
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
          defaultValues={template}
          onSubmit={onSubmit}
          isLoading={isPending}
          submitLabel={__("Update Template", "elemacy")}
        />
        <Slot<SlotPropsMap[typeof Slots.TEMPLATE_CONDITIONS]>
          name={Slots.TEMPLATE_CONDITIONS}
          slotProps={{ templateId: template.id }}
          fallback={
            <ProFeaturePlaceholder
              title={__('Display Conditions', 'elemacy')}
              description={__('Control where this template appears — target specific pages, post types, or categories.', 'elemacy')}
            />
          }
        />
        <Button
          onClick={() => window.open(template.edit_with_elementor, "_blank")}
          className="w-full bg-[#93003F] hover:bg-[#7a0034] text-white"
        >
          {__("Edit with Elementor", "elemacy")}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
