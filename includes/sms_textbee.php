<?php
// =====================================================
// MÓDULO API SMS CONFIG - TEXTBEE SMS GATEWAY
// =====================================================
// Módulo independente: configuração por admin, verificação de conexão,
// envio manual de teste e histórico. Não integra com cobranças/financeiro.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

if (!defined('TEXTBEE_API_BASE')) define('TEXTBEE_API_BASE', 'https://api.textbee.dev/api/v1');
if (!defined('SMS_TEST_RATE_LIMIT_SECONDS')) define('SMS_TEST_RATE_LIMIT_SECONDS', 30);
if (!defined('SMS_TEST_RATE_LIMIT_MAX_5MIN')) define('SMS_TEST_RATE_LIMIT_MAX_5MIN', 5);
if (!defined('SMS_TEMPLATE_COBRANCA_PADRAO')) define('SMS_TEMPLATE_COBRANCA_PADRAO', "Olá {nome_cliente}! Sua fatura {codigo_fatura} no valor de R$ {valor} vence em {vencimento}. Pague em: {link_pagamento} - {nome_empresa}");
if (!defined('SMS_MAX_CHARS')) define('SMS_MAX_CHARS', 480);
if (!defined('SMS_LIMITE_DIARIO')) define('SMS_LIMITE_DIARIO', 50);
if (!defined('SMS_LIMITE_MENSAL')) define('SMS_LIMITE_MENSAL', 300);

function smsKeyPath() {
    return dirname(__DIR__) . '/config/.sms_key';
}

function smsKeyObter() {
    static $chave = null;
    if ($chave !== null) return $chave;
    $arquivo = smsKeyPath();
    if (!is_file($arquivo)) {
        $bruta = random_bytes(32);
        $ok = @file_put_contents($arquivo, base64_encode($bruta), LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Não foi possível criar config/.sms_key. Verifique permissões de escrita.');
        }
        @chmod($arquivo, 0600);
    }
    $conteudo = trim((string) @file_get_contents($arquivo));
    $decodificada = base64_decode($conteudo, true);
    if ($decodificada === false || strlen($decodificada) < 32) {
        throw new RuntimeException('Chave de criptografia SMS inválida em config/.sms_key.');
    }
    $chave = substr($decodificada, 0, 32);
    return $chave;
}

