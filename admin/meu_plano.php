<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$adminId = $_SESSION['admin_id'] ?? 0;

$plano = $pdo->prepare("
    SELECT p.*, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$plano->execute([$adminId]);
$meuPlano = $plano->fetch();

$pageTitle = 'Meu Plano';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-tags me-1"></i> Meu Plano</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
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
        <?php if ($meuPlano): ?>
            <div class="row g-4 justify-content-center">
                <div class="col-md-7">
                    <div class="form-card text-center">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0"><i class="fas fa-crown me-2" style="color:#f59e0b;"></i>Seu Plano Atual</h6>
                        </div>
                        <div class="p-4">
                            <span class="badge fs-5 px-3 py-2" style="background:<?= $meuPlano['cor'] ?: 'secondary' ?>;color:#fff;">
                                <?= htmlspecialchars($meuPlano['nome']) ?>
                            </span>
                            <h3 class="mt-3 mb-1">R$ <?= number_format($meuPlano['preco'], 2, ',', '.') ?></h3>
                            <p class="text-muted small">por mês</p>

                            <?php if ($meuPlano['data_inicio']): ?>
                                <div class="row mt-3 mb-3 text-center">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Início</small>
                                        <strong><?= date('d/m/Y', strtotime($meuPlano['data_inicio'])) ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Vencimento</small>
                                        <?php if ($meuPlano['data_fim']): ?>
                                            <strong><?= date('d/m/Y', strtotime($meuPlano['data_fim'])) ?></strong>
                                        <?php else: ?>
                                            <strong>—</strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($meuPlano['descricao']): ?>
                                <hr>
                                <p class="text-muted"><?= nl2br(htmlspecialchars($meuPlano['descricao'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="form-card">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0"><i class="fas fa-list-check me-2"></i>Benefícios do Plano</h6>
                        </div>
                        <div class="p-3">
                            <?php $beneficios = array_filter(array_map('trim', explode("\n", $meuPlano['beneficios'] ?? ''))); ?>
                            <?php if ($beneficios): ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($beneficios as $b): ?>
                                        <li class="py-1 border-bottom d-flex">
                                            <i class="fas fa-check-circle me-2 mt-1" style="color:#198754;"></i>
                                            <span><?= htmlspecialchars($b) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Nenhum benefício listado.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="form-card text-center py-5" style="max-width:560px;margin:0 auto;">
                <div style="width:90px;height:90px;border-radius:50%;background:#eef2ff;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px;">
                    <i class="fas fa-tags" style="font-size:2rem;color:#6366f1;"></i>
                </div>
                <h5 class="mb-2">Nenhum plano contratado</h5>
                <p class="text-muted mb-4">Você ainda não possui um plano ativo. Entre em contato com o suporte para contratar o seu plano.</p>
                <a href="https://wa.me/5591982675573" target="_blank" class="btn" style="background:#25D366;color:#fff;"><i class="fab fa-whatsapp me-1"></i>Falar com Suporte</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
