<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/inter_pix.php';
require_once __DIR__ . '/../config/mercadopago.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];

// Métodos de pagamento
$metodosAdmin = ['pix', 'boleto', 'cartao'];
if (getConfig('super_plano_cartao', '1') !== '1') {
    $metodosAdmin = array_values(array_diff($metodosAdmin, ['cartao']));
}
if (getConfig('super_plano_pix_boleto', '1') !== '1') {
    $metodosAdmin = array_values(array_diff($metodosAdmin, ['pix', 'boleto']));
}
$cartaoOk = in_array('cartao', $metodosAdmin, true);
$maxParcelasAdmin = $cartaoOk ? max(1, min((int)getConfig('super_mp_max_parcelas', '12'), 12)) : 0;

$mpPubKeyAdmin = '';
if ($cartaoOk) {
    $superMp = getMPConfigSuper();
    $mpPubKeyAdmin = $superMp['super_mp_public_key'] ?? '';
}

$stmt = $pdo->prepare("SELECT cpf, cnpj FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$rowCpf = $stmt->fetch();
$adminCpfCnpj = $rowCpf ? preg_replace('/[^0-9]/', '', (($rowCpf['cnpj'] ?: '') ?: ($rowCpf['cpf'] ?? ''))) : '';

$faturaAutoId = function_exists('gerarFaturaRenovacaoAuto') ? gerarFaturaRenovacaoAuto($adminId) : null;

$stmtPlano = $pdo->prepare("
    SELECT p.nome AS plano_nome, p.cor AS plano_cor, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$stmtPlano->execute([$adminId]);
$meuPlanoInfo = $stmtPlano->fetch();

$diasRestantes = null;
if ($meuPlanoInfo && !empty($meuPlanoInfo['data_fim'])) {
    $diasRestantes = (int)floor((strtotime($meuPlanoInfo['data_fim']) - strtotime(date('Y-m-d'))) / 86400);
}

$faturasStmt = $pdo->prepare("
    SELECT pp.*, p.nome AS plano_nome
    FROM planos_pagamentos pp
    LEFT JOIN planos p ON p.id = pp.plano_id
    WHERE pp.admin_id = ?
    ORDER BY pp.criado_em DESC, pp.id DESC
");
$faturasStmt->execute([$adminId]);
$faturas = $faturasStmt->fetchAll();

$totalPendente = 0;
$totalPago = 0.0;
foreach ($faturas as $f) {
    if ($f['status'] === 'pendente') $totalPendente++;
    if ($f['status'] === 'pago') $totalPago += (float)$f['valor'];
}

$periodoLabel = [1 => 'Mensal', 3 => 'Trimestral', 6 => 'Semestral', 12 => 'Anual'];

$pageTitle = 'Minhas Faturas de Assinatura';
$pageCategory = 'Financeiro da Conta';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Minhas Faturas do Sistema</h2>
            <p class="text-muted small mb-0">Histórico de cobranças e faturas da sua assinatura da plataforma.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="meu_plano.php" class="btn btn-fintech-primary">
                <i class="bi bi-award me-1"></i> Ver Meu Plano & Upgrades
            </a>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="fintech-kpi-grid mb-4">
        <div class="fintech-kpi-card info">
            <div class="fintech-kpi-header">
                <span class="fintech-kpi-title">Plano Atual</span>
                <div class="fintech-kpi-icon"><i class="bi bi-shield-check"></i></div>
            </div>
            <div class="fintech-kpi-value fs-4" style="text-transform: capitalize;">
                <?= htmlspecialchars($meuPlanoInfo['plano_nome'] ?? 'Sem Plano Ativo') ?>
            </div>
            <div class="fintech-kpi-progress">
                <div class="fintech-kpi-progress-bar bg-info" style="width: 100%;"></div>
            </div>
            <div class="fintech-kpi-footer">
                <span class="text-muted"><?= $diasRestantes !== null ? ($diasRestantes > 0 ? "{$diasRestantes} dias restantes" : "Expirado") : "Vitalício ou Inativo" ?></span>
            </div>
        </div>

        <div class="fintech-kpi-card <?= $totalPendente > 0 ? 'warning' : 'success' ?>">
            <div class="fintech-kpi-header">
                <span class="fintech-kpi-title">Faturas Pendentes</span>
                <div class="fintech-kpi-icon"><i class="bi bi-clock-history"></i></div>
            </div>
            <div class="fintech-kpi-value <?= $totalPendente > 0 ? 'text-warning' : 'text-success' ?>">
                <?= $totalPendente ?>
            </div>
            <div class="fintech-kpi-progress">
                <div class="fintech-kpi-progress-bar <?= $totalPendente > 0 ? 'bg-warning' : 'bg-success' ?>" style="width: <?= $totalPendente > 0 ? '100%' : '0%' ?>;"></div>
            </div>
            <div class="fintech-kpi-footer">
                <span class="text-muted"><?= $totalPendente > 0 ? "Aguardando quitação" : "Conta em dia" ?></span>
            </div>
        </div>

        <div class="fintech-kpi-card success">
            <div class="fintech-kpi-header">
                <span class="fintech-kpi-title">Total Já Liquidado</span>
                <div class="fintech-kpi-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="fintech-kpi-value text-success">
                R$ <?= number_format($totalPago, 2, ',', '.') ?>
            </div>
            <div class="fintech-kpi-progress">
                <div class="fintech-kpi-progress-bar bg-success" style="width: 100%;"></div>
            </div>
            <div class="fintech-kpi-footer">
                <span class="text-muted">Total pago na plataforma</span>
            </div>
        </div>
    </div>

    <!-- Tabela de Faturas -->
    <div class="fintech-card">
        <div class="fintech-card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <h3 class="fintech-card-title mb-0">Extrato de Pagamentos de Assinatura</h3>
            <span class="text-muted small"><?= count($faturas) ?> registro(s)</span>
        </div>

        <div class="table-responsive">
            <table class="fintech-table w-100">
                <thead>
                    <tr>
                        <th>Nº Fatura</th>
                        <th>Plano / Referência</th>
                        <th>Data de Emissão</th>
                        <th class="text-end">Valor</th>
                        <th>Método</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faturas)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                Nenhuma fatura gerada para sua conta até o momento.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($faturas as $f): 
                            $badgeStatus = 'neutral';
                            if ($f['status'] === 'pago') $badgeStatus = 'success';
                            elseif ($f['status'] === 'pendente') $badgeStatus = 'warning';
                            elseif ($f['status'] === 'vencido' || $f['status'] === 'cancelado') $badgeStatus = 'danger';
                        ?>
                            <tr>
                                <td>
                                    <strong class="font-monospace text-dark">#<?= str_pad($f['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($f['plano_nome'] ?? 'Assinatura') ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($f['descricao'] ?: 'Renovação de Assinatura') ?></small>
                                </td>
                                <td>
                                    <span class="text-muted"><?= date('d/m/Y H:i', strtotime($f['criado_em'])) ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="fintech-tabular fw-bold text-dark">R$ <?= number_format((float)$f['valor'], 2, ',', '.') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size:11px;">
                                        <?= strtoupper(htmlspecialchars($f['metodo'] ?: 'PIX')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fintech-badge <?= $badgeStatus ?>">
                                        <span class="dot"></span> <?= ucfirst(htmlspecialchars($f['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($f['status'] === 'pendente'): ?>
                                        <a href="/cobranca/admin/minhas_faturas.php?pagar=<?= $f['id'] ?>" class="btn btn-sm btn-fintech-primary">
                                            <i class="bi bi-qr-code me-1"></i> Pagar Agora
                                        </a>
                                    <?php elseif ($f['status'] === 'pago'): ?>
                                        <span class="text-success small fw-semibold"><i class="bi bi-check-circle me-1"></i>Liquidado</span>
                                    <?php else: ?>
                                        <span class="text-muted small">&#8212;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

