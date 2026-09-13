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
            <span><?= t('usuario.faturas') ?></span>
        </a>
        <?php if (getConfig('financeiro_whatsapp') || getConfig('financeiro_email') || getConfig('financeiro_fone')): ?>
        <a href="/cobranca/usuario/financeiro.php" class="<?= basename($_SERVER['PHP_SELF']) === 'financeiro.php' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i>
            <span><?= t('usuario.financeiro') ?></span>
        </a>
        <?php endif; ?>
        <a href="/cobranca/usuario/perfil.php" class="<?= basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span><?= t('usuario.perfil') ?></span>
        </a>
        <a href="/cobranca/usuario/logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span><?= t('usuario.sair') ?></span>
        </a>
    </nav>
    <?php endif; ?>

    <script src="/cobranca/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/cobranca/assets/js/main.js?v=<?= filemtime((defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/assets/js/main.js') ?>"></script>
    <script>
    (function () {
        // Seletor de idioma (bandeiras) injetado na topbar
        var topbar = document.querySelector('.topbar');
        if (!topbar || !window.PAINEL_LANG) return;
        var L = window.PAINEL_LANG;
        var cur = L.CUR || 'pt-BR';
        function langHref(code) {
            var u = new URL(window.location.href);
            u.searchParams.set('idioma', code);
            return u.href;
        }
        var box = document.createElement('div');
        box.className = 'dropdown d-inline-block';
        box.style.marginLeft = '4px';
        var html = '<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" style="border-radius:8px;padding:5px 10px;font-size:0.8rem;">' +
            '<img src="/cobranca/assets/img/flags/' + (L.FLAGS[cur] || 'br.svg') + '" width="16" height="11" style="border-radius:2px;object-fit:cover;margin-right:5px;vertical-align:middle;" alt="">' +
            '<span>' + (L.LABELS[cur] || cur) + '</span></button>' +
            '<ul class="dropdown-menu dropdown-menu-end" style="min-width:230px;"></ul>';
        box.innerHTML = html;
        var ul = box.querySelector('ul');
        (L.CODES || []).forEach(function (code) {
            var li = document.createElement('li');
            li.innerHTML = '<a class="dropdown-item d-flex align-items-center gap-2' + (code === cur ? ' active' : '') + '" href="' + langHref(code) + '">' +
                '<img src="/cobranca/assets/img/flags/' + L.FLAGS[code] + '" width="18" height="12" style="border-radius:2px;object-fit:cover;" alt="">' +
                '<span class="flex-1">' + (L.LABELS[code] || code) + '</span>' +
                (code === cur ? '<i class="bi bi-check-lg ms-auto text-success"></i>' : '') + '</a>';
            ul.appendChild(li);
        });
        var suporte = topbar.querySelector('a[href*="wa.me"]');
        if (suporte) {
            box.style.marginLeft = '4px';
            suporte.parentNode.insertBefore(box, suporte);
        } else {
            var msauto = topbar.querySelector('.ms-auto');
            if (msauto) msauto.parentNode.insertBefore(box, msauto.nextSibling);
            else topbar.appendChild(box);
        }
        box.querySelectorAll('.dropdown-item[href]').forEach(function (a) {
            a.addEventListener('click', function () { /* navegaÃ§Ã£o normal */ });
        });
    })();
    </script>
    <script>
    (function(){
        var topbar = document.querySelector('.topbar');
        if (!topbar) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.style.cssText = 'border-radius:8px;padding:5px 10px;font-size:0.8rem;margin-left:4px;';
        btn.title = 'Alternar tema';
        var current = localStorage.getItem('theme') || 'light';
        function updateIcon(theme) {
            var isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            btn.innerHTML = isDark ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-fill"></i>';
        }
        updateIcon(current);
        btn.addEventListener('click', function(){
            var t = localStorage.getItem('theme') || 'light';
            var next = t === 'light' ? 'dark' : t === 'dark' ? 'system' : 'light';
            localStorage.setItem('theme', next);
            var isDark = next === 'dark' || (next === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
            updateIcon(next);
        });
        var suporte = topbar.querySelector('a[href*="wa.me"]');
        if (suporte) {
            suporte.parentNode.insertBefore(btn, suporte.nextSibling);
        } else {
            var msauto = topbar.querySelector('.ms-auto');
            if (msauto) msauto.parentNode.insertBefore(btn, msauto.nextSibling);
            else topbar.appendChild(btn);
        }
    })();
    </script>
    <script>
    function copiarPix(code) {
        if (!code) {
            var el = document.getElementById('pixCode');
            code = el ? el.textContent.trim() : '';
        }
        if (!code) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function() {
                showToastPix(<?= json_encode(t('modal.pix_copiado')) ?>);
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
        try { document.execCommand('copy'); showToastPix(<?= json_encode(t('modal.pix_copiado')) ?>); } catch(e) {}
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn.cancelar')) ?></button>
                    <a href="#" id="confirmModalBtn" class="btn btn-danger btn-sm"><?= htmlspecialchars(t('btn.confirmar')) ?></a>
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn.cancelar')) ?></button>
                    <a href="#" id="confirmModalPrimaryBtn" class="btn btn-primary btn-sm"><?= htmlspecialchars(t('btn.confirmar')) ?></a>
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn.cancelar')) ?></button>
                    <a href="#" id="confirmModalSuccessBtn" class="btn btn-success btn-sm"><?= htmlspecialchars(t('btn.confirmar')) ?></a>
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn.cancelar')) ?></button>
                    <button type="button" id="confirmModalFormBtn" class="btn btn-danger btn-sm"><?= htmlspecialchars(t('btn.confirmar')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="alertModalTitle"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="alertModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn.ok')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($extraScripts)) echo $extraScripts; ?>
    <div style="text-align:center; padding:16px 16px 8px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none; display:block; text-align:center;">Todos os Direitos Reservados - WD SoluÃ§Ãµes Digitais LTDA - 2010 - 2026</a><span style="float:right;font-size:0.7rem;color:#475569;">VersÃ£o: 1.0</span>
    </div>
</body>
</html>
