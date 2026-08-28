<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $preco = trim($_POST['preco'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $cor = trim($_POST['cor'] ?? 'secondary');
    $ordem = intval($_POST['ordem'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (!empty($nome) && is_numeric($preco)) {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE planos SET nome=?, preco=?, descricao=?, cor=?, ordem=?, ativo=?, beneficios=? WHERE id=?")
                    ->execute([$nome, $preco, $descricao, $cor, $ordem, $ativo, $_POST['beneficios'] ?? '', $id]);
            } else {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $nome));
                $pdo->prepare("INSERT INTO planos (nome, slug, preco, descricao, beneficios, cor, ativo, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$nome, $slug, $preco, $descricao, $_POST['beneficios'] ?? '', $cor, $ativo, $ordem]);
            }
            header('Location: planos.php?msg=salvo');
            exit;
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . $e->getMessage();
            $tipo = 'danger';
        }
    } else {
        $mensagem = 'Nome e preço são obrigatórios.';
        $tipo = 'danger';
    }
}

if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $pdo->prepare("DELETE FROM admin_planos WHERE plano_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM planos WHERE id = ?")->execute([$id]);
    $mensagem = 'Plano excluído!';
    $tipo = 'success';
}

if (isset($_GET['msg'])) {
    $mensagem = 'Plano salvo com sucesso!';
    $tipo = 'success';
}

$planos = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM admin_planos ap WHERE ap.plano_id = p.id) AS total_admins FROM planos p ORDER BY p.ordem ASC")->fetchAll();

$pageTitle = 'Planos';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-tags me-1"></i> Planos</h5>
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
            <div class="col-lg-4">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Novo Plano</h6>
                    </div>
                    <div class="p-3">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required placeholder="Ex: Diamante">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preço (R$) *</label>
                                <input type="number" step="0.01" name="preco" class="form-control" required placeholder="0.00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Benefícios (um por linha)</label>
                                <textarea name="beneficios" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cor</label>
                                <select name="cor" class="form-select">
                                    <option value="primary">Azul (primary)</option>
                                    <option value="secondary">Cinza (secondary)</option>
                                    <option value="success">Verde (success)</option>
                                    <option value="warning">Amarelo (warning)</option>
                                    <option value="danger">Vermelho (danger)</option>
                                    <option value="info">Azul claro (info)</option>
                                    <option value="bronze">Bronze</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ordem</label>
                                <input type="number" name="ordem" class="form-control" value="10">
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="planoAtivo" checked>
                                <label class="form-check-label" for="planoAtivo">Plano ativo</label>
                            </div>
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Cadastrar Plano</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Planos Cadastrados</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Plano</th>
                                    <th>Preço</th>
                                    <th>Admins</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($planos)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum plano cadastrado</td></tr>
                                <?php else: foreach ($planos as $pl): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge" style="background:<?= $pl['cor'] ?: 'secondary' ?>;color:#fff;"><?= htmlspecialchars($pl['nome']) ?></span>
                                            </div>
                                        </td>
                                        <td>R$ <?= number_format($pl['preco'], 2, ',', '.') ?></td>
                                        <td><?= $pl['total_admins'] ?></td>
                                        <td>
                                            <?php if ($pl['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a href="edit_plano.php?editar=<?= $pl['id'] ?>" class="acao-btn acao-btn-primary" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                                <a href="planos.php?excluir=<?= $pl['id'] ?>" class="acao-btn acao-btn-danger" title="Excluir" onclick="return confirmarExclusao('<?= htmlspecialchars(addslashes($pl['nome'])) ?>', this.href);"><i class="bi bi-trash3"></i></a>
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
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
