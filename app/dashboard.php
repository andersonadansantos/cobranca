<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];

$filtro_status = $_GET['filtro_status'] ?? '';
$filtro_busca = trim($_GET['filtro_busca'] ?? '');

$sql = "SELECT * FROM faturas WHERE cliente_id = ?";
$params = [$userId];

if ($filtro_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filtro_status;
}
if ($filtro_busca !== '') {
    $sql .= " AND (numero LIKE ? OR descricao LIKE ?)";
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
}

$countSql = "SELECT COUNT(*) FROM faturas WHERE cliente_id = ?";
$countParams = [$userId];
if ($filtro_status !== '') {
    $countSql .= " AND status = ?";
    $countParams[] = $filtro_status;
}
if ($filtro_busca !== '') {
    $countSql .= " AND (numero LIKE ? OR descricao LIKE ?)";
    $countParams[] = '%' . $filtro_busca . '%';
    $countParams[] = '%' . $filtro_busca . '%';
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalFaturasCount = $countStmt->fetchColumn();

$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalFaturasCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listaFaturas = $stmt->fetchAll();

$statsStmt = $pdo->prepare("SELECT status, valor_final FROM faturas WHERE cliente_id = ?");
$statsStmt->execute([$userId]);
$allFaturas = $statsStmt->fetchAll();

$totalPendente = 0;
$totalPago = 0;
$abertas = 0;
$pagasCount = 0;
foreach ($allFaturas as $f) {
    if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado') {
        $totalPendente += $f['valor_final'];
        $abertas++;
    }
    if ($f['status'] === 'pago') {
        $totalPago += $f['valor_final'];
        $pagasCount++;
    }
}

