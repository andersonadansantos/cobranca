<?php
require_once __DIR__ . '/auth.php';
requireSuper();

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$ativo = isset($_POST['ativo']) ? (int)(bool)$_POST['ativo'] : -1;

if ($id <= 0 || !in_array($ativo, [0, 1], true)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetros inválidos']);
    exit;
}

$pdo = getConnection();

$ok = $pdo->prepare("UPDATE administradores SET ativo = ? WHERE id = ?")->execute([$ativo, $id]);

$planoId = null;
// Ao ATIVAR o admin, além de liberar o acesso, concede 30 dias do plano mensal
// mais barato ativo do sistema.
if ($ok && $ativo === 1) {
    $planoId = $pdo->query("SELECT id FROM planos WHERE ativo = 1 ORDER BY preco ASC, ordem ASC LIMIT 1")->fetchColumn();
    if ($planoId) {
        $stmt = $pdo->prepare("INSERT INTO admin_planos (admin_id, plano_id, data_inicio, data_fim)
            VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))
            ON DUPLICATE KEY UPDATE plano_id=VALUES(plano_id), data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim)");
        $ok = $stmt->execute([$id, (int)$planoId]);
    }
}

if ($ok) {
    echo json_encode(['ok' => true, 'id' => $id, 'ativo' => $ativo, 'plano_id' => $planoId ? (int)$planoId : null]);
} else {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao atualizar status']);
}