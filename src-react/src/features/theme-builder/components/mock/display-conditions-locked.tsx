import { __ } from "@wordpress/i18n"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"

export function DisplayConditionsLocked() {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Label>{__('Display Conditions', 'elemacy')}</Label>
                    <Badge variant="default">{__('Pro', 'elemacy')}</Badge>
                </div>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    disabled
                    className="h-auto p-0"
                >
                    {__('+ Add Condition', 'elemacy')}
                </Button>
            </div>
            <div className="space-y-2">
                <div className="flex items-center gap-2 mb-2">
                    <Select disabled value="include">
                        <SelectTrigger className="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="include">{__('Show on', 'elemacy')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select disabled value="entire-site">
                        <SelectTrigger className="flex-1">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="entire-site">{__('Entire Site', 'elemacy')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        disabled
                        aria-label={__('Remove condition', 'elemacy')}
                        className="text-muted-foreground"
                    >
                        ×
                    </Button>
                </div>
            </div>
        </div>
    )
}
