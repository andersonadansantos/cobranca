<?php
// =====================================================
// MODULO MARKETING (Super Admin) - FUNCOES COMPARTILHADAS
// =====================================================
// Centraliza: cofre de credenciais (AES-256-GCM), clientes Evolution/OpenAI/
// Google, normalizacao e deduplicacao de contatos, coleta publica, fila de
// disparos, templates, execucao agendada com lock e registro de auditoria.
//
// Regra de ouro: nunca inventar dados. Todo telefone/e-mail extraido pela IA
// precisa aparecer literalmente no texto publico que serviu de fonte.

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';

if (!defined('MKT_MAX_WHATSAPP_DIA')) define('MKT_MAX_WHATSAPP_DIA', 20);
if (!defined('MKT_MAX_EMAIL_DIA')) define('MKT_MAX_EMAIL_DIA', 20);
if (!defined('MKT_MAX_IMAGEM_BYTES')) define('MKT_MAX_IMAGEM_BYTES', 5 * 1024 * 1024);
if (!defined('MKT_MAX_IMAGENS_TEMPLATE')) define('MKT_MAX_IMAGENS_TEMPLATE', 8);

// =====================================================
// Cofre de credenciais
// =====================================================

function mkt_key_path() {
    return __DIR__ . '/.marketing_key';
}

function mkt_key_obter() {
    static $chave = null;
    if ($chave !== null) return $chave;

    $arquivo = mkt_key_path();
    if (!is_file($arquivo)) {
        $bruta = random_bytes(32);
        $ok = @file_put_contents($arquivo, base64_encode($bruta), LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Nao foi possivel criar config/.marketing_key. Verifique permissoes de escrita.');
        }
        @chmod($arquivo, 0600);
    }

    $conteudo = trim((string) @file_get_contents($arquivo));
    $decodificada = base64_decode($conteudo, true);
    if ($decodificada === false || strlen($decodificada) < 32) {
        throw new RuntimeException('Chave de criptografia do Marketing invalida em config/.marketing_key.');
    }
    $chave = substr($decodificada, 0, 32);
    return $chave;
}

function mkt_cifrar($texto) {
    $texto = (string) $texto;
    if ($texto === '') return '';
    $chave = mkt_key_obter();
    $iv = random_bytes(12);
    $tag = '';
    $cifrado = openssl_encrypt($texto, 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cifrado === false) {
        throw new RuntimeException('Falha ao cifrar credencial do Marketing.');
    }
    return 'enc:v1:' . base64_encode($iv . $tag . $cifrado);
}

function mkt_decifrar($texto) {
    $texto = (string) $texto;
    if ($texto === '') return '';
    if (strpos($texto, 'enc:v1:') !== 0) return $texto; // legado em texto puro
    try {
        $bruto = base64_decode(substr($texto, 7), true);
        if ($bruto === false || strlen($bruto) < 29) return '';
        $iv = substr($bruto, 0, 12);
        $tag = substr($bruto, 12, 16);
        $cifrado = substr($bruto, 28);
        $plano = openssl_decrypt($cifrado, 'aes-256-gcm', mkt_key_obter(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plano === false ? '' : $plano;
    } catch (Throwable $e) {
        return '';
    }
}

function mkt_config_get($chave, $padrao = '', $segredo = false) {
    $valor = getConfigGlobal($chave, '');
    if ($valor === '' || $valor === null) return $padrao;
    return $segredo ? mkt_decifrar($valor) : (string) $valor;
}

function mkt_config_set($chave, $valor, $segredo = false) {
    try {
        $valor = (string) $valor;
        if ($segredo && $valor !== '') {
            $valor = mkt_cifrar($valor);
        }
        return salvarConfigGlobal($chave, $valor);
    } catch (Throwable $e) {
        error_log('[Marketing] config_set ' . $chave . ': ' . $e->getMessage());
        return false;
    }
}

// Mostra apenas os ultimos 4 caracteres de um segredo salvo.
function mkt_mascarar($valor) {
    $valor = (string) $valor;
    $tam = strlen($valor);
    if ($tam === 0) return '';
    if ($tam <= 8) return str_repeat('*', $tam);
    return str_repeat('*', 8) . substr($valor, -4);
}

// =====================================================
// CSRF (formularios e chamadas AJAX do painel)
// =====================================================

function mkt_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['mkt_csrf'])) {
        $_SESSION['mkt_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['mkt_csrf'];
}

function mkt_csrf_validar($token) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $sessao = (string) ($_SESSION['mkt_csrf'] ?? '');
    $token = (string) $token;
    return $sessao !== '' && $token !== '' && hash_equals($sessao, $token);
}

// =====================================================
// Normalizacao / validacao de contatos
// =====================================================

// Retorna o telefone no formato canonico 55DDDNUMERO (somente digitos) ou ''.
function mkt_normalizar_whatsapp($valor) {
    $d = preg_replace('/\D+/', '', (string) $valor);
    if ($d === '') return '';

    if (strlen($d) > 13) {
        // Remove prefixos internacionais alem do 55 quando presentes.
        $pos = strpos($d, '55');
        if ($pos !== false && strlen($d) - $pos >= 12) {
            $d = substr($d, $pos);
        }
    }

    // Prefixo tronco nacional (011, 021 etc.).
    $d = ltrim($d, '0');
    if ($d === '') return '';

    if (strlen($d) === 10 || strlen($d) === 11) {
        $ddd = (int) substr($d, 0, 2);
        if ($ddd < 11 || $ddd > 99) return '';
        $numero = substr($d, 2);
        if (!preg_match('/^[2-9]\d{7,8}$/', $numero)) return '';
        return '55' . $d;
    }

    if (strlen($d) === 12 || strlen($d) === 13) {
        if (substr($d, 0, 2) !== '55') return '';
        $ddd = (int) substr($d, 2, 2);
        if ($ddd < 11 || $ddd > 99) return '';
        $numero = substr($d, 4);
        if (!preg_match('/^[2-9]\d{7,8}$/', $numero)) return '';
        return $d;
    }

    return '';
}

function mkt_formatar_whatsapp($norm) {
    $d = preg_replace('/\D+/', '', (string) $norm);
    if (strlen($d) === 13 && substr($d, 0, 2) === '55') {
        return '+55 (' . substr($d, 2, 2) . ') ' . substr($d, 4, 5) . '-' . substr($d, 9, 4);
    }
    if (strlen($d) === 12 && substr($d, 0, 2) === '55') {
        return '+55 (' . substr($d, 2, 2) . ') ' . substr($d, 4, 4) . '-' . substr($d, 8, 4);
    }
    return (string) $norm;
}

function mkt_normalizar_email($valor) {
    $email = strtolower(trim((string) $valor));
    $email = preg_replace('/^mailto:/i', '', $email);
    $email = trim($email, " \t\n\r\0\x0B.,;:<>()[]\"'");
    if ($email === '' || strlen($email) > 180) return '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    if (preg_match('/\.(png|jpg|jpeg|gif|webp|svg|css|js)$/i', $email)) return '';
    return $email;
}

function mkt_estado_valido($uf) {
    $uf = strtoupper(trim((string) $uf));
    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    return in_array($uf, $ufs, true) ? $uf : '';
}

// =====================================================
// HTTP generico (cURL) com protecao de SSRF para leitura de sites
// =====================================================

function mkt_url_publica($url) {
    $partes = parse_url((string) $url);
    if (!$partes || empty($partes['scheme']) || empty($partes['host'])) return false;
    $scheme = strtolower($partes['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return false;

    $host = strtolower($partes['host']);
    if (in_array($host, ['localhost', 'localhost.localdomain', '127.0.0.1', '::1'], true)) return false;

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $ipv4 = @gethostbyname($host);
        if ($ipv4 && $ipv4 !== $host) $ips[] = $ipv4;
        $ipv6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($ipv6)) {
            foreach ($ipv6 as $reg) {
                if (!empty($reg['ipv6'])) $ips[] = $reg['ipv6'];
            }
        }
    }
    if (!$ips) return false;

    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

// Executa uma requisicao HTTP. Retorna [ok, http, body, error].
function mkt_curl($metodo, $url, $headers = [], $corpo = null, $timeout = 20, $maxBytes = 0) {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http' => 0, 'body' => '', 'error' => 'Extensao cURL indisponivel.'];
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($metodo),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => (int) $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'CentralDeFaturas-Marketing/1.0',
    ];
    if ($corpo !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_string($corpo) ? $corpo : json_encode($corpo, JSON_UNESCAPED_UNICODE);
    }
    if ($maxBytes > 0) {
        $opts[CURLOPT_NOPROGRESS] = false;
        $opts[CURLOPT_PROGRESSFUNCTION] = function ($res, $down, $up, $total) use ($maxBytes) {
            return $down > $maxBytes ? 1 : 0;
        };
    }
    curl_setopt_array($ch, $opts);
    $retorno = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($retorno === false) {
        return ['ok' => false, 'http' => $http, 'body' => '', 'error' => $erro ?: 'Falha de conexao.'];
    }
    return ['ok' => $http >= 200 && $http < 300, 'http' => $http, 'body' => (string) $retorno, 'error' => ''];
}

function mkt_json($corpo) {
    $d = json_decode((string) $corpo, true);
    return is_array($d) ? $d : [];
}

// Le uma pagina publica e devolve texto limpo (sem scripts/estilos/tags).
function mkt_ler_pagina($url, $limite = 120000) {
    if (!mkt_url_publica($url)) {
        return ['ok' => false, 'texto' => '', 'error' => 'URL nao permitida.'];
    }
    $r = mkt_curl('GET', $url, ['Accept: text/html,application/xhtml+xml'], null, 15, 400000);
    if (!$r['ok'] || $r['body'] === '') {
        return ['ok' => false, 'texto' => '', 'error' => $r['error'] ?: ('HTTP ' . $r['http'])];
    }
    $html = $r['body'];
    $html = preg_replace('#<(script|style|noscript|svg|iframe)\b[^>]*>.*?</\1>#is', ' ', $html);
    $html = preg_replace('#<!--.*?-->#s', ' ', $html);
    $texto = strip_tags($html);
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/[ \t\x{00A0}]+/u', ' ', $texto);
    $texto = preg_replace('/\s*\n\s*/', "\n", $texto);
    $texto = trim($texto);
    if (function_exists('mb_substr')) {
        $texto = mb_substr($texto, 0, $limite, 'UTF-8');
    } else {
        $texto = substr($texto, 0, $limite);
    }
    return ['ok' => true, 'texto' => $texto, 'error' => ''];
}

