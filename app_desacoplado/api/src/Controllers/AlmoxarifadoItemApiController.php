<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\AlmoxarifadoItemRepository;
use Throwable;

final class AlmoxarifadoItemApiController
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
        } elseif ($method === 'DELETE') {
            self::excluir();
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
            $repo = new AlmoxarifadoItemRepository();
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(200, ['item' => $row]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'não encontrado')) {
                ApiResponse::emit(404, ['error' => 'not_found', 'message' => $msg]);
            }
            error_log('app_desacoplado/api/almoxarifado_item GET: ' . $msg);
            ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Erro ao carregar item.']);
        }
    }

    public static function cadastrar(): void
    {
        ApiResponse::requireMethod('POST');
        AuthSession::requireGestorJson();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        $nome = isset($data['nome']) ? trim((string) $data['nome']) : '';
        if ($nome === '') {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'O nome do item é obrigatório.']);
        }

        $quantidade = filter_var($data['quantidade'] ?? null, FILTER_VALIDATE_INT);
        if ($quantidade === false || $quantidade < 0) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Informe uma quantidade inteira maior ou igual a zero.']);
        }

        $valorUnit = filter_var($data['valor_unitario'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($valorUnit === false || $valorUnit < 0) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Informe um valor unitário válido.']);
        }

        $dados = [
            'codigo_barras' => isset($data['codigo_barras']) ? trim((string) $data['codigo_barras']) : '',
            'nome' => $nome,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnit,
        ];

        try {
            $repo = new AlmoxarifadoItemRepository();
            $repo->cadastrar($dados);
            $id = $repo->lastInsertedId();
            if ($id < 1) {
                ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Não foi possível obter o id do item criado.']);
            }
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(201, ['ok' => true, 'id' => $id, 'item' => $row]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/almoxarifado_item POST: ' . $e->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $e->getMessage()]);
        }
    }

    public static function atualizar(): void
    {
        ApiResponse::requireMethod('PUT');
        AuthSession::requireGestorJson();

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
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'O nome do item é obrigatório.']);
        }

        $quantidade = filter_var($data['quantidade'] ?? null, FILTER_VALIDATE_INT);
        if ($quantidade === false || $quantidade < 0) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Informe uma quantidade inteira maior ou igual a zero.']);
        }

        $valorUnit = filter_var($data['valor_unitario'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($valorUnit === false || $valorUnit < 0) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Informe um valor unitário válido.']);
        }

        $dados = [
            'codigo_barras' => isset($data['codigo_barras']) ? trim((string) $data['codigo_barras']) : '',
            'nome' => $nome,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnit,
        ];

        try {
            $repo = new AlmoxarifadoItemRepository();
            $repo->atualizar($id, $dados);
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(200, ['ok' => true, 'item' => $row]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/almoxarifado_item PUT: ' . $e->getMessage());
            $msg = $e->getMessage();
            $code = str_contains($msg, 'não encontrado') ? 404 : 422;
            ApiResponse::emit($code, [
                'error' => $code === 404 ? 'not_found' : 'business',
                'message' => $msg,
            ]);
        }
    }

    public static function excluir(): void
    {
        ApiResponse::requireMethod('DELETE');
        AuthSession::requireGestorJson();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Parâmetro id inválido.']);
        }

        $repo = new AlmoxarifadoItemRepository();
        if ($repo->contarUsoEmOrdensServico($id) > 0) {
            ApiResponse::emit(422, [
                'error' => 'business',
                'message' => 'Item em uso em ordens de serviço.',
            ]);
        }

        try {
            $repo->excluir($id);
            ApiResponse::emit(200, ['ok' => true]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/almoxarifado_item DELETE: ' . $e->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $e->getMessage()]);
        }
    }
}
