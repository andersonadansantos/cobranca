<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();

if (isMobileDevice()) {
    header('Location: /cobranca/app/fatura.php?id=' . intval($_GET['id'] ?? 0));
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$faturaId = intval($_GET['id'] ?? 0);
$erroMsg = '';

if ($faturaId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND cliente_id = ?");
$stmt->execute([$faturaId, $userId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    header('Location: index.php');
    exit;
}

// Gerar boleto
if (isset($_GET['gerar_boleto']) && $fatura['status'] !== 'pago') {
    if (!empty($fatura['boleto_url'])) {
        header('Location: ' . $fatura['boleto_url']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$userId]);
    $cli = $stmt->fetch();

    $result = criarBoleto(
        $fatura['descricao'],
        $fatura['valor_final'],
        $cli['nome_razao'] ?? '',
        $cli['cpf_cnpj'] ?? '',
        $cli['email'] ?? '',
        $cli['cep'] ?? '',
        $cli['logradouro'] ?? '',
        $cli['numero'] ?? '',
        $cli['bairro'] ?? '',
        $cli['cidade'] ?? '',
        $cli['estado'] ?? ''
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
        $fatura['mp_payment_id'] = $result['payment_id'];
    } else {
        $erroMsg = $result['erro'] ?? 'Erro ao gerar pagamento.';
    }
}

$pageTitle = 'Fatura ' . $fatura['numero'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_usuario.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Fatura <?= htmlspecialchars($fatura['numero']) ?></h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/usuario/perfil.php"><i class="fas fa-user-edit me-2"></i>Meu Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/usuario/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <div class="mb-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
        </div>

        <?php if ($erroMsg && empty($_GET['erro'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($erroMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'boleto'): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($erroMsg ?: 'Erro ao gerar boleto. Verifique seus dados cadastrais.') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="fatura-card mb-4">
            <div class="fatura-card-row">
                <span class="fatura-numero-tag"><?= htmlspecialchars($fatura['numero']) ?></span>
                <h4 class="fatura-titulo"><?= htmlspecialchars($fatura['descricao']) ?></h4>
                <?php
                $classes = [
                    'pendente' => 'badge-pendente',
                    'pago' => 'badge-pago',
                    'atrasado' => 'badge-atrasado',
                    'vencido' => 'badge-vencido',
                    'cancelado' => 'badge-cancelado'
                ];
                $classe = $classes[$fatura['status']] ?? 'badge-pendente';
                ?>
                <span class="badge-status <?= $classe ?>"><?= ucfirst($fatura['status']) ?></span>
            </div>

            <div class="fatura-divider"></div>

            <div class="fatura-card-row fatura-info-strip">
                <div class="fatura-strip-item">
                    <span class="fatura-info-label">Valor</span>
                    <span class="fatura-info-value fatura-valor-grande">R$ <?= number_format($fatura['valor_final'], 2, ',', '.') ?></span>
                </div>
                <div class="fatura-strip-item">
                    <span class="fatura-info-label">Vencimento</span>
                    <span class="fatura-info-value"><?= date('d/m/Y', strtotime($fatura['data_vencimento'])) ?></span>
                    <?php
                    $dias = (strtotime($fatura['data_vencimento']) - strtotime(date('Y-m-d'))) / 86400;
                    if ($fatura['status'] !== 'pago' && $dias < 0): ?>
                        <span class="fatura-info-tag fatura-tag-perigo"><i class="fas fa-exclamation-circle me-1"></i><?= abs(intval($dias)) ?> dia(s) atrasado</span>
                    <?php elseif ($fatura['status'] !== 'pago' && $dias <= 3 && $dias >= 0): ?>
                        <span class="fatura-info-tag fatura-tag-aviso"><i class="fas fa-clock me-1"></i>Vence em <?= intval($dias) ?> dia(s)</span>
                    <?php endif; ?>
                </div>
                <div class="fatura-strip-item">
                    <span class="fatura-info-label">Emissão</span>
                    <span class="fatura-info-value"><?= date('d/m/Y', strtotime($fatura['data_emissao'])) ?></span>
                </div>
                <?php if ($fatura['data_pagamento']): ?>
                <div class="fatura-strip-item">
                    <span class="fatura-info-label">Pagamento</span>
                    <span class="fatura-info-value fatura-text-sucesso"><?= date('d/m/Y', strtotime($fatura['data_pagamento'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="pagamentoBox">
            <?php if ($fatura['status'] === 'pago'): ?>
                <div class="fatura-card fatura-card-pago" id="pagoBox">
                    <div class="fatura-pago-check">
                        <i class="fas fa-check"></i>
                    </div>
                    <h5 class="fatura-pago-texto">Pagamento Confirmado</h5>
                    <p class="fatura-pago-sub">Esta fatura já foi quitada.</p>
                </div>
            <?php else: ?>
                <div class="fatura-card fatura-card-pagamento" id="pendenteBox">
                    <div class="fatura-pag-header">
                        <i class="fas fa-qrcode me-2"></i>Pagamento
                    </div>
                    <div class="fatura-pag-body">
                        <div class="fatura-pag-strip">

                            <!-- Coluna 1: QR Code -->
                            <div class="fatura-pag-col">
                                <?php if ($fatura['pix_copia_cola']): ?>
                                    <?php if ($fatura['pix_qrcode']): ?>
                                        <div id="qrImageWrap">
                                            <img src="data:image/png;base64,<?= htmlspecialchars($fatura['pix_qrcode']) ?>" alt="QR Code PIX" style="max-width:140px; border:1px solid #dee2e6; border-radius:8px;">
                                        </div>
                                    <?php else: ?>
                                        <div id="qrImageWrap">
                                            <div id="qrCodeContainer" style="display:inline-block;"></div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="fatura-pag-empty">
                                        <i class="fas fa-qrcode"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Coluna 2: PIX + Pagar -->
                            <div class="fatura-pag-col fatura-pag-col-pix">
                                <?php if ($fatura['pix_copia_cola']): ?>
                                    <div class="fatura-pix-label">Código PIX</div>
                                    <div class="pix-copia-cola mb-2" id="pixCode">
                                        <?= htmlspecialchars($fatura['pix_copia_cola']) ?>
                                    </div>
                                    <button class="btn btn-success btn-sm w-100 mb-2" onclick="copiarPix(document.getElementById('pixCode').textContent.trim())">
                                        <i class="fas fa-copy me-1"></i> Copiar
                                    </button>
                                    <?php if ($fatura['link_pagamento']): ?>
                                        <a href="<?= htmlspecialchars($fatura['link_pagamento']) ?>" target="_blank" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-external-link-alt me-1"></i> Pagar Agora
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Sem dados PIX</span>
                                <?php endif; ?>
                            </div>

                            <!-- Coluna 3: Boleto -->
                            <div class="fatura-pag-col fatura-pag-col-boleto">
                                <div class="fatura-boleto-icon">
                                    <i class="fas fa-barcode"></i>
                                </div>
                                <?php if (!empty($fatura['boleto_url'])): ?>
                                    <a href="<?= htmlspecialchars($fatura['boleto_url']) ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="fas fa-file-invoice me-1"></i> Boleto
                                    </a>
                                <?php elseif ($fatura['status'] !== 'pago'): ?>
                                    <a href="?id=<?= $faturaId ?>&gerar_boleto=1" class="btn btn-outline-primary btn-sm w-100" onclick="showConfirm('Gerar Boleto','Deseja gerar o boleto para pagamento?','?id=<?= $faturaId ?>&gerar_boleto=1','primary'); return false;">
                                        <i class="fas fa-barcode me-1"></i> Boleto
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$fatura['pix_qrcode'] && $fatura['pix_copia_cola']): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('qrCodeContainer');
    if (container) {
        var pixText = document.getElementById('pixCode').textContent.trim();
        new QRCode(container, {
            text: pixText,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
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
var _pollMax = 120;
function verificarPagamento() {
    if (_pollCount >= _pollMax) return;
    _pollCount++;
    fetch('/cobranca/api/verificar_status.php?id=' + _faturaId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'pago') {
                var modal = new bootstrap.Modal(document.getElementById('modalPagamentoSucesso'));
                modal.show();
                var box = document.getElementById('pagamentoBox');
                if (box) {
                    box.innerHTML = '<div class="form-card text-center" id="pagoBox"><div class="fatura-pago-check"><i class="fas fa-check"></i></div><h5 class="fatura-pago-texto">Pagamento Confirmado</h5><p class="fatura-pago-sub">Esta fatura já foi quitada.</p></div>';
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
<div class="modal fade" id="modalPagamentoSucesso" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body py-4">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success fw-bold">Pagamento Confirmado!</h5>
                <p class="text-muted small mb-0">Seu pagamento foi realizado com sucesso.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <a href="index.php" class="btn btn-success btn-sm px-3">Voltar ao Painel</a>
            </div>
        </div>
    </div>
</div>
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
var _interPollMax = 60;
function verificarInterPix() {
    if (_interPollCount >= _interPollMax) return;
    _interPollCount++;
    fetch('/cobranca/api/inter_status.php?id=' + _faturaId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'pago') {
                var modal = new bootstrap.Modal(document.getElementById('modalPagamentoSucesso'));
                modal.show();
                var box = document.getElementById('pagamentoBox');
                if (box) {
                    box.innerHTML = '<div class="form-card text-center" id="pagoBox"><div class="fatura-pago-check"><i class="fas fa-check"></i></div><h5 class="fatura-pago-texto">Pagamento Confirmado</h5><p class="fatura-pago-sub">Esta fatura já foi quitada.</p></div>';
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
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
