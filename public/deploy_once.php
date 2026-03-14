<?php

/**
 * TEMPORARY DEPLOYMENT UTILITY SCRIPT
 * ===================================
 *
 * This file is INTENTIONALLY temporary and must be DELETED after use.
 * - Use it only when you have no SSH access and need to run a few Artisan commands once.
 * - Do NOT leave it on production servers; remove it as soon as deployment is done.
 * - Do NOT commit this file to production repositories long term (add to .gitignore if needed).
 * - Replace CHANGE_THIS_TO_A_LONG_RANDOM_TOKEN with a long random secret before use.
 *
 * Usage: https://your-domain.com/public/deploy_once.php?token=YOUR_SECRET_TOKEN
 *
 * Allowed commands only: optimize:clear, config:clear, cache:clear, migrate --force
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// 1) Token validation – no Laravel bootstrap until token is valid
// ---------------------------------------------------------------------------

$expectedToken = 'CHANGE_THIS_TO_A_LONG_RANDOM_TOKEN';
$providedToken = $_GET['token'] ?? '';

if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Forbidden.</p></body></html>';
    exit;
}

// ---------------------------------------------------------------------------
// 2) Bootstrap Laravel (works even when config is cached)
// ---------------------------------------------------------------------------

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$outputBuffer = new \Symfony\Component\Console\Output\BufferedOutput();

// ---------------------------------------------------------------------------
// 3) Allowed commands only – no arbitrary execution
// ---------------------------------------------------------------------------

$allowedCommands = [
    ['name' => 'optimize:clear', 'params' => []],
    ['name' => 'config:clear', 'params' => []],
    ['name' => 'cache:clear', 'params' => []],
    ['name' => 'migrate', 'params' => ['--force' => true]],
];

$lines = [];
$lines[] = 'Deploy script started at ' . date('Y-m-d H:i:s T');
$lines[] = '';

foreach ($allowedCommands as $cmd) {
    $commandName = $cmd['name'] . (isset($cmd['params']['--force']) ? ' --force' : '');
    $lines[] = '--- Running: php artisan ' . $commandName . ' ---';
    $outputBuffer->fetch(); // clear previous output
    $exitCode = $kernel->call($cmd['name'], $cmd['params'], $outputBuffer);
    $lines[] = trim($outputBuffer->fetch());
    $lines[] = 'Exit code: ' . $exitCode;
    $lines[] = '';
}

$lines[] = 'Deploy script finished at ' . date('Y-m-d H:i:s T');

// ---------------------------------------------------------------------------
// 4) Output in readable HTML <pre>
// ---------------------------------------------------------------------------

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Deploy output</title></head><body>';
echo '<pre>' . htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8') . '</pre>';
echo '</body></html>';
