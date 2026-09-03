<?php
// =====================================================
// CONFIGURAÇÕES DO SISTEMA
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tenant.php';

// Contexto de admin usado para ler/gravar configurações por admin.
// Painel admin -> admin logado. Demais contextos (portal, login, webhook) -> tenant do subdomínio.
function getConfigAdminId() {
    if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0) {
        return (int)$_SESSION['admin_id'];
    }
    return getTenantAdminId();
}

function getConfig($chave, $padrao = '') {
    $pdo = getConnection();
    if (!$pdo) return $padrao;
    $adminId = getConfigAdminId();

    if ($adminId > 0) {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id = ? AND chave = ?");
        $stmt->execute([$adminId, $chave]);
        $row = $stmt->fetch();
        if ($row) return $row['valor'];
    }

    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : $padrao;
}

function saveConfig($chave, $valor) {
    $pdo = getConnection();
    if (!$pdo) return false;
    $adminId = getConfigAdminId();
    if ($adminId <= 0) {
        $adminId = null;
    }
    $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    return $stmt->execute([$adminId, $chave, $valor]);
}

// Lê uma configuração global (admin_id NULL), independente da sessão.
// Usada pelo Super Admin para configurações únicas do sistema (ex.: cron).
function getConfigGlobal($chave, $padrao = '') {
    $pdo = getConnection();
    if (!$pdo) return $padrao;
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : $padrao;
}

// Grava uma configuração global (admin_id NULL), independente da sessão.
function salvarConfigGlobal($chave, $valor) {
    $pdo = getConnection();
    if (!$pdo) return false;
    $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (NULL, ?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    return $stmt->execute([$chave, $valor]);
}

function getAllConfig() {
    $pdo = getConnection();
    if (!$pdo) return [];
    $adminId = getConfigAdminId();

    $config = [];

    if ($adminId > 0) {
        // Globais (admin_id NULL) como base + sobreposição do admin atual
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE admin_id IS NULL");
        while ($row = $stmt->fetch()) {
            $config[$row['chave']] = $row['valor'];
        }
        $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        while ($row = $stmt->fetch()) {
            $config[$row['chave']] = $row['valor'];
        }
        return $config;
    }

    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE admin_id IS NULL");
    while ($row = $stmt->fetch()) {
        $config[$row['chave']] = $row['valor'];
    }
    return $config;
}

function getCorPrimaria() {
    return getConfig('cor_primaria', '#0f7b5c');
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

function generateInvoiceNumber($adminId = null) {
    $ano = date('Y');
    $mes = date('m');
    $pdo = getConnection();

    if ($adminId === null) {
        $adminId = getConfigAdminId();
    }
    $adminId = (int)$adminId;

    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) as maior FROM faturas WHERE numero LIKE ? AND numero REGEXP ? AND admin_id = ?");
    $prefixo = "FAT-{$ano}%";
    $stmt->execute([$prefixo, '^FAT-[0-9]{6}-[0-9]+$', $adminId]);
    $row = $stmt->fetch();
    $sequencia = intval($row['maior'] ?? 0) + 1;

    return "FAT-{$ano}{$mes}-" . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
}
