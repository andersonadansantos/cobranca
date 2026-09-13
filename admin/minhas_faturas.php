<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/inter_pix.php';
require_once __DIR__ . '/../config/mercadopago.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];

// === Métodos de pagamento disponíveis para faturas de plano (PIX + boleto + cartão) ===
$metodosAdmin = ['pix', 'boleto', 'cartao'];
if (getConfig('super_plano_cartao', '1') !== '1') {
    $metodosAdmin = array_values(array_diff($metodosAdmin, ['cartao']));
}
if (getConfig('super_plano_pix_boleto', '1') !== '1') {
    $metodosAdmin = array_values(array_diff($metodosAdmin, ['pix', 'boleto']));
}
$cartaoOk = in_array('cartao', $metodosAdmin, true);
$maxParcelasAdmin = $cartaoOk ? max(1, min((int)getConfig('super_mp_max_parcelas', '12'), 12)) : 0;

$mpPubKeyAdmin = '';
if ($cartaoOk) {
    $superMp = getMPConfigSuper();
    $mpPubKeyAdmin = $superMp['super_mp_public_key'] ?? '';
}

// CPF/CNPJ do admin (obrigatório para cartão e boleto)
$adminCpfCnpj = '';
$stmt = $pdo->prepare("SELECT cpf, cnpj FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$rowCpf = $stmt->fetch();
$adminCpfCnpj = $rowCpf ? preg_replace('/[^0-9]/', '', (($rowCpf['cnpj'] ?: '') ?: ($rowCpf['cpf'] ?? ''))) : '';

// Gera automaticamente a fatura de renovação quando faltam 7 dias (ou menos)
$faturaAutoId = gerarFaturaRenovacaoAuto($adminId);

$stmtPlano = $pdo->prepare("
    SELECT p.nome AS plano_nome, p.cor AS plano_cor, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$stmtPlano->execute([$adminId]);
$meuPlanoInfo = $stmtPlano->fetch();

$diasRestantes = null;
if ($meuPlanoInfo && !empty($meuPlanoInfo['data_fim'])) {
    $diasRestantes = (int)floor((strtotime($meuPlanoInfo['data_fim']) - strtotime(date('Y-m-d'))) / 86400);
}

$faturas = $pdo->prepare("
    SELECT pp.*, p.nome AS plano_nome
    FROM planos_pagamentos pp
    LEFT JOIN planos p ON p.id = pp.plano_id
    WHERE pp.admin_id = ?
    ORDER BY pp.criado_em DESC, pp.id DESC
");
$faturas->execute([$adminId]);
$faturas = $faturas->fetchAll();

$totalPendente = 0;
$totalPago = 0.0;
foreach ($faturas as $f) {
    if ($f['status'] === 'pendente') $totalPendente++;
    if ($f['status'] === 'pago') $totalPago += (float)$f['valor'];
}

$periodoLabel = [1 => 'Mensal', 3 => 'Trimestral', 6 => 'Semestral', 12 => 'Anual'];
$badgeStatus = [
    'pendente'  => ['badge-pendente',  'Pendente'],
    'pago'      => ['badge-pago',      'Pago'],
    'expirado'  => ['badge-cancelado', 'Expirado'],
    'cancelado' => ['badge-cancelado', 'Cancelado'],
    'vencido'   => ['badge-vencido',   'Vencido'],
];

// Dados para reutilizar QR/copia-cola já gerados sem criar nova cobrança no Inter
$faturasJs = [];
foreach ($faturas as $f) {
    $faturasJs[$f['id']] = [
        'descricao'   => $f['descricao'] ?: ('Fatura de plano'),
        'plano'       => $f['plano_nome'],
        'valor'       => 'R$ ' . number_format((float)$f['valor'], 2, ',', '.'),
        'valor_num'   => (float)$f['valor'],
        'qr'          => $f['qr_code'] ?: '',
        'pix'         => $f['pix_copia_cola'] ?: '',
        'status'      => $f['status'],
        'metodo'      => $f['metodo'] ?: '',
        'boleto_url'  => $f['boleto_url'] ?: '',
        'boleto_linha' => $f['boleto_linha_digitavel'] ?: ($f['boleto_codigo_barras'] ?: ''),
    ];
}

$metodoLabel = ['pix' => 'PIX', 'boleto' => 'Boleto', 'cartao' => 'Cartão'];
$metodoBadge = [
    'pix'    => 'badge-api-ativa bg-success',
    'boleto' => 'badge-api-ativa bg-warning text-dark',
    'cartao' => 'badge-api-ativa bg-primary',
];

$pageTitle = 'Minhas Faturas';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<style>
    #pixModalFatura .qr-box {
        width: 200px; height: 200px; margin:0 auto; border:1px solid #eee; border-radius:.75rem;
        display:flex; align-items:center; justify-content:center; background:#fff; position:relative;
    }
    #pixModalFatura .pix-copia {
        background:#f6f3ef; border:1px dashed #cd7f32; border-radius:.6rem;
        font-size:.78rem; word-break:break-all; padding:.6rem .75rem; color:#4b3f35;
    }
    .pulse-dot { width:10px; height:10px; border-radius:50%; background:#f59e0b; display:inline-block; animation:pulse 1.4s infinite; }
    @keyframes pulse { 0%{box-shadow:0 0 0 0 rgba(245,158,11,.5);} 70%{box-shadow:0 0 0 8px rgba(245,158,11,0);} 100%{box-shadow:0 0 0 0 rgba(245,158,11,0);} }
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice-dollar me-1"></i> Minhas Faturas</h5>
        </div>
        <?php if (!empty($meuPlanoInfo) && $diasRestantes !== null): ?>
        <span class="d-inline-flex align-items-center gap-1 me-2 py-1 px-2 rounded-pill" style="font-size:0.78rem;border:1px solid #dee2e6;<?= $diasRestantes <= 7 ? 'background:#fff3cd;color:#856404;border-color:#ffc107;' : 'background:#e8f5ee;color:#0f7b5c;' ?>">
            <i class="fas fa-hourglass-half me-1"></i>
            <?php if ($diasRestantes <= 0): ?>
                <strong class="ms-1">Plano vencido</strong>
            <?php elseif ($diasRestantes === 1): ?>
                <strong class="ms-1">Vence hoje</strong>
            <?php else: ?>
                <strong class="ms-1"><?= $diasRestantes ?> dia<?= $diasRestantes > 1 ? 's' : '' ?></strong> restantes
            <?php endif; ?>
            <span class="text-muted">· <?= htmlspecialchars($meuPlanoInfo['plano_nome']) ?></span>
        </span>
        <?php endif; ?>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome'] ?? '') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if (function_exists('adminPlanoExpirado') && adminPlanoExpirado()): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="border-left:4px solid #dc3545;">
            <i class="fas fa-exclamation-triangle me-3" style="font-size:1.2rem;"></i>
            <div>
                <strong>Seu plano está vencido.</strong>
                <br><small>Selecione uma fatura pendente abaixo e efetue o pagamento para reativar o acesso.</small>
            </div>
        </div>
        <?php elseif ($diasRestantes !== null && $diasRestantes <= 7): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert" style="border-left:4px solid #ffc107;">
            <i class="fas fa-bell me-3" style="font-size:1.2rem;"></i>
            <div>
                <strong>Seu plano encerra em <?= max(0, $diasRestantes) ?> dia(s).</strong>
                <br><small>Uma fatura de renovação foi gerada. Efetue o pagamento para renovar mais dias.</small>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= count($faturas) ?></div>
                            <div class="stat-label">Total de Faturas</div>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-file-invoice"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: var(--cor-aviso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalPendente ?></div>
                            <div class="stat-label">Faturas Pendentes</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-aviso);"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: var(--cor-sucesso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($totalPago, 2, ',', '.') ?></div>
                            <div class="stat-label">Total Pago em Assinaturas</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-sucesso);"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Faturas do Plano</h6>
                <a href="/cobranca/admin/meu_plano.php" class="btn btn-sm btn-outline-warning fw-semibold"><i class="fas fa-tags me-1"></i>Ver / Assinar Planos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fatura</th>
                            <th>Plano</th>
                            <th>Período</th>
                            <th>Método</th>
                            <th>Emissão</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma fatura encontrada.</td></tr>
                        <?php else: foreach ($faturas as $f): ?>
                            <?php
                                $st = $f['status'];
                                $badge = $badgeStatus[$st] ?? ['badge-pendente', ucfirst($st)];
                                $periodo = $periodoLabel[(int)($f['duracao_meses'] ?? 1)] ?? ((int)($f['duracao_meses'] ?? 1) . ' meses');
                                $isPendente = ($st === 'pendente');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">#<?= str_pad((string)$f['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($f['descricao'] ?: 'Fatura de plano') ?></small>
                                </td>
                                <td><?= htmlspecialchars($f['plano_nome'] ?? '—') ?></td>
                                <td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= htmlspecialchars($periodo) ?></span></td>
                                <td><span class="badge <?= $metodoBadge[$f['metodo'] ?? 'pix'] ?? 'badge-api-ativa bg-secondary' ?>"><?= htmlspecialchars($metodoLabel[$f['metodo'] ?? 'pix'] ?? 'PIX') ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($f['criado_em'])) ?></td>
                                <td><strong>R$ <?= number_format((float)$f['valor'], 2, ',', '.') ?></strong></td>
                                <td><span class="badge-status <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                                <td class="text-end">
                                    <?php if ($isPendente && ($f['metodo'] ?? 'pix') === 'boleto'): ?>
                                        <button class="btn btn-sm btn-warning fw-bold" onclick="abrirFatura(<?= (int)$f['id'] ?>)">
                                            <i class="fas fa-barcode me-1"></i>Pagar boleto
                                        </button>
                                    <?php elseif ($isPendente && ($f['metodo'] ?? 'pix') === 'cartao'): ?>
                                        <button class="btn btn-sm btn-outline-primary fw-semibold" onclick="verificarCartaoFatura(<?= (int)$f['id'] ?>, this)">
                                            <i class="fas fa-sync-alt me-1"></i>Verificar pagamento
                                        </button>
                                    <?php elseif ($isPendente): ?>
                                        <button class="btn btn-sm btn-warning fw-bold" onclick="abrirFatura(<?= (int)$f['id'] ?>)">
                                            <i class="fas fa-wallet me-1"></i>Pagar agora
                                        </button>
                                    <?php elseif ($st === 'pago'): ?>
                                        <span class="text-success small"><i class="fas fa-check-circle me-1"></i>Confirmado</span>
                                    <?php else: ?>
                                        <a href="/cobranca/admin/meu_plano.php" class="btn btn-sm btn-outline-warning fw-semibold">Nova assinatura</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal PIX Fatura -->
