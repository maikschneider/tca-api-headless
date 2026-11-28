// Code wraps between words and after slashes, not at the hyphens inside a
// package name or path segment: whitespace stays text, everything else becomes
// a run that breaks internally only if it is wider than its line.
export const codeRuns = (text: string): string[] => text.match(/\s+|[^\s/]*\/|[^\s/]+/g) ?? [];
