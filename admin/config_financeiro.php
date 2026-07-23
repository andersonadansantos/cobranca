<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_financeiro') {
        $pdo = getConnection();
        $campos = ['financeiro_whatsapp', 'financeiro_email', 'financeiro_fone'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$campo, $valor, $valor]);
        }
        $mensagem = 'Contatos financeiros salvos com sucesso!';
        $tipo = 'success';
    }
}

$config = getAllConfig();
$whatsapp = $config['financeiro_whatsapp'] ?? '';
$email = $config['financeiro_email'] ?? '';
$fone = $config['financeiro_fone'] ?? '';

$pageTitle = 'Config. Financeiro';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Config. Financeiro</h5>
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
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= htmlspecialchars($mensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <p class="text-muted mb-4">Configure os meus de contato que serão exibidos aos clientes no painel do usuário.</p>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-headset me-2"></i>Contatos do Financeiro</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="salvar_financeiro">

                    <div class="mb-3">
                        <label for="whatsapp" class="form-label"><i class="fab fa-whatsapp text-success me-2"></i>Número do WhatsApp</label>
                        <input type="text" class="form-control" id="whatsapp" name="financeiro_whatsapp" value="<?= htmlspecialchars($whatsapp) ?>" placeholder="Ex: 5511999998888">
                        <div class="form-text">Formato internacional com código do país. Ex: 5511999998888</div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label"><i class="fas fa-envelope text-primary me-2"></i>E-mail</label>
                        <input type="email" class="form-control" id="email" name="financeiro_email" value="<?= htmlspecialchars($email) ?>" placeholder="Ex: financeiro@empresa.com.br">
                        <div class="form-text">E-mail que será aberto ao clicar no botão de envio.</div>
                    </div>

                    <div class="mb-3">
                        <label for="fone" class="form-label"><i class="fas fa-phone text-info me-2"></i>Telefone</label>
                        <input type="text" class="form-control" id="fone" name="financeiro_fone" value="<?= htmlspecialchars($fone) ?>" placeholder="Ex: 11999998888">
                        <div class="form-text">Número para ligação. Use apenas números com código de área.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar Contatos
                    </button>
                </form>
            </div>
        </div>

        <?php if ($whatsapp || $email || $fone): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-eye me-2"></i>Pré-visualização</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3">
                    <?php if ($whatsapp): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank" class="btn btn-success">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp
                        </a>
                    <?php endif; ?>
                    <?php if ($email): ?>
                        <a href="mailto:<?= htmlspecialchars($email) ?>" class="btn btn-primary">
                            <i class="fas fa-envelope me-2"></i>E-mail
                        </a>
                    <?php endif; ?>
                    <?php if ($fone): ?>
                        <a href="tel:<?= htmlspecialchars($fone) ?>" class="btn btn-info text-white">
                            <i class="fas fa-phone me-2"></i>Ligar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
