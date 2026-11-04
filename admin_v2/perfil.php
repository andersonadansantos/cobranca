<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$adminId = $_SESSION['admin_id'];
$mensagem = '';
$tipo = '';

$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $nomeFantasia = trim($_POST['nome_fantasia'] ?? '');
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $inscEstadual = trim($_POST['inscricao_estadual'] ?? '');
    $inscMunicipal = trim($_POST['inscricao_municipal'] ?? '');
    $telComercial = trim($_POST['telefone_comercial'] ?? '');
    $emailComercial = trim($_POST['email_comercial'] ?? '');
    $cepEmpresa = preg_replace('/[^0-9]/', '', $_POST['cep_empresa'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    $empObrigatorios = [
        'razao_social' => 'Razão Social',
        'nome_fantasia' => 'Nome Fantasia',
        'cnpj' => 'CNPJ',
        'telefone_comercial' => 'Telefone Comercial',
        'email_comercial' => 'E-mail Comercial',
        'cep_empresa' => 'CEP',
        'logradouro' => 'Logradouro',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'estado' => 'UF',
    ];
    $empDados = [
        'razao_social' => $razaoSocial, 'nome_fantasia' => $nomeFantasia, 'cnpj' => $cnpj,
        'inscricao_estadual' => $inscEstadual, 'inscricao_municipal' => $inscMunicipal,
        'telefone_comercial' => $telComercial, 'email_comercial' => $emailComercial,
        'cep_empresa' => $cepEmpresa, 'logradouro' => $logradouro, 'numero' => $numero,
        'bairro' => $bairro, 'cidade' => $cidade, 'estado' => $estado,
    ];
    $empFaltando = [];
    foreach ($empObrigatorios as $chave => $rotulo) {
        if (empty($empDados[$chave])) $empFaltando[] = $rotulo;
    }

    try {
        if (!empty($empFaltando)) {
            $mensagem = 'Preencha os dados obrigatórios da empresa: ' . implode(', ', $empFaltando) . '.';
            $tipo = 'danger';
        } else {
            if (!empty($novaSenha)) {
                if (empty($nome) || empty($email) || empty($usuario)) {
                    $mensagem = 'Nome, e-mail e usuário são obrigatórios.';
                    $tipo = 'danger';
                } elseif (strlen($novaSenha) < 6) {
                    $mensagem = 'A nova senha deve ter no mínimo 6 caracteres.';
                    $tipo = 'danger';
                } elseif ($novaSenha !== $confirmaSenha) {
                    $mensagem = 'As senhas informadas não conferem.';
                    $tipo = 'danger';
                } elseif (empty($senhaAtual)) {
                    $mensagem = 'Informe a senha atual para autorizar a alteração de senha.';
                    $tipo = 'danger';
                } elseif (!password_verify($senhaAtual, $admin['senha']) && !(strlen($admin['senha']) === 32 && md5($senhaAtual) === $admin['senha'])) {
                    $mensagem = 'Senha atual informada está incorreta.';
                    $tipo = 'danger';
                } else {
                    $stmt = $pdo->prepare("UPDATE administradores SET nome=?, email=?, usuario=?, senha=? WHERE id=?");
                    $stmt->execute([$nome, $email, $usuario, password_hash($novaSenha, PASSWORD_BCRYPT), $adminId]);
                    $_SESSION['admin_nome'] = $nome;
                    $_SESSION['admin_usuario'] = $usuario;
                    $mensagem = 'Perfil e senha atualizados com sucesso!';
                    $tipo = 'success';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE administradores SET nome=?, email=?, usuario=? WHERE id=?");
                $stmt->execute([$nome, $email, $usuario, $adminId]);
                $_SESSION['admin_nome'] = $nome;
                $_SESSION['admin_usuario'] = $usuario;
                $mensagem = 'Perfil atualizado com sucesso!';
                $tipo = 'success';
            }

            if ($tipo === 'success') {
                $stmt = $pdo->prepare("UPDATE administradores SET razao_social=?, nome_fantasia=?, cnpj=?, inscricao_estadual=?, inscricao_municipal=?, telefone_comercial=?, email_comercial=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id=?");
                $stmt->execute([$razaoSocial, $nomeFantasia, $cnpj, $inscEstadual, $inscMunicipal, $telComercial, $emailComercial, $cepEmpresa, $logradouro, $numero, $complemento, $bairro, $cidade, $estado, $adminId]);
            }
        }

        $avatarPath = $admin['avatar'] ?? null;
        $avisoFoto = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $res = salvarUploadSeguro($_FILES['avatar'], 'avatars', 'admin_' . (int)$adminId, ['jpg','jpeg','png','gif','webp']);
            if ($res['sucesso']) {
                $avatarPath = $res['url'];
                $stmt = $pdo->prepare("UPDATE administradores SET avatar=? WHERE id=?");
                $stmt->execute([$avatarPath, $adminId]);
                $_SESSION['admin_avatar'] = $avatarPath;
            } else {
                $avisoFoto = $res['erro'];
            }
        }
        if ($avisoFoto !== '') {
            $mensagem .= ($mensagem !== '' ? ' ' : '') . $avisoFoto;
            if ($tipo !== 'danger') $tipo = 'warning';
        }

        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

    } catch (PDOException $e) {
        $mensagem = 'Erro ao atualizar dados: ' . $e->getMessage();
        $tipo = 'danger';
    }
}

$avatarSrc = function_exists('getAdminAvatarUrl') ? getAdminAvatarUrl($admin['avatar'] ?? null) : ($admin['avatar'] ?: '/cobranca/assets/img/avatars/admin.svg');

$pageTitle = 'Meu Perfil';
$pageCategory = 'Configurações de Conta';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Meu Perfil & Dados da Empresa</h2>
            <p class="text-muted small mb-0">Gerencie informações do titular, credenciais de login e dados fiscais do emitente.</p>
        </div>
        <div>
            <span class="fintech-badge success px-3 py-2"><span class="dot"></span> Conta Ativa</span>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : ($tipo === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <div><?= htmlspecialchars($mensagem) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Coluna da Esquerda: Avatar e Acesso -->
            <div class="col-lg-4">
                <div class="fintech-card p-4 mb-4 text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width:100px;height:100px;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 12px rgba(15,23,42,0.1) !important;" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/admin.svg';">
                    </div>
                    <h5 class="fw-bold text-slate-900 mb-0"><?= htmlspecialchars($admin['nome']) ?></h5>
                    <small class="text-muted font-monospace d-block mb-3"><?= htmlspecialchars($admin['email']) ?></small>

                    <div class="mb-3 text-start">
                        <label class="form-label text-muted small fw-semibold">Trocar Foto de Perfil</label>
                        <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                    </div>
                </div>

                <div class="fintech-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <h3 class="fintech-card-title mb-0">Segurança & Login</h3>
                        <i class="bi bi-shield-lock text-primary fs-5"></i>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nome Completo *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($admin['nome']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">E-mail *</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Usuário de Acesso *</label>
                        <input type="text" name="usuario" class="form-control font-monospace" value="<?= htmlspecialchars($admin['usuario']) ?>" required>
                    </div>

                    <hr class="my-3">

                    <p class="small text-muted mb-2">Para alterar sua senha, preencha os campos abaixo:</p>
                    <div class="mb-2">
                        <label class="form-label text-muted small">Senha Atual</label>
                        <input type="password" name="senha_atual" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-muted small">Nova Senha</label>
                        <input type="password" name="nova_senha" class="form-control" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-muted small">Confirmar Nova Senha</label>
                        <input type="password" name="confirma_senha" class="form-control" placeholder="Repita a nova senha">
                    </div>
                </div>
            </div>

            <!-- Coluna da Direita: Dados Fiscais da Empresa -->
            <div class="col-lg-8">
                <div class="fintech-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <h3 class="fintech-card-title mb-0">Dados da Empresa / Emitente</h3>
                            <small class="text-muted">Utilizados no cabeçalho das faturas, boletos e recibos</small>
                        </div>
                        <i class="bi bi-building text-primary fs-5"></i>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Razão Social *</label>
                            <input type="text" name="razao_social" class="form-control" value="<?= htmlspecialchars($admin['razao_social'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Nome Fantasia *</label>
                            <input type="text" name="nome_fantasia" class="form-control" value="<?= htmlspecialchars($admin['nome_fantasia'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">CNPJ *</label>
                            <input type="text" name="cnpj" class="form-control font-monospace" maxlength="18" value="<?= htmlspecialchars($admin['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Inscrição Estadual</label>
                            <input type="text" name="inscricao_estadual" class="form-control" value="<?= htmlspecialchars($admin['inscricao_estadual'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Inscrição Municipal</label>
                            <input type="text" name="inscricao_municipal" class="form-control" value="<?= htmlspecialchars($admin['inscricao_municipal'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Telefone / WhatsApp Comercial *</label>
                            <input type="text" name="telefone_comercial" class="form-control" value="<?= htmlspecialchars($admin['telefone_comercial'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">E-mail Comercial *</label>
                            <input type="email" name="email_comercial" class="form-control" value="<?= htmlspecialchars($admin['email_comercial'] ?? '') ?>" required>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-12">
                            <h6 class="fw-bold mb-2 text-slate-900"><i class="bi bi-geo-alt me-1 text-primary"></i>Endereço Corporativo</h6>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">CEP *</label>
                            <input type="text" name="cep_empresa" id="cepEmpresa" class="form-control font-monospace" maxlength="9" value="<?= htmlspecialchars($admin['cep'] ?? '') ?>" placeholder="00000-000" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label text-muted small fw-semibold">Logradouro / Rua *</label>
                            <input type="text" name="logradouro" id="logradouroEmpresa" class="form-control" value="<?= htmlspecialchars($admin['logradouro'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small fw-semibold">Nº *</label>
                            <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($admin['numero'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Complemento</label>
                            <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($admin['complemento'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Bairro *</label>
                            <input type="text" name="bairro" id="bairroEmpresa" class="form-control" value="<?= htmlspecialchars($admin['bairro'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">Cidade *</label>
                            <input type="text" name="cidade" id="cidadeEmpresa" class="form-control" value="<?= htmlspecialchars($admin['cidade'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label text-muted small fw-semibold">UF *</label>
                            <input type="text" name="estado" id="estadoEmpresa" class="form-control" maxlength="2" value="<?= htmlspecialchars($admin['estado'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-fintech-primary px-5 py-2">
                            <i class="bi bi-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Auto-preenchimento CEP via ViaCEP
const cepInput = document.getElementById('cepEmpresa');
if (cepInput) {
    cepInput.addEventListener('blur', function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(r => r.json())
                .then(d => {
                    if (!d.erro) {
                        document.getElementById('logradouroEmpresa').value = d.logradouro || '';
                        document.getElementById('bairroEmpresa').value = d.bairro || '';
                        document.getElementById('cidadeEmpresa').value = d.localidade || '';
                        document.getElementById('estadoEmpresa').value = d.uf || '';
                    }
                })
                .catch(() => {});
        }
    });
}
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

