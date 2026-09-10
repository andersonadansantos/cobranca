<?php
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

// Mensagens
$mensagem = '';
$tipo = '';
if (isset($_GET['msg'])) {
    $msgs = [
        'excluido' => ['Fatura excluída!', 'warning'],
        'cancelado' => ['Fatura cancelada!', 'info'],
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
$filtro_mes = $_GET['mes'] ?? date('m');
$filtro_ano = $_GET['ano'] ?? date('Y');

$where = [];
$params = [];

$where[] = "f.admin_id = ?";
$params[] = $adminIdF;

if ($filtro_status) {
    $where[] = "f.status = ?";
    $params[] = $filtro_status;
}
if ($filtro_cliente) {
    $where[] = "f.cliente_id = ?";
    $params[] = $filtro_cliente;
}
if ($filtro_mes && $filtro_ano) {
    $where[] = "MONTH(f.data_vencimento) = ? AND YEAR(f.data_vencimento) = ?";
    $params[] = $filtro_mes;
    $params[] = $filtro_ano;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.cpf_cnpj 
    FROM faturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    {$whereClause}
    ORDER BY f.data_vencimento DESC
");
$stmt->execute($params);
$faturas = $stmt->fetchAll();

$stmtCli = $pdo->prepare("SELECT id, nome_razao FROM clientes WHERE ativo = 1 AND admin_id = ? ORDER BY nome_razao");
$stmtCli->execute([$adminIdF]);
$clientes = $stmtCli->fetchAll();

$pageTitle = t('fat.titulo');
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><?= t('fat.titulo') ?></h5>
        </div>
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
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><?= t('filtro.status') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= t('filtro.todos') ?></option>
                        <option value="pendente" <?= $filtro_status === 'pendente' ? 'selected' : '' ?>><?= t('st.pendente') ?></option>
                        <option value="pago" <?= $filtro_status === 'pago' ? 'selected' : '' ?>><?= t('st.pago') ?></option>
                        <option value="vencido" <?= $filtro_status === 'vencido' ? 'selected' : '' ?>><?= t('st.vencido') ?></option>
                        <option value="atrasado" <?= $filtro_status === 'atrasado' ? 'selected' : '' ?>><?= t('st.atrasado') ?></option>
                        <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>><?= t('st.cancelado') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= t('filtro.cliente') ?></label>
                    <select name="cliente_id" class="form-select">
                        <option value=""><?= t('filtro.todos') ?></option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filtro_cliente == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome_razao']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= t('filtro.mes') ?></label>
                    <select name="mes" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $filtro_mes == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>/<?= date('Y') ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= t('filtro.ano') ?></label>
                    <select name="ano" class="form-select">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?= $y ?>" <?= $filtro_ano == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> <?= t('btn.filtrar') ?></button>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-receipt me-2"></i><?= t('fat.lista', [count($faturas)]) ?></h6>
                <a href="export_csv.php?tipo=faturas" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv me-1"></i><?= t('btn.exportar_csv') ?></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?= t('th.numero') ?></th>
                            <th><?= t('th.cliente') ?></th>
                            <th><?= t('th.descricao') ?></th>
                            <th><?= t('th.valor') ?></th>
                            <th><?= t('th.emissao') ?></th>
                            <th><?= t('th.vencimento') ?></th>
                            <th><?= t('th.status') ?></th>
                            <th><?= t('th.acoes') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($faturas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4"><?= t('txt.nenhuma_fatura') ?></td></tr>
                        <?php else: foreach ($faturas as $f): ?>
                            <tr style="<?= $f['status'] === 'cancelado' ? 'opacity:0.55; pointer-events:none;' : '' ?>">
                                <td><strong><?= htmlspecialchars($f['numero']) ?></strong></td>
                                <td><?= htmlspecialchars($f['nome_razao']) ?></td>
                                <td><?= htmlspecialchars($f['descricao']) ?></td>
                                <td>R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y', strtotime($f['data_emissao'])) ?></td>
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
                                <td class="text-nowrap">
                                    <?php if ($f['status'] !== 'cancelado'): ?>
                                    <?php if ($f['pix_copia_cola']): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="copiarPix('<?= htmlspecialchars($f['pix_copia_cola']) ?>')" title="<?= t('fat.copiar_pix') ?>">
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($f['link_pagamento']): ?>
                                        <a href="<?= htmlspecialchars($f['link_pagamento']) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="<?= t('fat.link_pagamento') ?>">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($f['status'] !== 'pago'): ?>
                                        <a href="?cancelar=<?= $f['id'] ?>" class="btn btn-sm btn-outline-warning" title="<?= t('fat.cancelar') ?>" onclick="showConfirm(<?= htmlspecialchars(json_encode(t('fat.tit_cancelar')), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode(t('fat.confirm_cancelar', [$f['numero']])), ENT_QUOTES) ?>, '?cancelar=<?= $f['id'] ?>'); return false;">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                        <small class="text-muted">--</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

