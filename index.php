<?php
declare(strict_types=1);

$manifest_path = __DIR__ . '/update-manifest.json';
$release_notes_path = __DIR__ . '/release-notes.json';
$plugin_file_path = __DIR__ . '/feuer-einsatzberichte.php';
$plugin_version = '';
$requires_wordpress = '7.1';
$requires_php = '8.1';
$latest_releases = [];

if (is_readable($manifest_path)) {
    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    if (is_array($manifest)) {
        $plugin_version = preg_replace('/[^0-9A-Za-z.\-]/', '', (string) ($manifest['version'] ?? '')) ?: '';
        $requires_wordpress = preg_replace('/[^0-9.]/', '', (string) ($manifest['requires'] ?? $requires_wordpress)) ?: $requires_wordpress;
        $requires_php = preg_replace('/[^0-9.]/', '', (string) ($manifest['requires_php'] ?? $requires_php)) ?: $requires_php;
    }
}

if (is_readable($plugin_file_path)) {
    $plugin_header = (string) file_get_contents($plugin_file_path, false, null, 0, 4096);
    if ($plugin_version === '' && preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $plugin_header, $matches)) {
        $plugin_version = preg_replace('/[^0-9A-Za-z.\-]/', '', (string) $matches[1]) ?: '';
    }
    if (preg_match('/^\s*\*\s*Requires at least:\s*([^\r\n]+)/mi', $plugin_header, $matches)) {
        $requires_wordpress = preg_replace('/[^0-9.]/', '', (string) $matches[1]) ?: $requires_wordpress;
    }
    if (preg_match('/^\s*\*\s*Requires PHP:\s*([^\r\n]+)/mi', $plugin_header, $matches)) {
        $requires_php = preg_replace('/[^0-9.]/', '', (string) $matches[1]) ?: $requires_php;
    }
}

if (is_readable($release_notes_path)) {
    $release_notes = json_decode((string) file_get_contents($release_notes_path), true);
    if (is_array($release_notes) && is_array($release_notes['releases'] ?? null)) {
        $latest_releases = array_slice($release_notes['releases'], 0, 4);
    }
}

$plugin_version = $plugin_version !== '' ? $plugin_version : 'Aktuell';
$canonical_url = 'https://wdmin.com/plugins/feuer-einsatzberichte/';
$social_image_url = $canonical_url . 'assets/images/plugin-social-preview.png';
$description = 'Feuer-Einsatzberichte ist das professionelle WordPress-Plugin für Einsatzberichte, Kartenbilder, Mannschaftsverwaltung, Statistiken und sichere Feuerwehr-Kommunikation.';

