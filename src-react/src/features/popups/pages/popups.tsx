import { useState } from 'react';
import Page from '@/components/layout/page';
import PageHeader from '@/components/layout/page-header';
import { CreatePopupModal } from '../components/create-popup-modal';
import { useModuleQuery } from '@/services/module';
import PopupList from '../components/popup-list';
import InactiveModule from '@/components/inactive-module';
import Spinner from '@/components/spinner';

function Popups() {
    const { data: module, isLoading } = useModuleQuery({ name: 'popups' });
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    return (
        <Page>
                {isLoading ? (
                    <div className="flex justify-center items-center h-64">
                        <Spinner />
                    </div>
                ) : (
                    <>
                        <PageHeader title={module?.title} description={module?.description}>
                            <CreatePopupModal
                                isOpen={isCreateOpen}
                                isDisabled={!module?.is_active}
                                onOpenChange={setIsCreateOpen}
                            />
                        </PageHeader>
                        {module?.is_active ? (
                            <PopupList setIsOpen={setIsCreateOpen} />
                        ) : (
                            <InactiveModule />
                        )}
                    </>
                )}
        </Page>
    );
}

export default Popups;
