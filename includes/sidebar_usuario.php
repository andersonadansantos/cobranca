<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/cobranca/usuario';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logo): ?>
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-width: 150px;">
        <?php endif; ?>
        <h4><i class="fas fa-user me-2"></i>Área do Cliente</h4>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Menu</div>
        <a href="<?= $basePath ?>/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Painel Geral
        </a>
        <a href="<?= $basePath ?>/perfil.php" class="nav-link <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <i class="fas fa-user-edit"></i> Perfil
        </a>

        <?php
        $temContato = getConfig('financeiro_whatsapp') || getConfig('financeiro_email') || getConfig('financeiro_fone');
        if ($temContato): ?>
        <a href="<?= $basePath ?>/financeiro.php" class="nav-link <?= $currentPage === 'financeiro' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i> Falar com Financeiro
        </a>
        <?php endif; ?>

        <div class="nav-section">Conta</div>
        <a href="<?= $basePath ?>/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </nav>
</aside>
