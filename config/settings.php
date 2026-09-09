<?php
// =====================================================
// CONFIGURAÇÕES DO SISTEMA
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tenant.php';

// === UTF-8 global ===
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
if (function_exists('mb_http_output')) { mb_http_output('UTF-8'); }
ini_set('default_charset', 'UTF-8');

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

function getCorPrimaria() {
    return getConfig('cor_primaria', '#0f7b5c');
}

function getCorSecundaria() {
    return getConfig('cor_secundaria', '#6c757d');
}

function getCorFundo() {
    return getConfig('cor_fundo', '#f8f9fa');
}

function getLogo() {
    return getConfig('logo_empresa', '');
}

function getLogoLogin() {
    return getConfig('logo_login', '');
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
