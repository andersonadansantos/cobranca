<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/lang_painel.php';

$pdo = getConnection();
$adminIdI = (int)$_SESSION['admin_id'];

$empCampos = ['razao_social'=>'Razão Social','nome_fantasia'=>'Nome Fantasia','cnpj'=>'CNPJ','telefone_comercial'=>'Telefone Comercial','email_comercial'=>'E-mail Comercial','cep'=>'CEP','logradouro'=>'Logradouro','numero'=>'Número','bairro'=>'Bairro','cidade'=>'Cidade','estado'=>'UF'];
$empPendentes = [];
$stmtEmp = $pdo->prepare("SELECT " . implode(',', array_keys($empCampos)) . " FROM administradores WHERE id = ?");
$stmtEmp->execute([$adminIdI]);
$dadosEmpresa = $stmtEmp->fetch();
if ($dadosEmpresa) {
    foreach ($empCampos as $chave => $rotulo) {
        if (empty(trim((string)($dadosEmpresa[$chave] ?? '')))) $empPendentes[] = $rotulo;
    }
}

$totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND admin_id = $adminIdI")->fetchColumn();
$totalFaturas = $pdo->query("SELECT COUNT(*) FROM faturas WHERE status IN ('pendente','vencido','atrasado') AND admin_id = $adminIdI")->fetchColumn();
$totalRecebido = $pdo->query("SELECT COALESCE(SUM(valor),0) FROM faturas WHERE status = 'pago' AND MONTH(data_pagamento) = MONTH(NOW()) AND YEAR(data_pagamento) = YEAR(NOW()) AND admin_id = $adminIdI")->fetchColumn();
$totalPendente = $pdo->query("SELECT COALESCE(SUM(valor_final),0) FROM faturas WHERE status IN ('pendente','vencido','atrasado') AND admin_id = $adminIdI")->fetchColumn();

