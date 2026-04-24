<?php
declare(strict_types=1);

namespace AppDesacoplado\Repositories;

use AppDesacoplado\Infrastructure\Database;

/**
 * Listagens simples (substitui ResourceListController + classes/*.php).
 */
final class CatalogListRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /** @return array<int, array<string, mixed>> */
    public function listByResourceKey(string $resourceKey): array
    {
        $sql = match ($resourceKey) {
            'clientes' => 'SELECT * FROM clientes ORDER BY nome',
            'veiculos' => 'SELECT * FROM veiculos ORDER BY tag',
            'embarcacoes' => 'SELECT * FROM embarcacoes ORDER BY nome',
            'tanques' => 'SELECT * FROM tanques ORDER BY tag',
            'implementos' => 'SELECT * FROM implementos ORDER BY tag',
            'patios' => 'SELECT * FROM patios ORDER BY nome',
            'oficinas' => 'SELECT * FROM oficinas ORDER BY nome',
            'escritorios' => 'SELECT * FROM escritorios ORDER BY nome',
            'usuarios' => 'SELECT id, nome, email, nivel_acesso, data_criacao FROM usuarios ORDER BY nome',
            'almoxarifado_itens' => 'SELECT id, codigo_barras, nome, quantidade, valor_unitario, data_criacao FROM almoxarifado_itens ORDER BY nome',
            default => throw new \InvalidArgumentException('Recurso desconhecido.'),
        };

        return $this->db->query($sql);
    }
}
