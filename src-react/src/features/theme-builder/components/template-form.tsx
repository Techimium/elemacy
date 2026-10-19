import { useEffect, useRef, useState } from "react"
import { __ } from "@wordpress/i18n"
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
import { ConditionsField } from "@/components/conditions/conditions-field"
import { newConditionRuleId, type ConditionRule } from "@/schemas/condition"
import {
    CreateTemplateSchema,
    UpdateTemplateSchema,
    isSettableStatus,
    statusLabel,
    type TemplateType,
    type UpdateTemplate,
} from "@/features/theme-builder/schemas/template"
import { useTemplateTypes } from "@/features/theme-builder/services/template"

interface TemplateFormProps {
    /** An existing template's values; omitted when creating one. */
    defaultValues?: UpdateTemplate
    /** Primary save action (e.g. create / update, then close). */
    onSubmit: (values: UpdateTemplate) => Promise<void>
    /**
     * Optional secondary action that saves the form, then opens the Elementor
     * editor. When provided, a "Save & Edit with Elementor" button is shown.
     */
    onSaveAndEdit?: (values: UpdateTemplate) => Promise<void>
    submitLabel?: string
    saveAndEditLabel?: string
}

const withIds = (type: TemplateType): ConditionRule[] =>
    type.default_conditions.map((rule) => ({ id: newConditionRuleId(), ...rule }))

export function TemplateForm({ defaultValues, onSubmit, onSaveAndEdit, submitLabel, saveAndEditLabel }: TemplateFormProps) {
    const creating = !defaultValues;
    const displaySubmitLabel = submitLabel || __('Create Template', 'elemacy');
    const displaySaveAndEditLabel = saveAndEditLabel || __('Save & Edit with Elementor', 'elemacy');
    const { data: templateTypes = [], isLoading: isLoadingTypes } = useTemplateTypes();

    // Tracks which button triggered the in-flight request so each shows its own
    // loading label while both stay disabled.
    const [pending, setPending] = useState<null | 'save' | 'edit'>(null);
    const busy = pending !== null;

    const form = useForm<UpdateTemplate>({
        resolver: zodResolver(creating ? CreateTemplateSchema : UpdateTemplateSchema),
        defaultValues: defaultValues || {
            title: "",
            type: "header",
            // Nothing goes live on creation unless the author asks for it.
            status: "draft",
            conditions: [],
        }
    })

    const type = form.watch('type');
    const typeDefinition = templateTypes.find((t) => t.value === type);
    const targetsLocation = typeDefinition?.targeting === 'location';
    const storedStatus = defaultValues?.status;

    // A new template starts from its type's broadest condition (Entire Site for
    // a header) and follows the type until the author edits the rules. Rules
    // the builder drops because the new type does not offer them are not an
    // edit; they arrive through onPrune.
    const edited = useRef(!creating);
    useEffect(() => {
        if (!typeDefinition || edited.current) return;
        form.setValue('conditions', withIds(typeDefinition));
    }, [typeDefinition, form]);

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
                <FormField
                    control={form.control}
                    name="type"
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>{__('Type', 'elemacy')}</FormLabel>
                            <Select onValueChange={field.onChange} defaultValue={field.value}>
                                <FormControl>
                                    <SelectTrigger className="w-full" disabled={isLoadingTypes}>
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
                                    <SelectItem value="publish">{statusLabel('publish')}</SelectItem>
                                    <SelectItem value="draft">{statusLabel('draft')}</SelectItem>
                                    {storedStatus && !isSettableStatus(storedStatus) && (
                                        <SelectItem value={storedStatus}>{statusLabel(storedStatus)}</SelectItem>
                                    )}
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                {targetsLocation ? (
                    <p className="text-sm text-muted-foreground">
                        {__('A 404 template has no display conditions: once published, it is used for every page that is not found.', 'elemacy')}
                    </p>
                ) : (
                    <FormField
                        control={form.control}
                        name="conditions"
                        render={({ field }) => (
                            <FormItem>
                                <ConditionsField
                                    value={field.value ?? []}
                                    onChange={(rules) => {
                                        edited.current = true;
                                        field.onChange(rules);
                                    }}
                                    onPrune={field.onChange}
                                    templateType={type}
                                    emptyMessage={__('No conditions — this template is not displayed anywhere. Add one to choose where it appears.', 'elemacy')}
                                />
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}
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
