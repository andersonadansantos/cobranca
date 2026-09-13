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
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../includes/lang_painel.php';

$corPrimaria = getCorPrimaria();
$corSecundaria = getCorSecundaria();
$corFundo = getCorFundo();
$logo = getLogo();
$nomeSistema = getNomeSistema();
$pageTitle = isset($pageTitle) ? $pageTitle : 'Super Admin';
$painelIdioma = painelIdiomaAtual();
?>
<!DOCTYPE html>
<html lang="<?= $painelIdioma ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Super Admin</title>
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>" rel="stylesheet">
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
