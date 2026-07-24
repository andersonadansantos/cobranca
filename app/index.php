<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedInUser()) {
    header('Location: dashboard.php');
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
        
        if (empty($cpfCnpj)) {
            $erro = 'Informe seu CPF ou CNPJ.';
        } elseif (loginUser($cpfCnpj)) {
            header('Location: dashboard.php');
            exit;
        } else {
            recordLoginAttempt('user', 'all');
            $erro = 'CPF/CNPJ não encontrado.';
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
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
            <p class="subtitle">Área do Cliente</p>

            <?php if ($erro): ?>
                <div class="app-alert app-alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>CPF ou CNPJ</label>
                    <div class="app-input-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="cpf_cnpj" class="app-input" placeholder="Digite seu CPF ou CNPJ" required inputmode="numeric" autofocus>
                    </div>
                </div>
                <div style="margin-bottom:16px; display:flex; justify-content:center;">
                    <div class="g-recaptcha" data-sitekey="6LcVcGEtAAAAAF34K5Uf5bUHqWH2WlPUChwl8VsZ"></div>
                </div>
                <button type="submit" class="app-btn app-btn-primary" style="margin-bottom:6px;">
                    <i class="fas fa-arrow-right-to-bracket"></i> Entrar
                </button>
                <div class="text-center"><small style="color:#94a3b8; font-size:0.65rem;">Desenvolvido por WD Soluções Digitais.</small></div>
            </form>
        </div>
    </div>
</body>
</html>