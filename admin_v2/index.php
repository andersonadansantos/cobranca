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

// Pendências cadastrais da empresa
$empCampos = [
    'razao_social' => 'Razão Social', 'nome_fantasia' => 'Nome Fantasia', 'cnpj' => 'CNPJ',
    'telefone_comercial' => 'Telefone Comercial', 'email_comercial' => 'E-mail Comercial',
    'cep' => 'CEP', 'logradouro' => 'Logradouro', 'numero' => 'Número',
    'bairro' => 'Bairro', 'cidade' => 'Cidade', 'estado' => 'UF'
];
$empPendentes = [];
$stmtEmp = $pdo->prepare("SELECT " . implode(',', array_keys($empCampos)) . " FROM administradores WHERE id = ?");
$stmtEmp->execute([$adminIdI]);
$dadosEmpresa = $stmtEmp->fetch();
if ($dadosEmpresa) {
    foreach ($empCampos as $chave => $rotulo) {
        if (empty(trim((string)($dadosEmpresa[$chave] ?? '')))) $empPendentes[] = $rotulo;
    }
}

// Métricas Principais
$totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE ativo = 1 AND admin_id = $adminIdI")->fetchColumn();
$totalFaturas = (int)$pdo->query("SELECT COUNT(*) FROM faturas WHERE status IN ('pendente','vencido','atrasado') AND admin_id = $adminIdI")->fetchColumn();
$totalRecebido = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM faturas WHERE status = 'pago' AND MONTH(data_pagamento) = MONTH(NOW()) AND YEAR(data_pagamento) = YEAR(NOW()) AND admin_id = $adminIdI")->fetchColumn();
$totalPendente = (float)$pdo->query("SELECT COALESCE(SUM(valor_final),0) FROM faturas WHERE status IN ('pendente','vencido','atrasado') AND admin_id = $adminIdI")->fetchColumn();

// Receita dos últimos 6 meses (Gráfico de Linha)
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

