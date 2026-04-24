<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;
use Exception;

/** Embarcações — mesmas regras SQL do modelo legado (sem upload de foto na API JSON). */
final class EmbarcacaoRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT * FROM embarcacoes WHERE id = :id';
        $result = $this->db->query($sql, [':id' => $id]);

        return $result[0] ?? null;
    }

    /**
     * @param array<string, mixed> $dados tipo, subtipo_balsa, tag, inscricao, nome, armador, ano_fabricacao, capacidade_volumetrica, status
     */
    public function cadastrar(array $dados): void
    {
        $this->validarSubtipoBalsa($dados);

        $sql = 'INSERT INTO embarcacoes (tipo, subtipo_balsa, tag, inscricao, nome, armador, ano_fabricacao, capacidade_volumetrica, status, foto) 
                VALUES (:tipo, :subtipo_balsa, :tag, :inscricao, :nome, :armador, :ano_fabricacao, :capacidade_volumetrica, :status, :foto)';

        $this->db->execute($sql, [
            ':tipo' => $dados['tipo'],
            ':subtipo_balsa' => $dados['subtipo_balsa'] ?? null,
            ':tag' => $dados['tag'],
            ':inscricao' => $dados['inscricao'],
            ':nome' => $dados['nome'],
            ':armador' => $dados['armador'],
            ':ano_fabricacao' => $dados['ano_fabricacao'],
            ':capacidade_volumetrica' => $dados['capacidade_volumetrica'],
            ':status' => $dados['status'] ?? 'ativo',
            ':foto' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $dados
     */
    public function atualizar(int $id, array $dados): void
    {
        $embarcacao = $this->buscarPorId($id);
        if ($embarcacao === null) {
            throw new Exception('Embarcação não encontrada.');
        }

        $this->validarSubtipoBalsa($dados);

        $sql = 'UPDATE embarcacoes SET 
                tipo = :tipo,
                subtipo_balsa = :subtipo_balsa,
                tag = :tag,
                inscricao = :inscricao,
                nome = :nome,
                armador = :armador,
                ano_fabricacao = :ano_fabricacao,
                capacidade_volumetrica = :capacidade_volumetrica,
                status = :status,
                foto = :foto
                WHERE id = :id';

        $this->db->execute($sql, [
            ':id' => $id,
            ':tipo' => $dados['tipo'],
            ':subtipo_balsa' => $dados['subtipo_balsa'] ?? null,
            ':tag' => $dados['tag'],
            ':inscricao' => $dados['inscricao'],
            ':nome' => $dados['nome'],
            ':armador' => $dados['armador'],
            ':ano_fabricacao' => $dados['ano_fabricacao'],
            ':capacidade_volumetrica' => $dados['capacidade_volumetrica'],
            ':status' => $dados['status'] ?? $embarcacao['status'],
            ':foto' => $embarcacao['foto'] ?? null,
        ]);
    }

    public function idAposInsert(string $tag, string $inscricao): int
    {
        $rows = $this->db->query(
            'SELECT id FROM embarcacoes WHERE tag = :t AND inscricao = :i ORDER BY id DESC LIMIT 1',
            [':t' => $tag, ':i' => $inscricao]
        );

        return (int) ($rows[0]['id'] ?? 0);
    }

    /** @param array<string, mixed> $dados */
    private function validarSubtipoBalsa(array $dados): void
    {
        $tipo = $dados['tipo'] ?? '';
        if (($tipo === 'balsa_simples' || $tipo === 'balsa_motorizada') && empty($dados['subtipo_balsa'])) {
            throw new Exception('Subtipo da balsa é obrigatório.');
        }
    }
}
