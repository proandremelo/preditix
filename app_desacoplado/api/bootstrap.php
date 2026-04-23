<?php
declare(strict_types=1);

/**
 * Raiz do projeto Preditix (pasta pai de app_desacoplado/).
 */
define('PREDITIX_ROOT', dirname(__DIR__, 2));

require_once PREDITIX_ROOT . '/includes/config.php';
require_once PREDITIX_ROOT . '/classes/Database.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'AppDesacoplado\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
