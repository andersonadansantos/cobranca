<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/inter_pix.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_inter') {
    $pdo = getConnection();
    $campos = ['super_inter_client_id', 'super_inter_client_secret', 'super_inter_conta', 'super_inter_webhook_url'];
    foreach ($campos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute([$campo, $valor, $valor]);
    }

    $certDir = __DIR__ . '/../config/inter_certs_super';
    if (!is_dir($certDir)) {
        mkdir($certDir, 0755, true);
    }

    $certCampos = ['super_inter_cert_crt' => 'certificado.crt', 'super_inter_cert_key' => 'certificado.key', 'super_inter_cert_webhook' => 'certificado_webhook.pem'];
    foreach ($certCampos as $campo => $nomeArquivo) {
        if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['crt', 'key', 'pem'])) {
                $mensagem = 'Extensão de arquivo inválida para ' . $nomeArquivo . '. Use .crt, .key ou .pem';
                $tipo = 'danger';
                break;
            }
            $destino = $certDir . '/' . $nomeArquivo;
            if (move_uploaded_file($_FILES[$campo]['tmp_name'], $destino)) {
                $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
                $stmt->execute([$campo, $destino, $destino]);
            } else {
                $mensagem = 'Erro ao enviar arquivo ' . $nomeArquivo;
                $tipo = 'danger';
            }
        }
    }

    if (empty($mensagem)) {
        $mensagem = 'Configurações do Banco Inter salvas com sucesso!';
        $tipo = 'success';
    }
}

$config = getConfigInterSuper();
$interBaseUrl = getInterSuperBaseUrl();
$temCredenciais = !empty($config['super_inter_client_id']) && !empty($config['super_inter_client_secret']);

$pageTitle = 'API Pagamento';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-university me-1"></i> API Pagamento</h5>
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
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-university me-2"></i>Credenciais do Banco Inter (PIX de Planos)</h6>
                    </div>
                    <div class="p-3">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Estas credenciais são <strong>exclusivas do Super Admin</strong> e responsáveis pelo recebimento via PIX quando os admins contratam seus planos. São independentes das credenciais do painel admin.
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="acao" value="salvar_inter">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="super_inter_client_id" class="form-control font-monospace"
                                        placeholder="Seu Client ID do Inter"
                                        value="<?= htmlspecialchars($config['super_inter_client_id'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Client Secret</label>
                                    <input type="password" name="super_inter_client_secret" class="form-control font-monospace"
                                        placeholder="Seu Client Secret do Inter"
                                        value="<?= htmlspecialchars($config['super_inter_client_secret'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Conta (Agência + Conta)</label>
                                    <input type="text" name="super_inter_conta" class="form-control"
                                        placeholder="0001-1234567-8"
                                        value="<?= htmlspecialchars($config['super_inter_conta'] ?? '') ?>">
                                    <small class="text-muted">Formato: agência-conta-dígito</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">URL do Webhook</label>
                                    <input type="url" name="super_inter_webhook_url" class="form-control"
                                        placeholder="https://seudominio.com/cobranca/api/webhook_pix_plano.php"
                                        value="<?= htmlspecialchars($config['super_inter_webhook_url'] ?? '') ?>">
                                    <small class="text-muted">URL para confirmar pagamentos de planos</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Certificado (.crt)</label>
                                    <input type="file" name="super_inter_cert_crt" class="form-control" accept=".crt,.pem,.cer">
                                    <?php $certCrt = $config['super_inter_cert_crt'] ?? ''; ?>
                                    <?php if ($certCrt && file_exists($certCrt)): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certCrt) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Arquivo do certificado público</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Chave Privada (.key)</label>
                                    <input type="file" name="super_inter_cert_key" class="form-control" accept=".key,.pem">
                                    <?php $certKey = $config['super_inter_cert_key'] ?? ''; ?>
                                    <?php if ($certKey && file_exists($certKey)): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i><?= basename($certKey) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Arquivo da chave privada</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Configurações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Como Configurar</h6>
                    </div>
                    <div class="p-3">
                        <ol class="small text-muted mb-0" style="padding-left: 18px;">
                            <li class="mb-2">Acesse <a href="https://developers.inter.co/" target="_blank">developers.inter.co</a></li>
                            <li class="mb-2">Crie uma aplicação no portal de desenvolvedores</li>
                            <li class="mb-2">Copie o <strong>Client ID</strong> e <strong>Client Secret</strong></li>
                            <li class="mb-2">Configure os escopos de <strong>Cobrança</strong> e <strong>Pagamentos</strong></li>
                            <li class="mb-2">Envie os certificados <strong>.crt</strong> e <strong>.key</strong></li>
                            <li class="mb-2">Cadastre o <strong>Webhook</strong> com a URL acima e o evento de pagamento</li>
                            <li class="mb-2">Salve</li>
                        </ol>
                    </div>
                </div>

                <div class="form-card mt-3">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-server me-2"></i>Status</h6>
                    </div>
                    <div class="p-3">
                        <?php if ($temCredenciais): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-success me-2"><i class="fas fa-check"></i></span>
                                <span>Credenciais configuradas</span>
                            </div>
                            <small class="text-muted d-block">Client ID: <?= htmlspecialchars(substr($config['super_inter_client_id'], 0, 10)) ?>...</small>
                            <small class="text-muted d-block">Ambiente: <?= strpos($interBaseUrl, 'sandbox') !== false ? 'Sandbox (UAT)' : 'Produção' ?></small>
                        <?php else: ?>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-secondary me-2"><i class="fas fa-times"></i></span>
                                <span>Não configurado</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
