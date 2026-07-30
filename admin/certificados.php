<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$adminId = $_SESSION['admin_id'];
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {

        if ($_POST['acao'] === 'registrar') {
            $cert = getClientCertificate();
            if (!$cert) {
                $erro = 'Nenhum certificado digital detectado. Acesse esta página via HTTPS para registrar seu certificado.';
            } else {
                $nome = trim($_POST['nome_certificado'] ?? '');
                if (empty($nome)) {
                    $erro = 'Dê um nome para o certificado.';
                } else {
                    $resultado = registrarCertificado($adminId, $nome, $cert);
                    if ($resultado === 'duplicado') {
                        $erro = 'Este certificado já está registrado.';
                    } elseif ($resultado) {
                        $sucesso = 'Certificado registrado com sucesso!';
                    } else {
                        $erro = 'Erro ao registrar certificado.';
                    }
                }
            }
        }

        if ($_POST['acao'] === 'excluir' && isset($_POST['cert_id'])) {
            $certId = (int) $_POST['cert_id'];
            $stmt = $pdo->prepare("DELETE FROM admin_certificados WHERE id = ? AND admin_id = ?");
            $stmt->execute([$certId, $adminId]);
            $sucesso = 'Certificado removido.';
        }

        if ($_POST['acao'] === 'toggle' && isset($_POST['cert_id'])) {
            $certId = (int) $_POST['cert_id'];
            $stmt = $pdo->prepare("UPDATE admin_certificados SET ativo = NOT ativo WHERE id = ? AND admin_id = ?");
            $stmt->execute([$certId, $adminId]);
            $sucesso = 'Status do certificado atualizado.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM admin_certificados WHERE admin_id = ? ORDER BY criado_em DESC");
$stmt->execute([$adminId]);
$certificados = $stmt->fetchAll();

$hasSSL = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;

$pageTitle = 'Certificados Digitais';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Certificados Digitais</h5>
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
        <?php if ($erro): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-1"></i> <?= $erro ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i> <?= $sucesso ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($hasSSL): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-1"></i> Registrar Novo Certificado
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Para registrar um certificado, o navegador enviará automaticamente o certificado instalado em seu computador.</p>

                    <div class="p-3 rounded mb-3" style="background:#fff3cd; border:1px solid #ffc107;">
                        <small><i class="fas fa-info-circle me-1"></i> <strong>Importante:</strong> Certifique-se de que seu certificado digital ICP-Brasil está instalado e acessível pelo navegador antes de prosseguir.</small>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="acao" value="registrar">
                        <div class="mb-3">
                            <label class="form-label">Nome para identificação do certificado</label>
                            <input type="text" name="nome_certificado" class="form-control" placeholder="Ex: Meu e-CPF, Certificado da Empresa" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-fingerprint me-1"></i> Registrar Certificado
                        </button>
                    </form>

                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-link me-1"></i>
                            Acesse via HTTPS: <code>https://<?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?>/cobranca/admin/certificados.php</code>
                        </small>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-1"></i>
                <strong>HTTPS necessário:</strong> Para registrar certificados digitais, acesse via HTTPS:
                <code>https://<?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?>/cobranca/admin/certificados.php</code>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header">
                <i class="fas fa-list me-1"></i> Certificados Registrados (<?= count($certificados) ?>)
            </div>
            <div class="card-body p-0">
                <?php if (empty($certificados)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-certificate fa-2x mb-2"></i>
                        <p>Nenhum certificado registrado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Titular (CN)</th>
                                    <th>Emissor</th>
                                    <th>Validade</th>
                                    <th>Último Uso</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificados as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['subject_dn']) ?></td>
                                        <td><?= htmlspecialchars($c['issuer_dn']) ?></td>
                                        <td>
                                            <small>
                                                <?= date('d/m/Y', strtotime($c['validade_inicio'])) ?>
                                                a <?= date('d/m/Y', strtotime($c['validade_fim'])) ?>
                                            </small>
                                        </td>
                                        <td><?= $c['ultimo_uso'] ? date('d/m/Y H:i', strtotime($c['ultimo_uso'])) : '<span class="text-muted">Nunca</span>' ?></td>
                                        <td>
                                            <?php if ($c['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="acao" value="toggle">
                                                    <input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-<?= $c['ativo'] ? 'warning' : 'success' ?> btn-sm" title="<?= $c['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                                        <i class="fas fa-<?= $c['ativo'] ? 'ban' : 'check' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Excluir este certificado?')">
                                                    <input type="hidden" name="acao" value="excluir">
                                                    <input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
