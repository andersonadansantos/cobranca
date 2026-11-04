<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'template') {
        $templateEscolhido = in_array($_POST['template_admin'] ?? '', ['classico', 'fintech'], true) ? $_POST['template_admin'] : 'fintech';
        saveConfig('template_admin', $templateEscolhido);
        $_SESSION['admin_template'] = $templateEscolhido;
        $mensagem = 'Template do painel alterado com sucesso!';
        $tipo = 'success';
        
        if ($templateEscolhido === 'classico') {
            header('Location: /cobranca/admin/personalizacao.php?msg=template_salvo');
            exit;
        }
    }

    if ($acao === 'cores') {
        $corPrimaria = trim($_POST['cor_primaria'] ?? '#0f172a');
        $corSecundaria = trim($_POST['cor_secundaria'] ?? '#64748b');
        $corFundo = trim($_POST['cor_fundo'] ?? '#f8f9ff');
        $nomeSistema = trim($_POST['nome_sistema'] ?? 'Sistema de Cobrança');

        saveConfig('cor_primaria', $corPrimaria);
        saveConfig('cor_secundaria', $corSecundaria);
        saveConfig('cor_fundo', $corFundo);
        saveConfig('nome_sistema', $nomeSistema);

        $mensagem = 'Personalização de marca salva com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'logo') {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $res = salvarUploadSeguro($_FILES['logo'] ?? null, 'logos', 'logo_admin_' . $adminId);
        if ($res['sucesso']) {
            saveConfig('logo_empresa', $res['url']);
            $mensagem = 'Logo atualizada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = $res['erro'];
            $tipo = 'danger';
        }
    }

    if ($acao === 'logo_painel') {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $res = salvarUploadSeguro($_FILES['logo_painel'] ?? null, 'logos', 'logo_empresa_admin_' . $adminId);
        if ($res['sucesso']) {
            saveConfig('logo_empresa_admin', $res['url']);
            $mensagem = 'Logo do menu atualizada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = $res['erro'];
            $tipo = 'danger';
        }
    }
}

$config = getAllConfig();
$tplAtual = function_exists('getAdminTemplate') ? getAdminTemplate() : 'fintech';

