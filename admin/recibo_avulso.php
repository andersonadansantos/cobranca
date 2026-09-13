<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/recibo_template.php';
require_once __DIR__ . '/../config/valor_extenso.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];
$config = getAllConfig();

$mensagem = '';
$tipo = '';

$stAdmin = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stAdmin->execute([$adminId]);
$admin = $stAdmin->fetch() ?: [];

if (!function_exists('reciboAvulsoDoc')) {
    function reciboAvulsoDoc($v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if (strlen($v) === 11) {
            return substr($v,0,3) . '.' . substr($v,3,3) . '.' . substr($v,6,3) . '-' . substr($v,9,2);
        }
        if (strlen($v) === 14) {
            return substr($v,0,2) . '.' . substr($v,2,3) . '.' . substr($v,5,3) . '/' . substr($v,8,4) . '-' . substr($v,12,2);
        }
        return $v;
    }
}
if (!function_exists('reciboAvulsoCep')) {
    function reciboAvulsoCep($v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if (strlen($v) === 8) {
            return substr($v,0,5) . '-' . substr($v,5,3);
        }
        return $v;
    }
}

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

$emitNome = trim($admin['nome_fantasia'] ?? '') ?: (trim($admin['razao_social'] ?? '') ?: getNomeSistema());
$emitCnpj = reciboAvulsoDoc($admin['cnpj'] ?? '');
$emitInscMun = trim($admin['inscricao_municipal'] ?? '');
$emitEnd = trim((trim($admin['logradouro'] ?? '') . ', ' . trim($admin['numero'] ?? '')), " ,");
$emitCidade = trim($admin['cidade'] ?? '');
$emitEstado = trim($admin['estado'] ?? '');
$emitCep = reciboAvulsoCep($admin['cep'] ?? '');
$emitTel = trim($admin['telefone_comercial'] ?? '');
$emitEmail = trim($admin['email_comercial'] ?? '') ?: trim($admin['email'] ?? '');

$exibirAssinatura = ($config['template_recibo_assinatura'] ?? '1') === '1';
$cidadeEmissaoPadrao = trim($config['template_recibo_cidade_emissao'] ?? '') ?: $emitCidade;
$descPadrao = trim($config['template_recibo_descricao_padrao'] ?? '') ?: 'referente à prestação de serviços conforme acordado entre as partes.';

