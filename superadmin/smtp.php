<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';

if (!function_exists('testarConexaoSmtp')) {
function testarConexaoSmtp($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $testEmail) {
    $connexion = null;
    $ehloResponse = '';
    $erroConexao = '';
    list($connexion, $ehloResponse, $erroConexao) = smtpConectar($host, $port, $ssl);
    if (!$connexion) {
        return ['sucesso' => false, 'mensagem' => $erroConexao];
    }

    $erroAuth = '';
    if (!smtpAutenticar($connexion, $ehloResponse, $user, $pass, $erroAuth)) {
        @fclose($connexion);
        return ['sucesso' => false, 'mensagem' => $erroAuth];
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
        $senha = trim((string)($_POST['smtp_senha'] ?? ''));
        if ($senha !== '') {
            salvarConfigGlobal('smtp_senha', $senha);
        }
        salvarConfigGlobal('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        salvarConfigGlobal('smtp_from_nome', trim($_POST['smtp_from_nome'] ?? 'Sistema de Cobrança'));
        salvarConfigGlobal('smtp_ssl', $_POST['smtp_ssl'] ?? 'tls');
        $mensagem = $senha !== ''
            ? 'Configuração SMTP global salva com sucesso!'
            : 'Configuração SMTP global salva com sucesso! A senha não foi alterada.';
        $tipo = 'success';
    }

    if ($acao === 'testar_smtp') {
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = intval($_POST['smtp_port'] ?? 587);
        $smtpUser = trim($_POST['smtp_usuario'] ?? '');
        $smtpFrom = trim($_POST['smtp_from_email'] ?? '');
        $smtpNome = trim($_POST['smtp_from_nome'] ?? 'Sistema de Cobrança');
        $smtpSsl  = $_POST['smtp_ssl'] ?? 'tls';
        $testEmail = trim($_POST['smtp_test_email'] ?? '');

        $senhaPost = trim((string)($_POST['smtp_senha'] ?? ''));
        $smtpPass = $senhaPost !== '' ? $senhaPost : (string) getConfigGlobal('smtp_senha', '');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($testEmail)) {
            $mensagem = 'Preencha todos os campos obrigatórios antes de testar.';
            $tipo = 'danger';
        } elseif ($smtpPass === '') {
            $mensagem = 'Informe a senha SMTP: ainda não há senha salva e o campo ficou vazio.';
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
                    <form method="POST" id="formSmtp">
                        <input type="hidden" name="acao" value="smtp">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Provedor</label>
                                <select id="presetProvedor" class="form-select">
                                    <option value="">Personalizado (preencher manualmente)</option>
                                    <option value="gmail">Gmail / Google Workspace</option>
                                    <option value="outlook">Outlook / Hotmail / Microsoft 365</option>
                                    <option value="emailarray">EmailArray</option>
                                    <option value="ses">Amazon SES</option>
                                    <option value="zoho">Zoho Mail</option>
                                </select>
                                <small class="text-muted" id="avisoProvedor"></small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Host SMTP *</label>
                                <input type="text" name="smtp_host" id="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($smtp['smtp_host']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Porta</label>
                                <input type="number" name="smtp_port" id="smtp_port" class="form-control" value="<?= htmlspecialchars($smtp['smtp_port']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Usuário SMTP *</label>
                                <input type="text" name="smtp_usuario" id="smtp_usuario" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($smtp['smtp_usuario']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Senha SMTP *</label>
                                <input type="password" name="smtp_senha" id="smtp_senha" class="form-control" autocomplete="new-password" placeholder="<?= $smtp['smtp_senha'] !== '' ? 'Senha já salva — deixe em branco para mantê-la' : 'Senha ou senha de aplicativo' ?>">
                                <?php if ($smtp['smtp_senha'] !== ''): ?>
                                    <small class="text-muted">Já existe uma senha gravada. Ela não é exibida por segurança; preencha aqui somente para trocá-la.</small>
                                <?php endif; ?>
                                <?php
                                $hostGuia = strtolower((string)($smtp['smtp_host'] ?? ''));
                                $mostrarGuiaGmail = strpos($hostGuia, 'gmail') !== false || strpos($hostGuia, 'google') !== false;
                                ?>
                                <details class="mt-2" id="ajudaSenhaSmtp" style="<?= $mostrarGuiaGmail ? '' : 'display:none' ?>">
                                    <summary class="text-primary" style="cursor:pointer"><i class="fas fa-circle-question me-1"></i>Como criar a senha de aplicativo do Gmail</summary>
                                    <div class="border rounded p-3 mt-2 bg-light" style="font-size:.9rem">
                                        <p class="mb-2">O Gmail exige senha de aplicativo sempre que a conta usa verificação em 2 etapas. A senha normal da conta não funciona.</p>
                                        <ol class="mb-2 ps-3">
                                            <li>Abra <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a> e entre na conta que será o remetente.</li>
                                            <li>Se aparecer o aviso de verificação em 2 etapas, ative primeiro em <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">myaccount.google.com/security</a>.</li>
                                            <li>No campo <em>Nome do app</em> escreva um nome livre (ex.: <code>Cobranca</code>) e clique em <strong>Criar</strong>.</li>
                                            <li>Copie os <strong>16 caracteres</strong> (o Google mostra em 4 blocos de 4) e cole no campo acima <strong>sem os espaços</strong>.</li>
                                            <li>Clique em <strong>Salvar SMTP</strong> e depois em <strong>Testar</strong>.</li>
                                        </ol>
                                        <p class="mb-0 small text-muted">O Google só exibe a senha uma vez: se perder, gere outra. Em contas de empresa (Workspace) a tela pode vir bloqueada por política do administrador.</p>
                                        <hr>
                                        <p class="mb-0 small"><strong>Como ler o erro do teste:</strong>
                                            <code>534-5.7.9</code> a conta está correta, mas o Gmail exige senha de aplicativo.
                                            <code>535-5.7.8</code> a senha não pertence a essa conta.
                                        </p>
                                    </div>
                                </details>
                                <script>
                                (function () {
                                    var sel = document.getElementById('presetProvedor');
                                    var host = document.getElementById('smtp_host');
                                    var bloco = document.getElementById('ajudaSenhaSmtp');
                                    if (!bloco || !host) { return; }
                                    function eHostGmail(h) {
                                        h = (h || '').toLowerCase();
                                        return h.indexOf('gmail') > -1 || h.indexOf('google') > -1;
                                    }
                                    function aplicar() {
                                        var mostrar = (sel && sel.value === 'gmail') || eHostGmail(host.value);
                                        bloco.style.display = mostrar ? '' : 'none';
                                    }
                                    if (sel) { sel.addEventListener('change', aplicar); }
                                    host.addEventListener('input', aplicar);
                                    aplicar();
                                })();
                                </script>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">E-mail Remetente *</label>
                                <input type="email" name="smtp_from_email" id="smtp_from_email" class="form-control" placeholder="noreply@seudominio.com" value="<?= htmlspecialchars($smtp['smtp_from_email']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome Remetente</label>
                                <input type="text" name="smtp_from_nome" id="smtp_from_nome" class="form-control" value="<?= htmlspecialchars($smtp['smtp_from_nome']) ?>">
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
                    <form method="POST" id="formTesteSmtp">
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
                        <input type="hidden" name="smtp_host" id="t_host">
                        <input type="hidden" name="smtp_port" id="t_port">
                        <input type="hidden" name="smtp_usuario" id="t_usuario">
                        <input type="hidden" name="smtp_from_email" id="t_from">
                        <input type="hidden" name="smtp_from_nome" id="t_nome">
                        <input type="hidden" name="smtp_ssl" id="t_ssl">
                        <input type="hidden" name="smtp_senha" id="t_senha" value="">
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
(function () {
    var formSmtp = document.getElementById('formSmtp');
    var formTeste = document.getElementById('formTesteSmtp');
    if (!formSmtp || !formTeste) { return; }

    var mapa = { smtp_host: 't_host', smtp_port: 't_port', smtp_usuario: 't_usuario',
                 smtp_from_email: 't_from', smtp_from_nome: 't_nome', smtp_ssl: 't_ssl',
                 smtp_senha: 't_senha' };

    function sincronizar() {
        Object.keys(mapa).forEach(function (k) {
            var origem = formSmtp.querySelector('[name="' + k + '"]');
            var destino = document.getElementById(mapa[k]);
            if (origem && destino) { destino.value = origem.value; }
        });
    }
    formSmtp.addEventListener('input', sincronizar);
    formTeste.addEventListener('submit', sincronizar);
    sincronizar();

    var PRESETS = {
        gmail:      { host: 'smtp.gmail.com',        port: '587', ssl: 'tls',
                      aviso: 'Com verificação em 2 etapas ativa o Gmail exige senha de aplicativo (Conta > Segurança > Verificação em 2 etapas > Senhas de app).' },
        outlook:    { host: 'smtp-mail.outlook.com',  port: '587', ssl: 'tls',
                      aviso: 'Use a senha da conta. Se a conta usa 2 etapas, o Outlook também exige senha de aplicativo.' },
        emailarray: { host: 'smtp.emailarray.com',   port: '587', ssl: 'tls', aviso: '' },
        ses:        { host: 'email-smtp.us-east-1.amazonaws.com', port: '587', ssl: 'tls',
                      aviso: 'O remetente precisa ser verificado no Amazon SES.' },
        zoho:       { host: 'smtp.zoho.com',          port: '465', ssl: 'ssl',
                      aviso: 'Zoho usa 465 com SSL implícito. Zoho Accounts pede gerar senha específica para SMTP.' }
    };

    var sel = document.getElementById('presetProvedor');
    var aviso = document.getElementById('avisoProvedor');
    sel.addEventListener('change', function () {
        var p = PRESETS[sel.value];
        if (!p) { aviso.textContent = ''; return; }
        formSmtp.querySelector('[name="smtp_host"]').value = p.host;
        formSmtp.querySelector('[name="smtp_port"]').value = p.port;
        formSmtp.querySelector('[name="smtp_ssl"]').value = p.ssl;
        aviso.textContent = p.aviso;
        sincronizar();
    });

    var selSsl = formSmtp.querySelector('[name="smtp_ssl"]');
    selSsl.addEventListener('change', function () {
        if (sel.value) { sel.value = ''; aviso.textContent = ''; }
    });
})();
</script>