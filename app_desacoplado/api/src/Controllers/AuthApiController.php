<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;

final class AuthApiController
{
    public static function login(): void
    {
        ApiResponse::requireMethod('POST');

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $senha = isset($data['senha']) ? (string) $data['senha'] : '';

        if ($email === '' || $senha === '') {
            ApiResponse::emit(400, [
                'error' => 'validation',
                'message' => 'E-mail e senha são obrigatórios.',
            ]);
        }

        if (AuthSession::isLoggedIn()) {
            ApiResponse::emit(200, [
                'ok' => true,
                'user' => self::userArray(),
            ]);
        }

        if (!AuthSession::login($email, $senha)) {
            ApiResponse::emit(401, [
                'error' => 'invalid_credentials',
                'message' => 'Credenciais inválidas.',
            ]);
        }

        ApiResponse::emit(200, [
            'ok' => true,
            'user' => self::userArray(),
        ]);
    }

    public static function logout(): void
    {
        ApiResponse::requireMethod('POST');
        AuthSession::logout();
        ApiResponse::emit(200, ['ok' => true]);
    }

    public static function me(): void
    {
        ApiResponse::requireMethod('GET');
        AuthSession::requireLoginJson();
        ApiResponse::emit(200, ['user' => self::userArray()]);
    }

    /** @return array<string, mixed> */
    private static function userArray(): array
    {
        $nivel = AuthSession::getNivelAcesso();

        return [
            'id' => (int) $_SESSION['usuario_id'],
            'nome' => (string) $_SESSION['usuario_nome'],
            'email' => (string) $_SESSION['usuario_email'],
            'nivel_acesso' => $nivel,
            'is_gestor' => $nivel === 'gestor',
        ];
    }
}
