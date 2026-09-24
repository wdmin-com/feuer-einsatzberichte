Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,
        [Parameter(Mandatory = $true)]
        [string] $Content
    )

    $directory = Split-Path -Parent $Path
    if ($directory -and -not (Test-Path -LiteralPath $directory)) {
        [System.IO.Directory]::CreateDirectory($directory) | Out-Null
    }

    $encoding = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Read-JsonFile {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    $utf8 = New-Object System.Text.UTF8Encoding($false)
    return [System.IO.File]::ReadAllText($Path, $utf8) | ConvertFrom-Json
}

function Write-JsonFile {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,
        [Parameter(Mandatory = $true)]
        $Data
    )

    $json = $Data | ConvertTo-Json -Depth 100
    Write-Utf8NoBom -Path $Path -Content $json
}

function Copy-PluginTree {
    param(
        [Parameter(Mandatory = $true)]
        [string] $SourceRoot,
        [Parameter(Mandatory = $true)]
        [string] $TargetRoot,
        [Parameter(Mandatory = $true)]
        [string[]] $ExcludeTopLevel
    )

    if (-not (Test-Path -LiteralPath $TargetRoot)) {
        [System.IO.Directory]::CreateDirectory($TargetRoot) | Out-Null
    }

    Get-ChildItem -LiteralPath $SourceRoot -Force | Where-Object {
        $ExcludeTopLevel -notcontains $_.Name
    } | ForEach-Object {
        $destination = Join-Path $TargetRoot $_.Name
        if ($_.PSIsContainer) {
            Copy-Item -LiteralPath $_.FullName -Destination $destination -Recurse -Force
        } else {
            Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
        }
    }
}

function New-NormalizedZip {
    param(
        [Parameter(Mandatory = $true)]
        [string] $SourceRoot,
        [Parameter(Mandatory = $true)]
        [string] $ZipPath
    )

    if (Test-Path -LiteralPath $ZipPath) {
        Remove-Item -LiteralPath $ZipPath -Force
    }

    $zip = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        $items = Get-ChildItem -LiteralPath $SourceRoot -Recurse -Force | Sort-Object FullName
        foreach ($item in $items) {
            if ($item.PSIsContainer) {
                continue
            }

            $relative = $item.FullName.Substring($SourceRoot.Length).TrimStart('\', '/')
            if ([string]::IsNullOrWhiteSpace($relative)) {
                continue
            }

            $entryName = ($relative -replace '\\', '/')
            $entry = $zip.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $entryStream = $entry.Open()
            try {
                $fileStream = [System.IO.File]::OpenRead($item.FullName)
                try {
                    $fileStream.CopyTo($entryStream)
                } finally {
                    $fileStream.Dispose()
                }
            } finally {
                $entryStream.Dispose()
            }
        }
    } finally {
        $zip.Dispose()
    }
}

function Get-FileHashHex {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent $scriptRoot
$pluginSlug = 'feuer-einsatzberichte'

$manifestPath = Join-Path $repoRoot 'update-manifest.json'
$releaseNotesPath = Join-Path $repoRoot 'release-notes.json'

$manifest = Read-JsonFile -Path $manifestPath
$releaseNotes = Read-JsonFile -Path $releaseNotesPath
$version = [string] $manifest.version

if ([string]::IsNullOrWhiteSpace($version)) {
    throw 'Version could not be read from update-manifest.json.'
}

$manifest.sections.changelog = @($releaseNotes.releases | ForEach-Object {
    $encodedVersion = [System.Net.WebUtility]::HtmlEncode([string] $_.version)
    $changeItems = @($_.changes | ForEach-Object {
        '<li>' + [System.Net.WebUtility]::HtmlEncode([string] $_) + '</li>'
    }) -join ''

    '<h4>' + $encodedVersion + '</h4><ul>' + $changeItems + '</ul>'
}) -join ''

$releaseRoot = Join-Path $repoRoot 'release'
$versionDir = Join-Path $releaseRoot $version
$latestDir = Join-Path $releaseRoot 'latest'
$snapshotDir = Join-Path (Join-Path $repoRoot 'Version') $version
$buildRoot = Join-Path $repoRoot '.build'
$stageRoot = Join-Path $buildRoot 'release-package'
$stagePluginRoot = Join-Path $stageRoot $pluginSlug

foreach ($path in @($stageRoot, (Join-Path $versionDir $pluginSlug), (Join-Path $latestDir $pluginSlug))) {
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Recurse -Force
    }
}

