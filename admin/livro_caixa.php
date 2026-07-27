<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$mensagem = '';
$tipo = '';

// Filtro ano/mes
$anoFiltro = intval($_GET['ano'] ?? date('Y'));
$mesFiltro = intval($_GET['mes'] ?? date('m'));
$meses = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// Ações CRUD
if (isset($_GET['excluir_custo'])) {
    $id = intval($_GET['excluir_custo']);
    $stmt = $pdo->prepare("DELETE FROM livro_caixa_custos WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: livro_caixa.php?ano=' . $anoFiltro . '&mes=' . $mesFiltro . '&msg=excluido');
    exit;
}
if (isset($_GET['msg'])) {
    $msgs = ['excluido' => ['Custo excluído!', 'warning']];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_entrada') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!empty($descricao) && $valor > 0) {
            $stmt = $pdo->prepare("INSERT INTO livro_caixa_entradas (descricao, valor, data) VALUES (?, ?, ?)");
            $stmt->execute([$descricao, $valor, $data]);
            $mensagem = 'Entrada adicionada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Preencha descrição e valor.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'add_saida') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!empty($descricao) && $valor > 0) {
            $stmt = $pdo->prepare("INSERT INTO livro_caixa_saidas (descricao, valor, data) VALUES (?, ?, ?)");
            $stmt->execute([$descricao, $valor, $data]);
            $mensagem = 'Saída adicionada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Preencha descrição e valor.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'add_custo') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!empty($descricao) && $valor > 0) {
            $stmt = $pdo->prepare("INSERT INTO livro_caixa_custos (descricao, valor, data) VALUES (?, ?, ?)");
            $stmt->execute([$descricao, $valor, $data]);
            $mensagem = 'Custo fixo adicionado com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Preencha descrição e valor.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'excluir_entrada') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM livro_caixa_entradas WHERE id = ?");
        $stmt->execute([$id]);
        $mensagem = 'Entrada excluída!';
        $tipo = 'warning';
    }
    if ($acao === 'excluir_saida') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM livro_caixa_saidas WHERE id = ?");
        $stmt->execute([$id]);
        $mensagem = 'Saída excluída!';
        $tipo = 'warning';
    }
    if ($acao === 'salvar_custos_pago') {
        $pagos = $_POST['pago'] ?? [];
        $custoIds = $_POST['custo_ids'] ?? [];
        foreach ($custoIds as $cid) {
            $cid = intval($cid);
            $pago = isset($pagos[$cid]) ? 1 : 0;
            if ($pago) {
                $stmt = $pdo->prepare("UPDATE livro_caixa_custos SET pago_mes = ?, pago_ano = ? WHERE id = ?");
                $stmt->execute([$mesFiltro, $anoFiltro, $cid]);
            } else {
                $stmt = $pdo->prepare("UPDATE livro_caixa_custos SET pago_mes = NULL, pago_ano = NULL WHERE id = ? AND pago_mes = ? AND pago_ano = ?");
                $stmt->execute([$cid, $mesFiltro, $anoFiltro]);
            }
        }
        $mensagem = 'Pagamento dos custos atualizado!';
        $tipo = 'success';
    }
}

// Buscar dados filtrados por ano/mês
$stmt = $pdo->prepare("SELECT * FROM livro_caixa_entradas WHERE YEAR(data) = ? AND MONTH(data) = ? ORDER BY data DESC");
$stmt->execute([$anoFiltro, $mesFiltro]);
$entradas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_saidas WHERE YEAR(data) = ? AND MONTH(data) = ? ORDER BY data DESC");
$stmt->execute([$anoFiltro, $mesFiltro]);
$saidas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_custos WHERE YEAR(data) = ? ORDER BY data DESC");
$stmt->execute([$anoFiltro]);
$custos = $stmt->fetchAll();

