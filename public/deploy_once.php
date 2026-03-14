<?php

declare(strict_types=1);

/**
 * TEMPORARY DEPLOYMENT UTILITY SCRIPT
 * ===================================
 *
 * This file is TEMPORARY and must be DELETED immediately after use.
 * Use only when you do not have SSH access and need to run a limited set
 * of Artisan commands once.
 */

$expectedToken = 'eshterelyDeploy2026SecureToken123';
$providedToken = $_GET['token'] ?? '';

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Forbidden.</p></body></html>';
    exit;
}

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allowedCommands = [
    ['name' => 'optimize:clear', 'params' => []],
    ['name' => 'config:clear', 'params' => []],
    ['name' => 'cache:clear', 'params' => []],
    ['name' => 'migrate', 'params' => ['--force' => true]],
];

$lines = [];
$lines[] = 'Deploy script started at ' . date('Y-m-d H:i:s T');
$lines[] = '';

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Deploy output</title></head><body><pre>';

foreach ($allowedCommands as $cmd) {
    $commandName = $cmd['name'] . (isset($cmd['params']['--force']) ? ' --force' : '');

    echo htmlspecialchars(">>> Running: php artisan {$commandName}\n", ENT_QUOTES, 'UTF-8');

    try {
        $exitCode = $kernel->call($cmd['name'], $cmd['params']);
        $output = $kernel->output();

        echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        echo htmlspecialchars("\nExit code: {$exitCode}\n", ENT_QUOTES, 'UTF-8');
        echo "----------------------------------------\n";
    } catch (\Throwable $e) {
        echo htmlspecialchars("ERROR: " . $e->getMessage() . "\n", ENT_QUOTES, 'UTF-8');
        echo htmlspecialchars("File: " . $e->getFile() . ':' . $e->getLine() . "\n", ENT_QUOTES, 'UTF-8');
        break;
    }
}

echo htmlspecialchars("\nDone.\n", ENT_QUOTES, 'UTF-8');
echo '</pre></body></html>';
