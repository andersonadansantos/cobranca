<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'smtp') {
        $campos = ['smtp_host', 'smtp_port', 'smtp_usuario', 'smtp_senha', 'smtp_from_email', 'smtp_from_nome', 'smtp_ssl'];
        $pdo = getConnection();
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$campo, $valor, $valor]);
        }
        $mensagem = 'Configurações SMTP salvas com sucesso!';
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

    if ($acao === 'envio') {
        $pdo = getConnection();
        $envioHora = trim($_POST['envio_hora'] ?? '08:00');
        $cronAtivo = isset($_POST['cron_envio_ativo']) ? '1' : '0';

        $camposRegua = [
            'regua_1_enviar_geracao' => isset($_POST['regua_1_enviar_geracao']) ? '1' : '0',
            'regua_2_dias_antes'     => intval($_POST['regua_2_dias_antes'] ?? 0),
            'regua_3_dias_antes'     => intval($_POST['regua_3_dias_antes'] ?? 0),
            'regua_4_no_vencimento'  => isset($_POST['regua_4_no_vencimento']) ? '1' : '0',
            'regua_5_dias_depois'    => intval($_POST['regua_5_dias_depois'] ?? 0),
        ];

        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['envio_hora', $envioHora, $envioHora]);
        $stmt->execute(['cron_envio_ativo', $cronAtivo, $cronAtivo]);

        foreach ($camposRegua as $chave => $valor) {
            $stmt->execute([$chave, $valor, $valor]);
        }

        $mensagem = 'Régua de cobrança salva com sucesso!';
        $tipo = 'success';
    }
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
                    <form method="POST">
                        <input type="hidden" name="acao" value="smtp">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Host SMTP *</label>
                                <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Porta</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($config['smtp_port'] ?? '587') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Usuário SMTP *</label>
                                <input type="text" name="smtp_usuario" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Senha SMTP *</label>
                                <input type="password" name="smtp_senha" class="form-control" placeholder="Sua senha ou senha de app" value="<?= htmlspecialchars($config['smtp_senha'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">E-mail Remetente *</label>
                                <input type="email" name="smtp_from_email" class="form-control" placeholder="noreply@seudominio.com" value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome Remetente</label>
                                <input type="text" name="smtp_from_nome" class="form-control" value="<?= htmlspecialchars($config['smtp_from_nome'] ?? 'Sistema de Cobrança') ?>">
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
                    <form method="POST">
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
                        <input type="hidden" name="smtp_host" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>">
                        <input type="hidden" name="smtp_port" value="<?= htmlspecialchars($config['smtp_port'] ?? '587') ?>">
                        <input type="hidden" name="smtp_usuario" value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>">
                        <input type="hidden" name="smtp_senha" value="<?= htmlspecialchars($config['smtp_senha'] ?? '') ?>">
                        <input type="hidden" name="smtp_from_email" value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>">
                        <input type="hidden" name="smtp_from_nome" value="<?= htmlspecialchars($config['smtp_from_nome'] ?? '') ?>">
                        <input type="hidden" name="smtp_ssl" value="<?= htmlspecialchars($config['smtp_ssl'] ?? 'tls') ?>">
                    </form>
                </div>
            </div>

            <!-- CRON + CONFIG DE ENVIO -->
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
                                <small class="text-muted">Horário que o CRON executará os envios</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-1"></i> Salvar Régua de Cobrança
                        </button>
                    </form>
                </div>

                <!-- CRON -->
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Configuração CRON</h6>
                    <p class="text-muted small mb-3">Adicione a linha abaixo ao CRON do servidor para envio automático:</p>
                    <div class="bg-dark text-light p-3 rounded mb-3" style="font-family: monospace; font-size: 0.82rem; word-break: break-all;">
                        <?php
                        $horaEnvio = $config['envio_hora'] ?? '08:00';
                        $horaCron = date('H', strtotime($horaEnvio));
                        $minCron = date('i', strtotime($horaEnvio));
                        ?>
                        <?= htmlspecialchars($config['cron_envio_ativo'] === '1' ? "{$minCron} {$horaCron} * * * php " . __DIR__ . '/../api/cron_envio.php' : '# Envio automático desativado') ?>
                    </div>
                    <div class="bg-light p-3 rounded mb-3">
                        <small class="text-muted d-block mb-1"><strong>Como funciona:</strong></small>
                        <small class="text-muted d-block">• Executa todo dia às <?= htmlspecialchars($horaEnvio) ?></small>
                        <small class="text-muted d-block">• Envia conforme a régua de cobrança configurada</small>
                        <small class="text-muted d-block">• Não envia o mesmo tipo de e-mail duas vezes para a mesma fatura no mesmo dia</small>
                    </div>
                    <?php if (($config['cron_envio_ativo'] ?? '0') === '1'): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Envio automático ativo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="fas fa-times me-1"></i> Envio automático desativado</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

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

<?php
function testarConexaoSmtp($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $testEmail) {
    $errno = 0;
    $errstr = '';

    $proto = ($ssl === 'ssl') ? 'ssl://' : '';
    $connexion = @fsockopen($proto . $host, $port, $errno, $errstr, 10);

    if (!$connexion) {
        return ['sucesso' => false, 'mensagem' => "Falha ao conectar: {$errstr} (código {$errno})"];
    }

    $response = @fgets($connexion, 512);
    $banner = $response;

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

    if ($authPlain) {
        @fputs($connexion, "AUTH PLAIN\r\n");
        $response = @fgets($connexion, 512);
        if (substr($response, 0, 3) === '334') {
            @fputs($connexion, base64_encode("\0" . $user . "\0" . $pass) . "\r\n");
            $response = @fgets($connexion, 512);
            if (substr($response, 0, 3) === '235') {
                $authOk = true;
            }
        }
    }

    if (empty($authOk) && $authLogin) {
        @fputs($connexion, "AUTH LOGIN\r\n");
        $response = @fgets($connexion, 512);
        if (substr($response, 0, 3) === '334') {
            @fputs($connexion, base64_encode($user) . "\r\n");
            $response = @fgets($connexion, 512);
            if (substr($response, 0, 3) === '334') {
                @fputs($connexion, base64_encode($pass) . "\r\n");
                $response = @fgets($connexion, 512);
                if (substr($response, 0, 3) === '235') {
                    $authOk = true;
                }
            }
        }
    }

    if (empty($authOk)) {
        @fclose($connexion);
        return ['sucesso' => false, 'mensagem' => "Servidor não aceitou AUTH PLAIN nem AUTH LOGIN. Verifique host, porta e criptografia."];
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
