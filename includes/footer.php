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
    <script>
    function copiarPix(code) {
        if (!code) {
            var el = document.getElementById('pixCode');
            code = el ? el.textContent.trim() : '';
        }
        if (!code) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function() {
                showToastPix('Código PIX copiado!');
            }).catch(function() {
                fallbackCopy(code);
            });
        } else {
            fallbackCopy(code);
        }
    }
    function fallbackCopy(code) {
        var ta = document.createElement('textarea');
        ta.value = code;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showToastPix('Código PIX copiado!'); } catch(e) {}
        document.body.removeChild(ta);
    }
    function showToastPix(msg) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#27ae60;color:#fff;padding:10px 20px;border-radius:8px;font-size:0.85rem;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 2000);
    }
    </script>

    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="confirmModalTitle"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmModalBtn" class="btn btn-danger btn-sm">Confirmar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmModalPrimary" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="confirmModalPrimaryTitle"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalPrimaryBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmModalPrimaryBtn" class="btn btn-primary btn-sm">Confirmar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmModalSuccess" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="confirmModalSuccessTitle"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalSuccessBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmModalSuccessBtn" class="btn btn-success btn-sm">Confirmar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmModalForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="confirmModalFormTitle"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalFormBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="confirmModalFormBtn" class="btn btn-danger btn-sm">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($extraScripts)) echo $extraScripts; ?>
    <div style="text-align:center; padding:16px 16px 8px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none; display:block; text-align:center;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a>
    </div>
</body>
</html>
