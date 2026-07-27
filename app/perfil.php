<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$mensagem = '';
$tipo = '';

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$userId]);
$cliente = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'senha') {
        $senhaAtual = trim($_POST['senha_atual'] ?? '');
        $novaSenha = trim($_POST['nova_senha'] ?? '');
        $confirmaSenha = trim($_POST['confirma_senha'] ?? '');

        if (empty($senhaAtual) || empty($novaSenha)) {
            $mensagem = 'Preencha todos os campos de senha.';
            $tipo = 'danger';
        } elseif ($novaSenha !== $confirmaSenha) {
            $mensagem = 'As senhas não conferem.';
            $tipo = 'danger';
        } elseif (strlen($novaSenha) < 6) {
            $mensagem = 'A nova senha deve ter no mínimo 6 caracteres.';
            $tipo = 'danger';
        } else {
            $stmt = $pdo->prepare("SELECT id, senha FROM clientes WHERE id = ?");
            $stmt->execute([$userId]);
            $cli = $stmt->fetch();
            if ($cli && (password_verify($senhaAtual, $cli['senha']) || (strlen($cli['senha']) === 32 && md5($senhaAtual) === $cli['senha']))) {
                $stmt = $pdo->prepare("UPDATE clientes SET senha = ? WHERE id = ?");
                $stmt->execute([password_hash($novaSenha, PASSWORD_BCRYPT), $userId]);
                $mensagem = 'Senha alterada com sucesso!';
                $tipo = 'success';
            } else {
                $mensagem = 'Senha atual incorreta.';
                $tipo = 'danger';
            }
        }
    } else {

    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $celular = trim($_POST['celular'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE clientes SET email=?, telefone=?, celular=? WHERE id=?");
        $stmt->execute([$email, $telefone, $celular, $userId]);

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = __DIR__ . '/../assets/img/avatars';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'user_' . $userId . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $filename)) {
                    $avatarPath = '/cobranca/assets/img/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE clientes SET avatar=? WHERE id=?");
                    $stmt->execute([$avatarPath, $userId]);
                    $_SESSION['user_avatar'] = $avatarPath;
                }
            }
        }

        $_SESSION['user_nome'] = $cliente['nome_razao'];
        $mensagem = 'Perfil atualizado!';
        $tipo = 'success';

        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$userId]);
        $cliente = $stmt->fetch();
    } catch (PDOException $e) {
        $mensagem = 'Erro ao atualizar.';
        $tipo = 'danger';
    }

    } // fecha else acao senha
}

$logo = getConfig('logo_mobile', '') ?: getLogo();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#6C5CE7">
    <title>Meu Perfil</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="icon.php?size=192">
    <link rel="apple-touch-icon" href="icon.php?size=192">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <div class="app-topbar">
            <a href="dashboard.php" style="text-decoration:none; color:var(--app-text); display:flex; align-items:center; gap:8px;">
                <i class="fas fa-arrow-left"></i>
                <span style="font-size:0.9rem; font-weight:600;">Voltar</span>
            </a>
            <span style="font-weight:700;">Meu Perfil</span>
        </div>

        <div class="app-content">
            <?php if ($mensagem): ?>
                <div class="app-alert app-alert-<?= $tipo === 'success' ? 'success' : 'danger' ?> app-animate" <?= $tipo === 'success' ? 'style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;"' : '' ?>>
                    <i class="fas fa-<?= $tipo === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <div class="app-perfil-header app-animate">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="app-perfil-avatar">
                <div class="app-perfil-name"><?= htmlspecialchars($cliente['nome_razao']) ?></div>
                <div class="app-perfil-email"><?= htmlspecialchars($cliente['email'] ?? '') ?></div>
            </div>

            <div class="app-perfil-card app-animate">
                <h6><i class="fas fa-id-card"></i> Dados Pessoais</h6>
                <div class="app-perfil-row">
                    <span class="label">Nome</span>
                    <span class="value"><?= htmlspecialchars($cliente['nome_razao']) ?></span>
                </div>
                <div class="app-perfil-row">
                    <span class="label">CPF/CNPJ</span>
                    <span class="value"><?= htmlspecialchars($cliente['cpf_cnpj']) ?></span>
                </div>
                <div class="app-perfil-row">
                    <span class="label">Tipo</span>
                    <span class="value"><?= $cliente['tipo_pessoa'] === 'PJ' ? 'Pessoa Jurídica' : 'Pessoa Física' ?></span>
                </div>
            </div>

            <div class="app-perfil-card app-animate">
                <h6><i class="fas fa-address-book"></i> Contato</h6>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">E-mail</label>
                        <input type="email" name="email" class="app-input" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">Telefone</label>
                        <input type="text" name="telefone" class="app-input" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">Celular</label>
                        <input type="text" name="celular" class="app-input" value="<?= htmlspecialchars($cliente['celular'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">Foto de Perfil</label>
                        <input type="file" name="avatar" class="app-input" accept="image/*" style="padding:10px;">
                    </div>
                    <button type="submit" class="app-btn app-btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>

            <div class="app-perfil-card app-animate" style="margin-bottom:24px;">
                <h6><i class="fas fa-key"></i> Alterar Senha</h6>
                <form method="POST">
                    <input type="hidden" name="acao" value="senha">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">Senha Atual</label>
                        <input type="password" name="senha_atual" class="app-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">Nova Senha</label>
                        <input type="password" name="nova_senha" class="app-input" minlength="6" required>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="font-size:0.75rem; color:var(--app-text-muted); font-weight:600; margin-bottom:4px; display:block;">Confirmar Nova Senha</label>
                        <input type="password" name="confirma_senha" class="app-input" minlength="6" required>
                    </div>
                    <button type="submit" class="app-btn app-btn-primary" style="background:#f59e0b;">
                        <i class="fas fa-lock"></i> Alterar Senha
                    </button>
                </form>
            </div>

            <div class="app-perfil-card app-animate" style="margin-bottom:24px;">
                <h6><i class="fas fa-map-marker-alt"></i> Endereço</h6>
                <div class="app-perfil-row">
                    <span class="label">CEP</span>
                    <span class="value"><?= htmlspecialchars($cliente['cep'] ?? '--') ?></span>
                </div>
                <div class="app-perfil-row">
                    <span class="label">Logradouro</span>
                    <span class="value"><?= htmlspecialchars(($cliente['logradouro'] ?? '') . ', ' . ($cliente['numero'] ?? '')) ?></span>
                </div>
                <div class="app-perfil-row">
                    <span class="label">Bairro</span>
                    <span class="value"><?= htmlspecialchars($cliente['bairro'] ?? '--') ?></span>
                </div>
                <div class="app-perfil-row">
                    <span class="label">Cidade/UF</span>
                    <span class="value"><?= htmlspecialchars(($cliente['cidade'] ?? '') . '/' . ($cliente['estado'] ?? '')) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align:center; padding:16px; font-size:0.65rem; color:#94a3b8;">
        <a href="https://agenciawd.com.br" target="_blank" style="color:#94a3b8; text-decoration:none;">Todos os Direitos Reservados - WD Soluções Digitais LTDA - 2010 - 2026</a>
    </div>

    <nav class="app-bottom-nav">
        <a href="dashboard.php" class="app-nav-item">
            <i class="fas fa-home"></i>
            <span>Faturas</span>
        </a>
        <a href="financeiro.php" class="app-nav-item">
            <i class="fas fa-headset"></i>
            <span>Financeiro</span>
        </a>
        <a href="perfil.php" class="app-nav-item active">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
        <a href="logout.php" class="app-nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </nav>
    <script src="pwa.js"></script>
</body>
</html>