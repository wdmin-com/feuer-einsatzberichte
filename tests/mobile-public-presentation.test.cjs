const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const gallery = fs.readFileSync(path.join(root, 'templates/public/single/gallery.php'), 'utf8');
const card = fs.readFileSync(path.join(root, 'templates/public/overview/partials/report-card-grid.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/public/css/public-calls.css'), 'utf8');
const photoCss = fs.readFileSync(path.join(root, 'assets/public/css/public-style.css'), 'utf8');
const galleryScript = gallery.match(/<script>([\s\S]*?)<\/script>/i);

assert.ok(galleryScript, 'The gallery interaction script must exist.');
assert.doesNotThrow(() => new Function(galleryScript[1]), 'The gallery navigation script must be valid JavaScript.');

assert.match(gallery, /feu-einsatz-photo-modal-prev/, 'The photo viewer needs a previous-photo control.');
assert.match(gallery, /feu-einsatz-photo-modal-next/, 'The photo viewer needs a next-photo control.');
assert.match(gallery, /activeIndex\s*\+\s*\(deltaX\s*<\s*0\s*\?\s*1\s*:\s*-1\)/, 'Mobile users must be able to swipe between photos.');
assert.match(gallery, /event\.key === 'ArrowLeft'/, 'The photo viewer must support left/right keyboard navigation.');
assert.match(gallery, /feu-einsatz-photo-modal-counter/, 'The photo viewer must show its current position in the gallery.');
assert.match(photoCss, /@media\s*\(max-width:\s*640px\)[\s\S]*?feu-einsatz-photo-modal-nav/, 'Photo navigation controls must remain visible and tappable on phones.');
assert.match(card, /feuer-call-card-media is-placeholder/, 'Cards without images must reserve the same thumbnail area.');
assert.match(card, /feuer-call-card-kicker[\s\S]*?is-placeholder/, 'Cards without a category must reserve the same heading offset.');
assert.match(card, /feuer-call-card-excerpt is-placeholder/, 'Cards without an excerpt must reserve the same content height.');
assert.match(css, /\.feuer-call-card h2\s*\{[^}]*-webkit-line-clamp:\s*2/s, 'Card titles must use a consistent two-line height.');
assert.match(css, /@media\s*\(max-width:\s*640px\)[\s\S]*?feuer-call-detail-map \.feu-einsatz-live-map/, 'The live map must fit within the mobile viewport.');

console.log('Mobile photo navigation and card consistency checks passed.');
