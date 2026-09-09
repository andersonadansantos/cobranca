<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/inter_pix.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];

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
        'qr'          => $f['qr_code'] ?: '',
        'pix'         => $f['pix_copia_cola'] ?: '',
        'status'      => $f['status'],
        'metodo'      => $f['metodo'] ?: 'pix',
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
                                            <i class="fas fa-qrcode me-1"></i>Pagar com PIX
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
                <h5 class="modal-title fw-bold" id="pixModalFaturaTitle">Pagamento PIX</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4" id="pixModalFaturaBody">
                <div id="pixFaturaLoading" class="text-center py-4">
                    <div class="spinner-border" style="color:#cd7f32;"></div>
                    <p class="text-muted mt-3 mb-0">Gerando cobrança PIX...</p>
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

<script>
const faturasData = <?= json_encode($faturasJs) ?>;
let pixPolling = null;
let pixPagamentoId = null;
let pixQrJsInstance = null;

function carregarQrJs(callback) {
    if (typeof QRCode !== 'undefined') { callback(); return; }
    let s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
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

function abrirFatura(id) {
    const f = faturasData[id];
    if (!f) return;
    pixPagamentoId = id;
    document.getElementById('pixModalFaturaTitle').textContent = f.descricao + ' · ' + f.plano;
    document.getElementById('pixFaturaLoading').style.display = 'block';
    document.getElementById('pixFaturaContent').style.display = 'none';
    document.getElementById('boletoFaturaContent').style.display = 'none';
    document.getElementById('faturaConfirmado').style.display = 'none';
    document.getElementById('pixFaturaErro').style.display = 'none';
    let modal = new bootstrap.Modal(document.getElementById('pixModalFatura'));
    modal.show();

    if (f.metodo === 'boleto') {
        // Boleto já foi gerado: exibe link e linha digitável e monitora o status
        document.getElementById('boletoFaturaLinha').textContent = f.boleto_linha;
        const link = document.getElementById('boletoFaturaLink');
        if (f.boleto_url) {
            link.href = f.boleto_url;
            link.classList.remove('disabled');
        } else {
            link.classList.add('disabled');
        }
        document.getElementById('pixFaturaLoading').style.display = 'none';
        document.getElementById('boletoFaturaContent').style.display = 'block';
        iniciarPolling();
        return;
    }

    if (f.qr || f.pix) {
        // Já existe cobrança gerada: apenas exibe e inicia o monitoramento
        document.getElementById('pixFaturaCopia').textContent = f.pix;
        document.getElementById('pixFaturaLoading').style.display = 'none';
        document.getElementById('pixFaturaContent').style.display = 'block';
        mostrarQrFatura(f.qr, f.pix);
        iniciarPolling();
        return;
    }

    // Sem cobrança ainda: gera o PIX para esta fatura
    const fd = new FormData();
    fd.append('pagamento_id', id);
    fetch('/cobranca/api/criar_pix_fatura_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.erro) {
                mostrarErroFatura(res.erro);
                return;
            }
            faturasData[id].qr = res.qr_code || '';
            faturasData[id].pix = res.pix_copia_cola || '';
            document.getElementById('pixFaturaCopia').textContent = res.pix_copia_cola;
            document.getElementById('pixFaturaLoading').style.display = 'none';
            document.getElementById('pixFaturaContent').style.display = 'block';
            mostrarQrFatura(res.qr_code, res.pix_copia_cola);
            iniciarPolling();
        })
        .catch(() => mostrarErroFatura('Erro de conexão ao gerar o PIX. Tente novamente.'));
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
    document.getElementById('pixFaturaLoading').style.display = 'none';
    document.getElementById('pixFaturaContent').style.display = 'none';
    document.getElementById('boletoFaturaContent').style.display = 'none';
    document.getElementById('faturaConfirmado').style.display = 'none';
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

document.getElementById('pixModalFatura').addEventListener('hidden.bs.modal', pararPolling);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>