<?php
// Isolated development WordPress. Never run against a production installation.
$root = dirname(__DIR__);
$wpDirectory = $root . '/.tools/wordpress';
if (!is_dir($wpDirectory) || !is_dir($root . '/.tools/sqlite-database-integration')) { throw new RuntimeException('Download the test dependencies first.'); }
@mkdir($wpDirectory . '/wp-content/mu-plugins', 0777, true);
$sqlite = str_replace('\\', '/', $root . '/.tools/sqlite-database-integration');
$dropin = file_get_contents($sqlite . '/db.copy');
$dropin = str_replace(['{SQLITE_IMPLEMENTATION_FOLDER_PATH}', '{SQLITE_PLUGIN}'], [$sqlite, $sqlite . '/load.php'], $dropin);
file_put_contents($wpDirectory . '/wp-content/db.php', $dropin);
$config = <<<'PHP'
<?php
define('DB_NAME', 'ee_local');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('DB_ENGINE', 'sqlite');
define('WP_HOME', 'http://127.0.0.1:8097');
define('WP_SITEURL', 'http://127.0.0.1:8097');
define('WP_ENVIRONMENT_TYPE', 'local');
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', true);
$table_prefix = 'wp_';
PHP;
foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key) {
    $config .= "\ndefine('$key', '" . bin2hex(random_bytes(32)) . "');";
}
$config .= "\nif (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n";
if (!file_exists($wpDirectory . '/wp-config.php')) { file_put_contents($wpDirectory . '/wp-config.php', $config); }
$mu = <<<'PHP'
<?php
// Only the isolated test server: suppress all external mail and HTTP requests.
add_filter('pre_http_request', static fn() => new WP_Error('local_only', 'No external traffic in tests.'), 10, 3);
add_filter('wp_mail_from', static fn() => 'noreply@example.test');
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
class EE_Local_Mailer extends PHPMailer\PHPMailer\PHPMailer {
    public function send() {
        $recipients = $this->getToAddresses();
        $attachments = $this->getAttachments();
        $log = ['to' => $recipients, 'subject' => $this->Subject, 'attachments' => array_map(static fn($a) => ['name' => $a[2], 'bytes' => strlen($a[0]), 'hash' => hash('sha256', $a[0])], $attachments)];
        file_put_contents(dirname(ABSPATH) . '/mail-log.jsonl', json_encode($log) . "\n", FILE_APPEND);
        if (get_option('ee_test_mail_fail', false)) { throw new PHPMailer\PHPMailer\Exception('Simulated transport failure'); }
        return true;
    }
}
$GLOBALS['phpmailer'] = new EE_Local_Mailer(true);
PHP;
file_put_contents($wpDirectory . '/wp-content/mu-plugins/ee-local-only.php', $mu);
define('WP_INSTALLING', true);
require $wpDirectory . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_blog_installed()) { wp_install('Paketservice Test', 'ee-admin', 'admin@example.test', true, '', 'Local-EE-Test-2026!'); }
$error = activate_plugin('empfaengererklaerung/empfaengererklaerung.php');
if (is_wp_error($error)) { throw new RuntimeException($error->get_error_message()); }
if (!class_exists('EE_Plugin')) { require_once WP_PLUGIN_DIR . '/empfaengererklaerung/empfaengererklaerung.php'; }
$settings = EE_Plugin::settings(); $settings['enabled'] = true; $settings['company'] = 'Paketservice'; $settings['service_email'] = 'service@example.test'; update_option('ee_settings', $settings);
update_option('blogname', 'Paketservice Test');
$testPage = get_page_by_path('empfaengererklaerung');
wp_insert_post(['ID' => $testPage ? $testPage->ID : 0, 'post_type' => 'page', 'post_title' => 'Empfängererklärung', 'post_name' => 'empfaengererklaerung', 'post_content' => '[empfaengererklaerung]', 'post_status' => 'publish']);
if (!username_exists('ee-reader')) { $id = wp_create_user('ee-reader', 'Local-EE-Reader-2026!', 'reader@example.test'); (new WP_User($id))->set_role('subscriber'); }
echo "Local WordPress ready.\n";
