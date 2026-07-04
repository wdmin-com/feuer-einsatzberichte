<?php
$manifest_path = __DIR__ . '/update-manifest.json';
$plugin_file_path = __DIR__ . '/feuer-einsatzberichte.php';
$plugin_version = '3.2.9';

if (is_readable($manifest_path)) {
    $manifest = json_decode((string) file_get_contents($manifest_path), true);

    if (is_array($manifest) && !empty($manifest['version'])) {
        $plugin_version = preg_replace('/[^0-9A-Za-z.\-]/', '', (string) $manifest['version']);
    }
}

if ($plugin_version === '3.2.9' && is_readable($plugin_file_path)) {
    $plugin_file_header = (string) file_get_contents($plugin_file_path, false, null, 0, 2048);

    if (preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $plugin_file_header, $matches)) {
        $plugin_version = preg_replace('/[^0-9A-Za-z.\-]/', '', (string) $matches[1]);
    }
}

$plugin_version = $plugin_version !== '' ? $plugin_version : '3.2.9';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feuer-Einsatzberichte - WordPress Plugin fuer Feuerwehren</title>
    <meta name="description" content="Feuer-Einsatzberichte ist ein WordPress Plugin fuer professionelle Einsatzberichte, Karten, animierte Statistiken, Teilnehmerverwaltung und oeffentliche Feuerwehr-Kommunikation.">
    <style>
        :root {
            --feu-bg: #f3f6f9;
            --feu-card: #ffffff;
            --feu-text: #101828;
            --feu-muted: #53657d;
            --feu-line: #d9e2ec;
            --feu-red: #e32632;
            --feu-red-dark: #b91824;
            --feu-blue: #0b2948;
            --feu-blue-soft: #e8f1fb;
            --feu-shadow: 0 22px 70px rgba(11, 41, 72, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(227, 38, 50, 0.10), transparent 34rem),
                linear-gradient(135deg, #f8fafc 0%, var(--feu-bg) 56%, #eef3f8 100%);
            color: var(--feu-text);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            line-height: 1.6;
        }

        a {
            color: var(--feu-red-dark);
            text-decoration: none;
        }

        a:hover,
        a:focus {
            text-decoration: underline;
        }

        .page {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
            padding: 48px 0 56px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.65fr);
            gap: 28px;
            align-items: stretch;
            margin-bottom: 28px;
        }

        .hero-main,
        .hero-side,
        .card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--feu-line);
            border-radius: 22px;
            box-shadow: var(--feu-shadow);
        }

        .hero-main {
            padding: clamp(32px, 5vw, 58px);
            position: relative;
            overflow: hidden;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: -80px;
            bottom: -96px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(227, 38, 50, 0.08);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            color: var(--feu-red-dark);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 28px;
            height: 3px;
            border-radius: 99px;
            background: var(--feu-red);
        }

        h1,
        h2,
        h3 {
            margin: 0;
            line-height: 1.12;
            color: var(--feu-blue);
        }

        h1 {
            max-width: 820px;
            font-size: clamp(38px, 7vw, 78px);
            letter-spacing: -0.055em;
        }

        h2 {
            margin-bottom: 12px;
            font-size: clamp(26px, 3vw, 38px);
            letter-spacing: -0.035em;
        }

        h3 {
            margin-bottom: 8px;
            font-size: 18px;
        }

        .lead {
            max-width: 790px;
            margin: 22px 0 0;
            color: var(--feu-muted);
            font-size: clamp(18px, 2vw, 22px);
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 800;
            text-decoration: none;
        }

        .button-primary {
            background: var(--feu-red);
            color: #ffffff;
        }

        .button-secondary {
            background: var(--feu-blue-soft);
            color: var(--feu-blue);
        }

        .hero-side {
            padding: 28px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 22px;
        }

        .status {
            display: grid;
            gap: 14px;
        }

        .status-item {
            padding: 16px;
            border: 1px solid var(--feu-line);
            border-radius: 16px;
            background: #fbfdff;
        }

        .status-label {
            display: block;
            color: var(--feu-muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .status-value {
            display: block;
            margin-top: 4px;
            color: var(--feu-blue);
            font-size: 20px;
            font-weight: 900;
        }

        .section {
            margin-top: 28px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .grid-two {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .card {
            padding: 26px;
        }

        .card p,
        .card ul {
            margin: 0;
            color: var(--feu-muted);
        }

        .card ul {
            padding-left: 20px;
        }

        .card li + li {
            margin-top: 8px;
        }

        .feature {
            min-height: 100%;
        }

        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            margin-bottom: 16px;
            border-radius: 14px;
            background: var(--feu-blue-soft);
            color: var(--feu-red-dark);
            font-weight: 900;
        }

        .requirements {
            border-left: 5px solid var(--feu-red);
        }

        .author {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }

        .author strong {
            color: var(--feu-blue);
        }

        .footer {
            margin-top: 26px;
            color: var(--feu-muted);
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 860px) {
            .hero,
            .grid,
            .grid-two {
                grid-template-columns: 1fr;
            }

            .page {
                width: min(100% - 20px, 1160px);
                padding-top: 22px;
            }

            .hero-main,
            .hero-side,
            .card {
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="hero" aria-labelledby="plugin-title">
            <div class="hero-main">
                <span class="eyebrow">WordPress Plugin fuer Feuerwehren</span>
                <h1 id="plugin-title">Feuer-Einsatzberichte fuer moderne Feuerwehr-Websites.</h1>
                <p class="lead">
                    Feuer-Einsatzberichte erweitert WordPress um einen spezialisierten Redaktionsablauf fuer
                    Einsatzberichte, Kartenbilder, Teilnehmerverwaltung, animierte Statistiken und oeffentliche
                    Feuerwehr-Kommunikation. Diese Seite beschreibt Funktionen und Einsatzbereiche; direkte
                    Paket-Downloads werden hier nicht angeboten.
                </p>
                <div class="hero-actions">
                    <a class="button button-primary" href="https://wdmin.com/plugins/feuer-einsatzberichte/">Pluginbeschreibung</a>
                    <a class="button button-secondary" href="https://wdmin.com/">Autorenseite</a>
                </div>
            </div>

            <aside class="hero-side" aria-label="Plugin Informationen">
                <div>
                    <h2>Pluginprofil</h2>
                    <p>
                        Entwickelt fuer Freiwillige Feuerwehren, die Einsaetze strukturiert erfassen,
                        praesentieren und intern auswerten moechten.
                    </p>
                </div>
                <div class="status">
                    <div class="status-item">
                        <span class="status-label">Aktuelle Version</span>
                        <span class="status-value"><?php echo htmlspecialchars($plugin_version, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Entwicklung</span>
                        <span class="status-value">Walter Faerber</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Website</span>
                        <span class="status-value">wdmin.com</span>
                    </div>
                </div>
            </aside>
        </section>

        <section class="section" aria-labelledby="functions-title">
            <h2 id="functions-title">Was das Plugin leistet</h2>
            <div class="grid">
                <article class="card feature">
                    <span class="feature-icon">01</span>
                    <h3>Einsatzberichte erfassen</h3>
                    <p>
                        Einsatzart, Einsatznummer, Datum, Uhrzeit, Einsatzort, Kategorien, Beschreibung,
                        Fotos und Veroeffentlichung werden in einem auf Feuerwehren abgestimmten Workflow gepflegt.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">02</span>
                    <h3>Karten und Share-Bilder</h3>
                    <p>
                        Kartenbilder mit Strassenmarkierung, Feuerwehrhaus, Copyright-Hinweis,
                        Wasserzeichen und konfigurierbarer Share-Grafik werden fuer Beitraege vorbereitet.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">03</span>
                    <h3>Mannschaft und Funktionen</h3>
                    <p>
                        Teilnehmer, Standardfunktionen, Kraefte vor Ort und Einsatzfunktionen lassen sich
                        zentral verwalten und direkt mit Einsatzberichten verbinden.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">04</span>
                    <h3>Dashboard und Schnelleingabe</h3>
                    <p>
                        Kompakte Admin-Widgets, Schnellzugriff, Schnelleingabe und Statusanzeigen
                        reduzieren den Aufwand bei der regelmaessigen Einsatzpflege.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">05</span>
                    <h3>Animierte Statistik</h3>
                    <p>
                        Live-Visualisierung, animierte Kennzahlen, Monatsverteilung, Kategorien,
                        Kalenderansichten und Aktivitaetskarte machen Entwicklungen im Adminbereich schnell sichtbar.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">06</span>
                    <h3>Archiv und Protokolle</h3>
                    <p>
                        Archivfunktionen, Logs, Backup-Werkzeuge und Statusmeldungen helfen dabei,
                        Datenbestand, Wartung und Fehleranalyse kontrolliert im Blick zu behalten.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">07</span>
                    <h3>Shortcodes und Frontend</h3>
                    <p>
                        Oeffentliche Einsatzlisten, Detailseiten, weitere Einsatzberichte und Layoutvarianten
                        koennen in bestehende WordPress-Seiten eingebunden werden.
                    </p>
                </article>
                <article class="card feature">
                    <span class="feature-icon">08</span>
                    <h3>Updates ohne oeffentliche Download-Schaltflaeche</h3>
                    <p>
                        Update-Informationen werden fuer WordPress bereitgestellt, waehrend die oeffentliche
                        Projektseite auf Beschreibung, Funktionen und Autor verweist.
                    </p>
                </article>
            </div>
        </section>

        <section class="section" aria-labelledby="benefits-title">
            <h2 id="benefits-title">Vorteile fuer den Betrieb</h2>
            <div class="grid-two">
                <article class="card">
                    <h3>Schneller publizieren</h3>
                    <p>
                        Wiederkehrende Angaben, Strassen-Memory, PLZ-Unterstuetzung, Kategorien,
                        Teilnehmer und Hintergrundprozesse senken den manuellen Aufwand pro Einsatzbericht.
                    </p>
                </article>
                <article class="card">
                    <h3>Einheitliches Erscheinungsbild</h3>
                    <p>
                        Kartenbild, Share-Grafik, Logo, Wasserzeichen, Texte, Farben und Anzeigeelemente
                        bleiben konsistent und muessen nicht bei jedem Beitrag neu aufgebaut werden.
                    </p>
                </article>
                <article class="card">
                    <h3>Lokale Kontrolle</h3>
                    <p>
                        Admin-Oberflaeche, Einstellungen, Rollenrechte und Plugin-Status arbeiten innerhalb
                        der WordPress-Installation. Externe Abhaengigkeiten werden auf das notwendige Mass reduziert.
                    </p>
                </article>
                <article class="card">
                    <h3>Bessere Uebersicht</h3>
                    <p>
                        Dashboard, Statusmeldungen, animierte Statistiken und Archivfunktionen zeigen schnell,
                        welche Daten fehlen, welche Kartenbilder warten und wie sich Einsaetze entwickeln.
                    </p>
                </article>
            </div>
        </section>

        <section class="section" aria-labelledby="requirements-title">
            <article class="card requirements">
                <h2 id="requirements-title">Ausgelegt fuer Anforderungen in Deutschland</h2>
                <ul>
                    <li>Unterstuetzt eine datenschutzbewusste Arbeitsweise nach DSGVO-Grundsaetzen durch Rollenrechte, lokale Plugin-Assets und vermeidbare externe Admin-Abfragen.</li>
                    <li>Beruecksichtigt die sichtbare Attribution fuer Kartenmaterial, insbesondere Leaflet und OpenStreetMap, damit Quellenhinweise nicht in Share- und Kartenbildern verloren gehen.</li>
                    <li>Hilft bei einer kontrollierten Veroeffentlichung von Einsatzinformationen, ohne personenbezogene oder einsatztaktisch sensible Daten erzwingen zu muessen.</li>
                    <li>Unterstuetzt Bild-, Logo- und Wasserzeichenverwaltung, damit Urheberrechte, Bildrechte und ein einheitlicher Auftritt leichter eingehalten werden koennen.</li>
                    <li>Impressum, Datenschutzerklaerung, redaktionelle Freigabe und rechtliche Bewertung bleiben Aufgabe des jeweiligen Website-Betreibers.</li>
                </ul>
            </article>
        </section>

        <section class="section" aria-labelledby="author-title">
            <article class="card author">
                <div>
                    <h2 id="author-title">Autor und Projekt</h2>
                    <p>
                        Entwicklung: <strong>Walter Faerber</strong><br>
                        Pluginbeschreibung: <a href="https://wdmin.com/plugins/feuer-einsatzberichte/">Feuer-Einsatzberichte</a><br>
                        Autorenseite: <a href="https://wdmin.com/">wdmin.com</a>
                    </p>
                </div>
                <a class="button button-primary" href="https://wdmin.com/plugins/feuer-einsatzberichte/">Pluginbeschreibung</a>
            </article>
        </section>

        <p class="footer">
            Feuer-Einsatzberichte ist ein spezialisiertes WordPress Plugin fuer Einsatzberichte, Karten,
            animierte Statistiken und Feuerwehr-Kommunikation. Diese Informationsseite enthaelt keine
            direkte Download-Schaltflaeche fuer Plugin-Pakete.
        </p>
    </main>
</body>
</html>
