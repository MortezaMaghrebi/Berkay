<?php

/** Write a Laravel front controller for an external cPanel public directory. */
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    exit(1);
}
$appPath = realpath($argv[1]);
$publicPath = realpath($argv[2]);
if (! $appPath || ! $publicPath || $appPath === $publicPath || ! is_file($appPath.'/bootstrap/app.php')) {
    fwrite(STDERR, "Invalid application or public path.\n");
    exit(1);
}
$entry = <<<'PHPENTRY'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$appPath = __APP_PATH__;
if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
require $appPath.'/vendor/autoload.php';
$app = require_once $appPath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
PHPENTRY;
$entry = str_replace('__APP_PATH__', var_export($appPath, true), $entry);
if (file_put_contents($publicPath.'/index.php', $entry."\n") === false) {
    fwrite(STDERR, "Could not write public index.php.\n");
    exit(1);
}
