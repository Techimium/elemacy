import { __ } from "@wordpress/i18n";
import Container from "@/components/container";
import { Card } from "@/components/ui/card";
import Topbar from "@/components/layout/topbar";

import { WidgetList } from "@/features/widgets/components/widget-list";

// The widget platform is always on: this screen lists every widget whose
// owner (the platform or an active module) is available, each with its switch.
function Widgets() {
    return (
        <>
            <Topbar />

            <Container className="space-y-6 mt-6 px-4">
                <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 py-6 border-b border-gray-100 shadow-sm">
                    <div>
                        <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight tracking-tight">
                            <span>{__("Widgets", "elemacy")}</span>
                        </div>
                        <div className="text-gray-500 mt-2 max-w-2xl">
                            {__("Switch each Elemacy widget on or off. Widgets that belong to a module appear here while that module is active.", "elemacy")}
                        </div>
                    </div>
                </Card>

                <WidgetList />
            </Container>
        </>
    );
}

export default Widgets;
