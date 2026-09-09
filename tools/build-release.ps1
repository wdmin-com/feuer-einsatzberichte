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

    return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
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

$currentRelease = $releaseNotes.releases | Where-Object { [string] $_.version -eq $version } | Select-Object -First 1
if ($currentRelease) {
    $encodedVersion = [System.Net.WebUtility]::HtmlEncode($version)
    $changeItems = @($currentRelease.changes | ForEach-Object {
        '<li>' + [System.Net.WebUtility]::HtmlEncode([string] $_) + '</li>'
    }) -join ''
    $currentHeading = '<h4>' + $encodedVersion + '</h4>'
    $existingChangelog = [string] $manifest.sections.changelog

    if (-not $existingChangelog.StartsWith($currentHeading)) {
        $manifest.sections.changelog = $currentHeading + '<ul>' + $changeItems + '</ul>' + $existingChangelog
    }
}

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
