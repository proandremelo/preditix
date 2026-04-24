<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;

/** Dados do painel inicial (substitui classes do legado no DashboardApiController). */
final class DashboardReadRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * @return array{
     *   totaisAtivos: array<string, int>,
     *   ordensResumo: array<string, int>,
     *   custos: array<string, float>,
     *   alertasCriticos: array<string, mixed>,
     *   alertasTempo: array<string, mixed>,
     *   estatisticas: array<string, mixed>
     * }
     */
    public function fetchHomePayload(): array
    {
        $cat = new CatalogListRepository();
        $embarcacoes = $cat->listByResourceKey('embarcacoes');
        $implementos = $cat->listByResourceKey('implementos');
        $tanques = $cat->listByResourceKey('tanques');
        $veiculos = $cat->listByResourceKey('veiculos');

        $ordens = $this->db->query('SELECT * FROM ordens_servico');

        $alertas = new AlertasRepository();
        $alertasCriticos = $alertas->getAlertasCriticos();
        $alertasTempo = $alertas->getAlertasTempo();
        $estatisticas = $alertas->getEstatisticas();

        $ordensAbertas = array_filter($ordens, static fn ($o) => ($o['status'] ?? '') === 'aberta');
        $ordensEmAndamento = array_filter($ordens, static fn ($o) => ($o['status'] ?? '') === 'em_andamento');
        $ordensConcluidas = array_filter($ordens, static fn ($o) => ($o['status'] ?? '') === 'concluida');

        $custoTotal = array_sum(array_map(static fn ($o) => (float) ($o['custo_estimado'] ?? 0), $ordens));
        $custoConcluido = array_sum(array_map(static fn ($o) => (float) ($o['custo_estimado'] ?? 0), $ordensConcluidas));

        return [
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
        ];
    }
}
