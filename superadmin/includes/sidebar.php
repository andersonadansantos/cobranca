<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/cobranca/superadmin';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logo): ?>
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-width: 150px;">
        <?php endif; ?>
        <h4><i class="fas fa-crown me-2"></i>Super Admin</h4>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Menu Principal</div>
        <a href="<?= $basePath ?>/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="<?= $basePath ?>/cadastros.php" class="nav-link <?= $currentPage === 'cadastros' ? 'active' : '' ?>">
            <i class="fas fa-building"></i> Cadastros
        </a>
        <a href="<?= $basePath ?>/api_admins.php" class="nav-link <?= $currentPage === 'api_admins' ? 'active' : '' ?>">
            <i class="fab fa-whatsapp" style="color:#25D366;"></i> API Admins
        </a>
        <a href="<?= $basePath ?>/planos.php" class="nav-link <?= $currentPage === 'planos' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Planos
        </a>

        <div class="nav-section">Conta</div>
        <a href="<?= $basePath ?>/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </nav>
</aside>
