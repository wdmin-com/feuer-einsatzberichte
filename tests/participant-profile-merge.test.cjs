const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const database = fs.readFileSync(path.join(root, 'includes', 'class-database.php'), 'utf8');
const admin = fs.readFileSync(path.join(root, 'includes', 'class-admin.php'), 'utf8');
const integration = fs.readFileSync(path.join(root, 'includes', 'class-mannschaft-integration.php'), 'utf8');
const template = fs.readFileSync(path.join(root, 'templates', 'admin', 'participants.php'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'assets', 'admin', 'css', 'admin-style.css'), 'utf8');

assert.match(database, /function merge_participant_profiles\(/, 'Database must provide an explicit profile merge operation.');
assert.match(database, /START TRANSACTION/, 'Profile merging must be transactional.');
assert.match(database, /ROLLBACK/, 'A partial merge must be rolled back.');
assert.match(database, /clean_post_cache\(\$report_id\)/, 'A rolled-back merge must discard stale report meta from the runtime cache.');
assert.match(database, /_feu_einsatz_teilnehmer/, 'Serialized report assignments must be migrated.');
assert.match(database, /prefer_participant_function/, 'Duplicate assignments must retain the more specific function.');
assert.match(database, /external_name_locked/, 'The selected local name must survive later external synchronization.');
assert.match(integration, /\], true\);/, 'Manual connection of differing names must preserve the local name.');
assert.match(admin, /admin_post_feu_einsatz_merge_participant_profiles/, 'The merge action must be registered.');
assert.match(admin, /check_admin_referer\('feu_einsatz_merge_participant_profiles'\)/, 'The merge action must verify its nonce.');
assert.match(template, /Mannschaftsprofile zusammenführen/, 'The participant screen must expose the merge workflow.');
assert.match(template, /<details class="feu-einsatz-mannschaft-sync feu-einsatz-profile-merge-disclosure"/, 'The merge workflow must stay collapsed until explicitly opened.');
assert.match(template, /\$merge_panel_open = isset\(\$_GET\['feu_mannschaft_merged'\]\)/, 'The merge panel should reopen after showing its result.');
assert.match(template, /feu_einsatz_confirm_profile_merge/, 'The destructive merge must require explicit confirmation.');
assert.match(template, /feu_einsatz_keep_primary_name/, 'The user must be able to keep the primary display name.');
assert.match(styles, /\.feu-einsatz-profile-merge-grid/, 'The merge workflow must have a responsive layout.');

console.log('Participant identity merge workflow checks passed.');
