<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

$pdo = getConnection();

// Busca a configuração de WhatsApp do admin logado na tabela admin_evolution
$adminId = $_SESSION['admin_id'] ?? 0;
$evoConfig = null;
if ($adminId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM admin_evolution WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    $evoConfig = $stmt->fetch();
}

// Sem instância configurada -> bloqueio, não processa ações
$semInstancia = empty($evoConfig) || empty($evoConfig['url_api']) || empty($evoConfig['instance']);

// Plano restrito (demo) -> API de WhatsApp bloqueada
$demoBloqueado = !planoPermiteEnvio('whatsapp');

if (($_SERVER['REQUEST_METHOD'] === 'POST' && $semInstancia)) {
    $mensagem = 'Seu WhatsApp ainda não está configurado. Entre em contato com o suporte.';
    $tipo = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $demoBloqueado) {
    $mensagem = 'A configuração do WhatsApp está bloqueada na conta de demonstração. Assine um plano para liberar.';
    $tipo = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$semInstancia && !$demoBloqueado) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_config') {
        $url = trim($_POST['whatsapp_api_url'] ?? '');
        $key = trim($_POST['whatsapp_api_key'] ?? '');
        $inst = trim($_POST['whatsapp_instance'] ?? '');
        $ativo = isset($_POST['whatsapp_ativo']) ? 1 : 0;
        $pdo->prepare("UPDATE admin_evolution SET url_api=?, api_key=?, instance=?, ativo=? WHERE admin_id=?")
            ->execute([$url, $key, $inst, $ativo, $adminId]);
        $mensagem = 'Configurações do WhatsApp salvas!';
        $tipo = 'success';
    }

    if ($acao === 'testar') {
        $apiUrl = rtrim(getConfig('whatsapp_api_url', ''), '/');
        $apiKey = getConfig('whatsapp_api_key', '');
        $instance = getConfig('whatsapp_instance', '');

        if (empty($apiUrl) || empty($apiKey) || empty($instance)) {
            $mensagem = 'Preencha URL, API Key e nome da instância.';
            $tipo = 'danger';
        } else {
            $ch = curl_init("{$apiUrl}/instance/fetchInstances");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $mensagem = 'Erro de conexão: ' . htmlspecialchars($err);
                $tipo = 'danger';
            } else {
                $data = json_decode($resp, true);
                if ($data) {
                    $mensagem = 'Conexão OK! Instâncias encontradas: ' . count($data);
                    $tipo = 'success';
                } else {
                    $mensagem = 'Resposta inválida da API.';
                    $tipo = 'danger';
                }
            }
        }
    }
}

$apiUrl = $semInstancia ? '' : rtrim($evoConfig['url_api'], '/');
$apiKey = $semInstancia ? '' : ($evoConfig['api_key'] ?? '');
$instance = $semInstancia ? '' : $evoConfig['instance'];
$whatsappAtivo = $semInstancia ? '0' : ($evoConfig['ativo'] ? '1' : '0');

$statusConexao = 'desconectado';
$qrCode = null;

if (!empty($apiUrl) && !empty($apiKey) && !empty($instance)) {
    $ch = curl_init("{$apiUrl}/instance/connectionState/{$instance}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $state = json_decode($resp, true);
    $instanceState = $state['instance']['state'] ?? $state['state'] ?? 'close';
    if ($instanceState === 'open') {
        $statusConexao = 'conectado';
    } else {
        $ch = curl_init("{$apiUrl}/instance/create");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'instanceName' => $instance,
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS',
            ]),
            CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);

        sleep(1);

        $qrBase64 = null;
        $tentativas = 0;
        while ($tentativas < 5 && empty($qrBase64)) {
            $ch = curl_init("{$apiUrl}/instance/connect/{$instance}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $qrData = json_decode($resp, true);
            if ($qrData) {
                $b64 = $qrData['base64'] ?? $qrData['qrcode'] ?? '';
                if (is_array($b64)) $b64 = $b64['code'] ?? '';
                $b64 = preg_replace('/^data:image\/[a-z]+;base64,/i', '', $b64);
                if (!empty($b64)) $qrBase64 = $b64;
            }
            if (empty($qrBase64)) {
                $ch = curl_init("{$apiUrl}/instance/qrcode/{$instance}");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $resp = curl_exec($ch);
                curl_close($ch);
                $qrData2 = json_decode($resp, true);
                if ($qrData2) {
                    $b64 = $qrData2['base64'] ?? $qrData2['qrcode'] ?? '';
                    if (is_array($b64)) $b64 = $b64['code'] ?? '';
                    $b64 = preg_replace('/^data:image\/[a-z]+;base64,/i', '', $b64);
                    if (!empty($b64)) $qrBase64 = $b64;
                }
            }
            if (empty($qrBase64)) sleep(1);
            $tentativas++;
        }
        if ($qrBase64) $qrCode = $qrBase64;
    }
}

