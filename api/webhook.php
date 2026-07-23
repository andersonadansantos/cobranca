<?php
// =====================================================
// WEBHOOK DO MERCADO PAGO - Notificações de Pagamento
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

// Mercado Pago envia notificações via POST
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

$tipo = $dados['type'] ?? '';
$action = $dados['action'] ?? '';
$dataId = $dados['data']['id'] ?? null;

// Log da notificação
$logEntry = date('Y-m-d H:i:s') . " | Tipo: {$tipo} | Action: {$action} | ID: {$dataId}\n";
file_put_contents(__DIR__ . '/webhook_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

if ($tipo === 'payment' && $dataId) {
    // Consultar pagamento no Mercado Pago
    $pagamento = consultarPagamento($dataId);
    
    if ($pagamento) {
        $pdo = getConnection();
        
        if ($pdo) {
            // Buscar fatura pelo payment_id
            $stmt = $pdo->prepare("SELECT * FROM faturas WHERE mp_payment_id = ?");
            $stmt->execute([$dataId]);
            $fatura = $stmt->fetch();
            
            if ($fatura) {
                $statusMP = $pagamento['status'] ?? '';
                $statusDetail = $pagamento['status_detail'] ?? '';
                $valorPago = $pagamento['transaction_amount'] ?? 0;
                $tipoPgto = $pagamento['payment_type_id'] ?? '';
                
                // Atualizar status da fatura
                $novoStatus = 'pendente';
                $dataPagamento = null;
                
                switch ($statusMP) {
                    case 'approved':
                        $novoStatus = 'pago';
                        $dataPagamento = date('Y-m-d');
                        break;
                    case 'pending':
                        $novoStatus = 'pendente';
                        break;
                    case 'cancelled':
                    case 'refunded':
                        $novoStatus = 'cancelado';
                        break;
                    case 'rejected':
                        $novoStatus = 'pendente';
                        break;
                }
                
                $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $dataPagamento, $fatura['id']]);
                
                // Registrar log de pagamento
                $stmt = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $fatura['id'],
                    $dataId,
                    $statusMP,
                    $statusDetail,
                    $valorPago,
                    $tipoPgto,
                    json_encode($pagamento)
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
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
