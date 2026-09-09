<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedInUser() && !empty($_GET['token'])) {
    autoLoginByToken($_GET['token']);
}
requireUser();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$faturaId = intval($_GET['id'] ?? 0);
$erroMsg = '';

if ($faturaId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND cliente_id = ?");
$stmt->execute([$faturaId, $userId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    header('Location: dashboard.php');
    exit;
}

// Contexto de tenant para resolver as configurações do admin dono da fatura.
if (!empty($fatura['admin_id'])) {
    $_SESSION['tenant_admin_id'] = (int) $fatura['admin_id'];
}

$apiDaFatura = $fatura['api_pagamento'] ?: getApiAtiva();
$mpConfig = getMPConfig();
$cartaoPermitido = ($apiDaFatura === 'mercadopago')
    && aceitaCartaoCredito()
    && !empty($mpConfig['mp_public_key'])
    && $fatura['status'] !== 'pago';

if (isset($_GET['gerar_boleto']) && $fatura['status'] !== 'pago') {
    if (!empty($fatura['boleto_url'])) {
        header('Location: ' . $fatura['boleto_url']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$userId]);
    $cli = $stmt->fetch();

    $result = criarBoleto(
        $fatura['descricao'], $fatura['valor_final'], $cli['nome_razao'] ?? '',
        $cli['cpf_cnpj'] ?? '', $cli['email'] ?? '', $cli['cep'] ?? '',
        $cli['logradouro'] ?? '', $cli['numero'] ?? '', $cli['bairro'] ?? '',
        $cli['cidade'] ?? '', $cli['estado'] ?? ''
    );

    if (isset($result['sucesso']) && $result['sucesso']) {
        if ($apiDaFatura === 'inter' || $apiDaFatura === 'bb') {
            $stmt = $pdo->prepare("UPDATE faturas SET boleto_url = ?, mp_payment_id = ?, inter_codigo_solicitacao = ? WHERE id = ?");
            $stmt->execute([$result['boleto_url'], null, $result['payment_id'], $faturaId]);
        } else {
            $stmt = $pdo->prepare("UPDATE faturas SET boleto_url = ?, mp_payment_id = ? WHERE id = ?");
            $stmt->execute([$result['boleto_url'], $result['payment_id'], $faturaId]);
        }
        header('Location: ' . $result['boleto_url']);
        exit;
    } else {
        $erroMsg = $result['erro'] ?? 'Erro ao gerar boleto.';
        header('Location: fatura.php?id=' . $faturaId . '&erro=boleto&msg=' . urlencode($erroMsg));
        exit;
    }
}

$jaTemCobranca = !empty($fatura['inter_codigo_solicitacao']) || !empty($fatura['mp_payment_id']);
if (!$jaTemCobranca && !$fatura['link_pagamento'] && !$fatura['pix_copia_cola'] && $fatura['status'] !== 'pago') {
    $stmt = $pdo->prepare("SELECT email, nome_razao FROM clientes WHERE id = ?");
    $stmt->execute([$userId]);
    $cli = $stmt->fetch();

    $result = criarPagamento($fatura['descricao'], $fatura['valor_final'], $cli['email'] ?? '', $cli['nome_razao'] ?? '');

    if (isset($result['sucesso']) && $result['sucesso']) {
        if ($apiDaFatura === 'inter' || $apiDaFatura === 'bb') {
            $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ?, api_pagamento = ? WHERE id = ?");
            $stmt->execute([$result['qr_code'], $result['qr_code_copia_cola'], $result['link_pagamento'], null, $result['payment_id'], $apiDaFatura, $faturaId]);
        } else {
            $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, api_pagamento = ? WHERE id = ?");
            $stmt->execute([$result['qr_code'], $result['qr_code_copia_cola'], $result['link_pagamento'], $result['payment_id'], $apiDaFatura, $faturaId]);
        }
        $fatura['pix_qrcode'] = $result['qr_code'];
        $fatura['pix_copia_cola'] = $result['qr_code_copia_cola'];
        $fatura['link_pagamento'] = $result['link_pagamento'];
    } else {
        $erroMsg = $result['erro'] ?? 'Erro ao gerar pagamento.';
    }
}

$logo = getConfig('logo_mobile', '') ?: getLogo();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#6C5CE7">
    <title>Fatura <?= htmlspecialchars($fatura['numero']) ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="icon.php?size=192">
    <link rel="apple-touch-icon" href="icon.php?size=192">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <div class="app-topbar">
            <a href="dashboard.php" style="text-decoration:none; color:var(--app-text); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-arrow-left"></i>
                <span style="font-size:0.9rem; font-weight:600;">Voltar</span>
            </a>
            <a href="perfil.php">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="app-topbar-avatar">
            </a>
        </div>

        <div class="app-content">
            <?php if ($erroMsg && empty($_GET['erro'])): ?>
                <div class="app-alert app-alert-danger app-animate">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($erroMsg) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'boleto'): ?>
                <?php $msgBoleto = trim((string) ($_GET['msg'] ?? '')); ?>
                <div class="app-alert app-alert-danger app-animate" style="display:flex; align-items:flex-start; gap:8px; flex-wrap:wrap;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span style="flex:1;">
                        <?= htmlspecialchars($msgBoleto !== '' ? $msgBoleto : 'Erro ao gerar boleto. Verifique seus dados cadastrais.') ?>
                        <?php if (stripos($msgBoleto, 'perfil') !== false): ?>
                            <a href="perfil.php" style="color:inherit; text-decoration:underline; font-weight:600;">Completar meu cadastro</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="app-fatura-detail app-animate">
                <div class="app-fatura-detail-header">
                    <div class="app-fatura-numero" style="font-size:0.82rem; margin-bottom:4px;"><?= htmlspecialchars($fatura['numero']) ?></div>
                    <h5 style="font-weight:700; margin-bottom:8px;"><?= htmlspecialchars($fatura['descricao']) ?></h5>
                    <?php
                    $badgeClass = [
                        'pendente' => 'app-badge-pendente', 'pago' => 'app-badge-pago',
                        'vencido' => 'app-badge-vencido', 'atrasado' => 'app-badge-atrasado',
                        'cancelado' => 'app-badge-cancelado'
                    ];
                    ?>
                    <span class="app-badge <?= $badgeClass[$fatura['status']] ?? 'app-badge-pendente' ?>"><?= ucfirst($fatura['status']) ?></span>
                </div>

                <div class="app-detail-row">
                    <span class="app-detail-label">Valor</span>
                    <span class="app-detail-valor">R$ <?= number_format($fatura['valor_final'], 2, ',', '.') ?></span>
                </div>
                <div class="app-detail-row">
                    <span class="app-detail-label">Vencimento</span>
                    <span class="app-detail-value">
                        <?= date('d/m/Y', strtotime($fatura['data_vencimento'])) ?>
                        <?php
                        $dias = (strtotime($fatura['data_vencimento']) - strtotime(date('Y-m-d'))) / 86400;
                        if ($fatura['status'] !== 'pago' && $dias < 0): ?>
                            <br><small class="text-danger"><?= abs(intval($dias)) ?> dia(s) atrasado</small>
                        <?php elseif ($fatura['status'] !== 'pago' && $dias <= 3 && $dias >= 0): ?>
                            <br><small class="text-warning">Vence em <?= intval($dias) ?> dia(s)</small>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="app-detail-row">
                    <span class="app-detail-label">Emissão</span>
                    <span class="app-detail-value"><?= date('d/m/Y', strtotime($fatura['data_emissao'])) ?></span>
                </div>
                <?php if ($fatura['data_pagamento']): ?>
                <div class="app-detail-row">
                    <span class="app-detail-label">Pagamento</span>
                    <span class="app-detail-value text-success"><?= date('d/m/Y', strtotime($fatura['data_pagamento'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div id="pagamentoBox">
                <?php if ($fatura['status'] === 'pago'): ?>
                    <div class="app-pagamento-box app-pago-box app-animate">
                        <div class="app-pago-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h5 style="color:var(--app-success); font-weight:700;">Pagamento Confirmado</h5>
                        <p style="color:var(--app-text-muted); font-size:0.88rem;">Esta fatura já foi quitada.</p>
                        <?php
                        $nubankWa = getConfig('nubank_whatsapp', '');
                        $pmWa = getConfig('pix_manual_whatsapp', '');
                        $whatsappNum = $nubankWa ?: $pmWa;
                        if (!empty($whatsappNum)):
                            $msgWa = 'Oi, segue comprovante da fatura (' . $fatura['numero'] . ').';
                            $linkWa = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsappNum) . '?text=' . urlencode($msgWa);
                        ?>
                            <a href="<?= htmlspecialchars($linkWa) ?>" target="_blank" class="app-btn app-btn-success" style="margin-top:8px;">
                                <i class="fab fa-whatsapp"></i> Enviar Comprovante via WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="app-pagamento-box app-animate">
                        <h6><i class="fas fa-qrcode"></i> Pagamento</h6>

                        <?php if ($apiDaFatura === 'pix_manual'): ?>
                            <?php $pmCfg = getConfigPixManual(); ?>
                            <?php if (!empty($pmCfg['pix_manual_chave'])): ?>
                                <div class="app-pix-section">
                                    <div class="app-qr-wrap">
                                        <div id="pmQrContainer" style="display:inline-block;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($pmCfg['pix_manual_chave']) || !empty($pmCfg['pix_manual_favorecido'])): ?>
                                <div class="text-start mt-2">
                                    <div class="app-pix-label text-start">Dados do Recebedor</div>
                                    <?php if (!empty($pmCfg['pix_manual_chave'])): ?>
                                        <div style="font-size:0.88rem; margin-bottom:6px;">
                                            <span style="color:var(--app-text-muted);">Chave PIX:</span> <strong><?= htmlspecialchars($pmCfg['pix_manual_chave']) ?></strong>
                                            <button type="button" class="app-btn app-btn-outline" style="display:inline-block; padding:2px 8px; font-size:0.7rem; margin-left:6px; vertical-align:middle;" onclick="copiarPix('<?= htmlspecialchars($pmCfg['pix_manual_chave']) ?>')"><i class="fas fa-copy"></i></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pmCfg['pix_manual_banco'])): ?>
                                        <div style="font-size:0.88rem; margin-bottom:6px;">
                                            <span style="color:var(--app-text-muted);">Banco:</span> <strong><?= htmlspecialchars($pmCfg['pix_manual_banco']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pmCfg['pix_manual_favorecido'])): ?>
                                        <div style="font-size:0.88rem; margin-bottom:6px;">
                                            <span style="color:var(--app-text-muted);">Favorecido:</span> <strong><?= htmlspecialchars($pmCfg['pix_manual_favorecido']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($pmCfg['pix_manual_whatsapp'])): ?>
                                <?php $pmWaLink = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $pmCfg['pix_manual_whatsapp']) . '?text=' . urlencode('Oi, tenho uma dúvida sobre pagamento.'); ?>
                                <a href="<?= htmlspecialchars($pmWaLink) ?>" target="_blank" class="app-btn app-btn-success" style="margin-top:8px;">
                                    <i class="fab fa-whatsapp"></i> Enviar Comprovante
                                </a>
                            <?php endif; ?>

                        <?php else: ?>

                            <?php if ($fatura['pix_copia_cola']): ?>
                                <?php if ($fatura['pix_qrcode']): ?>
                                    <div class="app-pix-section">
                                        <div class="app-qr-wrap">
                                            <img src="data:image/png;base64,<?= htmlspecialchars($fatura['pix_qrcode']) ?>" alt="QR Code PIX">
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="app-pix-section">
                                        <div class="app-qr-wrap">
                                            <div id="qrCodeContainer"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="app-pix-label">Código PIX Copia e Cola</div>
                                <div class="app-pix-code" id="pixCode"><?= htmlspecialchars($fatura['pix_copia_cola']) ?></div>
                                <button class="app-btn app-btn-success mb-2" onclick="copiarPix()" style="margin-bottom:8px;">
                                    <i class="fas fa-copy"></i> Copiar Código PIX
                                </button>

                                <?php if ($fatura['link_pagamento']): ?>
                                    <a href="<?= htmlspecialchars($fatura['link_pagamento']) ?>" target="_blank" class="app-btn app-btn-primary" style="margin-bottom:8px;">
                                        <i class="fas fa-external-link-alt"></i> Pagar Agora
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="color:var(--app-text-muted); font-size:0.88rem;">Aguardando dados de pagamento...</p>
                            <?php endif; ?>

                            <div class="app-divider">ou</div>

                            <?php if (!empty($fatura['boleto_url'])): ?>
                                <a href="<?= htmlspecialchars($fatura['boleto_url']) ?>" target="_blank" class="app-btn app-btn-outline">
                                    <i class="fas fa-file-invoice"></i> Ver Boleto
                                </a>
                            <?php elseif ($fatura['status'] !== 'pago'): ?>
                                <a href="?id=<?= $faturaId ?>&gerar_boleto=1" class="app-btn app-btn-outline" onclick="document.getElementById('modalAppConfirm').style.display='flex'; return false;">
                                    <i class="fas fa-barcode"></i> Gerar Boleto
                                </a>
                            <?php endif; ?>

                        <?php endif; ?>

                        <?php if ($cartaoPermitido): ?>
                            <div class="app-divider">ou pague com Cartão</div>
                            <div style="display:flex; gap:8px; margin-bottom:12px;">
                                <button type="button" id="tabCredito" data-tipo="credito" style="flex:1; padding:11px 8px; border:1px solid transparent; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; border-radius:10px; font-size:0.82rem; font-weight:700; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; box-shadow:0 4px 12px rgba(79,70,229,.25);">
                                    <i class="fas fa-credit-card"></i> Crédito
                                </button>
                                <button type="button" id="tabDebito" data-tipo="debito" style="flex:1; padding:11px 8px; border:1px solid #e2e8f0; background:var(--app-card); color:var(--app-text-muted); border-radius:10px; font-size:0.82rem; font-weight:600; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer;">
                                    <i class="fas fa-money-check-alt"></i> Débito
                                </button>
                            </div>
                            <form id="ccForm" autocomplete="off">
                                <div style="margin-bottom:8px;">
                                    <div style="display:flex; align-items:center; justify-content:space-between;">
                                        <label style="font-size:0.7rem; font-weight:600; color:var(--app-text-muted);">Número do cartão</label>
                                        <span id="ccBrand" style="font-size:0.6rem; font-weight:800; color:#fff; background:#94a3b8; border-radius:5px; padding:2px 7px; text-transform:uppercase; opacity:0; transition:opacity .15s ease;"></span>
                                    </div>
                                    <input type="text" id="ccNumero" inputmode="numeric" maxlength="19" placeholder="0000 0000 0000 0000" autocomplete="cc-number" style="width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:0.9rem; background:var(--app-card); color:var(--app-text); box-sizing:border-box;" required>
                                </div>
                                <div style="margin-bottom:8px;">
                                    <label style="font-size:0.7rem; font-weight:600; color:var(--app-text-muted);">Nome impresso no cartão</label>
                                    <input type="text" id="ccNome" maxlength="40" placeholder="NOME COMO ESTÁ NO CARTÃO" autocomplete="cc-name" style="width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:0.9rem; background:var(--app-card); color:var(--app-text); box-sizing:border-box;" required>
                                </div>
                                <div style="display:flex; gap:8px; margin-bottom:8px;">
                                    <div style="flex:1;">
                                        <label style="font-size:0.7rem; font-weight:600; color:var(--app-text-muted);">Validade</label>
                                        <input type="text" id="ccValidade" inputmode="numeric" maxlength="5" placeholder="MM/AA" autocomplete="cc-exp" style="width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:0.9rem; background:var(--app-card); color:var(--app-text); box-sizing:border-box;" required>
                                    </div>
                                    <div style="flex:1;">
                                        <label style="font-size:0.7rem; font-weight:600; color:var(--app-text-muted);">CVV</label>
                                        <input type="text" id="ccCvv" inputmode="numeric" maxlength="4" placeholder="123" autocomplete="cc-csc" style="width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:0.9rem; background:var(--app-card); color:var(--app-text); box-sizing:border-box;" required>
                                    </div>
                                    <div style="flex:1;" id="ccParcelasWrap">
                                        <label style="font-size:0.7rem; font-weight:600; color:var(--app-text-muted);">Parcelas</label>
                                        <select id="ccParcelas" style="width:100%; padding:10px 8px; border:1px solid #e2e8f0; border-radius:10px; font-size:0.9rem; background:var(--app-card); color:var(--app-text); box-sizing:border-box;"></select>
                                    </div>
                                </div>
                                <div id="ccMsg" style="font-size:0.82rem; margin-bottom:8px;"></div>
                                <button type="submit" id="ccBtn" class="app-btn app-btn-primary">
                                    <i class="fas fa-credit-card"></i> Pagar
                                </button>
                                <p style="margin:10px 0 0; font-size:0.66rem; color:var(--app-text-muted); text-align:center;"><i class="fas fa-lock"></i> Pagamento processado com segurança pelo Mercado Pago</p>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="text-align:center; padding:16px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a><span style="float:right;">Versão: 1.0</span>
    </div>

    <nav class="app-bottom-nav">
        <a href="dashboard.php" class="app-nav-item">
            <i class="fas fa-home"></i>
            <span>Faturas</span>
        </a>
        <a href="financeiro.php" class="app-nav-item">
            <i class="fas fa-headset"></i>
            <span>Financeiro</span>
        </a>
        <a href="perfil.php" class="app-nav-item">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
        <a href="logout.php" class="app-nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </nav>

    <script>
    function copiarPix(code) {
        if (!code) {
            var el = document.getElementById('pixCode');
            code = el ? el.textContent.trim() : '';
        }
        if (!code) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function() {
                showToastPix('Código PIX copiado!');
            }).catch(function() {
                fallbackCopy(code);
            });
        } else {
            fallbackCopy(code);
        }
    }
    function fallbackCopy(code) {
        var ta = document.createElement('textarea');
        ta.value = code;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showToastPix('Código PIX copiado!'); } catch(e) {}
        document.body.removeChild(ta);
    }
    function showToastPix(msg) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#27ae60;color:#fff;padding:10px 20px;border-radius:8px;font-size:0.85rem;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 2000);
    }
    </script>

    <?php if ($apiDaFatura !== 'pix_manual' && !$fatura['pix_qrcode'] && $fatura['pix_copia_cola']): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var c = document.getElementById('qrCodeContainer');
        if (c) {
            new QRCode(c, {
                text: document.getElementById('pixCode').textContent.trim(),
                width: 170, height: 170,
                colorDark: "#000000", colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
    </script>
    <?php endif; ?>
    <?php if ($apiDaFatura === 'pix_manual'): ?>
        <?php $pmQrChave = getConfig('pix_manual_chave', ''); ?>
        <?php if (!empty($pmQrChave)): ?>
            <?php if (!$fatura['pix_qrcode']): ?>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <?php endif; ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var pmContainer = document.getElementById('pmQrContainer');
                if (pmContainer) {
                    new QRCode(pmContainer, {
                        text: <?= json_encode($pmQrChave) ?>,
                        width: 170, height: 170,
                        colorDark: "#000000", colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            });
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($fatura['status'] !== 'pago'): ?>
    <script>
    var _faturaId = <?= $faturaId ?>;
    var _pollCount = 0;
    function verificarPagamento() {
        if (_pollCount >= 120) return;
        _pollCount++;
        fetch('/cobranca/api/verificar_status.php?id=' + _faturaId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'pago') {
                    document.getElementById('appPagamentoSucesso').style.display = 'flex';
                    var box = document.getElementById('pagamentoBox');
                    if (box) {
                        box.innerHTML = '<div class="app-pagamento-box app-pago-box app-animate"><div class="app-pago-icon"><i class="fas fa-check"></i></div><h5 style="color:var(--app-success); font-weight:700;">Pagamento Confirmado</h5><p style="color:var(--app-text-muted); font-size:0.88rem;">Esta fatura já foi quitada.</p></div>';
                    }
                } else {
                    setTimeout(verificarPagamento, 5000);
                }
            })
            .catch(function() {
                setTimeout(verificarPagamento, 5000);
            });
    }
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(verificarPagamento, 3000);
    });
    </script>
    <?php
    $apiAtiva = $apiDaFatura;
    $temInterCodigo = !empty($fatura['inter_codigo_solicitacao']);
    $semPix = empty($fatura['pix_copia_cola']);
    $naoPago = $fatura['status'] !== 'pago';
    if ($apiAtiva === 'inter' && $temInterCodigo && $semPix && $naoPago):
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    var _interPollCount = 0;
    function verificarInterPix() {
        if (_interPollCount >= 60) return;
        _interPollCount++;
        fetch('/cobranca/api/inter_status.php?id=' + _faturaId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'pago') {
                    document.getElementById('appPagamentoSucesso').style.display = 'flex';
                    var box = document.getElementById('pagamentoBox');
                    if (box) {
                        box.innerHTML = '<div class="app-pagamento-box app-pago-box app-animate"><div class="app-pago-icon"><i class="fas fa-check"></i></div><h5 style="color:var(--app-success); font-weight:700;">Pagamento Confirmado</h5><p style="color:var(--app-text-muted); font-size:0.88rem;">Esta fatura já foi quitada.</p></div>';
                    }
                } else if (d.status === 'pronto' && d.pix) {
                    location.reload();
                } else if (d.status === 'aguardando' || d.status === 'pendente') {
                    setTimeout(verificarInterPix, 3000);
                }
            })
            .catch(function() {
                setTimeout(verificarInterPix, 5000);
            });
    }
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(verificarInterPix, 2000);
    });
    </script>
    <?php endif; endif; ?>

    <div id="modalAppConfirm" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:24px; text-align:center; max-width:300px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
            <h6 style="font-weight:700; margin-bottom:8px;">Gerar Boleto</h6>
            <p style="color:#6c757d; font-size:0.85rem; margin-bottom:16px;">Deseja gerar o boleto para pagamento?</p>
            <div style="display:flex; gap:8px;">
                <button onclick="document.getElementById('modalAppConfirm').style.display='none'" style="flex:1; padding:10px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; font-size:0.85rem; cursor:pointer;">Cancelar</button>
                <a href="?id=<?= $faturaId ?>&gerar_boleto=1" style="flex:1; padding:10px; border:none; border-radius:10px; background:#6C5CE7; color:#fff; font-size:0.85rem; text-decoration:none; text-align:center;">Confirmar</a>
            </div>
        </div>
    </div>

    <div id="appPagamentoSucesso" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:32px 24px; text-align:center; max-width:300px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
            <div style="width:64px; height:64px; border-radius:50%; background:#d4edda; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <i class="fas fa-check" style="font-size:1.8rem; color:#28a745;"></i>
            </div>
            <h5 style="font-weight:700; color:#28a745; margin-bottom:8px;">Pagamento Confirmado!</h5>
            <p style="color:#6c757d; font-size:0.9rem; margin-bottom:16px;">Seu pagamento foi realizado com sucesso.</p>
            <a href="dashboard.php" class="app-btn app-btn-success" style="width:100%; text-align:center; text-decoration:none;">Voltar ao Painel</a>
