# Empfängererklärung für WordPress

Das Plugin befindet sich in `empfaengererklaerung/`. Das auslieferbare ZIP wird mit `scripts/package.ps1` unter `dist/` erstellt. Es enthält Dompdf und alle Laufzeitabhängigkeiten.

## Installation

1. Die fertige `empfaengererklaerung-1.2.0.zip` aus den [GitHub-Releases](https://github.com/marcelDevParadise/empfaengererklaerung/releases/latest) im WordPress-Backend unter **Plugins → Installieren → Plugin hochladen** installieren und aktivieren. Bei vorhandener Version das angebotene Ersetzen durch die hochgeladene Version wählen; Einstellungen und Archiv bleiben erhalten. Das GitHub-Quellcodearchiv ist kein installierbares Plugin-Paket.
2. **Empfängererklärungen → Einstellungen** öffnen. Unternehmensname, Service-E-Mail, optional Logo und Akzentfarbe einstellen. Die E-Mail-Texte lassen sich mit `{name}`, `{vorgang}`, `{sendungsnummer}` und `{unternehmen}` anpassen.
3. Nach Prüfung der Service-Adresse **Formular freischalten** aktivieren und speichern.
4. Auf einer Seite einen Shortcode-Block mit `[empfaengererklaerung]` einsetzen. Pro Seite wird ein Formular gerendert.
5. Eine vollständige Test-Erklärung abgeben. Kunden- und Service-Postfach samt PDF-Anhängen sowie den Archiveintrag prüfen.

Voraussetzungen: WordPress ab 6.6, PHP ab 7.4 (insbesondere 7.4.33), PHP-Erweiterungen DOM, mbstring und GD. Eine reguläre WordPress-Einzelinstallation mit funktionierendem Mailtransport und HTTPS wird vorausgesetzt. Für Composer oder Node besteht auf dem Zielserver kein Bedarf.

## Automatische Updates über GitHub

Version 1.2.0 enthält die Update-Anbindung. Diese Version muss einmalig hochgeladen werden. Anschließend unter **Plugins → Installierte Plugins → Empfängererklärung → Automatische Updates aktivieren** einschalten. WordPress übernimmt dann künftige Updates im Hintergrund; manuelle Aktualisierung und „Auf Updates prüfen“ bleiben verfügbar. Ein GitHub-Konto oder Token auf dem Webserver ist nicht erforderlich. Automatische Updates müssen vom Hosting erlaubt sein und benötigen funktionierende WordPress-Hintergrundaufgaben (WP-Cron oder einen Server-Cron).

Die gebündelte Bibliothek Plugin Update Checker 5.7 prüft ungefähr alle zwölf Stunden `https://github.com/marcelDevParadise/empfaengererklaerung/releases/latest/download/update.json`. Es werden ausschließlich vollständige Releases mit passender ZIP und kompatibler PHP-/WordPress-Anforderung angeboten. Entwicklungsstände, Quellcodearchive und Vorabversionen werden nicht installiert. Metadaten und ZIP werden gemeinsam aus einem zunächst privaten Release-Entwurf veröffentlicht. Netzwerkausfälle verhindern nur die Updateprüfung; Erklärungen bleiben nutzbar.

GitHub erhält die Server-IP und die üblichen HTTP-Verbindungsdaten. Es werden keine Erklärungen, Kundendaten, Signaturen oder PDF-Inhalte für Updates übertragen. Der Updater fügt keine Abfrageparameter für installierte Version, PHP oder Sprache an. Einstellungen und Archiv liegen in WordPress und werden beim Austausch des Plugins erhalten.

## Neue Version veröffentlichen

Das öffentliche Repository ist [marcelDevParadise/empfaengererklaerung](https://github.com/marcelDevParadise/empfaengererklaerung). `.tools/`, `dist/`, lokale Beispiel-PDFs/Bilder, Logs und Datenbanken gehören nicht ins Git-Repository. Die fest benannten Testbenutzer in den Testskripten gelten ausschließlich für die isolierte Testinstallation.

1. Änderungen implementieren und prüfen. In `empfaengererklaerung.php` Header und `EE_VERSION` sowie in `readme.txt` den Stable tag auf dieselbe neue Version setzen; Changelog ergänzen.
2. Commit nach `main` pushen und den erfolgreichen Workflow **Validate and release** abwarten. Bei Änderungen an Formular oder Backend zusätzlich die lokalen Browserprüfungen ausführen.
3. Den geprüften Stand mit einem passenden Tag markieren, beispielsweise `git tag v1.2.1`, und diesen mit `git push origin v1.2.1` veröffentlichen.

Der Tag-Workflow prüft PHP-7.4-Syntax, Versionsgleichheit, ZIP-Inhalt, Integration und Updateverhalten in WordPress 6.6. Erst nach erfolgreichen Prüfungen veröffentlicht er die fertige ZIP, `update.json` und SHA-256-Prüfsummen als GitHub-Release. Gewöhnliche Commits auf `main` und Pull Requests werden geprüft, veröffentlichen aber kein Plugin-Update. Fehlgeschlagene Veröffentlichungen können einen Release-Entwurf hinterlassen; dessen Ursache prüfen, bevor erneut veröffentlicht wird. Veröffentlichte Versionen nicht nachträglich überschreiben, sondern eine neue Versionsnummer verwenden.

Das plattformunabhängige Paket-Skript lautet `php scripts/release.php`; es benötigt PHP mit ZIP-Erweiterung nur auf dem Entwicklungs-/Buildsystem. Es schreibt Paket, Update-Metadaten, Release-Notizen und Prüfsummen nach `dist/`. Das bisherige `scripts/package.ps1` erstellt weiterhin nur eine Plugin-ZIP.

## Kundenablauf

Der Absender steht fest auf **Paradise X Sales GmbH, Gothaer Straße 4, 40880 Ratingen**. Das Formular zeigt die Anschrift schreibgeschützt an. Sie wird serverseitig eingesetzt, auch wenn ein Browser keine oder abweichende Absenderdaten übermittelt. Bestehende Erklärungen und PDFs werden nicht verändert.

Sendungsdaten erfassen → Erhalt und gegebenenfalls Nachnahme erklären → Angaben prüfen → Ort, Datum und Bestätigung ergänzen → mit Finger, Stift oder Maus unterschreiben → absenden.

Sendungsnummern dürfen ausschließlich Ziffern enthalten und müssen mit `003404347382` beginnen. Der Präfix allein reicht nicht aus; die vollständige Nummer ist erforderlich. Führende Nullen bleiben erhalten. Buchstaben, Leerzeichen und Sonderzeichen werden im Formular sowie serverseitig abgelehnt. Eine feste Gesamtlänge ist nicht vorgegeben.

## Bestehende Erklärungen überarbeiten

Unter **Empfängererklärungen → Vorgang öffnen → Angaben bearbeiten** können Sendungsdaten, Personen- und Kontaktdaten sowie Erhalt und Nachnahme korrigiert werden. Der feste Absender bleibt schreibgeschützt. **Überarbeitung speichern** aktualisiert die Detailansicht und die Suchdaten und erzeugt ein separates PDF, gekennzeichnet als „Überarbeitete Fassung · nicht erneut unterschrieben“. Der Download **PDF herunterladen** liefert weiterhin das unterschriebene Original; **Überarbeitetes PDF herunterladen** liefert die Korrektur.

Das Original mit seinen Angaben und seiner Unterschrift bleibt gespeichert. Die Korrektur enthält keine übernommene Unterschrift oder Richtigkeitsbestätigung. Gespeichert werden die letzte Überarbeitung, ihre Nummer, Zeitpunkt und bearbeitende Benutzer-ID. Eine erneute Überarbeitung ersetzt die vorherige Korrektur; es gibt kein vollständiges Versionsarchiv. Gleichzeitige Bearbeitungen werden auf veralteten Stand geprüft. Fehlermeldungen erhalten die Eingaben.

Bearbeitung und Korrektur-Download erfordern `manage_ee_declarations`. Kundenlinks und erneute Mailversuche liefern weiterhin das Original; Mailversuche verwenden die ursprüngliche Kundenadresse und ursprünglichen Angaben. Eine Überarbeitung löst keinen Versand aus. Beim Löschen eines Vorgangs werden Original und Korrektur gemeinsam entfernt.

Eingaben bleiben bei Rücknavigation, Validierungs- und Versandfehlern auf der Seite erhalten. Änderungen nach dem Zeichnen löschen die Unterschrift. Nach erfolgreicher Speicherung wird das Formular geleert. Die Daten werden nicht im Browser-LocalStorage gespeichert.

Pflichtfelder: Sendungsnummer, Versanddienstleister, Empfänger mit Anschrift, Inhalt, Vor- und Nachname, Anschrift der erklärenden Person, E-Mail, Erhalt-Auswahl, Ort, Erklärungsdatum, Richtigkeitsbestätigung und Unterschrift. Bei „erhalten“ kommt das Empfangsdatum hinzu, bei Nachnahme die Zahlungsart. Telefon und Angaben aus der dokumentierten Zustellung sind optional. Anschriften sind auf 500 Zeichen und 12 Zeilen, der Inhalt auf 1.500 Zeichen und 30 Zeilen begrenzt; einzelne Feldlimits sind im Code zentral definiert.

Das PDF verwendet eine eigene A4-Vorlage mit vollständiger zutreffender Erklärung. Übliche Angaben passen auf eine Seite; längere Inhalte werden auf zusätzliche Seiten verteilt. Erklärung und Unterschrift bleiben zusammen. Eine Signaturprüfung anhand eines Zertifikats erfolgt nicht.

## Archiv, Versand und Daten

- Eigene Berechtigung `manage_ee_declarations`, zunächst nur für Administratoren. Einstellungen erfordern zusätzlich `manage_options`.
- Suche nach Vorgang, Sendungsnummer, Name oder E-Mail; Datumfilter in der WordPress-Zeitzone; 20 Einträge je Seite.
- Einzel- und Sammellöschung mit Bestätigung. Die Auswahl umfasst die sichtbare Ergebnisseite. Gelöscht werden Datensatz und darin enthaltenes PDF. Keine automatische Löschung.
- PDF und Mailvorlagen werden bei Abgabe eingefroren. Änderungen an Branding, Zieladressen und Mailtexten wirken auf neue Erklärungen.
- Service und Kunde haben getrennte Versandstatus. Fehler können in der Detailansicht erneut versucht werden. Nach einem Prozessabbruch ist nach zehn Minuten ein erneuter Versuch möglich, wobei eine doppelte E-Mail nicht ausgeschlossen ist.
- Der Mailtransport nutzt `wp_mail()` und hängt die PDF-Bytes mit `phpmailer_init` direkt an. Das vermeidet öffentlich zugängliche temporäre PDF-Dateien. Standard-PHPMailer-/SMTP-Versand wird unterstützt; vollständig ersetzende Mail-APIs müssen auf Anhänge geprüft werden.
- Kundenlinks sind 30 Minuten ab Speicherung gültig. Administratoren laden Dokumente unabhängig davon mit Berechtigung und WordPress-Nonce herunter.
- Datenbanktabelle `{prefix}ee_declarations`; PDF als Base64 in einem LONGTEXT-Feld, damit WordPress keine Binärzeichen verändert. Keine öffentlich erreichbaren Dokumentdateien. Die Unterschrift wird nicht zusätzlich als Einzelbild archiviert.
- Kurzlebige Zähler in `wp_options` begrenzen Sitzungsaufrufe und Übermittlungen anhand eines gesalzenen Hashes der direkten Client-IP. Alte Zähler werden beim nächsten Formularaufruf entfernt. Hinter einem Reverse Proxy sollte die korrekte Client-IP auf Webserver-Ebene bereitgestellt werden; beliebige Forwarded-Header werden nicht vertraut.
- Deaktivierung und Deinstallation erhalten die Daten. Zum vollständigen Entfernen zuerst das Archiv leeren; danach können die Tabelle, die Optionen `ee_settings` / `ee_db_version` / `ee_rate_*` und die eigene Berechtigung im Rahmen einer administrativen Datenbereinigung entfernt werden. Externe Backups und versendete E-Mails bleiben davon unberührt.

## Schnittstellen und Schutz

`POST /wp-json/empfaengererklaerung/v1/session` erstellt eine signierte Formularsitzung. `POST /wp-json/empfaengererklaerung/v1/declarations` akzeptiert JSON mit Formularfeldern, PNG-Signatur, Sitzungskennung und Honeypot. Bei einfachen WordPress-Permalinks werden automatisch die entsprechenden `?rest_route=`-URLs verwendet.

Eine Übermittlung ist an die Sitzungskennung gebunden; ein eindeutiger Datenbankindex verhindert doppelte Datensätze. Eine identische Wiederholung liefert denselben Vorgang, ein veränderter Inhalt kann das unterschriebene Dokument nicht überschreiben. Neue Sitzungen sind zwei Stunden gültig. Fehler liefern deutsche Meldungen und gegebenenfalls Feldfehler.

Downloads laufen über `admin-post.php?action=ee_download`. Für Kunden sind Vorgangs-ID und ein zufälliger, zeitlich begrenzter Zugang nötig; gespeichert wird nur sein Hash. Verwaltungsaktionen sind POST-Anfragen mit Berechtigungs- und Nonce-Prüfung. Bildgrößen, Formularlängen und PDF-Größe sind begrenzt; entfernte Ressourcen und PHP/JavaScript im PDF-Renderer sind deaktiviert.

## Lokale Entwicklung und Tests

Die isolierte Testumgebung liegt in `.tools/`, gehört nicht ins Plugin-ZIP und verschickt keine echten E-Mails. Sie verwendet portable PHP, WordPress, den offiziellen SQLite-Adapter und einen PHPMailer-Testtransport. Die in den Testskripten enthaltenen Zugangsdaten gelten ausschließlich für den an `127.0.0.1:8097` gebundenen Testserver.

```powershell
# Nach Bereitstellung von PHP, WordPress und SQLite-Adapter in .tools:
Copy-Item empfaengererklaerung .tools/wordpress/wp-content/plugins/empfaengererklaerung -Recurse
& .tools/php/php.exe tests/setup-local.php
& .tools/php/php.exe tests/integration.php
& .tools/php/php.exe -S 127.0.0.1:8097 -t .tools/wordpress

# In einer weiteren Konsole; Playwright, PDF.js und Canvas liegen unter .tools/browser:
node tests/browser.mjs
node tests/render-pdf.mjs
powershell -ExecutionPolicy Bypass -File scripts/package.ps1
```

Prüfungen und Grenzen sind in `VALIDIERUNG.md` dokumentiert. Vor dem Live-Einsatz bleiben die Installation auf dem tatsächlichen Hosting und die Zustellung in echten Postfächern zu prüfen. Die Versanddienstleister-Anerkennung der eigenen Vorlage ist separat zu klären.

Für PHP 7.4.33 liegt die portable Laufzeit unter `.tools/php74/php.exe`. Die PHP-Befehle entsprechend damit ausführen und vor den Browserprüfungen `$env:EE_PHP = '.tools/php74/php.exe'` setzen. Auch der lokale Webserver muss mit dieser Laufzeit gestartet werden. Der SQLite-Testadapter benötigt eine neuere SQLite-Bibliothek als die im ursprünglichen Windows-PHP-7.4-Paket enthaltene; deshalb verwendet diese isolierte Testlaufzeit die `libsqlite3.dll` aus dem vorhandenen PHP-8.3-Paket. Diese Testabhängigkeit ist nicht Bestandteil des Plugins und betrifft das MySQL-/MariaDB-Zielhosting nicht.

## Abhängigkeiten und Lizenz

Die Update-Anbindung verwendet [Plugin Update Checker 5.7](https://github.com/YahnisElsts/plugin-update-checker/releases/tag/v5.7), MIT-Lizenz. Bibliothek und Lizenz sind unter `empfaengererklaerung/lib/plugin-update-checker/` gebündelt.

Eigener Plugin-Code: GPL-2.0-or-later. Gebündelt ist Dompdf 3.1.6 aus dem vollständigen offiziellen Release-Archiv: https://github.com/dompdf/dompdf/releases/tag/v3.1.6. Die Original-Lizenzen von Dompdf und seinen Abhängigkeiten befinden sich in `empfaengererklaerung/lib/dompdf/` und dessen `vendor/`-Verzeichnis.
