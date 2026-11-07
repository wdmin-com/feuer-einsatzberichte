const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const overview = fs.readFileSync(path.join(root, 'templates/public/overview/list.php'), 'utf8');
const page = fs.readFileSync(path.join(root, 'templates/public/overview/page.php'), 'utf8');
const single = fs.readFileSync(path.join(root, 'templates/public/single-render.php'), 'utf8');
const info = fs.readFileSync(path.join(root, 'templates/public/single/info.php'), 'utf8');
const publicClass = fs.readFileSync(path.join(root, 'includes/class-public.php'), 'utf8');
const callsCss = fs.readFileSync(path.join(root, 'assets/public/css/public-calls.css'), 'utf8');
const commonCss = fs.readFileSync(path.join(root, 'assets/public/css/public-style.css'), 'utf8');
const installer = fs.readFileSync(path.join(root, 'includes/class-installer.php'), 'utf8');

assert.match(installer, /'feu_einsatz_overview_map_mode'\s*=>\s*'all'/, 'Existing installs must default to showing map images.');
assert.match(overview, /\$has_featured_report\s*=\s*1\s*===\s*\$paged/, 'Only page one should get a featured report.');
assert.match(overview, /array_slice\(\$posts, \$has_featured_report \? 1 : 0\)/, 'Page two and later must retain every report in their grid.');
assert.match(overview, /\$show_map\s*=\s*'all'\s*===\s*\$map_mode/, 'Map mode must apply consistently to secondary cards.');
assert.match(overview, /\$image_report\['image_url'\]\s*=\s*''/, 'Map-only images must be suppressed when maps are hidden.');
assert.match(page, /feuer-public-calls-sidebar/, 'The overview page must render the sidebar beside the report layout.');
assert.match(single, /normalize_single_map_display_mode\(.*?\)\)\s*:\s*\?>/s, 'Disabled map mode must omit the empty map section.');
assert.match(info, /in_array\('category', \$info_fields, true\)/, 'Header metadata must honor category visibility.');
assert.match(info, /in_array\('date', \$info_fields, true\)/, 'Header metadata must honor date visibility.');
assert.match(info, /in_array\('time', \$info_fields, true\)/, 'Header metadata must honor time visibility.');
assert.match(publicClass, /assets\/public\/css\/public-calls\.css/, 'The dedicated presentation stylesheet must be enqueued.');
assert.match(callsCss, /grid-template-areas:.*pagination/s, 'The overview grid must reserve a pagination area.');
assert.doesNotMatch(commonCss, /Independent public incident overview and detail presentation/, 'The dedicated presentation styles must not leak into the shared stylesheet.');

console.log('Public overview and detail consistency checks passed.');