$pageTitle = 'WhatsApp';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<style>
.content-blurred {
    filter: blur(6px);
    pointer-events: none;
    user-select: none;
    opacity: 0.6;
}
.with-block-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 5;
}
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>WhatsApp - Evolution API</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($demoBloqueado): ?>
        <div class="alert alert-warning d-flex align-items-center">
            <i class="fas fa-flask me-2 fa-lg"></i>
            <div><strong>WhatsApp bloqueado na conta de demonstração.</strong><br>
            <small>O envio e a configuração da API de WhatsApp estão bloqueados nesta versão demo. <a href="/cobranca/planos.php">Assine um plano</a> para liberar.</small></div>
        </div>
        <?php endif; ?>

        <?php if ($semInstancia): ?>
        <div class="position-relative" style="overflow:hidden;">
            <div class="content-blurred">
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fab fa-whatsapp me-2" style="color:#25D366;"></i>Configuração da Evolution API</h6>
                    <div class="mb-1">
                        <label class="form-label text-muted mb-0">URL da API</label>
                        <div class="form-control bg-light" style="border:1px solid #dee2e6;"><?= !empty($apiUrl) ? '****' : '<em class="text-muted">Não configurado</em>' ?></div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label text-muted mb-0">API Key</label>
                        <div class="form-control bg-light" style="border:1px solid #dee2e6;"><?= !empty($apiKey) ? '****' : '<em class="text-muted">Não configurado</em>' ?></div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label text-muted mb-0">Nome da Instância</label>
                        <div class="form-control bg-light" style="border:1px solid #dee2e6;"><?= !empty($instance) ? htmlspecialchars($instance) : '<em class="text-muted">Não configurado</em>' ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted mb-0">Envio via WhatsApp no cron</label>
                        <div>
                            <?php if ($whatsappAtivo === '1'): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Ativo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inativo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-0">
                        <i class="fas fa-lock me-1"></i> As configurações do WhatsApp são gerenciadas pelo suporte e não podem ser alteradas aqui.
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-qrcode me-2"></i>Status da Conexão</h6>
                    <?php if (empty($apiUrl) || empty($apiKey) || empty($instance)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>A conexão do WhatsApp ainda não foi configurada pelo suporte.
                        </div>
                    <?php elseif ($statusConexao === 'conectado'): ?>
                        <div class="text-center py-4">
                            <div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                                <i class="fas fa-check" style="font-size:2rem;color:#10b981;"></i>
                            </div>
                            <h5 style="color:#10b981;">Conectado</h5>
                            <p class="text-muted">WhatsApp conectado e pronto para envio.</p>
                            <p class="text-muted small mb-0">Instância: <strong><?= htmlspecialchars($instance) ?></strong></p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-3">Escaneie o QR Code abaixo com o WhatsApp:</p>
                            <?php if ($qrCode): ?>
                                <div class="mb-3">
                                    <img src="data:image/png;base64,<?= htmlspecialchars($qrCode) ?>" alt="QR Code WhatsApp" style="max-width:280px; border:1px solid #dee2e6; border-radius:8px;">
                                </div>
                                <p class="text-muted small"><i class="fas fa-info-circle me-1"></i>QR Code atualiza automaticamente. Escaneie antes que expire.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Atualizar QR Code</button>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Não foi possível gerar o QR Code. Verifique as configurações.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($semInstancia): ?>
            </div>
            <div class="with-block-overlay">
                <div class="form-card text-center py-5" style="max-width:460px;margin:0 auto;">
                    <div style="width:90px;height:90px;border-radius:50%;background:#fee2e2;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px;">
                        <i class="fas fa-lock" style="font-size:2.2rem;color:#dc2626;"></i>
                    </div>
                    <h5 class="mb-2" style="color:#dc2626;"><i class="fab fa-whatsapp me-1"></i>WhatsApp bloqueado</h5>
                    <p class="mb-1" style="color:#000000;font-weight:500;">Seu WhatsApp ainda não está configurado.</p>
                    <p class="mb-4" style="color:#000000;font-weight:500;">Entre em contato com o suporte para conectar a API do WhatsApp.</p>
                    <a href="https://wa.me/5591982675573" target="_blank" class="btn" style="background:#25D366;color:#fff;"><i class="fab fa-whatsapp me-1"></i>Falar com Suporte</a>
                </div>
            </div>
        </div>
        <?php endif; ?>


    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php if ($statusConexao !== 'conectado' && !empty($apiUrl) && !empty($apiKey) && !empty($instance)): ?>
<script>
(function() {
    var url = <?= json_encode($apiUrl) ?>;
    var key = <?= json_encode($apiKey) ?>;
    var name = <?= json_encode($instance) ?>;
    function check() {
        fetch(url + '/instance/connectionState/' + name, {headers: {'apikey': key}})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var s = (d.instance && d.instance.state) || d.state || '';
                if (s === 'open') {
                    var card = document.querySelector('.form-card .text-center.py-4');
                    if (card) {
                        card.innerHTML = '<div style="padding:20px 0;"><div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;"><i class="fas fa-check" style="font-size:2rem;color:#10b981;"></i></div><h5 style="color:#10b981;">Conexão atualizada!</h5><p class="text-muted">Redirecionando...</p></div>';
                    }
                    setTimeout(function() { location.reload(); }, 2000);
                }
            }).catch(function() {});
    }
    setInterval(check, 3000);
})();
</script>
<?php endif; ?>