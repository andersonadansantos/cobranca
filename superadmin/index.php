<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$totalAdmins = $pdo->query("SELECT COUNT(*) FROM administradores WHERE ativo = 1")->fetchColumn();
$admins = $pdo->query("
    SELECT a.id, a.usuario, a.nome, a.email, a.avatar, a.ativo, a.ultimo_login,
           ae.url_api, ae.api_key, ae.instance,
           p.nome AS plano, p.slug AS plano_slug, p.cor AS plano_cor
    FROM administradores a
    LEFT JOIN admin_evolution ae ON ae.admin_id = a.id
    LEFT JOIN admin_planos ap ON ap.admin_id = a.id
    LEFT JOIN planos p ON p.id = ap.plano_id
    ORDER BY a.criado_em DESC
")->fetchAll();

$planoStats = $pdo->query("
    SELECT p.nome, p.cor, COUNT(ap.id) AS total
    FROM planos p
    LEFT JOIN admin_planos ap ON ap.plano_id = p.id
    GROUP BY p.id ORDER BY p.ordem ASC
")->fetchAll();

$comWhats = 0;
$semWhats = 0;
foreach ($admins as $adm) {
    if (!empty($adm['instance']) && !empty($adm['url_api'])) {
        $comWhats++;
    } else {
        $semWhats++;
    }
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-crown me-1"></i> Dashboard Super Admin</h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['super_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['super_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalAdmins ?></div>
                            <div class="stat-label">Admins Ativos</div>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-users-cog"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $comWhats ?></div>
                            <div class="stat-label">Com WhatsApp</div>
                        </div>
                        <div class="stat-icon" style="background:#25D366;"><i class="fab fa-whatsapp"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $semWhats ?></div>
                            <div class="stat-label">Sem WhatsApp</div>
                        </div>
                        <div class="stat-icon bg-secondary"><i class="fas fa-lock"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value">3</div>
                            <div class="stat-label">Planos</div>
                        </div>
                        <div class="stat-icon bg-warning"><i class="fas fa-tags"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($planoStats)): ?>
        <div class="row g-4 mb-4">
            <?php foreach ($planoStats as $pl): ?>
            <div class="col-md-4">
                <div class="form-card text-center">
                    <h6 class="mb-2">Plano <?= htmlspecialchars($pl['nome']) ?></h6>
                    <div class="stat-value"><?= $pl['total'] ?></div>
                    <div class="stat-label">Admins neste plano</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Admins Cadastrados (<?= count($admins) ?>)</h6>
                <a href="cadastros.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Novo Admin</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Usuário</th>
                            <th>E-mail</th>
                            <th>Plano</th>
                            <th>WhatsApp</th>
                            <th>Status</th>
                            <th>Último Login</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum admin cadastrado</td></tr>
                        <?php else: foreach ($admins as $adm): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= htmlspecialchars($adm['avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover;">
                                        <strong><?= htmlspecialchars($adm['nome']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($adm['usuario']) ?></td>
                                <td><?= htmlspecialchars($adm['email']) ?></td>
                                <td>
                                    <?php if (!empty($adm['plano'])): ?>
                                        <span class="badge" style="background:<?= $adm['plano_cor'] ?: 'secondary' ?>;color:#fff;"><?= htmlspecialchars($adm['plano']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sem plano</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($adm['instance']) && !empty($adm['url_api'])): ?>
                                        <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i>Configurado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($adm['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $adm['ultimo_login'] ? date('d/m/Y H:i', strtotime($adm['ultimo_login'])) : '—' ?></td>
                                <td>
                                    <div class="d-inline-flex gap-1">
                                        <a href="cadastros.php?editar=<?= $adm['id'] ?>" class="acao-btn acao-btn-primary" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                        <a href="api_admins.php?admin=<?= $adm['id'] ?>" class="acao-btn" style="background:#25D366;color:#fff;" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                        <a href="cadastros.php?excluir=<?= $adm['id'] ?>" class="acao-btn acao-btn-danger" title="Excluir" onclick="return confirmarExclusao('<?= htmlspecialchars(addslashes($adm['nome'])) ?>', this.href);"><i class="bi bi-trash3"></i></a>
                                    </div>
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