</div>

    <?php if ($cartaoPermitido): ?>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script>
    (function() {
        var faturaId = <?= (int) $faturaId ?>;
        var faturaValor = <?= (float) $fatura['valor_final'] ?>;
        var maxParcelas = <?= (int) getMaxParcelasCartao() ?>;
        var cpfCliente = <?= json_encode(preg_replace('/[^0-9]/', '', $_SESSION['user_cpf_cnpj'] ?? '')) ?>;

        var mp = new MercadoPago(<?= json_encode($mpConfig['mp_public_key']) ?>, { locale: 'pt-BR' });

        var btn = document.getElementById('ccBtn');
        var msg = document.getElementById('ccMsg');
        var selParcelas = document.getElementById('ccParcelas');
        var wrapParcelas = document.getElementById('ccParcelasWrap');
        var inputNumero = document.getElementById('ccNumero');
        var inputNome = document.getElementById('ccNome');
        var inputValidade = document.getElementById('ccValidade');
        var inputCvv = document.getElementById('ccCvv');
        var brandEl = document.getElementById('ccBrand');
        var tabCredito = document.getElementById('tabCredito');
        var tabDebito = document.getElementById('tabDebito');
        var tipoAtual = 'credito';

        var corTabAtiva = 'linear-gradient(135deg,#4f46e5,#7c3aed)';
        var corTabInativa = 'var(--app-card)';
        var coresBandeira = {
            visa: '#1a1f71', master: '#eb001b', amex: '#2e77bc', elo: '#00a4e0',
            hipercard: '#b3131b', hiper: '#7a0c11', aura: '#d8670d', diners: '#0868ac',
            discover: '#ef6a00', outros: '#64748b'
        };

        function txtMoeda(v) {
            return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        for (var i = 1; i <= maxParcelas; i++) {
            var opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i === 1 ? 'À vista (1x)' : i + 'x';
            selParcelas.appendChild(opt);
        }

        function mostrarMsg(texto, tipo) {
            msg.style.color = tipo === 'success' ? '#27ae60' : (tipo === 'warning' ? '#e67e22' : '#d63031');
            msg.innerHTML = texto || '';
        }

        function detectarBandeira(num) {
            num = num.replace(/\s+/g, '');
            if (!/^\d{6,}$/.test(num)) return null;
            var p = parseInt(num.slice(0, 6), 10);
            if (/^4/.test(num)) return { b: 'visa', label: 'Visa' };
            if (/^3[47]/.test(num)) return { b: 'amex', label: 'Amex' };
            if (/(^5[1-5]|^2[2-7])/.test(num)) return { b: 'master', label: 'Mastercard' };
            if (/^(4011|4312|4389|4514|4573|4576|5041|5066|5090|6277|6362|6363|6504|6505|6507|6509|6516|6550)/.test(num)) return { b: 'elo', label: 'Elo' };
            if (/^(6062|3841)/.test(num)) return { b: 'hipercard', label: 'Hipercard' };
            if (/^(637095|637599|637609|637612)/.test(num)) return { b: 'hiper', label: 'Hiper' };
            if (/^50/.test(num)) return { b: 'aura', label: 'Aura' };
            if (/^(30[0-5]|36|38|39)/.test(num)) return { b: 'diners', label: 'Diners' };
            if (/^(6011|644|645|646|647|648|649|65)/.test(num) || (p >= 622126 && p <= 622925)) return { b: 'discover', label: 'Discover' };
            return { b: 'outros', label: 'Cartão' };
        }

        function formatarNumero(val) {
            var d = val.replace(/\D+/g, '');
            var b = detectarBandeira(d);
            if (b && b.b === 'amex') return d.slice(0, 15).replace(/(\d{4})(\d{6})(\d+)/, '$1 $2 $3');
            return d.slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
        }

        function atualizarBandeira() {
            var b = detectarBandeira(inputNumero.value);
            if (b) {
                brandEl.textContent = b.label;
                brandEl.style.background = b.b === 'outros' ? '#64748b' : (coresBandeira[b.b] || '#64748b');
                brandEl.style.opacity = '1';
            } else {
                brandEl.textContent = '';
                brandEl.style.opacity = '0';
            }
        }

        inputNumero.addEventListener('input', function() {
            inputNumero.value = formatarNumero(inputNumero.value);
            atualizarBandeira();
        });

        function parcelasAtuais() {
            if (tipoAtual === 'debito') return 1;
            return parseInt(selParcelas.value, 10) || 1;
        }

        function atualizarBtn() {
            var p = parcelasAtuais();
            var icone = tipoAtual === 'debito'
                ? '<i class="fas fa-money-check-alt"></i>'
                : '<i class="fas fa-credit-card"></i>';
            if (tipoAtual === 'debito') {
                btn.innerHTML = icone + ' Pagar no Débito ' + txtMoeda(faturaValor);
            } else if (p <= 1) {
                btn.innerHTML = icone + ' Pagar ' + txtMoeda(faturaValor) + ' à vista';
            } else {
                btn.innerHTML = icone + ' Pagar em ' + p + 'x de ' + txtMoeda(faturaValor / p);
            }
        }

        function setarTipo(tipo) {
            tipoAtual = tipo === 'debito' ? 'debito' : 'credito';
            var ativa = tipoAtual === 'debito' ? tabDebito : tabCredito;
            var inativa = tipoAtual === 'debito' ? tabCredito : tabDebito;
            ativa.style.background = corTabAtiva;
            ativa.style.color = '#fff';
            ativa.style.border = '1px solid transparent';
            ativa.style.fontWeight = '700';
            ativa.style.boxShadow = '0 4px 12px rgba(79,70,229,.25)';
            inativa.style.background = corTabInativa;
            inativa.style.color = 'var(--app-text-muted)';
            inativa.style.border = '1px solid #e2e8f0';
            inativa.style.fontWeight = '600';
            inativa.style.boxShadow = 'none';
            if (wrapParcelas) {
                wrapParcelas.style.opacity = tipoAtual === 'debito' ? '0.35' : '1';
                wrapParcelas.style.pointerEvents = tipoAtual === 'debito' ? 'none' : 'auto';
            }
            atualizarBtn();
        }

        tabCredito.addEventListener('click', function() { setarTipo('credito'); });
        tabDebito.addEventListener('click', function() { setarTipo('debito'); });
        selParcelas.addEventListener('change', atualizarBtn);

        document.getElementById('ccForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var numero = inputNumero.value.replace(/\s+/g, '');
            var nome = inputNome.value.trim();
            var validade = inputValidade.value.trim();
            var cvv = inputCvv.value.trim();
            var parcelas = parcelasAtuais();

            if (!/^\d{13,16}$/.test(numero)) { mostrarMsg('Número de cartão inválido.'); return; }
            var m = validade.match(/^(\d{2})\s*\/\s*(\d{2})$/);
            if (!m) { mostrarMsg('Validade inválida. Use o formato MM/AA.'); return; }
            var mes = parseInt(m[1], 10), ano = 2000 + parseInt(m[2], 10);
            if (mes < 1 || mes > 12) { mostrarMsg('Mês da validade inválido.'); return; }
            if (cvv.length < 3) { mostrarMsg('CVV inválido.'); return; }
            if (!nome) { mostrarMsg('Informe o nome impresso no cartão.'); return; }

            var hoje = new Date();
            if (ano < hoje.getFullYear() || (ano === hoje.getFullYear() && mes < hoje.getMonth() + 1)) {
                mostrarMsg('Este cartão está vencido.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            mostrarMsg('', '');

            var payload = {
                cardNumber: numero,
                cardholderName: nome,
                cardExpirationMonth: (mes < 10 ? '0' : '') + mes,
                cardExpirationYear: String(ano),
                securityCode: cvv,
                installments: parcelas,
                identificationType: 'CPF',
                identificationNumber: cpfCliente,
                locale: 'pt-BR'
            };

            mp.createCardToken(payload, function(resp, err) {
                if (err && err.length) {
                    btn.disabled = false;
                    atualizarBtn();
                    var mensagens = [];
                    for (var i = 0; i < err.length; i++) {
                        if (err[i] && err[i].message) mensagens.push(err[i].message);
                    }
                    mostrarMsg(mensagens.join(' | ') || 'Não foi possível validar o cartão.');
                    return;
                }

                btn.innerHTML = '<i class="fas fa-credit-card"></i> Aguardando confirmação...';

                var method = (resp.payment_method && resp.payment_method.id) ? resp.payment_method.id : '';
                var body = 'fatura_id=' + encodeURIComponent(faturaId) +
                    '&card_token=' + encodeURIComponent(resp.id) +
                    '&installments=' + encodeURIComponent(parcelas) +
                    '&tipo=' + encodeURIComponent(tipoAtual) +
                    '&method=' + encodeURIComponent(method);

                fetch('/cobranca/api/cartao_pagamento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.sucesso) {
                        if (d.status === 'pago') {
                            mostrarMsg('<i class="fas fa-check-circle"></i> Pagamento aprovado!', 'success');
                            var modal = document.getElementById('appPagamentoSucesso');
                            if (modal) modal.style.display = 'flex';
                            var box = document.getElementById('pagamentoBox');
                            if (box) {
                                box.innerHTML = '<div class="app-pagamento-box app-pago-box app-animate"><div class="app-pago-icon"><i class="fas fa-check"></i></div><h5 style="color:var(--app-success); font-weight:700;">Pagamento Confirmado</h5><p style="color:var(--app-text-muted); font-size:0.88rem;">Esta fatura já foi quitada.</p></div>';
                            }
                        } else {
                            btn.disabled = false;
                            atualizarBtn();
                            mostrarMsg('<i class="fas fa-clock"></i> Pagamento em análise. Acompanhe a confirmação aqui.', 'warning');
                        }
                    } else {
                        btn.disabled = false;
                        atualizarBtn();
                        mostrarMsg(d.erro || 'Não foi possível processar o pagamento.');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    atualizarBtn();
                    mostrarMsg('Erro de conexão. Tente novamente.');
                });
            });
        });

        setarTipo('credito');
    })();
    </script>
    <?php endif; ?>

    <script src="pwa.js"></script>
</body>
</html>