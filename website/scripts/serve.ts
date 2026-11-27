import { resolve, extname } from 'node:path';
import { prefix } from '../src/lib/config';

const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const types: Record<string, string> = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json', '.jpg': 'image/jpeg', '.avif': 'image/avif', '.webp': 'image/webp', '.txt': 'text/plain' };
Bun.serve({
  hostname: '127.0.0.1',
  port: Number(process.env.PORT ?? 4400),
  async fetch(request) {
    const url = new URL(request.url);
    if (prefix && !url.pathname.startsWith(prefix + '/')) return new Response('Wrong deployment base', { status: 404 });
    const path = decodeURIComponent(url.pathname.slice(prefix.length));
    const filename = resolve(root, '.' + path + (path.endsWith('/') ? 'index.html' : ''));
    if (!filename.startsWith(root + '/')) return new Response('Not found', { status: 404 });
    const file = Bun.file(filename);
    if (!await file.exists()) return new Response(Bun.file(root + '/404.html'), { status: 404, headers: { 'Content-Type': 'text/html' } });
    return new Response(file, { headers: { 'Content-Type': types[extname(filename)] ?? file.type } });
  },
});
console.log(`Static files only: http://127.0.0.1:${process.env.PORT ?? 4400}${prefix}/`);
