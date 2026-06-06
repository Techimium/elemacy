import { __ } from '@wordpress/i18n';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EllipsisIcon } from 'lucide-react';
import type { PopupListItem } from '@/features/popups/schemas/popup';
import { POPUP_TYPES } from '@/features/popups/constants/popups';
import { Slot } from '@/components/slot';
import { Slots } from '@/lib/slots';

interface PopupCardProps {
    popup: PopupListItem;
    onEdit: (popup: PopupListItem) => void;
    onDelete: (popup: PopupListItem) => void;
    onDuplicate: (popup: PopupListItem) => void;
    onEditWithElementor: (popup: PopupListItem) => void;
}

function PopupCard({ popup, onEdit, onDelete, onDuplicate, onEditWithElementor }: PopupCardProps) {
    const typeLabel =
        POPUP_TYPES.find((t) => t.value === popup.type)?.label || __('Unknown', 'elemacy');

    return (
        <Card>
            <div className="px-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-1">
                        <span
                            className={`h-2 w-2 rounded-full ${
                                popup.status === 'publish' ? 'bg-green-500' : 'bg-gray-400'
                            }`}
                        ></span>
                        <span className="font-semibold text-gray-900">{popup.title}</span>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger>
                            <EllipsisIcon size={16} aria-hidden="true" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuLabel>{__('Actions', 'elemacy')}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={() => onEdit(popup)}>
                                {__('Edit', 'elemacy')}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onEditWithElementor(popup)}>
                                {__('Edit with Elementor', 'elemacy')}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onDuplicate(popup)}>
                                {__('Duplicate', 'elemacy')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                className="text-red-500"
                                onClick={() => onDelete(popup)}
                            >
                                {__('Delete', 'elemacy')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <div className="flex items-center justify-center w-full h-32 uppercase text-4xl font-bold text-gray-300 rounded bg-gray-100 my-4">
                    {popup.type ? popup.type.charAt(0) : 'P'}
                </div>
                <div className="flex items-center justify-between">
                    <Badge variant="secondary">{typeLabel}</Badge>
                    {popup.date && <span className="text-xs text-gray-400">{popup.date}</span>}
                </div>
                <Slot name={Slots.POPUP_CARD_FOOTER} slotProps={{ popupId: popup.id }} />
            </div>
        </Card>
    );
}

export default PopupCard;
