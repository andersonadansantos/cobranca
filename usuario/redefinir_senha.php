<?php
require_once __DIR__ . '/../includes/auth.php';

if (isMobileDevice()) {
    $tok = trim($_GET['token'] ?? $_POST['token'] ?? '');
    header('Location: /cobranca/app/redefinir_senha.php?token=' . urlencode($tok));
    exit;
}

if (isLoggedInUser()) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if (empty($token)) {
    header('Location: recuperar_senha.php');
    exit;
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    if (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $pdo = getConnection();
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE token_recuperacao = ? AND token_recuperacao_expira > NOW() AND ativo = 1");
        $stmt->execute([$hash]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            $newHash = password_hash($senha, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE clientes SET senha = ?, token_recuperacao = NULL, token_recuperacao_expira = NULL WHERE id = ?");
            $upd->execute([$newHash, $cliente['id']]);
            $mensagem = 'Senha redefinida com sucesso! Você já pode entrar.';
        } else {
            $erro = 'Link inválido ou expirado. Solicite uma nova recuperação.';
        }
    }
}

require_once __DIR__ . '/../config/settings.php';
$logo = getLogoLogin();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-page">
        <div class="login-split">
            <div class="login-left">
                <?php if ($logo): ?>
                    <div class="logo"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo"></div>
                <?php else: ?>
                    <i class="fas fa-file-invoice-dollar fa-3x mb-3"></i>
                <?php endif; ?>
                <h3>Redefinir senha</h3>
                <p>Defina uma nova senha para acessar sua conta no <?= htmlspecialchars($nomeSistema) ?>.</p>
            </div>
            <div class="login-right">
                <div class="login-form">
                    <div class="mb-3 text-center">
                        <h2 class="mb-0"><?= htmlspecialchars($nomeSistema) ?></h2>
                    </div>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger py-2">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= $erro ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensagem): ?>
                        <div class="alert alert-success py-2">
                            <i class="fas fa-check-circle me-1"></i> <?= $mensagem ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="/cobranca/usuario/login.php" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-1"></i> Ir para o Login
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <div class="mb-3">
                                <label class="form-label">Nova senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6" autofocus>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirmar nova senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="confirmar_senha" class="form-control" placeholder="Repita a nova senha" required minlength="6">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-check me-1"></i> Redefinir senha
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
