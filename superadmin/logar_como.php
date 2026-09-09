<?php
// =====================================================
// LOGAR COMO ADMIN (impersonação pelo superadmin)
// =====================================================
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

// Retorno ao painel do superadmin
if (isset($_GET['voltar'])) {
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_nome'],
        $_SESSION['admin_usuario'],
        $_SESSION['admin_avatar'],
        $_SESSION['admin_origem'],
        $_SESSION['admin_login_via'],
        $_SESSION['impersonando']
    );
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: cadastros.php');
    exit;
}

$id = (int)($_GET['admin'] ?? 0);
if ($id <= 0) {
    header('Location: cadastros.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nome, usuario, avatar, ativo, origem FROM administradores WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$adm = $stmt->fetch();

if (!$adm) {
    header('Location: cadastros.php?msg=nao_encontrado');
    exit;
}

$_SESSION['admin_id'] = (int)$adm['id'];
$_SESSION['admin_nome'] = $adm['nome'];
$_SESSION['admin_usuario'] = $adm['usuario'];
$_SESSION['admin_avatar'] = $adm['avatar'];
$_SESSION['admin_origem'] = $adm['origem'] ?? 'painel';
$_SESSION['admin_login_via'] = 'impersonacao';
$_SESSION['impersonando'] = true;

header('Location: /cobranca/admin/index.php');
exit;