// Faturas pagas no mês (entradas automáticas)
$stmt = $pdo->prepare("SELECT f.id, f.numero, f.descricao, f.valor_final AS valor, f.data_pagamento AS data, c.nome_razao FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.status = 'pago' AND YEAR(f.data_pagamento) = ? AND MONTH(f.data_pagamento) = ? ORDER BY f.data_pagamento DESC");
$stmt->execute([$anoFiltro, $mesFiltro]);
$faturasPagas = $stmt->fetchAll();

$totalEntradasManuais = array_sum(array_column($entradas, 'valor'));
$totalFaturasPagas = array_sum(array_column($faturasPagas, 'valor'));
$totalEntradas = $totalEntradasManuais + $totalFaturasPagas;
$totalSaidas = array_sum(array_column($saidas, 'valor'));
$totalCustos = array_sum(array_column($custos, 'valor'));
$saldo = $totalEntradas - $totalSaidas - $totalCustos;

// Anos disponíveis
$anosDisponiveis = [];
$stmtAnos = $pdo->query("SELECT ano FROM (SELECT YEAR(data) AS ano FROM livro_caixa_entradas UNION SELECT YEAR(data) AS ano FROM livro_caixa_saidas UNION SELECT YEAR(data) AS ano FROM livro_caixa_custos UNION SELECT YEAR(data_pagamento) AS ano FROM faturas WHERE data_pagamento IS NOT NULL) AS t GROUP BY ano ORDER BY ano DESC");
foreach ($stmtAnos->fetchAll() as $row) {
    $anosDisponiveis[] = $row['ano'];
}
$anoAtual = intval(date('Y'));
if (!in_array($anoAtual, $anosDisponiveis)) $anosDisponiveis[] = $anoAtual;
if (!in_array($anoAtual + 1, $anosDisponiveis)) $anosDisponiveis[] = $anoAtual + 1;
sort($anosDisponiveis, SORT_NUMERIC);
$anosDisponiveis = array_reverse($anosDisponiveis);

