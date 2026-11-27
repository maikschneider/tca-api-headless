import { defineConfig } from 'astro/config';

export default defineConfig({
  output: 'static',
  site: process.env.WEBSITE_SITE ?? 'https://maikschneider.github.io',
  base: process.env.WEBSITE_BASE ?? '/tca-api-headless/',
  trailingSlash: 'always',
  build: { format: 'directory', inlineStylesheets: 'always' },
});
