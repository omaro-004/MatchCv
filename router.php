<?php

if (php_sapi_name() !== 'cli-server') {
    exit(1);
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$publicDir = __DIR__ . '/public';
$file = $publicDir . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require_once __DIR__ . '/vendor/autoload.php';

foreach ([
    'APP_ENV' => 'dev',
    'APP_DEBUG' => '1',
    'APP_SECRET' => 'dev-6e8f6f2d0d8a4b8c9b7f1a2c3d4e5f6a',
    'DEFAULT_URI' => 'http://localhost',
] as $name => $value) {
    if (!isset($_SERVER[$name])) {
        $_SERVER[$name] = $value;
    }

    if (!isset($_ENV[$name])) {
        $_ENV[$name] = $value;
    }

    if (getenv($name) === false) {
        putenv($name . '=' . $value);
    }
}

if (class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
    (new \Symfony\Component\Dotenv\Dotenv())->usePutenv()->bootEnv(__DIR__ . '/.env');
}

$kernel = new \App\Kernel(
    $_SERVER['APP_ENV'] ?? 'dev',
    ($_SERVER['APP_DEBUG'] ?? '1') !== '0'
);

$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);