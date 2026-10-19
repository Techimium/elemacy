import { z } from 'zod';
import { __ } from '@wordpress/i18n';
import { ConditionRuleSchema } from '@/schemas/condition';

/** A rule a new template of a type starts with, in stored form (no id yet). */
const DefaultConditionSchema = z.object({
    type: z.string(),
    operator: z.enum(['include', 'exclude']),
    value: z.string(),
});

const TemplateTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
    // `location`: offers no conditions and covers its whole location (404).
    targeting: z.enum(['conditions', 'location']),
    default_conditions: z.array(DefaultConditionSchema),
});

const TemplateFilterSchema = z.object({
    search: z.string().optional(),
    type: z.string().optional(),
    status: z.enum(['publish', 'draft', 'trash']).optional()
});

/** Where a template displays, as the server's resolver sees it. */
const TemplateDisplaySchema = z.object({
    state: z.enum(['displayed', 'not_displayed', 'suspended', 'unpublished']),
    summary: z.array(z.string()),
    exclusions: z.number(),
    unavailable: z.array(z.string()),
    overridden_by: z.object({ id: z.number(), title: z.string() }).nullable(),
});

const TemplateSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.string(),
    // Any WordPress status: Elementor can also set private, pending or a schedule.
    status: z.string(),
    author: z.number(),
    edit_with_elementor: z.string(),
    date: z.string().nullable().optional(),
    conditions: z.array(ConditionRuleSchema).optional(),
    display: TemplateDisplaySchema,
});

const conditionsRule = z.array(ConditionRuleSchema).optional().refine(
    // A stored rule carries `availability` and is let through even when its
    // condition is unknown, so it can be repaired; a rule added in this form
    // must name a condition.
    (rules) => (rules ?? []).every((rule) => rule.type !== '' || rule.availability !== undefined),
    { message: __('Choose a condition for each row, or remove it.', 'elemacy') },
);

/** The statuses this form sets; any other is Elementor's and is left as it is. */
const SETTABLE_STATUSES = ['publish', 'draft'] as const;

const CreateTemplateSchema = z.object({
    title: z.string().min(1, __('Title is required', 'elemacy')),
    type: z.string().min(1, __('Type is required', 'elemacy')),
    status: z.enum(SETTABLE_STATUSES),
    conditions: conditionsRule,
});

const UpdateTemplateSchema = CreateTemplateSchema.extend({
    status: z.string(),
});

type TemplateType = z.infer<typeof TemplateTypeSchema>;
type TemplateFilter = z.infer<typeof TemplateFilterSchema>;
type TemplateDisplay = z.infer<typeof TemplateDisplaySchema>;
type Template = z.infer<typeof TemplateSchema>;
type CreateTemplate = z.infer<typeof CreateTemplateSchema>;
type UpdateTemplate = z.infer<typeof UpdateTemplateSchema>;

const isSettableStatus = (status: string): status is CreateTemplate['status'] =>
    (SETTABLE_STATUSES as readonly string[]).includes(status);

/** The label of any WordPress post status a template can have. */
function statusLabel(status: string): string {
    switch (status) {
        case 'publish':
            return __('Published', 'elemacy');
        case 'draft':
            return __('Draft', 'elemacy');
        case 'pending':
            return __('Pending Review', 'elemacy');
        case 'private':
            return __('Private', 'elemacy');
        case 'future':
            return __('Scheduled', 'elemacy');
        default:
            return status;
    }
}

export {
    TemplateTypeSchema, type TemplateType,
    TemplateFilterSchema, type TemplateFilter,
    TemplateDisplaySchema, type TemplateDisplay,
    TemplateSchema, type Template,
    CreateTemplateSchema, type CreateTemplate,
    UpdateTemplateSchema, type UpdateTemplate,
    isSettableStatus, statusLabel,
};
