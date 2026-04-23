<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

/**
 * INSERT de executor para a API desacoplada (evita alterar classes/Cliente.php).
 */
final class ClienteExecutorRepository
{
    /**
     * @param array{nome: string, cnpj?: string|null, telefone?: string|null, email?: string|null, endereco?: string|null} $dados
     */
    public function inserirRetornandoId(array $dados): int|false
    {
        $db = new \Database();
        $sql = 'INSERT INTO clientes (nome, cnpj, telefone, email, endereco) 
                VALUES (:nome, :cnpj, :telefone, :email, :endereco)';

        $params = [
            ':nome' => $dados['nome'],
            ':cnpj' => $dados['cnpj'] ?? null,
            ':telefone' => $dados['telefone'] ?? null,
            ':email' => $dados['email'] ?? null,
            ':endereco' => $dados['endereco'] ?? null,
        ];

        if (!$db->execute($sql, $params)) {
            return false;
        }

        return (int) $db->lastInsertId();
    }
}