function feu_page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function feu_page_release_date(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp ? date('d.m.Y', $timestamp) : $date;
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feuer-Einsatzberichte – WordPress-Plugin für Feuerwehren</title>
    <meta name="description" content="<?php echo feu_page_escape($description); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#071b31">
    <link rel="canonical" href="<?php echo feu_page_escape($canonical_url); ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_DE">
    <meta property="og:site_name" content="wdmin.com">
    <meta property="og:title" content="Feuer-Einsatzberichte – professionelle Feuerwehr-Kommunikation in WordPress">
    <meta property="og:description" content="<?php echo feu_page_escape($description); ?>">
    <meta property="og:url" content="<?php echo feu_page_escape($canonical_url); ?>">
    <meta property="og:image" content="<?php echo feu_page_escape($social_image_url); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Feuer-Einsatzberichte für WordPress – Dashboard, Karte und Statistik">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Feuer-Einsatzberichte für WordPress">
    <meta name="twitter:description" content="<?php echo feu_page_escape($description); ?>">
    <meta name="twitter:image" content="<?php echo feu_page_escape($social_image_url); ?>">
    <script type="application/ld+json"><?php
    echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Feuer-Einsatzberichte',
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'WordPress-Plugin für Feuerwehren',
        'operatingSystem' => 'WordPress',
        'softwareVersion' => $plugin_version,
        'description' => $description,
        'url' => $canonical_url,
        'author' => ['@type' => 'Person', 'name' => 'Walter Faerber', 'url' => 'https://wdmin.com/'],
        'softwareRequirements' => sprintf('WordPress %s oder höher, PHP %s oder höher', $requires_wordpress, $requires_php),
        'inLanguage' => 'de-DE',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?></script>
    <style>
        :root {
            --ink: #071b31; --ink-2: #173d62; --muted: #587086;
            --surface: #fff; --soft: #f4f7fa; --blue-soft: #eaf4fc;
            --line: #dbe5ed; --red: #df2434; --red-dark: #b81625;
            --blue: #1477c9; --green: #0f9c75;
            --shadow: 0 24px 70px rgba(7, 27, 49, .13);
            --radius: 22px; --width: 1200px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; color: var(--ink); background: #fff;
            font: 16px/1.6 Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        a:focus-visible, summary:focus-visible { outline: 3px solid rgba(20,119,201,.35); outline-offset: 4px; border-radius: 8px; }
        .shell { width: min(var(--width), calc(100% - 40px)); margin-inline: auto; }
        .site-header {
            position: sticky; z-index: 30; top: 0; border-bottom: 1px solid rgba(219,229,237,.85);
            background: rgba(255,255,255,.92); backdrop-filter: blur(18px);
        }
        .nav { min-height: 74px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 850; letter-spacing: -.02em; text-decoration: none; }
        .brand-mark {
            width: 38px; height: 38px; display: grid; place-items: center; border-radius: 11px;
            color: #fff; background: var(--red); box-shadow: 0 8px 20px rgba(223,36,52,.24); font-size: 20px;
        }
        .nav-links { display: flex; gap: 26px; }
        .nav-links a { color: var(--muted); font-size: 14px; font-weight: 750; text-decoration: none; }
        .nav-links a:hover { color: var(--red-dark); }
        .version {
            display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 7px 13px;
            border: 1px solid var(--line); border-radius: 999px; background: var(--soft);
            color: var(--ink-2); font-size: 13px; font-weight: 850; white-space: nowrap;
        }
        .version::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 4px rgba(15,156,117,.12); }
        .hero {
            position: relative; overflow: hidden; padding: 88px 0 82px;
            background: radial-gradient(circle at 7% 18%, rgba(223,36,52,.11), transparent 30rem),
                        radial-gradient(circle at 94% 0, rgba(20,119,201,.13), transparent 34rem),
                        linear-gradient(180deg, #f9fbfd, #fff);
        }
        .hero::before {
            content: ""; position: absolute; inset: 0; opacity: .42; pointer-events: none;
            background-image: linear-gradient(rgba(7,27,49,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(7,27,49,.035) 1px,transparent 1px);
            background-size: 40px 40px; mask-image: linear-gradient(to bottom,#000,transparent 86%);
        }
        .hero-grid { position: relative; display: grid; grid-template-columns: 1fr minmax(440px,.9fr); gap: clamp(48px,7vw,92px); align-items: center; }
        .eyebrow { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 20px; color: var(--red-dark); font-size: 12px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
        .eyebrow::before { content: ""; width: 26px; height: 3px; border-radius: 999px; background: var(--red); }
        h1, h2, h3, p { margin-top: 0; }
        h1, h2, h3 { line-height: 1.08; text-wrap: balance; }
        h1 { margin-bottom: 24px; font-size: clamp(44px,5.6vw,76px); letter-spacing: -.058em; }
        h2 { margin-bottom: 16px; font-size: clamp(32px,4vw,50px); letter-spacing: -.045em; }
        h3 { margin-bottom: 10px; font-size: 20px; letter-spacing: -.025em; }
        .lead { max-width: 680px; margin-bottom: 30px; color: var(--muted); font-size: clamp(18px,2vw,21px); line-height: 1.65; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .button {
            min-height: 50px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 12px 20px; border: 1px solid transparent; border-radius: 999px;
            font-size: 15px; font-weight: 850; text-decoration: none; transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
        }
        .button:hover { transform: translateY(-2px); }
        .primary { color: #fff; background: var(--red); box-shadow: 0 12px 26px rgba(223,36,52,.23); }
        .primary:hover { background: var(--red-dark); box-shadow: 0 16px 32px rgba(223,36,52,.3); }
        .secondary { border-color: #c6d3df; color: var(--ink); background: rgba(255,255,255,.78); }
        .secondary:hover { background: #fff; box-shadow: 0 8px 24px rgba(7,27,49,.08); }
        .hero-points { display: flex; flex-wrap: wrap; gap: 9px 18px; margin-top: 26px; color: var(--muted); font-size: 13px; font-weight: 700; }
        .hero-points span::before { content: "✓"; margin-right: 7px; color: var(--green); font-weight: 950; }
        .product-window { position: relative; border: 1px solid #c9d6e1; border-radius: 24px; background: rgba(255,255,255,.96); box-shadow: var(--shadow); transform: rotate(.6deg); }
        .window-bar { height: 48px; display: flex; align-items: center; gap: 7px; padding: 0 18px; border-bottom: 1px solid var(--line); background: #f9fbfd; border-radius: 24px 24px 0 0; }
        .dot { width: 9px; height: 9px; border-radius: 50%; background: #c7d2dc; }
        .dot:first-child { background: #ef6a72; } .dot:nth-child(2) { background: #f0b742; } .dot:nth-child(3) { background: #52b98c; }
        .window-title { margin-left: auto; color: var(--muted); font-size: 11px; font-weight: 800; }
        .window-body { display: grid; grid-template-columns: 70px 1fr; min-height: 420px; }
        .window-side { display: grid; align-content: start; gap: 13px; padding: 18px 13px; border-radius: 0 0 0 24px; background: var(--ink); }
        .side-logo { width: 34px; height: 34px; display: grid; place-items: center; margin-inline: auto; border-radius: 10px; color: #fff; background: var(--red); font-weight: 950; }
        .side-line { width: 32px; height: 7px; margin-inline: auto; border-radius: 8px; background: rgba(255,255,255,.16); }
        .side-line.active { height: 30px; background: rgba(255,255,255,.14); }
        .window-content { padding: 22px; overflow: hidden; }
        .preview-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
        .preview-head strong { font-size: 17px; }
        .chip { padding: 6px 9px; border-radius: 999px; color: #087a59; background: #e8f8f2; font-size: 10px; font-weight: 900; }
        .metrics { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
        .metric { padding: 13px; border: 1px solid var(--line); border-radius: 13px; }
        .metric span { display: block; color: var(--muted); font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .metric strong { display: block; margin-top: 3px; font-size: 21px; line-height: 1.2; }
        .preview-grid { display: grid; grid-template-columns: 1.08fr .92fr; gap: 12px; margin-top: 12px; }
        .preview-card { min-width: 0; padding: 14px; border: 1px solid var(--line); border-radius: 14px; }
        .preview-label { margin-bottom: 12px; color: var(--ink-2); font-size: 10px; font-weight: 900; text-transform: uppercase; }
        .chart { height: 116px; display: flex; align-items: end; gap: 7px; border-bottom: 1px solid var(--line); background: repeating-linear-gradient(to bottom,transparent 0 28px,#edf1f5 28px 29px); }
        .bar { flex: 1; min-width: 8px; border-radius: 6px 6px 0 0; background: linear-gradient(#2f95db,#1265a8); }
        .bar:nth-child(1){height:42%}.bar:nth-child(2){height:64%}.bar:nth-child(3){height:54%}.bar:nth-child(4){height:84%}.bar:nth-child(5){height:72%}.bar:nth-child(6){height:98%;background:linear-gradient(#ef5662,#c92231)}
        .map {
            position: relative; height: 116px; overflow: hidden; border-radius: 9px;
            background: linear-gradient(32deg,transparent 48%,rgba(255,255,255,.95) 49% 53%,transparent 54%),
                        linear-gradient(115deg,transparent 45%,rgba(255,255,255,.9) 46% 50%,transparent 51%),
                        linear-gradient(145deg,#dff0dc 0 38%,#dceaf4 38% 47%,#eee8db 47%);
        }
        .map::before { content: ""; position: absolute; width: 110px; height: 4px; top: 54px; left: 14px; border-radius: 99px; background: var(--red); transform: rotate(-18deg); box-shadow: 0 0 0 2px #fff; }
        .pin { position: absolute; top: 36px; left: 60%; width: 27px; height: 27px; display: grid; place-items: center; border: 3px solid #fff; border-radius: 50% 50% 50% 4px; color: #fff; background: var(--red); font-size: 9px; font-weight: 950; transform: rotate(-45deg); box-shadow: 0 5px 12px rgba(7,27,49,.22); }
        .pin span { transform: rotate(45deg); }
        .preview-status { display: grid; gap: 8px; margin-top: 12px; color: var(--muted); font-size: 10px; font-weight: 700; }
        .preview-status span::before { content: ""; display: inline-block; width: 8px; height: 8px; margin-right: 9px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 3px rgba(15,156,117,.11); }
        .floating { position: absolute; right: -18px; bottom: -24px; width: 220px; padding: 15px 17px; border: 1px solid #d7e4ee; border-radius: 16px; background: rgba(255,255,255,.97); box-shadow: 0 16px 40px rgba(7,27,49,.18); transform: rotate(-2.6deg); }
        .floating strong, .floating span { display: block; } .floating strong { font-size: 13px; } .floating span { color: var(--muted); font-size: 11px; }
        .trust { border-block: 1px solid var(--line); background: var(--soft); }
        .trust-grid { min-height: 112px; display: grid; grid-template-columns: repeat(4,1fr); align-items: center; }
        .trust-item { min-height: 58px; padding: 0 28px; border-right: 1px solid var(--line); }
        .trust-item:first-child { padding-left: 0; } .trust-item:last-child { border: 0; }
        .trust-item strong, .trust-item span { display: block; } .trust-item strong { font-size: 15px; } .trust-item span { color: var(--muted); font-size: 13px; }
        .section { padding: 104px 0; }
        .section-soft { background: var(--soft); }
        .section-dark { background: var(--ink); color: #fff; }
        .section-head { max-width: 760px; margin-bottom: 46px; }
        .section-head p { margin-bottom: 0; color: var(--muted); font-size: 18px; }
        .workflow { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; }
        .step { position: relative; min-height: 240px; overflow: hidden; padding: 26px; border: 1px solid var(--line); border-radius: var(--radius); background: #fff; box-shadow: 0 8px 24px rgba(7,27,49,.06); }
        .step-no { width: 38px; height: 38px; display: grid; place-items: center; margin-bottom: 32px; border-radius: 12px; color: #fff; background: var(--ink); font-size: 12px; font-weight: 900; }
        .step p { margin: 0; color: var(--muted); font-size: 14px; }
        .feature-grid { display: grid; grid-template-columns: repeat(12,1fr); gap: 20px; }
        .feature { min-height: 300px; padding: 30px; border: 1px solid var(--line); border-radius: var(--radius); background: #fff; box-shadow: 0 8px 24px rgba(7,27,49,.06); }
        .span-7{grid-column:span 7}.span-5{grid-column:span 5}.span-4{grid-column:span 4}
        .feature.accent { border-color: rgba(223,36,52,.18); background: linear-gradient(145deg,#fff,#fff5f6); }
        .feature.blue { border-color: rgba(20,119,201,.18); background: linear-gradient(145deg,#fff,#eff7fd); }
        .feature-icon { width: 48px; height: 48px; display: grid; place-items: center; margin-bottom: 44px; border-radius: 14px; color: #0e5b9d; background: var(--blue-soft); font-size: 21px; font-weight: 900; }
        .accent .feature-icon { color: var(--red-dark); background: #fff0f1; }
        .feature p { max-width: 650px; margin-bottom: 18px; color: var(--muted); }
        .tags { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .tags li { padding: 6px 10px; border: 1px solid var(--line); border-radius: 999px; background: rgba(255,255,255,.78); color: var(--muted); font-size: 11px; font-weight: 800; }
        .showcase { display: grid; grid-template-columns: .78fr 1.22fr; gap: 54px; align-items: center; }
        .section-dark h2, .section-dark h3 { color: #fff; }
        .show-copy p { color: #a9bac9; font-size: 17px; }
        .checks { display: grid; gap: 14px; margin: 26px 0 0; padding: 0; list-style: none; }
        .checks li { display: flex; gap: 12px; color: #dce6ef; font-size: 14px; }
        .checks li::before { content: "✓"; flex: 0 0 24px; height: 24px; display: grid; place-items: center; border-radius: 8px; color: #55d4ac; background: rgba(15,156,117,.16); font-weight: 950; }
        .show-panel { padding: 18px; border: 1px solid rgba(255,255,255,.12); border-radius: 26px; background: rgba(255,255,255,.055); box-shadow: 0 40px 100px rgba(0,0,0,.24); }
        .show-toolbar { display: flex; justify-content: space-between; margin-bottom: 14px; color: #a9bac9; font-size: 11px; font-weight: 800; }
        .live::before { content: ""; display: inline-block; width: 7px; height: 7px; margin-right: 7px; border-radius: 50%; background: #4fd2a8; box-shadow: 0 0 0 4px rgba(79,210,168,.13); }
        .show-content { padding: clamp(20px,4vw,38px); border-radius: 18px; color: var(--ink); background: #f7f9fb; }
        .show-title { display: flex; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .show-title strong { font-size: clamp(22px,3vw,34px); letter-spacing: -.04em; }
        .show-title span { padding: 7px 11px; border-radius: 99px; color: #087a59; background: #e6f6f1; font-size: 10px; font-weight: 900; }
        .podium { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; align-items: end; }
        .podium-card { padding: 18px 12px; border: 1px solid var(--line); border-radius: 14px; background: #fff; text-align: center; }
        .podium-card.winner { padding-top: 28px; border-color: #e7b233; background: #fff9e8; }
        .podium-card b, .podium-card strong, .podium-card small, .podium-card em { display: block; }
        .podium-card b { color: var(--muted); font-size: 11px; } .podium-card strong { margin: 7px 0 3px; font-size: 13px; }
        .podium-card small { color: var(--muted); font-size: 10px; } .podium-card em { margin-top: 9px; color: #0e5b9d; font-size: 10px; font-style: normal; font-weight: 900; }
        .ranking { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .rank { display: flex; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: #fff; font-size: 10px; }
        .rank strong { color: var(--red-dark); }
        .release-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 18px; }
        .release { padding: 28px; border: 1px solid var(--line); border-radius: var(--radius); background: #fff; box-shadow: 0 8px 24px rgba(7,27,49,.06); }
        .release-meta { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .release-version { padding: 6px 10px; border-radius: 99px; color: #fff; background: var(--ink); font-size: 12px; font-weight: 900; }
        .release time { color: var(--muted); font-size: 12px; font-weight: 750; }
        .release p { margin: 0; color: var(--muted); font-size: 14px; }
        .requirements { display: grid; grid-template-columns: .9fr 1.1fr; gap: 30px; }
        .req-card, .security { padding: clamp(28px,5vw,48px); border-radius: 30px; }
        .req-card { color: #fff; background: var(--ink); }
        .req-card h2 { color: #fff; } .req-card > p { color: #a9bac9; }
        .req-list { display: grid; gap: 1px; margin-top: 30px; overflow: hidden; border: 1px solid rgba(255,255,255,.12); border-radius: 16px; background: rgba(255,255,255,.1); }
        .req { display: flex; justify-content: space-between; gap: 20px; padding: 15px 17px; background: rgba(7,27,49,.94); }
        .req span { color: #a9bac9; font-size: 13px; } .req strong { font-size: 14px; }
        .security { border: 1px solid var(--line); background: var(--soft); }
        .security-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 28px; }
        .security-item { padding: 18px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
        .security-item strong, .security-item span { display: block; } .security-item strong { font-size: 14px; } .security-item span { margin-top: 5px; color: var(--muted); font-size: 12px; }
        .faq { max-width: 880px; display: grid; gap: 12px; }
        details { border: 1px solid var(--line); border-radius: 16px; background: #fff; }
        summary { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 20px 22px; cursor: pointer; color: var(--ink); font-weight: 850; list-style: none; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: "+"; flex: 0 0 28px; height: 28px; display: grid; place-items: center; border-radius: 9px; color: #0e5b9d; background: var(--blue-soft); }
        details[open] summary::after { content: "−"; }
        .answer { padding: 0 22px 22px; color: var(--muted); } .answer p { margin: 0; }
        .cta { padding: 84px 0; }
        .cta-card { position: relative; overflow: hidden; display: grid; grid-template-columns: 1fr auto; gap: 40px; align-items: center; padding: clamp(34px,6vw,66px); border-radius: 30px; color: #fff; background: linear-gradient(135deg,var(--ink),#103a61); box-shadow: var(--shadow); }
        .cta-card h2 { max-width: 700px; color: #fff; } .cta-card p { max-width: 700px; margin: 0; color: #b9c8d5; }
        .cta .secondary { border-color: rgba(255,255,255,.22); color: #fff; background: rgba(255,255,255,.1); }
        footer { border-top: 1px solid var(--line); background: var(--soft); }
        .footer-grid { min-height: 150px; display: flex; align-items: center; justify-content: space-between; gap: 32px; }
        .footer-copy { max-width: 680px; margin: 0; color: var(--muted); font-size: 13px; }
        .footer-links { display: flex; flex-wrap: wrap; gap: 18px; }
        .footer-links a { color: var(--muted); font-size: 13px; font-weight: 800; text-decoration: none; }
        @media (max-width: 1020px) {
            .nav-links { display: none; } .hero-grid, .showcase, .requirements { grid-template-columns: 1fr; }
            .hero-copy { max-width: 760px; } .hero-visual { max-width: 720px; }
            .workflow { grid-template-columns: 1fr 1fr; }
            .span-7,.span-5,.span-4 { grid-column: span 6; }
            .cta-card { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .shell { width: min(100% - 24px,var(--width)); } .site-header { position: static; } .nav { min-height: 64px; }
            .brand > span:last-child { display: none; } .hero { padding: 58px 0 62px; } .hero-grid { gap: 42px; }
            h1 { font-size: clamp(42px,14vw,58px); } .actions .button { width: 100%; }
            .product-window { transform: none; } .window-body { grid-template-columns: 48px 1fr; min-height: 360px; }
            .window-side { padding-inline: 7px; } .window-content { padding: 14px; }
            .metrics { grid-template-columns: 1fr 1fr; } .metric:nth-child(3), .preview-grid .preview-card:last-child { display: none; }
            .preview-grid { grid-template-columns: 1fr; } .floating { right: 4px; bottom: -34px; }
            .trust-grid { grid-template-columns: 1fr 1fr; padding: 20px 0; }
            .trust-item { padding: 16px; border-right: 0; border-bottom: 1px solid var(--line); } .trust-item:nth-last-child(-n+2) { border-bottom: 0; }
            .section { padding: 76px 0; } .workflow, .release-grid, .security-grid { grid-template-columns: 1fr; }
            .span-7,.span-5,.span-4 { grid-column: span 12; } .feature { min-height: 0; } .feature-icon { margin-bottom: 30px; }
            .podium, .ranking { grid-template-columns: 1fr; } .podium-card.winner { order: -1; }
            .footer-grid { align-items: flex-start; flex-direction: column; padding: 34px 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; } *,*::before,*::after { transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="shell nav">
            <a class="brand" href="#start" aria-label="Feuer-Einsatzberichte – zum Seitenanfang"><span class="brand-mark" aria-hidden="true">♨</span><span>Feuer-Einsatzberichte</span></a>
            <nav class="nav-links" aria-label="Seitennavigation">
                <a href="#workflow">Workflow</a><a href="#funktionen">Funktionen</a><a href="#statistik">Statistik</a><a href="#neu">Neu</a><a href="#anforderungen">Anforderungen</a>
            </nav>
            <span class="version">Version <?php echo feu_page_escape($plugin_version); ?></span>
        </div>
    </header>

    <main id="start">
        <section class="hero" aria-labelledby="page-title">
            <div class="shell hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow">WordPress-Plugin für Feuerwehren</span>
                    <h1 id="page-title">Vom Einsatz bis zur fertigen Veröffentlichung.</h1>
                    <p class="lead">Feuer-Einsatzberichte bündelt Redaktion, Karten, Fotos, Mannschaft, Statistik und Archivierung in einem professionellen Arbeitsablauf – direkt in WordPress und für den Feuerwehralltag entwickelt.</p>
                    <div class="actions"><a class="button primary" href="#funktionen">Funktionen entdecken <span aria-hidden="true">↓</span></a><a class="button secondary" href="#neu">Aktuelle Änderungen</a></div>
                    <div class="hero-points"><span>Redaktioneller Freigabeprozess</span><span>Lokale Karten- und Bild-Caches</span><span>Rollen und Rechte</span></div>
                </div>
                <div class="hero-visual" aria-label="Vorschau des Plugin-Dashboards">
                    <div class="product-window">
                        <div class="window-bar" aria-hidden="true"><i class="dot"></i><i class="dot"></i><i class="dot"></i><span class="window-title">Feuer-Einsatzberichte · Dashboard</span></div>
                        <div class="window-body">
                            <div class="window-side" aria-hidden="true"><span class="side-logo">F</span><i class="side-line active"></i><i class="side-line"></i><i class="side-line"></i><i class="side-line"></i></div>
                            <div class="window-content">
                                <div class="preview-head"><strong>Einsatzübersicht</strong><span class="chip">System bereit</span></div>
                                <div class="metrics"><div class="metric"><span>Einsätze</span><strong>42</strong></div><div class="metric"><span>Veröffentlicht</span><strong>39</strong></div><div class="metric"><span>Kartenbilder</span><strong>100%</strong></div></div>
                                <div class="preview-grid">
                                    <div class="preview-card"><div class="preview-label">Einsätze nach Monat</div><div class="chart" aria-hidden="true"><i class="bar"></i><i class="bar"></i><i class="bar"></i><i class="bar"></i><i class="bar"></i><i class="bar"></i></div></div>
                                    <div class="preview-card"><div class="preview-label">Kartenstatus</div><div class="map" aria-hidden="true"><span class="pin"><span>F</span></span></div></div>
                                </div>
                                <div class="preview-status"><span>Kartenbild und Share-Grafik aktuell</span><span>Hintergrundaufträge erfolgreich</span></div>
                            </div>
                        </div>
                        <div class="floating"><strong>Live-Karte verfügbar</strong><span>Das lokale Vorschaubild bleibt bis zum erfolgreichen Laden sichtbar.</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="trust" aria-label="Plugin-Eigenschaften"><div class="shell trust-grid">
            <div class="trust-item"><strong>Für den Einsatzalltag</strong><span>Spezialisierter Redaktionsablauf</span></div>
            <div class="trust-item"><strong>Datenschutzbewusst</strong><span>Externe Daten nur wenn erforderlich</span></div>
            <div class="trust-item"><strong>Update-sicher</strong><span>GitHub Releases mit SHA-256-Prüfung</span></div>
            <div class="trust-item"><strong>Responsiv</strong><span>Adminbereich und Frontend</span></div>
        </div></section>

        <section class="section" id="workflow" aria-labelledby="workflow-title"><div class="shell">
            <div class="section-head"><span class="eyebrow">Ein durchgängiger Prozess</span><h2 id="workflow-title">Weniger Einzelschritte. Mehr Kontrolle.</h2><p>Alle Arbeitsschritte bleiben miteinander verbunden – von den Einsatzdaten bis zur öffentlichen Seite und späteren Auswertung.</p></div>
            <div class="workflow">
                <article class="step"><span class="step-no">01</span><h3>Einsatz erfassen</h3><p>Adresse, Stichwort, Zeit, Kräfte, Mannschaft und Berichtstext in einem klar gegliederten Editor pflegen.</p></article>
                <article class="step"><span class="step-no">02</span><h3>Inhalte prüfen</h3><p>Pflichtdaten, Adresse, Karte, Medien, URL und Veröffentlichung vor der Freigabe kontrollieren.</p></article>
                <article class="step"><span class="step-no">03</span><h3>Automatisch aufbereiten</h3><p>Kartenbild, Social Preview, Wasserzeichen, SEO-Daten und Darstellung im Hintergrund erzeugen.</p></article>
                <article class="step"><span class="step-no">04</span><h3>Auswerten</h3><p>Einsätze, Stichworte, Zeiträume und Beteiligung der Mannschaft statistisch und im Präsentationsmodus darstellen.</p></article>
            </div>
        </div></section>

        <section class="section section-soft" id="funktionen" aria-labelledby="features-title"><div class="shell">
            <div class="section-head"><span class="eyebrow">Funktionsumfang</span><h2 id="features-title">Ein System statt vieler Insellösungen.</h2><p>Die Funktionen sind auf wiederkehrende Abläufe von Freiwilligen Feuerwehren abgestimmt und lassen sich zentral konfigurieren.</p></div>
            <div class="feature-grid">
                <article class="feature span-7 accent"><span class="feature-icon" aria-hidden="true">✎</span><h3>Professioneller Berichtseditor</h3><p>Entwürfe vollständig sichern, Einsatzdaten strukturiert bearbeiten, Veröffentlichungszeitpunkt wählen und Berichte vor der Freigabe prüfen.</p><ul class="tags"><li>Sofort</li><li>Zum Einsatzdatum</li><li>48 Stunden später</li><li>Freigabestatus</li><li>Entwurfswiederherstellung</li></ul></article>
                <article class="feature span-5 blue"><span class="feature-icon" aria-hidden="true">⌖</span><h3>Karten mit zuverlässigem Fallback</h3><p>Live-Vorschau, Straßenmarkierung und lokale Kartenbilder. Bei einem externen Ladefehler bleibt das gespeicherte Vorschaubild sichtbar.</p><ul class="tags"><li>Leaflet</li><li>OpenStreetMap</li><li>Lokaler Cache</li><li>Schnellprofile</li></ul></article>
                <article class="feature span-4"><span class="feature-icon" aria-hidden="true">▣</span><h3>Fotos und Medien</h3><p>Galerien, Beitragsbilder, Wasserzeichen, Bildschutz und kontrollierte Regenerierung beschädigter oder fehlender Dateien.</p></article>
                <article class="feature span-4"><span class="feature-icon" aria-hidden="true">◎</span><h3>SEO und Social Sharing</h3><p>Passende Seitentitel, Beschreibungen und Share-Grafiken sorgen beim Teilen und in Suchmaschinen für konsistente Informationen.</p></article>
                <article class="feature span-4"><span class="feature-icon" aria-hidden="true">↻</span><h3>Migration und Updates</h3><p>Geführte Datenmigration mit Einzelstatus, Wiederholungsversuchen und Diagnosen sowie signaturgeprüfte Updates über GitHub Releases.</p></article>
                <article class="feature span-5 blue"><span class="feature-icon" aria-hidden="true">♟</span><h3>Mannschaft und Funktionen</h3><p>Mannschaftsmitglieder, Einsatzfunktionen und Kräfte vor Ort zentral verwalten, archivieren und direkt Berichten zuordnen.</p><ul class="tags"><li>Profile</li><li>Archiv</li><li>Funktionen</li><li>Beteiligungsquote</li></ul></article>
                <article class="feature span-7 accent"><span class="feature-icon" aria-hidden="true">▥</span><h3>Dashboard, Archiv und Betrieb</h3><p>Arbeitszentrale für offene Aufgaben, Kartenstatus, letzte Berichte, Protokolle, Sicherungen und schnelle Aktionen – ergänzt durch Shortcodes und konfigurierbare Frontend-Ansichten.</p><ul class="tags"><li>Schnelleingabe</li><li>Protokolle</li><li>Backup</li><li>Shortcodes</li><li>Rollenrechte</li><li>Statusdiagnose</li></ul></article>
            </div>
        </div></section>

        <section class="section section-dark" id="statistik" aria-labelledby="statistics-title"><div class="shell showcase">
            <div class="show-copy"><span class="eyebrow">Statistik und Präsentation</span><h2 id="statistics-title">Aus Daten wird ein verständlicher Überblick.</h2><p>Jahreszahlen, Einsatzstichworte, Kalender, Aktivitätskarte und Mannschafts-Ranking lassen sich kompakt auswerten oder im Vollbild präsentieren.</p><ul class="checks"><li>Beteiligungsquote als Einsätze und Prozentwert</li><li>Konfigurierbare Grenze für minimale Beteiligung</li><li>Detailansicht der Mannschaft ohne Orientierungsverlust</li><li>Präsentationsmodus und PDF-Ausgabe</li></ul></div>
            <div class="show-panel" aria-label="Vorschau der Mannschaftsstatistik">
                <div class="show-toolbar"><span>Mannschaft · Jahresübersicht</span><span class="live">Live-Daten</span></div>
                <div class="show-content"><div class="show-title"><strong>Mannschaft · 29 Mitglieder</strong><span>2026</span></div>
                    <div class="podium"><div class="podium-card"><b>2. Platz</b><strong>Sophie Grimm</strong><small>18 Einsätze</small><em>72 % Beteiligung</em></div><div class="podium-card winner"><b>1. Platz</b><strong>Jonas Dierks</strong><small>21 Einsätze</small><em>84 % Beteiligung</em></div><div class="podium-card"><b>3. Platz</b><strong>Miriam Clausen</strong><small>16 Einsätze</small><em>64 % Beteiligung</em></div></div>
                    <div class="ranking"><div class="rank"><span>4. Leonie Eggers</span><b>14 von 25</b></div><div class="rank"><span>5. Felix Fischer</span><b>13 von 25</b></div><div class="rank"><span>6. Tobias Hansen</span><strong>28 %</strong></div><div class="rank"><span>7. David Krüger</span><strong>24 %</strong></div></div>
                </div>
            </div>
        </div></section>

        <section class="section section-soft" id="neu" aria-labelledby="releases-title"><div class="shell">
            <div class="section-head"><span class="eyebrow">Aktuell gepflegt</span><h2 id="releases-title">Die letzten Änderungen.</h2><p>Die Produktseite liest die aktuellen Einträge direkt aus der mitgelieferten Release-Historie des Plugins.</p></div>
            <?php if ($latest_releases !== []) : ?><div class="release-grid">
                <?php foreach ($latest_releases as $release) : ?><article class="release">
                    <div class="release-meta"><span class="release-version">Version <?php echo feu_page_escape((string) ($release['version'] ?? '')); ?></span><time datetime="<?php echo feu_page_escape((string) ($release['date'] ?? '')); ?>"><?php echo feu_page_escape(feu_page_release_date((string) ($release['date'] ?? ''))); ?></time></div>
                    <h3><?php echo feu_page_escape((string) ($release['title'] ?? 'Aktualisierung')); ?></h3><p><?php echo feu_page_escape((string) ($release['summary'] ?? '')); ?></p>
                </article><?php endforeach; ?>
            </div><?php else : ?><article class="release"><h3>Version <?php echo feu_page_escape($plugin_version); ?></h3><p>Die aktuelle Version enthält Verbesserungen für Redaktion, Karten, Statistik und Betrieb.</p></article><?php endif; ?>
        </div></section>

        <section class="section" id="anforderungen" aria-labelledby="requirements-title"><div class="shell requirements">
            <article class="req-card"><span class="eyebrow">Technische Basis</span><h2 id="requirements-title">Klar definierte Anforderungen.</h2><p>Für einen stabilen Betrieb setzt das Plugin auf eine aktuelle WordPress- und PHP-Umgebung.</p><div class="req-list"><div class="req"><span>WordPress</span><strong><?php echo feu_page_escape($requires_wordpress); ?> oder höher</strong></div><div class="req"><span>PHP</span><strong><?php echo feu_page_escape($requires_php); ?> oder höher</strong></div><div class="req"><span>Aktuelle Version</span><strong><?php echo feu_page_escape($plugin_version); ?></strong></div><div class="req"><span>Lizenz</span><strong>Persönliche Freigabe erforderlich</strong></div></div></article>
            <article class="security"><span class="eyebrow">Sicherheit und Verantwortung</span><h2>Kontrollierter Betrieb in WordPress.</h2><p>Das Plugin unterstützt eine datenschutzbewusste Arbeitsweise. Rechtliche Prüfung, Impressum, Datenschutzerklärung und redaktionelle Verantwortung verbleiben beim Betreiber.</p><div class="security-grid"><div class="security-item"><strong>Rollen und Rechte</strong><span>Zugriffe für Plugin-Bereiche gezielt konfigurieren.</span></div><div class="security-item"><strong>Lokale Assets</strong><span>Admin-Ressourcen und Vorschaubilder lokal bereitstellen.</span></div><div class="security-item"><strong>Karten-Attribution</strong><span>Leaflet- und OpenStreetMap-Hinweise sichtbar erhalten.</span></div><div class="security-item"><strong>Diagnose</strong><span>Fehlerstatus und technische Protokolle nachvollziehbar anzeigen.</span></div></div></article>
        </div></section>

        <section class="section section-soft" aria-labelledby="faq-title"><div class="shell">
            <div class="section-head"><span class="eyebrow">Häufige Fragen</span><h2 id="faq-title">Kurz beantwortet.</h2></div>
            <div class="faq">
                <details><summary>Für wen ist Feuer-Einsatzberichte gedacht?</summary><div class="answer"><p>Für Feuerwehren und vergleichbare Organisationen, die Einsatzberichte in WordPress strukturiert erstellen, veröffentlichen und intern auswerten möchten.</p></div></details>
                <details><summary>Werden Karten bei jedem Seitenaufruf neu geladen?</summary><div class="answer"><p>Nein. Das Plugin kann lokale Kartenbilder und Caches verwenden. Eine interaktive Karte lädt externe Kartendaten abhängig von der gewählten Datenschutz- und Darstellungseinstellung.</p></div></details>
                <details><summary>Können vorhandene Daten übernommen werden?</summary><div class="answer"><p>Ja. Die Datenmigration führt durch Kategorien, Berichte, Mannschaft und weitere Datenbereiche. Jeder Bereich besitzt einen eigenen Status und kann bei einem Fehler gezielt wiederholt werden.</p></div></details>
                <details><summary>Wie werden Aktualisierungen bereitgestellt?</summary><div class="answer"><p>Veröffentlichte Versionen werden über GitHub Releases bereitgestellt. Manifest und Installationspaket gehören zum selben Release; die Paketprüfung verwendet SHA-256.</p></div></details>
            </div>
        </div></section>

        <section class="cta" aria-labelledby="project-title"><div class="shell"><article class="cta-card">
            <div><span class="eyebrow">Entwicklung und Projekt</span><h2 id="project-title">Entwickelt für klare Abläufe im Feuerwehralltag.</h2><p>Feuer-Einsatzberichte wird von Walter Faerber entwickelt und fortlaufend an reale Anforderungen aus Redaktion, Einsatzdarstellung und Statistik angepasst.</p></div>
            <div class="actions"><a class="button primary" href="https://wdmin.com/">Über wdmin.com</a><a class="button secondary" href="#neu">Versionsverlauf</a></div>
        </article></div></section>
    </main>

    <footer><div class="shell footer-grid"><p class="footer-copy">Feuer-Einsatzberichte · WordPress-Plugin für Einsatzberichte, Karten, Mannschaft und Statistik · Version <?php echo feu_page_escape($plugin_version); ?> · Entwicklung Walter Faerber</p><nav class="footer-links" aria-label="Fußnavigation"><a href="#funktionen">Funktionen</a><a href="#neu">Änderungen</a><a href="#anforderungen">Anforderungen</a><a href="https://wdmin.com/">wdmin.com</a></nav></div></footer>
</body>
</html>
