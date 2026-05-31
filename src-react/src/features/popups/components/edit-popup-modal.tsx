import { __ } from '@wordpress/i18n';
import { Dialog, DialogContent, DialogHeader } from '@/components/ui/dialog';
import { PopupForm } from './popup-form';
import type { CreatePopup, Popup, UpdatePopup } from '@/features/popups/schemas/popup';
import { usePopup, useUpdatePopupMutation } from '@/features/popups/services/popup';
import { DialogDescription, DialogTitle } from '@radix-ui/react-dialog';
import Spinner from '@/components/spinner';

interface EditPopupModalProps {
    popupId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess?: (popup: Popup) => void;
}

export function EditPopupModal({ popupId, open, onOpenChange, onSuccess }: EditPopupModalProps) {
    const { data: popup, isLoading } = usePopup(open ? popupId : null);
    const { mutateAsync: updatePopup } = useUpdatePopupMutation();

    const onSubmit = async (values: UpdatePopup) => {
        if (!popupId) return;
        const data = await updatePopup({ id: popupId, ...values });
        onSuccess?.(data);
        onOpenChange(false);
    };

    // Save the form first, then open the Elementor editor for this popup.
    const onSaveAndEdit = async (values: UpdatePopup) => {
        if (!popupId) return;
        const data = await updatePopup({ id: popupId, ...values });
        onSuccess?.(data);
        const editUrl = data?.edit_with_elementor || popup?.edit_with_elementor;
        if (editUrl) {
            window.open(editUrl, '_self');
        }
    };

    // The list item lacks the nested fields; PopupForm seeds from the full record.
    const defaultValues: CreatePopup | undefined = popup
        ? {
              title: popup.title,
              type: popup.type,
              status: popup.status,
              conditions: popup.conditions ?? [],
              triggers: popup.triggers ?? [],
              rules: popup.rules ?? [],
          }
        : undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle asChild>
                        <div className="text-lg font-semibold">{__('Edit Popup', 'elemacy')}</div>
                    </DialogTitle>
                    <DialogDescription asChild>
                        <div className="text-sm text-muted-foreground">
                            {__('Update the configuration for this popup.', 'elemacy')}
                        </div>
                    </DialogDescription>
                </DialogHeader>

                {isLoading || !popup ? (
                    <div className="flex justify-center items-center h-40">
                        <Spinner />
                    </div>
                ) : (
                    <PopupForm
                        // Remount per popup so the form re-initializes its defaults.
                        key={popup.id}
                        defaultValues={defaultValues}
                        onSubmit={onSubmit}
                        onSaveAndEdit={onSaveAndEdit}
                        submitLabel={__('Update Popup', 'elemacy')}
                        saveAndEditLabel={__('Save & Edit with Elementor', 'elemacy')}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
