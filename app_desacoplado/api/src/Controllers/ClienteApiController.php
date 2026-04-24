<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\ClienteRepository;
use Throwable;

final class ClienteApiController
{
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'GET') {
            self::detail();
        } elseif ($method === 'POST') {
            self::cadastrar();
        } elseif ($method === 'PUT') {
            self::atualizar();
        } else {
            ApiResponse::emit(405, ['error' => 'method_not_allowed', 'message' => 'Método não suportado.']);
        }
    }

    public static function detail(): void
    {
        ApiResponse::requireMethod('GET');
        AuthSession::requireLoginJson();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Parâmetro id inválido.']);
        }

        try {
            $row = (new ClienteRepository())->buscarPorId($id);
            if ($row === null) {
                ApiResponse::emit(404, [
                    'error' => 'not_found',
                    'message' => 'Executor não encontrado.',
                ]);
            }
            ApiResponse::emit(200, ['item' => $row]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/cliente GET: ' . $e->getMessage());
            ApiResponse::emit(500, [
                'error' => 'server_error',
                'message' => 'Erro ao carregar executor.',
            ]);
        }
    }

    public static function cadastrar(): void
    {
        ApiResponse::requireMethod('POST');
        AuthSession::requireLoginJson();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        $nome = isset($data['nome']) ? trim((string) $data['nome']) : '';
        if ($nome === '') {
            ApiResponse::emit(400, [
                'error' => 'validation',
                'message' => 'O nome do executor é obrigatório.',
            ]);
        }

        $dados = [
            'nome' => $nome,
            'cnpj' => self::optionalString($data, 'cnpj'),
            'telefone' => self::optionalString($data, 'telefone'),
            'email' => self::optionalString($data, 'email'),
            'endereco' => self::optionalString($data, 'endereco'),
        ];

        try {
            $repo = new ClienteRepository();
            $id = $repo->inserirRetornandoId($dados);
            if ($id < 1) {
                ApiResponse::emit(500, [
                    'error' => 'server_error',
                    'message' => 'Não foi possível cadastrar o executor.',
                ]);
            }
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(201, [
                'ok' => true,
                'id' => $id,
                'item' => $row,
            ]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/cliente POST: ' . $e->getMessage());
            ApiResponse::emit(422, [
                'error' => 'business',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function atualizar(): void
    {
        ApiResponse::requireMethod('PUT');
        AuthSession::requireLoginJson();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Parâmetro id inválido.']);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        $nome = isset($data['nome']) ? trim((string) $data['nome']) : '';
        if ($nome === '') {
            ApiResponse::emit(400, [
                'error' => 'validation',
                'message' => 'O nome do executor é obrigatório.',
            ]);
        }

        $dados = [
            'nome' => $nome,
            'cnpj' => self::optionalString($data, 'cnpj'),
            'telefone' => self::optionalString($data, 'telefone'),
            'email' => self::optionalString($data, 'email'),
            'endereco' => self::optionalString($data, 'endereco'),
        ];

        try {
            $repo = new ClienteRepository();
            $repo->atualizar($id, $dados);
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(200, ['ok' => true, 'item' => $row]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/cliente PUT: ' . $e->getMessage());
            $msg = $e->getMessage();
            $code = str_contains($msg, 'não encontrado') ? 404 : 422;
            ApiResponse::emit($code, [
                'error' => $code === 404 ? 'not_found' : 'business',
                'message' => $msg,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }
        $v = $data[$key];
        if ($v === null || $v === '') {
            return null;
        }

        return trim((string) $v);
    }
}
