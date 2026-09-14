<?php
// =====================================================
// CONFIGURAÇÕES DO SISTEMA
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tenant.php';

// Resolve o tenant pelo subdomínio do host em toda requisição compartilhada
// (painel, site, portal e webhooks). É o que dá "vida" ao subdomínio criado
// no cadastro: branding, configurações e escopo do admin.
initTenant();

// === Raiz física do sistema (funciona em qualquer ambiente/host) ===
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..') ?: __DIR__ . '/..');
}

// === UTF-8 global ===
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
if (function_exists('mb_http_output')) { mb_http_output('UTF-8'); }
ini_set('default_charset', 'UTF-8');

// Produção: nunca imprimir avisos/deprecations na resposta (evita corromper HTML/headers).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Contexto de admin usado para ler/gravar configurações por admin.
// Painel admin -> admin logado. Demais contextos (portal, login, webhook) -> tenant do subdomínio.
function getConfigAdminId() {
    if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0) {
        return (int)$_SESSION['admin_id'];
    }
    return getTenantAdminId();
}

function getConfig($chave, $padrao = '') {
    $pdo = getConnection();
    if (!$pdo) return $padrao;
    $adminId = getConfigAdminId();

    if ($adminId > 0) {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id = ? AND chave = ?");
        $stmt->execute([$adminId, $chave]);
        $row = $stmt->fetch();
        if ($row) return $row['valor'];
    }

    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : $padrao;
}

function saveConfig($chave, $valor) {
    $pdo = getConnection();
    if (!$pdo) return false;
    $adminId = getConfigAdminId();
    if ($adminId <= 0) {
        $adminId = null;
    }
    $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    return $stmt->execute([$adminId, $chave, $valor]);
}

// Lê uma configuração global (admin_id NULL), independente da sessão.
// Usada pelo Super Admin para configurações únicas do sistema (ex.: cron).
function getConfigGlobal($chave, $padrao = '') {
    $pdo = getConnection();
    if (!$pdo) return $padrao;
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : $padrao;
}

