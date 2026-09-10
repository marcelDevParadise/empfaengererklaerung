<?php
if (!defined('ABSPATH')) { exit; }

final class EE_Validation {
    public const TRACKING_PREFIX = '003404347382';
    public const SENDER_ADDRESS = "Paradise X Sales GmbH\nGothaer Straße 4\n40880 Ratingen";
    public const FIELDS = [
        'tracking' => ['Sendungsnummer', 100, true],
        'carrier' => ['Versanddienstleister', 100, true],
        'sender' => ['Absender mit Anschrift', 500, true],
        'recipient' => ['Sendung adressiert an', 500, true],
        'contents' => ['Sendungsinhalt', 1500, true],
        'delivery_to' => ['Dokumentierter Zustellempfänger', 200, false],
        'delivery_date' => ['Dokumentiertes Zustelldatum', 10, false],
        'first_name' => ['Vorname', 100, true],
        'last_name' => ['Nachname', 100, true],
        'address' => ['Anschrift der erklärenden Person', 500, true],
        'email' => ['E-Mail-Adresse', 254, true],
        'phone' => ['Telefon', 60, false],
        'received_date' => ['Empfangsdatum', 10, false],
        'place' => ['Ort', 150, true],
        'date' => ['Datum der Erklärung', 10, true],
    ];

    /** @return array|WP_Error */
    public static function validate(array $input, bool $require_signature = true) {
        // The sender is fixed by the business, never supplied by the customer.
        $input['sender'] = self::SENDER_ADDRESS;
        $data = []; $errors = [];
        foreach (self::FIELDS as $key => [$label, $limit, $required]) {
            $raw = $input[$key] ?? '';
            if (!is_string($raw)) { $errors[$key] = 'Bitte einen gültigen Wert eingeben.'; $raw = ''; }
            if (mb_strlen($raw) > $limit) { $errors[$key] = "Maximal {$limit} Zeichen sind erlaubt."; }
            $data[$key] = in_array($key, ['sender', 'recipient', 'contents', 'address'], true)
                ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
            if ($required && $data[$key] === '') { $errors[$key] = $label . ' fehlt.'; }
            if (in_array($key, ['sender', 'recipient', 'address'], true) && substr_count($data[$key], "\n") > 11) {
                $errors[$key] = 'Bitte die Anschrift auf höchstens 12 Zeilen begrenzen.';
            }
            if ($key === 'contents' && substr_count($data[$key], "\n") > 29) { $errors[$key] = 'Bitte den Inhalt auf höchstens 30 Zeilen begrenzen.'; }
        }
        if (!is_string($input['tracking'] ?? null) || !preg_match('/\A' . self::TRACKING_PREFIX . '[0-9]+\z/', $input['tracking'])) {
            $errors['tracking'] = 'Bitte die vollständige Sendungsnummer eingeben: nur Ziffern, beginnend mit ' . self::TRACKING_PREFIX . '.';
        }
        if (!is_email($data['email'])) { $errors['email'] = 'Bitte eine gültige E-Mail-Adresse eingeben.'; }
        foreach (['person' => ['ich', 'wir'], 'receipt' => ['not_received', 'received'], 'cod' => ['no', 'yes']] as $key => $values) {
            $data[$key] = is_string($input[$key] ?? null) ? $input[$key] : '';
            if (!in_array($data[$key], $values, true)) { $errors[$key] = 'Bitte eine Option auswählen.'; }
        }
        $data['unknown'] = ($input['unknown'] ?? false) === true && $data['receipt'] === 'not_received';
        $data['cod_payment'] = $data['cod'] === 'yes' && is_string($input['cod_payment'] ?? null) ? $input['cod_payment'] : '';
        if ($data['cod'] === 'yes' && !in_array($data['cod_payment'], ['courier', 'branch', 'unpaid'], true)) {
            $errors['cod_payment'] = 'Bitte die Zahlung des Nachnahmebetrags angeben.';
        }
        if ($data['receipt'] !== 'received') { $data['received_date'] = ''; }
        elseif ($data['received_date'] === '') { $errors['received_date'] = 'Bitte das Empfangsdatum angeben.'; }
        foreach (['delivery_date', 'received_date', 'date'] as $key) {
            if ($data[$key] === '') { continue; }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$key]);
            if (!$date || $date->format('Y-m-d') !== $data[$key] || $data[$key] > current_time('Y-m-d')) {
                $errors[$key] = 'Bitte ein gültiges Datum angeben, das nicht in der Zukunft liegt.';
            }
        }
        if (($input['confirmed'] ?? false) !== true) { $errors['confirmed'] = 'Bitte die Richtigkeit und Vollständigkeit bestätigen.'; }
        $data['confirmed'] = true;
        if ($require_signature) {
            $signature = self::signature($input['signature'] ?? '');
            if (is_wp_error($signature)) { $errors['signature'] = $signature->get_error_message(); }
            else { $data['signature'] = $signature; }
        }
        if ($errors) { return new WP_Error('invalid_fields', 'Bitte prüfe die markierten Angaben.', ['status' => 422, 'fields' => $errors]); }
        return $data;
    }

    /**
     * @param mixed $value
     * @return string|WP_Error
     */
    private static function signature($value) {
        $error = new WP_Error('signature', 'Bitte im Unterschriftsfeld unterschreiben.');
        if (!is_string($value) || strlen($value) > 180000 || substr($value, 0, 22) !== 'data:image/png;base64,') { return $error; }
        $bytes = base64_decode(substr($value, 22), true);
        if (!$bytes || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") { return $error; }
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] < 100 || $size[1] < 60 || $size[0] > 1600 || $size[1] > 700) { return $error; }
        $image = @imagecreatefromstring($bytes);
        if (!$image) { return $error; }
        $ink = 0;
        for ($y = 0; $y < imagesy($image); $y += 2) {
            for ($x = 0; $x < imagesx($image); $x += 2) {
                $c = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if ($c['alpha'] < 100 && ($c['red'] + $c['green'] + $c['blue']) < 600) { $ink++; }
            }
        }
        if ($ink < 25) { imagedestroy($image); return $error; }
        imagesavealpha($image, true);
        ob_start(); imagepng($image); $clean = ob_get_clean(); imagedestroy($image);
        return 'data:image/png;base64,' . base64_encode($clean);
    }

    public static function statement(array $d): string {
        $we = $d['person'] === 'wir';
        $text = $we ? 'Wir erklären, dass wir die oben bezeichnete Sendung ' : 'Ich erkläre, dass ich die oben bezeichnete Sendung ';
        $text .= $d['receipt'] === 'received' ? 'am ' . self::date($d['received_date']) . ' erhalten ' : 'nicht erhalten ';
        $text .= $we ? 'haben.' : 'habe.';
        if ($d['unknown']) { $text .= $we ? ' Über den Verbleib ist uns nichts bekannt.' : ' Über den Verbleib ist mir nichts bekannt.'; }
        if ($d['cod'] === 'yes') {
            $payments = [
                'courier' => 'Der Nachnahmebetrag wurde an den Zusteller gezahlt.',
                'branch' => 'Der Nachnahmebetrag wurde am Ausgabeschalter der Postfiliale oder Postagentur gezahlt.',
                'unpaid' => 'Der Nachnahmebetrag wurde nicht gezahlt.',
            ];
            $text .= ' ' . ($payments[$d['cod_payment']] ?? $payments['unpaid']);
        }
        return $text;
    }

    public static function date(string $date): string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $d ? $d->format('d.m.Y') : '—';
    }
}
