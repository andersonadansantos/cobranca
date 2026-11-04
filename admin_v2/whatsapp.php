<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';
$pdo = getConnection();

$adminId = $_SESSION['admin_id'] ?? 0;
$evoConfig = null;
if ($adminId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM admin_evolution WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    $evoConfig = $stmt->fetch();
}

$semInstancia = empty($evoConfig) || empty($evoConfig['url_api']) || empty($evoConfig['instance']);
$demoBloqueado = !planoPermiteEnvio('whatsapp');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $semInstancia) {
    $mensagem = 'A instância de WhatsApp não está atribuída à sua conta. Entre em contato com o suporte.';
    $tipo = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $demoBloqueado) {
    $mensagem = 'O envio via WhatsApp está bloqueado na conta de demonstração. Assine um plano para liberar.';
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
        $mensagem = 'Configurações do WhatsApp salvas com sucesso!';
        $tipo = 'success';
        
        $stmt = $pdo->prepare("SELECT * FROM admin_evolution WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        $evoConfig = $stmt->fetch();
    }

    if ($acao === 'testar') {
        if (!function_exists('getWhatsAppConfig')) {
            require_once __DIR__ . '/../api/whatsapp_send.php';
        }
        $wa = getWhatsAppConfig();
        $apiUrl = rtrim($wa['url_api'] ?? '', '/');
        $apiKey = $wa['api_key'] ?? '';
        $instance = $wa['instance'] ?? '';

        if (empty($apiUrl) || empty($apiKey) || empty($instance)) {
            $mensagem = 'Preencha a URL, a API Key e a instância antes de testar.';
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
                $mensagem = 'Erro de comunicação: ' . htmlspecialchars($err);
                $tipo = 'danger';
            } else {
                $data = json_decode($resp, true);
                if ($data) {
                    $mensagem = 'Comunicação com a Evolution API estabelecida com sucesso!';
                    $tipo = 'success';
                } else {
                    $mensagem = 'Resposta inesperada da Evolution API.';
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
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $state = json_decode($resp, true);
    $instanceState = $state['instance']['state'] ?? $state['state'] ?? 'close';

    if ($instanceState === 'open') {
        $statusConexao = 'conectado';
    } else {
        $ch = curl_init("{$apiUrl}/instance/connect/{$instance}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['apikey: ' . $apiKey],
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $qrData = json_decode($resp, true);
        if ($qrData) {
            $b64 = $qrData['base64'] ?? $qrData['qrcode'] ?? '';
            if (is_array($b64)) $b64 = $b64['code'] ?? '';
            $b64 = preg_replace('/^data:image\/[a-z]+;base64,/i', '', $b64);
            if (!empty($b64)) {
                $qrCode = $b64;
                $statusConexao = 'aguardando_qrcode';
            }
        }
    }
}

$pageTitle = 'WhatsApp API';
$pageCategory = 'Automações & APIs';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">WhatsApp & Notificações Instantâneas</h2>
            <p class="text-muted small mb-0">Gerencie a conexão da sua instância com o WhatsApp para envio automático de faturas e lembretes.</p>
        </div>
        <div>
            <?php if ($statusConexao === 'conectado'): ?>
                <span class="fintech-badge success" style="font-size:12px; padding:6px 12px;"><span class="dot"></span> Conectado & Operante</span>
            <?php elseif ($statusConexao === 'aguardando_qrcode'): ?>
                <span class="fintech-badge warning" style="font-size:12px; padding:6px 12px;"><span class="dot"></span> Aguardando Leitura do QR Code</span>
            <?php else: ?>
                <span class="fintech-badge danger" style="font-size:12px; padding:6px 12px;"><span class="dot"></span> Desconectado</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : ($tipo === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <div><?= htmlspecialchars($mensagem) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Parâmetros da Instância -->
        <div class="col-lg-6">
            <div class="fintech-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h3 class="fintech-card-title mb-0">Instância Evolution API</h3>
                        <small class="text-muted">Parâmetros de comunicação do servidor</small>
                    </div>
                    <i class="bi bi-whatsapp fs-4 text-success"></i>
                </div>

                <form method="POST">
                    <input type="hidden" name="acao" value="salvar_config">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">URL da API</label>
                            <input type="url" name="whatsapp_api_url" class="form-control font-monospace" placeholder="https://api.seudominio.com" value="<?= htmlspecialchars($apiUrl) ?>" <?= $semInstancia ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">API Key / Token de Acesso</label>
                            <input type="password" name="whatsapp_api_key" class="form-control font-monospace" placeholder="••••••••••••••••••••••••" value="<?= htmlspecialchars($apiKey) ?>" <?= $semInstancia ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Nome da Instância</label>
                            <input type="text" name="whatsapp_instance" class="form-control font-monospace" placeholder="minha_empresa" value="<?= htmlspecialchars($instance) ?>" <?= $semInstancia ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="whatsapp_ativo" value="1" id="whatsappAtivoV2" <?= $whatsappAtivo === '1' ? 'checked' : '' ?> <?= $semInstancia ? 'disabled' : '' ?>>
                                <label class="form-check-label fw-bold text-slate-900" for="whatsappAtivoV2">Habilitar envios automáticos por WhatsApp</label>
                                <small class="text-muted d-block">Ao ativar, o sistema enviará avisos automáticos conforme a régua de cobrança.</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-2 mt-4 pt-3 border-top">
                        <button type="submit" name="acao" value="testar" class="btn btn-fintech-secondary">
                            <i class="bi bi-arrow-repeat me-1"></i> Testar Comunicação
                        </button>
                        <button type="submit" class="btn btn-fintech-primary px-4" <?= $semInstancia ? 'disabled' : '' ?>>
                            <i class="bi bi-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- QR Code & Status da Sessão -->
        <div class="col-lg-6">
            <div class="fintech-card p-4 h-100 text-center d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom text-start">
                        <div>
                            <h3 class="fintech-card-title mb-0">Status de Conexão com o WhatsApp</h3>
                            <small class="text-muted">Pareie seu smartphone para envio de mensagens</small>
                        </div>
                        <i class="bi bi-phone fs-4 text-primary"></i>
                    </div>

                    <?php if ($statusConexao === 'conectado'): ?>
                        <div class="py-5">
                            <div class="mb-3" style="width:72px;height:72px;border-radius:50%;background:#ecfdf5;color:#059669;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="bi bi-check-lg fs-1"></i>
                            </div>
                            <h5 class="fw-bold text-slate-900 mb-1">WhatsApp Conectado com Sucesso!</h5>
                            <p class="text-muted small mx-auto" style="max-width:360px;">
                                Sua sessão está autenticada e pronta para disparar boletos, códigos PIX e avisos de vencimento em tempo real.
                            </p>
                            <span class="fintech-badge success px-3 py-2"><span class="dot"></span> Instância Online: <?= htmlspecialchars($instance) ?></span>
                        </div>
                    <?php elseif ($qrCode): ?>
                        <div class="py-3">
                            <div class="p-3 bg-white border d-inline-block rounded-3 shadow-sm mb-3">
                                <img src="data:image/png;base64,<?= htmlspecialchars($qrCode) ?>" alt="QR Code WhatsApp" style="width:240px;height:240px;object-fit:contain;">
                            </div>
                            <h6 class="fw-bold text-slate-900 mb-1">Escaneie o QR Code</h6>
                            <p class="text-muted small mb-0">Abra o WhatsApp no seu celular, vá em <strong>Aparelhos Conectados → Conectar Aparelho</strong> e aponte para a tela.</p>
                        </div>
                    <?php else: ?>
                        <div class="py-5">
                            <div class="mb-3" style="width:72px;height:72px;border-radius:50%;background:#f1f5f9;color:#64748b;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="bi bi-qr-code fs-1"></i>
                            </div>
                            <h6 class="fw-bold text-slate-900 mb-1">Nenhum QR Code Ativo</h6>
                            <p class="text-muted small mx-auto mb-3" style="max-width:340px;">
                                Verifique as credenciais da API ou clique em recarregar para obter uma nova chave de pareamento.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pt-3 border-top">
                    <a href="whatsapp.php" class="btn btn-fintech-secondary w-100">
                        <i class="bi bi-arrow-clockwise me-1"></i> Atualizar Status da Sessão
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

