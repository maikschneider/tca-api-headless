import { test, expect } from 'bun:test';
import { imageFormat, localizedName } from '../../src/lib/formats';

test('only optimizable formats enter the image pipeline, with normalized extensions', () => {
  expect(imageFormat('/files/photo.JPG')).toBe('jpg');
  expect(localizedName('photo.JPG')).toBe('photo.jpg');
  expect(localizedName('photo.WeBp')).toBe('photo.webp');
  expect(imageFormat('/files/animation.gif')).toBeNull();
  expect(imageFormat('/files/diagram.SVG')).toBeNull();
  expect(localizedName('diagram.SVG')).toBe('diagram.SVG');
});