<div class="modal fade" id="pixModalFatura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:1rem;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="pixModalFaturaTitle">Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4" id="pixModalFaturaBody">
                <div id="pixFaturaLoading" class="text-center py-4">
                    <div class="spinner-border" style="color:#cd7f32;"></div>
                    <p class="text-muted mt-3 mb-0">Gerando cobrança...</p>
                </div>

                <div id="metodoFaturaStep" style="display:none;">
                    <p class="small text-muted mb-3">Escolha a forma de pagamento desta fatura</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-success text-start w-100" id="metodoFaturaPixBtn" onclick="escolherMetodoFatura('pix')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
                                <span class="d-flex align-items-center gap-3">
                                    <span class="d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;border-radius:10px;background:#ecfdf5;color:#047857;flex:0 0 38px;"><i class="fab fa-pix fa-lg"></i></span>
                                    <span class="d-block lh-sm text-start">
                                        <span class="d-block fw-bold">PIX</span>
                                        <small class="text-muted">Pagamento instantâneo</small>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-warning text-start w-100" id="metodoFaturaBoletoBtn" onclick="escolherMetodoFatura('boleto')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
                                <span class="d-flex align-items-center gap-3">
                                    <span class="d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;border-radius:10px;background:#fffbeb;color:#b45309;flex:0 0 38px;"><i class="fas fa-barcode fa-lg"></i></span>
                                    <span class="d-block lh-sm text-start">
                                        <span class="d-block fw-bold">Boleto bancário</span>
                                        <small class="text-muted">Compensação em até 3 dias úteis</small>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-primary text-start w-100" id="metodoFaturaCartaoCreditoBtn" onclick="escolherMetodoFatura('cartao_credito')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
                                <span class="d-flex align-items-center gap-3">
                                    <span class="d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;border-radius:10px;background:#eef2ff;color:#4338ca;flex:0 0 38px;"><i class="fas fa-credit-card fa-lg"></i></span>
                                    <span class="d-block lh-sm text-start">
                                        <span class="d-block fw-bold">Cartão de crédito</span>
                                        <small class="text-muted">À vista ou parcelado · Mercado Pago</small>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-info text-start w-100" id="metodoFaturaCartaoDebitoBtn" onclick="escolherMetodoFatura('cartao_debito')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
                                <span class="d-flex align-items-center gap-3">
                                    <span class="d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;border-radius:10px;background:#e0f2fe;color:#0369a1;flex:0 0 38px;"><i class="fas fa-credit-card fa-lg"></i></span>
                                    <span class="d-block lh-sm text-start">
                                        <span class="d-block fw-bold">Cartão de débito</span>
                                        <small class="text-muted">Pagamento à vista · Mercado Pago</small>
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="pixFaturaContent" style="display:none;">
                    <div class="text-center mb-3">
                        <div class="d-inline-flex align-items-center gap-2">
                            <span class="pulse-dot"></span>
                            <span class="text-muted small">Aguardando pagamento</span>
                        </div>
                    </div>
                    <div class="qr-box" id="pixFaturaQrBox">
                        <img id="pixFaturaQr" src="" alt="QR Code PIX" style="width:180px;height:180px;display:none;">
                        <div id="pixFaturaQrJs" style="width:180px;height:180px;"></div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">Escaneie com o app do seu banco</small>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold">PIX Copia e Cola</label>
                        <div class="pix-copia d-flex align-items-center justify-content-between gap-2">
                            <span id="pixFaturaCopia" style="flex:1;"></span>
                            <button class="btn btn-sm btn-outline-warning" id="btnCopiarFatura" title="Copiar">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="small text-muted" onclick="trocarMetodoFatura();return false;">← Escolher outro método</a>
                    </div>
                </div>
                <div id="boletoFaturaContent" style="display:none;">
                    <div class="text-center mb-3">
                        <div class="d-inline-flex align-items-center gap-2">
                            <span class="pulse-dot"></span>
                            <span class="text-muted small">Aguardando pagamento do boleto</span>
                        </div>
                    </div>
                    <div class="text-center">
                        <a id="boletoFaturaLink" href="#" target="_blank" rel="noopener" class="btn btn-lg btn-warning fw-bold" style="border-radius:.7rem;">
                            <i class="fas fa-external-link-alt me-2"></i> Abrir boleto bancário
                        </a>
                        <div class="text-muted small mt-2">Ou pague pela linha digitável no app do seu banco</div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Linha digitável / Código de barras</label>
                        <div class="pix-copia d-flex align-items-center justify-content-between gap-2">
                            <span id="boletoFaturaLinha" style="flex:1;" class="small"></span>
                            <button class="btn btn-sm btn-outline-warning" id="btnCopiarBoletoFatura" title="Copiar">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="small text-muted" onclick="trocarMetodoFatura();return false;">← Escolher outro método</a>
                    </div>
                </div>
                <div id="cartaoFaturaContent" style="display:none;">
                    <div class="d-flex rounded border p-1 mb-3">
                        <button type="button" id="tabFaturaCredito" class="btn btn-sm flex-fill fw-bold active" style="background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12);">Crédito</button>
                        <button type="button" id="tabFaturaDebito" class="btn btn-sm flex-fill fw-bold text-muted">Débito</button>
                    </div>
                    <div id="ccFaturaMsg" class="small mb-2" style="min-height:18px;"></div>
                    <form id="ccFaturaForm">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Número do cartão</label>
                            <input id="ccFaturaNumero" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" class="form-control font-monospace">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nome impresso no cartão</label>
                            <input id="ccFaturaNome" autocomplete="cc-name" class="form-control">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Validade</label>
                                <input id="ccFaturaValidade" inputmode="numeric" placeholder="MM/AA" maxlength="5" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">CVV</label>
                                <input id="ccFaturaCvv" inputmode="numeric" maxlength="4" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3" id="ccFaturaParcelasWrap">
                            <label class="form-label small fw-semibold">Parcelas</label>
                            <select id="ccFaturaParcelas" class="form-select"></select>
                        </div>
                        <button type="submit" id="ccFaturaBtn" class="btn btn-dark w-100 fw-bold" style="border-radius:.7rem;">Pagar</button>
                    </form>
                    <p class="mt-3 mb-0 small text-muted text-center">Pagamento processado com segurança pelo Mercado Pago.</p>
                    <div class="text-center mt-3">
                        <a href="#" class="small text-muted" onclick="trocarMetodoFatura();return false;">← Escolher outro método</a>
                    </div>
                </div>
                <div id="cartaoFaturaAguardando" style="display:none;">
                    <div class="text-center mb-3">
                        <div class="d-inline-flex align-items-center gap-2">
                            <span class="pulse-dot"></span>
                            <span class="text-muted small">Aguardando confirmação do pagamento com cartão</span>
                        </div>
                    </div>
                    <div class="text-center">
                        <button class="btn btn-sm btn-outline-primary fw-semibold" onclick="verificarCartaoFatura(pixPagamentoId, this)">
                            <i class="fas fa-sync-alt me-1"></i>Verificar pagamento
                        </button>
                    </div>
                </div>
                <div class="alert alert-success mt-3 py-2 mb-0" id="faturaConfirmado" style="display:none;">
                    <i class="fas fa-check-circle me-1"></i> <strong>Pagamento confirmado!</strong> Seu plano foi renovado. Atualizando...
                </div>
                <div id="pixFaturaErro" class="alert alert-danger py-2 mb-0" style="display:none;"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php if ($cartaoOk && !empty($mpPubKeyAdmin)): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<?php endif; ?>

