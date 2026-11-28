import type { APIRoute } from 'astro';
import { loadBuild } from '../lib/content';
import { deployment } from '../lib/config';

const escape = (value: string) => value.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

// The emitted route inventory is also the sitemap, including actual language
// alternates. A page without a translation never gets a fictitious hreflang.
export const GET: APIRoute = async () => {
  const { entries } = await loadBuild();
  const urls = entries.map((entry) => `<url><loc>${escape(new URL(entry.output, deployment).href)}</loc>${entry.alternates.map((alternate) => `<xhtml:link rel="alternate" hreflang="${alternate.language}" href="${escape(new URL(alternate.output, deployment).href)}"/>`).join('')}</url>`).join('');
  return new Response(`<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">${urls}</urlset>`, { headers: { 'Content-Type': 'application/xml' } });
};
