    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <?php if (strpos($_SERVER['PHP_SELF'], '/usuario/') !== false): ?>
    <div class="topbar-mobile-user" style="display:none; position:fixed; top:0; right:0; z-index:201; padding:10px 16px; align-items:center; gap:8px;">
        <a href="/cobranca/usuario/perfil.php" style="display:flex; align-items:center; gap:6px; text-decoration:none;">
            <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:2px solid var(--cor-primaria);">
        </a>
    </div>
    <nav class="app-bottom-nav-mobile">
        <a href="/cobranca/usuario/index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Faturas</span>
        </a>
        <?php if (getConfig('financeiro_whatsapp') || getConfig('financeiro_email') || getConfig('financeiro_fone')): ?>
        <a href="/cobranca/usuario/financeiro.php" class="<?= basename($_SERVER['PHP_SELF']) === 'financeiro.php' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i>
            <span>Financeiro</span>
        </a>
        <?php endif; ?>
        <a href="/cobranca/usuario/perfil.php" class="<?= basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
        <a href="/cobranca/usuario/logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </nav>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/cobranca/assets/js/main.js"></script>
    <?php if(isset($extraScripts)) echo $extraScripts; ?>
    <div style="text-align:center; padding:16px 16px 8px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none; display:block; text-align:center;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a>
    </div>
</body>
</html>
