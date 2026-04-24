<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\CatalogListRepository;

/**
 * Listagens GET via SQL no repositório (sem classes/ do legado).
 */
final class ResourceListController
{
    /** @var list<string> */
    private const KEYS = [
        'clientes',
        'veiculos',
        'embarcacoes',
        'tanques',
        'implementos',
        'patios',
        'oficinas',
        'escritorios',
        'usuarios',
        'almoxarifado_itens',
    ];

    public static function handle(string $resourceKey): void
    {
        if (!in_array($resourceKey, self::KEYS, true)) {
            ApiResponse::emit(500, ['error' => 'config', 'message' => 'Recurso desconhecido.']);
        }
        $repo = new CatalogListRepository();
        ApiResponse::authorizeListGet(static function () use ($repo, $resourceKey) {
            return $repo->listByResourceKey($resourceKey);
        }, $resourceKey);
    }
}
