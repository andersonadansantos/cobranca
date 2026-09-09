<?php
require_once __DIR__ . '/auth.php';
requireSuper();
$pdo = getConnection();

$mensagem = '';
$tipo = '';

if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    try {
        $pdo->prepare("DELETE FROM admin_evolution WHERE admin_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM admin_planos WHERE admin_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM administradores WHERE id = ?")->execute([$id]);
        $mensagem = 'Admin excluído com sucesso!';
        $tipo = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro ao excluir admin.';
        $tipo = 'danger';
    }
} elseif (isset($_GET['ativar'])) {
    $pdo->prepare("UPDATE administradores SET ativo = 1 WHERE id = ?")->execute([intval($_GET['ativar'])]);
    $mensagem = 'Admin ativado!';
    $tipo = 'success';
} elseif (isset($_GET['desativar'])) {
    $pdo->prepare("UPDATE administradores SET ativo = 0 WHERE id = ?")->execute([intval($_GET['desativar'])]);
    $mensagem = 'Admin desativado!';
    $tipo = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $subdominio = strtolower(trim($_POST['subdominio'] ?? ''));
    $plano_id = intval($_POST['plano_id'] ?? 0);
    $data_fim = trim($_POST['data_fim'] ?? '');
    $id_edit = intval($_POST['id'] ?? 0);

    // Campos da empresa (necessários para emissão de boleto e PIX nas faturas)
    $emp = [];
    $emp['razao_social'] = trim($_POST['razao_social'] ?? '');
    $emp['nome_fantasia'] = trim($_POST['nome_fantasia'] ?? '');
    $emp['cnpj'] = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $emp['cpf'] = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $emp['inscricao_estadual'] = trim($_POST['inscricao_estadual'] ?? '');
    $emp['inscricao_municipal'] = trim($_POST['inscricao_municipal'] ?? '');
    $emp['telefone_comercial'] = trim($_POST['telefone_comercial'] ?? '');
    $emp['email_comercial'] = trim($_POST['email_comercial'] ?? '');
    $emp['cep'] = preg_replace('/[^0-9]/', '', $_POST['cep_empresa'] ?? '');
    $emp['logradouro'] = trim($_POST['logradouro'] ?? '');
    $emp['numero'] = trim($_POST['numero'] ?? '');
    $emp['complemento'] = trim($_POST['complemento'] ?? '');
    $emp['bairro'] = trim($_POST['bairro'] ?? '');
    $emp['cidade'] = trim($_POST['cidade'] ?? '');
    $emp['estado'] = strtoupper(trim($_POST['estado'] ?? ''));

    // Campos obrigatórios da empresa para emissão de boleto/PIX (complemento é opcional)
    $empObrigatorios = [
        'razao_social' => 'Razão Social',
        'nome_fantasia' => 'Nome Fantasia',
        'cnpj' => 'CNPJ',
        'telefone_comercial' => 'Telefone Comercial',
        'email_comercial' => 'E-mail Comercial',
        'cep' => 'CEP',
        'logradouro' => 'Logradouro',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'estado' => 'UF',
    ];
    $empFaltando = [];
    foreach ($empObrigatorios as $chave => $rotulo) {
        if (empty($emp[$chave])) $empFaltando[] = $rotulo;
    }

    if (empty($usuario) || empty($nome)) {
        $mensagem = 'Usuário e nome são obrigatórios.';
        $tipo = 'danger';
    } elseif (!empty($empFaltando)) {
        $mensagem = 'Preencha os dados da empresa: ' . implode(', ', $empFaltando) . '.';
        $tipo = 'danger';
    } elseif (!empty($senha) && strlen($senha) < 6) {
        $mensagem = 'A senha deve ter no mínimo 6 caracteres.';
        $tipo = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            if ($id_edit > 0) {
                $sql = "UPDATE administradores SET usuario=?, nome=?, email=?, ativo=?, subdominio=?,
                        razao_social=?, nome_fantasia=?, cnpj=?, cpf=?, inscricao_estadual=?, inscricao_municipal=?, telefone_comercial=?, email_comercial=?,
                        cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=?";
                $params = [$usuario, $nome, $email, $ativo, $subdominio,
                    $emp['razao_social'], $emp['nome_fantasia'], $emp['cnpj'], $emp['cpf'], $emp['inscricao_estadual'], $emp['inscricao_municipal'], $emp['telefone_comercial'], $emp['email_comercial'],
                    $emp['cep'], $emp['logradouro'], $emp['numero'], $emp['complemento'], $emp['bairro'], $emp['cidade'], $emp['estado']];
                if (!empty($senha)) {
                    $sql .= ", senha=?";
                    $params[] = password_hash($senha, PASSWORD_BCRYPT);
                }
                $sql .= " WHERE id=?";
                $params[] = $id_edit;
                $pdo->prepare($sql)->execute($params);

                if ($plano_id > 0) {
                    $pdo->prepare("INSERT INTO admin_planos (admin_id, plano_id, data_inicio, data_fim) VALUES (?, ?, CURDATE(), ?)
                        ON DUPLICATE KEY UPDATE plano_id=VALUES(plano_id), data_fim=VALUES(data_fim)")
                        ->execute([$id_edit, $plano_id, $data_fim ?: null]);
                } else {
                    $pdo->prepare("DELETE FROM admin_planos WHERE admin_id = ?")->execute([$id_edit]);
                }
            } else {
                if (empty($senha)) $senha = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $pdo->prepare("INSERT INTO administradores (usuario, nome, email, senha, ativo, subdominio,
                        razao_social, nome_fantasia, cnpj, cpf, inscricao_estadual, inscricao_municipal, telefone_comercial, email_comercial,
                        cep, logradouro, numero, complemento, bairro, cidade, estado)
                    VALUES (?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$usuario, $nome, $email, password_hash($senha, PASSWORD_BCRYPT), $ativo, $subdominio,
                        $emp['razao_social'], $emp['nome_fantasia'], $emp['cnpj'], $emp['cpf'], $emp['inscricao_estadual'], $emp['inscricao_municipal'], $emp['telefone_comercial'], $emp['email_comercial'],
                        $emp['cep'], $emp['logradouro'], $emp['numero'], $emp['complemento'], $emp['bairro'], $emp['cidade'], $emp['estado']]);
                $novoId = (int)$pdo->lastInsertId();
                if ($plano_id > 0) {
                    $pdo->prepare("INSERT INTO admin_planos (admin_id, plano_id, data_inicio, data_fim) VALUES (?, ?, CURDATE(), ?)")
                        ->execute([$novoId, $plano_id, $data_fim ?: null]);
                }
            }
            $pdo->commit();
            header('Location: cadastros.php?msg=salvo');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = 'Erro: ' . ($e->getCode() == 23000 ? 'Usuário já cadastrado.' : $e->getMessage());
            $tipo = 'danger';
        }
    }
}

