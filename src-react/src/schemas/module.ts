import { z } from 'zod';

const ModuleSchema = z.object({
    name: z.string(),
    title: z.string(),
    description: z.string(),
    is_active: z.boolean(),
    dependencies: z.array(z.string()),
});

type Module = z.infer<typeof ModuleSchema>;

export {
    ModuleSchema, type Module,
};