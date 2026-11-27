export function imageFormat(href: string): string | null {
  return href.match(/\.(jpe?g|png|webp|avif)$/i)?.[1].toLowerCase() ?? null;
}

export function localizedName(name: string): string {
  return name.replace(/\.(jpe?g|png|webp|avif)$/i, (extension) => extension.toLowerCase());
}
