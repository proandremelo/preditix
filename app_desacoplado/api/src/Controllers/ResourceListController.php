<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Http\ApiResponse;

/**
 * Listagens GET que delegam às classes do núcleo Preditix (models em classes/).
 */
final class ResourceListController
{
    /** @var array<string, array{0: string, 1: string}> */
    private const MAP = [
        'clientes' => ['Cliente', 'listar'],
        'veiculos' => ['Veiculo', 'listar'],
        'embarcacoes' => ['Embarcacao', 'listar'],
        'tanques' => ['Tanque', 'listar'],
        'implementos' => ['Implemento', 'listar'],
        'patios' => ['Patio', 'listar'],
        'oficinas' => ['Oficina', 'listar'],
        'escritorios' => ['Escritorio', 'listar'],
        'usuarios' => ['Usuario', 'listarDetalhado'],
        'almoxarifado_itens' => ['AlmoxarifadoItem', 'listar'],
    ];

    public static function handle(string $resourceKey): void
    {
        if (!isset(self::MAP[$resourceKey])) {
            ApiResponse::emit(500, ['error' => 'config', 'message' => 'Recurso desconhecido.']);
        }
        [$className, $method] = self::MAP[$resourceKey];
        $path = PREDITIX_ROOT . '/classes/' . $className . '.php';
        if (!is_file($path)) {
            ApiResponse::emit(500, ['error' => 'config', 'message' => 'Classe não encontrada.']);
        }
        require_once $path;

        $fqcn = '\\' . $className;
        $obj = new $fqcn();
        ApiResponse::authorizeListGet(static function () use ($obj, $method) {
            return $obj->$method();
        }, $resourceKey);
    }
}
