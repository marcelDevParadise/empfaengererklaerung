<?php
/**
 * Plugin Name: Empfängererklärung
 * Description: Empfängererklärungen mit digitaler Unterschrift, PDF, E-Mail und geschütztem Archiv.
 * Version: 1.2.1
 * Plugin URI: https://github.com/marcelDevParadise/empfaengererklaerung
 * Update URI: https://github.com/marcelDevParadise/empfaengererklaerung
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Paradise-Shisha
 * License: GPL-2.0-or-later
 * Text Domain: empfaengererklaerung
 */
if (!defined('ABSPATH')) { exit; }
define('EE_VERSION', '1.2.1');
define('EE_DIR', __DIR__ . '/');
define('EE_URL', plugin_dir_url(__FILE__));
require_once EE_DIR . 'includes/class-validation.php';
require_once EE_DIR . 'includes/class-store.php';
require_once EE_DIR . 'includes/class-pdf.php';
require_once EE_DIR . 'includes/class-plugin.php';
require_once EE_DIR . 'includes/class-admin.php';
require_once EE_DIR . 'includes/class-updater.php';
register_activation_hook(__FILE__, ['EE_Store', 'activate']);
add_action('plugins_loaded', static function () {
    EE_Updater::init();
    EE_Plugin::init();
    if (is_admin()) { EE_Admin::init(); }
});