// =====================================================
// Evolution API
// =====================================================

function mkt_evolution_config() {
    return [
        'url'      => rtrim(trim((string) mkt_config_get('mkt_evolution_url', '')), '/'),
        'key'      => trim((string) mkt_config_get('mkt_evolution_key', '', true)),
        'instance' => trim((string) mkt_config_get('mkt_evolution_instance', '')),
    ];
}

function mkt_evolution_configurada($cfg = null) {
    if ($cfg === null) $cfg = mkt_evolution_config();
    return $cfg['url'] !== '' && $cfg['key'] !== '' && $cfg['instance'] !== '';
}

function mkt_evolution_request($cfg, $metodo, $caminho, $corpo = null, $timeout = 25) {
    if (!mkt_evolution_configurada($cfg)) {
        return ['ok' => false, 'http' => 0, 'data' => [], 'raw' => '', 'error' => 'API Evolution nao configurada (URL, API Key e instancia).'];
    }
    $url = rtrim($cfg['url'], '/') . '/' . ltrim($caminho, '/');
    $headers = ['apikey: ' . $cfg['key'], 'Content-Type: application/json', 'Accept: application/json'];
    $r = mkt_curl($metodo, $url, $headers, $corpo, $timeout);
    $data = mkt_json($r['body']);
    $erro = $r['error'];
    if ($erro === '' && !$r['ok']) {
        $erro = (string) ($data['message'] ?? $data['error'] ?? $data['response']['message'] ?? ('HTTP ' . $r['http']));
        if (is_array($erro)) $erro = json_encode($erro, JSON_UNESCAPED_UNICODE);
    }
    return ['ok' => $r['ok'], 'http' => $r['http'], 'data' => $data, 'raw' => $r['body'], 'error' => (string) $erro];
}

// Consulta o estado da instancia. Estados: conectado, conectando, desconectado, erro.
function mkt_evolution_status($cfg = null) {
    if ($cfg === null) $cfg = mkt_evolution_config();
    $resultado = [
        'configurada' => mkt_evolution_configurada($cfg),
        'estado'      => 'desconectado',
        'telefone'    => '',
        'telefone_formatado' => '',
        'perfil'      => '',
        'mensagem'    => '',
        'http'        => 0,
        'raw'         => '',
    ];
    if (!$resultado['configurada']) {
        $resultado['estado'] = 'desconectado';
        $resultado['mensagem'] = 'Configure URL, API Key e instancia para conectar.';
        return $resultado;
    }

    $r = mkt_evolution_request($cfg, 'GET', '/instance/connectionState/' . rawurlencode($cfg['instance']));
    $resultado['http'] = $r['http'];
    $resultado['raw'] = $r['raw'];

    if ($r['http'] === 404) {
        $resultado['estado'] = 'desconectado';
        $resultado['mensagem'] = 'Instancia ainda nao existe na Evolution.';
        return $resultado;
    }
    if (!$r['ok']) {
        $resultado['estado'] = 'erro';
        $resultado['mensagem'] = $r['error'] ?: 'Falha ao consultar a Evolution.';
        return $resultado;
    }

    $state = (string) ($r['data']['instance']['state'] ?? $r['data']['state'] ?? '');
    $resultado['telefone'] = (string) ($r['data']['instance']['owner'] ?? $r['data']['owner'] ?? '');
    $resultado['perfil'] = (string) ($r['data']['instance']['profileName'] ?? $r['data']['profileName'] ?? '');

    if ($state === 'open') {
        $resultado['estado'] = 'conectado';
        $resultado['mensagem'] = 'WhatsApp conectado.';
    } elseif (in_array($state, ['connecting', 'qrcode', 'pairing'], true)) {
        $resultado['estado'] = 'conectando';
        $resultado['mensagem'] = 'Conexao em andamento.';
    } else {
        $resultado['estado'] = 'desconectado';
        $resultado['mensagem'] = $state !== '' ? ('Estado informado pela Evolution: ' . $state) : 'Instancia desconectada.';
    }

    // Identificacao do numero: fetchInstances traz o owner da instancia.
    if ($resultado['telefone'] === '') {
        $lista = mkt_evolution_request($cfg, 'GET', '/instance/fetchInstances');
        if ($lista['ok'] && is_array($lista['data'])) {
            foreach ($lista['data'] as $inst) {
                $nome = (string) ($inst['name'] ?? $inst['instanceName'] ?? $inst['instance']['instanceName'] ?? '');
                if ($nome === $cfg['instance']) {
                    $resultado['telefone'] = (string) ($inst['owner'] ?? $inst['instance']['owner'] ?? '');
                    $resultado['perfil'] = (string) ($inst['profileName'] ?? $inst['instance']['profileName'] ?? $resultado['perfil']);
                    break;
                }
            }
        }
    }
    if ($resultado['telefone'] !== '') {
        $resultado['telefone_formatado'] = mkt_formatar_whatsapp($resultado['telefone']);
    }
    return $resultado;
}

function mkt_evolution_criar($cfg) {
    $r = mkt_evolution_request($cfg, 'POST', '/instance/create', [
        'instanceName' => $cfg['instance'],
        'integration'  => 'WHATSAPP-BAILEYS',
        'qrcode'       => true,
    ]);
    // 403/409 normalmente significa que a instancia ja existe.
    if (!$r['ok'] && !in_array($r['http'], [403, 409], true)) {
        return $r;
    }
    return ['ok' => true, 'http' => $r['http'], 'data' => $r['data'], 'raw' => $r['raw'], 'error' => ''];
}

// Solicita o QR Code. Retorna [ok, base64, code, error, raw].
function mkt_evolution_qrcode($cfg) {
    $r = mkt_evolution_request($cfg, 'GET', '/instance/connect/' . rawurlencode($cfg['instance']), null, 30);
    $base64 = '';
    if ($r['ok'] || $r['http'] === 200) {
        $base64 = (string) ($r['data']['base64'] ?? '');
        if ($base64 === '' && isset($r['data']['qrcode'])) {
            $q = $r['data']['qrcode'];
            $base64 = is_array($q) ? (string) ($q['base64'] ?? $q['code'] ?? '') : (string) $q;
        }
        $base64 = preg_replace('#^data:image/[a-z]+;base64,#i', '', $base64);
        // Alguns builds retornam apenas o "code" (texto do QR).
        if ($base64 === '') {
            $base64 = (string) ($r['data']['code'] ?? '');
        }
    }
    if ($base64 === '') {
        $r2 = mkt_evolution_request($cfg, 'GET', '/instance/qrcode/' . rawurlencode($cfg['instance']), null, 20);
        if ($r2['ok']) {
            $base64 = (string) ($r2['data']['base64'] ?? '');
            if ($base64 === '' && isset($r2['data']['qrcode'])) {
                $q = $r2['data']['qrcode'];
                $base64 = is_array($q) ? (string) ($q['base64'] ?? $q['code'] ?? '') : (string) $q;
            }
            $base64 = preg_replace('#^data:image/[a-z]+;base64,#i', '', $base64);
        }
        if ($base64 === '' && !$r['ok']) {
            return ['ok' => false, 'base64' => '', 'code' => '', 'error' => $r2['error'] ?: $r['error'], 'raw' => $r['raw']];
        }
    }
    $code = (string) ($r['data']['code'] ?? '');
    return ['ok' => $base64 !== '', 'base64' => $base64, 'code' => $code, 'error' => $base64 === '' ? 'A Evolution nao retornou QR Code.' : '', 'raw' => $r['raw']];
}

function mkt_evolution_logout($cfg) {
    return mkt_evolution_request($cfg, 'DELETE', '/instance/logout/' . rawurlencode($cfg['instance']));
}

// Envia texto. Retorna [ok, http, data, error].
function mkt_evolution_enviar($cfg, $numero, $texto) {
    $destino = mkt_normalizar_whatsapp($numero);
    if ($destino === '') {
        return ['ok' => false, 'http' => 0, 'data' => [], 'error' => 'Numero de WhatsApp invalido.'];
    }
    return mkt_evolution_request($cfg, 'POST', '/message/sendText/' . rawurlencode($cfg['instance']), [
        'number'  => $destino,
        'text'    => (string) $texto,
        'options' => ['delay' => 1200, 'presence' => 'composing', 'linkPreview' => false],
    ], 40);
}

// Envia uma imagem (com legenda opcional) pela Evolution v2.
// Retorna [ok, http, data, raw, error] igual aos demais envios.
function mkt_evolution_enviar_midia($cfg, $numero, $caminho, $mime, $nome, $legenda = '') {
    $destino = mkt_normalizar_whatsapp($numero);
    if ($destino === '') {
        return ['ok' => false, 'http' => 0, 'data' => [], 'raw' => '', 'error' => 'Numero de WhatsApp invalido.'];
    }
    if (!is_file($caminho)) {
        return ['ok' => false, 'http' => 0, 'data' => [], 'raw' => '', 'error' => 'Imagem nao encontrada no servidor.'];
    }
    $binario = @file_get_contents($caminho);
    if ($binario === false || $binario === '') {
        return ['ok' => false, 'http' => 0, 'data' => [], 'raw' => '', 'error' => 'Nao foi possivel ler a imagem.'];
    }
    $mime = trim((string) $mime);
    if (!isset(mkt_imagem_tipos()[$mime])) {
        return ['ok' => false, 'http' => 0, 'data' => [], 'raw' => '', 'error' => 'Tipo de imagem nao suportado.'];
    }

    return mkt_evolution_request($cfg, 'POST', '/message/sendMedia/' . rawurlencode($cfg['instance']), [
        'number'    => $destino,
        'mediatype' => 'image',
        'mimetype'  => $mime,
        'caption'   => (string) $legenda,
        'media'     => base64_encode($binario),
        'fileName'  => (string) $nome,
        'options'   => ['delay' => 1200, 'presence' => 'composing'],
    ], 60);
}

