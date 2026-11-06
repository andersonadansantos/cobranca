<?php
// =====================================================
// WEBHOOK DO MERCADO PAGO - Notificações de Pagamento
// Suporta POST (JSON v2 / IPN) e GET (IPN query string)
// Baixa automática imediata com notificação ao admin
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$dados = json_decode($rawInput, true);
if (!is_array($dados)) {
    $dados = [];
}

// 1. Extração flexível e resiliente do ID do pagamento
$dataId = null;
$tipo = $dados['type'] ?? ($_GET['type'] ?? ($_GET['topic'] ?? ($dados['topic'] ?? '')));
$action = $dados['action'] ?? '';

if (!empty($dados['data']['id'])) {
    $dataId = (string)$dados['data']['id'];
} elseif (!empty($_GET['data']['id'])) {
    $dataId = (string)$_GET['data']['id'];
} elseif (!empty($_GET['data_id'])) {
    $dataId = (string)$_GET['data_id'];
} elseif (!empty($_GET['id'])) {
    $dataId = (string)$_GET['id'];
} elseif (!empty($dados['id'])) {
    $dataId = (string)$dados['id'];
} elseif (!empty($dados['resource'])) {
    if (preg_match('#/payments/(\d+)#', (string)$dados['resource'], $m)) {
        $dataId = $m[1];
    }
} elseif (!empty($_GET['resource'])) {
    if (preg_match('#/payments/(\d+)#', (string)$_GET['resource'], $m)) {
        $dataId = $m[1];
    }
}

// Registrar log para auditoria de webhooks recebidos
$logEntry = date('Y-m-d H:i:s') . " | Method: {$method} | Tipo: {$tipo} | Action: {$action} | ID: {$dataId}\n";
@file_put_contents(__DIR__ . '/webhook_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

// Se não houver ID de pagamento identificado, retorna 200 (pode ser verificação do MP)
if (empty($dataId)) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'msg' => 'Sem identificador de pagamento']);
    exit;
}

$pdo = getConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Sem conexao']);
    exit;
}

// 2. Localizar a fatura no sistema
$fatura = null;
$pagamento = null;

// Quando o webhook chega pelo subdominio do admin, o initTenant ja definiu o
// tenant. Nesse caso so as faturas/credenciais daquele admin sao usadas.
$tenantAdminId = function_exists('getTenantAdminId') ? (int) getTenantAdminId() : 0;

// Tentativa 1: busca direta por mp_payment_id
$sqlCandidatas = "SELECT * FROM faturas WHERE mp_payment_id = ?";
$paramsCandidatas = [$dataId];
if ($tenantAdminId > 0) {
    $sqlCandidatas .= " AND admin_id = ?";
    $paramsCandidatas[] = $tenantAdminId;
}
$sqlCandidatas .= " ORDER BY id DESC LIMIT 5";
$stmt = $pdo->prepare($sqlCandidatas);
$stmt->execute($paramsCandidatas);
$candidatas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($candidatas)) {
    foreach ($candidatas as $cand) {
        if (!empty($cand['admin_id'])) {
            $_SESSION['tenant_admin_id'] = (int)$cand['admin_id'];
        }
        $pgto = consultarPagamento($dataId);
        if (is_array($pgto) && isset($pgto['id'], $pgto['status']) && (string)$pgto['id'] === (string)$dataId) {
            $fatura = $cand;
            $pagamento = $pgto;
            break;
        }
    }
    // Se a API não respondeu ou não confirmou com o token, mas a fatura já tem esse mp_payment_id
    if (!$fatura && count($candidatas) === 1) {
        $fatura = $candidatas[0];
    }
}

// Tentativa 2: se não achou por mp_payment_id, consulta o pagamento no Mercado Pago e busca por external_reference
if (!$fatura || !$pagamento) {
    // Com subdominio, usa apenas o token do admin do tenant. Na URL
    // compartilhada (raiz), tenta os administradores ate achar o dono.
    if ($tenantAdminId > 0) {
        $admins = [$tenantAdminId];
    } else {
        $stmtAdmins = $pdo->query("SELECT id FROM administradores WHERE ativo = 1 ORDER BY id ASC");
        $admins = $stmtAdmins ? $stmtAdmins->fetchAll(PDO::FETCH_COLUMN) : [];
    }
    
    foreach ($admins as $admId) {
        $_SESSION['tenant_admin_id'] = (int)$admId;
        $pgto = consultarPagamento($dataId);
        if (is_array($pgto) && isset($pgto['id'], $pgto['status']) && (string)$pgto['id'] === (string)$dataId) {
            $pagamento = $pgto;
            $extRef = trim((string)($pgto['external_reference'] ?? ''));
            if ($extRef !== '') {
                // Busca sempre escopada ao admin dono do token, para nunca
                // casar a fatura de outro tenant (numero e unico por admin).
                if (is_numeric($extRef)) {
                    $stRef = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ?");
                    $stRef->execute([(int)$extRef, (int)$admId]);
                    $fatura = $stRef->fetch(PDO::FETCH_ASSOC);
                }
                if (!$fatura) {
                    $stRefNum = $pdo->prepare("SELECT * FROM faturas WHERE admin_id = ? AND numero = ?");
                    $stRefNum->execute([(int)$admId, $extRef]);
                    $fatura = $stRefNum->fetch(PDO::FETCH_ASSOC);
                }
            }
            break;
        }
    }
}

// Garante que a notificacao e a baixa rodem no tenant dono da fatura.
if ($fatura && !empty($fatura['admin_id'])) {
    $_SESSION['tenant_admin_id'] = (int) $fatura['admin_id'];
}

// Se encontrou a fatura correspondente E a API confirmou o pagamento.
// Sem `$pagamento` valido nao existe confirmacao efetiva: o webhook do MP so
// avisa que algo mudou, nao informa o status. Antes o codigo assumia
// 'approved' quando a consulta falhava, dando baixa indevida.
if ($fatura && is_array($pagamento) && !empty($pagamento['status'])) {
    $statusMP = (string)$pagamento['status'];
    $statusDetail = $pagamento['status_detail'] ?? 'Webhook Mercado Pago';
    $valorPago = isset($pagamento['transaction_amount']) ? (float)$pagamento['transaction_amount'] : (float)$fatura['valor_final'];
    $tipoPgto = $pagamento['payment_type_id'] ?? ($pagamento['payment_method_id'] ?? 'pix');
    
    if ($statusMP === 'approved') {
        confirmarPagamentoEBaixarFatura($fatura['id'], [
            'gateway' => 'mercadopago',
            'payment_id' => $dataId,
            'valor_pago' => $valorPago,
            'tipo_pagamento' => $tipoPgto,
            'status_gateway' => $statusMP,
            'status_detail' => $statusDetail,
            'data_pagamento' => date('Y-m-d'),
            'dados_raw' => $pagamento
        ]);
    } elseif (in_array($statusMP, ['refunded', 'cancelled', 'rejected'], true)) {
        $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
        $stmt = $pdo->prepare("UPDATE faturas SET status = ? WHERE id = ? AND status != 'pago'");
        $stmt->execute([$novoStatus, $fatura['id']]);
    }
}

// Fatura localizada, mas sem confirmacao da API: registra e nao baixa.
if ($fatura && (!is_array($pagamento) || empty($pagamento['status']))) {
    @file_put_contents(__DIR__ . '/webhook_log.txt', date('Y-m-d H:i:s') . " | SEM CONFIRMACAO DA API | Fatura: {$fatura['id']} | Payment: {$dataId}\n", FILE_APPEND | LOCK_EX);
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
