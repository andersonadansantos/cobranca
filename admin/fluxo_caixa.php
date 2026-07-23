<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();

$entradasMes = $pdo->query("
    SELECT COALESCE(SUM(valor_final), 0) FROM faturas WHERE status = 'pago' AND MONTH(data_pagamento) = MONTH(NOW()) AND YEAR(data_pagamento) = YEAR(NOW())
")->fetchColumn();

$saidasMes = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM livro_caixa_saidas WHERE MONTH(data) = MONTH(NOW()) AND YEAR(data) = YEAR(NOW())")->fetchColumn();
$custosMes = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM livro_caixa_custos WHERE MONTH(data) = MONTH(NOW()) AND YEAR(data) = YEAR(NOW())")->fetchColumn();
$saldoMes = $entradasMes - $saidasMes - $custosMes;

$prox30 = $pdo->query("
    SELECT f.numero, f.valor_final, f.data_vencimento, c.nome_razao,
           DATEDIFF(f.data_vencimento, NOW()) AS dias
    FROM faturas f JOIN clientes c ON f.cliente_id = c.id
    WHERE f.status IN ('pendente','vencido','atrasado')
      AND f.data_vencimento <= DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY f.data_vencimento ASC
")->fetchAll();

$prox60 = $pdo->query("
    SELECT f.numero, f.valor_final, f.data_vencimento, c.nome_razao,
           DATEDIFF(f.data_vencimento, NOW()) AS dias
    FROM faturas f JOIN clientes c ON f.cliente_id = c.id
    WHERE f.status IN ('pendente','vencido','atrasado')
      AND f.data_vencimento > DATE_ADD(NOW(), INTERVAL 30 DAY)
      AND f.data_vencimento <= DATE_ADD(NOW(), INTERVAL 60 DAY)
    ORDER BY f.data_vencimento ASC
")->fetchAll();

$prox90 = $pdo->query("
    SELECT f.numero, f.valor_final, f.data_vencimento, c.nome_razao,
           DATEDIFF(f.data_vencimento, NOW()) AS dias
    FROM faturas f JOIN clientes c ON f.cliente_id = c.id
    WHERE f.status IN ('pendente','vencido','atrasado')
      AND f.data_vencimento > DATE_ADD(NOW(), INTERVAL 60 DAY)
      AND f.data_vencimento <= DATE_ADD(NOW(), INTERVAL 90 DAY)
    ORDER BY f.data_vencimento ASC
")->fetchAll();

$total30 = array_sum(array_column($prox30, 'valor_final'));
$total60 = array_sum(array_column($prox60, 'valor_final'));
$total90 = array_sum(array_column($prox90, 'valor_final'));
$totalGeral = $total30 + $total60 + $total90;

$pageTitle = 'Fluxo de Caixa';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Fluxo de Caixa</h5>
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
                <div class="stat-card" style="border-left-color: var(--cor-sucesso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($entradasMes, 2, ',', '.') ?></div>
                            <div class="stat-label">Entradas (Mês)</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-sucesso);"><i class="fas fa-arrow-down"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-perigo);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($saidasMes + $custosMes, 2, ',', '.') ?></div>
                            <div class="stat-label">Saídas (Mês)</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-arrow-up"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: <?= $saldoMes >= 0 ? 'var(--cor-sucesso)' : 'var(--cor-perigo)' ?>;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($saldoMes, 2, ',', '.') ?></div>
                            <div class="stat-label">Saldo (Mês)</div>
                        </div>
                        <div class="stat-icon" style="background: <?= $saldoMes >= 0 ? 'var(--cor-sucesso)' : 'var(--cor-perigo)' ?>;"><i class="fas fa-balance-scale"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: var(--cor-aviso);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($totalGeral, 2, ',', '.') ?></div>
                            <div class="stat-label">Projeção 90 dias</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-aviso);"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><span class="badge bg-danger me-2"><?= count($prox30) ?></span>Próximos 30 dias</h6>
                    </div>
                    <div class="p-3">
                        <div class="text-center mb-3">
                            <small class="text-muted">Total Previsto</small>
                            <div class="fs-5 fw-bold text-danger">R$ <?= number_format($total30, 2, ',', '.') ?></div>
                        </div>
                        <?php if (empty($prox30)): ?>
                            <div class="text-center text-muted py-3"><i class="fas fa-check-circle me-1"></i>Nenhuma fatura</div>
                        <?php else: foreach ($prox30 as $fp): ?>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size:0.85rem;">
                                <div>
                                    <strong><?= htmlspecialchars($fp['numero']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($fp['nome_razao']) ?></small>
                                </div>
                                <div class="text-end">
                                    R$ <?= number_format($fp['valor_final'], 2, ',', '.') ?><br>
                                    <small class="text-muted"><?= date('d/m', strtotime($fp['data_vencimento'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><span class="badge bg-warning text-dark me-2"><?= count($prox60) ?></span>31 a 60 dias</h6>
                    </div>
                    <div class="p-3">
                        <div class="text-center mb-3">
                            <small class="text-muted">Total Previsto</small>
                            <div class="fs-5 fw-bold text-warning">R$ <?= number_format($total60, 2, ',', '.') ?></div>
                        </div>
                        <?php if (empty($prox60)): ?>
                            <div class="text-center text-muted py-3"><i class="fas fa-check-circle me-1"></i>Nenhuma fatura</div>
                        <?php else: foreach ($prox60 as $fp): ?>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size:0.85rem;">
                                <div>
                                    <strong><?= htmlspecialchars($fp['numero']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($fp['nome_razao']) ?></small>
                                </div>
                                <div class="text-end">
                                    R$ <?= number_format($fp['valor_final'], 2, ',', '.') ?><br>
                                    <small class="text-muted"><?= date('d/m', strtotime($fp['data_vencimento'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><span class="badge bg-info me-2"><?= count($prox90) ?></span>61 a 90 dias</h6>
                    </div>
                    <div class="p-3">
                        <div class="text-center mb-3">
                            <small class="text-muted">Total Previsto</small>
                            <div class="fs-5 fw-bold text-info">R$ <?= number_format($total90, 2, ',', '.') ?></div>
                        </div>
                        <?php if (empty($prox90)): ?>
                            <div class="text-center text-muted py-3"><i class="fas fa-check-circle me-1"></i>Nenhuma fatura</div>
                        <?php else: foreach ($prox90 as $fp): ?>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size:0.85rem;">
                                <div>
                                    <strong><?= htmlspecialchars($fp['numero']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($fp['nome_razao']) ?></small>
                                </div>
                                <div class="text-end">
                                    R$ <?= number_format($fp['valor_final'], 2, ',', '.') ?><br>
                                    <small class="text-muted"><?= date('d/m', strtotime($fp['data_vencimento'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>