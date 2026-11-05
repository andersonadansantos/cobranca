<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/cobranca/usuario';
$templateAtual = function_exists('getAdminTemplate') ? getAdminTemplate() : 'fintech';

$logo = function_exists('getLogoPainelUsuario') ? getLogoPainelUsuario() : ($logo ?? '');
$nomeCliente = $_SESSION['user_nome'] ?? 'Cliente';
$emailCliente = $_SESSION['user_email'] ?? '';
$avatarCliente = function_exists('getUserAvatarUrl') ? getUserAvatarUrl() : ($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg');
$temContato = getConfig('financeiro_whatsapp') || getConfig('financeiro_email') || getConfig('financeiro_fone');
$whatsFinanceiro = getConfig('financeiro_whatsapp', '');
$nomeSistema = function_exists('getNomeSistema') ? getNomeSistema() : 'Cobrança';
?>

<aside class="fintech-sidebar" id="fintechSidebar">
    <div class="fintech-sidebar-header">
        <a href="<?= $basePath ?>/index.php" class="fintech-brand">
            <?php if (!empty($logo)): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" class="fintech-brand-logo">
            <?php else: ?>
                <div class="d-flex align-items-center gap-2">
                    <div style="width:34px;height:34px;border-radius:10px;background:var(--fintech-primary);display:flex;align-items:center;justify-content:center;color:#fff;">
                        <i class="fas fa-wallet" style="font-size:16px;"></i>
                    </div>
                    <span style="font-weight:800;font-size:16px;letter-spacing:-0.02em;color:var(--fintech-primary);"><?= htmlspecialchars($nomeSistema) ?></span>
                </div>
            <?php endif; ?>
        </a>
        <span class="fintech-brand-badge" style="background:#ecfdf5;color:#059669;border-color:#a7f3d0;">Cliente</span>
    </div>

    <nav class="fintech-sidebar-nav">
        <div class="fintech-nav-category"><?= t('layout.menu') ?></div>
        
        <a href="<?= $basePath ?>/index.php" class="fintech-nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i>
            <span><?= t('nav.painel_geral') ?></span>
        </a>

        <a href="<?= $basePath ?>/perfil.php" class="fintech-nav-item <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i>
            <span><?= t('nav.meu_perfil') ?></span>
        </a>

        <?php if ($temContato): ?>
        <a href="<?= $basePath ?>/financeiro.php" class="fintech-nav-item <?= $currentPage === 'financeiro' ? 'active' : '' ?>">
            <i class="bi bi-headset"></i>
            <span><?= t('nav.contato_financeiro') ?></span>
        </a>
        <?php endif; ?>

        <div class="fintech-nav-category"><?= t('layout.conta') ?></div>

        <a href="<?= $basePath ?>/logout.php" class="fintech-nav-item text-danger mt-2">
            <i class="bi bi-box-arrow-right text-danger"></i>
            <span><?= t('nav.sair') ?></span>
        </a>
    </nav>
</aside>

<div class="fintech-main">
    <header class="fintech-topbar">
        <div class="fintech-topbar-left">
            <button class="btn btn-fintech-icon d-lg-none" id="fintechSidebarToggle" type="button" aria-label="Abrir menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="fintech-breadcrumb">
                <span class="fintech-breadcrumb-sub"><?= t('layout.area_cliente') ?></span>
                <h1 class="fintech-breadcrumb-title"><?= htmlspecialchars($pageTitle ?? 'Minhas Faturas') ?></h1>
            </div>
        </div>

        <div class="fintech-topbar-right">
            <?php if (!empty($whatsFinanceiro)): ?>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsFinanceiro) ?>" target="_blank" class="fintech-support-btn d-none d-sm-inline-flex">
                <i class="bi bi-headset"></i>
                <span>Suporte Financeiro</span>
            </a>
            <?php endif; ?>

            <!-- Dropdown Usuário com Avatar Funcional -->
            <div class="dropdown">
                <a href="#" class="fintech-admin-dropdown dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= htmlspecialchars($avatarCliente) ?>" alt="Avatar" class="fintech-admin-avatar" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/user.svg';">
                    <div class="fintech-admin-info d-none d-md-flex">
                        <span class="fintech-admin-name"><?= htmlspecialchars($nomeCliente) ?></span>
                        <span class="fintech-admin-role"><?= htmlspecialchars($emailCliente ?: 'Cliente') ?></span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius:12px;border:1px solid var(--fintech-border);">
                    <li class="px-3 py-2 border-bottom">
                        <strong class="d-block text-dark"><?= htmlspecialchars($nomeCliente) ?></strong>
                        <small class="text-muted"><?= htmlspecialchars($emailCliente) ?></small>
                    </li>
                    <li><a class="dropdown-item py-2" href="<?= $basePath ?>/perfil.php"><i class="bi bi-person me-2"></i><?= t('nav.meu_perfil') ?></a></li>
                    <?php if ($temContato): ?>
                    <li><a class="dropdown-item py-2" href="<?= $basePath ?>/financeiro.php"><i class="bi bi-headset me-2"></i><?= t('nav.contato_financeiro') ?></a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="<?= $basePath ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= t('nav.sair') ?></a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="fintech-content">

