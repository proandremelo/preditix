<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\EmbarcacaoRepository;
use Throwable;

final class EmbarcacaoApiController
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
            $repo = new EmbarcacaoRepository();
            $row = $repo->buscarPorId($id);
            if ($row === null) {
                ApiResponse::emit(404, ['error' => 'not_found', 'message' => 'Embarcação não encontrada.']);
            }
            ApiResponse::emit(200, ['item' => $row]);
        } catch (Throwable $ex) {
            error_log('app_desacoplado/api/embarcacao GET: ' . $ex->getMessage());
            ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Erro ao carregar embarcação.']);
        }
    }

    /** @return array<string, mixed> */
    private static function buildDadosFromJson(array $data): array
    {
        return [
            'tipo' => isset($data['tipo']) ? trim((string) $data['tipo']) : '',
            'subtipo_balsa' => isset($data['subtipo_balsa']) && $data['subtipo_balsa'] !== ''
                ? trim((string) $data['subtipo_balsa'])
                : null,
            'tag' => isset($data['tag']) ? trim((string) $data['tag']) : '',
            'inscricao' => isset($data['inscricao']) ? trim((string) $data['inscricao']) : '',
            'nome' => isset($data['nome']) ? trim((string) $data['nome']) : '',
            'armador' => isset($data['armador']) ? trim((string) $data['armador']) : '',
            'ano_fabricacao' => $data['ano_fabricacao'] ?? null,
            'capacidade_volumetrica' => $data['capacidade_volumetrica'] ?? null,
            'status' => isset($data['status']) && $data['status'] !== '' ? trim((string) $data['status']) : 'ativo',
        ];
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

        $dados = self::buildDadosFromJson($data);
        if ($dados['nome'] === '' || $dados['tag'] === '' || $dados['inscricao'] === '' || $dados['tipo'] === '') {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Tipo, tag, inscrição e nome são obrigatórios.']);
        }

        try {
            $repo = new EmbarcacaoRepository();
            $repo->cadastrar($dados);
            $id = $repo->idAposInsert($dados['tag'], $dados['inscricao']);
            if ($id < 1) {
                ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Não foi possível obter o id da embarcação criada.']);
            }
            $row = $repo->buscarPorId($id);
            if ($row === null) {
                ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Embarcação criada mas não foi possível recarregar.']);
            }
            ApiResponse::emit(201, ['ok' => true, 'id' => $id, 'item' => $row]);
        } catch (Throwable $ex) {
            error_log('app_desacoplado/api/embarcacao POST: ' . $ex->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $ex->getMessage()]);
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

        $dados = self::buildDadosFromJson($data);
        if ($dados['nome'] === '' || $dados['tag'] === '' || $dados['inscricao'] === '' || $dados['tipo'] === '') {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Tipo, tag, inscrição e nome são obrigatórios.']);
        }

        try {
            $repo = new EmbarcacaoRepository();
            $repo->atualizar($id, $dados);
            $row = $repo->buscarPorId($id);
            if ($row === null) {
                ApiResponse::emit(404, ['error' => 'not_found', 'message' => 'Embarcação não encontrada.']);
            }
            ApiResponse::emit(200, ['ok' => true, 'item' => $row]);
        } catch (Throwable $ex) {
            error_log('app_desacoplado/api/embarcacao PUT: ' . $ex->getMessage());
            $msg = $ex->getMessage();
            $code = str_contains($msg, 'não encontrada') ? 404 : 422;
            ApiResponse::emit($code, [
                'error' => $code === 404 ? 'not_found' : 'business',
                'message' => $msg,
            ]);
        }
    }
}
