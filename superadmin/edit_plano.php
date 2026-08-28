<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$mensagem = '';
$tipo = '';
$planEdit = null;

if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ?");
    $stmt->execute([$id]);
    $planEdit = $stmt->fetch();
    if (!$planEdit) {
        header('Location: planos.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $preco = trim($_POST['preco'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $beneficios = $_POST['beneficios'] ?? '';
    $cor = trim($_POST['cor'] ?? 'secondary');
    $ordem = intval($_POST['ordem'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (!empty($nome) && is_numeric($preco)) {
        $pdo->prepare("UPDATE planos SET nome=?, preco=?, descricao=?, beneficios=?, cor=?, ordem=?, ativo=? WHERE id=?")
            ->execute([$nome, $preco, $descricao, $beneficios, $cor, $ordem, $ativo, $id]);
        header('Location: planos.php?msg=salvo');
        exit;
    } else {
        $mensagem = 'Nome e preço são obrigatórios.';
        $tipo = 'danger';
    }
}

$pageTitle = 'Editar Plano';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-edit me-1"></i> Editar Plano: <?= htmlspecialchars($planEdit['nome']) ?></h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> py-2"><i class="fas fa-info-circle me-1"></i> <?= $mensagem ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Dados do Plano</h6>
                    </div>
                    <div class="p-3">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $planEdit['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($planEdit['nome']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preço (R$) *</label>
                                <input type="number" step="0.01" name="preco" class="form-control" required value="<?= $planEdit['preco'] ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2"><?= htmlspecialchars($planEdit['descricao']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Benefícios (um por linha)</label>
                                <textarea name="beneficios" class="form-control" rows="4"><?= htmlspecialchars($planEdit['beneficios']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cor</label>
                                <select name="cor" class="form-select">
                                    <option value="primary" <?= $planEdit['cor'] == 'primary' ? 'selected' : '' ?>>Azul (primary)</option>
                                    <option value="secondary" <?= $planEdit['cor'] == 'secondary' ? 'selected' : '' ?>>Cinza (secondary)</option>
                                    <option value="success" <?= $planEdit['cor'] == 'success' ? 'selected' : '' ?>>Verde (success)</option>
                                    <option value="warning" <?= $planEdit['cor'] == 'warning' ? 'selected' : '' ?>>Amarelo (warning)</option>
                                    <option value="danger" <?= $planEdit['cor'] == 'danger' ? 'selected' : '' ?>>Vermelho (danger)</option>
                                    <option value="info" <?= $planEdit['cor'] == 'info' ? 'selected' : '' ?>>Azul claro (info)</option>
                                    <option value="bronze" <?= $planEdit['cor'] == 'bronze' ? 'selected' : '' ?>>Bronze</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ordem</label>
                                <input type="number" name="ordem" class="form-control" value="<?= $planEdit['ordem'] ?>">
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="planoAtivoEdit" <?= $planEdit['ativo'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="planoAtivoEdit">Plano ativo</label>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                                <a href="planos.php" class="btn btn-secondary">Voltar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
