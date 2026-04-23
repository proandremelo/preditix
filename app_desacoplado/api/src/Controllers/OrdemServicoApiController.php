<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\OrdemServicoReadRepository;
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

        require_once PREDITIX_ROOT . '/classes/OrdemServico.php';

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
            $os = new \OrdemServico();
            $itens = $os->listarItens($id);
            ApiResponse::emit(200, ['item' => $row, 'itens' => $itens]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/ordem_servico: ' . $e->getMessage());
            ApiResponse::emit(500, [
                'error' => 'server_error',
                'message' => 'Erro ao carregar ordem de serviço.',
            ]);
        }
    }
}