function smsCifrar($texto) {
    $texto = (string)$texto;
    if ($texto === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $cifrado = openssl_encrypt($texto, 'aes-256-gcm', smsKeyObter(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cifrado === false) throw new RuntimeException('Falha ao cifrar credencial SMS.');
    return 'enc:v1:' . base64_encode($iv . $tag . $cifrado);
}

function smsDecifrar($texto) {
    $texto = (string)$texto;
    if ($texto === '') return '';
    if (strpos($texto, 'enc:v1:') !== 0) return $texto;
    try {
        $bruto = base64_decode(substr($texto, 7), true);
        if ($bruto === false || strlen($bruto) < 29) return '';
        $iv = substr($bruto, 0, 12);
        $tag = substr($bruto, 12, 16);
        $cifrado = substr($bruto, 28);
        $plano = openssl_decrypt($cifrado, 'aes-256-gcm', smsKeyObter(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plano === false ? '' : $plano;
    } catch (Throwable $e) {
        return '';
    }
}

function smsMascararApiKey($valor) {
    $valor = (string)$valor;
    $tam = strlen($valor);
    if ($tam === 0) return '';
    if ($tam <= 8) return str_repeat('*', $tam);
    return str_repeat('*', max(4, $tam - 4)) . substr($valor, -4);
}

function smsNormalizarTelefone($telefone) {
    $digitos = preg_replace('/\D+/', '', (string)$telefone);
    if ($digitos === '') return '';
    if (strlen($digitos) === 11 || strlen($digitos) === 10) return $digitos;
    if (strlen($digitos) === 13 && strpos($digitos, '55') === 0) return substr($digitos, 2);
    if (strlen($digitos) > 13) return substr($digitos, -13);
    return $digitos;
}

function smsFormatarTelefoneBr($telefone) {
    $d = smsNormalizarTelefone($telefone);
    if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    return (string)$telefone;
}

function smsTelefoneValidoBr($telefone) {
    $d = smsNormalizarTelefone($telefone);
    if (!preg_match('/^\d{10,11}$/', $d)) return false;
    $ddd = (int)substr($d, 0, 2);
    if ($ddd < 11 || $ddd > 99) return false;
    if (strlen($d) === 11 && substr($d, 2, 1) !== '9') return false;
    return true;
}

function smsParaE164Br($telefone) {
    $d = smsNormalizarTelefone($telefone);
    if ($d === '') return '';
    if (strpos($d, '55') === 0 && (strlen($d) === 12 || strlen($d) === 13)) return '+' . $d;
    if (strlen($d) === 10 || strlen($d) === 11) return '+55' . $d;
    $raw = preg_replace('/[^\d+]/', '', (string)$telefone);
    if (strpos($raw, '+') === 0) return $raw;
    return '+' . $d;
}

function smsGarantirTabelas($pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return false;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_sms_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT NOT NULL,
            `provider` VARCHAR(30) NOT NULL DEFAULT 'textbee',
            `api_key_encrypted` TEXT DEFAULT NULL,
            `phone_number` VARCHAR(30) DEFAULT NULL,
            `device_id` VARCHAR(100) DEFAULT NULL,
            `connection_status` VARCHAR(40) NOT NULL DEFAULT 'desconectado',
            `last_connection_check` DATETIME DEFAULT NULL,
            `last_error` TEXT DEFAULT NULL,
            `template_cobranca` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_admin_sms_settings_admin` (`admin_id`),
            KEY `idx_admin_sms_settings_status` (`connection_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { error_log('[SMS] criar admin_sms_settings: ' . $e->getMessage()); }

    // Migração idempotente para instalações existentes
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `admin_sms_settings` LIKE 'template_cobranca'")->fetch();
        if (!$cols) $pdo->exec("ALTER TABLE `admin_sms_settings` ADD COLUMN `template_cobranca` TEXT DEFAULT NULL AFTER `last_error`");
    } catch (Throwable $e) { /* ignora */ }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_sms_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT NOT NULL,
            `device_id` VARCHAR(100) DEFAULT NULL,
            `recipient_phone` VARCHAR(30) DEFAULT NULL,
            `message` TEXT DEFAULT NULL,
            `provider_message_id` VARCHAR(120) DEFAULT NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'pendente',
            `error_message` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_admin_sms_logs_admin_created` (`admin_id`, `created_at`),
            KEY `idx_admin_sms_logs_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { error_log('[SMS] criar admin_sms_logs: ' . $e->getMessage()); }
    return true;
}

function smsObterConfig($adminId, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return null;
    smsGarantirTabelas($pdo);
    $stmt = $pdo->prepare("SELECT * FROM admin_sms_settings WHERE admin_id = ? LIMIT 1");
    $stmt->execute([(int)$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function smsSalvarConfig($adminId, array $dados, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return false;
    smsGarantirTabelas($pdo);
    $existente = smsObterConfig($adminId, $pdo);
    $apiKeyEncrypted = $existente['api_key_encrypted'] ?? null;
    if (array_key_exists('api_key', $dados)) {
        $apiKey = trim((string)$dados['api_key']);
        if ($apiKey !== '') $apiKeyEncrypted = smsCifrar($apiKey);
    }
    $phone = smsNormalizarTelefone($dados['phone_number'] ?? ($existente['phone_number'] ?? ''));
    $deviceId = trim((string)($dados['device_id'] ?? ($existente['device_id'] ?? '')));
    $status = trim((string)($dados['connection_status'] ?? ($existente['connection_status'] ?? 'desconectado')));
    $lastError = array_key_exists('last_error', $dados) ? $dados['last_error'] : ($existente['last_error'] ?? null);
    $lastCheck = array_key_exists('last_connection_check', $dados) ? $dados['last_connection_check'] : ($existente['last_connection_check'] ?? null);
    $template = array_key_exists('template_cobranca', $dados) ? $dados['template_cobranca'] : ($existente['template_cobranca'] ?? null);

    if ($existente) {
        $stmt = $pdo->prepare("UPDATE admin_sms_settings SET provider='textbee', api_key_encrypted=?, phone_number=?, device_id=?, connection_status=?, last_connection_check=?, last_error=?, template_cobranca=?, updated_at=NOW() WHERE admin_id=?");
        return $stmt->execute([$apiKeyEncrypted, $phone, $deviceId, $status, $lastCheck, $lastError, $template, (int)$adminId]);
    }
    $stmt = $pdo->prepare("INSERT INTO admin_sms_settings (admin_id, provider, api_key_encrypted, phone_number, device_id, connection_status, last_connection_check, last_error, template_cobranca, created_at, updated_at) VALUES (?, 'textbee', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    return $stmt->execute([(int)$adminId, $apiKeyEncrypted, $phone, $deviceId, $status, $lastCheck, $lastError, $template]);
}

function smsAtualizarStatusConexao($adminId, $status, $erro = null, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return false;
    smsGarantirTabelas($pdo);
    $stmt = $pdo->prepare("UPDATE admin_sms_settings SET connection_status=?, last_connection_check=NOW(), last_error=?, updated_at=NOW() WHERE admin_id=?");
    return $stmt->execute([substr((string)$status, 0, 40), $erro !== null ? (string)$erro : null, (int)$adminId]);
}

function smsHttpRequest($method, $path, $apiKey, $body = null, $timeout = 20) {
    $url = rtrim(TEXTBEE_API_BASE, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $headers = [
        'x-api-key: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: CentralDeFaturas-SMS/1.0',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$resp, true);
    return ['ok' => $err === '' && $code >= 200 && $code < 300, 'http_code' => $code, 'body' => $json, 'raw' => (string)$resp, 'curl_error' => $err];
}

function smsClassificarErroApi($httpCode, $body, $curlError = '') {
    if ($curlError !== '') return ['status' => 'erro_comunicacao', 'mensagem' => 'Erro de comunicação: ' . $curlError];
    $msg = '';
    if (is_array($body)) $msg = (string)($body['message'] ?? $body['error'] ?? $body['data']['message'] ?? '');
    $msgLower = strtolower($msg);
    if ($httpCode === 401 || $httpCode === 403) return ['status' => 'api_key_invalida', 'mensagem' => 'API Key inválida ou conta não autorizada.'];
    if ($httpCode === 404) {
        if (strpos($msgLower, 'device') !== false) return ['status' => 'dispositivo_nao_encontrado', 'mensagem' => 'Dispositivo não encontrado na conta TextBee.'];
        return ['status' => 'erro_comunicacao', 'mensagem' => 'Recurso não encontrado na API TextBee.'];
    }
    if ($httpCode >= 500) return ['status' => 'erro_comunicacao', 'mensagem' => 'Erro na API TextBee (HTTP ' . $httpCode . ').'];
    if ($httpCode >= 400) return ['status' => 'erro_comunicacao', 'mensagem' => $msg !== '' ? $msg : 'Erro na comunicação com a API TextBee (HTTP ' . $httpCode . ').'];
    return ['status' => 'erro_comunicacao', 'mensagem' => $msg !== '' ? $msg : 'Erro desconhecido na API TextBee.'];
}

function smsListarDispositivos($apiKey) {
    $res = smsHttpRequest('GET', '/gateway/devices', $apiKey);
    if (!$res['ok']) {
        $err = smsClassificarErroApi($res['http_code'], $res['body'], $res['curl_error']);
        return ['sucesso' => false, 'status' => $err['status'], 'mensagem' => $err['mensagem'], 'dispositivos' => []];
    }
    $data = $res['body']['data'] ?? [];
    if (!is_array($data)) $data = [];
    $lista = [];
    foreach ($data as $d) {
        if (!is_array($d)) continue;
        $lista[] = [
            'id' => (string)($d['_id'] ?? $d['id'] ?? ''),
            'name' => (string)($d['name'] ?? $d['model'] ?? $d['brand'] ?? 'Dispositivo'),
            'enabled' => !empty($d['enabled']),
            'lastHeartbeat' => $d['lastHeartbeat'] ?? null,
        ];
    }
    return ['sucesso' => true, 'status' => 'ok', 'mensagem' => 'Dispositivos carregados.', 'dispositivos' => $lista];
}

function smsVerificarConexao($adminId, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    smsGarantirTabelas($pdo);
    $cfg = smsObterConfig($adminId, $pdo);
    if (!$cfg) return ['status' => 'desconectado', 'mensagem' => 'Configuração não encontrada. Salve a API Key e o dispositivo.'];
    $apiKey = smsDecifrar($cfg['api_key_encrypted'] ?? '');
    $deviceId = trim((string)($cfg['device_id'] ?? ''));
    if ($apiKey === '') { smsAtualizarStatusConexao($adminId, 'api_key_invalida', 'API Key não configurada.', $pdo); return ['status' => 'api_key_invalida', 'mensagem' => 'API Key não configurada.']; }
    if ($deviceId === '') { smsAtualizarStatusConexao($adminId, 'dispositivo_nao_encontrado', 'Device ID não informado.', $pdo); return ['status' => 'dispositivo_nao_encontrado', 'mensagem' => 'Informe o Device ID do seu dispositivo Android.']; }

    $stats = smsHttpRequest('GET', '/gateway/stats', $apiKey);
    if (!$stats['ok']) { $err = smsClassificarErroApi($stats['http_code'], $stats['body'], $stats['curl_error']); smsAtualizarStatusConexao($adminId, $err['status'], $err['mensagem'], $pdo); return ['status' => $err['status'], 'mensagem' => $err['mensagem']]; }

    $devices = smsHttpRequest('GET', '/gateway/devices', $apiKey);
    if (!$devices['ok']) { $err = smsClassificarErroApi($devices['http_code'], $devices['body'], $devices['curl_error']); smsAtualizarStatusConexao($adminId, $err['status'], $err['mensagem'], $pdo); return ['status' => $err['status'], 'mensagem' => $err['mensagem']]; }

    $encontrado = null;
    foreach (($devices['body']['data'] ?? []) as $d) {
        $id = (string)($d['_id'] ?? $d['id'] ?? '');
        if ($id !== '' && hash_equals($id, $deviceId)) { $encontrado = $d; break; }
    }
    if (!$encontrado) { smsAtualizarStatusConexao($adminId, 'dispositivo_nao_encontrado', 'O Device ID informado não pertence à conta ou não foi encontrado.', $pdo); return ['status' => 'dispositivo_nao_encontrado', 'mensagem' => 'Dispositivo não encontrado nesta conta TextBee.']; }

    $enabled = !empty($encontrado['enabled']);
    $lastHeartbeat = $encontrado['lastHeartbeat'] ?? null;
    $comunicacaoIncerta = false;
    if ($lastHeartbeat) { $ts = strtotime((string)$lastHeartbeat); if ($ts !== false && (time() - $ts) > 86400) $comunicacaoIncerta = true; }
    if (!$enabled) { smsAtualizarStatusConexao($adminId, 'dispositivo_indisponivel', 'Dispositivo encontrado, mas está desabilitado no TextBee.', $pdo); return ['status' => 'dispositivo_indisponivel', 'mensagem' => 'Dispositivo encontrado, mas está indisponível/desabilitado.']; }
    if ($comunicacaoIncerta) { smsAtualizarStatusConexao($adminId, 'dispositivo_indisponivel', 'Dispositivo sem heartbeat recente; pode estar indisponível.', $pdo); return ['status' => 'dispositivo_indisponivel', 'mensagem' => 'Dispositivo encontrado, mas sem comunicação recente (heartbeat antigo). Verifique se o app está online.']; }

    smsAtualizarStatusConexao($adminId, 'conectado', null, $pdo);
    return ['status' => 'conectado', 'mensagem' => 'Conexão verificada com sucesso. API Key válida e dispositivo disponível.'];
}

function smsRegistrarLog($adminId, array $dados, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return false;
    smsGarantirTabelas($pdo);
    $stmt = $pdo->prepare("INSERT INTO admin_sms_logs (admin_id, device_id, recipient_phone, message, provider_message_id, status, error_message, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    return $stmt->execute([
        (int)$adminId,
        substr((string)($dados['device_id'] ?? ''), 0, 100),
        substr((string)($dados['recipient_phone'] ?? ''), 0, 30),
        (string)($dados['message'] ?? ''),
        substr((string)($dados['provider_message_id'] ?? ''), 0, 120),
        substr((string)($dados['status'] ?? 'pendente'), 0, 40),
        $dados['error_message'] ?? null,
    ]);
}

function smsPodeEnviarTeste($adminId, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return ['ok' => true];
    smsGarantirTabelas($pdo);
    $stmt = $pdo->prepare("SELECT created_at FROM admin_sms_logs WHERE admin_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)$adminId]);
    $ultimo = $stmt->fetchColumn();
    if ($ultimo) {
        $delta = time() - strtotime((string)$ultimo);
        if ($delta < SMS_TEST_RATE_LIMIT_SECONDS) return ['ok' => false, 'mensagem' => 'Aguarde ' . (SMS_TEST_RATE_LIMIT_SECONDS - $delta) . 's antes de enviar outro teste.'];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_sms_logs WHERE admin_id = ? AND created_at >= (NOW() - INTERVAL 5 MINUTE)");
    $stmt->execute([(int)$adminId]);
    if ((int)$stmt->fetchColumn() >= SMS_TEST_RATE_LIMIT_MAX_5MIN) return ['ok' => false, 'mensagem' => 'Limite de testes atingido. Aguarde alguns minutos antes de tentar novamente.'];
    return ['ok' => true];
}

function smsEnviarTeste($adminId, $destino, $mensagem, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    smsGarantirTabelas($pdo);
    $cfg = smsObterConfig($adminId, $pdo);
    if (!$cfg) return ['sucesso' => false, 'status' => 'desconectado', 'mensagem' => 'Configuração SMS não encontrada.'];
    $apiKey = smsDecifrar($cfg['api_key_encrypted'] ?? '');
    $deviceId = trim((string)($cfg['device_id'] ?? ''));
    if ($apiKey === '' || $deviceId === '') return ['sucesso' => false, 'status' => 'desconectado', 'mensagem' => 'Configure a API Key e o Device ID antes de enviar.'];

    $rate = smsPodeEnviarTeste($adminId, $pdo);
    if (!$rate['ok']) return ['sucesso' => false, 'status' => 'limite', 'mensagem' => $rate['mensagem']];

    $destinoNorm = smsParaE164Br($destino);
    $destinoDigits = preg_replace('/\D+/', '', $destinoNorm);
    if (strlen($destinoDigits) < 10 || strlen($destinoDigits) > 15) return ['sucesso' => false, 'status' => 'numero_invalido', 'mensagem' => 'Número de destino inválido. Use DDD + número.'];
    $mensagem = trim((string)$mensagem);
    if ($mensagem === '') return ['sucesso' => false, 'status' => 'mensagem_invalida', 'mensagem' => 'Informe a mensagem do teste.'];
    if (mb_strlen($mensagem) > 480) return ['sucesso' => false, 'status' => 'mensagem_invalida', 'mensagem' => 'Mensagem muito longa (máximo 480 caracteres).'];

    $res = smsHttpRequest('POST', '/gateway/send-sms', $apiKey, ['message' => $mensagem, 'recipients' => [$destinoNorm], 'deviceId' => $deviceId]);

    $providerId = ''; $status = 'falha_envio'; $erro = null; $msgUsuario = 'Falha no envio.';
    if ($res['ok']) {
        $data = $res['body']['data'] ?? [];
        $providerId = (string)($data['smsBatchId'] ?? $data['id'] ?? '');
        $status = 'solicitacao_aceita';
        $msgUsuario = 'Solicitação aceita pela API TextBee. O envio final depende do dispositivo Android e da operadora.';
    } else {
        $class = smsClassificarErroApi($res['http_code'], $res['body'], $res['curl_error']);
        $bodyMsg = strtolower((string)($res['body']['message'] ?? $res['body']['error'] ?? ''));
        if ($class['status'] === 'dispositivo_nao_encontrado' || $class['status'] === 'dispositivo_indisponivel' || (strpos($bodyMsg, 'device') !== false && strpos($bodyMsg, 'disabled') !== false)) {
            $status = 'dispositivo_indisponivel'; $msgUsuario = 'Dispositivo indisponível ou desabilitado.';
        } elseif ($class['status'] === 'api_key_invalida') {
            $status = 'erro_api'; $msgUsuario = $class['mensagem'];
        } elseif ($res['http_code'] === 400) {
            $status = 'falha_envio'; $msgUsuario = (string)($res['body']['message'] ?? $res['body']['error'] ?? 'A API recusou a solicitação de envio.');
        } else {
            $status = 'erro_api'; $msgUsuario = $class['mensagem'];
        }
        $erro = $msgUsuario;
    }

    smsRegistrarLog($adminId, ['device_id' => $deviceId, 'recipient_phone' => $destinoNorm, 'message' => $mensagem, 'provider_message_id' => $providerId, 'status' => $status, 'error_message' => $erro], $pdo);

    if ($status === 'solicitacao_aceita') smsAtualizarStatusConexao($adminId, 'conectado', null, $pdo);
    elseif (in_array($status, ['dispositivo_indisponivel', 'erro_api'], true)) {
        $statusConexao = $status === 'dispositivo_indisponivel' ? 'dispositivo_indisponivel' : 'erro_comunicacao';
        if (strpos(strtolower((string)$erro), 'api key') !== false) $statusConexao = 'api_key_invalida';
        smsAtualizarStatusConexao($adminId, $statusConexao, $erro, $pdo);
    }

    return ['sucesso' => $res['ok'], 'status' => $status, 'mensagem' => $msgUsuario, 'provider_message_id' => $providerId, 'http_code' => $res['http_code'], 'resposta' => $res['body']];
}

function smsBuscarLogs($adminId, array $filtros = [], $pdo = null) {
    $pdo = $pdo ?: getConnection();
    smsGarantirTabelas($pdo);
    $where = ['admin_id = ?']; $params = [(int)$adminId];
    $status = trim((string)($filtros['status'] ?? ''));
    if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
    $de = trim((string)($filtros['de'] ?? ''));
    $ate = trim((string)($filtros['ate'] ?? ''));
    if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) { $where[] = 'DATE(created_at) >= ?'; $params[] = $de; }
    if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $where[] = 'DATE(created_at) <= ?'; $params[] = $ate; }
    $sql = "SELECT * FROM admin_sms_logs WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function smsObterTemplateCobranca($adminId, $pdo = null) {
    $cfg = smsObterConfig($adminId, $pdo);
    $tpl = trim((string)($cfg['template_cobranca'] ?? ''));
    return $tpl !== '' ? $tpl : SMS_TEMPLATE_COBRANCA_PADRAO;
}

function smsRenderizarTemplate($template, array $vars) {
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
    return strtr((string)$template, $map);
}

function smsContarSegmentos($mensagem) {
    $len = function_exists('mb_strlen') ? mb_strlen((string)$mensagem, 'UTF-8') : strlen((string)$mensagem);
    if ($len <= 0) return 0;
    if ($len <= 160) return 1;
    return (int)ceil($len / 153);
}

function smsValidarTemplate($template) {
    $template = trim((string)$template);
    if ($template === '') return ['ok' => false, 'erro' => 'O template de SMS não pode ficar vazio.'];
    $len = function_exists('mb_strlen') ? mb_strlen($template, 'UTF-8') : strlen($template);
    if ($len > SMS_MAX_CHARS) return ['ok' => false, 'erro' => 'O template excede o limite de ' . SMS_MAX_CHARS . ' caracteres.'];
    return ['ok' => true, 'len' => $len, 'segmentos' => smsContarSegmentos($template)];
}

function smsDadosFaturaParaTemplate($fatura, $adminId = 0) {
    $adminId = $adminId > 0 ? $adminId : (int)($fatura['admin_id'] ?? 0);
    $nomeEmpresa = '';
    if ($adminId > 0 && function_exists('getNomeSistema')) $nomeEmpresa = (string)getNomeSistema($adminId);
    if ($nomeEmpresa === '') $nomeEmpresa = 'Central de Faturas';
    $link = trim((string)($fatura['link_pagamento'] ?? ''));
    if ($link === '' && !empty($fatura['id']) && function_exists('getLinkFatura')) $link = (string)getLinkFatura((int)$fatura['id']);
    $venc = $fatura['data_vencimento'] ?? '';
    $vencFmt = $venc ? date('d/m/Y', strtotime($venc)) : '';
    $valor = number_format((float)($fatura['valor_final'] ?? $fatura['valor'] ?? 0), 2, ',', '.');
    return [
        'nome_cliente' => (string)($fatura['nome_razao'] ?? $fatura['cliente_nome'] ?? 'Cliente'),
        'valor' => $valor,
        'vencimento' => $vencFmt,
        'codigo_fatura' => (string)($fatura['numero'] ?? $fatura['id'] ?? ''),
        'link_pagamento' => $link,
        'nome_empresa' => $nomeEmpresa,
    ];
}

function smsVerificarLimites($adminId, $pdo = null) {
    $pdo = $pdo ?: getConnection();
    if (!$pdo) return ['ok' => true];
    smsGarantirTabelas($pdo);
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM admin_sms_logs WHERE admin_id=? AND status='solicitacao_aceita' AND DATE(created_at)=CURDATE()");
        $st->execute([(int)$adminId]);
        $dia = (int)$st->fetchColumn();
        if ($dia >= SMS_LIMITE_DIARIO) return ['ok' => false, 'mensagem' => 'Limite diário de SMS atingido (' . SMS_LIMITE_DIARIO . ').'];
        $st = $pdo->prepare("SELECT COUNT(*) FROM admin_sms_logs WHERE admin_id=? AND status='solicitacao_aceita' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
        $st->execute([(int)$adminId]);
        $mes = (int)$st->fetchColumn();
        if ($mes >= SMS_LIMITE_MENSAL) return ['ok' => false, 'mensagem' => 'Limite mensal de SMS atingido (' . SMS_LIMITE_MENSAL . ').'];
    } catch (Throwable $e) { /* em caso de falha, não bloquear envio */ }
    return ['ok' => true];
}

function smsEnviarCobrancaFatura($adminId, array $fatura, array $opcoes = [], $pdo = null) {
    $pdo = $pdo ?: getConnection();
    smsGarantirTabelas($pdo);
    $adminId = (int)$adminId;
    $cfg = smsObterConfig($adminId, $pdo);
    if (!$cfg) return ['sucesso' => false, 'status' => 'desconectado', 'mensagem' => 'Configuração SMS não encontrada.'];

    $apiKey = smsDecifrar($cfg['api_key_encrypted'] ?? '');
    $deviceId = trim((string)($cfg['device_id'] ?? ''));
    if ($apiKey === '' || $deviceId === '') return ['sucesso' => false, 'status' => 'desconectado', 'mensagem' => 'API Key ou Device ID não configurados.'];

    $destino = trim((string)($opcoes['destino'] ?? ($fatura['celular'] ?? $fatura['telefone'] ?? '')));
    if ($destino === '' || !smsTelefoneValidoBr($destino)) {
        return ['sucesso' => false, 'status' => 'numero_invalido', 'mensagem' => 'Cliente sem número de celular válido.'];
    }

    $limite = smsVerificarLimites($adminId, $pdo);
    if (!$limite['ok']) return ['sucesso' => false, 'status' => 'limite', 'mensagem' => $limite['mensagem']];

    $template = trim((string)($opcoes['mensagem'] ?? ''));
    if ($template === '') $template = smsObterTemplateCobranca($adminId, $pdo);
    $vars = smsDadosFaturaParaTemplate($fatura, $adminId);
    $mensagem = smsRenderizarTemplate($template, $vars);
    $valid = smsValidarTemplate($mensagem);
    if (!$valid['ok']) return ['sucesso' => false, 'status' => 'mensagem_invalida', 'mensagem' => $valid['erro']];

    $tipoLog = substr((string)($opcoes['tipo_log'] ?? 'sms_cobranca'), 0, 30);
    $faturaId = (int)($fatura['id'] ?? 0);

    // Deduplicação para régua automática: mesma fatura/tipo/canal enviado com sucesso
    if (!empty($opcoes['deduplicar_regua']) && $faturaId > 0) {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM envios_log WHERE fatura_id=? AND canal='sms' AND tipo=? AND status='enviado'");
            $st->execute([$faturaId, $tipoLog]);
            if ((int)$st->fetchColumn() > 0) {
                return ['sucesso' => false, 'status' => 'duplicado', 'mensagem' => 'SMS já enviado para esta fatura e etapa da régua.'];
            }
        } catch (Throwable $e) { /* se envios_log indisponível, não bloqueia */ }
    }

    $destinoNorm = smsParaE164Br($destino);
    $res = smsHttpRequest('POST', '/gateway/send-sms', $apiKey, ['message' => $mensagem, 'recipients' => [$destinoNorm], 'deviceId' => $deviceId]);

    $providerId = ''; $status = 'falha_envio'; $erro = null; $msgUsuario = 'Falha no envio.';
    if ($res['ok']) {
        $data = $res['body']['data'] ?? [];
        $providerId = (string)($data['smsBatchId'] ?? $data['id'] ?? '');
        $status = 'solicitacao_aceita';
        $msgUsuario = 'Solicitação aceita pela API TextBee. O envio final depende do dispositivo Android e da operadora.';
    } else {
        $class = smsClassificarErroApi($res['http_code'], $res['body'], $res['curl_error']);
        $bodyMsg = strtolower((string)($res['body']['message'] ?? $res['body']['error'] ?? ''));
        if ($class['status'] === 'dispositivo_nao_encontrado' || $class['status'] === 'dispositivo_indisponivel' || (strpos($bodyMsg, 'device') !== false && strpos($bodyMsg, 'disabled') !== false)) {
            $status = 'dispositivo_indisponivel'; $msgUsuario = 'Dispositivo indisponível ou desabilitado.';
        } elseif ($class['status'] === 'api_key_invalida') {
            $status = 'erro_api'; $msgUsuario = $class['mensagem'];
        } elseif ($res['http_code'] === 400) {
            $status = 'falha_envio'; $msgUsuario = (string)($res['body']['message'] ?? $res['body']['error'] ?? 'A API recusou a solicitação de envio.');
        } else {
            $status = 'erro_api'; $msgUsuario = $class['mensagem'];
        }
        $erro = $msgUsuario;
    }

    smsRegistrarLog($adminId, ['device_id' => $deviceId, 'recipient_phone' => $destinoNorm, 'message' => $mensagem, 'provider_message_id' => $providerId, 'status' => $status, 'error_message' => $erro], $pdo);

    // Log unificado do sistema (envios_log) para histórico/régua
    try {
        if (function_exists('registrarEnvioLog')) {
            $detalhe = $msgUsuario;
            if ($tipoLog === 'sms_manual') $detalhe = 'Envio manual de SMS. ' . $detalhe;
            if ($providerId !== '') $detalhe .= ' [id:' . $providerId . ']';
            registrarEnvioLog($adminId, $faturaId, 'sms', $tipoLog, $res['ok'] ? 'enviado' : 'erro', $destinoNorm, $detalhe);
        }
    } catch (Throwable $e) { /* não interrompe envio */ }

    if ($status === 'solicitacao_aceita') smsAtualizarStatusConexao($adminId, 'conectado', null, $pdo);
    elseif (in_array($status, ['dispositivo_indisponivel', 'erro_api'], true)) {
        $statusConexao = $status === 'dispositivo_indisponivel' ? 'dispositivo_indisponivel' : 'erro_comunicacao';
        if (strpos(strtolower((string)$erro), 'api key') !== false) $statusConexao = 'api_key_invalida';
        smsAtualizarStatusConexao($adminId, $statusConexao, $erro, $pdo);
    }

    return ['sucesso' => $res['ok'], 'status' => $status, 'mensagem' => $msgUsuario, 'provider_message_id' => $providerId, 'destino' => $destinoNorm, 'mensagem_renderizada' => $mensagem];
}