    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        return showConfirm('Excluir', 'Deseja excluir <strong>' + nome + '</strong>? Esta ação não pode ser desfeita.', url, 'danger');
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
    <div style="text-align:center; padding:16px 16px 8px; font-size:0.65rem; color:#94a3b8;">
        <span>Área restrita - Super Admin - WD Soluções Digitais LTDA</span>
    </div>
</body>
</html>
