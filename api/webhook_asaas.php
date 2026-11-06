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

$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || empty($dados['event']) || empty($dados['payment']['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$evento = $dados['event'];
$payment = $dados['payment'];
$paymentId = $payment['id'];

// Quantos admins tem subdominio, a URL e dele e o initTenant ja definiu o
// contexto. Nesse caso o token do proprio admin e validado antes da consulta.
// Na URL compartilhada (raiz), o token e validado depois que a fatura revela
// o admin dono — assim cada admin pode ter o seu token.
$tenantAdminId = function_exists('getTenantAdminId') ? (int) getTenantAdminId() : 0;

if ($tenantAdminId > 0) {
    $webhookToken = getConfig('asaas_webhook_token', '');
    if (!empty($webhookToken)) {
        $recebido = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? ($_GET['token'] ?? '');
        if (!hash_equals($webhookToken, (string) $recebido)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }
}

// Log da notificação
$logEntry = date('Y-m-d H:i:s') . " | Evento: {$evento} | Payment: {$paymentId}\n";
file_put_contents(__DIR__ . '/webhook_asaas_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

$statusAsaas = strtoupper($payment['status'] ?? '');
$mapeamento = [
    'PAYMENT_RECEIVED' => 'pago',
    'PAYMENT_CONFIRMED' => 'pago',
    'PAYMENT_OVERDUE' => 'atrasado',
    // NUNCA cancela a fatura sozinha: estorno/chargeback/exclusão/falha
    // devolvem a fatura ao status do vencimento (atrasado/pendente).
    'PAYMENT_REFUNDED' => '__semcancelar__',
    'PAYMENT_CHARGEBACK_REQUESTED' => '__semcancelar__',
    'PAYMENT_CHARGEBACK_DISPUTE' => '__semcancelar__',
    'PAYMENT_DELETED' => '__semcancelar__',
    'PAYMENT_FAILED' => '__semcancelar__',
];

if (isset($mapeamento[$evento])) {
    $novoStatus = $mapeamento[$evento];

    $pdo = getConnection();
    if ($pdo) {
        $sqlFatura = "SELECT * FROM faturas WHERE mp_payment_id = ? AND api_pagamento = 'asaas'";
        $paramsFatura = [$paymentId];
        if ($tenantAdminId > 0) {
            $sqlFatura .= " AND admin_id = ?";
            $paramsFatura[] = $tenantAdminId;
        }
        $sqlFatura .= " ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sqlFatura);
        $stmt->execute($paramsFatura);
        $fatura = $stmt->fetch();

        if ($fatura) {
            $donoAdminId = (int) ($fatura['admin_id'] ?? 0);
            // URL compartilhada: descobre o admin pela fatura e valida o token
            // dele. Se ele nao tiver token, mantem o comportamento global.
            if ($tenantAdminId <= 0 && $donoAdminId > 0) {
                $_SESSION['tenant_admin_id'] = $donoAdminId;
                $webhookToken = getConfig('asaas_webhook_token', '');
                if (!empty($webhookToken)) {
                    $recebido = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? ($_GET['token'] ?? '');
                    if (!hash_equals($webhookToken, (string) $recebido)) {
                        http_response_code(401);
                        echo json_encode(['error' => 'Unauthorized']);
                        exit;
                    }
                }
            }
        }

        if ($fatura && $fatura['status'] !== 'pago') {
            if ($novoStatus === '__semcancelar__') {
                $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
            }
            $dataPagamento = $novoStatus === 'pago' ? date('Y-m-d') : null;
            // Contexto de tenant para resolução de configurações (email etc.)
            if (!empty($fatura['admin_id'])) $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];

            // Confirmacao dupla: se a API do Asaas responder, ela e a fonte de
            // verdade e impede baixa por evento forjado. Se a API estiver
            // indisponivel, o proprio webhook (evento oficial) e aceito.
            if ($novoStatus === 'pago' && function_exists('consultarPagamentoAsaas')) {
                $consultaApi = consultarPagamentoAsaas($paymentId);
                if (is_array($consultaApi) && !isset($consultaApi['erro'])) {
                    $statusApi = strtoupper((string)($consultaApi['status'] ?? ''));
                    if (!in_array($statusApi, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
                        http_response_code(200);
                        echo json_encode(['status' => 'ok', 'ignorado' => 'pagamento ainda nao confirmado na API']);
                        exit;
                    }
                }
            }

            $valorPago = $payment['value'] ?? 0;
            $tipoPgto = $payment['billingType'] ?? '';
            if ($novoStatus === 'pago') {
                confirmarPagamentoEBaixarFatura($fatura['id'], [
                    'gateway' => 'asaas',
                    'payment_id' => $paymentId,
                    'valor_pago' => $valorPago,
                    'tipo_pagamento' => $tipoPgto,
                    'status_gateway' => $statusAsaas,
                    'status_detail' => $evento,
                    'data_pagamento' => $dataPagamento,
                    'dados_raw' => $dados
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $dataPagamento, $fatura['id']]);

                $stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (admin_id, fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmtLog->execute([
                    $fatura['admin_id'] ?? 0,
                    $fatura['id'],
                    $paymentId,
                    $statusAsaas,
                    $evento,
                    $valorPago,
                    $tipoPgto,
                    json_encode($dados),
                ]);
            }
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
