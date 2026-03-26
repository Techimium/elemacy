import { useWidgetsQuery, useToggleWidgetMutation } from "@/features/widgets/services/widget";
import { WidgetCard } from "@/features/widgets/components/widget-card";
import Spinner from "@/components/spinner";
import NoData from "@/components/no-data";

export function WidgetList() {
    const { data: widgets, isLoading } = useWidgetsQuery();
    const { mutate: toggleWidget } = useToggleWidgetMutation();

    if (isLoading) {
        return (
            <div className="flex justify-center items-center h-64">
                <Spinner />
            </div>
        );
    }

    if (!widgets || widgets.length === 0) {
        return <NoData title="No widgets found!" description="No widgets found!" />
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 pb-20">
            {widgets.map(widget => (
                <WidgetCard
                    key={widget.name}
                    widget={widget}
                    onToggle={(isEnabled, name) => toggleWidget({ isEnabled, name })}
                />
            ))}
        </div>
    );
}

export default WidgetList;
