<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isV2Dir = strpos($_SERVER['PHP_SELF'], '/admin_v2') !== false;
$basePath = $isV2Dir ? '/cobranca/admin_v2' : '/cobranca/admin';
$templateAtual = function_exists('getAdminTemplate') ? getAdminTemplate() : ($isV2Dir ? 'fintech' : 'classico');

$planoExpirado = function_exists('adminPlanoExpirado') ? adminPlanoExpirado() : false;
$adminDesativado = function_exists('adminEstaAtivo') ? !adminEstaAtivo() : false;
$acessoRestrito = $adminDesativado;
$semPlano = function_exists('adminTemPlanoAtivo') ? !adminTemPlanoAtivo() : false;

$logoEmpresaAdmin = function_exists('getConfig') ? getConfig('logo_empresa_admin', '') : '';
if ($logoEmpresaAdmin && function_exists('logoPathValido') && logoPathValido($logoEmpresaAdmin)) {
    $logo = $logoEmpresaAdmin;
}
?>

<aside class="fintech-sidebar" id="fintechSidebar">
    <div class="fintech-sidebar-header">
        <a href="<?= $basePath ?>/index.php" class="fintech-brand">
            <?php if (!empty($logo)): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" class="fintech-brand-logo">
            <?php else: ?>
                <div class="d-flex align-items-center gap-2">
                    <div style="width:34px;height:34px;border-radius:10px;background:var(--fintech-primary);display:flex;align-items:center;justify-content:center;color:#fff;">
                        <i class="fas fa-wallet" style="font-size:16px;"></i>
                    </div>
                    <span style="font-weight:800;font-size:16px;letter-spacing:-0.02em;color:var(--fintech-primary);"><?= htmlspecialchars(getNomeSistema()) ?></span>
                </div>
            <?php endif; ?>
        </a>
        <span class="fintech-brand-badge">Fintech</span>
    </div>

    <nav class="fintech-sidebar-nav">
        <?php if (!$acessoRestrito): ?>
            <div class="fintech-nav-category">Operações Principais</div>
            
            <a href="<?= $basePath ?>/index.php" class="fintech-nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Painel Executivo</span>
            </a>

            <a href="<?= $basePath ?>/cadastro.php" class="fintech-nav-item <?= $currentPage === 'cadastro' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Clientes</span>
            </a>

            <?php if (!$semPlano): ?>
            <a href="<?= $basePath ?>/emissao.php" class="fintech-nav-item <?= $currentPage === 'emissao' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                <span>Emitir Faturas / Carnês</span>
            </a>

            <a href="<?= $basePath ?>/faturas.php" class="fintech-nav-item <?= $currentPage === 'faturas' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i>
                <span>Gestão de Faturas</span>
            </a>

            <a href="<?= $basePath ?>/recibo_avulso.php" class="fintech-nav-item <?= $currentPage === 'recibo_avulso' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                <span>Recibo Avulso</span>
            </a>
            <?php endif; ?>

            <a href="<?= $basePath ?>/livro_caixa.php" class="fintech-nav-item <?= $currentPage === 'livro_caixa' ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark"></i>
                <span>Livro Caixa</span>
            </a>

            <a href="<?= $basePath ?>/inadimplencia.php" class="fintech-nav-item <?= $currentPage === 'inadimplencia' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-octagon"></i>
                <span>Inadimplência</span>
                <?php if (($totalAlerta ?? 0) > 0): ?>
                    <span class="fintech-nav-badge"><?= (int)$totalAlerta ?></span>
                <?php endif; ?>
            </a>

            <?php if (!$semPlano): ?>
            <div class="fintech-nav-category">Automações & APIs</div>

            <a href="<?= $basePath ?>/config_api.php" class="fintech-nav-item <?= $currentPage === 'config_api' ? 'active' : '' ?>">
                <i class="bi bi-cpu"></i>
                <span>Gateways de Pagamento</span>
            </a>

            <a href="<?= $basePath ?>/envios.php" class="fintech-nav-item <?= $currentPage === 'envios' ? 'active' : '' ?>">
                <i class="bi bi-send-check"></i>
                <span>Régua de Cobrança</span>
            </a>

            <a href="<?= $basePath ?>/whatsapp.php" class="fintech-nav-item <?= $currentPage === 'whatsapp' ? 'active' : '' ?>">
                <i class="bi bi-whatsapp"></i>
                <span>WhatsApp API</span>
            </a>

            <a href="<?= $basePath ?>/personalizacao.php" class="fintech-nav-item <?= $currentPage === 'personalizacao' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i>
                <span>Personalização & Templates</span>
            </a>
            <?php endif; ?>

            <div class="fintech-nav-category">Administração</div>

            <a href="<?= $basePath ?>/meu_plano.php" class="fintech-nav-item <?= $currentPage === 'meu_plano' ? 'active' : '' ?>">
                <i class="bi bi-award"></i>
                <span>Meu Plano</span>
            </a>

            <a href="<?= $basePath ?>/minhas_faturas.php" class="fintech-nav-item <?= $currentPage === 'minhas_faturas' ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front"></i>
                <span>Minhas Faturas</span>
            </a>

            <a href="<?= $basePath ?>/usuarios.php" class="fintech-nav-item <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
                <i class="bi bi-shield-lock"></i>
                <span>Operadores</span>
            </a>

            <a href="<?= $basePath ?>/perfil.php" class="fintech-nav-item <?= $currentPage === 'perfil' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Meu Perfil</span>
            </a>
        <?php endif; ?>

        <a href="/cobranca/admin/logout.php" class="fintech-nav-item text-danger mt-2">
            <i class="bi bi-box-arrow-right text-danger"></i>
            <span>Encerrar Sessão</span>
        </a>
    </nav>

    <div class="fintech-sidebar-footer">
        <?php
        $toggleUrl = $isV2Dir
            ? "/cobranca/admin/{$currentPage}.php?template=classico"
            : ($templateAtual === 'fintech' ? "?template=classico" : "?template=fintech");
        ?>
        <a href="<?= $toggleUrl ?>" class="fintech-template-pill" title="Alternar para o Template Clássico">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left-right text-primary"></i>
                <span>Template Clássico</span>
            </div>
            <i class="bi bi-chevron-right text-muted" style="font-size:10px;"></i>
        </a>
    </div>