<script>
const faturasData = <?= json_encode($faturasJs) ?>;
const METODOS_FATURA = <?= json_encode($metodosAdmin) ?>;
const CARTAO_FATURA = METODOS_FATURA.indexOf('cartao') !== -1;
const MAX_PARCELAS = <?= (int)$maxParcelasAdmin ?>;
const PUBLIC_KEY = <?= json_encode($mpPubKeyAdmin) ?>;
const CPF_CLIENTE = <?= json_encode($adminCpfCnpj) ?>;
let pixPolling = null;
let pixPagamentoId = null;
let pixQrJsInstance = null;
let criando = false;
let tipoCartao = 'credito';
let parcelaMontada = false;
let mp = null;

function carregarQrJs(callback) {
    if (typeof QRCode !== 'undefined') { callback(); return; }
    let s = document.createElement('script');
    s.src = '/cobranca/assets/vendor/qrcodejs/qrcode.min.js';
    s.onload = callback;
    s.onerror = callback;
    document.head.appendChild(s);
}

function mostrarQrFatura(qrB64, copiaCola) {
    let qrImg = document.getElementById('pixFaturaQr');
    let qrJs = document.getElementById('pixFaturaQrJs');
    qrJs.innerHTML = '';
    if (qrB64) {
        let b64 = String(qrB64).replace(/\s/g, '');
        if (b64.indexOf('data:image') === 0) {
            qrImg.src = b64;
        } else {
            qrImg.src = 'data:image/png;base64,' + b64;
        }
        qrImg.style.display = 'block';
        qrJs.style.display = 'none';
    } else if (copiaCola) {
        qrImg.style.display = 'none';
        qrJs.style.display = 'block';
        carregarQrJs(function () {
            if (typeof QRCode !== 'undefined') {
                pixQrJsInstance = new QRCode(qrJs, {
                    text: copiaCola,
                    width: 180,
                    height: 180,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
    }
}

function esconderPassosFatura() {
    ['pixFaturaLoading', 'metodoFaturaStep', 'pixFaturaContent', 'boletoFaturaContent', 'cartaoFaturaContent', 'cartaoFaturaAguardando', 'faturaConfirmado', 'pixFaturaErro'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
}
function mostrarPassoFatura(id) {
    esconderPassosFatura();
    var el = document.getElementById(id);
    if (el) el.style.display = 'block';
}

function abrirFatura(id) {
    const f = faturasData[id];
    if (!f) return;
    pixPagamentoId = id;
    criando = false;
    document.getElementById('pixModalFaturaTitle').textContent = f.descricao + ' · ' + f.plano;
    esconderPassosFatura();
    document.getElementById('pixFaturaLoading').style.display = 'block';
    let modal = new bootstrap.Modal(document.getElementById('pixModalFatura'));
    modal.show();

    if (f.status !== 'pendente') {
        document.getElementById('pixFaturaLoading').style.display = 'none';
        mostrarErroFatura('Esta fatura não está mais pendente.');
        return;
    }

    // Boleto já foi gerado: exibe link e linha digitável e monitora o status
    if (f.metodo === 'boleto' && f.boleto_linha) {
        document.getElementById('boletoFaturaLinha').textContent = f.boleto_linha;
        const link = document.getElementById('boletoFaturaLink');
        if (f.boleto_url) {
            link.href = f.boleto_url;
            link.classList.remove('disabled');
        } else {
            link.classList.add('disabled');
        }
        mostrarPassoFatura('boletoFaturaContent');
        iniciarPolling();
        return;
    }

    // Cartão já foi tentado: aguarda a confirmação
    if (f.metodo === 'cartao') {
        mostrarPassoFatura('cartaoFaturaAguardando');
        iniciarPolling();
        return;
    }

    // Já existe cobrança PIX gerada: apenas exibe e inicia o monitoramento
    if (f.qr || f.pix) {
        document.getElementById('pixFaturaCopia').textContent = f.pix;
        mostrarPassoFatura('pixFaturaContent');
        mostrarQrFatura(f.qr, f.pix);
        iniciarPolling();
        return;
    }

    // Sem cobrança ainda: apresenta os 4 métodos para o admin escolher
    mostrarMetodosFatura();
}

function mostrarMetodosFatura() {
    pararPolling();
    criando = false;
    document.getElementById('metodoFaturaPixBtn').style.display = METODOS_FATURA.indexOf('pix') !== -1 ? 'block' : 'none';
    document.getElementById('metodoFaturaBoletoBtn').style.display = METODOS_FATURA.indexOf('boleto') !== -1 ? 'block' : 'none';
    document.getElementById('metodoFaturaCartaoCreditoBtn').style.display = CARTAO_FATURA ? 'block' : 'none';
    document.getElementById('metodoFaturaCartaoDebitoBtn').style.display = CARTAO_FATURA ? 'block' : 'none';
    mostrarPassoFatura('metodoFaturaStep');
}

function trocarMetodoFatura() {
    mostrarMetodosFatura();
}

function escolherMetodoFatura(m) {
    if (m === 'pix') { gerarPixFaturaSel(pixPagamentoId); }
    else if (m === 'boleto') { gerarBoletoFaturaSel(pixPagamentoId); }
    else if (m === 'cartao_credito') { tipoCartao = 'credito'; mostrarCartaoFatura(pixPagamentoId); }
    else if (m === 'cartao_debito') { tipoCartao = 'debito'; mostrarCartaoFatura(pixPagamentoId); }
}

function gerarPixFaturaSel(id) {
    if (criando) return;
    criando = true;
    mostrarPassoFatura('pixFaturaLoading');
    const fd = new FormData();
    fd.append('pagamento_id', id);
    fetch('/cobranca/api/criar_pix_fatura_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            criando = false;
            if (res.erro) {
                mostrarErroFatura(res.erro);
                return;
            }
            faturasData[id].qr = res.qr_code || '';
            faturasData[id].pix = res.pix_copia_cola || '';
            faturasData[id].metodo = 'pix';
            document.getElementById('pixFaturaCopia').textContent = res.pix_copia_cola;
            mostrarPassoFatura('pixFaturaContent');
            mostrarQrFatura(res.qr_code, res.pix_copia_cola);
            iniciarPolling();
        })
        .catch(() => { criando = false; mostrarErroFatura('Erro de conexão ao gerar o PIX. Tente novamente.'); });
}

function gerarBoletoFaturaSel(id) {
    if (criando) return;
    criando = true;
    mostrarPassoFatura('pixFaturaLoading');
    const fd = new FormData();
    fd.append('pagamento_id', id);
    fetch('/cobranca/api/criar_boleto_fatura_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            criando = false;
            if (res.erro) {
                mostrarErroFatura(res.erro);
                return;
            }
            const f = faturasData[id];
            f.metodo = 'boleto';
            f.boleto_url = res.boleto_url || '';
            f.boleto_linha = res.boleto_linha_digitavel || res.boleto_codigo_barras || '';
            document.getElementById('boletoFaturaLinha').textContent = f.boleto_linha;
            const link = document.getElementById('boletoFaturaLink');
            if (f.boleto_url) {
                link.href = f.boleto_url;
                link.classList.remove('disabled');
            } else {
                link.classList.add('disabled');
            }
            mostrarPassoFatura('boletoFaturaContent');
            iniciarPolling();
        })
        .catch(() => { criando = false; mostrarErroFatura('Erro de conexão ao gerar o boleto. Tente novamente.'); });
}

function verificarCartaoFatura(id, btn) {
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verificando...';
    }
    const fd = new FormData();
    fd.append('pagamento_id', id);
    fetch('/cobranca/api/verificar_pix_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.erro) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Verificar pagamento'; }
                alert(res.erro);
                return;
            }
            if (res.status === 'pago') { window.location.reload(); return; }
            if (res.status === 'pendente') {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Verificar pagamento'; }
                alert('Pagamento ainda em processamento. Verifique novamente em instantes.');
            } else {
                window.location.reload();
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Verificar pagamento'; }
        });
}

function iniciarPolling() {
    pararPolling();
    pixPolling = setInterval(verificarPagamento, 5000);
    setTimeout(verificarPagamento, 2000);
}
function pararPolling() {
    if (pixPolling) { clearInterval(pixPolling); pixPolling = null; }
}
function verificarPagamento() {
    if (!pixPagamentoId) return;
    const fd = new FormData();
    fd.append('pagamento_id', pixPagamentoId);
    fetch('/cobranca/api/verificar_pix_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.erro) return;
            if (res.status === 'pago') {
                pararPolling();
                document.getElementById('faturaConfirmado').style.display = 'block';
                setTimeout(() => window.location.reload(), 1800);
            } else if (res.status === 'expirado' || res.status === 'cancelado' || res.status === 'vencido') {
                pararPolling();
                mostrarErroFatura('Esta cobrança foi ' + res.status + '. Gere uma nova assinatura.');
            }
        })
        .catch(() => {});
}
function mostrarErroFatura(msg) {
    pararPolling();
    esconderPassosFatura();
    const el = document.getElementById('pixFaturaErro');
    el.textContent = msg;
    el.style.display = 'block';
}

