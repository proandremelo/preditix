<?php
declare(strict_types=1);

/**
 * Buffer desde o 1.º byte: avisos/erros de includes ou BOM não podem ser enviados
 * antes do JSON (senão o header fica text/html e a UI não lê o utilizador; sessão falha no me.php).
 */
if (ob_get_level() === 0) {
    ob_start();
}

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
