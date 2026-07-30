<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';

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

$defaultTemplate = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;">
  <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
    <div style="background:' . $corPrimaria . ';height:50px;"></div>
    <div style="padding:30px 30px 0 30px;text-align:center;">
      ' . getLogoTagEmail() . '
    </div>
    <div style="padding:20px 30px 30px 30px;">
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

$previewAntes = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
$previewAntes .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#1a1a2e;">Lembrete de Fatura</h2>';
$previewAntes .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>Nome do Cliente</strong>,</p>';
$previewAntes .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Identificamos que sua fatura <strong>FAT-202607-0001</strong> vence em <strong>' . date('d/m/Y', strtotime('+3 days')) . '</strong>.</p>';
$previewAntes .= '</div>';
$previewAntes .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
$previewAntes .= '<table style="width:100%;border-collapse:collapse;">';
$previewAntes .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;">Descrição</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;">Serviço de exemplo</td></tr>';
$previewAntes .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Valor</td><td style="padding:10px 0;font-size:18px;font-weight:700;color:' . $corPrimaria . ';text-align:right;border-top:1px solid #e8edf5;">R$ 1.500,00</td></tr>';
$previewAntes .= '</table>';
$previewAntes .= '</div>';
$previewAntes .= '<div style="background:#ffffff;border:2px dashed ' . $corPrimaria . ';border-radius:12px;padding:24px;margin-bottom:24px;text-align:center;">';
$previewAntes .= '<h3 style="margin:0 0 4px 0;font-size:20px;font-weight:700;color:#1a1a2e;">Pague com PIX</h3>';
$previewAntes .= '<div style="background:#f8f9fa;border:1px dashed ' . $corPrimaria . ';border-radius:8px;padding:12px;margin-bottom:12px;text-align:left;">';
$previewAntes .= '<p style="margin:0 0 6px 0;font-size:12px;color:#666;">Código PIX Copia e Cola:</p>';
$previewAntes .= '<div style="font-family:\'Courier New\',monospace;font-size:12px;word-break:break-all;color:#333;user-select:all;-webkit-user-select:all;">00020126580014BR.GOV.BCB.PIX0136a1b2c3d4-e5f6-7890-abcd-ef123456789052040000530398654041.505802BR5925EMPRESA EXEMPLO LTDA6009SAO PAULO62070503***6304ABCD</div>';
$previewAntes .= '</div>';
$previewAntes .= '<div style="margin-top:16px;padding:12px;background:#fff8e1;border-radius:8px;display:flex;align-items:center;gap:8px;justify-content:center;">';
$previewAntes .= '<span style="font-size:16px;">🛡️</span>';
$previewAntes .= '<span style="font-size:12px;color:#666;">Pagamento 100% seguro via PIX</span>';
$previewAntes .= '</div>';
$previewAntes .= '</div>';
$previewAntes .= '<a href="#" style="display:block;text-align:center;color:' . $corPrimaria . ';text-decoration:none;padding:14px;border:2px solid ' . $corPrimaria . ';border-radius:8px;font-weight:600;font-size:15px;margin-bottom:24px;">Ver Fatura Completa →</a>';
$previewAntes .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars(getNomeSistema()) . '</strong></p>';

