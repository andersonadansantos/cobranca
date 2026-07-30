<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $slot = intval($_POST['slot'] ?? 0);
    $tipoBanner = $_POST['tipo'] ?? '';

    if ($acao === 'remover' && $slot >= 1 && $slot <= 3 && in_array($tipoBanner, ['desktop','mobile'])) {
        $chave = 'banner_' . $tipoBanner . '_' . $slot;
        $antigo = getConfig($chave, '');
        if ($antigo && file_exists(__DIR__ . '/..' . $antigo)) {
            unlink(__DIR__ . '/..' . $antigo);
        }
        saveConfig($chave, '');
        $mensagem = 'Banner ' . $tipoBanner . ' slot ' . $slot . ' removido.';
        $tipo = 'success';
    }

    if ($acao === 'upload' && $slot >= 1 && $slot <= 3 && in_array($tipoBanner, ['desktop','mobile'])) {
        $inputName = 'banner_' . $tipoBanner . '_' . $slot;
        $file = $_FILES[$inputName] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = __DIR__ . '/../assets/img/banners';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = $tipoBanner . '_' . $slot . '.' . $ext;
                $chave = 'banner_' . $tipoBanner . '_' . $slot;
                $antigo = getConfig($chave, '');
                if ($antigo && file_exists(__DIR__ . '/..' . $antigo)) {
                    unlink(__DIR__ . '/..' . $antigo);
                }
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                    saveConfig($chave, '/cobranca/assets/img/banners/' . $filename);
                    $mensagem = 'Banner ' . $tipoBanner . ' slot ' . $slot . ' salvo com sucesso!';
                    $tipo = 'success';
                }
            } else {
                $mensagem = 'Formato inválido. Use JPG, PNG, GIF ou WebP.';
                $tipo = 'danger';
            }
        }
    }
}

$slots = [
    1 => getConfig('banner_desktop_1', ''),
    2 => getConfig('banner_desktop_2', ''),
    3 => getConfig('banner_desktop_3', ''),
];
$slotsMobile = [
    1 => getConfig('banner_mobile_1', ''),
    2 => getConfig('banner_mobile_2', ''),
    3 => getConfig('banner_mobile_3', ''),
];

$pageTitle = 'Banners';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Banners</h5>
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

        <p class="text-muted mb-4">Os banners serão exibidos no painel do usuário e podem ser utilizados para divulgar informações, promoções, novidades ou avisos importantes.</p>

        <div class="row">
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-desktop me-2"></i>Banners Desktop <small class="text-muted">(até 3)</small></h6>
                    <small class="text-muted d-block mb-3">Imagem 1000×200 pixels</small>
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="mb-3 p-3 bg-light rounded">
                            <strong>Slot <?= $i ?></strong>
                            <?php if ($slots[$i]): ?>
                                <div class="text-center mb-2">
                                    <img src="<?= htmlspecialchars($slots[$i]) ?>" alt="Banner Desktop <?= $i ?>" style="max-width:100%; height:auto; border:1px solid #dee2e6; border-radius:6px;">
                                </div>
                                <form method="POST" class="text-center">
                                    <input type="hidden" name="acao" value="remover">
                                    <input type="hidden" name="tipo" value="desktop">
                                    <input type="hidden" name="slot" value="<?= $i ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Remover</button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted mb-2 small">Vazio.</p>
                            <?php endif; ?>
                            <form method="POST" enctype="multipart/form-data" class="mt-2">
                                <input type="hidden" name="acao" value="upload">
                                <input type="hidden" name="tipo" value="desktop">
                                <input type="hidden" name="slot" value="<?= $i ?>">
                                <div class="input-group input-group-sm">
                                    <input type="file" name="banner_desktop_<?= $i ?>" class="form-control" accept="image/*">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i></button>
                                </div>
                            </form>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-mobile-alt me-2"></i>Banners Mobile <small class="text-muted">(até 3)</small></h6>
                    <small class="text-muted d-block mb-3">Imagem 600×400 pixels</small>
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="mb-3 p-3 bg-light rounded">
                            <strong>Slot <?= $i ?></strong>
                            <?php if ($slotsMobile[$i]): ?>
                                <div class="text-center mb-2">
                                    <img src="<?= htmlspecialchars($slotsMobile[$i]) ?>" alt="Banner Mobile <?= $i ?>" style="max-width:100%; height:auto; border:1px solid #dee2e6; border-radius:6px;">
                                </div>
                                <form method="POST" class="text-center">
                                    <input type="hidden" name="acao" value="remover">
                                    <input type="hidden" name="tipo" value="mobile">
                                    <input type="hidden" name="slot" value="<?= $i ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Remover</button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted mb-2 small">Vazio.</p>
                            <?php endif; ?>
                            <form method="POST" enctype="multipart/form-data" class="mt-2">
                                <input type="hidden" name="acao" value="upload">
                                <input type="hidden" name="tipo" value="mobile">
                                <input type="hidden" name="slot" value="<?= $i ?>">
                                <div class="input-group input-group-sm">
                                    <input type="file" name="banner_mobile_<?= $i ?>" class="form-control" accept="image/*">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i></button>
                                </div>
                            </form>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>