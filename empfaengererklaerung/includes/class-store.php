<?php
if (!defined('ABSPATH')) { exit; }

final class EE_Store {
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'ee_declarations'; }
    public static function activate(bool $network_wide = false): void {
        if (is_multisite() && $network_wide) { wp_die('Bitte Empfängererklärung für jede Website einzeln aktivieren. Netzwerkweite Aktivierung wird in Version 1 nicht unterstützt.'); }
        $missing = array_filter(['dom', 'mbstring', 'gd'], static fn($ext) => !extension_loaded($ext));
        if ($missing || !file_exists(EE_DIR . 'lib/dompdf/autoload.inc.php')) {
            wp_die('Empfängererklärung benötigt die PHP-Erweiterungen DOM, mbstring und GD sowie das vollständige Plugin-ZIP mit Dompdf.');
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(50) NOT NULL,
            request_hash char(64) NOT NULL,
            payload_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            tracking varchar(100) NOT NULL,
            customer_name varchar(201) NOT NULL,
            customer_email varchar(254) NOT NULL,
            service_email varchar(254) NOT NULL,
            data longtext NOT NULL,
            pdf longtext NOT NULL,
            token_hash char(64) NOT NULL,
            token_expires bigint(20) NOT NULL,
            customer_status varchar(20) NOT NULL DEFAULT 'pending',
            service_status varchar(20) NOT NULL DEFAULT 'pending',
            customer_attempt datetime DEFAULT NULL,
            service_attempt datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_hash (request_hash),
            UNIQUE KEY reference (reference),
            KEY created_at (created_at)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { wp_die('Die Archivtabelle konnte nicht angelegt werden. Bitte die Datenbankberechtigungen prüfen.'); }
        $role = get_role('administrator');
        if ($role) { $role->add_cap('manage_ee_declarations'); }
        update_option('ee_db_version', EE_VERSION, false);
    }

    public static function get(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
    }
    public static function by_request(string $hash): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE request_hash = %s', $hash));
    }
    public static function insert(array $row): int {
        global $wpdb;
        if ($wpdb->insert(self::table(), $row) === false) { return 0; }
        return (int) $wpdb->insert_id;
    }
    public static function remove(array $ids): int {
        global $wpdb;
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (!$ids) { return 0; }
        return (int) $wpdb->query('DELETE FROM ' . self::table() . ' WHERE id IN (' . implode(',', $ids) . ')');
    }

    /** @return true|WP_Error */
    public static function revise(int $id, array $input, string $revision) {
        if (!current_user_can('manage_ee_declarations')) { return new WP_Error('forbidden', 'Keine Berechtigung.'); }
        $row = self::get($id);
        if (!$row) { return new WP_Error('missing', 'Erklärung nicht gefunden.'); }
        if (!hash_equals(hash('sha256', $row->data), $revision)) { return new WP_Error('conflict', 'Die Erklärung wurde inzwischen geändert. Bitte die Detailansicht neu öffnen und die aktuelle Fassung prüfen.'); }
        $input['confirmed'] = true;
        $clean = EE_Validation::validate($input, false);
        if (is_wp_error($clean)) { return $clean; }
        unset($clean['confirmed']);
        $original = json_decode($row->data, true);
        $clean['_revised_at'] = gmdate('Y-m-d H:i:s');
        $clean['_revised_by'] = get_current_user_id();
        $clean['_revision_number'] = (int) ($original['_correction']['_revision_number'] ?? 0) + 1;
        try { $pdf = EE_PDF::generate($clean, $original['_brand'] + ['logo' => ''], $row->reference); }
        catch (Throwable $error) { return new WP_Error('pdf_failed', 'Das überarbeitete PDF konnte nicht erstellt werden. Bitte erneut versuchen.'); }
        $clean['_pdf'] = base64_encode($pdf);
        $original['_correction'] = $clean;
        global $wpdb;
        $saved = $wpdb->update(self::table(), [
            'data' => wp_json_encode($original), 'tracking' => $clean['tracking'],
            'customer_name' => $clean['first_name'] . ' ' . $clean['last_name'], 'customer_email' => $clean['email'],
        ], ['id' => $id, 'data' => $row->data]);
        if ($saved !== 1) { return new WP_Error('save_failed', 'Speichern fehlgeschlagen oder die Erklärung wurde inzwischen geändert. Bitte die Detailansicht neu öffnen.'); }
        return true;
    }

    public static function send(int $id, string $target, bool $retry = false): void {
        if (!in_array($target, ['customer', 'service'], true)) { return; }
        global $wpdb;
        $table = self::table(); $status = $target . '_status'; $attempt = $target . '_attempt';
        // Atomic claim: parallel retries cannot send the same attachment twice.
        $allowed = $retry ? "('pending','failed')" : "('pending')";
        $claimed = $wpdb->query($wpdb->prepare("UPDATE $table SET $status = 'sending', $attempt = %s WHERE id = %d AND ($status IN $allowed OR ($status = 'sending' AND $attempt < %s))", gmdate('Y-m-d H:i:s'), $id, gmdate('Y-m-d H:i:s', time() - 600)));
        if (!$claimed) { return; }
        $row = self::get($id);
        if (!$row) { return; }
        $data = json_decode($row->data, true);
        $settings = $data['_mail'];
        $replacements = ['{vorgang}' => $row->reference, '{name}' => $data['first_name'] . ' ' . $data['last_name'], '{sendungsnummer}' => $data['tracking'], '{unternehmen}' => $data['_brand']['company']];
        $subject = strtr($settings[$target . '_subject'], $replacements);
        $body = strtr($settings[$target . '_body'], $replacements);
        $bytes = base64_decode($row->pdf, true);
        $attach = static function ($mailer) use ($bytes, $row): void {
            $mailer->addStringAttachment($bytes, $row->reference . '.pdf', 'base64', 'application/pdf');
        };
        $ok = false;
        try {
            // Attach directly from memory: no customer PDF in a publicly accessible temp folder.
            add_action('phpmailer_init', $attach, PHP_INT_MAX);
            $ok = wp_mail($target === 'customer' ? $data['email'] : $row->service_email, sanitize_text_field($subject), $body, ['Content-Type: text/plain; charset=UTF-8']);
        } catch (Throwable $error) { $ok = false; }
        finally { remove_action('phpmailer_init', $attach, PHP_INT_MAX); }
        $wpdb->update($table, [$status => $ok ? 'handed_off' : 'failed'], ['id' => $id]);
    }
}
