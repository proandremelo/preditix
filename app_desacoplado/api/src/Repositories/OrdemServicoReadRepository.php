<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;

/**
 * Consultas de OS usadas apenas pela API desacoplada (não altera classes/).
 */
final class OrdemServicoReadRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<int, array<string, mixed>> */
    public function listarItens(int $ordemServicoId): array
    {
        $sql = 'SELECT * FROM itens_ordem_servico 
                WHERE ordem_servico_id = :ordem_servico_id
                ORDER BY id';

        return $this->db->query($sql, [':ordem_servico_id' => $ordemServicoId]);
    }

    /**
     * @param array<string, mixed> $filtros tipo_equipamento, ativo_id, status, prioridade, tipo_manutencao, data_abertura
     * @return array<int, array<string, mixed>>
     */
    public function listarPainel(array $filtros = []): array
    {
        $sql = "SELECT os.*, 
               u.nome as nome_usuario_abertura,
               c.nome as nome_cliente,
               CASE 
                   WHEN os.tipo_equipamento = 'embarcacao' THEN e.nome
                   WHEN os.tipo_equipamento = 'veiculo' THEN v.placa
                   WHEN os.tipo_equipamento = 'implemento' THEN i.placa
                   WHEN os.tipo_equipamento = 'tanque' THEN t.tag
                   WHEN os.tipo_equipamento = 'patio' THEN pt.nome
                   WHEN os.tipo_equipamento = 'oficina' THEN ofi.nome
                   WHEN os.tipo_equipamento = 'escritorio' THEN esc.nome
               END as identificacao_equipamento
        FROM ordens_servico os
        LEFT JOIN usuarios u ON u.id = os.usuario_abertura_id
        LEFT JOIN clientes c ON c.id = os.cliente_id
        LEFT JOIN embarcacoes e ON e.id = os.equipamento_id AND os.tipo_equipamento = 'embarcacao'
        LEFT JOIN veiculos v ON v.id = os.equipamento_id AND os.tipo_equipamento = 'veiculo'
        LEFT JOIN implementos i ON i.id = os.equipamento_id AND os.tipo_equipamento = 'implemento'
        LEFT JOIN tanques t ON t.id = os.equipamento_id AND os.tipo_equipamento = 'tanque'
        LEFT JOIN patios pt ON pt.id = os.equipamento_id AND os.tipo_equipamento = 'patio'
        LEFT JOIN oficinas ofi ON ofi.id = os.equipamento_id AND os.tipo_equipamento = 'oficina'
        LEFT JOIN escritorios esc ON esc.id = os.equipamento_id AND os.tipo_equipamento = 'escritorio'
        WHERE 1=1";

        $params = [];

        if (!empty($filtros['tipo_equipamento'])) {
            $sql .= ' AND os.tipo_equipamento = :tipo_equipamento';
            $params[':tipo_equipamento'] = $filtros['tipo_equipamento'];
        }

        if (!empty($filtros['ativo_id'])) {
            $sql .= ' AND os.equipamento_id = :ativo_id';
            $params[':ativo_id'] = (int) $filtros['ativo_id'];
        }

        if (!empty($filtros['status'])) {
            $sql .= ' AND os.status = :status';
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['prioridade'])) {
            $sql .= ' AND os.prioridade = :prioridade';
            $params[':prioridade'] = $filtros['prioridade'];
        }

        if (!empty($filtros['tipo_manutencao'])) {
            $sql .= ' AND os.tipo_manutencao = :tipo_manutencao';
            $params[':tipo_manutencao'] = $filtros['tipo_manutencao'];
        }

        if (!empty($filtros['data_abertura'])) {
            $sql .= ' AND DATE(os.data_abertura) = :data_abertura';
            $params[':data_abertura'] = $filtros['data_abertura'];
        }

        $sql .= ' ORDER BY os.data_abertura DESC, os.numero_os DESC';

        $result = $this->db->query($sql, $params);

        return is_array($result) ? $result : [];
    }

    /** @return array<string, int|string> */
    public function estatisticasPainel(): array
    {
        $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'aberta' THEN 1 ELSE 0 END) as abertas,
        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
        SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
        SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
        SUM(CASE WHEN prioridade = 'alta' OR prioridade = 'urgente' THEN 1 ELSE 0 END) as prioridade_alta
        FROM ordens_servico";

        $rows = $this->db->query($sql);

        return $rows && isset($rows[0]) ? $rows[0] : [
            'total' => 0,
            'abertas' => 0,
            'em_andamento' => 0,
            'concluidas' => 0,
            'canceladas' => 0,
            'prioridade_alta' => 0,
        ];
    }

    /** @return array<string, mixed>|null */
    public function buscarParaVisualizacao(int $id): ?array
    {
        $sql = "SELECT os.*, 
                u.nome as nome_usuario_abertura,
                g.nome as nome_gestor,
                r.nome as nome_responsavel,
                CASE 
                    WHEN os.tipo_equipamento = 'embarcacao' THEN e.nome
                    WHEN os.tipo_equipamento = 'veiculo' THEN v.placa
                    WHEN os.tipo_equipamento = 'implemento' THEN i.placa
                    WHEN os.tipo_equipamento = 'tanque' THEN t.tag
                    WHEN os.tipo_equipamento = 'patio' THEN pt.nome
                    WHEN os.tipo_equipamento = 'oficina' THEN ofi.nome
                    WHEN os.tipo_equipamento = 'escritorio' THEN esc.nome
                END as identificacao_equipamento
            FROM ordens_servico os
            LEFT JOIN usuarios u ON u.id = os.usuario_abertura_id
            LEFT JOIN usuarios g ON g.id = os.gestor_id
            LEFT JOIN usuarios r ON r.id = os.usuario_responsavel_id
            LEFT JOIN embarcacoes e ON e.id = os.equipamento_id AND os.tipo_equipamento = 'embarcacao'
            LEFT JOIN veiculos v ON v.id = os.equipamento_id AND os.tipo_equipamento = 'veiculo'
            LEFT JOIN implementos i ON i.id = os.equipamento_id AND os.tipo_equipamento = 'implemento'
            LEFT JOIN tanques t ON t.id = os.equipamento_id AND os.tipo_equipamento = 'tanque'
            LEFT JOIN patios pt ON pt.id = os.equipamento_id AND os.tipo_equipamento = 'patio'
            LEFT JOIN oficinas ofi ON ofi.id = os.equipamento_id AND os.tipo_equipamento = 'oficina'
            LEFT JOIN escritorios esc ON esc.id = os.equipamento_id AND os.tipo_equipamento = 'escritorio'
            WHERE os.id = :id";
        $result = $this->db->query($sql, [':id' => $id]);

        return $result && isset($result[0]) ? $result[0] : null;
    }
}
