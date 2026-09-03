<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $pdo = getConnection();

    if ($acao === 'cron') {
        $siteUrl = trim($_POST['site_url'] ?? '');
        $cronToken = trim($_POST['cron_token'] ?? '');

        if (empty($cronToken)) {
            $cronToken = getConfigGlobal('cron_token', '') ?: bin2hex(random_bytes(16));
        }

        salvarConfigGlobal('site_url', $siteUrl);
        salvarConfigGlobal('cron_token', $cronToken);

        $mensagem = 'Configuração CRON salva com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'gerar_token') {
        $cronToken = bin2hex(random_bytes(16));
        salvarConfigGlobal('cron_token', $cronToken);

        $mensagem = 'Novo token gerado! A URL no cron-job.org precisará ser atualizada.';
        $tipo = 'success';
    }
}

$siteUrl = getConfigGlobal('site_url', '');
$cronToken = getConfigGlobal('cron_token', '');

// Gera token automaticamente na primeira visita
if (empty($cronToken)) {
    $cronToken = bin2hex(random_bytes(16));
    salvarConfigGlobal('cron_token', $cronToken);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteUrlDisplay = $siteUrl !== '' ? rtrim($siteUrl, '/') : $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$cronUrl = rtrim($siteUrlDisplay, '/') . '/cobranca/api/cron_envio.php?token=' . urlencode($cronToken);

$pageTitle = 'Cron Job';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-clock me-1"></i> Cron Job</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show py-2">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Configuração CRON (cron-job.org)</h6>
                    </div>
                    <div class="p-3">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Uma única chamada (cron job) do <strong>cron-job.org</strong> (gratuito) processa todos os envios automáticos de <strong>todos</strong> os clientes/admins. Basta cadastrar uma única URL abaixo.
                        </div>

                        <div class="bg-light p-3 rounded mb-3">
                            <small class="text-muted d-block mb-1"><strong>Passo a passo:</strong></small>
                            <small class="text-muted d-block">1. Crie uma conta gratuita em <a href="https://cron-job.org" target="_blank">https://cron-job.org</a></small>
                            <small class="text-muted d-block">2. No painel, clique em <strong>Create cronjob</strong></small>
                            <small class="text-muted d-block">3. Em <strong>URL</strong>, cole o endereço abaixo (com o token)</small>
                            <small class="text-muted d-block">4. Em <strong>Schedule</strong>, escolha <strong>Every 10 minutes</strong> (ou 5 minutos para baixa mais rápida)</small>
                            <small class="text-muted d-block">5. Marque <strong>Enabled</strong> e clique em <strong>Create cronjob</strong></small>
                            <small class="text-muted d-block">6. Clique em <strong>Run now</strong> para testar e veja a resposta no histórico</small>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="acao" value="cron">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">URL pública do sistema (domínio)</label>
                                    <input type="text" name="site_url" class="form-control" placeholder="https://www.seudominio.com" value="<?= htmlspecialchars($siteUrl) ?>">
                                    <small class="text-muted">Ex.: https://www.seudominio.com (sem /cobranca no final)</small>
                                </div>

                                <div class="col-12 mt-3">
                                    <label class="form-label">URL do cron (cole no cron-job.org)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="cronUrl" readonly value="<?= htmlspecialchars($cronUrl) ?>" style="font-family:monospace;font-size:0.8rem;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copiarCronUrl()"><i class="fas fa-copy me-1"></i>Copiar</button>
                                    </div>
                                    <small class="text-muted d-block mt-1">Token: <code><?= htmlspecialchars($cronToken) ?></code> (mantenha em segredo)</small>
                                </div>
                            </div>

                            <div class="col-12 d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar CRON
                                </button>
                                <button type="submit" name="acao" value="gerar_token" class="btn btn-outline-danger" onclick="return confirm('Gerar novo token? A URL no cron-job.org precisará ser atualizada.');">
                                    <i class="fas fa-key me-1"></i> Gerar novo token
                                </button>
                            </div>
                        </form>

                        <div class="bg-light p-3 rounded mt-3">
                            <small class="text-muted d-block mb-1"><strong>Como funciona:</strong></small>
                            <small class="text-muted d-block">• O cron-job.org acessa a URL a cada poucos minutos (conforme você configurar)</small>
                            <small class="text-muted d-block">• A cada acesso: consulta pagamentos e dá baixa automática nas faturas pagas de todos os clientes</small>
                            <small class="text-muted d-block">• Gera a próxima fatura recorrente ao término do vencimento da anterior (mesmo que não paga) e envia conforme a régua de cobrança de cada admin</small>
                            <small class="text-muted d-block">• E-mails/WhatsApp da régua só são enviados dentro da janela de 1h do horário configurado por cada admin</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-universal-access me-2"></i>Modelo Multi-Clientes</h6>
                    </div>
                    <div class="p-3">
                        <p class="small text-muted mb-2">
                            Esta configuração é <strong>única e global</strong> (pertencente ao Super Admin).
                        </p>
                        <ul class="small text-muted mb-0" style="padding-left: 18px;">
                            <li class="mb-2">Cada admin configura a sua <strong>Configuração de Envio</strong> (régua de cobrança, horário, SMTP) no painel dele.</li>
                            <li class="mb-2">Uma única URL do cron processa todos eles.</li>
                            <li class="mb-2">O token protege o acesso: somente quem tem a URL completa consegue acionar o cron.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function copiarCronUrl() {
    var el = document.getElementById('cronUrl');
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(function() {
        var btn = document.querySelector('[onclick="copiarCronUrl()"]');
        if (btn) { btn.textContent = 'Copiado!'; setTimeout(function(){ btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copiar'; }, 1500); }
    });
}
</script>
