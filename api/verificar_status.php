<?php
// =====================================================
// VERIFICAR STATUS DA FATURA (POLLING EM TEMPO REAL)
// Consulta o gateway ativo e realiza baixa automática
// imediata com disparo de e-mail ao administrador
// =====================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

$pdo = getConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'msg' => 'Sem conexao']);
    exit;
}

$faturaId = intval($_GET['id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($faturaId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'msg' => 'ID invalido']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? LIMIT 1");
$stmt->execute([$faturaId]);
$fatura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fatura) {
    http_response_code(404);
    echo json_encode(['status' => 'erro', 'msg' => 'Fatura nao encontrada']);
    exit;
}

// Se já está paga, retorna imediatamente
if (($fatura['status'] ?? '') === 'pago') {
    echo json_encode(['status' => 'pago']);
    exit;
}

// Configura o contexto do administrador dono da fatura (tenant)
if (!empty($fatura['admin_id'])) {
    $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];
}

$apiAtiva = $fatura['api_pagamento'] ?: getApiAtiva();
$novoStatus = $fatura['status'];
$dataPagamento = null;
$detalhesRaw = null;
$valorEfetivo = (float)$fatura['valor_final'];
$paymentIdRef = (string)(($fatura['mp_payment_id'] ?? '') ?: ($fatura['inter_codigo_solicitacao'] ?? ''));

// 1. BANCO INTER
if ($apiAtiva === 'inter' && !empty($fatura['inter_codigo_solicitacao'])) {
    $detalhe = consultarCobrancaInter($fatura['inter_codigo_solicitacao']);
    if ($detalhe && !isset($detalhe['erro'])) {
        $detalhesRaw = $detalhe;
        $situacao = strtoupper($detalhe['situacao'] ?? ($detalhe['cobranca']['situacao'] ?? ''));
        $statusAprovados = ['PAGA', 'PAGO', 'RECEBIDO', 'RECEBIDA', 'LIQUIDADO', 'LIQUIDADA', 'COMPENSADO', 'BAIXADO'];
        
        if (in_array($situacao, $statusAprovados, true)) {
            $novoStatus = 'pago';
            $dataPagamento = date('Y-m-d');
        } elseif ($situacao === 'VENCIDA') {
            $novoStatus = 'atrasado';
        } elseif (in_array($situacao, ['EXPIRADA', 'CANCELADA'], true)) {
            $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
        }
    }
}
// 2. MERCADO PAGO
elseif ($apiAtiva === 'mercadopago' && !empty($fatura['mp_payment_id'])) {
    $pagamento = consultarPagamento($fatura['mp_payment_id']);
    if ($pagamento && is_array($pagamento)) {
        $detalhesRaw = $pagamento;
        $statusMP = $pagamento['status'] ?? '';
        if (isset($pagamento['transaction_amount']) && (float)$pagamento['transaction_amount'] > 0) {
            $valorEfetivo = (float)$pagamento['transaction_amount'];
        }
        
        if ($statusMP === 'approved') {
            $novoStatus = 'pago';
            $dataPagamento = date('Y-m-d');
        } elseif ($statusMP === 'refunded') {
            $novoStatus = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
        }
    }
}
// 3. ASAAS
elseif ($apiAtiva === 'asaas' && !empty($fatura['mp_payment_id'])) {
    if (function_exists('consultarPagamentoAsaas')) {
        $pagamento = consultarPagamentoAsaas($fatura['mp_payment_id']);
        if ($pagamento && !isset($pagamento['erro'])) {
            $detalhesRaw = $pagamento;
            $statusAsaas = strtoupper($pagamento['status'] ?? '');
            if (isset($pagamento['value']) && (float)$pagamento['value'] > 0) {
                $valorEfetivo = (float)$pagamento['value'];
            }
            if (in_array($statusAsaas, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
                $novoStatus = 'pago';
                $dataPagamento = date('Y-m-d');
            } elseif ($statusAsaas === 'OVERDUE') {
                $novoStatus = 'atrasado';
            }
        }
    }
}
// 4. PAGBANK
elseif ($apiAtiva === 'pagbank' && !empty($fatura['mp_payment_id'])) {
    if (function_exists('consultarPedidoPagBank')) {
        $detalhe = consultarPedidoPagBank($fatura['mp_payment_id']);
        if ($detalhe) {
            $detalhesRaw = $detalhe;
            $charges = $detalhe['charges'] ?? [];
            foreach ($charges as $charge) {
                $situacao = strtoupper($charge['status'] ?? '');
                if ($situacao === 'PAID') {
                    $novoStatus = 'pago';
                    $dataPagamento = date('Y-m-d');
                    if (isset($charge['amount']['paid'])) {
                        $valorEfetivo = (float)($charge['amount']['paid'] / 100);
                    }
                    break;
                }
            }
        }
    }
}

// Se o pagamento foi confirmado, realiza a baixa imediata e dispara o e-mail
if ($novoStatus === 'pago') {
    $resBaixa = confirmarPagamentoEBaixarFatura($faturaId, [
        'gateway' => $apiAtiva,
        'payment_id' => $paymentIdRef,
        'valor_pago' => $valorEfetivo,
        'tipo_pagamento' => $apiAtiva,
        'status_gateway' => 'approved',
        'status_detail' => 'Verificado em tempo real via polling',
        'data_pagamento' => $dataPagamento ?: date('Y-m-d'),
        'dados_raw' => $detalhesRaw
    ]);

    echo json_encode(['status' => 'pago', 'sucesso' => true]);
    exit;
}

if ($novoStatus !== $fatura['status']) {
    $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
    $stmt->execute([$novoStatus, $dataPagamento, $faturaId]);
}

echo json_encode(['status' => $novoStatus]);
