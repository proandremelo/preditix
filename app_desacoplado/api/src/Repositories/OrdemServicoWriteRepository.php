<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;
use InvalidArgumentException;

/**
 * Criação e atualização de OS para a API (alinhado a ordens_servico/processamento/processa_os.php).
 */
final class OrdemServicoWriteRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    private function obterProximoNumeroOS(): int
    {
        $ano = date('Y');
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_os, '-', -1) AS UNSIGNED)) as ultimo_numero 
                FROM ordens_servico 
                WHERE numero_os LIKE :padrao";
        $result = $this->db->query($sql, [':padrao' => 'OS-' . $ano . '-%']);
        $ultimo = (int) ($result[0]['ultimo_numero'] ?? 0);

        return $ultimo + 1;
    }

    /** @var list<string> */
    private const TIPOS_EQUIPAMENTO = [
        'embarcacao', 'veiculo', 'implemento', 'tanque', 'patio', 'oficina', 'escritorio',
    ];

    private function normalizarPrioridade(string $p): string
    {
        $p = strtolower(trim($p));
        if ($p === 'critica') {
            return 'urgente';
        }

        return $p;
    }

    /**
     * @param array<string, mixed> $in
     */
    public function inserirNovaOs(array $in, int $usuarioAberturaId): int
    {
        $tipo = isset($in['tipo_equipamento']) ? (string) $in['tipo_equipamento'] : '';
        if (!in_array($tipo, self::TIPOS_EQUIPAMENTO, true)) {
            throw new InvalidArgumentException('Tipo de equipamento inválido.');
        }

        $equipId = isset($in['equipamento_id']) ? (int) $in['equipamento_id'] : 0;
        if ($equipId < 1) {
            throw new InvalidArgumentException('Equipamento inválido.');
        }

        $tipoMan = isset($in['tipo_manutencao']) ? (string) $in['tipo_manutencao'] : '';
        if (!in_array($tipoMan, ['preventiva', 'corretiva', 'preditiva'], true)) {
            throw new InvalidArgumentException('Tipo de manutenção inválido.');
        }

        $prior = $this->normalizarPrioridade(isset($in['prioridade']) ? (string) $in['prioridade'] : 'media');
        if (!in_array($prior, ['baixa', 'media', 'alta', 'urgente'], true)) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }

        $gestorId = isset($in['gestor_id']) ? (int) $in['gestor_id'] : 0;
        $respId = isset($in['usuario_responsavel_id']) ? (int) $in['usuario_responsavel_id'] : 0;
        if ($gestorId < 1 || $respId < 1) {
            throw new InvalidArgumentException('Gestor e responsável são obrigatórios.');
        }

        $tipoProp = isset($in['tipo_proprietario']) ? (string) $in['tipo_proprietario'] : 'proprio';
        $clienteId = null;
        if ($tipoProp === 'terceiro') {
            $cid = isset($in['cliente_id']) ? (int) $in['cliente_id'] : 0;
            if ($cid < 1) {
                throw new InvalidArgumentException('Executor (cliente) obrigatório quando o proprietário é terceiro.');
            }
            $clienteId = $cid;
        }

        $dataAbertura = isset($in['data_abertura']) ? trim((string) $in['data_abertura']) : '';
        if ($dataAbertura === '') {
            throw new InvalidArgumentException('Data de abertura é obrigatória.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAbertura)) {
            $dataAbertura .= ' 00:00:00';
        }

        $next = $this->obterProximoNumeroOS();
        $numeroOs = 'OS-' . date('Y') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        $obs = isset($in['observacoes']) ? (string) $in['observacoes'] : '';
        $sistemas = json_encode(isset($in['sistemas_afetados']) && is_array($in['sistemas_afetados']) ? $in['sistemas_afetados'] : []);
        $sintomas = json_encode(isset($in['sintomas_detectados']) && is_array($in['sintomas_detectados']) ? $in['sintomas_detectados'] : []);
        $causas = json_encode(isset($in['causas_defeitos']) && is_array($in['causas_defeitos']) ? $in['causas_defeitos'] : []);
        $tipoInter = json_encode(isset($in['tipo_intervencao']) && is_array($in['tipo_intervencao']) ? $in['tipo_intervencao'] : []);
        $acoes = json_encode(isset($in['acoes_realizadas']) && is_array($in['acoes_realizadas']) ? $in['acoes_realizadas'] : []);

        $dataPrevista = null;
        if (!empty($in['data_prevista'])) {
            $dp = trim((string) $in['data_prevista']);
            $dataPrevista = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dp) ? $dp . ' 00:00:00' : $dp;
        }

        $odometro = null;
        if (in_array($tipo, ['veiculo', 'implemento'], true) && array_key_exists('odometro', $in) && $in['odometro'] !== null && $in['odometro'] !== '') {
            $odometro = (float) $in['odometro'];
        }

        $params = [
            ':numero_os' => $numeroOs,
            ':tipo_equipamento' => $tipo,
            ':equipamento_id' => $equipId,
            ':tipo_manutencao' => $tipoMan,
            ':prioridade' => $prior,
            ':gestor_id' => $gestorId,
            ':usuario_responsavel_id' => $respId,
            ':cliente_id' => $clienteId,
            ':observacoes' => $obs,
            ':sistemas_afetados' => $sistemas,
            ':sintomas_detectados' => $sintomas,
            ':causas_defeitos' => $causas,
            ':tipo_intervencao' => $tipoInter,
            ':acoes_realizadas' => $acoes,
            ':data_abertura' => $dataAbertura,
            ':usuario_abertura_id' => $usuarioAberturaId,
            ':status' => 'aberta',
            ':data_prevista' => $dataPrevista,
            ':odometro' => $odometro,
        ];

        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO ordens_servico (
                        numero_os, tipo_equipamento, equipamento_id, tipo_manutencao, prioridade,
                        gestor_id, usuario_responsavel_id, cliente_id, observacoes, sistemas_afetados, sintomas_detectados,
                        causas_defeitos, tipo_intervencao, acoes_realizadas,
                        data_abertura, usuario_abertura_id, status, data_prevista,
                        odometro, created_at, updated_at
                    ) VALUES (
                        :numero_os, :tipo_equipamento, :equipamento_id, :tipo_manutencao, :prioridade,
                        :gestor_id, :usuario_responsavel_id, :cliente_id, :observacoes, :sistemas_afetados, :sintomas_detectados,
                        :causas_defeitos, :tipo_intervencao, :acoes_realizadas,
                        :data_abertura, :usuario_abertura_id, :status, :data_prevista,
                        :odometro, NOW(), NOW()
                    )";
            $this->db->execute($sql, $params);
            $id = (int) $this->db->lastInsertId();

            $descProblema = isset($in['descricao_problema']) ? trim((string) $in['descricao_problema']) : '';
            if ($descProblema !== '' && $id > 0) {
                try {
                    $this->db->execute(
                        'UPDATE ordens_servico SET descricao_problema = :d WHERE id = :id',
                        [':d' => $descProblema, ':id' => $id]
                    );
                } catch (\Throwable) {
                    // Coluna opcional em instalações antigas
                }
            }

            $this->db->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza OS em status "aberta" (itens / PDF não alterados por esta API).
     *
     * @param array<string, mixed> $in
     */
    public function atualizarOsAberta(int $id, array $in, int $usuarioSessaoId): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID inválido.');
        }

        $rows = $this->db->query('SELECT id, status FROM ordens_servico WHERE id = :id', [':id' => $id]);
        if (!$rows) {
            throw new InvalidArgumentException('Ordem de serviço não encontrada.');
        }
        if (($rows[0]['status'] ?? '') !== 'aberta') {
            throw new InvalidArgumentException('Só é possível editar ordens de serviço abertas.');
        }

        $tipo = isset($in['tipo_equipamento']) ? (string) $in['tipo_equipamento'] : '';
        if (!in_array($tipo, self::TIPOS_EQUIPAMENTO, true)) {
            throw new InvalidArgumentException('Tipo de equipamento inválido.');
        }

        $equipId = isset($in['equipamento_id']) ? (int) $in['equipamento_id'] : 0;
        if ($equipId < 1) {
            throw new InvalidArgumentException('Equipamento inválido.');
        }

        $tipoMan = isset($in['tipo_manutencao']) ? (string) $in['tipo_manutencao'] : '';
        if (!in_array($tipoMan, ['preventiva', 'corretiva', 'preditiva'], true)) {
            throw new InvalidArgumentException('Tipo de manutenção inválido.');
        }

        $prior = $this->normalizarPrioridade(isset($in['prioridade']) ? (string) $in['prioridade'] : 'media');
        if (!in_array($prior, ['baixa', 'media', 'alta', 'urgente'], true)) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }

        $status = isset($in['status']) ? (string) $in['status'] : 'aberta';
        if (!in_array($status, ['aberta', 'em_andamento', 'concluida', 'cancelada'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }

        $gestorId = isset($in['gestor_id']) ? (int) $in['gestor_id'] : 0;
        $respId = isset($in['usuario_responsavel_id']) ? (int) $in['usuario_responsavel_id'] : 0;
        if ($gestorId < 1 || $respId < 1) {
            throw new InvalidArgumentException('Gestor e responsável são obrigatórios.');
        }

        $tipoProp = isset($in['tipo_proprietario']) ? (string) $in['tipo_proprietario'] : 'proprio';
        $clienteId = null;
        if ($tipoProp === 'terceiro') {
            $cid = isset($in['cliente_id']) ? (int) $in['cliente_id'] : 0;
            if ($cid < 1) {
                throw new InvalidArgumentException('Executor (cliente) obrigatório quando o proprietário é terceiro.');
            }
            $clienteId = $cid;
        }

        $obs = isset($in['observacoes']) ? (string) $in['observacoes'] : '';
        $sistemas = json_encode(isset($in['sistemas_afetados']) && is_array($in['sistemas_afetados']) ? $in['sistemas_afetados'] : []);
        $sintomas = json_encode(isset($in['sintomas_detectados']) && is_array($in['sintomas_detectados']) ? $in['sintomas_detectados'] : []);
        $causas = json_encode(isset($in['causas_defeitos']) && is_array($in['causas_defeitos']) ? $in['causas_defeitos'] : []);
        $tipoInter = json_encode(isset($in['tipo_intervencao']) && is_array($in['tipo_intervencao']) ? $in['tipo_intervencao'] : []);
        $acoes = json_encode(isset($in['acoes_realizadas']) && is_array($in['acoes_realizadas']) ? $in['acoes_realizadas'] : []);

        $dataPrevista = null;
        if (!empty($in['data_prevista'])) {
            $dp = trim((string) $in['data_prevista']);
            $dataPrevista = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dp) ? $dp . ' 00:00:00' : $dp;
        }

        $odometro = null;
        if (in_array($tipo, ['veiculo', 'implemento'], true) && array_key_exists('odometro', $in) && $in['odometro'] !== null && $in['odometro'] !== '') {
            $odometro = (float) $in['odometro'];
        }

        $descProblema = isset($in['descricao_problema']) ? trim((string) $in['descricao_problema']) : null;

        $params = [
            ':tipo_equipamento' => $tipo,
            ':equipamento_id' => $equipId,
            ':tipo_manutencao' => $tipoMan,
            ':prioridade' => $prior,
            ':gestor_id' => $gestorId,
            ':usuario_responsavel_id' => $respId,
            ':cliente_id' => $clienteId,
            ':observacoes' => $obs,
            ':sistemas_afetados' => $sistemas,
            ':sintomas_detectados' => $sintomas,
            ':causas_defeitos' => $causas,
            ':tipo_intervencao' => $tipoInter,
            ':acoes_realizadas' => $acoes,
            ':data_prevista' => $dataPrevista,
            ':odometro' => $odometro,
            ':status' => $status,
            ':id' => $id,
        ];

        $extraSet = '';
        if ($status === 'concluida') {
            $params[':data_conclusao'] = date('Y-m-d H:i:s');
            $params[':usuario_conclusao_id'] = $usuarioSessaoId;
            $extraSet = ', data_conclusao = :data_conclusao, usuario_conclusao_id = :usuario_conclusao_id';
        }

        if ($descProblema !== null && $descProblema !== '') {
            $params[':descricao_problema'] = $descProblema;
            $extraSet .= ', descricao_problema = :descricao_problema';
        }

        $sql = "UPDATE ordens_servico SET
                tipo_equipamento = :tipo_equipamento,
                equipamento_id = :equipamento_id,
                tipo_manutencao = :tipo_manutencao,
                prioridade = :prioridade,
                gestor_id = :gestor_id,
                usuario_responsavel_id = :usuario_responsavel_id,
                cliente_id = :cliente_id,
                observacoes = :observacoes,
                sistemas_afetados = :sistemas_afetados,
                sintomas_detectados = :sintomas_detectados,
                causas_defeitos = :causas_defeitos,
                tipo_intervencao = :tipo_intervencao,
                acoes_realizadas = :acoes_realizadas,
                data_prevista = :data_prevista,
                odometro = :odometro,
                status = :status
                {$extraSet}
                , updated_at = NOW()
                WHERE id = :id";

        $this->db->execute($sql, $params);
    }
}
