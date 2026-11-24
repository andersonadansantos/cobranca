<?php
require_once __DIR__ . '/auth.php';
if (!function_exists('csrf_field')) { require_once __DIR__ . '/../includes/csrf.php'; }
require_once __DIR__ . '/../config/version.php';

if (isLoggedInSuper()) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();

    $rate = checkLoginRateLimit('superadmin', 'all');
    if (!empty($rate['blocked'])) {
        $erro = 'Muitas tentativas de acesso. Tente novamente em ' . (int)$rate['minutes'] . ' minuto(s).';
    }

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
            clearLoginAttempts('superadmin', 'all');
            header('Location: index.php');
            exit;
        } else {
            recordLoginAttempt('superadmin', 'all');
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
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="geo.region" content="BR">
    <link rel="icon" type="image/png" href="/cobranca/assets/img/favicon.png?v=20261020">
    <link rel="apple-touch-icon" href="/cobranca/assets/img/favicon.png?v=20261020">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Super Admin - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
    <link href="/cobranca/assets/css/login_fintech.css?v=20261117-2" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="fintech-login">
    <main class="fin-shell">
        <section class="fin-card" aria-labelledby="finTituloSuper">
            <aside class="fin-brand">
                <div class="fin-brand-top">
                    <div class="fin-logo">
                        <?php if ($logo): ?>
                            <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($nomeSistema) ?>">
                        <?php else: ?>
                            <i class="fas fa-crown"></i>
                        <?php endif; ?>
                    </div>
                    <span class="fin-env-badge"><i class="fas fa-crown"></i> Super Admin</span>
                </div>

                <div class="fin-brand-copy">
                    <h1>Acesso restrito &mdash; Super Admin</h1>
                    <p>Gerencie os administradores, APIs de WhatsApp e planos do sistema <?= htmlspecialchars($nomeSistema) ?>.</p>
                </div>

                <ul class="fin-features">
                    <li><i class="fas fa-users-gear"></i><span>Gestão de administradores</span></li>
                    <li><i class="fas fa-comment-dots"></i><span>APIs de WhatsApp</span></li>
                    <li><i class="fas fa-layer-group"></i><span>Planos e assinaturas</span></li>
                </ul>

                <div class="fin-brand-footer">
                    <i class="fas fa-shield-halved"></i>
                    <span>Acesso restrito à gestão geral da plataforma.</span>
                </div>
            </aside>

            <div class="fin-form-panel">
                <div class="fin-form-wrap">
                    <div class="fin-form-head">
                        <span class="fin-env-pill"><i class="fas fa-crown"></i> Gestão da plataforma</span>
                        <h2 id="finTituloSuper">Acesso restrito</h2>
                        <p>Entre com suas credenciais de Super Admin para gerenciar a plataforma.</p>
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
                            <label for="loginUsuarioSuper">Usuário</label>
                            <div class="fin-input">
                                <i class="fas fa-user"></i>
                                <input type="text" id="loginUsuarioSuper" name="usuario" placeholder="Digite seu usuário" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="fin-field">
                            <label for="senhaSuper">Senha</label>
                            <div class="fin-input">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="senhaSuper" name="senha" placeholder="Digite sua senha" required autocomplete="current-password">
                                <button type="button" class="fin-eye" onclick="alternarSenha('senhaSuper', this)" tabindex="-1" title="Mostrar ou ocultar senha" aria-label="Mostrar ou ocultar senha"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="fin-turnstile">
                            <div class="cf-turnstile" data-sitekey="0x4AAAAAAExttofBsCkR1InN" data-theme="light"></div>
                        </div>
                        <button type="submit" class="fin-submit">
                            <i class="fas fa-arrow-right-to-bracket"></i> Entrar
                        </button>
                    </form>

                    <div class="fin-form-footer">
                        <span>Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</span>
                        <span>Versão <?= htmlspecialchars(sistemaVersao()) ?></span>
                    </div>

                    <div class="fin-switch">
                        <a href="/cobranca/admin/login.php">
                            <i class="fas fa-arrow-left"></i> Voltar para Login Admin
                        </a>
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
