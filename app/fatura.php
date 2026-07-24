<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
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
        $apiAtiva = getApiAtiva();
        if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
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
        header('Location: fatura.php?id=' . $faturaId . '&erro=boleto');
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
        $apiAtiva = getApiAtiva();
        if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
            $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ? WHERE id = ?");
            $stmt->execute([$result['qr_code'], $result['qr_code_copia_cola'], $result['link_pagamento'], null, $result['payment_id'], $faturaId]);
        } else {
            $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ? WHERE id = ?");
            $stmt->execute([$result['qr_code'], $result['qr_code_copia_cola'], $result['link_pagamento'], $result['payment_id'], $faturaId]);
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
                    </div>
                <?php else: ?>
                    <div class="app-pagamento-box app-animate">
                        <h6><i class="fas fa-qrcode"></i> Pagamento</h6>

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
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="text-align:center; padding:16px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a>
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
    function copiarPix() {
        var code = document.getElementById('pixCode').textContent.trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function() {
                alert('Código PIX copiado!');
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            alert('Código PIX copiado!');
        }
    }
    </script>

    <?php if (!$fatura['pix_qrcode'] && $fatura['pix_copia_cola']): ?>
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
    $apiAtiva = getApiAtiva();
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
    </div>
    <script src="pwa.js"></script>
</body>
</html>