<?php
define('FS_METHOD', 'direct');
require dirname(__DIR__) . '/.tools/wordpress/wp-load.php';
if (wp_get_environment_type() !== 'local') { throw new RuntimeException('Local only'); }
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
wp_set_current_user(get_user_by('login', 'ee-admin')->ID);
global $wpdb;
$countBefore = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . EE_Store::table());
deactivate_plugins('empfaengererklaerung/empfaengererklaerung.php');
$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
$result = $upgrader->install(dirname(__DIR__) . '/dist/empfaengererklaerung-' . EE_VERSION . '.zip', ['overwrite_package' => true]);
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
if (!$result) { throw new RuntimeException('ZIP installation failed: ' . print_r($upgrader->skin->get_errors(), true)); }
$active = activate_plugin('empfaengererklaerung/empfaengererklaerung.php');
if (is_wp_error($active)) { throw new RuntimeException($active->get_error_message()); }
$countAfter = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . EE_Store::table());
if ($countBefore !== $countAfter) { throw new RuntimeException('Reactivation changed archive data'); }
if (hash_file('sha256', EE_DIR . 'assets/form.js') !== hash_file('sha256', dirname(__DIR__) . '/empfaengererklaerung/assets/form.js')) { throw new RuntimeException('Installed asset mismatch'); }
echo "PASS: WordPress installed the release ZIP and activated it; archive data preserved.\n";
