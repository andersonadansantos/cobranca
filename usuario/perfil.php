<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();

if (isMobileDevice()) {
    header('Location: /cobranca/app/perfil.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$mensagem = '';
$tipo = '';

// Buscar dados do cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$userId]);
$cliente = $stmt->fetch();

// Atualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    try {
        $stmt = $pdo->prepare("UPDATE clientes SET email=?, telefone=?, celular=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id=?");
        $stmt->execute([$email, $telefone, $celular, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $userId]);

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = __DIR__ . '/../assets/img/avatars';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'user_' . $userId . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $filename)) {
                    $avatarPath = '/cobranca/assets/img/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE clientes SET avatar=? WHERE id=?");
                    $stmt->execute([$avatarPath, $userId]);
                    $_SESSION['user_avatar'] = $avatarPath;
                }
            }
        }

        $mensagem = 'Perfil atualizado com sucesso!';
        $tipo = 'success';
        
        // Recarregar dados
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$userId]);
        $cliente = $stmt->fetch();
        
    } catch (PDOException $e) {
        $mensagem = 'Erro ao atualizar: ' . $e->getMessage();
        $tipo = 'danger';
    }
}

$pageTitle = 'Meu Perfil';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_usuario.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Meu Perfil</h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/usuario/perfil.php"><i class="fas fa-user-edit me-2"></i>Meu Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/usuario/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
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

        <div class="row">
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-id-card me-2"></i>Dados Pessoais</h6>
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="rounded-circle" width="80" height="80" style="object-fit:cover; border: 3px solid var(--cor-primaria);">
                    </div>
                    <div class="mb-3 p-3 bg-light rounded">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Nome / Razão Social</small>
                                <strong><?= htmlspecialchars($cliente['nome_razao']) ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">CPF/CNPJ</small>
                                <strong><?= htmlspecialchars($cliente['cpf_cnpj']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Nome e CPF/CNPJ não podem ser alterados pelo cliente.</small>
                </div>
            </div>
            
        </div>

        <div class="form-card mt-4">
            <h6 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Endereço e Contato</h6>
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control mask-phone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Celular</label>
                        <input type="text" name="celular" class="form-control mask-phone" value="<?= htmlspecialchars($cliente['celular'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" class="form-control mask-cep" id="cep" value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>" onblur="buscarCEP(this.value, {logradouro:'logradouro',bairro:'bairro',cidade:'cidade',estado:'estado'})">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Logradouro</label>
                        <input type="text" name="logradouro" id="logradouro" class="form-control" value="<?= htmlspecialchars($cliente['logradouro'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Nº</label>
                        <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($cliente['numero'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($cliente['complemento'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control" value="<?= htmlspecialchars($cliente['bairro'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">UF</label>
                        <input type="text" name="estado" id="estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($cliente['estado'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Foto de Perfil</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="fas fa-save me-1"></i> Salvar Alterações
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
