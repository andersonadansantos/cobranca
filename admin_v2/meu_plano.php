<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];

$stmtPlano = $pdo->prepare("
    SELECT p.*, ap.data_inicio, ap.data_fim
    FROM admin_planos ap
    JOIN planos p ON p.id = ap.plano_id
    WHERE ap.admin_id = ?
    ORDER BY ap.id DESC LIMIT 1
");
$stmtPlano->execute([$adminId]);
$meuPlano = $stmtPlano->fetch();

$diasRestantes = null;
if ($meuPlano && !empty($meuPlano['data_fim'])) {
    $diasRestantes = (int)floor((strtotime($meuPlano['data_fim']) - strtotime(date('Y-m-d'))) / 86400);
}

$semPlanoInfo = !$meuPlano;
$planos = $pdo->query("SELECT * FROM planos WHERE ativo = 1 AND COALESCE(slug,'') NOT IN ('diamante', 'demo') ORDER BY ordem ASC")->fetchAll();

$pageTitle = 'Meu Plano';
$pageCategory = 'Assinatura';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Plano & Assinatura da Plataforma</h2>
            <p class="text-muted small mb-0">Gerencie a vigência do seu plano e faça upgrade para desbloquear mais recursos.</p>
        </div>
        <div>
            <a href="minhas_faturas.php" class="btn btn-fintech-secondary">
                <i class="bi bi-receipt me-1"></i> Ver Minhas Faturas
            </a>
        </div>
    </div>

    <!-- Banner de Status do Plano Atual -->
    <div class="fintech-card p-4 mb-4" style="background:#ffffff; border:1px solid var(--fintech-border);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:52px;height:52px;border-radius:14px;background:var(--fintech-primary);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(15,23,42,0.15);">
                    <i class="bi bi-award fs-3"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fintech-badge <?= $semPlanoInfo ? 'warning' : 'success' ?>">
                            <span class="dot"></span> <?= $semPlanoInfo ? 'Sem Plano Ativo' : 'Plano Ativo' ?>
                        </span>
                        <h4 class="mb-0 fw-bold text-slate-900"><?= htmlspecialchars($meuPlano['nome'] ?? 'Demonstração') ?></h4>
                    </div>
                    <small class="text-muted">
                        <?php if (!$semPlanoInfo && !empty($meuPlano['data_fim'])): ?>
                            Vigência até <?= date('d/m/Y', strtotime($meuPlano['data_fim'])) ?> (<?= $diasRestantes > 0 ? "{$diasRestantes} dias restantes" : "Expirado" ?>)
                        <?php else: ?>
                            Assine um dos planos abaixo para liberar todas as funcionalidades de cobrança.
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <a href="/cobranca/admin/meu_plano.php" class="btn btn-fintech-primary px-4 py-2">
                <i class="bi bi-arrow-up-circle me-1"></i> Gerenciar / Renovar Plano
            </a>
        </div>
    </div>

    <!-- Grade de Planos -->
    <div class="row g-4">
        <?php foreach ($planos as $p): 
            $isCurrent = $meuPlano && (int)$meuPlano['id'] === (int)$p['id'];
        ?>
            <div class="col-lg-4 col-md-6">
                <div class="fintech-card h-100 p-4 d-flex flex-column justify-content-between" style="border: <?= $isCurrent ? '2px solid var(--fintech-primary)' : '1px solid var(--fintech-border)' ?>; background: #ffffff;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-uppercase fw-bold text-muted small" style="letter-spacing:0.06em;"><?= htmlspecialchars($p['nome']) ?></span>
                            <?php if ($isCurrent): ?>
                                <span class="fintech-badge success"><span class="dot"></span> Seu Plano</span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-baseline gap-1">
                                <span class="fs-6 text-muted fw-semibold">R$</span>
                                <span class="display-6 fw-bold text-slate-900 font-monospace"><?= number_format((float)$p['preco_mensal'], 2, ',', '.') ?></span>
                                <span class="text-muted small">/mês</span>
                            </div>
                            <small class="text-muted d-block mt-1"><?= htmlspecialchars($p['descricao'] ?? '') ?></small>
                        </div>

                        <hr class="my-3">

                        <!-- Recursos -->
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 small text-slate-900">
                            <li class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2 text-success fs-5"></i>
                                <span>Emissão de Boletos e PIX</span>
                            </li>
                            <li class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2 text-success fs-5"></i>
                                <span>Envio automático de e-mails</span>
                            </li>
                            <li class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2 text-success fs-5"></i>
                                <span>WhatsApp e Régua de Cobrança</span>
                            </li>
                            <li class="d-flex align-items-center gap-2">
                                <i class="bi bi-check2 text-success fs-5"></i>
                                <span>Relatórios e Livro Caixa</span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <?php if ($isCurrent): ?>
                            <a href="/cobranca/admin/meu_plano.php" class="btn btn-fintech-secondary w-100 py-2">
                                <i class="bi bi-arrow-repeat me-1"></i> Renovar Assinatura
                            </a>
                        <?php else: ?>
                            <a href="/cobranca/admin/meu_plano.php?plano=<?= $p['id'] ?>" class="btn btn-fintech-primary w-100 py-2">
                                <i class="bi bi-check2 me-1"></i> Escolher <?= htmlspecialchars($p['nome']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

