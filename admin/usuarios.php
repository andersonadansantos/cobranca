<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$tipo = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'atendimento';

        if ($nome && $email && $usuario && $senha) {
            if (strlen($senha) < 6) {
                $tipo = 'danger';
                $msg = 'A senha deve ter no mínimo 6 caracteres.';
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios_admin WHERE usuario = ? OR email = ?");
                $check->execute([$usuario, $email]);
                if ($check->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO usuarios_admin (nome, email, usuario, senha, perfil) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $email, $usuario, password_hash($senha, PASSWORD_BCRYPT), $perfil]);
                    $tipo = 'success';
                    $msg = 'Usuário criado com sucesso!';
                } else {
                    $tipo = 'danger';
                    $msg = 'Usuário ou e-mail já cadastrado.';
                }
            }
        } else {
            $tipo = 'danger';
            $msg = 'Preencha todos os campos.';
        }
    }

    if ($acao === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $perfil = $_POST['perfil'] ?? 'atendimento';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $senha = $_POST['senha'] ?? '';

        if ($id && $nome && $email) {
            if ($senha && strlen($senha) < 6) {
                $tipo = 'danger';
                $msg = 'A senha deve ter no mínimo 6 caracteres.';
            } else {
                if ($senha) {
                    $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome=?, email=?, perfil=?, ativo=?, senha=? WHERE id=?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, password_hash($senha, PASSWORD_BCRYPT), $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome=?, email=?, perfil=?, ativo=? WHERE id=?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, $id]);
                }
                $tipo = 'success';
                $msg = 'Usuário atualizado!';
            }
        }
    }

    if ($acao === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
        if ($id && $id != $_SESSION['admin_id']) {
            $stmt = $pdo->prepare("DELETE FROM usuarios_admin WHERE id = ?");
            $stmt->execute([$id]);
            $tipo = 'success';
            $msg = 'Usuário removido!';
        } else {
            $tipo = 'danger';
            $msg = 'Não é possível excluir seu próprio usuário.';
        }
    }
}

$usuarios = $pdo->query("SELECT * FROM usuarios_admin ORDER BY criado_em DESC")->fetchAll();
$totalUsuarios = count($usuarios);
$perfis = ['admin' => 'Administrador', 'financeiro' => 'Financeiro', 'atendimento' => 'Atendimento'];
$corPerfil = ['admin' => 'primary', 'financeiro' => 'warning', 'atendimento' => 'info'];

$pageTitle = 'Usuários Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Usuários Admin</h5>
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
        <?php if ($msg): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value"><?= $totalUsuarios ?></div>
                            <div class="stat-label">Usuários Cadastrados</div>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-users-cog"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-9 d-flex align-items-center justify-content-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriar">
                    <i class="fas fa-plus me-1"></i> Novo Usuário
                </button>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-users-cog me-2"></i>Usuários Cadastrados</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Usuário</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum usuário cadastrado</td></tr>
                        <?php else: foreach ($usuarios as $u): ?>
                            <tr style="<?= $u['ativo'] ? '' : 'opacity:0.5;' ?>">
                                <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($u['usuario']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-<?= $corPerfil[$u['perfil']] ?? 'secondary' ?>"><?= $perfis[$u['perfil']] ?? $u['perfil'] ?></span></td>
                                <td>
                                    <?php if ($u['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $u['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluir<?= $u['id'] ?>"><i class="fas fa-trash"></i></button>
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

<div class="modal fade" id="modalCriar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-user-plus me-2"></i>Novo Usuário</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="criar">
                    <div class="mb-3"><label class="form-label small">Nome</label><input type="text" name="nome" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small">E-mail</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small">Usuário</label><input type="text" name="usuario" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small">Senha</label><input type="password" name="senha" class="form-control" required minlength="6"></div>
                    <div class="mb-3">
                        <label class="form-label small">Perfil</label>
                        <select name="perfil" class="form-select">
                            <option value="atendimento">Atendimento</option>
                            <option value="financeiro">Financeiro</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Criar Usuário</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($usuarios as $u): ?>
<div class="modal fade" id="modalEditar<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-user-edit me-2"></i>Editar <?= htmlspecialchars($u['nome']) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <div class="mb-3"><label class="form-label small">Nome</label><input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($u['nome']) ?>" required></div>
                    <div class="mb-3"><label class="form-label small">E-mail</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
                    <div class="mb-3">
                        <label class="form-label small">Perfil</label>
                        <select name="perfil" class="form-select">
                            <?php foreach ($perfis as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $u['perfil'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= $u['id'] ?>" <?= $u['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativo<?= $u['id'] ?>">Ativo</label>
                    </div>
                    <div class="mb-3"><label class="form-label small">Nova senha (deixe vazio para manter)</label><input type="password" name="senha" class="form-control" minlength="6"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php foreach ($usuarios as $u): ?>
<?php if ($u['id'] != $_SESSION['admin_id']): ?>
<div class="modal fade" id="modalExcluir<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <div class="modal-header">
                    <h6 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Excluir</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">Remover <strong><?= htmlspecialchars($u['nome']) ?></strong>?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Não</button>
                    <button type="submit" class="btn btn-danger btn-sm">Sim, excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>