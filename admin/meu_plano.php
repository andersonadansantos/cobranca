<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$adminId = $_SESSION['admin_id'] ?? 0;

$stmtPlano = $pdo->prepare("
    SELECT p.*, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$stmtPlano->execute([$adminId]);
$meuPlano = $stmtPlano->fetch();

$planos = $pdo->query("SELECT * FROM planos WHERE ativo = 1 AND COALESCE(slug,'') <> 'diamante' ORDER BY ordem ASC")->fetchAll();

$pageTitle = 'Meu Plano';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';

// Paleta por nível (para estilo dos cards)
function corPlano($cor) {
    $mapa = [
        'bronze' => ['badge' => '#cd7f32', 'grad' => 'linear-gradient(135deg, #8a5a2b, #cd7f32)'],
        'secondary' => ['badge' => '#6c757d', 'grad' => 'linear-gradient(135deg, #495057, #adb5bd)'],
        'warning' => ['badge' => '#f59e0b', 'grad' => 'linear-gradient(135deg, #b45309, #f59e0b)'],
        'primary' => ['badge' => '#0d6efd', 'grad' => 'linear-gradient(135deg, #0a4dc4, #3b82f6)'],
        'success' => ['badge' => '#16a34a', 'grad' => 'linear-gradient(135deg, #0f7b5c, #22c55e)'],
        'info' => ['badge' => '#22d3ee', 'grad' => 'linear-gradient(135deg, #0891b2, #22d3ee)'],
        'danger' => ['badge' => '#dc2626', 'grad' => 'linear-gradient(135deg, #b91c1c, #ef4444)'],
        'dark' => ['badge' => '#1f2937', 'grad' => 'linear-gradient(135deg, #111827, #374151)'],
    ];
    return $mapa[$cor] ?? ['badge' => '#0f7b5c', 'grad' => 'linear-gradient(135deg, #0f7b5c, #22c55e)'];
}
?>
<style>
.btn-contratar {
    background: linear-gradient(135deg, #c7852c, #f59e0b);
    color: #fff;
    font-weight: 700;
    border: none;
    padding: .75rem 1.25rem;
    border-radius: .65rem;
    transition: all .25s ease;
    box-shadow: 0 6px 16px rgba(245,158,11,.35);
}
.btn-contratar:hover { transform: translateY(-2px); color:#fff; box-shadow: 0 10px 22px rgba(245,158,11,.45); }
.btn-contratar:disabled { opacity:.55; box-shadow:none; transform:none; }
.current-plan-btn { background:#e8f5ee; color:#0f7b5c; border:1px solid #0f7b5c; font-weight:700; }
.current-plan-btn:hover { color:#0f7b5c; }
.floating-cta {
    position: sticky; bottom: 1rem; z-index: 10; text-align: center;
}
.plan-card { border-radius: 1.1rem; overflow: hidden; transition: transform .25s ease, box-shadow .25s ease; border:1px solid #efe7e2; background:#fff; }
.plan-card:hover { transform: translateY(-6px); box-shadow: 0 18px 36px rgba(120,80,40,.12); }
.plan-card .plan-head { color:#fff; text-align:center; padding: 1.4rem 1rem .2rem; }
.plan-card.featured { box-shadow: 0 14px 30px rgba(205,127,50,.30); border:2px solid #cd7f32; }
.plan-card.featured .plan-head { padding:1.9rem 1rem .3rem; }
.plan-ico { font-size:1.7rem; }
.plan-name { font-size:.85rem; text-transform:uppercase; letter-spacing:.14em; font-weight:700; margin:.3rem 0 .1rem; }
.plan-tagline { font-size:.78rem; opacity:.9; }
.plan-preco-num { font-size:2.6rem; font-weight:800; line-height:1.05; }
.plan-preco-period { font-size:.85rem; opacity:.85; }
.plan-body { padding:1.2rem 1.4rem 1.5rem; }
.plan-feat { list-style:none; padding:0; margin:0 0 1.2rem; }
.plan-feat li { display:flex; align-items:center; gap:.55rem; font-size:.9rem; color:#4b3f35; padding:.32rem 0; }
.plan-feat li i { color:#16a34a; }
.plan-feat li.plan-gw { align-items:flex-start; }
.plan-feat li.plan-gw .plan-gw-body { display:flex; flex-direction:column; align-items:flex-start; gap:.15rem; }
.plan-feat li.plan-gw .plan-gw-logos { display:flex; justify-content:flex-start; align-items:center; gap:.4rem; }
.plan-feat li.plan-gw .plan-gw-logo { height:14px; max-width:64px; object-fit:contain; opacity:.85; }
.plan-feat li.plan-gw .plan-gw-text { font-size:.75rem; color:#8b7f72; }
.current-tag { position:absolute; top:.9rem; right:.9rem; background:#fff; color:#0f7b5c; font-size:.68rem; font-weight:700; padding:.2rem .6rem; border-radius:999px; box-shadow:0 2px 6px rgba(0,0,0,.1); }

#pixModal .qr-box {
    width: 210px; height: 210px; margin:0 auto; border:1px solid #eee; border-radius:.75rem;
    display:flex; align-items:center; justify-content:center; background:#fff; position:relative;
}
#pixModal .pix-copia {
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
            <h5><i class="fas fa-crown me-1"></i> Meu Plano</h5>
        </div>
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

    <div class="content-area" style="background:#fcf9f8; min-height:100vh; border-radius:1.1rem 1.1rem 0 0;">
        <?php if (function_exists('adminEstaAtivo') && !adminEstaAtivo()): ?>
        <div class="alert alert-warning d-flex align-items-center mx-md-4 mt-3 mb-0" role="alert" style="border-left:4px solid #ffc107;">
            <i class="fas fa-user-slash me-3" style="font-size:1.2rem;"></i>
            <div>
                <strong>Sua conta está desativada.</strong>
                <br><small>O acesso ao painel foi restrito pelo administrador. Contate o suporte para reativar o acesso.</small>
            </div>
        </div>
        <?php elseif (function_exists('adminPlanoExpirado') && adminPlanoExpirado()): ?>
        <div class="alert alert-danger d-flex align-items-center mx-md-4 mt-3 mb-0" role="alert" style="border-left:4px solid #dc3545;">
            <i class="fas fa-exclamation-triangle me-3" style="font-size:1.2rem;"></i>
            <div>
                <strong>Seu plano está vencido.</strong>
                <br><small>O acesso ao painel foi restrito. Escolha um plano abaixo e efetue o pagamento para reativar o acesso.</small>
            </div>
        </div>
        <?php endif; ?>
        <div class="text-center pt-4 pb-2">
            <div class="d-inline-flex align-items-center gap-2 text-uppercase fw-bold mb-2" style="letter-spacing:.18em; font-size:.75rem; color:#cd7f32;">
                <span style="width:30px;height:2px;background:#cd7f32;display:inline-block;"></span>
                Planos
                <span style="width:30px;height:2px;background:#cd7f32;display:inline-block;"></span>
            </div>
            <h1 class="fw-bold mb-2" style="color:#2b2118;">Assinatura <span style="background:linear-gradient(135deg,#cd7f32,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Elite</span></h1>
            <p class="text-muted mb-0">Escolha o nível ideal para impulsionar o seu negócio</p>
        </div>

        <div class="row justify-content-center g-4 px-md-4 mt-2">
            <?php if (empty($planos)): ?>
                <div class="col-12 text-center text-muted py-5">Nenhum plano disponível no momento. Entre em contato com o suporte.</div>
            <?php else:
                $total = count($planos);
                $i = 0;
                foreach ($planos as $p): $i++;
                    $isCurrent = ($meuPlano && $p['id'] == $meuPlano['id']);
                    // Bronze é o plano em destaque ("Mais Popular")
                    $feat = ($p['slug'] ?? '') === 'bronze';
                    $c = corPlano($p['cor'] ?? '');
                    $benef = array_filter(array_map('trim', explode("\n", $p['beneficios'] ?? '')));
            ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="plan-card position-relative h-100 <?= $feat ? 'featured' : '' ?> <?= $isCurrent ? 'border-success' : '' ?>">
                        <?php if ($isCurrent): ?>
                            <span class="current-tag"><i class="fas fa-check me-1"></i>Plano Atual</span>
                        <?php elseif ($feat): ?>
                            <span class="current-tag" style="background:#f59e0b;color:#fff;">Mais Popular</span>
                        <?php endif; ?>
                        <div class="plan-head" style="background:<?= $c['grad'] ?>;">
                            <i class="fas <?= htmlspecialchars($p['icon'] ?: 'fa-circle') ?> plan-ico"></i>
                            <div class="plan-name"><?= htmlspecialchars($p['nome']) ?></div>
                            <div class="plan-tagline"><?= htmlspecialchars($p['descricao'] ?? '') ?></div>
                            <div class="mt-3">
                                <span class="plan-preco-num">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                                <span class="plan-preco-period">/mês</span>
                            </div>
                        </div>
                        <div class="plan-body">
                            <ul class="plan-feat">
                                <?php
                                $gwLogos = [
                                    'mercado pago' => '/cobranca/assets/img/mercado-pago-logo.png',
                                    'banco inter'  => '/cobranca/assets/img/banco-inter-logo-0-1.png',
                                    'inter'        => '/cobranca/assets/img/banco-inter-logo-0-1.png',
                                    'asaas'        => '/cobranca/assets/img/asaas-logo.svg',
                                    'pix'          => '/cobranca/assets/img/pix-logo.svg',
                                    'pix manual'   => '/cobranca/assets/img/pix-logo.svg',
                                ];
                                if ($benef):
                                    foreach ($benef as $b):
                                        if (stripos($b, 'Gateways:') === 0):
                                            $lista = trim(substr($b, strpos($b, ':') + 1));
                                            $nomes = preg_split('/\s*[·,]\s*/u', $lista);
                                ?>
                                    <li class="plan-gw">
                                        <i class="fas fa-check-circle"></i>
                                        <span class="plan-gw-body">
                                            <span class="plan-gw-title">Gateways</span>
                                            <span class="plan-gw-logos">
                                                <?php foreach ($nomes as $n): $k = strtolower(trim($n)); ?>
                                                    <?php if (isset($gwLogos[$k])): ?>
                                                        <img src="<?= $gwLogos[$k] ?>" alt="<?= htmlspecialchars(trim($n)) ?>" title="<?= htmlspecialchars(trim($n)) ?>" class="plan-gw-logo">
                                                    <?php else: ?>
                                                        <span class="plan-gw-text"><?= htmlspecialchars(trim($n)) ?></span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </span>
                                        </span>
                                    </li>
                                <?php else: ?>
                                    <li><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($b) ?></span></li>
                                <?php endif; ?>
                            <?php endforeach;
                                else: ?>
                                    <li><i class="fas fa-check-circle"></i><span>Recursos inclusos</span></li>
                                <?php endif; ?>
                            </ul>
                            <?php if ($isCurrent): ?>
                                <button class="btn current-plan-btn w-100" disabled><i class="fas fa-check me-1"></i>Plano Atual</button>
                            <?php else: ?>
                                <button class="btn btn-contratar w-100" onclick="abrirPagamento(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nome'])) ?>', <?= $p['preco'] ?>)">
                                    <i class="fas fa-shopping-cart me-1"></i> Assinar Agora
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <?php if ($meuPlano): ?>
            <div class="floating-cta">
                <div class="d-inline-flex align-items-center gap-2 px-4 py-2 rounded-pill shadow" style="background:#fff;border:1px solid #efe7e2;">
                    <i class="fas <?= htmlspecialchars($meuPlano['icon'] ?: 'fa-circle') ?>" style="color:<?= corPlano($meuPlano['cor'] ?? '')['badge'] ?>;"></i>
                    <small class="text-muted">Seu plano atual: <strong><?= htmlspecialchars($meuPlano['nome']) ?></strong>
                        <?php if ($meuPlano['data_inicio']): ?> · desde <?= date('d/m/Y', strtotime($meuPlano['data_inicio'])) ?><?php endif; ?>
                        <?php if ($meuPlano['data_fim']): ?> · vence em <?= date('d/m/Y', strtotime($meuPlano['data_fim'])) ?><?php endif; ?>
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal PIX -->
<div class="modal fade" id="pixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:1rem;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="pixModalTitle">Pagamento PIX</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4" id="pixModalBody">
                <div id="pixLoading" class="text-center py-4">
                    <div class="spinner-border" style="color:#cd7f32;"></div>
                    <p class="text-muted mt-3 mb-0">Gerando cobrança PIX...</p>
                </div>
                <div id="pixContent" style="display:none;">
                    <div class="text-center mb-3">
                        <div class="d-inline-flex align-items-center gap-2">
                            <span class="pulse-dot"></span>
                            <span class="text-muted small">Aguardando pagamento</span>
                        </div>
                    </div>
                    <div class="qr-box" id="pixQrBox">
                        <img id="pixQr" src="" alt="QR Code PIX" style="width:190px;height:190px;display:none;">
                        <div id="pixQrJs" style="width:190px;height:190px;"></div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">Escaneie com o app do seu banco</small>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold">PIX Copia e Cola</label>
                        <div class="pix-copia d-flex align-items-center justify-content-between gap-2">
                            <span id="pixCopia" style="flex:1;"></span>
                            <button class="btn btn-sm btn-outline-warning" id="btnCopiar" title="Copiar">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-success mt-3 py-2 mb-0" id="pixConfirmado" style="display:none;">
                        <i class="fas fa-check-circle me-1"></i> <strong>Pagamento confirmado!</strong> Seu plano foi ativado. Atualizando...
                    </div>
                </div>
                <div id="pixErro" class="alert alert-danger py-2 mb-0" style="display:none;"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
let pixPolling = null;
let pixPagamentoId = null;
let pixPlanoId = null;
let pixQrJsInstance = null;

function carregarQrJs(callback) {
    if (typeof QRCode !== 'undefined') { callback(); return; }
    let s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    s.onload = callback;
    s.onerror = callback;
    document.head.appendChild(s);
}

function mostrarQr(res) {
    let qrImg = document.getElementById('pixQr');
    let qrJs = document.getElementById('pixQrJs');
    if (res.qr_code) {
        // API retornou imagem base64
        let b64 = String(res.qr_code).replace(/\s/g, '');
        if (b64.indexOf('data:image') === 0) {
            qrImg.src = b64;
        } else {
            qrImg.src = 'data:image/png;base64,' + b64;
        }
        qrImg.style.display = 'block';
        qrJs.style.display = 'none';
    } else if (res.pix_copia_cola) {
        // Inter não retorna imagem -> gera QR do copia e cola
        qrImg.style.display = 'none';
        qrJs.innerHTML = '';
        carregarQrJs(function () {
            if (typeof QRCode !== 'undefined') {
                pixQrJsInstance = new QRCode(qrJs, {
                    text: res.pix_copia_cola,
                    width: 190,
                    height: 190,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
    }
}

function abrirPagamento(planoId, nome, preco) {
    pixPlanoId = planoId;
    document.getElementById('pixModalTitle').textContent = 'Pagamento - ' + nome;
    document.getElementById('pixLoading').style.display = 'block';
    document.getElementById('pixContent').style.display = 'none';
    document.getElementById('pixErro').style.display = 'none';
    document.getElementById('pixConfirmado').style.display = 'none';
    let modal = new bootstrap.Modal(document.getElementById('pixModal'));
    modal.show();

    const fd = new FormData();
    fd.append('plano_id', planoId);
    fetch('/cobranca/api/criar_pix_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.erro) {
                mostrarPixErro(res.erro);
                return;
            }
            pixPagamentoId = res.pagamento_id;
            document.getElementById('pixCopia').textContent = res.pix_copia_cola;
            document.getElementById('pixLoading').style.display = 'none';
            document.getElementById('pixContent').style.display = 'block';
            mostrarQr(res);
            iniciarPolling();
        })
        .catch(() => mostrarPixErro('Erro de conexão ao gerar o PIX. Tente novamente.'));
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
                document.getElementById('pixConfirmado').style.display = 'block';
                setTimeout(() => window.location.reload(), 1800);
            }
        })
        .catch(() => {});
}
function mostrarPixErro(msg) {
    pararPolling();
    document.getElementById('pixLoading').style.display = 'none';
    document.getElementById('pixContent').style.display = 'none';
    const el = document.getElementById('pixErro');
    el.textContent = msg;
    el.style.display = 'block';
}

document.getElementById('btnCopiar').addEventListener('click', function () {
    const texto = document.getElementById('pixCopia').textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => feedbackCopiar());
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); feedbackCopiar();
    }
});
function feedbackCopiar() {
    const btn = document.getElementById('btnCopiar');
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.remove('btn-outline-warning'); btn.classList.add('btn-success');
    setTimeout(() => { btn.innerHTML = old; btn.classList.add('btn-outline-warning'); btn.classList.remove('btn-success'); }, 1500);
}

document.getElementById('pixModal').addEventListener('hidden.bs.modal', pararPolling);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
