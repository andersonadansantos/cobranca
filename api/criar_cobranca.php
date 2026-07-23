<?php
// =====================================================
// API - Criar cobrança via Mercado Pago
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedInAdmin() && !isLoggedInUser()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

$faturaId = intval($dados['fatura_id'] ?? 0);

if ($faturaId <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID da fatura é obrigatório']);
    exit;
}

$pdo = getConnection();

$stmt = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.email, c.cpf_cnpj 
    FROM faturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    WHERE f.id = ? AND f.status IN ('pendente', 'vencido', 'atrasado')
");
$stmt->execute([$faturaId]);
$fatura = $stmt->fetch();

if (!$fatura) {
    http_response_code(404);
    echo json_encode(['erro' => 'Fatura não encontrada ou já processada']);
    exit;
}

$descricao = $fatura['numero'] . ' - ' . $fatura['descricao'];
$resultado = criarPagamento($descricao, $fatura['valor_final'], $fatura['email'], $fatura['nome_razao']);

if (isset($resultado['sucesso']) && $resultado['sucesso']) {
    $apiAtiva = getApiAtiva();
    if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
        $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ? WHERE id = ?");
        $stmt->execute([
            $resultado['qr_code'],
            $resultado['qr_code_copia_cola'],
            $resultado['link_pagamento'],
            null,
            $resultado['payment_id'],
            $faturaId
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ? WHERE id = ?");
        $stmt->execute([
            $resultado['qr_code'],
            $resultado['qr_code_copia_cola'],
            $resultado['link_pagamento'],
            $resultado['payment_id'],
            $faturaId
        ]);
    }

    echo json_encode([
        'sucesso' => true,
        'payment_id' => $resultado['payment_id'],
        'qr_code' => $resultado['qr_code'],
        'link_pagamento' => $resultado['link_pagamento'],
    ]);
} else {
    http_response_code(500);
    echo json_encode($resultado);
}
