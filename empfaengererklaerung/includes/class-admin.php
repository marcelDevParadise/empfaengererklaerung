<?php
if (!defined('ABSPATH')) { exit; }

final class EE_Admin {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_post_ee_archive_action', [self::class, 'action']);
    }
    public static function menu(): void {
        add_menu_page('Empfängererklärungen', 'Empfängererklärungen', 'manage_ee_declarations', 'ee-archive', [self::class, 'archive'], 'dashicons-media-document', 58);
        add_submenu_page('ee-archive', 'Einstellungen', 'Einstellungen', 'manage_options', 'ee-settings', [self::class, 'settings']);
    }
    public static function assets(string $hook): void {
        if (!in_array(sanitize_key($_GET['page'] ?? ''), ['ee-settings', 'ee-archive'], true)) { return; }
        wp_enqueue_style('ee-admin', EE_URL . 'assets/admin.css', [], EE_VERSION);
        wp_enqueue_script('ee-admin', EE_URL . 'assets/admin.js', [], EE_VERSION, true);
        if (($_GET['page'] ?? '') === 'ee-settings') { wp_enqueue_media(); }
    }
    public static function register(): void {
        register_setting('ee_settings_group', 'ee_settings', ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize']]);
    }
    /** @param mixed $input */
    public static function sanitize($input): array {
        $old = EE_Plugin::settings();
        if (!is_array($input)) { return $old; }
        $out = $old;
        foreach (['company' => 150, 'customer_subject' => 200, 'service_subject' => 200, 'customer_body' => 5000, 'service_body' => 5000] as $field => $length) {
            $value = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            if ($value === '' || mb_strlen($value) > $length) {
                add_settings_error('ee_settings', $field, 'Bitte alle Texte ausfüllen und die angegebenen Zeichenlimits beachten.');
                continue;
            }
            $out[$field] = substr($field, -5) === '_body' ? sanitize_textarea_field($value) : sanitize_text_field($value);
        }
        $email = is_string($input['service_email'] ?? null) ? sanitize_email($input['service_email']) : '';
        if (!is_email($email)) { add_settings_error('ee_settings', 'email', 'Bitte eine gültige Service-E-Mail-Adresse angeben.'); }
        else { $out['service_email'] = $email; }
        $out['accent'] = sanitize_hex_color(is_string($input['accent'] ?? null) ? $input['accent'] : '') ?: $old['accent'];
        $logo = absint($input['logo_id'] ?? 0);
        if ($logo && !EE_PDF::logo($logo)) {
            add_settings_error('ee_settings', 'logo', 'Bitte ein PNG- oder JPG-Logo bis 1 MB und maximal 4 Millionen Pixel auswählen.');
        } else { $out['logo_id'] = $logo; }
        $out['enabled'] = !empty($input['enabled']) && is_email($email);
        return $out;
    }
    public static function settings(): void {
        if (!current_user_can('manage_options')) { return; }
        $s = EE_Plugin::settings();
        ?>
        <div class="wrap ee-admin"><h1>Empfängererklärung · Einstellungen</h1>
        <p>Formular auf einer Seite einbinden: <code>[empfaengererklaerung]</code></p>
        <p>Plugin-Updates kommen aus den öffentlichen <a href="https://github.com/marcelDevParadise/empfaengererklaerung/releases" target="_blank" rel="noopener noreferrer">GitHub-Releases</a>. Unter <a href="<?= esc_url(admin_url('plugins.php')) ?>">Plugins</a> kannst du für „Empfängererklärung“ automatische Updates aktivieren oder manuell nach Updates suchen. Dafür wird kein GitHub-Zugang benötigt.</p>
        <?php settings_errors('ee_settings'); ?>
        <form action="options.php" method="post"><?php settings_fields('ee_settings_group'); ?>
        <table class="form-table" role="presentation"><tbody>
        <tr><th><label for="ee-enabled">Formular freischalten</label></th><td><label><input id="ee-enabled" name="ee_settings[enabled]" type="checkbox" value="1" <?php checked($s['enabled']); ?>> Öffentliches Formular aktivieren. Die Service-E-Mail-Adresse unten ist geprüft.</label></td></tr>
        <tr><th><label for="ee-company">Unternehmensname</label></th><td><input class="regular-text" id="ee-company" name="ee_settings[company]" maxlength="150" required value="<?= esc_attr($s['company']) ?>"></td></tr>
        <tr><th>Logo</th><td><input type="hidden" id="ee-logo-id" name="ee_settings[logo_id]" value="<?= (int) $s['logo_id'] ?>"><div id="ee-logo-preview"><?php if ($s['logo_id']) { echo wp_get_attachment_image((int) $s['logo_id'], 'thumbnail'); } ?></div><button class="button" type="button" data-logo-select>Logo auswählen</button> <button class="button" type="button" data-logo-remove>Entfernen</button><p class="description">PNG oder JPG, bis 1 MB, maximal 4 Millionen Pixel. Ohne Logo wird nur der Unternehmensname angezeigt.</p></td></tr>
        <tr><th><label for="ee-accent">Akzentfarbe</label></th><td><input type="color" id="ee-accent" name="ee_settings[accent]" value="<?= esc_attr($s['accent']) ?>"></td></tr>
        <tr><th><label for="ee-service-email">Service-E-Mail</label></th><td><input type="email" class="regular-text" id="ee-service-email" name="ee_settings[service_email]" required value="<?= esc_attr($s['service_email']) ?>"><p class="description">Hierhin gehen neue Erklärungen. Der Versand verwendet die bestehende WordPress-Mailkonfiguration.</p></td></tr>
        <?php foreach (['customer' => 'Kundenkopie', 'service' => 'Service-Nachricht'] as $key => $label): ?>
        <tr><th colspan="2"><h2><?= esc_html($label) ?></h2></th></tr>
        <tr><th><label for="ee-<?= esc_attr($key) ?>-subject">Betreff</label></th><td><input class="large-text" id="ee-<?= esc_attr($key) ?>-subject" name="ee_settings[<?= esc_attr($key) ?>_subject]" maxlength="200" required value="<?= esc_attr($s[$key . '_subject']) ?>"></td></tr>
        <tr><th><label for="ee-<?= esc_attr($key) ?>-body">Nachricht</label></th><td><textarea class="large-text" rows="7" id="ee-<?= esc_attr($key) ?>-body" name="ee_settings[<?= esc_attr($key) ?>_body]" maxlength="5000" required><?= esc_textarea($s[$key . '_body']) ?></textarea><p class="description">Platzhalter: <code>{name}</code>, <code>{vorgang}</code>, <code>{sendungsnummer}</code>, <code>{unternehmen}</code>. Reiner Text, kein HTML.</p></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <p>Bereits gespeicherte PDFs, Empfängeradressen und E-Mail-Texte bleiben bei Änderungen unverändert. Erklärungen werden ausschließlich manuell im Archiv gelöscht.</p>
        <?php submit_button(); ?></form></div>
        <?php
    }
    private static function status(string $value): string {
        return ['pending' => 'Ausstehend', 'sending' => 'Versand läuft / Ergebnis offen', 'handed_off' => 'An Mailversand übergeben', 'failed' => 'Versand fehlgeschlagen'][$value] ?? 'Unbekannt';
    }
    private static function download_url(int $id): string {
        return wp_nonce_url(add_query_arg(['action' => 'ee_download', 'id' => $id], admin_url('admin-post.php')), 'ee_download_' . $id);
    }
    public static function archive(): void {
        if (!current_user_can('manage_ee_declarations')) { return; }
        if (!empty($_GET['edit'])) { self::edit(absint($_GET['edit'])); return; }
        if (!empty($_GET['view'])) { self::detail(absint($_GET['view'])); return; }
        global $wpdb;
        $search = isset($_GET['s']) && is_string($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $from = self::filter_date($_GET['from'] ?? ''); $to = self::filter_date($_GET['to'] ?? '');
        $page = max(1, absint($_GET['paged'] ?? 1)); $where = '1=1'; $params = [];
        if ($search !== '') { $where .= ' AND (tracking LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR reference LIKE %s)'; $like = '%' . $wpdb->esc_like($search) . '%'; $params = [$like, $like, $like, $like]; }
        if ($from) { $where .= ' AND created_at >= %s'; $params[] = get_gmt_from_date($from . ' 00:00:00'); }
        if ($to) { $where .= ' AND created_at <= %s'; $params[] = get_gmt_from_date($to . ' 23:59:59'); }
        $table = EE_Store::table();
        $condition = $params ? $wpdb->prepare($where, $params) : $where;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $condition");
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,reference,created_at,tracking,customer_name,customer_email,customer_status,service_status FROM $table WHERE $condition ORDER BY id DESC LIMIT 20 OFFSET %d", ($page - 1) * 20));
        ?>
        <div class="wrap ee-admin"><h1>Empfängererklärungen <span class="ee-count"><?= (int) $total ?></span></h1>
        <p>Zum Überarbeiten einen Vorgang öffnen und „Angaben bearbeiten“ wählen. Unterschriebene Originale bleiben erhalten. „An Mailversand übergeben“ bestätigt keine Zustellung beim Empfänger.</p>
        <?php if (isset($_GET['deleted'])): ?><div class="notice notice-success"><p><?= absint($_GET['deleted']) ?> Erklärung(en) gelöscht.</p></div><?php endif; ?>
        <form method="get" class="ee-filters"><input type="hidden" name="page" value="ee-archive"><label>Suche <input type="search" name="s" value="<?= esc_attr($search) ?>" placeholder="Name, E-Mail, Sendung, Vorgang"></label><label>Von <input type="date" name="from" value="<?= esc_attr($from) ?>"></label><label>Bis <input type="date" name="to" value="<?= esc_attr($to) ?>"></label><button class="button">Filtern</button><a href="<?= esc_url(admin_url('admin.php?page=ee-archive')) ?>">Zurücksetzen</a></form>
        <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" data-archive-form>
        <input type="hidden" name="action" value="ee_archive_action"><input type="hidden" name="operation" value="delete"><?php wp_nonce_field('ee_archive_action'); ?>
        <div class="ee-bulk"><strong data-selection-count>0 ausgewählt</strong><button type="submit" class="button ee-delete" disabled>Ausgewählte löschen</button><span>Die Auswahl gilt für diese Ergebnisseite.</span></div>
        <div class="ee-table-scroll"><table class="wp-list-table widefat fixed striped"><thead><tr><td class="check-column"><input type="checkbox" data-select-all aria-label="Alle Einträge auf dieser Seite auswählen"></td><th>Vorgang / Datum</th><th>Sendung</th><th>Kunde</th><th>Kundenkopie</th><th>Service</th><th>Dokument</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="7">Keine Erklärungen gefunden.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr>
            <th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="<?= (int) $row->id ?>" aria-label="<?= esc_attr($row->reference) ?> auswählen"></th>
            <td><a href="<?= esc_url(add_query_arg(['page' => 'ee-archive', 'view' => $row->id], admin_url('admin.php'))) ?>"><strong><?= esc_html($row->reference) ?></strong></a><br><?= esc_html(get_date_from_gmt($row->created_at, 'd.m.Y H:i')) ?></td>
            <td><?= esc_html($row->tracking) ?></td><td><?= esc_html($row->customer_name) ?><br><?= esc_html($row->customer_email) ?></td>
            <td><?= esc_html(self::status($row->customer_status)) ?></td><td><?= esc_html(self::status($row->service_status)) ?></td>
            <td><a href="<?= esc_url(self::download_url((int) $row->id)) ?>">PDF herunterladen</a></td>
        </tr><?php endforeach; ?></tbody></table></div></form>
        <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post((string) paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $page, 'total' => (int) ceil($total / 20)])); ?></div></div></div>
        <?php
    }
    /** @param mixed $value */
    private static function filter_date($value): string {
        if (!is_string($value)) { return ''; }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value ? $value : '';
    }
    private static function detail(int $id): void {
        $row = EE_Store::get($id);
        if (!$row) { echo '<div class="wrap"><h1>Erklärung nicht gefunden</h1></div>'; return; }
        $original = json_decode($row->data, true);
        $data = $original['_correction'] ?? $original;
        ?>
        <div class="wrap ee-admin"><a href="<?= esc_url(admin_url('admin.php?page=ee-archive')) ?>">← Zum Archiv</a><h1><?= esc_html($row->reference) ?></h1>
        <p>Gespeichert am <?= esc_html(get_date_from_gmt($row->created_at, 'd.m.Y H:i')) ?> · <a class="button" href="<?= esc_url(self::download_url($id)) ?>">PDF herunterladen</a></p>
        <p><a class="button button-primary" href="<?= esc_url(add_query_arg(['page' => 'ee-archive', 'edit' => $id], admin_url('admin.php'))) ?>">Angaben bearbeiten</a></p>
        <?php if (!empty($original['_correction'])): ?><div class="notice notice-info"><p>Überarbeitete Fassung <?= (int) $data['_revision_number'] ?> · <?= esc_html(get_date_from_gmt($data['_revised_at'], 'd.m.Y H:i')) ?> · bearbeitet von Benutzer-ID <?= (int) $data['_revised_by'] ?>. Nicht erneut unterschrieben. „PDF herunterladen“ öffnet das unterschriebene Original.</p><p><a class="button" href="<?= esc_url(add_query_arg('version', 'revised', self::download_url($id))) ?>">Überarbeitetes PDF herunterladen</a></p></div><?php endif; ?>
        <?php if (isset($_GET['resent'])): ?><div class="notice notice-info"><p>Versandaktion verarbeitet. Den aktuellen Status findest du unten.</p></div><?php endif; ?>
        <div class="ee-admin-card"><h2>Angaben</h2><dl class="ee-detail-list">
        <?php foreach (EE_Validation::FIELDS as $key => [$label]): if (empty($data[$key])) { continue; } ?>
            <dt><?= esc_html($label) ?></dt><dd><?= nl2br(esc_html(substr($key, -4) === 'date' ? EE_Validation::date($data[$key]) : $data[$key])) ?></dd>
        <?php endforeach; ?></dl><h2><?= isset($original['_correction']) ? 'Überarbeitete Erklärung' : 'Unterschriebene Erklärung' ?></h2><p><?= esc_html(EE_Validation::statement($data)) ?></p><p><?= isset($original['_correction']) ? 'Die Änderungen sind nicht erneut unterschrieben. Das Original-PDF enthält die ursprünglich bestätigten Angaben und die Unterschrift.' : 'Richtigkeit und Vollständigkeit wurden bestätigt. Die Unterschrift ist im gespeicherten PDF enthalten.' ?></p></div>
        <div class="ee-admin-card"><h2>E-Mail-Versand des Originals</h2><p>Versandstatus und erneute Versandversuche beziehen sich auf das Original-PDF und die ursprünglichen E-Mail-Adressen. Überarbeitungen werden nicht automatisch versendet.</p>
        <?php foreach (['customer' => 'Kundenkopie', 'service' => 'Service'] as $target => $label): ?>
            <p><strong><?= esc_html($label) ?></strong> · <?= esc_html($target === 'customer' ? $original['email'] : $row->service_email) ?><br><?= esc_html(self::status($row->{$target . '_status'})) ?><?php if ($row->{$target . '_attempt'}): ?> · letzter Versuch <?= esc_html(get_date_from_gmt($row->{$target . '_attempt'}, 'd.m.Y H:i')) ?><?php endif; ?></p>
            <?php if (in_array($row->{$target . '_status'}, ['pending', 'failed'], true) || ($row->{$target . '_status'} === 'sending' && strtotime($row->{$target . '_attempt'} . ' UTC') < time() - 600)): ?>
            <form action="<?= esc_url(admin_url('admin-post.php')) ?>" method="post" <?= $row->{$target . '_status'} === 'sending' ? 'data-uncertain-mail' : '' ?>><?php wp_nonce_field('ee_archive_action'); ?><input type="hidden" name="action" value="ee_archive_action"><input type="hidden" name="operation" value="resend"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="target" value="<?= esc_attr($target) ?>"><button class="button">Versand erneut versuchen</button></form>
            <?php endif; ?>
        <?php endforeach; ?><p class="description">Bei abgebrochenen Versandvorgängen ist das Ergebnis möglicherweise unbekannt. Ein erneuter Versuch kann eine zweite E-Mail auslösen.</p></div>
        <form action="<?= esc_url(admin_url('admin-post.php')) ?>" method="post" data-archive-form><?php wp_nonce_field('ee_archive_action'); ?><input type="hidden" name="action" value="ee_archive_action"><input type="hidden" name="operation" value="delete"><input type="hidden" name="ids[]" value="<?= $id ?>"><button class="button ee-delete">Erklärung endgültig löschen</button></form>
        </div>
        <?php
    }
    private static function edit(int $id, array $values = [], ?WP_Error $error = null): void {
        $row = EE_Store::get($id);
        if (!$row) { echo '<div class="wrap"><h1>Erklärung nicht gefunden</h1></div>'; return; }
        $stored = json_decode($row->data, true);
        $data = $values ?: ($stored['_correction'] ?? $stored);
        $revision = $values ? (is_string($_POST['revision'] ?? null) ? wp_unslash($_POST['revision']) : '') : hash('sha256', $row->data);
        require EE_DIR . 'templates/admin-edit.php';
    }
    public static function action(): void {
        if (!current_user_can('manage_ee_declarations')) { wp_die('Keine Berechtigung.', '', ['response' => 403]); }
        check_admin_referer('ee_archive_action');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Ungültige Anfrage.', '', ['response' => 405]); }
        $operation = sanitize_key($_POST['operation'] ?? '');
        if ($operation === 'edit') {
            $id = absint($_POST['id'] ?? 0);
            $input = isset($_POST['declaration']) && is_array($_POST['declaration']) ? wp_unslash($_POST['declaration']) : [];
            $input['unknown'] = !empty($input['unknown']);
            $revision = is_string($_POST['revision'] ?? null) ? wp_unslash($_POST['revision']) : '';
            $result = EE_Store::revise($id, $input, $revision);
            if (is_wp_error($result)) {
                global $hook_suffix, $title, $parent_file, $submenu_file;
                $hook_suffix = 'toplevel_page_ee-archive';
                $title = 'Angaben bearbeiten'; $parent_file = 'ee-archive'; $submenu_file = 'ee-archive';
                set_current_screen($hook_suffix);
                wp_enqueue_style('ee-admin', EE_URL . 'assets/admin.css', [], EE_VERSION);
                require_once ABSPATH . 'wp-admin/admin-header.php';
                self::edit($id, $input, $result);
                require_once ABSPATH . 'wp-admin/admin-footer.php'; exit;
            }
            wp_safe_redirect(add_query_arg(['page' => 'ee-archive', 'view' => $id, 'updated' => 1], admin_url('admin.php'))); exit;
        }
        if ($operation === 'delete') {
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_slice($_POST['ids'], 0, 100) : [];
            $count = EE_Store::remove($ids);
            wp_safe_redirect(add_query_arg(['page' => 'ee-archive', 'deleted' => $count], admin_url('admin.php'))); exit;
        }
        if ($operation === 'resend') {
            $id = absint($_POST['id'] ?? 0); $target = sanitize_key($_POST['target'] ?? '');
            EE_Store::send($id, $target, true);
            wp_safe_redirect(add_query_arg(['page' => 'ee-archive', 'view' => $id, 'resent' => 1], admin_url('admin.php'))); exit;
        }
        wp_die('Unbekannte Aktion.', '', ['response' => 400]);
    }
}