// =====================================================
// OpenAI + Google Programmable Search
// =====================================================

function mkt_openai_config() {
    return [
        'key'    => trim((string) mkt_config_get('mkt_openai_key', '', true)),
        'modelo' => trim((string) mkt_config_get('mkt_openai_modelo', 'gpt-4o-mini')) ?: 'gpt-4o-mini',
    ];
}

function mkt_google_config() {
    return [
        'key' => trim((string) mkt_config_get('mkt_google_key', '', true)),
        'cx'  => trim((string) mkt_config_get('mkt_google_cx', '')),
    ];
}

function mkt_openai_request($cfg, $metodo, $caminho, $corpo = null, $timeout = 60) {
    if ($cfg['key'] === '') {
        return ['ok' => false, 'http' => 0, 'data' => [], 'raw' => '', 'error' => 'API Key da OpenAI nao configurada.'];
    }
    $url = 'https://api.openai.com/v1/' . ltrim($caminho, '/');
    $headers = ['Authorization: Bearer ' . $cfg['key'], 'Content-Type: application/json'];
    $r = mkt_curl($metodo, $url, $headers, $corpo, $timeout);
    $data = mkt_json($r['body']);
    $erro = $r['error'];
    if ($erro === '' && !$r['ok']) {
        $erro = (string) ($data['error']['message'] ?? ('HTTP ' . $r['http']));
    }
    return ['ok' => $r['ok'], 'http' => $r['http'], 'data' => $data, 'raw' => $r['body'], 'error' => (string) $erro];
}

function mkt_openai_testar($cfg = null) {
    if ($cfg === null) $cfg = mkt_openai_config();
    if ($cfg['key'] === '') return ['ok' => false, 'mensagem' => 'Informe a API Key da OpenAI antes de testar.'];
    $r = mkt_openai_request($cfg, 'GET', 'models', null, 25);
    if (!$r['ok']) {
        return ['ok' => false, 'mensagem' => 'Falha na OpenAI: ' . ($r['error'] ?: ('HTTP ' . $r['http']))];
    }
    $ids = [];
    foreach (($r['data']['data'] ?? []) as $m) {
        if (!empty($m['id'])) $ids[] = (string) $m['id'];
    }
    $temModelo = in_array($cfg['modelo'], $ids, true);
    return [
        'ok' => true,
        'mensagem' => 'Conexao com a OpenAI OK.' . ($temModelo ? ' Modelo ' . $cfg['modelo'] . ' disponivel.' : ' Atencao: o modelo ' . $cfg['modelo'] . ' nao apareceu na lista da conta.'),
        'modelos' => array_slice($ids, 0, 30),
    ];
}

function mkt_google_testar($cfg = null) {
    if ($cfg === null) $cfg = mkt_google_config();
    if ($cfg['key'] === '' || $cfg['cx'] === '') {
        return ['ok' => false, 'mensagem' => 'Informe a API Key do Google e o ID do mecanismo de busca (CX).'];
    }
    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
        'key' => $cfg['key'],
        'cx'  => $cfg['cx'],
        'q'   => 'teste de conexao',
        'num' => 1,
    ]);
    $r = mkt_curl('GET', $url, ['Accept: application/json'], null, 25);
    $data = mkt_json($r['body']);
    if (!$r['ok']) {
        $msg = (string) ($data['error']['message'] ?? ('HTTP ' . $r['http']));
        return ['ok' => false, 'mensagem' => 'Falha no Google Programmable Search: ' . $msg];
    }
    $total = (int) ($data['searchInformation']['totalResults'] ?? 0);
    return ['ok' => true, 'mensagem' => 'Conexao com o Google Programmable Search OK. Resultados encontrados: ' . $total . '.'];
}

function mkt_google_buscar($cfg, $consulta, $num = 10) {
    $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
        'key' => $cfg['key'],
        'cx'  => $cfg['cx'],
        'q'   => $consulta,
        'num' => max(1, min(10, (int) $num)),
        'gl'  => 'br',
        'lr'  => 'lang_pt',
    ]);
    $r = mkt_curl('GET', $url, ['Accept: application/json'], null, 25);
    $data = mkt_json($r['body']);
    if (!$r['ok']) {
        return ['ok' => false, 'itens' => [], 'error' => (string) ($data['error']['message'] ?? ('HTTP ' . $r['http']))];
    }
    $itens = [];
    foreach (($data['items'] ?? []) as $item) {
        $link = (string) ($item['link'] ?? '');
        if ($link === '') continue;
        $itens[] = [
            'titulo'   => (string) ($item['title'] ?? ''),
            'link'     => $link,
            'resumo'   => (string) ($item['snippet'] ?? ''),
        ];
    }
    return ['ok' => true, 'itens' => $itens, 'error' => ''];
}

function mkt_openai_extrair($cfg, $nicho, $fontes) {
    $contexto = '';
    foreach ($fontes as $i => $f) {
        $n = $i + 1;
        $contexto .= "\n--- FONTE {$n} ---\n";
        $contexto .= 'TITULO: ' . ($f['titulo'] ?? '') . "\n";
        $contexto .= 'URL: ' . ($f['link'] ?? '') . "\n";
        $contexto .= 'RESUMO: ' . ($f['resumo'] ?? '') . "\n";
        $contexto .= 'TEXTO: ' . ($f['texto'] ?? '') . "\n";
    }

    $sistema = 'Voce e um assistente de prospeccao comercial B2B no Brasil. '
        . 'Sua tarefa e extrair empresas e contatos APENAS das fontes fornecidas. '
        . 'NUNCA invente, deduza, complete ou reformate numeros de telefone/e-mail que nao aparecam literalmente no texto. '
        . 'Se um campo nao estiver presente na fonte, devolva string vazia. '
        . 'Responda somente JSON valido no formato: '
        . '{"contatos":[{"empresa":"","nome_contato":"","cidade":"","estado":"","whatsapp":"","email":"","site":"","fonte":""}]}';

    $usuario = 'Nicho desejado: ' . $nicho['nome'] . "\n"
        . 'Descricao do segmento: ' . $nicho['descricao'] . "\n"
        . 'Cidade: ' . $nicho['cidade'] . ' - ' . $nicho['estado'] . "\n"
        . 'Regiao de abrangencia: ' . $nicho['regiao'] . "\n\n"
        . 'Extraia no maximo 40 contatos que atendam ao nicho e a localizacao. '
        . 'Para cada um, informe empresa, nome do contato (se houver), cidade, estado (UF), '
        . 'whatsapp (somente se o numero aparecer literalmente), e-mail (somente se aparecer literalmente), site e a URL da fonte. '
        . 'Nao inclua exemplos genericos, diretorios sem dados ou textos institucionais.' . "\n"
        . $contexto;

    $r = mkt_openai_request($cfg, 'POST', 'chat/completions', [
        'model' => $cfg['modelo'],
        'temperature' => 0,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $sistema],
            ['role' => 'user', 'content' => $usuario],
        ],
    ], 90);

    if (!$r['ok']) {
        return ['ok' => false, 'contatos' => [], 'error' => $r['error'] ?: ('HTTP ' . $r['http'])];
    }
    $conteudo = (string) ($r['data']['choices'][0]['message']['content'] ?? '');
    $json = mkt_json($conteudo);
    $lista = $json['contatos'] ?? [];
    if (!is_array($lista)) $lista = [];
    return ['ok' => true, 'contatos' => $lista, 'error' => ''];
}

// =====================================================
// Templates
// =====================================================

function mkt_template_padrao($canal) {
    if ($canal === 'email') {
        return [
            'canal' => 'email',
            'remetente_nome' => '',
            'remetente_email' => '',
            'assunto' => 'Uma oportunidade para {nome_empresa}',
            'conteudo' => "<p>Ola {nome_contato}, tudo bem?</p>\n"
                . "<p>Identificamos que a <strong>{nome_empresa}</strong> atua no segmento de {nicho} em {cidade}/{estado} e acreditamos que podemos ajudar com uma solucao que ja apoia empresas da mesma regiao.</p>\n"
                . "<p>Podemos conversar rapidamente esta semana?</p>\n"
                . "<p>Abraco,<br>Equipe Comercial</p>",
            'html' => 1,
        ];
    }
    return [
        'canal' => 'whatsapp',
        'remetente_nome' => '',
        'remetente_email' => '',
        'assunto' => '',
        'conteudo' => 'Ola {nome_contato}, tudo bem? Aqui e da equipe comercial. '
            . 'Vimos que a {nome_empresa} atua com {nicho} em {cidade}/{estado} e gostariamos de apresentar uma solucao que pode ajudar o seu negocio. Podemos conversar?',
        'html' => 0,
    ];
}

function mkt_template_obter($canal) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM marketing_templates WHERE canal = ? LIMIT 1");
    $stmt->execute([$canal]);
    $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tpl) return mkt_template_padrao($canal);
    $padrao = mkt_template_padrao($canal);
    foreach ($padrao as $k => $v) {
        if (!array_key_exists($k, $tpl) || $tpl[$k] === null) $tpl[$k] = $v;
    }
    return $tpl;
}

function mkt_template_salvar($canal, $dados) {
    if (!in_array($canal, ['whatsapp', 'email'], true)) return false;
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id FROM marketing_templates WHERE canal = ? LIMIT 1");
    $stmt->execute([$canal]);
    $id = $stmt->fetchColumn();

    $valores = [
        'remetente_nome'  => mb_substr(trim((string) ($dados['remetente_nome'] ?? '')), 0, 120, 'UTF-8'),
        'remetente_email' => mkt_normalizar_email($dados['remetente_email'] ?? ''),
        'assunto'         => mb_substr(trim((string) ($dados['assunto'] ?? '')), 0, 200, 'UTF-8'),
        'conteudo'        => (string) ($dados['conteudo'] ?? ''),
        'html'            => $canal === 'email' ? 1 : 0,
    ];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE marketing_templates SET remetente_nome=?, remetente_email=?, assunto=?, conteudo=?, html=? WHERE id=?");
        return $stmt->execute([$valores['remetente_nome'], $valores['remetente_email'], $valores['assunto'], $valores['conteudo'], $valores['html'], $id]);
    }
    $stmt = $pdo->prepare("INSERT INTO marketing_templates (canal, remetente_nome, remetente_email, assunto, conteudo, html) VALUES (?,?,?,?,?,?)");
    return $stmt->execute([$canal, $valores['remetente_nome'], $valores['remetente_email'], $valores['assunto'], $valores['conteudo'], $valores['html']]);
}

