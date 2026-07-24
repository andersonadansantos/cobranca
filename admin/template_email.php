<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_antes') {
        $pdo = getConnection();
        $assunto = trim($_POST['template_email_assunto_antes'] ?? '');
        $corpo = $_POST['template_email_corpo_antes'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_email_assunto_antes', $assunto, $assunto]);
        $stmt->execute(['template_email_corpo_antes', $corpo, $corpo]);
        if (!empty($_FILES['email_logo_antes']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../assets/img/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['email_logo_antes']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
                $nomeArquivo = 'logo_empresa_' . time() . '.' . $ext;
                $destino = $uploadDir . $nomeArquivo;
                if (move_uploaded_file($_FILES['email_logo_antes']['tmp_name'], $destino)) {
                    $stmt->execute(['logo_empresa', '/cobranca/assets/img/' . $nomeArquivo, '/cobranca/assets/img/' . $nomeArquivo]);
                }
            }
        }
        $mensagem = 'Template Lembrete salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'salvar_depois') {
        $pdo = getConnection();
        $assunto = trim($_POST['template_email_assunto_depois'] ?? '');
        $corpo = $_POST['template_email_corpo_depois'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_email_assunto_depois', $assunto, $assunto]);
        $stmt->execute(['template_email_corpo_depois', $corpo, $corpo]);
        if (!empty($_FILES['email_logo_depois']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../assets/img/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['email_logo_depois']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
                $nomeArquivo = 'logo_empresa_' . time() . '.' . $ext;
                $destino = $uploadDir . $nomeArquivo;
                if (move_uploaded_file($_FILES['email_logo_depois']['tmp_name'], $destino)) {
                    $stmt->execute(['logo_empresa', '/cobranca/assets/img/' . $nomeArquivo, '/cobranca/assets/img/' . $nomeArquivo]);
                }
            }
        }
        $mensagem = 'Template Cobrança salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'restaurar_antes') {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_email_corpo_antes', '', '']);
        $stmt->execute(['template_email_assunto_antes', 'Lembrete: Fatura {numero} vence em {data_vencimento}', 'Lembrete: Fatura {numero} vence em {data_vencimento}']);
        $mensagem = 'Template Lembrete restaurado para o padrão!';
        $tipo = 'info';
    }

    if ($acao === 'restaurar_depois') {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_email_corpo_depois', '', '']);
        $stmt->execute(['template_email_assunto_depois', 'Cobrança: Fatura {numero} vencida', 'Cobrança: Fatura {numero} vencida']);
        $mensagem = 'Template Cobrança restaurado para o padrão!';
        $tipo = 'info';
    }

    if ($acao === 'salvar_pagamento') {
        $pdo = getConnection();
        $assunto = trim($_POST['template_email_assunto_pagamento'] ?? '');
        $corpo = $_POST['template_email_corpo_pagamento'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_email_assunto_pagamento', $assunto, $assunto]);
        $stmt->execute(['template_email_corpo_pagamento', $corpo, $corpo]);
        $mensagem = 'Template Pagamento Recebido salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'restaurar_pagamento') {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_email_corpo_pagamento', '', '']);
        $stmt->execute(['template_email_assunto_pagamento', 'Pagamento Confirmado - Fatura {numero}', 'Pagamento Confirmado - Fatura {numero}']);
        $mensagem = 'Template Pagamento Recebido restaurado para o padrão!';
        $tipo = 'info';
    }
}

$config = getAllConfig();
$corPrimaria = getCorPrimaria();
$logo = getLogoEmail();

$logoTag = $logo ? '<img src="' . htmlspecialchars($logo) . '" alt="Logo" width="250" style="width:250px;max-width:250px;height:auto;display:block;margin:0 auto 20px auto;">' : '<h2 style="margin:0 0 20px 0;">' . htmlspecialchars(getNomeSistema()) . '</h2>';
$defaultTemplate = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <div style="background:' . $corPrimaria . ';padding:24px 30px;text-align:center;">
      ' . $logoTag . '
    </div>
    <div style="padding:30px;">
      {{CONTEUDO}}
    </div>
  </div>
</body>
</html>';

$templateAntes = $config['template_email_corpo_antes'] ?? '';
$templateDepois = $config['template_email_corpo_depois'] ?? '';
$templatePagamento = $config['template_email_corpo_pagamento'] ?? '';
if (empty($templateAntes)) $templateAntes = $defaultTemplate;
if (empty($templateDepois)) $templateDepois = $defaultTemplate;
if (empty($templatePagamento)) $templatePagamento = $defaultTemplate;

