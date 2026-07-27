<?php
// =====================================================
// SISTEMA DE AUTENTICAÇÃO
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    if (ini_get('session.use_only_cookies') == '0') {
        ini_set('session.use_only_cookies', '1');
    }
    ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

$sessionTimeout = 3600;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionTimeout) {
    session_unset();
    session_destroy();
    header('Location: /cobranca/index.php');
    exit;
}
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../config/database.php';

function isMobileDevice() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua);
}

function isLoggedInAdmin() {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0;
}

function isLoggedInUser() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function requireAdmin() {
    if (!isLoggedInAdmin()) {
        header('Location: /cobranca/admin/login.php');
        exit;
    }
}

function requireUser() {
    if (!isLoggedInUser()) {
        header('Location: /cobranca/usuario/login.php');
        exit;
    }
}

function loginAdmin($usuario, $senha) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE usuario = ? AND ativo = 1");
    $stmt->execute([$usuario]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $hash = $admin['senha'];
        $valid = false;
        if (password_verify($senha, $hash)) {
            $valid = true;
        } elseif (strlen($hash) === 32 && md5($senha) === $hash) {
            $valid = true;
            $newHash = password_hash($senha, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
            $upd->execute([$newHash, $admin['id']]);
        }
        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            $_SESSION['admin_avatar'] = $admin['avatar'] ?? null;
            
            $stmt = $pdo->prepare("UPDATE administradores SET ultimo_login = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
            
            clearLoginAttempts('admin', $usuario);
            return true;
        }
    }
    return false;
}

function loginUser($cpfCnpj, $senha = '') {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
    
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE cpf_cnpj = ? AND ativo = 1");
    $stmt->execute([$cpfCnpj]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
        $hash = $cliente['senha'] ?? '';
        if (!empty($hash) && !empty($senha)) {
            $valid = false;
            if (password_verify($senha, $hash)) {
                $valid = true;
            } elseif (strlen($hash) === 32 && md5($senha) === $hash) {
                $valid = true;
                $newHash = password_hash($senha, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE clientes SET senha = ? WHERE id = ?");
                $upd->execute([$newHash, $cliente['id']]);
            }
            if (!$valid) return false;
        } elseif (empty($hash)) {
            // Senha não definida — login sem senha (compatibilidade legada)
        } elseif (empty($senha)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $cliente['id'];
        $_SESSION['user_nome'] = $cliente['nome_razao'];
        $_SESSION['user_cpf_cnpj'] = $cliente['cpf_cnpj'];
        $_SESSION['user_tipo'] = $cliente['tipo_pessoa'];
        $_SESSION['user_avatar'] = $cliente['avatar'] ?? null;
        clearLoginAttempts('user', $cpfCnpj);
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: /cobranca/index.php');
    exit;
}

function logoutAdmin() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: /cobranca/admin/login.php');
    exit;
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: /cobranca/usuario/login.php');
    exit;
}

function verificarGoogleCredential($credential) {
    $response = @file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential));
    if (!$response) return false;
    $data = json_decode($response, true);
    if (!$data || !isset($data['sub']) || !isset($data['email'])) return false;
    if (isset($data['aud']) && $data['aud'] !== getConfig('google_client_id', '')) return false;
    return $data;
}

function loginAdminGoogle($googleId, $email, $nome) {
    $pdo = getConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE google_id = ? AND ativo = 1");
    $stmt->execute([$googleId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if ($admin) {
            $stmt = $pdo->prepare("UPDATE administradores SET google_id = ? WHERE id = ?");
            $stmt->execute([$googleId, $admin['id']]);
            $admin['google_id'] = $googleId;
        }
    }

    if ($admin) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_nome'] = $admin['nome'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        $_SESSION['admin_avatar'] = $admin['avatar'] ?? null;
        $stmt = $pdo->prepare("UPDATE administradores SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        return true;
    }
    return false;
}

function loginUserGoogle($googleId, $email, $nome) {
    $pdo = getConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE google_id = ? AND ativo = 1");
    $stmt->execute([$googleId]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();
        if ($cliente) {
            $stmt = $pdo->prepare("UPDATE clientes SET google_id = ? WHERE id = ?");
            $stmt->execute([$googleId, $cliente['id']]);
            $cliente['google_id'] = $googleId;
        }
    }

    if ($cliente) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $cliente['id'];
        $_SESSION['user_nome'] = $cliente['nome_razao'];
        $_SESSION['user_cpf_cnpj'] = $cliente['cpf_cnpj'];
        $_SESSION['user_tipo'] = $cliente['tipo_pessoa'];
        $_SESSION['user_avatar'] = $cliente['avatar'] ?? null;
        clearLoginAttempts('user_google', $email);
        return true;
    }
    return false;
}