document.getElementById('btnCopiarFatura').addEventListener('click', function () {
    const texto = document.getElementById('pixFaturaCopia').textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => feedbackCopiarFatura());
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); feedbackCopiarFatura();
    }
});
function feedbackCopiarFatura() {
    const btn = document.getElementById('btnCopiarFatura');
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.remove('btn-outline-warning'); btn.classList.add('btn-success');
    setTimeout(() => { btn.innerHTML = old; btn.classList.add('btn-outline-warning'); btn.classList.remove('btn-success'); }, 1500);
}

document.getElementById('btnCopiarBoletoFatura').addEventListener('click', function () {
    const texto = document.getElementById('boletoFaturaLinha').textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => feedbackCopiarBoletoFatura());
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); feedbackCopiarBoletoFatura();
    }
});
function feedbackCopiarBoletoFatura() {
    const btn = document.getElementById('btnCopiarBoletoFatura');
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.remove('btn-outline-warning'); btn.classList.add('btn-success');
    setTimeout(() => { btn.innerHTML = old; btn.classList.add('btn-outline-warning'); btn.classList.remove('btn-success'); }, 1500);
}

document.getElementById('pixModalFatura').addEventListener('hidden.bs.modal', function () { pararPolling(); criando = false; });

/* -------- CARTÃO -------- */
function valorFaturaAtual() {
    const f = faturasData[pixPagamentoId];
    return f ? (parseFloat(f.valor_num) || 0) : 0;
}
function txtMoeda(v) {
    return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}
