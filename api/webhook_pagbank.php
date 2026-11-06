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

// Quando o webhook chega pelo subdominio do admin, o initTenant ja definiu o
// tenant. Nesse caso somente faturas daquele admin podem ser baixadas.
$tenantAdminId = function_exists('getTenantAdminId') ? (int) getTenantAdminId() : 0;

$sqlFatura = "SELECT * FROM faturas WHERE mp_payment_id = ?";
$paramsFatura = [$orderPagBankId];
if ($tenantAdminId > 0) {
    $sqlFatura .= " AND admin_id = ?";
    $paramsFatura[] = $tenantAdminId;
}
$sqlFatura .= " ORDER BY id DESC LIMIT 1";
$stmt = $pdo->prepare($sqlFatura);
$stmt->execute($paramsFatura);
$fatura = $stmt->fetch();

if (!$fatura) {
    error_log("[PAGBANK WEBHOOK] Fatura não encontrada para order: {$orderPagBankId}");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Fatura not found']);
    exit;
}

// Contexto de tenant para resolução de configurações (email etc.)
if (!empty($fatura['admin_id'])) $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];

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
            $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
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
        $tipoPgto = $charge['payment_method']['type'] ?? 'unknown';
        $valorPago = ($charge['amount']['paid'] ?? 0) / 100;
        if ($novoStatus === 'pago') {
            confirmarPagamentoEBaixarFatura($fatura['id'], [
                'gateway' => 'pagbank',
                'payment_id' => $chargeId ?: $orderPagBankId,
                'valor_pago' => $valorPago > 0 ? $valorPago : $fatura['valor_final'],
                'tipo_pagamento' => $tipoPgto,
                'status_gateway' => $chargeStatus,
                'status_detail' => $charge['payment_response']['message'] ?? 'Recebido via PagBank',
                'data_pagamento' => $dataPagamento,
                'dados_raw' => $dados
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
            $stmt->execute([$novoStatus, $dataPagamento, $fatura['id']]);

            $stmt = $pdo->prepare("INSERT INTO pagamentos_log (admin_id, fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $fatura['admin_id'] ?? 0,
                $fatura['id'],
                $chargeId ?: $orderPagBankId,
                $chargeStatus,
                $charge['payment_response']['message'] ?? '',
                $valorPago,
                $tipoPgto,
                json_encode($dados)
            ]);
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
