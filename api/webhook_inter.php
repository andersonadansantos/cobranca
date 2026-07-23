<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';
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

$logEntry = date('Y-m-d H:i:s') . " | Inter Webhook | " . json_encode($dados) . "\n";
file_put_contents(__DIR__ . '/webhook_inter_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

$codigoSolicitacao = $dados['codigoSolicitacao'] ?? '';
$situacao = $dados['situacao'] ?? '';

if (empty($codigoSolicitacao)) {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE inter_codigo_solicitacao = ?");
$stmt->execute([$codigoSolicitacao]);
$fatura = $stmt->fetch();

if ($fatura) {
    $novoStatus = 'pendente';
    $dataPagamento = null;

    switch (strtoupper($situacao)) {
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
        case 'AGUARDANDO':
        case 'EM ANALISE':
            $novoStatus = 'pendente';
            break;
    }

    $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ?");
    $stmt->execute([$novoStatus, $dataPagamento, $fatura['id']]);

    $stmt = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, 'inter', ?)");
    $stmt->execute([
        $fatura['id'],
        $codigoSolicitacao,
        $situacao,
        $situacao,
        $fatura['valor_final'],
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

http_response_code(200);
echo json_encode(['status' => 'ok']);
