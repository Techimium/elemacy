import { z } from 'zod';
import { TEMPLATE_VALUES } from '@/features/theme-builder/constants/templates';
import { ConditionRuleSchema } from '@/schemas/condition';

const TemplateFilterSchema = z.object({
    search: z.string().optional(),
    type: z.enum(TEMPLATE_VALUES).optional(),
    status: z.enum(['publish', 'draft', 'trash']).optional()
});

const TemplateSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.enum(TEMPLATE_VALUES),
    status: z.enum(['publish', 'draft', 'trash']),
    author: z.number(),
    edit_with_elementor: z.string(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
    conditions: z.array(ConditionRuleSchema).optional(),
});

const CreateTemplateSchema = z.object({
    title: z.string(),
    type: z.enum(TEMPLATE_VALUES),
    status: z.enum(['publish', 'draft', 'trash']),
    conditions: z.array(ConditionRuleSchema).optional(),
});

const UpdateTemplateSchema = CreateTemplateSchema;

type TemplateFilter = z.infer<typeof TemplateFilterSchema>;
type Template = z.infer<typeof TemplateSchema>;
type CreateTemplate = z.infer<typeof CreateTemplateSchema>;
type UpdateTemplate = z.infer<typeof UpdateTemplateSchema>;

export {
    TemplateFilterSchema, type TemplateFilter,
    TemplateSchema, type Template,
    CreateTemplateSchema, type CreateTemplate,
    UpdateTemplateSchema, type UpdateTemplate,
};