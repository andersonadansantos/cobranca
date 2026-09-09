<?php
// =====================================================
// PAGAMENTO DE PLANO COM CARTÃO (Mercado Pago - Super Admin)
// Recebe o token do cartão gerado no frontend (MercadoPago.js) e cria a cobrança.
// Funciona em todos os países (cartão internacional).
// =====================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/inter_pix.php';

header('Content-Type: application/json');

function apiResponder($data) {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    echo json_encode($data);
    exit;
}

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    apiResponder(['sucesso' => false, 'erro' => 'Sessão expirada. Faça login novamente.']);
}

if (getConfig('super_plano_cartao', '1') !== '1') {
    apiResponder(['sucesso' => false, 'erro' => 'O pagamento com cartão não está habilitado neste momento.']);
}

$planoId = (int)($_POST['plano_id'] ?? 0);
if ($planoId <= 0) {
    apiResponder(['sucesso' => false, 'erro' => 'Plano inválido.']);
}

$duracao = (int)($_POST['duracao_meses'] ?? 1);
if (!in_array($duracao, [1, 3, 6, 12])) {
    $duracao = 1;
}

$tipo = trim((string)($_POST['tipo'] ?? 'credito'));
$tipo = ($tipo === 'debito') ? 'debito' : 'credito';
$cardToken = trim((string)($_POST['card_token'] ?? ''));
$installments = max(1, (int)($_POST['installments'] ?? 1));
if ($tipo === 'debito') {
    $installments = 1;
}
$paymentMethodId = trim((string)($_POST['method'] ?? ''));

if ($cardToken === '') {
    apiResponder(['sucesso' => false, 'erro' => 'Token do cartão inválido.']);
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1");
$stmt->execute([$planoId]);
$plano = $stmt->fetch();
if (!$plano) {
    apiResponder(['sucesso' => false, 'erro' => 'Plano não encontrado ou inativo.']);
}

$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) {
    apiResponder(['sucesso' => false, 'erro' => 'Administrador não encontrado.']);
}

$valor = valorPlanoPorDuracao($plano['preco'], $duracao);
$descricao = descricaoPeriodoPlano($plano['nome'], $duracao);

$resultado = criarCartaoPlano($adminId, $planoId, $valor, $descricao, $admin, $duracao, $cardToken, $installments, $paymentMethodId, $tipo);

apiResponder($resultado);