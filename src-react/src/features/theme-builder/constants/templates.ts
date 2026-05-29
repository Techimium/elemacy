export const TEMPLATE_TYPES = window.elemacy?.templateTypes || [];

export type TemplateValue = (typeof TEMPLATE_TYPES)[number]["value"];
export const TEMPLATE_VALUES = TEMPLATE_TYPES.map((t) => t.value) as [string, ...string[]];
