<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/lang_painel.php';

$pdo = getConnection();
$adminIdC = (int)$_SESSION['admin_id'];
$mensagem = '';
$tipo = '';
$editando = false;
$clienteEdit = null;

// Excluir cliente
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdC]);
    header('Location: cadastro.php?msg=excluido');
    exit;
}

// Logar como cliente
if (isset($_GET['logar_como'])) {
    $id = intval($_GET['logar_como']);
    $stmt = $pdo->prepare("SELECT id, nome_razao, email, ativo FROM clientes WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdC]);
    $cli = $stmt->fetch();
    if ($cli && $cli['ativo']) {
        $_SESSION['user_id'] = $cli['id'];
        $_SESSION['user_nome'] = $cli['nome_razao'];
        $_SESSION['user_email'] = $cli['email'];
        $_SESSION['user_avatar'] = null;
        header('Location: ' . APP_BASE . '/usuario/index.php');
        exit;
    }
}

// Editar cliente
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdC]);
    $clienteEdit = $stmt->fetch();
    if ($clienteEdit) $editando = true;
}

// Mensagens
if (isset($_GET['msg'])) {
    $msgs = [
        'salvo' => ['Cliente salvo com sucesso!', 'success'],
        'excluido' => ['Cliente excluído com sucesso!', 'warning'],
        'erro' => ['Erro ao salvar cliente.', 'danger'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

// Salvar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_pessoa = $_POST['tipo_pessoa'] ?? 'PF';
    $nome_razao = trim($_POST['nome_razao'] ?? '');
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $_POST['cpf_cnpj'] ?? '');
    $rg_ie = trim($_POST['rg_ie'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $email2 = trim($_POST['email2'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $id_edit = intval($_POST['id'] ?? 0);

    if (empty($nome_razao) || empty($cpf_cnpj)) {
        $mensagem = 'Nome e CPF/CNPJ são obrigatórios.';
        $tipo = 'danger';
    } elseif (!empty($senha) && strlen($senha) < 6) {
        $mensagem = 'A senha deve ter no mínimo 6 caracteres.';
        $tipo = 'danger';
    } else {
        try {
            if ($id_edit > 0) {
                $sql = "UPDATE clientes SET tipo_pessoa=?, nome_razao=?, cpf_cnpj=?, rg_ie=?, email=?, email2=?, telefone=?, celular=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=?";
                $params = [$tipo_pessoa, $nome_razao, $cpf_cnpj, $rg_ie, $email, $email2, $telefone, $celular, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado];
                
                if (!empty($senha)) {
                    $sql .= ", senha=?";
                    $params[] = password_hash($senha, PASSWORD_BCRYPT);
                }
                
                $sql .= " WHERE id=? AND admin_id=?";
                $params[] = $id_edit;
                $params[] = $adminIdC;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                header('Location: cadastro.php?msg=salvo');
                exit;
            } else {
                $limiteCli = verificarLimitePlano('clientes', $adminIdC);
                if (!$limiteCli['ok']) {
                    $mensagem = $limiteCli['mensagem'];
                    $tipo = 'danger';
                } else {
                    if (empty($senha)) {
                        $senha = $cpf_cnpj;
                    }
                    $stmt = $pdo->prepare("INSERT INTO clientes (admin_id, tipo_pessoa, nome_razao, cpf_cnpj, rg_ie, email, email2, telefone, celular, cep, logradouro, numero, complemento, bairro, cidade, estado, senha, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$adminIdC, $tipo_pessoa, $nome_razao, $cpf_cnpj, $rg_ie, $email, $email2, $telefone, $celular, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, password_hash($senha, PASSWORD_BCRYPT)]);
                    header('Location: cadastro.php?msg=salvo');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . ($e->getCode() == 23000 ? 'CPF/CNPJ já cadastrado.' : $e->getMessage());
            $tipo = 'danger';
        }
    }
}

$busca_nome = trim($_GET['busca_nome'] ?? '');
if (!empty($busca_nome)) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE admin_id = ? AND (nome_razao LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?) ORDER BY criado_em DESC");
    $stmt->execute([$adminIdC, '%' . $busca_nome . '%', '%' . $busca_nome . '%', '%' . $busca_nome . '%']);
    $clientes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE admin_id = ? ORDER BY criado_em DESC");
    $stmt->execute([$adminIdC]);
    $clientes = $stmt->fetchAll();
}

$pageTitle = $editando ? 'Editar Cliente' : 'Gestão de Clientes';
$pageCategory = 'Cadastros';

include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo ?> alert-dismissible fade show border-0 shadow-sm mb-4" style="border-radius:12px;">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- FORMULÁRIO DE CADASTRO / EDIÇÃO -->
<div class="fintech-card mb-4">
    <div class="fintech-card-header">
        <h2 class="fintech-card-title">
            <i class="bi bi-<?= $editando ? 'pencil-square' : 'person-plus' ?> text-primary"></i>
            <?= $editando ? 'Editar Cadastro do Cliente' : 'Cadastrar Novo Cliente' ?>
        </h2>
        <?php if ($editando): ?>
            <a href="cadastro.php" class="btn-fintech-secondary btn-sm">
                <i class="bi bi-x-lg"></i> Cancelar Edição
            </a>
        <?php endif; ?>
    </div>

    <form method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($clienteEdit['id'] ?? '') ?>">
        
        <div class="row g-3">
            <div class="col-md-2">
                <label class="fintech-form-label">Tipo *</label>
                <select name="tipo_pessoa" class="form-select" id="tipoPessoa" required>
                    <option value="PF" <?= ($clienteEdit['tipo_pessoa'] ?? '') === 'PF' ? 'selected' : '' ?>>Pessoa Física (PF)</option>
                    <option value="PJ" <?= ($clienteEdit['tipo_pessoa'] ?? '') === 'PJ' ? 'selected' : '' ?>>Pessoa Jurídica (PJ)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="fintech-form-label" id="labelNome">Nome Completo *</label>
                <input type="text" name="nome_razao" class="form-control" required value="<?= htmlspecialchars($clienteEdit['nome_razao'] ?? '') ?>" placeholder="Ex: João da Silva ou Empresa LTDA">
            </div>
            <div class="col-md-4">
                <label class="fintech-form-label" id="labelDoc">CPF *</label>
                <input type="text" name="cpf_cnpj" id="cpf_cnpj" class="form-control" required value="<?= htmlspecialchars($clienteEdit['cpf_cnpj'] ?? '') ?>" placeholder="Somente números ou com máscara">
            </div>

            <div class="col-md-4">
                <label class="fintech-form-label">E-mail Principal *</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($clienteEdit['email'] ?? '') ?>" placeholder="cliente@email.com">
            </div>
            <div class="col-md-4">
                <label class="fintech-form-label">E-mail Secundário</label>
                <input type="email" name="email2" class="form-control" value="<?= htmlspecialchars($clienteEdit['email2'] ?? '') ?>" placeholder="financeiro@email.com">
            </div>
            <div class="col-md-4">
                <label class="fintech-form-label">Celular / WhatsApp *</label>
                <input type="text" name="celular" id="celular" class="form-control" required value="<?= htmlspecialchars($clienteEdit['celular'] ?? '') ?>" placeholder="(00) 00000-0000">
            </div>

            <div class="col-md-2">
                <label class="fintech-form-label">CEP</label>
                <input type="text" name="cep" id="cep" class="form-control" value="<?= htmlspecialchars($clienteEdit['cep'] ?? '') ?>" placeholder="00000-000" onblur="buscarCep(this.value)">
            </div>
            <div class="col-md-5">
                <label class="fintech-form-label">Logradouro / Rua</label>
                <input type="text" name="logradouro" id="logradouro" class="form-control" value="<?= htmlspecialchars($clienteEdit['logradouro'] ?? '') ?>" placeholder="Av. Principal">
            </div>
            <div class="col-md-2">
                <label class="fintech-form-label">Número</label>
                <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($clienteEdit['numero'] ?? '') ?>" placeholder="123">
            </div>
            <div class="col-md-3">
                <label class="fintech-form-label">Bairro</label>
                <input type="text" name="bairro" id="bairro" class="form-control" value="<?= htmlspecialchars($clienteEdit['bairro'] ?? '') ?>" placeholder="Centro">
            </div>

            <div class="col-md-4">
                <label class="fintech-form-label">Cidade</label>
                <input type="text" name="cidade" id="cidade" class="form-control" value="<?= htmlspecialchars($clienteEdit['cidade'] ?? '') ?>" placeholder="São Paulo">
            </div>
            <div class="col-md-2">
                <label class="fintech-form-label">UF</label>
                <input type="text" name="estado" id="estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($clienteEdit['estado'] ?? '') ?>" placeholder="SP">
            </div>
            <div class="col-md-3">
                <label class="fintech-form-label">Complemento</label>
                <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($clienteEdit['complemento'] ?? '') ?>" placeholder="Sala 101, Apto 2">
            </div>
            <div class="col-md-3">
                <label class="fintech-form-label">Senha de Acesso <?= $editando ? '(deixe em branco para manter)' : '(padrão: CPF/CNPJ)' ?></label>
                <input type="password" name="senha" class="form-control" placeholder="<?= $editando ? 'Nova senha' : 'Senha do cliente' ?>">
            </div>

            <div class="col-12 text-end mt-4">
                <button type="submit" class="btn-fintech-primary">
                    <i class="bi bi-check2-circle"></i> <?= $editando ? 'Atualizar Cliente' : 'Salvar Cliente' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- LISTAGEM DE CLIENTES -->
<div class="fintech-table-container">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h3 class="fintech-card-title m-0" style="font-size:15px;">
                <i class="bi bi-people text-muted"></i> Clientes Cadastrados (<?= count($clientes) ?>)
            </h3>
        </div>

        <form method="GET" class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 280px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="busca_nome" class="form-control border-start-0" placeholder="Buscar por nome, documento ou e-mail..." value="<?= htmlspecialchars($busca_nome) ?>">
            </div>
            <?php if (!empty($busca_nome)): ?>
                <a href="cadastro.php" class="btn-fintech-secondary btn-sm" title="Limpar busca"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="fintech-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Contatos</th>
                    <th>Localização</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            Nenhum cliente localizado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:38px;height:38px;border-radius:10px;background:var(--fintech-primary-light);color:var(--fintech-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;">
                                    <?= strtoupper(substr($c['nome_razao'], 0, 2)) ?>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= htmlspecialchars($c['nome_razao']) ?></strong>
                                    <span class="badge bg-light text-secondary border" style="font-size:10px;"><?= $c['tipo_pessoa'] ?? 'PF' ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="font-monospace text-dark" style="font-size:13px;"><?= htmlspecialchars($c['cpf_cnpj']) ?></span>
                        </td>
                        <td>
                            <div class="text-dark" style="font-size:12.5px;"><?= htmlspecialchars($c['email']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($c['celular'] ?: $c['telefone'] ?: '-') ?></small>
                        </td>
                        <td>
                            <span class="text-secondary" style="font-size:12.5px;">
                                <?= htmlspecialchars(($c['cidade'] ? $c['cidade'] . ' - ' . $c['estado'] : '-')) ?>
                            </span>
                        </td>
                        <td>
                            <span class="fintech-badge <?= $c['ativo'] ? 'fintech-badge-pago' : 'fintech-badge-cancelado' ?>">
                                <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="cadastro.php?editar=<?= $c['id'] ?>" class="btn-fintech-icon" title="Editar Dados">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="cadastro.php?logar_como=<?= $c['id'] ?>" target="_blank" class="btn-fintech-icon" title="Acessar como Cliente">
                                    <i class="bi bi-box-arrow-in-right"></i>
                                </a>
                                <a href="faturas.php?busca=<?= urlencode($c['nome_razao']) ?>" class="btn-fintech-icon" title="Ver Cobranças">
                                    <i class="bi bi-receipt"></i>
                                </a>
                                <a href="cadastro.php?excluir=<?= $c['id'] ?>" class="btn-fintech-icon text-danger" title="Excluir Cliente" onclick="return confirm('Deseja realmente excluir este cliente?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function buscarCep(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) return;
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(r => r.json())
        .then(d => {
            if (!d.erro) {
                document.getElementById('logradouro').value = d.logradouro || '';
                document.getElementById('bairro').value = d.bairro || '';
                document.getElementById('cidade').value = d.localidade || '';
                document.getElementById('estado').value = d.uf || '';
                document.getElementById('numero').focus();
            }
        }).catch(e => console.log(e));
}

var tpSelect = document.getElementById('tipoPessoa');
if (tpSelect) {
    tpSelect.addEventListener('change', function() {
        var isPj = this.value === 'PJ';
        document.getElementById('labelNome').textContent = isPj ? 'Razão Social *' : 'Nome Completo *';
        document.getElementById('labelDoc').textContent = isPj ? 'CNPJ *' : 'CPF *';
    });
}
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

