$ErrorActionPreference = "Stop"

$sourceDir = "C:\laragon\www\seo-academy"
$distDir = "$sourceDir\dist"
$zipPath = "$distDir\seo-academy-v5.6.0-candidate.zip"
$manifestPath = "$distDir\release-manifest-v5.6.0.json"
$php = "C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe"

Write-Host "Step 1: Publishing Livewire static assets..."
& $php artisan vendor:publish --tag=livewire:assets --ansi --force

Write-Host "Step 2: Building frontend assets with Vite..."
npm run build

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$tempStaging = "$distDir\staging-v5.6.0"
if (Test-Path $tempStaging) {
    Remove-Item $tempStaging -Recurse -Force
}
New-Item -ItemType Directory -Path $tempStaging -Force | Out-Null

Write-Host "Step 3: Staging directories..."
$dirsToCopy = @("app", "bootstrap", "config", "database", "docs", "public", "resources", "routes", "storage", "vendor")
foreach ($d in $dirsToCopy) {
    $src = "$sourceDir\$d"
    $dst = "$tempStaging\$d"
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $dst -Recurse -Force
    }
}

Write-Host "Step 4: Staging root files..."
$filesToCopy = @(".env.example", "artisan", "composer.json", "composer.lock", "package.json", "README.md")
foreach ($f in $filesToCopy) {
    $src = "$sourceDir\$f"
    $dst = "$tempStaging\$f"
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $dst -Force
    }
}

Write-Host "Step 5: Verifying critical frontend assets exist in staging..."
if (-not (Test-Path "$tempStaging\public\build\manifest.json")) {
    throw "CRITICAL ERROR: public\build\manifest.json is missing in staging!"
}
if (-not (Test-Path "$tempStaging\public\vendor\livewire\manifest.json")) {
    throw "CRITICAL ERROR: public\vendor\livewire\manifest.json is missing in staging!"
}
if (-not (Test-Path "$tempStaging\public\vendor\livewire\livewire.min.js")) {
    throw "CRITICAL ERROR: public\vendor\livewire\livewire.min.js is missing in staging!"
}

Write-Host "Step 6: Cleaning sensitive and transient files..."
Get-ChildItem -Path "$tempStaging\database" -Filter "*.sqlite*" -Recurse | Remove-Item -Force -ErrorAction SilentlyContinue

$storageSubdirs = @(
    "storage\app\private",
    "storage\app\private_media",
    "storage\app\public",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\testing",
    "storage\framework\views",
    "storage\logs"
)
foreach ($sub in $storageSubdirs) {
    $target = "$tempStaging\$sub"
    if (Test-Path $target) {
        Get-ChildItem -Path $target -Recurse | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$bootCache = "$tempStaging\bootstrap\cache"
if (Test-Path $bootCache) {
    Get-ChildItem -Path $bootCache | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force -ErrorAction SilentlyContinue
}

Write-Host "Step 7: Compressing into release zip..."
[System.Reflection.Assembly]::LoadWithPartialName('System.IO.Compression.FileSystem') | Out-Null
[System.IO.Compression.ZipFile]::CreateFromDirectory($tempStaging, $zipPath, [System.IO.Compression.CompressionLevel]::Optimal, $false)

$fileInfo = Get-Item $zipPath
$hash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLower()
$sizeBytes = $fileInfo.Length
$sizeMb = [math]::Round($sizeBytes / 1MB, 1)

Write-Host "Package created: $zipPath ($sizeBytes bytes, $sizeMb MB, SHA256: $hash)"

# Count migrations
$migrationCount = (Get-ChildItem -Path "$sourceDir\database\migrations" -Filter "*.php").Count

# Generate manifest
$manifest = [ordered]@{
    product_name = "SEO Academy"
    version = "5.6.0-candidate"
    release_channel = "commercial-self-hosted"
    build_timestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ss+00:00")
    supported_php_versions = @("8.3", "8.4")
    supported_databases = [ordered]@{
        SQLite = "3.35+"
        MySQL = "8.4+ InnoDB"
        MariaDB = "10.11+ InnoDB"
    }
    required_extensions = @(
        "bcmath", "ctype", "curl", "dom", "fileinfo", "filter", "gd", "hash",
        "intl", "json", "mbstring", "openssl", "pcre", "pdo", "pdo_sqlite",
        "pdo_mysql", "session", "tokenizer", "xml", "zip"
    )
    package = [ordered]@{
        filename = "seo-academy-v5.6.0-candidate.zip"
        size_bytes = $sizeBytes
        size_mb = $sizeMb
        sha256 = $hash
    }
    frontend_build = [ordered]@{
        engine = "Vite 8.3"
        node_runtime_required_after_install = $false
        livewire_assets_published = $true
    }
    migrations_count = $migrationCount
    license = "Proprietary Commercial Self-Hosted License"
    license_notices_path = "docs/licenses/third-party-notices.md"
}

$manifest | ConvertTo-Json -Depth 5 | Set-Content -Path $manifestPath -Encoding UTF8
Write-Host "Manifest written to $manifestPath"

# Cleanup staging
Remove-Item $tempStaging -Recurse -Force
Write-Host "Packaging complete."
