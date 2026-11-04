<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/tutoriais.php';

$mensagem = '';
$tipo = '';
$adminId = getCurrentAdminId();
$configAdminId = $adminId > 0 ? $adminId : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_mp') {
        $accessToken = trim($_POST['mp_access_token'] ?? '');
        $publicKey = trim($_POST['mp_public_key'] ?? '');
        $webhookUrl = trim($_POST['mp_webhook_url'] ?? '');
        if (saveMPConfig($accessToken, $publicKey, $webhookUrl)) {
            $mensagem = 'Configurações do Mercado Pago salvas com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Erro ao salvar configurações do Mercado Pago.';
            $tipo = 'danger';
        }
        $cartao = isset($_POST['mp_cartao_credito']) && $_POST['mp_cartao_credito'] === '1' ? '1' : '0';
        saveConfig('mp_cartao_credito', $cartao);
        $maxParcelas = min(max((int) ($_POST['mp_max_parcelas'] ?? 12), 1), 12);
        saveConfig('mp_max_parcelas', (string) $maxParcelas);
    }

    if ($acao === 'salvar_inter') {
        $pdo = getConnection();
        $campos = ['inter_client_id', 'inter_client_secret', 'inter_conta', 'inter_webhook_url'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$configAdminId, $campo, $valor]);
        }

        $certDir = __DIR__ . '/../config/inter_certs/admin_' . $configAdminId;
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $certCampos = ['inter_cert_crt' => 'certificado.crt', 'inter_cert_key' => 'certificado.key', 'inter_cert_webhook' => 'certificado_webhook.pem'];
        foreach ($certCampos as $campo => $nomeArquivo) {
            if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['crt', 'key', 'pem'])) {
                    $mensagem = 'Extensão inválida para ' . $nomeArquivo . '. Utilize .crt, .key ou .pem';
                    $tipo = 'danger';
                    break;
                }
                $destino = $certDir . '/' . $nomeArquivo;
                if (move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                    $stmt->execute([$configAdminId, $campo, $destino]);
                } else {
                    $mensagem = 'Erro ao salvar certificado ' . $nomeArquivo;
                    $tipo = 'danger';
                }
            }
        }

        if (empty($mensagem)) {
            $mensagem = 'Configurações do Banco Inter salvas com sucesso!';
            $tipo = 'success';
        }
    }

    if ($acao === 'salvar_pix_manual') {
        $pdo = getConnection();
        $campos = ['pix_manual_chave', 'pix_manual_banco', 'pix_manual_favorecido', 'pix_manual_cnpj', 'pix_manual_whatsapp'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$configAdminId, $campo, $valor]);
        }
        $mensagem = 'Configurações do PIX Manual salvas com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'salvar_asaas') {
        $pdo = getConnection();
        $campos = ['asaas_api_key', 'asaas_ambiente', 'asaas_webhook_url', 'asaas_webhook_token'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$configAdminId, $campo, $valor]);
        }
        $mensagem = 'Configurações do Asaas salvas com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'ativar_api') {
        $pdo = getConnection();
        $api = $_POST['api'] ?? '';
        if (in_array($api, ['mercadopago', 'inter', 'pix_manual', 'asaas'])) {
            $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$configAdminId, 'api_pagamento_ativa', $api]);
            $nomes = ['mercadopago' => 'Mercado Pago', 'inter' => 'Banco Inter', 'pix_manual' => 'PIX Manual', 'asaas' => 'ASAAS'];
            $mensagem = "Gateway de pagamento alterado para {$nomes[$api]}!";
            $tipo = 'success';
        }
    }
}

$config = getAllConfig();
$mpConfig = getMPConfig();
$apiAtiva = $config['api_pagamento_ativa'] ?? 'mercadopago';

$mpWebhookPadrao = webhookUrlPadrao('webhook.php');
$interWebhookPadrao = webhookUrlPadrao('webhook_inter.php');
$asaasWebhookPadrao = webhookUrlPadrao('webhook_asaas.php');

