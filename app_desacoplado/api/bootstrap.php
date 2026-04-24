<?php
declare(strict_types=1);

/**
 * Raiz da aplicação desacoplada (pasta app_desacoplado/).
 */
define('APP_DESACOPLADO_ROOT', dirname(__DIR__));

require_once APP_DESACOPLADO_ROOT . '/config.php';
require_once APP_DESACOPLADO_ROOT . '/src/Infrastructure/Database.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'AppDesacoplado\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    foreach (
        [
            APP_DESACOPLADO_ROOT . '/src/',
            APP_DESACOPLADO_ROOT . '/api/src/',
        ] as $base
    ) {
        $path = $base . $relative;
        if (is_file($path)) {
            require_once $path;

            return;
        }
    }
});
