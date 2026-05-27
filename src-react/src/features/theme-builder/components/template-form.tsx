import { __ } from "@wordpress/i18n"
import { Controller, useForm } from "react-hook-form"
import { Button } from "@/components/ui/button"
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { Slot } from "@/components/slot"
import { Slots, type SlotPropsMap } from "@/lib/slots"
import { DisplayConditionsLocked } from "@/features/theme-builder/components/mock/display-conditions-locked"
import type { CreateTemplate, UpdateTemplate } from "@/features/theme-builder/schemas/template"
import { TEMPLATE_TYPES } from "@/features/theme-builder/constants/templates"

interface TemplateFormProps {
    defaultValues?: CreateTemplate
    onSubmit: (values: CreateTemplate | UpdateTemplate) => Promise<void>
    isLoading?: boolean
    submitLabel?: string
}

export function TemplateForm({ defaultValues, onSubmit, isLoading, submitLabel }: TemplateFormProps) {
    const displaySubmitLabel = submitLabel || __('Create Template', 'elemacy');
    const form = useForm<CreateTemplate>({
        defaultValues: defaultValues || {
            title: "",
            type: "header",
            status: "publish",
            extras: {},
        }
    })

    return (
        <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                <FormField
                    control={form.control}
                    name="title"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>{__('Title', 'elemacy')}</FormLabel>
                            <FormControl>
                                <Input placeholder={__('My New Template', 'elemacy')} {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={form.control}
                    name="type"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>{__('Type', 'elemacy')}</FormLabel>
                            <Select onValueChange={field.onChange} defaultValue={field.value}>
                                <FormControl>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={__('Select a template type', 'elemacy')} />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    {TEMPLATE_TYPES.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={form.control}
                    name="status"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>{__('Status', 'elemacy')}</FormLabel>
                            <Select onValueChange={field.onChange} defaultValue={field.value}>
                                <FormControl>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={__('Select a template status', 'elemacy')} />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem value="publish">{__('Published', 'elemacy')}</SelectItem>
                                    <SelectItem value="draft">{__('Draft', 'elemacy')}</SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <Controller
                    control={form.control}
                    name="extras"
                    render={({ field }) => (
                        <Slot<SlotPropsMap[typeof Slots.TEMPLATE_EXTRAS]>
                            name={Slots.TEMPLATE_EXTRAS}
                            slotProps={{
                                value: (field.value ?? {}) as Record<string, unknown>,
                                onChange: field.onChange,
                            }}
                            fallback={<DisplayConditionsLocked />}
                        />
                    )}
                />
                <Button type="submit" disabled={isLoading} className="w-full">
                    {isLoading ? __('Saving...', 'elemacy') : displaySubmitLabel}
                </Button>
            </form>
        </Form>
    )
}