$pageTitle = 'Gateways de Pagamento';
$pageCategory = 'Automações & APIs';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Gateways & Integrações de Pagamento</h2>
            <p class="text-muted small mb-0">Configure credenciais do Mercado Pago, Banco Inter, Asaas e Chave PIX Manual para liquidação automática.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Ativo Agora:</span>
            <?php
            $badges = [
                'mercadopago' => ['text' => 'Mercado Pago', 'class' => 'success'],
                'inter' => ['text' => 'Banco Inter', 'class' => 'info'],
                'pix_manual' => ['text' => 'PIX Manual', 'class' => 'warning'],
                'asaas' => ['text' => 'Asaas', 'class' => 'primary']
            ];
            $currentBadge = $badges[$apiAtiva] ?? ['text' => 'Desconhecido', 'class' => 'neutral'];
            ?>
            <span class="fintech-badge <?= $currentBadge['class'] ?>" style="font-size:12px; padding:6px 12px;">
                <span class="dot"></span> <?= $currentBadge['text'] ?>
            </span>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($mensagem) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Gateway Switcher Banner -->
    <div class="fintech-card p-3 mb-4" style="background:#ffffff; border:1px solid var(--fintech-border);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:var(--fintech-primary);color:#fff;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-toggles2 fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-slate-900">Selecione o Gateway Primário</h6>
                    <small class="text-muted">Apenas um meio processa e recebe os pagamentos automáticos simultaneamente.</small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="acao" value="ativar_api">
                    <input type="hidden" name="api" value="mercadopago">
                    <button type="submit" class="btn btn-sm <?= $apiAtiva === 'mercadopago' ? 'btn-fintech-primary' : 'btn-fintech-secondary' ?>">
                        <i class="bi bi-check2 me-1"></i> Mercado Pago
                    </button>
                </form>

                <form method="POST" class="d-inline">
                    <input type="hidden" name="acao" value="ativar_api">
                    <input type="hidden" name="api" value="inter">
                    <button type="submit" class="btn btn-sm <?= $apiAtiva === 'inter' ? 'btn-fintech-primary' : 'btn-fintech-secondary' ?>">
                        <i class="bi bi-bank me-1"></i> Banco Inter
                    </button>
                </form>

                <form method="POST" class="d-inline">
                    <input type="hidden" name="acao" value="ativar_api">
                    <input type="hidden" name="api" value="asaas">
                    <button type="submit" class="btn btn-sm <?= $apiAtiva === 'asaas' ? 'btn-fintech-primary' : 'btn-fintech-secondary' ?>">
                        <i class="bi bi-wallet2 me-1"></i> Asaas
                    </button>
                </form>

                <form method="POST" class="d-inline">
                    <input type="hidden" name="acao" value="ativar_api">
                    <input type="hidden" name="api" value="pix_manual">
                    <button type="submit" class="btn btn-sm <?= $apiAtiva === 'pix_manual' ? 'btn-fintech-primary' : 'btn-fintech-secondary' ?>">
                        <i class="bi bi-qr-code me-1"></i> PIX Manual
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabs de Configuração -->
    <ul class="nav nav-pills gap-2 mb-4" id="apiTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $apiAtiva === 'mercadopago' ? 'active' : '' ?>" id="mp-tab" data-bs-toggle="tab" data-bs-target="#mercadopago" type="button" role="tab" style="border-radius:10px; font-weight:600;">
                <i class="bi bi-wallet me-1"></i> Mercado Pago
                <?php if ($apiAtiva === 'mercadopago'): ?><span class="badge bg-light text-dark ms-1">Ativo</span><?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $apiAtiva === 'inter' ? 'active' : '' ?>" id="inter-tab" data-bs-toggle="tab" data-bs-target="#bancoInter" type="button" role="tab" style="border-radius:10px; font-weight:600;">
                <i class="bi bi-bank me-1"></i> Banco Inter
                <?php if ($apiAtiva === 'inter'): ?><span class="badge bg-light text-dark ms-1">Ativo</span><?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $apiAtiva === 'asaas' ? 'active' : '' ?>" id="asaas-tab" data-bs-toggle="tab" data-bs-target="#asaas" type="button" role="tab" style="border-radius:10px; font-weight:600;">
                <i class="bi bi-credit-card me-1"></i> ASAAS
                <?php if ($apiAtiva === 'asaas'): ?><span class="badge bg-light text-dark ms-1">Ativo</span><?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $apiAtiva === 'pix_manual' ? 'active' : '' ?>" id="pixManual-tab" data-bs-toggle="tab" data-bs-target="#pixManual" type="button" role="tab" style="border-radius:10px; font-weight:600;">
                <i class="bi bi-qr-code me-1"></i> PIX Manual
                <?php if ($apiAtiva === 'pix_manual'): ?><span class="badge bg-light text-dark ms-1">Ativo</span><?php endif; ?>
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="apiTabContent">

        <!-- ==================== MERCADO PAGO ==================== -->
        <div class="tab-pane fade <?= $apiAtiva === 'mercadopago' ? 'show active' : '' ?>" id="mercadopago" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="fintech-card p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="fintech-card-title mb-0">Credenciais Mercado Pago</h3>
                                <small class="text-muted">Gere suas chaves no Mercado Pago Developers</small>
                            </div>
                            <?php if (!empty($mpConfig['mp_access_token'])): ?>
                                <span class="fintech-badge success"><span class="dot"></span> Configurado</span>
                            <?php else: ?>
                                <span class="fintech-badge neutral"><span class="dot"></span> Não configurado</span>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_mp">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">Access Token (Produção) *</label>
                                    <input type="text" name="mp_access_token" class="form-control font-monospace" placeholder="APP_USR-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxx" value="<?= htmlspecialchars($mpConfig['mp_access_token'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">Public Key *</label>
                                    <input type="text" name="mp_public_key" class="form-control font-monospace" placeholder="APP_USR-xxxxxxxxxxxxxxxxx" value="<?= htmlspecialchars($mpConfig['mp_public_key'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">URL de Webhook (Notificações)</label>
                                    <input type="url" name="mp_webhook_url" class="form-control font-monospace" placeholder="<?= htmlspecialchars($mpWebhookPadrao) ?>" value="<?= htmlspecialchars(!empty($mpConfig['mp_webhook_url']) ? $mpConfig['mp_webhook_url'] : $mpWebhookPadrao) ?>">
                                    <small class="text-muted">Cadastre esta URL nas Notificações Webhook do Mercado Pago.</small>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="mp_cartao_credito" value="1" id="mpCartaoCreditoV2" <?= ($config['mp_cartao_credito'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="mpCartaoCreditoV2">
                                            Aceitar pagamento por Cartão de Crédito e Débito
                                        </label>
                                        <small class="d-block text-muted">Permite ao cliente pagar com cartão direto na tela de checkout da fatura.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Parcelamento Máximo no Cartão</label>
                                    <select name="mp_max_parcelas" class="form-select">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= (int) ($config['mp_max_parcelas'] ?? 12) === $i ? 'selected' : '' ?>>Até <?= $i ?>x</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-fintech-primary px-4">
                                    <i class="bi bi-save me-1"></i> Salvar Mercado Pago
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="fintech-card p-4 mb-4">
                        <h6 class="fw-bold mb-3 text-slate-900"><i class="bi bi-info-circle text-primary me-2"></i>Passo a Passo</h6>
                        <ol class="small text-muted mb-0 ps-3">
                            <li class="mb-2">Acesse <a href="https://www.mercadopago.com.br/developers" target="_blank" class="fw-semibold">Mercado Pago Developers</a>.</li>
                            <li class="mb-2">Vá em <strong>Suas Aplicações</strong> e selecione sua credencial de produção.</li>
                            <li class="mb-2">Copie o <strong>Access Token</strong> e a <strong>Public Key</strong>.</li>
                            <li class="mb-2">Configure a <strong>URL de Webhook</strong> nos eventos de pagamentos.</li>
                        </ol>
                    </div>
                    <?php renderTutorialVideo('mercadopago', 'Tutorial em Vídeo: Mercado Pago'); ?>
                </div>
            </div>
        </div>

        <!-- ==================== BANCO INTER ==================== -->
        <div class="tab-pane fade <?= $apiAtiva === 'inter' ? 'show active' : '' ?>" id="bancoInter" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="fintech-card p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="fintech-card-title mb-0">Credenciais Banco Inter (Open Banking)</h3>
                                <small class="text-muted">Integração oficial via API com certificados digitais mTLS</small>
                            </div>
                            <?php if (!empty($config['inter_client_id'])): ?>
                                <span class="fintech-badge success"><span class="dot"></span> Configurado</span>
                            <?php else: ?>
                                <span class="fintech-badge neutral"><span class="dot"></span> Não configurado</span>
                            <?php endif; ?>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="acao" value="salvar_inter">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">Client ID *</label>
                                    <input type="text" name="inter_client_id" class="form-control font-monospace" placeholder="Seu Client ID do Inter" value="<?= htmlspecialchars($config['inter_client_id'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">Client Secret *</label>
                                    <input type="password" name="inter_client_secret" class="form-control font-monospace" placeholder="Seu Client Secret" value="<?= htmlspecialchars($config['inter_client_secret'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Conta Corrente (Agência-Conta)</label>
                                    <input type="text" name="inter_conta" class="form-control" placeholder="0001-1234567-8" value="<?= htmlspecialchars($config['inter_conta'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">URL de Webhook Inter</label>
                                    <input type="url" name="inter_webhook_url" class="form-control font-monospace" placeholder="<?= htmlspecialchars($interWebhookPadrao) ?>" value="<?= htmlspecialchars(!empty($config['inter_webhook_url']) ? $config['inter_webhook_url'] : $interWebhookPadrao) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Certificado Cliente (.crt)</label>
                                    <input type="file" name="inter_cert_crt" class="form-control" accept=".crt,.pem,.cer">
                                    <?php $certCrt = $config['inter_cert_crt'] ?? ''; if ($certCrt && file_exists($certCrt)): ?>
                                        <small class="text-success"><i class="bi bi-check-circle me-1"></i><?= basename($certCrt) ?> salvo</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Chave Privada (.key)</label>
                                    <input type="file" name="inter_cert_key" class="form-control" accept=".key,.pem">
                                    <?php $certKey = $config['inter_cert_key'] ?? ''; if ($certKey && file_exists($certKey)): ?>
                                        <small class="text-success"><i class="bi bi-check-circle me-1"></i><?= basename($certKey) ?> salvo</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">Certificado Webhook (.pem)</label>
                                    <input type="file" name="inter_cert_webhook" class="form-control" accept=".pem,.crt,.cer">
                                    <?php $certW = $config['inter_cert_webhook'] ?? ''; if ($certW && file_exists($certW)): ?>
                                        <small class="text-success"><i class="bi bi-check-circle me-1"></i><?= basename($certW) ?> salvo</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-fintech-primary px-4">
                                    <i class="bi bi-save me-1"></i> Salvar Banco Inter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="fintech-card p-4 mb-4">
                        <h6 class="fw-bold mb-3 text-slate-900"><i class="bi bi-info-circle text-primary me-2"></i>Como Configurar</h6>
                        <ol class="small text-muted mb-0 ps-3">
                            <li class="mb-2">Acesse o portal <a href="https://developers.inter.co/" target="_blank" class="fw-semibold">Inter Developers</a>.</li>
                            <li class="mb-2">Crie uma aplicação com escopos de Cobrança / Boletos / PIX.</li>
                            <li class="mb-2">Faça o download do par de certificados e envie nos campos ao lado.</li>
                        </ol>
                    </div>
                    <?php renderTutorialVideo('inter', 'Tutorial em Vídeo: Banco Inter'); ?>
                </div>
            </div>
        </div>

        <!-- ==================== ASAAS ==================== -->
        <div class="tab-pane fade <?= $apiAtiva === 'asaas' ? 'show active' : '' ?>" id="asaas" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="fintech-card p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="fintech-card-title mb-0">Credenciais ASAAS</h3>
                                <small class="text-muted">Integração simplificada com boletos e PIX</small>
                            </div>
                            <?php if (!empty($config['asaas_api_key'])): ?>
                                <span class="fintech-badge success"><span class="dot"></span> Configurado</span>
                            <?php else: ?>
                                <span class="fintech-badge neutral"><span class="dot"></span> Não configurado</span>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_asaas">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">Chave de API (API Key) *</label>
                                    <input type="password" name="asaas_api_key" class="form-control font-monospace" placeholder="$aact_YTU5YTE0M2M6N2Z..." value="<?= htmlspecialchars($config['asaas_api_key'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Ambiente</label>
                                    <select name="asaas_ambiente" class="form-select">
                                        <option value="producao" <?= ($config['asaas_ambiente'] ?? 'producao') === 'producao' ? 'selected' : '' ?>>Produção</option>
                                        <option value="sandbox" <?= ($config['asaas_ambiente'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Homologação)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Token do Webhook</label>
                                    <input type="text" name="asaas_webhook_token" class="form-control font-monospace" placeholder="Token secreto para validação" value="<?= htmlspecialchars($config['asaas_webhook_token'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">URL de Webhook</label>
                                    <input type="url" name="asaas_webhook_url" class="form-control font-monospace" placeholder="<?= htmlspecialchars($asaasWebhookPadrao) ?>" value="<?= htmlspecialchars(!empty($config['asaas_webhook_url']) ? $config['asaas_webhook_url'] : $asaasWebhookPadrao) ?>">
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-fintech-primary px-4">
                                    <i class="bi bi-save me-1"></i> Salvar Asaas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="fintech-card p-4">
                        <h6 class="fw-bold mb-3 text-slate-900"><i class="bi bi-info-circle text-primary me-2"></i>Integração Asaas</h6>
                        <p class="small text-muted">Acesse <strong>Menu → Minha Conta → Integrações</strong> no painel do Asaas para gerar sua Chave de API de Produção.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== PIX MANUAL ==================== -->
        <div class="tab-pane fade <?= $apiAtiva === 'pix_manual' ? 'show active' : '' ?>" id="pixManual" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="fintech-card p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div>
                                <h3 class="fintech-card-title mb-0">Chave PIX Manual</h3>
                                <small class="text-muted">Receba transferências PIX diretamente na sua conta corrente com confirmação manual</small>
                            </div>
                            <?php if (!empty($config['pix_manual_chave'])): ?>
                                <span class="fintech-badge success"><span class="dot"></span> Configurado</span>
                            <?php else: ?>
                                <span class="fintech-badge neutral"><span class="dot"></span> Não configurado</span>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_pix_manual">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Chave PIX *</label>
                                    <input type="text" name="pix_manual_chave" class="form-control font-monospace" placeholder="CPF, CNPJ, E-mail, Celular ou EVP" value="<?= htmlspecialchars($config['pix_manual_chave'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Instituição Bancária</label>
                                    <input type="text" name="pix_manual_banco" class="form-control" placeholder="Ex: Nubank, Itaú, Bradesco" value="<?= htmlspecialchars($config['pix_manual_banco'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">Nome do Favorecido</label>
                                    <input type="text" name="pix_manual_favorecido" class="form-control" placeholder="Nome Completo / Razão Social" value="<?= htmlspecialchars($config['pix_manual_favorecido'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-semibold">CPF / CNPJ do Titular</label>
                                    <input type="text" name="pix_manual_cnpj" class="form-control font-monospace" placeholder="00.000.000/0000-00" value="<?= htmlspecialchars($config['pix_manual_cnpj'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-semibold">WhatsApp para Envio de Comprovante</label>
                                    <input type="text" name="pix_manual_whatsapp" class="form-control" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($config['pix_manual_whatsapp'] ?? '') ?>">
                                    <small class="text-muted">Número onde os clientes enviarão o comprovante após a transferência.</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-fintech-primary px-4">
                                    <i class="bi bi-save me-1"></i> Salvar PIX Manual
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="fintech-card p-4">
                        <h6 class="fw-bold mb-3 text-slate-900"><i class="bi bi-info-circle text-primary me-2"></i>Como Funciona o PIX Manual</h6>
                        <p class="small text-muted mb-2">Com o PIX Manual ativo, o cliente recebe a sua chave PIX com um botão "Copiar Chave".</p>
                        <p class="small text-muted mb-0">Após a transferência, o cliente envia o comprovante diretamente no seu WhatsApp e você dá baixa na fatura com 1 clique.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

