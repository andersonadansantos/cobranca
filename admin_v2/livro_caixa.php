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
$adminIdL = (int)$_SESSION['admin_id'];
$mensagem = '';
$tipo = '';

// Filtro ano/mês
$anoFiltro = intval($_GET['ano'] ?? date('Y'));
$mesFiltro = intval($_GET['mes'] ?? date('m'));
$meses = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

// Ações CRUD
if (isset($_GET['excluir_custo'])) {
    $id = intval($_GET['excluir_custo']);
    $stmt = $pdo->prepare("DELETE FROM livro_caixa_custos WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdL]);
    header('Location: livro_caixa.php?ano=' . $anoFiltro . '&mes=' . $mesFiltro . '&msg=excluido');
    exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['excluido' => ['Item excluído com sucesso!', 'warning']];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_entrada') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valorRaw = trim((string)($_POST['valor'] ?? '0'));
        $valorLimpo = strpos($valorRaw, ',') !== false ? str_replace(',', '.', preg_replace('/[^\d,]/', '', $valorRaw)) : preg_replace('/[^\d.]/', '', $valorRaw);
        $valor = round((float)$valorLimpo, 2);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!empty($descricao) && $valor > 0) {
            $stmt = $pdo->prepare("INSERT INTO livro_caixa_entradas (descricao, valor, data, admin_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$descricao, $valor, $data, $adminIdL]);
            $mensagem = 'Entrada registrada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Preencha a descrição e um valor válido.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'add_saida') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valorRaw = trim((string)($_POST['valor'] ?? '0'));
        $valorLimpo = strpos($valorRaw, ',') !== false ? str_replace(',', '.', preg_replace('/[^\d,]/', '', $valorRaw)) : preg_replace('/[^\d.]/', '', $valorRaw);
        $valor = round((float)$valorLimpo, 2);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!empty($descricao) && $valor > 0) {
            $stmt = $pdo->prepare("INSERT INTO livro_caixa_saidas (descricao, valor, data, admin_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$descricao, $valor, $data, $adminIdL]);
            $mensagem = 'Saída registrada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Preencha a descrição e um valor válido.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'add_custo') {
        $descricao = trim($_POST['descricao'] ?? '');
        $valorRaw = trim((string)($_POST['valor'] ?? '0'));
        $valorLimpo = strpos($valorRaw, ',') !== false ? str_replace(',', '.', preg_replace('/[^\d,]/', '', $valorRaw)) : preg_replace('/[^\d.]/', '', $valorRaw);
        $valor = round((float)$valorLimpo, 2);
        $data = $_POST['data'] ?? date('Y-m-d');
        if (!empty($descricao) && $valor > 0) {
            $stmt = $pdo->prepare("INSERT INTO livro_caixa_custos (descricao, valor, data, admin_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$descricao, $valor, $data, $adminIdL]);
            $mensagem = 'Custo fixo registrado com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Preencha a descrição e um valor válido.';
            $tipo = 'danger';
        }
    }

    if ($acao === 'excluir_entrada') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM livro_caixa_entradas WHERE id = ? AND admin_id = ?");
        $stmt->execute([$id, $adminIdL]);
        $mensagem = 'Entrada excluída!';
        $tipo = 'warning';
    }
    if ($acao === 'excluir_saida') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM livro_caixa_saidas WHERE id = ? AND admin_id = ?");
        $stmt->execute([$id, $adminIdL]);
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
                $stmt = $pdo->prepare("UPDATE livro_caixa_custos SET pago_mes = ?, pago_ano = ? WHERE id = ? AND admin_id = ?");
                $stmt->execute([$mesFiltro, $anoFiltro, $cid, $adminIdL]);
            } else {
                $stmt = $pdo->prepare("UPDATE livro_caixa_custos SET pago_mes = NULL, pago_ano = NULL WHERE id = ? AND admin_id = ? AND pago_mes = ? AND pago_ano = ?");
                $stmt->execute([$cid, $adminIdL, $mesFiltro, $anoFiltro]);
            }
        }
        $mensagem = 'Status de pagamento dos custos atualizado!';
        $tipo = 'success';
    }
}

// Buscar dados filtrados por ano/mês
$stmt = $pdo->prepare("SELECT * FROM livro_caixa_entradas WHERE admin_id = ? AND YEAR(data) = ? AND MONTH(data) = ? ORDER BY data DESC");
$stmt->execute([$adminIdL, $anoFiltro, $mesFiltro]);
$entradas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_saidas WHERE admin_id = ? AND YEAR(data) = ? AND MONTH(data) = ? ORDER BY data DESC");
$stmt->execute([$adminIdL, $anoFiltro, $mesFiltro]);
$saidas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_custos WHERE admin_id = ? AND YEAR(data) = ? ORDER BY data DESC");
$stmt->execute([$adminIdL, $anoFiltro]);
$custos = $stmt->fetchAll();

