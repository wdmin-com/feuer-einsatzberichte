const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const admin = fs.readFileSync(path.resolve(__dirname, '..', 'includes', 'class-admin.php'), 'utf8');

test('temporary save hook removal is always restored with finally', () => {
    const removal = "remove_action('save_post', [$this, 'save_post_data'], 10);";
    const restoration = "add_action('save_post', [$this, 'save_post_data'], 10, 3);";
    let offset = 0;
    let count = 0;

    while ((offset = admin.indexOf(removal, offset)) >= 0) {
        const nextRemoval = admin.indexOf(removal, offset + removal.length);
        const scopeEnd = nextRemoval >= 0 ? nextRemoval : admin.length;
        const block = admin.slice(offset, scopeEnd);

        assert.match(block, /try\s*\{[\s\S]*?\}\s*finally\s*\{[\s\S]*?add_action\('save_post', \[\$this, 'save_post_data'\], 10, 3\);/, `save hook removal near offset ${offset} is not protected by finally`);
        assert.ok(block.indexOf(restoration) >= 0, `save hook removal near offset ${offset} is never restored`);
        count += 1;
        offset += removal.length;
    }

    assert.ok(count >= 5, 'expected all report save paths to suspend the save hook');
});
