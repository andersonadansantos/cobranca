<?php
// =====================================================
// VERIFICAR STATUS DO BANCO INTER (POLLING PIX/BOLETO)
// Realiza baixa automática imediata e notificação ao admin
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

$faturaId = intval($_GET['id'] ?? 0);
if ($faturaId <= 0) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID inválido']);
    exit;
}

$pdo = getConnection();
if (!$pdo) {
    echo json_encode(['status' => 'erro', 'msg' => 'Sem conexão com banco']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? LIMIT 1");
$stmt->execute([$faturaId]);
$fatura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fatura) {
    echo json_encode(['status' => 'erro', 'msg' => 'Fatura não encontrada']);
    exit;
}

// Se já está paga, retorna imediatamente
if (($fatura['status'] ?? '') === 'pago') {
    echo json_encode(['status' => 'pago']);
    exit;
}

$codigo = $fatura['inter_codigo_solicitacao'] ?? '';
if (empty($codigo)) {
    echo json_encode(['status' => $fatura['status']]);
    exit;
}

if (!empty($fatura['admin_id'])) {
    $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];
}

$detalhe = consultarCobrancaInter($codigo);
if (!$detalhe || isset($detalhe['erro'])) {
    echo json_encode(['status' => $fatura['status']]);
    exit;
}

$situacao = strtoupper((string)($detalhe['situacao'] ?? ($detalhe['cobranca']['situacao'] ?? '')));
$statusAprovados = ['PAGA', 'PAGO', 'RECEBIDO', 'RECEBIDA', 'LIQUIDADO', 'LIQUIDADA', 'COMPENSADO', 'BAIXADO'];

if (in_array($situacao, $statusAprovados, true)) {
    confirmarPagamentoEBaixarFatura($faturaId, [
        'gateway' => 'inter',
        'payment_id' => $codigo,
        'valor_pago' => (float)$fatura['valor_final'],
        'tipo_pagamento' => 'pix',
        'status_gateway' => $situacao,
        'status_detail' => 'Verificado em tempo real via inter_status',
        'data_pagamento' => date('Y-m-d'),
        'dados_raw' => $detalhe
    ]);

    echo json_encode(['status' => 'pago']);
    exit;
}

if (in_array($situacao, ['VENCIDA', 'ATRASADA'], true)) {
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'atrasado', inter_situacao = ? WHERE id = ? AND status != 'pago'");
    $stmt->execute([$situacao, $faturaId]);
}

echo json_encode(['status' => $fatura['status']]);
