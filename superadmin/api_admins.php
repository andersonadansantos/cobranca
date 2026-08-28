<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$mensagem = '';
$tipo = '';

$adminId = isset($_GET['admin']) ? intval($_GET['admin']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminId = intval($_POST['admin_id'] ?? 0);
    $url_api = trim($_POST['url_api'] ?? '');
    $api_key = trim($_POST['api_key'] ?? '');
    $instance = trim($_POST['instance'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($adminId > 0 && !empty($url_api) && !empty($instance)) {
        try {
            $check = $pdo->prepare("SELECT id FROM admin_evolution WHERE admin_id = ?");
            $check->execute([$adminId]);
            if ($check->fetch()) {
                $pdo->prepare("UPDATE admin_evolution SET url_api=?, api_key=?, instance=?, ativo=? WHERE admin_id=?")
                    ->execute([$url_api, $api_key, $instance, $ativo, $adminId]);
            } else {
                $pdo->prepare("INSERT INTO admin_evolution (admin_id, url_api, api_key, instance, ativo) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$adminId, $url_api, $api_key, $instance, $ativo]);
            }
            $mensagem = 'Configuração de WhatsApp salva com sucesso!';
            $tipo = 'success';
        } catch (Exception $e) {
            $mensagem = 'Erro ao salvar: ' . $e->getMessage();
            $tipo = 'danger';
        }
    } else {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipo = 'danger';
    }
}

$admins = $pdo->query("SELECT id, nome, usuario FROM administradores ORDER BY nome ASC")->fetchAll();

$evoEdit = null;
if ($adminId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM admin_evolution WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    $evoEdit = $stmt->fetch();
}

$todos = $pdo->query("
    SELECT a.id, a.nome, a.usuario,
           ae.url_api, ae.api_key, ae.instance, ae.ativo AS evo_ativo
    FROM administradores a
    LEFT JOIN admin_evolution ae ON ae.admin_id = a.id
    ORDER BY a.nome ASC
")->fetchAll();

$pageTitle = 'API Admins';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fab fa-whatsapp me-1"></i> API dos Admins (WhatsApp)</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> py-2"><i class="fas fa-info-circle me-1"></i> <?= $mensagem ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-plug me-2"></i>Configuração de WhatsApp</h6>
                    </div>
                    <div class="p-3">
                        <div class="mb-3">
                            <label class="form-label">Admin *</label>
                            <select name="admin_id" class="form-select" onchange="if(this.value) location.href='api_admins.php?admin='+this.value;">
                                <option value="">— Selecione um admin —</option>
                                <?php foreach ($admins as $a): ?>
                                    <option value="<?= $a['id'] ?>" <?= ($adminId == $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars($a['nome']) ?> (<?= htmlspecialchars($a['usuario']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($adminId > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="admin_id" value="<?= $adminId ?>">
                                <div class="mb-3">
                                    <label class="form-label">URL da API *</label>
                                    <input type="text" name="url_api" class="form-control" placeholder="https://seu-evolution.com" required value="<?= htmlspecialchars($evoEdit['url_api'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">API Key</label>
                                    <input type="text" name="api_key" class="form-control" placeholder="Chave da API" value="<?= htmlspecialchars($evoEdit['api_key'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Instância *</label>
                                    <input type="text" name="instance" class="form-control" placeholder="Nome da instância" required value="<?= htmlspecialchars($evoEdit['instance'] ?? '') ?>">
                                </div>
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="evoAtivo" <?= (empty($evoEdit) || $evoEdit['ativo']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="evoAtivo">Instância ativa</label>
                                </div>
                                <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Salvar Configuração</button>
                            </form>
                        <?php else: ?>
                            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>Selecione um admin para configurar a instância do WhatsApp.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Instâncias por Admin</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Instância</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($todos)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Nenhum admin</td></tr>
                                <?php else: foreach ($todos as $t): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($t['nome']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($t['usuario']) ?></small></td>
                                        <td>
                                            <?php if (!empty($t['instance'])): ?>
                                                <?= htmlspecialchars($t['instance']) ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($t['url_api'] ?? '') ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($t['instance']) && $t['evo_ativo']): ?>
                                                <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i>Ativo</span>
                                            <?php elseif (!empty($t['instance'])): ?>
                                                <span class="badge bg-warning">Configurado</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Bloqueado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="api_admins.php?admin=<?= $t['id'] ?>" class="acao-btn" style="background:#25D366;color:#fff;" title="Configurar"><i class="fas fa-cog"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
