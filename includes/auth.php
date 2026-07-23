<?php
// =====================================================
// SISTEMA DE AUTENTICAÇÃO
// =====================================================

session_start();

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
    
    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE usuario = ? AND senha = MD5(?) AND ativo = 1");
    $stmt->execute([$usuario, $senha]);
    $admin = $stmt->fetch();
    
    if ($admin) {
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

function loginUser($cpfCnpj, $senha = '') {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
    
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE cpf_cnpj = ? AND ativo = 1");
    $stmt->execute([$cpfCnpj]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
        $_SESSION['user_id'] = $cliente['id'];
        $_SESSION['user_nome'] = $cliente['nome_razao'];
        $_SESSION['user_cpf_cnpj'] = $cliente['cpf_cnpj'];
        $_SESSION['user_tipo'] = $cliente['tipo_pessoa'];
        $_SESSION['user_avatar'] = $cliente['avatar'] ?? null;
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: /cobranca/index.php');
    exit;
}

function logoutAdmin() {
    session_destroy();
    header('Location: /cobranca/admin/login.php');
    exit;
}

function logoutUser() {
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
        $_SESSION['user_id'] = $cliente['id'];
        $_SESSION['user_nome'] = $cliente['nome_razao'];
        $_SESSION['user_cpf_cnpj'] = $cliente['cpf_cnpj'];
        $_SESSION['user_tipo'] = $cliente['tipo_pessoa'];
        $_SESSION['user_avatar'] = $cliente['avatar'] ?? null;
        return true;
    }
    return false;
}