if (isset($_GET['msg'])) {
    $msgs = [
        'salvo' => ['Admin salvo com sucesso!', 'success'],
        'nao_encontrado' => ['Admin não encontrado.', 'danger'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

$admins = $pdo->query("
    SELECT a.*,
           ae.url_api, ae.api_key, ae.instance,
           p.nome AS plano, p.cor AS plano_cor, p.icon AS plano_icon,
           ap.plano_id AS admin_plano_id, ap.data_fim AS admin_plano_fim
    FROM administradores a
    LEFT JOIN admin_evolution ae ON ae.admin_id = a.id
    LEFT JOIN admin_planos ap ON ap.admin_id = a.id
    LEFT JOIN planos p ON p.id = ap.plano_id
    ORDER BY a.criado_em DESC
")->fetchAll();

$planos = $pdo->query("SELECT id, nome, preco, cor, icon FROM planos WHERE ativo = 1 ORDER BY ordem ASC")->fetchAll();

$planOptionsHtml = '<option value="">— Sem plano —</option>';
foreach ($planos as $p) {
    $planOptionsHtml .= '<option value="' . $p['id'] . '">' . htmlspecialchars($p['nome']) . ' - R$ ' . number_format($p['preco'], 2, ',', '.') . '</option>';
}

$pageTitle = 'Cadastros';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-building me-1"></i> Cadastros de Admins</h5>
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
                                <h6 class="mb-0"><i class="fas fa-user-plus me-2"></i>Novo Admin</h6>
                            </div>
                            <div class="p-3">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Usuário *</label>
                                        <input type="text" name="usuario" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Subdomínio</label>
                                        <input type="text" name="subdominio" class="form-control" placeholder="ex.: clientea">
                                        <div class="form-text">Acesso pelo subdomínio .seudominio.com (ex.: clientea.seudominio.com)</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" name="nome" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Senha *</label>
                                        <input type="password" name="senha" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Plano</label>
                                        <select name="plano_id" class="form-select">
                                            <?= $planOptionsHtml ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Vencimento do plano</label>
                                        <input type="date" name="data_fim" class="form-control">
                                    </div>
                                    <hr>
                                    <div class="small text-muted mb-3"><i class="fas fa-building me-1"></i> Dados da empresa (para emissão de boleto e PIX)</div>
                                    <div class="mb-3">
                                        <label class="form-label">Razão Social</label>
                                        <input type="text" name="razao_social" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nome Fantasia</label>
                                        <input type="text" name="nome_fantasia" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">CNPJ</label>
                                        <input type="text" name="cnpj" class="form-control mask-cpf" required placeholder="00.000.000/0000-00">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">CPF</label>
                                        <input type="text" name="cpf" class="form-control mask-cpf" placeholder="000.000.000-00">
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label">Inscrição Estadual</label>
                                            <input type="text" name="inscricao_estadual" class="form-control">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Inscrição Municipal</label>
                                            <input type="text" name="inscricao_municipal" class="form-control">
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label">Telefone Comercial</label>
                                            <input type="text" name="telefone_comercial" class="form-control mask-phone" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">E-mail Comercial</label>
                                            <input type="email" name="email_comercial" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label">CEP</label>
                                            <input type="text" name="cep_empresa" class="form-control mask-cep" required placeholder="00000-000">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">UF</label>
                                            <input type="text" name="estado" class="form-control" required maxlength="2" placeholder="SP">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Logradouro</label>
                                        <input type="text" name="logradouro" class="form-control" required>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-4">
                                            <label class="form-label">Número</label>
                                            <input type="text" name="numero" class="form-control" required>
                                        </div>
                                        <div class="col-8">
                                            <label class="form-label">Complemento</label>
                                            <input type="text" name="complemento" class="form-control">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Bairro</label>
                                        <input type="text" name="bairro" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control" required>
                                    </div>
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="ativo" id="ativoNovo" checked>
                                        <label class="form-check-label" for="ativoNovo">Admin ativo</label>
                                    </div>
                                    <button class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Cadastrar Admin</button>
                                </form>
                            </div>
                        </div>
            </div>

            <div class="col-lg-8">
                <div class="table-card">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Admins (<?= count($admins) ?>)</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Usuário</th>
                                    <th>Plano</th>
                                    <th>WhatsApp</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admins)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum admin cadastrado</td></tr>
                                <?php else: foreach ($admins as $adm): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($adm['nome']) ?></strong>
                                            <?php if (($adm['origem'] ?? 'painel') === 'site'): ?>
                                                <span class="badge bg-info ms-1"><i class="bi bi-globe me-1"></i>Site</span>
                                            <?php elseif (($adm['origem'] ?? 'painel') === 'demo'): ?>
                                                <span class="badge bg-warning text-dark ms-1"><i class="fas fa-flask me-1"></i>Demo</span>
                                            <?php endif; ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($adm['email']) ?></small></td>
                                        <td><?= htmlspecialchars($adm['usuario']) ?></td>
                                        <td>
                                            <?php if (!empty($adm['plano'])): ?>
                                                <span class="badge" style="background:#000000;color:#fff;"><?= htmlspecialchars($adm['plano']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sem plano</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($adm['instance']) && !empty($adm['url_api'])): ?>
                                                <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i>Configurado</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Bloqueado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($adm['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-inline-flex gap-1">
                                                <a href="logar_como.php?admin=<?= (int)$adm['id'] ?>" class="acao-btn acao-btn-info" title="Logar como este admin"><i class="bi bi-person-bounding-box"></i></a>
                                                <a href="javascript:void(0)" class="acao-btn acao-btn-primary" title="Editar" data-admin='<?= htmlspecialchars(json_encode(['id'=>$adm['id'],'usuario'=>$adm['usuario'],'nome'=>$adm['nome'],'email'=>$adm['email'],'ativo'=>(int)$adm['ativo'],'plano_id'=>(int)($adm['admin_plano_id'] ?? 0),'data_fim'=>$adm['admin_plano_fim']??'',
                                                    'razao_social'=>$adm['razao_social']??'', 'nome_fantasia'=>$adm['nome_fantasia']??'', 'cnpj'=>$adm['cnpj']??'', 'cpf'=>$adm['cpf']??'', 'inscricao_estadual'=>$adm['inscricao_estadual']??'', 'inscricao_municipal'=>$adm['inscricao_municipal']??'', 'telefone_comercial'=>$adm['telefone_comercial']??'', 'email_comercial'=>$adm['email_comercial']??'',
                                                    'cep'=>$adm['cep']??'', 'logradouro'=>$adm['logradouro']??'', 'numero'=>$adm['numero']??'', 'complemento'=>$adm['complemento']??'', 'bairro'=>$adm['bairro']??'', 'cidade'=>$adm['cidade']??'', 'estado'=>$adm['estado']??''
                                                ], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG), ENT_QUOTES) ?>' onclick="openEditModal(this)"><i class="bi bi-pencil-square"></i></a>
                                                <a href="api_admins.php?admin=<?= $adm['id'] ?>" class="acao-btn" style="background:#25D366;color:#fff;" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                                <?php if ($adm['ativo']): ?>
                                                    <a href="cadastros.php?desativar=<?= $adm['id'] ?>" class="acao-btn acao-btn-secondary" title="Desativar"><i class="bi bi-pause-circle"></i></a>
                                                <?php else: ?>
                                                    <a href="cadastros.php?ativar=<?= $adm['id'] ?>" class="acao-btn acao-btn-success" title="Ativar"><i class="bi bi-play-circle"></i></a>
                                                <?php endif; ?>
                                                <a href="#" class="acao-btn acao-btn-danger" title="Excluir" onclick="return confirmarExclusaoAdmin('<?= htmlspecialchars(addslashes($adm['nome'])) ?>', <?= (int)$adm['id'] ?>);"><i class="bi bi-trash3"></i></a>
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

<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Admin: <span id="editNomeTitulo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Usuário *</label>
                            <input type="text" name="usuario" id="editUsuario" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" id="editNome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subdomínio</label>
                            <input type="text" name="subdominio" id="editSubdominio" class="form-control" placeholder="ex.: clientea">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nova senha (deixe vazio para manter)</label>
                            <input type="password" name="senha" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Plano</label>
                            <select name="plano_id" id="editPlano" class="form-select">
                                <?= $planOptionsHtml ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vencimento do plano</label>
                            <input type="date" name="data_fim" id="editDataFim" class="form-control">
                        </div>
                        <div class="col-12">
                            <hr>
                            <div class="small text-muted"><i class="fas fa-building me-1"></i> Dados da empresa (para emissão de boleto e PIX)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Razão Social</label>
                            <input type="text" name="razao_social" id="editRazao" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nome Fantasia</label>
                            <input type="text" name="nome_fantasia" id="editFantasia" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CNPJ</label>
                            <input type="text" name="cnpj" id="editCnpj" class="form-control mask-cpf" required placeholder="00.000.000/0000-00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF</label>
                            <input type="text" name="cpf" id="editCpf" class="form-control mask-cpf" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail Comercial</label>
                            <input type="email" name="email_comercial" id="editEmailComercial" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inscrição Estadual</label>
                            <input type="text" name="inscricao_estadual" id="editInscEstadual" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inscrição Municipal</label>
                            <input type="text" name="inscricao_municipal" id="editInscMunicipal" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone Comercial</label>
                            <input type="text" name="telefone_comercial" id="editTelefone" class="form-control mask-phone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CEP</label>
                            <input type="text" name="cep_empresa" id="editCep" class="form-control mask-cep" required placeholder="00000-000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" id="editLogradouro" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Número</label>
                            <input type="text" name="numero" id="editNumero" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Complemento</label>
                            <input type="text" name="complemento" id="editComplemento" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bairro</label>
                            <input type="text" name="bairro" id="editBairro" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" id="editCidade" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF</label>
                            <input type="text" name="estado" id="editEstado" class="form-control" required maxlength="2" placeholder="SP">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="editAtivo">
                                <label class="form-check-label" for="editAtivo">Admin ativo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(el) {
    var d = el.getAttribute('data-admin');
    d = d.replace(/&quot;/g, '"');
    var admin = JSON.parse(d);
    document.getElementById('editId').value = admin.id;
    document.getElementById('editUsuario').value = admin.usuario;
    document.getElementById('editNome').value = admin.nome;
    document.getElementById('editNomeTitulo').textContent = admin.nome;
    document.getElementById('editEmail').value = admin.email || '';
    document.getElementById('editSubdominio').value = admin.subdominio || '';
    document.getElementById('editAtivo').checked = admin.ativo == 1;
    document.getElementById('editPlano').value = admin.plano_id || '';
    document.getElementById('editDataFim').value = admin.data_fim || '';
    document.getElementById('editRazao').value = admin.razao_social || '';
    document.getElementById('editFantasia').value = admin.nome_fantasia || '';
    document.getElementById('editCnpj').value = admin.cnpj || '';
    document.getElementById('editCpf').value = admin.cpf || '';
    document.getElementById('editEmailComercial').value = admin.email_comercial || '';
    document.getElementById('editInscEstadual').value = admin.inscricao_estadual || '';
    document.getElementById('editInscMunicipal').value = admin.inscricao_municipal || '';
    document.getElementById('editTelefone').value = admin.telefone_comercial || '';
    document.getElementById('editCep').value = admin.cep || '';
    document.getElementById('editLogradouro').value = admin.logradouro || '';
    document.getElementById('editNumero').value = admin.numero || '';
    document.getElementById('editComplemento').value = admin.complemento || '';
    document.getElementById('editBairro').value = admin.bairro || '';
    document.getElementById('editCidade').value = admin.cidade || '';
    document.getElementById('editEstado').value = admin.estado || '';
    new bootstrap.Modal(document.getElementById('editAdminModal')).show();
}

(function () {
    function maskCpf(el) {
        el.addEventListener('input', function () {
            var v = el.value.replace(/\D/g, '');
            if (v.length > 14) v = v.substring(0, 14);
            if (v.length > 11) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})$/, '$1.$2.$3/$4-$5');
            else if (v.length > 9) v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
            else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{1,3})$/, '$1.$2.$3');
            else if (v.length > 3) v = v.replace(/^(\d{3})(\d{1,3})$/, '$1.$2');
            el.value = v;
        });
    }
    function maskPhone(el) {
        el.addEventListener('input', function () {
            var v = el.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            el.value = v;
        });
    }
    function maskCep(el) {
        el.addEventListener('input', function () {
            var v = el.value.replace(/\D/g, '');
            if (v.length > 8) v = v.substring(0, 8);
            if (v.length > 5) v = v.replace(/^(\d{5})(\d{1,3})$/, '$1-$2');
            el.value = v;
        });
    }
    function bindMasks(scope) {
        (scope || document).querySelectorAll('.mask-cpf').forEach(maskCpf);
        (scope || document).querySelectorAll('.mask-phone').forEach(maskPhone);
        (scope || document).querySelectorAll('.mask-cep').forEach(maskCep);
    }
    document.addEventListener('DOMContentLoaded', function () { bindMasks(); });
    window.__bindCadastroMasks = bindMasks;
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
