<?php
declare(strict_types=1);

namespace AppDesacoplado\Infrastructure;

use Exception;
use PDO;
use PDOException;

/**
 * PDO do app desacoplado (espelha classes/Database.php sem acoplar ao legado).
 */
final class Database
{
    private PDO $pdo;

    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('app_desacoplado DB: ' . $e->getMessage());
            throw new Exception('Não foi possível conectar ao banco de dados.');
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($result) ? $result : [];
        } catch (PDOException $e) {
            error_log('app_desacoplado query: ' . $e->getMessage() . "\nSQL: " . $sql);
            throw new Exception('Erro ao buscar dados.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('app_desacoplado execute: ' . $e->getMessage() . "\nSQL: " . $sql);
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode == 23000 && strpos($errorMessage, 'Duplicate entry') !== false) {
                if (strpos($errorMessage, 'tag') !== false) {
                    throw new Exception('Já existe um equipamento cadastrado com esta Tag.');
                }
                if (strpos($errorMessage, 'placa') !== false) {
                    throw new Exception('Já existe um equipamento cadastrado com esta Placa.');
                }
                throw new Exception('Este registro já existe no sistema.');
            }
            if ($errorCode == 22001) {
                throw new Exception('Algum campo foi preenchido com um valor muito grande.');
            }
            if ($errorCode == 22007 || $errorCode == 22008) {
                throw new Exception('Algum campo foi preenchido com um valor inválido.');
            }
            throw new Exception('Não foi possível salvar os dados.');
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
