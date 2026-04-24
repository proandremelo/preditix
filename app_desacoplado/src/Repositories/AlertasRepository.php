<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;

/** Consultas de alertas (espelho de classes/Alertas.php). */
final class AlertasRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<string, mixed> */
    public function getAlertasCriticos(): array
    {
        $alertas = [];

        $sql = "SELECT 
                    id, numero_os, tipo_equipamento, equipamento_id, 
                    data_abertura, prioridade, status
                FROM ordens_servico 
                WHERE prioridade IN ('urgente', 'alta') 
                AND status IN ('aberta', 'em_andamento')
                ORDER BY prioridade DESC, data_abertura ASC
                LIMIT 10";
        $result = $this->db->query($sql);
        if ($result) {
            $alertas['ordens_urgentes'] = $result;
        }

        $sql = "SELECT 
                    id, numero_os, tipo_equipamento, equipamento_id,
                    data_abertura, prioridade, status,
                    DATEDIFF(CURDATE(), data_abertura) as dias_aberta
                FROM ordens_servico 
                WHERE status IN ('aberta', 'em_andamento')
                AND DATEDIFF(CURDATE(), data_abertura) > 30
                ORDER BY data_abertura ASC
                LIMIT 10";
        $result = $this->db->query($sql);
        if ($result) {
            $alertas['ordens_antigas'] = $result;
        }

        $sql = "SELECT 
                    'embarcacao' as tipo, id, nome as identificacao, status, 
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM embarcacoes 
                WHERE status = 'inativo'
                UNION ALL
                SELECT 
                    'veiculo' as tipo, id, placa as identificacao, status,
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM veiculos 
                WHERE status = 'inativo'
                UNION ALL
                SELECT 
                    'implemento' as tipo, id, placa as identificacao, status,
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM implementos 
                WHERE status = 'inativo'
                UNION ALL
                SELECT 
                    'tanque' as tipo, id, tag as identificacao, status,
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM tanques 
                WHERE status = 'inativo'
                UNION ALL
                SELECT 
                    'patio' as tipo, id, nome as identificacao, status,
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM patios 
                WHERE status = 'inativo'
                UNION ALL
                SELECT 
                    'oficina' as tipo, id, nome as identificacao, status,
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM oficinas 
                WHERE status = 'inativo'
                UNION ALL
                SELECT 
                    'escritorio' as tipo, id, nome as identificacao, status,
                    data_criacao, DATEDIFF(CURDATE(), data_criacao) as dias_criado
                FROM escritorios 
                WHERE status = 'inativo'
                ORDER BY dias_criado DESC
                LIMIT 10";
        $result = $this->db->query($sql);
        if ($result) {
            $alertas['equipamentos_parados'] = $result;
        }

        return $alertas;
    }

    /** @return array<string, mixed> */
    public function getAlertasTempo(): array
    {
        $alertas = [];

        $sql = "SELECT 
                    id, numero_os, tipo_equipamento, equipamento_id,
                    data_abertura, prioridade, status,
                    DATEDIFF(CURDATE(), data_abertura) as dias_aberta
                FROM ordens_servico 
                WHERE status IN ('aberta', 'em_andamento')
                AND DATEDIFF(CURDATE(), data_abertura) > 7
                AND DATEDIFF(CURDATE(), data_abertura) <= 30
                ORDER BY data_abertura ASC
                LIMIT 10";
        $result = $this->db->query($sql);
        if ($result) {
            $alertas['ordens_7_dias'] = $result;
        }

        $sql = "SELECT 
                    id, numero_os, tipo_equipamento, equipamento_id,
                    data_abertura, prioridade, status,
                    DATEDIFF(CURDATE(), data_abertura) as dias_aberta
                FROM ordens_servico 
                WHERE status = 'em_andamento'
                AND DATEDIFF(CURDATE(), data_abertura) > 15
                ORDER BY data_abertura ASC
                LIMIT 10";
        $result = $this->db->query($sql);
        if ($result) {
            $alertas['ordens_andamento_longo'] = $result;
        }

        $sql = "SELECT 
                    id, numero_os, tipo_equipamento, equipamento_id,
                    data_abertura, data_conclusao, prioridade,
                    DATEDIFF(data_conclusao, data_abertura) as dias_duracao
                FROM ordens_servico 
                WHERE status = 'concluida'
                AND DATE(data_conclusao) = CURDATE()
                ORDER BY data_conclusao DESC
                LIMIT 10";
        $result = $this->db->query($sql);
        if ($result) {
            $alertas['ordens_concluidas_hoje'] = $result;
        }

        return $alertas;
    }

    /** @return array<string, mixed> */
    public function getEstatisticas(): array
    {
        $stats = [];

        $sql = "SELECT 
                    status, COUNT(*) as total
                FROM ordens_servico 
                GROUP BY status";
        $result = $this->db->query($sql);
        if ($result) {
            $stats['ordens_por_status'] = $result;
        }

        $sql = "SELECT COUNT(*) as total
                FROM ordens_servico 
                WHERE prioridade IN ('urgente', 'alta') 
                AND status IN ('aberta', 'em_andamento')";
        $result = $this->db->query($sql);
        if ($result) {
            $stats['ordens_urgentes_total'] = $result[0]['total'];
        }

        $sql = "SELECT COUNT(*) as total
                FROM ordens_servico 
                WHERE status IN ('aberta', 'em_andamento')
                AND DATEDIFF(CURDATE(), data_abertura) > 30";
        $result = $this->db->query($sql);
        if ($result) {
            $stats['ordens_antigas_total'] = $result[0]['total'];
        }

        $sql = "SELECT 
                    (SELECT COUNT(*) FROM embarcacoes WHERE status = 'inativo') +
                    (SELECT COUNT(*) FROM veiculos WHERE status = 'inativo') +
                    (SELECT COUNT(*) FROM implementos WHERE status = 'inativo') +
                    (SELECT COUNT(*) FROM tanques WHERE status = 'inativo') +
                    (SELECT COUNT(*) FROM patios WHERE status = 'inativo') +
                    (SELECT COUNT(*) FROM oficinas WHERE status = 'inativo') +
                    (SELECT COUNT(*) FROM escritorios WHERE status = 'inativo') as total";
        $result = $this->db->query($sql);
        if ($result) {
            $stats['equipamentos_inativos_total'] = $result[0]['total'];
        }

        return $stats;
    }
}
