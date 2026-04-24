<?php
declare(strict_types=1);

namespace AppDesacoplado\Controllers;

use AppDesacoplado\Auth\AuthSession;
use AppDesacoplado\Http\ApiResponse;
use AppDesacoplado\Repositories\UsuarioRepository;
use Throwable;

final class UsuarioApiController
{
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'GET') {
            self::detail();
        } elseif ($method === 'POST') {
            self::cadastrar();
        } elseif ($method === 'PUT') {
            self::atualizar();
        } elseif ($method === 'DELETE') {
            self::excluir();
        } else {
            ApiResponse::emit(405, ['error' => 'method_not_allowed', 'message' => 'Método não suportado.']);
        }
    }

    public static function detail(): void
    {
        ApiResponse::requireMethod('GET');
        AuthSession::requireLoginJson();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Parâmetro id inválido.']);
        }

        try {
            $repo = new UsuarioRepository();
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(200, ['item' => $row]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'não encontrado')) {
                ApiResponse::emit(404, ['error' => 'not_found', 'message' => $msg]);
            }
            error_log('app_desacoplado/api/usuario GET: ' . $msg);
            ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Erro ao carregar usuário.']);
        }
    }

    public static function cadastrar(): void
    {
        ApiResponse::requireMethod('POST');
        AuthSession::requireGestorJson();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        $nome = isset($data['nome']) ? trim((string) $data['nome']) : '';
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $senha = isset($data['senha']) ? (string) $data['senha'] : '';
        $nivel = isset($data['nivel_acesso']) ? trim((string) $data['nivel_acesso']) : 'responsavel';

        if ($nome === '') {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'O nome é obrigatório.']);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Informe um e-mail válido.']);
        }
        if ($senha === '') {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'A senha é obrigatória para novos usuários.']);
        }
        if (!in_array($nivel, ['gestor', 'responsavel', 'admin', 'usuario'], true)) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Nível de acesso inválido.']);
        }

        try {
            $repo = new UsuarioRepository();
            $id = $repo->inserirRetornandoId([
                'nome' => $nome,
                'email' => $email,
                'senha' => $senha,
                'nivel_acesso' => $nivel,
            ]);
            if ($id < 1) {
                ApiResponse::emit(500, ['error' => 'server_error', 'message' => 'Não foi possível obter o id do usuário criado.']);
            }
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(201, ['ok' => true, 'id' => $id, 'item' => $row]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/usuario POST: ' . $e->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $e->getMessage()]);
        }
    }

    public static function atualizar(): void
    {
        ApiResponse::requireMethod('PUT');
        AuthSession::requireGestorJson();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Parâmetro id inválido.']);
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        if (!is_array($data)) {
            $data = [];
        }

        $nome = isset($data['nome']) ? trim((string) $data['nome']) : '';
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $senha = isset($data['senha']) ? (string) $data['senha'] : '';
        $nivel = isset($data['nivel_acesso']) ? trim((string) $data['nivel_acesso']) : 'responsavel';

        if ($nome === '') {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'O nome é obrigatório.']);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Informe um e-mail válido.']);
        }
        if (!in_array($nivel, ['gestor', 'responsavel', 'admin', 'usuario'], true)) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Nível de acesso inválido.']);
        }

        $dados = [
            'nome' => $nome,
            'email' => $email,
            'nivel_acesso' => $nivel,
            'senha' => $senha,
        ];

        try {
            $repo = new UsuarioRepository();
            $repo->atualizar($id, $dados);
            $row = $repo->buscarPorId($id);
            ApiResponse::emit(200, ['ok' => true, 'item' => $row]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/usuario PUT: ' . $e->getMessage());
            $msg = $e->getMessage();
            $code = str_contains($msg, 'não encontrado') ? 404 : 422;
            ApiResponse::emit($code, [
                'error' => $code === 404 ? 'not_found' : 'business',
                'message' => $msg,
            ]);
        }
    }

    public static function excluir(): void
    {
        ApiResponse::requireMethod('DELETE');
        AuthSession::requireGestorJson();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id < 1) {
            ApiResponse::emit(400, ['error' => 'validation', 'message' => 'Parâmetro id inválido.']);
        }

        $sessionId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id === $sessionId) {
            ApiResponse::emit(403, ['error' => 'forbidden', 'message' => 'Você não pode excluir seu próprio usuário.']);
        }

        try {
            (new UsuarioRepository())->excluir($id);
            ApiResponse::emit(200, ['ok' => true]);
        } catch (Throwable $e) {
            error_log('app_desacoplado/api/usuario DELETE: ' . $e->getMessage());
            ApiResponse::emit(422, ['error' => 'business', 'message' => $e->getMessage()]);
        }
    }
}
