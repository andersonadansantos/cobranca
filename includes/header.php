<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang_painel.php';

$corPrimaria = getCorPrimaria();
$corSecundaria = getCorSecundaria();
$corFundo = getCorFundo();
$logo = getLogo();
$nomeSistema = getNomeSistema();
$pageTitle = isset($pageTitle) ? $pageTitle : $nomeSistema;
$painelIdioma = painelIdiomaAtual();
?>
<!DOCTYPE html>
<html lang="<?= $painelIdioma ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css?v=<?= filemtime((defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/assets/css/style.css') ?>" rel="stylesheet">
    <script src="/cobranca/assets/vendor/chartjs/chart.umd.min.js"></script>
    <script>
    (function(){
        var t = localStorage.getItem('theme') || 'light';
        var d = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bs-theme', d ? 'dark' : 'light');
    })();
    </script>
    <script>
    window.PAINEL_LANG = <?= json_encode([
        'CUR' => $painelIdioma,
        'CODES' => array_keys(painelIdiomas()),
        'LABELS' => painelIdiomas(),
        'FLAGS' => array_map('painelBandeira', array_keys(painelIdiomas())),
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <style>
        :root {
            --cor-primaria: <?= $corPrimaria ?: '#0f7b5c' ?>;
            --cor-secundaria: <?= $corSecundaria ?: '#6c757d' ?>;
            --cor-fundo: <?= $corFundo ?: '#fbfbfc' ?>;
        }
    </style>
</head>
<body>
<?php if (($_SESSION['admin_origem'] ?? '') === 'demo'): ?>
    <div class="demo-banner">
        <i class="fas fa-flask me-2"></i>
        <strong>Você está na conta de demonstração.</strong>&nbsp;Envios de WhatsApp e e-mail estão bloqueados para não gerar cobranças reais.
        <a href="/planos.php" class="ms-2" style="color:#fff;text-decoration:underline;">Conheça os planos</a>
    </div>
<?php endif; ?>
