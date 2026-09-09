<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/tutoriais.php';

$mensagem = '';
$tipo = '';

$gateways = [
    'mercadopago' => ['nome' => 'Mercado Pago', 'icone' => 'fab fa-pix', 'cor' => '#009ee3'],
    'inter'       => ['nome' => 'Banco Inter', 'icone' => 'fas fa-university', 'cor' => '#ff6a00'],
    'bb'          => ['nome' => 'Banco do Brasil', 'icone' => 'fas fa-university', 'cor' => '#fecd1a'],
    'pix_manual'  => ['nome' => 'PIX Manual', 'icone' => 'fas fa-qrcode', 'cor' => '#0a7d2c'],
    'asaas'       => ['nome' => 'ASAAS', 'icone' => 'fas fa-money-bill-wave', 'cor' => '#1CC3F2'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_tutoriais') {
    $ok = true;
    foreach ($gateways as $gw => $meta) {
        $valor = trim($_POST['tutorial_' . $gw] ?? '');
        if (!salvarConfigGlobal(tutorialChave($gw), $valor)) {
            $ok = false;
        }
    }
    $mensagem = $ok ? 'Tutoriais salvos com sucesso!' : 'Erro ao salvar tutoriais.';
    $tipo = $ok ? 'success' : 'danger';
}

$pageTitle = 'Tutoriais';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-video me-1"></i> Tutoriais</h5>
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

        <div class="form-card mb-4">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-video me-2"></i>Vídeos de tutoriais dos Gateways</h6>
            </div>
            <div class="p-3">
                <div class="alert alert-info py-2 mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Insira abaixo o link do vídeo de tutorial de cada gateway de pagamento. Os vídeos aparecem automaticamente no painel de cada admin, na página
                    <strong>Configurações &gt; API de Pagamento</strong>, logo abaixo da sessão "Como Configurar"/"Como Funciona".
                </div>
                <form method="POST">
                    <input type="hidden" name="acao" value="salvar_tutoriais">
                    <div class="row g-4">
                        <?php foreach ($gateways as $gw => $meta): ?>
                            <?php $urlAtual = getTutorialUrl($gw); ?>
                            <div class="col-lg-6">
                                <div class="form-card h-100">
                                    <div class="p-3 border-bottom d-flex align-items-center gap-2">
                                        <span style="width:34px;height:34px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:<?= $meta['cor'] ?>22;color:<?= $meta['cor'] ?>;">
                                            <i class="<?= $meta['icone'] ?>"></i>
                                        </span>
                                        <h6 class="mb-0"><?= $meta['nome'] ?></h6>
                                    </div>
                                    <div class="p-3">
                                        <label class="form-label">Link do vídeo (YouTube, Vimeo ou arquivo .mp4)</label>
                                        <input type="url" name="tutorial_<?= $gw ?>" class="form-control font-monospace"
                                            placeholder="https://www.youtube.com/watch?v=..."
                                            value="<?= htmlspecialchars($urlAtual) ?>">
                                        <small class="text-muted">Deixe vazio para não exibir tutorial deste gateway.</small>

                                        <?php if (trim($urlAtual) !== ''): ?>
                                            <div class="mt-3">
                                                <?php echo tutorialCover($gw, $urlAtual, 'Pré-visualização de ' . $meta['nome']); ?>
                                                <?php renderTutorialModal($gw, $urlAtual, $meta['nome'] . ' — Tutorial'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Tutoriais</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>