function parcelasAtuaisFatura() {
    const sel = document.getElementById('ccFaturaParcelas');
    if (tipoCartao === 'debito' || !sel) return 1;
    return parseInt(sel.value, 10) || 1;
}
function montarParcelasFatura() {
    const sel = document.getElementById('ccFaturaParcelas');
    if (parcelaMontada || !sel) return;
    parcelaMontada = true;
    sel.innerHTML = '';
    for (let i = 1; i <= MAX_PARCELAS; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i === 1 ? 'À vista (1x)' : i + 'x';
        sel.appendChild(opt);
    }
}
function atualizarBtnFatura() {
    const btn = document.getElementById('ccFaturaBtn');
    const p = parcelasAtuaisFatura();
    const v = valorFaturaAtual();
    if (tipoCartao === 'debito' || p <= 1) {
        btn.textContent = 'Pagar à vista — ' + txtMoeda(v);
    } else {
        btn.textContent = 'Pagar em ' + p + 'x de ' + txtMoeda(v / p);
    }
}
function aplicarTabFatura() {
    const cred = document.getElementById('tabFaturaCredito');
    const deb = document.getElementById('tabFaturaDebito');
    const parcelas = document.getElementById('ccFaturaParcelasWrap');
    if (!cred || !deb) return;
    if (tipoCartao === 'debito') {
        deb.className = 'btn btn-sm flex-fill fw-bold active';
        deb.style.background = '#fff';
        cred.className = 'btn btn-sm flex-fill fw-bold text-muted';
        cred.style.background = '';
        if (parcelas) { parcelas.style.opacity = '0.35'; parcelas.style.pointerEvents = 'none'; }
    } else {
        cred.className = 'btn btn-sm flex-fill fw-bold active';
        cred.style.background = '#fff';
        deb.className = 'btn btn-sm flex-fill fw-bold text-muted';
        deb.style.background = '';
        if (parcelas) { parcelas.style.opacity = '1'; parcelas.style.pointerEvents = 'auto'; }
    }
    atualizarBtnFatura();
}
function mostrarCartaoFatura(id) {
    pixPagamentoId = id;
    pararPolling();
    criando = false;
    aplicarTabFatura();
    montarParcelasFatura();
    atualizarBtnFatura();
    const msg = document.getElementById('ccFaturaMsg');
    if (!CPF_CLIENTE) {
        msg.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Para pagar com cartão ou boleto é preciso ter CPF/CNPJ no cadastro. <a href="/cobranca/admin/perfil.php" class="fw-bold text-decoration-underline">Editar perfil</a>';
        msg.style.color = '#b45309';
    } else {
        msg.textContent = '';
    }
    mostrarPassoFatura('cartaoFaturaContent');
}

