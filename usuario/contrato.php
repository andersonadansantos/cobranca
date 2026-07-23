<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM contratos WHERE cliente_id = ? ORDER BY criado_em DESC LIMIT 1");
$stmt->execute([$userId]);
$contrato = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$userId]);
$cliente = $stmt->fetch();

// Gerar PDF do contrato
if (isset($_GET['download']) && $contrato) {
    // Sem biblioteca externa, gerar HTML para impressão
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato - ' . htmlspecialchars($contrato['numero']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header p { color: #666; margin-top: 5px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 8px 12px; border: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 200px; background: #f8f9fa; }
        .content { line-height: 1.8; margin: 30px 0; text-align: justify; }
        .signature { margin-top: 60px; display: flex; justify-content: space-between; }
        .signature div { text-align: center; width: 45%; border-top: 1px solid #333; padding-top: 10px; }
        .footer { text-align: center; margin-top: 40px; font-size: 0.8rem; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars(getNomeSistema()) . '</h1>
        <p>CONTRATO DE PRESTAÇÃO DE SERVIÇOS</p>
        <p>Nº ' . htmlspecialchars($contrato['numero']) . '</p>
    </div>
    
    <table class="info-table">
        <tr><td>Cliente:</td><td>' . htmlspecialchars($cliente['nome_razao']) . '</td></tr>
        <tr><td>CPF/CNPJ:</td><td>' . htmlspecialchars($cliente['cpf_cnpj']) . '</td></tr>
        <tr><td>Data Início:</td><td>' . date('d/m/Y', strtotime($contrato['data_inicio'])) . '</td></tr>
        ' . ($contrato['data_fim'] ? '<tr><td>Data Fim:</td><td>' . date('d/m/Y', strtotime($contrato['data_fim'])) . '</td></tr>' : '') . '
        ' . ($contrato['valor_mensal'] ? '<tr><td>Valor Mensal:</td><td>R$ ' . number_format($contrato['valor_mensal'], 2, ',', '.') . '</td></tr>' : '') . '
        <tr><td>Status:</td><td>' . ucfirst($contrato['status']) . '</td></tr>
    </table>
    
    <div class="content">
        ' . nl2br(htmlspecialchars($contrato['conteudo'])) . '
    </div>
    
    <div class="signature">
        <div>
            <p><strong>' . htmlspecialchars($cliente['nome_razao']) . '</strong></p>
            <p>CPF/CNPJ: ' . htmlspecialchars($cliente['cpf_cnpj']) . '</p>
        </div>
        <div>
            <p><strong>' . htmlspecialchars(getNomeSistema()) . '</strong></p>
            <p>Representante Legal</p>
        </div>
    </div>
    
    <div class="footer">
        <p>Documento gerado em ' . date('d/m/Y H:i') . ' - ' . htmlspecialchars(getNomeSistema()) . '</p>
    </div>
</body>
</html>';
    
    // Retornar como PDF usando conversão nativa
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="contrato_' . $contrato['numero'] . '.html"');
    echo $html;
    exit;
}

$pageTitle = 'Contrato';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_usuario.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Contrato</h5>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/cobranca/assets/img/avatars/user.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/usuario/perfil.php"><i class="fas fa-user-edit me-2"></i>Meu Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/usuario/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($contrato): ?>
            <div class="form-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-file-contract me-2"></i><?= htmlspecialchars($contrato['titulo']) ?></h6>
                        <small class="text-muted">
                            Contrato Nº <?= htmlspecialchars($contrato['numero']) ?> | 
                            Início: <?= date('d/m/Y', strtotime($contrato['data_inicio'])) ?>
                            <?= $contrato['data_fim'] ? ' | Fim: ' . date('d/m/Y', strtotime($contrato['data_fim'])) : '' ?>
                        </small>
                    </div>
                    <a href="?download=1" class="btn btn-success">
                        <i class="fas fa-download me-1"></i> Download PDF
                    </a>
                </div>

                <div class="border rounded p-4 bg-white" id="contrato-view" style="max-height: 600px; overflow-y: auto;">
                    <div class="text-center border-bottom pb-3 mb-3">
                        <h4><?= htmlspecialchars(getNomeSistema()) ?></h4>
                        <p class="text-muted">CONTRATO DE PRESTAÇÃO DE SERVIÇOS</p>
                    </div>
                    
                    <table class="table table-bordered mb-4">
                        <tr>
                            <td style="width:200px" class="fw-bold bg-light">Cliente</td>
                            <td><?= htmlspecialchars($cliente['nome_razao']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">CPF/CNPJ</td>
                            <td><?= htmlspecialchars($cliente['cpf_cnpj']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold bg-light">Data Início</td>
                            <td><?= date('d/m/Y', strtotime($contrato['data_inicio'])) ?></td>
                        </tr>
                        <?php if ($contrato['valor_mensal']): ?>
                        <tr>
                            <td class="fw-bold bg-light">Valor Mensal</td>
                            <td>R$ <?= number_format($contrato['valor_mensal'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="fw-bold bg-light">Status</td>
                            <td><span class="badge bg-<?= $contrato['status'] === 'ativo' ? 'success' : ($contrato['status'] === 'suspenso' ? 'warning' : 'secondary') ?>"><?= ucfirst($contrato['status']) ?></span></td>
                        </tr>
                    </table>
                    
                    <div class="contrato-texto" style="line-height: 1.8; text-align: justify;">
                        <?= nl2br(htmlspecialchars($contrato['conteudo'])) ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="form-card text-center py-5">
                <i class="fas fa-file-contract fa-4x text-muted mb-3"></i>
                <h5>Nenhum contrato encontrado</h5>
                <p class="text-muted">Seu contrato será disponibilizado em breve pelo administrador.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