function mkt_template_render($texto, $contato) {
    $nomeContato = trim((string) ($contato['nome_contato'] ?? ''));
    if ($nomeContato === '') {
        // Sem nome do contato: usa uma saudacao neutra em vez de "Ola ,".
        $nomeContato = 'equipe';
    }
    $mapa = [
        '{nome_empresa}' => (string) ($contato['empresa'] ?? ''),
        '{nome_contato}' => $nomeContato,
        '{nicho}'        => (string) ($contato['nicho'] ?? ''),
        '{cidade}'       => (string) ($contato['cidade'] ?? ''),
        '{estado}'       => (string) ($contato['estado'] ?? ''),
    ];
    return str_replace(array_keys($mapa), array_values($mapa), (string) $texto);
}

// =====================================================
// Imagens dos templates (corpo do e-mail ou anexo / WhatsApp)
// =====================================================

function mkt_upload_dir() {
    $dir = __DIR__ . '/marketing_uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    // Protecao extra caso o diretorio seja recriado em outra instalacao.
    $ht = $dir . '/.htaccess';
    if (is_dir($dir) && !is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n", LOCK_EX);
    }
    return $dir;
}

function mkt_imagem_tipos() {
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
}

function mkt_arquivo_caminho($arquivo) {
    $nome = basename((string) $arquivo);
    if ($nome === '' || $nome !== (string) $arquivo || !preg_match('/^mkt_[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $nome)) {
        return '';
    }
    $caminho = mkt_upload_dir() . DIRECTORY_SEPARATOR . $nome;
    return is_file($caminho) ? $caminho : '';
}

function mkt_arquivo_url($id) {
    return 'api/marketing_arquivo.php?id=' . (int) $id;
}

function mkt_arquivo_listar($canal) {
    if (!in_array($canal, ['whatsapp', 'email'], true)) return [];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, canal, nome_original, arquivo, mime, tamanho, modo, criado_em
            FROM marketing_template_arquivos WHERE canal = ? ORDER BY id ASC");
        $stmt->execute([$canal]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[Marketing] listar imagens: ' . $e->getMessage());
        return [];
    }
}

function mkt_arquivo_obter($id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM marketing_template_arquivos WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

// Valida e grava uma imagem enviada pelo Super Admin. Retorna
// [ok, mensagem, arquivo(array)].
function mkt_arquivo_salvar_upload($canal, $file, $modo = 'inline') {
    if (!in_array($canal, ['whatsapp', 'email'], true)) {
        return ['ok' => false, 'mensagem' => 'Canal invalido.', 'arquivo' => null];
    }
    if ($canal === 'whatsapp') $modo = 'inline';
    if (!in_array($modo, ['inline', 'anexo'], true)) $modo = 'inline';

    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $codigo = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $motivos = [
            UPLOAD_ERR_INI_SIZE   => 'A imagem excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'A imagem excede o limite permitido.',
            UPLOAD_ERR_PARTIAL    => 'O envio da imagem foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE    => 'Selecione uma imagem para enviar.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario do servidor indisponivel.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu gravar a imagem.',
            UPLOAD_ERR_EXTENSION  => 'O envio da imagem foi bloqueado pelo servidor.',
        ];
        return ['ok' => false, 'mensagem' => $motivos[$codigo] ?? 'Falha no envio da imagem.', 'arquivo' => null];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return ['ok' => false, 'mensagem' => 'Arquivo temporario da imagem nao encontrado.', 'arquivo' => null];
    }
    $tamanho = (int) (filesize($tmp) ?: 0);
    if ($tamanho <= 0) {
        return ['ok' => false, 'mensagem' => 'A imagem enviada esta vazia.', 'arquivo' => null];
    }
    if ($tamanho > MKT_MAX_IMAGEM_BYTES) {
        return ['ok' => false, 'mensagem' => 'A imagem excede o limite de 5 MB.', 'arquivo' => null];
    }
    if (count(mkt_arquivo_listar($canal)) >= MKT_MAX_IMAGENS_TEMPLATE) {
        return ['ok' => false, 'mensagem' => 'Limite de ' . MKT_MAX_IMAGENS_TEMPLATE . ' imagens por template atingido.', 'arquivo' => null];
    }

    // Tipo real do arquivo (nao confia na extensao nem no Content-Type do browser).
    $info = @getimagesize($tmp);
    $tipos = mkt_imagem_tipos();
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset($tipos[$mime])) {
        return ['ok' => false, 'mensagem' => 'Formato invalido. Envie uma imagem JPG, PNG, GIF ou WEBP.', 'arquivo' => null];
    }
    if ((int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
        return ['ok' => false, 'mensagem' => 'Nao foi possivel ler as dimensoes da imagem.', 'arquivo' => null];
    }

    $nomeOriginal = trim((string) ($file['name'] ?? 'imagem'));
    $nomeOriginal = preg_replace('/[\x00-\x1F\x7F]+/u', '', $nomeOriginal);
    $nomeOriginal = mb_substr($nomeOriginal !== '' ? $nomeOriginal : 'imagem', 0, 180, 'UTF-8');
    // Somente o nome, sem caminho.
    $nomeOriginal = basename(str_replace('\\', '/', $nomeOriginal));

    $dir = mkt_upload_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'mensagem' => 'Diretorio de imagens sem permissao de escrita.', 'arquivo' => null];
    }
    $ext = $tipos[$mime];
    $arquivo = 'mkt_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $destino = $dir . DIRECTORY_SEPARATOR . $arquivo;
    $movido = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $destino) : @rename($tmp, $destino);
    if (!$movido || !is_file($destino)) {
        return ['ok' => false, 'mensagem' => 'Nao foi possivel gravar a imagem no servidor.', 'arquivo' => null];
    }
    @chmod($destino, 0640);

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO marketing_template_arquivos (canal, nome_original, arquivo, mime, tamanho, modo)
            VALUES (?,?,?,?,?,?)");
        $stmt->execute([$canal, $nomeOriginal, $arquivo, $mime, $tamanho, $modo]);
        $id = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        @unlink($destino);
        error_log('[Marketing] gravar imagem: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Nao foi possivel registrar a imagem no banco.', 'arquivo' => null];
    }

    return ['ok' => true, 'mensagem' => 'Imagem enviada.', 'arquivo' => mkt_arquivo_obter($id)];
}