$previewAntes = '<h3 style="color:#333;margin-top:0;">Lembrete de Fatura</h3>';
$previewAntes .= '<p>Olá, <strong>Nome do Cliente</strong>,</p>';
$previewAntes .= '<p>Identificamos que sua fatura <strong>FAT-202607-0001</strong> vence em <strong>' . date('d/m/Y', strtotime('+3 days')) . '</strong>.</p>';
$previewAntes .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;"><tr><td style="padding:8px 0;color:#666;">Descrição</td><td style="padding:8px 0;">Serviço de exemplo</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Valor</td><td style="padding:8px 0;border-top:1px solid #eee;font-weight:bold;">R$ 1.500,00</td></tr></table>';
$previewAntes .= '<p>Acesse sua fatura para mais detalhes e realizar o pagamento:</p>';
$previewAntes .= '<div style="text-align:center;margin:25px 0;"><a href="#" style="display:inline-block;background:' . $corPrimaria . ';color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;">Ver Fatura</a></div>';
$previewAntes .= '<p style="color:#999;font-size:12px;margin-top:30px;">Atenciosamente,<br>' . htmlspecialchars(getNomeSistema()) . '</p>';

$previewDepois = '<h3 style="color:#c0392b;margin-top:0;">Fatura Vencida</h3>';
$previewDepois .= '<p>Olá, <strong>Nome do Cliente</strong>,</p>';
$previewDepois .= '<p>Sua fatura <strong>FAT-202607-0001</strong> encontra-se vencida há <strong>2 dia(s)</strong>.</p>';
$previewDepois .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;"><tr><td style="padding:8px 0;color:#666;">Descrição</td><td style="padding:8px 0;">Serviço de exemplo</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Valor</td><td style="padding:8px 0;border-top:1px solid #eee;font-weight:bold;">R$ 1.500,00</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Vencimento</td><td style="padding:8px 0;border-top:1px solid #eee;">' . date('d/m/Y', strtotime('-2 days')) . '</td></tr></table>';
$previewDepois .= '<p>Por favor, regularize sua situação o mais rápido possível:</p>';
$previewDepois .= '<div style="text-align:center;margin:25px 0;"><a href="#" style="display:inline-block;background:#c0392b;color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;">Ver Fatura</a></div>';
$previewDepois .= '<p style="color:#999;font-size:12px;margin-top:30px;">Atenciosamente,<br>' . htmlspecialchars(getNomeSistema()) . '</p>';

$previewHtmlAntes = str_replace('{{CONTEUDO}}', $previewAntes, $templateAntes);
$previewHtmlDepois = str_replace('{{CONTEUDO}}', $previewDepois, $templateDepois);

$previewPagamento = '<h3 style="color:#27ae60;margin-top:0;">Pagamento Confirmado!</h3>';
$previewPagamento .= '<p>Olá, <strong>Nome do Cliente</strong>,</p>';
$previewPagamento .= '<p>Confirmamos o recebimento do pagamento da sua fatura <strong>FAT-202607-0001</strong>.</p>';
$previewPagamento .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;"><tr><td style="padding:8px 0;color:#666;">Descrição</td><td style="padding:8px 0;">Serviço de exemplo</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Valor Pago</td><td style="padding:8px 0;border-top:1px solid #eee;font-weight:bold;color:#27ae60;">R$ 1.500,00</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Data do Pagamento</td><td style="padding:8px 0;border-top:1px solid #eee;">' . date('d/m/Y') . '</td></tr></table>';
$previewPagamento .= '<p>Sua fatura está quitada. Acesse sua conta para acompanhar suas faturas:</p>';
$previewPagamento .= '<div style="text-align:center;margin:25px 0;"><a href="#" style="display:inline-block;background:#27ae60;color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;">Acessar Painel</a></div>';
$previewPagamento .= '<p style="color:#999;font-size:12px;margin-top:30px;">Atenciosamente,<br>' . htmlspecialchars(getNomeSistema()) . '</p>';

$previewHtmlPagamento = str_replace('{{CONTEUDO}}', $previewPagamento, $templatePagamento);

