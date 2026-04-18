<?php
require_once 'includes/auth.php';
require_once 'classes/Patio.php';

Auth::checkAuth();

$patio = new Patio();
$dados = [];
$acao = 'cadastrar';
$titulo = 'Novo Pátio';
$erro = null;

if (isset($_GET['id'])) {
    $dados = $patio->buscarPorId((int) $_GET['id']);
    if ($dados) {
        $acao = 'atualizar';
        $titulo = 'Editar Pátio';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = isset($_POST['id']) ? 'atualizar' : 'cadastrar';
        $payload = [
            'tag' => $_POST['tag'] ?? '',
            'nome' => $_POST['nome'] ?? '',
            'localizacao' => $_POST['localizacao'] ?? '',
            'area_m2' => $_POST['area_m2'] ?? '',
            'observacoes' => $_POST['observacoes'] ?? '',
            'status' => $_POST['status'] ?? 'ativo',
            'foto' => $_FILES['foto'] ?? null,
        ];
        if ($acao === 'cadastrar') {
            $patio->cadastrar($payload);
        } else {
            $patio->atualizar((int) $_POST['id'], $payload);
        }
        header('Location: patios.php');
        exit;
    } catch (Exception $e) {
        $erro = $e->getMessage();
        $dados = array_merge($dados ?: [], $_POST);
        if (isset($_POST['id'])) {
            $dados['id'] = (int) $_POST['id'];
        }
        $acao = isset($dados['id']) ? 'atualizar' : 'cadastrar';
        $titulo = $acao === 'atualizar' ? 'Editar Pátio' : 'Novo Pátio';
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <h1><?php echo htmlspecialchars($titulo); ?></h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($erro); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?php if ($acao === 'atualizar' && !empty($dados['id'])): ?>
                    <input type="hidden" name="id" value="<?php echo (int) $dados['id']; ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="tag" class="form-label">Tag</label>
                            <input type="text" class="form-control" id="tag" name="tag" value="<?php echo htmlspecialchars($dados['tag'] ?? ''); ?>"
                                   data-tag-alfanumerica
                                   maxlength="50" pattern="[A-Za-z0-9]*"
                                   title="Opcional. Apenas letras (A–Z) e números, sem espaços."
                                   autocomplete="off" spellcheck="false">
                        </div>
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($dados['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="localizacao" class="form-label">Localização</label>
                            <input type="text" class="form-control" id="localizacao" name="localizacao" value="<?php echo htmlspecialchars($dados['localizacao'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="area_m2" class="form-label">Área (m²)</label>
                            <input type="text" class="form-control" id="area_m2" name="area_m2" data-area-m2
                                   inputmode="decimal" autocomplete="off"
                                   value="<?php echo htmlspecialchars($dados['area_m2'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="ativo" <?php echo ($dados['status'] ?? '') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($dados['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                                <option value="manutencao" <?php echo ($dados['status'] ?? '') === 'manutencao' ? 'selected' : ''; ?>>Em Manutenção</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="foto" class="form-label">Foto</label>
                            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($dados['observacoes'] ?? ''); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="patios.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="assets/js/tag_instalacao.js"></script>
<script src="assets/js/area_m2_instalacao.js"></script>
