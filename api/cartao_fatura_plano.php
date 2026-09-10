<?php
// =====================================================
// CARTÃO DE FATURA DE PLANO EXISTENTE (Mercado Pago - Super Admin)
// Recebe o token do cartão gerado no frontend (MercadoPago.js) e cobra uma
// fatura (planos_pagamentos) já pendente do admin logado.
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
    apiResponder(['sucesso' => false, 'erro' => 'Sessão expirada. Faça login novamente.']);
}

if (getConfig('super_plano_cartao', '1') !== '1') {
    apiResponder(['sucesso' => false, 'erro' => 'O pagamento com cartão não está habilitado neste momento.']);
}

$pagamentoId = (int)($_POST['pagamento_id'] ?? 0);
if ($pagamentoId <= 0) {
    apiResponder(['sucesso' => false, 'erro' => 'Fatura inválida.']);
}

$tipo = trim((string)($_POST['tipo'] ?? 'credito'));
$tipo = ($tipo === 'debito') ? 'debito' : 'credito';
$cardToken = trim((string)($_POST['card_token'] ?? ''));
$installments = max(1, (int)($_POST['installments'] ?? 1));
if ($tipo === 'debito') {
    $installments = 1;
}
$paymentMethodId = trim((string)($_POST['method'] ?? ''));

if ($cardToken === '') {
    apiResponder(['sucesso' => false, 'erro' => 'Token do cartão inválido.']);
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE id = ? AND admin_id = ?");
$stmt->execute([$pagamentoId, $adminId]);
$fatura = $stmt->fetch();
if (!$fatura) {
    apiResponder(['sucesso' => false, 'erro' => 'Fatura não encontrada.']);
}

if ($fatura['status'] !== 'pendente') {
    apiResponder(['sucesso' => false, 'erro' => 'Esta fatura não está mais pendente.']);
}

apiResponder(gerarCartaoFaturaPlano($pagamentoId, $cardToken, $installments, $paymentMethodId, $tipo));