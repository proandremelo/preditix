<?php
/**
 * Configuração do app desacoplado — não depende de includes/ nem da raiz Preditix.
 *
 * Variáveis de ambiente (opcional): APP_DESACOPLADO_DB_HOST, DB_USER, DB_PASS, DB_NAME,
 * APP_DESACOPLADO_SESSION_NAME, APP_DESACOPLADO_UPLOAD_DIR
 */
declare(strict_types=1);

if (defined('APP_DESACOPLADO_CONFIG_LOADED')) {
    return;
}
define('APP_DESACOPLADO_CONFIG_LOADED', true);

$env = static function (string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }

    return $default;
};

if (!defined('DB_HOST')) {
    define('DB_HOST', $env('APP_DESACOPLADO_DB_HOST', $env('DB_HOST', 'localhost')) ?? 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', $env('APP_DESACOPLADO_DB_USER', $env('DB_USER', 'root')) ?? 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $env('APP_DESACOPLADO_DB_PASS', $env('DB_PASS', 'root')) ?? 'root');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $env('APP_DESACOPLADO_DB_NAME', $env('DB_NAME', 'preditix')) ?? 'preditix');
}

$rootAbove = dirname(__DIR__);
if (!defined('UPLOAD_DIR')) {
    $upload = $env('APP_DESACOPLADO_UPLOAD_DIR', $rootAbove . '/assets/uploads/');
    define('UPLOAD_DIR', $upload !== null && $upload !== '' ? $upload : $rootAbove . '/assets/uploads/');
}

if (session_status() === PHP_SESSION_NONE) {
    $sn = $env('APP_DESACOPLADO_SESSION_NAME', 'sess_preditix_app_desacoplado');
    session_name($sn !== null && $sn !== '' ? $sn : 'sess_preditix_app_desacoplado');
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
