# Eigene öffentliche Vorlagen

Die Auswahl liegt unter **Einsatzberichte → Einstellungen → Vorlagen**. Die
Plugin-Beispiele befinden sich in `templates/public/custom/`; sie sind nur als
Ausgangspunkt gedacht und werden bei einem Plugin-Update ersetzt.

Für dauerhafte Anpassungen eine Datei im aktiven Child-Theme anlegen:

```text
wp-content/themes/mein-child-theme/feuer-einsatzberichte/templates/custom_name.html
```

Oder für PHP:

```text
wp-content/themes/mein-child-theme/feuer-einsatzberichte/templates/custom_name.php
```

Der Dateikopf bestimmt den Einsatzbereich:

```html
<!--
Template Name: Meine Einsatzseite
Template Type: single
-->
```

`Template Type` akzeptiert `single`, `overview` oder `sidebar`. Alternativ
werden Dateinamen wie `custom_single_name.php` automatisch erkannt.

## HTML-Makros

HTML-Dateien enthalten keinen ausführbaren PHP-Code. Der Plugin-Renderer
ersetzt nur bekannte Makros; Daten aus Berichten werden dabei korrekt escaped.

| Makro | Inhalt |
| --- | --- |
| `{{feu:header}}`, `{{feu:footer}}` | Theme-Header/-Footer; nur bei `single` verwenden |
| `{{feu:breadcrumbs}}` | Navigation zum Einsatz |
| `{{feu:map}}` | Karte inklusive Datenschutz- und Geometrie-Logik |
| `{{feu:info}}` | Ort, Datum, Einsatzart und Kräfte vor Ort |
| `{{feu:content}}`, `{{feu:description}}` | Beschreibung des Einsatzes |
| `{{feu:gallery}}` | Galerie mit Bilddialog |
| `{{feu:related}}` | Weitere Einsatzberichte |
| `{{feu:list}}`, `{{feu:sidebar}}` | Übersichtsliste bzw. Sidebar |
| `{{feu:title}}`, `{{feu:date}}`, `{{feu:time}}` | Einzelwerte eines Berichts |

Eine PHP-Vorlage erhält `$context` und `$report`. Sie ist für fortgeschrittene
Anpassungen vorgesehen und darf nur durch vertrauenswürdige Entwickler im
Theme-Repository gepflegt werden.
