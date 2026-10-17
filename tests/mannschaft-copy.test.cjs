const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const projectRoot = path.resolve(__dirname, '..');
const roots = ['templates', 'includes', 'assets', 'index.php'];
const allowedExtensions = new Set(['.php', '.js', '.css']);
const legacyLabel = 'Teil' + 'nehmer';
const matches = [];

function inspect(target) {
    const absolute = path.join(projectRoot, target);
    const stat = fs.statSync(absolute);

    if (stat.isDirectory()) {
        fs.readdirSync(absolute).forEach((entry) => inspect(path.join(target, entry)));
        return;
    }

    if (!allowedExtensions.has(path.extname(absolute).toLowerCase())) {
        return;
    }

    fs.readFileSync(absolute, 'utf8').split(/\r?\n/).forEach((line, index) => {
        if (line.includes(legacyLabel)) {
            matches.push(`${target}:${index + 1}`);
        }
    });
}

roots.forEach(inspect);
assert.deepEqual(matches, [], `Legacy Mannschaft label remains in: ${matches.join(', ')}`);
console.log('Mannschaft terminology is consistent across user-facing plugin sources.');
