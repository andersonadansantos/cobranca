<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();

$totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes WHERE ativo = 1")->fetchColumn();
$totalFaturas = $pdo->query("SELECT COUNT(*) FROM faturas WHERE status IN ('pendente','vencido','atrasado')")->fetchColumn();
$totalRecebido = $pdo->query("SELECT COALESCE(SUM(valor),0) FROM faturas WHERE status = 'pago' AND MONTH(data_pagamento) = MONTH(NOW()) AND YEAR(data_pagamento) = YEAR(NOW())")->fetchColumn();
$totalPendente = $pdo->query("SELECT COALESCE(SUM(valor_final),0) FROM faturas WHERE status IN ('pendente','vencido','atrasado')")->fetchColumn();

$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalFaturasRecentes = $pdo->query("SELECT COUNT(*) FROM faturas")->fetchColumn();
$totalPages = max(1, ceil($totalFaturasRecentes / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$faturasRecentes = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.cpf_cnpj 
    FROM faturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    ORDER BY f.criado_em DESC 
    LIMIT ? OFFSET ?
");
$faturasRecentes->execute([$perPage, $offset]);
$faturasRecentes = $faturasRecentes->fetchAll();

$pageTitle = 'Painel Geral';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Painel Geral</h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalClientes ?></div>
                            <div class="stat-label">Clientes Cadastrados</div>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-aviso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalFaturas ?></div>
                            <div class="stat-label">Faturas em Aberto</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-aviso);"><i class="fas fa-file-invoice"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-sucesso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($totalRecebido, 2, ',', '.') ?></div>
                            <div class="stat-label">Recebido no Mês</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-sucesso);"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-perigo);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($totalPendente, 2, ',', '.') ?></div>
                            <div class="stat-label">Total Pendente</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Faturas Recentes</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturasRecentes)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma fatura encontrada</td></tr>
                        <?php else: foreach ($faturasRecentes as $f): ?>
                            <tr style="<?= $f['status'] === 'cancelado' ? 'opacity:0.55; pointer-events:none;' : '' ?>">
                                <td><strong><?= htmlspecialchars($f['numero']) ?></strong></td>
                                <td><?= htmlspecialchars($f['nome_razao']) ?></td>
                                <td>R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></td>
                                <td>
                                    <?php
                                    $classes = [
                                        'pendente' => 'badge-pendente',
                                        'pago' => 'badge-pago',
                                        'atrasado' => 'badge-atrasado',
                                        'cancelado' => 'badge-cancelado',
                                        'vencido' => 'badge-vencido'
                                    ];
                                    $classe = $classes[$f['status']] ?? 'badge-pendente';
                                    ?>
                                    <span class="badge-status <?= $classe ?>"><?= ucfirst($f['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($f['status'] !== 'cancelado'): ?>
                                    <a href="faturas.php?cliente_id=<?= $f['cliente_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
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
            <?php if ($totalFaturasRecentes > 0): ?>
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">Mostrando <?= count($faturasRecentes) ?> de <?= $totalFaturasRecentes ?> registros</small>
                    <select class="form-select form-select-sm" style="width:auto;" onchange="window.location.href='?page=1&per_page='+this.value">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?>/página</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?>">«</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1&per_page=<?= $perPage ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endif;
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&per_page=<?= $perPage ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?>&per_page=<?= $perPage ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?>">»</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
