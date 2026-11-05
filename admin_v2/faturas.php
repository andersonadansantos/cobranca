<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../includes/lang_painel.php';

$pdo = getConnection();
$adminIdF = (int)$_SESSION['admin_id'];

// Cancelar fatura
if (isset($_GET['cancelar'])) {
    $id = intval($_GET['cancelar']);
    $stFat = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ? AND status != 'pago'");
    $stFat->execute([$id, $adminIdF]);
    $faturaCancel = $stFat->fetch();

    if ($faturaCancel) {
        cancelarCobrancaFatura($faturaCancel);
    }

    $stmt = $pdo->prepare("UPDATE faturas SET status = 'cancelado' WHERE id = ? AND admin_id = ? AND status != 'pago'");
    $stmt->execute([$id, $adminIdF]);
    header('Location: faturas.php?msg=cancelado');
    exit;
}

// Marcar fatura como paga
if (isset($_GET['marcar_pago'])) {
    $id = intval($_GET['marcar_pago']);
    $stVer = $pdo->prepare("SELECT id FROM faturas WHERE id = ? AND admin_id = ?");
    $stVer->execute([$id, $adminIdF]);
    if ($stVer->fetch()) {
        require_once __DIR__ . '/../config/email_helpers.php';
        confirmarPagamentoEBaixarFatura($id, [
            'gateway' => 'pix_manual',
            'tipo_pagamento' => 'pix_manual',
            'status_gateway' => 'manual',
            'status_detail' => 'Confirmação manual pelo administrador'
        ]);
    }
    header('Location: faturas.php?msg=pago');
    exit;
}