function detectarBandeira(num) {
    num = num.replace(/\s+/g, '');
    if (!/^\d{6,}$/.test(num)) return null;
    if (/^4/.test(num)) return { b: 'visa', label: 'Visa' };
    if (/^3[47]/.test(num)) return { b: 'amex', label: 'Amex' };
    if (/(^5[1-5]|^2[2-7])/.test(num)) return { b: 'master', label: 'Mastercard' };
    if (/^(4011|4312|4389|4514|4573|4576|5041|5066|5090|6277|6362|6363|6504|6505|6507|6509|6516|6550)/.test(num)) return { b: 'elo', label: 'Elo' };
    if (/^(6062|3841)/.test(num)) return { b: 'hipercard', label: 'Hipercard' };
    return null;
}
function metodoDebitoFatura(numero) {
    const b = detectarBandeira(numero.replace(/\s+/g, ''));
    if (!b) return '';
    switch (b.b) {
        case 'visa': return 'debvisa';
        case 'master': return 'debmaster';
        case 'elo': return 'debelo';
        case 'hipercard': return 'debhipercard';
        default: return '';
    }
}
function formatarNumeroFatura(val) {
    const d = val.replace(/\D+/g, '');
    const b = detectarBandeira(d);
    if (b && b.b === 'amex') return d.slice(0, 15).replace(/(\d{4})(\d{6})(\d+)/, '$1 $2 $3');
    return d.slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
}

