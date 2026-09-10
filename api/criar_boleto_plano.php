<?php
// =====================================================
// BOLETO DE PLANO (Mercado Pago - Super Admin)
// Gera boleto bancário registrado. Disponível somente no Brasil.
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

// Boleto só existe no Brasil: deriva o país do idioma escolhido no site.
$mapaPais = ['pt-BR' => 'BR', 'es-MX' => 'MX', 'es-AR' => 'AR', 'es-CO' => 'CO', 'es-CL' => 'CL', 'es-PE' => 'PE'];
$paisSite = $mapaPais[$_COOKIE['cobranca_site_idioma'] ?? 'pt-BR'] ?? 'BR';

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    apiResponder(['erro' => 'Sessão expirada. Faça login novamente.']);
}

if ($paisSite !== 'BR') {
    apiResponder(['erro' => 'O boleto bancário está disponível somente no Brasil. Use o cartão de crédito/débito.']);
}

if (getConfig('super_plano_pix_boleto', '1') !== '1') {
    apiResponder(['erro' => 'O pagamento por PIX e boleto não está habilitado neste momento.']);
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

$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1 AND COALESCE(slug,'') <> 'demo'");
$stmt->execute([$planoId]);
$plano = $stmt->fetch();
if (!$plano) {
    apiResponder(['erro' => 'Plano não encontrado ou inativo.']);
}

$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
if (!$admin) {
    apiResponder(['erro' => 'Administrador não encontrado.']);
}

$valor = valorPlanoPorDuracao($plano['preco'], $duracao);
$descricao = descricaoPeriodoPlano($plano['nome'], $duracao);

$resultado = criarBoletoPlano($adminId, $planoId, $valor, $descricao, $admin, $duracao);

apiResponder($resultado);