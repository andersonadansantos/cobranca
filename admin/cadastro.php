<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$mensagem = '';
$tipo = '';
$editando = false;
$clienteEdit = null;

// Excluir cliente
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: cadastro.php?msg=excluido');
    exit;
}

// Logar como cliente
if (isset($_GET['logar_como'])) {
    $id = intval($_GET['logar_como']);
    $stmt = $pdo->prepare("SELECT id, nome_razao, email, ativo FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
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
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
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
    } else {
        try {
            if ($id_edit > 0) {
                $sql = "UPDATE clientes SET tipo_pessoa=?, nome_razao=?, cpf_cnpj=?, rg_ie=?, email=?, telefone=?, celular=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=?";
                $params = [$tipo_pessoa, $nome_razao, $cpf_cnpj, $rg_ie, $email, $telefone, $celular, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado];
                
                if (!empty($senha)) {
                    $sql .= ", senha=MD5(?)";
                    $params[] = $senha;
                }
                
                $sql .= " WHERE id=?";
                $params[] = $id_edit;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                header('Location: cadastro.php?msg=salvo');
                exit;
            } else {
                if (empty($senha)) {
                    $senha = substr($cpf_cnpj, -4) . '123';
                }
                $stmt = $pdo->prepare("INSERT INTO clientes (tipo_pessoa, nome_razao, cpf_cnpj, rg_ie, email, telefone, celular, cep, logradouro, numero, complemento, bairro, cidade, estado, senha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, MD5(?))");
                $stmt->execute([$tipo_pessoa, $nome_razao, $cpf_cnpj, $rg_ie, $email, $telefone, $celular, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $senha]);
                header('Location: cadastro.php?msg=salvo');
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . ($e->getCode() == 23000 ? 'CPF/CNPJ já cadastrado.' : $e->getMessage());
            $tipo = 'danger';
        }
    }
}

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY criado_em DESC")->fetchAll();

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
                    <div class="col-md-3">
                        <label class="form-label">Senha <?= $editando ? '(deixe vazio para manter)' : '*' ?></label>
                        <input type="password" name="senha" class="form-control" <?= $editando ? '' : 'required' ?>>
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
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Clientes Cadastrados (<?= count($clientes) ?>)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome/Razão</th>
                            <th>CPF/CNPJ</th>
                            <th>Tipo</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Cidade/UF</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum cliente cadastrado</td></tr>
                        <?php else: foreach ($clientes as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['nome_razao']) ?></strong></td>
                                <td><?= htmlspecialchars($c['cpf_cnpj']) ?></td>
                                <td><span class="badge bg-<?= $c['tipo_pessoa'] === 'PJ' ? 'info' : 'secondary' ?>"><?= $c['tipo_pessoa'] ?></span></td>
                                <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($c['celular'] ?? $c['telefone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(($c['cidade'] ?? '') . '/' . ($c['estado'] ?? '')) ?></td>
                                <td>
                                    <a href="?editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?logar_como=<?= $c['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Logar como este cliente" onclick="showConfirm('Logar como cliente','Deseja logar como <?= htmlspecialchars(addslashes($c['nome_razao'])) ?>?','?logar_como=<?= $c['id'] ?>','success'); return false;">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </a>
                                    <a href="?excluir=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="confirmarExclusao('<?= htmlspecialchars(addslashes($c['nome_razao'])) ?>','?excluir=<?= $c['id'] ?>'); return false;">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