// Mensagens
$mensagem = '';
$tipo = '';
if (isset($_GET['msg'])) {
    $msgs = [
        'excluido' => ['Fatura excluída com sucesso!', 'warning'],
        'cancelado' => ['Fatura cancelada com sucesso!', 'info'],
        'pago' => ['Fatura liquidada com sucesso!', 'success'],
        'gerada' => ['Fatura gerada com sucesso!', 'success'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_cliente = intval($_GET['cliente_id'] ?? 0);
$filtro_mes = $_GET['mes'] ?? '';
$filtro_ano = $_GET['ano'] ?? date('Y');
$filtro_busca = trim($_GET['busca'] ?? '');

$where = ["f.admin_id = ?"];
$params = [$adminIdF];

if ($filtro_status !== '') {
    $where[] = "f.status = ?";
    $params[] = $filtro_status;
}
if ($filtro_cliente > 0) {
    $where[] = "f.cliente_id = ?";
    $params[] = $filtro_cliente;
}
if ($filtro_mes !== '') {
    $where[] = "MONTH(f.data_vencimento) = ?";
    $params[] = $filtro_mes;
}
if ($filtro_ano !== '') {
    $where[] = "YEAR(f.data_vencimento) = ?";
    $params[] = $filtro_ano;
}
if ($filtro_busca !== '') {
    $where[] = "(f.numero LIKE ? OR c.nome_razao LIKE ? OR c.cpf_cnpj LIKE ? OR f.descricao LIKE ?)";
    $params[] = "%{$filtro_busca}%";
    $params[] = "%{$filtro_busca}%";
    $params[] = "%{$filtro_busca}%";
    $params[] = "%{$filtro_busca}%";
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.cpf_cnpj, c.email, c.celular 
    FROM faturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    {$whereClause}
    ORDER BY f.data_vencimento DESC, f.numero DESC
");
$stmt->execute($params);
$faturas = $stmt->fetchAll();

// Totalizadores dos filtros
$totalRegistros = count($faturas);
$totalValor = array_sum(array_column($faturas, 'valor_final'));

// Clientes para filtro
$stmtCli = $pdo->prepare("SELECT id, nome_razao FROM clientes WHERE ativo = 1 AND admin_id = ? ORDER BY nome_razao");
$stmtCli->execute([$adminIdF]);
$clientes = $stmtCli->fetchAll();

$pageTitle = 'Gestão de Faturas';
$pageCategory = 'Financeiro';

include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo ?> alert-dismissible fade show border-0 shadow-sm mb-4" style="border-radius:12px;">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- RESUMO RÁPIDO DOS RESULTADOS -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="fintech-kpi-card" style="padding:16px 20px;">
            <div class="fintech-kpi-label">Faturas no Filtro</div>
            <div class="fintech-kpi-value" style="font-size:24px;"><?= $totalRegistros ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="fintech-kpi-card" style="padding:16px 20px;">
            <div class="fintech-kpi-label">Valor Total dos Títulos</div>
            <div class="fintech-kpi-value text-primary" style="font-size:24px;">R$ <?= number_format($totalValor, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4 d-flex align-items-center justify-content-md-end gap-2">
        <a href="emissao.php" class="btn-fintech-primary w-100 w-md-auto justify-content-center">
            <i class="bi bi-plus-lg"></i> Nova Cobrança
        </a>
    </div>
</div>

<!-- BARRA DE FILTROS EXECUTIVOS -->
<div class="fintech-card mb-4" style="padding:18px 22px;">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-xl-4">
            <label class="fintech-form-label" style="font-size:11px;text-transform:uppercase;">Status da Cobrança</label>
            <div class="fintech-filter-pills">
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>" class="fintech-pill-btn <?= $filtro_status === '' ? 'active' : '' ?>">Todos</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'pago'])) ?>" class="fintech-pill-btn <?= $filtro_status === 'pago' ? 'active' : '' ?>">
                    <span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span> Pagos
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'pendente'])) ?>" class="fintech-pill-btn <?= $filtro_status === 'pendente' ? 'active' : '' ?>">
                    <span style="width:6px;height:6px;border-radius:50%;background:#f59e0b;display:inline-block;"></span> Pendentes
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'vencido'])) ?>" class="fintech-pill-btn <?= $filtro_status === 'vencido' ? 'active' : '' ?>">
                    <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;display:inline-block;"></span> Vencidos
                </a>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <label class="fintech-form-label" style="font-size:11px;text-transform:uppercase;">Cliente</label>
            <select name="cliente_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">Todos os clientes</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filtro_cliente === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome_razao']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-sm-3 col-xl-2">
            <label class="fintech-form-label" style="font-size:11px;text-transform:uppercase;">Mês</label>
            <select name="mes" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): $mPad = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $mPad ?>" <?= $filtro_mes === $mPad ? 'selected' : '' ?>><?= tMes($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-6 col-sm-3 col-xl-2">
            <label class="fintech-form-label" style="font-size:11px;text-transform:uppercase;">Ano</label>
            <select name="ano" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($y = date('Y') + 1; $y >= date('Y') - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $filtro_ano == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-12 col-xl-1 text-end">
            <a href="faturas.php" class="btn-fintech-secondary btn-sm w-100 justify-content-center" title="Limpar Filtros">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </form>
</div>

<!-- TABELA DE FATURAS -->
<div class="fintech-table-container">
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
                <?php if (empty($faturas)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Nenhuma fatura encontrada com os filtros selecionados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($faturas as $f): 
                        $st = strtolower($f['status'] ?? 'pendente');
                        $badgeClass = 'fintech-badge-pendente';
                        if ($st === 'pago') $badgeClass = 'fintech-badge-pago';
                        elseif ($st === 'vencido' || $st === 'atrasado') $badgeClass = 'fintech-badge-vencido';
                        elseif ($st === 'cancelado') $badgeClass = 'fintech-badge-cancelado';
                    ?>
                    <tr>
                        <td>
                            <strong class="font-monospace text-dark" style="font-size:13px;">#<?= htmlspecialchars($f['numero']) ?></strong>
                            <?php if (!empty($f['parcela_atual'])): ?>
                                <span class="badge bg-light text-secondary border d-block mt-1" style="font-size:9.5px;width:fit-content;">
                                    Parcela <?= $f['parcela_atual'] ?>/<?= $f['total_parcelas'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="d-block text-dark"><?= htmlspecialchars($f['nome_razao']) ?></strong>
                            <small class="text-muted"><?= htmlspecialchars($f['cpf_cnpj']) ?></small>
                        </td>
                        <td>
                            <span class="text-dark fw-medium"><?= htmlspecialchars($f['descricao']) ?></span>
                        </td>
                        <td>
                            <span class="fintech-table-val">R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></span>
                        </td>
                        <td>
                            <span class="text-dark"><?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></span>
                            <?php if ($st === 'pago' && !empty($f['data_pagamento'])): ?>
                                <small class="text-success d-block" style="font-size:11px;">Pago em <?= date('d/m/Y', strtotime($f['data_pagamento'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fintech-badge <?= $badgeClass ?>">
                                <?= ucfirst($st) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if (!empty($f['link_pagamento'])): ?>
                                    <a href="<?= htmlspecialchars($f['link_pagamento']) ?>" target="_blank" class="btn-fintech-icon" title="Abrir Link da Fatura">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($f['boleto_url'])): ?>
                                    <a href="<?= htmlspecialchars($f['boleto_url']) ?>" target="_blank" class="btn-fintech-icon" title="Visualizar Boleto">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($st !== 'pago'): ?>
                                    <a href="faturas.php?marcar_pago=<?= $f['id'] ?>" class="btn-fintech-icon text-success" title="Marcar como Pago" onclick="return confirm('Deseja marcar esta fatura como liquidada?');">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                    <a href="faturas.php?cancelar=<?= $f['id'] ?>" class="btn-fintech-icon text-danger" title="Cancelar Fatura" onclick="return confirm('Deseja cancelar esta fatura?');">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

