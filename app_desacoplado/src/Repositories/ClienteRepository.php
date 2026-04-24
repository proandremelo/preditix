<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;
use Exception;

/** Executores (clientes) — só PDO do app desacoplado. */
final class ClienteRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT * FROM clientes WHERE id = :id';
        $result = $this->db->query($sql, [':id' => $id]);

        return $result[0] ?? null;
    }

    /**
     * @param array{nome: string, cnpj?: string|null, telefone?: string|null, email?: string|null, endereco?: string|null} $dados
     */
    public function cadastrar(array $dados): void
    {
        $sql = 'INSERT INTO clientes (nome, cnpj, telefone, email, endereco) 
                VALUES (:nome, :cnpj, :telefone, :email, :endereco)';
        $this->db->execute($sql, [
            ':nome' => $dados['nome'],
            ':cnpj' => $dados['cnpj'] ?? null,
            ':telefone' => $dados['telefone'] ?? null,
            ':email' => $dados['email'] ?? null,
            ':endereco' => $dados['endereco'] ?? null,
        ]);
    }

    /**
     * @param array{nome: string, cnpj?: string|null, telefone?: string|null, email?: string|null, endereco?: string|null} $dados
     */
    public function atualizar(int $id, array $dados): void
    {
        $cliente = $this->buscarPorId($id);
        if ($cliente === null) {
            throw new Exception('Executor não encontrado.');
        }

        $sql = 'UPDATE clientes SET 
                nome = :nome,
                cnpj = :cnpj,
                telefone = :telefone,
                email = :email,
                endereco = :endereco
                WHERE id = :id';

        $this->db->execute($sql, [
            ':id' => $id,
            ':nome' => $dados['nome'],
            ':cnpj' => $dados['cnpj'] ?? null,
            ':telefone' => $dados['telefone'] ?? null,
            ':email' => $dados['email'] ?? null,
            ':endereco' => $dados['endereco'] ?? null,
        ]);
    }

    public function inserirRetornandoId(array $dados): int
    {
        $this->cadastrar($dados);

        return (int) $this->db->lastInsertId();
    }
}
