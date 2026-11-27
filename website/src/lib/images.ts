import type { ImageMetadata } from 'astro';
import { prefix } from './config';

const images = import.meta.glob<{ default: ImageMetadata }>('../assets/**/*.{jpg,jpeg,png,webp,avif}', { eager: true });

export function imageSource(href: string): ImageMetadata {
  const key = '../assets/' + href.slice(prefix.length + 1);
  const image = images[key]?.default;
  if (!image) throw new Error(`No localized image ${href}`);
  return image;
}
