<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedInUser()) {
    header('Location: dashboard.php');
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
$logo = getConfig('logo_mobile', '') ?: getLogoLogin();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#6C5CE7">
    <title><?= htmlspecialchars($nomeSistema) ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="icon.php?size=192">
    <link rel="apple-touch-icon" href="icon.php?size=192">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
    <script src="pwa.js"></script>
</head>
<body>
    <div class="app-login">
        <div class="app-login-card app-animate">
            <?php if ($logo): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" class="app-logo">
            <?php else: ?>
                <div style="width:200px;height:200px;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fas fa-file-invoice-dollar" style="font-size:3rem;color:var(--app-primary)"></i>
                </div>
            <?php endif; ?>
            <h1><?= htmlspecialchars($nomeSistema) ?></h1>
            <p class="subtitle">Redefinir senha</p>

            <?php if ($erro): ?>
                <div class="app-alert app-alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                </div>
            <?php endif; ?>

            <?php if ($mensagem): ?>
                <div class="app-alert" style="background:rgba(22,163,74,0.1);color:#16a34a;border:1px solid rgba(22,163,74,0.3);">
                    <i class="fas fa-check-circle"></i> <?= $mensagem ?>
                </div>
                <a href="index.php" class="app-btn app-btn-primary" style="display:block;text-align:center;">
                    <i class="fas fa-arrow-right-to-bracket"></i> Ir para o Login
                </a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group">
                        <label>Nova senha</label>
                        <div class="app-input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="senha" class="app-input" placeholder="Mínimo 6 caracteres" required minlength="6" autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirmar nova senha</label>
                        <div class="app-input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirmar_senha" class="app-input" placeholder="Repita a nova senha" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" class="app-btn app-btn-primary" style="margin-bottom:6px;">
                        <i class="fas fa-check"></i> Redefinir senha
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