// Faturas pagas no mês (entradas automáticas de cobrança)
$stmt = $pdo->prepare("SELECT f.id, f.numero, f.descricao, f.valor_final AS valor, f.data_pagamento AS data, c.nome_razao FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.admin_id = ? AND f.status = 'pago' AND YEAR(f.data_pagamento) = ? AND MONTH(f.data_pagamento) = ? ORDER BY f.data_pagamento DESC");
$stmt->execute([$adminIdL, $anoFiltro, $mesFiltro]);
$faturasPagas = $stmt->fetchAll();

$totalEntradasManuais = array_sum(array_column($entradas, 'valor'));
$totalFaturasPagas = array_sum(array_column($faturasPagas, 'valor'));
$totalEntradas = $totalEntradasManuais + $totalFaturasPagas;
$totalSaidas = array_sum(array_column($saidas, 'valor'));
$totalCustos = array_sum(array_column($custos, 'valor'));
$saldo = $totalEntradas - $totalSaidas - $totalCustos;

$pageTitle = 'Livro Caixa & Fluxo Operacional';
$pageCategory = 'Gestão Financeira';

include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo ?> alert-dismissible fade show border-0 shadow-sm mb-4" style="border-radius:12px;">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- 4 KPI CARDS DO FLUXO DE CAIXA -->
<div class="row g-3 mb-4">
    <!-- Entradas Totais -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Entradas Totais</div>
                        <div class="fintech-kpi-value text-success">
                            R$ <?= number_format($totalEntradas, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon success">
                        <i class="bi bi-arrow-down-left"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <small class="text-muted">Faturas: R$ <?= number_format($totalFaturasPagas, 2, ',', '.') ?></small>
                </div>
            </div>
            <div class="fintech-kpi-bar"><div class="fintech-kpi-bar-fill success" style="width: 100%;"></div></div>
        </div>
    </div>

    <!-- Saídas Operacionais -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Saídas / Despesas</div>
                        <div class="fintech-kpi-value text-danger">
                            R$ <?= number_format($totalSaidas, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon danger">
                        <i class="bi bi-arrow-up-right"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <small class="text-muted"><?= count($saidas) ?> lançamento(s)</small>
                </div>
            </div>
            <div class="fintech-kpi-bar"><div class="fintech-kpi-bar-fill danger" style="width: 100%;"></div></div>
        </div>
    </div>

    <!-- Custos Fixos -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Custos Fixos / Recorrentes</div>
                        <div class="fintech-kpi-value" style="color:var(--fintech-warning-text);">
                            R$ <?= number_format($totalCustos, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon warning">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <small class="text-muted"><?= count($custos) ?> custo(s) cadastrado(s)</small>
                </div>
            </div>
            <div class="fintech-kpi-bar"><div class="fintech-kpi-bar-fill warning" style="width: 100%;"></div></div>
        </div>
    </div>

    <!-- Saldo Líquido -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="fintech-kpi-card">
            <div>
                <div class="fintech-kpi-top">
                    <div>
                        <div class="fintech-kpi-label">Resultado Operacional</div>
                        <div class="fintech-kpi-value <?= $saldo >= 0 ? 'text-primary' : 'text-danger' ?>">
                            R$ <?= number_format($saldo, 2, ',', '.') ?>
                        </div>
                    </div>
                    <div class="fintech-kpi-icon" style="background:#e0f2fe;color:#0284c7;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="fintech-kpi-meta">
                    <span class="fintech-badge <?= $saldo >= 0 ? 'fintech-badge-pago' : 'fintech-badge-vencido' ?>" style="font-size:10px;">
                        <?= $saldo >= 0 ? 'Superávit' : 'Déficit' ?>
                    </span>
                </div>
            </div>
            <div class="fintech-kpi-bar"><div class="fintech-kpi-bar-fill" style="background:#0284c7; width: 100%;"></div></div>
        </div>
    </div>
</div>

<!-- BARRA DE AÇÕES E FILTROS DE COMPETÊNCIA -->
<div class="fintech-card mb-4" style="padding:18px 22px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <form method="GET" class="d-flex align-items-center gap-2">
            <span class="text-muted fw-semibold" style="font-size:12px;text-transform:uppercase;">Competência:</span>
            <select name="mes" class="form-select form-select-sm" style="width:130px;" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mesFiltro ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="ano" class="form-select form-select-sm" style="width:100px;" onchange="this.form.submit()">
                <?php for ($y = date('Y') + 1; $y >= date('Y') - 4; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $anoFiltro ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>

        <div class="d-flex gap-2">
            <button type="button" class="btn-fintech-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEntrada">
                <i class="bi bi-plus-circle"></i> Nova Entrada
            </button>
            <button type="button" class="btn-fintech-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSaida">
                <i class="bi bi-dash-circle"></i> Nova Saída
            </button>
            <button type="button" class="btn-fintech-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCusto">
                <i class="bi bi-gear"></i> Custo Fixo
            </button>
        </div>
    </div>
</div>

<!-- TABELAS EM ABAS EXECUTIVAS -->
<div class="fintech-table-container">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
        <h3 class="fintech-card-title m-0" style="font-size:15px;">
            <i class="bi bi-list-check text-muted"></i> Movimentações do Período: <?= $meses[$mesFiltro] ?> / <?= $anoFiltro ?>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="fintech-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Origem</th>
                    <th>Valor</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Entradas de Faturas Pagas -->
                <?php foreach ($faturasPagas as $fp): ?>
                <tr>
                    <td><span class="text-dark"><?= date('d/m/Y', strtotime($fp['data'])) ?></span></td>
                    <td><span class="fintech-badge fintech-badge-pago">Entrada</span></td>
                    <td>
                        <strong class="text-dark"><?= htmlspecialchars($fp['descricao']) ?></strong>
                        <small class="text-muted d-block">Cliente: <?= htmlspecialchars($fp['nome_razao']) ?></small>
                    </td>
                    <td><span class="badge bg-light text-secondary border font-monospace">Fatura #<?= $fp['numero'] ?></span></td>
                    <td><span class="fintech-table-val text-success">+ R$ <?= number_format($fp['valor'], 2, ',', '.') ?></span></td>
                    <td class="text-end"><span class="text-muted" style="font-size:11px;">Automático</span></td>
                </tr>
                <?php endforeach; ?>

                <!-- Entradas Manuais -->
                <?php foreach ($entradas as $e): ?>
                <tr>
                    <td><span class="text-dark"><?= date('d/m/Y', strtotime($e['data'])) ?></span></td>
                    <td><span class="fintech-badge fintech-badge-pago">Entrada Manual</span></td>
                    <td><strong class="text-dark"><?= htmlspecialchars($e['descricao']) ?></strong></td>
                    <td><span class="badge bg-light text-secondary border">Lançamento</span></td>
                    <td><span class="fintech-table-val text-success">+ R$ <?= number_format($e['valor'], 2, ',', '.') ?></span></td>
                    <td class="text-end">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir esta entrada?');">
                            <input type="hidden" name="acao" value="excluir_entrada">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn-fintech-icon text-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Saídas -->
                <?php foreach ($saidas as $s): ?>
                <tr>
                    <td><span class="text-dark"><?= date('d/m/Y', strtotime($s['data'])) ?></span></td>
                    <td><span class="fintech-badge fintech-badge-vencido">Saída</span></td>
                    <td><strong class="text-dark"><?= htmlspecialchars($s['descricao']) ?></strong></td>
                    <td><span class="badge bg-light text-secondary border">Despesa</span></td>
                    <td><span class="fintech-table-val text-danger">- R$ <?= number_format($s['valor'], 2, ',', '.') ?></span></td>
                    <td class="text-end">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir esta saída?');">
                            <input type="hidden" name="acao" value="excluir_saida">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-fintech-icon text-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($faturasPagas) && empty($entradas) && empty($saidas)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Nenhuma movimentação registrada nesta competência.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NOVA ENTRADA -->
<div class="modal fade" id="modalEntrada" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="acao" value="add_entrada">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle text-success me-2"></i>Nova Entrada no Caixa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="fintech-form-label">Descrição *</label>
                    <input type="text" name="descricao" class="form-control" placeholder="Ex: Recebimento de serviço avulso" required>
                </div>
                <div class="col-md-6">
                    <label class="fintech-form-label">Valor (R$) *</label>
                    <input type="text" name="valor" class="form-control" placeholder="R$ 0,00" required oninput="mascaraMoeda(this)">
                </div>
                <div class="col-md-6">
                    <label class="fintech-form-label">Data *</label>
                    <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-fintech-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-fintech-primary">Salvar Entrada</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NOVA SAÍDA -->
<div class="modal fade" id="modalSaida" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="acao" value="add_saida">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-dash-circle text-danger me-2"></i>Nova Saída / Despesa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="fintech-form-label">Descrição *</label>
                    <input type="text" name="descricao" class="form-control" placeholder="Ex: Pagamento de hospedagem" required>
                </div>
                <div class="col-md-6">
                    <label class="fintech-form-label">Valor (R$) *</label>
                    <input type="text" name="valor" class="form-control" placeholder="R$ 0,00" required oninput="mascaraMoeda(this)">
                </div>
                <div class="col-md-6">
                    <label class="fintech-form-label">Data *</label>
                    <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-fintech-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-fintech-primary">Salvar Saída</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NOVO CUSTO FIXO -->
<div class="modal fade" id="modalCusto" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="acao" value="add_custo">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear text-primary me-2"></i>Novo Custo Fixo Recorrente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="fintech-form-label">Descrição *</label>
                    <input type="text" name="descricao" class="form-control" placeholder="Ex: Aluguel do escritório ou Internet" required>
                </div>
                <div class="col-md-6">
                    <label class="fintech-form-label">Valor (R$) *</label>
                    <input type="text" name="valor" class="form-control" placeholder="R$ 0,00" required oninput="mascaraMoeda(this)">
                </div>
                <div class="col-md-6">
                    <label class="fintech-form-label">Data de Início *</label>
                    <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-fintech-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-fintech-primary">Salvar Custo Fixo</button>
            </div>
        </form>
    </div>
</div>

<script>
function mascaraMoeda(el) {
    var v = el.value.replace(/\D/g, '');
    if (!v || parseInt(v, 10) === 0) { el.value = ''; return; }
    var num = (parseInt(v, 10) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    el.value = 'R$ ' + num;
}
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

