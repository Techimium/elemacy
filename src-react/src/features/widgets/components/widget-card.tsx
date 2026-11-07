import { useEffect, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { Card, CardContent, CardFooter, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import DynamicIcon from "@/components/dynamic-icon";
import { cn } from "@/lib/utils";
import type { Widget } from "@/schemas/widget";

interface WidgetCardProps {
    widget: Widget;
    onToggle: (isEnabled: boolean, name: string) => void;
}

// Same anatomy as the module card (icon, title, description, footer with the
// switch at the bottom right); the footer's left slot shows the widget's
// status where a module has its "Manage" button.
export function WidgetCard({ widget, onToggle }: WidgetCardProps) {
    // Local state so the card reacts the moment the switch is flipped,
    // instead of waiting for the list to refetch.
    const [enabled, setEnabled] = useState(widget.is_enabled);

    useEffect(() => {
        setEnabled(widget.is_enabled);
    }, [widget.is_enabled]);

    const handleChange = (checked: boolean) => {
        setEnabled(checked);
        onToggle(checked, widget.name);
    };

    return (
        <Card className="flex flex-col gap-6 transition-all hover:shadow-md border border-gray-100/60 bg-white/70 backdrop-blur-sm">
            <div className="flex flex-col items-start gap-4">
                <CardHeader>
                    <div
                        className={cn(
                            "inline-flex p-3 rounded-xl shrink-0 transition-colors",
                            enabled ? "bg-indigo-50 text-indigo-600" : "bg-gray-200 text-gray-500"
                        )}
                    >
                        <DynamicIcon name={widget.icon || "layout-template"} className="w-6 h-6" />
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-2">
                    <div className="text-xl font-bold text-gray-900 mb-1 leading-tight tracking-tight">
                        {widget.title}
                    </div>
                    <div className="text-sm text-gray-500 max-w-sm">
                        {widget.description || __("An Elemacy widget for Elementor.", "elemacy")}
                    </div>
                </CardContent>
            </div>
            <CardFooter className="flex items-center justify-between">
                <Badge
                    variant="secondary"
                    className={cn(
                        "gap-1.5",
                        enabled
                            ? "bg-emerald-50 text-emerald-700"
                            : "bg-gray-100 text-gray-500"
                    )}
                >
                    <span
                        className={cn("size-1.5 rounded-full", enabled ? "bg-emerald-500" : "bg-gray-400")}
                        aria-hidden="true"
                    />
                    {enabled ? __("Active in Elementor", "elemacy") : __("Hidden from Elementor", "elemacy")}
                </Badge>
                <Switch
                    checked={enabled}
                    onCheckedChange={handleChange}
                    aria-label={sprintf(
                        /* translators: %s: widget name */
                        __("Enable %s", "elemacy"),
                        widget.title
                    )}
                />
            </CardFooter>
        </Card>
    );
}
