<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';

$mensagem = '';
$tipo = '';

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
            $mensagem = 'Erro ao salvar configurações.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'salvar_inter') {
        $pdo = getConnection();
        $campos = ['inter_client_id', 'inter_client_secret', 'inter_conta', 'inter_webhook_url'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$campo, $valor, $valor]);
        }

        $certDir = __DIR__ . '/../config/inter_certs';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $certCampos = ['inter_cert_crt' => 'certificado.crt', 'inter_cert_key' => 'certificado.key', 'inter_cert_webhook' => 'certificado_webhook.pem'];
        foreach ($certCampos as $campo => $nomeArquivo) {
            if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['crt', 'key', 'pem'])) {
                    $mensagem = 'Extensão de arquivo inválida para ' . $nomeArquivo . '. Use .crt, .key ou .pem';
                    $tipo = 'danger';
                    break;
                }
                $destino = $certDir . '/' . $nomeArquivo;
                if (move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
                    $stmt->execute([$campo, $destino, $destino]);
                } else {
                    $mensagem = 'Erro ao enviar arquivo ' . $nomeArquivo;
                    $tipo = 'danger';
                }
            }
        }

        if (empty($mensagem)) {
            $mensagem = 'Configurações do Banco Inter salvas com sucesso!';
            $tipo = 'success';
        }
    }

    if ($acao === 'salvar_bb') {
        $pdo = getConnection();
        $campos = ['bb_client_id', 'bb_client_secret', 'bb_conta', 'bb_agencia', 'bb_convenio', 'bb_carteira', 'bb_variacao', 'bb_webhook_url', 'bb_chave_pix', 'bb_ambiente'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$campo, $valor, $valor]);
        }
        $mensagem = 'Configurações do Banco do Brasil salvas com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'salvar_pix_manual') {
        $pdo = getConnection();
        $campos = ['pix_manual_chave', 'pix_manual_banco', 'pix_manual_favorecido', 'pix_manual_cnpj', 'pix_manual_whatsapp'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$campo, $valor, $valor]);
        }
        $mensagem = 'Configurações do PIX Manual salvas com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'ativar_api') {
        $pdo = getConnection();
        $api = $_POST['api'] ?? '';
        if (in_array($api, ['mercadopago', 'inter', 'bb', 'pix_manual'])) {
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute(['api_pagamento_ativa', $api, $api]);
            $nomes = ['mercadopago' => 'Mercado Pago', 'inter' => 'Banco Inter', 'bb' => 'Banco do Brasil', 'pix_manual' => 'PIX Manual'];
            $mensagem = "API ativa alterada para {$nomes[$api]}!";
            $tipo = 'success';
        }
    }
}

$config = getAllConfig();
$mpConfig = getMPConfig();
$apiAtiva = $config['api_pagamento_ativa'] ?? 'mercadopago';

