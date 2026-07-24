<?php
// =====================================================
// INSTALADOR DO SISTEMA WD_payments
// Passo 1: Conexão com banco de dados
// Passo 2: Criar administrador
// Passo 3: Acessar o painel
// =====================================================

require_once __DIR__ . '/../config/database.php';
$pdoTest = getConnection();
if ($pdoTest) {
    $tabela = $pdoTest->query("SHOW TABLES LIKE 'configuracoes'")->fetch();
    if ($tabela) {
        header('Location: /cobranca/admin/login.php');
        exit;
    }
}

$etapa = 1;
$erro = '';
$sucesso = '';

$dbHost = $_POST['db_host'] ?? 'localhost';
$dbPort = $_POST['db_port'] ?? '3306';
$dbName = $_POST['db_name'] ?? 'cobranca';
$dbUser = $_POST['db_user'] ?? '';
$dbPass = $_POST['db_pass'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'conectar') {
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            $sqlFile = __DIR__ . '/../estrutura_banco.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
                $sql = preg_replace('/\r\n/', "\n", $sql);
                $statements = array_filter(array_map('trim', explode(";\n", $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && !preg_match('/^--/', $stmt)) {
                        try { $pdo->exec($stmt); } catch (PDOException $e) {}
                    }
                }
            }

            $dsn2 = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn2, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $configContent = "<?php\n";
            $configContent .= "// =====================================================\n";
            $configContent .= "// CONFIGURACAO DO BANCO DE DADOS\n";
            $configContent .= "// Gerado pelo instalador em " . date('d/m/Y H:i:s') . "\n";
            $configContent .= "// =====================================================\n\n";
            $configContent .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $configContent .= "define('DB_PORT', " . var_export($dbPort, true) . ");\n";
            $configContent .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $configContent .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $configContent .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $configContent .= "define('DB_CHARSET', 'utf8mb4');\n\n";

            $original = file_get_contents(__DIR__ . '/../config/database.php');
            $afterDefine = preg_replace('/<\?php.*?define\(\'DB_CHARSET\'.*?\n\n/s', '', $original);
            $configContent .= ltrim($afterDefine);

            file_put_contents(__DIR__ . '/../config/database.php', $configContent);

            $etapa = 2;
            $sucesso = 'Banco de dados conectado e tabelas importadas!';

        } catch (PDOException $e) {
            $erro = 'Falha na conexão: ' . $e->getMessage();
            $etapa = 1;
        }

    } elseif ($acao === 'criar_admin') {
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';
        $adminPassConf = $_POST['admin_pass_conf'] ?? '';

        if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
            $erro = 'Preencha todos os campos.';
            $etapa = 2;
        } elseif (strlen($adminPass) < 6) {
            $erro = 'A senha deve ter no mínimo 6 caracteres.';
            $etapa = 2;
        } elseif ($adminPass !== $adminPassConf) {
            $erro = 'As senhas não conferem.';
            $etapa = 2;
        } else {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                $stmt = $pdo->prepare("UPDATE administradores SET usuario=?, email=?, senha=? WHERE usuario='admin'");
                $stmt->execute([$adminUser, $adminEmail, password_hash($adminPass, PASSWORD_BCRYPT)]);

                $etapa = 3;
                $sucesso = 'Administrador criado com sucesso!';

            } catch (PDOException $e) {
                $erro = 'Erro ao criar administrador: ' . $e->getMessage();
                $etapa = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - WD_payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .install-card { max-width: 520px; width: 100%; border: none; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .install-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; border-radius: 16px 16px 0 0; padding: 28px 32px; }
        .install-header h4 { font-weight: 700; margin: 0; }
        .install-header small { opacity: .7; }
        .step-num { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
        .step-active { background: #0d6efd; color: #fff; }
        .step-done { background: #198754; color: #fff; }
        .step-pending { background: #dee2e6; color: #6c757d; }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card install-card mx-auto">
        <div class="install-header text-center">
            <h4><i class="fas fa-cog me-2"></i>WD_payments</h4>
            <small>Instalador do Sistema</small>
        </div>
        <div class="card-body p-4">

            <?php if ($erro): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="text-center">
                    <span class="step-num <?= $etapa >= 1 ? ($etapa > 1 ? 'step-done' : 'step-active') : 'step-pending' ?>">1</span>
                    <div class="small text-muted mt-1">Conexão</div>
                </div>
                <div class="flex-grow-1 mx-2" style="height:2px;background:#dee2e6;"></div>
                <div class="text-center">
                    <span class="step-num <?= $etapa >= 2 ? ($etapa > 2 ? 'step-done' : 'step-active') : 'step-pending' ?>">2</span>
                    <div class="small text-muted mt-1">Administrador</div>
                </div>
                <div class="flex-grow-1 mx-2" style="height:2px;background:#dee2e6;"></div>
                <div class="text-center">
                    <span class="step-num <?= $etapa >= 3 ? 'step-done' : 'step-pending' ?>">3</span>
                    <div class="small text-muted mt-1">Concluído</div>
                </div>
            </div>

            <?php if ($etapa === 1): ?>
            <h6 class="mb-3"><i class="fas fa-database me-1"></i> Passo 1 — Conexão com o Banco de Dados</h6>
            <form method="POST">
                <input type="hidden" name="acao" value="conectar">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Host</label>
                    <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($dbHost) ?>" required>
                    <div class="form-text">Ex: localhost, mysql.hostinger.com</div>
                </div>
                <div class="row mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Porta</label>
                        <input type="text" name="db_port" class="form-control" value="<?= htmlspecialchars($dbPort) ?>" required>
                    </div>
                    <div class="col-8">
                        <label class="form-label small fw-semibold">Banco de Dados</label>
                        <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($dbName) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Usuário</label>
                    <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($dbUser) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Senha</label>
                    <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($dbPass) ?>">
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plug me-1"></i> Conectar e Importar</button>
            </form>

            <?php elseif ($etapa === 2): ?>
            <h6 class="mb-3"><i class="fas fa-user-shield me-1"></i> Passo 2 — Criar Administrador</h6>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_admin">
                <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
                <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPort) ?>">
                <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
                <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
                <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Usuário</label>
                    <input type="text" name="admin_user" class="form-control" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">E-mail</label>
                    <input type="email" name="admin_email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Senha</label>
                    <input type="password" name="admin_pass" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Confirmar Senha</label>
                    <input type="password" name="admin_pass_conf" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i> Criar Administrador</button>
            </form>

            <?php elseif ($etapa === 3): ?>
            <div class="text-center py-3">
                <div class="mb-3"><i class="fas fa-check-circle text-success" style="font-size:3rem;"></i></div>
                <h5 class="mb-2">Instalação Concluída!</h5>
                <p class="text-muted small mb-4">Sistema configurado com sucesso. Delete a pasta <code>/install</code> após o primeiro login.</p>
                <a href="/cobranca/admin/login.php" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-1"></i> Acessar o Painel</a>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <p class="text-center text-muted small mt-3">WD_payments &copy; <?= date('Y') ?></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
