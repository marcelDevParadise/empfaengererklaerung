# Validierung · Version 1.1.0

Stand: 10.09.2026. Die Release-ZIP wurde lokal unter PHP 7.4.33 durch den WordPress-Installer installiert und aktiviert. Vorhandene Archiveinträge blieben erhalten.

## Umgebung

Windows, PHP 7.4.33 mit DOM, mbstring, GD und SQLite; WordPress 7.1 mit offiziellem SQLite-Adapter. Die isolierte PHP-7.4-Testlaufzeit verwendet die SQLite-DLL aus dem vorhandenen PHP-8.3-Paket. Diese lokale Testabhängigkeit gehört nicht zum Plugin; das Zielhosting verwendet MySQL/MariaDB.

Chrome über Playwright: Desktop 1365 × 1000 und Touch-/Mobilansicht 390 × 844. Dompdf 3.1.6; PDF-Darstellung mit PDF.js. Ausschließlich lokaler PHPMailer-Testtransport, keine extern versendeten E-Mails.

## Ergebnis

- **73 serverseitige Prüfungen erfolgreich** (`tests/integration.php`, PHP 7.4.33). Pflichtfelder, feste Absenderanschrift, numerische Sendungsnummer mit Präfix 003404347382, führende Nullen, Ablehnung von Buchstaben/Leerzeichen/Sonderzeichen/HTML/falschem Präfix und bloßem Präfix; Signatur, Datum, Nachnahme, PDF, Sitzungen, Wiederholungen, Mailfehler und Löschung.
- **Neue Backend-Funktion geprüft:** Administrator kann Angaben ohne Übernahme der Originalunterschrift korrigieren. Suchdaten werden aktualisiert; ursprüngliche Daten und PDF bleiben erhalten. Separates Korrektur-PDF ist ausdrücklich nicht erneut unterschrieben. Bearbeiter, Zeitpunkt und fortlaufende Nummer werden gespeichert. Veraltete Bearbeitungsstände werden abgelehnt. Zweite Überarbeitung und identische Wiederholung der ursprünglichen Kundenübermittlung funktionieren.
- **53 Browserprüfungen erfolgreich** (`tests/browser.mjs`, PHP-7.4.33-Webserver). Desktop-/Touch-Übermittlung, Sendungsnummerfehler, Backend-Maske, tatsächliches Speichern und Wiederöffnen, serverseitige Ablehnung ungültiger Korrekturwerte, Nonce-Prüfung, Konfliktmeldung, bytegleicher Originaldownload und separater Korrekturdownload. Abonnenten und anonyme Nutzer erhalten keinen Bearbeitungs- oder Korrekturdownloadzugriff. Suche, Versandversuch, Einstellungen und Sammellöschung funktionieren. Keine JavaScript-Laufzeitfehler im abschließenden Lauf.
- **PDF-Prüfung:** Standard, Logo, Nachnahmevarianten und überarbeitete Fassung jeweils eine Seite; lange Originalangaben zwei Seiten. Das im Browser erzeugte Korrektur-PDF wurde gerendert und visuell geprüft: korrigierter Inhalt und E-Mail-Adresse, feste Absenderanschrift, führende Nullen, sichtbare Kennzeichnung, keine Originalunterschrift. Die Backend-Detailansicht wurde ebenfalls anhand des Screenshots geprüft.
- **Mail-Nachweis:** Der lokale Mail-Log zeigt beim erneuten Versand nach einer Korrektur weiterhin die ursprüngliche Kundenadresse und denselben Original-PDF-Hash. Das Speichern der Korrektur selbst versendet keine E-Mail.
- **Paket:** 334 Dateien einschließlich Dompdf; 232 PHP-Dateien bestehen die Syntaxprüfung unter PHP 7.4.33. Keine Testumgebung oder Node-Abhängigkeiten im ZIP. WordPress-Installation, Aktivierung und Erhalt des Archivs erfolgreich (`tests/package-install.php`).

ZIP: `dist/empfaengererklaerung-1.1.0.zip` (4.163.226 Bytes).

SHA-256: `E31D7C1C33D5ADFB10F09164CEF74FF5D31718185B68214058E4514F2BD0D97D`

## Verhalten und Grenzen

Es werden das unterschriebene Original und die jeweils letzte überarbeitete Fassung gespeichert; Zwischenfassungen werden ersetzt. Die feste Absenderadresse bleibt auch im Editor schreibgeschützt. Eine feste Gesamtlänge für Sendungsnummern wurde nicht vorgegeben; es gelten Präfix, mindestens eine weitere Ziffer und das bestehende Maximum von 100 Zeichen.

Kundenlinks und Mail-Wiederholungen beziehen sich auf das Original. Korrekturen sind als separates PDF nur im Backend verfügbar und werden nicht automatisch versendet. Sie enthalten keine neue Kundenbestätigung oder Unterschrift. Das Korrektur-PDF nutzt Unternehmensname und Akzentfarbe des archivierten Vorgangs; ein nur im Original-PDF eingebettetes Logo wird nicht übernommen.

Die Tests belegen die lokale PHP-7.4.33-/SQLite-Umgebung. MySQL/MariaDB auf dem tatsächlichen Hosting, WordPress 6.6 als Mindestversion, physische Mobilgeräte und echte Postfachzustellung wurden nicht geprüft. Auf der Zielwebsite wurde nichts installiert oder veröffentlicht. Dort bleiben Installation und ein vollständiger Testversand mit der bestehenden Mailkonfiguration zu prüfen.
