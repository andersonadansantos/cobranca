// =====================================================
// MENU LATERAL - RECOLHER/EXPANDIR (desktop) E GAVETA (mobile)
// =====================================================
// - Desktop: o botao no topo do menu recolhe para apenas icones (com tooltip)
//   e expande novamente. O estado fica salvo em localStorage.
// - Mobile: o botao do topbar abre/fecha a gaveta lateral com overlay.
// - Preserva todos os links, funcoes e permissoes do menu (nada e removido).
(function () {
    'use strict';

    var COLLAPSE_KEY = 'sidebarCollapsed';
    var DESKTOP_QUERY = '(min-width: 769px)';

    function isDesktop() {
        return window.matchMedia(DESKTOP_QUERY).matches;
    }

    function lerPreferencia() {
        try {
            return localStorage.getItem(COLLAPSE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function salvarPreferencia(recolhido) {
        try {
            localStorage.setItem(COLLAPSE_KEY, recolhido ? '1' : '0');
        } catch (e) { /* modo privado: ignora */ }
    }

    // Texto do proprio link (ignora o icone e badges), usado como tooltip.
    function rotuloDe(el) {
        var partes = [];
        for (var i = 0; i < el.childNodes.length; i++) {
            if (el.childNodes[i].nodeType === 3) partes.push(el.childNodes[i].nodeValue);
        }
        return partes.join(' ').replace(/\s+/g, ' ').trim();
    }

    function init() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggler = document.getElementById('sidebarToggle');
        var collapseBtn = sidebar ? sidebar.querySelector('.sidebar-collapse-btn') : null;
        var body = document.body;

        // ---------- Mobile: gaveta lateral ----------
        function abrirGaveta() {
            if (!sidebar) return;
            sidebar.classList.add('show');
            if (overlay) overlay.classList.add('show');
            if (toggler) toggler.setAttribute('aria-expanded', 'true');
        }
        function fecharGaveta() {
            if (!sidebar) return;
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
            if (toggler) toggler.setAttribute('aria-expanded', 'false');
        }
        if (toggler) {
            toggler.addEventListener('click', function (e) {
                e.preventDefault();
                if (sidebar && sidebar.classList.contains('show')) fecharGaveta();
                else abrirGaveta();
            });
        }
        if (overlay) overlay.addEventListener('click', fecharGaveta);
        if (sidebar) {
            sidebar.querySelectorAll('.sidebar-nav .nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (!isDesktop()) fecharGaveta();
                });
            });
        }

        if (!sidebar || !collapseBtn) return;

        // ---------- Desktop: recolher para icones ----------
        var alvos = [].slice.call(sidebar.querySelectorAll('.sidebar-nav .nav-link, .sidebar-nav .nav-section'));
        alvos.forEach(function (el) {
            if (!el.getAttribute('data-sidebar-label')) {
                var rotulo = rotuloDe(el);
                if (rotulo) el.setAttribute('data-sidebar-label', rotulo);
            }
        });

        var tooltips = [];

        function destruirTooltips() {
            tooltips.forEach(function (t) {
                try { t.dispose(); } catch (e) { /* ignora */ }
            });
            tooltips = [];
        }

        function criarTooltips() {
            destruirTooltips();
            if (!window.bootstrap || !bootstrap.Tooltip) return;
            alvos.forEach(function (el) {
                var rotulo = el.getAttribute('data-sidebar-label');
                if (!rotulo) return;
                el.setAttribute('title', rotulo);
                el.setAttribute('data-bs-placement', 'right');
                try {
                    tooltips.push(new bootstrap.Tooltip(el, { trigger: 'hover focus', container: 'body' }));
                } catch (e) { /* ignora */ }
            });
        }

        function limparTooltips() {
            destruirTooltips();
            alvos.forEach(function (el) {
                el.removeAttribute('title');
                el.removeAttribute('data-bs-placement');
            });
        }

        function aplicarIcone(recolhido) {
            btnAtualizarRotulo(recolhido);
            var icone = collapseBtn.querySelector('i');
            if (icone) {
                icone.classList.toggle('fa-angles-right', recolhido);
                icone.classList.toggle('fa-angles-left', !recolhido);
            }
        }

        function btnAtualizarRotulo(recolhido) {
            var rotulo = recolhido ? 'Expandir menu' : 'Recolher menu';
            collapseBtn.setAttribute('title', rotulo);
            collapseBtn.setAttribute('aria-label', rotulo);
            collapseBtn.setAttribute('aria-expanded', recolhido ? 'false' : 'true');
        }

        function aplicar(recolhido) {
            body.classList.toggle('sidebar-collapsed', recolhido);
            aplicarIcone(recolhido);
            if (recolhido && isDesktop()) criarTooltips();
            else limparTooltips();
            salvarPreferencia(recolhido);
        }

        // Restaura o estado salvo apenas no desktop.
        var recolhidoInicial = isDesktop() && lerPreferencia();
        if (recolhidoInicial) {
            body.classList.add('sidebar-collapsed');
            aplicarIcone(true);
            criarTooltips();
        } else {
            aplicarIcone(false);
        }

        collapseBtn.addEventListener('click', function () {
            // Recolher para icones e um recurso de desktop.
            if (!isDesktop()) return;
            aplicar(!body.classList.contains('sidebar-collapsed'));
        });

        // Mantem desktop e mobile coerentes ao redimensionar a janela.
        var mq = window.matchMedia(DESKTOP_QUERY);
        var aoMudarTela = function () {
            if (!isDesktop()) {
                // No mobile o menu e gaveta: sempre expandido ao abrir.
                body.classList.remove('sidebar-collapsed');
                limparTooltips();
                fecharGaveta();
            } else if (lerPreferencia()) {
                body.classList.add('sidebar-collapsed');
                aplicarIcone(true);
                criarTooltips();
            } else {
                body.classList.remove('sidebar-collapsed');
                aplicarIcone(false);
                limparTooltips();
            }
        };
        if (mq.addEventListener) mq.addEventListener('change', aoMudarTela);
        else if (mq.addListener) mq.addListener(aoMudarTela);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
