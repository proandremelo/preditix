<?php
/**
 * App desacoplado — configuração com segredos fora do repositório em produção.
 *
 * Produção (ex.: HostGator): definir no servidor, sem ficheiro no Git com passwords.
 *   SetEnv APP_DESACOPLADO_REMOTO 1
 *   SetEnv APP_DESACOPLADO_DB_HOST "localhost"
 *   SetEnv APP_DESACOPLADO_DB_USER "seuuser_mysql"
 *   SetEnv APP_DESACOPLADO_DB_PASS "sua_senha"
 *   SetEnv APP_DESACOPLADO_DB_NAME "seuuser_preditix"
 * (Alternativa: chaves genéricas DB_HOST, DB_USER, DB_PASS, DB_NAME.)
 *
 * Se o site abrir com https e estiver atrás de proxy (cabeçalho X-Forwarded-Proto), pode
 * ser necessário: SetEnv APP_DESACOPLADO_TRUST_PROXY_HTTPS 1
 *
 * Desenvolvimento local: sem APP_DESACOPLADO_REMOTO; credenciais opcionais via
 * config.local.php (copie de config.local.php.example) ou variáveis de ambiente.
 */
declare(strict_types=1);

if (defined('APP_DESACOPLADO_CONFIG_LOADED')) {
    return;
}
define('APP_DESACOPLADO_CONFIG_LOADED', true);

/** @return array{db_host?: string, db_user?: string, db_pass?: string, db_name?: string} */
$loadLocalFile = static function (): array {
    $path = __DIR__ . '/config.local.php';
    if (!is_file($path)) {
        return [];
    }
    $c = require $path;

    return is_array($c) ? $c : [];
};

$local = $loadLocalFile();

/**
 * Em muitos Apache/cPanel, SetEnv do .htaccess entra em $_SERVER e NÃO em getenv()
 * (comportamento de PHP/FCGI). Isto alinha com o SetEnv usado no HostGator.
 */
$readVar = static function (string $key): ?string {
    $g = getenv($key);
    if ($g !== false) {
        return (string) $g;
    }
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return (string) $_SERVER[$key];
    }
    $redirect = 'REDIRECT_' . $key;
    if (isset($_SERVER[$redirect])) {
        return (string) $_SERVER[$redirect];
    }

    return null;
};

$envIsRemoto = $readVar('APP_DESACOPLADO_REMOTO');
$isRemoto = $envIsRemoto !== null
    && $envIsRemoto !== ''
    && in_array(
        strtolower(trim($envIsRemoto)),
        ['1', 'true', 'yes', 'on'],
        true
    );

if (!defined('APP_DESACOPLADO_PRODUCTION')) {
    define('APP_DESACOPLADO_PRODUCTION', $isRemoto);
}

/**
 * Ordem: preferência APP_DESACOPLADO_* e depois DB_* (comum em muitos hosts).
 *
 * @param list<string> $keys
 */
$firstEnv = static function (array $keys) use ($readVar): ?string {
    foreach ($keys as $k) {
        $v = $readVar($k);
        if ($v !== null) {
            return $v;
        }
    }

    return null;
};

$failConfig = static function (string $msg): void {
    error_log('app_desacoplado config: ' . $msg);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Erro de configuração: {$msg}\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Serviço indisponível. Entre em contacto com o administrador.';
    exit(1);
};

if ($isRemoto) {
    $dbHost = $firstEnv(['APP_DESACOPLADO_DB_HOST', 'DB_HOST']);
    $dbUser = $firstEnv(['APP_DESACOPLADO_DB_USER', 'DB_USER']);
    $dbName = $firstEnv(['APP_DESACOPLADO_DB_NAME', 'DB_NAME']);
    $dbPassRaw = $firstEnv(['APP_DESACOPLADO_DB_PASS', 'DB_PASS']);
    $dbPass = $dbPassRaw !== null ? $dbPassRaw : '';

    if ($dbHost === null || $dbHost === '' || $dbUser === null || $dbUser === '' || $dbName === null || $dbName === '') {
        $failConfig('Em produção (APP_DESACOPLADO_REMOTO) é obrigatório definir host, utilizador e nome da base (variáveis de ambiente).');
    }
} else {
    $dbHost = (string) ($local['db_host'] ?? $firstEnv(['DB_HOST', 'APP_DESACOPLADO_DB_HOST']) ?? 'localhost');
    $dbUser = (string) ($local['db_user'] ?? $firstEnv(['DB_USER', 'APP_DESACOPLADO_DB_USER']) ?? 'root');
    $dbName = (string) ($local['db_name'] ?? $firstEnv(['DB_NAME', 'APP_DESACOPLADO_DB_NAME']) ?? 'preditix');
    if (array_key_exists('db_pass', $local)) {
        $dbPass = (string) $local['db_pass'];
    } else {
        $p = $firstEnv(['DB_PASS', 'APP_DESACOPLADO_DB_PASS']);
        $dbPass = $p !== null ? $p : 'root';
    }
}

if (!defined('DB_HOST')) {
    define('DB_HOST', $dbHost);
}
if (!defined('DB_USER')) {
    define('DB_USER', $dbUser);
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $dbPass);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $dbName);
}

$rootAbove = dirname(__DIR__);
$upload = $readVar('APP_DESACOPLADO_UPLOAD_DIR') ?? $rootAbove . '/assets/uploads/';
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', $upload !== '' ? $upload : $rootAbove . '/assets/uploads/');
}

/*
 * Só tratar o pedido como HTTPS pelo próprio Apache ($HTTPS / porta 443).
 * X-Forwarded-Proto/SSL às vezes vêm enganados em alojamento partilhado e, com “Secure=1”
 * no cookie de sessão, o browser deixa de guardar o cookie em acesso http://
 * (login responde 200 mas a sessão nunca fica, ou falha a seguir).
 * Para passar a confiar nesses cabeçalhos, defina APP_DESACOPLADO_TRUST_PROXY_HTTPS=1 (ex.: .htaccess).
 */
$https = false;
if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
    $https = true;
} elseif (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
    $https = true;
}
$trustProxy = $readVar('APP_DESACOPLADO_TRUST_PROXY_HTTPS');
$trust = $trustProxy !== null && in_array(
    strtolower(trim($trustProxy)),
    ['1', 'true', 'yes', 'on'],
    true
);
if ($trust) {
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $https = true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && (string) $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        $https = true;
    }
}
if (!defined('APP_DESACOPLADO_HTTPS')) {
    define('APP_DESACOPLADO_HTTPS', $https);
}

if (session_status() === PHP_SESSION_NONE) {
    $sn = $readVar('APP_DESACOPLADO_SESSION_NAME') ?? 'sess_preditix_app_desacoplado';
    session_name($sn !== '' ? $sn : 'sess_preditix_app_desacoplado');

    $params = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
    ];
    if (PHP_VERSION_ID >= 70300) {
        $params['samesite'] = 'Lax';
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(0, '/', $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_start();
}

// Produção: não mostrar detalhes ao browser; ficheiros a aceder por URL não devem listar erros
error_reporting(E_ALL);
$logFile = $readVar('APP_DESACOPLADO_ERROR_LOG');
if ($isRemoto) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if ($logFile !== null && $logFile !== '') {
        ini_set('error_log', $logFile);
    }
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}

unset(
    $loadLocalFile,
    $local,
    $readVar,
    $firstEnv,
    $failConfig,
    $dbHost,
    $dbUser,
    $dbName,
    $dbPass,
    $sn,
    $params,
    $https,
    $logFile,
    $envIsRemoto,
    $trustProxy
);