$pageTitle = 'Template E-mail';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Template E-mail</h5>
        </div>
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

        <!-- ==================== MODELO 1: ANTES DO VENCIMENTO ==================== -->
        <form method="POST" enctype="multipart/form-data" class="mb-4">
            <input type="hidden" name="acao" value="salvar_antes">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Modelo 1 — Lembrete (Antes do Vencimento)</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Assunto do E-mail</label>
                                <input type="text" name="template_email_assunto_antes" class="form-control" value="<?= htmlspecialchars($config['template_email_assunto_antes'] ?? 'Lembrete: Fatura {numero} vence em {data_vencimento}') ?>">
                                <small class="text-muted">Variáveis: {numero}, {data_vencimento}, {valor}</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Logo no topo</label>
                                <input type="file" name="email_logo_antes" class="form-control" accept="image/*">
                                <?php if ($logo): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block mb-1">Logo atual:</small>
                                        <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-height:50px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-code me-2"></i>Corpo do E-mail (HTML/CSS)</h6>
                        <div class="mb-3">
                            <textarea name="template_email_corpo_antes" class="form-control template-editor" data-target="previewAntes" rows="18" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($templateAntes) ?></textarea>
                            <small class="text-muted">Use <code>{{CONTEUDO}}</code> para inserir o conteúdo automático da fatura.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Salvar Lembrete
                            </button>
                            <button type="submit" class="btn btn-outline-secondary" onclick="document.querySelector('[name=acao]').value='restaurar_antes'">
                                <i class="fas fa-undo me-1"></i> Restaurar Padrão
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Preview — Lembrete</h6>
                        <div style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <iframe id="previewAntes" style="width:100%;height:520px;border:none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <!-- ==================== MODELO 2: DEPOIS DO VENCIMENTO ==================== -->
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar_depois">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Modelo 2 — Cobrança (Após Vencimento)</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Assunto do E-mail</label>
                                <input type="text" name="template_email_assunto_depois" class="form-control" value="<?= htmlspecialchars($config['template_email_assunto_depois'] ?? 'Cobrança: Fatura {numero} vencida') ?>">
                                <small class="text-muted">Variáveis: {numero}, {data_vencimento}, {valor}, {dias_atraso}</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Logo no topo</label>
                                <input type="file" name="email_logo_depois" class="form-control" accept="image/*">
                                <?php if ($logo): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block mb-1">Logo atual:</small>
                                        <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" style="max-height:50px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-code me-2"></i>Corpo do E-mail (HTML/CSS)</h6>
                        <div class="mb-3">
                            <textarea name="template_email_corpo_depois" class="form-control template-editor" data-target="previewDepois" rows="18" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($templateDepois) ?></textarea>
                            <small class="text-muted">Use <code>{{CONTEUDO}}</code> para inserir o conteúdo automático da fatura.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Salvar Cobrança
                            </button>
                            <button type="submit" class="btn btn-outline-secondary" onclick="document.querySelector('[name=acao]').value='restaurar_depois'">
                                <i class="fas fa-undo me-1"></i> Restaurar Padrão
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Preview — Cobrança</h6>
                        <div style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <iframe id="previewDepois" style="width:100%;height:520px;border:none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <hr class="my-4">

        <!-- ==================== MODELO 3: PAGAMENTO RECEBIDO ==================== -->
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="salvar_pagamento">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-check-circle me-2 text-success"></i>Modelo 3 — Pagamento Recebido</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Assunto do E-mail</label>
                                <input type="text" name="template_email_assunto_pagamento" class="form-control" value="<?= htmlspecialchars($config['template_email_assunto_pagamento'] ?? 'Pagamento Confirmado - Fatura {numero}') ?>">
                                <small class="text-muted">Variáveis: {numero}, {data_vencimento}, {valor}, {data_pagamento}</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-code me-2"></i>Corpo do E-mail (HTML/CSS)</h6>
                        <div class="mb-3">
                            <textarea name="template_email_corpo_pagamento" class="form-control template-editor" data-target="previewPagamento" rows="18" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($templatePagamento) ?></textarea>
                            <small class="text-muted">Use <code>{{CONTEUDO}}</code> para inserir o conteúdo automático.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Salvar Pagamento
                            </button>
                            <button type="submit" class="btn btn-outline-secondary" onclick="document.querySelector('[name=acao]').value='restaurar_pagamento'">
                                <i class="fas fa-undo me-1"></i> Restaurar Padrão
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Preview — Pagamento Recebido</h6>
                        <div style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <iframe id="previewPagamento" style="width:100%;height:520px;border:none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var defaultTemplate = <?= json_encode($defaultTemplate) ?>;
var previewAntesContent = <?= json_encode($previewAntes) ?>;
var previewDepoisContent = <?= json_encode($previewDepois) ?>;
var previewPagamentoContent = <?= json_encode($previewPagamento) ?>;

function setPreview(iframeId, html) {
    var iframe = document.getElementById(iframeId);
    if (iframe) {
        var doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
    }
}

setPreview('previewAntes', defaultTemplate.replace('{{CONTEUDO}}', previewAntesContent));
setPreview('previewDepois', defaultTemplate.replace('{{CONTEUDO}}', previewDepoisContent));
setPreview('previewPagamento', defaultTemplate.replace('{{CONTEUDO}}', previewPagamentoContent));

document.querySelectorAll('.template-editor').forEach(function(el) {
    el.addEventListener('input', function() {
        var targetId = this.getAttribute('data-target');
        var contentMap = { previewAntes: previewAntesContent, previewDepois: previewDepoisContent, previewPagamento: previewPagamentoContent };
        var content = contentMap[targetId] || previewAntesContent;
        var html = this.value || defaultTemplate;
        setPreview(targetId, html.replace('{{CONTEUDO}}', content));
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
