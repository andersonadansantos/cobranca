<?php
require_once __DIR__ . '/../includes/auth.php';

if (isMobileDevice()) {
    header('Location: /cobranca/app/');
    exit;
}

if (isLoggedInUser()) {
    header('Location: index.php');
    exit;
}

$erro = '';

$rateLimit = checkLoginRateLimit('user', 'all');
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
        $cpfCnpj = trim($_POST['cpf_cnpj'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        
        if (empty($cpfCnpj) || empty($senha)) {
            $erro = 'Informe seu CPF/CNPJ e senha.';
        } elseif (loginUser($cpfCnpj, $senha)) {
            header('Location: index.php');
            exit;
        } else {
            recordLoginAttempt('user', 'all');
            $erro = 'CPF/CNPJ ou senha inválidos.';
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
    <title>Login - <?= htmlspecialchars($nomeSistema) ?></title>
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
                    <i class="fas fa-file-invoice-dollar fa-3x mb-3"></i>
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
                            <label class="form-label">CPF ou CNPJ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" name="cpf_cnpj" class="form-control" placeholder="Digite seu CPF ou CNPJ" required autofocus value="<?= htmlspecialchars($_POST['cpf_cnpj'] ?? '') ?>" inputmode="numeric" pattern="[0-9.\-\/]*">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required minlength="6">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="g-recaptcha" data-sitekey="6LcVcGEtAAAAAF34K5Uf5bUHqWH2WlPUChwl8VsZ" data-size="normal"></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Entrar
                        </button>
                        <div class="text-center"><small class="text-muted" style="font-size:0.65rem;">Desenvolvido por WD Soluções Digitais.</small></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