function mensagensErro(err) {
    const msgs = [];
    function add(m) { if (m && typeof m === 'string' && msgs.indexOf(m) === -1) msgs.push(m); }
    if (Array.isArray(err)) err.forEach(function (i) { add(i && (i.message || i.description)); });
    else if (err && typeof err === 'object') { add(err.message); add(err.error); if (Array.isArray(err.cause)) err.cause.forEach(function (c) { add(c && c.description); }); }
    else add(err);
    return msgs.join(' | ') || 'Não foi possível validar o cartão. Verifique os dados e tente novamente.';
}
function msgFatura(texto, tipo) {
    const m = document.getElementById('ccFaturaMsg');
    m.className = 'small mb-2';
    if (tipo === 'ok') { m.style.color = '#0f7b5c'; }
    else if (tipo === 'warn') { m.style.color = '#b45309'; }
    else { m.style.color = '#dc3545'; }
    m.textContent = texto || '';
}
function tokenErroFatura(err) {
    const btn = document.getElementById('ccFaturaBtn');
    btn.disabled = false;
    atualizarBtnFatura();
    msgFatura(mensagensErro(err), 'erro');
}
function tokenSucessoFatura(resp) {
    if (!resp || typeof resp.id !== 'string' || resp.id === '') { tokenErroFatura(resp); return; }
    const fd = new FormData();
    fd.append('pagamento_id', pixPagamentoId);
    fd.append('card_token', resp.id);
    fd.append('installments', parcelasAtuaisFatura());
    fd.append('tipo', tipoCartao);
    fd.append('method', tipoCartao === 'debito' ? metodoDebitoFatura(document.getElementById('ccFaturaNumero').value) : '');
    fetch('/cobranca/api/cartao_fatura_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.sucesso && (d.status === 'pago' || d.mp_status === 'approved')) {
                pararPolling();
                mostrarPassoFatura('faturaConfirmado');
                setTimeout(() => window.location.reload(), 1800);
            } else if (d.sucesso) {
                faturasData[pixPagamentoId].metodo = 'cartao';
                msgFatura('Pagamento em processamento. Acompanhe a confirmação abaixo.', 'warn');
                mostrarPassoFatura('cartaoFaturaAguardando');
                iniciarPolling();
            } else {
                const btn = document.getElementById('ccFaturaBtn');
                btn.disabled = false;
                atualizarBtnFatura();
                msgFatura(d.erro || 'Não foi possível processar o pagamento.', 'erro');
            }
        })
        .catch(function () {
            const btn = document.getElementById('ccFaturaBtn');
            btn.disabled = false;
            atualizarBtnFatura();
            msgFatura('Erro de conexão. Tente novamente.', 'erro');
        });
}

