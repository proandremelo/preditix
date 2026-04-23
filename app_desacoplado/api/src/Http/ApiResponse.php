<?php
declare(strict_types=1);

namespace AppDesacoplado\Http;

use AppDesacoplado\Auth\AuthSession;
use Throwable;

/**
 * Respostas JSON e padrões comuns (método HTTP, lista autenticada).
 */
final class ApiResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function emit(int $status, array $payload): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function requireMethod(string $expected): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === $expected) {
            return;
        }
        self::emit(405, [
            'error' => 'method_not_allowed',
            'message' => 'Use ' . $expected . '.',
        ]);
    }

    /**
     * GET autenticado que retorna { items: [...] }.
     *
     * @param callable(): array<int, array<string, mixed>> $loader
     */
    public static function authorizeListGet(callable $loader, string $contextoLog): void
    {
        self::requireMethod('GET');
        AuthSession::requireLoginJson();
        try {
            $items = $loader();
            self::emit(200, ['items' => is_array($items) ? $items : []]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api [' . $contextoLog . ']: ' . $e->getMessage());
            self::emit(500, [
                'error' => 'server_error',
                'message' => 'Erro ao carregar dados.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function stripOrdemPdf(array $row): array
    {
        $hasPdf = !empty($row['pdf']);
        unset($row['pdf']);
        if ($hasPdf) {
            $row['has_pdf'] = true;
        }

        return $row;
    }
}
