<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedInAdmin()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';

$hasSSL = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;

if ($hasSSL) {
    $cert = getClientCertificate();

    if ($cert) {
        if (loginAdminCertificado()) {
            header('Location: index.php');
            exit;
        } else {
            $erro = 'Certificado digital não registrado no sistema. Faça login com usuário/senha e cadastre seu certificado em <strong>Certificados Digitais</strong>.';
        }
    } else {
        $erro = 'Nenhum certificado digital detectado. Caso o navegador não tenha pedido, tente novamente ou verifique se o certificado está instalado.';
    }
} else {
    $erro = 'HTTPS não está configurado. Para autenticação por certificado digital, acesse via <code>https://localhost/cobranca/admin/login_certificado.php</code>';
}

require_once __DIR__ . '/../config/settings.php';
$logo = getLogoLogin();
$nomeSistema = getNomeSistema();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado Digital - <?= htmlspecialchars($nomeSistema) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/cobranca/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-page">
        <div class="login-split">
            <div class="login-left">
                <?php if ($logo): ?>
                    <div class="logo"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo"></div>
                <?php else: ?>
                    <i class="fas fa-shield-halved fa-3x mb-3"></i>
                <?php endif; ?>
                <h3>Autenticação por Certificado Digital</h3>
                <p>O navegador solicitará seu certificado digital ICP-Brasil. Selecione-o para acessar o painel <?= htmlspecialchars($nomeSistema) ?>.</p>
                <div class="mt-3 text-center" style="font-size:0.8rem; color:#888;">
                    <i class="fas fa-shield-halved me-1"></i> Autenticação de alto nível
                </div>
            </div>
            <div class="login-right">
                <div class="login-form">
                    <div class="mb-3 text-center">
                        <h2 class="mb-0"><?= htmlspecialchars($nomeSistema) ?></h2>
                    </div>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger py-2">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= $erro ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($cert): ?>
                        <div class="mb-3">
                            <div class="p-3 rounded" style="background:#d4edda; border:1px solid #28a745;">
                                <h6 class="mb-2"><i class="fas fa-check-circle me-2 text-success"></i>Certificado Detectado</h6>
                                <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem;">
                                    <tr><td class="text-muted" style="width:40%">Titular:</td><td><?= htmlspecialchars($cert['subject_cn']) ?></td></tr>
                                    <tr><td class="text-muted">Emissor:</td><td><?= htmlspecialchars($cert['issuer_cn']) ?></td></tr>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <?php if ($hasSSL): ?>
                            <a href="login_certificado.php" class="btn btn-primary">
                                <i class="fas fa-redo me-1"></i> Tentar Novamente
                            </a>
                        <?php else: ?>
                            <a href="https://localhost/cobranca/admin/login_certificado.php" class="btn btn-primary">
                                <i class="fas fa-lock me-1"></i> Acessar via HTTPS
                            </a>
                        <?php endif; ?>
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Voltar para Login
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted" style="font-size:0.65rem;">Desenvolvido por WD Soluções Digitais.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
