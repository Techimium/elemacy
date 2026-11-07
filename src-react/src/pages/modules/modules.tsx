import { __ } from "@wordpress/i18n";
import Page from "@/components/layout/page";
import PageHeader from "@/components/layout/page-header";
import { useModulesQuery } from "@/services/module";
import Spinner from "@/components/spinner";
import { ModuleCard } from "./components/module-card";

export default function Modules() {
    const { data: modules, isLoading } = useModulesQuery();

    return (
        <Page>
            {isLoading ? (
                <div className="flex justify-center items-center h-64">
                    <Spinner />
                </div>
            ) : (
                <>
                    <PageHeader
                        title={__("Modules", "elemacy")}
                        description={__("Manage all the modules provided by Elemacy for your Elementor editor.", "elemacy")}
                    />

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {modules?.map((module) => (
                            <ModuleCard key={module.name} module={module} />
                        ))}
                    </div>
                </>
            )}
        </Page>
    );
}
