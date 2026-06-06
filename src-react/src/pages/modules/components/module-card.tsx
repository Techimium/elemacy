import { __ } from "@wordpress/i18n";
import { Card, CardContent, CardFooter, CardHeader } from "@/components/ui/card";
import ModuleSwitch from "@/components/module-switch";
import { type Module } from "@/schemas/module";
import { Badge } from "@/components/ui/badge";
import { ProBadge } from "@/components/pro-badge";
import { Button } from "@/components/ui/button";
import DynamicIcon from "@/components/dynamic-icon";
import { Lock } from "lucide-react";
import { useNavigate } from "react-router";

interface ModuleCardProps {
    module: Module;
}

export function ModuleCard({ module }: ModuleCardProps) {
    const navigate = useNavigate();

    return (
        <Card className="flex flex-col gap-6 transition-all hover:shadow-md border border-gray-100/60 bg-white/70 backdrop-blur-sm">
            <div className="flex flex-col items-start gap-4">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="inline-flex p-3 bg-indigo-50 text-indigo-600 rounded-xl shrink-0">
                            <DynamicIcon name={module.icon} className="w-6 h-6" />
                        </div>
                        {module.badge &&
                            (module.is_placeholder ? (
                                <ProBadge label={module.badge} />
                            ) : (
                                <Badge variant="default">{module.badge}</Badge>
                            ))}
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-2">
                    <div className="text-xl font-bold text-gray-900 mb-1 leading-tight tracking-tight">
                        {module.title}
                    </div>
                    <div className="text-sm text-gray-500 max-w-sm">
                        {module.description || __("Manage settings and features for this module.", "elemacy")}
                    </div>
                </CardContent>
            </div>
            <CardFooter className="flex items-center justify-between">
                {module.is_placeholder ? (
                   <Button
                    className="cursor-pointer"
                    variant={'outline'}
                    onClick={() => window.location.href = module.url ?? "https://elemacy.com"}>
                        {__('Learn more', 'elemacy')}
                   </Button>
                ) : (
                    <Button disabled={module.is_headless} variant="outline" onClick={() => navigate(`/${module.name}`)} className="cursor-pointer">{__('Manage', 'elemacy')}</Button>
                )}
                <div className="flex items-center justify-between sm:justify-end border-t border-gray-100 pt-4 sm:pt-0 sm:border-t-0 w-full sm:w-auto mt-2 sm:mt-0">
                    <div className="text-xs font-semibold uppercase tracking-wider text-gray-400 mr-4">
                        {__('Status', 'elemacy')}
                    </div>
                    {module.is_placeholder ? (
                        <Lock
                            className="w-5 h-5 text-gray-400"
                            aria-label={__('Not available yet', 'elemacy')}
                        />
                    ) : (
                        <ModuleSwitch isEnabled={module.is_active} name={module.name} />
                    )}
                </div>
            </CardFooter>
        </Card>
    );
}