// Grava uma configuração global (admin_id NULL), independente da sessão.
// Obs.: a UNIQUE(admin_id, chave) não vale para admin_id NULL no MySQL (NULLs
// não colidem), então o ON DUPLICATE nunca atualiza. Garantimos uma única
// linha deletando as existentes antes de inserir.
function salvarConfigGlobal($chave, $valor) {
    $pdo = getConnection();
    if (!$pdo) return false;
    $chave = (string) $chave;
    $stmt = $pdo->prepare("DELETE FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
    if (!$stmt->execute([$chave])) return false;

    // Valor vazio = remove a configuração (comportamento equivalente de "não exibir").
    if (trim((string) $valor) === '') {
        return true;
    }

    $stmt = $pdo->prepare("INSERT INTO configuracoes (admin_id, chave, valor) VALUES (NULL, ?, ?)");
    return $stmt->execute([$chave, $valor]);
}

function getAllConfig() {
    $pdo = getConnection();
    if (!$pdo) return [];
    $adminId = getConfigAdminId();

    $config = [];

    if ($adminId > 0) {
        // Globais (admin_id NULL) como base + sobreposição do admin atual
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE admin_id IS NULL");
        while ($row = $stmt->fetch()) {
            $config[$row['chave']] = $row['valor'];
        }
        $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        while ($row = $stmt->fetch()) {
            $config[$row['chave']] = $row['valor'];
        }
        return $config;
    }

    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE admin_id IS NULL");
    while ($row = $stmt->fetch()) {
        $config[$row['chave']] = $row['valor'];
    }
    return $config;
}

// === Helpers por admin (independentes da sessão) ===
// Resolvem a configuração de um admin específico (ex.: o admin dono da fatura)
// para que recibo e fatura em PDF usem sempre a personalização do admin correto.

function getConfigForAdmin($adminId, $chave, $padrao = '') {
    $pdo = getConnection();
    if (!$pdo) return $padrao;
    $adminId = (int)$adminId;
    if ($adminId > 0) {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id = ? AND chave = ?");
        $stmt->execute([$adminId, $chave]);
        $row = $stmt->fetch();
        if ($row) return $row['valor'];
    }
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : $padrao;
}

// Todas as configurações aplicáveis a um admin específico
// (base global + sobreposição do admin), independente da sessão.
function getAllConfigForAdmin($adminId) {
    $pdo = getConnection();
    $config = [];
    if (!$pdo) return $config;

    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE admin_id IS NULL");
    while ($row = $stmt->fetch()) $config[$row['chave']] = $row['valor'];

    $adminId = (int)$adminId;
    if ($adminId > 0) {
        $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        while ($row = $stmt->fetch()) $config[$row['chave']] = $row['valor'];
    }
    return $config;
}

// Novo admin nasce 100% zerado: remove quaisquer linhas existentes do admin e
// insere valores vazios para todas as configurações globais administráveis, de
// modo que getConfig()/getAllConfig() retornem '' (bloqueando a herança global).
// Chaves de infraestrutura do sistema (base_domain, cron_token, plano, super_*,
// tutorial_*, turnstile, isolamento etc.) continuam globais normalmente.
function zerarConfigAdminNovo($pdo, $adminId) {
    $adminId = (int)$adminId;
    if (!$pdo || $adminId <= 0) return;

    $pdo->prepare("DELETE FROM configuracoes WHERE admin_id = ?")->execute([$adminId]);

    // Chaves globais que NAO devem ser bloqueadas no admin novo (infraestrutura/sistema).
    $chavesSistema = [
        'base_domain', 'cron_token', 'site_url',
        'planos_limites', 'planos_utf8_fix',
        'multitenant', 'isolamento_admin', 'usuarios_admin_isolado',
        'turnstile_secret_key', 'google_client_id',
    ];

    $placeholders = implode(',', array_fill(0, count($chavesSistema), '?'));
    $stmt = $pdo->prepare(
        "INSERT INTO configuracoes (admin_id, chave, valor)
         SELECT ?, chave, '' FROM configuracoes
         WHERE admin_id IS NULL
           AND chave NOT IN ($placeholders)
           AND chave NOT LIKE 'super\\_%'
           AND chave NOT LIKE 'tutorial\\_%'
         ON DUPLICATE KEY UPDATE valor = ''"
    );
    $params = array_merge([$adminId], $chavesSistema);
    $stmt->execute($params);
}

/**
 * Exclusão TOTAL de um admin: remove todas as linhas em todas as tabelas que
 * possuem a coluna admin_id (dados de clientes, faturas, configs, plano,
 * livro caixa, recibos, etc.), além do cadastro em administradores. Com isso o
 * subdomínio/usuário/email ficam livres para um recadastramento completo.
 * Retorna true em caso de sucesso.
 */
function excluirAdminCompleto($pdo, $adminId) {
    $adminId = (int)$adminId;
    if (!$pdo || $adminId <= 0) return false;

    // Tabelas que possuem coluna `admin_id` (isolamento por tenant).
    $tabelas = [
        'admin_certificados',
        'admin_evolution',
        'admin_planos',
        'clientes',
        'configuracoes',
        'contratos',
        'faturas',
        'faturas_recorrentes',
        'livro_caixa_custos',
        'livro_caixa_entradas',
        'livro_caixa_saidas',
        'nfse_notas',
        'pagamentos_log',
        'planos_pagamentos',
        'recibos',
        'usuarios_admin',
    ];

    // Recupera caminhos de arquivos (logos, banners, avatar) para limpar órfãos.
    $arquivos = [];
    try {
        $st = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE admin_id = ?");
        $st->execute([$adminId]);
        while ($row = $st->fetch()) {
            $arquivos[] = $row['valor'];
        }
    } catch (PDOException $e) {}
    try {
        $st = $pdo->prepare("SELECT avatar FROM administradores WHERE id = ?");
        $st->execute([$adminId]);
        $avatar = (string)$st->fetchColumn();
        if ($avatar !== '') $arquivos[] = $avatar;
    } catch (PDOException $e) {}

    try {
        $pdo->beginTransaction();
        // Desabilita FKs momentaneamente: algumas tabelas (faturas/clientes/
        // contratos/faturas_recorrentes) têm FK entre si e a ordem de exclusão
        // pode variar conforme o schema de cada ambiente.
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($tabelas as $tbl) {
            $existe = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl))->fetch();
            if ($existe) {
                $pdo->prepare("DELETE FROM `" . $tbl . "` WHERE admin_id = ?")->execute([$adminId]);
            }
        }
        $pdo->prepare("DELETE FROM administradores WHERE id = ?")->execute([$adminId]);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $e2) {} }
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Throwable $e2) {}
        return false;
    }

    // Remove arquivos órfãos (logos/banners/avatars) salvos em assets/.
    foreach ($arquivos as $caminho) {
        if (!is_string($caminho) || $caminho === '') continue;
        $path = null;
        if (preg_match('#^/cobranca/(assets/.+)$#', $caminho, $m)) {
            $path = __DIR__ . '/..' . '/' . $m[1];
        } elseif (preg_match('#^/cobranca/#', $caminho)) {
            $path = __DIR__ . '/..' . substr($caminho, strlen('/cobranca'));
        }
        if ($path && strpos($path, 'assets/') !== false && is_file($path)) {
            @unlink($path);
        }
    }
    // Remove o diretório de certificados Inter/Sicoob do admin, se existir.
    $certDir = __DIR__ . '/inter_certs/admin_' . $adminId;
    if (is_dir($certDir)) {
        foreach (glob($certDir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($certDir);
    }

    return true;
}

// Logo da empresa de um admin específico (logo_empresa_admin). Cai para a
// marca global se o admin não tiver enviado logo própria.
function getLogoEmpresaFatura($adminId) {
    $logo = getConfigForAdmin($adminId, 'logo_empresa_admin', '');
    if (!logoPathValido($logo)) $logo = getLogo();
    return $logo;
}

function getCorPrimaria() {
    return getConfig('cor_primaria', '#0f7b5c');
}

function getCorSecundaria() {
    return getConfig('cor_secundaria', '#6c757d');
}

function getCorFundo() {
    return getConfig('cor_fundo', '#f8f9fa');
}

// Verifica se o caminho da logo ainda aponta para um arquivo existente.
// Evita imagens quebradas quando o arquivo foi removido mas o config ficou no banco.
function logoPathValido($caminho) {
    $caminho = trim((string)$caminho);
    if ($caminho === '') return false;
    if (strpos($caminho, 'http') === 0 || strpos($caminho, 'data:') === 0) return true;
    $arquivo = __DIR__ . '/..' . preg_replace('#^/cobranca#', '', $caminho);
    $arquivo = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $arquivo);
    return file_exists($arquivo);
}

