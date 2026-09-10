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
$tipo = '';
$msg = '';
$adminId = (int)($_SESSION['admin_id'] ?? 0);

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
                $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios_admin WHERE admin_id = ? AND (usuario = ? OR email = ?)");
                $check->execute([$adminId, $usuario, $email]);
                if ($check->fetchColumn() == 0) {
                    $limiteUsr = verificarLimitePlano('usuarios', $adminId);
                    if ($limiteUsr['ok']) {
                        $stmt = $pdo->prepare("INSERT INTO usuarios_admin (admin_id, nome, email, usuario, senha, perfil) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$adminId, $nome, $email, $usuario, password_hash($senha, PASSWORD_BCRYPT), $perfil]);
                        $tipo = 'success';
                        $msg = 'Usuário criado com sucesso!';
                    } else {
                        $tipo = 'danger';
                        $msg = $limiteUsr['mensagem'];
                    }
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
                    $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome=?, email=?, perfil=?, ativo=?, senha=? WHERE id=? AND admin_id=?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, password_hash($senha, PASSWORD_BCRYPT), $id, $adminId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome=?, email=?, perfil=?, ativo=? WHERE id=? AND admin_id=?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, $id, $adminId]);
                }
                $tipo = 'success';
                $msg = 'Usuário atualizado!';
            }
        }
    }

    if ($acao === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
        // Protege apenas a conta principal (mesmo usuário do administrador logado),
        // permitindo excluir qualquer usuário auxiliar.
        $stmtU = $pdo->prepare("SELECT usuario FROM usuarios_admin WHERE id = ? AND admin_id = ?");
        $stmtU->execute([$id, $adminId]);
        $usuarioAux = $stmtU->fetchColumn();
        $stmtPri = $pdo->prepare("SELECT usuario FROM administradores WHERE id = ?");
        $stmtPri->execute([$adminId]);
        $usuarioPrincipal = $stmtPri->fetchColumn();
        if ($usuarioAux && $usuarioAux !== $usuarioPrincipal) {
            $stmt = $pdo->prepare("DELETE FROM usuarios_admin WHERE id = ? AND admin_id = ?");
            $stmt->execute([$id, $adminId]);
            $tipo = 'success';
            $msg = 'Usuário removido!';
        } else {
            $tipo = 'danger';
            $msg = 'Não é possível excluir este usuário.';
        }
    }
}

$stmtUsuarios = $pdo->prepare("SELECT * FROM usuarios_admin WHERE admin_id = ? ORDER BY criado_em DESC");
$stmtUsuarios->execute([$adminId]);
$usuarios = $stmtUsuarios->fetchAll();
$totalUsuarios = count($usuarios);
$perfis = ['admin' => t('uso.perfil_admin'), 'financeiro' => t('uso.perfil_financeiro'), 'atendimento' => t('uso.perfil_atendimento')];
$corPerfil = ['admin' => 'primary', 'financeiro' => 'warning', 'atendimento' => 'info'];

$pageTitle = t('uso.titulo');
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><?= t('uso.titulo') ?></h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> <?= t('tb.suporte') ?></a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i><?= t('tb.editar_perfil') ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i><?= t('tb.sair') ?></a></li>
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
                            <div class="stat-label"><?= t('uso.cadastrados') ?></div>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-users-cog"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-9 d-flex align-items-center justify-content-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriar">
<i class="fas fa-plus me-1"></i> <?= t('btn.novo_usuario') ?>
                </button>
            </div>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-users-cog me-2"></i><?= t('uso.lista') ?></h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><?= t('th.nome') ?></th>
                            <th><?= t('th.usuario') ?></th>
                            <th><?= t('th.email') ?></th>
                            <th><?= t('th.perfil') ?></th>
                            <th><?= t('th.status') ?></th>
                            <th><?= t('th.acoes') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><?= t('txt.nenhum_usuario') ?></td></tr>
                        <?php else: foreach ($usuarios as $u): ?>
                            <tr style="<?= $u['ativo'] ? '' : 'opacity:0.5;' ?>">
                                <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($u['usuario']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-<?= $corPerfil[$u['perfil']] ?? 'secondary' ?>"><?= $perfis[$u['perfil']] ?? $u['perfil'] ?></span></td>
                                <td>
                                    <?php if ($u['ativo']): ?>
                                        <span class="badge bg-success"><?= t('uso.ativo') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= t('uso.inativo') ?></span>
                                    <?php endif; ?>
                                </td>
<td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $u['id'] ?>" title="<?= t('uso.editar') ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluir<?= $u['id'] ?>" title="<?= t('uso.excluir') ?>"><i class="fas fa-trash"></i></button>
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
                    <h6 class="modal-title"><i class="fas fa-user-plus me-2"></i><?= t('btn.novo_usuario') ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="criar">
                    <div class="mb-3"><label class="form-label small"><?= t('th.nome') ?></label><input type="text" name="nome" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small"><?= t('th.email') ?></label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small"><?= t('th.usuario') ?></label><input type="text" name="usuario" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small"><?= t('uso.senha') ?></label><input type="password" name="senha" class="form-control" required minlength="6"></div>
                    <div class="mb-3">
                        <label class="form-label small"><?= t('th.perfil') ?></label>
                        <select name="perfil" class="form-select">
                            <option value="atendimento"><?= t('uso.perfil_atendimento') ?></option>
                            <option value="financeiro"><?= t('uso.perfil_financeiro') ?></option>
                            <option value="admin"><?= t('uso.perfil_admin') ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('btn.cancelar') ?></button>
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('btn.criar_usuario') ?></button>
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
                    <h6 class="modal-title"><i class="fas fa-user-edit me-2"></i><?= t('uso.editar') ?>: <?= htmlspecialchars($u['nome']) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <div class="mb-3"><label class="form-label small"><?= t('th.nome') ?></label><input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($u['nome']) ?>" required></div>
                    <div class="mb-3"><label class="form-label small"><?= t('th.email') ?></label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
                    <div class="mb-3">
                        <label class="form-label small"><?= t('th.perfil') ?></label>
                        <select name="perfil" class="form-select">
                            <?php foreach ($perfis as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $u['perfil'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="ativo" class="form-check-input" id="ativo<?= $u['id'] ?>" <?= $u['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativo<?= $u['id'] ?>"><?= t('uso.ativo') ?></label>
                    </div>
                    <div class="mb-3"><label class="form-label small"><?= t('uso.nova_senha') ?></label><input type="password" name="senha" class="form-control" minlength="6"></div>
                </div>
                <div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('btn.cancelar') ?></button>
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('btn.salvar') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php foreach ($usuarios as $u): ?>
<div class="modal fade" id="modalExcluir<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <div class="modal-header">
                    <h6 class="modal-title text-danger"><i class="fas fa-trash me-2"></i><?= t('uso.excluir') ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><?= t('uso.remover', ['<strong>' . htmlspecialchars($u['nome']) . '</strong>']) ?></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('uso.nao') ?></button>
                    <button type="submit" class="btn btn-danger btn-sm"><?= t('uso.sim_excluir') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>