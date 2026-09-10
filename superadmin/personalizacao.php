<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'logo_login') {
        if (isset($_FILES['logo_login']) && $_FILES['logo_login']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo_login']['name'], PATHINFO_EXTENSION));
            $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (in_array($ext, $permitidos)) {
                $nome = 'logo_login_' . time() . '.' . $ext;
                $destino = __DIR__ . '/../assets/img/' . $nome;

                if (move_uploaded_file($_FILES['logo_login']['tmp_name'], $destino)) {
                    $logoAntiga = getConfigGlobal('logo_login');
                    $logoAntigaPath = __DIR__ . '/..' . preg_replace('#^/cobranca#', '', (string)$logoAntiga);
                    $logoAntigaPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoAntigaPath);
                    if ($logoAntiga && strpos($logoAntiga, 'http') !== 0 && $logoAntigaPath !== __DIR__ . '/../assets/img/' . $nome && file_exists($logoAntigaPath)) {
                        unlink($logoAntigaPath);
                    }

                    salvarConfigGlobal('logo_login', '/cobranca/assets/img/' . $nome);

                    $mensagem = 'Logo de login atualizada com sucesso!';
                    $tipo = 'success';
                } else {
                    $mensagem = 'Falha ao salvar o arquivo.';
                    $tipo = 'danger';
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

$logoLogin = getLogoLoginGlobal();

$pageTitle = 'Personalização';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-image me-1"></i> Personalização</h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['super_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['super_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
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
            <!-- Logo das Telas de Login -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-sign-in-alt me-2"></i>Logo das Telas de Login</h6>
                    <p class="text-muted mb-3" style="font-size:0.85rem;">Esta logo aparece no login do painel admin, recuperação de senha, login por certificado e PDFs gerados.</p>

                    <?php if ($logoLogin): ?>
                        <div class="text-center mb-3">
                            <img src="<?= htmlspecialchars($logoLogin) ?>" alt="Logo de Login Atual" style="max-width: 250px;">
                            <br><small class="text-muted">Logo atual (Logins)</small>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo_login">
                        <div class="mb-3">
                            <input type="file" name="logo_login" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo de Login
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>