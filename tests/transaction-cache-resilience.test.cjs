const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const backup = fs.readFileSync(path.join(root, 'includes', 'class-backup-manager.php'), 'utf8');
const database = fs.readFileSync(path.join(root, 'includes', 'class-database.php'), 'utf8');

test('failed archive restores flush non-transactional WordPress caches', () => {
    assert.match(
        backup,
        /catch\s*\(Throwable \$error\)\s*\{\s*\$wpdb->query\('ROLLBACK'\);[\s\S]*?wp_cache_flush\(\);/,
    );
});

test('failed Mannschaft profile merges invalidate changed report meta caches', () => {
    assert.match(database, /\$updated_report_ids\[\]\s*=\s*\$report_id/);
    assert.match(
        database,
        /catch\s*\(Throwable \$error\)\s*\{\s*\$this->wpdb->query\('ROLLBACK'\);[\s\S]*?clean_post_cache\(\$report_id\);/,
    );
});
