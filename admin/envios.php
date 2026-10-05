<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $bloqueado = !planoPermiteEnvio('email') || !planoPermiteEnvio('whatsapp');

    if ($acao === 'smtp' && !$bloqueado) {
        $campos = ['smtp_host', 'smtp_port', 'smtp_usuario', 'smtp_from_email', 'smtp_from_nome', 'smtp_ssl'];
        foreach ($campos as $campo) {
            saveConfig($campo, trim($_POST[$campo] ?? ''));
        }
        $senha = trim($_POST['smtp_senha'] ?? '');
        if ($senha !== '') {
            saveConfig('smtp_senha', $senha);
            $mensagem = 'Configurações SMTP salvas com sucesso!';
        } else {
            $mensagem = 'Configurações SMTP salvas com sucesso! A senha não foi alterada.';
        }
        $tipo = 'success';
    }

    if ($acao === 'testar_smtp' && !$bloqueado) {
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = intval($_POST['smtp_port'] ?? 587);
        $smtpUser = trim($_POST['smtp_usuario'] ?? '');
        $smtpFrom = trim($_POST['smtp_from_email'] ?? '');
        $smtpNome = trim($_POST['smtp_from_nome'] ?? 'Sistema de Cobrança');
        $smtpSsl  = $_POST['smtp_ssl'] ?? 'tls';
        $testEmail = trim($_POST['smtp_test_email'] ?? '');

        $senhaPost = trim($_POST['smtp_senha'] ?? '');
        $smtpPass = $senhaPost !== '' ? $senhaPost : (string) (getConfig('smtp_senha') ?? '');

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

    if ($acao === 'envio' && !$bloqueado) {
        $envioHora = trim($_POST['envio_hora'] ?? '08:00');
        $cronAtivo = isset($_POST['cron_envio_ativo']) ? '1' : '0';

        saveConfig('envio_hora', $envioHora);
        saveConfig('cron_envio_ativo', $cronAtivo);

        $camposRegua = [
            'regua_1_enviar_geracao' => isset($_POST['regua_1_enviar_geracao']) ? '1' : '0',
            'regua_2_dias_antes'     => intval($_POST['regua_2_dias_antes'] ?? 0),
            'regua_3_dias_antes'     => intval($_POST['regua_3_dias_antes'] ?? 0),
            'regua_4_no_vencimento'  => isset($_POST['regua_4_no_vencimento']) ? '1' : '0',
            'regua_5_dias_depois'    => intval($_POST['regua_5_dias_depois'] ?? 0),
        ];

        foreach ($camposRegua as $chave => $valor) {
            saveConfig($chave, $valor);
        }

        $mensagem = 'Régua de cobrança salva com sucesso!';
        $tipo = 'success';
    }
}

// Plano restrito (demo) -> e-mail e WhatsApp bloqueados
$demoBloqueado = !planoPermiteEnvio('email') || !planoPermiteEnvio('whatsapp');
if ($demoBloqueado) {
    $mensagem = 'As configurações de e-mail e WhatsApp estão bloqueadas na conta de demonstração. Assine um plano para liberar.';
    $tipo = 'warning';
}

$config = getAllConfig();