$pageTitle = 'API de Pagamento';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>API de Pagamento</h5>
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

        <div class="form-card mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <span class="me-2">API Ativa:</span>
                    <?php if ($apiAtiva === 'mercadopago'): ?>
                        <span class="badge bg-success fs-6"><i class="fab fa-pix me-1"></i> Mercado Pago</span>
                    <?php elseif ($apiAtiva === 'inter'): ?>
                        <span class="badge bg-info fs-6"><i class="fas fa-university me-1"></i> Banco Inter</span>
                    <?php elseif ($apiAtiva === 'pix_manual'): ?>
                        <span class="badge bg-warning text-dark fs-6"><i class="fas fa-qrcode me-1"></i> PIX Manual</span>
                    <?php else: ?>
                        <span class="badge bg-secondary fs-6">Nenhuma</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs" id="apiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $apiAtiva === 'mercadopago' ? 'active' : '' ?>" id="mp-tab" data-bs-toggle="tab" data-bs-target="#mercadopago" type="button" role="tab">
                    <img src="/cobranca/assets/img/mercado-pago-logo.png" alt="MP" style="height:18px; margin-right:6px; vertical-align:middle;"> Mercado Pago
                    <?php if ($apiAtiva === 'mercadopago'): ?>
                        <span class="badge bg-success ms-1">Ativa</span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $apiAtiva === 'inter' ? 'active' : '' ?>" id="inter-tab" data-bs-toggle="tab" data-bs-target="#bancoInter" type="button" role="tab">
                    <img src="/cobranca/assets/img/banco-inter-logo-0-1.png" alt="Inter" style="height:18px; margin-right:6px; vertical-align:middle;"> Banco Inter
                    <?php if ($apiAtiva === 'inter'): ?>
                        <span class="badge bg-success ms-1">Ativa</span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $apiAtiva === 'pix_manual' ? 'active' : '' ?>" id="pixManual-tab" data-bs-toggle="tab" data-bs-target="#pixManual" type="button" role="tab">
                    <i class="fas fa-qrcode me-1"></i> PIX Manual
                    <?php if ($apiAtiva === 'pix_manual'): ?>
                        <span class="badge bg-success ms-1">Ativa</span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3 form-card rounded-top-0" id="apiTabContent">

            <!-- ==================== MERCADO PAGO ==================== -->
            <div class="tab-pane fade show <?= $apiAtiva === 'mercadopago' ? 'active' : '' ?>" id="mercadopago" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <h6 class="mb-3"><i class="fab fa-pix me-2"></i>Credenciais do Mercado Pago</h6>
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Acesse <a href="https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post" target="_blank">Mercado Pago Developers</a> para obter suas credenciais.
                        </div>
                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_mp">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Access Token</label>
                                    <input type="text" name="mp_access_token" class="form-control font-monospace"
                                        placeholder="APP_USR-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                        value="<?= htmlspecialchars($mpConfig['mp_access_token'] ?? '') ?>">
                                    <small class="text-muted">Token de produção (APP_USR-...)</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Public Key</label>
                                    <input type="text" name="mp_public_key" class="form-control font-monospace"
                                        placeholder="APP_USR-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                        value="<?= htmlspecialchars($mpConfig['mp_public_key'] ?? '') ?>">
                                    <small class="text-muted">Chave pública para o frontend</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">URL do Webhook</label>
                                    <input type="url" name="mp_webhook_url" class="form-control"
                                        placeholder="https://seudominio.com/cobranca/api/webhook.php"
                                        value="<?= htmlspecialchars($mpConfig['mp_webhook_url'] ?? '') ?>">
                                    <small class="text-muted">URL para receber notificações de pagamento</small>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Configurações
                                </button>
                            </div>
                        </form>
                        <?php if ($apiAtiva !== 'mercadopago'): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="acao" value="ativar_api">
                            <input type="hidden" name="api" value="mercadopago">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="fas fa-power-off me-1"></i> Ativar Mercado Pago
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-card">
                            <h6 class="mb-3"><i class="fas fa-question-circle me-2"></i>Como Configurar</h6>
                            <ol class="small text-muted mb-0" style="padding-left: 20px;">
                                <li class="mb-2">Acesse sua conta no <a href="https://www.mercadopago.com.br" target="_blank">Mercado Pago</a></li>
                                <li class="mb-2">Vá em <strong>Credenciais</strong> no menu de desenvolvedores</li>
                                <li class="mb-2">Copie o <strong>Access Token</strong> (produção)</li>
                                <li class="mb-2">Copie a <strong>Public Key</strong></li>
                                <li class="mb-2">Configure o <strong>Webhook</strong> na URL acima</li>
                                <li class="mb-2">Salve e clique em <strong>Ativar</strong></li>
                            </ol>
                        </div>
                        <div class="form-card mt-3">
                            <h6 class="mb-3"><i class="fas fa-server me-2"></i>Status</h6>
                            <?php if (!empty($mpConfig['mp_access_token'])): ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                                    <span>Credenciais configuradas</span>
                                </div>
                                <small class="text-muted d-block mt-2">Token: <?= substr($mpConfig['mp_access_token'], 0, 15) ?>...</small>
                            <?php else: ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2"><i class="fas fa-times"></i></span>
                                    <span>Não configurado</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== BANCO INTER ==================== -->
            <div class="tab-pane fade show <?= $apiAtiva === 'inter' ? 'active' : '' ?>" id="bancoInter" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <h6 class="mb-3"><i class="fas fa-university me-2"></i>Credenciais do Banco Inter</h6>
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Acesse <a href="https://developers.inter.co/" target="_blank">Inter Developers</a> para obter suas credenciais via Open Banking.
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="acao" value="salvar_inter">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="inter_client_id" class="form-control font-monospace"
                                        placeholder="Seu Client ID do Inter"
                                        value="<?= htmlspecialchars($config['inter_client_id'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Client Secret</label>
                                    <input type="password" name="inter_client_secret" class="form-control font-monospace"
                                        placeholder="Seu Client Secret do Inter"
                                        value="<?= htmlspecialchars($config['inter_client_secret'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Conta (Agência + Conta)</label>
                                    <input type="text" name="inter_conta" class="form-control"
                                        placeholder="0001-1234567-8"
                                        value="<?= htmlspecialchars($config['inter_conta'] ?? '') ?>">
                                    <small class="text-muted">Formato: agência-conta-dígito</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">URL do Webhook</label>
                                    <input type="url" name="inter_webhook_url" class="form-control"
                                        placeholder="https://seudominio.com/cobranca/api/webhook_inter.php"
                                        value="<?= htmlspecialchars($config['inter_webhook_url'] ?? '') ?>">
                                    <small class="text-muted">URL para receber notificações</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Certificado (.crt)</label>
                                    <input type="file" name="inter_cert_crt" class="form-control" accept=".crt,.pem,.cer">
                                    <?php
                                    $certCrt = $config['inter_cert_crt'] ?? '';
                                    if ($certCrt && file_exists($certCrt)): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certCrt) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Arquivo do certificado público</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Chave Privada (.key)</label>
                                    <input type="file" name="inter_cert_key" class="form-control" accept=".key,.pem">
                                    <?php
                                    $certKey = $config['inter_cert_key'] ?? '';
                                    if ($certKey && file_exists($certKey)): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certKey) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Arquivo da chave privada</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Certificado Webhook (.pem)</label>
                                    <input type="file" name="inter_cert_webhook" class="form-control" accept=".pem,.crt,.cer">
                                    <?php
                                    $certWebhook = $config['inter_cert_webhook'] ?? '';
                                    if ($certWebhook && file_exists($certWebhook)): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certWebhook) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Certificado para validação do webhook</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Configurações
                                </button>
                            </div>
                        </form>
                        <?php if ($apiAtiva !== 'inter'): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="acao" value="ativar_api">
                            <input type="hidden" name="api" value="inter">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="fas fa-power-off me-1"></i> Ativar Banco Inter
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-card">
                            <h6 class="mb-3"><i class="fas fa-question-circle me-2"></i>Como Configurar</h6>
                            <ol class="small text-muted mb-0" style="padding-left: 20px;">
                                <li class="mb-2">Acesse <a href="https://developers.inter.co/" target="_blank">developers.inter.co</a></li>
                                <li class="mb-2">Crie uma aplicação no portal de desenvolvedores</li>
                                <li class="mb-2">Copie o <strong>Client ID</strong></li>
                                <li class="mb-2">Copie o <strong>Client Secret</strong></li>
                                <li class="mb-2">Configure os escopos de <strong>Cobrança</strong> e <strong>Pagamentos</strong></li>
                                <li class="mb-2">Envie os certificados <strong>Client (.crt + .key)</strong></li>
                                <li class="mb-2">Envie o <strong>Certificado Webhook (.pem)</strong></li>
                                <li class="mb-2">Configure o <strong>Webhook</strong> na URL acima</li>
                                <li class="mb-2">Salve e clique em <strong>Ativar</strong></li>
                            </ol>
                        </div>
                        <div class="form-card mt-3">
                            <h6 class="mb-3"><i class="fas fa-server me-2"></i>Status</h6>
                            <?php if (!empty($config['inter_client_id'])): ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                                    <span>Credenciais configuradas</span>
                                </div>
                                <small class="text-muted d-block mt-2">Client ID: <?= substr($config['inter_client_id'], 0, 10) ?>...</small>
                            <?php else: ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2"><i class="fas fa-times"></i></span>
                                    <span>Não configurado</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== PIX MANUAL ==================== -->
            <div class="tab-pane fade show <?= $apiAtiva === 'pix_manual' ? 'active' : '' ?>" id="pixManual" role="tabpanel">
                <?php $pmConfig = getConfigPixManual(); ?>
                <div class="row">
                    <div class="col-lg-8">
                        <h6 class="mb-3"><i class="fas fa-qrcode me-2"></i>Configuração do PIX Manual</h6>
                        <div class="alert alert-warning py-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            O PIX Manual gera QR Codes estáticos a partir dos dados cadastrados abaixo. Não utiliza API externa — o cliente paga manualmente pelo app do banco.
                        </div>
                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_pix_manual">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Chave PIX</label>
                                    <input type="text" name="pix_manual_chave" class="form-control"
                                        placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória"
                                        value="<?= htmlspecialchars($pmConfig['pix_manual_chave']) ?>">
                                    <small class="text-muted">Chave que receberá os pagamentos</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Banco</label>
                                    <input type="text" name="pix_manual_banco" class="form-control"
                                        placeholder="Ex: Banco do Brasil, Itaú, Nubank..."
                                        value="<?= htmlspecialchars($pmConfig['pix_manual_banco']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Favorecido / Empresa</label>
                                    <input type="text" name="pix_manual_favorecido" class="form-control"
                                        placeholder="Nome que aparece no QR Code"
                                        value="<?= htmlspecialchars($pmConfig['pix_manual_favorecido']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CNPJ / CPF do Favorecido</label>
                                    <input type="text" name="pix_manual_cnpj" class="form-control"
                                        placeholder="00.000.000/0001-00"
                                        value="<?= htmlspecialchars($pmConfig['pix_manual_cnpj']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp (comprovante)</label>
                                    <input type="text" name="pix_manual_whatsapp" class="form-control"
                                        placeholder="5511999999999"
                                        value="<?= htmlspecialchars($pmConfig['pix_manual_whatsapp']) ?>">
                                    <small class="text-muted">Número com DDD para envio de comprovante</small>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Configurações
                                </button>
                            </div>
                        </form>
                        <?php if ($apiAtiva !== 'pix_manual'): ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="acao" value="ativar_api">
                            <input type="hidden" name="api" value="pix_manual">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="fas fa-power-off me-1"></i> Ativar PIX Manual
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-card">
                            <h6 class="mb-3"><i class="fas fa-question-circle me-2"></i>Como Funciona</h6>
                            <ol class="small text-muted mb-0" style="padding-left: 20px;">
                                <li class="mb-2">Preencha sua <strong>Chave PIX</strong> (CPF, CNPJ, e-mail ou chave aleatória)</li>
                                <li class="mb-2">Informe o <strong>Nome do Favorecido</strong> que aparecerá no QR Code</li>
                                <li class="mb-2">Salve e clique em <strong>Ativar PIX Manual</strong></li>
                                <li class="mb-2">Ao visualizar uma fatura, o sistema gera automaticamente o QR Code e o código copia e cola</li>
                                <li class="mb-2">O cliente escaneia o QR Code ou copia o código e paga pelo app do banco</li>
                            </ol>
                        </div>
                        <div class="form-card mt-3">
                            <h6 class="mb-3"><i class="fas fa-server me-2"></i>Status</h6>
                            <?php if (!empty($pmConfig['pix_manual_chave'])): ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                                    <span>Chave PIX configurada</span>
                                </div>
                                <small class="text-muted d-block mt-2">Chave: <?= htmlspecialchars(substr($pmConfig['pix_manual_chave'], 0, 15)) ?>...</small>
                                <?php if (!empty($pmConfig['pix_manual_favorecido'])): ?>
                                    <small class="text-muted d-block mt-1">Favorecido: <?= htmlspecialchars($pmConfig['pix_manual_favorecido']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2"><i class="fas fa-times"></i></span>
                                    <span>Não configurado</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
