import { __ } from "@wordpress/i18n"
import { useState } from "react"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
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
import { CreateBlockTemplateSchema, type CreateBlockTemplate, type UpdateBlockTemplate } from "@/features/library/schemas/block-template"
import { useBlockTemplateTypes } from "@/features/library/services/block-template"

interface BlockTemplateFormProps {
    defaultValues?: CreateBlockTemplate
    /** Primary save action (e.g. create / update, then close). */
    onSubmit: (values: CreateBlockTemplate | UpdateBlockTemplate) => Promise<void>
    /**
     * Optional secondary action that saves the form, then opens the Elementor
     * editor. When provided, a "Save & Edit with Elementor" button is shown.
     */
    onSaveAndEdit?: (values: CreateBlockTemplate | UpdateBlockTemplate) => Promise<void>
    submitLabel?: string
    saveAndEditLabel?: string
}

export function BlockTemplateForm({ defaultValues, onSubmit, onSaveAndEdit, submitLabel, saveAndEditLabel }: BlockTemplateFormProps) {
    const displaySubmitLabel = submitLabel || __('Create Template', 'elemacy');
    const displaySaveAndEditLabel = saveAndEditLabel || __('Save & Edit with Elementor', 'elemacy');
    const { data: templateTypes = [] } = useBlockTemplateTypes();

    // Tracks which button triggered the in-flight request so each shows its own
    // loading label while both stay disabled.
    const [pending, setPending] = useState<null | 'save' | 'edit'>(null);
    const busy = pending !== null;

    // Default a new template to Core's always-present generic "section" type.
    // The selector is hidden when only one type exists, so this keeps the
    // submitted value valid even when the user never sees a choice.
    const form = useForm<CreateBlockTemplate>({
        resolver: zodResolver(CreateBlockTemplateSchema),
        defaultValues: defaultValues || {
            title: "",
            type: "section",
            status: "publish",
        }
    })

    const runSave = form.handleSubmit(async (values) => {
        setPending('save');
        try {
            await onSubmit(values);
        } catch {
            // Surfacing is handled by the mutation layer; keep the modal open.
        } finally {
            setPending(null);
        }
    });

    const runSaveAndEdit = onSaveAndEdit
        ? form.handleSubmit(async (values) => {
            setPending('edit');
            try {
                await onSaveAndEdit(values);
            } catch {
                setPending(null);
            }
            // On success we navigate away to the editor, so leave `pending`
            // set to keep the buttons disabled until the page unloads.
        })
        : undefined;

    return (
        <Form {...form}>
            <form onSubmit={runSave} className="space-y-6">
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
                {templateTypes.length > 1 && (
                    <FormField
                        control={form.control}
                        name="type"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>{__('Type', 'elemacy')}</FormLabel>
                                <Select onValueChange={field.onChange} value={field.value}>
                                    <FormControl>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder={__('Select a template type', 'elemacy')} />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        {templateTypes.map((type) => (
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
                )}
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
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Button type="submit" disabled={busy} className="flex-1">
                        {pending === 'save' ? __('Saving...', 'elemacy') : displaySubmitLabel}
                    </Button>
                    {runSaveAndEdit && (
                        <Button
                            type="button"
                            variant="elementor"
                            onClick={runSaveAndEdit}
                            disabled={busy}
                            className="flex-1"
                        >
                            {pending === 'edit' ? __('Saving...', 'elemacy') : displaySaveAndEditLabel}
                        </Button>
                    )}
                </div>
            </form>
        </Form>
    )
}
