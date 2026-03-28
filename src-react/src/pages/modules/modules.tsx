import { __ } from "@wordpress/i18n";
import Container from "@/components/container";
import Topbar from "@/components/layout/topbar";
import { useModulesQuery } from "@/services/module";
import Spinner from "@/components/spinner";
import { Card } from "@/components/ui/card";
import { ModuleCard } from "./components/module-card";

export default function Modules() {
    const { data: modules, isLoading } = useModulesQuery();

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
                        <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 py-6 border-b border-gray-100 shadow-sm">
                            <div>
                                <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight tracking-tight">
                                    <span>{__("Modules", "elemacy")}</span>
                                </div>
                                <div className="text-gray-500 mt-2 max-w-2xl">
                                    {__("Manage all the modules provided by Elemacy for your Elementor editor.", "elemacy")}
                                </div>
                            </div>
                        </Card>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {modules?.map((module) => (
                                <ModuleCard key={module.name} module={module} />
                            ))}
                        </div>
                    </>
                )}
            </Container>
        </>
    );
}
