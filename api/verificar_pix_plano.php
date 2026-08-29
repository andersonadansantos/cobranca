<?php
// Verifica status do PIX de plano do admin logado (polling)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/inter_pix.php';

session_start();

header('Content-Type: application/json');

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    echo json_encode(['erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$pagamentoId = (int)($_POST['pagamento_id'] ?? 0);
if ($pagamentoId <= 0) {
    echo json_encode(['erro' => 'Pagamento inválido.']);
    exit;
}

$pdo = getConnection();
$stmt = $pdo->prepare("SELECT id FROM planos_pagamentos WHERE id = ? AND admin_id = ?");
$stmt->execute([$pagamentoId, $adminId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['erro' => 'Pagamento não encontrado para este usuário.']);
    exit;
}

$resultado = verificarPixPlano($pagamentoId);
echo json_encode($resultado);
