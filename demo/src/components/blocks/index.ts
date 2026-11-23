import type { HeadlessContentFallbackRead, HeadlessContentRead } from '../../api/schema';
import Bullets from './Bullets.astro';
import Fallback from './Fallback.astro';
import Header from './Header.astro';
import Image from './Image.astro';
import Table from './Table.astro';
import Text from './Text.astro';
import Uploads from './Uploads.astro';

export type Content = HeadlessContentRead | HeadlessContentFallbackRead;
type Props<T extends Content['CType']> = { content: Extract<Content, { CType: T }> };

// One component per content type. A type the API adds without a component here
// fails `astro check`, so the demo can't silently drop it.
export const blocks: { [T in Content['CType']]: (props: Props<T>) => unknown } = {
  text: Text,
  textmedia: Text,
  image: Image,
  header: Header,
  bullets: Bullets,
  table: Table,
  uploads: Uploads,
  html: Fallback,
  shortcut: Fallback,
};
