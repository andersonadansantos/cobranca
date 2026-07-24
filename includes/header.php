<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/auth.php';

$corPrimaria = getCorPrimaria();
$corSecundaria = getCorSecundaria();
$corFundo = getCorFundo();
$logo = getLogo();
$nomeSistema = getNomeSistema();
$pageTitle = isset($pageTitle) ? $pageTitle : $nomeSistema;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --cor-primaria: <?= $corPrimaria ?>;
            --cor-secundaria: <?= $corSecundaria ?>;
            --cor-fundo: <?= $corFundo ?>;
        }
    </style>
</head>
<body>
