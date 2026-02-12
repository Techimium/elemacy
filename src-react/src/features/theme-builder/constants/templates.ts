export const TEMPLATE_TYPES = [
    { value: "header", label: "Header" },
    { value: "footer", label: "Footer" },
    { value: "single", label: "Single Post" },
    { value: "archive", label: "Archive" },
    { value: "404", label: "404 Page" },
    { value: "search", label: "Search Results" },
    { value: "single_product", label: "Single Product" },
    { value: "archive_product", label: "Archive Product" },
] as const;

export type TemplateValue = (typeof TEMPLATE_TYPES)[number]["value"];
export const TEMPLATE_VALUES = TEMPLATE_TYPES.map((t) => t.value) as [string, ...string[]];