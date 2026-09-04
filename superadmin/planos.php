<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $preco = trim($_POST['preco'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $cor = trim($_POST['cor'] ?? 'secondary');
    $icon = trim($_POST['icon'] ?? 'fa-circle');
    $ordem = intval($_POST['ordem'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $limparInt = function ($v) { return ($v === '' || $v === null) ? null : (int)$v; };
    $max_clientes = $limparInt($_POST['max_clientes'] ?? null);
    $max_usuarios = $limparInt($_POST['max_usuarios'] ?? null);
    $max_faturas_mensais = $limparInt($_POST['max_faturas_mensais'] ?? null);
    $whatsapp_cobranca = isset($_POST['whatsapp_cobranca']) ? 1 : 0;
    $email_cobranca = isset($_POST['email_cobranca']) ? 1 : 0;

    if (!empty($nome) && is_numeric($preco)) {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE planos SET nome=?, preco=?, descricao=?, cor=?, icon=?, ordem=?, ativo=?, beneficios=?, max_clientes=?, max_usuarios=?, max_faturas_mensais=?, whatsapp_cobranca=?, email_cobranca=? WHERE id=?")
                    ->execute([$nome, $preco, $descricao, $cor, $icon, $ordem, $ativo, $_POST['beneficios'] ?? '', $max_clientes, $max_usuarios, $max_faturas_mensais, $whatsapp_cobranca, $email_cobranca, $id]);
            } else {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $nome));
                $pdo->prepare("INSERT INTO planos (nome, slug, preco, descricao, beneficios, cor, icon, ativo, ordem, max_clientes, max_usuarios, max_faturas_mensais, whatsapp_cobranca, email_cobranca) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$nome, $slug, $preco, $descricao, $_POST['beneficios'] ?? '', $cor, $icon, $ativo, $ordem, $max_clientes, $max_usuarios, $max_faturas_mensais, $whatsapp_cobranca, $email_cobranca]);
            }
            header('Location: planos.php?msg=salvo');
            exit;
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . $e->getMessage();
            $tipo = 'danger';
        }
    } else {
        $mensagem = 'Nome e preço são obrigatórios.';
        $tipo = 'danger';
    }
}

if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $pdo->prepare("DELETE FROM admin_planos WHERE plano_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM planos WHERE id = ?")->execute([$id]);
    $mensagem = 'Plano excluído!';
    $tipo = 'success';
}

if (isset($_GET['msg'])) {
    $mensagem = 'Plano salvo com sucesso!';
    $tipo = 'success';
}

$planos = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM admin_planos ap WHERE ap.plano_id = p.id) AS total_admins FROM planos p ORDER BY p.ordem ASC")->fetchAll();

$pageTitle = 'Planos';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-tags me-1"></i> Planos</h5>
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
            <div class="col-lg-4">
                <div class="form-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Novo Plano</h6>
                    </div>
                    <div class="p-3">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required placeholder="Ex: Diamante">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preço (R$) *</label>
                                <input type="number" step="0.01" name="preco" class="form-control" required placeholder="0.00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Benefícios (um por linha)</label>
                                <textarea name="beneficios" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cor</label>
                                <select name="cor" class="form-select">
                                    <option value="primary">Azul (primary)</option>
                                    <option value="secondary">Cinza (secondary)</option>
                                    <option value="success">Verde (success)</option>
                                    <option value="warning">Amarelo (warning)</option>
                                    <option value="danger">Vermelho (danger)</option>
                                    <option value="info">Azul claro (info)</option>
                                    <option value="bronze">Bronze</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ícone</label>
                                <select name="icon" class="form-select">
                                    <option value="fa-crown">Coroa <i class="fas fa-crown"></i></option>
                                    <option value="fa-medal">Medalha</option>
                                    <option value="fa-gem">Gema (Diamante)</option>
                                    <option value="fa-star">Estrela</option>
                                    <option value="fa-rocket">Foguete</option>
                                    <option value="fa-circle-half-stroke">Círculo (Prata)</option>
                                    <option value="fa-circle">Círculo</option>
                                    <option value="fa-bolt">Raio</option>
                                    <option value="fa-shield-halved">Escudo</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ordem</label>
                                <input type="number" name="ordem" class="form-control" value="10">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Limite de clientes (vazio = ilimitado)</label>
                                <input type="number" min="0" name="max_clientes" class="form-control" placeholder="Ex: 100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Limite de usuários (vazio = ilimitado)</label>
                                <input type="number" min="0" name="max_usuarios" class="form-control" placeholder="Ex: 3">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Limite de faturas/mês (vazio = ilimitado)</label>
                                <input type="number" min="0" name="max_faturas_mensais" class="form-control" placeholder="Ex: 500">
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="whatsapp_cobranca" id="planoWhats" checked>
                                <label class="form-check-label" for="planoWhats">Cobranças por WhatsApp</label>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_cobranca" id="planoEmail" checked>
                                <label class="form-check-label" for="planoEmail">Cobranças por e-mail</label>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="planoAtivo" checked>
                                <label class="form-check-label" for="planoAtivo">Plano ativo</label>
                            </div>
                            <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Cadastrar Plano</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="table-card">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Planos Cadastrados</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Plano</th>
                                    <th>Preço</th>
                                    <th>Admins</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($planos)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum plano cadastrado</td></tr>
                                <?php else: foreach ($planos as $pl):
                                    $corHex = planoCorHex($pl['cor'] ?? 'secondary');
                                    $iconCls = planoIconClass($pl['icon'] ?: 'fa-circle');
                                    $tinta = $corHex . '1f';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="plano-ico-tile" style="background:<?= $tinta ?>;color:<?= $corHex ?>;" title="<?= htmlspecialchars($pl['icon'] ?: '') ?>">
                                                    <i class="<?= $iconCls ?>"></i>
                                                </span>
                                                <div>
                                                    <strong><?= htmlspecialchars($pl['nome']) ?></strong>
                                                    <?php if (!empty($pl['descricao'])): ?>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($pl['descricao']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>R$ <?= number_format($pl['preco'], 2, ',', '.') ?></td>
                                        <td><?= $pl['total_admins'] ?></td>
                                        <td>
                                            <?php if ($pl['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a href="edit_plano.php?editar=<?= $pl['id'] ?>" class="acao-btn acao-btn-primary" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                                <a href="planos.php?excluir=<?= $pl['id'] ?>" class="acao-btn acao-btn-danger" title="Excluir" onclick="return confirmarExclusao('<?= htmlspecialchars(addslashes($pl['nome'])) ?>', this.href);"><i class="bi bi-trash3"></i></a>
                                            </div>
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
