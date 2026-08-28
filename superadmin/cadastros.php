<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$mensagem = '';
$tipo = '';
$editando = false;
$adminEdit = null;

if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    try {
        $pdo->prepare("DELETE FROM admin_evolution WHERE admin_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM admin_planos WHERE admin_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM administradores WHERE id = ?")->execute([$id]);
        $mensagem = 'Admin excluído com sucesso!';
        $tipo = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro ao excluir admin.';
        $tipo = 'danger';
    }
} elseif (isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE administradores SET ativo = 1 WHERE id = ?")->execute([intval($_GET['ativar'])]);
    $mensagem = 'Admin ativado!';
    $tipo = 'success';
} elseif (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE administradores SET ativo = 0 WHERE id = ?")->execute([intval($_GET['desativar'])]);
    $mensagem = 'Admin desativado!';
    $tipo = 'warning';
}

if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
    $stmt->execute([$id]);
    $adminEdit = $stmt->fetch();
    if ($adminEdit) $editando = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $id_edit = intval($_POST['id'] ?? 0);

    if (empty($usuario) || empty($nome)) {
        $mensagem = 'Usuário e nome são obrigatórios.';
        $tipo = 'danger';
    } elseif (!empty($senha) && strlen($senha) < 6) {
        $mensagem = 'A senha deve ter no mínimo 6 caracteres.';
        $tipo = 'danger';
    } else {
        try {
            if ($id_edit > 0) {
                $sql = "UPDATE administradores SET usuario=?, nome=?, email=?, ativo=?";
                $params = [$usuario, $nome, $email, $ativo];
                if (!empty($senha)) {
                    $sql .= ", senha=?";
                    $params[] = password_hash($senha, PASSWORD_BCRYPT);
                }
                $sql .= " WHERE id=?";
                $params[] = $id_edit;
                $pdo->prepare($sql)->execute($params);
                header('Location: cadastros.php?msg=salvo');
                exit;
            } else {
                if (empty($senha)) $senha = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $pdo->prepare("INSERT INTO administradores (usuario, nome, email, senha, ativo) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$usuario, $nome, $email, password_hash($senha, PASSWORD_BCRYPT), $ativo]);
                header('Location: cadastros.php?msg=salvo');
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . ($e->getCode() == 23000 ? 'Usuário já cadastrado.' : $e->getMessage());
            $tipo = 'danger';
        }
    }
}

if (isset($_GET['msg'])) {
    $msgs = [
        'salvo' => ['Admin salvo com sucesso!', 'success'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

$admins = $pdo->query("
    SELECT a.*,
           ae.url_api, ae.api_key, ae.instance,
           p.nome AS plano, p.cor AS plano_cor
    FROM administradores a
    LEFT JOIN admin_evolution ae ON ae.admin_id = a.id
    LEFT JOIN admin_planos ap ON ap.admin_id = a.id
    LEFT JOIN planos p ON p.id = ap.plano_id
    ORDER BY a.criado_em DESC
")->fetchAll();

$planos = $pdo->query("SELECT id, nome, preco, cor FROM planos WHERE ativo = 1 ORDER BY ordem ASC")->fetchAll();

$pageTitle = 'Cadastros';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-building me-1"></i> Cadastros de Admins</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> py-2"><i class="fas fa-info-circle me-1"></i> <?= $mensagem ?></div>
        <?php endif; ?>

        <?php if ($editando): ?>
            <div class="form-card mb-4">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Admin: <?= htmlspecialchars($adminEdit['nome']) ?></h6>
                </div>
                <div class="p-3">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $adminEdit['id'] ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Usuário *</label>
                                <input type="text" name="usuario" class="form-control" required value="<?= htmlspecialchars($adminEdit['usuario']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($adminEdit['nome']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($adminEdit['email']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nova senha (deixe vazio para manter)</label>
                                <input type="password" name="senha" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="ativoEdit" <?= $adminEdit['ativo'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ativoEdit">Admin ativo</label>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                                <a href="cadastros.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-user-plus me-2"></i><?= $editando ? 'Editar' : 'Novo' ?> Admin</h6>
                    </div>
                    <div class="p-3">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Usuário *</label>
                                <input type="text" name="usuario" class="form-control" required value="<?= $editando ? htmlspecialchars($adminEdit['usuario']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required value="<?= $editando ? htmlspecialchars($adminEdit['nome']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" value="<?= $editando ? htmlspecialchars($adminEdit['email']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= $editando ? 'Nova senha (opcional)' : 'Senha *' ?></label>
                                <input type="password" name="senha" class="form-control" <?= $editando ? 'placeholder="Deixe vazio para manter"' : 'required' ?>>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="ativoNovo" <?= (!$editando || $adminEdit['ativo']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ativoNovo">Admin ativo</label>
                            </div>
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i><?= $editando ? 'Salvar Alterações' : 'Cadastrar Admin' ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="table-card">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Admins (<?= count($admins) ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Usuário</th>
                                    <th>Plano</th>
                                    <th>WhatsApp</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admins)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum admin cadastrado</td></tr>
                                <?php else: foreach ($admins as $adm): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($adm['nome']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($adm['email']) ?></small></td>
                                        <td><?= htmlspecialchars($adm['usuario']) ?></td>
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
                                        <td>
                                            <div class="d-inline-flex gap-1">
                                                <a href="cadastros.php?editar=<?= $adm['id'] ?>" class="acao-btn acao-btn-primary" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                                <a href="api_admins.php?admin=<?= $adm['id'] ?>" class="acao-btn" style="background:#25D366;color:#fff;" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                                <?php if ($adm['ativo']): ?>
                                                    <a href="cadastros.php?desativar=<?= $adm['id'] ?>" class="acao-btn acao-btn-secondary" title="Desativar"><i class="bi bi-pause-circle"></i></a>
                                                <?php else: ?>
                                                    <a href="cadastros.php?ativar=<?= $adm['id'] ?>" class="acao-btn acao-btn-success" title="Ativar"><i class="bi bi-play-circle"></i></a>
                                                <?php endif; ?>
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
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
