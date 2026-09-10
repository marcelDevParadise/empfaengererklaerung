$ErrorActionPreference = 'Stop'
$workspace = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$pluginPath = Join-Path $workspace 'empfaengererklaerung'
$distributionPath = Join-Path $workspace 'dist'
if (-not (Test-Path -LiteralPath (Join-Path $pluginPath 'lib\dompdf\autoload.inc.php'))) { throw 'Dompdf fehlt. Vollständige Abhängigkeiten vor dem Packen bereitstellen.' }
$main = Get-Content -LiteralPath (Join-Path $pluginPath 'empfaengererklaerung.php') -Raw -Encoding UTF8
$version = [regex]::Match($main, 'Version:\s*([0-9.]+)').Groups[1].Value
if (-not $version) { throw 'Plugin-Version nicht gefunden.' }
New-Item -ItemType Directory -Force -Path $distributionPath | Out-Null
$destination = Join-Path $distributionPath "empfaengererklaerung-$version.zip"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [IO.File]::Open($destination, [IO.FileMode]::Create)
try {
    $archive = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem -LiteralPath $pluginPath -File -Recurse | ForEach-Object {
            $relative = $_.FullName.Substring($workspace.Length + 1).Replace('\', '/')
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $relative, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally { $archive.Dispose() }
} finally { $stream.Dispose() }
$check = [IO.Compression.ZipFile]::OpenRead($destination)
try {
    if (-not $check.GetEntry('empfaengererklaerung/empfaengererklaerung.php') -or -not $check.GetEntry('empfaengererklaerung/lib/dompdf/autoload.inc.php')) { throw 'ZIP-Struktur ist unvollständig.' }
    if ($check.Entries.FullName | Where-Object { $_ -match '\\|(^|/)(\.tools|tests|node_modules)(/|$)' }) { throw 'Unerwartete Testdateien oder Pfadseparatoren im ZIP.' }
    Write-Output ("{0} Dateien im Plugin-ZIP." -f $check.Entries.Count)
} finally { $check.Dispose() }
Get-Item -LiteralPath $destination | Select-Object FullName, Length
Get-FileHash -LiteralPath $destination -Algorithm SHA256
