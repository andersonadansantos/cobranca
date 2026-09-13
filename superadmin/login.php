<?php
require_once __DIR__ . '/auth.php';

if (isLoggedInSuper()) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turnstile = trim($_POST['cf-turnstile-response'] ?? '');
    $turnstileSecret = getenv('TURNSTILE_SECRET_KEY') ?: '';
    if (empty($turnstileSecret)) {
        require_once __DIR__ . '/../config/settings.php';
        $turnstileSecret = getConfig('turnstile_secret_key', '');
    }
    if (empty($turnstileSecret)) {
        error_log('Turnstile secret não configurado (env TURNSTILE_SECRET_KEY ou config turnstile_secret_key). Verificação desativada.');
    } elseif (empty($turnstile)) {
        $erro = 'Confirme que você não é um robô.';
    } else {
        $verify = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query(['secret' => $turnstileSecret, 'response' => $turnstile])
            ]
        ]));
        $result = json_decode($verify ?? '', true);
        if (!$result || empty($result['success'])) {
            $erro = 'Falha na verificação. Tente novamente.';
        }
    }

    if (empty($erro)) {
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        if (empty($usuario) || empty($senha)) {
            $erro = 'Preencha todos os campos.';
        } elseif (loginSuper($usuario, $senha)) {
            header('Location: index.php');
            exit;
        } else {
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
    <title>Login Super Admin - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
    <div class="login-page">
        <div class="login-split">
            <div class="login-left">
                <?php if ($logo): ?>
                    <div class="logo"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo"></div>
                <?php else: ?>
                    <i class="fas fa-crown fa-3x mb-3"></i>
                <?php endif; ?>
                <h3>Acesso restrito - Super Admin</h3>
                <p>Gerencie os administradores, APIs de WhatsApp e planos do sistema <?= htmlspecialchars($nomeSistema) ?>.</p>
            </div>
            <div class="login-right">
                <div class="login-form">
                    <div class="mb-3 text-center">
                        <h2 class="mb-0">Super Admin</h2>
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
                                <input type="password" name="senha" id="senhaSuper" class="form-control" placeholder="Digite sua senha" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="alternarSenha('senhaSuper', this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="cf-turnstile" data-sitekey="0x4AAAAAAExttofBsCkR1InN" data-theme="light"></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Entrar
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="/cobranca/admin/login.php" class="text-decoration-none">
                            <small><i class="fas fa-arrow-left me-1"></i> Voltar para Login Admin</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
function alternarSenha(id, btn) {
    var campo = document.getElementById(id);
    if (!campo) return;
    var mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    btn.innerHTML = mostrar ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}
</script>
</body>
</html>
