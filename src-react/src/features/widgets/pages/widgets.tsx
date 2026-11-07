import { __ } from "@wordpress/i18n";
import Page from "@/components/layout/page";
import PageHeader from "@/components/layout/page-header";

import { WidgetList } from "@/features/widgets/components/widget-list";

// The widget platform is always on: this screen lists every widget whose
// owner (the platform or an active module) is available, each with its switch.
function Widgets() {
    return (
        <Page>
            <PageHeader
                title={__("Widgets", "elemacy")}
                description={__("Switch each Elemacy widget on or off. Widgets that belong to a module appear here while that module is active.", "elemacy")}
            />

            <WidgetList />
        </Page>
    );
}

export default Widgets;
