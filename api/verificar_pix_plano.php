<?php
// Verifica status do PIX de plano do admin logado (polling)
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

$pagamentoId = (int)($_POST['pagamento_id'] ?? 0);
if ($pagamentoId <= 0) {
    apiResponder(['erro' => 'Pagamento inválido.']);
}

$pdo = getConnection();
$stmt = $pdo->prepare("SELECT id FROM planos_pagamentos WHERE id = ? AND admin_id = ?");
$stmt->execute([$pagamentoId, $adminId]);
if (!$stmt->fetchColumn()) {
    apiResponder(['erro' => 'Pagamento não encontrado para este usuário.']);
}

$resultado = verificarPixPlano($pagamentoId);
apiResponder($resultado);