$pageTitle = 'Personalização & Templates';
$pageCategory = 'Configurações';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Personalização & Identidade Visual</h2>
            <p class="text-muted small mb-0">Alterne entre templates disponíveis e customize logotipo e cores corporativas da sua conta.</p>
        </div>
        <div>
            <span class="fintech-badge neutral px-3 py-2"><span class="dot"></span> 2 Templates Disponíveis</span>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($mensagem) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- SELETOR DE TEMPLATES -->
    <div class="fintech-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
            <div>
                <h3 class="fintech-card-title mb-1">Aparência do Painel / Templates</h3>
                <small class="text-muted">Selecione a identidade visual que você deseja utilizar em todo o painel de controle administrativo.</small>
            </div>
            <span class="fintech-badge primary"><span class="dot"></span> Ativo: Fintech Minimalist</span>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="template">
            <div class="row g-4 mb-4">
                <!-- Template 1: Clássico -->
                <div class="col-md-6">
                    <div class="card h-100 p-3" style="border: 1px solid var(--fintech-border); border-radius: 14px; cursor: pointer; transition: all 0.2s ease;" onclick="document.getElementById('radio_classico_v2').checked = true;">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold mb-1 text-slate-900"><i class="bi bi-window text-success me-2"></i>Template 1 – Atual (Original)</h6>
                                <small class="text-muted">Layout clássico e tradicional do sistema de cobrança.</small>
                            </div>
                            <input class="form-check-input fs-5" type="radio" name="template_admin" id="radio_classico_v2" value="classico" <?= $tplAtual === 'classico' ? 'checked' : '' ?>>
                        </div>
                        <div class="p-3 my-2" style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success">Pago</span>
                                <span class="badge bg-warning text-dark">Pendente</span>
                                <span class="badge bg-danger">Vencido</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span>Design Clássico</span>
                                <span>Menu Original</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Template 2: Fintech Minimalist -->
                <div class="col-md-6">
                    <div class="card h-100 p-3" style="border: 2px solid var(--fintech-primary); border-radius: 14px; cursor: pointer; background: #fafbff;" onclick="document.getElementById('radio_fintech_v2').checked = true;">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold mb-1" style="color:var(--fintech-primary);"><i class="bi bi-palette text-primary me-2"></i>Template 2 – Fintech Minimalist</h6>
                                <small class="text-muted">Conceito Linear / Stripe: tipografia Plus Jakarta Sans, bordas sutis e foco em métricas.</small>
                            </div>
                            <input class="form-check-input fs-5" type="radio" name="template_admin" id="radio_fintech_v2" value="fintech" <?= $tplAtual === 'fintech' ? 'checked' : '' ?>>
                        </div>
                        <div class="p-3 my-2" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="fintech-badge success"><span class="dot"></span> Pago</span>
                                <span class="fintech-badge warning"><span class="dot"></span> Pendente</span>
                                <span class="fintech-badge danger"><span class="dot"></span> Vencido</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span>Executivo & SaaS</span>
                                <span>Micro Progress Bars</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-fintech-primary px-4 py-2">
                    <i class="bi bi-check2-circle me-1"></i> Aplicar Template Selecionado
                </button>
            </div>
        </form>
    </div>

    <!-- LOGOS & IDENTIDADE -->
    <div class="row g-4">
        <!-- Logotipos -->
        <div class="col-lg-6">
            <div class="fintech-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h3 class="fintech-card-title mb-0">Logotipo da Empresa</h3>
                        <small class="text-muted">Personalize a identidade da sua marca</small>
                    </div>
                    <i class="bi bi-image fs-4 text-primary"></i>
                </div>

                <!-- Logo do Menu / Painel -->
                <form method="POST" enctype="multipart/form-data" class="mb-4">
                    <input type="hidden" name="acao" value="logo_painel">
                    <label class="form-label text-muted small fw-semibold">Logo da Barra Lateral (Painel)</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <?php $logoPainel = getConfig('logo_empresa_admin', ''); ?>
                        <div style="width:70px;height:50px;border-radius:8px;border:1px solid #dee2e6;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                            <?php if ($logoPainel && logoPathValido($logoPainel)): ?>
                                <img src="<?= htmlspecialchars($logoPainel) ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
                            <?php else: ?>
                                <i class="bi bi-image text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo_painel" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-fintech-secondary">
                        <i class="bi bi-upload me-1"></i> Atualizar Logo Lateral
                    </button>
                </form>

                <hr class="my-3">

                <!-- Logo da Fatura / Login -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="logo">
                    <label class="form-label text-muted small fw-semibold">Logo das Faturas & Tela de Login</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <?php $logoFat = getConfig('logo_empresa', ''); ?>
                        <div style="width:70px;height:50px;border-radius:8px;border:1px solid #dee2e6;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                            <?php if ($logoFat && logoPathValido($logoFat)): ?>
                                <img src="<?= htmlspecialchars($logoFat) ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
                            <?php else: ?>
                                <i class="bi bi-image text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-fintech-secondary">
                        <i class="bi bi-upload me-1"></i> Atualizar Logo das Faturas
                    </button>
                </form>
            </div>
        </div>

        <!-- Cores e Nome do Sistema -->
        <div class="col-lg-6">
            <div class="fintech-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h3 class="fintech-card-title mb-0">Cores & Nome da Plataforma</h3>
                        <small class="text-muted">Ajuste o nome exibido e as tonalidades</small>
                    </div>
                    <i class="bi bi-droplet-half fs-4 text-primary"></i>
                </div>

                <form method="POST">
                    <input type="hidden" name="acao" value="cores">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Nome da Aplicação / Sistema *</label>
                            <input type="text" name="nome_sistema" class="form-control" value="<?= htmlspecialchars(getConfig('nome_sistema', 'Sistema de Cobrança')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Cor Primária</label>
                            <div class="input-group">
                                <input type="color" name="cor_primaria" class="form-control form-control-color" value="<?= htmlspecialchars(getConfig('cor_primaria', '#0f172a')) ?>">
                                <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars(getConfig('cor_primaria', '#0f172a')) ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Cor Secundária</label>
                            <div class="input-group">
                                <input type="color" name="cor_secundaria" class="form-control form-control-color" value="<?= htmlspecialchars(getConfig('cor_secundaria', '#64748b')) ?>">
                                <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars(getConfig('cor_secundaria', '#64748b')) ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Cor de Fundo</label>
                            <div class="input-group">
                                <input type="color" name="cor_fundo" class="form-control form-control-color" value="<?= htmlspecialchars(getConfig('cor_fundo', '#f8f9ff')) ?>">
                                <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars(getConfig('cor_fundo', '#f8f9ff')) ?>" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-fintech-primary px-4">
                            <i class="bi bi-save me-1"></i> Salvar Identidade
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

