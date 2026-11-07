import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ConditionsField } from '@/components/conditions/conditions-field';
import { CreatePopupSchema, type CreatePopup, type UpdatePopup } from '@/features/popups/schemas/popup';
import { STATUS_OPTIONS } from '@/features/popups/constants/popups';
import type { Availability } from '@/schemas/availability';
import { TypeField } from './type-field';
import { TriggersField } from './triggers/triggers-field';
import { useTriggerTypes } from '@/features/popups/services/triggers';
import { generateEntryId, seedParams } from '@/features/popups/lib/seed-params';
import { RulesField } from './rules/rules-field';

interface PopupFormProps {
    defaultValues?: CreatePopup;
    /** Primary save action (e.g. create / update, then close). */
    onSubmit: (values: CreatePopup | UpdatePopup) => Promise<void>;
    /**
     * Optional secondary action that saves the form, then opens the Elementor
     * editor. When provided, a "Save & Edit with Elementor" button is shown.
     */
    onSaveAndEdit?: (values: CreatePopup | UpdatePopup) => Promise<void>;
    submitLabel?: string;
    saveAndEditLabel?: string;
    /** Whether to show the publish/draft status select (Edit modal only). */
    showStatus?: boolean;
    /**
     * The currently saved popup's own type availability —
     * presentational only, not part of the submitted form values, so it is a
     * separate prop rather than a `defaultValues` field.
     */
    typeAvailability?: Availability;
}

export function PopupForm({
    defaultValues,
    onSubmit,
    onSaveAndEdit,
    submitLabel,
    saveAndEditLabel,
    showStatus = false,
    typeAvailability,
}: PopupFormProps) {
    const displaySubmitLabel = submitLabel || __('Create Popup', 'elemacy');
    const displaySaveAndEditLabel =
        saveAndEditLabel || __('Save & Edit with Elementor', 'elemacy');

    // Tracks which button triggered the in-flight request so each shows its own
    // loading label while both stay disabled.
    const [pending, setPending] = useState<null | 'save' | 'edit'>(null);
    const busy = pending !== null;

    const form = useForm<CreatePopup>({
        resolver: zodResolver(CreatePopupSchema),
        defaultValues: defaultValues || {
            title: '',
            type: 'popup',
            status: 'draft',
            conditions: [],
            // A new popup opens on page load until its author says otherwise.
            triggers: [{ id: generateEntryId('trig'), type: 'page_load', params: {} }],
            rules: [],
        },
    });

    // The Page Load entry of a new popup takes its parameters from the trigger
    // type's schema once the types have loaded, unless the author has already
    // changed the triggers.
    const { data: triggerTypes } = useTriggerTypes();
    const pageLoad = triggerTypes?.find((type) => type.value === 'page_load');
    useEffect(() => {
        if (defaultValues || !pageLoad || form.getFieldState('triggers').isDirty) {
            return;
        }
        const [entry] = form.getValues('triggers');
        if (entry?.type === 'page_load') {
            form.resetField('triggers', { defaultValue: [{ ...entry, params: seedParams(pageLoad.params_schema) }] });
        }
    }, [defaultValues, pageLoad, form]);

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
                                <Input placeholder={__('My New Popup', 'elemacy')} {...field} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />

                {showStatus && (
                    <FormField
                        control={form.control}
                        name="status"
                        render={({ field }) => (
                            <FormItem>
                                <FormLabel>{__('Status', 'elemacy')}</FormLabel>
                                <Select onValueChange={field.onChange} value={field.value}>
                                    <FormControl>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder={__('Select a status', 'elemacy')} />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        {STATUS_OPTIONS.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                )}

                <Tabs defaultValue="type">
                    <TabsList className="w-full">
                        <TabsTrigger value="type">{__('Type', 'elemacy')}</TabsTrigger>
                        <TabsTrigger value="conditions">{__('Conditions', 'elemacy')}</TabsTrigger>
                        <TabsTrigger value="triggers">{__('Triggers', 'elemacy')}</TabsTrigger>
                        <TabsTrigger value="rules">{__('Advanced Rules', 'elemacy')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="type">
                        <Controller
                            control={form.control}
                            name="type"
                            render={({ field }) => (
                                <TypeField
                                    value={field.value}
                                    onChange={field.onChange}
                                    availability={typeAvailability}
                                />
                            )}
                        />
                        <p className="mt-4 text-sm text-muted-foreground">
                            {__(
                                'Layout & style — overlay, size, position, animation and close button — are configured in the Elementor editor via “Edit with Elementor”.',
                                'elemacy'
                            )}
                        </p>
                    </TabsContent>

                    <TabsContent value="conditions">
                        <Controller
                            control={form.control}
                            name="conditions"
                            render={({ field }) => (
                                <FormItem>
                                    <ConditionsField
                                        value={field.value ?? []}
                                        onChange={field.onChange}
                                        context="popup"
                                    />
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                    </TabsContent>

                    <TabsContent value="triggers">
                        <TriggersField />
                    </TabsContent>

                    <TabsContent value="rules">
                        <RulesField />
                    </TabsContent>
                </Tabs>

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
                            {pending === 'edit'
                                ? __('Saving...', 'elemacy')
                                : displaySaveAndEditLabel}
                        </Button>
                    )}
                </div>
            </form>
        </Form>
    );
}
