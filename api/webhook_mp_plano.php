<?php
// Webhook de pagamento de PLANOS (PIX Mercado Pago - Super Admin)
// Confirma pagamento e ativa o plano do admin
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/inter_pix.php';
require_once __DIR__ . '/../config/mercadopago.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!$dados) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$logEntry = date('Y-m-d H:i:s') . " | Webhook MP Plano | " . json_encode($dados) . "\n";
file_put_contents(__DIR__ . '/webhook_mp_plano_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

// A notificação do Mercado Pago traz o id do pagamento
$paymentId = $dados['data']['id'] ?? ($dados['data_id'] ?? ($dados['payment_id'] ?? 0));
if (empty($paymentId)) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored']);
    exit;
}

$pdo = getConnection();

// Localiza o pagamento de plano pelo id do pagamento (guardado em codigo_solicitacao)
$stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE codigo_solicitacao = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1");
$stmt->execute([(string)$paymentId]);
$pg = $stmt->fetch();

if (!$pg) {
    http_response_code(200);
    echo json_encode(['status' => 'not_found']);
    exit;
}

$mp = consultarPagamentoMPSuper($paymentId);
if (!$mp) {
    http_response_code(200);
    echo json_encode(['status' => 'consulta_falhou']);
    exit;
}

$status = strtolower($mp['status'] ?? '');

if ($status === 'approved') {
    $pdo->prepare("UPDATE planos_pagamentos SET status='pago', pago_em=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s'), $pg['id']]);
    ativarPlanoAdmin($pg['admin_id'], $pg['plano_id'], (int)($pg['duracao_meses'] ?? 1));
    echo json_encode(['status' => 'approved']);
    exit;
}

if (in_array($status, ['rejected', 'cancelled', 'expired'])) {
    $novo = in_array($status, ['cancelled', 'rejected']) ? 'cancelado' : 'expirado';
    $pdo->prepare("UPDATE planos_pagamentos SET status=? WHERE id=?")->execute([$novo, $pg['id']]);
}

http_response_code(200);
echo json_encode(['status' => 'ok']);