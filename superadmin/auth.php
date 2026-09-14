<?php
// =====================================================
// AUTENTICAÇÃO DO SUPERADMIN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    if (ini_get('session.use_only_cookies') == '0') {
        ini_set('session.use_only_cookies', '1');
    }
    ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

$sessionTimeout = 3600;
if (isset($_SESSION['super_last_activity']) && (time() - $_SESSION['super_last_activity']) > $sessionTimeout) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['super_last_activity'] = time();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/tenant.php';

// REGRA: o painel /superadmin existe apenas no DOMÍNIO PRINCIPAL (raiz do SaaS).
// Em subdomínio de cliente (tenant) NÃO existe /superadmin: leva ao superadmin
// do domínio principal. Os painéis do tenant são apenas {subdominio}/admin e
// {subdominio}/usuario.
$superHostAtual = preg_replace('/:\d+$/', '', strtolower(trim($_SERVER['HTTP_HOST'] ?? '')));
$superBaseAtual = function_exists('getBaseDomain') ? strtolower(ltrim((string)getBaseDomain(), '.')) : '';
if ($superBaseAtual !== '' && $superHostAtual !== $superBaseAtual && substr($superHostAtual, -strlen($superBaseAtual)) === $superBaseAtual) {
    $superProto = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') ? 'https' : 'http';
    header('Location: ' . $superProto . '://' . $superBaseAtual . '/superadmin/login.php');
    exit;
}

function isLoggedInSuper() {
    return isset($_SESSION['super_id']) && $_SESSION['super_id'] > 0;
}

function requireSuper() {
    if (!isLoggedInSuper()) {
        header('Location: login.php');
        exit;
    }
}

function loginSuper($usuario, $senha) {
    $pdo = getConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT * FROM superadmin WHERE usuario = ? LIMIT 1");
    $stmt->execute([$usuario]);
    $s = $stmt->fetch();

    if ($s) {
        if (password_verify($senha, $s['senha'])) {
            session_regenerate_id(true);
            $_SESSION['super_id'] = $s['id'];
            $_SESSION['super_nome'] = $s['nome'];
            $_SESSION['super_usuario'] = $s['usuario'];
            $_SESSION['super_avatar'] = $s['avatar'] ?? null;
            $upd = $pdo->prepare("UPDATE superadmin SET ultimo_login = NOW() WHERE id = ?");
            $upd->execute([$s['id']]);
            return true;
        }
    }
    return false;
}

function logoutSuper() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
