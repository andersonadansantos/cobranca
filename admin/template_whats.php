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
        $corpo = $_POST['template_whats_antes'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_whats_antes', $corpo, $corpo]);
        $mensagem = 'Template Lembrete salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'salvar_depois') {
        $pdo = getConnection();
        $corpo = $_POST['template_whats_depois'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_whats_depois', $corpo, $corpo]);
        $mensagem = 'Template Cobrança salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'salvar_pagamento') {
        $pdo = getConnection();
        $corpo = $_POST['template_whats_pagamento'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_whats_pagamento', $corpo, $corpo]);
        $mensagem = 'Template Pagamento Recebido salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'restaurar_antes') {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_whats_antes', '', '']);
        $mensagem = 'Template Lembrete restaurado para o padrão!';
        $tipo = 'info';
    }

    if ($acao === 'restaurar_depois') {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_whats_depois', '', '']);
        $mensagem = 'Template Cobrança restaurado para o padrão!';
        $tipo = 'info';
    }

    if ($acao === 'restaurar_pagamento') {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['template_whats_pagamento', '', '']);
        $mensagem = 'Template Pagamento Recebido restaurado para o padrão!';
        $tipo = 'info';
    }
}

$config = getAllConfig();
$templateAntes = $config['template_whats_antes'] ?? '';
$templateDepois = $config['template_whats_depois'] ?? '';
$templatePagamento = $config['template_whats_pagamento'] ?? '';

$pageTitle = 'Template WhatsApp';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="main-content">
    <div class="container-fluid p-4">
        <div class="mb-4">
            <h4><i class="fab fa-whatsapp me-2" style="color:#25D366;"></i>Template WhatsApp</h4>
            <p class="text-muted mb-0">Personalize as mensagens de cobrança enviadas automaticamente pelo WhatsApp aos seus clientes.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show py-2">
                <i class="fas fa-<?= $tipo === 'success' ? 'check-circle' : ($tipo === 'info' ? 'info-circle' : 'exclamation-circle') ?> me-1"></i> <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Template Lembrete -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Lembrete</h6>
                        <small>Enviado antes do vencimento</small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_antes">
                            <div class="mb-3">
                                <label class="form-label small">Mensagem</label>
                                <textarea name="template_whats_antes" class="form-control" rows="12" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($templateAntes) ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Salvar</button>
                                <button type="submit" name="acao" value="restaurar_antes" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Restaurar padrão?')"><i class="fas fa-undo me-1"></i>Restaurar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Template Cobrança -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Cobrança</h6>
                        <small>Enviado após vencimento</small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_depois">
                            <div class="mb-3">
                                <label class="form-label small">Mensagem</label>
                                <textarea name="template_whats_depois" class="form-control" rows="12" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($templateDepois) ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Salvar</button>
                                <button type="submit" name="acao" value="restaurar_depois" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Restaurar padrão?')"><i class="fas fa-undo me-1"></i>Restaurar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Template Pagamento -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Pagamento Recebido</h6>
                        <small>Enviado após confirmação</small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar_pagamento">
                            <div class="mb-3">
                                <label class="form-label small">Mensagem</label>
                                <textarea name="template_whats_pagamento" class="form-control" rows="12" style="font-family:monospace;font-size:0.82rem;"><?= htmlspecialchars($templatePagamento) ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i>Salvar</button>
                                <button type="submit" name="acao" value="restaurar_pagamento" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Restaurar padrão?')"><i class="fas fa-undo me-1"></i>Restaurar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body bg-light">
                <h6 class="mb-2"><i class="fas fa-info-circle me-1"></i>Variáveis disponíveis</h6>
                <div class="row small">
                    <div class="col-md-3"><code>{nomeEmpresa}</code> — Nome da empresa</div>
                    <div class="col-md-3"><code>{cliente}</code> — Nome do cliente</div>
                    <div class="col-md-3"><code>{numero}</code> — Nº da fatura</div>
                    <div class="col-md-3"><code>{data_vencimento}</code> — Data de vencimento</div>
                    <div class="col-md-3"><code>{descricao}</code> — Descrição da fatura</div>
                    <div class="col-md-3"><code>{valor}</code> — Valor da fatura</div>
                    <div class="col-md-3"><code>{link_fatura}</code> — Link de pagamento</div>
                    <div class="col-md-3"><code>{pix}</code> — Código PIX copia e cola</div>
                    <div class="col-md-3"><code>{cpf_cnpj}</code> — CPF/CNPJ do cliente</div>
                    <div class="col-md-3"><code>{dias}</code> — Dias até/após vencimento</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h6 class="mb-2"><i class="fas fa-question-circle me-1"></i>Como configurar</h6>
                <div class="small text-muted">
                    <p class="mb-1">1. Acesse <strong>Configurações &gt; WhatsApp</strong> no menu lateral e configure a Evolution API.</p>
                    <p class="mb-1">2. Escaneie o QR Code com seu WhatsApp para conectar.</p>
                    <p class="mb-1">3. Volte aqui e personalize as mensagens de Lembrete, Cobrança e Pagamento.</p>
                    <p class="mb-0">4. As mensagens serão enviadas automaticamente pelo cron.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
