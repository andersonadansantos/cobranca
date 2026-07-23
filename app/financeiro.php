<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$config = getAllConfig();
$whatsapp = $config['financeiro_whatsapp'] ?? '';
$email = $config['financeiro_email'] ?? '';
$fone = $config['financeiro_fone'] ?? '';

$logo = getConfig('logo_mobile', '') ?: getLogo();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#6C5CE7">
    <title>Falar com Financeiro</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="/cobranca/assets/img/avatars/user.svg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <div class="app-topbar">
            <a href="dashboard.php" style="text-decoration:none; color:var(--app-text); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-arrow-left"></i>
                <span style="font-size:0.9rem; font-weight:600;">Voltar</span>
            </a>
            <span style="font-weight:700;">Financeiro</span>
        </div>

        <div class="app-content">
            <div class="app-perfil-header app-animate">
                <div style="width:70px; height:70px; border-radius:50%; background:var(--app-primary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                    <i class="fas fa-headset" style="font-size:28px; color:#fff;"></i>
                </div>
                <div class="app-perfil-name">Fale com o Financeiro</div>
                <div class="app-perfil-email">Escolha o meio de contato abaixo</div>
            </div>

            <?php if ($whatsapp): ?>
            <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank" style="display:block; margin-bottom:12px; text-decoration:none !important; color:inherit;">
                <div class="app-perfil-card app-animate" style="cursor:pointer; margin-bottom:0;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:48px; height:48px; border-radius:12px; background:#25d366; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fab fa-whatsapp" style="font-size:22px; color:#fff;"></i>
                        </div>
                            <div style="flex:1; text-decoration:none;">
                            <div style="font-weight:600; color:#25d366; text-decoration:none;">WhatsApp</div>
                            <div style="font-size:0.75rem; color:var(--app-text-muted); text-decoration:none;">Clique para conversar</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:var(--app-text-muted); font-size:0.8rem;"></i>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($email): ?>
            <a href="mailto:<?= htmlspecialchars($email) ?>" style="display:block; margin-bottom:12px; text-decoration:none !important; color:inherit;">
                <div class="app-perfil-card app-animate" style="cursor:pointer; margin-bottom:0;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:48px; height:48px; border-radius:12px; background:var(--app-primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fas fa-envelope" style="font-size:22px; color:#fff;"></i>
                        </div>
                        <div style="flex:1; text-decoration:none;">
                            <div style="font-weight:600; color:var(--app-primary); text-decoration:none;">E-mail</div>
                            <div style="font-size:0.75rem; color:var(--app-text-muted); text-decoration:none;">Clique para enviar e-mail</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:var(--app-text-muted); font-size:0.8rem;"></i>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if ($fone): ?>
            <a href="tel:<?= htmlspecialchars($fone) ?>" style="display:block; margin-bottom:12px; text-decoration:none !important; color:inherit;">
                <div class="app-perfil-card app-animate" style="cursor:pointer; margin-bottom:0;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:48px; height:48px; border-radius:12px; background:#0dcaf0; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fas fa-phone" style="font-size:22px; color:#fff;"></i>
                        </div>
                        <div style="flex:1; text-decoration:none;">
                            <div style="font-weight:600; color:#0dcaf0; text-decoration:none;">Telefone</div>
                            <div style="font-size:0.75rem; color:var(--app-text-muted); text-decoration:none;">Clique para ligar</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:var(--app-text-muted); font-size:0.8rem;"></i>
                    </div>
                </div>
            </a>
            <?php endif; ?>

            <?php if (!$whatsapp && !$email && !$fone): ?>
            <div class="app-perfil-card app-animate" style="text-align:center; padding:32px 16px;">
                <i class="fas fa-info-circle" style="font-size:28px; color:var(--app-text-muted); margin-bottom:12px; display:block;"></i>
                <div style="color:var(--app-text-muted);">Nenhum contato financeiro disponível no momento.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align:center; padding:16px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a>
    </div>

    <nav class="app-bottom-nav">
        <a href="dashboard.php" class="app-nav-item">
            <i class="fas fa-home"></i>
            <span>Faturas</span>
        </a>
        <a href="financeiro.php" class="app-nav-item active">
            <i class="fas fa-headset"></i>
            <span>Financeiro</span>
        </a>
        <a href="perfil.php" class="app-nav-item">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
        <a href="logout.php" class="app-nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </nav>
</body>
</html>