function iniciarCartaoFatura() {
    document.getElementById('tabFaturaCredito').addEventListener('click', function () {
        tipoCartao = 'credito';
        aplicarTabFatura();
    });
    document.getElementById('tabFaturaDebito').addEventListener('click', function () {
        tipoCartao = 'debito';
        aplicarTabFatura();
    });
    document.getElementById('ccFaturaParcelas').addEventListener('change', atualizarBtnFatura);
    document.getElementById('ccFaturaNumero').addEventListener('input', function (e) { e.target.value = formatarNumeroFatura(e.target.value); });
    document.getElementById('ccFaturaValidade').addEventListener('input', function (e) {
        let v = e.target.value.replace(/\D+/g, '').slice(0, 4);
        if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
        e.target.value = v;
    });

    document.getElementById('ccFaturaForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const numero = document.getElementById('ccFaturaNumero').value.replace(/\s+/g, '');
        const nome = document.getElementById('ccFaturaNome').value.trim();
        const validade = document.getElementById('ccFaturaValidade').value.trim();
        const cvv = document.getElementById('ccFaturaCvv').value.trim();
        if (!CPF_CLIENTE) {
            msgFatura('Cadastre um CPF ou CNPJ no seu perfil para pagar com cartão.', 'erro');
            return;
        }
        if (!/^\d{13,16}$/.test(numero)) { msgFatura('Número de cartão inválido.', 'erro'); return; }
        const m = validade.match(/^(\d{2})\s*\/\s*(\d{2})$/);
        if (!m) { msgFatura('Validade inválida. Use o formato MM/AA.', 'erro'); return; }
        const mes = parseInt(m[1], 10), ano = 2000 + parseInt(m[2], 10);
        if (mes < 1 || mes > 12) { msgFatura('Mês da validade inválido.', 'erro'); return; }
        if (cvv.length < 3) { msgFatura('CVV inválido.', 'erro'); return; }
        if (!nome) { msgFatura('Informe o nome impresso no cartão.', 'erro'); return; }
        const hoje = new Date();
        if (ano < hoje.getFullYear() || (ano === hoje.getFullYear() && mes < hoje.getMonth() + 1)) {
            msgFatura('Este cartão está vencido.', 'erro');
            return;
        }

        const btn = document.getElementById('ccFaturaBtn');
        btn.disabled = true;
        btn.textContent = 'Processando...';
        msgFatura('', 'ok');

        if (!mp) { tokenErroFatura('SDK de pagamento não carregado. Recarregue a página.'); return; }

        const payload = {
            cardNumber: numero,
            cardholderName: nome,
            cardExpirationMonth: (mes < 10 ? '0' : '') + mes,
            cardExpirationYear: String(ano),
            securityCode: cvv,
            installments: parcelasAtuaisFatura(),
            identificationType: CPF_CLIENTE.length === 14 ? 'CNPJ' : 'CPF',
            identificationNumber: CPF_CLIENTE,
            locale: 'pt-BR'
        };

        try {
            const prom = mp.createCardToken(payload);
            if (prom && typeof prom.then === 'function') {
                prom.then(tokenSucessoFatura).catch(function (err) { tokenErroFatura(err); });
            } else {
                mp.createCardToken(payload, function (resp, err) {
                    if (err && (err.length || err.message)) tokenErroFatura(err);
                    else tokenSucessoFatura(resp);
                });
            }
        } catch (ex) {
            tokenErroFatura(ex);
        }
    });

    atualizarBtnFatura();
}

if (CARTAO_FATURA && PUBLIC_KEY) {
    try { mp = new MercadoPago(PUBLIC_KEY, { locale: 'pt-BR' }); } catch (e) { mp = null; }
    iniciarCartaoFatura();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>