function mkt_arquivo_atualizar_modo($id, $modo) {
    if (!in_array($modo, ['inline', 'anexo'], true)) return false;
    $row = mkt_arquivo_obter($id);
    if (!$row || ($row['canal'] ?? '') !== 'email') return false;
    try {
        $pdo = getConnection();
        $pdo->prepare("UPDATE marketing_template_arquivos SET modo = ? WHERE id = ? AND canal = 'email'")
            ->execute([$modo, (int) $id]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function mkt_arquivo_excluir($id) {
    $row = mkt_arquivo_obter($id);
    if (!$row) return false;
    $caminho = mkt_arquivo_caminho($row['arquivo']);
    try {
        $pdo = getConnection();
        $pdo->prepare("DELETE FROM marketing_template_arquivos WHERE id = ?")->execute([(int) $id]);
    } catch (Throwable $e) {
        return false;
    }
    if ($caminho !== '' && is_file($caminho)) @unlink($caminho);
    return true;
}

// Aplica as imagens no HTML do e-mail:
// - {{imagem:ID}} vira <img src="cid:..."> (somente imagens "inline");
// - placeholders invalidos/orfaos sao removidos;
// - imagens inline sem placeholder entram no fim do corpo (nunca somem em silencio).
// Retorna [html, inline, anexos, usados].
function mkt_email_aplicar_imagens($html, $imagens) {
    $html = (string) $html;
    $inline = [];
    $anexos = [];
    foreach ((array) $imagens as $img) {
        $caminho = mkt_arquivo_caminho($img['arquivo'] ?? '');
        if ($caminho === '') continue;
        $item = $img + ['caminho' => $caminho, 'cid' => 'mkt-img-' . (int) $img['id']];
        if (($img['modo'] ?? 'inline') === 'anexo') $anexos[] = $item;
        else $inline[] = $item;
    }

    $usados = [];
    foreach ($inline as $img) {
        $placeholder = '{{imagem:' . (int) $img['id'] . '}}';
        $tag = '<img src="cid:' . htmlspecialchars($img['cid'], ENT_QUOTES, 'UTF-8') . '" alt="'
            . htmlspecialchars((string) $img['nome_original'], ENT_QUOTES, 'UTF-8')
            . '" style="max-width:100%;height:auto;display:block;margin:16px auto;border:0;outline:none;text-decoration:none;">';
        $novo = str_replace($placeholder, $tag, $html, $count);
        if ($count > 0) {
            $html = $novo;
            $usados[(int) $img['id']] = true;
        }
    }
    // Remove qualquer placeholder restante (imagem excluida, anexo ou ID invalido).
    $html = preg_replace('/\{\{imagem:\d+\}\}/', '', $html);

    $extras = '';
    foreach ($inline as $img) {
        if (!empty($usados[(int) $img['id']])) continue;
        $extras .= '<img src="cid:' . htmlspecialchars($img['cid'], ENT_QUOTES, 'UTF-8') . '" alt="'
            . htmlspecialchars((string) $img['nome_original'], ENT_QUOTES, 'UTF-8')
            . '" style="max-width:100%;height:auto;display:block;margin:16px auto;border:0;outline:none;text-decoration:none;">';
    }
    if ($extras !== '') {
        $html .= '<div style="margin-top:16px;text-align:center;">' . $extras . '</div>';
    }
    return ['html' => $html, 'inline' => $inline, 'anexos' => $anexos, 'usados' => $usados];
}

// Remove os placeholders de imagem do texto (usado na legenda do WhatsApp e no
// texto puro do e-mail).
function mkt_texto_sem_placeholders_imagem($texto) {
    return preg_replace('/\{\{imagem:\d+\}\}/', '', (string) $texto);
}

// =====================================================
// Contatos: deduplicacao, armazenamento e descartes
// =====================================================

function mkt_registrar_descarte($dados, $motivo, $detalhe = '') {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO marketing_descartes (empresa, whatsapp, email, nicho, motivo, detalhe, origem) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            mb_substr((string) ($dados['empresa'] ?? ''), 0, 200, 'UTF-8'),
            mb_substr((string) ($dados['whatsapp'] ?? ''), 0, 30, 'UTF-8'),
            mb_substr((string) ($dados['email'] ?? ''), 0, 180, 'UTF-8'),
            mb_substr((string) ($dados['nicho'] ?? ''), 0, 120, 'UTF-8'),
            mb_substr((string) $motivo, 0, 60, 'UTF-8'),
            mb_substr((string) $detalhe, 0, 255, 'UTF-8'),
            mb_substr((string) ($dados['origem'] ?? ''), 0, 500, 'UTF-8'),
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('[Marketing] descarte: ' . $e->getMessage());
        return false;
    }
}

// Insere um contato apos normalizar e checar duplicidade. Retorna [ok, id, motivo, detalhe].
function mkt_contato_inserir($dados) {
    $pdo = getConnection();
    $empresa = trim((string) ($dados['empresa'] ?? ''));
    $whatsappNorm = mkt_normalizar_whatsapp($dados['whatsapp'] ?? '');
    $emailNorm = mkt_normalizar_email($dados['email'] ?? '');

    if ($empresa === '' || ($whatsappNorm === '' && $emailNorm === '')) {
        mkt_registrar_descarte($dados, 'invalido', 'Empresa ou canal de contato ausente/invalido.');
        return ['ok' => false, 'id' => 0, 'motivo' => 'invalido', 'detalhe' => 'Sem empresa ou canal valido.'];
    }

    // Duplicidade em PHP (mensagem clara) antes da barreira UNIQUE do banco.
    $condicoes = [];
    $params = [];
    if ($whatsappNorm !== '') {
        $condicoes[] = 'whatsapp_norm = ?';
        $params[] = $whatsappNorm;
    }
    if ($emailNorm !== '') {
        $condicoes[] = 'email_norm = ?';
        $params[] = $emailNorm;
    }
    $stmt = $pdo->prepare("SELECT id, whatsapp_norm, email_norm FROM marketing_contatos WHERE " . implode(' OR ', $condicoes) . " LIMIT 1");
    $stmt->execute($params);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existente) {
        $canal = ($whatsappNorm !== '' && $existente['whatsapp_norm'] === $whatsappNorm) ? 'WhatsApp' : 'e-mail';
        mkt_registrar_descarte($dados, 'duplicado', $canal . ' ja cadastrado no contato #' . $existente['id'] . '.');
        return ['ok' => false, 'id' => (int) $existente['id'], 'motivo' => 'duplicado', 'detalhe' => $canal . ' ja cadastrado.'];
    }

    $nicho = trim((string) ($dados['nicho'] ?? ''));
    $estado = mkt_estado_valido($dados['estado'] ?? '');
    $dataColeta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($dados['data_coleta'] ?? '')) ? $dados['data_coleta'] : date('Y-m-d');

    try {
        $stmt = $pdo->prepare("INSERT INTO marketing_contatos
            (empresa, nome_contato, nicho, descricao_nicho, cidade, estado, regiao, whatsapp, whatsapp_norm, email, email_norm,
             site, origem, origem_tipo, base_legal, opt_out, status, observacoes, data_coleta)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?)");
        $stmt->execute([
            mb_substr($empresa, 0, 200, 'UTF-8'),
            mb_substr(trim((string) ($dados['nome_contato'] ?? '')), 0, 160, 'UTF-8'),
            mb_substr($nicho, 0, 120, 'UTF-8'),
            mb_substr((string) ($dados['descricao_nicho'] ?? ''), 0, 255, 'UTF-8'),
            mb_substr((string) ($dados['cidade'] ?? ''), 0, 120, 'UTF-8'),
            $estado !== '' ? $estado : null,
            mb_substr((string) ($dados['regiao'] ?? ''), 0, 120, 'UTF-8'),
            $whatsappNorm !== '' ? mkt_formatar_whatsapp($whatsappNorm) : null,
            $whatsappNorm !== '' ? $whatsappNorm : null,
            $emailNorm !== '' ? $emailNorm : null,
            $emailNorm !== '' ? $emailNorm : null,
            mb_substr((string) ($dados['site'] ?? ''), 0, 255, 'UTF-8'),
            mb_substr((string) ($dados['origem'] ?? ''), 0, 500, 'UTF-8'),
            mb_substr((string) ($dados['origem_tipo'] ?? 'busca_ia'), 0, 30, 'UTF-8'),
            mb_substr((string) ($dados['base_legal'] ?? 'legitimo_interesse'), 0, 60, 'UTF-8'),
            'coletado',
            mb_substr((string) ($dados['observacoes'] ?? ''), 0, 2000, 'UTF-8'),
            $dataColeta,
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'motivo' => '', 'detalhe' => ''];
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            mkt_registrar_descarte($dados, 'duplicado', 'Bloqueado pela restricao UNIQUE do banco.');
            return ['ok' => false, 'id' => 0, 'motivo' => 'duplicado', 'detalhe' => 'Contato ja existente.'];
        }
        mkt_registrar_descarte($dados, 'erro_banco', mb_substr($e->getMessage(), 0, 250, 'UTF-8'));
        return ['ok' => false, 'id' => 0, 'motivo' => 'erro_banco', 'detalhe' => $e->getMessage()];
    }
}

function mkt_nicho_config() {
    return [
        'nome'        => trim((string) mkt_config_get('mkt_nicho_nome', '')),
        'descricao'   => trim((string) mkt_config_get('mkt_nicho_descricao', '')),
        'cidade'      => trim((string) mkt_config_get('mkt_nicho_cidade', '')),
        'estado'      => mkt_estado_valido(mkt_config_get('mkt_nicho_estado', '')),
        'regiao'      => trim((string) mkt_config_get('mkt_nicho_regiao', '')),
        'limite_wa'   => max(1, min(MKT_MAX_WHATSAPP_DIA, (int) mkt_config_get('mkt_limite_whatsapp', MKT_MAX_WHATSAPP_DIA))),
        'limite_email'=> max(1, min(MKT_MAX_EMAIL_DIA, (int) mkt_config_get('mkt_limite_email', MKT_MAX_EMAIL_DIA))),
    ];
}

function mkt_hoje_contatos_por_canal() {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT
        SUM(CASE WHEN whatsapp_norm IS NOT NULL AND whatsapp_norm <> '' THEN 1 ELSE 0 END) AS wa,
        SUM(CASE WHEN email_norm IS NOT NULL AND email_norm <> '' THEN 1 ELSE 0 END) AS em
        FROM marketing_contatos WHERE data_coleta = CURDATE()");
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['wa' => 0, 'em' => 0];
    return ['whatsapp' => (int) $row['wa'], 'email' => (int) $row['em']];
}

// Verifica se o telefone/e-mail aparece literalmente nas fontes consultadas.
function mkt_valor_na_fonte($valor, $texto, $tipo) {
    if ($tipo === 'whatsapp') {
        $digitosFonte = preg_replace('/\D+/', '', $texto);
        $norm = mkt_normalizar_whatsapp($valor);
        if ($norm === '') return false;
        $semPais = substr($norm, 2);
        return strpos($digitosFonte, $norm) !== false || strpos($digitosFonte, $semPais) !== false;
    }
    $norm = mkt_normalizar_email($valor);
    if ($norm === '') return false;
    return strpos(mb_strtolower($texto, 'UTF-8'), $norm) !== false;
}

// =====================================================
// Pesquisa publica (Google + leitura de paginas + OpenAI)
// =====================================================

