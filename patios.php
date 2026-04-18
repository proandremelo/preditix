<?php
require_once 'includes/auth.php';
require_once 'classes/Patio.php';

Auth::checkAuth();

$patio = new Patio();

include 'includes/header.php';

$lista = $patio->listar();
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Pátios</h1>
        <a href="form_patio.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> Novo Pátio
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-ativos">
                    <thead>
                        <tr>
                            <th class="col-tag">Tag</th>
                            <th class="col-nome">Nome</th>
                            <th class="col-localizacao">Localização</th>
                            <th class="table-cell-number">Área (m²)</th>
                            <th class="table-cell-status">Status</th>
                            <th class="table-cell-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $row): ?>
                            <tr>
                                <td class="table-cell-text"><?php echo htmlspecialchars($row['tag'] ?? '-'); ?></td>
                                <td class="table-cell-text"><?php echo htmlspecialchars($row['nome'] ?? ''); ?></td>
                                <td class="table-cell-text"><?php echo htmlspecialchars($row['localizacao'] ?? '-'); ?></td>
                                <td class="table-cell-number"><?php echo $row['area_m2'] !== null ? number_format((float) $row['area_m2'], 2, ',', '.') : '-'; ?></td>
                                <td class="table-cell-status">
                                    <span class="badge bg-<?php echo $row['status'] === 'ativo' ? 'success' : ($row['status'] === 'inativo' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($row['status'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="table-cell-actions">
                                    <div class="btn-group">
                                        <a href="form_patio.php?id=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="ordens_servico/os.php?tipo=patio&id_equipamento=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-success" title="Nova OS">
                                            <i class="bi bi-clipboard-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
