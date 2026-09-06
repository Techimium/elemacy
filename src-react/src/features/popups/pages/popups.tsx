import { useState } from 'react';
import Container from '@/components/container';
import { Card } from '@/components/ui/card';
import Topbar from '@/components/layout/topbar';
import { CreatePopupModal } from '../components/create-popup-modal';
import { useModuleQuery } from '@/services/module';
import PopupList from '../components/popup-list';
import InactiveModule from '@/components/inactive-module';
import Spinner from '@/components/spinner';

function Popups() {
    const { data: module, isLoading } = useModuleQuery({ name: 'popups' });
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    return (
        <>
            <Topbar />

            <Container className="space-y-6 mt-6 px-4">
                {isLoading ? (
                    <div className="flex justify-center items-center h-64">
                        <Spinner />
                    </div>
                ) : (
                    <>
                        <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6">
                            <div>
                                <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight">
                                    <span>{module?.title}</span>
                                </div>
                                <div className="text-gray-500 mt-1">{module?.description}</div>
                            </div>

                            <CreatePopupModal
                                isOpen={isCreateOpen}
                                isDisabled={!module?.is_active}
                                onOpenChange={setIsCreateOpen}
                            />
                        </Card>
                        {module?.is_active ? (
                            <PopupList setIsOpen={setIsCreateOpen} />
                        ) : (
                            <InactiveModule />
                        )}
                    </>
                )}
            </Container>
        </>
    );
}

export default Popups;
