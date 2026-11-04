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
                $msg = 'A senha deve conter no mínimo 6 caracteres.';
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios_admin WHERE admin_id = ? AND (usuario = ? OR email = ?)");
                $check->execute([$adminId, $usuario, $email]);
                if ($check->fetchColumn() == 0) {
                    $limiteUsr = verificarLimitePlano('usuarios', $adminId);
                    if ($limiteUsr['ok']) {
                        $stmt = $pdo->prepare("INSERT INTO usuarios_admin (admin_id, nome, email, usuario, senha, perfil) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$adminId, $nome, $email, $usuario, password_hash($senha, PASSWORD_BCRYPT), $perfil]);
                        $tipo = 'success';
                        $msg = 'Novo operador cadastrado com sucesso!';
                    } else {
                        $tipo = 'danger';
                        $msg = $limiteUsr['mensagem'];
                    }
                } else {
                    $tipo = 'danger';
                    $msg = 'Nome de usuário ou e-mail já cadastrado.';
                }
            }
        } else {
            $tipo = 'danger';
            $msg = 'Por favor preencha todos os campos obrigatórios.';
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
                $msg = 'A senha deve conter no mínimo 6 caracteres.';
            } else {
                if ($senha) {
                    $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome=?, email=?, perfil=?, ativo=?, senha=? WHERE id=? AND admin_id=?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, password_hash($senha, PASSWORD_BCRYPT), $id, $adminId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios_admin SET nome=?, email=?, perfil=?, ativo=? WHERE id=? AND admin_id=?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, $id, $adminId]);
                }
                $tipo = 'success';
                $msg = 'Dados do operador atualizados com sucesso!';
            }
        }
    }

    if ($acao === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
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
            $msg = 'Operador removido com sucesso!';
        } else {
            $tipo = 'danger';
            $msg = 'A conta principal do administrador não pode ser excluída.';
        }
    }
}

$stmtUsuarios = $pdo->prepare("SELECT * FROM usuarios_admin WHERE admin_id = ? ORDER BY criado_em DESC");
$stmtUsuarios->execute([$adminId]);
$usuarios = $stmtUsuarios->fetchAll();
$totalUsuarios = count($usuarios);

$pageTitle = 'Operadores & Acessos';
$pageCategory = 'Administração';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Operadores & Controle de Acesso</h2>
            <p class="text-muted small mb-0">Gerencie subcontas e perfis de permissão para seus colaboradores.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-fintech-primary" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                <i class="bi bi-person-plus me-1"></i> Adicionar Operador
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <div><?= htmlspecialchars($msg) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabela de Usuários -->
    <div class="fintech-card">
        <div class="fintech-card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <h3 class="fintech-card-title mb-0">Colaboradores Cadastrados</h3>
            <span class="text-muted small"><?= $totalUsuarios ?> usuário(s)</span>
        </div>

        <div class="table-responsive">
            <table class="fintech-table w-100">
                <thead>
                    <tr>
                        <th>Nome & E-mail</th>
                        <th>Usuário</th>
                        <th>Perfil de Acesso</th>
                        <th>Status</th>
                        <th>Data de Cadastro</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
                                Nenhum operador auxiliar cadastrado. Clique em "Adicionar Operador" para criar o primeiro.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): 
                            $perfilBadge = 'primary';
                            if ($u['perfil'] === 'financeiro') $perfilBadge = 'warning';
                            if ($u['perfil'] === 'atendimento') $perfilBadge = 'info';
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:36px;height:36px;border-radius:10px;background:#f1f5f9;color:var(--fintech-primary);display:flex;align-items:center;justify-content:center;font-weight:700;">
                                            <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($u['nome']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-monospace text-dark"><?= htmlspecialchars($u['usuario']) ?></span>
                                </td>
                                <td>
                                    <span class="fintech-badge <?= $perfilBadge ?>">
                                        <span class="dot"></span> <?= ucfirst(htmlspecialchars($u['perfil'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['ativo']): ?>
                                        <span class="fintech-badge success"><span class="dot"></span> Ativo</span>
                                    <?php else: ?>
                                        <span class="fintech-badge danger"><span class="dot"></span> Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted"><?= date('d/m/Y', strtotime($u['criado_em'])) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-fintech-secondary" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?= $u['id'] ?>" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja remover este operador?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Modal Editar Usuário -->
                                    <div class="modal fade" id="modalEditarUsuario<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content" style="border-radius:16px;">
                                                <div class="modal-header border-bottom">
                                                    <h5 class="modal-title fw-bold text-slate-900">Editar Operador</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="acao" value="editar">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-semibold">Nome Completo *</label>
                                                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($u['nome']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-semibold">E-mail *</label>
                                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-semibold">Perfil de Acesso</label>
                                                            <select name="perfil" class="form-select">
                                                                <option value="admin" <?= $u['perfil'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                                                <option value="financeiro" <?= $u['perfil'] === 'financeiro' ? 'selected' : '' ?>>Financeiro</option>
                                                                <option value="atendimento" <?= $u['perfil'] === 'atendimento' ? 'selected' : '' ?>>Atendimento</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-semibold">Nova Senha (opcional)</label>
                                                            <input type="password" name="senha" class="form-control" placeholder="Deixe em branco para não alterar">
                                                        </div>
                                                        <div class="form-check form-switch mt-3">
                                                            <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativoUser<?= $u['id'] ?>" <?= $u['ativo'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label fw-semibold" for="ativoUser<?= $u['id'] ?>">Operador Ativo no Sistema</label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top bg-light">
                                                        <button type="button" class="btn btn-fintech-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-fintech-primary">Salvar Alterações</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Novo Usuário -->
<div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-slate-900">Novo Operador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nome Completo *</label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Lucas Silva" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">E-mail *</label>
                        <input type="email" name="email" class="form-control" placeholder="lucas@empresa.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nome de Usuário (Login) *</label>
                        <input type="text" name="usuario" class="form-control font-monospace" placeholder="lucas.silva" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Senha Inicial *</label>
                        <input type="password" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Perfil de Acesso</label>
                        <select name="perfil" class="form-select">
                            <option value="atendimento">Atendimento (Visualização e suporte)</option>
                            <option value="financeiro">Financeiro (Emissão e controle)</option>
                            <option value="admin">Administrador (Acesso completo)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-fintech-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fintech-primary">Criar Operador</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

