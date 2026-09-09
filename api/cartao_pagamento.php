<?php
// =====================================================
// PAGAMENTO COM CARTÃO DE CRÉDITO/DÉBITO (Mercado Pago)
// Recebe o token do cartão gerado no frontend e cria a cobrança.
// =====================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão expirada. Entre novamente.']);
    exit;
}

$pdo = getConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro de conexão com o banco de dados']);
    exit;
}

$faturaId = intval($_POST['fatura_id'] ?? 0);
if ($faturaId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Fatura inválida']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND cliente_id = ?");
$stmt->execute([$faturaId, $userId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'erro' => 'Fatura não encontrada']);
    exit;
}

if ($fatura['status'] === 'pago') {
    echo json_encode(['sucesso' => false, 'erro' => 'Esta fatura já foi paga.']);
    exit;
}

// Contexto de tenant para resolver as configurações do admin dono da fatura.
if (!empty($fatura['admin_id'])) {
    $_SESSION['tenant_admin_id'] = (int) $fatura['admin_id'];
}

$apiDaFatura = $fatura['api_pagamento'] ?: getApiAtiva();
if ($apiDaFatura !== 'mercadopago') {
    echo json_encode(['sucesso' => false, 'erro' => 'Pagamento com cartão não está disponível para esta fatura.']);
    exit;
}

if (!aceitaCartaoCredito()) {
    echo json_encode(['sucesso' => false, 'erro' => 'O pagamento com cartão não está habilitado.']);
    exit;
}

$tipo = trim((string) ($_POST['tipo'] ?? 'credito'));
$tipo = ($tipo === 'debito') ? 'debito' : 'credito';
$cardToken = trim((string) ($_POST['card_token'] ?? ''));
$installments = max(1, (int) ($_POST['installments'] ?? 1));
$installments = min($installments, getMaxParcelasCartao());
if ($tipo === 'debito') {
    $installments = 1;
}
$paymentMethodId = trim((string) ($_POST['method'] ?? ''));

if ($cardToken === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Token do cartão inválido.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$userId]);
$cli = $stmt->fetch();

$cpfCnpj = preg_replace('/[^0-9]/', '', $cli['cpf_cnpj'] ?? '');
if ($cpfCnpj === '') {
    echo json_encode(['sucesso' => false, 'erro' => 'Cadastre seu CPF/CNPJ no seu perfil para pagar com cartão.']);
    exit;
}

$result = criarPagamentoCartaoMercadoPago(
    $fatura['descricao'],
    $fatura['valor_final'],
    $cli['email'] ?? '',
    $cli['nome_razao'] ?? '',
    $cpfCnpj,
    $cardToken,
    $installments,
    $paymentMethodId,
    $tipo
);

if (!isset($result['sucesso']) || !$result['sucesso']) {
    $erro = $result['erro'] ?? 'Erro ao processar pagamento com cartão.';
    if (!empty($result['detalhes']) && is_string($result['detalhes'])) {
        $erro .= ' — ' . $result['detalhes'];
    }
    echo json_encode(['sucesso' => false, 'erro' => $erro, 'mp_status' => 'rejected']);
    exit;
}

$mpStatus = $result['status'] ?? '';

// Cartão recusado (ou cancelado/estornado): registra a tentativa e informa o
// motivo ao cliente para ele poder tentar de novo ou usar outro meio.
if (in_array($mpStatus, ['rejected', 'cancelled', 'refunded', 'charged_back'], true)) {
    $stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (admin_id, fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtLog->execute([
        !empty($fatura['admin_id']) ? (int) $fatura['admin_id'] : null,
        $faturaId,
        $result['payment_id'],
        $mpStatus,
        $result['status_detail'] ?? '',
        $fatura['valor_final'],
        'cartao',
        json_encode(['fonte' => 'cartao_pagamento', 'tipo' => $tipo, 'parcelas' => $installments])
    ]);
    echo json_encode([
        'sucesso' => false,
        'erro' => msgErroCartaoMercadoPago($result['status_detail'] ?? ''),
        'mp_status' => $mpStatus,
        'payment_id' => $result['payment_id'],
    ]);
    exit;
}

$novoStatus = 'pendente';
$dataPagamento = null;

if ($mpStatus === 'approved') {
    $novoStatus = 'pago';
    $dataPagamento = date('Y-m-d');
}

// Grava o pagamento na fatura apenas quando ele pode ser confirmado depois
// (aprovado ou em análise). Se recusado, mantém a fatura pendente para o
// cliente tentar de novo ou usar PIX.
if (in_array($mpStatus, ['approved', 'pending', 'in_process'], true)) {
    $stmt = $pdo->prepare("UPDATE faturas SET mp_payment_id = ?, api_pagamento = 'mercadopago', status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
    $stmt->execute([$result['payment_id'], $novoStatus, $dataPagamento, $faturaId]);
}

$stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (admin_id, fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtLog->execute([
    !empty($fatura['admin_id']) ? (int) $fatura['admin_id'] : null,
    $faturaId,
    $result['payment_id'],
    $mpStatus,
    $result['status_detail'] ?? '',
    $fatura['valor_final'],
    'cartao',
    json_encode(['fonte' => 'cartao_pagamento', 'tipo' => $tipo, 'parcelas' => $installments])
]);

if ($mpStatus === 'approved') {
    $fatura['nome_razao'] = $cli['nome_razao'] ?? '';
    $fatura['email'] = $cli['email'] ?? '';
    $fatura['data_pagamento'] = $dataPagamento;
    enviarEmailPagamento($fatura);
}

echo json_encode([
    'sucesso' => true,
    'status' => $novoStatus,
    'mp_status' => $mpStatus,
    'payment_id' => $result['payment_id'],
]);