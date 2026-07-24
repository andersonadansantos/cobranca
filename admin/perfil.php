<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$adminId = $_SESSION['admin_id'];
$mensagem = '';
$tipo = '';

$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $inscEstadual = trim($_POST['inscricao_estadual'] ?? '');
    $inscMunicipal = trim($_POST['inscricao_municipal'] ?? '');
    $telComercial = trim($_POST['telefone_comercial'] ?? '');
    $emailComercial = trim($_POST['email_comercial'] ?? '');
    $cepEmpresa = preg_replace('/[^0-9]/', '', $_POST['cep_empresa'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    try {
        if (!empty($novaSenha)) {
            if (empty($nome) || empty($email) || empty($usuario)) {
                $mensagem = 'Nome, e-mail e usuário são obrigatórios.';
                $tipo = 'danger';
            } elseif ($novaSenha !== $confirmaSenha) {
                $mensagem = 'As senhas não conferem.';
                $tipo = 'danger';
            } elseif (empty($senhaAtual)) {
                $mensagem = 'Informe a senha atual para definir uma nova.';
                $tipo = 'danger';
            } elseif (!password_verify($senhaAtual, $admin['senha']) && md5($senhaAtual) !== $admin['senha']) {
                $mensagem = 'Senha atual incorreta.';
                $tipo = 'danger';
            } else {
                $stmt = $pdo->prepare("UPDATE administradores SET nome=?, email=?, usuario=?, senha=? WHERE id=?");
                $stmt->execute([$nome, $email, $usuario, password_hash($novaSenha, PASSWORD_BCRYPT), $adminId]);
                $_SESSION['admin_nome'] = $nome;
                $_SESSION['admin_usuario'] = $usuario;
                $mensagem = 'Perfil atualizado com sucesso!';
                $tipo = 'success';
            }
        } else {
            $stmt = $pdo->prepare("UPDATE administradores SET nome=?, email=?, usuario=? WHERE id=?");
            $stmt->execute([$nome, $email, $usuario, $adminId]);
            $_SESSION['admin_nome'] = $nome;
            $_SESSION['admin_usuario'] = $usuario;
            $mensagem = 'Perfil atualizado com sucesso!';
            $tipo = 'success';
        }

        if ($tipo === 'success') {
            $stmt = $pdo->prepare("UPDATE administradores SET razao_social=?, nome_fantasia=?, cnpj=?, inscricao_estadual=?, inscricao_municipal=?, telefone_comercial=?, email_comercial=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id=?");
            $stmt->execute([$razaoSocial, $nomeFantasia, $cnpj, $inscEstadual, $inscMunicipal, $telComercial, $emailComercial, $cepEmpresa, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $adminId]);
        }

        $avatarPath = $admin['avatar'] ?? null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = __DIR__ . '/../assets/img/avatars';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'admin_' . $adminId . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $filename)) {
                    $avatarPath = '/cobranca/assets/img/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE administradores SET avatar=? WHERE id=?");
                    $stmt->execute([$avatarPath, $adminId]);
                    $_SESSION['admin_avatar'] = $avatarPath;
                }
            }
        }

        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

    } catch (PDOException $e) {
        $mensagem = 'Erro ao atualizar: ' . $e->getMessage();
        $tipo = 'danger';
    }
}

$pageTitle = 'Meu Perfil';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Meu Perfil</h5>
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

        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-lg-4">
                    <div class="form-card text-center">
                        <img src="<?= htmlspecialchars($admin['avatar'] ?: '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle mb-3" width="120" height="120" style="object-fit:cover; border: 3px solid var(--cor-primaria);">
                        <h5><?= htmlspecialchars($admin['nome']) ?></h5>
                        <small class="text-muted"><?= htmlspecialchars($admin['email']) ?></small>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-user-edit me-2"></i>Editar Perfil</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($admin['nome']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Usuário</label>
                                <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($admin['usuario']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Foto de Perfil</label>
                                <input type="file" name="avatar" class="form-control" accept="image/*">
                            </div>
                            <div class="col-12"><hr></div>
                            <div class="col-md-4">
                                <label class="form-label">Senha Atual</label>
                                <input type="password" name="senha_atual" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nova Senha</label>
                                <input type="password" name="nova_senha" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirmar Nova Senha</label>
                                <input type="password" name="confirma_senha" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-card mt-4">
                <h6 class="mb-3"><i class="fas fa-building me-2"></i>Dados da Empresa</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Razão Social</label>
                        <input type="text" name="razao_social" class="form-control" value="<?= htmlspecialchars($admin['razao_social'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome Fantasia</label>
                        <input type="text" name="nome_fantasia" class="form-control" value="<?= htmlspecialchars($admin['nome_fantasia'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-control mask-cnpj" value="<?= htmlspecialchars($admin['cnpj'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Inscrição Estadual</label>
                        <input type="text" name="inscricao_estadual" class="form-control" value="<?= htmlspecialchars($admin['inscricao_estadual'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Inscrição Municipal</label>
                        <input type="text" name="inscricao_municipal" class="form-control" value="<?= htmlspecialchars($admin['inscricao_municipal'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Telefone Comercial</label>
                        <input type="text" name="telefone_comercial" class="form-control mask-phone" value="<?= htmlspecialchars($admin['telefone_comercial'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">E-mail Comercial</label>
                        <input type="email" name="email_comercial" class="form-control" value="<?= htmlspecialchars($admin['email_comercial'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep_empresa" class="form-control mask-cep" value="<?= htmlspecialchars($admin['cep'] ?? '') ?>" onblur="buscarCEP(this.value, {logradouro:'logradouro',bairro:'bairro',cidade:'cidade',estado:'estado'})">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Logradouro</label>
                        <input type="text" name="logradouro" id="logradouro" class="form-control" value="<?= htmlspecialchars($admin['logradouro'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Nº</label>
                        <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($admin['numero'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($admin['complemento'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control" value="<?= htmlspecialchars($admin['bairro'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" value="<?= htmlspecialchars($admin['cidade'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">UF</label>
                        <input type="text" name="estado" id="estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($admin['estado'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="fas fa-save me-1"></i> Salvar Dados da Empresa
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>