$pageTitle = 'Config. de Envios';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Config. de Envios</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- CONFIGURAÇÕES SMTP -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-server me-2"></i>Configurações SMTP</h6>
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
                                <input type="text" name="smtp_host" id="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Porta</label>
                                <input type="number" name="smtp_port" id="smtp_port" class="form-control" value="<?= htmlspecialchars($config['smtp_port'] ?? '587') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Usuário SMTP *</label>
                                <input type="text" name="smtp_usuario" id="smtp_usuario" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Senha SMTP *</label>
                                <input type="password" name="smtp_senha" id="smtp_senha" class="form-control" autocomplete="new-password" placeholder="<?= !empty($config['smtp_senha']) ? 'Senha já salva — deixe em branco para mantê-la' : 'Senha ou senha de aplicativo' ?>">
                                <?php if (!empty($config['smtp_senha'])): ?>
                                    <small class="text-muted">Já existe uma senha gravada. Ela não é exibida por segurança; preencha aqui somente para trocá-la.</small>
                                <?php endif; ?>
                                <?php
                                $hostGuia = strtolower((string)($config['smtp_host'] ?? ''));
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
                                <input type="email" name="smtp_from_email" id="smtp_from_email" class="form-control" placeholder="noreply@seudominio.com" value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome Remetente</label>
                                <input type="text" name="smtp_from_nome" id="smtp_from_nome" class="form-control" value="<?= htmlspecialchars($config['smtp_from_nome'] ?? 'Sistema de Cobrança') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Criptografia</label>
                                <select name="smtp_ssl" class="form-select">
                                    <option value="tls" <?= ($config['smtp_ssl'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (recomendado)</option>
                                    <option value="ssl" <?= ($config['smtp_ssl'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= ($config['smtp_ssl'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma</option>
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
                                <input type="email" name="smtp_test_email" class="form-control" placeholder="seu@email.com" required value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>">
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

            <!-- CONFIG DE ENVIO -->
            <div class="col-lg-6">
                <!-- REGUA DE COBRANCA -->
                <div class="form-card mb-4">
                    <h6 class="mb-3"><i class="fas fa-envelope me-2"></i>Configurações de Envio</h6>
                    <form method="POST">
                        <input type="hidden" name="acao" value="envio">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="cron_envio_ativo" id="cronAtivo" <?= ($config['cron_envio_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="cronAtivo">Ativar envio automático de e-mails</label>
                                </div>
                            </div>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-12">
                                <small class="text-muted"><i class="fas fa-list-ol me-1"></i> Régua de Cobrança</small>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="regua_1_enviar_geracao" id="regua1" <?= ($config['regua_1_enviar_geracao'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="regua1"><strong>1º Envio</strong> — E-mail no momento que a fatura é gerada</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="regua_2_check" id="regua2Check" <?= intval($config['regua_2_dias_antes'] ?? 0) > 0 ? 'checked' : '' ?> onchange="document.getElementById('regua2Dias').disabled=!this.checked">
                                    <label class="form-check-label" for="regua2Check"><strong>2º Envio</strong> — 1º Lembrete antes do vencimento</label>
                                </div>
                                <div class="ms-4 mt-1">
                                    <div class="input-group input-group-sm" style="max-width:300px;">
                                        <input type="number" name="regua_2_dias_antes" id="regua2Dias" class="form-control" min="1" max="60" value="<?= htmlspecialchars($config['regua_2_dias_antes'] ?? '15') ?>" <?= intval($config['regua_2_dias_antes'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                        <span class="input-group-text">dias antes do vencimento</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="regua_3_check" id="regua3Check" <?= intval($config['regua_3_dias_antes'] ?? 0) > 0 ? 'checked' : '' ?> onchange="document.getElementById('regua3Dias').disabled=!this.checked">
                                    <label class="form-check-label" for="regua3Check"><strong>3º Envio</strong> — 2º Lembrete antes do vencimento</label>
                                </div>
                                <div class="ms-4 mt-1">
                                    <div class="input-group input-group-sm" style="max-width:300px;">
                                        <input type="number" name="regua_3_dias_antes" id="regua3Dias" class="form-control" min="1" max="60" value="<?= htmlspecialchars($config['regua_3_dias_antes'] ?? '7') ?>" <?= intval($config['regua_3_dias_antes'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                        <span class="input-group-text">dias antes do vencimento</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="regua_4_no_vencimento" id="regua4" <?= ($config['regua_4_no_vencimento'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="regua4"><strong>4º Envio</strong> — Lembrete final no dia do vencimento</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="regua_5_check" id="regua5Check" <?= intval($config['regua_5_dias_depois'] ?? 0) > 0 ? 'checked' : '' ?> onchange="document.getElementById('regua5Dias').disabled=!this.checked">
                                    <label class="form-check-label" for="regua5Check"><strong>5º Envio</strong> — Fatura em atraso</label>
                                </div>
                                <div class="ms-4 mt-1">
                                    <div class="input-group input-group-sm" style="max-width:300px;">
                                        <input type="number" name="regua_5_dias_depois" id="regua5Dias" class="form-control" min="1" max="90" value="<?= htmlspecialchars($config['regua_5_dias_depois'] ?? '3') ?>" <?= intval($config['regua_5_dias_depois'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                        <span class="input-group-text">dias depois do vencimento</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-sm-6">
                                <label class="form-label">Horário de envio</label>
                                <input type="time" name="envio_hora" class="form-control" value="<?= htmlspecialchars($config['envio_hora'] ?? '08:00') ?>">
                                <small class="text-muted">Janela de 1h (a partir deste horário) em que os e-mails/WhatsApp são enviados</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-1"></i> Salvar Régua de Cobrança
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

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

<?php
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
    $response = @fgets($connexion, 512);

    @fputs($connexion, "RCPT TO:<{$testEmail}>\r\n");
    $response = @fgets($connexion, 512);
    if (substr($response, 0, 3) !== '250') {
        @fclose($connexion);
        return ['sucesso' => false, 'mensagem' => "E-mail de destino inválido ou recusado."];
    }

    @fputs($connexion, "DATA\r\n");
    $response = @fgets($connexion, 512);

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
?>
