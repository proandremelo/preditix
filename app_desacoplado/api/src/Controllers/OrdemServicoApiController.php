<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\OrdemServicoReadRepository;
use AppDesacoplado\Repositories\OrdemServicoWriteRepository;
use InvalidArgumentException;
use Throwable;

final class OrdemServicoApiController
{
    public static function list(): void
    {
        ApiResponse::requireMethod('GET');
        AuthSession::requireLoginJson();

        $filtros = [
            'tipo_equipamento' => isset($_GET['tipo']) && $_GET['tipo'] !== '' ? (string) $_GET['tipo'] : null,
            'ativo_id' => isset($_GET['ativo_id']) && $_GET['ativo_id'] !== '' ? (int) $_GET['ativo_id'] : null,
            'status' => isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : null,
            'prioridade' => isset($_GET['prioridade']) && $_GET['prioridade'] !== '' ? (string) $_GET['prioridade'] : null,
            'tipo_manutencao' => isset($_GET['tipo_manutencao']) && $_GET['tipo_manutencao'] !== '' ? (string) $_GET['tipo_manutencao'] : null,
            'data_abertura' => isset($_GET['data_abertura']) && $_GET['data_abertura'] !== '' ? (string) $_GET['data_abertura'] : null,
        ];

        try {
            $repo = new OrdemServicoReadRepository();
            $items = $repo->listarPainel($filtros);
            $stats = $repo->estatisticasPainel();
            ApiResponse::emit(200, ['items' => $items, 'stats' => $stats]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/ordens_servico: ' . $e->getMessage());
            ApiResponse::emit(500, [
                'error' => 'server_error',
                'message' => 'Erro ao carregar ordens de serviço.',
            ]);
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
            $repo = new OrdemServicoReadRepository();
            $row = $repo->buscarParaVisualizacao($id);
            if ($row === null) {
                ApiResponse::emit(404, [
                    'error' => 'not_found',
                    'message' => 'Ordem de serviço não encontrada.',
                ]);
            }
            $row = ApiResponse::stripOrdemPdf($row);
            $itens = $repo->listarItens($id);
            ApiResponse::emit(200, ['item' => $row, 'itens' => $itens]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/ordem_servico: ' . $e->getMessage());
            ApiResponse::emit(500, [
                'error' => 'server_error',
                'message' => 'Erro ao carregar ordem de serviço.',
            ]);
        }
    }

    public static function create(): void
    {
        ApiResponse::requireMethod('POST');
        AuthSession::requireLoginJson();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        try {
            $repo = new OrdemServicoWriteRepository();
            $uid = (int) ($_SESSION['usuario_id'] ?? 0);
            $id = $repo->inserirNovaOs($data, $uid);
            $read = new OrdemServicoReadRepository();
            $row = $read->buscarParaVisualizacao($id);
            if ($row === null) {
                ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'OS criada mas não foi possível recarregar.']);
            }
            $row = ApiResponse::stripOrdemPdf($row);
            ApiResponse::emit(201, ['ok' => true, 'id' => $id, 'item' => $row]);
        } catch (InvalidArgumentException $e) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/ordem_servico POST: ' . $e->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $e->getMessage()]);
        }
    }

    public static function update(): void
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

        try {
            $repo = new OrdemServicoWriteRepository();
            $uid = (int) ($_SESSION['usuario_id'] ?? 0);
            $repo->atualizarOsAberta($id, $data, $uid);
            $read = new OrdemServicoReadRepository();
            $row = $read->buscarParaVisualizacao($id);
            if ($row === null) {
                ApiResponse::emit(404, ['error' => 'not_found', 'message' => 'Ordem de serviço não encontrada.']);
            }
            $row = ApiResponse::stripOrdemPdf($row);
            $itens = $read->listarItens($id);
            ApiResponse::emit(200, ['ok' => true, 'item' => $row, 'itens' => $itens]);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'não encontrada') ? 404 : 400;
            ApiResponse::emit($code, [
                'error' => $code === 404 ? 'not_found' : 'validation',
                'message' => $msg,
            ]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/ordem_servico PUT: ' . $e->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $e->getMessage()]);
        }
    }
}
