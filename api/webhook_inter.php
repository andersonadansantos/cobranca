<?php
// =====================================================
// WEBHOOK DO BANCO INTER - Cobranças v3 e PIX
// Suporta notificações de Boleto/Cobranca e PIX BACEN
// Baixa automática imediata com notificação ao admin
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
$rawInput = file_get_contents('php://input');
$dados = json_decode($rawInput, true);

// Log detalhado para auditoria de webhooks do Inter
$logEntry = date('Y-m-d H:i:s') . " | Inter Webhook | " . ($rawInput ?: json_encode($_REQUEST)) . "\n";
@file_put_contents(__DIR__ . '/webhook_inter_log.txt', $logEntry, FILE_APPEND | LOCK_EX);

if (!is_array($dados)) {
    $dados = [];
}

$pdo = getConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Sem conexao']);
    exit;
}

$fatura = null;
$codigoSolicitacao = $dados['codigoSolicitacao'] ?? ($dados['cobranca']['codigoSolicitacao'] ?? '');
$situacao = $dados['situacao'] ?? ($dados['cobranca']['situacao'] ?? '');
$seuNumero = $dados['seuNumero'] ?? ($dados['cobranca']['seuNumero'] ?? '');
$txid = $dados['txid'] ?? '';
$valorPagoInter = 0;

// Caso 1: Notificação em formato de array PIX Bacen
if (isset($dados['pix']) && is_array($dados['pix'])) {
    foreach ($dados['pix'] as $itemPix) {
        if (!empty($itemPix['txid'])) {
            $txid = $itemPix['txid'];
        }
        if (isset($itemPix['valor'])) {
            $valorPagoInter = (float)$itemPix['valor'];
        }
    }
    $situacao = 'RECEBIDO';
} elseif (isset($dados[0]['pix']) && is_array($dados[0]['pix'])) {
    $txid = $dados[0]['txid'] ?? '';
    if (isset($dados[0]['pix'][0]['valor'])) {
        $valorPagoInter = (float)$dados[0]['pix'][0]['valor'];
    }
    $situacao = 'RECEBIDO';
}

// Localizar a fatura no banco de dados
if (!empty($codigoSolicitacao)) {
    $stmt = $pdo->prepare("SELECT * FROM faturas WHERE inter_codigo_solicitacao = ? LIMIT 1");
    $stmt->execute([$codigoSolicitacao]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$fatura && !empty($txid)) {
    $stmtTx = $pdo->prepare("SELECT * FROM faturas WHERE pix_inter_codigo = ? OR inter_codigo_solicitacao = ? OR pix_copia_cola LIKE ? LIMIT 1");
    $stmtTx->execute([$txid, $txid, '%' . $txid . '%']);
    $fatura = $stmtTx->fetch(PDO::FETCH_ASSOC);
}

if (!$fatura && !empty($seuNumero)) {
    $stmtNum = $pdo->prepare("SELECT * FROM faturas WHERE numero = ? LIMIT 1");
    $stmtNum->execute([$seuNumero]);
    $fatura = $stmtNum->fetch(PDO::FETCH_ASSOC);
}

if ($fatura) {
    if (!empty($fatura['admin_id'])) {
        $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];
    }

    $sitUpper = strtoupper((string)$situacao);

    // Confirmacao dupla: quando a API responde, a situacao oficial prevalece
    // sobre o corpo do webhook. Sem resposta da API, vale o proprio webhook.
    if (!empty($codigoSolicitacao) && function_exists('consultarCobrancaInter')) {
        $consultaInter = consultarCobrancaInter($codigoSolicitacao);
        if (is_array($consultaInter) && !isset($consultaInter['erro'])) {
            $sitApi = strtoupper((string)($consultaInter['situacao'] ?? ($consultaInter['cobranca']['situacao'] ?? '')));
            if ($sitApi !== '') {
                $sitUpper = $sitApi;
            }
        }
    }

    $statusAprovados = ['PAGA', 'PAGO', 'RECEBIDO', 'RECEBIDA', 'LIQUIDADO', 'LIQUIDADA', 'COMPENSADO', 'BAIXADO'];

    if (in_array($sitUpper, $statusAprovados, true)) {
        $vFinal = $valorPagoInter > 0 ? $valorPagoInter : (float)$fatura['valor_final'];
        $codRef = $codigoSolicitacao ?: ($txid ?: ($fatura['inter_codigo_solicitacao'] ?? 'INTER'));
        
        confirmarPagamentoEBaixarFatura($fatura['id'], [
            'gateway' => 'inter',
            'payment_id' => $codRef,
            'valor_pago' => $vFinal,
            'tipo_pagamento' => 'pix',
            'status_gateway' => $sitUpper ?: 'RECEBIDO',
            'status_detail' => 'Confirmado via Webhook Banco Inter',
            'data_pagamento' => date('Y-m-d'),
            'dados_raw' => $dados
        ]);
    } elseif (in_array($sitUpper, ['VENCIDA', 'ATRASADA'], true)) {
        $stmt = $pdo->prepare("UPDATE faturas SET status = 'atrasado', inter_situacao = ? WHERE id = ? AND status != 'pago'");
        $stmt->execute([$sitUpper, $fatura['id']]);
    } elseif (in_array($sitUpper, ['EXPIRADA', 'CANCELADA'], true)) {
        $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
        $stmt = $pdo->prepare("UPDATE faturas SET status = ?, inter_situacao = ? WHERE id = ? AND status != 'pago'");
        $stmt->execute([$novoStatus, $sitUpper, $fatura['id']]);
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
