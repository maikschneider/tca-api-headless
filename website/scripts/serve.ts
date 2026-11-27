import { resolve, extname } from 'node:path';
import { prefix } from '../src/lib/config';

const root = resolve(process.env.WEBSITE_DIST ?? 'dist');
const types: Record<string, string> = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json', '.jpg': 'image/jpeg', '.avif': 'image/avif', '.webp': 'image/webp', '.txt': 'text/plain', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.ts': 'text/plain' };
const compressible = /^(text\/|application\/json|image\/svg)/;

// Behaves like a plain static host (GitHub Pages): gzip for text, short caching.
async function respond(request: Request, filename: string, status = 200): Promise<Response> {
  const file = Bun.file(filename);
  const type = types[extname(filename)] ?? file.type;
  const headers: Record<string, string> = { 'Content-Type': type, 'Cache-Control': 'max-age=600' };
  if (compressible.test(type) && request.headers.get('accept-encoding')?.includes('gzip')) {
    return new Response(Bun.gzipSync(new Uint8Array(await file.arrayBuffer())), { status, headers: { ...headers, 'Content-Encoding': 'gzip', Vary: 'Accept-Encoding' } });
  }
  return new Response(file, { status, headers });
}

Bun.serve({
  hostname: '127.0.0.1',
  port: Number(process.env.PORT ?? 4400),
  async fetch(request) {
    const url = new URL(request.url);
    if (prefix && !url.pathname.startsWith(prefix + '/')) return new Response('Wrong deployment base', { status: 404 });
    const path = decodeURIComponent(url.pathname.slice(prefix.length));
    const filename = resolve(root, '.' + path + (path.endsWith('/') ? 'index.html' : ''));
    if (!filename.startsWith(root + '/')) return new Response('Not found', { status: 404 });
    if (!await Bun.file(filename).exists()) return respond(request, root + '/404.html', 404);
    return respond(request, filename);
  },
});
console.log(`Static files only: http://127.0.0.1:${process.env.PORT ?? 4400}${prefix}/`);
