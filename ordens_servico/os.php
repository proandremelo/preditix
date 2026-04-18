<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once 'includes/config_campos.php';
require_once '../classes/Database.php';
require_once '../classes/Cliente.php';

// Inicializa a conexão com o banco de dados
$db = new Database();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$usuario_gestor = Auth::isGestor();

// Determina se é edição ou criação
$modo_edicao = isset($_GET['id']);
$id_os = $modo_edicao ? (int)$_GET['id'] : null;

// Obtém o tipo de equipamento da URL ou da OS existente
$tipo_equipamento = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$id_equipamento = isset($_GET['id_equipamento']) ? (int)$_GET['id_equipamento'] : null;

// Se for edição, busca os dados da OS
if ($modo_edicao) {
    $sql = "SELECT os.*, 
                   CASE 
                       WHEN os.tipo_equipamento = 'embarcacao' THEN e.nome
                       WHEN os.tipo_equipamento = 'veiculo' THEN v.placa
                       WHEN os.tipo_equipamento = 'implemento' THEN i.placa
                       WHEN os.tipo_equipamento = 'tanque' THEN t.tag
                       WHEN os.tipo_equipamento = 'patio' THEN pt.nome
                       WHEN os.tipo_equipamento = 'oficina' THEN ofi.nome
                       WHEN os.tipo_equipamento = 'escritorio' THEN esc.nome
                   END as identificacao_equipamento
            FROM ordens_servico os
            LEFT JOIN embarcacoes e ON e.id = os.equipamento_id AND os.tipo_equipamento = 'embarcacao'
            LEFT JOIN veiculos v ON v.id = os.equipamento_id AND os.tipo_equipamento = 'veiculo'
            LEFT JOIN implementos i ON i.id = os.equipamento_id AND os.tipo_equipamento = 'implemento'
            LEFT JOIN tanques t ON t.id = os.equipamento_id AND os.tipo_equipamento = 'tanque'
            LEFT JOIN patios pt ON pt.id = os.equipamento_id AND os.tipo_equipamento = 'patio'
            LEFT JOIN oficinas ofi ON ofi.id = os.equipamento_id AND os.tipo_equipamento = 'oficina'
            LEFT JOIN escritorios esc ON esc.id = os.equipamento_id AND os.tipo_equipamento = 'escritorio'
            WHERE os.id = :id";

    $result = $db->query($sql, [':id' => $id_os]);
    if (empty($result)) {
        $_SESSION['erro'] = "Ordem de serviço não encontrada.";
        header('Location: ../ordens_servico.php');
        exit;
    }

    $os = $result[0];
    
    // Define o tipo e ID do equipamento a partir da OS
    $tipo_equipamento = $os['tipo_equipamento'];
    $id_equipamento = $os['equipamento_id'];
}

// Nova OS: precisa de tipo e equipamento na URL (evita formulário incompleto e perda de contexto no POST)
if (!$modo_edicao) {
    if ($tipo_equipamento === null || $tipo_equipamento === '' || !$id_equipamento) {
        $_SESSION['erro'] = 'Para criar uma ordem de serviço, use o botão Nova OS na lista do ativo ou do equipamento.';
        header('Location: ordens_servico.php');
        exit;
    }
}

// Valida o tipo de equipamento
$tipos_permitidos = ['embarcacao', 'veiculo', 'implemento', 'tanque', 'patio', 'oficina', 'escritorio'];
if (!in_array($tipo_equipamento, $tipos_permitidos, true)) {
    $_SESSION['erro'] = 'Tipo de equipamento inválido.';
    header('Location: ordens_servico.php');
    exit;
}

// Carrega a configuração específica do tipo de equipamento
if (!isset($config_campos[$tipo_equipamento])) {
    $_SESSION['erro'] = 'Configuração não encontrada para este tipo de equipamento.';
    header('Location: ordens_servico.php');
    exit;
}

$config = $config_campos[$tipo_equipamento];

$labels_tipo_os = [
    'embarcacao' => 'Embarcação',
    'veiculo' => 'Veículo',
    'implemento' => 'Implemento',
    'tanque' => 'Tanque',
    'patio' => 'Pátio',
    'oficina' => 'Oficina',
    'escritorio' => 'Escritório',
];
$titulo_tipo = $labels_tipo_os[$tipo_equipamento] ?? ucfirst($tipo_equipamento);

// Carrega os dados do equipamento
$equipamento = null;
switch ($tipo_equipamento) {
    case 'embarcacao':
        require_once '../classes/Embarcacao.php';
        $equipamento = new Embarcacao();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
    case 'veiculo':
        require_once '../classes/Veiculo.php';
        $equipamento = new Veiculo();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
    case 'implemento':
        require_once '../classes/Implemento.php';
        $equipamento = new Implemento();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
    case 'tanque':
        require_once '../classes/Tanque.php';
        $equipamento = new Tanque();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
    case 'patio':
        require_once '../classes/Patio.php';
        $equipamento = new Patio();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
    case 'oficina':
        require_once '../classes/Oficina.php';
        $equipamento = new Oficina();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
    case 'escritorio':
        require_once '../classes/Escritorio.php';
        $equipamento = new Escritorio();
        $dados_equipamento = $equipamento->buscarPorId($id_equipamento);
        break;
}

