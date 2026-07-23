<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();

$inadimplentes = $pdo->query("
    SELECT f.*, c.nome_razao, c.cpf_cnpj, c.email, c.celular, c.telefone,
           DATEDIFF(NOW(), f.data_vencimento) AS dias_atraso
    FROM faturas f
    JOIN clientes c ON f.cliente_id = c.id
    WHERE f.status IN ('atrasado','vencido')
    ORDER BY dias_atraso DESC
")->fetchAll();

$totalInadimplencia = 0;
foreach ($inadimplentes as $f) {
    $totalInadimplencia += $f['valor_final'];
}

$clientesInadimplentes = count(array_unique(array_column($inadimplentes, 'cliente_id')));

$pageTitle = 'Relatório de Inadimplência';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Relatório de Inadimplência</h5>
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
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: var(--cor-perigo);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= count($inadimplentes) ?></div>
                            <div class="stat-label">Faturas em Atraso</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: var(--cor-perigo);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">R$ <?= number_format($totalInadimplencia, 2, ',', '.') ?></div>
                            <div class="stat-label">Valor Total em Atraso</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="border-left-color: var(--cor-perigo);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $clientesInadimplentes ?></div>
                            <div class="stat-label">Clientes Inadimplentes</div>
                        </div>
                        <div class="stat-icon" style="background: var(--cor-perigo);"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Faturas em Atraso</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Dias Atraso</th>
                            <th>Contato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inadimplentes)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-check-circle me-2"></i>Nenhuma fatura em atraso</td></tr>
                        <?php else: foreach ($inadimplentes as $f): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($f['numero']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($f['nome_razao']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($f['cpf_cnpj']) ?></small>
                                </td>
                                <td><strong class="text-danger">R$ <?= number_format($f['valor_final'], 2, ',', '.') ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></td>
                                <td><span class="badge bg-danger"><?= $f['dias_atraso'] ?> dia(s)</span></td>
                                <td>
                                    <?php
                                    $tel = preg_replace('/[^0-9]/', '', $f['celular'] ?: $f['telefone'] ?? '');
                                    $linkWa = $tel ? 'https://wa.me/55' . $tel . '?text=' . urlencode('Olá, identificamos que a fatura ' . $f['numero'] . ' está em atraso. Favor regularizar.') : '#';
                                    ?>
                                    <?php if ($tel): ?>
                                        <a href="<?= $linkWa ?>" target="_blank" class="btn btn-sm btn-success" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                    <?php endif; ?>
                                    <?php if ($f['email']): ?>
                                        <a href="mailto:<?= htmlspecialchars($f['email']) ?>" class="btn btn-sm btn-primary" title="E-mail"><i class="fas fa-envelope"></i></a>
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