// Logo da marca global (enviada pelo Super Admin). Usada nos painéis,
// e-mails, PDFs e site público. As logos de personalização do admin
// (logo_empresa/logo_mobile) ficam reservadas ao login do cliente.
function getLogo() {
    return getLogoLoginGlobal();
}

// Logo das telas de login do ADMIN (logins internos). Retorna vazio se o arquivo sumiu.
function getLogoLogin() {
    $logo = getConfig('logo_login', '');
    return logoPathValido($logo) ? $logo : '';
}

// Logo global de login enviada pelo Super Admin (admin_id NULL).
// Usada nas telas de login do admin, independente do tenant.
function getLogoLoginGlobal() {
    $logo = getConfigGlobal('logo_login', '');
    return logoPathValido($logo) ? $logo : '';
}

// Logo do LOGIN DO CLIENTE (desktop): é o "Logo da Empresa" da personalização
// do admin (logo_empresa). Cai para a marca global se o admin não enviou.
function getLogoClienteLogin() {
    $logo = getConfig('logo_empresa', '');
    if (!logoPathValido($logo)) {
        $logo = getLogoLoginGlobal();
    }
    return $logo;
}

// Logo do LOGIN DO CLIENTE (mobile/app): é o "Logo Versão Mobile" da
// personalização do admin (logo_mobile). Cai para a marca global se não houver.
function getLogoClienteLoginMobile() {
    $logo = getConfig('logo_mobile', '');
    if (!logoPathValido($logo)) {
        $logo = getLogoLoginGlobal();
    }
    return $logo;
}

