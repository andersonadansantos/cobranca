<?php
// =====================================================
// MULTI-TENANT: resolução do admin por subdomínio
// =====================================================
// Cada admin possui um subdomínio próprio (ex.: clientea.seudominio.com).
// Este helper resolve a qual admin a requisição pertence e é a fonte única
// de verdade para o scoping de dados por admin (admin_id).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

function getBaseDomain() {
    $pdo = getConnection();
    if (!$pdo) return '';
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = 'base_domain'");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? trim((string)$row['valor']) : '';
    } catch (PDOException $e) {
        return '';
    }
}

// Garante que o admin do tenant esteja carregado na sessão
function initTenant() {
    $pdo = getConnection();
    if (!$pdo) return;

    $host = preg_replace('/:\d+$/', '', strtolower(trim($_SERVER['HTTP_HOST'] ?? '')));
    if (empty($host)) return;

    $baseDomain = strtolower(ltrim(getBaseDomain(), '.'));
    $subdominio = '';

    if (!empty($baseDomain) && substr($host, -strlen($baseDomain)) === $baseDomain && $host !== $baseDomain) {
        $subdominio = rtrim(substr($host, 0, -strlen($baseDomain)), '.');
    }

    if (!empty($subdominio)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM administradores WHERE ativo = 1 AND (subdominio = ? OR usuario = ?) AND (subdominio IS NULL OR subdominio = '' OR subdominio = ? OR usuario = ?) LIMIT 1");
            $stmt->execute([$subdominio, $subdominio, $subdominio, $subdominio]);
            $admin = $stmt->fetch();
            if ($admin && empty($_SESSION['tenant_admin_id'])) {
                $_SESSION['tenant_admin_id'] = (int)$admin['id'];
            }
        } catch (PDOException $e) {}
    }
}

// Admin ativo do painel (logado) — usado pelo painel admin
function getCurrentAdminId() {
    if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0) {
        return (int)$_SESSION['admin_id'];
    }
    return 0;
}

// Admin do tenant (subdomínio) — usado onde não há sessão de admin (portal, webhooks)
function getTenantAdminId() {
    if (isset($_SESSION['tenant_admin_id']) && (int)$_SESSION['tenant_admin_id'] > 0) {
        return (int)$_SESSION['tenant_admin_id'];
    }
    if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0) {
        return (int)$_SESSION['admin_id'];
    }
    return 0;
}