$proxima = null;
$allF = $pdo->prepare("SELECT * FROM faturas WHERE cliente_id = ? AND status NOT IN ('pago','cancelado') ORDER BY data_vencimento ASC LIMIT 1");
$allF->execute([$userId]);
$proxima = $allF->fetch();

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
    <title><?= htmlspecialchars($nomeSistema) ?></title>
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
            <div>
                <?php if ($logo): ?>
                    <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="height:32px; object-fit:contain;">
                <?php else: ?>
                    <h5><?= htmlspecialchars($nomeSistema) ?></h5>
                <?php endif; ?>
            </div>
            <a href="perfil.php">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="app-topbar-avatar">
            </a>
        </div>

        <div class="app-content">
            <?php
            $bannersMobile = [];
            for ($i = 1; $i <= 3; $i++) {
                $m = getConfig('banner_mobile_' . $i, '');
                if ($m) $bannersMobile[] = $m;
            }
            if ($bannersMobile): ?>
                <div class="app-banner-carousel" style="margin-bottom:16px; position:relative; border-radius:10px; overflow:hidden;">
                    <div class="app-banner-track" style="display:flex; transition:transform 0.4s ease;">
                        <?php foreach ($bannersMobile as $b): ?>
                            <div class="app-banner-slide" style="min-width:100%;">
                                <img src="<?= htmlspecialchars($b) ?>" alt="Banner" style="width:100%; max-height:400px; object-fit:contain; display:block;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($bannersMobile) > 1): ?>
                        <button onclick="appBannerPrev()" style="position:absolute; left:6px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.3); border:none; color:#fff; width:28px; height:28px; border-radius:50%; font-size:14px; cursor:pointer;"><i class="fas fa-chevron-left"></i></button>
                        <button onclick="appBannerNext()" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.3); border:none; color:#fff; width:28px; height:28px; border-radius:50%; font-size:14px; cursor:pointer;"><i class="fas fa-chevron-right"></i></button>
                        <div class="app-banner-dots" style="position:absolute; bottom:6px; left:50%; transform:translateX(-50%); display:flex; gap:6px;">
                            <?php foreach ($bannersMobile as $idx => $b): ?>
                                <span onclick="appBannerGo(<?= $idx ?>)" style="width:7px; height:7px; border-radius:50%; background:<?= $idx === 0 ? '#fff' : 'rgba(255,255,255,0.5)' ?>; cursor:pointer; display:block;" class="app-banner-dot"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="app-stats app-animate">
                <div class="app-stat stat-danger">
                    <div class="app-stat-value"><?= $abertas ?></div>
                    <div class="app-stat-label">Em Aberto</div>
                </div>
                <div class="app-stat stat-warning">
                    <div class="app-stat-value">R$ <?= number_format($totalPendente, 2, ',', '.') ?></div>
                    <div class="app-stat-label">Total a Pagar</div>
                </div>
                <div class="app-stat stat-success">
                    <div class="app-stat-value"><?= $pagasCount ?></div>
                    <div class="app-stat-label">Pagas</div>
                </div>
                <div class="app-stat stat-info">
                    <div class="app-stat-value"><?= $proxima ? date('d/m', strtotime($proxima['data_vencimento'])) : '--' ?></div>
                    <div class="app-stat-label">Próx. Vencimento</div>
                </div>
            </div>

            <div class="app-filter-bar app-animate">
                <div class="app-filter-status">
                    <a href="?filtro_status=&filtro_busca=<?= urlencode($filtro_busca) ?>" class="app-filter-chip <?= $filtro_status === '' ? 'active chip-all' : '' ?>">
                        <span class="dot" style="background:#6c757d"></span> Todos
                    </a>
                    <a href="?filtro_status=pendente&filtro_busca=<?= urlencode($filtro_busca) ?>" class="app-filter-chip <?= $filtro_status === 'pendente' ? 'active chip-pendente' : '' ?>">
                        <span class="dot" style="background:#fd7e14"></span> Pendente
                    </a>
                    <a href="?filtro_status=pago&filtro_busca=<?= urlencode($filtro_busca) ?>" class="app-filter-chip <?= $filtro_status === 'pago' ? 'active chip-pago' : '' ?>">
                        <span class="dot" style="background:#00b894"></span> Pago
                    </a>
                    <a href="?filtro_status=vencido&filtro_busca=<?= urlencode($filtro_busca) ?>" class="app-filter-chip <?= $filtro_status === 'vencido' ? 'active chip-vencido' : '' ?>">
                        <span class="dot" style="background:#e74c3c"></span> Atrasado
                    </a>
                    <a href="?filtro_status=cancelado&filtro_busca=<?= urlencode($filtro_busca) ?>" class="app-filter-chip <?= $filtro_status === 'cancelado' ? 'active chip-cancelado' : '' ?>">
                        <span class="dot" style="background:#6c757d"></span> Cancelado
                    </a>
                </div>
                <form method="GET" class="app-filter-search">
                    <?php if ($filtro_status): ?>
                        <input type="hidden" name="filtro_status" value="<?= htmlspecialchars($filtro_status) ?>">
                    <?php endif; ?>
                    <i class="fas fa-search"></i>
                    <input type="text" name="filtro_busca" placeholder="Buscar por Nº Fatura ou descrição..." value="<?= htmlspecialchars($filtro_busca) ?>">
                </form>
            </div>

            <?php if (empty($listaFaturas)): ?>
                <div class="app-empty app-animate">
                    <i class="fas fa-inbox"></i>
                    <p>Nenhuma fatura encontrada.</p>
                </div>
            <?php else: ?>
                <?php foreach ($listaFaturas as $f): ?>
                    <?php
                    $diasAteVenc = (strtotime($f['data_vencimento']) - strtotime(date('Y-m-d'))) / 86400;
                    ?>
                    <div class="app-fatura-card status-<?= $f['status'] ?> app-animate" <?= $f['status'] !== 'cancelado' ? "onclick=\"location.href='fatura.php?id={$f['id']}'\"" : 'style="opacity:0.55; pointer-events:none;"' ?>>
                        <div class="app-fatura-header">
                            <div>
                                <div class="app-fatura-numero"><?= htmlspecialchars($f['numero']) ?></div>
                                <div class="app-fatura-desc"><?= htmlspecialchars($f['descricao']) ?></div>
                            </div>
                            <div class="app-fatura-valor">R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></div>
                        </div>
                        <div class="app-fatura-meta">
                            <div class="app-fatura-venc">
                                Venc: <?= date('d/m/Y', strtotime($f['data_vencimento'])) ?>
                                <?php if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado'): ?>
                                    <?php if ($diasAteVenc < 0): ?>
                                        <small class="text-danger"><?= abs(intval($diasAteVenc)) ?> dia(s) atrasado</small>
                                    <?php elseif ($diasAteVenc <= 3 && $diasAteVenc >= 0): ?>
                                        <small class="text-warning">Vence em <?= intval($diasAteVenc) ?> dia(s)</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <span class="app-badge app-badge-<?= $f['status'] ?>"><?= ucfirst($f['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($totalFaturasCount > 0): ?>
            <div class="app-pagination app-animate">
                <div class="app-pagination-info">
                    Mostrando <?= count($listaFaturas) ?> de <?= $totalFaturasCount ?> faturas
                    <select onchange="window.location.href='?page=1&per_page='+this.value+'&filtro_status=<?= urlencode($filtro_status) ?>&filtro_busca=<?= urlencode($filtro_busca) ?>'">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?>/página</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($totalPages > 1): ?>
                <?php $bpUrl = '?per_page=' . $perPage . '&filtro_status=' . urlencode($filtro_status) . '&filtro_busca=' . urlencode($filtro_busca); ?>
                <a href="<?= $bpUrl ?>&page=<?= $page - 1 ?>" class="app-pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
                <?php
                $bpStart = max(1, $page - 2);
                $bpEnd = min($totalPages, $page + 2);
                if ($bpStart > 1): ?>
                    <a href="<?= $bpUrl ?>&page=1" class="app-pagination-btn">1</a>
                    <?php if ($bpStart > 2): ?><span class="app-pagination-dots">...</span><?php endif; ?>
                <?php endif;
                for ($i = $bpStart; $i <= $bpEnd; $i++): ?>
                    <a href="<?= $bpUrl ?>&page=<?= $i ?>" class="app-pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor;
                if ($bpEnd < $totalPages): ?>
                    <?php if ($bpEnd < $totalPages - 1): ?><span class="app-pagination-dots">...</span><?php endif; ?>
                    <a href="<?= $bpUrl ?>&page=<?= $totalPages ?>" class="app-pagination-btn"><?= $totalPages ?></a>
                <?php endif; ?>
                <a href="<?= $bpUrl ?>&page=<?= $page + 1 ?>" class="app-pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">›</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align:center; padding:16px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a><span style="float:right;">Versão: 1.0</span>
    </div>

    <nav class="app-bottom-nav">
        <a href="dashboard.php" class="app-nav-item active">
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
    <?php if ($bannersMobile && count($bannersMobile) > 1): ?>
    <script>
    (function(){
        var idx = 0, total = <?= count($bannersMobile) ?>, track = document.querySelector('.app-banner-track'), dots = document.querySelectorAll('.app-banner-dot');
        function update(){ track.style.transform = 'translateX(-' + (idx * 100) + '%)'; dots.forEach(function(d,i){ d.style.background = i === idx ? '#fff' : 'rgba(255,255,255,0.5)'; }); }
        window.appBannerNext = function(){ idx = (idx + 1) % total; update(); };
        window.appBannerPrev = function(){ idx = (idx - 1 + total) % total; update(); };
        window.appBannerGo = function(i){ idx = i; update(); };
        setInterval(function(){ idx = (idx + 1) % total; update(); }, 4000);
    })();
    </script>
    <?php endif; ?>
    <script src="pwa.js"></script>
</body>
</html>