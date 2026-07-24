<?php
// =====================================================
// INSTALADOR DO SISTEMA WD_payments
// Configure o banco de dados e importe a estrutura
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

    if ($acao === 'testar') {
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            $tabela = $pdo->query("SHOW TABLES LIKE 'configuracoes'")->fetch();
            if ($tabela) {
                $etapa = 3;
                $sucesso = 'Conexão OK! Banco de dados já possui as tabelas.';
            } else {
                $etapa = 2;
                $sucesso = 'Conexão OK! Banco de dados criado/selecionado.';
            }
        } catch (PDOException $e) {
            $erro = 'Falha na conexão: ' . $e->getMessage();
            $etapa = 1;
        }

    } elseif ($acao === 'importar') {
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $sqlFile = __DIR__ . '/../estrutura_banco.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
                $etapa = 3;
                $sucesso = 'Tabelas importadas com sucesso!';
            } else {
                $erro = 'Arquivo estrutura_banco.sql não encontrado.';
                $etapa = 2;
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao importar: ' . $e->getMessage();
            $etapa = 2;
        }

    } elseif ($acao === 'salvar') {
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $senhaAdmin = bin2hex(random_bytes(8));
            $hash = password_hash($senhaAdmin, PASSWORD_BCRYPT);

            $admin = $pdo->query("SELECT COUNT(*) FROM `administradores` WHERE `usuario` = 'admin'")->fetchColumn();
            if ($admin == 0) {
                $stmt = $pdo->prepare("INSERT INTO `administradores` (`usuario`, `senha`, `nome`, `email`, `ativo`) VALUES (?, ?, 'Administrador', 'admin@sistema.com', 1)");
                $stmt->execute(['admin', $hash]);
            } else {
                $stmt = $pdo->prepare("UPDATE `administradores` SET `senha` = ? WHERE `usuario` = 'admin'");
                $stmt->execute([$hash]);
            }

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

            $etapa = 4;
            $sucesso = $senhaAdmin;

        } catch (PDOException $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
            $etapa = 3;
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
        .step-num { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; margin-right: 8px; }
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
                <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <?php if ($sucesso && $etapa !== 4): ?>
                <div class="alert alert-success py-2"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <div class="d-flex align-items-center mb-4">
                <span class="step-num <?= $etapa >= 1 ? ($etapa > 1 ? 'step-done' : 'step-active') : 'step-pending' ?>">1</span>
                <span class="step-num <?= $etapa >= 2 ? ($etapa > 2 ? 'step-done' : 'step-active') : 'step-pending' ?>">2</span>
                <span class="step-num <?= $etapa >= 3 ? ($etapa > 3 ? 'step-done' : 'step-active') : 'step-pending' ?>">3</span>
                <span class="step-num <?= $etapa >= 4 ? 'step-done' : 'step-pending' ?>">4</span>
                <span class="ms-2 text-muted small">Etapa <?= $etapa ?> de 4</span>
            </div>

            <?php if ($etapa === 1): ?>
            <h6 class="mb-3"><i class="fas fa-database me-1"></i> Configuração do Banco de Dados</h6>
            <form method="POST">
                <input type="hidden" name="acao" value="testar">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Host</label>
                    <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($dbHost) ?>" required>
                    <div class="form-text">Ex: localhost, 127.0.0.1, mysql.hostinger.com</div>
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
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plug me-1"></i> Testar Conexão</button>
            </form>

            <?php elseif ($etapa === 2): ?>
            <h6 class="mb-3"><i class="fas fa-table me-1"></i> Importar Estrutura</h6>
            <p class="text-muted small">O banco de dados está conectado. Deseja importar as tabelas?</p>
            <form method="POST">
                <input type="hidden" name="acao" value="importar">
                <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
                <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPort) ?>">
                <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
                <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
                <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">
                <div class="d-flex gap-2">
                    <a href="?limpar=1" class="btn btn-outline-secondary flex-fill"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
                    <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-download me-1"></i> Importar Tabelas</button>
                </div>
            </form>

            <?php elseif ($etapa === 3): ?>
            <h6 class="mb-3"><i class="fas fa-save me-1"></i> Finalizar Instalação</h6>
            <p class="text-muted small">Salve as credenciais no servidor e gere a senha do administrador.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbHost) ?>">
                <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbPort) ?>">
                <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbName) ?>">
                <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbUser) ?>">
                <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>">
                <div class="d-flex gap-2">
                    <a href="?limpar=1" class="btn btn-outline-secondary flex-fill"><i class="fas fa-arrow-left me-1"></i> Voltar</a>
                    <button type="submit" class="btn btn-success flex-fill"><i class="fas fa-check me-1"></i> Instalar</button>
                </div>
            </form>

            <?php elseif ($etapa === 4): ?>
            <h6 class="mb-3 text-success"><i class="fas fa-check-circle me-1"></i> Instalação Concluída!</h6>
            <div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle me-1"></i> <strong>Anote estas credenciais</strong> e delete a pasta <code>/install</code> após o primeiro login.</div>
            <div class="bg-light p-3 rounded border mb-3">
                <div class="mb-1"><strong>Usuário:</strong> <code>admin</code></div>
                <div class="mb-0"><strong>Senha:</strong> <code style="font-size:1.05em"><?= htmlspecialchars($sucesso) ?></code></div>
            </div>
            <p class="text-muted small mb-3">Acesse <code>/admin/login.php</code> e altere a senha após o primeiro login.</p>
            <a href="/cobranca/admin/login.php" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-1"></i> Acessar o Painel</a>
            <?php endif; ?>

        </div>
    </div>
    <p class="text-center text-muted small mt-3">WD_payments &copy; <?= date('Y') ?></p>
</div>

<?php if (isset($_GET['limpar'])): ?>
    <script>window.location = window.location.pathname;</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
