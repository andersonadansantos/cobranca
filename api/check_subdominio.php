<?php
// =====================================================
// API: Verifica disponibilidade de subdomínio (AJAX)
// =====================================================
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$sub = strtolower(trim((string)($_GET['subdominio'] ?? '')));

if (!preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $sub)) {
    echo json_encode(['disponivel' => false, 'motivo' => 'formato']);
    exit;
}

// Subdomínios reservados (não podem ser usados por tenants)
$reservados = ['www', 'admin', 'superadmin', 'api', 'app', 'mail', 'ftp', 'smtp', 'pop', 'ns1', 'ns2', 'dns', 'cdn', 'blog', 'loja', 'shop', 'teste', 'test', 'demo', 'suporte', 'help', 'status', 'webmail', 'cpanel', 'pma', 'phpmyadmin', 'centraldefaturas'];
if (in_array($sub, $reservados, true)) {
    echo json_encode(['disponivel' => false, 'motivo' => 'reservado']);
    exit;
}

$pdo = getConnection();
if (!$pdo) {
    echo json_encode(['disponivel' => false, 'motivo' => 'erro_db']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE subdominio = ?");
$stmt->execute([$sub]);
$existe = (int)$stmt->fetchColumn() > 0;

echo json_encode(['disponivel' => !$existe]);
