<?php
// Gera (ou reutiliza) o PIX de uma fatura de plano já existente do admin logado
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
    apiResponder(['erro' => 'Fatura inválida.']);
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE id = ? AND admin_id = ?");
$stmt->execute([$pagamentoId, $adminId]);
$fatura = $stmt->fetch();
if (!$fatura) {
    apiResponder(['erro' => 'Fatura não encontrada.']);
}

if ($fatura['status'] !== 'pendente') {
    apiResponder(['erro' => 'Esta fatura não está mais pendente.']);
}

$resultado = gerarPixFaturaPlano($pagamentoId);

apiResponder($resultado);