<?php
/**
 * FEU_Einsatz_Participant_Ranking
 *
 * PIN-Schutz und Session-Logik für das Teilnehmer-Ranking.
 *
 * Ausgelagert aus class-admin.php in Version 3.1.35 (Phase 1 Refactoring).
 * Alle Methoden waren bereits statisch — kein Behavior-Change.
 *
 * Aufrufer (keine Änderung nötig, da Klassen-Name identisch bleibt
 * gegenüber FEU_Einsatz_Admin::…):
 *   - includes/class-ajax-handler.php
 *   - templates/admin/settings.php
 *   - templates/admin/statistics.php
 *
 * Rückwärts-Kompatibilität: class-admin.php delegiert alle statischen
 * Methoden per forward-Methode an diese Klasse, sodass bestehende
 * Aufrufe via FEU_Einsatz_Admin::… weiterhin funktionieren.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Participant_Ranking {

    const PIN_OPTION    = 'feu_einsatz_participant_ranking_pin';
    const UNLOCK_META   = '_feu_einsatz_participant_ranking_unlocked_until';
    const UNLOCK_TTL    = 43200; // 12 Stunden in Sekunden
    const FAILED_ATTEMPTS_META = '_feu_einsatz_participant_ranking_failed_attempts';
    const LOCKED_UNTIL_META = '_feu_einsatz_participant_ranking_locked_until';
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_TTL = 900;

    // ── PIN-Datei (Legacy) ───────────────────────────────────────────

    public static function get_pin_file_path(): string {
        return trailingslashit(FEU_EINSATZ_PLUGIN_DIR) . 'feuer-einsatzberichte-participant-ranking-pin.php';
    }

    public static function cleanup_legacy_pin_file(): bool {
        $pin_file = self::get_pin_file_path();

        if (!file_exists($pin_file)) {
            return true;
        }

        if (function_exists('wp_delete_file')) {
            wp_delete_file($pin_file);
        } else {
            @unlink($pin_file);
        }

        return !file_exists($pin_file);
    }

    // ── PIN-Verwaltung ───────────────────────────────────────────────

    public static function sanitize_pin(string $pin): string {
        $pin = trim(sanitize_text_field($pin));

        if ('' === $pin) {
            return '';
        }

        return strlen($pin) > 64 ? substr($pin, 0, 64) : $pin;
    }

    public static function get_pin(): string {
        $stored = get_option(self::PIN_OPTION, '');

        if (is_scalar($stored)) {
            $sanitized = self::sanitize_pin((string) $stored);

            if ('' !== $sanitized) {
                return $sanitized;
            }
        }

        return '';
    }

    public static function update_pin(string $pin): bool {
        $pin = self::sanitize_pin($pin);

        if (strlen($pin) < 4) {
            return false;
        }

        $hash = password_hash($pin, PASSWORD_DEFAULT);

        if (!is_string($hash) || '' === $hash) {
            return false;
        }

        $updated = update_option(self::PIN_OPTION, $hash, false);
        self::cleanup_legacy_pin_file();

        return $updated;
    }

    public static function verify_pin(string $pin): bool {
        $expected  = self::get_pin();
        $submitted = self::sanitize_pin($pin);
        $user_id = get_current_user_id();

        if (
            '' === $expected
            || '' === $submitted
            || !$user_id
            || (int) get_user_meta($user_id, self::LOCKED_UNTIL_META, true) > time()
        ) {
            return false;
        }

        $password_info = password_get_info($expected);
        $is_hashed = !empty($password_info['algo']);
        $is_valid = $is_hashed
            ? password_verify($submitted, $expected)
            : hash_equals($expected, $submitted);

        if ($is_valid) {
            delete_user_meta($user_id, self::FAILED_ATTEMPTS_META);
            delete_user_meta($user_id, self::LOCKED_UNTIL_META);

            if (!$is_hashed) {
                self::update_pin($submitted);
            } elseif (password_needs_rehash($expected, PASSWORD_DEFAULT)) {
                self::update_pin($submitted);
            }

            return true;
        }

        $failed_attempts = (int) get_user_meta($user_id, self::FAILED_ATTEMPTS_META, true) + 1;
        update_user_meta($user_id, self::FAILED_ATTEMPTS_META, $failed_attempts);

        if ($failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            update_user_meta($user_id, self::LOCKED_UNTIL_META, time() + self::LOCKOUT_TTL);
            delete_user_meta($user_id, self::FAILED_ATTEMPTS_META);
        }

        return false;
    }

    // ── Session-Status ───────────────────────────────────────────────

    public static function is_unlocked_for_current_user(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $unlock_until = (int) get_user_meta(get_current_user_id(), self::UNLOCK_META, true);

        return $unlock_until >= time();
    }

    public static function unlock_for_current_user(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        update_user_meta(
            get_current_user_id(),
            self::UNLOCK_META,
            time() + self::UNLOCK_TTL
        );

        return true;
    }

    public static function lock_for_current_user(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        delete_user_meta(get_current_user_id(), self::UNLOCK_META);

        return true;
    }
}
