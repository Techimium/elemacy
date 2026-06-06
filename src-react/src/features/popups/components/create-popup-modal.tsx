import { __ } from '@wordpress/i18n';
import { Dialog, DialogContent, DialogHeader } from '@/components/ui/dialog';
import { PopupForm } from './popup-form';
import type { CreatePopup, Popup } from '@/features/popups/schemas/popup';
import { useCreatePopupMutation } from '@/features/popups/services/popup';
import { Button } from '@/components/ui/button';
import { DialogDescription, DialogTitle } from '@radix-ui/react-dialog';

interface CreatePopupModalProps {
    onSuccess?: (popup: Popup) => void;
    isOpen?: boolean;
    isDisabled?: boolean;
    onOpenChange?: (open: boolean) => void;
}

export function CreatePopupModal({
    onSuccess,
    isOpen = false,
    isDisabled = false,
    onOpenChange,
}: CreatePopupModalProps) {
    const { mutateAsync: createPopup } = useCreatePopupMutation();

    const onSubmit = async (popup: CreatePopup) => {
        const data = await createPopup(popup);
        onSuccess?.(data);
        onOpenChange?.(false);
    };

    // Create the popup, then hand off to the Elementor editor for the new post.
    const onSaveAndEdit = async (popup: CreatePopup) => {
        const data = await createPopup(popup);
        onSuccess?.(data);
        if (data?.edit_with_elementor) {
            window.open(data.edit_with_elementor, '_self');
        }
    };

    return (
        <>
            <Button
                className="cursor-pointer"
                size="lg"
                onClick={() => onOpenChange?.(true)}
                disabled={isDisabled}
            >
                {__('Create New Popup', 'elemacy')}
            </Button>
            <Dialog open={isOpen} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle asChild>
                            <div className="text-lg font-semibold">
                                {__('Create New Popup', 'elemacy')}
                            </div>
                        </DialogTitle>
                        <DialogDescription asChild>
                            <div className="text-sm text-muted-foreground">
                                {__('Configure the details for your new popup.', 'elemacy')}
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    {isOpen && (
                        <PopupForm
                            onSubmit={onSubmit}
                            onSaveAndEdit={onSaveAndEdit}
                            saveAndEditLabel={__('Create & Edit with Elementor', 'elemacy')}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
