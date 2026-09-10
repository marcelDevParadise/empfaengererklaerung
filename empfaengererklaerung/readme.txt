=== Empfängererklärung ===
Contributors: empfaengererklaerung
Tags: empfaengererklaerung, formular, pdf
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Eigenständiges Formular für unterschriebene Empfängererklärungen mit eigener PDF-Vorlage und geschütztem Archiv.

== Installation ==

1. ZIP unter Plugins > Installieren > Plugin hochladen auswählen und aktivieren.
2. Empfängererklärungen > Einstellungen öffnen. Unternehmensname, Service-E-Mail, optional Logo und Akzentfarbe eintragen.
3. Service-E-Mail prüfen, Formular freischalten und Einstellungen speichern.
4. Eine WordPress-Seite mit dem Shortcode [empfaengererklaerung] veröffentlichen.
5. Eine Test-Erklärung ausfüllen und den Eingang beider E-Mails mit PDF-Anhang prüfen.

Benötigt PHP-Erweiterungen DOM, mbstring und GD. Dompdf 3.1.6 und seine Abhängigkeiten sind enthalten. Keine Composer-Installation auf dem Server erforderlich.

== Verwendung ==

Das Formular umfasst Sendung, Erklärung sowie Prüfung und Unterschrift. Die E-Mail-Adresse ist Pflicht. Nachnahme und Empfangsdatum erscheinen nur bei zutreffender Auswahl.

Der Absender ist fest hinterlegt: Paradise X Sales GmbH, Gothaer Straße 4, 40880 Ratingen. Kunden können diese Anschrift nicht ändern. Sie wird serverseitig übernommen und im Formular, in der Zusammenfassung und im PDF angezeigt.

Kunden erhalten einen Downloadzugang für 30 Minuten und eine E-Mail-Kopie. Unter Empfängererklärungen findet ihr Suche, Datumfilter, PDF-Download, Versandstatus und endgültige Einzel-/Sammellöschung. Auf jeder Archivseite können bis zu 20 sichtbare Einträge gemeinsam ausgewählt werden.

An Mailversand übergeben bestätigt nur die Übergabe an den Mailtransport. Eine Zustellung beim Empfänger kann das Plugin nicht bestätigen. Fehlgeschlagene oder nach zehn Minuten unklare Versandvorgänge können erneut versucht werden. Unklare Vorgänge können dabei eine zweite E-Mail auslösen.

== Daten und Berechtigungen ==

Formularwerte und PDF werden in einer eigenen WordPress-Datenbanktabelle gespeichert, nicht in der Mediathek. Die Signatur ist im fertigen PDF eingebettet. Nur Administratoren erhalten zunächst die Berechtigung manage_ee_declarations.

Keine automatische Löschfrist. Deaktivierung und Deinstallation erhalten Erklärungen und Einstellungen. Zur Löschung die Einträge vorher ausdrücklich im Archiv entfernen. Bereits versendete E-Mail-Kopien und externe Backups werden dadurch nicht gelöscht.

E-Mail-Texte, Zieladressen und das PDF werden bei der Übermittlung festgehalten. Spätere Einstellungen verändern bereits unterschriebene Erklärungen nicht.

== Externe Dienste ==

Zur Updateprüfung ruft das Plugin ungefähr alle zwölf Stunden die öffentlichen Release-Metadaten von github.com/marcelDevParadise/empfaengererklaerung ab. Updates werden als fertige ZIP von GitHub heruntergeladen. Es wird kein GitHub-Zugang benötigt; Erklärungen, Kundendaten und PDFs werden nicht übertragen. GitHub erhält technisch bedingt die Server-IP und die üblichen HTTP-Verbindungsdaten. Automatische Installation kann unter Plugins aktiviert werden und setzt funktionierende WordPress-Hintergrundaufgaben voraus.

Das Plugin verwendet keine externen PDF-, Signatur-, Schrift- oder Captcha-Dienste. Es nutzt den vorhandenen WordPress-E-Mail-Versand (wp_mail / PHPMailer). Mailanbieter und Mail-Plugins können eigene externe Dienste verwenden. Der PDF-Anhang wird in PHPMailer direkt aus dem Speicher angefügt. Plugins, die wp_mail vollständig ersetzen oder über pre_wp_mail kurzschließen, müssen vor Live-Nutzung auf Anhang-Kompatibilität geprüft werden.

== Häufige Fragen ==

= Ist eine Bestellung oder Anmeldung erforderlich? =
Nein. Das Formular ist unabhängig von WooCommerce und öffentlich zugänglich.

= Ist die Unterschrift zertifiziert? =
Die gezeichnete Unterschrift wird als Bild eingebettet. Das Plugin erzeugt keine zertifikatsbasierte elektronische Signatur. Die Anerkennung der eigenen Vorlage beim jeweiligen Versanddienstleister ist separat zu klären.

= Was ist vor Veröffentlichung zu prüfen? =
HTTPS, Unternehmensangaben, Service-E-Mail, tatsächliche E-Mail-Zustellung samt Anhängen, Datenschutzhinweise und die Verarbeitung einer Test-Erklärung. Das Plugin verlinkt die in WordPress konfigurierte Datenschutzseite.

= Wird Multisite unterstützt? =
Version 1 ist für Einzelinstallationen gedacht. In Multisite einzeln pro Website aktivieren; eine netzwerkweite Aktivierung wird nicht angeboten.

== Changelog ==

= 1.2.0 =
GitHub-Updates über den normalen WordPress-Updater. Fertige Release-ZIP und Update-Metadaten werden gemeinsam veröffentlicht. Öffentliche Downloads ohne Token; automatische Installation über die WordPress-Option für Plugin-Updates.

= 1.1.0 =
Bestehende Erklärungen im Backend überarbeiten und separates, nicht erneut unterschriebenes PDF herunterladen. Originale bleiben erhalten. Sendungsnummern dürfen nur Ziffern enthalten und müssen mit 003404347382 beginnen.

= 1.0.2 =
Feste Absenderanschrift für Paradise X Sales GmbH. Keine Kundeneingabe erforderlich; abweichende übermittelte Absenderangaben werden serverseitig ersetzt.

= 1.0.1 =
Kompatibilität mit PHP 7.4.33: PHP-8-Syntax und neuere String-Funktionen ersetzt. PDF-Bibliothek und Funktionsumfang bleiben erhalten.

= 1.0.0 =
Erste Version: dreistufiges Formular, Touch-Unterschrift, eigene PDF-Vorlage, Kunden- und Service-E-Mail, geschütztes Archiv.
