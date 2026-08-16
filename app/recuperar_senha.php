<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';

if (isLoggedInUser()) {
    header('Location: dashboard.php');
    exit;
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $erro = 'Informe seu e-mail cadastrado.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, nome_razao, email FROM clientes WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);

            $upd = $pdo->prepare("UPDATE clientes SET token_recuperacao = ?, token_recuperacao_expira = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
            $upd->execute([$hash, $cliente['id']]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/cobranca/usuario/redefinir_senha.php?token=' . $token;

            if (enviarEmailRecuperacaoSenha($cliente['email'], $cliente['nome_razao'], $link, 'usuario')) {
                $mensagem = 'Enviamos um link de recuperação para o e-mail informado.';
            } else {
                $erro = 'Não foi possível enviar o e-mail. Tente novamente mais tarde.';
            }
        } else {
            $mensagem = 'Se o e-mail informado estiver cadastrado, enviaremos um link de recuperação.';
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
            <p class="subtitle">Recuperação de senha</p>

            <?php if ($erro): ?>
                <div class="app-alert app-alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                </div>
            <?php endif; ?>

            <?php if ($mensagem): ?>
                <div class="app-alert" style="background:rgba(22,163,74,0.1);color:#16a34a;border:1px solid rgba(22,163,74,0.3);">
                    <i class="fas fa-check-circle"></i> <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>E-mail cadastrado</label>
                    <div class="app-input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="app-input" placeholder="Digite seu e-mail" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="app-btn app-btn-primary" style="margin-bottom:6px;">
                    <i class="fas fa-paper-plane"></i> Enviar link de recuperação
                </button>
                <div class="text-center">
                    <a href="index.php" style="color:#94a3b8;text-decoration:none;font-size:0.85rem;">
                        <i class="fas fa-arrow-left"></i> Voltar para o Login
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