function mkt_pesquisar_contatos(&$stats = null) {
    if ($stats === null) $stats = [];
    $stats += ['whatsapp_novos' => 0, 'emails_novos' => 0, 'duplicados' => 0, 'invalidos' => 0, 'fontes' => 0];

    $pdo = getConnection();
    $nicho = mkt_nicho_config();
    if ($nicho['nome'] === '' || $nicho['cidade'] === '' || $nicho['estado'] === '') {
        return ['ok' => false, 'mensagem' => 'Configure nome do nicho, cidade e estado antes de pesquisar.', 'stats' => $stats];
    }

    $openai = mkt_openai_config();
    $google = mkt_google_config();
    if ($openai['key'] === '' || $google['key'] === '' || $google['cx'] === '') {
        return [
            'ok' => false,
            'mensagem' => 'Pesquisa pendente de credenciais: configure a API Key da OpenAI e a API Key/CX do Google Programmable Search na aba Config. ChatGPT.',
            'stats' => $stats,
        ];
    }

    $coletadosHoje = mkt_hoje_contatos_por_canal();
    $faltaWa = max(0, $nicho['limite_wa'] - $coletadosHoje['whatsapp']);
    $faltaEmail = max(0, $nicho['limite_email'] - $coletadosHoje['email']);
    if ($faltaWa <= 0 && $faltaEmail <= 0) {
        return ['ok' => true, 'mensagem' => 'Limite diario de contatos ja atingido (WhatsApp e e-mail).', 'stats' => $stats];
    }

    $local = trim($nicho['cidade'] . ' ' . $nicho['estado']);
    $consultas = [
        '"' . $nicho['nome'] . '" "' . $nicho['cidade'] . '" whatsapp contato',
        '"' . $nicho['nome'] . '" "' . $nicho['cidade'] . '" email contato',
        '"' . $nicho['nome'] . '" ' . $local . ' site oficial',
    ];
    if ($nicho['regiao'] !== '') {
        $consultas[] = '"' . $nicho['nome'] . '" "' . $nicho['regiao'] . '" contato whatsapp';
    }

    $resultados = [];
    $vistos = [];
    foreach ($consultas as $consulta) {
        $busca = mkt_google_buscar($google, $consulta, 10);
        if (!$busca['ok']) {
            return ['ok' => false, 'mensagem' => 'Falha na pesquisa Google: ' . $busca['error'], 'stats' => $stats];
        }
        foreach ($busca['itens'] as $item) {
            if (isset($vistos[$item['link']])) continue;
            $vistos[$item['link']] = true;
            $resultados[] = $item;
        }
        if (count($resultados) >= 24) break;
    }
    if (!$resultados) {
        return ['ok' => true, 'mensagem' => 'Nenhum resultado publico encontrado para o nicho configurado.', 'stats' => $stats];
    }

    // Le as primeiras paginas publicas (respeitando SSRF e volume).
    $fontes = [];
    $lidas = 0;
    foreach ($resultados as $item) {
        if ($lidas >= 6) break;
        $leitura = mkt_ler_pagina($item['link']);
        if (!$leitura['ok'] || strlen($leitura['texto']) < 120) {
            $fontes[] = $item + ['texto' => mb_substr($item['resumo'], 0, 1500, 'UTF-8')];
            continue;
        }
        $fontes[] = $item + ['texto' => mb_substr($leitura['texto'], 0, 7000, 'UTF-8')];
        $lidas++;
    }
    $stats['fontes'] = count($fontes);

    $extracao = mkt_openai_extrair($openai, $nicho, $fontes);
    if (!$extracao['ok']) {
        return ['ok' => false, 'mensagem' => 'Falha na analise da OpenAI: ' . $extracao['error'], 'stats' => $stats];
    }

    $textoFontes = '';
    foreach ($fontes as $f) {
        $textoFontes .= "\n" . ($f['titulo'] ?? '') . "\n" . ($f['link'] ?? '') . "\n" . ($f['resumo'] ?? '') . "\n" . ($f['texto'] ?? '') . "\n";
    }

    foreach ($extracao['contatos'] as $c) {
        if ($faltaWa <= 0 && $faltaEmail <= 0) break;
        $empresa = trim((string) ($c['empresa'] ?? ''));
        $whats = trim((string) ($c['whatsapp'] ?? ''));
        $email = trim((string) ($c['email'] ?? ''));
        if ($empresa === '') continue;

        $waNorm = mkt_normalizar_whatsapp($whats);
        $emNorm = mkt_normalizar_email($email);

        // Nao confia na IA: o valor precisa existir literalmente nas fontes.
        if ($waNorm !== '' && !mkt_valor_na_fonte($waNorm, $textoFontes, 'whatsapp')) {
            mkt_registrar_descarte(['empresa' => $empresa, 'whatsapp' => $whats, 'nicho' => $nicho['nome'], 'origem' => $c['fonte'] ?? ''], 'nao_confere_fonte', 'WhatsApp nao aparece literalmente nas fontes.');
            $stats['invalidos']++;
            $waNorm = '';
        }
        if ($emNorm !== '' && !mkt_valor_na_fonte($emNorm, $textoFontes, 'email')) {
            mkt_registrar_descarte(['empresa' => $empresa, 'email' => $email, 'nicho' => $nicho['nome'], 'origem' => $c['fonte'] ?? ''], 'nao_confere_fonte', 'E-mail nao aparece literalmente nas fontes.');
            $stats['invalidos']++;
            $emNorm = '';
        }
        if ($waNorm === '' && $emNorm === '') {
            $stats['invalidos']++;
            continue;
        }

        // Respeita as cotas independentes.
        if ($waNorm !== '' && $faltaWa <= 0) $waNorm = '';
        if ($emNorm !== '' && $faltaEmail <= 0) $emNorm = '';
        if ($waNorm === '' && $emNorm === '') continue;

        $origem = trim((string) ($c['fonte'] ?? ''));
        if ($origem === '') {
            $origem = (string) ($fontes[0]['link'] ?? '');
        }

        $ins = mkt_contato_inserir([
            'empresa'         => $empresa,
            'nome_contato'    => trim((string) ($c['nome_contato'] ?? '')),
            'nicho'           => $nicho['nome'],
            'descricao_nicho' => $nicho['descricao'],
            'cidade'          => trim((string) ($c['cidade'] ?? $nicho['cidade'])),
            'estado'          => trim((string) ($c['estado'] ?? $nicho['estado'])),
            'regiao'          => $nicho['regiao'],
            'whatsapp'        => $waNorm,
            'email'           => $emNorm,
            'site'            => trim((string) ($c['site'] ?? '')),
            'origem'          => $origem,
            'origem_tipo'     => 'google_openai',
            'base_legal'      => 'legitimo_interesse',
            'observacoes'     => 'Nome do contato informado na fonte: ' . trim((string) ($c['nome_contato'] ?? '')),
            'data_coleta'     => date('Y-m-d'),
        ]);

        if ($ins['ok']) {
            if ($waNorm !== '') { $stats['whatsapp_novos']++; $faltaWa--; }
            if ($emNorm !== '') { $stats['emails_novos']++; $faltaEmail--; }
        } elseif ($ins['motivo'] === 'duplicado') {
            $stats['duplicados']++;
        } else {
            $stats['invalidos']++;
        }
    }

    $partes = [];
    $partes[] = $stats['whatsapp_novos'] . ' novo(s) WhatsApp';
    $partes[] = $stats['emails_novos'] . ' novo(s) e-mail';
    if ($stats['duplicados'] > 0) $partes[] = $stats['duplicados'] . ' duplicado(s) descartado(s)';
    if ($stats['invalidos'] > 0) $partes[] = $stats['invalidos'] . ' invalido(s)/nao confirmado(s)';
    return ['ok' => true, 'mensagem' => 'Pesquisa concluida: ' . implode(', ', $partes) . '.', 'stats' => $stats];
}

// =====================================================
// Fila de disparos
// =====================================================

function mkt_fila_enfileirar() {
    $pdo = getConnection();
    $tplWa = mkt_template_obter('whatsapp');
    $tplEm = mkt_template_obter('email');
    $inseridos = ['whatsapp' => 0, 'email' => 0];

    $sqlWa = "INSERT IGNORE INTO marketing_envios
        (contato_id, canal, template_id, empresa, whatsapp, email, nicho, mensagem, status)
        SELECT c.id, 'whatsapp', ?, c.empresa, c.whatsapp, c.email, c.nicho, ?, 'aguardando'
        FROM marketing_contatos c
        WHERE c.opt_out = 0 AND c.status IN ('coletado','na_fila','enviado','falha')
          AND (c.origem_tipo IS NULL OR c.origem_tipo <> 'manual')
          AND c.whatsapp_norm IS NOT NULL AND c.whatsapp_norm <> ''";
    $stmt = $pdo->prepare($sqlWa);
    $stmt->execute([$tplWa['id'] ?? null, (string) $tplWa['conteudo']]);
    $inseridos['whatsapp'] = $stmt->rowCount();

    $sqlEm = "INSERT IGNORE INTO marketing_envios
        (contato_id, canal, template_id, empresa, whatsapp, email, nicho, assunto, mensagem, status)
        SELECT c.id, 'email', ?, c.empresa, c.whatsapp, c.email, c.nicho, ?, ?, 'aguardando'
        FROM marketing_contatos c
        WHERE c.opt_out = 0 AND c.status IN ('coletado','na_fila','enviado','falha')
          AND (c.origem_tipo IS NULL OR c.origem_tipo <> 'manual')
          AND c.email_norm IS NOT NULL AND c.email_norm <> ''";
    $stmt = $pdo->prepare($sqlEm);
    $stmt->execute([$tplEm['id'] ?? null, (string) $tplEm['assunto'], (string) $tplEm['conteudo']]);
    $inseridos['email'] = $stmt->rowCount();

    if ($inseridos['whatsapp'] > 0 || $inseridos['email'] > 0) {
        $pdo->exec("UPDATE marketing_contatos SET status = 'na_fila' WHERE status = 'coletado'");
    }
    return $inseridos;
}

function mkt_smtp_global_config() {
    return [
        'host'       => trim((string) getConfigGlobal('smtp_host', '')),
        'port'       => (int) (getConfigGlobal('smtp_port', '587') ?: 587),
        'user'       => trim((string) getConfigGlobal('smtp_usuario', '')),
        'pass'       => (string) getConfigGlobal('smtp_senha', ''),
        'from_email' => trim((string) getConfigGlobal('smtp_from_email', '')),
        'from_nome'  => trim((string) getConfigGlobal('smtp_from_nome', 'Sistema de Cobranca')),
        'ssl'        => (string) (getConfigGlobal('smtp_ssl', 'tls') ?: 'tls'),
    ];
}

function mkt_optout_token($contatoId) {
    try {
        return substr(hash_hmac('sha256', 'optout:' . (int) $contatoId, mkt_key_obter()), 0, 32);
    } catch (Throwable $e) {
        return '';
    }
}

function mkt_optout_url($contatoId) {
    $token = mkt_optout_token($contatoId);
    if ($token === '') return '';
    $base = function_exists('urlPublicaSistema') ? urlPublicaSistema() : '';
    return $base . '/cobranca/api/marketing_optout.php?id=' . (int) $contatoId . '&token=' . $token;
}

function mkt_optout_verificar($contatoId, $token) {
    $esperado = mkt_optout_token($contatoId);
    return $esperado !== '' && $token !== '' && hash_equals($esperado, (string) $token);
}

function mkt_rodape_optout($contatoId) {
    $url = mkt_optout_url($contatoId);
    if ($url === '') return '';
    return '<div style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:11px;color:#6b7280;line-height:1.5;">'
        . 'Voce recebeu este contato comercial porque seus dados estao publicamente disponiveis e ha legitimo interesse na oferta. '
        . 'Para nao receber mais mensagens, <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:#6b7280;">clique aqui para sair da lista</a>.'
        . '</div>';
}

function mkt_limites_envio() {
    $nicho = mkt_nicho_config();
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT
        SUM(CASE WHEN canal='whatsapp' AND status='enviado' THEN 1 ELSE 0 END) AS wa,
        SUM(CASE WHEN canal='email' AND status='enviado' THEN 1 ELSE 0 END) AS em
        FROM marketing_envios WHERE DATE(enviado_em) = CURDATE()");
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['wa' => 0, 'em' => 0];
    return [
        'wa_restante' => max(0, $nicho['limite_wa'] - (int) $row['wa']),
        'em_restante' => max(0, $nicho['limite_email'] - (int) $row['em']),
        'wa_enviados_hoje' => (int) $row['wa'],
        'em_enviados_hoje' => (int) $row['em'],
    ];
}

