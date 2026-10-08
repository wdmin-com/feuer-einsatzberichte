param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+-rc\d+$')]
    [string] $Version
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$repoRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$pluginSlug = 'feuer-einsatzberichte'
$stageRoot = Join-Path $repoRoot ('.build\test-release-' + [guid]::NewGuid().ToString('N'))
$stagePluginRoot = Join-Path $stageRoot $pluginSlug
$releaseRoot = Join-Path $repoRoot ("release\test\$Version")
$zipPath = Join-Path $releaseRoot "$pluginSlug-$Version.zip"
$pluginFile = Join-Path $stagePluginRoot "$pluginSlug.php"

if (Test-Path -LiteralPath $zipPath) {
    throw "Test release already exists: $zipPath"
}

[System.IO.Directory]::CreateDirectory($stagePluginRoot) | Out-Null
[System.IO.Directory]::CreateDirectory($releaseRoot) | Out-Null

$excludedTopLevel = @(
    'release', 'Version', '.git', '.github', '.vscode', '.agents', '.codex',
    '.build', '.cmp', '.cmp2', 'node_modules', 'vendor', 'tests', 'tools',
    'README.md', 'feuer-einsatzberichte.git', '.gitattributes', '.gitignore',
    'update-manifest.json', 'release-notes.json'
)

Get-ChildItem -LiteralPath $repoRoot -Force | Where-Object {
    $excludedTopLevel -notcontains $_.Name
} | ForEach-Object {
    $target = Join-Path $stagePluginRoot $_.Name
    if ($_.PSIsContainer) {
        Copy-Item -LiteralPath $_.FullName -Destination $target -Recurse -Force
    } else {
        Copy-Item -LiteralPath $_.FullName -Destination $target -Force
    }
}

$utf8 = New-Object System.Text.UTF8Encoding($false)
$pluginSource = [System.IO.File]::ReadAllText($pluginFile, $utf8)
$baseVersionMatch = [regex]::Match($pluginSource, '(?m)^\s*\* Version: (\d+\.\d+\.\d+)\s*$')
if (-not $baseVersionMatch.Success) {
    throw 'Stable plugin version could not be read from the plugin header.'
}
$baseVersion = $baseVersionMatch.Groups[1].Value
$candidateCoreVersion = $Version -replace '-rc\d+$', ''
if ([version] $candidateCoreVersion -le [version] $baseVersion) {
    throw "Candidate version must be newer than stable $baseVersion."
}
$headerOld = " * Version: $baseVersion"
$headerNew = " * Version: $Version"
$constantOld = "define('FEU_EINSATZ_VERSION', '$baseVersion');"
$constantNew = "define('FEU_EINSATZ_VERSION', '$Version');"
if (-not $pluginSource.Contains($headerOld) -or -not $pluginSource.Contains($constantOld)) {
    throw 'Plugin header and runtime version do not match.'
}
$pluginSource = $pluginSource.Replace($headerOld, $headerNew).Replace($constantOld, $constantNew)
[System.IO.File]::WriteAllText($pluginFile, $pluginSource, $utf8)

$bomFiles = Get-ChildItem -LiteralPath $stagePluginRoot -Recurse -File -Filter '*.php' | Where-Object {
    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    $bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF
}
if ($bomFiles) {
    throw 'Test release contains PHP files with a UTF-8 BOM.'
}

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -LiteralPath $stageRoot -Recurse -File | Sort-Object FullName | ForEach-Object {
        $entryName = $_.FullName.Substring($stageRoot.Length).TrimStart('\', '/') -replace '\\', '/'
        $entry = $zip.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $entryStream = $entry.Open()
        try {
            $fileStream = [System.IO.File]::OpenRead($_.FullName)
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

$hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
[System.IO.File]::WriteAllText((Join-Path $releaseRoot 'SHA256SUMS.txt'), "$hash  $pluginSlug-$Version.zip`n", $utf8)
Write-Output "test-build-complete:${Version}:${hash}"
Write-Output $zipPath
