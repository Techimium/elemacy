import { z } from 'zod';

// Mirrors Elemacy\Core\Capabilities\FeatureAvailability::to_array(). Optional
// on every entry schema that carries it: a freshly added, not-yet-saved
// condition/rule/trigger has no server-computed availability yet.
export const AvailabilitySchema = z.object({
    available: z.boolean(),
    reason_code: z.string(),
    message: z.string(),
});

export type Availability = z.infer<typeof AvailabilitySchema>;