$receitaMes = $pdo->query("
    SELECT MONTH(data_pagamento) AS mes, YEAR(data_pagamento) AS ano, SUM(valor_final) AS total
    FROM faturas WHERE status = 'pago' AND data_pagamento IS NOT NULL AND admin_id = $adminIdI
    AND data_pagamento >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(data_pagamento), MONTH(data_pagamento)
    ORDER BY ano, mes
")->fetchAll();

$chartMeses = [];
$chartValores = [];
foreach ($receitaMes as $rm) {
    $chartMeses[] = tMesAbbr($rm['mes']) . '/' . substr($rm['ano'], -2);
    $chartValores[] = floatval($rm['total']);
}

$statusDist = $pdo->query("
    SELECT status, COUNT(*) AS qtd FROM faturas
    WHERE admin_id = $adminIdI
    GROUP BY status ORDER BY qtd DESC
")->fetchAll();

$chartStatusLabels = [];
$chartStatusValores = [];
$chartStatusCores = [];
$coreStatus = ['pago'=>'#198754','pendente'=>'#ffc107','atrasado'=>'#dc3545','vencido'=>'#fd7e14','cancelado'=>'#6c757d'];
foreach ($statusDist as $sd) {
    $chartStatusLabels[] = ucfirst($sd['status']);
    $chartStatusValores[] = intval($sd['qtd']);
    $chartStatusCores[] = $coreStatus[$sd['status']] ?? '#6c757d';
}

$faturasAlerta = $pdo->query("
    SELECT f.numero, f.valor_final, f.data_vencimento, c.nome_razao,
           DATEDIFF(f.data_vencimento, CURDATE()) AS dias_restantes
    FROM faturas f JOIN clientes c ON f.cliente_id = c.id
    WHERE f.status IN ('pendente','vencido','atrasado')
      AND f.admin_id = $adminIdI
      AND f.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY f.data_vencimento ASC
")->fetchAll();
$totalAlerta = count($faturasAlerta);
$valorAlerta = array_sum(array_column($faturasAlerta, 'valor_final'));

$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalFaturasRecentes = $pdo->query("SELECT COUNT(*) FROM faturas WHERE admin_id = $adminIdI")->fetchColumn();
$totalPages = max(1, ceil($totalFaturasRecentes / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$faturasRecentes = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.cpf_cnpj 
    FROM faturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    WHERE f.admin_id = ?
    ORDER BY f.criado_em DESC 
    LIMIT ? OFFSET ?
");
$faturasRecentes->execute([$adminIdI, $perPage, $offset]);
$faturasRecentes = $faturasRecentes->fetchAll();

$pageTitle = t('dash.titulo');
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';

$mesAtual = intval(date('m'));
$anoAtual = intval(date('Y'));

$lcEntradasManuais = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS total FROM livro_caixa_entradas WHERE admin_id = ? AND YEAR(data) = ? AND MONTH(data) = ?");
$lcEntradasManuais->execute([$adminIdI, $anoAtual, $mesAtual]);
$lcTotalEntradasManuais = floatval($lcEntradasManuais->fetchColumn());

$lcFaturasPagas = $pdo->prepare("SELECT COALESCE(SUM(valor_final),0) AS total FROM faturas WHERE status = 'pago' AND admin_id = ? AND YEAR(data_pagamento) = ? AND MONTH(data_pagamento) = ?");
$lcFaturasPagas->execute([$adminIdI, $anoAtual, $mesAtual]);
$lcTotalFaturasPagas = floatval($lcFaturasPagas->fetchColumn());

$lcSaidas = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS total FROM livro_caixa_saidas WHERE admin_id = ? AND YEAR(data) = ? AND MONTH(data) = ?");
$lcSaidas->execute([$adminIdI, $anoAtual, $mesAtual]);
$lcTotalSaidas = floatval($lcSaidas->fetchColumn());

$lcCustos = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS total FROM livro_caixa_custos WHERE admin_id = ? AND YEAR(data) = ? AND MONTH(data) = ?");
$lcCustos->execute([$adminIdI, $anoAtual, $mesAtual]);
$lcTotalCustos = floatval($lcCustos->fetchColumn());

$lcTotalEntradas = $lcTotalEntradasManuais + $lcTotalFaturasPagas;
$lcSaldo = $lcTotalEntradas - $lcTotalSaidas - $lcTotalCustos;

$stmtPlano = $pdo->prepare("
    SELECT p.nome AS plano_nome, p.cor AS plano_cor, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$stmtPlano->execute([$adminIdI]);
$meuPlanoInfo = $stmtPlano->fetch();

$diasRestantes = null;
if ($meuPlanoInfo && !empty($meuPlanoInfo['data_fim'])) {
    $diasRestantes = (int)floor((strtotime($meuPlanoInfo['data_fim']) - strtotime(date('Y-m-d'))) / 86400);
}
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><?= t('dash.titulo') ?></h5>
        </div>
        <?php if (!empty($meuPlanoInfo)): ?>
        <span class="d-inline-flex align-items-center gap-1 me-2 py-1 px-2 rounded-pill" style="font-size:0.78rem;border:1px solid #dee2e6;<?= ($diasRestantes !== null && $diasRestantes <= 7) ? 'background:#fff3cd;color:#856404;border-color:#ffc107;' : 'background:#e8f5ee;color:#0f7b5c;' ?>">
            <i class="fas fa-hourglass-half me-1"></i>
            <?php if ($diasRestantes === null): ?>
                <?= t('tb.plano') ?> <strong class="ms-1"><?= htmlspecialchars($meuPlanoInfo['plano_nome']) ?></strong>
            <?php elseif ($diasRestantes <= 0): ?>
                <strong class="ms-1"><?= t('tb.plano_vencido') ?></strong> · <a href="/cobranca/admin/minhas_faturas.php" class="fw-bold" style="text-decoration:underline;"><?= t('tb.renovar') ?></a>
            <?php elseif ($diasRestantes === 1): ?>
                <strong class="ms-1"><?= t('tb.vence_hoje') ?></strong>
                <span class="text-muted">· <?= htmlspecialchars($meuPlanoInfo['plano_nome']) ?></span>
                <a href="/cobranca/admin/minhas_faturas.php" class="btn btn-sm btn-warning fw-bold ms-1" style="font-size:0.7rem;"><?= t('tb.renovar') ?></a>
            <?php else: ?>
                <strong class="ms-1"><?= t('tb.dias_restantes', [$diasRestantes]) ?></strong>
                <span class="text-muted">· <?= htmlspecialchars($meuPlanoInfo['plano_nome']) ?></span>
                <?php if ($diasRestantes <= 7): ?>
                    <a href="/cobranca/admin/minhas_faturas.php" class="btn btn-sm btn-warning fw-bold ms-1" style="font-size:0.7rem;"><?= t('tb.renovar') ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </span>
        <?php endif; ?>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> <?= t('tb.suporte') ?></a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i><?= t('tb.editar_perfil') ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i><?= t('tb.sair') ?></a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if (!empty($empPendentes)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="border-left:4px solid #dc3545;">
            <i class="fas fa-exclamation-triangle me-3" style="font-size:1.2rem;"></i>
            <div>
                <strong><?= t('dash.alert_emp_incompletos') ?></strong>
                <br><small><?= t('dash.alert_campos_faltando', [htmlspecialchars(implode(', ', $empPendentes))]) ?>
                <a href="perfil.php" class="alert-link"><i class="fa-solid fa-wand-magic-sparkles ms-1"></i><?= t('dash.preencher_agora') ?></a></small>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($totalAlerta > 0): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert" style="border-left:4px solid #ffc107;">
            <i class="fas fa-bell me-3" style="font-size:1.2rem;"></i>
            <div>
                <?= t('dash.alert_vencendo', [$totalAlerta, number_format($valorAlerta, 2, ',', '.')]) ?>
                <br><small>
                <?php foreach ($faturasAlerta as $fa): ?>
                    <?= htmlspecialchars($fa['numero']) ?> (<?= htmlspecialchars($fa['nome_razao']) ?>) — <?= t('dash.vence_em', [$fa['dias_restantes'] == 0 ? t('dash.hoje') : $fa['dias_restantes']]) ?>&nbsp;&nbsp;
                <?php endforeach; ?>
                </small>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalClientes ?></div>
                            <div class="stat-label"><?= t('dash.clientes') ?></div>
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
                            <div class="stat-label"><?= t('dash.faturas_aberto') ?></div>
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
                            <div class="stat-label"><?= t('dash.recebido_mes') ?></div>
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
                            <div class="stat-label"><?= t('dash.total_pendente') ?></div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i><?= t('dash.receita_mensal') ?></h6>
                    </div>
                    <div class="p-3" style="height:280px;">
                        <canvas id="chartReceita"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i><?= t('dash.status_faturas') ?></h6>
                    </div>
                    <div class="p-3" style="height:280px;">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="table-card" style="height:100%;">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-book me-2"></i><?= t('dash.livro_caixa_mes', [tMes($mesAtual) . '/' . $anoAtual]) ?></h6>
                    </div>
                    <div class="p-3" style="height:240px;">
                        <canvas id="chartLivroCaixaMini"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-card" style="height:100%;">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-coins me-2"></i><?= t('dash.resumo_livro') ?></h6>
                    </div>
                    <div class="p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-success"><i class="fas fa-arrow-down me-1"></i> <?= t('dash.entradas') ?></span>
                            <strong class="text-success">R$ <?= number_format($lcTotalEntradas, 2, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-danger"><i class="fas fa-arrow-up me-1"></i> <?= t('dash.saidas') ?></span>
                            <strong class="text-danger">R$ <?= number_format($lcTotalSaidas, 2, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-warning"><i class="fas fa-wrench me-1"></i> <?= t('dash.custos_fixos') ?></span>
                            <strong class="text-warning">R$ <?= number_format($lcTotalCustos, 2, ',', '.') ?></strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <strong><?= t('dash.saldo') ?></strong>
                            <strong class="<?= $lcSaldo >= 0 ? 'text-primary' : 'text-danger' ?>">R$ <?= number_format($lcSaldo, 2, ',', '.') ?></strong>
                        </div>
                        <div class="text-center mt-3">
                            <a href="livro_caixa.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i> <?= t('dash.ver_livro') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i><?= t('dash.faturas_recentes') ?></h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?= t('th.numero') ?></th>
                            <th><?= t('th.cliente') ?></th>
                            <th><?= t('th.valor') ?></th>
                            <th><?= t('th.vencimento') ?></th>
                            <th><?= t('th.status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturasRecentes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><?= t('txt.nenhuma_fatura') ?></td></tr>
                        <?php else: foreach ($faturasRecentes as $f): ?>
                            <tr style="<?= $f['status'] === 'cancelado' ? 'opacity:0.55;' : '' ?>">
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
                                    <span class="badge-status <?= $classe ?>"><?= tStatus($f['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalFaturasRecentes > 0): ?>
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted"><?= t('dash.quant_registros', [count($faturasRecentes), $totalFaturasRecentes]) ?></small>
                    <select class="form-select form-select-sm" style="width:auto;" onchange="window.location.href='?page=1&per_page='+this.value">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= t('dash.pagina', [$opt]) ?></option>
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

<script>
new Chart(document.getElementById('chartReceita'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartMeses) ?>,
        datasets: [{
            label: <?= json_encode(t('dash.receita_r')) ?>,
            data: <?= json_encode($chartValores) ?>,
            backgroundColor: 'rgba(25,135,84,0.7)',
            borderColor: '#198754',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } }
        }
    }
});
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartStatusLabels) ?>,
        datasets: [{
            data: <?= json_encode($chartStatusValores) ?>,
            backgroundColor: <?= json_encode($chartStatusCores) ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8 } } }
    }
});
new Chart(document.getElementById('chartLivroCaixaMini'), {
    type: 'bar',
    data: {
        labels: <?= json_encode([t('dash.entradas'), t('dash.saidas'), t('dash.custos_fixos'), t('dash.saldo')]) ?>,
        datasets: [{
            label: <?= json_encode(t('dash.valor_r')) ?>,
            data: [<?= $lcTotalEntradas ?>, <?= $lcTotalSaidas ?>, <?= $lcTotalCustos ?>, <?= $lcSaldo ?>],
            backgroundColor: [
                'rgba(25,135,84,0.75)',
                'rgba(220,53,69,0.75)',
                'rgba(255,193,7,0.75)',
                <?= $lcSaldo >= 0 ? "'rgba(13,110,253,0.75)'" : "'rgba(220,53,69,0.75)'" ?>
            ],
            borderColor: ['#198754', '#dc3545', '#ffc107', <?= $lcSaldo >= 0 ? "'#0f7b5c'" : "'#dc3545'" ?>],
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
