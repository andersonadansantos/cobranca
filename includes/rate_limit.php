<?php
// =====================================================
// RATE LIMITING DE LOGIN (compartilhado admin/superadmin)
// As funções são idênticas às de includes/auth.php; as
// verificações function_exists evitam redefinição quando
// ambos os arquivos são carregados na mesma requisição.
// =====================================================

if (!defined('RATE_LIMIT_MAX')) define('RATE_LIMIT_MAX', 5);
if (!defined('RATE_LIMIT_BLOCK_MINUTES')) define('RATE_LIMIT_BLOCK_MINUTES', 30);

if (!function_exists('checkLoginRateLimit')) {
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
}

if (!function_exists('recordLoginAttempt')) {
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
}

if (!function_exists('clearLoginAttempts')) {
    function clearLoginAttempts($contexto, $identificador) {
        $pdo = getConnection();
        if (!$pdo) return;
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE contexto = ? AND identificador = ?");
        $stmt->execute([$contexto, $identificador]);
    }
}
