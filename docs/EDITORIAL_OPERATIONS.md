# Redaktion und Arbeitszentrale

Im vorhandenen **Einsatzberichte → Dashboard** stehen neben der Übersicht drei Arbeitsbereiche als Reiter. Es gibt keine zusätzliche Verwaltungsseite:

1. **Systemstatus:** Die letzten 40 Einsatzberichte zeigen den Zustand von Kartenbild, Link-Vorschaubild, Wasserzeichen und geplanter Veröffentlichung. Fehlgeschlagene Karten, Share Cards und Wasserzeichen lassen sich pro Bericht erneut einplanen. Eine fehlgeschlagene Aufgabe hält andere Berichte nicht an. Ein überfälliger Veröffentlichungstermin erscheint zusätzlich unter **Werkzeuge → Website-Zustand**; dort WP-Cron prüfen.
2. **Redaktionsliste:** Berichte lassen sich nach „Zur Prüfung“, Entwurf, geplant und veröffentlicht filtern. Personen mit `edit_posts` können Berichte bearbeiten; `publish_posts` ist für die Freigabe nötig. Der Status `pending` nutzt den regulären WordPress-Beitragsstatus und damit dessen vorhandene Rechte und Revisionen.
3. **Teilnehmerdaten:** Administratoren können ein Teilnehmerprofil samt verknüpften Einsätzen als JSON exportieren, die öffentliche Anzeige von Teilnehmernamen für Einsatzberichte abschalten und eine Frist für die manuelle Aufbewahrungsprüfung wählen. Lokale Profile können nach Eingabe von `ANONYMISIEREN` anonymisiert werden. Historische Einsatzzuordnung und Funktionen bleiben für die Statistik bestehen. Berichtstexte und hochgeladene Bilder sind gesondert zu prüfen. Extern verwaltete Profile werden nicht lokal anonymisiert, da ein Abgleich ihre Daten wiederherstellen könnte.

Vor dem Veröffentlichen oder Einreichen zeigt der Berichtseditor eine Prüfliste für Pflichtfelder, Einsatzort, Stichwort, Karte, Bilder, Zeitplanung, Link-Vorschau und URL. Fehlende Pflichtfelder und bestätigte URL-Konflikte sperren die Bestätigung. Ist die URL-Prüfung technisch nicht erreichbar, weist die Liste darauf hin und die serverseitige Speicherung bleibt möglich.

Die öffentliche Einsatzkarte wird erst geladen, wenn sie in die Nähe des sichtbaren Bereichs scrollt. Für Browser ohne `IntersectionObserver` gilt der bestehende direkte Start.

Unter **Einstellungen → Karten → Schnellprofile im Berichtseditor** lassen sich die fünf Editor-Vorlagen umbenennen, deaktivieren und auf Einsatzstichworte begrenzen. Auch Modus und Abschnitts- beziehungsweise Radiusgröße sind einstellbar. Ohne ausgewählte Stichworte ist ein Profil überall verfügbar. Bei einem Bericht mit mehreren Stichworten erscheint ein eingeschränktes Profil nur, wenn es für jedes davon freigegeben ist. Profile sind Eingabehilfen: Ein Bericht speichert weiterhin die konkret gewählte Kartendarstellung. Änderungen an Profilen schreiben vorhandene Berichte nicht um.

Diese Funktionen setzen WordPress-Cron für die Hintergrundaufgaben voraus. Die Arbeitszentrale zeigt den Status des aktuellen Ausschnitts; sie ist keine vollständige Ereignishistorie. Technische Logs bleiben unter **Einsatzberichte → Logs**.
