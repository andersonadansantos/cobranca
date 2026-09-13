<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/valor_extenso.php';
require_once __DIR__ . '/../config/recibo_template.php';

if (!function_exists('mascaraCpfCnpj')) {
    function mascaraCpfCnpj($valor) {
        $v = preg_replace('/[^0-9]/', '', (string) $valor);
        if (strlen($v) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
        if (strlen($v) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
        return $valor;
    }
}

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];
$mensagem = '';
$tipo = '';

$faturaId = (int)($_GET['fatura_id'] ?? $_POST['fatura_id'] ?? 0);
$fatura = null;
$cliente = null;

if ($faturaId > 0) {
    $stmt = $pdo->prepare("SELECT f.*, c.nome_razao, c.cpf_cnpj, c.email, c.telefone, c.celular,
        c.cep, c.logradouro, c.numero as cliente_numero, c.complemento, c.bairro, c.cidade, c.estado
        FROM faturas f JOIN clientes c ON f.cliente_id = c.id
        WHERE f.id = ? AND f.admin_id = ? AND f.status = 'pago'");
    $stmt->execute([$faturaId, $adminId]);
    $fatura = $stmt->fetch();
    if ($fatura) {
        $cliente = $fatura;
    }
}

$admin = null;
$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

$config = getAllConfig();
$templateHtml = getTemplateReciboHtml();
$exibirAssinatura = ($config['template_recibo_assinatura'] ?? '1') === '1';
$descPadrao = $config['template_recibo_descricao_padrao'] ?? 'referente à prestação de serviços conforme acordado entre as partes.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'emitir' && $fatura && $admin) {
    $descricaoServico = trim($_POST['descricao_servico'] ?? $descPadrao);
    $cidadeEmissao = trim($_POST['cidade_emissao'] ?? ($config['template_recibo_cidade_emissao'] ?? ''));
    $dataEmissao = trim($_POST['data_emissao'] ?? date('Y-m-d'));
    if (empty($cidadeEmissao)) $cidadeEmissao = $admin['cidade'] ?? 'São Paulo';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recibos WHERE admin_id = ? AND fatura_id = ?");
    $stmt->execute([$adminId, $faturaId]);
    if ($stmt->fetchColumn() > 0) {
        $mensagem = 'Já existe um recibo emitido para esta fatura.';
        $tipo = 'warning';
    } else {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numero, 11) AS UNSIGNED)) FROM recibos WHERE admin_id = ?");
        $stmt->execute([$adminId]);
        $proximo = ((int)$stmt->fetchColumn()) + 1;
        $reciboNumero = 'REC-' . date('Y') . '-' . str_pad($proximo, 6, '0', STR_PAD_LEFT);

        $valorExt = valorPorExtenso((float)$fatura['valor_final']);
        $dataEmissaoFmt = date('d/m/Y', strtotime($dataEmissao));

        $empresaLogo = getConfig('logo_empresa_admin', '');
        if (empty($empresaLogo) || !logoPathValido($empresaLogo)) {
            $empresaLogo = getLogoLogin();
        }
        if (empty($empresaLogo) || !logoPathValido($empresaLogo)) {
            $empresaLogo = '/cobranca/assets/img/logo_color.png';
        }
        $logoAbs = $empresaLogo;
        if (strpos($empresaLogo, 'http') !== 0 && strpos($empresaLogo, 'data:') !== 0) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = dirname($_SERVER['SCRIPT_NAME']);
            $base = preg_replace('#/cobranca/admin$#', '', $base);
            $logoAbs = $proto . '://' . $host . $base . $empresaLogo;
        }

        $enderecoEmpresa = trim(($admin['logradouro'] ?? '') . ', ' . ($admin['numero'] ?? '') . ' ' . ($admin['complemento'] ?? ''));
        $enderecoCliente = trim(($cliente['logradouro'] ?? '') . ', ' . ($cliente['cliente_numero'] ?? '') . ' ' . ($cliente['complemento'] ?? ''));

        $assinaturaBloco = '';
        if ($exibirAssinatura) {
            $assinaturaBloco = '<div style="text-align:center;margin-top:40px;">
                <div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div>
                <div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($admin['nome_fantasia'] ?: $admin['nome']) . '</div>
                <div style="font-size:11px;color:#555;">CNPJ: ' . htmlspecialchars($admin['cnpj'] ?? '') . '</div>
            </div>';
        }

        $vars = [
            '{{empresa_logo}}'    => '<img src="' . htmlspecialchars($logoAbs) . '" style="max-width:250px;">',
            '{{empresa_nome}}'    => htmlspecialchars($admin['nome_fantasia'] ?: $admin['nome']),
            '{{empresa_cnpj}}'    => htmlspecialchars($admin['cnpj'] ?? ''),
            '{{empresa_inscricao_municipal}}' => htmlspecialchars($admin['inscricao_municipal'] ?? ''),
            '{{empresa_endereco}}' => htmlspecialchars($enderecoEmpresa),
            '{{empresa_cidade}}'  => htmlspecialchars($admin['cidade'] ?? ''),
            '{{empresa_estado}}'  => htmlspecialchars($admin['estado'] ?? ''),
            '{{empresa_cep}}'     => htmlspecialchars($admin['cep'] ?? ''),
            '{{empresa_telefone}}' => htmlspecialchars($admin['telefone_comercial'] ?? ''),
            '{{empresa_email}}'   => htmlspecialchars($admin['email_comercial'] ?? $admin['email']),
            '{{cliente_nome}}'    => htmlspecialchars($cliente['nome_razao']),
            '{{cliente_cnpj_cpf}}' => htmlspecialchars(mascaraCpfCnpj($cliente['cpf_cnpj'])),
            '{{cliente_endereco}}' => htmlspecialchars($enderecoCliente),
            '{{cliente_cidade}}'  => htmlspecialchars($cliente['cidade'] ?? ''),
            '{{cliente_estado}}'  => htmlspecialchars($cliente['estado'] ?? ''),
            '{{cliente_cep}}'     => htmlspecialchars($cliente['cep'] ?? ''),
            '{{cliente_telefone}}' => htmlspecialchars($cliente['telefone'] ?? $cliente['celular'] ?? ''),
            '{{cliente_email}}'   => htmlspecialchars($cliente['email']),
            '{{fatura_numero}}'   => htmlspecialchars($fatura['numero']),
            '{{recibo_numero}}'   => htmlspecialchars($reciboNumero),
            '{{valor_recebido}}'  => 'R$ ' . number_format((float)$fatura['valor_final'], 2, ',', '.'),
            '{{valor_extenso}}'   => htmlspecialchars($valorExt),
            '{{descricao_servico}}' => htmlspecialchars($descricaoServico),
            '{{data_emissao}}'    => $dataEmissaoFmt,
            '{{cidade_emissao}}'  => htmlspecialchars($cidadeEmissao),
            '{{assinatura_bloco}}' => $assinaturaBloco,
        ];

        $htmlGerado = str_replace(array_keys($vars), array_values($vars), $templateHtml);

        $stmt = $pdo->prepare("INSERT INTO recibos (admin_id, cliente_id, fatura_id, numero, descricao_servico, cidade_emissao, data_emissao, valor_recebido, valor_extenso, html_gerado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $adminId, $fatura['cliente_id'], $faturaId,
            $reciboNumero, $descricaoServico, $cidadeEmissao, $dataEmissao,
            $fatura['valor_final'], $valorExt, $htmlGerado
        ]);

        $mensagem = 'Recibo ' . $reciboNumero . ' emitido com sucesso!';
        $tipo = 'success';
    }
}

