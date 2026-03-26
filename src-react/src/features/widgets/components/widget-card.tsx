import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import DynamicIcon from "@/components/dynamic-icon";
import type { Widget } from "@/schemas/widget";

interface WidgetCardProps {
    widget: Widget;
    onToggle: (isEnabled: boolean, name: string) => void;
}

export function WidgetCard({ widget, onToggle }: WidgetCardProps) {
    return (
        <Card className="group relative overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-1 border-gray-100">
            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle className="text-sm font-semibold text-gray-800">
                    <div className="flex items-center gap-3">
                        <div className="p-2.5 bg-indigo-50 rounded-xl group-hover:bg-indigo-100 transition-colors">
                            <DynamicIcon name={widget.icon || "layout-template"} className="h-5 w-5 text-indigo-600" />
                        </div>
                        {widget.title}
                    </div>
                </CardTitle>
                <Switch
                    defaultChecked={widget.is_enabled}
                    onCheckedChange={(checked) => onToggle(checked, widget.name)}
                    className="data-[state=checked]:bg-indigo-600"
                />
            </CardHeader>
            <CardContent>
                <p className="text-xs text-gray-500 mt-2">
                    {widget.is_enabled ? "Enabled" : "Disabled"} - Toggle to {widget.is_enabled ? "hide" : "show"} this widget in Elementor.
                </p>
            </CardContent>
        </Card>
    );
}
