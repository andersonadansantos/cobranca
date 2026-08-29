<?php
// Cria cobrança PIX para compra de plano do admin logado
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/inter_pix.php';

session_start();

header('Content-Type: application/json');

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    echo json_encode(['erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$planoId = (int)($_POST['plano_id'] ?? 0);
if ($planoId <= 0) {
    echo json_encode(['erro' => 'Plano inválido.']);
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1");
$stmt->execute([$planoId]);
$plano = $stmt->fetch();
if (!$plano) {
    echo json_encode(['erro' => 'Plano não encontrado ou inativo.']);
    exit;
}

// Dados do admin (pagador)
$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) {
    echo json_encode(['erro' => 'Administrador não encontrado.']);
    exit;
}

$descricao = 'Plano ' . $plano['nome'] . ' - Assinatura';
$resultado = criarPixPlano($adminId, $planoId, $plano['preco'], $descricao, $admin);

echo json_encode($resultado);
