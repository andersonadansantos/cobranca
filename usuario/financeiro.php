<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$config = getAllConfig();
$whatsapp = $config['financeiro_whatsapp'] ?? '';
$email = $config['financeiro_email'] ?? '';
$fone = $config['financeiro_fone'] ?? '';
$nomeSistema = getNomeSistema();

$pageTitle = 'Falar com Financeiro';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_usuario.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Falar com Financeiro</h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/usuario/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/usuario/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="text-center mb-4">
                    <div style="width:70px; height:70px; border-radius:50%; background:var(--cor-primaria); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                        <i class="fas fa-headset" style="font-size:28px; color:#fff;"></i>
                    </div>
                    <h5>Fale com o Financeiro</h5>
                    <p class="text-muted">Escolha o meio de contato abaixo para falar com nossa equipe financeira.</p>
                </div>

                <?php if ($whatsapp): ?>
                <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank" style="display:block; margin-bottom:16px; text-decoration:none !important; color:inherit;">
                    <div class="card" style="border:none; box-shadow:0 2px 12px rgba(0,0,0,0.08); transition:transform 0.2s; cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                        <div class="card-body d-flex align-items-center gap-3 p-3">
                            <div style="width:50px; height:50px; border-radius:12px; background:#25d366; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <i class="fab fa-whatsapp" style="font-size:24px; color:#fff;"></i>
                            </div>
                            <div style="text-decoration:none;">
                                <h6 class="mb-0" style="color:#25d366; text-decoration:none;">WhatsApp</h6>
                                <small class="text-muted" style="text-decoration:none;">Clique para conversar pelo WhatsApp</small>
                            </div>
                            <i class="fas fa-arrow-right ms-auto text-muted"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($email): ?>
                <a href="mailto:<?= htmlspecialchars($email) ?>" style="display:block; margin-bottom:16px; text-decoration:none !important; color:inherit;">
                    <div class="card" style="border:none; box-shadow:0 2px 12px rgba(0,0,0,0.08); transition:transform 0.2s; cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                        <div class="card-body d-flex align-items-center gap-3 p-3">
                            <div style="width:50px; height:50px; border-radius:12px; background:var(--cor-primaria); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <i class="fas fa-envelope" style="font-size:24px; color:#fff;"></i>
                            </div>
                            <div style="text-decoration:none;">
                                <h6 class="mb-0" style="color:var(--cor-primaria); text-decoration:none;">E-mail</h6>
                                <small class="text-muted" style="text-decoration:none;">Clique para enviar um e-mail</small>
                            </div>
                            <i class="fas fa-arrow-right ms-auto text-muted"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($fone): ?>
                <a href="tel:<?= htmlspecialchars($fone) ?>" style="display:block; margin-bottom:16px; text-decoration:none !important; color:inherit;">
                    <div class="card" style="border:none; box-shadow:0 2px 12px rgba(0,0,0,0.08); transition:transform 0.2s; cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                        <div class="card-body d-flex align-items-center gap-3 p-3">
                            <div style="width:50px; height:50px; border-radius:12px; background:#0dcaf0; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <i class="fas fa-phone" style="font-size:24px; color:#fff;"></i>
                            </div>
                            <div style="text-decoration:none;">
                                <h6 class="mb-0" style="color:#0dcaf0; text-decoration:none;">Telefone</h6>
                                <small class="text-muted" style="text-decoration:none;">Clique para ligar agora</small>
                            </div>
                            <i class="fas fa-arrow-right ms-auto text-muted"></i>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (!$whatsapp && !$email && !$fone): ?>
                <div class="card" style="border:none; box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Nenhum contato financeiro configurado no momento.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
