<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/cobranca/admin';
$planoExpirado = function_exists('adminPlanoExpirado') ? adminPlanoExpirado() : false;
$adminDesativado = function_exists('adminEstaAtivo') ? !adminEstaAtivo() : false;
$acessoRestrito = $planoExpirado || $adminDesativado;
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logo): ?>
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-width: 150px;">
        <?php endif; ?>
        <h4><i class="fas fa-shield-halved me-2"></i><?= t('layout.admin_area') ?></h4>
    </div>
    <nav class="sidebar-nav">
        <?php if (!$acessoRestrito): ?>
        <div class="nav-section"><?= t('layout.menu_label') ?></div>
        <a href="<?= $basePath ?>/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> <?= t('nav.painel_geral') ?>
            <?php if (($totalAlerta ?? 0) > 0): ?>
                <span class="badge bg-danger ms-auto" style="font-size:0.65rem;"><?= $totalAlerta ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/cadastro.php" class="nav-link <?= $currentPage === 'cadastro' ? 'active' : '' ?>">
            <i class="fas fa-building"></i> <?= t('nav.cadastro') ?>
        </a>
        <a href="<?= $basePath ?>/emissao.php" class="nav-link <?= $currentPage === 'emissao' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> <?= t('nav.emissao') ?>
        </a>
        <a href="<?= $basePath ?>/livro_caixa.php" class="nav-link <?= $currentPage === 'livro_caixa' ? 'active' : '' ?>">
            <i class="fas fa-book"></i> <?= t('nav.livro_caixa') ?>
        </a>
        <a href="<?= $basePath ?>/inadimplencia.php" class="nav-link <?= $currentPage === 'inadimplencia' ? 'active' : '' ?>">
            <i class="fas fa-exclamation-triangle"></i> <?= t('nav.inadimplencia') ?>
        </a>

        <div class="nav-section"><?= t('nav.configuracoes') ?></div>
        <a href="#" class="nav-link sidebar-toggle-config" onclick="var el=document.getElementById('configSubmenu');el.style.display=el.style.display==='none'?'block':'none';var icon=this.querySelector('.fa-chevron-down,.fa-chevron-up');if(icon){icon.classList.toggle('fa-chevron-down');icon.classList.toggle('fa-chevron-up');}return false;">
            <i class="fas fa-cog"></i> <?= t('nav.configuracoes') ?> <i class="fas fa-chevron-down ms-auto" style="font-size:0.65rem;"></i>
        </a>
        <div id="configSubmenu" style="display:<?= (in_array($currentPage, ['config_api','personalizacao','banners','envios','template_email','template_whats','config_financeiro','whatsapp'])) ? 'block' : 'none' ?>;">
            <a href="<?= $basePath ?>/config_api.php" class="nav-link <?= $currentPage === 'config_api' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fas fa-key"></i> <?= t('nav.api_pagamento') ?>
            </a>
            <a href="<?= $basePath ?>/personalizacao.php" class="nav-link <?= $currentPage === 'personalizacao' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fas fa-palette"></i> <?= t('nav.personalizacao') ?>
            </a>
            <a href="<?= $basePath ?>/banners.php" class="nav-link <?= $currentPage === 'banners' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fas fa-image"></i> <?= t('nav.banners') ?>
            </a>
            <a href="<?= $basePath ?>/envios.php" class="nav-link <?= $currentPage === 'envios' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fas fa-paper-plane"></i> <?= t('nav.config_envios') ?>
            </a>
            <a href="<?= $basePath ?>/template_email.php" class="nav-link <?= $currentPage === 'template_email' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fas fa-envelope-open-text"></i> <?= t('nav.template_email') ?>
            </a>
            <a href="<?= $basePath ?>/template_whats.php" class="nav-link <?= $currentPage === 'template_whats' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fab fa-whatsapp" style="color:#25D366;"></i> <?= t('nav.template_whats') ?>
            </a>
            <a href="<?= $basePath ?>/config_financeiro.php" class="nav-link <?= $currentPage === 'config_financeiro' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fas fa-headset"></i> <?= t('nav.contato_financeiro') ?>
            </a>
            <a href="<?= $basePath ?>/whatsapp.php" class="nav-link <?= $currentPage === 'whatsapp' ? 'active' : '' ?>" style="padding-left:2rem;">
                <i class="fab fa-whatsapp" style="color:#25D366;"></i> <?= t('nav.config_whatsapp') ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($acessoRestrito): ?>
        <div class="nav-section"><?= $adminDesativado ? t('layout.conta_desativada') : t('layout.plano_expirado') ?></div>
        <div style="margin:0 16px 14px;padding:10px 12px;border-radius:8px;background:rgba(220,53,69,.08);border:1px solid rgba(220,53,69,.25);font-size:.75rem;color:#b02a37;">
            <i class="fas <?= $adminDesativado ? 'fa-user-slash' : 'fa-exclamation-triangle' ?> me-1"></i>
            <?= $adminDesativado
                ? t('layout.conta_desativada_msg')
                : t('layout.plano_vencido_msg') ?>
        </div>
        <?php endif; ?>

        <div class="nav-section"><?= t('layout.conta') ?></div>
        <a href="<?= $basePath ?>/meu_plano.php" class="nav-link <?= $currentPage === 'meu_plano' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> <?= t('nav.meu_plano') ?>
        </a>
        <a href="<?= $basePath ?>/minhas_faturas.php" class="nav-link <?= $currentPage === 'minhas_faturas' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> <?= t('nav.minhas_faturas') ?>
        </a>
        <?php if (!$acessoRestrito): ?>
        <a href="<?= $basePath ?>/usuarios.php" class="nav-link <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> <?= t('nav.usuarios_admin') ?>
        </a>
        <a href="<?= $basePath ?>/perfil.php" class="nav-link <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <i class="fas fa-user-edit"></i> <?= t('nav.meu_perfil') ?>
        </a>
        <?php endif; ?>
        <?php if (isset($_SESSION['impersonando']) && $_SESSION['impersonando']): ?>
        <div class="nav-section"><?= t('layout.super_admin') ?></div>
        <a href="/cobranca/superadmin/logar_como.php?voltar=1" class="nav-link" style="color:var(--cor-primaria);font-weight:600;">
            <i class="fas fa-user-shield"></i> <?= t('nav.voltar_super') ?>
        </a>
        <?php endif; ?>
        <a href="<?= $basePath ?>/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> <?= t('nav.sair') ?>
        </a>
    </nav>
</aside>