<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use Throwable;

/**
 * Dados do painel inicial (equivalente ao index.php do sistema legado).
 */
final class DashboardApiController
{
    public static function data(): void
    {
        ApiResponse::requireMethod('GET');
        AuthSession::requireLoginJson();

        require_once PREDITIX_ROOT . '/classes/Embarcacao.php';
        require_once PREDITIX_ROOT . '/classes/Implemento.php';
        require_once PREDITIX_ROOT . '/classes/Tanque.php';
        require_once PREDITIX_ROOT . '/classes/Veiculo.php';
        require_once PREDITIX_ROOT . '/classes/OrdemServico.php';
        require_once PREDITIX_ROOT . '/classes/Alertas.php';

        try {
            $embarcacoes = (new \Embarcacao())->listar();
            $implementos = (new \Implemento())->listar();
            $tanques = (new \Tanque())->listar();
            $veiculos = (new \Veiculo())->listar();
            $ordens = (new \OrdemServico())->listar();

            $alertas = new \Alertas();
            $alertasCriticos = $alertas->getAlertasCriticos();
            $alertasTempo = $alertas->getAlertasTempo();
            $estatisticas = $alertas->getEstatisticas();

            $ordensAbertas = array_filter($ordens, static fn ($o) => ($o['status'] ?? '') === 'aberta');
            $ordensEmAndamento = array_filter($ordens, static fn ($o) => ($o['status'] ?? '') === 'em_andamento');
            $ordensConcluidas = array_filter($ordens, static fn ($o) => ($o['status'] ?? '') === 'concluida');

            $custoTotal = array_sum(array_map(static fn ($o) => (float) ($o['custo_estimado'] ?? 0), $ordens));
            $custoConcluido = array_sum(array_map(static fn ($o) => (float) ($o['custo_estimado'] ?? 0), $ordensConcluidas));

            ApiResponse::emit(200, [
                'totaisAtivos' => [
                    'embarcacoes' => count($embarcacoes),
                    'implementos' => count($implementos),
                    'tanques' => count($tanques),
                    'veiculos' => count($veiculos),
                    'total' => count($embarcacoes) + count($implementos) + count($tanques) + count($veiculos),
                ],
                'ordensResumo' => [
                    'abertas' => count($ordensAbertas),
                    'em_andamento' => count($ordensEmAndamento),
                    'concluidas' => count($ordensConcluidas),
                ],
                'custos' => [
                    'estimado_total' => $custoTotal,
                    'concluido_estimado' => $custoConcluido,
                ],
                'alertasCriticos' => $alertasCriticos,
                'alertasTempo' => $alertasTempo,
                'estatisticas' => $estatisticas,
            ]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/home_dashboard: ' . $e->getMessage());
            ApiResponse::emit(500, [
                'error' => 'server_error',
                'message' => 'Erro ao carregar o painel.',
            ]);
        }
    }
}