// Logo do PAINEL DO CLIENTE logado (canto superior esquerdo): é o "Logo da
// Empresa" disponibilizado pelo admin que emitiu as faturas (logo_empresa_admin).
// Resolve o admin dono do cliente mesmo sem contexto de subdomínio. Cai para a
// marca global se o admin não enviou logo própria.
function getLogoPainelUsuario() {
    $adminId = getTenantAdminId();
    if ($adminId <= 0 && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
        $pdo = getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT admin_id FROM clientes WHERE id = ?");
                $stmt->execute([(int)$_SESSION['user_id']]);
                $adminId = (int)$stmt->fetchColumn();
                if ($adminId > 0) $_SESSION['tenant_admin_id'] = $adminId;
            } catch (Exception $e) {}
        }
    }

    $logo = '';
    if ($adminId > 0) {
        $pdo = getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id = ? AND chave = 'logo_empresa_admin'");
                $stmt->execute([$adminId]);
                $logo = (string)$stmt->fetchColumn();
            } catch (Exception $e) {}
        }
    }
    if (!logoPathValido($logo)) {
        $logo = getLogo();
    }
    return $logo;
}

function getNomeSistema() {
    return getConfig('nome_sistema', 'Sistema de Cobrança');
}

// Mapa de cores dos planos (nome -> hex) usado no Super Admin e painéis
function planoCorHex($cor) {
    $mapa = [
        'primary'   => '#0d6efd',
        'secondary' => '#6c757d',
        'success'   => '#128a53',
        'warning'   => '#f59e0b',
        'danger'    => '#dc3545',
        'info'      => '#22d3ee',
        'bronze'    => '#cd7f32',
        'dark'      => '#1f2937',
        'light'     => '#adb5bd',
    ];
    $cor = strtolower(trim((string)$cor));
    return $mapa[$cor] ?? '#0f7b5c';
}

// Ícone do plano via Unicons (IconScout) com fallback para Font Awesome
function planoIconClass($icon) {
    $mapa = [
        'fa-crown'              => 'uil uil-trophy',
        'fa-medal'              => 'uil uil-medal',
        'fa-gem'                => 'uil uil-diamond',
        'fa-star'               => 'uil uil-star',
        'fa-rocket'             => 'uil uil-rocket',
        'fa-circle-half-stroke' => 'uil uil-adjust-circle',
        'fa-circle'             => 'uil uil-circle',
        'fa-bolt'               => 'uil uil-bolt',
        'fa-shield-halved'      => 'uil uil-shield',
        'fa-crown-solid'        => 'fa-solid fa-crown',
    ];
    return $mapa[$icon] ?? 'uil uil-circle';
}

function generateInvoiceNumber($adminId = null) {
    $ano = date('Y');
    $mes = date('m');
    $pdo = getConnection();

    if ($adminId === null) {
        $adminId = getConfigAdminId();
    }
    $adminId = (int)$adminId;

    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) as maior FROM faturas WHERE numero LIKE ? AND numero REGEXP ? AND admin_id = ?");
    $prefixo = "FAT-{$ano}%";
    $stmt->execute([$prefixo, '^FAT-[0-9]{6}-[0-9]+$', $adminId]);
    $row = $stmt->fetch();
    $sequencia = intval($row['maior'] ?? 0) + 1;

    return "FAT-{$ano}{$mes}-" . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
}

// Retorna o plano ativo do admin (linha completa da tabela planos) ou null.
function planoAtivoAdmin($adminId = null) {
    if ($adminId === null) {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
    }
    $adminId = (int)$adminId;
    if ($adminId <= 0) return null;

    $pdo = getConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT p.* FROM admin_planos ap JOIN planos p ON p.id = ap.plano_id WHERE ap.admin_id = ? ORDER BY ap.id DESC LIMIT 1");
    $stmt->execute([$adminId]);
    $plano = $stmt->fetch();
    return $plano ?: null;
}

