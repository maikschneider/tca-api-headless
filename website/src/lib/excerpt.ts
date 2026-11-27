import type { Reveal } from './content';

// Display helpers for recorded JSON. They shorten what is shown, never the
// recording itself, and mark the omission as a comment (shown as JSONC).

// An invisible separator: JSON.stringify keeps it unescaped, content never has it.
const hole = '\u2063';

/** Pretty JSON where the arrays and objects chosen by `collapse` become `[/* n items *\/]` or `{/* n items *\/}`. */
export function elide(value: unknown, collapse: (key: string, item: unknown[] | object) => string | undefined): string {
  const json = JSON.stringify(value, (key, item) => {
    if (item && typeof item === 'object' && key !== '') {
      const note = collapse(key, item);
      if (note) return `${hole}${Array.isArray(item) ? '[' : '{'}${note}${hole}`;
    }
    return item;
  }, 2);
  return json.replace(new RegExp(`"${hole}([[{])(.*?)${hole}"`, 'g'), (_match, open: string, note: string) => `${open}/* ${note} */${open === '[' ? ']' : '}'}`);
}

const count = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;

/** An element with its nested arrays (rich text, files) collapsed. */
export const elementExcerpt = (content: unknown) =>
  elide(content, (_key, array) => Array.isArray(array) && array.length && typeof array[0] === 'object' ? count(array.length, 'item', 'items') : undefined);

/** A page with each region's elements collapsed; the elements have their own proofs. */
export const pageExcerpt = (page: { regions?: Record<string, unknown[]> }) => {
  const regions = new Set<unknown>(page.regions ? Object.values(page.regions) : []);
  return elide(page, (_key, array) => Array.isArray(array) && regions.has(array) ? count(array.length, 'element', 'elements') : undefined);
};

/** An OpenAPI document with its paths and schemas collapsed; the page lists them. */
export const documentExcerpt = (document: { paths?: object; components?: { schemas?: object } }) =>
  elide(document, (key, item) => (key === 'paths' && item === document.paths) || (key === 'schemas' && item === document.components?.schemas)
    ? count(Object.keys(item).length, key === 'paths' ? 'path' : 'schema', key === 'paths' ? 'paths' : 'schemas') : undefined);

/** 1-based number of the first top-level line of `key`, or 0. */
export function lineOf(json: string, key: string): number {
  return json.split('\n').findIndex((line) => line.startsWith(`  "${key}":`)) + 1;
}

/** A JSON pointer as a property path: `/regions/a~1b/0` → `regions["a/b"][0]`. */
export function pointerLabel(pointer: string): string {
  return pointer.split('/').slice(1).map((part) => part.replaceAll('~1', '/').replaceAll('~0', '~')).map((key, index) =>
    /^\d+$/.test(key) ? `[${key}]` : /^[A-Za-z_$][\w$]*$/.test(key) ? `${index ? '.' : ''}${key}` : `[${JSON.stringify(key)}]`,
  ).join('');
}

/** Several element proofs of one response as one: their JSON as an array, in order. */
export function groupReveal(reveals: Reveal[]): Reveal {
  const [first] = reveals;
  if (!first || reveals.some((reveal) => reveal.request !== first.request || reveal.type !== first.type)) throw new Error('Only proofs of one request and type can be grouped');
  return { ...first, pointer: '', excerpt: '', json: `[\n${reveals.map((reveal) => reveal.json.replace(/^/gm, '  ')).join(',\n')}\n]` };
}
