<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_config') {
        saveConfig('whatsapp_api_url', trim($_POST['whatsapp_api_url'] ?? ''));
        saveConfig('whatsapp_api_key', trim($_POST['whatsapp_api_key'] ?? ''));
        saveConfig('whatsapp_instance', trim($_POST['whatsapp_instance'] ?? ''));
        saveConfig('whatsapp_ativo', isset($_POST['whatsapp_ativo']) ? '1' : '0');
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

$apiUrl = rtrim(getConfig('whatsapp_api_url', ''), '/');
$apiKey = getConfig('whatsapp_api_key', '');
$instance = getConfig('whatsapp_instance', '');
$whatsappAtivo = getConfig('whatsapp_ativo', '0');

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
    if ($state && ($state['state'] ?? '') === 'open') {
        $statusConexao = 'conectado';
    } else {
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
        if ($qrData && isset($qrData['base64'])) {
            $qrCode = $qrData['base64'];
        }
    }
}

$pageTitle = 'WhatsApp';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>WhatsApp - Evolution API</h5>
        </div>
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

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fab fa-whatsapp me-2" style="color:#25D366;"></i>Configuração da Evolution API</h6>
                    <form method="POST">
                        <input type="hidden" name="acao" value="salvar_config">
                        <div class="mb-3">
                            <label class="form-label">URL da API <small class="text-muted">(ex: https://sua-api.onrender.com)</small></label>
                            <input type="url" name="whatsapp_api_url" class="form-control" value="<?= htmlspecialchars($apiUrl) ?>" placeholder="https://sua-api.onrender.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="whatsapp_api_key" class="form-control" value="<?= htmlspecialchars($apiKey) ?>" placeholder="Sua chave da API">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome da Instância</label>
                            <input type="text" name="whatsapp_instance" class="form-control" value="<?= htmlspecialchars($instance) ?>" placeholder="minha-instancia">
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input type="checkbox" name="whatsapp_ativo" class="form-check-input" id="whatsappAtivo" <?= $whatsappAtivo === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="whatsappAtivo">Ativar envio via WhatsApp no cron</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                        </div>
                    </form>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="acao" value="testar">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-plug me-1"></i>Testar Conexão</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-qrcode me-2"></i>Status da Conexão</h6>
                    <?php if (empty($apiUrl) || empty($apiKey) || empty($instance)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>Preencha a URL, API Key e Instância ao lado para conectar o WhatsApp.
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

        <div class="row mt-4">
            <div class="col-12">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Como configurar</h6>
                    <ol class="mb-0" style="font-size:0.9rem;">
                        <li>Crie uma conta no <a href="https://www.render.com" target="_blank">Render.com</a> (grátis)</li>
                        <li>Crie um "Web Service" e conecte o repositório da <a href="https://github.com/EvolutionAPI/evolution-api" target="_blank">Evolution API</a></li>
                        <li>Aguarde o deploy (pode levar alguns minutos)</li>
                        <li>Copie a URL gerada (ex: https://sua-api.onrender.com) e cole acima</li>
                        <li>Crie uma instância no painel da Evolution API e copie a API Key</li>
                        <li>Preencha os dados acima, salve e escaneie o QR Code</li>
                        <li>Ative o envio via WhatsApp e configure os dias na Config. de Envios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>