// Verifica se o plano do admin atingiu o limite para $tipo ('clientes'|'usuarios'|'faturas').
// Retorna ['ok'=>bool, 'atual'=>int, 'max'=>int|null, 'nome'=>string, 'mensagem'=>string].
// Limite vazio (NULL) = ilimitado.
// Verifica se o plano do admin permite envio de cobrança via WhatsApp ou e-mail.
// Usado para bloquear envios em planos restritos (ex.: conta demo).
function planoPermiteEnvio($rotulo = 'whatsapp', $adminId = null) {
    if ($adminId === null) {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
    }
    $adminId = (int)$adminId;
    if ($adminId <= 0) return true;

    $plano = planoAtivoAdmin($adminId);
    if (!$plano) return true;

    if ($rotulo === 'whatsapp') {
        return (int)($plano['whatsapp_cobranca'] ?? 1) === 1;
    }
    if ($rotulo === 'email') {
        return (int)($plano['email_cobranca'] ?? 1) === 1;
    }
    return true;
}

// Retorna bool informando se o admin logado é a conta de demonstração.
function adminEhDemo($adminId = null) {
    if ($adminId === null) {
        return (($_SESSION['admin_origem'] ?? '') === 'demo');
    }
    $adminId = (int)$adminId;
    if ($adminId <= 0) return false;
    $pdo = getConnection();
    if (!$pdo) return false;
    $stmt = $pdo->prepare("SELECT origem FROM administradores WHERE id = ? LIMIT 1");
    $stmt->execute([$adminId]);
    return $stmt->fetchColumn() === 'demo';
}

function verificarLimitePlano($tipo, $adminId = null) {
    if ($adminId === null) {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
    }
    $adminId = (int)$adminId;
    if ($adminId <= 0) {
        return ['ok' => true, 'atual' => 0, 'max' => null, 'nome' => '', 'mensagem' => ''];
    }

    $plano = planoAtivoAdmin($adminId);
    if (!$plano) {
        return ['ok' => true, 'atual' => 0, 'max' => null, 'nome' => '', 'mensagem' => ''];
    }

    $pdo = getConnection();
    $limite = null;
    $atual = 0;
    switch ($tipo) {
        case 'clientes':
            $limite = $plano['max_clientes'];
            $rotulo = 'clientes';
            break;
        case 'usuarios':
            $limite = $plano['max_usuarios'];
            $rotulo = 'usuários';
            break;
        case 'faturas':
            $limite = $plano['max_faturas_mensais'];
            $rotulo = 'faturas no mês';
            break;
        default:
            return ['ok' => true, 'atual' => 0, 'max' => null, 'nome' => $plano['nome'] ?: '', 'mensagem' => ''];
    }

    if ($limite === null || (int)$limite <= 0) {
        return ['ok' => true, 'atual' => $atual, 'max' => null, 'nome' => $plano['nome'] ?: '', 'mensagem' => ''];
    }

    if ($pdo) {
        switch ($tipo) {
            case 'clientes':
                $st = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE admin_id = ?");
                $st->execute([$adminId]);
                $atual = (int)$st->fetchColumn();
                break;
            case 'usuarios':
                $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios_admin WHERE admin_id = ?");
                $st->execute([$adminId]);
                $atual = (int)$st->fetchColumn();
                break;
            case 'faturas':
                $st = $pdo->prepare("SELECT COUNT(*) FROM faturas WHERE admin_id = ? AND MONTH(data_emissao) = MONTH(CURDATE()) AND YEAR(data_emissao) = YEAR(CURDATE())");
                $st->execute([$adminId]);
                $atual = (int)$st->fetchColumn();
                break;
        }
    }

    $limite = (int)$limite;
    if ($atual >= $limite) {
        return [
            'ok' => false,
            'atual' => $atual,
            'max' => $limite,
            'nome' => $plano['nome'] ?: '',
            'mensagem' => "Limite do plano {$plano['nome']} atingido: {$limite} {$rotulo}. Assine um plano superior em Meu Plano para liberar mais espaço."
        ];
    }

    return ['ok' => true, 'atual' => $atual, 'max' => $limite, 'nome' => $plano['nome'] ?: '', 'mensagem' => ''];
}