if (!$dados_equipamento) {
    $_SESSION['erro'] = 'Equipamento não encontrado.';
    header('Location: ordens_servico.php');
    exit;
}

// Busca a lista de usuários para os campos de gestor e responsável
$sql_gestores = "SELECT id, nome FROM usuarios WHERE nivel_acesso IN ('gestor', 'admin') ORDER BY nome";
$gestores = $db->query($sql_gestores);

$sql_responsaveis = "SELECT id, nome FROM usuarios WHERE nivel_acesso IN ('responsavel', 'usuario') ORDER BY nome";
$responsaveis = $db->query($sql_responsaveis);

// Busca a lista de clientes
$cliente = new Cliente();
$clientes = $cliente->buscarAtivos();

// Busca itens do almoxarifado
$almoxarifado_itens = $db->query(
    "SELECT id, codigo_barras, nome, quantidade, valor_unitario
     FROM almoxarifado_itens
     ORDER BY nome"
);
$estoque_por_item = [];
foreach ($almoxarifado_itens as $item) {
    $estoque_por_item[$item['id']] = (float)$item['quantidade'];
}

// Inclui o cabeçalho
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php echo $modo_edicao ? 'Editar' : 'Nova'; ?> Ordem de Serviço - <?php echo htmlspecialchars($titulo_tipo); ?>
                        <?php if ($modo_edicao): ?>
                            #<?php echo htmlspecialchars($os['numero_os']); ?>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['erro'])): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
                    <?php endif; ?>
                    <div id="alertValidacaoOS" class="alert alert-danger mb-3 d-none" role="alert" aria-live="polite"></div>

                    <?php
                    // Log para debug
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        error_log("Formulário enviado em os.php - Dados POST: " . print_r($_POST, true));
                    }
                    ?>

                    <form method="POST" action="processamento/processa_os.php" id="formOS" class="needs-validation" novalidate enctype="multipart/form-data">
                        <?php if ($modo_edicao): ?>
                            <input type="hidden" name="id" value="<?php echo $id_os; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="tipo_equipamento" value="<?php echo $tipo_equipamento; ?>">
                        <input type="hidden" name="equipamento_id" value="<?php echo $id_equipamento; ?>">

                        <!-- Dados do Equipamento -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Dados do Equipamento</h4>
                                <div class="table-responsive">
                                    <?php if (in_array($tipo_equipamento, ['patio', 'oficina', 'escritorio'], true)): ?>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Tag</th>
                                            <td><?php echo htmlspecialchars($dados_equipamento['tag'] ?? '-'); ?></td>
                                            <th>Nome</th>
                                            <td><?php echo htmlspecialchars($dados_equipamento['nome'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Localização</th>
                                            <td><?php echo htmlspecialchars($dados_equipamento['localizacao'] ?? '-'); ?></td>
                                            <th>Área (m²)</th>
                                            <td><?php echo isset($dados_equipamento['area_m2']) && $dados_equipamento['area_m2'] !== null && $dados_equipamento['area_m2'] !== ''
                                                ? number_format((float) $dados_equipamento['area_m2'], 2, ',', '.')
                                                : '-'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td><?php echo htmlspecialchars(ucfirst($dados_equipamento['status'] ?? '')); ?></td>
                                            <th>Observações</th>
                                            <td><?php echo htmlspecialchars($dados_equipamento['observacoes'] ?? '-'); ?></td>
                                        </tr>
                                    </table>
                                    <?php else: ?>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Identificação</th>
                                            <td><?php 
                                                switch ($tipo_equipamento) {
                                                    case 'embarcacao':
                                                        echo htmlspecialchars($dados_equipamento['nome'] ?? '');
                                                        break;
                                                    case 'veiculo':
                                                        echo htmlspecialchars($dados_equipamento['placa'] ?? '');
                                                        break;
                                                    case 'implemento':
                                                        echo htmlspecialchars($dados_equipamento['placa'] ?? '');
                                                        break;
                                                    case 'tanque':
                                                        echo htmlspecialchars($dados_equipamento['tag'] ?? '');
                                                        break;
                                                }
                                            ?></td>
                                            <th>Modelo</th>
                                            <td><?php 
                                                switch ($tipo_equipamento) {
                                                    case 'embarcacao':
                                                        echo htmlspecialchars($dados_equipamento['tipo'] ?? '');
                                                        break;
                                                    case 'veiculo':
                                                    case 'implemento':
                                                    case 'tanque':
                                                        echo htmlspecialchars($dados_equipamento['modelo'] ?? '');
                                                        break;
                                                }
                                            ?></td>
                                        </tr>
                                        <tr>
                                            <th>Fabricante</th>
                                            <td><?php 
                                                switch ($tipo_equipamento) {
                                                    case 'embarcacao':
                                                        echo htmlspecialchars($dados_equipamento['armador'] ?? '');
                                                        break;
                                                    case 'veiculo':
                                                    case 'implemento':
                                                        echo htmlspecialchars($dados_equipamento['fabricante'] ?? '');
                                                        break;
                                                    case 'tanque':
                                                        echo htmlspecialchars($dados_equipamento['fabricante_responsavel'] ?? '');
                                                        break;
                                                }
                                            ?></td>
                                            <th>Ano</th>
                                            <td><?php echo htmlspecialchars($dados_equipamento['ano_fabricacao'] ?? ''); ?></td>
                                        </tr>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Dados da OS -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Dados da Ordem de Serviço</h4>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="tipo_proprietario">Tipo de Proprietário</label>
                                            <select name="tipo_proprietario" id="tipo_proprietario" class="form-control" required>
                                                <option value="proprio" <?php echo (!$modo_edicao || empty($os['cliente_id'])) ? 'selected' : ''; ?>>Próprio</option>
                                                <option value="terceiro" <?php echo ($modo_edicao && !empty($os['cliente_id'])) ? 'selected' : ''; ?>>Terceiro</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o tipo de proprietário.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" id="cliente_terceiro_field" style="display: <?php echo ($modo_edicao && !empty($os['cliente_id'])) ? 'block' : 'none'; ?>;">
                                            <label for="cliente_id">Executor Terceiro</label>
                                            <select name="cliente_id" id="cliente_id" class="form-control">
                                                <option value="">Selecione o executor...</option>
                                                <?php foreach ($clientes as $c): ?>
                                                    <option value="<?php echo $c['id']; ?>" <?php echo ($modo_edicao && $os['cliente_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">
                                                <a href="../clientes.php" target="_blank">Gerenciar executores</a>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="tipo_manutencao">Tipo de Manutenção</label>
                                            <select name="tipo_manutencao" id="tipo_manutencao" class="form-control" required>
                                                <option value="">Selecione...</option>
                                                <option value="preventiva" <?php echo ($modo_edicao && $os['tipo_manutencao'] === 'preventiva') ? 'selected' : ''; ?>>Preventiva</option>
                                                <option value="corretiva" <?php echo ($modo_edicao && $os['tipo_manutencao'] === 'corretiva') ? 'selected' : ''; ?>>Corretiva</option>
                                                <option value="preditiva" <?php echo ($modo_edicao && $os['tipo_manutencao'] === 'preditiva') ? 'selected' : ''; ?>>Preditiva</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o tipo de manutenção.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="prioridade">Prioridade</label>
                                            <select name="prioridade" id="prioridade" class="form-control" required>
                                                <option value="">Selecione...</option>
                                                <option value="baixa" <?php echo ($modo_edicao && $os['prioridade'] === 'baixa') ? 'selected' : ''; ?>>Baixa</option>
                                                <option value="media" <?php echo ($modo_edicao && $os['prioridade'] === 'media') ? 'selected' : ''; ?>>Média</option>
                                                <option value="alta" <?php echo ($modo_edicao && $os['prioridade'] === 'alta') ? 'selected' : ''; ?>>Alta</option>
                                                <option value="critica" <?php echo ($modo_edicao && $os['prioridade'] === 'critica') ? 'selected' : ''; ?>>Crítica</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione a prioridade.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="data_abertura">Data de Abertura</label>
                                            <?php if ($modo_edicao): ?>
                                                <input type="datetime-local" name="data_abertura" id="data_abertura" class="form-control" 
                                                       value="<?php echo date('Y-m-d\TH:i', strtotime($os['data_abertura'])); ?>" 
                                                       <?php echo $usuario_gestor ? '' : 'readonly'; ?>>
                                            <?php else: ?>
                                                <input type="datetime-local" name="data_abertura" id="data_abertura" class="form-control" 
                                                       value="<?php echo date('Y-m-d\TH:i'); ?>" 
                                                       required>
                                            <?php endif; ?>
                                            <div class="invalid-feedback">
                                                Por favor, selecione a data e hora de abertura.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="data_prevista">Estimativa de conclusão</label>
                                            <input type="date" name="data_prevista" id="data_prevista" class="form-control" 
                                                   value="<?php echo !empty($os['data_prevista']) ? date('Y-m-d', strtotime($os['data_prevista'])) : ''; ?>"
                                                   placeholder="Opcional">
                                        </div>
                                    </div>
                                </div>
                                <?php if ($modo_edicao): ?>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="status">Status da OS</label>
                                            <select name="status" id="status" class="form-control" required>
                                                <option value="aberta" <?php echo ($os['status'] === 'aberta') ? 'selected' : ''; ?>>Aberta</option>
                                                <option value="em_andamento" <?php echo ($os['status'] === 'em_andamento') ? 'selected' : ''; ?>>Em Andamento</option>
                                                <option value="concluida" <?php echo ($os['status'] === 'concluida') ? 'selected' : ''; ?>>Concluída</option>
                                                <option value="cancelada" <?php echo ($os['status'] === 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o status da OS.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="gestor_id">Gestor *</label>
                                            <select name="gestor_id" id="gestor_id" class="form-control" required>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($gestores as $gestor): ?>
                                                    <option value="<?php echo $gestor['id']; ?>"
                                                        <?php echo ($modo_edicao && $os['gestor_id'] == $gestor['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($gestor['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o gestor.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="usuario_responsavel_id">Responsável *</label>
                                            <select name="usuario_responsavel_id" id="usuario_responsavel_id" class="form-control" required>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($responsaveis as $responsavel): ?>
                                                    <option value="<?php echo $responsavel['id']; ?>"
                                                        <?php echo ($modo_edicao && $os['usuario_responsavel_id'] == $responsavel['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($responsavel['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o responsável.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="gestor_id">Gestor *</label>
                                            <select name="gestor_id" id="gestor_id" class="form-control" required>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($gestores as $gestor): ?>
                                                    <option value="<?php echo $gestor['id']; ?>">
                                                        <?php echo htmlspecialchars($gestor['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o gestor.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="usuario_responsavel_id">Responsável *</label>
                                            <select name="usuario_responsavel_id" id="usuario_responsavel_id" class="form-control" required>
                                                <option value="">Selecione...</option>
                                                <?php foreach ($responsaveis as $responsavel): ?>
                                                    <option value="<?php echo $responsavel['id']; ?>">
                                                        <?php echo htmlspecialchars($responsavel['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione o responsável.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Sistemas Afetados -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Sistemas Afetados</h4>
                                <div class="row">
                                    <?php 
                                    $sistemas_afetados = $modo_edicao ? json_decode($os['sistemas_afetados'] ?? '[]', true) : [];
                                    foreach ($config['sistemas'] as $chave => $nome): 
                                    ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="sistemas_afetados[]" 
                                                       value="<?php echo $chave; ?>" 
                                                       class="form-check-input" id="sistema_<?php echo $chave; ?>"
                                                       <?php echo in_array($chave, $sistemas_afetados) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="sistema_<?php echo $chave; ?>">
                                                    <?php echo $nome; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sintomas Detectados -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Sintomas Detectados</h4>
                                <div class="row">
                                    <?php 
                                    $sintomas_detectados = $modo_edicao ? json_decode($os['sintomas_detectados'] ?? '[]', true) : [];
                                    foreach ($config['sintomas'] as $chave => $nome): 
                                    ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="sintomas_detectados[]" 
                                                       value="<?php echo $chave; ?>" 
                                                       class="form-check-input" id="sintoma_<?php echo $chave; ?>"
                                                       <?php echo in_array($chave, $sintomas_detectados) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="sintoma_<?php echo $chave; ?>">
                                                    <?php echo $nome; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Causas dos Defeitos -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Causas dos Defeitos</h4>
                                <div class="row">
                                    <?php 
                                    $causas_defeitos = $modo_edicao ? json_decode($os['causas_defeitos'] ?? '[]', true) : [];
                                    foreach ($config['causas'] as $chave => $nome): 
                                    ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="causas_defeitos[]" 
                                                       value="<?php echo $chave; ?>" 
                                                       class="form-check-input" id="causa_<?php echo $chave; ?>"
                                                       <?php echo in_array($chave, $causas_defeitos) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="causa_<?php echo $chave; ?>">
                                                    <?php echo $nome; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Intervenções Realizadas -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Intervenções Realizadas</h4>
                                <div class="row">
                                    <?php 
                                    $intervencoes_realizadas = $modo_edicao ? json_decode($os['tipo_intervencao'] ?? '[]', true) : [];
                                    foreach ($config['intervencoes'] as $chave => $nome): 
                                    ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="intervencoes_realizadas[]" 
                                                       value="<?php echo $chave; ?>" 
                                                       class="form-check-input" id="intervencao_<?php echo $chave; ?>"
                                                       <?php echo in_array($chave, $intervencoes_realizadas) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="intervencao_<?php echo $chave; ?>">
                                                    <?php echo $nome; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Ações Realizadas -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Ações Realizadas</h4>
                                <div class="row">
                                    <?php 
                                    $acoes_realizadas = $modo_edicao ? json_decode($os['acoes_realizadas'] ?? '[]', true) : [];
                                    foreach ($config['acoes'] as $chave => $nome): 
                                    ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="acoes_realizadas[]" 
                                                       value="<?php echo $chave; ?>" 
                                                       class="form-check-input" id="acao_<?php echo $chave; ?>"
                                                       <?php echo in_array($chave, $acoes_realizadas) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="acao_<?php echo $chave; ?>">
                                                    <?php echo $nome; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Observações -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="observacoes">Observações</label>
                                    <textarea name="observacoes" id="observacoes" class="form-control" rows="4"><?php echo $modo_edicao ? htmlspecialchars($os['observacoes'] ?? '') : ''; ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Itens da OS -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4>Itens da Ordem de Serviço</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="tabelaItens">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Detalhe (Outro)</th>
                                                <th>Estoque</th>
                                                <th>Quantidade</th>
                                                <th>Valor Unitário</th>
                                                <th>Total</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($modo_edicao): 
                                                $sql_itens = "SELECT io.*, ai.quantidade as estoque_atual, ai.codigo_barras, ai.nome
                                                              FROM itens_ordem_servico io
                                                              LEFT JOIN almoxarifado_itens ai ON ai.id = io.almoxarifado_item_id
                                                              WHERE io.ordem_servico_id = :id_os";
                                                $itens = $db->query($sql_itens, [':id_os' => $id_os]);
                                                foreach ($itens as $item):
                                                    $item_id = (int)($item['almoxarifado_item_id'] ?? 0);
                                                    $is_outro = $item_id === 0;
                                                    $estoque_real = $is_outro ? 0.0 : (float)($estoque_por_item[$item_id] ?? 0);
                                                    $estoque_max_esta_linha = $is_outro ? 0.0 : ($estoque_real + (float)$item['quantidade']);
                                            ?>
                                                <tr>
                                                    <td>
                                                        <select name="itens[item_id][]" class="form-control item-select" required>
                                                            <option value="">Selecione...</option>
                                                            <?php foreach ($almoxarifado_itens as $almox_item): ?>
                                                                <?php
                                                                $selecionado = $item_id === (int)$almox_item['id'];
                                                                $estoque_opcao = $selecionado ? $estoque_max_esta_linha : (float)$almox_item['quantidade'];
                                                                $estoque_opcao_real = (float)$almox_item['quantidade'];
                                                                ?>
                                                                <option value="<?php echo $almox_item['id']; ?>"
                                                                        data-nome="<?php echo htmlspecialchars($almox_item['nome']); ?>"
                                                                        data-valor="<?php echo number_format((float)$almox_item['valor_unitario'], 2, '.', ''); ?>"
                                                                        data-estoque="<?php echo number_format((float)$estoque_opcao, 2, '.', ''); ?>"
                                                                        data-estoque-real="<?php echo number_format($estoque_opcao_real, 2, '.', ''); ?>"
                                                                        <?php echo $selecionado ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($almox_item['codigo_barras'] ? ($almox_item['codigo_barras'] . ' - ' . $almox_item['nome']) : $almox_item['nome']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                            <option value="outro" <?php echo $is_outro ? 'selected' : ''; ?>>Outro</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <?php $det_outro = $is_outro ? htmlspecialchars($item['descricao'] ?? '') : ''; ?>
                                                        <input type="hidden" name="itens[descricao][]" class="descricao-post" value="<?php echo $det_outro; ?>">
                                                        <input type="text" class="form-control descricao-item<?php echo $is_outro ? '' : ' bg-light'; ?>"
                                                               value="<?php echo $det_outro; ?>"
                                                               <?php echo $is_outro ? '' : 'disabled'; ?>
                                                               placeholder="Descreva o material"
                                                               autocomplete="off"
                                                               title="<?php echo $is_outro ? '' : 'Habilitado ao escolher Outro'; ?>">
                                                    </td>
                                                    <td>
                                                        <span class="estoque-item">
                                                            <?php echo $is_outro ? '-' : number_format($estoque_real, 2, ',', '.'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="itens[quantidade][]" class="form-control quantidade" value="<?php echo (int)$item['quantidade']; ?>" step="1" min="1" required>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $vu_val = (float)($item['valor_unitario'] ?? 0);
                                                        $vu_str = number_format($vu_val, 2, '.', '');
                                                        ?>
                                                        <input type="hidden" name="itens[valor_unitario][]" class="valor-post" value="<?php echo htmlspecialchars($vu_str); ?>">
                                                        <input type="number" class="form-control valor-unitario-ui<?php echo $is_outro ? '' : ' bg-light'; ?>"
                                                               value="<?php echo htmlspecialchars($vu_str); ?>" step="0.01" min="0"
                                                               <?php echo $is_outro ? '' : 'disabled'; ?> autocomplete="off"
                                                               title="<?php echo $is_outro ? '' : 'Valor registrado nesta linha (custo no momento em que a OS foi preenchida)'; ?>">
                                                    </td>
                                                <td>
                                                    <span class="total-item"><?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remover-item">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="7">
                                                    <button type="button" class="btn btn-success btn-sm" id="adicionarItem">
                                                        <i class="bi bi-plus"></i> Adicionar Item
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                                <td colspan="2">
                                                    <span id="total-geral">R$ 0,00</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Upload de PDF -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="pdf_os">PDF da OS</label>
                                    <input type="file" name="pdf_os" id="pdf_os" class="form-control" accept=".pdf">
                                    <small class="form-text text-muted">Apenas arquivos PDF são aceitos (máximo 16MB)</small>
                                    <?php if ($modo_edicao && !empty($os['pdf'])): ?>
                                        <div class="mt-2" id="pdf-existente">
                                            <small class="text-primary">
                                                <i class="bi bi-file-pdf"></i> 
                                                <a href="../visualizar_os_pdf.php?id=<?php echo $os['id']; ?>" 
                                                   target="_blank" 
                                                   class="text-primary text-decoration-none">
                                                   OS_<?php echo $os['numero_os']; ?>.pdf
                                                </a>
                                                <span class="ms-2 text-primary" 
                                                      style="cursor: pointer;" 
                                                      onclick="removerPDF()" 
                                                      title="Remover PDF">
                                                    <i class="bi bi-x"></i>
                                                </span>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Botões -->
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary" id="btnSalvar"><?php echo $modo_edicao ? 'Salvar Alterações' : 'Salvar OS'; ?></button>
                                <a href="ordens_servico.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </div>
                    </form>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const form = document.getElementById('formOS');
                        const btnSalvar = document.getElementById('btnSalvar');
                        const tabelaItens = document.getElementById('tabelaItens');
                        const btnAdicionarItem = document.getElementById('adicionarItem');
                        const totalGeral = document.getElementById('total-geral');
                        const opcoesItens = <?php
                            $opcoes = '<option value="">Selecione...</option>';
                            foreach ($almoxarifado_itens as $almox_item) {
                                $valor = number_format((float)$almox_item['valor_unitario'], 2, '.', '');
                                $estoque = number_format((float)$almox_item['quantidade'], 2, '.', '');
                                $nome = htmlspecialchars($almox_item['nome']);
                                $label = $almox_item['codigo_barras']
                                    ? htmlspecialchars($almox_item['codigo_barras']) . ' - ' . $nome
                                    : $nome;
                                $opcoes .= '<option value="' . (int)$almox_item['id'] . '" data-nome="' . $nome . '" data-valor="' . $valor . '" data-estoque="' . $estoque . '" data-estoque-real="' . $estoque . '">' . $label . '</option>';
                            }
                            $opcoes .= '<option value="outro">Outro</option>';
                            echo json_encode($opcoes);
                        ?>;
                        const temItensDisponiveis = <?php echo !empty($almoxarifado_itens) ? 'true' : 'false'; ?>;
                        const modoEdicao = <?php echo $modo_edicao ? 'true' : 'false'; ?>;

                        function escapeHtml(text) {
                            const div = document.createElement('div');
                            div.textContent = text;
                            return div.innerHTML;
                        }

                        function limparErrosValidacaoCliente() {
                            const box = document.getElementById('alertValidacaoOS');
                            if (!box) {
                                return;
                            }
                            box.classList.add('d-none');
                            box.innerHTML = '';
                        }

                        function mostrarErrosValidacaoCliente(mensagens) {
                            const box = document.getElementById('alertValidacaoOS');
                            if (!box || !mensagens.length) {
                                return;
                            }
                            box.innerHTML = mensagens.map(function (m) {
                                return '<div>' + escapeHtml(m) + '</div>';
                            }).join('');
                            box.classList.remove('d-none');
                            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }

                        function parseNumber(value) {
                            if (value === null || value === undefined) {
                                return 0;
                            }
                            const normalized = String(value).replace(',', '.');
                            const parsed = parseFloat(normalized);
                            return Number.isNaN(parsed) ? 0 : parsed;
                        }

                        function syncDescricaoPost(row) {
                            const select = row.querySelector('.item-select');
                            const hidden = row.querySelector('.descricao-post');
                            const visible = row.querySelector('.descricao-item');
                            if (!hidden || !visible) {
                                return;
                            }
                            if (select && select.value === 'outro') {
                                hidden.value = visible.value;
                            } else {
                                hidden.value = '';
                            }
                        }

                        function syncValorPost(row) {
                            const select = row.querySelector('.item-select');
                            const post = row.querySelector('.valor-post');
                            const ui = row.querySelector('.valor-unitario-ui');
                            if (!post || !ui) {
                                return;
                            }
                            if (select && select.value === 'outro') {
                                const v = parseFloat(String(ui.value).replace(',', '.'));
                                post.value = (Number.isNaN(v) ? 0 : v).toFixed(2);
                            }
                        }

                        function validarItemErros(row, linhaNum) {
                            const erros = [];
                            const qEl = row.querySelector('.quantidade');
                            const quantidade = qEl ? parseFloat(qEl.value) : NaN;
                            const valorPost = row.querySelector('.valor-post');
                            const valorUnitario = valorPost ? parseFloat(valorPost.value) : NaN;
                            const itemSelect = row.querySelector('.item-select');
                            const itemId = itemSelect ? itemSelect.value : '';
                            const descEl = row.querySelector('.descricao-item');
                            const descricao = descEl ? descEl.value.trim() : '';
                            const estoque = parseNumber(itemSelect && itemSelect.selectedOptions[0]
                                ? itemSelect.selectedOptions[0].dataset.estoque
                                : undefined);
                            const ref = ' (material, linha ' + linhaNum + ')';

                            if (!itemId) {
                                erros.push('O campo Item do almoxarifado' + ref + ' é obrigatório.');
                                return erros;
                            }
                            if (itemId === 'outro' && !descricao) {
                                erros.push('O campo Descrição do material' + ref + ' é obrigatório.');
                            }
                            if (isNaN(quantidade) || quantidade <= 0 || !Number.isInteger(quantidade)) {
                                erros.push('O campo Quantidade' + ref + ' deve ser um número inteiro maior que zero.');
                            }
                            if (itemId !== 'outro' && !isNaN(quantidade) && quantidade > estoque) {
                                erros.push('A quantidade' + ref + ' não pode ser maior que o estoque disponível.');
                            }
                            if (itemId === 'outro' && (isNaN(valorUnitario) || valorUnitario < 0)) {
                                erros.push('O campo Valor unitário' + ref + ' deve ser maior ou igual a zero.');
                            }
                            return erros;
                        }

                        function coletarErrosItens() {
                            const rows = tabelaItens.querySelectorAll('tbody tr');
                            const todos = [];
                            rows.forEach(function (row, i) {
                                validarItemErros(row, i + 1).forEach(function (msg) {
                                    todos.push(msg);
                                });
                            });
                            return todos;
                        }

                        // Função para calcular o total de um item
                        function calcularTotalItem(row) {
                            const quantidade = parseFloat(row.querySelector('.quantidade').value) || 0;
                            const valorUnitario = parseFloat(row.querySelector('.valor-post')?.value) || 0;
                            const total = quantidade * valorUnitario;
                            row.querySelector('.total-item').textContent = total.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }

                        // Função para calcular o total geral
                        function calcularTotalGeral() {
                            let total = 0;
                            document.querySelectorAll('#tabelaItens tbody tr').forEach(row => {
                                const quantidade = parseFloat(row.querySelector('.quantidade').value) || 0;
                                const valorUnitario = parseFloat(row.querySelector('.valor-post')?.value) || 0;
                                total += quantidade * valorUnitario;
                            });
                            totalGeral.textContent = 'R$ ' + total.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }

                        function aplicarItemSelecionado(row) {
                            const select = row.querySelector('.item-select');
                            const descricaoInput = row.querySelector('.descricao-item');
                            const descricaoPost = row.querySelector('.descricao-post');
                            const valorPost = row.querySelector('.valor-post');
                            const valorUi = row.querySelector('.valor-unitario-ui');
                            const estoqueSpan = row.querySelector('.estoque-item');

                            const selected = select.selectedOptions[0];
                            const isOutro = select.value === 'outro';
                            const estoque = parseNumber(selected?.dataset?.estoque);
                            const estoqueReal = parseNumber(selected?.dataset?.estoqueReal);
                            const valor = parseNumber(selected?.dataset?.valor);
                            if (isOutro) {
                                descricaoInput.disabled = false;
                                descricaoInput.classList.remove('bg-light');
                                if (descricaoPost) {
                                    descricaoPost.value = descricaoInput.value;
                                }
                                estoqueSpan.textContent = '-';
                                if (valorUi) {
                                    valorUi.disabled = false;
                                    valorUi.classList.remove('bg-light');
                                }
                                if (valorPost && valorUi) {
                                    const v = parseFloat(String(valorUi.value).replace(',', '.'));
                                    valorPost.value = (Number.isNaN(v) ? 0 : v).toFixed(2);
                                }
                            } else {
                                descricaoInput.disabled = true;
                                descricaoInput.classList.add('bg-light');
                                descricaoInput.value = '';
                                if (descricaoPost) {
                                    descricaoPost.value = '';
                                }
                                const exibirEstoque = !Number.isNaN(estoqueReal) ? estoqueReal : estoque;
                                estoqueSpan.textContent = exibirEstoque.toLocaleString('pt-BR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                if (valorUi) {
                                    valorUi.disabled = true;
                                    valorUi.classList.add('bg-light');
                                }
                                if (!Number.isNaN(valor) && valorPost && valorUi) {
                                    const s = valor.toFixed(2);
                                    valorPost.value = s;
                                    valorUi.value = s;
                                }
                            }
                        }

                        // Adicionar novo item
                        btnAdicionarItem.addEventListener('click', function() {
                            const tbody = tabelaItens.querySelector('tbody');
                            const novaLinha = document.createElement('tr');
                            novaLinha.innerHTML = `
                                <td>
                                    <select name="itens[item_id][]" class="form-control item-select" required>
                                        ${opcoesItens}
                                    </select>
                                </td>
                                <td>
                                    <input type="hidden" name="itens[descricao][]" class="descricao-post" value="">
                                    <input type="text" class="form-control descricao-item bg-light" placeholder="Descreva o material" disabled autocomplete="off">
                                </td>
                                <td>
                                    <span class="estoque-item">-</span>
                                </td>
                                <td>
                                    <input type="number" name="itens[quantidade][]" class="form-control quantidade" step="1" min="1" required>
                                </td>
                                <td>
                                    <input type="hidden" name="itens[valor_unitario][]" class="valor-post" value="0.00">
                                    <input type="number" class="form-control valor-unitario-ui bg-light" value="0.00" step="0.01" min="0" disabled autocomplete="off">
                                </td>
                                <td>
                                    <span class="total-item">0,00</span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remover-item">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(novaLinha);

                            const inputs = novaLinha.querySelectorAll('input');
                            inputs.forEach(input => {
                                if (input.classList.contains('descricao-post') || input.classList.contains('valor-post')) {
                                    return;
                                }
                                input.addEventListener('input', () => {
                                    if (input.classList.contains('descricao-item')) {
                                        syncDescricaoPost(novaLinha);
                                    }
                                    if (input.classList.contains('valor-unitario-ui')) {
                                        syncValorPost(novaLinha);
                                    }
                                    calcularTotalItem(novaLinha);
                                    calcularTotalGeral();
                                });
                            });

                            const select = novaLinha.querySelector('.item-select');
                            select.addEventListener('change', () => {
                                aplicarItemSelecionado(novaLinha);
                                calcularTotalItem(novaLinha);
                                calcularTotalGeral();
                            });

                            novaLinha.querySelector('.remover-item').addEventListener('click', function() {
                                novaLinha.remove();
                                calcularTotalGeral();
                            });
                        });

                        // Adiciona eventos aos itens existentes
                        document.querySelectorAll('#tabelaItens tbody tr').forEach(row => {
                            const inputs = row.querySelectorAll('input');
                            inputs.forEach(input => {
                                if (input.classList.contains('descricao-post') || input.classList.contains('valor-post')) {
                                    return;
                                }
                                input.addEventListener('input', () => {
                                    if (input.classList.contains('descricao-item')) {
                                        syncDescricaoPost(row);
                                    }
                                    if (input.classList.contains('valor-unitario-ui')) {
                                        syncValorPost(row);
                                    }
                                    calcularTotalItem(row);
                                    calcularTotalGeral();
                                });
                            });

                            const select = row.querySelector('.item-select');
                            if (select) {
                                select.addEventListener('change', () => {
                                    aplicarItemSelecionado(row);
                                    calcularTotalItem(row);
                                    calcularTotalGeral();
                                });
                            }

                            row.querySelector('.remover-item').addEventListener('click', function() {
                                row.remove();
                                calcularTotalGeral();
                            });
                        });

                        // Validação do formulário antes do envio (todos os erros no mesmo alerta)
                        form.addEventListener('submit', function(e) {
                            limparErrosValidacaoCliente();
                            document.querySelectorAll('#tabelaItens tbody tr').forEach(function (row) {
                                syncDescricaoPost(row);
                                syncValorPost(row);
                            });

                            const erros = [];
                            coletarErrosItens().forEach(function (msg) {
                                erros.push(msg);
                            });

                            const tipoManutencao = document.getElementById('tipo_manutencao');
                            if (tipoManutencao && !tipoManutencao.value) {
                                erros.push('O campo Tipo de Manutenção é obrigatório.');
                            }
                            const prioridade = document.getElementById('prioridade');
                            if (prioridade && !prioridade.value) {
                                erros.push('O campo Prioridade é obrigatório.');
                            }
                            const dataAbertura = document.getElementById('data_abertura');
                            if (!modoEdicao && dataAbertura && !dataAbertura.value) {
                                erros.push('O campo Data de Abertura é obrigatório.');
                            }
                            const gestorEl = document.getElementById('gestor_id');
                            if (gestorEl && !gestorEl.value) {
                                erros.push('O campo Gestor é obrigatório.');
                            }
                            const responsavelEl = document.getElementById('usuario_responsavel_id');
                            if (responsavelEl && !responsavelEl.value) {
                                erros.push('O campo Responsável é obrigatório.');
                            }
                            const tipoProprietarioEl = document.getElementById('tipo_proprietario');
                            const clienteEl = document.getElementById('cliente_id');
                            if (tipoProprietarioEl && clienteEl && tipoProprietarioEl.value === 'terceiro' && !clienteEl.value) {
                                erros.push('O campo Executor Terceiro é obrigatório quando o proprietário for terceiro.');
                            }
                            const statusOsEl = document.getElementById('status');
                            if (modoEdicao && statusOsEl && !statusOsEl.value) {
                                erros.push('O campo Status da OS é obrigatório.');
                            }

                            if (erros.length) {
                                e.preventDefault();
                                mostrarErrosValidacaoCliente(erros);
                                return false;
                            }
                        });

                        // Calcula o total inicial
                        calcularTotalGeral();
                        
                        // Controle do campo cliente terceiro
                        const tipoProprietario = document.getElementById('tipo_proprietario');
                        const clienteField = document.getElementById('cliente_terceiro_field');
                        const clienteSelect = document.getElementById('cliente_id');
                        
                        function toggleClienteField() {
                            if (tipoProprietario.value === 'terceiro') {
                                clienteField.style.display = 'block';
                                clienteSelect.required = true;
                            } else {
                                clienteField.style.display = 'none';
                                clienteSelect.required = false;
                                clienteSelect.value = '';
                            }
                        }
                        
                        // Verificar estado inicial
                        toggleClienteField();
                        
                        // Adicionar listener para mudanças
                        tipoProprietario.addEventListener('change', toggleClienteField);
                        
                        // Função para remover PDF
                        window.removerPDF = function() {
                            if (confirm('Tem certeza que deseja remover o PDF anexado?')) {
                                // Esconde o texto do PDF
                                document.getElementById('pdf-existente').style.display = 'none';
                                
                                // Adiciona campo hidden para indicar remoção
                                const hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'remover_pdf';
                                hiddenInput.value = '1';
                                form.appendChild(hiddenInput);
                            }
                        };
                    });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
