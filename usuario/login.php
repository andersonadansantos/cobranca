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
        
        if (empty($cpfCnpj)) {
            $erro = 'Informe seu CPF ou CNPJ.';
        } elseif (loginUser($cpfCnpj)) {
            header('Location: index.php');
            exit;
        } else {
            recordLoginAttempt('user', 'all');
            $erro = 'CPF/CNPJ não encontrado.';
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
                <div class="login-left-svg">
                    <svg viewBox="0 0 200 160" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="gradCoin" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#f7c948"/>
                                <stop offset="100%" style="stop-color:#e6a817"/>
                            </linearGradient>
                            <linearGradient id="gradWallet" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#0d6efd"/>
                                <stop offset="100%" style="stop-color:#0a58ca"/>
                            </linearGradient>
                        </defs>
                        <rect x="55" y="85" width="90" height="55" rx="10" fill="url(#gradWallet)" opacity="0.9"/>
                        <rect x="55" y="85" width="90" height="20" rx="10" fill="#0a58ca"/>
                        <rect x="125" y="93" width="20" height="10" rx="5" fill="#fff" opacity="0.8"/>
                        <g>
                            <animateTransform attributeName="transform" type="translate" values="0,-60;0,0" dur="1.8s" repeatCount="indefinite" begin="0s"/>
                            <animate attributeName="opacity" values="0;1;1;0" dur="1.8s" repeatCount="indefinite" begin="0s"/>
                            <circle cx="80" cy="30" r="14" fill="url(#gradCoin)" stroke="#d4a017" stroke-width="1.5"/>
                            <text x="80" y="35" text-anchor="middle" font-size="12" font-weight="bold" fill="#b8860b">$</text>
                        </g>
                        <g>
                            <animateTransform attributeName="transform" type="translate" values="0,-60;0,0" dur="1.8s" repeatCount="indefinite" begin="0.6s"/>
                            <animate attributeName="opacity" values="0;1;1;0" dur="1.8s" repeatCount="indefinite" begin="0.6s"/>
                            <circle cx="105" cy="25" r="12" fill="url(#gradCoin)" stroke="#d4a017" stroke-width="1.5"/>
                            <text x="105" y="30" text-anchor="middle" font-size="11" font-weight="bold" fill="#b8860b">$</text>
                        </g>
                        <g>
                            <animateTransform attributeName="transform" type="translate" values="0,-55;0,0" dur="1.8s" repeatCount="indefinite" begin="1.2s"/>
                            <animate attributeName="opacity" values="0;1;1;0" dur="1.8s" repeatCount="indefinite" begin="1.2s"/>
                            <circle cx="125" cy="28" r="11" fill="url(#gradCoin)" stroke="#d4a017" stroke-width="1.5"/>
                            <text x="125" y="33" text-anchor="middle" font-size="10" font-weight="bold" fill="#b8860b">$</text>
                        </g>
                        <circle cx="35" cy="60" r="3" fill="#f7c948" opacity="0.6">
                            <animate attributeName="opacity" values="0.6;0.1;0.6" dur="2s" repeatCount="indefinite" begin="0.3s"/>
                        </circle>
                        <circle cx="170" cy="55" r="2.5" fill="#f7c948" opacity="0.4">
                            <animate attributeName="opacity" values="0.4;0.1;0.4" dur="2.5s" repeatCount="indefinite" begin="0.8s"/>
                        </circle>
                        <polyline points="88,112 95,120 112,103" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0">
                            <animate attributeName="opacity" values="0;0;1" dur="1.8s" repeatCount="indefinite" begin="0s"/>
                        </polyline>
                    </svg>
                </div>
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
                        <div class="mb-4">
                            <label class="form-label">CPF ou CNPJ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" name="cpf_cnpj" class="form-control" placeholder="Digite seu CPF ou CNPJ" required autofocus value="<?= htmlspecialchars($_POST['cpf_cnpj'] ?? '') ?>" inputmode="numeric" pattern="[0-9.\-\/]*">
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
