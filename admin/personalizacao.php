<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
$mensagem = '';
$tipo = '';
// Salvar personalização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'template') {
        $templateEscolhido = in_array($_POST['template_admin'] ?? '', ['classico', 'fintech'], true) ? $_POST['template_admin'] : 'classico';
        saveConfig('template_admin', $templateEscolhido);
        $_SESSION['admin_template'] = $templateEscolhido;
        $mensagem = 'Template atualizado com sucesso!';
        $tipo = 'success';
    }
    if ($acao === 'cores') {
        $corPrimaria = trim($_POST['cor_primaria'] ?? '#0f7b5c');
        $corSecundaria = trim($_POST['cor_secundaria'] ?? '#6c757d');
        $corFundo = trim($_POST['cor_fundo'] ?? '#f8f9fa');
        $nomeSistema = trim($_POST['nome_sistema'] ?? 'Sistema de Cobrança');
        saveConfig('cor_primaria', $corPrimaria);
        saveConfig('cor_secundaria', $corSecundaria);
        saveConfig('cor_fundo', $corFundo);
        saveConfig('nome_sistema', $nomeSistema);
        $mensagem = 'Personalização salva com sucesso!';
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
    
    if ($acao === 'logo_mobile') {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $res = salvarUploadSeguro($_FILES['logo_mobile'] ?? null, 'logos', 'logo_mobile_' . $adminId);
        if ($res['sucesso']) {
            saveConfig('logo_mobile', $res['url']);
            $mensagem = 'Logo mobile atualizada com sucesso!';
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
            $mensagem = 'Logo da empresa atualizada com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = $res['erro'];
            $tipo = 'danger';
        }
    }
}
$config = getAllConfig();
$pageTitle = 'Personalização';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Personalização</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars(function_exists('getAdminAvatarUrl') ? getAdminAvatarUrl() : ($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg')) ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/admin.svg';">
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
        <!-- SELETOR DE TEMPLATES: APARÊNCIA / TEMPLATES -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="form-card" style="border: 2px solid #e2e8f0; border-radius: 14px;">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1"><i class="fas fa-layer-group text-primary me-2"></i>Aparência / Templates do Sistema</h5>
                            <p class="text-muted mb-0" style="font-size:0.88rem;">Selecione a identidade visual que você deseja utilizar em todo o painel de controle administrativo.</p>
                        </div>
                        <?php $tplAtual = function_exists('getAdminTemplate') ? getAdminTemplate() : 'fintech'; ?>
                        <span class="badge <?= $tplAtual === 'fintech' ? 'bg-dark' : 'bg-success' ?> px-3 py-2" style="font-size:0.8rem; border-radius: 20px;">
                            Template Ativo: <?= $tplAtual === 'fintech' ? 'Fintech Minimalist (V2)' : 'Template Padrão (Unificado com Painel do Usuário)' ?>
                        </span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="acao" value="template">
                        <div class="row g-3">
                            <!-- Template 1 - Atual -->
                            <div class="col-md-6">
                                <div class="card h-100 p-3" style="border: <?= $tplAtual === 'classico' ? '2px solid #0f7b5c' : '1px solid #dee2e6' ?>; border-radius: 12px; cursor: pointer;" onclick="document.getElementById('radio_classico').checked = true;">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1"><i class="fas fa-desktop text-success me-2"></i>Template 1 – Padrão (Unificado com Painel do Usuário) <span class="badge bg-success" style="font-size:0.68rem;">Principal</span></h6>
                                            <small class="text-muted">Mesma identidade visual, estrutura, tipografia, cores e componentes aplicados ao painel do usuário, unificando a experiência da plataforma.</small>
                                        </div>
                                        <input class="form-check-input fs-5" type="radio" name="template_admin" id="radio_classico" value="classico" <?= $tplAtual === 'classico' ? 'checked' : '' ?>>
                                    </div>
                                    <div class="p-3 my-2" style="background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-success">Pago</span>
                                            <span class="badge bg-warning text-dark">Pendente</span>
                                            <span class="badge bg-danger">Vencido</span>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size:0.8rem;">
                                            <span>Visual Original</span>
                                            <span>Barra Lateral Padrão</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Template 2 - Fintech Minimalist -->
                            <div class="col-md-6">
                                <div class="card h-100 p-3" style="border: <?= $tplAtual === 'fintech' ? '2px solid #0f172a' : '1px solid #dee2e6' ?>; border-radius: 12px; cursor: pointer; background: #fafbff;" onclick="document.getElementById('radio_fintech').checked = true;">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-sparkles text-primary me-2"></i>Template 2 – Fintech Minimalist</h6>
                                            <small class="text-muted">Conceito Executive Precision: estética minimalista, tipografia Plus Jakarta Sans, fundo #f8f9ff e badges em pílula com ponto luminoso.</small>
                                        </div>
                                        <input class="form-check-input fs-5" type="radio" name="template_admin" id="radio_fintech" value="fintech" <?= $tplAtual === 'fintech' ? 'checked' : '' ?>>
                                    </div>
                                    <div class="p-3 my-2" style="background:#ffffff; border:1px solid #eef2f6; border-radius:10px; box-shadow:0 1px 3px rgba(15,23,42,0.04);">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge" style="background:#ecfdf5;color:#059669;border-radius:9999px;font-size:11px;font-weight:600;"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#10b981;margin-right:4px;"></span>Pago</span>
                                            <span class="badge" style="background:#fffbeb;color:#d97706;border-radius:9999px;font-size:11px;font-weight:600;"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#f59e0b;margin-right:4px;"></span>Pendente</span>
                                            <span class="badge" style="background:#fef2f2;color:#dc2626;border-radius:9999px;font-size:11px;font-weight:600;"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#ef4444;margin-right:4px;"></span>Vencido</span>
                                        </div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size:0.8rem;">
                                            <span>Slate 900 Executivo</span>
                                            <span>Microbarras & Gráficos Suaves</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-check-circle me-1"></i> Salvar Preferência de Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="row g-4">
            <!-- Cores e Nome -->
            <div class="col-lg-6">
                <div class="form-card">
                    <h6 class="mb-3"><i class="fas fa-palette me-2"></i>Cores e Identidade</h6>
                    <form method="POST">
                        <input type="hidden" name="acao" value="cores">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nome do Sistema</label>
                                <input type="text" name="nome_sistema" class="form-control" value="<?= htmlspecialchars($config['nome_sistema'] ?? 'Sistema de Cobrança') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor Primária</label>
                                <input type="color" name="cor_primaria" class="form-control form-control-color w-100" value="<?= htmlspecialchars($config['cor_primaria'] ?? '#0f7b5c') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor Secundária</label>
                                <input type="color" name="cor_secundaria" class="form-control form-control-color w-100" value="<?= htmlspecialchars($config['cor_secundaria'] ?? '#6c757d') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Fundo</label>
                                <input type="color" name="cor_fundo" class="form-control form-control-color w-100" value="<?= htmlspecialchars($config['cor_fundo'] ?? '#f8f9fa') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-1"></i> Salvar Cores
                        </button>
                    </form>
                </div>
            </div>
            <!-- Logo -->
            <div class="col-lg-6">
                <div class="form-card">
<h6 class="mb-3"><i class="fas fa-image me-2"></i>Logo do Login do Cliente (Computador)</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo">
                        <p class="text-muted mb-3" style="font-size:0.85rem;">Esta logo aparece somente na tela de login do cliente (área do pagador) no computador.</p>
                        
                        <?php if (!empty($config['logo_empresa']) && logoPathValido($config['logo_empresa'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($config['logo_empresa']) ?>" alt="Logo Atual" style="max-width: 250px;">
                                <br><small class="text-muted">Logo atual (Login do Cliente - Computador)</small>
                            </div>
                        <?php elseif (!empty($config['logo_empresa'])): ?>
                            <div class="alert alert-warning py-2" style="font-size:0.8rem;"><i class="fas fa-triangle-exclamation me-1"></i> A logo anterior foi removida do servidor. Envie novamente.</div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo
                        </button>
                    </form>
                </div>
            </div>
<!-- Logo Mobile -->
            <div class="col-lg-6">
                <div class="form-card">
<h6 class="mb-3"><i class="fas fa-mobile-alt me-2"></i>Logo do Login do Cliente (Mobile)</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo_mobile">
                        <p class="text-muted mb-3" style="font-size:0.85rem;">Esta logo aparece somente na tela de login do cliente no celular (app).</p>
                        
                        <?php if (!empty($config['logo_mobile']) && logoPathValido($config['logo_mobile'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($config['logo_mobile']) ?>" alt="Logo Mobile Atual" style="max-width: 200px;">
                                <br><small class="text-muted">Logo atual (Login do Cliente - Mobile)</small>
                            </div>
                        <?php elseif (!empty($config['logo_mobile'])): ?>
                            <div class="alert alert-warning py-2" style="font-size:0.8rem;"><i class="fas fa-triangle-exclamation me-1"></i> A logo anterior foi removida do servidor. Envie novamente.</div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" name="logo_mobile" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo Mobile
                        </button>
                    </form>
                </div>
            </div>
<!-- Logo da Empresa -->
            <div class="col-lg-6">
                <div class="form-card">
<h6 class="mb-3"><i class="fas fa-building me-2"></i>Logo da Empresa</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="logo_painel">
                        <p class="text-muted mb-3" style="font-size:0.85rem;">Esta logo aparece no recibo e no cabeçalho do painel administrativo.</p>
                        
                        <?php if (!empty($config['logo_empresa_admin']) && logoPathValido($config['logo_empresa_admin'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?= htmlspecialchars($config['logo_empresa_admin']) ?>" alt="Logo da Empresa Atual" style="max-width: 200px;">
                                <br><small class="text-muted">Logo atual da empresa</small>
                            </div>
                        <?php elseif (!empty($config['logo_empresa_admin'])): ?>
                            <div class="alert alert-warning py-2" style="font-size:0.8rem;"><i class="fas fa-triangle-exclamation me-1"></i> A logo anterior foi removida do servidor. Envie novamente.</div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="file" name="logo_painel" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Enviar Logo da Empresa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
