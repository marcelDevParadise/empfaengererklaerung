<?php
// Simulate an older installed build, then run the real WordPress Plugin_Upgrader.
$root = dirname(__DIR__);
$installed = $root . '/.tools/wordpress/wp-content/plugins/empfaengererklaerung/empfaengererklaerung.php';
$config = file_get_contents($root . '/.tools/wordpress/wp-config.php');
if (strpos($config, "define('WP_ENVIRONMENT_TYPE', 'local')") === false) { throw new RuntimeException('Local test configuration required.'); }
$original_main = file_get_contents($installed);
$metadata = json_decode(file_get_contents($root . '/dist/update.json'), true);
$package = $root . '/dist/empfaengererklaerung-' . $metadata['version'] . '.zip';
$fixture_id = 0;
file_put_contents($installed, str_replace($metadata['version'], '1.1.99', $original_main));
try {
    define('FS_METHOD', 'direct');
    define('DOING_CRON', true);
    require $root . '/.tools/wordpress/wp-load.php';
    if (wp_get_environment_type() !== 'local') { throw new RuntimeException('Local only.'); }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    wp_set_current_user(get_user_by('login', 'ee-admin')->ID);
    $fixture_id = EE_Store::insert([
        'reference' => 'EE-UPDATE-' . bin2hex(random_bytes(6)), 'request_hash' => bin2hex(random_bytes(32)), 'payload_hash' => bin2hex(random_bytes(32)),
        'created_at' => gmdate('Y-m-d H:i:s'), 'tracking' => '00340434738212345678', 'customer_name' => 'Update Test',
        'customer_email' => 'update@example.test', 'service_email' => 'service@example.test',
        'data' => wp_json_encode(['original' => 'unchanged', '_correction' => ['_revision_number' => 1, '_pdf' => base64_encode(file_get_contents($root . '/.tools/artifacts/revised.pdf'))]]),
        'pdf' => base64_encode(file_get_contents($root . '/.tools/artifacts/normal.pdf')), 'token_hash' => bin2hex(random_bytes(32)), 'token_expires' => time() + 1800,
    ]);
    if (!$fixture_id) { throw new RuntimeException('Cannot create archive fixture.'); }
    $archive_before = serialize(EE_Store::get($fixture_id));
    $settings_before = get_option('ee_settings');
    $http_mock = static function ($pre, $args, $url) use ($metadata) {
        if ($url !== EE_Updater::METADATA_URL) { return $pre; }
        return ['headers' => [], 'body' => wp_json_encode($metadata), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
    };
    add_filter('pre_http_request', $http_mock, 100, 3);
    $download_mock = static function ($reply, $url) use ($metadata, $package) {
        if ($url !== $metadata['download_url']) { return $reply; }
        $temp = wp_tempnam('ee-upgrade.zip'); copy($package, $temp); return $temp;
    };
    add_filter('upgrader_pre_download', $download_mock, 10, 2);
    $checker = EE_Updater::checker();
    $checker->checkForUpdates();
    $key = 'empfaengererklaerung/empfaengererklaerung.php';
    $updates = get_site_transient('update_plugins');
    if (($updates->response[$key]->new_version ?? '') !== $metadata['version']) { throw new RuntimeException('Update not offered to older installation.'); }
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->upgrade($key);
    if (!$result || is_wp_error($result)) { throw new RuntimeException('WordPress update failed: ' . print_r($upgrader->skin->get_errors(), true)); }
    if (get_plugin_data($installed, false, false)['Version'] !== $metadata['version']) { throw new RuntimeException('Wrong version installed.'); }
    if (!is_plugin_active($key)) { throw new RuntimeException('Plugin was not kept active.'); }
    if ($archive_before !== serialize(EE_Store::get($fixture_id)) || $settings_before !== get_option('ee_settings')) { throw new RuntimeException('Update changed archive or settings.'); }
    if (!is_file(dirname($installed) . '/lib/plugin-update-checker/plugin-update-checker.php') || !is_file(dirname($installed) . '/lib/dompdf/autoload.inc.php')) { throw new RuntimeException('Missing installed dependencies.'); }
    echo "PASS: WordPress detected and installed the newer release; plugin remains active; settings, original PDF and correction preserved.\n";
} finally {
    if ($fixture_id) { EE_Store::remove([$fixture_id]); }
    file_put_contents($installed, $original_main);
    if (class_exists('EE_Updater')) { EE_Updater::checker()->resetUpdateState(); }
}
