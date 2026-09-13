    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <script src="/cobranca/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
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
                '<span>' + (L.LABELS[code] || code) + '</span>' +
                (code === cur ? '<i class="bi bi-check-lg ms-auto text-success"></i>' : '') + '</a>';
            ul.appendChild(li);
        });
        var msauto = topbar.querySelector('.ms-auto');
        if (msauto) msauto.parentNode.insertBefore(box, msauto.nextSibling);
        else topbar.appendChild(box);
    })();
    </script>
    <script>
    var MSG_EXCLUIR_TIT = <?= json_encode(t('modal.excluir')) ?>;
    var MSG_EXCLUIR_CONF = <?= json_encode(t('modal.confirm_excluir')) ?>;
    var MSG_CONFIRMAR_EXCLUSAO = <?= json_encode(t('modal.confirmar_exclusao')) ?>;
    var MSG_TEM_CERTEZA_EXCLUIR = <?= json_encode(t('modal.tem_certeza_excluir')) ?>;
    var MSG_DIGITE_DELETAR = <?= json_encode(t('modal.digite_deletar')) ?>;
    function showConfirm(title, body, url, type) {
        var t = type || 'danger';
        var modalEl = document.getElementById('confirmModal');
        if (!modalEl) return false;
        var modal = new bootstrap.Modal(modalEl);
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalBody').innerHTML = body;
        var btn = document.getElementById('confirmModalBtn');
        btn.href = url;
        btn.className = 'btn btn-' + t + ' btn-sm';
        modal.show();
        return false;
    }
    function confirmarExclusao(nome, url) {
        return showConfirm(MSG_EXCLUIR_TIT, MSG_EXCLUIR_CONF.replace('%s', '<strong>' + nome + '</strong>'), url, 'danger');
    }
    function confirmarExclusaoAdmin(nome, id) {
        var modalEl = document.getElementById('confirmDeleteAdminModal');
        if (!modalEl) return false;
        document.getElementById('deleteAdminNome').textContent = nome;
        document.getElementById('deleteAdminBtn').setAttribute('data-id', id);
        document.getElementById('deleteAdminInput').value = '';
        document.getElementById('deleteAdminBtn').classList.add('disabled');
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
        return false;
    }
    function deletarAdminConfirmado() {
        var id = document.getElementById('deleteAdminBtn').getAttribute('data-id');
        window.location.href = 'cadastros.php?excluir=' + id;
    }
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'deleteAdminInput') {
            var btn = document.getElementById('deleteAdminBtn');
            if (e.target.value.trim().toUpperCase() === 'DELETAR') btn.classList.remove('disabled');
            else btn.classList.add('disabled');
        }
    });
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
    <div class="modal fade" id="confirmDeleteAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i><?= htmlspecialchars(t('modal.confirmar_exclusao')) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p><?= str_replace('%s', '<strong id="deleteAdminNome"></strong>', t('modal.tem_certeza_excluir')) ?></p>
                    <label class="form-label"><?= t('modal.digite_deletar') ?></label>
                    <input type="text" id="deleteAdminInput" class="form-control" placeholder="DELETAR">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= htmlspecialchars(t('btn.cancelar')) ?></button>
                    <button type="button" class="btn btn-danger btn-sm disabled" id="deleteAdminBtn" onclick="deletarAdminConfirmado()"><?= htmlspecialchars(t('modal.excluir')) ?></button>
                </div>
            </div>
        </div>
    </div>
    <div style="text-align:center; padding:16px 16px 8px; font-size:0.65rem; color:#94a3b8;">
        <span>Área restrita - Super Admin - WD Soluções Digitais LTDA</span>
    </div>
</body>
</html>
