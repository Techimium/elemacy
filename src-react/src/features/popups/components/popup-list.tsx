import { __ } from '@wordpress/i18n';
import { useMemo, useState } from 'react';
import type { PopupListItem } from '../schemas/popup';
import {
    useDeletePopupMutation,
    useDuplicatePopupMutation,
    usePopups,
} from '../services/popup';
import PopupCard from './popup-card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Card } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MessageSquareIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EditPopupModal } from './edit-popup-modal';
import { DeletePopupDialog } from './delete-popup-dialog';
import Spinner from '@/components/spinner';
import { POPUP_TYPES } from '../constants/popups';

const FILTERS = [{ value: 'all', label: __('All', 'elemacy') }, ...POPUP_TYPES];

function PopupList({ setIsOpen }: { setIsOpen: (open: boolean) => void }) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingPopup, setDeletingPopup] = useState<PopupListItem | null>(null);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [filter, setFilter] = useState<string>('all');

    const { data: popups, isLoading } = usePopups();
    const { mutateAsync: deletePopup, isPending: isDeleting } = useDeletePopupMutation();
    const { mutate: duplicatePopup } = useDuplicatePopupMutation();

    const filtered = useMemo(() => {
        if (!popups) return [];
        return filter === 'all' ? popups : popups.filter((p) => p.type === filter);
    }, [popups, filter]);

    const handleEdit = (popup: PopupListItem) => {
        setEditingId(popup.id);
        setIsEditOpen(true);
    };

    const confirmDelete = async () => {
        if (!deletingPopup) return;
        await deletePopup(deletingPopup.id);
        setDeletingPopup(null);
    };

    if (isLoading) {
        return (
            <div className="flex justify-center items-center h-64">
                <Spinner />
            </div>
        );
    }

    return (
        <>
            <Tabs value={filter} onValueChange={setFilter}>
                <TabsList>
                    {FILTERS.map((f) => (
                        <TabsTrigger key={f.value} value={f.value}>
                            {f.label}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>

            {filtered.length > 0 ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    {filtered.map((popup) => (
                        <PopupCard
                            key={popup.id}
                            popup={popup}
                            onEdit={handleEdit}
                            onEditWithElementor={() =>
                                window.open(popup.edit_with_elementor, '_self')
                            }
                            onDuplicate={(p) => duplicatePopup(p.id)}
                            onDelete={(p) => setDeletingPopup(p)}
                        />
                    ))}
                </div>
            ) : (
                <Card>
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia className="w-16 h-16" variant="icon">
                                <MessageSquareIcon />
                            </EmptyMedia>
                            <EmptyTitle>{__('No Popups Yet', 'elemacy')}</EmptyTitle>
                            <EmptyDescription>
                                {filter === 'all'
                                    ? __(
                                          "You haven't created any popups yet. Get started by creating your first popup.",
                                          'elemacy'
                                      )
                                    : __('No popups of this type yet.', 'elemacy')}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <div className="flex gap-2">
                                <Button className="cursor-pointer" onClick={() => setIsOpen(true)}>
                                    {__('Create Popup', 'elemacy')}
                                </Button>
                            </div>
                        </EmptyContent>
                    </Empty>
                </Card>
            )}

            <EditPopupModal
                popupId={editingId}
                open={isEditOpen}
                onOpenChange={(open) => {
                    setIsEditOpen(open);
                    if (!open) setEditingId(null);
                }}
            />

            <DeletePopupDialog
                open={!!deletingPopup}
                onOpenChange={(open) => !open && setDeletingPopup(null)}
                onConfirm={confirmDelete}
                popupName={deletingPopup?.title}
                isDeleting={isDeleting}
            />
        </>
    );
}

export default PopupList;
