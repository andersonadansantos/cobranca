<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/inter_pix.php';
require_once __DIR__ . '/../config/mercadopago.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_inter') {
        $pdo = getConnection();
        $campos = ['super_inter_client_id', 'super_inter_client_secret', 'super_inter_conta', 'super_inter_webhook_url'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$campo, $valor, $valor]);
        }

        $certDir = __DIR__ . '/../config/inter_certs_super';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $certCampos = ['super_inter_cert_crt' => 'certificado.crt', 'super_inter_cert_key' => 'certificado.key', 'super_inter_cert_webhook' => 'certificado_webhook.pem'];
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

    if ($acao === 'salvar_mp') {
        $accessToken = trim($_POST['super_mp_access_token'] ?? '');
        $publicKey = trim($_POST['super_mp_public_key'] ?? '');
        $webhookUrl = trim($_POST['super_mp_webhook_url'] ?? '');
        if (saveMPConfigSuper($accessToken, $publicKey, $webhookUrl)) {
            $mensagem = 'Configurações do Mercado Pago salvas com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Erro ao salvar configurações.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'ativar_plano_api') {
        $api = $_POST['api'] ?? '';
        if (in_array($api, ['inter', 'mercadopago'], true)) {
            salvarConfigGlobal('super_plano_api', $api);
            $nomes = ['inter' => 'Banco Inter', 'mercadopago' => 'Mercado Pago'];
            $mensagem = "API responsável pelo PIX de Planos alterada para {$nomes[$api]}!";
            $tipo = 'success';
        } else {
            $mensagem = 'API inválida.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'salvar_metodos_plano') {
        $cartaoAtivo = ($_POST['super_plano_cartao'] ?? '') === '1' ? '1' : '0';
        $pixBoletoAtivo = ($_POST['super_plano_pix_boleto'] ?? '') === '1' ? '1' : '0';
        $parcelas = max(1, min((int)($_POST['super_mp_max_parcelas'] ?? 12), 12));

        salvarConfigGlobal('super_plano_cartao', $cartaoAtivo);
        salvarConfigGlobal('super_plano_pix_boleto', $pixBoletoAtivo);
        salvarConfigGlobal('super_mp_max_parcelas', (string)$parcelas);

        if ($cartaoAtivo === '0' && $pixBoletoAtivo === '0') {
            $mensagem = 'Atenção: todos os métodos de pagamento de plano foram desativados. Os admins não conseguirão contratar planos.';
            $tipo = 'warning';
        } else {
            $mensagem = 'Métodos de pagamento do site (planos) atualizados com sucesso!';
            $tipo = 'success';
        }
    }
}

$config = getConfigInterSuper();
$interBaseUrl = getInterSuperBaseUrl();
$temCredenciais = !empty($config['super_inter_client_id']) && !empty($config['super_inter_client_secret']);

$mpConfigSuper = getMPConfigSuper();
$temCredenciaisMp = !empty($mpConfigSuper['super_mp_access_token']);

$planoApiAtiva = planoPagamentoAtivo();

$cartaoPlanosAtivo = getConfigGlobal('super_plano_cartao', '1') === '1';
$pixBoletoPlanosAtivo = getConfigGlobal('super_plano_pix_boleto', '1') === '1';
$maxParcelasPlanos = max(1, min((int)getConfigGlobal('super_mp_max_parcelas', '12'), 12));

$rotulosMetodo = ['pix' => 'PIX', 'boleto' => 'Boleto', 'cartao' => 'Cartão'];
$iconesMetodo = ['pix' => 'fa-qrcode', 'boleto' => 'fa-barcode', 'cartao' => 'fa-credit-card'];
$metodosBr = ['pix', 'boleto', 'cartao'];
$metodosFora = ['cartao'];
if (!$cartaoPlanosAtivo) {
    $metodosBr = array_values(array_diff($metodosBr, ['cartao']));
    $metodosFora = [];
}
if (!$pixBoletoPlanosAtivo) {
    $metodosBr = array_values(array_diff($metodosBr, ['pix', 'boleto']));
}

$pageTitle = 'API Pagamento';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-university me-1"></i> API Pagamento</h5>
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

        <!-- API ativa para PIX de Planos -->
        <div class="form-card mb-4">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>API responsável pelo PIX de Planos</h6>
            </div>
            <div class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <span class="me-2 text-muted">API ativa:</span>
                    <?php if ($planoApiAtiva === 'mercadopago'): ?>
                        <span class="badge badge-api-ativa bg-success"><img src="/cobranca/assets/img/mercado-pago-logo.png" alt="MP" style="height:14px; margin-right:4px; vertical-align:middle;"> Mercado Pago</span>
                    <?php else: ?>
                        <span class="badge badge-api-ativa bg-success"><i class="fas fa-university me-1"></i> Banco Inter</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($planoApiAtiva !== 'inter'): ?>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="acao" value="ativar_plano_api">
                        <input type="hidden" name="api" value="inter">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-power-off me-1"></i> Ativar Banco Inter</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($planoApiAtiva !== 'mercadopago'): ?>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="acao" value="ativar_plano_api">
                        <input type="hidden" name="api" value="mercadopago">
                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-power-off me-1"></i> Ativar Mercado Pago</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Métodos de pagamento do site (planos) -->
        <style>
            .method-card { transition: border-color .15s ease, background-color .15s ease; }
            .method-card.is-on { border-color: #198754 !important; background-color: #f4fbf7; }
            .method-icon { width: 46px; height: 46px; flex: 0 0 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #475569; background: #f1f5f9; transition: background-color .15s ease, color .15s ease; }
            .method-card.is-on .method-icon { background: #198754; color: #fff; }
            .form-card .badge-auto { width: auto; height: auto; padding: 4px 10px; font-size: 0.72rem; }
        </style>
        <div class="form-card mb-4">
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Métodos de pagamento do site (planos)</h6>
                    <span class="small text-muted"><i class="fas fa-shopping-cart me-1"></i>Contratação de planos na página <strong>Planos</strong></span>
                </div>
            </div>
            <div class="p-3">
                <?php if ($metodosBr === [] && $metodosFora === []): ?>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Todos os métodos estão desativados. Habilite ao menos um deles para que os admins consigam contratar planos.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="acao" value="salvar_metodos_plano">

                    <div class="row g-3">
                        <!-- Cartão -->
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 d-flex align-items-center gap-3 h-100 method-card <?= $cartaoPlanosAtivo ? 'is-on' : '' ?>">
                                <div class="method-icon"><i class="fas fa-credit-card"></i></div>
                                <div class="flex-grow-1 lh-sm">
                                    <div class="fw-medium mb-1">Cartão de crédito/débito</div>
                                    <div class="text-muted small">
                                        <i class="fas fa-globe-americas me-1"></i>Brasil e exterior
                                        <span class="mx-1">·</span> <strong>Mercado Pago</strong>
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input method-toggle" type="checkbox" id="swCartaoPlano" name="super_plano_cartao" value="1" <?= $cartaoPlanosAtivo ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <!-- PIX + Boleto -->
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 d-flex align-items-center gap-3 h-100 method-card <?= $pixBoletoPlanosAtivo ? 'is-on' : '' ?>">
                                <div class="method-icon"><i class="fas fa-qrcode"></i></div>
                                <div class="flex-grow-1 lh-sm">
                                    <div class="fw-medium mb-1">PIX + Boleto bancário</div>
                                    <div class="text-muted small">
                                        <i class="fas fa-globe-americas me-1"></i>Brasil
                                        <span class="mx-1">·</span> <strong>Banco Inter</strong>
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input method-toggle" type="checkbox" id="swPixBoletoPlano" name="super_plano_pix_boleto" value="1" <?= $pixBoletoPlanosAtivo ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <!-- Parcelamento -->
                        <div class="col-md-5">
                            <div class="border rounded-3 p-3 h-100">
                                <label class="form-label fw-medium mb-1" for="super_mp_max_parcelas"><i class="fas fa-layer-group me-1"></i> Parcelas no cartão</label>
                                <select id="super_mp_max_parcelas" name="super_mp_max_parcelas" class="form-select">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= $maxParcelasPlanos === $i ? 'selected' : '' ?>><?= $i ?>x<?= $i === 1 ? ' (à vista)' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                                <small class="text-muted mt-1 d-block">Máximo na contratação de plano (crédito).</small>
                            </div>
                        </div>

                        <!-- O que cada visitante vê -->
                        <div class="col-md-7">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="fw-medium mb-2"><i class="fas fa-eye me-1"></i> O que cada visitante vê</div>
                                <div class="d-flex flex-column">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <i class="fas fa-globe-americas text-muted"></i>
                                        <strong class="small">Brasil</strong>
                                        <span class="small text-muted">· pt-BR</span>
                                    </div>
                                    <div class="mt-2 d-flex gap-1 flex-wrap">
                                        <?php if (empty($metodosBr)): ?>
                                            <span class="badge badge-auto bg-light text-muted border">Nenhum método</span>
                                        <?php else: ?>
                                            <?php foreach ($metodosBr as $m): ?>
                                                <span class="badge badge-auto bg-success-subtle text-success border border-success-subtle"><i class="fas <?= $iconesMetodo[$m] ?> me-1"></i><?= $rotulosMetodo[$m] ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex flex-column">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <i class="fas fa-globe-europe text-muted"></i>
                                        <strong class="small">Fora do Brasil</strong>
                                        <span class="small text-muted">· es-MX, es-AR, es-CO, es-CL, es-PE</span>
                                    </div>
                                    <div class="mt-2 d-flex gap-1 flex-wrap">
                                        <?php if (empty($metodosFora)): ?>
                                            <span class="badge badge-auto bg-light text-muted border">Nenhum método</span>
                                        <?php else: ?>
                                            <?php foreach ($metodosFora as $m): ?>
                                                <span class="badge badge-auto bg-success-subtle text-success border border-success-subtle"><i class="fas <?= $iconesMetodo[$m] ?> me-1"></i><?= $rotulosMetodo[$m] ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Métodos</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('method-toggle')) {
                    var card = e.target.closest('.method-card');
                    if (card) {
                        card.classList.toggle('is-on', e.target.checked);
                    }
                }
            });
        </script>

        <ul class="nav nav-tabs" id="apiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $planoApiAtiva === 'inter' ? 'active' : '' ?>" id="inter-tab" data-bs-toggle="tab" data-bs-target="#abaInter" type="button" role="tab">
                    <img src="/cobranca/assets/img/banco-inter-logo-0-1.png" alt="Inter" style="height:18px; margin-right:6px; vertical-align:middle;"> Banco Inter (PIX de Planos)
                    <?php if ($planoApiAtiva === 'inter'): ?>
                        <span class="badge badge-api-active bg-success ms-1">Ativa</span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $planoApiAtiva === 'mercadopago' ? 'active' : '' ?>" id="mp-tab" data-bs-toggle="tab" data-bs-target="#abaMp" type="button" role="tab">
                    <img src="/cobranca/assets/img/mercado-pago-logo.png" alt="MP" style="height:18px; margin-right:6px; vertical-align:middle;"> Mercado Pago
                    <?php if ($planoApiAtiva === 'mercadopago'): ?>
                        <span class="badge badge-api-active bg-success ms-1">Ativa</span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3 form-card rounded-top-0" id="apiTabContent">

            <!-- ==================== BANCO INTER ==================== -->
            <div class="tab-pane fade show <?= $planoApiAtiva === 'inter' ? 'active' : '' ?>" id="abaInter" role="tabpanel">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3 p-3 rounded-3 border <?= $planoApiAtiva === 'inter' ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle' ?>">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($planoApiAtiva === 'inter'): ?>
                            <span class="badge badge-auto bg-success"><i class="fas fa-check me-1"></i> API Ativa</span>
                            <small class="text-success">Responsável pelo recebimento de PIX (e boleto) na contratação de planos.</small>
                        <?php else: ?>
                            <span class="badge badge-auto bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> API Inativa</span>
                            <small class="text-muted">Ao ativar, esta API passa a receber o PIX (e boleto) dos planos contratados.</small>
                        <?php endif; ?>
                    </div>
                    <?php if ($planoApiAtiva !== 'inter'): ?>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="acao" value="ativar_plano_api">
                            <input type="hidden" name="api" value="inter">
                            <button type="submit" class="btn btn-success btn-sm fw-bold"><i class="fas fa-power-off me-1"></i> ATIVAR API</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0"><i class="fas fa-university me-2"></i>Credenciais do Banco Inter (PIX de Planos)</h6>
                            </div>
                            <div class="p-3">
                                <div class="alert alert-info py-2 mb-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Estas credenciais são <strong>exclusivas do Super Admin</strong> e responsáveis pelo recebimento via PIX quando os admins contratam seus planos. São independentes das credenciais do painel admin.
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="acao" value="salvar_inter">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Client ID</label>
                                            <input type="text" name="super_inter_client_id" class="form-control font-monospace"
                                                placeholder="Seu Client ID do Inter"
                                                value="<?= htmlspecialchars($config['super_inter_client_id'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Client Secret</label>
                                            <input type="password" name="super_inter_client_secret" class="form-control font-monospace"
                                                placeholder="Seu Client Secret do Inter"
                                                value="<?= htmlspecialchars($config['super_inter_client_secret'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Conta (Agência + Conta)</label>
                                            <input type="text" name="super_inter_conta" class="form-control"
                                                placeholder="0001-1234567-8"
                                                value="<?= htmlspecialchars($config['super_inter_conta'] ?? '') ?>">
                                            <small class="text-muted">Formato: agência-conta-dígito</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">URL do Webhook</label>
                                            <input type="url" name="super_inter_webhook_url" class="form-control"
                                                placeholder="https://seudominio.com/cobranca/api/webhook_pix_plano.php"
                                                value="<?= htmlspecialchars($config['super_inter_webhook_url'] ?? '') ?>">
                                            <small class="text-muted">URL para confirmar pagamentos de planos</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Certificado (.crt)</label>
                                            <input type="file" name="super_inter_cert_crt" class="form-control" accept=".crt,.pem,.cer">
                                            <?php $certCrt = $config['super_inter_cert_crt'] ?? ''; ?>
                                            <?php if ($certCrt && file_exists($certCrt)): ?>
                                                <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certCrt) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Arquivo do certificado público</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Chave Privada (.key)</label>
                                            <input type="file" name="super_inter_cert_key" class="form-control" accept=".key,.pem">
                                            <?php $certKey = $config['super_inter_cert_key'] ?? ''; ?>
                                            <?php if ($certKey && file_exists($certKey)): ?>
                                                <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certKey) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Arquivo da chave privada</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="mt-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Configurações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-card">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Como Configurar</h6>
                            </div>
                            <div class="p-3">
                                <ol class="small text-muted mb-0" style="padding-left: 18px;">
                                    <li class="mb-2">Acesse <a href="https://developers.inter.co/" target="_blank">developers.inter.co</a></li>
                                    <li class="mb-2">Crie uma aplicação no portal de desenvolvedores</li>
                                    <li class="mb-2">Copie o <strong>Client ID</strong> e <strong>Client Secret</strong></li>
                                    <li class="mb-2">Configure os escopos de <strong>Cobrança</strong> e <strong>Pagamentos</strong></li>
                                    <li class="mb-2">Envie os certificados <strong>.crt</strong> e <strong>.key</strong></li>
                                    <li class="mb-2">Cadastre o <strong>Webhook</strong> com a URL acima e o evento de pagamento</li>
                                    <li class="mb-2">Salve</li>
                                </ol>
                            </div>
                        </div>

                        <div class="form-card mt-3">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0"><i class="fas fa-server me-2"></i>Status</h6>
                            </div>
                            <div class="p-3">
                                <?php if ($temCredenciais): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                                        <span>Credenciais configuradas</span>
                                    </div>
                                    <small class="text-muted d-block">Client ID: <?= htmlspecialchars(substr($config['super_inter_client_id'], 0, 10)) ?>...</small>
                                    <small class="text-muted d-block">Ambiente: <?= strpos($interBaseUrl, 'sandbox') !== false ? 'Sandbox (UAT)' : 'Produção' ?></small>
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

            <!-- ==================== MERCADO PAGO ==================== -->
            <div class="tab-pane fade show <?= $planoApiAtiva === 'mercadopago' ? 'active' : '' ?>" id="abaMp" role="tabpanel">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3 p-3 rounded-3 border <?= $planoApiAtiva === 'mercadopago' ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle' ?>">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($planoApiAtiva === 'mercadopago'): ?>
                            <span class="badge badge-auto bg-success"><i class="fas fa-check me-1"></i> API Ativa</span>
                            <small class="text-success">Responsável pelo recebimento de PIX e cartão na contratação de planos.</small>
                        <?php else: ?>
                            <span class="badge badge-auto bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> API Inativa</span>
                            <small class="text-muted">Ao ativar, esta API passa a receber o PIX e o cartão dos planos contratados.</small>
                        <?php endif; ?>
                    </div>
                    <?php if ($planoApiAtiva !== 'mercadopago'): ?>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="acao" value="ativar_plano_api">
                            <input type="hidden" name="api" value="mercadopago">
                            <button type="submit" class="btn btn-success btn-sm fw-bold"><i class="fas fa-power-off me-1"></i> ATIVAR API</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0"><i class="fab fa-pix me-2"></i>Credenciais do Mercado Pago (PIX de Planos)</h6>
                            </div>
                            <div class="p-3">
                                <div class="alert alert-info py-2 mb-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Estas credenciais são <strong>exclusivas do Super Admin</strong> e responsáveis pelo recebimento via PIX quando os admins contratam seus planos. São independentes das credenciais do painel admin.
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="acao" value="salvar_mp">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Access Token</label>
                                            <input type="text" name="super_mp_access_token" class="form-control font-monospace"
                                                placeholder="APP_USR-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                                value="<?= htmlspecialchars($mpConfigSuper['super_mp_access_token'] ?? '') ?>">
                                            <small class="text-muted">Token de produção (APP_USR-...) da conta que recebe o PIX dos planos</small>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Public Key</label>
                                            <input type="text" name="super_mp_public_key" class="form-control font-monospace"
                                                placeholder="APP_USR-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                                value="<?= htmlspecialchars($mpConfigSuper['super_mp_public_key'] ?? '') ?>">
                                            <small class="text-muted">Chave pública para o frontend</small>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">URL do Webhook</label>
                                            <input type="url" name="super_mp_webhook_url" class="form-control"
                                                placeholder="https://seudominio.com/cobranca/api/webhook_mp_plano.php"
                                                value="<?= htmlspecialchars($mpConfigSuper['super_mp_webhook_url'] ?? '') ?>">
                                            <small class="text-muted">URL para confirmar pagamentos de planos via Mercado Pago</small>
                                        </div>
                                    </div>
                                    <div class="mt-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Configurações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-card">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Como Configurar</h6>
                            </div>
                            <div class="p-3">
                                <ol class="small text-muted mb-0" style="padding-left: 18px;">
                                    <li class="mb-2">Acesse sua conta no <a href="https://www.mercadopago.com.br/developers" target="_blank">Mercado Pago Developers</a></li>
                                    <li class="mb-2">Vá em <strong>Credenciais</strong> no menu de desenvolvedores</li>
                                    <li class="mb-2">Copie o <strong>Access Token</strong> (produção)</li>
                                    <li class="mb-2">Copie a <strong>Public Key</strong></li>
                                    <li class="mb-2">Cadastre o <strong>Webhook</strong> com a URL acima e o evento de pagamento</li>
                                    <li class="mb-2">Salve e clique em <strong>Ativar Mercado Pago</strong></li>
                                </ol>
                            </div>
                        </div>

                        <div class="form-card mt-3">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0"><i class="fas fa-server me-2"></i>Status</h6>
                            </div>
                            <div class="p-3">
                                <?php if ($temCredenciaisMp): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                                        <span>Credenciais configuradas</span>
                                    </div>
                                    <small class="text-muted d-block">Access Token: <?= htmlspecialchars(substr($mpConfigSuper['super_mp_access_token'], 0, 15)) ?>...</small>
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
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>