import type { ShikiTransformer, ThemeRegistration } from 'shiki';

// Wrapped lines hang below their own indentation, so nesting stays readable.
export const hangingIndent = (code: string): ShikiTransformer => {
  const indents = code.split('\n').map((line) => line.length - line.trimStart().length);
  return {
    line(node, line) {
      if (indents[line - 1]) node.properties.style = `--indent:${indents[line - 1]}ch`;
    },
  };
};

// Proof themes: ink for structure, process blue for values, muted punctuation.
// Proof red stays reserved for marks. Hex values mirror tokens.css (sheet ground).
const theme = (name: string, type: 'light' | 'dark', ink: string, soft: string, value: string): ThemeRegistration => ({
  name,
  type,
  colors: { 'editor.background': '#00000000', 'editor.foreground': ink },
  tokenColors: [
    { settings: { foreground: ink } },
    { scope: ['punctuation', 'meta.brace', 'keyword.operator', 'meta.structure.dictionary.json punctuation'], settings: { foreground: soft } },
    { scope: ['string', 'string.quoted', 'constant.numeric', 'constant.language', 'string.template'], settings: { foreground: value } },
    { scope: ['support.type.property-name', 'support.type.property-name.json', 'meta.object-literal.key', 'variable.other.property', 'entity.name.type'], settings: { foreground: ink } },
    { scope: ['keyword', 'storage', 'storage.type', 'storage.modifier', 'support.type.primitive', 'support.type.builtin'], settings: { foreground: soft } },
    { scope: ['comment', 'punctuation.definition.comment'], settings: { foreground: soft, fontStyle: 'italic' } },
  ],
});

export const proofThemes = {
  light: theme('proof-light', 'light', '#11212f', '#435260', '#035397'),
  dark: theme('proof-dark', 'dark', '#e2e9ee', '#afbdc6', '#86c5fa'),
};
