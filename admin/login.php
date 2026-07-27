<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedInAdmin()) {
    header('Location: index.php');
    exit;
}

$erro = '';

$rateLimit = checkLoginRateLimit('admin', 'all');
if ($rateLimit['blocked']) {
    $erro = 'Muitas tentativas. Tente novamente em ' . $rateLimit['minutes'] . ' minuto(s).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($erro)) {
    $recaptcha = trim($_POST['g-recaptcha-response'] ?? '');
    if (empty($recaptcha)) {
        $erro = 'Confirme que você não é um robô.';
    } else {
        $verify = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query(['secret' => '6LcVcGEtAAAAAJlbXJXzzbXXnTUw-2y4HFP1AjBT', 'response' => $recaptcha, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''])
            ]
        ]));
        $result = json_decode($verify ?? '', true);
        if (!$result || empty($result['success'])) {
            $erro = 'Falha na verificação reCAPTCHA. Tente novamente.';
        }
    }

    if (empty($erro)) {
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        
        if (empty($usuario) || empty($senha)) {
            $erro = 'Preencha todos os campos.';
        } elseif (loginAdmin($usuario, $senha)) {
            header('Location: index.php');
            exit;
        } else {
            recordLoginAttempt('admin', 'all');
            $erro = 'Usuário ou senha inválidos.';
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
    <title>Login Admin - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="login-page">
        <div class="login-split">
            <div class="login-left">
                <?php if ($logo): ?>
                    <div class="logo"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo"></div>
                <?php else: ?>
                    <i class="fas fa-shield-halved fa-3x mb-3"></i>
                <?php endif; ?>
                <h3>Acesse sua conta no painel <?= htmlspecialchars($nomeSistema) ?></h3>
                <p>Entre com suas credenciais e acesse seu painel <?= htmlspecialchars($nomeSistema) ?>.</p>
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

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Usuário</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="usuario" class="form-control" placeholder="Digite seu usuário" required autofocus value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="g-recaptcha" data-sitekey="6LcVcGEtAAAAAF34K5Uf5bUHqWH2WlPUChwl8VsZ" data-size="normal"></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Entrar
                        </button>
                    </form>

                    <div class="text-center my-3">
                        <div class="d-flex align-items-center mb-3">
                            <hr class="flex-grow-1">
                            <span class="mx-2 text-muted" style="font-size:0.8rem;">ou</span>
                            <hr class="flex-grow-1">
                        </div>
                        <a href="https://localhost/cobranca/admin/login_certificado.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-certificate me-1"></i> Entrar com Certificado Digital
                        </a>
                    </div>

                    <div class="text-center"><small class="text-muted" style="font-size:0.65rem;">Desenvolvido por WD Soluções Digitais.</small></div>

                    <div class="text-center mt-3">
                        <a href="/cobranca/usuario/login.php" class="text-decoration-none">
                            <small><i class="fas fa-arrow-left me-1"></i> Voltar para Login do Cliente</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
