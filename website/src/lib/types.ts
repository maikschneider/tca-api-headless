import ts from 'typescript';
import { readFile } from 'node:fs/promises';
import type { Site } from './config';

export function extractType(source: string, name: string): string {
  const file = ts.createSourceFile('schema.d.ts', source, ts.ScriptTarget.Latest, true);
  let result: ts.TypeNode | undefined;
  const visit = (node: ts.Node) => {
    if (ts.isInterfaceDeclaration(node) && node.name.text === 'components') {
      const schemas = node.members.find((member) => member.name?.getText(file) === 'schemas');
      if (schemas && ts.isPropertySignature(schemas) && schemas.type && ts.isTypeLiteralNode(schemas.type)) {
        const schema = schemas.type.members.find((member) => member.name?.getText(file) === name);
        if (schema && ts.isPropertySignature(schema)) result = schema.type;
      }
    }
    ts.forEachChild(node, visit);
  };
  visit(file);
  if (!result) throw new Error(`No generated type ${name}`);
  return `type ${name} = ${ts.createPrinter({ removeComments: true }).printNode(ts.EmitHint.Unspecified, result, file)};`;
}

export const typeName = (ctype: string) => ['text', 'textmedia', 'image', 'header', 'bullets', 'table', 'uploads'].includes(ctype)
  ? `HeadlessContent${ctype[0].toUpperCase()}${ctype.slice(1)}Read` : 'HeadlessContentFallbackRead';

export async function schemaType(site: Site, name: string): Promise<string> {
  return extractType(await readFile(`src/api/${site}.d.ts`, 'utf8'), name);
}
