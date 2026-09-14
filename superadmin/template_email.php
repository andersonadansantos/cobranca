<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $assunto = trim($_POST['template_email_assunto_boasvindas'] ?? '');
        $corpo = $_POST['template_email_corpo_boasvindas'] ?? '';
        salvarConfigGlobal('template_email_assunto_boasvindas', $assunto);
        salvarConfigGlobal('template_email_corpo_boasvindas', $corpo);
        $mensagem = 'Template de boas-vindas salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'restaurar') {
        salvarConfigGlobal('template_email_assunto_boasvindas', '');
        salvarConfigGlobal('template_email_corpo_boasvindas', '');
        $mensagem = 'Template de boas-vindas restaurado para o padrão!';
        $tipo = 'info';
    }
}

$nomeSistema = getNomeSistema();
$corPrimaria = getCorPrimaria();
$logoTag = getLogoEmail();

$defaultTemplate = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;">
  <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
    <div style="background:' . $corPrimaria . ';height:50px;"></div>
    <div style="padding:30px 30px 0 30px;text-align:center;">
      {{LOGO}}
    </div>
    <div style="padding:20px 30px 30px 30px;">
      {{CONTEUDO}}
    </div>
  </div>
</body>
</html>';

$assunto = getConfigGlobal('template_email_assunto_boasvindas', '');
if (empty($assunto)) $assunto = 'Seja bem-vindo(a) ao ' . $nomeSistema . '!';

$corpo = getConfigGlobal('template_email_corpo_boasvindas', '');
if (empty($corpo)) $corpo = $defaultTemplate;

$sample = [
    '{nome}'        => 'Nome do Cliente',
    '{usuario}'     => 'usuario0123',
    '{senha}'       => 'senha123',
    '{email}'       => 'cliente@empresa.com',
    '{subdominio}'  => 'clientea',
    '{link_acesso}' => 'https://clientea.seudominio.com/admin',
];

$previewConteudo = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
$previewConteudo .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#1a1a2e;">Seja bem-vindo(a)!</h2>';
$previewConteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>{nome}</strong>,</p>';
$previewConteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Sua conta no <strong>' . htmlspecialchars($nomeSistema) . '</strong> foi criada com sucesso. Veja seus dados de acesso abaixo:</p>';
$previewConteudo .= '</div>';
$previewConteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
$previewConteudo .= '<h4 style="margin:0 0 12px 0;font-size:14px;font-weight:600;color:#333;text-align:center;">Dados de Acesso</h4>';
$previewConteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">Usuário: <strong style="color:#333;">{usuario}</strong></p>';
$previewConteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">Senha: <strong style="color:#333;">{senha}</strong></p>';
$previewConteudo .= '<div style="text-align:center;margin-top:16px;"><a href="{link_acesso}" style="display:inline-block;background:' . $corPrimaria . ';color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Acessar Painel →</a></div>';
$previewConteudo .= '<p style="margin:10px 0 0 0;font-size:12px;color:#999;text-align:center;">Por segurança, altere sua senha após o primeiro acesso.</p>';
$previewConteudo .= '</div>';
$previewConteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';

$previewHtml = $corpo;
if (strpos($previewHtml, '{{CONTEUDO}}') !== false) {
    $previewHtml = str_replace('{{CONTEUDO}}', $previewConteudo, $previewHtml);
}
foreach ($sample as $k => $v) {
    $previewHtml = str_replace($k, $v, $previewHtml);
}
$logoTagHtml = '';
if (!empty($logoTag)) {
    $logoTagHtml = '<div style="text-align:center;margin:0 0 14px;"><img src="' . htmlspecialchars($logoTag) . '" alt="' . htmlspecialchars($nomeSistema) . '" style="max-width:250px;max-height:120px;width:auto;height:auto;display:block;margin:0 auto;border:0;outline:none;text-decoration:none;"></div>';
}
if (strpos($previewHtml, '{{LOGO}}') !== false) {
    $previewHtml = str_replace('{{LOGO}}', $logoTagHtml, $previewHtml);
} elseif ($logoTagHtml !== '' && stripos($previewHtml, '<img') === false) {
    $previewHtml = preg_replace('#(<body[^>]*>)#i', '$1<div style="text-align:center;padding:24px 24px 0 24px;">' . $logoTagHtml . '</div>', $previewHtml, 1);
}

$pageTitle = 'Template de Email';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-envelope-open-text me-1"></i> Template de Email — Boas-vindas</h5>
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

        <div class="alert alert-info py-2 mb-4">
            <i class="fas fa-info-circle me-1"></i>
            Este template é enviado ao e-mail do admin quando uma conta nova é criada no <strong>Cadastros</strong>.
            O SMTP usado é o <strong>SMTP Global</strong> (aba SMTP).
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="salvar">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-envelope me-2"></i>Assunto do E-mail</h6>
                        <input type="text" name="template_email_assunto_boasvindas" class="form-control" value="<?= htmlspecialchars($assunto) ?>">
                        <small class="text-muted">Variáveis: {nome}, {usuario}, {senha}, {email}, {subdominio}</small>
                    </div>
                    <div class="form-card mb-4">
                        <h6 class="mb-3"><i class="fas fa-code me-2"></i>Corpo do E-mail (HTML/CSS)</h6>
                        <div class="mb-3">
                            <textarea name="template_email_corpo_boasvindas" class="form-control template-editor" data-target="previewBoasVindas" rows="20" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($corpo) ?></textarea>
                            <small class="text-muted d-block mt-1">
                                Use <code>{{CONTEUDO}}</code> para inserir o bloco automático de boas-vindas/dados de acesso e <code>{{LOGO}}</code> para a logo do sistema no topo.
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Salvar Template
                            </button>
                            <button type="submit" class="btn btn-outline-secondary" onclick="this.closest('form').querySelector('[name=acao]').value='restaurar'">
                                <i class="fas fa-undo me-1"></i> Restaurar Padrão
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Preview — Boas-vindas</h6>
                        <div style="border:1px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <iframe id="previewBoasVindas" style="width:100%;height:520px;border:none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var defaultTemplate = <?= json_encode($defaultTemplate) ?>;
var previewConteudo = <?= json_encode($previewConteudo) ?>;
var sample = <?= json_encode($sample) ?>;
var logoHtml = <?= json_encode($logoTagHtml) ?>;

function buildPreview(tpl) {
    var html = (tpl || defaultTemplate).replace(/{{CONTEUDO}}/g, previewConteudo);
    for (var k in sample) {
        if (sample.hasOwnProperty(k)) html = html.split(k).join(sample[k]);
    }
    html = html.split('{{LOGO}}').join(logoHtml);
    if (html.indexOf('<img') === -1 && logoHtml) {
        html = html.replace(/(<body[^>]*>)/i, '$1<div style="text-align:center;padding:24px 24px 0 24px;">' + logoHtml + '</div>');
    }
    return html;
}

function setPreview(iframeId, html) {
    var iframe = document.getElementById(iframeId);
    if (iframe) {
        var doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
    }
}

setPreview('previewBoasVindas', buildPreview(defaultTemplate));

document.querySelectorAll('.template-editor').forEach(function(el) {
    el.addEventListener('input', function() {
        setPreview(this.getAttribute('data-target'), buildPreview(this.value));
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>