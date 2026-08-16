<?php
// =====================================================
// CONFIGURAÇÕES DO SISTEMA
// =====================================================

require_once __DIR__ . '/database.php';

function getConfig($chave, $padrao = '') {
    $pdo = getConnection();
    if (!$pdo) return $padrao;
    
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : $padrao;
}

function saveConfig($chave, $valor) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    return $stmt->execute([$chave, $valor, $valor]);
}

function getAllConfig() {
    $pdo = getConnection();
    if (!$pdo) return [];
    
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['chave']] = $row['valor'];
    }
    return $config;
}

function getCorPrimaria() {
    return getConfig('cor_primaria', '#0d6efd');
}

function getCorSecundaria() {
    return getConfig('cor_secundaria', '#6c757d');
}

function getCorFundo() {
    return getConfig('cor_fundo', '#f8f9fa');
}

function getLogo() {
    return getConfig('logo_empresa', '');
}

function getLogoLogin() {
    return getConfig('logo_login', '');
}

function getNomeSistema() {
    return getConfig('nome_sistema', 'Sistema de Cobrança');
}

function generateInvoiceNumber() {
    $ano = date('Y');
    $mes = date('m');
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) as maior FROM faturas WHERE numero LIKE ? AND numero REGEXP ?");
    $prefixo = "FAT-{$ano}%";
    $stmt->execute([$prefixo, '^FAT-[0-9]{6}-[0-9]+$']);
    $row = $stmt->fetch();
    $sequencia = intval($row['maior'] ?? 0) + 1;

    return "FAT-{$ano}{$mes}-" . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
}
