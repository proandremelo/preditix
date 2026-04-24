<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    \AppDesacoplado\Controllers\OrdemServicoApiController::detail();
} elseif ($method === 'POST') {
    \AppDesacoplado\Controllers\OrdemServicoApiController::create();
} elseif ($method === 'PUT') {
    \AppDesacoplado\Controllers\OrdemServicoApiController::update();
} else {
    \AppDesacoplado\Http\ApiResponse::emit(405, [
        'error' => 'method_not_allowed',
        'message' => 'Use GET, POST ou PUT.',
    ]);
}
