<?php
// =====================================================
// DIAGNÓSTICO DA API DE PAGAMENTO / GERAÇÃO DE BOLETO
// Acesse logado como admin. Exclua este arquivo após o uso.
// =====================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';

header('Content-Type: text/plain; charset=utf-8');

echo "===== DIAGNÓSTICO DE BOLETO =====\n\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "SO: " . PHP_OS . "\n";
echo "cURL: " . (function_exists('curl_init') ? 'ok' : '*** FALTANDO ***') . "\n";
echo "mbstring: " . (function_exists('mb_substr') ? 'ok' : '*** FALTANDO ***') . "\n\n";

echo "[1] API ATIVA NO BANCO DESTA HOSPEDAGEM: " . getApiAtiva() . "\n";
echo "    (valor bruto na tabela: " . var_export(getConfig('api_pagamento_ativa', '(linha inexistente)'), true) . ")\n\n";

echo "[2] BANCO INTER\n";
$ic = getConfigInter();
echo "    client_id     : " . (!empty($ic['inter_client_id']) ? substr($ic['inter_client_id'], 0, 6) . '...' : 'VAZIO') . "\n";
echo "    client_secret : " . (!empty($ic['inter_client_secret']) ? 'definido (' . strlen($ic['inter_client_secret']) . ' chars)' : 'VAZIO') . "\n";
echo "    conta         : " . (!empty($ic['inter_conta']) ? $ic['inter_conta'] : 'VAZIA') . "\n";
foreach (['inter_cert_crt' => 'certificado.crt', 'inter_cert_key' => 'certificado.key', 'inter_cert_webhook' => 'certificado_webhook.pem'] as $campo => $arquivo) {
    $caminho = $ic[$campo] ?? '';
    $resolvido = resolverCaminhoCert($caminho);
    echo "    {$campo}:\n";
    echo "        salvo no banco : " . ($caminho !== '' ? $caminho : 'VAZIO') . "\n";
    echo "        arquivo existe : " . ($caminho !== '' && file_exists($caminho) ? 'SIM' : 'NÃO') . "\n";
    echo "        resolvido p/   : " . $resolvido . " => " . (file_exists($resolvido) ? 'existe' : 'NÃO EXISTE') . "\n";
}
$dirCerts = __DIR__ . '/../config/inter_certs';
echo "    pasta {$dirCerts}: " . (is_dir($dirCerts) ? implode(', ', array_diff(scandir($dirCerts), ['.', '..'])) : 'NÃO EXISTE') . "\n";
echo "    Teste de token Inter:\n";
$token = obterTokenInter();
echo "        " . (isset($token['erro']) ? 'ERRO => ' . $token['erro'] : 'OK, token obtido com sucesso') . "\n\n";

echo "[3] ASAAS\n";
$ac = getAsaasConfig();
echo "    api_key  : " . (!empty($ac['asaas_api_key']) ? substr($ac['asaas_api_key'], 0, 12) . '... (' . strlen($ac['asaas_api_key']) . ' chars)' : 'VAZIA') . "\n";
echo "    ambiente : " . (!empty($ac['asaas_ambiente']) ? $ac['asaas_ambiente'] : '(padrão producao)') . "\n";
echo "    Teste de conexão Asaas (GET /customers):\n";
$tst = asaasRequest('GET', '/customers?limit=1');
if (isset($tst['sucesso'])) {
    echo "        OK, autenticação válida (total clientes: " . ($tst['dados']['totalCount'] ?? '?') . ")\n";
} else {
    echo "        ERRO => " . ($tst['erro'] ?? 'desconhecido') . "\n";
}
echo "\n";

echo "[4] ÚLTIMAS TENTATIVAS (boleto_debug.log)\n";
$logFile = __DIR__ . '/../boleto_debug.log';
if (file_exists($logFile)) {
    $linhas = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_slice($linhas, -10) as $l) {
        echo "    " . htmlspecialchars($l) . "\n";
    }
} else {
    echo "    (log não existe ainda)\n";
}

echo "\n===== FIM — exclua este arquivo após resolver =====\n";
