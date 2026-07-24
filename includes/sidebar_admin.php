<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '/cobranca/admin';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logo): ?>
            <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-width: 150px;">
        <?php endif; ?>
        <h4><i class="fas fa-shield-halved me-2"></i>Admin</h4>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Menu Principal</div>
        <a href="<?= $basePath ?>/index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Painel Geral
            <?php if (($totalAlerta ?? 0) > 0): ?>
                <span class="badge bg-danger ms-auto" style="font-size:0.65rem;"><?= $totalAlerta ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/cadastro.php" class="nav-link <?= $currentPage === 'cadastro' ? 'active' : '' ?>">
            <i class="fas fa-building"></i> Cadastro
        </a>
        <a href="<?= $basePath ?>/emissao.php" class="nav-link <?= $currentPage === 'emissao' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> Emissão de Faturas
        </a>
        <a href="<?= $basePath ?>/livro_caixa.php" class="nav-link <?= $currentPage === 'livro_caixa' ? 'active' : '' ?>">
            <i class="fas fa-book"></i> Livro Caixa
        </a>
        <a href="<?= $basePath ?>/inadimplencia.php" class="nav-link <?= $currentPage === 'inadimplencia' ? 'active' : '' ?>">
            <i class="fas fa-exclamation-triangle"></i> Inadimplência
        </a>
        <a href="<?= $basePath ?>/fluxo_caixa.php" class="nav-link <?= $currentPage === 'fluxo_caixa' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Fluxo de Caixa
        </a>

        <div class="nav-section">Configurações</div>
        <a href="<?= $basePath ?>/config_api.php" class="nav-link <?= $currentPage === 'config_api' ? 'active' : '' ?>">
            <i class="fas fa-key"></i> API de Pagamento
        </a>
        <a href="<?= $basePath ?>/personalizacao.php" class="nav-link <?= $currentPage === 'personalizacao' ? 'active' : '' ?>">
            <i class="fas fa-palette"></i> Personalização
        </a>
        <a href="<?= $basePath ?>/banners.php" class="nav-link <?= $currentPage === 'banners' ? 'active' : '' ?>">
            <i class="fas fa-image"></i> Banners
        </a>
        <a href="<?= $basePath ?>/envios.php" class="nav-link <?= $currentPage === 'envios' ? 'active' : '' ?>">
            <i class="fas fa-paper-plane"></i> Config. de Envios
        </a>
        <a href="<?= $basePath ?>/template_email.php" class="nav-link <?= $currentPage === 'template_email' ? 'active' : '' ?>">
            <i class="fas fa-envelope-open-text"></i> Template E-mail
        </a>
        <a href="<?= $basePath ?>/config_financeiro.php" class="nav-link <?= $currentPage === 'config_financeiro' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i> Contato/Financeiro
        </a>
        <a href="<?= $basePath ?>/backup.php" class="nav-link <?= $currentPage === 'backup' ? 'active' : '' ?>">
            <i class="fas fa-database"></i> Backup
        </a>
        <a href="<?= $basePath ?>/whatsapp.php" class="nav-link <?= $currentPage === 'whatsapp' ? 'active' : '' ?>">
            <i class="fab fa-whatsapp" style="color:#25D366;"></i> WhatsApp
        </a>

        <div class="nav-section">Conta</div>
        <a href="<?= $basePath ?>/usuarios.php" class="nav-link <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> Usuários Admin
        </a>
        <a href="<?= $basePath ?>/perfil.php" class="nav-link <?= $currentPage === 'perfil' ? 'active' : '' ?>">
            <i class="fas fa-user-edit"></i> Meu Perfil
        </a>
        <a href="<?= $basePath ?>/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </nav>
</aside>
