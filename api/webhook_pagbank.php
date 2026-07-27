<?php
// =====================================================
// WEBHOOK DO PAGBANK - Notificações de Pagamento
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/pagbank.php';
require_once __DIR__ . '/../config/email_helpers.php';

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

$orderPagBankId = $dados['id'] ?? '';
$referenceId = $dados['reference_id'] ?? '';
$charges = $dados['charges'] ?? [];

$logEntry = date('Y-m-d H:i:s') . " | Order: {$orderPagBankId} | Ref: {$referenceId}\n";
file_put_contents(__DIR__ . '/webhook_pagbank_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

if (empty($orderPagBankId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order id']);
    exit;
}

$pdo = getConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE mp_payment_id = ?");
$stmt->execute([$orderPagBankId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    error_log("[PAGBANK WEBHOOK] Fatura não encontrada para order: {$orderPagBankId}");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Fatura not found']);
    exit;
}

foreach ($charges as $charge) {
    $chargeStatus = $charge['status'] ?? '';
    $chargeId = $charge['id'] ?? '';

    $novoStatus = null;
    $dataPagamento = null;

    switch ($chargeStatus) {
        case 'PAID':
            $novoStatus = 'pago';
            $dataPagamento = date('Y-m-d');
            break;
        case 'CANCELED':
            $novoStatus = 'cancelado';
            break;
        case 'DECLINED':
        case 'WAITING':
            $novoStatus = 'pendente';
            break;
        case 'IN_ANALYSIS':
            $novoStatus = 'pendente';
            break;
    }

    if ($novoStatus !== null && $novoStatus !== $fatura['status']) {
        $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
        $stmt->execute([$novoStatus, $dataPagamento, $fatura['id']]);

        $tipoPgto = $charge['payment_method']['type'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $fatura['id'],
            $chargeId ?: $orderPagBankId,
            $chargeStatus,
            $charge['payment_response']['message'] ?? '',
            ($charge['amount']['paid'] ?? 0) / 100,
            $tipoPgto,
            json_encode($dados)
        ]);

        if ($novoStatus === 'pago') {
            $stmtCli = $pdo->prepare("SELECT c.nome_razao, c.email FROM clientes c WHERE c.id = ?");
            $stmtCli->execute([$fatura['cliente_id']]);
            $cli = $stmtCli->fetch();
            if ($cli) {
                $fatura['nome_razao'] = $cli['nome_razao'];
                $fatura['email'] = $cli['email'];
                $fatura['data_pagamento'] = $dataPagamento;
                enviarEmailPagamento($fatura);
            }
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
