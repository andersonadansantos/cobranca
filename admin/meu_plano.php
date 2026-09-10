<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/inter_pix.php';
require_once __DIR__ . '/../config/mercadopago.php';

$pdo = getConnection();
$adminId = $_SESSION['admin_id'] ?? 0;

// === Métodos de pagamento disponíveis para planos (Brasil = PIX + boleto + cartão) ===
$metodosAdmin = ['pix', 'boleto', 'cartao'];
if (getConfig('super_plano_cartao', '1') !== '1') {
    $metodosAdmin = array_values(array_diff($metodosAdmin, ['cartao']));
}
if (getConfig('super_plano_pix_boleto', '1') !== '1') {
    $metodosAdmin = array_values(array_diff($metodosAdmin, ['pix', 'boleto']));
}
$pixOk    = in_array('pix', $metodosAdmin, true);
$boletoOk = in_array('boleto', $metodosAdmin, true);
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

$stmtPlano = $pdo->prepare("
    SELECT p.*, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$stmtPlano->execute([$adminId]);
$meuPlano = $stmtPlano->fetch();

$diasRestantes = null;
if ($meuPlano && !empty($meuPlano['data_fim'])) {
    $diasRestantes = (int)floor((strtotime($meuPlano['data_fim']) - strtotime(date('Y-m-d'))) / 86400);
}

// O plano DEMO existe somente para testes pelo site (demo.php) e não é listado aqui.
$planos = $pdo->query("SELECT * FROM planos WHERE ativo = 1 AND COALESCE(slug,'') NOT IN ('diamante', 'demo') ORDER BY ordem ASC")->fetchAll();

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

// Converte hex (#rrggbb) em rgba para brilhos/sombras harmônicas com a paleta de cada plano
function hexToRgba($hex, $alpha) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
    if (strlen($hex) !== 6) { $hex = '0f7b5c'; }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r,$g,$b,$alpha)";
}
?>
<style>
.btn-contratar {
    background: var(--plan-grad, linear-gradient(135deg,#c7852c,#f59e0b));
    color: #fff;
    font-weight: 700;
    border: none;
    padding: .8rem 1.25rem;
    border-radius: .7rem;
    transition: all .22s ease;
    box-shadow: 0 6px 16px var(--plan-glow, rgba(245,158,11,.35));
}
.btn-contratar:hover { transform: translateY(-2px); color:#fff; box-shadow: 0 10px 24px var(--plan-glow, rgba(245,158,11,.45)); }
.btn-contratar:disabled { opacity:.55; box-shadow:none; transform:none; }
.current-plan-btn { background:transparent; color:var(--plan-accent,#0f7b5c); border:2px solid var(--plan-accent,#0f7b5c); font-weight:700; border-radius:.7rem; transition:all .2s ease; }
.current-plan-btn:hover { color:var(--plan-accent,#0f7b5c); background:var(--plan-glow,rgba(15,123,92,.1)); }
.floating-cta {
    position: sticky; bottom: 1rem; z-index: 10; text-align: center;
}
.plan-card {
    --plan-accent:#cd7f32;
    --plan-grad:linear-gradient(135deg,#c7852c,#f59e0b);
    --plan-glow:rgba(205,127,50,.30);
    border-radius: 1.15rem; overflow: hidden; transition: transform .25s ease, box-shadow .25s ease;
    border:1px solid #efe7e2; background:#fff; position:relative;
}
.plan-card:hover { transform: translateY(-6px); box-shadow: 0 18px 36px rgba(120,80,40,.12); }
.plan-card.featured, .plan-card.plan-now { border:2px solid var(--plan-accent,#cd7f32); box-shadow: 0 16px 34px var(--plan-glow,rgba(205,127,50,.30)); }
.plan-card.featured .plan-head, .plan-card.plan-now .plan-head { padding:2.2rem 1.2rem 1.2rem; }
.plan-card .plan-head {
    position:relative; color:#fff; text-align:center; padding:1.9rem 1.2rem 1.1rem;
    background:var(--plan-grad, linear-gradient(135deg,#0f7b5c,#22c55e));
    box-shadow: inset 0 -22px 36px rgba(0,0,0,.14);
}
.plan-ico-wrap {
    width:54px; height:54px; margin:0 auto .6rem; border-radius:.95rem;
    display:flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28);
    box-shadow: 0 6px 14px rgba(0,0,0,.15);
}
.plan-ico { font-size:1.35rem; }
.plan-name { font-size:.92rem; text-transform:uppercase; letter-spacing:.14em; font-weight:800; margin:0 0 .15rem; text-shadow:0 1px 3px rgba(0,0,0,.15); }
.plan-tagline { font-size:.8rem; opacity:.92; }
.plan-por { font-size:.66rem; text-transform:uppercase; letter-spacing:.16em; opacity:.85; margin-top:.65rem; }
.plan-price { display:flex; align-items:baseline; justify-content:center; gap:.3rem; }
.plan-preco-num { font-size:2.5rem; font-weight:800; line-height:1.05; }
.plan-preco-period { font-size:.85rem; opacity:.9; }
.plan-body { padding:1.3rem 1.4rem 1.5rem; }
.plan-feat { list-style:none; padding:0; margin:0 0 1.2rem; }
.plan-feat li { display:flex; align-items:center; gap:.55rem; font-size:.9rem; color:#4b3f35; padding:.34rem 0; }
.plan-feat li i { color:var(--plan-accent,#16a34a); }
.plan-feat li.plan-gw { align-items:flex-start; }
.plan-feat li.plan-gw .plan-gw-body { display:flex; flex-direction:column; align-items:flex-start; gap:.15rem; }
.plan-feat li.plan-gw .plan-gw-title { text-transform:uppercase; font-size:.68rem; letter-spacing:.06em; color:#8b7f72; font-weight:700; }
.plan-feat li.plan-gw .plan-gw-logos { display:flex; justify-content:flex-start; align-items:center; gap:.45rem; }
.plan-feat li.plan-gw .plan-gw-logo { height:20px; max-width:84px; object-fit:contain; opacity:.92; filter:drop-shadow(0 1px 1px rgba(0,0,0,.08)); }
.plan-feat li.plan-gw .plan-gw-text { font-size:.75rem; color:#8b7f72; }
.current-tag {
    position:absolute; top:.9rem; right:.9rem; z-index:2;
    background:#fff; color:var(--plan-accent,#0f7b5c); font-size:.68rem; font-weight:800;
    padding:.28rem .7rem; border-radius:999px; letter-spacing:.04em;
    box-shadow:0 4px 12px rgba(0,0,0,.16);
}
.current-tag.popular { background:var(--plan-grad, linear-gradient(135deg,#b45309,#f59e0b)); color:#fff; border:1px solid rgba(255,255,255,.35); }

.periodo-pill {
    background:#faf6f2; border:1px solid #eadfd6; color:#4b3f35; font-size:.78rem; font-weight:600;
    padding:.5rem .3rem; border-radius:.6rem; transition:all .18s ease;
}
.periodo-pill:hover { border-color:#cd7f32; color:#a04a00; }
.periodo-pill.active {
    background:linear-gradient(135deg,#c7852c,#f59e0b); border-color:#c7852c; color:#fff;
    box-shadow:0 4px 10px rgba(245,158,11,.30);
}
.periodo-pill .periodo-preco { font-size:.68rem; font-weight:700; }
.periodo-pill.active .periodo-preco { opacity:.95; }

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
        <?php elseif ($diasRestantes !== null && $diasRestantes <= 7 && $diasRestantes >= 0): ?>
        <div class="alert alert-warning d-flex align-items-center mx-md-4 mt-3 mb-0" role="alert" style="border-left:4px solid #ffc107;">
            <i class="fas fa-bell me-3" style="font-size:1.2rem;"></i>
            <div>
                <strong>Seu plano encerra em <?= $diasRestantes === 0 ? 'hoje' : $diasRestantes . ' dia(s)' ?>.</strong>
                <br><small>Renove agora para não perder o acesso. Ganhe 10% de desconto em planos Trimestral, Semestral ou Anual.</small>
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
                $gwLogos = [
                    'mercado pago' => '/cobranca/assets/img/mercado-pago-logo.png',
                    'banco inter'  => '/cobranca/assets/img/banco-inter-logo-0-1.png',
                    'inter'        => '/cobranca/assets/img/banco-inter-logo-0-1.png',
                    'asaas'        => '/cobranca/assets/img/asaas-logo.svg',
                    'pix'          => '/cobranca/assets/img/pix-logo.svg',
                    'pix manual'   => '/cobranca/assets/img/pix-logo.svg',
                ];
                foreach ($planos as $p):
                    $isCurrent = ($meuPlano && $p['id'] == $meuPlano['id']);
                    // Bronze é o plano em destaque ("Mais Popular")
                    $feat = ($p['slug'] ?? '') === 'bronze';
                    $c = corPlano($p['cor'] ?? '');
                    $benef = array_filter(array_map('trim', explode("\n", $p['beneficios'] ?? '')));
                    $precoMensal = (float)$p['preco'];
                    $periodos = [
                        1  => ['Mensal',      'fa-calendar-day', $precoMensal],
                        3  => ['Trimestral',  'fa-calendar-alt', round($precoMensal * 3 * 0.90, 2)],
                        6  => ['Semestral',   'fa-calendar-week', round($precoMensal * 6 * 0.90, 2)],
                        12 => ['Anual',       'fa-calendar-check', round($precoMensal * 12 * 0.90, 2)],
                    ];
            ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-4">
                    <div class="plan-card position-relative h-100 <?= $feat ? 'featured' : '' ?> <?= $isCurrent ? 'plan-now' : '' ?>" style="--plan-accent:<?= $c['badge'] ?>;--plan-grad:<?= $c['grad'] ?>;--plan-glow:<?= hexToRgba($c['badge'], .28) ?>;">
                        <?php if ($isCurrent): ?>
                            <span class="current-tag"><i class="fas fa-check me-1"></i>Plano Atual</span>
                        <?php elseif ($feat): ?>
                            <span class="current-tag popular"><i class="fas fa-star me-1"></i>Mais Popular</span>
                        <?php endif; ?>
                        <div class="plan-head" style="background:var(--plan-grad);">
                            <div class="plan-ico-wrap"><i class="fas <?= htmlspecialchars($p['icon'] ?: 'fa-circle') ?> plan-ico"></i></div>
                            <div class="plan-name"><?= htmlspecialchars($p['nome']) ?></div>
                            <div class="plan-tagline"><?= htmlspecialchars($p['descricao'] ?? '') ?></div>
                            <div class="plan-por">a partir de</div>
                            <div class="plan-price">
                                <span class="plan-preco-num">R$ <?= number_format($precoMensal, 2, ',', '.') ?></span>
                                <span class="plan-preco-period">/mês</span>
                            </div>
                        </div>
                        <div class="plan-body">
                            <ul class="plan-feat">
                                <?php
                                if ($benef):
                                    foreach ($benef as $b):
                                        if (stripos($b, 'Gateways:') === 0):
                                            $lista = trim(substr($b, strpos($b, ':') + 1));
                                            // Normaliza separadores corrompidos por importação antiga de charset (??) e separa por '·', vírgula ou espaço.
                                            $lista = str_replace('??', ' · ', $lista);
                                            $nomes = preg_split('/\s*[·,;]\s*/u', $lista);
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

                            <div class="plan-periodos mb-3">
                                <div class="small text-muted mb-2 fw-semibold"><i class="fas fa-clock me-1"></i>Escolha a duração</div>
                                <div class="row g-2">
                                    <?php foreach ($periodos as $dur => $info): ?>
                                        <div class="col-6">
                                            <button type="button" class="btn btn-sm w-100 periodo-pill <?= $dur === 1 ? 'active' : '' ?>" data-plano="<?= (int)$p['id'] ?>" data-duracao="<?= $dur ?>" onclick="selPeriodo(<?= (int)$p['id'] ?>, <?= $dur ?>, <?= $info[2] ?>)">
                                                <span class="d-flex flex-column align-items-center lh-sm">
                                                    <span><?= $info[0] ?></span>
                                                    <span class="periodo-preco">R$ <?= number_format($info[2], 2, ',', '.') ?></span>
                                                </span>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex align-items-center gap-1 mt-2">
                                    <span class="badge" style="background:rgba(220,53,69,.1);color:#dc3545;font-size:.68rem;">
                                        <i class="fas fa-bolt me-1"></i>10% OFF em Trimestral, Semestral e Anual
                                    </span>
                                </div>
                            </div>

                            <?php if ($isCurrent): ?>
                                <button class="btn current-plan-btn w-100" onclick="abrirPagamento(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nome'])) ?>', <?= (float)$precoMensal ?>)">
                                    <i class="fas fa-sync me-1"></i> Renovar este plano
                                </button>
                            <?php else: ?>
                                <button class="btn btn-contratar w-100" onclick="abrirPagamento(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nome'])) ?>', <?= (float)$precoMensal ?>)">
                                    <i class="fas fa-shopping-cart me-1"></i> Contratar / Renovar
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

<!-- Modal de pagamento (PIX / Boleto / Cartão) -->
<div class="modal fade" id="pixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:1rem;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="pixModalTitle">Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4" id="pixModalBody">
                <div id="pixLoading" class="text-center py-4">
                    <div class="spinner-border" style="color:#cd7f32;"></div>
                    <p class="text-muted mt-3 mb-0">Gerando cobrança...</p>
                </div>

                <div id="metodoStep" style="display:none;">
                    <p class="small text-muted mb-3">Escolha a forma de pagamento para <strong id="metodoPlanoNome"></strong></p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-success text-start w-100" id="metodoPixBtn" onclick="escolherMetodo('pix')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
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
                            <button type="button" class="btn btn-outline-warning text-start w-100" id="metodoBoletoBtn" onclick="escolherMetodo('boleto')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
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
                            <button type="button" class="btn btn-outline-primary text-start w-100" id="metodoCartaoCreditoBtn" onclick="escolherMetodo('cartao_credito')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
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
                            <button type="button" class="btn btn-outline-info text-start w-100" id="metodoCartaoDebitoBtn" onclick="escolherMetodo('cartao_debito')" style="border-width:2px;border-radius:.8rem;padding:.9rem 1rem;">
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
                </div>

                <div id="boletoContent" style="display:none;">
                    <div class="text-center mb-3">
                        <div class="d-inline-flex align-items-center gap-2">
                            <span class="pulse-dot"></span>
                            <span class="text-muted small">Aguardando pagamento do boleto</span>
                        </div>
                    </div>
                    <div class="text-center">
                        <a id="boletoLink" href="#" target="_blank" rel="noopener" class="btn btn-lg btn-warning fw-bold" style="border-radius:.7rem;">
                            <i class="fas fa-external-link-alt me-2"></i> Abrir boleto bancário
                        </a>
                        <div class="text-muted small mt-2">Ou pague pela linha digitável no app do seu banco</div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold">Linha digitável / Código de barras</label>
                        <div class="pix-copia d-flex align-items-center justify-content-between gap-2">
                            <span id="boletoLinha" style="flex:1;" class="small"></span>
                            <button class="btn btn-sm btn-outline-warning" id="btnCopiarBoleto" title="Copiar">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="cartaoContent" style="display:none;">
                    <div class="d-flex rounded border p-1 mb-3">
                        <button type="button" id="tabCredito" class="btn btn-sm flex-fill fw-bold active" style="background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.12);">Crédito</button>
                        <button type="button" id="tabDebito" class="btn btn-sm flex-fill fw-bold text-muted">Débito</button>
                    </div>
                    <div id="ccMsg" class="small mb-2" style="min-height:18px;"></div>
                    <form id="ccForm">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Número do cartão</label>
                            <input id="ccNumero" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" class="form-control font-monospace">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nome impresso no cartão</label>
                            <input id="ccNome" autocomplete="cc-name" class="form-control">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Validade</label>
                                <input id="ccValidade" inputmode="numeric" placeholder="MM/AA" maxlength="5" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">CVV</label>
                                <input id="ccCvv" inputmode="numeric" maxlength="4" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3" id="ccParcelasWrap">
                            <label class="form-label small fw-semibold">Parcelas</label>
                            <select id="ccParcelas" class="form-select"></select>
                        </div>
                        <button type="submit" id="ccBtn" class="btn btn-dark w-100 fw-bold" style="border-radius:.7rem;">Pagar</button>
                    </form>
                    <p class="mt-3 mb-0 small text-muted text-center">Pagamento processado com segurança pelo Mercado Pago.</p>
                </div>

                <div class="text-center mt-3">
                    <button type="button" id="voltarMetodoBtn" class="btn btn-sm btn-link text-muted" style="display:none;" onclick="voltarMetodos()">← Escolher outro método</button>
                </div>

                <div class="alert alert-success mt-3 py-2 mb-0" id="payConfirmado" style="display:none;">
                    <i class="fas fa-check-circle me-1"></i> <strong>Pagamento confirmado!</strong> Seu plano foi ativado. Atualizando...
                </div>
                <div id="pixErro" class="alert alert-danger py-2 mb-0" style="display:none;"></div>
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
let pixPolling = null;
let pixPagamentoId = null;
let pixPlanoId = null;
let pixQrJsInstance = null;
let duracaoSelecionada = 1;
let criando = false;

const METODOS = <?= json_encode($metodosAdmin) ?>;
const CARTAO_OK = METODOS.indexOf('cartao') !== -1;
const QTD_OPCOES = (METODOS.indexOf('pix') !== -1 ? 1 : 0) + (METODOS.indexOf('boleto') !== -1 ? 1 : 0) + (CARTAO_OK ? 1 : 0);
const MAX_PARCELAS = <?= (int)$maxParcelasAdmin ?>;
const PUBLIC_KEY = <?= json_encode($mpPubKeyAdmin) ?>;
const CPF_CLIENTE = <?= json_encode($adminCpfCnpj) ?>;

// Período selecionado por plano: { planoId: {duracao, preco} }
const planoSelecao = {};

function selPeriodo(planoId, duracao, preco) {
    planoSelecao[planoId] = { duracao: duracao, preco: preco };
    document.querySelectorAll('[data-plano="' + planoId + '"]').forEach(function (el) {
        el.classList.toggle('active', parseInt(el.dataset.duracao, 10) === duracao);
    });
}

function getSelecao(planoId, precoPadrao) {
    return planoSelecao[planoId] || { duracao: 1, preco: precoPadrao };
}

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

function abrirPagamento(planoId, nome, precoPadrao) {
    const s = getSelecao(planoId, precoPadrao);
    const duracao = s.duracao;
    duracaoSelecionada = duracao;
    pixPlanoId = planoId;
    const nomePeriodo = { 1: 'Mensal', 3: 'Trimestral', 6: 'Semestral', 12: 'Anual' }[duracao] || 'Mensal';
    document.getElementById('pixModalTitle').textContent = 'Pagamento - ' + nome + ' (' + nomePeriodo + ')';
    document.getElementById('metodoPlanoNome').textContent = nome + ' (' + nomePeriodo + ')';
    document.getElementById('voltarMetodoBtn').style.display = (QTD_OPCOES === 1 && !CARTAO_OK) ? 'none' : 'inline-block';
    esconderPassos();
    let modal = new bootstrap.Modal(document.getElementById('pixModal'));
    modal.show();

    document.getElementById('metodoPixBtn').style.display = METODOS.indexOf('pix') !== -1 ? 'block' : 'none';
    document.getElementById('metodoBoletoBtn').style.display = METODOS.indexOf('boleto') !== -1 ? 'block' : 'none';
    document.getElementById('metodoCartaoCreditoBtn').style.display = CARTAO_OK ? 'block' : 'none';
    document.getElementById('metodoCartaoDebitoBtn').style.display = CARTAO_OK ? 'block' : 'none';

    if (QTD_OPCOES === 1 && !CARTAO_OK) {
        escolherMetodo(METODOS[0]);
    } else {
        mostrarPasso('metodoStep');
    }
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
                mostrarPasso('payConfirmado');
                setTimeout(() => window.location.reload(), 1800);
            } else if (res.status === 'cancelado' || res.status === 'expirado') {
                pararPolling();
                mostrarPixErro('Este pagamento foi ' + res.status + '.');
            }
        })
        .catch(() => {});
}
function mostrarPixErro(msg) {
    pararPolling();
    esconderPassos();
    const el = document.getElementById('pixErro');
    el.textContent = msg;
    el.style.display = 'block';
}

function esconderPassos() {
    ['pixLoading', 'metodoStep', 'pixContent', 'boletoContent', 'cartaoContent', 'payConfirmado', 'pixErro'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
}
function mostrarPasso(id) {
    esconderPassos();
    var el = document.getElementById(id);
    if (el) el.style.display = 'block';
}

function escolherMetodo(m) {
    pararPolling();
    criando = false;
    if (m === 'pix') { puxarPix(); }
    else if (m === 'boleto') { puxarBoleto(); }
    else if (m === 'cartao_credito') { tipoCartao = 'credito'; mostrarCartao(); }
    else if (m === 'cartao_debito') { tipoCartao = 'debito'; mostrarCartao(); }
}
function voltarMetodos() {
    pararPolling();
    criando = false;
    mostrarPasso('metodoStep');
}

function puxarPix() {
    if (criando) return;
    criando = true;
    mostrarPasso('pixLoading');
    const fd = new FormData();
    fd.append('plano_id', pixPlanoId);
    fd.append('duracao_meses', duracaoSelecionada);
    fetch('/cobranca/api/criar_pix_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            criando = false;
            if (res.erro) { mostrarPixErro(res.erro); return; }
            pixPagamentoId = res.pagamento_id;
            document.getElementById('pixCopia').textContent = res.pix_copia_cola;
            mostrarPasso('pixContent');
            mostrarQr(res);
            iniciarPolling();
        })
        .catch(() => { criando = false; mostrarPixErro('Erro de conexão ao gerar o PIX. Tente novamente.'); });
}

function puxarBoleto() {
    if (criando) return;
    criando = true;
    mostrarPasso('pixLoading');
    const fd = new FormData();
    fd.append('plano_id', pixPlanoId);
    fd.append('duracao_meses', duracaoSelecionada);
    fetch('/cobranca/api/criar_boleto_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            criando = false;
            if (res.erro) { mostrarPixErro(res.erro); return; }
            pixPagamentoId = res.pagamento_id;
            document.getElementById('boletoLinha').textContent = res.boleto_linha_digitavel || res.boleto_codigo_barras || '';
            const link = document.getElementById('boletoLink');
            if (res.boleto_url) {
                link.href = res.boleto_url;
                link.classList.remove('disabled');
            } else {
                link.classList.add('disabled');
            }
            mostrarPasso('boletoContent');
            iniciarPolling();
        })
        .catch(() => { criando = false; mostrarPixErro('Erro de conexão ao gerar o boleto. Tente novamente.'); });
}

/* -------- CARTÃO -------- */
let mp = null;
let tipoCartao = 'credito';
let parcelaMontada = false;

function valorAtualSelecionado() {
    const s = getSelecao(pixPlanoId, 0);
    return parseFloat(s.preco) || 0;
}

function txtMoeda(v) {
    return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function parcelasAtuais() {
    const sel = document.getElementById('ccParcelas');
    if (tipoCartao === 'debito' || !sel) return 1;
    return parseInt(sel.value, 10) || 1;
}

function montarParcelasCartao() {
    const sel = document.getElementById('ccParcelas');
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

function atualizarBtnCartao() {
    const btn = document.getElementById('ccBtn');
    const p = parcelasAtuais();
    if (tipoCartao === 'debito' || p <= 1) {
        btn.textContent = 'Pagar à vista — ' + txtMoeda(valorAtualSelecionado());
    } else {
        btn.textContent = 'Pagar em ' + p + 'x de ' + txtMoeda(valorAtualSelecionado() / p);
    }
}

function mostrarCartao() {
    aplicarTabCartao();
    montarParcelasCartao();
    atualizarBtnCartao();
    const msg = document.getElementById('ccMsg');
    if (!CPF_CLIENTE) {
        msg.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Para pagar com cartão ou boleto é preciso ter CPF/CNPJ no cadastro. <a href="/cobranca/admin/perfil.php" class="fw-bold text-decoration-underline">Editar perfil</a>';
        msg.style.color = '#b45309';
    } else {
        msg.textContent = '';
    }
    mostrarPasso('cartaoContent');
}

function aplicarTabCartao() {
    const cred = document.getElementById('tabCredito');
    const deb = document.getElementById('tabDebito');
    const parcelas = document.getElementById('ccParcelasWrap');
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
    if (typeof atualizarBtnCartao === 'function') atualizarBtnCartao();
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

function metodoDebito(numero) {
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

function formatarNumero(val) {
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

function msgCartao(texto, tipo) {
    const m = document.getElementById('ccMsg');
    m.className = 'small mb-2';
    if (tipo === 'ok') { m.style.color = '#0f7b5c'; }
    else if (tipo === 'warn') { m.style.color = '#b45309'; }
    else { m.style.color = '#dc3545'; }
    m.textContent = texto || '';
}

function tokenErro(err) {
    const btn = document.getElementById('ccBtn');
    btn.disabled = false;
    atualizarBtnCartao();
    msgCartao(mensagensErro(err), 'erro');
}

function tokenSucesso(resp) {
    if (!resp || typeof resp.id !== 'string' || resp.id === '') { tokenErro(resp); return; }
    const fd = new FormData();
    fd.append('plano_id', pixPlanoId);
    fd.append('duracao_meses', duracaoSelecionada);
    fd.append('card_token', resp.id);
    fd.append('installments', parcelasAtuais());
    fd.append('tipo', tipoCartao);
    fd.append('method', tipoCartao === 'debito' ? metodoDebito(document.getElementById('ccNumero').value) : '');
    fetch('/cobranca/api/cartao_plano.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.sucesso && (d.status === 'pago' || d.mp_status === 'approved')) {
                pararPolling();
                mostrarPasso('payConfirmado');
                setTimeout(() => window.location.reload(), 1800);
            } else if (d.sucesso) {
                pixPagamentoId = d.pagamento_id;
                msgCartao('Pagamento em processamento. Acompanhe a confirmação abaixo.', 'warn');
                iniciarPolling();
            } else {
                const btn = document.getElementById('ccBtn');
                btn.disabled = false;
                atualizarBtnCartao();
                msgCartao(d.erro || 'Não foi possível processar o pagamento.', 'erro');
            }
        })
        .catch(function () {
            const btn = document.getElementById('ccBtn');
            btn.disabled = false;
            atualizarBtnCartao();
            msgCartao('Erro de conexão. Tente novamente.', 'erro');
        });
}

function iniciarCartao() {
    document.getElementById('tabCredito').addEventListener('click', function () {
        tipoCartao = 'credito';
        aplicarTabCartao();
    });
    document.getElementById('tabDebito').addEventListener('click', function () {
        tipoCartao = 'debito';
        aplicarTabCartao();
    });
    document.getElementById('ccParcelas').addEventListener('change', atualizarBtnCartao);
    document.getElementById('ccNumero').addEventListener('input', function (e) { e.target.value = formatarNumero(e.target.value); });
    document.getElementById('ccValidade').addEventListener('input', function (e) {
        let v = e.target.value.replace(/\D+/g, '').slice(0, 4);
        if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
        e.target.value = v;
    });

    document.getElementById('ccForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const numero = document.getElementById('ccNumero').value.replace(/\s+/g, '');
        const nome = document.getElementById('ccNome').value.trim();
        const validade = document.getElementById('ccValidade').value.trim();
        const cvv = document.getElementById('ccCvv').value.trim();
        const parcelas = parcelasAtuais();

        if (!CPF_CLIENTE) {
            msgCartao('Cadastre um CPF ou CNPJ no seu perfil para pagar com cartão.', 'erro');
            return;
        }
        if (!/^\d{13,16}$/.test(numero)) { msgCartao('Número de cartão inválido.', 'erro'); return; }
        const m = validade.match(/^(\d{2})\s*\/\s*(\d{2})$/);
        if (!m) { msgCartao('Validade inválida. Use o formato MM/AA.', 'erro'); return; }
        const mes = parseInt(m[1], 10), ano = 2000 + parseInt(m[2], 10);
        if (mes < 1 || mes > 12) { msgCartao('Mês da validade inválido.', 'erro'); return; }
        if (cvv.length < 3) { msgCartao('CVV inválido.', 'erro'); return; }
        if (!nome) { msgCartao('Informe o nome impresso no cartão.', 'erro'); return; }
        const hoje = new Date();
        if (ano < hoje.getFullYear() || (ano === hoje.getFullYear() && mes < hoje.getMonth() + 1)) {
            msgCartao('Este cartão está vencido.', 'erro');
            return;
        }

        const btn = document.getElementById('ccBtn');
        btn.disabled = true;
        btn.textContent = 'Processando...';
        msgCartao('', 'ok');

        if (!mp) { tokenErro('SDK de pagamento não carregado. Recarregue a página.'); return; }

        const payload = {
            cardNumber: numero,
            cardholderName: nome,
            cardExpirationMonth: (mes < 10 ? '0' : '') + mes,
            cardExpirationYear: String(ano),
            securityCode: cvv,
            installments: parcelas,
            identificationType: CPF_CLIENTE.length === 14 ? 'CNPJ' : 'CPF',
            identificationNumber: CPF_CLIENTE,
            locale: 'pt-BR'
        };

        try {
            const prom = mp.createCardToken(payload);
            if (prom && typeof prom.then === 'function') {
                prom.then(tokenSucesso).catch(function (err) { tokenErro(err); });
            } else {
                mp.createCardToken(payload, function (resp, err) {
                    if (err && (err.length || err.message)) tokenErro(err);
                    else tokenSucesso(resp);
                });
            }
        } catch (ex) {
            tokenErro(ex);
        }
    });

    atualizarBtnCartao();
}

function feedbackCopiarBoleto() {
    const btn = document.getElementById('btnCopiarBoleto');
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.remove('btn-outline-warning'); btn.classList.add('btn-success');
    setTimeout(() => { btn.innerHTML = old; btn.classList.add('btn-outline-warning'); btn.classList.remove('btn-success'); }, 1500);
}

document.getElementById('btnCopiarBoleto').addEventListener('click', function () {
    const texto = document.getElementById('boletoLinha').textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => feedbackCopiarBoleto());
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); feedbackCopiarBoleto();
    }
});

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

document.getElementById('pixModal').addEventListener('hidden.bs.modal', function () { pararPolling(); criando = false; });

if (METODOS.indexOf('cartao') !== -1 && PUBLIC_KEY) {
    try { mp = new MercadoPago(PUBLIC_KEY, { locale: 'pt-BR' }); } catch (e) { mp = null; }
    iniciarCartao();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
