<?php
// Webhook de pagamento de PLANOS (PIX Banco Inter - Super Admin)
// Confirma pagamento e ativa o plano do admin
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/inter_pix.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
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

$logEntry = date('Y-m-d H:i:s') . " | Webhook PIX Plano | " . json_encode($dados) . "\n";
file_put_contents(__DIR__ . '/webhook_pix_plano_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

// A notificação do Inter traz o código de solicitação da cobrança
$codigoSolicitacao = $dados['codigoSolicitacao'] ?? '';
$situacao = strtoupper($dados['situacao'] ?? '');

$pdo = getConnection();

// Tenta localizar o pagamento pelo código de solicitação
if (!empty($codigoSolicitacao)) {
    $stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE codigo_solicitacao = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$codigoSolicitacao]);
    $pg = $stmt->fetch();

    if ($pg && in_array($situacao, ['PAGO', 'PAGA', 'RECEBIDO', 'CONFIRMADO'])) {
        $dataPagamento = date('Y-m-d H:i:s');
        $pagoEm = null;
        $pix = $dados['horarioPagamento'] ?? ($dados['dataHoraPagamento'] ?? null);
        if ($pix) {
            $pagoEm = date('Y-m-d H:i:s', strtotime($pix));
        }
        $pdo->prepare("UPDATE planos_pagamentos SET status='pago', pago_em=? WHERE id=?")
            ->execute([$pagoEm ?: $dataPagamento, $pg['id']]);

        ativarPlanoAdmin($pg['admin_id'], $pg['plano_id']);
    }

    if ($pg && in_array($situacao, ['EXPIRADO', 'EXPIRADA', 'CANCELADO', 'CANCELADA', 'VENCIDO', 'VENCIDA'])) {
        $novo = in_array($situacao, ['EXPIRADO', 'EXPIRADA']) ? 'expirado' : (in_array($situacao, ['CANCELADO', 'CANCELADA']) ? 'cancelado' : 'vencido');
        $pdo->prepare("UPDATE planos_pagamentos SET status=? WHERE id=?")->execute([$novo, $pg['id']]);
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
