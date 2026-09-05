<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/inter_pix.php';
requireSuper();
$pdo = getConnection();

if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status, codigo_solicitacao FROM planos_pagamentos WHERE id = ?");
        $stmt->execute([$id]);
        $fatura = $stmt->fetch();
        if ($fatura) {
            $status = strtolower($fatura['status'] ?? '');
            $codigo = $fatura['codigo_solicitacao'] ?? '';
            $cancelErro = '';
            if (!empty($codigo) && $status === 'pendente') {
                $cancel = cancelarCobrancaInterSuper($codigo);
                if (isset($cancel['erro'])) {
                    $cancelErro = $cancel['erro'];
                    error_log("[SUPER-FATURAS] Exclusão #{$id} prossegue apesar de falha ao cancelar no Inter: " . $cancel['erro']);
                }
            }
            $pdo->prepare("DELETE FROM planos_pagamentos WHERE id = ?")->execute([$id]);
            header('Location: faturas.php?msg=' . ($cancelErro === '' ? 'excluido' : 'excluido_sem_cancelar'));
            exit;
        }
    }
}

$mensagem = '';
$tipo = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'excluido') {
        $mensagem = 'Fatura excluída com sucesso! Cobrança cancelada no Banco Inter.';
        $tipo = 'success';
    } elseif ($_GET['msg'] === 'excluido_sem_cancelar') {
        $mensagem = 'Fatura excluída, mas não foi possível cancelar a cobrança no Banco Inter.';
        $tipo = 'warning';
    }
}

$statusFiltro = trim($_GET['status'] ?? '');
$busca = trim($_GET['busca'] ?? '');

$sql = "
    SELECT pp.id, pp.valor, pp.status, pp.criado_em, pp.pago_em, pp.qr_code, pp.pix_copia_cola,
           pp.descricao, pp.duracao_meses,
           a.nome AS admin_nome, a.usuario AS admin_usuario, a.email AS admin_email,
           p.nome AS plano_nome
    FROM planos_pagamentos pp
    LEFT JOIN administradores a ON a.id = pp.admin_id
    LEFT JOIN planos p ON p.id = pp.plano_id
    WHERE 1=1
";
$params = [];
if (!empty($statusFiltro)) {
    $sql .= " AND pp.status = ?";
    $params[] = $statusFiltro;
}
if (!empty($busca)) {
    $sql .= " AND (a.nome LIKE ? OR a.usuario LIKE ? OR p.nome LIKE ? OR CAST(pp.id AS CHAR) LIKE ?)";
    $like = '%' . $busca . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= " ORDER BY pp.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faturas = $stmt->fetchAll();

$totalFaturas = count($faturas);
$totalPago = 0;
$totalPendente = 0;
$somaPago = 0;
foreach ($faturas as $f) {
    if ($f['status'] === 'pago') { $totalPago++; $somaPago += (float)$f['valor']; }
    if ($f['status'] === 'pendente') $totalPendente++;
}
$totalPlanoPagamentos = (int)$pdo->query("SELECT COUNT(*) FROM planos_pagamentos")->fetchColumn();
$periodoLabelSuper = [1 => 'Mensal', 3 => 'Trimestral', 6 => 'Semestral', 12 => 'Anual'];

$pageTitle = 'Faturas';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice me-1"></i> Faturas</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> py-2 alert-dismissible fade show">
                <i class="fas fa-info-circle me-1"></i> <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalFaturas ?></div>
                            <div class="stat-label">Faturas (filtro atual)</div>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-file-invoice"></i></div>
                    </div>
                    <small class="text-muted">Total geral: <?= $totalPlanoPagamentos ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalPago ?></div>
                            <div class="stat-label">Pagas</div>
                        </div>
                        <div class="stat-icon" style="background:#198754;"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalPendente ?></div>
                            <div class="stat-label">Pendentes</div>
                        </div>
                        <div class="stat-icon" style="background:#ffc107;"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($somaPago, 2, ',', '.') ?></div>
                            <div class="stat-label">Total Recebido</div>
                        </div>
                        <div class="stat-icon" style="background:#0d6efd;"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Faturas Geradas pelos Admins</h6>
                <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar admin, plano ou nº..." value="<?= htmlspecialchars($busca) ?>" style="max-width:200px;">
                    <select name="status" class="form-select form-select-sm" style="width:auto;">
                        <option value="">Todos os status</option>
                        <option value="pendente" <?= $statusFiltro === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= $statusFiltro === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="cancelado" <?= $statusFiltro === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="expirado" <?= $statusFiltro === 'expirado' ? 'selected' : '' ?>>Expirado</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    <?php if (!empty($statusFiltro) || !empty($busca)): ?>
                        <a href="faturas.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Admin</th>
                            <th>Plano</th>
                            <th>Período</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Gerada em</th>
                            <th>Paga em</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Nenhuma fatura encontrada</td></tr>
                        <?php else: foreach ($faturas as $f): ?>
                            <?php
                            $classeStatus = '';
                            switch ($f['status']) {
                                case 'pago': $classeStatus = 'badge-pago'; break;
                                case 'pendente': $classeStatus = 'badge-pendente'; break;
                                case 'cancelado': $classeStatus = 'badge-cancelado'; break;
                                case 'expirado': $classeStatus = 'badge-vencido'; break;
                                default: $classeStatus = 'badge-pendente';
                            }
                            ?>
                            <tr>
                                <td><strong>#<?= (int)$f['id'] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($f['admin_nome'] ?: '—') ?>
                                    <br><small class="text-muted">@<?= htmlspecialchars($f['admin_usuario'] ?: '?') ?></small>
                                </td>
                                <td><?= htmlspecialchars($f['plano_nome'] ?: '—') ?></td>
                                <td>
                                    <?php
                                    $durS = (int)($f['duracao_meses'] ?? 1);
                                    $periodoStr = $periodoLabelSuper[$durS] ?? ($durS . ' meses');
                                    ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= htmlspecialchars($periodoStr) ?></span>
                                    <?php if (!empty($f['descricao'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($f['descricao']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong>R$ <?= number_format($f['valor'], 2, ',', '.') ?></strong></td>
                                <td><span class="badge-status <?= $classeStatus ?>"><?= ucfirst(htmlspecialchars($f['status'])) ?></span></td>
                                <td><?= $f['criado_em'] ? date('d/m/Y H:i', strtotime($f['criado_em'])) : '—' ?></td>
                                <td><?= $f['pago_em'] ? date('d/m/Y H:i', strtotime($f['pago_em'])) : '—' ?></td>
                                <td class="text-center">
                                    <a href="#" class="acao-btn acao-btn-danger" title="Excluir" onclick="return confirmarExclusao('Fatura #<?= (int)$f['id'] ?>', 'faturas.php?excluir=<?= (int)$f['id'] ?>');"><i class="bi bi-trash3"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
