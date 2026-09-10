<?php
// Portable release build: the exact plugin folder, its update metadata, and checksums.
$root = dirname(__DIR__);
$plugin = $root . '/empfaengererklaerung';
$main = file_get_contents($plugin . '/empfaengererklaerung.php');
$readme = file_get_contents($plugin . '/readme.txt');
function release_header(string $text, string $name): string {
    if (!preg_match('/^[ \t*]*' . preg_quote($name, '/') . ':\s*(.+)$/m', $text, $match)) { throw new RuntimeException('Missing header: ' . $name); }
    return trim($match[1]);
}
$version = release_header($main, 'Version');
if (!preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/', $version)) { throw new RuntimeException('Stable x.y.z version required.'); }
if (release_header($readme, 'Stable tag') !== $version || strpos($main, "define('EE_VERSION', '$version')") === false) { throw new RuntimeException('Plugin version, constant and stable tag must match.'); }
if (isset($argv[1]) && $argv[1] !== 'v' . $version) { throw new RuntimeException('Git tag does not match the plugin version.'); }
$repository = 'https://github.com/marcelDevParadise/empfaengererklaerung';
if (release_header($main, 'Update URI') !== $repository) { throw new RuntimeException('Unexpected update source.'); }
foreach (['lib/dompdf/autoload.inc.php', 'lib/plugin-update-checker/plugin-update-checker.php', 'includes/class-updater.php'] as $required) {
    if (!is_file($plugin . '/' . $required)) { throw new RuntimeException('Missing dependency: ' . $required); }
}
$dist = $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0777, true)) { throw new RuntimeException('Cannot create dist directory.'); }
$name = 'empfaengererklaerung-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($dist . '/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('Cannot create ZIP.'); }
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isLink()) { throw new RuntimeException('Symlinks are not allowed in the plugin package.'); }
    if (!$file->isFile()) { continue; }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('~(^|/)(\.git|\.tools|tests|node_modules|\.env)(/|$)|\.(log|jsonl|sqlite|zip)$~i', $relative)) { throw new RuntimeException('Unexpected package file: ' . $relative); }
    $files[$relative] = $file->getPathname();
}
ksort($files);
foreach ($files as $relative => $path) {
    if (!$zip->addFile($path, $relative)) { throw new RuntimeException('Cannot add ' . $relative); }
}
if (!$zip->close()) { throw new RuntimeException('Cannot finish ZIP.'); }
$zip = new ZipArchive();
if ($zip->open($dist . '/' . $name, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files)) { throw new RuntimeException('ZIP verification failed.'); }
foreach ($files as $relative => $path) {
    if (hash('sha256', $zip->getFromName($relative)) !== hash_file('sha256', $path)) { throw new RuntimeException('ZIP content mismatch: ' . $relative); }
}
$zip->close();
if (!preg_match('/^= ' . preg_quote($version, '/') . ' =\s*\R(.*?)(?=^= |\z)/ms', $readme, $notes)) { throw new RuntimeException('Missing release notes.'); }
$notes = trim($notes[1]);
$metadata = [
    'name' => 'Empfängererklärung', 'slug' => 'empfaengererklaerung', 'version' => $version,
    'homepage' => $repository, 'author' => 'marcelDevParadise',
    'download_url' => $repository . '/releases/download/v' . $version . '/' . $name,
    'requires' => release_header($main, 'Requires at least'), 'requires_php' => release_header($main, 'Requires PHP'),
    'tested' => release_header($readme, 'Tested up to'),
    'sections' => ['description' => 'Empfängererklärungen mit PDF, Unterschrift und geschütztem Archiv.', 'changelog' => '<p>' . nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')) . '</p>'],
];
file_put_contents($dist . '/update.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
file_put_contents($dist . '/release-notes.md', $notes . "\n\nEinmaliger Wechsel auf die Update-Version: Plugin-ZIP in WordPress hochladen und ersetzen. Danach unter Plugins automatische Updates für Empfängererklärung aktivieren.\n");
$hashes = '';
foreach ([$name, 'update.json'] as $asset) { $hashes .= hash_file('sha256', $dist . '/' . $asset) . '  ' . $asset . "\n"; }
file_put_contents($dist . '/SHA256SUMS.txt', $hashes);
echo 'Built ' . $name . ' (' . count($files) . " files) and update.json\n" . $hashes;