</aside>

<div class="fintech-main">
    <header class="fintech-topbar">
        <div class="fintech-topbar-left">
            <button class="btn btn-fintech-icon d-lg-none" id="fintechSidebarToggle" type="button" aria-label="Abrir menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="fintech-breadcrumb">
                <span class="fintech-breadcrumb-sub"><?= htmlspecialchars($pageCategory ?? 'Visão Geral') ?></span>
                <h1 class="fintech-breadcrumb-title"><?= htmlspecialchars($pageTitle ?? 'Painel') ?></h1>
            </div>
        </div>

        <div class="fintech-topbar-right">
            <!-- Botão Trocar Template no Topo do Sistema -->
            <a href="<?= $toggleUrl ?>" class="fintech-switch-btn" title="Clique para alternar para o Template Clássico">
                <i class="bi bi-arrow-left-right text-primary"></i>
                <span class="fw-bold">Trocar Template</span>
                <span class="badge bg-dark text-white ms-1" style="font-size:10px; font-weight:700;">Clássico</span>
            </a>

            <!-- Suporte WhatsApp -->
            <a href="https://wa.me/5591982675573" target="_blank" class="fintech-support-btn d-none d-sm-inline-flex">
                <i class="bi bi-headset"></i>
                <span>Suporte</span>
            </a>

            <!-- Dropdown Usuário com Avatar Funcional -->
            <div class="dropdown">
                <a href="#" class="fintech-admin-dropdown dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= htmlspecialchars(function_exists('getAdminAvatarUrl') ? getAdminAvatarUrl() : ($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg')) ?>" alt="Avatar" class="fintech-admin-avatar" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/admin.svg';">
                    <div class="fintech-admin-info d-none d-md-flex">
                        <span class="fintech-admin-name"><?= htmlspecialchars($_SESSION['admin_nome'] ?? 'Administrador') ?></span>
                        <span class="fintech-admin-role"><?= htmlspecialchars($_SESSION['admin_email'] ?? 'Admin') ?></span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius:12px;border:1px solid var(--fintech-border);">
                    <li class="px-3 py-2 border-bottom">
                        <strong class="d-block text-dark"><?= htmlspecialchars($_SESSION['admin_nome'] ?? 'Administrador') ?></strong>
                        <small class="text-muted"><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></small>
                    </li>
                    <li><a class="dropdown-item py-2" href="<?= $basePath ?>/perfil.php"><i class="bi bi-person me-2"></i>Meu Perfil</a></li>
                    <li><a class="dropdown-item py-2" href="<?= $basePath ?>/personalizacao.php"><i class="bi bi-sliders me-2"></i>Aparência / Templates</a></li>
                    <li><a class="dropdown-item py-2" href="<?= $basePath ?>/meu_plano.php"><i class="bi bi-award me-2"></i>Meu Plano</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="/cobranca/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="fintech-content">