foreach ($path in @($versionDir, $latestDir, $snapshotDir, $buildRoot)) {
    if (-not (Test-Path -LiteralPath $path)) {
        [System.IO.Directory]::CreateDirectory($path) | Out-Null
    }
}

Copy-PluginTree -SourceRoot $repoRoot -TargetRoot $stagePluginRoot -ExcludeTopLevel @(
    'release',
    'Version',
    '.git',
    '.github',
    '.vscode',
    '.agents',
    '.codex',
    '.build',
    '.cmp',
    '.cmp2',
    'node_modules',
    'tests',
    'tools',
    'README.md',
    'feuer-einsatzberichte.git',
    '.gitattributes',
    '.gitignore',
    'update-manifest.json',
    'release-notes.json'
)

$bomFiles = Get-ChildItem -LiteralPath $stagePluginRoot -Recurse -File -Filter '*.php' | Where-Object {
    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    $bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF
}

if ($bomFiles) {
    $names = ($bomFiles | ForEach-Object { $_.FullName }) -join ', '
    throw "Release contains PHP files with UTF-8 BOM: $names"
}

$versionZipName = "$pluginSlug-$version.zip"
$versionZipPath = Join-Path $versionDir $versionZipName
$versionAliasZipPath = Join-Path $versionDir "$pluginSlug.zip"
$latestZipPath = Join-Path $latestDir "$pluginSlug-latest.zip"
$latestAliasZipPath = Join-Path $latestDir "$pluginSlug.zip"
$snapshotZipPath = Join-Path $snapshotDir $versionZipName

New-NormalizedZip -SourceRoot $stageRoot -ZipPath $versionZipPath
Copy-Item -LiteralPath $versionZipPath -Destination $versionAliasZipPath -Force
Copy-Item -LiteralPath $versionZipPath -Destination $latestZipPath -Force
Copy-Item -LiteralPath $versionZipPath -Destination $latestAliasZipPath -Force
Copy-Item -LiteralPath $versionZipPath -Destination $snapshotZipPath -Force

$demoManifestRoot = Join-Path $buildRoot 'demo-backup'
$demoManifestPath = Join-Path $demoManifestRoot 'manifest.json'
$demoBackupName = "$pluginSlug-demo-hamburg-backup-$version.zip"
$demoBackupPath = Join-Path $versionDir $demoBackupName
$demoBackupLatestPath = Join-Path $latestDir "$pluginSlug-demo-hamburg-backup-latest.zip"
$demoBundleRoot = Join-Path $buildRoot 'demo-package'
$demoBundleContent = Join-Path $demoBundleRoot "$pluginSlug-demo"
$demoBundleName = "$pluginSlug-demo-$version.zip"
$demoBundlePath = Join-Path $versionDir $demoBundleName
$demoBundleLatestPath = Join-Path $latestDir "$pluginSlug-demo-latest.zip"

foreach ($path in @($demoManifestRoot, $demoBundleRoot)) {
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Recurse -Force
    }
    [System.IO.Directory]::CreateDirectory($path) | Out-Null
}

& node (Join-Path $scriptRoot 'build-demo-backup.mjs') $demoManifestPath
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $demoManifestPath)) {
    throw 'Hamburg demo backup manifest could not be generated.'
}

