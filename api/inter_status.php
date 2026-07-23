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
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE id = ? AND status != 'pago'");
    $stmt->execute([$faturaId]);

    $stmt = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, 'PAGA', 'Verificado via inter_status', ?, 'inter', ?)");
    $stmt->execute([$faturaId, $codigo, $fatura['valor_final'], json_encode($detalhe)]);

    $stmtCli = $pdo->prepare("SELECT c.nome_razao, c.email FROM clientes c WHERE c.id = ?");
    $stmtCli->execute([$fatura['cliente_id']]);
    $cli = $stmtCli->fetch();
    if ($cli) {
        $fatura['nome_razao'] = $cli['nome_razao'];
        $fatura['email'] = $cli['email'];
        $fatura['data_pagamento'] = date('Y-m-d');
        enviarEmailPagamento($fatura);
    }

    echo json_encode(['status' => 'pago']);
    exit;
}

if ($situacao === 'VENCIDA') {
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'vencido' WHERE id = ? AND status = 'pendente'");
    $stmt->execute([$faturaId]);
}

if ($situacao === 'EXPIRADA' || $situacao === 'CANCELADA') {
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'cancelado' WHERE id = ? AND status != 'pago'");
    $stmt->execute([$faturaId]);
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
