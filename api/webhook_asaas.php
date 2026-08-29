<?php
// =====================================================
// WEBHOOK DO ASAAS - Notificações de Pagamento
// Eventos: PAYMENT_RECEIVED, PAYMENT_CONFIRMED,
// PAYMENT_REFUNDED, PAYMENT_DELETED, etc.
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/asaas.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validação opcional por token (header asaas-access-token)
$webhookToken = getConfig('asaas_webhook_token', '');
if (!empty($webhookToken)) {
    $recebido = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? ($_GET['token'] ?? '');
    if (!hash_equals($webhookToken, (string) $recebido)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || empty($dados['event']) || empty($dados['payment']['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$evento = $dados['event'];
$payment = $dados['payment'];
$paymentId = $payment['id'];

// Log da notificação
$logEntry = date('Y-m-d H:i:s') . " | Evento: {$evento} | Payment: {$paymentId}\n";
file_put_contents(__DIR__ . '/webhook_asaas_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

$statusAsaas = strtoupper($payment['status'] ?? '');
$mapeamento = [
    'PAYMENT_RECEIVED' => 'pago',
    'PAYMENT_CONFIRMED' => 'pago',
    'PAYMENT_OVERDUE' => 'vencido',
    'PAYMENT_REFUNDED' => 'cancelado',
    'PAYMENT_CHARGEBACK_REQUESTED' => 'cancelado',
    'PAYMENT_CHARGEBACK_DISPUTE' => 'cancelado',
    'PAYMENT_DELETED' => 'cancelado',
    'PAYMENT_FAILED' => 'cancelado',
];

if (isset($mapeamento[$evento])) {
    $novoStatus = $mapeamento[$evento];
    $dataPagamento = $novoStatus === 'pago' ? date('Y-m-d') : null;

    $pdo = getConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM faturas WHERE mp_payment_id = ? AND api_pagamento = 'asaas'");
        $stmt->execute([$paymentId]);
        $fatura = $stmt->fetch();

        if ($fatura && $fatura['status'] !== 'pago') {
            // Contexto de tenant para resolução de configurações (email etc.)
            if (!empty($fatura['admin_id'])) $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];

            $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ?");
            $stmt->execute([$novoStatus, $dataPagamento, $fatura['id']]);

            $valorPago = $payment['value'] ?? 0;
            $tipoPgto = $payment['billingType'] ?? '';
            $stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtLog->execute([
                $fatura['id'],
                $paymentId,
                $statusAsaas,
                $evento,
                $valorPago,
                $tipoPgto,
                json_encode($dados),
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
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
