<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

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
        header('Location: /cobranca/usuario/index.php');
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
                if (empty($senha)) {
                    $senha = $cpf_cnpj;
                }
                $stmt = $pdo->prepare("INSERT INTO clientes (admin_id, tipo_pessoa, nome_razao, cpf_cnpj, rg_ie, email, email2, telefone, celular, cep, logradouro, numero, complemento, bairro, cidade, estado, senha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$adminIdC, $tipo_pessoa, $nome_razao, $cpf_cnpj, $rg_ie, $email, $email2, $telefone, $celular, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, password_hash($senha, PASSWORD_BCRYPT)]);
                header('Location: cadastro.php?msg=salvo');
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . ($e->getCode() == 23000 ? 'CPF/CNPJ já cadastrado.' : $e->getMessage());
            $tipo = 'danger';
        }
    }
}

$busca_nome = trim($_GET['busca_nome'] ?? '');
if (!empty($busca_nome)) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE admin_id = ? AND nome_razao LIKE ? ORDER BY criado_em DESC");
    $stmt->execute([$adminIdC, '%' . $busca_nome . '%']);
    $clientes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE admin_id = ? ORDER BY criado_em DESC");
    $stmt->execute([$adminIdC]);
    $clientes = $stmt->fetchAll();
}

$pageTitle = 'Cadastro de Clientes';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><?= $editando ? 'Editar Cliente' : 'Cadastro de Clientes' ?></h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-card mb-4">
            <h6 class="mb-3"><i class="fas fa-<?= $editando ? 'edit' : 'plus-circle' ?> me-2"></i><?= $editando ? 'Editar' : 'Novo' ?> Cliente</h6>
            <form method="POST">
                <input type="hidden" name="id" value="<?= $clienteEdit['id'] ?? '' ?>">
                
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Pessoa *</label>
                        <select name="tipo_pessoa" class="form-select" id="tipoPessoa" required>
                            <option value="PF" <?= ($clienteEdit['tipo_pessoa'] ?? '') === 'PF' ? 'selected' : '' ?>>Pessoa Física</option>
                            <option value="PJ" <?= ($clienteEdit['tipo_pessoa'] ?? '') === 'PJ' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Nome / Razão Social *</label>
                        <input type="text" name="nome_razao" class="form-control" required value="<?= htmlspecialchars($clienteEdit['nome_razao'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">CPF/CNPJ *</label>
                        <input type="text" name="cpf_cnpj" class="form-control mask-cpf" required value="<?= htmlspecialchars($clienteEdit['cpf_cnpj'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">RG/IE</label>
                        <input type="text" name="rg_ie" class="form-control" value="<?= htmlspecialchars($clienteEdit['rg_ie'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($clienteEdit['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">2º E-mail (cópia)</label>
                        <input type="email" name="email2" class="form-control" value="<?= htmlspecialchars($clienteEdit['email2'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control mask-phone" value="<?= htmlspecialchars($clienteEdit['telefone'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Celular</label>
                        <input type="text" name="celular" class="form-control mask-phone" value="<?= htmlspecialchars($clienteEdit['celular'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" class="form-control mask-cep" id="cep" value="<?= htmlspecialchars($clienteEdit['cep'] ?? '') ?>" onblur="buscarCEP(this.value, {logradouro:'logradouro',bairro:'bairro',cidade:'cidade',estado:'estado'})">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Senha <?= $editando ? '(deixe vazio para manter)' : '*' ?></label>
                        <input type="password" name="senha" class="form-control" minlength="6" <?= $editando ? '' : 'required' ?>>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Logradouro</label>
                        <input type="text" name="logradouro" id="logradouro" class="form-control" value="<?= htmlspecialchars($clienteEdit['logradouro'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Nº</label>
                        <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($clienteEdit['numero'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($clienteEdit['complemento'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control" value="<?= htmlspecialchars($clienteEdit['bairro'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" value="<?= htmlspecialchars($clienteEdit['cidade'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">UF</label>
                        <input type="text" name="estado" id="estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($clienteEdit['estado'] ?? '') ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> <?= $editando ? 'Atualizar' : 'Cadastrar' ?>
                    </button>
                    <?php if ($editando): ?>
                        <a href="cadastro.php" class="btn btn-secondary ms-2">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Clientes Cadastrados (<?= count($clientes) ?>)</h6>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <form method="GET" class="d-flex gap-1 align-items-center">
                        <input type="text" name="busca_nome" class="form-control form-control-sm" placeholder="Buscar por nome..." value="<?= htmlspecialchars($busca_nome) ?>" style="max-width:220px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        <?php if (!empty($busca_nome)): ?>
                            <a href="cadastro.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                    <a href="export_csv.php?tipo=clientes" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv me-1"></i>Exportar CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome/Razão</th>
                            <th>CPF/CNPJ</th>
                            <th>Tipo</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Nenhum cliente cadastrado</td></tr>
                        <?php else: foreach ($clientes as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nome_razao']) ?></strong></td>
                                <td><?= htmlspecialchars($c['cpf_cnpj']) ?></td>
                                <td><span class="badge bg-<?= $c['tipo_pessoa'] === 'PJ' ? 'info' : 'secondary' ?>"><?= $c['tipo_pessoa'] ?></span></td>
                                <td>
                                    <div class="d-inline-flex gap-1 align-items-center justify-content-center">
                                        <a href="#" class="acao-btn acao-btn-secondary" title="Visualizar" 
                                           data-cliente="<?= htmlspecialchars(base64_encode(json_encode($c)), ENT_QUOTES) ?>"
                                           onclick="abrirModalCliente(JSON.parse(atob(this.dataset.cliente))); return false;">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="?editar=<?= $c['id'] ?>" class="acao-btn acao-btn-primary" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="#" class="acao-btn acao-btn-success" title="Logar como este cliente" onclick="showConfirm('Logar como cliente','Deseja logar como <?= htmlspecialchars(addslashes($c['nome_razao'])) ?>?','?logar_como=<?= $c['id'] ?>','success'); return false;">
                                            <i class="bi bi-box-arrow-in-right"></i>
                                        </a>
                                        <a href="#" class="acao-btn acao-btn-danger" title="Excluir" onclick="confirmarExclusao('<?= htmlspecialchars(addslashes($c['nome_razao'])) ?>','?excluir=<?= $c['id'] ?>'); return false;">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-person-badge me-2"></i>Detalhes do Cliente</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="text-muted small">Nome / Razão Social</label>
                        <div class="fw-semibold" id="mc-nome">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">CPF / CNPJ</label>
                        <div id="mc-cpf">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Tipo de Pessoa</label>
                        <div id="mc-tipo">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">E-mail</label>
                        <div id="mc-email">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">2º E-mail</label>
                        <div id="mc-email2">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Telefone</label>
                        <div id="mc-telefone">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Celular</label>
                        <div id="mc-celular">-</div>
                    </div>
                    <div class="col-md-8">
                        <label class="text-muted small">Endereço</label>
                        <div id="mc-endereco">-</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Bairro</label>
                        <div id="mc-bairro">-</div>
                    </div>
                    <div class="col-md-8">
                        <label class="text-muted small">Cidade / UF</label>
                        <div id="mc-cidade">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">CEP</label>
                        <div id="mc-cep">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Data de Cadastro</label>
                        <div id="mc-data">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <a href="#" id="mc-link-editar" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Editar</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
