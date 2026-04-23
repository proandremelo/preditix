<?php
declare(strict_types=1);

namespace AppDesacoplado\Auth;

/**
 * Sessão para esta API: mesmas chaves de $_SESSION que includes/auth.php.
 */
final class AuthSession
{
    public static function login(string $email, string $senha): bool
    {
        $db = new \Database();

        $sql = 'SELECT id, nome, email, senha, nivel_acesso FROM usuarios WHERE email = :email';
        $result = $db->query($sql, [':email' => $email]);

        if ($result && count($result) === 1) {
            $usuario = $result[0];
            if (password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nivel_acesso'] = self::normalizarNivelAcesso($usuario['nivel_acesso'] ?? 'responsavel');

                return true;
            }
        }

        return false;
    }

    public static function logout(): void
    {
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['usuario_id']);
    }

    public static function getNivelAcesso(): string
    {
        return $_SESSION['usuario_nivel_acesso'] ?? 'responsavel';
    }

    public static function requireLoginJson(): void
    {
        if (self::isLoggedIn()) {
            return;
        }
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'error' => 'unauthorized',
            'message' => 'Faça login para continuar.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param mixed $nivel */
    private static function normalizarNivelAcesso($nivel): string
    {
        if ($nivel === 'admin') {
            return 'gestor';
        }
        if ($nivel === 'usuario') {
            return 'responsavel';
        }

        return (string) $nivel;
    }
}
