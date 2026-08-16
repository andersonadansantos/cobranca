<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';

if (isMobileDevice()) {
    header('Location: /cobranca/app/recuperar_senha.php');
    exit;
}

if (isLoggedInUser()) {
    header('Location: index.php');
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
$logo = getLogoLogin();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - <?= htmlspecialchars($nomeSistema) ?></title>
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
                <h3>Recuperação de senha</h3>
                <p>Informe seu e-mail cadastrado e enviaremos um link para redefinir sua senha.</p>
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
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">E-mail cadastrado</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="Digite seu e-mail" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-paper-plane me-1"></i> Enviar link de recuperação
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/cobranca/usuario/login.php" class="text-decoration-none">
                            <small><i class="fas fa-arrow-left me-1"></i> Voltar para o Login</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
