import { describe, expect, test } from 'bun:test';
import { codeRuns } from '../../src/lib/wrap';

describe('codeRuns', () => {
  test('keeps hyphenated package names whole and breaks after slashes', () => {
    expect(codeRuns('composer require maikschneider/tca-api-headless:dev-main \\')).toEqual(['composer', ' ', 'require', ' ', 'maikschneider/', 'tca-api-headless:dev-main', ' ', '\\']);
  });

  test('preserves every character, including line breaks and indentation', () => {
    const text = 'a/b/c \\\n  "x-y:dev-release/2.4.0 as 2.4.0"';
    expect(codeRuns(text).join('')).toBe(text);
    expect(codeRuns('/_api/headless_navigation/{uid}')).toEqual(['/', '_api/', 'headless_navigation/', '{uid}']);
  });
});