$reciboHtml = null;
$reciboNumeroFmt = '';
if ($mensagem && $tipo === 'success' && $faturaId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM recibos WHERE admin_id = ? AND fatura_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$adminId, $faturaId]);
    $recibo = $stmt->fetch();
    if ($recibo) {
        $reciboHtml = $recibo['html_gerado'];
        $reciboNumeroFmt = $recibo['numero'];
    }
}

$faturasPagas = [];
if (!$fatura) {
    $stmt = $pdo->prepare("SELECT f.id, f.numero, f.valor_final, f.data_pagamento, c.nome_razao
        FROM faturas f JOIN clientes c ON f.cliente_id = c.id
        WHERE f.admin_id = ? AND f.status = 'pago' ORDER BY f.data_pagamento DESC");
    $stmt->execute([$adminId]);
    $faturasPagas = $stmt->fetchAll();
}

$empresaLogoPreview = getLogoLogin();
if (empty($empresaLogoPreview) || !logoPathValido($empresaLogoPreview)) {
    $empresaLogoPreview = '/cobranca/assets/img/logo_color.png';
}

$pageTitle = 'Emitir Recibo';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice me-2"></i>Emitir Recibo</h5>
        </div>
        <a href="/cobranca/admin/recibos.php" class="btn btn-outline-secondary btn-sm ms-auto me-2"><i class="fas fa-list me-1"></i> Histórico</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
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

        <?php if ($reciboHtml): ?>
            <div class="form-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Recibo <?= htmlspecialchars($reciboNumeroFmt) ?> emitido!</h6>
                    <div class="d-flex gap-2">
                        <a href="/cobranca/admin/recibos.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i> Ver Histórico</a>
                        <a href="/cobranca/admin/emitir_recibo.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Emitir Outro</a>
                        <button type="button" class="btn btn-success btn-sm" onclick="imprimirRecibo()"><i class="fas fa-print me-1"></i> Imprimir / PDF</button>
                    </div>
                </div>
                <div id="reciboPreview" style="border:1px solid #dee2e6;border-radius:8px;overflow:auto;background:#e9ecef;padding:20px;max-height:700px;">
                    <div style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;" id="reciboA4">
                    </div>
                </div>
            </div>
        <?php elseif (!$fatura): ?>
            <div class="form-card mb-4">
                <h6 class="mb-3"><i class="fas fa-search me-2"></i>Selecionar Fatura Paga</h6>
                <?php if (empty($faturasPagas)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                        <p>Nenhuma fatura paga encontrada. Emita recibos para faturas com status <strong>Pago</strong>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fatura</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Pago em</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faturasPagas as $fp): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($fp['numero']) ?></strong></td>
                                    <td><?= htmlspecialchars($fp['nome_razao']) ?></td>
                                    <td>R$ <?= number_format((float)$fp['valor_final'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($fp['data_pagamento'])) ?></td>
                                    <td>
                                        <a href="?fatura_id=<?= $fp['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-text me-1"></i> Emitir Recibo
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="POST" id="formEmitir">
                <input type="hidden" name="acao" value="emitir">
                <input type="hidden" name="fatura_id" value="<?= $fatura['id'] ?>">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="form-card mb-3">
                            <h6 class="mb-3"><i class="fas fa-file-invoice-dollar me-2"></i>Fatura <?= htmlspecialchars($fatura['numero']) ?></h6>
                            <table class="table table-sm mb-0" style="font-size:0.85rem;">
                                <tr><td class="text-muted">Cliente</td><td><strong><?= htmlspecialchars($fatura['nome_razao']) ?></strong></td></tr>
                                <tr><td class="text-muted">CPF/CNPJ</td><td><?= htmlspecialchars(mascaraCpfCnpj($fatura['cpf_cnpj'])) ?></td></tr>
                                <tr><td class="text-muted">Descrição</td><td><?= htmlspecialchars($fatura['descricao']) ?></td></tr>
                                <tr><td class="text-muted">Valor Pago</td><td><strong class="text-success">R$ <?= number_format((float)$fatura['valor_final'], 2, ',', '.') ?></strong></td></tr>
                                <tr><td class="text-muted">Data Pagamento</td><td><?= date('d/m/Y', strtotime($fatura['data_pagamento'])) ?></td></tr>
                            </table>
                        </div>

                        <div class="form-card mb-3">
                            <h6 class="mb-3"><i class="fas fa-edit me-2"></i>Dados do Recibo</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Descrição do Serviço</label>
                                    <textarea name="descricao_servico" class="form-control" rows="3"><?= htmlspecialchars($descPadrao) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cidade de Emissão</label>
                                    <input type="text" name="cidade_emissao" class="form-control" value="<?= htmlspecialchars($admin['cidade'] ?? 'São Paulo') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Data de Emissão</label>
                                    <input type="date" name="data_emissao" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Confirma a emissão deste recibo?')">
                                <i class="fas fa-check me-1"></i> Emitir Recibo
                            </button>
                            <a href="?fatura_id=" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="form-card" style="position:sticky;top:20px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="fas fa-eye me-2"></i>Pré-visualização</h6>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="atualizarPreviewEmitir()">
                                    <i class="fas fa-sync me-1"></i> Atualizar
                                </button>
                            </div>
                            <div id="previewWrapperEmitir" style="border:1px solid #dee2e6;border-radius:8px;overflow:auto;background:#e9ecef;padding:20px;max-height:700px;">
                                <div id="previewA4Emitir" style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
var templateHtml = <?= json_encode($templateHtml) ?>;
var empresaData = <?= json_encode([
    'logo' => $empresaLogoPreview,
    'nome' => htmlspecialchars($admin['nome_fantasia'] ?: $admin['nome']),
    'cnpj' => htmlspecialchars($admin['cnpj'] ?? ''),
    'insc_mun' => htmlspecialchars($admin['inscricao_municipal'] ?? ''),
    'endereco' => htmlspecialchars(trim(($admin['logradouro'] ?? '') . ', ' . ($admin['numero'] ?? ''))),
    'cidade' => htmlspecialchars($admin['cidade'] ?? ''),
    'estado' => htmlspecialchars($admin['estado'] ?? ''),
    'cep' => htmlspecialchars($admin['cep'] ?? ''),
    'telefone' => htmlspecialchars($admin['telefone_comercial'] ?? ''),
    'email' => htmlspecialchars($admin['email_comercial'] ?? $admin['email'] ?? ''),
]) ?>;
var clienteData = <?= json_encode($fatura ? [
    'nome' => htmlspecialchars($fatura['nome_razao']),
    'cpf_cnpj' => htmlspecialchars(mascaraCpfCnpj($fatura['cpf_cnpj'])),
    'endereco' => htmlspecialchars(trim(($fatura['logradouro'] ?? '') . ', ' . ($fatura['cliente_numero'] ?? ''))),
    'cidade' => htmlspecialchars($fatura['cidade'] ?? ''),
    'estado' => htmlspecialchars($fatura['estado'] ?? ''),
    'cep' => htmlspecialchars($fatura['cep'] ?? ''),
    'telefone' => htmlspecialchars($fatura['telefone'] ?? $fatura['celular'] ?? ''),
    'email' => htmlspecialchars($fatura['email'] ?? ''),
    'fatura_numero' => htmlspecialchars($fatura['numero']),
    'valor_final' => number_format((float)$fatura['valor_final'], 2, ',', '.'),
    'data_pagamento' => date('d/m/Y', strtotime($fatura['data_pagamento'])),
] : []) ?>;
var exibirAssinatura = <?= json_encode($exibirAssinatura) ?>;

function buildReciboPreview(descServico, cidade, dataEmissao, containerId) {
    var vars = {};
    var logoSrc = empresaData.logo;
    if (logoSrc.indexOf('http') !== 0 && logoSrc.indexOf('data:') !== 0) {
        logoSrc = location.protocol + '//' + location.hostname + logoSrc;
    }
    vars['{{empresa_logo}}'] = '<img src="' + logoSrc + '" style="max-width:250px;">';
    vars['{{empresa_nome}}'] = empresaData.nome;
    vars['{{empresa_cnpj}}'] = empresaData.cnpj;
    vars['{{empresa_inscricao_municipal}}'] = empresaData.insc_mun;
    vars['{{empresa_endereco}}'] = empresaData.endereco;
    vars['{{empresa_cidade}}'] = empresaData.cidade;
    vars['{{empresa_estado}}'] = empresaData.estado;
    vars['{{empresa_cep}}'] = empresaData.cep;
    vars['{{empresa_telefone}}'] = empresaData.telefone;
    vars['{{empresa_email}}'] = empresaData.email;
    vars['{{cliente_nome}}'] = clienteData.nome || '';
    vars['{{cliente_cnpj_cpf}}'] = clienteData.cpf_cnpj || '';
    vars['{{cliente_endereco}}'] = clienteData.endereco || '';
    vars['{{cliente_cidade}}'] = clienteData.cidade || '';
    vars['{{cliente_estado}}'] = clienteData.estado || '';
    vars['{{cliente_cep}}'] = clienteData.cep || '';
    vars['{{cliente_telefone}}'] = clienteData.telefone || '';
    vars['{{cliente_email}}'] = clienteData.email || '';
    vars['{{fatura_numero}}'] = clienteData.fatura_numero || '';
    vars['{{recibo_numero}}'] = 'REC-XXXX-XXXXXX';
    vars['{{valor_recebido}}'] = 'R$ ' + (clienteData.valor_final || '0,00');
    vars['{{valor_extenso}}'] = '(valor por extenso)';
    vars['{{descricao_servico}}'] = descServico || '';
    vars['{{data_emissao}}'] = dataEmissao || '';
    vars['{{cidade_emissao}}'] = cidade || '';
    var assinatura = exibirAssinatura ? '<div style="text-align:center;margin-top:40px;"><div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div><div style="font-size:12px;font-weight:bold;">' + empresaData.nome + '</div></div>' : '';
    vars['{{assinatura_bloco}}'] = assinatura;
    var html = templateHtml;
    for (var key in vars) {
        html = html.split(key).join(vars[key]);
    }
    document.getElementById(containerId).innerHTML = html;
}

function atualizarPreviewEmitir() {
    var desc = document.querySelector('[name=descricao_servico]').value;
    var cid = document.querySelector('[name=cidade_emissao]').value;
    var dt = document.querySelector('[name=data_emissao]').value;
    if (dt) {
        var parts = dt.split('-');
        dt = parts[2] + '/' + parts[1] + '/' + parts[0];
    }
    buildReciboPreview(desc, cid, dt, 'previewA4Emitir');
}

function imprimirRecibo() {
    var content = document.getElementById('reciboA4').innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>Recibo</title>');
    win.document.write('<link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">');
    win.document.write('<style>@page{size:A4;margin:15mm;}body{margin:0;padding:0;font-family:"Inter","Segoe UI",Arial,sans-serif;}@media print{.no-print{display:none!important;}}</style>');
    win.document.write('</head><body>');
    win.document.write(content);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(function() { win.print(); }, 500);
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($reciboHtml): ?>
    document.getElementById('reciboA4').innerHTML = <?= json_encode($reciboHtml) ?>;
    <?php elseif ($fatura && !$reciboHtml): ?>
    atualizarPreviewEmitir();
    <?php endif; ?>

    <?php if ($fatura && !$reciboHtml): ?>
    var form = document.getElementById('formEmitir');
    if (form) {
        form.addEventListener('input', function() { atualizarPreviewEmitir(); });
    }
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
