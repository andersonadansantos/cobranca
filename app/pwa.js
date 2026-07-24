(function() {
    if (!('serviceWorker' in navigator)) return;

    let deferredPrompt = null;
    const installKey = 'pwa_install_dismissed';

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/cobranca/app/service-worker.js', { scope: '/cobranca/app/' })
            .then((reg) => {
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateBanner();
                        }
                    });
                });
            })
            .catch(() => {});
    });

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (!localStorage.getItem(installKey)) {
            setTimeout(() => showInstallBanner(), 3000);
        }
    });

    function showInstallBanner() {
        if (document.getElementById('pwa-install-banner')) return;
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.innerHTML = `
            <div style="position:fixed;bottom:76px;left:12px;right:12px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.15);padding:18px;z-index:9999;display:flex;align-items:center;gap:14px;border:1px solid #e2e8f0;animation:slideUp 0.3s ease;">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6C5CE7,#a29bfe);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-file-invoice-dollar" style="color:#fff;font-size:1.2rem;"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:0.88rem;margin-bottom:2px;">Instalar Cobrança</div>
                    <div style="font-size:0.72rem;color:#94a3b8;">Acesse suas faturas rapidamente</div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button id="pwa-install-btn" style="background:#6C5CE7;color:#fff;border:none;border-radius:10px;padding:8px 16px;font-size:0.8rem;font-weight:600;cursor:pointer;">Instalar</button>
                    <button id="pwa-dismiss-btn" style="background:none;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;padding:4px;">&times;</button>
                </div>
            </div>
        `;
        document.body.appendChild(banner);

        document.getElementById('pwa-install-btn').addEventListener('click', () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choice) => {
                    if (choice.outcome === 'accepted') {
                        banner.remove();
                        deferredPrompt = null;
                    }
                });
            }
        });

        document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
            banner.remove();
            localStorage.setItem(installKey, Date.now().toString());
        });

        setTimeout(() => {
            if (banner.parentNode) banner.remove();
            localStorage.setItem(installKey, Date.now().toString());
        }, 12000);
    }

    function showUpdateBanner() {
        if (document.getElementById('pwa-update-banner')) return;
        const banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.innerHTML = `
            <div style="position:fixed;bottom:76px;left:12px;right:12px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.15);padding:18px;z-index:9999;display:flex;align-items:center;gap:14px;border:1px solid #e2e8f0;">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#10b981,#34d399);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-sync-alt" style="color:#fff;font-size:1.1rem;"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:0.88rem;margin-bottom:2px;">Atualização disponível</div>
                    <div style="font-size:0.72rem;color:#94a3b8;">Uma nova versão está pronta.</div>
                </div>
                <button id="pwa-update-btn" style="background:#10b981;color:#fff;border:none;border-radius:10px;padding:8px 16px;font-size:0.8rem;font-weight:600;cursor:pointer;">Atualizar</button>
            </div>
        `;
        document.body.appendChild(banner);

        document.getElementById('pwa-update-btn').addEventListener('click', () => {
            window.location.reload();
        });
    }

    window.requestPushPermission = function() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) return Promise.resolve('unsupported');
        if (Notification.permission === 'granted') return Promise.resolve('granted');
        if (Notification.permission === 'denied') return Promise.resolve('denied');
        return Notification.requestPermission();
    };

    const style = document.createElement('style');
    style.textContent = '@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}';
    document.head.appendChild(style);
})();