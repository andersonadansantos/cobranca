<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

$pdo = getConnection();
$userId = $_SESSION['user_id'] ?? 0;
$faturaId = intval($_GET['id'] ?? 0);

if ($userId <= 0 || $faturaId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'erro']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND cliente_id = ?");
$stmt->execute([$faturaId, $userId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    http_response_code(404);
    echo json_encode(['status' => 'erro']);
    exit;
}

// Contexto de tenant para resolução de configurações (API, email etc.)
if (!empty($fatura['admin_id'])) $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];

if ($fatura['status'] === 'pago') {
    echo json_encode(['status' => 'pago']);
    exit;
}

$apiAtiva = $fatura['api_pagamento'] ?: getApiAtiva();
$novoStatus = $fatura['status'];
$dataPagamento = null;

if ($apiAtiva === 'inter' && !empty($fatura['inter_codigo_solicitacao'])) {
    $detalhe = consultarCobrancaInter($fatura['inter_codigo_solicitacao']);
    if ($detalhe && !isset($detalhe['erro'])) {
        $situacao = strtoupper($detalhe['situacao'] ?? $detalhe['cobranca']['situacao'] ?? '');
        switch ($situacao) {
            case 'PAGA':
            case 'RECEBIDO':
                $novoStatus = 'pago';
                $dataPagamento = date('Y-m-d');
                break;
            case 'VENCIDA':
                $novoStatus = 'atrasado';
                break;
            case 'EXPIRADA':
            case 'CANCELADA':
                $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
                break;
        }
    }
} elseif ($apiAtiva === 'bb' && !empty($fatura['mp_payment_id'])) {
    $detalhe = consultarBoletoBB($fatura['mp_payment_id']);
    if ($detalhe && !isset($detalhe['erro'])) {
        $situacao = strtoupper($detalhe['situacaoBoleto']['codigoSituacaoBoleto'] ?? '');
        switch ($situacao) {
            case 'BAIXADO':
            case 'PAGO':
            case 'RECEBIDO':
                $novoStatus = 'pago';
                $dataPagamento = date('Y-m-d');
                break;
        }
    }
} elseif ($apiAtiva === 'asaas' && !empty($fatura['mp_payment_id'])) {
    $pagamento = consultarPagamentoAsaas($fatura['mp_payment_id']);
    if ($pagamento && !isset($pagamento['erro'])) {
        $statusAsaas = strtoupper($pagamento['status'] ?? '');
        switch ($statusAsaas) {
            case 'RECEIVED':
            case 'CONFIRMED':
            case 'RECEIVED_IN_CASH':
                $novoStatus = 'pago';
                $dataPagamento = date('Y-m-d');
                break;
            case 'OVERDUE':
                $novoStatus = 'atrasado';
                break;
            case 'REFUNDED':
            case 'CHARGEBACK_REQUESTED':
            case 'CHARGEBACK_DISPUTE':
            case 'PAYMENT_DELETED':
            case 'PAYMENT_FAILED':
                $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
                break;
        }
    }
} elseif ($apiAtiva === 'mercadopago' && !empty($fatura['mp_payment_id'])) {
    $pagamento = consultarPagamento($fatura['mp_payment_id']);
    if ($pagamento) {
        $statusMP = $pagamento['status'] ?? '';
        switch ($statusMP) {
            case 'approved':
                $novoStatus = 'pago';
                $dataPagamento = date('Y-m-d');
                break;
            case 'cancelled':
            case 'rejected':
                // PIX expirado/recusado: não cancela a fatura aqui — a regeneração
                // automática do PIX acontece no cron e no webhook do Mercado Pago.
                break;
            case 'refunded':
                $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
                break;
        }
    }
}

if ($novoStatus !== $fatura['status']) {
    if ($novoStatus === 'pago') {
        confirmarPagamentoEBaixarFatura($faturaId, [
            'gateway' => $apiAtiva,
            'payment_id' => $fatura['mp_payment_id'] ?? $fatura['inter_codigo_solicitacao'] ?? '',
            'valor_pago' => $fatura['valor_final'],
            'tipo_pagamento' => $apiAtiva,
            'status_gateway' => 'approved',
            'status_detail' => 'Verificado via polling',
            'data_pagamento' => $dataPagamento,
            'dados_raw' => ['source' => 'verificar_status']
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
        $stmt->execute([$novoStatus, $dataPagamento, $faturaId]);
    }

    echo json_encode(['status' => $novoStatus]);
    exit;
}

echo json_encode(['status' => $fatura['status']]);