$reciboNumeroGerado = '';
$reciboHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar') {
    $clNome = htmlspecialchars(trim($_POST['cliente_nome'] ?? ''));
    $clDoc = htmlspecialchars(reciboAvulsoDoc($_POST['cliente_doc'] ?? ''));
    $clEnd = trim((trim($_POST['cliente_logradouro'] ?? '') . ', ' . trim($_POST['cliente_numero'] ?? '') . ' ' . trim($_POST['cliente_complemento'] ?? '')), " ,");
    $clEnd = htmlspecialchars($clEnd);
    $clCidade = htmlspecialchars(trim($_POST['cliente_cidade'] ?? ''));
    $clEstado = htmlspecialchars(trim($_POST['cliente_estado'] ?? ''));
    $clCep = htmlspecialchars(reciboAvulsoCep($_POST['cliente_cep'] ?? ''));
    $clTel = htmlspecialchars(trim($_POST['cliente_telefone'] ?? ''));
    $clEmail = htmlspecialchars(trim($_POST['cliente_email'] ?? ''));
    $descServico = htmlspecialchars(trim($_POST['descricao_servico'] ?? '') ?: $descPadrao);

    $dataEmissao = trim($_POST['data_emissao'] ?? '');
    $dataEmissaoFmt = $dataEmissao !== '' ? date('d/m/Y', strtotime($dataEmissao)) : date('d/m/Y');
    $cidadeEmissao = htmlspecialchars(trim($_POST['cidade_emissao'] ?? '') ?: $cidadeEmissaoPadrao);

    $valorBruto = trim($_POST['valor'] ?? '0');
    $valorBruto = str_replace('.', '', $valorBruto);
    $valorBruto = str_replace(',', '.', $valorBruto);
    $valorRec = max(0, (float)$valorBruto);
    $valorFmt = 'R$ ' . number_format($valorRec, 2, ',', '.');
    $valorExtenso = valorPorExtenso($valorRec);

    $seq = (int)getConfig('recibo_avulso_seq', 0) + 1;
    saveConfig('recibo_avulso_seq', (string)$seq);
    $reciboNumero = 'AVU-' . date('Y') . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);

    $assinaturaBloco = '';
    if ($exibirAssinatura) {
        $assinaturaBloco = '<div style="text-align:center;margin-top:40px;">
            <div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div>
            <div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($emitNome) . '</div>
        </div>';
    }

    $vars = [
        '{{empresa_logo}}' => '<img src="' . htmlspecialchars($logoAbs) . '" style="max-width:250px;">',
        '{{empresa_nome}}' => htmlspecialchars($emitNome),
        '{{empresa_cnpj}}' => htmlspecialchars($emitCnpj),
        '{{empresa_inscricao_municipal}}' => htmlspecialchars($emitInscMun),
        '{{empresa_endereco}}' => htmlspecialchars($emitEnd),
        '{{empresa_cidade}}' => htmlspecialchars($emitCidade),
        '{{empresa_estado}}' => htmlspecialchars($emitEstado),
        '{{empresa_cep}}' => htmlspecialchars($emitCep),
        '{{empresa_telefone}}' => htmlspecialchars($emitTel),
        '{{empresa_email}}' => htmlspecialchars($emitEmail),
        '{{cliente_nome}}' => $clNome,
        '{{cliente_cnpj_cpf}}' => $clDoc,
        '{{cliente_endereco}}' => $clEnd,
        '{{cliente_cidade}}' => $clCidade,
        '{{cliente_estado}}' => $clEstado,
        '{{cliente_cep}}' => $clCep,
        '{{cliente_telefone}}' => $clTel,
        '{{cliente_email}}' => $clEmail,
        '{{fatura_numero}}' => '&#8212;',
        '{{recibo_numero}}' => htmlspecialchars($reciboNumero),
        '{{valor_recebido}}' => $valorFmt,
        '{{valor_extenso}}' => $valorExtenso,
        '{{descricao_servico}}' => $descServico,
        '{{data_emissao}}' => $dataEmissaoFmt,
        '{{cidade_emissao}}' => $cidadeEmissao,
        '{{assinatura_bloco}}' => $assinaturaBloco,
    ];

    $reciboHtml = strtr(getTemplateReciboHtml(), $vars);
    $reciboNumeroGerado = $reciboNumero;
    $mensagem = 'Recibo ' . $reciboNumero . ' gerado com sucesso!';
    $tipo = 'success';
}

