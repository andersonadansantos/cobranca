<?php
// =====================================================
// BOLETO DE FATURA DE PLANO EXISTENTE (Mercado Pago - Super Admin)
// Gera o boleto para uma fatura (planos_pagamentos) já pendente do admin logado.
// =====================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
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

if (getConfig('super_plano_pix_boleto', '1') !== '1') {
    apiResponder(['erro' => 'O pagamento por PIX e boleto não está habilitado neste momento.']);
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

apiResponder(gerarBoletoFaturaPlano($pagamentoId));