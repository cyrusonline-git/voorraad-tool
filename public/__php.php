<?php
/**
 * Boels — PHP-versiecheck (alleen-lezen), t.b.v. CVE-2026-109618 / CVE-2026-109619.
 * Open: https://<domein>/__php.php?k=<DEPLOY_SECRET van CORE>
 * Leest de sleutel uit de CORE-.env op dezelfde hostingaccount; anders 403.
 */
$secret = null;
$kandidaten = [
    __DIR__ . '/../laravel_app/.env',
    __DIR__ . '/../../laravel_app/.env',
    dirname((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), 2) . '/databasehub.sorai.nl/laravel_app/.env',
    (getenv('HOME') ?: '/home/deb2003831') . '/domains/databasehub.sorai.nl/laravel_app/.env',
];
foreach ($kandidaten as $envFile) {
    if (@is_readable($envFile) && preg_match('/^DEPLOY_SECRET=(.+)$/m', (string) file_get_contents($envFile), $m)) {
        $secret = trim($m[1]);
        break;
    }
}
if ($secret === null || ! hash_equals($secret, (string) ($_GET['k'] ?? ''))) {
    http_response_code(403);
    exit('forbidden');
}
header('Content-Type: text/plain; charset=utf-8');
echo "Domein                : " . ($_SERVER['HTTP_HOST'] ?? '?') . "\n";
echo "PHP-versie            : " . PHP_VERSION . "\n";
echo "SAPI                  : " . PHP_SAPI . "\n";
echo "session.upload_progress.enabled : " . var_export((bool) ini_get('session.upload_progress.enabled'), true) . "\n";
echo "Servertijd            : " . date('Y-m-d H:i:s T') . "\n";
ob_start(); phpinfo(INFO_GENERAL); $info = strip_tags(ob_get_clean());
foreach (['Build Date', 'Loaded Configuration File'] as $k) {
    if (preg_match('/' . preg_quote($k, '/') . '\s*(?:=>)?\s*(.+)/', $info, $m)) {
        echo str_pad($k, 22) . ": " . trim($m[1]) . "\n";
    }
}
if (preg_match('#/opt/alt/php(\d)(\d+)/#', (string) (ini_get('extension_dir') ?: PHP_BINARY), $m)) {
    $bin = "/opt/alt/php{$m[1]}{$m[2]}/usr/bin/lsphp";
    if (@file_exists($bin)) echo "Binary lsphp          : " . date('Y-m-d H:i:s', filemtime($bin)) . " $bin\n";
}
