import type { Navigation } from './config';
import type { Entry } from './content';

// Process inks give sections an identity. A page takes the ink of its place
// among its menu siblings, so a menu item, its card and its page share one ink.
export const inks = ['cyan', 'magenta', 'yellow', 'blue', 'green', 'key'] as const;
export type Ink = (typeof inks)[number];

export const inkAt = (index: number): Ink => inks[index % inks.length];

export function inkOf(navigation: Navigation, uid: number | undefined): Ink | undefined {
  const index = navigation.children.findIndex((child) => child.uid === uid);
  if (index >= 0) return inkAt(index);
  for (const child of navigation.children) {
    const ink = inkOf(child, uid);
    if (ink) return ink;
  }
  return undefined;
}

// The specimen site keeps its own workshop palette.
export const pageInk = (entry: Entry): Ink | undefined => (entry.site === 'website' ? inkOf(entry.navigation, entry.page.uid) : undefined);
export const entryInk = (entry: Entry, index: number): Ink | undefined => (entry.site === 'website' ? inkAt(index) : undefined);