// Processa a fila respeitando as cotas diarias. Retorna contadores e pendencias.
function mkt_processar_fila() {
    $pdo = getConnection();
    $resultado = [
        'whatsapp_enviados' => 0,
        'emails_enviados' => 0,
        'falhas' => 0,
        'pendencias' => [],
    ];

    $limites = mkt_limites_envio();
    $evo = mkt_evolution_config();
    $smtp = mkt_smtp_global_config();
    $tplWa = mkt_template_obter('whatsapp');
    $tplEm = mkt_template_obter('email');

    $evoPronta = mkt_evolution_configurada($evo);
    $smtpPronta = $smtp['host'] !== '' && $smtp['user'] !== '' && $smtp['pass'] !== '' && $smtp['from_email'] !== '';

    if ($limites['wa_restante'] > 0 && !$evoPronta) {
        $resultado['pendencias'][] = 'WhatsApp: API Evolution nao configurada (URL, API Key e instancia).';
    }
    if ($limites['em_restante'] > 0 && !$smtpPronta) {
        $resultado['pendencias'][] = 'E-mail: SMTP global incompleto (host, usuario, senha e remetente).';
    }

    $estadoEvo = null;
    if ($limites['wa_restante'] > 0 && $evoPronta) {
        $estadoEvo = mkt_evolution_status($evo);
        if ($estadoEvo['estado'] !== 'conectado') {
            $resultado['pendencias'][] = 'WhatsApp: instancia nao esta conectada (' . $estadoEvo['estado'] . ').';
            $evoPronta = false;
        }
    }

    // ----- WhatsApp -----
    if ($limites['wa_restante'] > 0 && $evoPronta) {
        $imagensWa = mkt_arquivo_listar('whatsapp');
        $midiasWa = [];
        foreach ($imagensWa as $img) {
            $caminho = mkt_arquivo_caminho($img['arquivo'] ?? '');
            if ($caminho !== '') $midiasWa[] = $img + ['caminho' => $caminho];
        }
        $stmt = $pdo->prepare("SELECT e.*, c.whatsapp_norm, c.opt_out, c.nome_contato, c.cidade, c.estado
            FROM marketing_envios e JOIN marketing_contatos c ON c.id = e.contato_id
            WHERE e.canal='whatsapp' AND e.status IN ('aguardando','falha') AND c.opt_out=0
            ORDER BY e.id ASC LIMIT " . (int) $limites['wa_restante']);
        $stmt->execute();
        $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fila as $item) {
            $contato = $item + ['nome_contato' => $item['nome_contato'] ?? ''];
            $texto = mkt_template_render($item['mensagem'] !== '' ? $item['mensagem'] : $tplWa['conteudo'], $contato);
            $texto = trim(mkt_texto_sem_placeholders_imagem($texto));
            if (stripos($texto, 'SAIR') === false) {
                $texto .= "\n\nSe nao quiser receber mensagens, responda SAIR.";
            }
            $pdo->prepare("UPDATE marketing_envios SET status='enviando', tentativas=tentativas+1 WHERE id=?")->execute([$item['id']]);

            if ($midiasWa) {
                // A legenda acompanha a PRIMEIRA imagem; as demais seguem sem
                // legenda para nao repetir o texto. Considera enviado quando ao
                // menos uma imagem foi aceita, evitando reenvio em duplicidade.
                $enviadas = 0;
                $falhasMidia = [];
                $raws = [];
                foreach ($midiasWa as $indice => $img) {
                    $legenda = $indice === 0 ? $texto : '';
                    $envioMidia = mkt_evolution_enviar_midia($evo, $item['whatsapp_norm'], $img['caminho'], $img['mime'], $img['nome_original'], $legenda);
                    $raws[] = $envioMidia['raw'];
                    if ($envioMidia['ok']) {
                        $enviadas++;
                    } else {
                        $falhasMidia[] = ($envioMidia['error'] ?: ('HTTP ' . $envioMidia['http']));
                    }
                }

                if ($enviadas > 0) {
                    $obs = 'Evolution aceitou ' . $enviadas . ' de ' . count($midiasWa) . ' imagem(ns).';
                    if ($falhasMidia) $obs .= ' Falha: ' . implode(' | ', $falhasMidia);
                    $pdo->prepare("UPDATE marketing_envios SET status='enviado', enviado_em=NOW(), resposta_api=?, erro=NULL WHERE id=?")
                        ->execute([mb_substr($obs . ' ' . implode(' ', $raws), 0, 4000, 'UTF-8'), $item['id']]);
                    $resultado['whatsapp_enviados']++;
                } else {
                    $pdo->prepare("UPDATE marketing_envios SET status='falha', erro=?, resposta_api=? WHERE id=?")
                        ->execute([mb_substr(implode(' | ', $falhasMidia), 0, 1500, 'UTF-8'), mb_substr(implode(' ', $raws), 0, 4000, 'UTF-8'), $item['id']]);
                    $resultado['falhas']++;
                }
            } else {
                $envio = mkt_evolution_enviar($evo, $item['whatsapp_norm'], $texto);
                if ($envio['ok']) {
                    $pdo->prepare("UPDATE marketing_envios SET status='enviado', enviado_em=NOW(), resposta_api=?, erro=NULL WHERE id=?")
                        ->execute([mb_substr($envio['raw'], 0, 4000, 'UTF-8'), $item['id']]);
                    $resultado['whatsapp_enviados']++;
                } else {
                    $pdo->prepare("UPDATE marketing_envios SET status='falha', erro=?, resposta_api=? WHERE id=?")
                        ->execute([mb_substr((string) $envio['error'], 0, 1500, 'UTF-8'), mb_substr($envio['raw'], 0, 4000, 'UTF-8'), $item['id']]);
                    $resultado['falhas']++;
                }
            }
        }
    }

    // ----- E-mail -----
    if ($limites['em_restante'] > 0 && $smtpPronta) {
        require_once __DIR__ . '/email_helpers.php';
        $imagensEm = mkt_arquivo_listar('email');
        $stmt = $pdo->prepare("SELECT e.*, c.email_norm, c.opt_out, c.cidade, c.estado
            FROM marketing_envios e JOIN marketing_contatos c ON c.id = e.contato_id
            WHERE e.canal='email' AND e.status IN ('aguardando','falha') AND c.opt_out=0
            ORDER BY e.id ASC LIMIT " . (int) $limites['em_restante']);
        $stmt->execute();
        $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fila as $item) {
            $html = mkt_template_render($item['mensagem'] !== '' ? $item['mensagem'] : $tplEm['conteudo'], $item);
            $aplicado = mkt_email_aplicar_imagens($html, $imagensEm);
            $html = $aplicado['html'];
            $html .= mkt_rodape_optout((int) $item['contato_id']);
            $assunto = mkt_template_render($item['assunto'] !== '' ? $item['assunto'] : $tplEm['assunto'], $item);
            $textoPlano = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));
            $fromEmail = $tplEm['remetente_email'] !== '' ? $tplEm['remetente_email'] : $smtp['from_email'];
            $fromNome = $tplEm['remetente_nome'] !== '' ? $tplEm['remetente_nome'] : $smtp['from_nome'];

            $pdo->prepare("UPDATE marketing_envios SET status='enviando', tentativas=tentativas+1 WHERE id=?")->execute([$item['id']]);
            $ok = enviarEmailMarketing($smtp['host'], $smtp['port'], $smtp['user'], $smtp['pass'], $fromEmail, $fromNome, $smtp['ssl'],
                $item['email_norm'], $item['empresa'], $assunto, $html, $textoPlano, $aplicado['inline'], $aplicado['anexos']);
            if ($ok) {
                $obs = 'SMTP aceitou a mensagem';
                $qtdInline = count($aplicado['inline']);
                $qtdAnexo = count($aplicado['anexos']);
                if ($qtdInline > 0 || $qtdAnexo > 0) {
                    $obs .= ' (' . $qtdInline . ' imagem(ns) no corpo, ' . $qtdAnexo . ' em anexo)';
                }
                $pdo->prepare("UPDATE marketing_envios SET status='enviado', enviado_em=NOW(), resposta_api=?, erro=NULL WHERE id=?")
                    ->execute([$obs, $item['id']]);
                $resultado['emails_enviados']++;
            } else {
                $pdo->prepare("UPDATE marketing_envios SET status='falha', erro='Falha no envio SMTP (ver log do servidor).' WHERE id=?")
                    ->execute([$item['id']]);
                $resultado['falhas']++;
            }
        }
    }

    // Atualiza status dos contatos que tiveram pelo menos um envio concluido.
    if ($resultado['whatsapp_enviados'] > 0 || $resultado['emails_enviados'] > 0) {
        $pdo->exec("UPDATE marketing_contatos c
            JOIN marketing_envios e ON e.contato_id = c.id AND e.status = 'enviado'
            SET c.status = 'enviado'
            WHERE c.status IN ('na_fila','coletado')");
    }

    return $resultado;
}

// =====================================================
// Configuracao e execucao do Cron (fuso America/Sao_Paulo)
// =====================================================

function mkt_cron_config() {
    $ativo = (string) mkt_config_get('mkt_cron_ativo', '0') === '1';
    $horario = (string) mkt_config_get('mkt_cron_horario', '09:00');
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $horario)) $horario = '09:00';
    return ['ativo' => $ativo, 'horario' => $horario];
}

function mkt_cron_proxima_execucao() {
    $cfg = mkt_cron_config();
    if (!$cfg['ativo']) return null;
    $tz = new DateTimeZone('America/Sao_Paulo');
    $agora = new DateTime('now', $tz);
    $alvo = new DateTime($agora->format('Y-m-d') . ' ' . $cfg['horario'] . ':00', $tz);
    if ($alvo <= $agora) {
        $alvo->modify('+1 day');
    }
    return $alvo;
}

function mkt_ultima_execucao($origem = null) {
    $pdo = getConnection();
    $sql = "SELECT * FROM marketing_execucoes";
    $params = [];
    if ($origem !== null) {
        $sql .= " WHERE origem = ?";
        $params[] = $origem;
    }
    $sql .= " ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mkt_cron_ja_executou_hoje() {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id FROM marketing_execucoes WHERE chave_diaria = ? LIMIT 1");
    $stmt->execute([date('Y-m-d')]);
    return (bool) $stmt->fetchColumn();
}