$pageTitle = 'Livro Caixa';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Livro Caixa</h5>
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
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- FILTRO ANO/MÊS + SALDO -->
        <div class="form-card mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Ano</label>
                    <select id="filtroAno" class="form-select" onchange="aplicarFiltro()">
                        <?php foreach ($anosDisponiveis as $a): ?>
                            <option value="<?= $a ?>" <?= $a == $anoFiltro ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mês</label>
                    <select id="filtroMes" class="form-select" onchange="aplicarFiltro()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $mesFiltro ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Entradas</label>
                    <div class="form-control bg-success bg-opacity-10 text-success fw-bold">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Saídas</label>
                    <div class="form-control bg-danger bg-opacity-10 text-danger fw-bold">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Custos Fixos</label>
                    <div class="form-control bg-warning bg-opacity-10 text-warning fw-bold">R$ <?= number_format($totalCustos, 2, ',', '.') ?></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Saldo</label>
                    <div class="form-control <?= $saldo >= 0 ? 'bg-primary bg-opacity-10 text-primary' : 'bg-danger bg-opacity-10 text-danger' ?> fw-bold">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- GRÁFICO ENTRADAS x SAÍDAS -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="table-card" style="height:100%;">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Visão Geral</h6>
                    </div>
                    <div class="p-3 d-flex align-items-center justify-content-center" style="height:260px;">
                        <canvas id="chartLivroCaixa"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="table-card" style="height:100%;">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Comparativo Mensal</h6>
                    </div>
                    <div class="p-3" style="height:260px;">
                        <canvas id="chartComparativo"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTÕES DOWNLOAD -->
        <div class="d-flex gap-2 mb-3">
            <button onclick="downloadPDF()" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf me-1"></i> Baixar PDF</button>
            <button onclick="downloadExcel()" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel me-1"></i> Baixar Excel</button>
        </div>

        <!-- ABAS -->
        <ul class="nav nav-tabs" id="livroCaixaTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="entradas-tab" data-bs-toggle="tab" data-bs-target="#entradas" type="button" role="tab">
                    <i class="fas fa-arrow-down me-1 text-success"></i> Entradas <span class="badge bg-success"><?= count($entradas) + count($faturasPagas) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="saidas-tab" data-bs-toggle="tab" data-bs-target="#saidas" type="button" role="tab">
                    <i class="fas fa-arrow-up me-1 text-danger"></i> Saídas <span class="badge bg-danger"><?= count($saidas) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="custos-tab" data-bs-toggle="tab" data-bs-target="#custos" type="button" role="tab">
                    <i class="fas fa-wrench me-1 text-warning"></i> Custos Mensais Fixos <span class="badge bg-warning text-dark"><?= count($custos) ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content p-3 form-card rounded-top-0" id="livroCaixaTabContent">

            <!-- ==================== ENTRADAS ==================== -->
            <div class="tab-pane fade show active" id="entradas" role="tabpanel">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="acao" value="add_entrada">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" placeholder="Ex: Serviço prestado" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus me-1"></i> Adicionar</button>
                        </div>
                    </div>
                </form>

                <!-- Faturas Pagas (automáticas) -->
                <?php if (!empty($faturasPagas)): ?>
                    <h6 class="text-muted mb-2"><i class="fas fa-file-invoice-dollar me-1"></i> Faturas Pagas (automático)</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Nº</th><th>Cliente</th><th>Descrição</th><th class="text-end">Valor</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faturasPagas as $fp): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($fp['data'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($fp['numero']) ?></span></td>
                                        <td><?= htmlspecialchars($fp['nome_razao']) ?></td>
                                        <td><?= htmlspecialchars($fp['descricao']) ?></td>
                                        <td class="text-end text-success fw-bold">R$ <?= number_format($fp['valor'], 2, ',', '.') ?></td>
                                        <td><small class="text-muted">automático</small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Entradas Manuais -->
                <h6 class="text-muted mb-2"><i class="fas fa-hand-holding me-1"></i> Entradas Manuais</h6>
                <?php if (empty($entradas)): ?>
                    <div class="text-center text-muted py-3">Nenhuma entrada manual neste período</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Descrição</th><th class="text-end">Valor</th><th class="text-end">Ação</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entradas as $e): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($e['data'])) ?></td>
                                        <td><?= htmlspecialchars($e['descricao']) ?></td>
                                        <td class="text-end text-success fw-bold">R$ <?= number_format($e['valor'], 2, ',', '.') ?></td>
                                        <td class="text-end">
                                             <form method="POST" class="d-inline" id="formExcluirEntrada<?= $e['id'] ?>">
                                                 <input type="hidden" name="acao" value="excluir_entrada">
                                                 <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                                 <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="showConfirmForm('Excluir Entrada','Excluir esta entrada?','formExcluirEntrada<?= $e['id'] ?>')"><i class="fas fa-trash"></i></button>
                                             </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-success"><td colspan="2"><strong>Total Entradas Manuais</strong></td><td class="text-end fw-bold">R$ <?= number_format($totalEntradasManuais, 2, ',', '.') ?></td><td></td></tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==================== SAÍDAS ==================== -->
            <div class="tab-pane fade" id="saidas" role="tabpanel">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="acao" value="add_saida">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" placeholder="Ex: Compra de material" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-danger w-100"><i class="fas fa-plus me-1"></i> Adicionar</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($saidas)): ?>
                    <div class="text-center text-muted py-3">Nenhuma saída neste período</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Descrição</th><th class="text-end">Valor</th><th class="text-end">Ação</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saidas as $s): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($s['data'])) ?></td>
                                        <td><?= htmlspecialchars($s['descricao']) ?></td>
                                        <td class="text-end text-danger fw-bold">R$ <?= number_format($s['valor'], 2, ',', '.') ?></td>
                                        <td class="text-end">
                                             <form method="POST" class="d-inline" id="formExcluirSaida<?= $s['id'] ?>">
                                                 <input type="hidden" name="acao" value="excluir_saida">
                                                 <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                 <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="showConfirmForm('Excluir Saída','Excluir esta saída?','formExcluirSaida<?= $s['id'] ?>')"><i class="fas fa-trash"></i></button>
                                             </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-danger"><td colspan="2"><strong>Total Saídas</strong></td><td class="text-end fw-bold">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></td><td></td></tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==================== CUSTOS MENSAIS FIXOS ==================== -->
            <div class="tab-pane fade" id="custos" role="tabpanel">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="acao" value="add_custo">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" placeholder="Ex: Aluguel, Internet, etc." required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning w-100"><i class="fas fa-plus me-1"></i> Adicionar</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($custos)): ?>
                    <div class="text-center text-muted py-3">Nenhum custo fixo neste período</div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="acao" value="salvar_custos_pago">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead class="table-light">
                                    <tr><th>Data</th><th>Descrição</th><th class="text-center">Pago este mês?</th><th class="text-end">Valor</th><th class="text-end">Ação</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($custos as $c): ?>
                                        <?php $jaPago = ($c['pago_mes'] == $mesFiltro && $c['pago_ano'] == $anoFiltro); ?>
                                        <input type="hidden" name="custo_ids[]" value="<?= $c['id'] ?>">
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($c['data'])) ?></td>
                                            <td><?= htmlspecialchars($c['descricao']) ?></td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input class="form-check-input custo-pago-check" type="checkbox" name="pago[<?= $c['id'] ?>]" value="1" <?= $jaPago ? 'checked' : '' ?> data-id="<?= $c['id'] ?>" style="cursor:pointer; <?= $jaPago ? 'background-color:#198754; border-color:#198754;' : '' ?>">
                                                </div>
                                            </td>
                                            <td class="text-end text-warning fw-bold">R$ <?= number_format($c['valor'], 2, ',', '.') ?></td>
                                            <td class="text-end">
                                                 <a href="?ano=<?= $anoFiltro ?>&mes=<?= $mesFiltro ?>&excluir_custo=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="showConfirm('Excluir Custo','Excluir este custo mensal?','?ano=<?= $anoFiltro ?>&mes=<?= $mesFiltro ?>&excluir_custo=<?= $c['id'] ?>'); return false;"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-warning"><td colspan="3"><strong>Total Custos Fixos</strong></td><td class="text-end fw-bold">R$ <?= number_format($totalCustos, 2, ',', '.') ?></td><td></td></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="text-end mt-2">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i> Salvar Pagamentos</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var totalEntradasLC = <?= $totalEntradas ?>;
