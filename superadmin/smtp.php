<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

if (!function_exists('testarConexaoSmtp')) {
function testarConexaoSmtp($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $testEmail) {
    $errno = 0;
    $errstr = '';

    $proto = ($ssl === 'ssl') ? 'ssl://' : '';
    $connexion = @fsockopen($proto . $host, $port, $errno, $errstr, 10);

    if (!$connexion) {
        return ['sucesso' => false, 'mensagem' => "Falha ao conectar: {$errstr} (código {$errno})"];
    }

    @fgets($connexion, 512);

    @fputs($connexion, "EHLO " . gethostname() . "\r\n");
    stream_set_timeout($connexion, 5);
    $ehloResponse = '';
    for ($i = 0; $i < 10; $i++) {
        $response = @fgets($connexion, 512);
        $ehloResponse .= $response;
        if (substr($response, 0, 3) === '250' && substr($response, 3, 1) === ' ') break;
    }

    if ($ssl === 'tls') {
        @fputs($connexion, "STARTTLS\r\n");
        $response = @fgets($connexion, 512);
        if (substr($response, 0, 3) === '220') {
            stream_context_set_option($connexion, 'ssl', 'verify_peer', false);
            stream_context_set_option($connexion, 'ssl', 'verify_peer_name', false);
            $crypto = @stream_socket_enable_crypto($connexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                @fclose($connexion);
                return ['sucesso' => false, 'mensagem' => "Falha ao iniciar TLS."];
            }
            @fputs($connexion, "EHLO " . gethostname() . "\r\n");
            $ehloResponse = '';
            for ($i = 0; $i < 10; $i++) {
                $response = @fgets($connexion, 512);
                $ehloResponse .= $response;
                if (substr($response, 0, 3) === '250' && substr($response, 3, 1) === ' ') break;
            }
        }
    }

    $authPlain = stripos($ehloResponse, 'AUTH') !== false && stripos($ehloResponse, 'PLAIN') !== false;
    $authLogin = stripos($ehloResponse, 'AUTH') !== false && stripos($ehloResponse, 'LOGIN') !== false;
    $authOk = false;

    if ($authPlain) {
        @fputs($connexion, "AUTH PLAIN\r\n");
        $response = @fgets($connexion, 512);
        if (substr($response, 0, 3) === '334') {
            @fputs($connexion, base64_encode("\0" . $user . "\0" . $pass) . "\r\n");
            $response = @fgets($connexion, 512);
            if (substr($response, 0, 3) === '235') $authOk = true;
        }
    }

    if (!$authOk && $authLogin) {
        @fputs($connexion, "AUTH LOGIN\r\n");
        $response = @fgets($connexion, 512);
        if (substr($response, 0, 3) === '334') {
            @fputs($connexion, base64_encode($user) . "\r\n");
            $response = @fgets($connexion, 512);
            if (substr($response, 0, 3) === '334') {
                @fputs($connexion, base64_encode($pass) . "\r\n");
                $response = @fgets($connexion, 512);
                if (substr($response, 0, 3) === '235') $authOk = true;
            }
        }
    }

    if (!$authOk) {
        @fclose($connexion);
        return ['sucesso' => false, 'mensagem' => "Servidor não aceitou AUTH PLAIN nem AUTH LOGIN. Verifique host, porta e criptografia."];
    }

    @fputs($connexion, "MAIL FROM:<{$fromEmail}>\r\n");
    @fgets($connexion, 512);

    @fputs($connexion, "RCPT TO:<{$testEmail}>\r\n");
    $response = @fgets($connexion, 512);
    if (substr($response, 0, 3) !== '250') {
        @fclose($connexion);
        return ['sucesso' => false, 'mensagem' => "E-mail de destino inválido ou recusado."];
    }

    @fputs($connexion, "DATA\r\n");
    @fgets($connexion, 512);

    $boundary = md5(uniqid(time()));
    $msgDate = date('r');
    $body  = "From: {$fromNome} <{$fromEmail}>\r\n";
    $body .= "To: <{$testEmail}>\r\n";
    $body .= "Date: {$msgDate}\r\n";
    $body .= "Subject: =?UTF-8?B?" . base64_encode("Teste SMTP - " . getNomeSistema()) . "?=\r\n";
    $body .= "MIME-Version: 1.0\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $body .= "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= "Este é um e-mail de teste do Sistema de Cobrança.\r\nSe você recebeu esta mensagem, a configuração SMTP está funcionando corretamente.\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:30px;background:#f4f6f9;font-family:Arial,sans-serif;"><div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);"><h2 style="color:#198754;">✓ Teste SMTP</h2><p style="color:#555;">Este é um e-mail de teste do <strong>Sistema de Cobrança</strong>.</p><p style="color:#555;">Se você recebeu esta mensagem, a configuração SMTP está funcionando corretamente.</p><hr style="border:none;border-top:1px solid #eee;margin:20px 0;"><small style="color:#999;">Enviado em ' . date('d/m/Y H:i:s') . '</small></div></body></html>';
    $body .= "\r\n\r\n";
    $body .= "--{$boundary}--\r\n";
    $body .= ".\r\n";

    @fputs($connexion, $body);
    $response = @fgets($connexion, 512);

    @fputs($connexion, "QUIT\r\n");
    @fclose($connexion);

    if (strpos($response, '250') !== false || strpos($response, '2') !== false) {
        return ['sucesso' => true, 'mensagem' => "E-mail de teste enviado com sucesso para {$testEmail}! Verifique sua caixa de entrada."];
    }

    return ['sucesso' => true, 'mensagem' => "Conexão SMTP estabelecida e e-mail enviado para {$testEmail}. Verifique sua caixa de entrada (e spam)."];
}
}

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'smtp') {
        salvarConfigGlobal('smtp_host', trim($_POST['smtp_host'] ?? ''));
        salvarConfigGlobal('smtp_port', trim($_POST['smtp_port'] ?? '587'));
        salvarConfigGlobal('smtp_usuario', trim($_POST['smtp_usuario'] ?? ''));
        salvarConfigGlobal('smtp_senha', (string)($_POST['smtp_senha'] ?? ''));
        salvarConfigGlobal('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        salvarConfigGlobal('smtp_from_nome', trim($_POST['smtp_from_nome'] ?? 'Sistema de Cobrança'));
        salvarConfigGlobal('smtp_ssl', $_POST['smtp_ssl'] ?? 'tls');
        $mensagem = 'Configuração SMTP global salva com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'testar_smtp') {
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = intval($_POST['smtp_port'] ?? 587);
        $smtpUser = trim($_POST['smtp_usuario'] ?? '');
        $smtpPass = $_POST['smtp_senha'] ?? '';
        $smtpFrom = trim($_POST['smtp_from_email'] ?? '');
        $smtpNome = trim($_POST['smtp_from_nome'] ?? 'Sistema de Cobrança');
        $smtpSsl  = $_POST['smtp_ssl'] ?? 'tls';
        $testEmail = trim($_POST['smtp_test_email'] ?? '');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($testEmail)) {
            $mensagem = 'Preencha todos os campos obrigatórios antes de testar.';
            $tipo = 'danger';
        } else {
            $resultado = testarConexaoSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $testEmail);
            $mensagem = $resultado['mensagem'];
            $tipo = $resultado['sucesso'] ? 'success' : 'danger';
        }
    }
}

