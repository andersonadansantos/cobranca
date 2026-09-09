<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/cobranca/superadmin';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logo): ?>
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-width: 150px;">
        <?php endif; ?>
        <h4><i class="fas fa-crown me-2"></i><?= t('layout.super_admin') ?></h4>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section"><?= t('layout.menu_label') ?></div>
        <a href="<?= $basePath ?>/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> <?= t('nav.dashboard') ?>
        </a>
        <a href="<?= $basePath ?>/cadastros.php" class="nav-link <?= $currentPage === 'cadastros' ? 'active' : '' ?>">
            <i class="fas fa-building"></i> <?= t('nav.cadastro') ?>
        </a>
        <a href="<?= $basePath ?>/api_admins.php" class="nav-link <?= $currentPage === 'api_admins' ? 'active' : '' ?>">
            <i class="fab fa-whatsapp" style="color:#25D366;"></i> <?= t('nav.api_admins') ?>
        </a>
        <a href="<?= $basePath ?>/planos.php" class="nav-link <?= $currentPage === 'planos' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> <?= t('nav.planos') ?>
        </a>
        <a href="<?= $basePath ?>/faturas.php" class="nav-link <?= $currentPage === 'faturas' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i> <?= t('nav.faturas') ?>
        </a>
        <a href="<?= $basePath ?>/api_pagamento.php" class="nav-link <?= $currentPage === 'api_pagamento' ? 'active' : '' ?>">
            <i class="fas fa-university"></i> <?= t('nav.api_pagamento') ?>
        </a>
        <a href="<?= $basePath ?>/tutoriais.php" class="nav-link <?= $currentPage === 'tutoriais' ? 'active' : '' ?>">
            <i class="fas fa-video"></i> <?= t('nav.tutoriais') ?>
        </a>
        <a href="<?= $basePath ?>/cron.php" class="nav-link <?= $currentPage === 'cron' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> <?= t('nav.cron_job') ?>
        </a>

        <div class="nav-section"><?= t('layout.conta') ?></div>
        <a href="<?= $basePath ?>/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> <?= t('nav.sair') ?>
        </a>
    </nav>
</aside>