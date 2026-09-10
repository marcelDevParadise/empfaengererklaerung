<?php
if (!defined('ABSPATH')) { exit; }

final class EE_Updater {
    public const REPOSITORY = 'https://github.com/marcelDevParadise/empfaengererklaerung';
    public const METADATA_URL = self::REPOSITORY . '/releases/latest/download/update.json';
    private static $checker;

    public static function init(): void {
        if (self::$checker) { return; }
        require_once EE_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
        // Use release metadata explicitly, never the GitHub branch/tag fallback.
        self::$checker = new \YahnisElsts\PluginUpdateChecker\v5p7\Plugin\UpdateChecker(
            self::METADATA_URL, EE_DIR . 'empfaengererklaerung.php', 'empfaengererklaerung', 12
        );
        self::$checker->debugMode = false;
        add_filter('puc_request_info_query_args-empfaengererklaerung', '__return_empty_array');
        add_filter('puc_request_info_result-empfaengererklaerung', [self::class, 'validate_release']);
    }

    /** @return \YahnisElsts\PluginUpdateChecker\v5p7\Plugin\UpdateChecker */
    public static function checker() {
        self::init();
        return self::$checker;
    }

    /** Only complete, stable releases with the expected packaged ZIP are eligible. */
    public static function validate_release($info) {
        if (!is_object($info) || !is_string($info->version ?? null) || !preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/', $info->version)) { return null; }
        $expected = self::REPOSITORY . '/releases/download/v' . $info->version . '/empfaengererklaerung-' . $info->version . '.zip';
        if (($info->download_url ?? '') !== $expected) { return null; }
        foreach (['requires', 'requires_php'] as $field) {
            if (!is_string($info->$field ?? null) || !preg_match('/\A[0-9]+\.[0-9]+(?:\.[0-9]+)?\z/', $info->$field)) { return null; }
        }
        // WordPress handles the PHP requirement; also exclude unsupported WP versions.
        global $wp_version;
        if (version_compare($wp_version, $info->requires, '<') || version_compare(PHP_VERSION, $info->requires_php, '<')) { return null; }
        return $info;
    }
}
