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
            $mensagem = 'Configurações SMTP salvas com sucesso! A senha atual foi mantida.';
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
        $smtpPass = $senhaPost !== '' ? $senhaPost : (string)(getConfig('smtp_senha') ?? '');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($testEmail)) {
            $mensagem = 'Preencha todos os campos obrigatórios antes de testar.';
            $tipo = 'danger';
        } elseif ($smtpPass === '') {
            $mensagem = 'Informe a senha SMTP antes de realizar o teste.';
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

        $mensagem = 'Régua de cobrança automática salva com sucesso!';
        $tipo = 'success';
    }
}

$demoBloqueado = !planoPermiteEnvio('email') || !planoPermiteEnvio('whatsapp');
if ($demoBloqueado) {
    $mensagem = 'As configurações de e-mail e WhatsApp estão bloqueadas na conta de demonstração. Assine um plano para liberar.';
    $tipo = 'warning';
}

$config = getAllConfig();

$pageTitle = 'Régua de Cobrança & Envios';
$pageCategory = 'Automações & APIs';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Régua de Cobrança & Configurações de Envio</h2>
            <p class="text-muted small mb-0">Parametrize o servidor SMTP e os gatilhos automáticos de cobrança antes e após o vencimento.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (($config['cron_envio_ativo'] ?? '0') === '1'): ?>
                <span class="fintech-badge success"><span class="dot"></span> Envio Automático Ativo</span>
            <?php else: ?>
                <span class="fintech-badge neutral"><span class="dot"></span> Envio Automático Pausado</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : ($tipo === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <div><?= htmlspecialchars($mensagem) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- SMTP Settings & Test -->
        <div class="col-lg-6">
            <div class="fintech-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h3 class="fintech-card-title mb-0">Servidor de E-mail (SMTP)</h3>
                        <small class="text-muted">Utilize suas credenciais profissionais de e-mail</small>
                    </div>
                    <i class="bi bi-envelope-at fs-4 text-primary"></i>
                </div>

                <form method="POST" id="formSmtp">
                    <input type="hidden" name="acao" value="smtp">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Predefinição de Provedor</label>
                            <select id="presetProvedor" class="form-select">
                                <option value="">Personalizado (preencher manualmente)</option>
                                <option value="gmail">Gmail / Google Workspace</option>
                                <option value="outlook">Outlook / Microsoft 365</option>
                                <option value="emailarray">EmailArray</option>
                                <option value="ses">Amazon SES</option>
                                <option value="zoho">Zoho Mail</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-muted small fw-semibold">Host SMTP *</label>
                            <input type="text" name="smtp_host" id="smtp_host" class="form-control font-monospace" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Porta</label>
                            <input type="number" name="smtp_port" id="smtp_port" class="form-control font-monospace" value="<?= htmlspecialchars($config['smtp_port'] ?? '587') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Usuário / E-mail de Autenticação *</label>
                            <input type="text" name="smtp_usuario" id="smtp_usuario" class="form-control" placeholder="seu@email.com" value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Senha SMTP *</label>
                            <input type="password" name="smtp_senha" id="smtp_senha" class="form-control" autocomplete="new-password" placeholder="<?= !empty($config['smtp_senha']) ? '•••••••••••• (deixe em branco para manter)' : 'Senha ou senha de app' ?>">
                            <small class="text-muted">Para o Gmail, use uma <strong>Senha de Aplicativo</strong> de 16 caracteres.</small>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label text-muted small fw-semibold">E-mail Remetente *</label>
                            <input type="email" name="smtp_from_email" id="smtp_from_email" class="form-control" placeholder="noreply@seudominio.com" value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-muted small fw-semibold">Nome Remetente</label>
                            <input type="text" name="smtp_from_nome" id="smtp_from_nome" class="form-control" value="<?= htmlspecialchars($config['smtp_from_nome'] ?? 'Sistema de Cobrança') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Criptografia</label>
                            <select name="smtp_ssl" class="form-select">
                                <option value="tls" <?= ($config['smtp_ssl'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recomendado)</option>
                                <option value="ssl" <?= ($config['smtp_ssl'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= ($config['smtp_ssl'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-fintech-primary px-4">
                            <i class="bi bi-save me-1"></i> Salvar SMTP
                        </button>
                    </div>
                </form>
            </div>

            <!-- Teste SMTP -->
            <div class="fintech-card p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h3 class="fintech-card-title mb-0">Testar Conexão de E-mail</h3>
                        <small class="text-muted">Envie um e-mail de teste para validar a entrega</small>
                    </div>
                    <i class="bi bi-send-check fs-4 text-primary"></i>
                </div>
                <form method="POST" id="formTesteSmtp">
                    <input type="hidden" name="acao" value="testar_smtp">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small fw-semibold">E-mail de Destino</label>
                            <input type="email" name="smtp_test_email" class="form-control" placeholder="seu@email.com" required value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-fintech-secondary w-100 py-2">
                                <i class="bi bi-play-circle me-1"></i> Testar Agora
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Régua de Cobrança Automática -->
        <div class="col-lg-6">
            <div class="fintech-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h3 class="fintech-card-title mb-0">Régua de Notificações</h3>
                        <small class="text-muted">Automação de lembretes e avisos por e-mail</small>
                    </div>
                    <i class="bi bi-clock-history fs-4 text-primary"></i>
                </div>

                <form method="POST">
                    <input type="hidden" name="acao" value="envio">

                    <div class="p-3 mb-4 rounded-3" style="background:#f8f9ff; border:1px solid #eef2f6;">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="cron_envio_ativo" id="cronAtivoV2" <?= ($config['cron_envio_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-slate-900" for="cronAtivoV2">Habilitar Régua Automática</label>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <label class="text-muted small fw-semibold mb-0">Horário de Disparo:</label>
                            <input type="time" name="envio_hora" class="form-control form-control-sm text-center font-monospace" style="width:110px;" value="<?= htmlspecialchars($config['envio_hora'] ?? '08:00') ?>">
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <!-- Etapa 1 -->
                        <div class="p-3 rounded-3 border" style="background:#fff;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="regua_1_enviar_geracao" id="regua1V2" <?= ($config['regua_1_enviar_geracao'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-slate-900" for="regua1V2">
                                    <span class="badge bg-light text-dark border me-1">1º Disparo</span>
                                    No momento em que a fatura é criada
                                </label>
                            </div>
                            <small class="text-muted d-block ps-4 mt-1">O cliente recebe imediatamente o link de pagamento assim que a fatura é cadastrada.</small>
                        </div>

                        <!-- Etapa 2 -->
                        <div class="p-3 rounded-3 border" style="background:#fff;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="regua_2_check" id="regua2CheckV2" <?= intval($config['regua_2_dias_antes'] ?? 0) > 0 ? 'checked' : '' ?> onchange="document.getElementById('regua2DiasV2').disabled=!this.checked">
                                <label class="form-check-label fw-semibold text-slate-900" for="regua2CheckV2">
                                    <span class="badge bg-light text-dark border me-1">2º Disparo</span>
                                    1º Lembrete prévio
                                </label>
                            </div>
                            <div class="input-group input-group-sm ps-4" style="max-width:280px;">
                                <input type="number" name="regua_2_dias_antes" id="regua2DiasV2" class="form-control" min="1" max="60" value="<?= htmlspecialchars($config['regua_2_dias_antes'] ?? '15') ?>" <?= intval($config['regua_2_dias_antes'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                <span class="input-group-text">dias antes</span>
                            </div>
                        </div>

                        <!-- Etapa 3 -->
                        <div class="p-3 rounded-3 border" style="background:#fff;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="regua_3_check" id="regua3CheckV2" <?= intval($config['regua_3_dias_antes'] ?? 0) > 0 ? 'checked' : '' ?> onchange="document.getElementById('regua3DiasV2').disabled=!this.checked">
                                <label class="form-check-label fw-semibold text-slate-900" for="regua3CheckV2">
                                    <span class="badge bg-light text-dark border me-1">3º Disparo</span>
                                    2º Lembrete de proximidade
                                </label>
                            </div>
                            <div class="input-group input-group-sm ps-4" style="max-width:280px;">
                                <input type="number" name="regua_3_dias_antes" id="regua3DiasV2" class="form-control" min="1" max="60" value="<?= htmlspecialchars($config['regua_3_dias_antes'] ?? '7') ?>" <?= intval($config['regua_3_dias_antes'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                <span class="input-group-text">dias antes</span>
                            </div>
                        </div>

                        <!-- Etapa 4 -->
                        <div class="p-3 rounded-3 border" style="background:#fff;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="regua_4_no_vencimento" id="regua4V2" <?= ($config['regua_4_no_vencimento'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-slate-900" for="regua4V2">
                                    <span class="badge bg-light text-dark border me-1">4º Disparo</span>
                                    No dia do vencimento da fatura
                                </label>
                            </div>
                            <small class="text-muted d-block ps-4 mt-1">Aviso urgente informando que o boleto ou PIX vence hoje.</small>
                        </div>

                        <!-- Etapa 5 -->
                        <div class="p-3 rounded-3 border" style="background:#fff;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="regua_5_check" id="regua5CheckV2" <?= intval($config['regua_5_dias_depois'] ?? 0) > 0 ? 'checked' : '' ?> onchange="document.getElementById('regua5DiasV2').disabled=!this.checked">
                                <label class="form-check-label fw-semibold text-slate-900" for="regua5CheckV2">
                                    <span class="badge bg-light text-dark border me-1">5º Disparo</span>
                                    Aviso de fatura em atraso (inadimplência)
                                </label>
                            </div>
                            <div class="input-group input-group-sm ps-4" style="max-width:280px;">
                                <input type="number" name="regua_5_dias_depois" id="regua5DiasV2" class="form-control" min="1" max="90" value="<?= htmlspecialchars($config['regua_5_dias_depois'] ?? '3') ?>" <?= intval($config['regua_5_dias_depois'] ?? 0) === 0 ? 'disabled' : '' ?>>
                                <span class="input-group-text">dias depois</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-fintech-primary px-4">
                            <i class="bi bi-save me-1"></i> Salvar Régua
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const presets = {
        gmail: { host: 'smtp.gmail.com', port: 587, ssl: 'tls' },
        outlook: { host: 'smtp.office365.com', port: 587, ssl: 'tls' },
        emailarray: { host: 'smtp.emailarray.com', port: 587, ssl: 'tls' },
        ses: { host: 'email-smtp.us-east-1.amazonaws.com', port: 587, ssl: 'tls' },
        zoho: { host: 'smtp.zoho.com', port: 587, ssl: 'tls' }
    };
    const presetSelect = document.getElementById('presetProvedor');
    if (presetSelect) {
        presetSelect.addEventListener('change', function () {
            const val = this.value;
            if (presets[val]) {
                document.getElementById('smtp_host').value = presets[val].host;
                document.getElementById('smtp_port').value = presets[val].port;
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