$previewDepois = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
$previewDepois .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#991b1b;">Fatura Vencida</h2>';
$previewDepois .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>Nome do Cliente</strong>,</p>';
$previewDepois .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Sua fatura <strong>FAT-202607-0001</strong> encontra-se vencida há <strong>2 dia(s)</strong>.</p>';
$previewDepois .= '</div>';
$previewDepois .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
$previewDepois .= '<table style="width:100%;border-collapse:collapse;">';
$previewDepois .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;">Descrição</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;">Serviço de exemplo</td></tr>';
$previewDepois .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Valor</td><td style="padding:10px 0;font-size:18px;font-weight:700;color:#dc2626;text-align:right;border-top:1px solid #e8edf5;">R$ 1.500,00</td></tr>';
$previewDepois .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Vencimento</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;border-top:1px solid #e8edf5;">' . date('d/m/Y', strtotime('-2 days')) . '</td></tr>';
$previewDepois .= '</table>';
$previewDepois .= '</div>';
$previewDepois .= '<div style="background:#ffffff;border:2px dashed #dc2626;border-radius:12px;padding:24px;margin-bottom:24px;text-align:center;">';
$previewDepois .= '<h3 style="margin:0 0 4px 0;font-size:20px;font-weight:700;color:#991b1b;">Pague com PIX</h3>';
$previewDepois .= '<div style="background:#f8f9fa;border:1px dashed #dc2626;border-radius:8px;padding:12px;margin-bottom:12px;text-align:left;">';
$previewDepois .= '<p style="margin:0 0 6px 0;font-size:12px;color:#666;">Código PIX Copia e Cola:</p>';
$previewDepois .= '<div style="font-family:\'Courier New\',monospace;font-size:12px;word-break:break-all;color:#333;user-select:all;-webkit-user-select:all;">00020126580014BR.GOV.BCB.PIX0136a1b2c3d4-e5f6-7890-abcd-ef123456789052040000530398654041.505802BR5925EMPRESA EXEMPLO LTDA6009SAO PAULO62070503***6304ABCD</div>';
$previewDepois .= '</div>';
$previewDepois .= '<div style="margin-top:16px;padding:12px;background:#fff8e1;border-radius:8px;display:flex;align-items:center;gap:8px;justify-content:center;">';
$previewDepois .= '<span style="font-size:16px;">🛡️</span>';
$previewDepois .= '<span style="font-size:12px;color:#666;">Pagamento 100% seguro via PIX</span>';
$previewDepois .= '</div>';
$previewDepois .= '</div>';
$previewDepois .= '<a href="#" style="display:block;text-align:center;color:#dc2626;text-decoration:none;padding:14px;border:2px solid #dc2626;border-radius:8px;font-weight:600;font-size:15px;margin-bottom:24px;">Regularizar Fatura →</a>';
$previewDepois .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars(getNomeSistema()) . '</strong></p>';

$previewHtmlAntes = str_replace('{{CONTEUDO}}', $previewAntes, $templateAntes);
$previewHtmlDepois = str_replace('{{CONTEUDO}}', $previewDepois, $templateDepois);

$previewPagamento = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
$previewPagamento .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#166534;">Pagamento Confirmado</h2>';
$previewPagamento .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>Nome do Cliente</strong>,</p>';
$previewPagamento .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Confirmamos o recebimento do pagamento da sua fatura <strong>FAT-202607-0001</strong>.</p>';
$previewPagamento .= '</div>';
$previewPagamento .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
$previewPagamento .= '<table style="width:100%;border-collapse:collapse;">';
$previewPagamento .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;">Descrição</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;">Serviço de exemplo</td></tr>';
$previewPagamento .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Valor Pago</td><td style="padding:10px 0;font-size:18px;font-weight:700;color:#16a34a;text-align:right;border-top:1px solid #e8edf5;">R$ 1.500,00</td></tr>';
$previewPagamento .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Data do Pagamento</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;border-top:1px solid #e8edf5;">' . date('d/m/Y') . '</td></tr>';
$previewPagamento .= '</table>';
$previewPagamento .= '</div>';
$previewPagamento .= '<div style="text-align:center;margin-bottom:24px;">';
$previewPagamento .= '<a href="#" style="display:inline-block;background:#16a34a;color:#fff;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">Acessar Painel →</a>';
$previewPagamento .= '</div>';
$previewPagamento .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars(getNomeSistema()) . '</strong></p>';

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
                            <button type="submit" class="btn btn-outline-secondary" onclick="this.closest('form').querySelector('[name=acao]').value='restaurar_antes'">
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
                            <button type="submit" class="btn btn-outline-secondary" onclick="this.closest('form').querySelector('[name=acao]').value='restaurar_depois'">
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
                            <button type="submit" class="btn btn-outline-secondary" onclick="this.closest('form').querySelector('[name=acao]').value='restaurar_pagamento'">
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
