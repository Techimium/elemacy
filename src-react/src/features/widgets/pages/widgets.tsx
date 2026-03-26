import Container from "@/components/container";
import { Card } from "@/components/ui/card";
import Topbar from "@/components/layout/topbar";
import ModuleSwitch from "@/components/module-switch";
import { useModuleQuery } from "@/services/module";
import InactiveModule from "@/components/inactive-module";
import Spinner from "@/components/spinner";
import { WidgetList } from "@/features/widgets/components/widget-list";

function Widgets() {
    const { data: module, isLoading: isLoadingModule } = useModuleQuery({ name: 'widgets' });

    return (
        <>
            <Topbar />

            <Container className="space-y-6 mt-6 px-4">
                {isLoadingModule ? (
                    <div className="flex justify-center items-center h-64">
                        <Spinner />
                    </div>
                ) : (
                    <>
                        <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 py-6 border-b border-gray-100 shadow-sm">
                            <div>
                                <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight tracking-tight">
                                    <span>{module?.title || "Widgets"}</span>
                                    <ModuleSwitch
                                        isEnabled={module?.is_active}
                                        name="widgets"
                                    />
                                </div>
                                <div className="text-gray-500 mt-2 max-w-2xl">
                                    {module?.description || "Manage native and pro widgets provided by Elemacy for your Elementor editor."}
                                </div>
                            </div>
                        </Card>

                        {module?.is_active ? <WidgetList /> : <InactiveModule />}
                    </>
                )}
            </Container>
        </>
    );
}

export default Widgets;
