    </main>
</div>

<!-- Backdrop para Mobile -->
<div id="fintechOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.4);z-index:1030;backdrop-filter:blur(2px);"></div>

<!-- Scripts Essenciais -->
<script src="/cobranca/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/cobranca/assets/js/template_fintech.js?v=<?= filemtime(__DIR__ . '/../assets/js/template_fintech.js') ?>"></script>
<script>
// Exibir/ocultar overlay no mobile
(function(){
    var overlay = document.getElementById('fintechOverlay');
    var sidebar = document.getElementById('fintechSidebar');
    if (overlay && sidebar) {
        var obs = new MutationObserver(function(){
            overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
        });
        obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        overlay.addEventListener('click', function(){
            sidebar.classList.remove('show');
            overlay.style.display = 'none';
        });
    }
})();
</script>
</body>
</html>

