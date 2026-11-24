<?php
require_once __DIR__ . '/../includes/auth.php';
if (!function_exists('csrf_field')) { require_once __DIR__ . '/../includes/csrf.php'; }
require_once __DIR__ . '/../config/version.php';
require_once __DIR__ . '/../includes/lang_painel.php';

if (isLoggedInAdmin()) {
    $_SESSION['admin_template'] = 'classico';
    header('Location: index.php');
    exit;
}

$erro = '';

$rateLimit = checkLoginRateLimit('admin', 'all');
if ($rateLimit['blocked']) {
    $erro = t('login.erro_rate', [$rateLimit['minutes']]);
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
        $erro = t('login.erro_turnstile');
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
            $erro = t('login.erro_falha');
        }
    }

    if (empty($erro)) {
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        
        if (empty($usuario) || empty($senha)) {
            $erro = t('login.erro_vazio');
        } elseif (loginAdmin($usuario, $senha)) {
            $_SESSION['admin_template'] = 'classico';
            header('Location: index.php');
            exit;
        } else {
            recordLoginAttempt('admin', 'all');
            $erro = t('login.erro_invalido');
        }
    }
}

require_once __DIR__ . '/../config/settings.php';
$logo = getLogoLoginGlobal();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="<?= painelIdiomaAtual() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="geo.region" content="BR">
    <link rel="icon" type="image/png" href="/cobranca/assets/img/favicon.png?v=20261020">
    <link rel="apple-touch-icon" href="/cobranca/assets/img/favicon.png?v=20261020">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login.titulo') ?> - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
    <link href="/cobranca/assets/css/login_fintech.css?v=20261117-2" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="fintech-login">
    <main class="fin-shell">
        <section class="fin-card" aria-labelledby="finTituloAdmin">
            <aside class="fin-brand">
                <div class="fin-brand-top">
                    <div class="fin-logo">
                        <?php if ($logo): ?>
                            <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($nomeSistema) ?>">
                        <?php else: ?>
                            <i class="fas fa-shield-halved"></i>
                        <?php endif; ?>
                    </div>
                    <span class="fin-env-badge"><i class="fas fa-user-shield"></i> <?= t('login.badge_admin') ?></span>
                </div>

                <div class="fin-brand-copy">
                    <h1><?= t('login.acesse', [htmlspecialchars($nomeSistema)]) ?></h1>
                    <p><?= t('login.entre', [htmlspecialchars($nomeSistema)]) ?></p>
                </div>

                <ul class="fin-features">
                    <li><i class="fas fa-lock"></i><span><?= t('login.feat_seguro') ?></span></li>
                    <li><i class="fas fa-chart-pie"></i><span><?= t('login.feat_controle') ?></span></li>
                    <li><i class="fas fa-paper-plane"></i><span><?= t('login.feat_envios') ?></span></li>
                </ul>

                <div class="fin-brand-footer">
                    <i class="fas fa-shield-halved"></i>
                    <span><?= t('login.rodape_seguranca') ?></span>
                </div>
            </aside>

            <div class="fin-form-panel">
                <div class="fin-form-wrap">
                    <div class="fin-form-head">
                        <span class="fin-env-pill"><i class="fas fa-user-shield"></i> <?= t('login.pill_admin') ?></span>
                        <h2 id="finTituloAdmin"><?= t('login.head_admin') ?></h2>
                        <p><?= t('login.desc_admin') ?></p>
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
                            <label for="loginUsuarioAdmin"><?= t('login.usuario') ?></label>
                            <div class="fin-input">
                                <i class="fas fa-user"></i>
                                <input type="text" id="loginUsuarioAdmin" name="usuario" placeholder="<?= t('login.ph_usuario') ?>" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="fin-field">
                            <label for="senhaAdmin"><?= t('login.senha') ?></label>
                            <div class="fin-input">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="senhaAdmin" name="senha" placeholder="<?= t('login.ph_senha') ?>" required autocomplete="current-password">
                                <button type="button" class="fin-eye" onclick="alternarSenha('senhaAdmin', this)" tabindex="-1" title="<?= htmlspecialchars(t('login.mostrar')) ?>" aria-label="<?= htmlspecialchars(t('login.mostrar')) ?>"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="fin-turnstile">
                            <div class="cf-turnstile" data-sitekey="0x4AAAAAAExttofBsCkR1InN" data-theme="light"></div>
                        </div>
                        <button type="submit" class="fin-submit">
                            <i class="fas fa-arrow-right-to-bracket"></i> <?= t('login.entrar') ?>
                        </button>
                    </form>

                    <div class="fin-links">
                        <a href="/cobranca/admin/recuperar_senha.php">
                            <i class="fas fa-key"></i> <?= t('login.lembrar') ?>
                        </a>
                    </div>

                    <div class="fin-form-footer">
                        <span><?= t('login.direitos') ?></span>
                        <span><?= t('login.versao', [sistemaVersao()]) ?></span>
                    </div>

                    <div class="fin-switch">
                        <a href="/cobranca/usuario/">
                            <i class="fas fa-arrow-left"></i> <?= t('login.voltar_cliente') ?>
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
