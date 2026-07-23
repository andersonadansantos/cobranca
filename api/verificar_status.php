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

if ($fatura['status'] === 'pago') {
    echo json_encode(['status' => 'pago']);
    exit;
}

$apiAtiva = getApiAtiva();
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
                $novoStatus = 'vencido';
                break;
            case 'EXPIRADA':
            case 'CANCELADA':
                $novoStatus = 'cancelado';
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
            case 'refunded':
                $novoStatus = 'cancelado';
                break;
        }
    }
}

if ($novoStatus !== $fatura['status']) {
    $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
    $stmt->execute([$novoStatus, $dataPagamento, $faturaId]);

    if ($novoStatus === 'pago') {
        $stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, 'approved', 'Verificado via polling', ?, ?, ?)");
        $stmtLog->execute([$faturaId, $fatura['mp_payment_id'] ?? $fatura['inter_codigo_solicitacao'] ?? '', $fatura['valor_final'], $apiAtiva, json_encode(['source' => 'verificar_status'])]);
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

    echo json_encode(['status' => $novoStatus]);
    exit;
}

echo json_encode(['status' => $fatura['status']]);