// =====================================================
// AUTENTICAÇÃO POR CERTIFICADO DIGITAL
// =====================================================

function getClientCertificate() {
    $certPem = $_SERVER['SSL_CLIENT_CERT'] ?? '';
    if (empty($certPem)) return false;

    $cert = openssl_x509_parse($certPem);
    if (!$cert) return false;

    $cert['pem'] = $certPem;

    $cert['thumbprint'] = strtoupper(sha1($certPem));

    $cert['subject_cn'] = $cert['subject']['CN'] ?? '';
    $cert['subject_o'] = $cert['subject']['O'] ?? '';
    $cert['subject_ou'] = $cert['subject']['OU'] ?? '';
    $cert['subject_serialnumber'] = $cert['subject']['serialNumber'] ?? $cert['subjects'][0] ?? '';

    $cert['issuer_cn'] = $cert['issuer']['CN'] ?? '';
    $cert['issuer_o'] = $cert['issuer']['O'] ?? '';

    return $cert;
}

function loginAdminCertificado() {
    $cert = getClientCertificate();
    if (!$cert) return false;

    $pdo = getConnection();
    if (!$pdo) return false;

    $thumbprint = $cert['thumbprint'];

    $stmt = $pdo->prepare("SELECT ac.*, a.id as admin_id, a.nome, a.usuario, a.avatar, a.ativo
        FROM admin_certificados ac
        JOIN administradores a ON ac.admin_id = a.id
        WHERE ac.thumbprint = ? AND ac.ativo = 1 AND a.ativo = 1 LIMIT 1");
    $stmt->execute([$thumbprint]);
    $certRecord = $stmt->fetch();

    if (!$certRecord) {
        $stmt = $pdo->prepare("SELECT ac.*, a.id as admin_id, a.nome, a.usuario, a.avatar, a.ativo
            FROM admin_certificados ac
            JOIN administradores a ON ac.admin_id = a.id
            WHERE ac.subject_dn = ? AND ac.ativo = 1 AND a.ativo = 1 LIMIT 1");
        $stmt->execute([$cert['subject']['CN'] ?? '']);
        $certRecord = $stmt->fetch();
    }

    if (!$certRecord) return false;

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $certRecord['admin_id'];
    $_SESSION['admin_nome'] = $certRecord['nome'];
    $_SESSION['admin_usuario'] = $certRecord['usuario'];
    $_SESSION['admin_avatar'] = $certRecord['avatar'] ?? null;
    $_SESSION['admin_login_via'] = 'certificado';

    $stmt = $pdo->prepare("UPDATE administradores SET ultimo_login = NOW() WHERE id = ?");
    $stmt->execute([$certRecord['admin_id']]);

    $stmt = $pdo->prepare("UPDATE admin_certificados SET ultimo_uso = NOW() WHERE id = ?");
    $stmt->execute([$certRecord['id']]);

    clearLoginAttempts('admin', 'certificado');
    return true;
}

function registrarCertificado($adminId, $nome, $cert) {
    $pdo = getConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_certificados WHERE admin_id = ? AND thumbprint = ?");
    $stmt->execute([$adminId, $cert['thumbprint']]);
    if ($stmt->fetchColumn() > 0) return 'duplicado';

    $validadeInicio = date('Y-m-d H:i:s', $cert['validFrom_time_t'] ?? 0);
    $validadeFim = date('Y-m-d H:i:s', $cert['validTo_time_t'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO admin_certificados (admin_id, nome, subject_dn, issuer_dn, serial, thumbprint, certificado_pem, validade_inicio, validade_fim, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $adminId,
        $nome,
        $cert['subject']['CN'] ?? '',
        $cert['issuer']['CN'] ?? '',
        $cert['serialNumberHex'] ?? $cert['serialNumber'] ?? '',
        $cert['thumbprint'],
        $cert['pem'],
        $validadeInicio,
        $validadeFim
    ]);
    return true;
}

// =====================================================
// RATE LIMITING - Login
// =====================================================

define('RATE_LIMIT_MAX', 5);
define('RATE_LIMIT_BLOCK_MINUTES', 30);

function checkLoginRateLimit($contexto, $identificador) {
    $pdo = getConnection();
    if (!$pdo) return ['blocked' => false, 'remaining' => 5];

    $stmt = $pdo->prepare("SELECT tentativas, bloqueado_ate FROM login_attempts WHERE contexto = ? AND identificador = ?");
    $stmt->execute([$contexto, $identificador]);
    $row = $stmt->fetch();

    if ($row) {
        if ($row['bloqueado_ate'] && strtotime($row['bloqueado_ate']) > time()) {
            $resto = ceil((strtotime($row['bloqueado_ate']) - time()) / 60);
            return ['blocked' => true, 'remaining' => 0, 'minutes' => $resto];
        }
        if ($row['bloqueado_ate'] && strtotime($row['bloqueado_ate']) <= time()) {
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE contexto = ? AND identificador = ?");
            $stmt->execute([$contexto, $identificador]);
            return ['blocked' => false, 'remaining' => RATE_LIMIT_MAX];
        }
        $resto = RATE_LIMIT_MAX - $row['tentativas'];
        return ['blocked' => false, 'remaining' => max(0, $resto)];
    }
    return ['blocked' => false, 'remaining' => RATE_LIMIT_MAX];
}

function recordLoginAttempt($contexto, $identificador) {
    $pdo = getConnection();
    if (!$pdo) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("SELECT id, tentativas FROM login_attempts WHERE contexto = ? AND identificador = ?");
    $stmt->execute([$contexto, $identificador]);
    $row = $stmt->fetch();

    if ($row) {
        $novasTentativas = $row['tentativas'] + 1;
        if ($novasTentativas >= RATE_LIMIT_MAX) {
            $bloqueadoAte = date('Y-m-d H:i:s', time() + RATE_LIMIT_BLOCK_MINUTES * 60);
            $stmt = $pdo->prepare("UPDATE login_attempts SET tentativas = ?, bloqueado_ate = ?, ip = ? WHERE id = ?");
            $stmt->execute([$novasTentativas, $bloqueadoAte, $ip, $row['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE login_attempts SET tentativas = ?, ip = ? WHERE id = ?");
            $stmt->execute([$novasTentativas, $ip, $row['id']]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (contexto, identificador, ip, tentativas, criado_em) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$contexto, $identificador, $ip]);
    }
}

function clearLoginAttempts($contexto, $identificador) {
    $pdo = getConnection();
    if (!$pdo) return;
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE contexto = ? AND identificador = ?");
    $stmt->execute([$contexto, $identificador]);
}

function autoLoginByToken($token) {
    if (empty($token) || isLoggedInUser()) return false;
    $pdo = getConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT f.id, f.cliente_id, c.nome_razao, c.cpf_cnpj, c.tipo_pessoa, c.avatar FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.acesso_token = ? AND f.status != 'cancelado' LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row) {
        $_SESSION['user_id'] = $row['cliente_id'];
        $_SESSION['user_nome'] = $row['nome_razao'];
        $_SESSION['user_cpf_cnpj'] = $row['cpf_cnpj'];
        $_SESSION['user_tipo'] = $row['tipo_pessoa'];
        $_SESSION['user_avatar'] = $row['avatar'] ?? null;
        clearLoginAttempts('user', $row['cpf_cnpj']);
        return $row['id'];
    }
    return false;
}

function generateAcessoToken() {
    return bin2hex(random_bytes(32));
}
