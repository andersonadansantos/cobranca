<?php
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedInUser() && !empty($_GET['token'])) {
    autoLoginByToken($_GET['token']);
}
requireUser();

if (isMobileDevice()) {
    $redirToken = !empty($_GET['token']) ? '&token=' . urlencode($_GET['token']) : '';
    header('Location: /cobranca/app/fatura.php?id=' . intval($_GET['id'] ?? 0) . $redirToken);
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

// Contexto de tenant para resolver as configurações do admin dono da fatura.
if (!empty($fatura['admin_id'])) {
    $_SESSION['tenant_admin_id'] = (int) $fatura['admin_id'];
}

// CPF/CNPJ do cadastro do cliente (fonte oficial para o pagamento com cartão).
$stmtCpf = $pdo->prepare("SELECT cpf_cnpj FROM clientes WHERE id = ?");
$stmtCpf->execute([$userId]);
$clienteCpfCnpj = (string) ($stmtCpf->fetchColumn() ?: '');

$apiDaFatura = $fatura['api_pagamento'] ?: getApiAtiva();
$mpConfig = getMPConfig();
$cartaoPermitido = ($apiDaFatura === 'mercadopago')
    && aceitaCartaoCredito()
    && !empty($mpConfig['mp_public_key'])
    && $fatura['status'] !== 'pago';

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
            <?php $msgBoleto = trim((string) ($_GET['msg'] ?? '')); ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($msgBoleto !== '' ? $msgBoleto : 'Erro ao gerar boleto. Verifique seus dados cadastrais.') ?>
                <?php if (stripos($msgBoleto, 'perfil') !== false): ?>
                    <a href="/cobranca/usuario/perfil.php" class="alert-link ms-1">Completar meu cadastro</a>
                <?php endif; ?>
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
                    <?php
                    $nubankWa = getConfig('nubank_whatsapp', '');
                    $pmWa = getConfig('pix_manual_whatsapp', '');
                    $whatsappNum = $nubankWa ?: $pmWa;
                    if (!empty($whatsappNum)):
                        $msgWa = 'Oi, segue comprovante da fatura (' . $fatura['numero'] . ').';
                        $linkWa = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsappNum) . '?text=' . urlencode($msgWa);
                    ?>
                        <a href="<?= htmlspecialchars($linkWa) ?>" target="_blank" class="btn btn-success btn-sm mt-2">
                            <i class="fab fa-whatsapp me-1"></i> Enviar Comprovante via WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="fatura-card fatura-card-pagamento" id="pendenteBox">
                    <div class="fatura-pag-header">
                        <i class="fas fa-qrcode me-2"></i>Pagamento
                    </div>
                    <div class="fatura-pag-body">
                        <?php
                        $pmCfgPag = ($apiDaFatura === 'pix_manual') ? getConfigPixManual() : [];
                        $temPix = ($apiDaFatura === 'pix_manual')
                            ? (!empty($pmCfgPag['pix_manual_chave']) || !empty($pmCfgPag['pix_manual_favorecido']))
                            : !empty($fatura['pix_copia_cola']);
                        $temBoleto = ($apiDaFatura !== 'pix_manual');
                        $temCartao = (bool) $cartaoPermitido;
                        $metodoAtivo = $temPix ? 'pix' : ($temBoleto ? 'boleto' : 'cartao');
                        ?>

                        <?php if ($temPix || $temBoleto || $temCartao): ?>
                        <div class="fatura-metodos" role="tablist" aria-label="Formas de pagamento">
                            <?php if ($temPix): ?>
                            <button type="button" class="fatura-metodo<?= $metodoAtivo === 'pix' ? ' active' : '' ?>" data-metodo="pix" role="tab" aria-selected="<?= $metodoAtivo === 'pix' ? 'true' : 'false' ?>">
                                <i class="fas fa-qrcode"></i><span>PIX</span>
                            </button>
                            <?php endif; ?>
                            <?php if ($temBoleto): ?>
                            <button type="button" class="fatura-metodo<?= $metodoAtivo === 'boleto' ? ' active' : '' ?>" data-metodo="boleto" role="tab" aria-selected="<?= $metodoAtivo === 'boleto' ? 'true' : 'false' ?>">
                                <i class="fas fa-barcode"></i><span>Boleto</span>
                            </button>
                            <?php endif; ?>
                            <?php if ($temCartao): ?>
                            <button type="button" class="fatura-metodo<?= $metodoAtivo === 'cartao' ? ' active' : '' ?>" data-metodo="cartao" role="tab" aria-selected="<?= $metodoAtivo === 'cartao' ? 'true' : 'false' ?>">
                                <i class="fas fa-credit-card"></i><span>Cartão</span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($temPix): ?>
                        <div class="fatura-metodo-panel<?= $metodoAtivo === 'pix' ? ' active' : '' ?>" data-panel="pix" role="tabpanel">
                            <?php if ($apiDaFatura === 'pix_manual'): ?>
                                <div class="fatura-pix-layout">
                                    <div class="fatura-pix-qr">
                                        <div class="fatura-pix-qr-titulo">PIX manual</div>
                                        <div id="qrImageWrap">
                                            <?php if (!empty($pmCfgPag['pix_manual_chave'])): ?>
                                                <div id="pmQrContainer" style="display:inline-block;"></div>
                                            <?php else: ?>
                                                <div class="fatura-pag-empty"><i class="fas fa-qrcode"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fatura-pix-qr-note"><i class="fas fa-mobile-alt me-1"></i>Aponte a câmera do app do seu banco</div>
                                    </div>
                                    <div class="fatura-pix-cola">
                                        <div class="fatura-pix-label">Dados do Recebedor</div>
                                        <?php if (!empty($pmCfgPag['pix_manual_chave'])): ?>
                                            <div class="fatura-pix-row">
                                                <span class="text-muted">Chave PIX:</span>
                                                <strong class="break-all"><?= htmlspecialchars($pmCfgPag['pix_manual_chave']) ?></strong>
                                                <button type="button" class="btn btn-outline-secondary btn-sm flex-none" onclick="copiarPix('<?= htmlspecialchars($pmCfgPag['pix_manual_chave']) ?>')"><i class="fas fa-copy"></i></button>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($pmCfgPag['pix_manual_banco'])): ?>
                                            <div class="fatura-pix-row"><span class="text-muted">Banco:</span> <strong><?= htmlspecialchars($pmCfgPag['pix_manual_banco']) ?></strong></div>
                                        <?php endif; ?>
                                        <?php if (!empty($pmCfgPag['pix_manual_favorecido'])): ?>
                                            <div class="fatura-pix-row"><span class="text-muted">Favorecido:</span> <strong><?= htmlspecialchars($pmCfgPag['pix_manual_favorecido']) ?></strong></div>
                                        <?php endif; ?>
                                        <?php if (!empty($pmCfgPag['pix_manual_whatsapp'])): ?>
                                            <?php $pmWaLink = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $pmCfgPag['pix_manual_whatsapp']) . '?text=' . urlencode('Oi, tenho uma dúvida sobre pagamento.'); ?>
                                            <a href="<?= htmlspecialchars($pmWaLink) ?>" target="_blank" class="btn btn-success w-100 mt-2">
                                                <i class="fab fa-whatsapp me-1"></i> Enviar Comprovante
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="fatura-pix-layout">
                                    <div class="fatura-pix-qr">
                                        <div class="fatura-pix-qr-titulo">Escaneie para pagar</div>
                                        <div id="qrImageWrap">
                                            <?php if ($fatura['pix_qrcode']): ?>
                                                <img src="data:image/png;base64,<?= htmlspecialchars($fatura['pix_qrcode']) ?>" alt="QR Code PIX" class="fatura-pix-qr-img">
                                            <?php else: ?>
                                                <div id="qrCodeContainer" style="display:inline-block;"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fatura-pix-qr-note"><i class="fas fa-mobile-alt me-1"></i>Aponte a câmera do app do seu banco</div>
                                    </div>
                                    <div class="fatura-pix-cola">
                                        <div class="fatura-pix-label">Código PIX (copia e cola)</div>
                                        <div class="pix-copia-cola" id="pixCode">
                                            <?= htmlspecialchars($fatura['pix_copia_cola']) ?>
                                        </div>
                                        <button class="btn btn-success w-100 mt-2" onclick="copiarPix(document.getElementById('pixCode').textContent.trim())">
                                            <i class="fas fa-copy me-1"></i> Copiar código PIX
                                        </button>
                                        <?php if ($fatura['link_pagamento']): ?>
                                            <a href="<?= htmlspecialchars($fatura['link_pagamento']) ?>" target="_blank" class="btn btn-primary w-100 mt-2">
                                                <i class="fas fa-external-link-alt me-1"></i> Pagar Agora
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($temBoleto): ?>
                        <div class="fatura-metodo-panel<?= $metodoAtivo === 'boleto' ? ' active' : '' ?>" data-panel="boleto" role="tabpanel">
                            <div class="fatura-boleto-panel">
                                <div class="fatura-boleto-icone-grande"><i class="fas fa-barcode"></i></div>
                                <div class="fatura-boleto-info">
                                    <div class="fatura-boleto-titulo">Boleto Bancário</div>
                                    <div class="fatura-boleto-desc">Pague pelo código de barras em qualquer banco, caixa eletrônico ou app.</div>
                                    <?php if (!empty($fatura['boleto_url'])): ?>
                                        <a href="<?= htmlspecialchars($fatura['boleto_url']) ?>" target="_blank" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-invoice me-1"></i> Ver meu Boleto
                                        </a>
                                    <?php else: ?>
                                        <a href="?id=<?= $faturaId ?>&gerar_boleto=1" class="btn btn-outline-primary w-100" onclick="showConfirm('Gerar Boleto','Deseja gerar o boleto para pagamento?','?id=<?= $faturaId ?>&gerar_boleto=1','primary'); return false;">
                                            <i class="fas fa-barcode me-1"></i> Gerar Boleto
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($temCartao): ?>
                        <div class="fatura-metodo-panel<?= $metodoAtivo === 'cartao' ? ' active' : '' ?>" data-panel="cartao" role="tabpanel">
                            <div class="fatura-pag-cartao" id="ccBox">
                                <div class="fatura-cartao-tabs" role="group" aria-label="Tipo de cartão">
                                    <button type="button" class="fatura-cartao-tab active" id="tabCredito" data-tipo="credito">
                                        <i class="fas fa-credit-card"></i> Crédito
                                    </button>
                                    <button type="button" class="fatura-cartao-tab" id="tabDebito" data-tipo="debito">
                                        <i class="fas fa-money-check-alt"></i> Débito
                                    </button>
                                </div>
                                <form id="ccForm" autocomplete="off">
                                    <div class="mb-2">
                                        <div class="fatura-cc-top">
                                            <label class="fatura-info-label">Número do cartão</label>
                                            <span class="fatura-cc-badge" id="ccBrand"></span>
                                        </div>
                                        <input type="text" id="ccNumero" class="form-control form-control-sm" inputmode="numeric" maxlength="19" placeholder="0000 0000 0000 0000" autocomplete="cc-number" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="fatura-info-label">Nome impresso no cartão</label>
                                        <input type="text" id="ccNome" class="form-control form-control-sm" maxlength="40" placeholder="NOME COMO ESTÁ NO CARTÃO" autocomplete="cc-name" required>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-5">
                                            <label class="fatura-info-label">Validade</label>
                                            <input type="text" id="ccValidade" class="form-control form-control-sm" inputmode="numeric" maxlength="5" placeholder="MM/AA" autocomplete="cc-exp" required>
                                        </div>
                                        <div class="col-4">
                                            <label class="fatura-info-label">CVV</label>
                                            <input type="text" id="ccCvv" class="form-control form-control-sm" inputmode="numeric" maxlength="4" placeholder="123" autocomplete="cc-csc" required>
                                        </div>
                                        <div class="col-3" id="ccParcelasWrap">
                                            <label class="fatura-info-label">Parcelas</label>
                                            <select id="ccParcelas" class="form-select form-select-sm"></select>
                                        </div>
                                    </div>
                                    <div id="ccMsg" class="small mb-2"></div>
                                    <button type="submit" id="ccBtn" class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-credit-card me-1"></i> Pagar
                                    </button>
                                    <p class="fatura-cartao-seguro"><i class="fas fa-lock"></i> Pagamento processado com segurança pelo Mercado Pago</p>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var metodos = document.querySelectorAll('.fatura-metodo');
                    var paineis = document.querySelectorAll('.fatura-metodo-panel');
                    metodos.forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            var alvo = btn.getAttribute('data-metodo');
                            metodos.forEach(function(b) {
                                var ativo = b === btn;
                                b.classList.toggle('active', ativo);
                                b.setAttribute('aria-selected', ativo ? 'true' : 'false');
                            });
                            paineis.forEach(function(p) {
                                p.classList.toggle('active', p.getAttribute('data-panel') === alvo);
                            });
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($apiDaFatura !== 'pix_manual' && !$fatura['pix_qrcode'] && $fatura['pix_copia_cola']): ?>
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
$apiAtiva = $apiDaFatura;
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
<?php if ($cartaoPermitido): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
(function() {
    var faturaId = <?= (int) $faturaId ?>;
    var faturaValor = <?= (float) $fatura['valor_final'] ?>;
    var maxParcelas = <?= (int) getMaxParcelasCartao() ?>;
    var cpfCliente = <?= json_encode(preg_replace('/[^0-9]/', '', $clienteCpfCnpj)) ?>;

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
    var numero = '';
    var nome = '';
    var validade = '';
    var cvv = '';
    var parcelas = 1;

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
        msg.className = 'small mb-2 text-' + (tipo || 'danger');
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
            brandEl.className = 'fatura-cc-badge show b-' + (b.b === 'outros' ? 'other' : b.b);
            brandEl.textContent = b.label;
        } else {
            brandEl.className = 'fatura-cc-badge';
            brandEl.textContent = '';
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
            ? '<i class="fas fa-money-check-alt me-1"></i>'
            : '<i class="fas fa-credit-card me-1"></i>';
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
        tabCredito.classList.toggle('active', tipoAtual === 'credito');
        tabDebito.classList.toggle('active', tipoAtual === 'debito');
        if (tipoAtual === 'debito') {
            wrapParcelas.style.opacity = '0.35';
            wrapParcelas.style.pointerEvents = 'none';
        } else {
            wrapParcelas.style.opacity = '1';
            wrapParcelas.style.pointerEvents = 'auto';
        }
        atualizarBtn();
    }

    tabCredito.addEventListener('click', function() { setarTipo('credito'); });
    tabDebito.addEventListener('click', function() { setarTipo('debito'); });
    selParcelas.addEventListener('change', atualizarBtn);

    function metodoDebito(numero) {
        var b = detectarBandeira(numero.replace(/\s+/g, ''));
        if (!b) return '';
        switch (b.b) {
            case 'visa': return 'debvisa';
            case 'master': return 'debmaster';
            case 'elo': return 'debelo';
            case 'hipercard': return 'debhipercard';
            default: return '';
        }
    }

    function montarMensagensErro(err) {
        var msgs = [];
        function adicionar(m) {
            if (m && typeof m === 'string' && msgs.indexOf(m) === -1) msgs.push(m);
        }
        if (Array.isArray(err)) {
            err.forEach(function(item) { adicionar(item && (item.message || item.description)); });
        } else if (err && typeof err === 'object') {
            adicionar(err.message);
            adicionar(err.error);
            if (Array.isArray(err.cause)) {
                err.cause.forEach(function(c) { adicionar(c && c.description); });
            }
        } else {
            adicionar(err);
        }
        return msgs.join(' | ') || 'Não foi possível validar o cartão. Verifique os dados e tente novamente.';
    }

    function tokenErro(err) {
        btn.disabled = false;
        atualizarBtn();
        mostrarMsg(montarMensagensErro(err), 'danger');
    }

    function tokenSucesso(resp) {
        if (!resp || typeof resp.id !== 'string' || resp.id === '') {
            tokenErro(resp);
            return;
        }

        btn.innerHTML = '<i class="fas fa-credit-card me-1"></i> Aguardando confirmação...';
        mostrarMsg('', 'muted');

        var method = tipoAtual === 'debito' ? metodoDebito(numero) : '';
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
                    mostrarMsg('<span class="text-success"><i class="fas fa-check-circle"></i> Pagamento aprovado!</span>', 'success');
                    if (typeof bootstrap !== 'undefined') {
                        var modal = new bootstrap.Modal(document.getElementById('modalPagamentoSucesso'));
                        modal.show();
                    }
                    var box = document.getElementById('pagamentoBox');
                    if (box) {
                        box.innerHTML = '<div class="form-card text-center" id="pagoBox"><div class="fatura-pago-check"><i class="fas fa-check"></i></div><h5 class="fatura-pago-texto">Pagamento Confirmado</h5><p class="fatura-pago-sub">Esta fatura já foi quitada.</p></div>';
                    }
                } else {
                    btn.disabled = false;
                    atualizarBtn();
                    mostrarMsg('<i class="fas fa-clock"></i> Pagamento em análise. Acompanhe a confirmação aqui.', 'warning');
                }
            } else {
                btn.disabled = false;
                atualizarBtn();
                mostrarMsg(d.erro || 'Não foi possível processar o pagamento.', 'danger');
            }
        })
        .catch(function() {
            btn.disabled = false;
            atualizarBtn();
            mostrarMsg('Erro de conexão. Tente novamente.', 'danger');
        });
    }

    document.getElementById('ccForm').addEventListener('submit', function(e) {
        e.preventDefault();
        numero = inputNumero.value.replace(/\s+/g, '');
        nome = inputNome.value.trim();
        validade = inputValidade.value.trim();
        cvv = inputCvv.value.trim();
        parcelas = parcelasAtuais();

        if (!cpfCliente) {
            mostrarMsg('Cadastre o seu CPF no seu perfil para pagar com cartão.', 'danger');
            return;
        }
        if (!/^\d{13,16}$/.test(numero)) { mostrarMsg('Número de cartão inválido.', 'danger'); return; }
        var m = validade.match(/^(\d{2})\s*\/\s*(\d{2})$/);
        if (!m) { mostrarMsg('Validade inválida. Use o formato MM/AA.', 'danger'); return; }
        var mes = parseInt(m[1], 10), ano = 2000 + parseInt(m[2], 10);
        if (mes < 1 || mes > 12) { mostrarMsg('Mês da validade inválido.', 'danger'); return; }
        if (cvv.length < 3) { mostrarMsg('CVV inválido.', 'danger'); return; }
        if (!nome) { mostrarMsg('Informe o nome impresso no cartão.', 'danger'); return; }

        var hoje = new Date();
        if (ano < hoje.getFullYear() || (ano === hoje.getFullYear() && mes < hoje.getMonth() + 1)) {
            mostrarMsg('Este cartão está vencido.', 'danger');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processando...';
        mostrarMsg('', 'muted');

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

        // SDK v2 do Mercado Pago: createCardToken retorna uma Promise e ignora
        // callbacks. Tratamos as duas formas para garantir compatibilidade.
        var prom = null;
        try {
            prom = mp.createCardToken(payload);
        } catch (ex) {
            tokenErro(ex);
            return;
        }
        if (prom && typeof prom.then === 'function') {
            prom.then(tokenSucesso).catch(function(err) { tokenErro(err); });
        } else {
            mp.createCardToken(payload, function(resp, err) {
                if (err && (err.length || err.message)) tokenErro(err);
                else tokenSucesso(resp);
            });
        }
    });

    setarTipo('credito');
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
