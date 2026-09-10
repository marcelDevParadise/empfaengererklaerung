<?php
if (!defined('ABSPATH')) { exit; }

final class EE_Plugin {
    public static function init(): void {
        add_shortcode('empfaengererklaerung', [self::class, 'shortcode']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('admin_post_nopriv_ee_download', [self::class, 'download']);
        add_action('admin_post_ee_download', [self::class, 'download']);
    }
    public static function settings(): array {
        return wp_parse_args(get_option('ee_settings', []), [
            'company' => get_bloginfo('name'), 'logo_id' => 0, 'accent' => '#205c50',
            'service_email' => get_option('admin_email'), 'enabled' => false,
            'customer_subject' => 'Deine Empfängererklärung {vorgang}',
            'customer_body' => "Hallo {name},\n\nim Anhang findest du deine unterschriebene Empfängererklärung zur Sendung {sendungsnummer}.\nDeine Vorgangsnummer: {vorgang}\n\nViele Grüße\n{unternehmen}",
            'service_subject' => 'Neue Empfängererklärung {vorgang}',
            'service_body' => "Eine Empfängererklärung von {name} liegt vor.\n\nSendungsnummer: {sendungsnummer}\nVorgang: {vorgang}\n\nDas unterschriebene PDF befindet sich im Anhang und im WordPress-Archiv.",
        ]);
    }
    public static function shortcode(): string {
        $settings = self::settings();
        if (!$settings['enabled']) {
            return '<div class="ee-unavailable" role="status">Das Formular für Empfängererklärungen ist derzeit nicht verfügbar.</div>';
        }
        static $rendered = false;
        if ($rendered) { return ''; }
        $rendered = true;
        wp_enqueue_style('ee-form', EE_URL . 'assets/form.css', [], EE_VERSION);
        wp_enqueue_script('ee-form', EE_URL . 'assets/form.js', [], EE_VERSION, true);
        $config = ['api' => esc_url_raw(rest_url('empfaengererklaerung/v1/')), 'accent' => sanitize_hex_color($settings['accent']) ?: '#205c50'];
        ob_start(); require EE_DIR . 'templates/form.php'; return ob_get_clean();
    }
    public static function routes(): void {
        register_rest_route('empfaengererklaerung/v1', '/session', ['methods' => 'POST', 'callback' => [self::class, 'session'], 'permission_callback' => '__return_true']);
        register_rest_route('empfaengererklaerung/v1', '/declarations', ['methods' => 'POST', 'callback' => [self::class, 'submit'], 'permission_callback' => '__return_true']);
    }
    private static function gate(WP_REST_Request $request): ?WP_Error {
        if (!self::settings()['enabled']) { return new WP_Error('disabled', 'Das Formular ist derzeit nicht verfügbar.', ['status' => 503]); }
        if (strlen((string) $request->get_body()) > 230000) { return new WP_Error('too_large', 'Die übermittelten Daten sind zu groß.', ['status' => 413]); }
        $origin = $request->get_header('origin');
        if ($origin) {
            $site = wp_parse_url(home_url()); $source = wp_parse_url($origin);
            if (!$source || strtolower($source['host'] ?? '') !== strtolower($site['host'] ?? '') || ($source['scheme'] ?? '') !== ($site['scheme'] ?? '') || ($source['port'] ?? null) !== ($site['port'] ?? null)) {
                return new WP_Error('origin', 'Bitte das Formular direkt auf unserer Website öffnen.', ['status' => 403]);
            }
        }
        return null;
    }
    private static function rate(string $scope, int $limit): bool {
        global $wpdb;
        $end = (intdiv(time(), 1800) + 1) * 1800;
        $hash = substr(hash_hmac('sha256', $scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('nonce')), 0, 40);
        $name = 'ee_rate_' . $end . '_' . $hash;
        add_option($name, '0', '', false);
        $ok = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $name, $limit));
        // Entries contain only a salted hash, and are removed after their 30-minute window.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s", $wpdb->esc_like('ee_rate_') . '%', 'ee_rate_' . time() . '_'));
        wp_cache_delete($name, 'options');
        return $ok === 1;
    }
    /** @return WP_REST_Response|WP_Error */
    public static function session(WP_REST_Request $request) {
        if ($error = self::gate($request)) { return $error; }
        if (!self::rate('session', 60)) { return new WP_Error('rate', 'Zu viele Aufrufe. Bitte versuche es in 30 Minuten erneut.', ['status' => 429]); }
        $payload = base64_encode(wp_json_encode(['key' => bin2hex(random_bytes(32)), 'time' => time()]));
        $token = $payload . '.' . hash_hmac('sha256', $payload, wp_salt('auth'));
        return self::response(['session' => $token, 'today' => current_time('Y-m-d')]);
    }
    /**
     * @param mixed $token
     * @return array|WP_Error
     */
    private static function session_data($token) {
        $error = new WP_Error('expired_session', 'Die Formularsitzung ist abgelaufen. Bitte erneut absenden; deine Angaben bleiben erhalten.', ['status' => 403]);
        if (!is_string($token) || strlen($token) > 400) { return $error; }
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], wp_salt('auth')), $parts[1])) { return $error; }
        $data = json_decode(base64_decode($parts[0], true) ?: '', true);
        if (!is_array($data) || !is_string($data['key'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $data['key']) || !is_int($data['time'] ?? null) || $data['time'] > time()) { return $error; }
        if (time() - $data['time'] < 2) { return new WP_Error('too_fast', 'Bitte kurz warten und erneut absenden.', ['status' => 429]); }
        return $data;
    }
    /** @return WP_REST_Response|WP_Error */
    public static function submit(WP_REST_Request $request) {
        if ($error = self::gate($request)) { return $error; }
        $input = $request->get_json_params();
        if (!is_array($input)) { return new WP_Error('invalid', 'Ungültige Formulardaten.', ['status' => 400]); }
        if (!empty($input['website'])) { return new WP_Error('invalid', 'Die Übermittlung wurde abgelehnt.', ['status' => 422]); }
        $session = self::session_data($input['session'] ?? '');
        if (is_wp_error($session)) { return $session; }
        if (!self::rate('submit', 20)) { return new WP_Error('rate', 'Zu viele Übermittlungen. Bitte versuche es in 30 Minuten erneut.', ['status' => 429]); }
        $data = EE_Validation::validate($input);
        if (is_wp_error($data)) { return $data; }
        $request_hash = hash('sha256', $session['key']);
        $payload_hash = hash('sha256', wp_json_encode($data));
        $download_token = hash_hmac('sha256', 'download|' . $session['key'], wp_salt('secure_auth'));
        $existing = EE_Store::by_request($request_hash);
        if ($existing) { return self::existing($existing, $payload_hash, $download_token); }
        if (time() - $session['time'] > 7200) { return new WP_Error('expired_session', 'Die Formularsitzung ist abgelaufen. Bitte erneut absenden; deine Angaben bleiben erhalten.', ['status' => 403]); }
        $settings = self::settings();
        $brand = ['company' => $settings['company'], 'accent' => $settings['accent'], 'logo' => EE_PDF::logo((int) $settings['logo_id'])];
        $reference = 'EE-' . current_time('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
        try { $pdf = EE_PDF::generate($data, $brand, $reference); }
        catch (Throwable $error) { return new WP_Error('pdf_failed', 'Das PDF konnte nicht erstellt werden. Deine Angaben bleiben erhalten. Bitte versuche es erneut.', ['status' => 500]); }
        $data['_brand'] = ['company' => $brand['company'], 'accent' => $brand['accent']];
        $data['_mail'] = array_intersect_key($settings, array_flip(['customer_subject', 'customer_body', 'service_subject', 'service_body']));
        // Signature is already embedded in the immutable PDF; do not keep a second copy.
        unset($data['signature']);
        $id = EE_Store::insert([
            'reference' => $reference, 'request_hash' => $request_hash, 'payload_hash' => $payload_hash,
            'created_at' => gmdate('Y-m-d H:i:s'), 'tracking' => $data['tracking'],
            'customer_name' => $data['first_name'] . ' ' . $data['last_name'], 'customer_email' => $data['email'],
            'service_email' => $settings['service_email'], 'data' => wp_json_encode($data), 'pdf' => base64_encode($pdf),
            'token_hash' => hash('sha256', $download_token), 'token_expires' => time() + 1800,
        ]);
        if (!$id) {
            $existing = EE_Store::by_request($request_hash);
            if ($existing) { return self::existing($existing, $payload_hash, $download_token); }
            return new WP_Error('save_failed', 'Die Erklärung konnte nicht gespeichert werden. Bitte erneut versuchen.', ['status' => 500]);
        }
        EE_Store::send($id, 'service'); EE_Store::send($id, 'customer');
        return self::existing(EE_Store::get($id), $payload_hash, $download_token);
    }
    /** @return WP_REST_Response|WP_Error */
    private static function existing(object $row, string $payload_hash, string $token) {
        if (!hash_equals($row->payload_hash, $payload_hash)) { return new WP_Error('already_submitted', 'Diese Erklärung wurde bereits gespeichert. Für eine neue Erklärung bitte die Seite neu laden.', ['status' => 409]); }
        $valid = (int) $row->token_expires > time();
        return self::response([
            'reference' => $row->reference,
            'download' => $valid ? add_query_arg(['action' => 'ee_download', 'id' => $row->id, 'token' => $token], admin_url('admin-post.php')) : null,
            'expires' => (int) $row->token_expires,
            'customer_mail' => $row->customer_status,
        ]);
    }
    private static function response(array $data): WP_REST_Response {
        $response = new WP_REST_Response($data);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }
    public static function download(): void {
        $id = absint($_GET['id'] ?? 0); $row = EE_Store::get($id);
        $token = isset($_GET['token']) && is_string($_GET['token']) ? wp_unslash($_GET['token']) : '';
        $admin = current_user_can('manage_ee_declarations') && isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ee_download_' . $id);
        $public = $row && preg_match('/^[a-f0-9]{64}$/', $token) && (int) $row->token_expires > time() && hash_equals($row->token_hash, hash('sha256', $token));
        nocache_headers(); header('Referrer-Policy: no-referrer'); header('X-Content-Type-Options: nosniff');
        if (!$row || (!$admin && !$public)) { wp_die('Dieser Download ist nicht verfügbar oder bereits abgelaufen.', 'Download nicht verfügbar', ['response' => 403]); }
        $corrected = ($_GET['version'] ?? '') === 'revised';
        $stored = json_decode($row->data, true);
        if ($corrected && (!$admin || empty($stored['_correction']['_pdf']))) { wp_die('Diese Fassung ist nicht verfügbar.', '', ['response' => 403]); }
        $pdf = base64_decode($corrected ? $stored['_correction']['_pdf'] : $row->pdf, true);
        if (!$pdf) { wp_die('Das Dokument konnte nicht geladen werden.', '', ['response' => 500]); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($row->reference) . ($corrected ? '-ueberarbeitet' : '') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf; exit;
    }
}
