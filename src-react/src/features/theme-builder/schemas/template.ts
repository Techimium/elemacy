import { z } from 'zod';
import { __ } from '@wordpress/i18n';
import { ConditionRuleSchema } from '@/schemas/condition';

const TemplateTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
});

const TemplateFilterSchema = z.object({
    search: z.string().optional(),
    type: z.string().optional(),
    status: z.enum(['publish', 'draft', 'trash']).optional()
});

const TemplateSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.string(),
    status: z.enum(['publish', 'draft', 'trash']),
    author: z.number(),
    edit_with_elementor: z.string(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
    conditions: z.array(ConditionRuleSchema).optional(),
});

const CreateTemplateSchema = z.object({
    title: z.string(),
    type: z.string().min(1, __('Type is required', 'elemacy')),
    status: z.enum(['publish', 'draft', 'trash']),
    conditions: z.array(ConditionRuleSchema).optional(),
});

const UpdateTemplateSchema = CreateTemplateSchema;

type TemplateType = z.infer<typeof TemplateTypeSchema>;
type TemplateFilter = z.infer<typeof TemplateFilterSchema>;
type Template = z.infer<typeof TemplateSchema>;
type CreateTemplate = z.infer<typeof CreateTemplateSchema>;
type UpdateTemplate = z.infer<typeof UpdateTemplateSchema>;

export {
    TemplateTypeSchema, type TemplateType,
    TemplateFilterSchema, type TemplateFilter,
    TemplateSchema, type Template,
    CreateTemplateSchema, type CreateTemplate,
    UpdateTemplateSchema, type UpdateTemplate,
};