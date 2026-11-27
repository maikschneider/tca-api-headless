// Display helpers for recorded JSON. They shorten what is shown, never the
// recording itself, and mark the omission as a comment (shown as JSONC).

// An invisible separator: JSON.stringify keeps it unescaped, content never has it.
const hole = '\u2063';

/** Pretty JSON where the arrays chosen by `collapse` become `[/* n items *\/]`. */
export function elide(value: unknown, collapse: (key: string, array: unknown[]) => string | undefined): string {
  const json = JSON.stringify(value, (key, item) => {
    if (Array.isArray(item) && key !== '') {
      const note = collapse(key, item);
      if (note) return `${hole}${note}${hole}`;
    }
    return item;
  }, 2);
  return json.replace(new RegExp(`"${hole}(.*?)${hole}"`, 'g'), '[/* $1 */]');
}

const count = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;

/** An element with its nested arrays (rich text, files) collapsed. */
export const elementExcerpt = (content: unknown) =>
  elide(content, (_key, array) => array.length && typeof array[0] === 'object' ? count(array.length, 'item', 'items') : undefined);

/** A page with each region's elements collapsed; the elements have their own proofs. */
export const pageExcerpt = (page: { regions?: Record<string, unknown[]> }) => {
  const regions = new Set(page.regions ? Object.values(page.regions) : []);
  return elide(page, (_key, array) => regions.has(array) ? count(array.length, 'element', 'elements') : undefined);
};

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
