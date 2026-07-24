<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

// Salvar personalização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'cores') {
        $corPrimaria = trim($_POST['cor_primaria'] ?? '#0d6efd');
        $corSecundaria = trim($_POST['cor_secundaria'] ?? '#6c757d');
        $corFundo = trim($_POST['cor_fundo'] ?? '#f8f9fa');
        $nomeSistema = trim($_POST['nome_sistema'] ?? 'Sistema de Cobrança');
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
        $stmt->execute([$corPrimaria, 'cor_primaria']);
        $stmt->execute([$corSecundaria, 'cor_secundaria']);
        $stmt->execute([$corFundo, 'cor_fundo']);
        $stmt->execute([$nomeSistema, 'nome_sistema']);
        
        $mensagem = 'Personalização salva com sucesso!';
        $tipo = 'success';
    }
    
    if ($acao === 'senha') {
        $senhaAtual = trim($_POST['senha_atual'] ?? '');
        $novaSenha = trim($_POST['nova_senha'] ?? '');
        $confirmaSenha = trim($_POST['confirma_senha'] ?? '');
        
        if (empty($senhaAtual) || empty($novaSenha)) {
            $mensagem = 'Preencha todos os campos de senha.';
            $tipo = 'danger';
        } elseif ($novaSenha !== $confirmaSenha) {
            $mensagem = 'As senhas não conferem.';
            $tipo = 'danger';
        } elseif (strlen($novaSenha) < 6) {
            $mensagem = 'A nova senha deve ter no mínimo 6 caracteres.';
            $tipo = 'danger';
        } else {
            $pdo = getConnection();
            $stmt = $pdo->prepare("SELECT id, senha FROM administradores WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            
            if ($admin && (password_verify($senhaAtual, $admin['senha']) || md5($senhaAtual) === $admin['senha'])) {
                $stmt = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                $stmt->execute([password_hash($novaSenha, PASSWORD_BCRYPT), $_SESSION['admin_id']]);
                $mensagem = 'Senha alterada com sucesso!';
                $tipo = 'success';
            } else {
                $mensagem = 'Senha atual incorreta.';
                $tipo = 'danger';
            }
        }
    }
    
    if ($acao === 'logo') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (in_array($ext, $permitidos)) {
                $nome = 'logo_' . time() . '.' . $ext;
                $destino = __DIR__ . '/../assets/img/' . $nome;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destino)) {
                    // Remover logo anterior
                    $pdo = getConnection();
                    $logoAntiga = getConfig('logo_empresa');
                    $logoAntigaPath = __DIR__ . '/..' . str_replace('/cobranca', '', $logoAntiga);
                    if ($logoAntiga && file_exists($logoAntigaPath)) {
                        unlink($logoAntigaPath);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'logo_empresa'");
                    $stmt->execute(['/cobranca/assets/img/' . $nome]);
                    
                    $mensagem = 'Logo atualizada com sucesso!';
                    $tipo = 'success';
                }
            } else {
                $mensagem = 'Formato de arquivo não permitido. Use: JPG, PNG, GIF, SVG ou WEBP.';
                $tipo = 'danger';
            }
        } else {
            $mensagem = 'Selecione uma imagem para upload.';
            $tipo = 'danger';
        }
    }
    
    if ($acao === 'logo_login') {
        if (isset($_FILES['logo_login']) && $_FILES['logo_login']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo_login']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (in_array($ext, $permitidos)) {
                $nome = 'logo_login_' . time() . '.' . $ext;
                $destino = __DIR__ . '/../assets/img/' . $nome;
                
                if (move_uploaded_file($_FILES['logo_login']['tmp_name'], $destino)) {
                    $pdo = getConnection();
                    $logoAntiga = getConfig('logo_login');
                    $logoAntigaPath = __DIR__ . '/..' . str_replace('/cobranca', '', $logoAntiga);
                    if ($logoAntiga && file_exists($logoAntigaPath)) {
                        unlink($logoAntigaPath);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('logo_login', ?) ON DUPLICATE KEY UPDATE valor = ?");
                    $stmt->execute(['/cobranca/assets/img/' . $nome, '/cobranca/assets/img/' . $nome]);
                    
                    $mensagem = 'Logo de login atualizada com sucesso!';
                    $tipo = 'success';
                }
            } else {
                $mensagem = 'Formato de arquivo não permitido. Use: JPG, PNG, GIF, SVG ou WEBP.';
                $tipo = 'danger';
            }
        } else {
            $mensagem = 'Selecione uma imagem para upload.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'logo_mobile') {
        if (isset($_FILES['logo_mobile']) && $_FILES['logo_mobile']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo_mobile']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (in_array($ext, $permitidos)) {
                $nome = 'logo_mobile_' . time() . '.' . $ext;
                $destino = __DIR__ . '/../assets/img/' . $nome;
                
                if (move_uploaded_file($_FILES['logo_mobile']['tmp_name'], $destino)) {
                    $pdo = getConnection();
                    $logoAntiga = getConfig('logo_mobile');
                    $logoAntigaPath = __DIR__ . '/..' . str_replace('/cobranca', '', $logoAntiga);
                    if ($logoAntiga && file_exists($logoAntigaPath)) {
                        unlink($logoAntigaPath);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('logo_mobile', ?) ON DUPLICATE KEY UPDATE valor = ?");
                    $stmt->execute(['/cobranca/assets/img/' . $nome, '/cobranca/assets/img/' . $nome]);
                    
                    $mensagem = 'Logo mobile atualizada com sucesso!';
                    $tipo = 'success';
                }
            } else {
                $mensagem = 'Formato de arquivo não permitido. Use: JPG, PNG, GIF, SVG ou WEBP.';
                $tipo = 'danger';
            }
        } else {
            $mensagem = 'Selecione uma imagem para upload.';
            $tipo = 'danger';
        }
    }
}

