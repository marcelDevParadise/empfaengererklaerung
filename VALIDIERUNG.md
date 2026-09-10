# Validierung · Version 1.2.0

Stand: 10.09.2026. GitHub-Update-Anbindung für das öffentliche Repository `marcelDevParadise/empfaengererklaerung`.

## Erfolgreiche Prüfungen

- **73 Integrationstests:** Bestehendes Formular, feste Absenderadresse, Sendungsnummerprüfung, PDF-Erstellung, E-Mail-Testtransport, Backend-Überarbeitungen, Originalerhalt, Zugriffsschutz und Löschung. Lokal unter PHP 7.4.33 / WordPress 7.1 und zusätzlich im GitHub-Workflow unter PHP 7.4 / WordPress 6.6 erfolgreich.
- **21 Updateprüfungen:** Versionsvergleich, native WordPress-Updateliste und Plugin-Details, erwartete vollständige Release-ZIP, keine Daten in URL-Abfrageparametern, unverändertes Verhalten anderer Plugins, Ablehnung fremder/fehlender Downloads und von Vorabversionen, PHP-/WordPress-Kompatibilität, ungültige Metadaten, fehlendes Release und Netzwerkausfälle. Lokal und im GitHub-Workflow erfolgreich.
- **Echter WordPress-Upgrade im Hintergrundmodus:** Eine isolierte Installation mit älterem Versionsheader erkennt die neue Version und installiert das gebaute Paket über `Plugin_Upgrader`. Plugin bleibt aktiv, Einstellungen sowie Original-PDF und Überarbeitung bleiben unverändert. Metadaten und Paket-Download werden hierbei aus lokalen Fixtures geliefert; kein Zugriff auf eine Kundenwebsite. Lokal und im GitHub-Workflow erfolgreich.
- **53 Browserprüfungen:** Desktop und Touch/Mobil, Unterschrift, Sendungsnummer, Backend-Überarbeitung, PDF-Downloads, Rechte, Einstellungen und Sammellöschung mit Version 1.2.0 erneut erfolgreich.
- **6 neue Browserprüfungen:** „Check for updates“ sichtbar und bedienbar; native automatische Updates lassen sich aktivieren, über Neuladen hinweg speichern und wieder deaktivieren. Die Updateprüfung meldet bei der aktuellen Version keinen Aktualisierungsbedarf. Keine JavaScript-Laufzeitfehler. Die Pluginliste wurde zusätzlich visuell anhand des Screenshots geprüft.
- **Paketprüfung:** `scripts/release.php` prüft Versionsheader, Konstante, Stable tag, gegebenenfalls Git-Tag, eingebundene Abhängigkeiten und jede einzelne ZIP-Datei anhand ihres SHA-256 gegen die Quelldatei. ZIP, Metadaten und Prüfsummen werden zusammen gebaut. PHP-Syntaxprüfung erfolgt im Workflow für Plugin einschließlich Abhängigkeiten, Skripte und Tests.

## Releaseweg

Der Workflow `Validate and release` prüft Commits auf main, Pull Requests und Versionstags. Ein geprüfter Versionstag erzeugt zunächst einen Release-Entwurf samt vollständiger ZIP, `update.json` und `SHA256SUMS.txt`, der danach veröffentlicht wird. Branch-Commits lösen keine Plugin-Veröffentlichung aus. Der erste main-Lauf ist erfolgreich: https://github.com/marcelDevParadise/empfaengererklaerung/actions/runs/34481552397

Die verbindlichen Paket-Prüfsummen stehen in `SHA256SUMS.txt` des jeweiligen GitHub-Releases. Lokale ZIPs und GitHub-Builds können wegen Archivzeitstempeln und normalisierten Zeilenenden unterschiedliche Bytes besitzen.

## Testumgebung und Grenzen

Lokal Windows / PHP 7.4.33 / WordPress 7.1; im GitHub-Workflow Linux / PHP 7.4 / WordPress 6.6. Beide verwenden den offiziellen SQLite-Adapter 3.0.1. Die portable Windows-Testlaufzeit verwendet die zuvor dokumentierte neuere SQLite-DLL. Das Zielhosting mit MySQL/MariaDB ist nicht Teil dieser Prüfungen.

Lokaler PHPMailer-Testtransport ohne externe E-Mails. Die Browserprüfungen laufen in Chrome über Playwright, Desktop 1365 × 1000 und Mobil/Touch 390 × 844. Der neue UI-Test aktiviert nur die Auto-Update-Bedienelemente; echte zeitgesteuerte Updates bleiben in der isolierten Testumgebung abgeschaltet. Nach dem Test werden die ursprünglichen Einstellungen wiederhergestellt und die temporäre Testhilfe entfernt.

Auf der Zielwebsite wurde nichts installiert. Dort ist einmal die neue ZIP zu installieren und unter Plugins die automatische Aktualisierung zu aktivieren. Hintergrundaufgaben und ausgehende HTTPS-Verbindungen zu GitHub müssen vom Hosting zugelassen sein. Hosting, reale Postfachzustellung und physische Mobilgeräte bleiben separat zu prüfen.
