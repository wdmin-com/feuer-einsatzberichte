const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const installer = fs.readFileSync(path.join(root, 'includes', 'class-installer.php'), 'utf8');

test('current installations leave the installer before maintenance queries run', () => {
    const updateStart = installer.indexOf('public static function update()');
    const defaultsCall = installer.indexOf('self::set_default_options();', updateStart);
    const fastReturn = installer.indexOf("version_compare((string) $current_version, FEU_EINSATZ_VERSION, '>=')", updateStart);

    assert.ok(updateStart >= 0, 'installer update method is missing');
    assert.ok(fastReturn > updateStart, 'installer has no current-version fast path');
    assert.ok(defaultsCall > fastReturn, 'maintenance work runs before the current-version fast path');
});

test('deactivation removes every plugin background queue', () => {
    for (const hook of [
        'feu_einsatz_daily_image_integrity',
        'feu_einsatz_warm_report_cache',
        'feu_einsatz_generate_map_preview_background',
        'feu_einsatz_background_geocode',
        'feu_einsatz_prime_watermark_cache',
        'feu_einsatz_generate_share_card_background',
        'feu_einsatz_prime_public_map',
        'feu_einsatz_prime_public_station',
    ]) {
        assert.match(installer, new RegExp(`['\"]${hook}['\"]`), `missing cron cleanup for ${hook}`);
    }
});
