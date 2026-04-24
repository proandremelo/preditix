<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;
use Exception;

/** Itens de almoxarifado — espelha regras de classes/AlmoxarifadoItem sem acoplar. */
final class AlmoxarifadoItemRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<string, mixed> */
    public function buscarPorId(int $id): array
    {
        $sql = 'SELECT id, codigo_barras, nome, quantidade, valor_unitario
                FROM almoxarifado_itens
                WHERE id = :id';
        $result = $this->db->query($sql, [':id' => $id]);
        if ($result === []) {
            throw new Exception('Item não encontrado.');
        }

        return $result[0];
    }

    /** @param array{codigo_barras?: string, nome: string, quantidade: int|float, valor_unitario: float} $dados */
    public function cadastrar(array $dados): void
    {
        $codigo = $this->normalizarCodigoBarras($dados['codigo_barras'] ?? '');
        $sql = 'INSERT INTO almoxarifado_itens (
                    codigo_barras, nome, quantidade, valor_unitario
                ) VALUES (
                    :codigo_barras, :nome, :quantidade, :valor_unitario
                )';
        $this->db->execute($sql, [
            ':codigo_barras' => $codigo,
            ':nome' => $dados['nome'],
            ':quantidade' => $dados['quantidade'],
            ':valor_unitario' => $dados['valor_unitario'],
        ]);
    }

    /** @param array{codigo_barras?: string, nome: string, quantidade: int|float, valor_unitario: float} $dados */
    public function atualizar(int $id, array $dados): void
    {
        $codigo = $this->normalizarCodigoBarras($dados['codigo_barras'] ?? '');
        $sql = 'UPDATE almoxarifado_itens SET
                    codigo_barras = :codigo_barras,
                    nome = :nome,
                    quantidade = :quantidade,
                    valor_unitario = :valor_unitario
                WHERE id = :id';
        $this->db->execute($sql, [
            ':id' => $id,
            ':codigo_barras' => $codigo,
            ':nome' => $dados['nome'],
            ':quantidade' => $dados['quantidade'],
            ':valor_unitario' => $dados['valor_unitario'],
        ]);
    }

    public function excluir(int $id): void
    {
        $this->db->execute('DELETE FROM almoxarifado_itens WHERE id = :id', [':id' => $id]);
    }

    public function contarUsoEmOrdensServico(int $id): int
    {
        $uso = $this->db->query(
            'SELECT COUNT(*) as total FROM itens_ordem_servico WHERE almoxarifado_item_id = :id',
            [':id' => $id]
        );

        return (int) ($uso[0]['total'] ?? 0);
    }

    public function lastInsertedId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    private function normalizarCodigoBarras(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new Exception('O código de barras é obrigatório.');
        }
        if (strlen($codigo) > 100) {
            throw new Exception('O código de barras deve ter no máximo 100 caracteres.');
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $codigo)) {
            throw new Exception('O código de barras só pode conter letras sem acento e números.');
        }

        return $codigo;
    }
}
