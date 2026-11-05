<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedInUser()) {
    echo json_encode(['status' => 'erro', 'msg' => 'Não autenticado']);
    exit;
}

$faturaId = intval($_GET['id'] ?? 0);
if ($faturaId <= 0) {
    echo json_encode(['status' => 'erro', 'msg' => 'ID inválido']);
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND cliente_id = ?");
$stmt->execute([$faturaId, $userId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    echo json_encode(['status' => 'erro', 'msg' => 'Fatura não encontrada']);
    exit;
}

// Contexto de tenant para resolução de configurações (API, email etc.)
if (!empty($fatura['admin_id'])) $_SESSION['tenant_admin_id'] = (int)$fatura['admin_id'];

if ($fatura['status'] === 'pago') {
    echo json_encode(['status' => 'pago']);
    exit;
}

$codigo = $fatura['inter_codigo_solicitacao'] ?? '';
if (empty($codigo)) {
    echo json_encode(['status' => 'pendente', 'pix' => false]);
    exit;
}

$detalhe = consultarCobrancaInter($codigo);
if (!$detalhe || isset($detalhe['erro'])) {
    echo json_encode(['status' => 'aguardando', 'pix' => false]);
    exit;
}

$situacao = strtoupper($detalhe['situacao'] ?? $detalhe['cobranca']['situacao'] ?? '');

if ($situacao === 'PAGA' || $situacao === 'RECEBIDO') {
    confirmarPagamentoEBaixarFatura($faturaId, [
        'gateway' => 'inter',
        'payment_id' => $codigo,
        'valor_pago' => $fatura['valor_final'],
        'tipo_pagamento' => 'pix',
        'status_gateway' => 'PAGA',
        'status_detail' => 'Verificado via inter_status',
        'dados_raw' => $detalhe
    ]);

    echo json_encode(['status' => 'pago']);
    exit;
}

if ($situacao === 'VENCIDA') {
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'atrasado' WHERE id = ? AND status = 'pendente'");
    $stmt->execute([$faturaId]);
}

// Fatura nunca é cancelada sozinha: expiração/cancelamento no Inter volta
// a fatura para o status do vencimento (atrasado/pendente).
if ($situacao === 'EXPIRADA' || $situacao === 'CANCELADA') {
    $novoStatusInter = statusFaturaSemCancelar($fatura['data_vencimento'] ?? '');
    $stmt = $pdo->prepare("UPDATE faturas SET status = ? WHERE id = ? AND status != 'pago'");
    $stmt->execute([$novoStatusInter, $faturaId]);
}

$pix = $detalhe['pix'] ?? ($detalhe['cobranca']['pix'] ?? []);
$pixCopiaECola = '';
$qrCode = '';
if (!empty($pix)) {
    $pixCopiaECola = $pix['pixCopiaECola'] ?? '';
    $qrCode = $pix['qrcode'] ?? '';
}

if ($pixCopiaECola) {
    $stmt = $pdo->prepare("UPDATE faturas SET pix_copia_cola = ?, pix_qrcode = ? WHERE id = ? AND (pix_copia_cola IS NULL OR pix_copia_cola = '')");
    $stmt->execute([$pixCopiaECola, $qrCode, $faturaId]);
    echo json_encode(['status' => 'pronto', 'pix' => true, 'pix_copia_cola' => $pixCopiaECola, 'pix_qrcode' => $qrCode]);
} else {
    echo json_encode(['status' => 'aguardando', 'pix' => false]);
}