// Distribuição de status (Gráfico Doughnut)
$statusDist = $pdo->query("
    SELECT status, COUNT(*) AS qtd FROM faturas
    WHERE admin_id = $adminIdI
    GROUP BY status ORDER BY qtd DESC
")->fetchAll();

$chartStatusLabels = [];
$chartStatusValores = [];
$chartStatusCores = [];
$coresStatusFintech = [
    'pago'      => '#10b981', // Sucesso
    'pendente'  => '#f59e0b', // Atenção
    'atrasado'  => '#ef4444', // Perigo
    'vencido'   => '#dc2626', // Perigo
    'cancelado' => '#94a3b8'  // Neutro
];
foreach ($statusDist as $sd) {
    $stNome = strtolower($sd['status']);
    $chartStatusLabels[] = ucfirst($stNome);
    $chartStatusValores[] = intval($sd['qtd']);
    $chartStatusCores[] = $coresStatusFintech[$stNome] ?? '#94a3b8';
}

// Faturas a vencer nos próximos 3 dias
$faturasAlerta = $pdo->query("
    SELECT f.id, f.numero, f.valor_final, f.data_vencimento, c.nome_razao,
           DATEDIFF(f.data_vencimento, CURDATE()) AS dias_restantes
    FROM faturas f JOIN clientes c ON f.cliente_id = c.id
    WHERE f.status IN ('pendente','vencido','atrasado')
      AND f.admin_id = $adminIdI
      AND f.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY f.data_vencimento ASC
")->fetchAll();
$totalAlerta = count($faturasAlerta);
$valorAlerta = array_sum(array_column($faturasAlerta, 'valor_final'));

// Faturas recentes com paginação
$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalFaturasRecentes = (int)$pdo->query("SELECT COUNT(*) FROM faturas WHERE admin_id = $adminIdI")->fetchColumn();
$totalPages = max(1, ceil($totalFaturasRecentes / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmtFats = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.cpf_cnpj 
    FROM faturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    WHERE f.admin_id = ?
    ORDER BY f.criado_em DESC 
    LIMIT ? OFFSET ?
");
$stmtFats->execute([$adminIdI, $perPage, $offset]);
$faturasRecentes = $stmtFats->fetchAll();

// Livro Caixa do mês atual
$mesAtual = intval(date('m'));
$anoAtual = intval(date('Y'));
$lcEntradasManuais = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM livro_caixa_entradas WHERE admin_id = $adminIdI AND YEAR(data) = $anoAtual AND MONTH(data) = $mesAtual")->fetchColumn();
$lcFaturasPagas = (float)$pdo->query("SELECT COALESCE(SUM(valor_final),0) FROM faturas WHERE status = 'pago' AND admin_id = $adminIdI AND YEAR(data_pagamento) = $anoAtual AND MONTH(data_pagamento) = $mesAtual")->fetchColumn();
$lcSaidas = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM livro_caixa_saidas WHERE admin_id = $adminIdI AND YEAR(data) = $anoAtual AND MONTH(data) = $mesAtual")->fetchColumn();
$lcCustos = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM livro_caixa_custos WHERE admin_id = $adminIdI AND YEAR(data) = $anoAtual AND MONTH(data) = $mesAtual")->fetchColumn();
$lcTotalEntradas = $lcEntradasManuais + $lcFaturasPagas;
$lcSaldo = $lcTotalEntradas - $lcSaidas - $lcCustos;

// Informações do Plano
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

// Configurações da página
$pageTitle = 'Painel Executivo';
$pageCategory = 'Visão Geral Financeira';

include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<!-- Banner Informativo de Pendências ou Alertas -->
<?php if ($totalAlerta > 0): ?>
<div class="fintech-card mb-4" style="border-left: 4px solid var(--fintech-warning-dot); background: #fffcf0;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="fintech-kpi-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size:14.5px;">Atenção: <?= $totalAlerta ?> fatura(s) vencendo nos próximos 3 dias</strong>
                <span class="text-muted" style="font-size:12.5px;">Montante total previsto: <strong>R$ <?= number_format($valorAlerta, 2, ',', '.') ?></strong></span>
            </div>
        </div>
        <a href="faturas.php?filtro_status=pendente" class="btn-fintech-secondary btn-sm" style="font-size:12px;">
            Ver Faturas Pendentes <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 4 KPI CARDS PRINCIPAIS -->
<div class="row g-3 mb-4">
    <!-- Receita Liquidada no Mês -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Receita Liquidada (Mês)</div>
                        <div class="fintech-kpi-value text-success">
                            R$ <?= number_format($totalRecebido, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon success">
                        <i class="bi bi-arrow-down-left"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <span class="fintech-badge fintech-badge-pago" style="font-size:10.5px;">Baixas Confirmadas</span>
                </div>
            </div>
            <div class="fintech-kpi-bar">
                <div class="fintech-kpi-bar-fill success" style="width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Valores Pendentes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Valores a Receber</div>
                        <div class="fintech-kpi-value" style="color:var(--fintech-warning-text);">
                            R$ <?= number_format($totalPendente, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <span class="fintech-badge fintech-badge-pendente" style="font-size:10.5px;"><?= $totalFaturas ?> cobrança(s) em aberto</span>
                </div>
            </div>
            <div class="fintech-kpi-bar">
                <div class="fintech-kpi-bar-fill warning" style="width: <?= $totalFaturas > 0 ? '75%' : '0%' ?>;"></div>
            </div>
        </div>
    </div>

    <!-- Base de Clientes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Clientes Ativos</div>
                        <div class="fintech-kpi-value">
                            <?= $totalClientes ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <span class="text-muted" style="font-size:11.5px;"><i class="bi bi-shield-check text-success me-1"></i>Contas habilitadas</span>
                </div>
            </div>
            <div class="fintech-kpi-bar">
                <div class="fintech-kpi-bar-fill primary" style="width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Saldo Líquido (Livro Caixa) -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Saldo Líquido (Livro Caixa)</div>
                        <div class="fintech-kpi-value <?= $lcSaldo >= 0 ? 'text-primary' : 'text-danger' ?>">
                            R$ <?= number_format($lcSaldo, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon" style="background:#e0f2fe;color:#0284c7;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <span class="text-muted" style="font-size:11.5px;">Entradas: <strong>R$ <?= number_format($lcTotalEntradas, 2, ',', '.') ?></strong></span>
                </div>
            </div>
            <div class="fintech-kpi-bar">
                <div class="fintech-kpi-bar-fill" style="background:#0284c7; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ATALHOS RÁPIDOS EXECUTIVOS -->
<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="emissao.php" class="btn-fintech-primary">
        <i class="bi bi-plus-lg"></i> Nova Cobrança / Carnê
    </a>
    <a href="cadastro.php" class="btn-fintech-secondary">
        <i class="bi bi-person-plus"></i> Novo Cliente
    </a>
    <a href="livro_caixa.php" class="btn-fintech-secondary">
        <i class="bi bi-journal-plus"></i> Lançamento Caixa
    </a>
    <a href="faturas.php" class="btn-fintech-secondary">
        <i class="bi bi-search"></i> Consultar Faturas
    </a>
</div>

<!-- SEÇÃO DE GRÁFICOS -->
<div class="row g-4 mb-4">
    <!-- Gráfico de Linha: Histórico de Faturamento -->
    <div class="col-12 col-xl-8">
        <div class="fintech-card h-100">
            <div class="fintech-card-header">
                <div>
                    <h2 class="fintech-card-title">
                        <i class="bi bi-graph-up text-primary"></i> Evolução de Receita Líquida
                    </h2>
                    <span class="text-muted" style="font-size:12px;">Histórico de liquidações nos últimos 6 meses</span>
                </div>
                <span class="fintech-badge" style="background:#f1f5f9;color:#334155;">Mensal</span>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="chartReceitaMes"></canvas>
            </div>
        </div>
    </div>

    <!-- Gráfico Doughnut: Composição da Carteira -->
    <div class="col-12 col-xl-4">
        <div class="fintech-card h-100">
            <div class="fintech-card-header">
                <div>
                    <h2 class="fintech-card-title">
                        <i class="bi bi-pie-chart text-primary"></i> Status da Carteira
                    </h2>
                    <span class="text-muted" style="font-size:12px;">Distribuição de títulos</span>
                </div>
            </div>
            <div style="height: 220px; position: relative;" class="d-flex align-items-center justify-content-center">
                <?php if (!empty($chartStatusValores) && array_sum($chartStatusValores) > 0): ?>
                    <canvas id="chartStatusCarteira"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        <span>Nenhuma fatura registrada</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3 pt-3 border-top d-flex justify-content-around text-center">
                <div>
                    <span class="text-muted d-block" style="font-size:11px;text-transform:uppercase;">Recebido</span>
                    <strong class="text-success" style="font-size:15px;">R$ <?= number_format($totalRecebido, 2, ',', '.') ?></strong>
                </div>
                <div style="border-left: 1px solid var(--fintech-border);"></div>
                <div>
                    <span class="text-muted d-block" style="font-size:11px;text-transform:uppercase;">Pendente</span>
                    <strong style="color:var(--fintech-warning-text);font-size:15px;">R$ <?= number_format($totalPendente, 2, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABELA DE FATURAS RECENTES -->
<div class="fintech-table-container">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-muted"></i>
            <h3 class="fintech-card-title m-0" style="font-size:15px;">Últimas Faturas Emitidas</h3>
        </div>
        <a href="faturas.php" class="btn-fintech-secondary btn-sm" style="font-size:12px;padding:6px 12px;">
            Ver Todas as Faturas (<?= $totalFaturasRecentes ?>) <i class="bi bi-chevron-right ms-1"></i>
        </a>
    </div>

    <div class="table-responsive">
        <table class="fintech-table">
            <thead>
                <tr>
                    <th>Nº Fatura</th>
                    <th>Cliente</th>
                    <th>Descrição</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($faturasRecentes)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-receipt fs-3 d-block mb-2 text-muted"></i>
                        Nenhuma fatura emitida recentemente.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($faturasRecentes as $f): 
                        $st = strtolower($f['status'] ?? 'pendente');
                        $badgeClass = 'fintech-badge-pendente';
                        if ($st === 'pago') $badgeClass = 'fintech-badge-pago';
                        elseif ($st === 'vencido' || $st === 'atrasado') $badgeClass = 'fintech-badge-vencido';
                        elseif ($st === 'cancelado') $badgeClass = 'fintech-badge-cancelado';
                    ?>
                    <tr>
                        <td>
                            <strong class="text-dark font-monospace" style="font-size:12.5px;">
                                #<?= htmlspecialchars($f['numero']) ?>
                            </strong>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($f['nome_razao']) ?></div>
                            <small class="text-muted" style="font-size:11px;"><?= htmlspecialchars($f['cpf_cnpj']) ?></small>
                        </td>
                        <td>
                            <span class="text-secondary"><?= htmlspecialchars($f['descricao']) ?></span>
                        </td>
                        <td>
                            <span class="fintech-table-val">R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></span>
                        </td>
                        <td>
                            <span class="text-dark" style="font-size:13px;"><?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></span>
                        </td>
                        <td>
                            <span class="fintech-badge <?= $badgeClass ?>">
                                <?= ucfirst($st) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if (!empty($f['link_pagamento'])): ?>
                                    <a href="<?= htmlspecialchars($f['link_pagamento']) ?>" target="_blank" class="btn-fintech-icon" title="Abrir Link de Pagamento">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($f['boleto_url'])): ?>
                                    <a href="<?= htmlspecialchars($f['boleto_url']) ?>" target="_blank" class="btn-fintech-icon" title="Visualizar Boleto">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="faturas.php?busca=<?= urlencode($f['numero']) ?>" class="btn-fintech-icon" title="Ver Detalhes na Gestão">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SCRIPTS DOS GRÁFICOS DO PAINEL -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Gráfico de Evolução de Receita
    var ctxReceita = document.getElementById('chartReceitaMes');
    if (ctxReceita) {
        var meses = <?= json_encode($chartMeses) ?>;
        var valores = <?= json_encode($chartValores) ?>;
        
        if (meses.length === 0) {
            meses = ['Mês Atual'];
            valores = [<?= (float)$totalRecebido ?>];
        }

        var gradient = ctxReceita.getContext('2d').createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(15, 23, 42, 0.16)');
        gradient.addColorStop(0.8, 'rgba(15, 23, 42, 0.01)');

        new Chart(ctxReceita, {
            type: 'line',
            data: {
                labels: meses,
                datasets: [{
                    label: 'Receita (R$)',
                    data: valores,
                    borderColor: '#0f172a',
                    borderWidth: 2.5,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.38,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0f172a',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return 'Recebido: R$ ' + ctx.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function (val) {
                                return 'R$ ' + val.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Gráfico Doughnut de Status
    var ctxStatus = document.getElementById('chartStatusCarteira');
    if (ctxStatus) {
        var statusLabels = <?= json_encode($chartStatusLabels) ?>;
        var statusValores = <?= json_encode($chartStatusValores) ?>;
        var statusCores = <?= json_encode($chartStatusCores) ?>;

        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValores,
                    backgroundColor: statusCores,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 12,
                            font: { size: 11, weight: '600' }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

