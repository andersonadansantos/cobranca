/**
 * Nova Identidade Visual: Fintech Minimalist & Executive Precision
 * Script interativo - Template V2
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Toggle Sidebar Mobile e Desktop
    var sidebar = document.getElementById('fintechSidebar');
    var sidebarToggle = document.getElementById('fintechSidebarToggle');
    var overlay = document.getElementById('fintechOverlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay && sidebar) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // 2. Configurações Globais do Chart.js para Estética Fintech
    if (window.Chart) {
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Inter', sans-serif";
        Chart.defaults.color = '#64748b';
        Chart.defaults.borderColor = '#eef2f6';
        Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
        Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
        Chart.defaults.plugins.tooltip.bodyColor = '#e2e8f0';
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.displayColors = false;
        Chart.defaults.plugins.tooltip.titleFont = { weight: '700', size: 12 };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 13, weight: '500' };
    }

    // 3. Inicialização de tooltips Bootstrap se existirem
    if (window.bootstrap && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // 4. Eliminação de duplicidade: garante a remoção de topbar legada em páginas do admin
    var legacyTopbars = document.querySelectorAll('.main-content > .topbar, .topbar:not(.fintech-topbar)');
    legacyTopbars.forEach(function (tb) {
        tb.style.setProperty('display', 'none', 'important');
    });
});

// Utilitário para criar gradiente translúcido elegante no Chart.js
function createFintechGradient(ctx, colorRgb) {
    var gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, 'rgba(' + colorRgb + ', 0.22)');
    gradient.addColorStop(0.7, 'rgba(' + colorRgb + ', 0.04)');
    gradient.addColorStop(1, 'rgba(' + colorRgb + ', 0.0)');
    return gradient;
}

