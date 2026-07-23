<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireUser();

if (isMobileDevice()) {
    header('Location: /cobranca/app/dashboard.php');
    exit;
}
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

$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));

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
$totalPages = max(1, ceil($totalFaturasCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listaFaturas = $stmt->fetchAll();

$totalPendente = 0;
$totalPago = 0;
$abertas = 0;
foreach ($listaFaturas as $f) {
    if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado') {
        $totalPendente += $f['valor_final'];
        $abertas++;
    }
    if ($f['status'] === 'pago') {
        $totalPago += $f['valor_final'];
    }
}

$proxima = null;
$allFaturas = $pdo->prepare("SELECT * FROM faturas WHERE cliente_id = ? ORDER BY id DESC");
$allFaturas->execute([$userId]);
$allRows = $allFaturas->fetchAll();
foreach ($allRows as $f) {
    if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado') {
        $proxima = $f;
        break;
    }
}

$pageTitle = 'Painel Geral';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_usuario.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Minhas Faturas</h5>
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
        <?php
        $bannersDesktop = [];
        $bannersMobile = [];
        for ($i = 1; $i <= 3; $i++) {
            $d = getConfig('banner_desktop_' . $i, '');
            $m = getConfig('banner_mobile_' . $i, '');
            if ($d) $bannersDesktop[] = $d;
            if ($m) $bannersMobile[] = $m;
        }
        if ($bannersDesktop || $bannersMobile): ?>
            <div class="mb-4">
                <?php if ($bannersDesktop): ?>
                    <div class="d-none d-md-block">
                        <?php if (count($bannersDesktop) === 1): ?>
                            <img src="<?= htmlspecialchars($bannersDesktop[0]) ?>" alt="Banner" style="width:100%; max-height:200px; object-fit:contain; border-radius:10px;">
                        <?php else: ?>
                            <div id="bannerCarouselDesktop" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner" style="border-radius:10px; overflow:hidden;">
                                    <?php foreach ($bannersDesktop as $idx => $b): ?>
                                        <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                                            <img src="<?= htmlspecialchars($b) ?>" alt="Banner <?= $idx + 1 ?>" style="width:100%; max-height:200px; object-fit:contain;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($bannersDesktop) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarouselDesktop" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#bannerCarouselDesktop" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                                    <div class="carousel-indicators mb-0">
                                        <?php foreach ($bannersDesktop as $idx => $b): ?>
                                            <button type="button" data-bs-target="#bannerCarouselDesktop" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($bannersMobile): ?>
                    <div class="d-md-none">
                        <?php if (count($bannersMobile) === 1): ?>
                            <img src="<?= htmlspecialchars($bannersMobile[0]) ?>" alt="Banner" style="width:100%; max-height:400px; object-fit:contain; border-radius:10px;">
                        <?php else: ?>
                            <div id="bannerCarouselMobile" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner" style="border-radius:10px; overflow:hidden;">
                                    <?php foreach ($bannersMobile as $idx => $b): ?>
                                        <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                                            <img src="<?= htmlspecialchars($b) ?>" alt="Banner <?= $idx + 1 ?>" style="width:100%; max-height:400px; object-fit:contain;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($bannersMobile) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarouselMobile" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#bannerCarouselMobile" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                                    <div class="carousel-indicators mb-0">
                                        <?php foreach ($bannersMobile as $idx => $b): ?>
                                            <button type="button" data-bs-target="#bannerCarouselMobile" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-perigo);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $abertas ?></div>
                            <div class="stat-label">Faturas em Aberto</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-file-invoice"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-aviso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($totalPendente, 2, ',', '.') ?></div>
                            <div class="stat-label">Total a Pagar</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-aviso);"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-sucesso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= count(array_filter($listaFaturas, fn($f) => $f['status'] === 'pago')) ?></div>
                            <div class="stat-label">Faturas Pagas</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-sucesso);"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php
                $proxima = null;
                foreach ($listaFaturas as $f) {
                    if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado') {
                        $proxima = $f;
                        break;
                    }
                }
                ?>
                <div class="stat-card" style="border-left-color: var(--cor-aviso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $proxima ? date('d/m', strtotime($proxima['data_vencimento'])) : '--' ?></div>
                            <div class="stat-label">Próximo Vencimento</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-aviso);"><i class="fas fa-calendar"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card mb-4">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Minhas Faturas</h6>
            </div>
            <div class="p-3 border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small">Status</label>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="?filtro_status=&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === '' ? 'btn-dark' : 'btn-outline-dark' ?>"><i class="fas fa-square me-1" style="color:#6c757d;font-size:.6rem"></i> Todos</a>
                            <a href="?filtro_status=pendente&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'pendente' ? 'btn-warning' : 'btn-outline-warning' ?>"><i class="fas fa-square me-1" style="color:#fd7e14;font-size:.6rem"></i> Pendente</a>
                            <a href="?filtro_status=pago&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'pago' ? 'btn-success' : 'btn-outline-success' ?>"><i class="fas fa-square me-1" style="color:#198754;font-size:.6rem"></i> Pago</a>
                            <a href="?filtro_status=vencido&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'vencido' ? 'btn-danger' : 'btn-outline-danger' ?>"><i class="fas fa-square me-1" style="color:#dc3545;font-size:.6rem"></i> Atrasado</a>
                            <a href="?filtro_status=cancelado&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'cancelado' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="fas fa-square me-1" style="color:#6c757d;font-size:.6rem"></i> Cancelado</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small"><i class="fas fa-square me-1" style="color:#6f42c1;font-size:.6rem"></i>Nº Fatura <i class="fas fa-square ms-2 me-1" style="color:#b19cd9;font-size:.6rem"></i>Descrição</label>
                        <input type="text" name="filtro_busca" class="form-control form-control-sm" placeholder="Buscar por número ou descrição..." value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="col-md-1">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-times"></i></a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listaFaturas)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    Nenhuma fatura encontrada.
                                </td>
                            </tr>
                        <?php else: foreach ($listaFaturas as $f): ?>
                            <?php
                            $diasAteVenc = (strtotime($f['data_vencimento']) - strtotime(date('Y-m-d'))) / 86400;
                            $linhaClass = '';
                            if ($f['status'] === 'pago') $linhaClass = 'table-success';
                            elseif ($f['status'] === 'cancelado') $linhaClass = 'table-secondary';
                            elseif ($f['status'] === 'atrasado' || $f['status'] === 'vencido') $linhaClass = 'table-danger';
                            elseif ($diasAteVenc <= 3 && $diasAteVenc >= 0) $linhaClass = 'table-warning';

                            $statusClasses = [
                                'pendente' => 'badge-pendente',
                                'pago' => 'badge-pago',
                                'vencido' => 'badge-vencido',
                                'atrasado' => 'badge-atrasado',
                                'cancelado' => 'badge-cancelado',
                            ];
                            $classe = $statusClasses[$f['status']] ?? 'badge-pendente';
                            ?>
                            <tr class="<?= $linhaClass ?>" style="<?= $f['status'] === 'cancelado' ? 'opacity:0.55; pointer-events:none;' : '' ?>">
                                <td><strong><?= htmlspecialchars($f['numero']) ?></strong></td>
                                <td><?= htmlspecialchars($f['descricao']) ?></td>
                                <td><strong>R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></strong></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($f['data_vencimento'])) ?>
                                    <?php if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado'): ?>
                                        <?php if ($diasAteVenc < 0): ?>
                                            <br><small class="text-danger"><?= abs(intval($diasAteVenc)) ?> dia(s) atrasado</small>
                                        <?php elseif ($diasAteVenc <= 3 && $diasAteVenc >= 0): ?>
                                            <br><small class="text-warning">Vence em <?= intval($diasAteVenc) ?> dia(s)</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $classe ?>"><?= ucfirst($f['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($f['status'] !== 'pago' && $f['status'] !== 'cancelado'): ?>
                                        <a href="fatura.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-credit-card me-1"></i> PAGAR
                                        </a>
                                    <?php else: ?>
                                        <small class="text-muted">--</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalFaturasCount > 0): ?>
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">Mostrando <?= count($listaFaturas) ?> de <?= $totalFaturasCount ?> registros</small>
                    <select class="form-select form-select-sm" style="width:auto;" onchange="window.location.href='?page=1&per_page='+this.value+'&filtro_status=<?= urlencode($filtro_status) ?>&filtro_busca=<?= urlencode($filtro_busca) ?>'">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?>/página</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php $baseUrl = '?per_page=' . $perPage . '&filtro_status=' . urlencode($filtro_status) . '&filtro_busca=' . urlencode($filtro_busca); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">«</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>&page=1">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endif;
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">»</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
