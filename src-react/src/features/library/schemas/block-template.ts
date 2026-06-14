import { z } from 'zod';
import { __ } from '@wordpress/i18n';

const BlockTemplateTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
});

const BlockTemplateSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.string(),
    status: z.enum(['publish', 'draft', 'trash']),
    author: z.number(),
    date: z.string().optional(),
    edit_with_elementor: z.string(),
});

const CreateBlockTemplateSchema = z.object({
    title: z.string(),
    type: z.string().min(1, __('Type is required', 'elemacy')),
    status: z.enum(['publish', 'draft', 'trash']),
});

const UpdateBlockTemplateSchema = CreateBlockTemplateSchema;

type BlockTemplateType = z.infer<typeof BlockTemplateTypeSchema>;
type BlockTemplate = z.infer<typeof BlockTemplateSchema>;
type CreateBlockTemplate = z.infer<typeof CreateBlockTemplateSchema>;
type UpdateBlockTemplate = z.infer<typeof UpdateBlockTemplateSchema>;

export {
    BlockTemplateTypeSchema, type BlockTemplateType,
    BlockTemplateSchema, type BlockTemplate,
    CreateBlockTemplateSchema, type CreateBlockTemplate,
    UpdateBlockTemplateSchema, type UpdateBlockTemplate,
};
