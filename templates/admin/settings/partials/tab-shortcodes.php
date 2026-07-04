<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var string $active_tab */
?>

<div id="tab-shortcodes" class="feu-einsatz-tab-content" <?php if ('shortcodes' !== $active_tab) : ?>style="display:none"<?php endif; ?>>

    <div class="feu-admin-settings-section">
        <div class="feu-admin-settings-section-header">
            <h2><?php esc_html_e('Shortcodes', 'feuer-einsatzberichte'); ?></h2>
            <p class="description"><?php esc_html_e('Alle verfuegbaren Shortcodes des Plugins. Die Codes koennen direkt in WordPress-Seiten, Beitraege oder Widget-Bereiche eingefuegt werden.', 'feuer-einsatzberichte'); ?></p>
        </div>
    </div>

    <?php
    $shortcodes = [
        [
            'tag'         => '[feu_einsatz_anzahl]',
            'label'       => __('Einsatz-Anzahl', 'feuer-einsatzberichte'),
            'description' => __('Gibt die Anzahl der Einsätze für ein bestimmtes Jahr als einfache Zahl aus. Geeignet für Einbindung in Fließtext oder Widgets.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_anzahl zeitraum="aktuell"]',
            'attributes'  => [
                [
                    'name'    => 'zeitraum',
                    'default' => 'aktuell',
                    'values'  => 'aktuell | vorjahr / previous / last',
                    'note'    => __('aktuell = laufendes Jahr, vorjahr = Vorjahr', 'feuer-einsatzberichte'),
                ],
            ],
        ],
        [
            'tag'         => '[feu_einsatz_aktuell]',
            'label'       => __('Einsätze dieses Jahr', 'feuer-einsatzberichte'),
            'description' => __('Kurzform – gibt die Einsatz-Anzahl des aktuellen Jahres aus. Entspricht [feu_einsatz_anzahl zeitraum="aktuell"].', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_aktuell]',
            'attributes'  => [],
        ],
        [
            'tag'         => '[feu_einsatz_vorjahr]',
            'label'       => __('Einsätze Vorjahr', 'feuer-einsatzberichte'),
            'description' => __('Kurzform – gibt die Einsatz-Anzahl des Vorjahres aus. Entspricht [feu_einsatz_anzahl zeitraum="vorjahr"].', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_vorjahr]',
            'attributes'  => [],
        ],
        [
            'tag'         => '[feu_einsatz_seite]',
            'label'       => __('Einsatz-Übersichtsseite', 'feuer-einsatzberichte'),
            'description' => __('Vollständige Übersichtsseite mit Seitennavigation, Jahresfilter und Statistikzeile. Für eine dedizierte WordPress-Seite vorgesehen.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_seite posts_per_page="10" card_variant="modern"]',
            'attributes'  => [
                [
                    'name'    => 'jahr',
                    'default' => __('aktuelles Jahr', 'feuer-einsatzberichte'),
                    'values'  => '2023 | 2024 | …',
                    'note'    => __('Vorauswahl des Jahresfilters', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'posts_per_page',
                    'default' => '10',
                    'values'  => '1 – beliebig',
                    'note'    => __('Anzahl Berichte pro Seite', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'card_variant / card_style / kartenstil',
                    'default' => __('aus Einstellungen', 'feuer-einsatzberichte'),
                    'values'  => 'modern | compact | minimal | classic',
                    'note'    => __('Darstellungsstil der Beitragskarten', 'feuer-einsatzberichte'),
                ],
            ],
        ],
        [
            'tag'         => '[feu_einsatz_liste]',
            'label'       => __('Einsatzliste', 'feuer-einsatzberichte'),
            'description' => __('Listendarstellung der Einsatzberichte ohne Seitenlayout. Für die Einbindung in bestehende Seiten oder Sidebars.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_liste posts_per_page="5" card_variant="compact"]',
            'attributes'  => [
                [
                    'name'    => 'jahr',
                    'default' => __('aktuelles Jahr', 'feuer-einsatzberichte'),
                    'values'  => '2023 | 2024 | …',
                    'note'    => __('Jahreszahl des angezeigten Zeitraums', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'posts_per_page',
                    'default' => '10',
                    'values'  => '1 – beliebig',
                    'note'    => __('Anzahl Berichte pro Seite', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'card_variant / card_style / kartenstil',
                    'default' => __('aus Einstellungen', 'feuer-einsatzberichte'),
                    'values'  => 'modern | compact | minimal | classic',
                    'note'    => __('Darstellungsstil der Beitragskarten', 'feuer-einsatzberichte'),
                ],
            ],
        ],
        [
            'tag'         => '[feu_einsatz_sidebar]',
            'label'       => __('Sidebar-Übersicht', 'feuer-einsatzberichte'),
            'description' => __('Kompakte Darstellung der Einsatzberichte für Sidebars oder Fußbereiche.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_sidebar posts_per_page="5"]',
            'attributes'  => [
                [
                    'name'    => 'jahr',
                    'default' => __('aktuelles Jahr', 'feuer-einsatzberichte'),
                    'values'  => '2023 | 2024 | …',
                    'note'    => __('Jahreszahl des angezeigten Zeitraums', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'posts_per_page',
                    'default' => '10',
                    'values'  => '1 – beliebig',
                    'note'    => __('Maximale Anzahl der angezeigten Berichte', 'feuer-einsatzberichte'),
                ],
            ],
        ],
        [
            'tag'         => '[feu_einsatz_letzte]',
            'label'       => __('Neueste Einsatzberichte', 'feuer-einsatzberichte'),
            'description' => __('Zeigt die neuesten N Einsatzberichte – ideal für Startseiten oder Widgets.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_letzte anzahl="3" format="alarm"]',
            'attributes'  => [
                [
                    'name'    => 'anzahl',
                    'default' => '3',
                    'values'  => '1 – 50',
                    'note'    => __('Anzahl der angezeigten Berichte', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'format',
                    'default' => 'cards',
                    'values'  => 'cards | kompakt / list / liste | alarm / timeline',
                    'note'    => __('Karten-, kompakte oder redaktionelle Alarm-Liste', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'jahr',
                    'default' => '',
                    'values'  => '2023 | 2024 | …',
                    'note'    => __('Auf dieses Jahr einschränken (leer = alle Jahre)', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'card_variant / card_style / kartenstil',
                    'default' => __('aus Einstellungen', 'feuer-einsatzberichte'),
                    'values'  => 'modern | compact | minimal | classic',
                    'note'    => __('Darstellungsstil der Beitragskarten', 'feuer-einsatzberichte'),
                ],
            ],
        ],
        [
            'tag'         => '[feu_einsatz_alarm_liste]',
            'label'       => __('Redaktionelle Alarm-Liste', 'feuer-einsatzberichte'),
            'description' => __('Zeigt die letzten Einsatzberichte als klare Liste mit Datum, Uhrzeit, Einsatzart, Strasse, Beschreibung und optionalen Zusatzdaten.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_alarm_liste anzahl="3" anzeigen="zeit,nummer,ort,fotos"]',
            'attributes'  => [
                [
                    'name'    => 'anzahl',
                    'default' => '3',
                    'values'  => '1 - 50',
                    'note'    => __('Anzahl der ausgegebenen Einsatzberichte', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'jahr',
                    'default' => '',
                    'values'  => '2024 | 2025 | 2026',
                    'note'    => __('Optional auf ein Jahr begrenzen', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'anzeigen',
                    'default' => 'zeit,nummer,ort,fotos',
                    'values'  => 'zeit | nummer | ort | fotos',
                    'note'    => __('Kommagetrennte Zusatzinformationen pro Eintrag', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'beschreibung',
                    'default' => '1',
                    'values'  => '1 | 0',
                    'note'    => __('Kurzbeschreibung ein- oder ausblenden', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'uebertitel / titel',
                    'default' => __('Aktuelles Einsatzgeschehen / Zuletzt alarmiert.', 'feuer-einsatzberichte'),
                    'values'  => __('beliebiger Text', 'feuer-einsatzberichte'),
                    'note'    => __('Texte oberhalb der Liste anpassen', 'feuer-einsatzberichte'),
                ],
                [
                    'name'    => 'alle_text / alle_url',
                    'default' => __('Alle Einsätze / automatisch', 'feuer-einsatzberichte'),
                    'values'  => __('Linktext und optionale Ziel-URL', 'feuer-einsatzberichte'),
                    'note'    => __('Link zur vollständigen Einsatzübersicht', 'feuer-einsatzberichte'),
                ],
            ],
        ],
        [
            'tag'         => '[feu_einsatz_gebiet]',
            'label'       => __('Einsatzgebiet-Seite', 'feuer-einsatzberichte'),
            'description' => __('Zeigt die interaktive Einsatzgebiet-Karte mit PLZ-Zonen und Alarmierungspunkten. Muss unter Einstellungen → Karten aktiviert sein.', 'feuer-einsatzberichte'),
            'example'     => '[feu_einsatz_gebiet]',
            'attributes'  => [],
        ],
    ];
    ?>

    <div class="feu-admin-settings-section">
        <div class="feu-shortcodes-grid">
            <?php foreach ($shortcodes as $sc) : ?>
                <div class="feu-shortcode-card postbox">
                    <div class="feu-shortcode-card-header">
                        <code class="feu-shortcode-tag"><?php echo esc_html($sc['tag']); ?></code>
                        <strong class="feu-shortcode-label"><?php echo esc_html($sc['label']); ?></strong>
                    </div>
                    <div class="feu-shortcode-card-body">
                        <p class="description"><?php echo esc_html($sc['description']); ?></p>

                        <?php if (!empty($sc['attributes'])) : ?>
                            <table class="feu-shortcode-atts widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Attribut', 'feuer-einsatzberichte'); ?></th>
                                        <th><?php esc_html_e('Standard', 'feuer-einsatzberichte'); ?></th>
                                        <th><?php esc_html_e('Mögliche Werte', 'feuer-einsatzberichte'); ?></th>
                                        <th><?php esc_html_e('Hinweis', 'feuer-einsatzberichte'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sc['attributes'] as $att) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($att['name']); ?></code></td>
                                            <td><code><?php echo esc_html($att['default']); ?></code></td>
                                            <td><code><?php echo esc_html($att['values']); ?></code></td>
                                            <td><?php echo esc_html($att['note']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p class="description feu-shortcode-no-atts"><?php esc_html_e('Keine Attribute – Shortcode wird ohne Parameter verwendet.', 'feuer-einsatzberichte'); ?></p>
                        <?php endif; ?>

                        <div class="feu-shortcode-example">
                            <span class="feu-shortcode-example-label"><?php esc_html_e('Beispiel:', 'feuer-einsatzberichte'); ?></span>
                            <code class="feu-shortcode-example-code"><?php echo esc_html($sc['example']); ?></code>
                            <button type="button"
                                    class="button button-small feu-shortcode-copy"
                                    data-code="<?php echo esc_attr($sc['example']); ?>"
                                    title="<?php esc_attr_e('In Zwischenablage kopieren', 'feuer-einsatzberichte'); ?>">
                                <span class="ti ti-copy" aria-hidden="true"></span>
                                <?php esc_html_e('Kopieren', 'feuer-einsatzberichte'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<style>
.feu-shortcodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
    gap: 16px;
    padding: 0 0 20px;
}
.feu-shortcode-card {
    margin: 0 !important;
    padding: 0;
}
.feu-shortcode-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
    border-radius: 4px 4px 0 0;
}
.feu-shortcode-tag {
    font-size: 13px;
    background: #1d2327;
    color: #f0f0f1;
    padding: 3px 8px;
    border-radius: 3px;
    font-family: monospace;
    white-space: nowrap;
}
.feu-shortcode-label {
    font-size: 13px;
    color: #1d2327;
}
.feu-shortcode-card-body {
    padding: 14px 16px;
}
.feu-shortcode-card-body > .description {
    margin: 0 0 10px;
    color: #50575e;
}
.feu-shortcode-atts {
    margin: 0 0 12px;
    font-size: 12px;
}
.feu-shortcode-atts th {
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    color: #646970;
    padding: 6px 8px;
}
.feu-shortcode-atts td {
    padding: 5px 8px;
    vertical-align: top;
}
.feu-shortcode-atts td code {
    font-size: 11.5px;
    background: #f0f0f0;
    padding: 1px 4px;
    border-radius: 2px;
}
.feu-shortcode-no-atts {
    margin: 0 0 12px;
    font-style: italic;
}
.feu-shortcode-example {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding: 8px 10px;
    background: #f0f6fc;
    border-left: 3px solid #2271b1;
    border-radius: 0 3px 3px 0;
}
.feu-shortcode-example-label {
    font-size: 11px;
    font-weight: 600;
    color: #2271b1;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}
.feu-shortcode-example-code {
    font-size: 12px;
    background: transparent;
    color: #1d2327;
    flex: 1;
    word-break: break-all;
}
.feu-shortcode-copy.copied {
    background: #d1e7dd !important;
    border-color: #badbcc !important;
    color: #0f5132 !important;
}
</style>

<script>
(function() {
    document.querySelectorAll('.feu-shortcode-copy').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var code = btn.dataset.code || '';
            if (!code) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function() {
                    btn.classList.add('copied');
                    btn.textContent = '✓ Kopiert';
                    setTimeout(function() {
                        btn.classList.remove('copied');
                        btn.innerHTML = '<span class="ti ti-copy" aria-hidden="true"></span> Kopieren';
                    }, 1800);
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = code;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                btn.classList.add('copied');
                btn.textContent = '✓ Kopiert';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<span class="ti ti-copy" aria-hidden="true"></span> Kopieren';
                }, 1800);
            }
        });
    });
})();
</script>
