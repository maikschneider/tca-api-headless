import { defineConfig } from 'astro/config';
import node from '@astrojs/node';

// Every page renders per request: pages can be login-protected, and the
// visitor's TYPO3 session decides what the API returns.
export default defineConfig({
  output: 'server',
  adapter: node({ mode: 'standalone' }),
});
