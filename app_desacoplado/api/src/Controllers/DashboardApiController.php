<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\DashboardReadRepository;
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

        try {
            $payload = (new DashboardReadRepository())->fetchHomePayload();
            ApiResponse::emit(200, [
                'totaisAtivos' => $payload['totaisAtivos'],
                'ordensResumo' => $payload['ordensResumo'],
                'custos' => $payload['custos'],
                'alertasCriticos' => $payload['alertasCriticos'],
                'alertasTempo' => $payload['alertasTempo'],
                'estatisticas' => $payload['estatisticas'],
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
