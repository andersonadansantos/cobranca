<?php
require_once __DIR__ . '/../includes/auth.php';
if (!function_exists('csrf_field')) { require_once __DIR__ . '/../includes/csrf.php'; }
require_once __DIR__ . '/../config/version.php';

if (isMobileDevice()) {
    header('Location: ' . APP_BASE . '/app/');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($erro)) {
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
$logo = getLogoClienteLogin();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="geo.region" content="BR">
    <link rel="icon" type="image/png" href="/cobranca/assets/img/favicon.png?v=20261020">
    <link rel="apple-touch-icon" href="/cobranca/assets/img/favicon.png?v=20261020">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
    <link href="/cobranca/assets/css/login_fintech.css?v=20261117-2" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="fintech-login">
    <main class="fin-shell">
        <section class="fin-card" aria-labelledby="finTituloCliente">
            <aside class="fin-brand">
                <div class="fin-brand-top">
                    <div class="fin-logo">
                        <?php if ($logo): ?>
                            <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($nomeSistema) ?>">
                        <?php else: ?>
                            <i class="fas fa-file-invoice-dollar"></i>
                        <?php endif; ?>
                    </div>
                    <span class="fin-env-badge"><i class="fas fa-user"></i> Área do Cliente</span>
                </div>

                <div class="fin-brand-copy">
                    <h1>Acesse sua conta no painel <?= htmlspecialchars($nomeSistema) ?></h1>
                    <p>Entre com suas credenciais e acompanhe suas faturas, pagamentos e histórico em um só lugar.</p>
                </div>

                <ul class="fin-features">
                    <li><i class="fas fa-file-invoice-dollar"></i><span>Faturas e pagamentos</span></li>
                    <li><i class="fas fa-clock-rotate-left"></i><span>Histórico organizado</span></li>
                    <li><i class="fas fa-headset"></i><span>Atendimento direto</span></li>
                </ul>

                <div class="fin-brand-footer">
                    <i class="fas fa-shield-halved"></i>
                    <span>Ambiente seguro para acompanhar suas faturas.</span>
                </div>
            </aside>

            <div class="fin-form-panel">
                <div class="fin-form-wrap">
                    <div class="fin-form-head">
                        <span class="fin-env-pill"><i class="fas fa-user"></i> Acesso do cliente</span>
                        <h2 id="finTituloCliente">Acesse sua conta</h2>
                        <p>Consulte faturas, pagamentos e mantenha seus dados em dia.</p>
                    </div>

                    <?php if ($erro): ?>
                        <div class="fin-alert" role="alert">
                            <i class="fas fa-circle-exclamation"></i>
                            <span><?= htmlspecialchars($erro) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="fin-form">
                        <?= csrf_field() ?>
                        <div class="fin-field">
                            <label for="loginCpfCnpj">CPF ou CNPJ</label>
                            <div class="fin-input">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="loginCpfCnpj" name="cpf_cnpj" placeholder="Digite seu CPF ou CNPJ" required autofocus value="<?= htmlspecialchars($_POST['cpf_cnpj'] ?? '') ?>" inputmode="numeric" pattern="[0-9.\-\/]*" autocomplete="username">
                            </div>
                        </div>
                        <div class="fin-field">
                            <label for="senhaUser">Senha</label>
                            <div class="fin-input">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="senhaUser" name="senha" placeholder="Digite sua senha" required minlength="6" autocomplete="current-password">
                                <button type="button" class="fin-eye" onclick="alternarSenha('senhaUser', this)" tabindex="-1" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="fin-turnstile">
                            <div class="cf-turnstile" data-sitekey="0x4AAAAAAExttofBsCkR1InN" data-theme="light"></div>
                        </div>
                        <button type="submit" class="fin-submit">
                            <i class="fas fa-arrow-right-to-bracket"></i> Entrar
                        </button>
                    </form>

                    <div class="fin-links">
                        <a href="/cobranca/usuario/recuperar_senha.php">
                            <i class="fas fa-key"></i> Esqueceu sua senha?
                        </a>
                    </div>

                    <div class="fin-form-footer">
                        <span>Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</span>
                        <span>Versão <?= htmlspecialchars(sistemaVersao()) ?></span>
                    </div>
                </div>
            </div>
        </section>
    </main>
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