// Rotina completa: pesquisa -> validacao/deduplicacao -> armazenamento ->
// fila -> envio -> registro. Protegida por GET_LOCK e chave diaria unica.
function mkt_executar_rotina($origem = 'manual') {
    $origem = $origem === 'cron' ? 'cron' : 'manual';
    $pdo = getConnection();

    $lock = (int) $pdo->query("SELECT GET_LOCK('mkt_cron_lock', 0)")->fetchColumn();
    if ($lock !== 1) {
        return ['ok' => false, 'mensagem' => 'Ja existe uma execucao do Marketing em andamento. Tente novamente em instantes.'];
    }

    $execucaoId = bin2hex(random_bytes(16));
    $chaveDiaria = $origem === 'cron' ? date('Y-m-d') : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO marketing_execucoes (execucao_id, origem, chave_diaria, iniciado_em, status) VALUES (?,?,?,NOW(),'em_andamento')");
        $stmt->execute([$execucaoId, $origem, $chaveDiaria]);
    } catch (PDOException $e) {
        $pdo->query("SELECT RELEASE_LOCK('mkt_cron_lock')");
        if ((string) $e->getCode() === '23000') {
            return ['ok' => true, 'mensagem' => 'A rotina automatica de hoje ja foi executada. Nada a fazer.'];
        }
        return ['ok' => false, 'mensagem' => 'Nao foi possivel registrar a execucao: ' . $e->getMessage()];
    }

    $id = (int) $pdo->lastInsertId();
    $mensagens = [];
    $stats = ['whatsapp_novos' => 0, 'emails_novos' => 0, 'duplicados' => 0, 'invalidos' => 0, 'fontes' => 0];
    $status = 'concluido';

    try {
        $pesquisa = mkt_pesquisar_contatos($stats);
        $mensagens[] = $pesquisa['mensagem'];
        if (!$pesquisa['ok']) {
            $status = 'parcial';
        }

        $fila = mkt_fila_enfileirar();
        $mensagens[] = 'Fila: ' . $fila['whatsapp'] . ' WhatsApp e ' . $fila['email'] . ' e-mail enfileirado(s).';

        $envio = mkt_processar_fila();
        $mensagens[] = 'Envios: ' . $envio['whatsapp_enviados'] . ' WhatsApp e ' . $envio['emails_enviados'] . ' e-mail concluido(s), ' . $envio['falhas'] . ' falha(s).';
        if ($envio['pendencias']) {
            $mensagens[] = 'Pendencias: ' . implode(' | ', $envio['pendencias']);
            if ($envio['whatsapp_enviados'] === 0 && $envio['emails_enviados'] === 0) $status = 'parcial';
        }

        $pdo->prepare("UPDATE marketing_execucoes SET finalizado_em=NOW(), status=?, whatsapp_novos=?, emails_novos=?,
            duplicados=?, invalidos=?, whatsapp_enviados=?, emails_enviados=?, falhas=?, mensagem=? WHERE id=?")
            ->execute([
                $status,
                (int) $stats['whatsapp_novos'],
                (int) $stats['emails_novos'],
                (int) $stats['duplicados'],
                (int) $stats['invalidos'],
                (int) $envio['whatsapp_enviados'],
                (int) $envio['emails_enviados'],
                (int) $envio['falhas'],
                mb_substr(implode("\n", $mensagens), 0, 4000, 'UTF-8'),
                $id,
            ]);

        return [
            'ok' => true,
            'mensagem' => implode(' ', $mensagens),
            'execucao_id' => $id,
            'stats' => $stats,
            'envio' => $envio,
        ];
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE marketing_execucoes SET finalizado_em=NOW(), status='erro', mensagem=? WHERE id=?")
            ->execute([mb_substr('Erro: ' . $e->getMessage(), 0, 4000, 'UTF-8'), $id]);
        error_log('[Marketing] rotina: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro na execucao do Marketing: ' . $e->getMessage()];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mkt_cron_lock')");
    }
}

// Chamado pelo endpoint externo: so executa se estiver ativo, no horario e
// ainda nao tiver rodado hoje. Manual nunca passa por aqui.
function mkt_cron_tick() {
    // Campanhas manuais sao independentes da automacao diaria: o Cron Job
    // processa a fila manual a cada acionamento, respeitando data/hora de
    // inicio, status da campanha, fila e limites diarios exclusivos.
    $manual = null;
    try {
        $manual = mkt_manual_processar();
    } catch (Throwable $e) {
        error_log('[Marketing] cron manual: ' . $e->getMessage());
        $manual = ['ok' => false, 'mensagens' => ['Erro ao processar os envios manuais.']];
    }

    $cfg = mkt_cron_config();
    if (!$cfg['ativo']) {
        return ['executado' => false, 'motivo' => 'Automacao desativada.', 'manual' => $manual];
    }
    $tz = new DateTimeZone('America/Sao_Paulo');
    $agora = new DateTime('now', $tz);
    $alvo = new DateTime($agora->format('Y-m-d') . ' ' . $cfg['horario'] . ':00', $tz);
    if ($agora < $alvo) {
        return ['executado' => false, 'motivo' => 'Horario programado ainda nao atingido (' . $cfg['horario'] . ').', 'manual' => $manual];
    }
    if (mkt_cron_ja_executou_hoje()) {
        return ['executado' => false, 'motivo' => 'Rotina automatica de hoje ja executada.', 'manual' => $manual];
    }
    $r = mkt_executar_rotina('cron');
    return ['executado' => true, 'resultado' => $r, 'manual' => $manual];
}

// =====================================================
// Consultas para a interface
// =====================================================

function mkt_contatos_listar($filtros = []) {
    $pdo = getConnection();
    $where = [];
    $params = [];
    if (!empty($filtros['busca'])) {
        $where[] = "(empresa LIKE ? OR whatsapp LIKE ? OR email LIKE ? OR nicho LIKE ?)";
        $b = '%' . $filtros['busca'] . '%';
        array_push($params, $b, $b, $b, $b);
    }
    if (!empty($filtros['nicho'])) {
        $where[] = "nicho = ?";
        $params[] = $filtros['nicho'];
    }
    if (!empty($filtros['status'])) {
        $where[] = "status = ?";
        $params[] = $filtros['status'];
    }
    $sql = "SELECT * FROM marketing_contatos";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY id DESC LIMIT 300";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_logs_listar($filtros = []) {
    $pdo = getConnection();
    $where = [];
    $params = [];
    if (!empty($filtros['data_ini'])) {
        $where[] = "DATE(e.criado_em) >= ?";
        $params[] = $filtros['data_ini'];
    }
    if (!empty($filtros['data_fim'])) {
        $where[] = "DATE(e.criado_em) <= ?";
        $params[] = $filtros['data_fim'];
    }
    if (!empty($filtros['nicho'])) {
        $where[] = "e.nicho = ?";
        $params[] = $filtros['nicho'];
    }
    if (!empty($filtros['canal'])) {
        $where[] = "e.canal = ?";
        $params[] = $filtros['canal'];
    }
    if (!empty($filtros['status'])) {
        $where[] = "e.status = ?";
        $params[] = $filtros['status'];
    }
    $sql = "SELECT e.* FROM marketing_envios e";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY e.id DESC LIMIT 300";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_execucoes_listar($limite = 30) {
    $pdo = getConnection();
    $limite = max(1, min(200, (int) $limite));
    return $pdo->query("SELECT * FROM marketing_execucoes ORDER BY id DESC LIMIT " . $limite)->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_stats() {
    $pdo = getConnection();
    $stats = [
        'contatos' => 0, 'whatsapp' => 0, 'emails' => 0, 'opt_out' => 0,
        'fila_whatsapp' => 0, 'fila_email' => 0,
        'enviados_whatsapp_hoje' => 0, 'enviados_email_hoje' => 0,
        'falhas' => 0, 'descartes' => 0,
    ];
    $row = $pdo->query("SELECT COUNT(*) total,
        SUM(CASE WHEN whatsapp_norm IS NOT NULL AND whatsapp_norm <> '' THEN 1 ELSE 0 END) wa,
        SUM(CASE WHEN email_norm IS NOT NULL AND email_norm <> '' THEN 1 ELSE 0 END) em,
        SUM(CASE WHEN opt_out = 1 THEN 1 ELSE 0 END) opt
        FROM marketing_contatos")->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['contatos'] = (int) ($row['total'] ?? 0);
    $stats['whatsapp'] = (int) ($row['wa'] ?? 0);
    $stats['emails'] = (int) ($row['em'] ?? 0);
    $stats['opt_out'] = (int) ($row['opt'] ?? 0);

    $row = $pdo->query("SELECT
        SUM(CASE WHEN canal='whatsapp' AND status IN ('aguardando','falha') THEN 1 ELSE 0 END) fwa,
        SUM(CASE WHEN canal='email' AND status IN ('aguardando','falha') THEN 1 ELSE 0 END) fem,
        SUM(CASE WHEN canal='whatsapp' AND status='enviado' AND DATE(enviado_em)=CURDATE() THEN 1 ELSE 0 END) ewa,
        SUM(CASE WHEN canal='email' AND status='enviado' AND DATE(enviado_em)=CURDATE() THEN 1 ELSE 0 END) eem,
        SUM(CASE WHEN status='falha' THEN 1 ELSE 0 END) falhas
        FROM marketing_envios")->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['fila_whatsapp'] = (int) ($row['fwa'] ?? 0);
    $stats['fila_email'] = (int) ($row['fem'] ?? 0);
    $stats['enviados_whatsapp_hoje'] = (int) ($row['ewa'] ?? 0);
    $stats['enviados_email_hoje'] = (int) ($row['eem'] ?? 0);
    $stats['falhas'] = (int) ($row['falhas'] ?? 0);
    $stats['descartes'] = (int) $pdo->query("SELECT COUNT(*) FROM marketing_descartes")->fetchColumn();
    return $stats;
}

// As campanhas manuais reutilizam este modulo (templates, Evolution, SMTP,
// opt-out, imagens e logs). O require fica no fim para que todas as funcoes
// base ja estejam declaradas.
require_once __DIR__ . '/marketing_manual_helpers.php';