var totalSaidasLC = <?= $totalSaidas ?>;
var totalCustosLC = <?= $totalCustos ?>;
var saldoLC = <?= $saldo ?>;

new Chart(document.getElementById('chartLivroCaixa'), {
    type: 'doughnut',
    data: {
        labels: ['Entradas', 'Saídas', 'Custos Fixos'],
        datasets: [{
            data: [totalEntradasLC, totalSaidasLC, totalCustosLC],
            backgroundColor: ['#198754', '#dc3545', '#ffc107'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 12 } } }
        }
    }
});

new Chart(document.getElementById('chartComparativo'), {
    type: 'bar',
    data: {
        labels: ['Entradas', 'Saídas', 'Custos Fixos', 'Saldo'],
        datasets: [{
            label: 'Valor (R$)',
            data: [totalEntradasLC, totalSaidasLC, totalCustosLC, saldoLC],
            backgroundColor: [
                'rgba(25,135,84,0.75)',
                'rgba(220,53,69,0.75)',
                'rgba(255,193,7,0.75)',
                saldoLC >= 0 ? 'rgba(13,110,253,0.75)' : 'rgba(220,53,69,0.75)'
            ],
            borderColor: ['#198754', '#dc3545', '#ffc107', saldoLC >= 0 ? '#0d6efd' : '#dc3545'],
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

function aplicarFiltro() {
    var ano = document.getElementById('filtroAno').value;
    var mes = document.getElementById('filtroMes').value;
    window.location.href = 'livro_caixa.php?ano=' + ano + '&mes=' + mes;
}

document.querySelectorAll('.custo-pago-check').forEach(function(el) {
    el.addEventListener('change', function() {
        if (this.checked) {
            this.style.backgroundColor = '#198754';
            this.style.borderColor = '#198754';
        } else {
            this.style.backgroundColor = '';
            this.style.borderColor = '';
        }
    });
});

function downloadPDF() {
    var ano = <?= $anoFiltro ?>;
    var mes = <?= $mesFiltro ?>;
    var mesNome = '<?= $meses[$mesFiltro] ?>';
    var w = window.open('', '_blank');
    var html = '<!DOCTYPE html><html><head><title>Livro Caixa - ' + mesNome + ' ' + ano + '</title>';
    html += '<style>body{font-family:Arial,sans-serif;padding:20px;color:#333;}';
    html += 'h1{font-size:18px;text-align:center;margin-bottom:5px;}';
    html += 'h2{font-size:14px;text-align:center;color:#666;margin-top:0;}';
    html += 'table{width:100%;border-collapse:collapse;margin:10px 0 20px 0;font-size:12px;}';
    html += 'th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;}';
    html += 'th{background:#f5f5f5;font-weight:bold;}';
    html += '.text-right{text-align:right;}';
    html += '.text-success{color:#198754;}.text-danger{color:#dc3545;}.text-warning{color:#ffc107;}';
    html += '.text-primary{color:#0d6efd;}.fw-bold{font-weight:bold;}';
    html += '.totals{margin-top:20px;border-top:2px solid #333;padding-top:10px;}';
    html += '.totals div{display:flex;justify-content:space-between;padding:4px 0;font-size:13px;}';
    html += '@media print{body{padding:10px;}}</style></head><body>';
    html += '<h1>Livro Caixa</h1><h2>' + mesNome + ' / ' + ano + '</h2>';

    html += '<table><thead><tr><th colspan="4" style="background:#d4edda;text-align:center;">ENTRADAS</th></tr>';
    html += '<tr><th>Data</th><th>Descrição</th><th>Ref</th><th class="text-right">Valor</th></tr></thead><tbody>';
    <?php foreach ($faturasPagas as $fp): ?>
    html += '<tr><td><?= date('d/m/Y', strtotime($fp['data'])) ?></td><td><?= addslashes(htmlspecialchars($fp['descricao'])) ?></td><td><?= addslashes(htmlspecialchars($fp['numero'])) ?></td><td class="text-right text-success">R$ <?= number_format($fp['valor'], 2, ',', '.') ?></td></tr>';
    <?php endforeach; ?>
    <?php foreach ($entradas as $e): ?>
    html += '<tr><td><?= date('d/m/Y', strtotime($e['data'])) ?></td><td><?= addslashes(htmlspecialchars($e['descricao'])) ?></td><td>-</td><td class="text-right text-success">R$ <?= number_format($e['valor'], 2, ',', '.') ?></td></tr>';
    <?php endforeach; ?>
    html += '<tr><td colspan="3" class="fw-bold">Total Entradas</td><td class="text-right fw-bold text-success">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></td></tr>';
    html += '</tbody></table>';

    html += '<table><thead><tr><th colspan="3" style="background:#f8d7da;text-align:center;">SAÍDAS</th></tr>';
    html += '<tr><th>Data</th><th>Descrição</th><th class="text-right">Valor</th></tr></thead><tbody>';
    <?php foreach ($saidas as $s): ?>
    html += '<tr><td><?= date('d/m/Y', strtotime($s['data'])) ?></td><td><?= addslashes(htmlspecialchars($s['descricao'])) ?></td><td class="text-right text-danger">R$ <?= number_format($s['valor'], 2, ',', '.') ?></td></tr>';
    <?php endforeach; ?>
    html += '<tr><td colspan="2" class="fw-bold">Total Saídas</td><td class="text-right fw-bold text-danger">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></td></tr>';
    html += '</tbody></table>';

    html += '<table><thead><tr><th colspan="3" style="background:#fff3cd;text-align:center;">CUSTOS MENSAIS FIXOS</th></tr>';
    html += '<tr><th>Data</th><th>Descrição</th><th class="text-right">Valor</th></tr></thead><tbody>';
    <?php foreach ($custos as $c): ?>
    html += '<tr><td><?= date('d/m/Y', strtotime($c['data'])) ?></td><td><?= addslashes(htmlspecialchars($c['descricao'])) ?></td><td class="text-right text-warning">R$ <?= number_format($c['valor'], 2, ',', '.') ?></td></tr>';
    <?php endforeach; ?>
    html += '<tr><td colspan="2" class="fw-bold">Total Custos Fixos</td><td class="text-right fw-bold text-warning">R$ <?= number_format($totalCustos, 2, ',', '.') ?></td></tr>';
    html += '</tbody></table>';

    html += '<div class="totals">';
    html += '<div><span>Total Entradas:</span><span class="text-success fw-bold">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></span></div>';
    html += '<div><span>Total Saídas:</span><span class="text-danger fw-bold">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></span></div>';
    html += '<div><span>Total Custos Fixos:</span><span class="text-warning fw-bold">R$ <?= number_format($totalCustos, 2, ',', '.') ?></span></div>';
    html += '<div style="border-top:2px solid #333;padding-top:6px;margin-top:6px;"><span><strong>SALDO:</strong></span><span class="<?= $saldo >= 0 ? 'text-success' : 'text-danger' ?> fw-bold"><strong>R$ <?= number_format($saldo, 2, ',', '.') ?></strong></span></div>';
    html += '</div>';
    html += '<script>window.onload=function(){window.print();}<\/script></body></html>';
    w.document.write(html);
    w.document.close();
}

function downloadExcel() {
    var ano = <?= $anoFiltro ?>;
    var mes = <?= $mesFiltro ?>;
    var mesNome = '<?= $meses[$mesFiltro] ?>';
    var csv = '\uFEFFData;Descrição;Tipo;Referência;Valor\n';
    <?php foreach ($faturasPagas as $fp): ?>
    csv += '<?= date('d/m/Y', strtotime($fp['data'])) ?>;<?= addslashes($fp['descricao']) ?>;Entrada;<?= addslashes($fp['numero']) ?>;<?= number_format($fp['valor'], 2, ',', '.') ?>\n';
    <?php endforeach; ?>
    <?php foreach ($entradas as $e): ?>
    csv += '<?= date('d/m/Y', strtotime($e['data'])) ?>;<?= addslashes($e['descricao']) ?>;Entrada Manual;-;<?= number_format($e['valor'], 2, ',', '.') ?>\n';
    <?php endforeach; ?>
    <?php foreach ($saidas as $s): ?>
    csv += '<?= date('d/m/Y', strtotime($s['data'])) ?>;<?= addslashes($s['descricao']) ?>;Saída;-;<?= number_format($s['valor'], 2, ',', '.') ?>\n';
    <?php endforeach; ?>
    <?php foreach ($custos as $c): ?>
    csv += '<?= date('d/m/Y', strtotime($c['data'])) ?>;<?= addslashes($c['descricao']) ?>;Custo Fixo;-;<?= number_format($c['valor'], 2, ',', '.') ?>\n';
    <?php endforeach; ?>

    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'livro_caixa_' + mes + '_' + ano + '.csv';
    link.click();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
