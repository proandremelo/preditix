<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;
use Exception;

/** CRUD de usuários só com PDO do app desacoplado. */
final class UsuarioRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<string, mixed> */
    public function buscarPorId(int $id): array
    {
        $sql = 'SELECT id, nome, email, nivel_acesso, data_criacao FROM usuarios WHERE id = :id LIMIT 1';
        $result = $this->db->query($sql, [':id' => $id]);
        if ($result === []) {
            throw new Exception('Usuário não encontrado.');
        }

        return $result[0];
    }

    /**
     * @param array{nome: string, email: string, senha: string, nivel_acesso: string} $dados
     */
    public function cadastrar(array $dados): void
    {
        $sql = 'INSERT INTO usuarios (nome, email, senha, nivel_acesso) VALUES (:nome, :email, :senha, :nivel_acesso)';
        $this->db->execute($sql, [
            ':nome' => $dados['nome'],
            ':email' => $dados['email'],
            ':senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
            ':nivel_acesso' => $this->normalizarNivelAcesso($dados['nivel_acesso']),
        ]);
    }

    /**
     * @param array{nome: string, email: string, nivel_acesso: string, senha?: string} $dados
     */
    public function atualizar(int $id, array $dados): void
    {
        $params = [
            ':id' => $id,
            ':nome' => $dados['nome'],
            ':email' => $dados['email'],
            ':nivel_acesso' => $this->normalizarNivelAcesso($dados['nivel_acesso']),
        ];

        if (!empty($dados['senha'])) {
            $sql = 'UPDATE usuarios SET nome = :nome, email = :email, senha = :senha, nivel_acesso = :nivel_acesso WHERE id = :id';
            $params[':senha'] = password_hash($dados['senha'], PASSWORD_DEFAULT);
        } else {
            $sql = 'UPDATE usuarios SET nome = :nome, email = :email, nivel_acesso = :nivel_acesso WHERE id = :id';
        }

        $this->db->execute($sql, $params);
    }

    public function excluir(int $id): void
    {
        $this->db->execute('DELETE FROM usuarios WHERE id = :id', [':id' => $id]);
    }

    /**
     * @param array{nome: string, email: string, senha: string, nivel_acesso: string} $dados
     */
    public function inserirRetornandoId(array $dados): int
    {
        $this->cadastrar($dados);
        $rows = $this->db->query(
            'SELECT id FROM usuarios WHERE email = :email ORDER BY id DESC LIMIT 1',
            [':email' => $dados['email']]
        );

        return (int) ($rows[0]['id'] ?? 0);
    }

    private function normalizarNivelAcesso(string $nivel): string
    {
        if ($nivel === 'admin') {
            return 'gestor';
        }
        if ($nivel === 'usuario') {
            return 'responsavel';
        }

        return $nivel;
    }
}
