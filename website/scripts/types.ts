import openapiTS, { astToString } from 'openapi-typescript';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

for (const site of ['website', 'kettenblatt']) {
  const document = await readFile(`../var/website/${site}.json`, 'utf8');
  const declaration = astToString(await openapiTS(JSON.parse(document)));
  const target = `src/api/${site}.d.ts`;
  if (process.argv.includes('--write')) {
    await mkdir('src/api', { recursive: true });
    await writeFile(target, declaration);
  } else if (await readFile(target, 'utf8') !== declaration) {
    throw new Error(`${target} differs from the current OpenAPI document. Run bun run types.`);
  }
  console.log(`${site}: generated declarations ${process.argv.includes('--write') ? 'written' : 'match'}`);
}
