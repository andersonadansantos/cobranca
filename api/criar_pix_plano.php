<?php
// Cria cobrança PIX para compra de plano do admin logado
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/inter_pix.php';

header('Content-Type: application/json');

function apiResponder($data) {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    echo json_encode($data);
    exit;
}

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    apiResponder(['erro' => 'Sessão expirada. Faça login novamente.']);
}

$planoId = (int)($_POST['plano_id'] ?? 0);
if ($planoId <= 0) {
    apiResponder(['erro' => 'Plano inválido.']);
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1");
$stmt->execute([$planoId]);
$plano = $stmt->fetch();
if (!$plano) {
    apiResponder(['erro' => 'Plano não encontrado ou inativo.']);
}

// Dados do admin (pagador)
$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) {
    apiResponder(['erro' => 'Administrador não encontrado.']);
}

$descricao = 'Plano ' . $plano['nome'] . ' - Assinatura';
$resultado = criarPixPlano($adminId, $planoId, $plano['preco'], $descricao, $admin);

apiResponder($resultado);
