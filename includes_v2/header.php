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
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang_painel.php';

$logo = getLogo();
$nomeSistema = getNomeSistema();
$pageTitle = isset($pageTitle) ? $pageTitle : $nomeSistema;
$pageCategory = isset($pageCategory) ? $pageCategory : 'Visão Geral';
$painelIdioma = painelIdiomaAtual();

// Logo do admin/empresa se configurada
$logoEmpresaAdmin = function_exists('getConfig') ? getConfig('logo_empresa_admin', '') : '';
if ($logoEmpresaAdmin && function_exists('logoPathValido') && logoPathValido($logoEmpresaAdmin)) {
    $logo = $logoEmpresaAdmin;
}
?>
<!DOCTYPE html>
<html lang="<?= $painelIdioma ?>" class="fintech-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($nomeSistema) ?></title>
    <meta name="robots" content="noindex, nofollow, noarchive, noimageindex">
    <meta name="geo.region" content="BR">
    <link rel="icon" type="image/png" href="/cobranca/assets/img/favicon.png?v=20261020">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Vendors -->
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/bootstrap/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/cobranca/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    
    <!-- Fintech Minimalist & Executive Precision CSS -->
    <link href="/cobranca/assets/css/template_fintech.css?v=<?= filemtime(__DIR__ . '/../assets/css/template_fintech.css') ?>" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="/cobranca/assets/vendor/chartjs/chart.umd.min.js"></script>
</head>
<body class="fintech-theme">
<div class="fintech-wrapper">

