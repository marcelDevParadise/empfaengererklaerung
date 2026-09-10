<?php
require dirname(__DIR__) . '/.tools/wordpress/wp-load.php';
if (wp_get_environment_type() !== 'local') { throw new RuntimeException('Local only'); }
global $wpdb;
$action = $argv[1] ?? ''; $id = (int) ($argv[2] ?? 0);
if ($action === 'reset-rate') { $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ee_rate_%'"); }
if ($action === 'expire') { $wpdb->update(EE_Store::table(), ['token_expires' => time()-1], ['id' => $id]); }
if ($action === 'fail-status') { $wpdb->update(EE_Store::table(), ['customer_status' => 'failed'], ['id' => $id]); }
if ($action === 'updates-start') {
    update_option('ee_test_saved_auto_updates', get_site_option('auto_update_plugins', []));
    update_site_option('auto_update_plugins', array_values(array_diff(get_site_option('auto_update_plugins', []), ['empfaengererklaerung/empfaengererklaerung.php'])));
    EE_Updater::checker()->checkForUpdates();
}
if ($action === 'updates-restore') {
    update_site_option('auto_update_plugins', get_option('ee_test_saved_auto_updates', []));
    delete_option('ee_test_saved_auto_updates');
    EE_Updater::checker()->resetUpdateState();
}