$config = getAllConfig();

$pageTitle = 'Personalização';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Personalização</h5>
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

        <div class="row g-4">
            <!-- Cores e Nome -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-palette me-2"></i>Cores e Identidade</h6>
                    <form method="POST">
                        <input type="hidden" name="acao" value="cores">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nome do Sistema</label>
                                <input type="text" name="nome_sistema" class="form-control" value="<?= htmlspecialchars($config['nome_sistema'] ?? 'Sistema de Cobrança') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor Primária</label>
                                <input type="color" name="cor_primaria" class="form-control form-control-color w-100" value="<?= htmlspecialchars($config['cor_primaria'] ?? '#0d6efd') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor Secundária</label>
                                <input type="color" name="cor_secundaria" class="form-control form-control-color w-100" value="<?= htmlspecialchars($config['cor_secundaria'] ?? '#6c757d') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Fundo</label>
                                <input type="color" name="cor_fundo" class="form-control form-control-color w-100" value="<?= htmlspecialchars($config['cor_fundo'] ?? '#f8f9fa') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-1"></i> Salvar Cores
                        </button>
                    </form>
                </div>
            </div>

            <!-- Logo -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-image me-2"></i>Logo da Empresa</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo">
                        
                        <?php if (!empty($config['logo_empresa'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($config['logo_empresa']) ?>" alt="Logo Atual" style="max-width: 250px;">
                                <br><small class="text-muted">Logo atual (Dashboards)</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo
                        </button>
                    </form>
                </div>
            </div>

            <!-- Logo Login -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-sign-in-alt me-2"></i>Logo das Telas de Login</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo_login">
                        
                        <?php if (!empty($config['logo_login'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($config['logo_login']) ?>" alt="Logo Login Atual" style="max-width: 250px;">
                                <br><small class="text-muted">Logo atual (Logins)</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" name="logo_login" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo de Login
                        </button>
                    </form>
                </div>
            </div>

            <!-- Logo Mobile -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-mobile-alt me-2"></i>Logo Versão Mobile</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo_mobile">
                        
                        <?php if (!empty($config['logo_mobile'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($config['logo_mobile']) ?>" alt="Logo Mobile Atual" style="max-width: 200px;">
                                <br><small class="text-muted">Logo atual (App Mobile)</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" name="logo_mobile" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo Mobile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Senha -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-lock me-2"></i>Alterar Senha</h6>
                    <form method="POST">
                        <input type="hidden" name="acao" value="senha">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Senha Atual</label>
                                <input type="password" name="senha_atual" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nova Senha</label>
                                <input type="password" name="nova_senha" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar Nova Senha</label>
                                <input type="password" name="confirma_senha" class="form-control" minlength="6" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning mt-3">
                            <i class="fas fa-key me-1"></i> Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
