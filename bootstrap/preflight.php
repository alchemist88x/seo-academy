<?php

/**
 * SEO Academy — Zero-Dependency Standalone Pre-flight Bootstrapper
 *
 * This file executes in plain PHP 8.3+ with zero Composer dependencies.
 * It is invoked when vendor/autoload.php is missing, or before Laravel boots,
 * to ensure that missing dependencies or missing .env files produce a clean,
 * helpful browser installation screen instead of fatal PHP exceptions.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);

// 1. Handle auto-creation of .env if missing and vendor exists
function ensureEnvironmentInitialized(string $baseDir): void
{
    $envPath = $baseDir.DIRECTORY_SEPARATOR.'.env';
    $examplePath = $baseDir.DIRECTORY_SEPARATOR.'.env.example';

    if (! file_exists($envPath)) {
        if (! file_exists($examplePath)) {
            renderPreflightError(
                'Missing Configuration Template',
                'The configuration template <code>.env.example</code> was not found in the project root. Please ensure all project files are uploaded.'
            );
            exit;
        }

        $exampleContent = file_get_contents($examplePath);
        if ($exampleContent === false) {
            renderPreflightError(
                'Permission Error',
                'Unable to read <code>.env.example</code>. Please check file read permissions.'
            );
            exit;
        }

        // Generate cryptographically secure 32-byte AES key
        $key = 'base64:'.base64_encode(random_bytes(32));
        $newEnv = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $exampleContent);
        if ($newEnv === null || strpos($newEnv, 'APP_KEY='.$key) === false) {
            $newEnv = "APP_KEY={$key}\n".$exampleContent;
        }

        $written = @file_put_contents($envPath, $newEnv);
        if ($written === false) {
            renderPreflightError(
                'Permission Error',
                'Unable to create <code>.env</code> file. Please ensure the project root directory is writable by PHP.'
            );
            exit;
        }

        // Ensure default SQLite database file is pre-touched if sqlite is configured
        $privateDir = $baseDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private';
        if (! is_dir($privateDir)) {
            @mkdir($privateDir, 0755, true);
        }
        $sqliteFile = $privateDir.DIRECTORY_SEPARATOR.'database.sqlite';
        if (! file_exists($sqliteFile)) {
            @touch($sqliteFile);
        }
    } else {
        // If .env exists but APP_KEY is empty, generate it
        $envContent = file_get_contents($envPath) ?: '';
        if (preg_match('/^APP_KEY=\s*$/m', $envContent)) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $envContent = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY='.$key, $envContent);
            @file_put_contents($envPath, $envContent);
        }
    }
}

// 2. Controlled Composer execution if hosting environment supports it
function handleControlledComposerInstall(string $baseDir): ?string
{
    if (isset($_GET['action']) && $_GET['action'] === 'install-dependencies' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify disabled functions
        $disabled = explode(',', (string) ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);

        if (in_array('proc_open', $disabled, true) || in_array('exec', $disabled, true)) {
            return 'Server restriction: The PHP process execution functions (proc_open, exec) are disabled by your hosting provider. Please upload the project with pre-installed vendor dependencies.';
        }

        // Check if composer binary exists
        $composerBin = findComposerBinary();
        if ($composerBin === null) {
            return 'Composer binary was not detected on this server. Please deploy the prebuilt release package containing pre-installed dependencies.';
        }

        $cmd = escapeshellcmd($composerBin).' install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress 2>&1';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes, $baseDir);
        if (! is_resource($process)) {
            return 'Unable to spawn Composer installation process. Please install dependencies locally and upload the vendor directory.';
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode === 0 && file_exists($baseDir.'/vendor/autoload.php')) {
            header('Location: /');
            exit;
        }

        return 'Composer install failed (Exit code '.$exitCode.'): '.htmlspecialchars((string) ($output ?: $stderr));
    }

    return null;
}

function findComposerBinary(): ?string
{
    $candidates = ['composer', 'composer.phar', '/usr/local/bin/composer', '/usr/bin/composer'];
    foreach ($candidates as $bin) {
        $output = [];
        $exit = 1;
        @exec(escapeshellcmd($bin).' --version 2>&1', $output, $exit);
        if ($exit === 0) {
            return $bin;
        }
    }

    return null;
}

// 3. Render Pre-flight HTML UI
function renderPreflightPage(string $baseDir, ?string $composerMessage = null): void
{
    $phpVersion = PHP_VERSION;
    $phpOk = version_compare($phpVersion, '8.3.0', '>=');
    $composerJsonExists = file_exists($baseDir.'/composer.json');
    $composerBinary = findComposerBinary();

    $requiredExtensions = [
        'bcmath' => 'BCMath Arbitrary Precision Mathematics',
        'ctype' => 'Character type checking',
        'curl' => 'cURL HTTP client',
        'dom' => 'DOM XML manipulation',
        'fileinfo' => 'File Information detection',
        'filter' => 'Data filtering',
        'hash' => 'Hashing engine',
        'mbstring' => 'Multibyte String handling',
        'openssl' => 'OpenSSL cryptography',
        'pcre' => 'Perl-Compatible Regular Expressions',
        'pdo' => 'PHP Data Objects core',
        'session' => 'Session state handler',
        'tokenizer' => 'PHP Tokenizer',
        'xml' => 'XML Parser',
        'zip' => 'ZIP archive handling (Backups & Exports)',
    ];

    $missingExts = [];
    foreach ($requiredExtensions as $ext => $desc) {
        if (! extension_loaded($ext)) {
            $missingExts[$ext] = $desc;
        }
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Academy — Dependency Pre-flight Check</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-elevated: #334155;
            --border: #475569;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
            padding: 2rem 1rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 760px;
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            border: 1px solid rgba(96, 165, 250, 0.3);
        }
        h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        p.subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #fca5a5;
        }
        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid var(--warning);
            color: #fde68a;
        }
        .card {
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-pill {
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-weight: 600;
        }
        .pill-pass { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .pill-fail { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        ul.list { list-style: none; margin-top: 0.5rem; }
        ul.list li {
            padding: 0.4rem 0;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
        }
        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
        }
        .btn-outline:hover { background: var(--surface-elevated); }
        code {
            font-family: monospace;
            background: rgba(0, 0, 0, 0.3);
            padding: 0.15rem 0.3rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .action-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <span class="badge">SEO Academy v5.6.0 &bull; Installation Pre-flight</span>
        <h1>Application Dependencies Required</h1>
        <p class="subtitle">SEO Academy is ready to install, but production PHP dependencies (<code>vendor/</code>) are not yet present on this server.</p>

        <?php if ($composerMessage) { ?>
            <div class="alert alert-error">
                <strong>Dependency Installation Notice:</strong><br>
                <?= $composerMessage; ?>
            </div>
        <?php } ?>

        <div class="card">
            <div class="card-title">
                <span>1. Core PHP Environment</span>
                <span class="status-pill <?= $phpOk ? 'pill-pass' : 'pill-fail'; ?>">
                    PHP <?= htmlspecialchars($phpVersion); ?> (<?= $phpOk ? 'Supported' : 'Requires >= 8.3'; ?>)
                </span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Server runtime: <?= htmlspecialchars(PHP_SAPI); ?> on <?= htmlspecialchars(PHP_OS_FAMILY); ?>
            </p>
        </div>

        <?php if (! empty($missingExts)) { ?>
            <div class="card">
                <div class="card-title">
                    <span>Missing Mandatory PHP Extensions</span>
                    <span class="status-pill pill-fail"><?= count($missingExts); ?> Missing</span>
                </div>
                <ul class="list">
                    <?php foreach ($missingExts as $ext => $desc) { ?>
                        <li>
                            <span><code>ext-<?= htmlspecialchars($ext); ?></code> &mdash; <?= htmlspecialchars($desc); ?></span>
                            <span style="color: var(--danger); font-weight: 600;">Disabled</span>
                        </li>
                    <?php } ?>
                </ul>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                    Please enable the missing PHP extensions in your hosting control panel (cPanel &gt; Select PHP Version &gt; Extensions, or php.ini).
                </p>
            </div>
        <?php } ?>

        <div class="card">
            <div class="card-title">
                <span>2. Deployment Options</span>
                <span class="status-pill pill-fail">vendor/ missing</span>
            </div>
            <div style="font-size: 0.88rem; color: var(--text-muted); space-y: 0.75rem;">
                <p style="margin-bottom: 0.75rem;">
                    <strong>Option A (Recommended for Shared Hosting &amp; cPanel):</strong><br>
                    Deploy the <em>prebuilt release package</em> from the GitHub Releases page, which includes the compiled <code>vendor/</code> directory, or run <code>composer install --no-dev</code> locally before uploading.
                </p>
                <p style="margin-bottom: 0.75rem;">
                    <strong>Option B (Server-Side Installation):</strong><br>
                    If your hosting environment has Composer installed, you can trigger dependency installation directly below.
                </p>
            </div>

            <div class="action-group">
                <?php if ($composerBinary !== null) { ?>
                    <form method="POST" action="?action=install-dependencies" style="display:inline;">
                        <button type="submit" class="btn">Install Dependencies via Server Composer</button>
                    </form>
                <?php } else { ?>
                    <span style="font-size: 0.85rem; color: var(--warning); display: flex; align-items: center;">
                        &bull; Composer CLI was not detected on this host. Please use Option A.
                    </span>
                <?php } ?>
                <a href="/" class="btn btn-outline">Re-check Dependencies</a>
            </div>
        </div>

        <p style="font-size: 0.75rem; color: var(--text-muted); text-align: center; margin-top: 1rem;">
            Once dependencies are in place, the browser installer will guide you through database setup and academy configuration.
        </p>
    </div>
</body>
</html>
    <?php
}

function renderPreflightError(string $title, string $message): void
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SEO Academy &mdash; Installation Error</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 90vh; }
        .box { max-width: 600px; background: #1e293b; border: 1px solid #ef4444; border-radius: 8px; padding: 2rem; }
        h1 { color: #f87171; margin-bottom: 1rem; font-size: 1.5rem; }
        p { color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; }
        code { background: #334155; padding: 0.2rem 0.4rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="box">
        <h1><?= htmlspecialchars($title); ?></h1>
        <p><?= $message; ?></p>
        <p style="margin-top: 1.5rem;"><a href="/" style="color: #60a5fa; text-decoration: none;">&larr; Refresh</a></p>
    </div>
</body>
</html>
    <?php
}

// 4. Main pre-flight execution
$composerMessage = handleControlledComposerInstall($baseDir);

if (! file_exists($baseDir.'/vendor/autoload.php')) {
    renderPreflightPage($baseDir, $composerMessage);
    exit;
}

// If vendor/ exists, ensure .env is auto-created with a secure APP_KEY
ensureEnvironmentInitialized($baseDir);