New-NormalizedZip -SourceRoot $demoManifestRoot -ZipPath $demoBackupPath
Copy-Item -LiteralPath $demoBackupPath -Destination $demoBackupLatestPath -Force
Copy-Item -LiteralPath $demoBackupPath -Destination (Join-Path $snapshotDir $demoBackupName) -Force

[System.IO.Directory]::CreateDirectory($demoBundleContent) | Out-Null
Copy-Item -LiteralPath $versionZipPath -Destination (Join-Path $demoBundleContent $versionZipName) -Force
Copy-Item -LiteralPath $demoBackupPath -Destination (Join-Path $demoBundleContent $demoBackupName) -Force
Write-Utf8NoBom -Path (Join-Path $demoBundleContent 'README-DE.txt') -Content @"
FEUER-EINSATZBERICHTE – HAMBURG-DEMO $version

1. Installieren oder aktualisieren Sie das Plugin mit $versionZipName.
2. Öffnen Sie in WordPress: Einsatzberichte > Archive.
3. Laden Sie $demoBackupName hoch.
4. Stellen Sie das hochgeladene Archiv wieder her.

WICHTIG: Die Wiederherstellung ersetzt die vorhandenen Plugin-Daten. Das Plugin erstellt davor automatisch ein Sicherheitsarchiv.

Demoinhalt:
- Feuerwehrhaus: Feuerwehrakademie Hamburg, Bredowstraße 4, 22113 Hamburg
- 25 veröffentlichte, vollständig erfundene Einsatzberichte
- 10 Einsatzberichte aus 2026 und 15 aus 2025
- 25 vollständig erfundene Teilnehmer
- 6 Organisationen, Kategorien, Teilnehmerzuordnungen und Kartenlinien

Alle Personen- und Einsatzangaben sind Demodaten und stellen keine realen Ereignisse dar.
"@
New-NormalizedZip -SourceRoot $demoBundleRoot -ZipPath $demoBundlePath
Copy-Item -LiteralPath $demoBundlePath -Destination $demoBundleLatestPath -Force
Copy-Item -LiteralPath $demoBundlePath -Destination (Join-Path $snapshotDir $demoBundleName) -Force

$packageHash = Get-FileHashHex -Path $versionZipPath
$today = Get-Date -Format 'yyyy-MM-dd'
$baseDownloadUrl = 'https://wdmin.com/plugins/feuer-einsatzberichte/release'

$manifest.last_updated = $today
$manifest.package_hash = $packageHash
$manifest.package_hash_algorithm = 'sha256'
$manifest.download_url = "$baseDownloadUrl/$version/$versionZipName"
Write-JsonFile -Path $manifestPath -Data $manifest

$versionManifest = Read-JsonFile -Path $manifestPath
$latestManifest = Read-JsonFile -Path $manifestPath
$latestManifest.download_url = "$baseDownloadUrl/latest/$pluginSlug-latest.zip"

Write-JsonFile -Path (Join-Path $versionDir 'update-manifest.json') -Data $versionManifest
Write-JsonFile -Path (Join-Path $latestDir 'update-manifest.json') -Data $latestManifest
Write-JsonFile -Path (Join-Path $snapshotDir 'update-manifest.json') -Data $versionManifest

Copy-Item -LiteralPath $releaseNotesPath -Destination (Join-Path $versionDir 'release-notes.json') -Force
Copy-Item -LiteralPath $releaseNotesPath -Destination (Join-Path $latestDir 'release-notes.json') -Force
Copy-Item -LiteralPath $releaseNotesPath -Destination (Join-Path $snapshotDir 'release-notes.json') -Force

$snapshot = [ordered]@{
    version = $version
    built_at = (Get-Date).ToString('s')
    package = $versionZipName
    package_hash = $packageHash
    package_hash_algorithm = 'sha256'
}
Write-JsonFile -Path (Join-Path $snapshotDir 'snapshot.json') -Data $snapshot

Write-Output "build-complete:${version}:${packageHash}"
