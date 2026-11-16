<?php
// =====================================================
// API AJAX - INTEGRACAO EVOLUTION (Super Admin)
// =====================================================
require_once __DIR__ . '/../auth.php';
requireSuper();
require_once __DIR__ . '/../../config/marketing_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function evo_resposta($dados, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function evo_erro($mensagem, $codigo = 400) {
    evo_resposta(['ok' => false, 'mensagem' => $mensagem], $codigo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    evo_erro('Metodo nao permitido.', 405);
}

$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!mkt_csrf_validar($token)) {
    evo_erro('Sessao expirada. Recarregue a pagina e tente novamente.', 419);
}

$acao = (string) ($_POST['acao'] ?? '');
$cfgAtual = mkt_evolution_config();

if ($acao === 'salvar') {
    $url = trim((string) ($_POST['url'] ?? ''));
    $apiKey = trim((string) ($_POST['api_key'] ?? ''));
    $instance = trim((string) ($_POST['instance'] ?? ''));

    if ($url === '' || !preg_match('#^https?://[^\s/]+#i', $url)) {
        evo_erro('Informe uma URL valida (http:// ou https://).');
    }
    if ($instance === '' || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $instance)) {
        evo_erro('Nome da instancia invalido. Use letras, numeros, hifen ou underline (ate 100 caracteres).');
    }
    if ($apiKey === '' && $cfgAtual['key'] === '') {
        evo_erro('Informe a API Key da Evolution.');
    }

    $ok = mkt_config_set('mkt_evolution_url', $url);
    $ok = mkt_config_set('mkt_evolution_instance', $instance) && $ok;
    if ($apiKey !== '') {
        $ok = mkt_config_set('mkt_evolution_key', $apiKey, true) && $ok;
    }
    if (!$ok) {
        evo_erro('Nao foi possivel salvar. Verifique permissoes de escrita em config/.marketing_key.');
    }
    $novo = mkt_evolution_config();
    evo_resposta([
        'ok' => true,
        'mensagem' => 'Configuracoes da Evolution salvas com sucesso.',
        'mascara' => mkt_mascarar($novo['key']),
    ]);
}

if ($acao === 'testar') {
    if (!mkt_evolution_configurada($cfgAtual)) {
        evo_erro('Configure URL, API Key e instancia antes de testar.');
    }
    $status = mkt_evolution_status($cfgAtual);
    if ($status['estado'] === 'erro') {
        evo_erro('Falha no teste: ' . $status['mensagem']);
    }
    evo_resposta([
        'ok' => true,
        'mensagem' => 'Conexao com a Evolution OK. Estado: ' . $status['estado'] . '.',
        'status' => $status,
    ]);
}

if ($acao === 'status') {
    $status = mkt_evolution_status($cfgAtual);
    evo_resposta(['ok' => true, 'status' => $status, 'mascara' => mkt_mascarar($cfgAtual['key'])]);
}

if ($acao === 'conectar' || $acao === 'qrcode') {
    if (!mkt_evolution_configurada($cfgAtual)) {
        evo_erro('Configure URL, API Key e instancia antes de conectar.');
    }
    $status = mkt_evolution_status($cfgAtual);
    if ($status['estado'] === 'conectado') {
        evo_resposta(['ok' => true, 'conectado' => true, 'status' => $status, 'mensagem' => 'WhatsApp ja esta conectado.']);
    }

    // Garante a existencia da instancia antes de pedir o QR.
    if ($acao === 'conectar') {
        $criar = mkt_evolution_criar($cfgAtual);
        if (!$criar['ok']) {
            evo_erro('Falha ao criar/verificar instancia: ' . $criar['error']);
        }
    }

    $qr = mkt_evolution_qrcode($cfgAtual);
    if (!$qr['ok']) {
        evo_erro('Falha ao obter QR Code: ' . $qr['error']);
    }

    // Se veio apenas o texto do QR, gera a imagem localmente (sem simular conexao).
    $qrImagem = $qr['base64'];
    $qrTexto = $qr['code'] !== '' ? $qr['code'] : (strlen($qrImagem) < 400 ? $qrImagem : '');
    if (strlen($qrImagem) < 400 && $qrTexto !== '') {
        require_once __DIR__ . '/../../config/phpqrcode.php';
        ob_start();
        try {
            QRcode::png($qrTexto, false, QR_ECLEVEL_L, 6, 2);
        } catch (Throwable $e) {
            ob_end_clean();
            evo_erro('A Evolution retornou apenas o texto do QR e nao foi possivel gera-lo localmente.');
        }
        $qrImagem = base64_encode(ob_get_clean());
    }

    evo_resposta([
        'ok' => true,
        'conectado' => false,
        'base64' => $qrImagem,
        'status' => mkt_evolution_status($cfgAtual),
        'mensagem' => 'QR Code gerado. Escaneie pelo aplicativo WhatsApp.',
    ]);
}

if ($acao === 'desconectar') {
    if (!mkt_evolution_configurada($cfgAtual)) {
        evo_erro('API Evolution nao configurada.');
    }
    $r = mkt_evolution_logout($cfgAtual);
    if (!$r['ok'] && !in_array($r['http'], [404, 405], true)) {
        evo_erro('Falha ao desconectar: ' . ($r['error'] ?: ('HTTP ' . $r['http'])));
    }
    evo_resposta(['ok' => true, 'mensagem' => 'WhatsApp desconectado.', 'status' => mkt_evolution_status($cfgAtual)]);
}

evo_erro('Acao invalida.');
