<?php
require dirname(__DIR__) . '/.tools/wordpress/wp-load.php';
if (wp_get_environment_type() !== 'local') { throw new RuntimeException('Local only.'); }
$checks = 0;
function update_expect(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++; echo 'PASS: ' . $label . "\n";
}
$checker = EE_Updater::checker();
$metadata = json_decode(file_get_contents(dirname(__DIR__) . '/dist/update.json'), true);
$fixture = $metadata;
$fixture['version'] = '9.9.9';
$fixture['download_url'] = EE_Updater::REPOSITORY . '/releases/download/v9.9.9/empfaengererklaerung-9.9.9.zip';
$http_status = 200; $requests = [];
$mock = static function ($pre, $args, $url) use (&$fixture, &$http_status, &$requests) {
    if (strpos($url, EE_Updater::METADATA_URL) !== 0) { return $pre; }
    $requests[] = $url;
    if ($http_status === 0) { return new WP_Error('offline', 'Simulated network outage'); }
    return ['headers' => [], 'body' => is_string($fixture) ? $fixture : wp_json_encode($fixture), 'response' => ['code' => $http_status, 'message' => 'Test'], 'cookies' => []];
};
add_filter('pre_http_request', $mock, 100, 3);
try {
    update_expect($checker === EE_Updater::checker(), 'one updater instance');
    $result = $checker->checkForUpdates();
    update_expect($result && $result->version === '9.9.9', 'published metadata exposes a newer version');
    update_expect(end($requests) === EE_Updater::METADATA_URL, 'no site or form data appended to metadata request');
    $updates = get_site_transient('update_plugins');
    $key = 'empfaengererklaerung/empfaengererklaerung.php';
    update_expect(isset($updates->response[$key]) && $updates->response[$key]->new_version === '9.9.9', 'new version appears in native WordPress update list');
    update_expect($updates->response[$key]->package === $fixture['download_url'], 'native updater uses complete release ZIP');
    update_expect($updates->response[$key]->requires_php === '7.4', 'PHP requirement reaches WordPress');
    $info = apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'empfaengererklaerung']);
    update_expect(is_object($info) && $info->download_link === $fixture['download_url'], 'plugin details and download available through WordPress');
    update_expect(apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'another-plugin']) === false, 'other plugins unaffected');
    $good = $fixture;
    foreach (['https://attacker.example/plugin.zip', EE_Updater::REPOSITORY . '/archive/refs/heads/main.zip', EE_Updater::REPOSITORY . '/releases/download/v9.9.8/empfaengererklaerung-9.9.8.zip', ''] as $url) {
        $fixture = $good; $fixture['download_url'] = $url;
        update_expect($checker->requestUpdate() === null, 'unexpected or missing package URL rejected');
    }
    $fixture = $good; $fixture['version'] = '9.9.9-beta';
    update_expect($checker->requestUpdate() === null, 'pre-release version rejected');
    $fixture = $good; $fixture['requires_php'] = '99.0';
    update_expect($checker->requestUpdate() === null, 'unsupported PHP version rejected');
    $fixture = $good; $fixture['requires'] = '99.0';
    update_expect($checker->requestUpdate() === null, 'unsupported WordPress version rejected');
    $fixture = $good; $fixture['requires_php'] = ['bad'];
    update_expect($checker->requestUpdate() === null, 'invalid metadata types rejected without fatal error');
    $fixture = '{broken'; update_expect($checker->requestUpdate() === null, 'invalid JSON safely ignored');
    $fixture = $good; $http_status = 404;
    update_expect($checker->requestUpdate() === null, 'missing release safely ignored');
    $http_status = 0; update_expect($checker->requestUpdate() === null, 'network outage does not break plugin');
    $http_status = 200; $fixture = $metadata;
    $checker->checkForUpdates();
    $updates = get_site_transient('update_plugins');
    update_expect(!isset($updates->response[$key]) && isset($updates->no_update[$key]), 'current version clears update and retains auto-update control');
    update_expect($checker->getInstalledVersion() === EE_VERSION, 'installed version matches plugin');
} finally {
    remove_filter('pre_http_request', $mock, 100);
    $checker->resetUpdateState();
}
echo "$checks updater assertions passed.\n";
