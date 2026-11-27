// Build data already uses the emitted static manifest, including file links.
export const demoHref = (href: string) => href;
export const fileUrl = (href: string) => href;
export const jsonLd = (value: unknown) => JSON.stringify(value).replace(/</g, '\\u003c');