$smtp = [
    'smtp_host'       => getConfigGlobal('smtp_host', ''),
    'smtp_port'       => getConfigGlobal('smtp_port', '587'),
    'smtp_usuario'    => getConfigGlobal('smtp_usuario', ''),
    'smtp_senha'      => getConfigGlobal('smtp_senha', ''),
    'smtp_from_email' => getConfigGlobal('smtp_from_email', ''),
    'smtp_from_nome'  => getConfigGlobal('smtp_from_nome', 'Sistema de Cobrança'),
    'smtp_ssl'        => getConfigGlobal('smtp_ssl', 'tls'),
];

$pageTitle = 'SMTP Global';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-server me-1"></i> SMTP Global</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show py-2">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-server me-2"></i>Configurações SMTP Global</h6>
                    <div class="alert alert-info py-2 mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Este SMTP é <strong>global</strong> e usado nos e-mails enviados pelo sistema (ex.: boas-vindas ao criar um novo admin).
                        Cada admin continua configurando o <strong>seu próprio SMTP</strong> no painel dele (Config. de Envios).
                    </div>
                    <form method="POST">
                        <input type="hidden" name="acao" value="smtp">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Host SMTP *</label>
                                <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($smtp['smtp_host']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Porta</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($smtp['smtp_port']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Usuário SMTP *</label>
                                <input type="text" name="smtp_usuario" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($smtp['smtp_usuario']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Senha SMTP *</label>
                                <input type="password" name="smtp_senha" class="form-control" placeholder="Sua senha ou senha de app" value="<?= htmlspecialchars($smtp['smtp_senha']) ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">E-mail Remetente *</label>
                                <input type="email" name="smtp_from_email" class="form-control" placeholder="noreply@seudominio.com" value="<?= htmlspecialchars($smtp['smtp_from_email']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome Remetente</label>
                                <input type="text" name="smtp_from_nome" class="form-control" value="<?= htmlspecialchars($smtp['smtp_from_nome']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Criptografia</label>
                                <select name="smtp_ssl" class="form-select">
                                    <option value="tls" <?= $smtp['smtp_ssl'] === 'tls' ? 'selected' : '' ?>>TLS (recomendado)</option>
                                    <option value="ssl" <?= $smtp['smtp_ssl'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= $smtp['smtp_ssl'] === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-1"></i> Salvar SMTP
                        </button>
                    </form>

                    <hr class="my-3">
                    <h6 class="mb-3"><i class="fas fa-vial me-2"></i>Testar Conexão</h6>
                    <form method="POST">
                        <input type="hidden" name="acao" value="testar_smtp">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">E-mail de teste</label>
                                <input type="email" name="smtp_test_email" class="form-control" placeholder="seu@email.com" required value="<?= htmlspecialchars($smtp['smtp_from_email']) ?>">
                                <small class="text-muted">Um e-mail de teste será enviado para este endereço</small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-paper-plane me-1"></i> Testar
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="smtp_host" value="<?= htmlspecialchars($smtp['smtp_host']) ?>">
                        <input type="hidden" name="smtp_port" value="<?= htmlspecialchars($smtp['smtp_port']) ?>">
                        <input type="hidden" name="smtp_usuario" value="<?= htmlspecialchars($smtp['smtp_usuario']) ?>">
                        <input type="hidden" name="smtp_senha" value="<?= htmlspecialchars($smtp['smtp_senha']) ?>">
                        <input type="hidden" name="smtp_from_email" value="<?= htmlspecialchars($smtp['smtp_from_email']) ?>">
                        <input type="hidden" name="smtp_from_nome" value="<?= htmlspecialchars($smtp['smtp_from_nome']) ?>">
                        <input type="hidden" name="smtp_ssl" value="<?= htmlspecialchars($smtp['smtp_ssl']) ?>">
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-universal-access me-2"></i>Fluxo Global</h6>
                    </div>
                    <div class="p-3">
                        <ul class="small text-muted mb-0" style="padding-left: 18px;">
                            <li class="mb-2">O SMTP global é único para todo o SaaS (Super Admin).</li>
                            <li class="mb-2">E-mails de sistema enviados pela plataforma (ex.: boas-vindas a novos admins) usam este SMTP.</li>
                            <li class="mb-2">Faturas e régua de cobrança usam o SMTP configurado por cada admin no painel dele.</li>
                            <li class="mb-2">Configure e teste aqui antes de usar o <strong>Template de email</strong> (boas-vindas).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.querySelectorAll('form').forEach(function(f) {
    f.addEventListener('submit', function() {
        var acao = this.querySelector('[name="acao"]');
        if (acao && acao.value === 'testar_smtp') {
            var fields = ['smtp_host','smtp_port','smtp_usuario','smtp_senha','smtp_from_email','smtp_from_nome','smtp_ssl'];
            var card = this.closest('.form-card');
            var saveForm = card ? card.querySelector('form:first-of-type') : null;
            if (saveForm) {
                var self = this;
                fields.forEach(function(k) {
                    var s = saveForm.querySelector('[name="'+k+'"]');
                    var t = self.querySelector('[name="'+k+'"]');
                    if (s && t) t.value = s.value;
                });
            }
        }
    });
});
</script>