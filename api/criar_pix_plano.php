<?php
// Cria cobrança PIX para compra de plano do admin logado
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
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
    apiResponder(['erro' => 'Sessão expirada. Faça login novamente.']);
}

$planoId = (int)($_POST['plano_id'] ?? 0);
if ($planoId <= 0) {
    apiResponder(['erro' => 'Plano inválido.']);
}

$duracao = (int)($_POST['duracao_meses'] ?? 1);
if (!in_array($duracao, [1, 3, 6, 12])) {
    $duracao = 1;
}

$pdo = getConnection();

$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1");
$stmt->execute([$planoId]);
$plano = $stmt->fetch();
if (!$plano) {
    apiResponder(['erro' => 'Plano não encontrado ou inativo.']);
}

// Dados do admin (pagador)
$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) {
    apiResponder(['erro' => 'Administrador não encontrado.']);
}

// Valor com desconto de 10% para períodos maiores que o mensal
$valorBase = (float)$plano['preco'];
if ($duracao > 1) {
    $valor = round($valorBase * $duracao * 0.90, 2);
} else {
    $valor = $valorBase;
}
$tituloPeriodo = [1 => 'Mensal', 3 => 'Trimestral', 6 => 'Semestral', 12 => 'Anual'][$duracao];
$descricao = 'Plano ' . $plano['nome'] . ' - ' . $tituloPeriodo;
$resultado = criarPixPlano($adminId, $planoId, $valor, $descricao, $admin, $duracao);

apiResponder($resultado);
