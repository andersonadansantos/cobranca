<?php
// =====================================================
// INSTALADOR - Redireciona para o painel
// O banco de dados é criado automaticamente
// =====================================================

require_once __DIR__ . '/config/database.php';

$pdo = getConnection();
if ($pdo) {
    $credFile = __DIR__ . '/_initial_credentials.txt';
    if (file_exists($credFile)) {
        $credenciais = file_get_contents($credFile);
        unlink($credFile);
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Credenciais Iniciais</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card shadow border-success">
                            <div class="card-body text-center p-5">
                                <h3 class="text-success mb-3">Banco configurado com sucesso!</h3>
                                <p>Salve estas credenciais e <strong>altere a senha após o primeiro login</strong>.</p>
                                <pre class="bg-light p-3 rounded text-start border"><?= htmlspecialchars($credenciais) ?></pre>
                                <a href="/cobranca/admin/login.php" class="btn btn-success">Acessar o Painel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    header('Location: /cobranca/admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <h3 class="text-danger mb-3">Erro de Conexão</h3>
                        <p>Não foi possível conectar ao banco de dados.</p>
                        <p class="small text-muted">Verifique se o XAMPP está rodando e o MariaDB está ativo.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
