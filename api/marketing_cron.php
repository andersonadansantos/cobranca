<?php
// =====================================================
// ENDPOINT EXTERNO - CRON JOB DO MARKETING
// =====================================================
// Acionado pelo Cron Job da VPS (ou CLI). Protegido pelo cron_token global.
// Exemplo:
//   https://seudominio.com.br/cobranca/api/marketing_cron.php?token=SEU_TOKEN
//   php api/marketing_cron.php
//
// Regras: a rotina automatica so executa se estiver ativa, se o horario diario
// (America/Sao_Paulo) ja tiver sido atingido e se ainda nao rodou hoje.
// As campanhas manuais sao processadas a cada acionamento, respeitando a
// data/hora de inicio, a fila e os limites diarios exclusivos (20 WA/150 e-mail).

define('CONFIG_CACHE_ATIVO', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/marketing_helpers.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $tokenEsperado = (string) getConfigGlobal('cron_token', '');
    $tokenRecebido = (string) ($_GET['token'] ?? $_POST['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));

    if ($tokenEsperado === '' || $tokenRecebido === '' || !hash_equals($tokenEsperado, $tokenRecebido)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'mensagem' => 'Token invalido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    @set_time_limit(600);
    ignore_user_abort(true);
}

try {
    $resultado = mkt_cron_tick();
} catch (Throwable $e) {
    if (!$isCli) http_response_code(500);
    $saida = ['ok' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
    echo $isCli ? ('[ERRO] ' . $saida['mensagem'] . PHP_EOL) : json_encode($saida, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($isCli) {
    echo '[' . date('Y-m-d H:i:s') . '] ';
    if (!empty($resultado['executado'])) {
        echo 'Executado: ' . ($resultado['resultado']['mensagem'] ?? '') . PHP_EOL;
    } else {
        echo 'Nao executado: ' . ($resultado['motivo'] ?? '') . PHP_EOL;
    }
    if (!empty($resultado['manual']['mensagens'])) {
        echo '[Manual] ' . implode(' ', (array) $resultado['manual']['mensagens']) . PHP_EOL;
    }
    exit;
}

echo json_encode([
    'ok' => true,
    'executado' => (bool) ($resultado['executado'] ?? false),
    'motivo' => $resultado['motivo'] ?? null,
    'resultado' => $resultado['resultado'] ?? null,
    'manual' => $resultado['manual'] ?? null,
    'horario' => date('Y-m-d H:i:s'),
    'fuso' => 'America/Sao_Paulo',
], JSON_UNESCAPED_UNICODE);
