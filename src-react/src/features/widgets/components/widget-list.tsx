import { useWidgetsQuery, useToggleWidgetMutation } from "@/features/widgets/services/widget";
import { WidgetCard } from "./widget-card";
import Spinner from "@/components/spinner";

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
        return (
            <div className="py-12 text-center text-gray-500 bg-gray-50 rounded-2xl border border-gray-100 border-dashed">
                No widgets found.
            </div>
        );
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