$pageTitle = 'Recibo Avulso';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice me-2"></i>Recibo Avulso</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
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

        <form method="POST">
            <input type="hidden" name="acao" value="gerar">
            <div class="row g-4">

                <div class="col-lg-6">
                    <div class="form-card h-100">
                        <h6 class="mb-3"><i class="fas fa-building me-2"></i>Emitente <small class="text-muted" style="font-weight:400;font-size:0.75rem;">(padrão - Meu Perfil / Dados da Empresa)</small></h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitNome) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CNPJ</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitCnpj) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Inscrição Municipal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitInscMun) ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitEnd) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitCidade) ?>" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">UF</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitEstado) ?>" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">CEP</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitCep) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitTel) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitEmail) ?>" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="form-card mb-3">
                        <h6 class="mb-3"><i class="fas fa-user me-2"></i>Tomador <small class="text-muted" style="font-weight:400;font-size:0.75rem;">(dados do cliente do recibo)</small></h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome / Razão Social</label>
                                <input type="text" name="cliente_nome" class="form-control" value="<?= htmlspecialchars($_POST['cliente_nome'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CPF / CNPJ</label>
                                <input type="text" name="cliente_doc" class="form-control" maxlength="18" value="<?= htmlspecialchars($_POST['cliente_doc'] ?? '') ?>" placeholder="000.000.000-00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="cliente_logradouro" class="form-control" value="<?= htmlspecialchars($_POST['cliente_logradouro'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nº</label>
                                <input type="text" name="cliente_numero" class="form-control" value="<?= htmlspecialchars($_POST['cliente_numero'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="cliente_complemento" class="form-control" value="<?= htmlspecialchars($_POST['cliente_complemento'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cliente_cidade" class="form-control" value="<?= htmlspecialchars($_POST['cliente_cidade'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">UF</label>
                                <input type="text" name="cliente_estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($_POST['cliente_estado'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cliente_cep" class="form-control" maxlength="9" value="<?= htmlspecialchars($_POST['cliente_cep'] ?? '') ?>" placeholder="00000-000">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="cliente_telefone" class="form-control" value="<?= htmlspecialchars($_POST['cliente_telefone'] ?? '') ?>">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">E-mail</label>
                                <input type="text" name="cliente_email" class="form-control" value="<?= htmlspecialchars($_POST['cliente_email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-wrench me-2"></i>Serviço Prestado</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Descrição do Serviço</label>
                                <textarea name="descricao_servico" class="form-control" rows="3" placeholder="Descreva o serviço prestado..."><?= htmlspecialchars($_POST['descricao_servico'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Valor do Serviço (R$)</label>
                                <input type="text" name="valor" class="form-control text-end" inputmode="decimal" oninput="mascaraMoedaAvulso(this)" value="<?= htmlspecialchars($_POST['valor'] ?? '') ?>" placeholder="0,00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data de Emissão</label>
                                <input type="date" name="data_emissao" class="form-control" value="<?= htmlspecialchars($_POST['data_emissao'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cidade de Emissão</label>
                                <input type="text" name="cidade_emissao" class="form-control" value="<?= htmlspecialchars($_POST['cidade_emissao'] ?? $cidadeEmissaoPadrao) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 text-center mb-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-file-earmark-text me-2"></i> Gerar Recibo
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalReciboAvulso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice text-success me-2"></i>Recibo <?= htmlspecialchars($reciboNumeroGerado) ?> gerado!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div style="border:1px solid #dee2e6;border-radius:8px;overflow:auto;background:#e9ecef;padding:20px;max-height:70vh;">
                    <div id="reciboAvulsoA4" style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;">
                    </div>
                </div>
                <div style="text-align:center;margin-top:10px;font-size:0.75rem;color:#64748b;">
                    Para salvar o PDF, clique em "Baixar PDF" e escolha "Salvar como PDF".
                </div>
            </div>
            <div class="modal-footer">
                <a href="/cobranca/admin/recibo_avulso.php" class="btn btn-outline-secondary"><i class="fas fa-plus me-1"></i> Novo Recibo</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-close me-1"></i> Fechar</button>
                <button type="button" class="btn btn-success" onclick="imprimirReciboAvulso()"><i class="fas fa-print me-1"></i> Baixar PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
function mascaraMoedaAvulso(el) {
    var d = el.value.replace(/\D/g, '');
    while (d.length < 3) d = '0' + d;
    el.value = (parseInt(d.slice(0, -2), 10) || 0).toLocaleString('pt-BR') + ',' + d.slice(-2);
}

function imprimirReciboAvulso() {
    var content = document.getElementById('reciboAvulsoA4').innerHTML;
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
</script>

<?php if ($reciboHtml): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('reciboAvulsoA4').innerHTML = <?= json_encode($reciboHtml) ?>;
    new bootstrap.Modal(document.getElementById('modalReciboAvulso')).show();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>